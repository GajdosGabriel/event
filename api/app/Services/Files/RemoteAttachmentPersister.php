<?php

namespace App\Services\Files;

use App\Enums\FileType;
use App\Jobs\GenerateFileVariantsJob;
use App\Services\Security\RemoteFetchRefused;
use App\Services\Security\SafeRemoteFetcher;
use finfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RemoteAttachmentPersister
{
    /**
     * Obrázky: MIME zistený z obsahu → prípustné prípony (prvá je kanonická).
     * SVG tu zámerne nie je — je to dokument so skriptami.
     */
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
    ];

    /**
     * Prílohy: prípona → [MIME, ktorý sa uloží, MIME, ktoré finfo pre takýto
     * súbor môže zistiť]. Staré Office formáty sú OLE kontajner, nové OOXML
     * a ODF sú ZIP — finfo ich nie vždy rozlíši presnejšie.
     */
    private const FILE_TYPES = [
        'pdf' => ['application/pdf', ['application/pdf']],
        'doc' => ['application/msword', ['application/msword', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage']],
        'xls' => ['application/vnd.ms-excel', ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage', 'application/msword']],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']],
        'odt' => ['application/vnd.oasis.opendocument.text', ['application/vnd.oasis.opendocument.text', 'application/zip']],
        'txt' => ['text/plain', ['text/plain']],
    ];

    public function __construct(
        private readonly \App\Services\Files\FileDisplayNameResolver $fileDisplayNameResolver = new \App\Services\Files\FileDisplayNameResolver,
        private readonly FileDuplicator $fileDuplicator = new FileDuplicator,
        private readonly SafeRemoteFetcher $fetcher = new SafeRemoteFetcher,
    ) {}

    /**
     * Stiahne a uloží všetky prílohy; prvé zlyhanie zahodí celú dávku.
     *
     * FileManager volá download() a persist() zvlášť, aby sťahovanie nebežalo
     * vnútri DB transakcie a zlyhaná príloha nezhodila ostatné.
     *
     * @param  Collection<int, array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $meta
     */
    public function store(
        Model $model,
        Collection $attachments,
        FileType $type,
        string $disk,
        string $storageDirectory,
        bool $makePrimary,
        array $meta
    ): Collection {
        return $attachments->values()
            ->map(fn (array $attachment) => $this->download($attachment, $type))
            ->map(fn (RemoteAttachmentDownload $download, int $index) => $this->persist(
                $model, $download, $type, $disk, $storageDirectory, $makePrimary && $index === 0, $meta,
            ));
    }

    /**
     * Stiahne a zvaliduje prílohu. Na DB ani disk nesiaha.
     *
     * @param  array<string, mixed>  $attachment
     */
    public function download(array $attachment, FileType $type): RemoteAttachmentDownload
    {
        $url = (string) $attachment['url'];

        // Adresy prichádzajú z článkov a od používateľov, preto cez
        // SafeRemoteFetcher: každé presmerovanie sa kontroluje proti vnútornej
        // sieti a sťahovanie sa preruší nad limitom veľkosti.
        // Wikimedia generický „bot" User-Agent škrtí (429) — volajúci smie
        // poslať vlastný, popisný.
        try {
            $response = $this->fetcher->get(
                $url,
                accept: $this->isImageType($type) ? 'image/webp,image/png,image/*;q=0.8' : '*/*',
                userAgent: (string) ($attachment['user_agent'] ?? 'Mozilla/5.0 (compatible; Event API Bot)'),
                timeout: 60,
                connectTimeout: 15,
                maxBytes: $this->maxBytes($type),
            )['response'];
        } catch (RemoteFetchRefused $e) {
            throw new RemoteAttachmentRejected($e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new InvalidArgumentException('Failed to download remote file: '.$url);
        }

        $content = $response->body();
        if ($content === '') {
            throw new InvalidArgumentException('Remote file is empty: '.$url);
        }

        $originalName = $this->resolveOriginalName($attachment);
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName) ?: ('remote_file_'.time());
        $nameExtension = strtolower((string) pathinfo($safeOriginalName, PATHINFO_EXTENSION));

        // Typ aj prípona sa určia z obsahu — hlavička Content-Type aj text
        // odkazu sú pod kontrolou cudzieho servera.
        [$mimeType, $extension] = $this->validateRemoteContent($type, $nameExtension, $content, $url);

        if ($nameExtension !== $extension) {
            $base = $nameExtension !== '' ? Str::beforeLast($safeOriginalName, '.') : $safeOriginalName;
            $safeOriginalName = ($base !== '' ? $base : 'remote_file_'.time()).'.'.$extension;
        }

        return new RemoteAttachmentDownload(
            attachment: $attachment,
            url: $url,
            content: $content,
            mimeType: $mimeType,
            checksum: hash('sha256', $content),
            originalName: $originalName,
            safeOriginalName: $safeOriginalName,
            extension: $extension,
        );
    }

    /**
     * Zapíše stiahnutú prílohu na disk a do DB — patrí do transakcie.
     *
     * @param  array<string, mixed>  $meta
     */
    public function persist(
        Model $model,
        RemoteAttachmentDownload $download,
        FileType $type,
        string $disk,
        string $storageDirectory,
        bool $makePrimary,
        array $meta
    ): \App\Models\File {
        $attachment = $download->attachment;
        $url = $download->url;
        $content = $download->content;
        $mimeType = $download->mimeType;
        $checksum = $download->checksum;
        $originalName = $download->originalName;
        $safeOriginalName = $download->safeOriginalName;
        $extension = $download->extension;

        $existingFile = $model->files()
            ->where('type', $type->value)
            ->where('checksum', $checksum)
            ->latest('id')
            ->first();

        if ($existingFile instanceof \App\Models\File) {
            if ($makePrimary && ! $existingFile->is_primary) {
                $existingFile->forceFill(['is_primary' => true])->save();
            }

            return $existingFile->fresh();
        }

        // Ten istý plagát visí pri viacerých podujatiach — v produkcii bol
        // jeden PNG stiahnutý jedenásťkrát. Keď už rovnaký obsah niekde
        // máme, netreba ho nahrávať znova ani znova prehnať generovaním
        // variantov: objekty sa prekopírujú na disku aj s hotovým thumb
        // a large. Rozhoduje checksum stiahnutého tela, nie odkaz, takže
        // zámena obrázka na zdroji sa nedá prehliadnuť.
        //
        // Kópia, nie zdieľanie cesty — dôvod drží FileDuplicator.
        $reused = $this->reuseStoredCopy($model, $checksum, $type, $safeOriginalName, $extension, $makePrimary, $meta, $attachment, $url);

        if ($reused !== null) {
            return $reused;
        }

        $storedFileName = Str::random(40).($extension !== '' ? '.'.$extension : '');
        $path = trim($storageDirectory, '/').'/'.$storedFileName;

        $stored = Storage::disk($disk)->put($path, $content);
        if (! $stored) {
            throw new InvalidArgumentException('Failed to store remote file: '.$url);
        }

        $file = $model->files()->create([
            'name' => $this->fileDisplayNameResolver->resolve($model, $originalName),
            'original_name' => $safeOriginalName,
            'extension' => $extension !== '' ? $extension : null,
            'size' => strlen($content),
            'mime_type' => $mimeType !== '' ? $mimeType : null,
            'disk' => $disk,
            'path' => $path,
            'checksum' => $checksum,
            'type' => $type->value,
            'is_primary' => $makePrimary,
            'meta' => array_merge($meta, [
                'source' => 'remote_attachment',
                'source_url' => $url,
                'source_name' => $attachment['name'] ?? null,
                'source_link_text' => $attachment['link_text'] ?? null,
                'source_size' => $attachment['size'] ?? null,
            ]),
        ]);

        GenerateFileVariantsJob::dispatch((int) $file->id);

        return $file;
    }

    /**
     * Prekopíruje už uložený súbor s rovnakým obsahom, ak nejaký existuje.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $attachment
     * @return \App\Models\File|null null, keď sa nedá použiť a treba ukladať normálne
     */
    private function reuseStoredCopy(
        Model $model,
        string $checksum,
        FileType $type,
        string $safeOriginalName,
        string $extension,
        bool $makePrimary,
        array $meta,
        array $attachment,
        string $url,
    ): ?\App\Models\File {
        $source = \App\Models\File::query()
            ->where('checksum', $checksum)
            ->where('type', $type->value)
            ->whereNotNull('path')
            // Vlastné prílohy tohto záznamu rieši kontrola vyššie; sem patrí
            // len to, čo už visí inde.
            ->where(fn ($query) => $query
                ->where('fileable_type', '!=', $model->getMorphClass())
                ->orWhere('fileable_id', '!=', $model->getKey()))
            ->oldest('id')
            ->first();

        if ($source === null) {
            return null;
        }

        // Vráti null aj vtedy, keď zdrojové objekty na disku medzitým zmizli
        // (napr. po zmazaní originálu po vygenerovaní variantov) — vtedy sa
        // ide normálnou cestou a súbor sa uloží nanovo.
        return $this->fileDuplicator->copyTo($source, $model, [
            'name' => $this->fileDisplayNameResolver->resolve($model, $safeOriginalName),
            'original_name' => $safeOriginalName,
            'extension' => $extension !== '' ? $extension : null,
            'is_primary' => $makePrimary,
            'sort_order' => 0,
            'meta' => array_merge($meta, [
                'source' => 'remote_attachment',
                'source_url' => $url,
                'source_name' => $attachment['name'] ?? null,
                'source_link_text' => $attachment['link_text'] ?? null,
                'source_size' => $attachment['size'] ?? null,
                'reused_from' => $source->id,
            ]),
        ]);
    }

    private function resolveOriginalName(array $attachment): string
    {
        $url = (string) ($attachment['url'] ?? '');
        $linkText = trim((string) ($attachment['link_text'] ?? ''));
        if ($linkText !== '' && str_contains($linkText, '.')) {
            return $linkText;
        }

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (isset($query['file']) && is_string($query['file'])) {
            $fromQuery = basename($query['file']);
            if ($fromQuery !== '') {
                return $fromQuery;
            }
        }

        $name = trim((string) ($attachment['name'] ?? ''));
        if ($name !== '' && $name !== 'subor.html') {
            return $name;
        }

        // Bez prípony — tú doplní obsah (validateRemoteContent).
        return 'remote_file_'.time();
    }

    private function isImageType(FileType $type): bool
    {
        return $type === FileType::IMAGE || $type === FileType::CARD;
    }

    private function maxBytes(FileType $type): int
    {
        return $this->isImageType($type)
            ? max(1, (int) config('services.venue_detection.attach.max_bytes', 10485760))
            : max(1, (int) config('services.remote_attachments.max_bytes', 20971520));
    }

    /**
     * Overí obsah a vráti [MIME, príponu], ktoré sa uložia.
     *
     * @return array{0: string, 1: string}
     *
     * @throws RemoteAttachmentRejected
     */
    private function validateRemoteContent(FileType $type, string $nameExtension, string $content, string $url): array
    {
        if (strlen($content) > $this->maxBytes($type)) {
            throw new RemoteAttachmentRejected('Remote file is too large: '.$url);
        }

        return $this->isImageType($type)
            ? $this->validateImage($nameExtension, $content, $url)
            : $this->validateFile($nameExtension, $content, $url);
    }

    /** @return array{0: string, 1: string} */
    private function validateImage(string $nameExtension, string $content, string $url): array
    {
        // getimagesizefromstring číta hlavičku formátu; HTML, SVG ani iný
        // text s hlavičkou `image/jpeg` ním neprejde.
        $info = @getimagesizefromstring($content);
        $normalizedMime = is_array($info) ? strtolower((string) ($info['mime'] ?? '')) : '';

        if (! isset(self::IMAGE_EXTENSIONS[$normalizedMime])) {
            throw new RemoteAttachmentRejected('Remote file is not an image: '.$url);
        }

        $allowedMimes = config('services.venue_detection.attach.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ]);

        if (is_string($allowedMimes)) {
            $allowedMimes = array_values(array_filter(array_map('trim', explode(',', $allowedMimes)), fn (string $item) => $item !== ''));
        }

        if (is_array($allowedMimes) && $allowedMimes !== []) {
            $allowedMimes = array_map(fn ($item) => strtolower((string) $item), $allowedMimes);

            if (! in_array($normalizedMime, $allowedMimes, true)) {
                throw new RemoteAttachmentRejected('Remote image mime type is not allowed: '.$normalizedMime);
            }
        }

        $extensions = self::IMAGE_EXTENSIONS[$normalizedMime];

        return [$normalizedMime, in_array($nameExtension, $extensions, true) ? $nameExtension : $extensions[0]];
    }

    /**
     * Príloha (FileType::FILE): len prípony z whitelistu a obsah, ktorý
     * k prípone sedí. HTML ani SVG sa z nášho úložiska servírovať nesmú.
     *
     * @return array{0: string, 1: string}
     */
    private function validateFile(string $nameExtension, string $content, string $url): array
    {
        $allowed = config('services.remote_attachments.allowed_extensions', 'pdf,doc,docx,xls,xlsx,odt,txt');
        $allowed = is_string($allowed) ? explode(',', $allowed) : (array) $allowed;
        $allowed = array_values(array_intersect(
            array_map(fn ($item) => strtolower(trim((string) $item)), $allowed),
            array_keys(self::FILE_TYPES),
        ));

        if ($nameExtension !== '' && ! in_array($nameExtension, $allowed, true)) {
            throw new RemoteAttachmentRejected('Remote file extension is not allowed: '.$nameExtension);
        }

        $sniffed = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($content));

        if ($nameExtension === '') {
            // Prípona chýba (odkaz „Prihláška") — určí ju obsah. Presná zhoda
            // MIME má prednosť pred všeobecným ZIP/OLE kontajnerom.
            foreach ($allowed as $candidate) {
                if (self::FILE_TYPES[$candidate][0] === $sniffed) {
                    return [$sniffed, $candidate];
                }
            }

            throw new RemoteAttachmentRejected('Remote file type is not allowed: '.$sniffed.' ('.$url.')');
        }

        [$canonicalMime, $acceptedMimes] = self::FILE_TYPES[$nameExtension];

        if (! in_array($sniffed, $acceptedMimes, true)) {
            throw new RemoteAttachmentRejected('Remote file content ('.$sniffed.') does not match .'.$nameExtension.': '.$url);
        }

        return [$canonicalMime, $nameExtension];
    }
}
