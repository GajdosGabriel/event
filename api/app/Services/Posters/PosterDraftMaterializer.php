<?php

namespace App\Services\Posters;

use App\Enums\CanalIdentityMode;
use App\Enums\CanalRole;
use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\PosterDraft;
use App\Models\User;
use App\Services\Canals\CanalMembership;
use App\Services\Files\FileManager;
use App\Services\Geocoding\MunicipalityResolver;
use App\Services\Imports\HtmlBodyCleaner;
use App\Services\Imports\ImportedVenueManager;
use App\Services\Imports\PdfConverterService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Z rozpracovaného plagátu spraví skutočné podujatie — aj s kanálom a miestom,
 * keď ešte neexistujú.
 *
 * Prečo tu nepoužívame `ImportedCanalManager`: ten zakladá kanály pre scraper
 * a vlastníkom im nastaví systémový účet. Kanál založený z nahratého plagátu
 * musí patriť tomu, kto ho nahral — inak by nemal právo vlastné podujatie
 * upraviť (EventPolicy ide cez `User::canInCanal()`).
 *
 * Miesto naopak riešime existujúcim `ImportedVenueManager`: hľadanie duplicít,
 * AI dodetekovanie adresy a fallback „Celé Slovensko" sú tam už odladené.
 */
class PosterDraftMaterializer
{
    public function __construct(
        private readonly ImportedVenueManager $venueManager = new ImportedVenueManager(),
        private readonly MunicipalityResolver $municipalityResolver = new MunicipalityResolver(),
        private readonly CanalMembership $membership = new CanalMembership(),
        private readonly HtmlBodyCleaner $cleaner = new HtmlBodyCleaner(),
        private readonly PdfConverterService $pdfConverter = new PdfConverterService(),
        private readonly ?FileManager $fileManager = null,
    ) {}

    public function materialize(PosterDraft $draft, User $user): Event
    {
        $payload = $this->mergedPayload($draft);

        $canal = $this->resolveCanal($draft, $user, $payload);
        $venue = $this->venueManager->resolveOrDetect(
            $canal,
            $this->stringOrNull($payload['venue']['name'] ?? null),
            $this->stringOrNull($payload['venue']['city'] ?? null),
            $this->stringOrNull($payload['venue']['street_and_number'] ?? null),
        );

        $startAt = $this->parseDate($payload['start_at'] ?? null);
        $endAt = $this->parseDate($payload['end_at'] ?? null) ?? $startAt?->copy()->addHours(2);

        // Názov sa testuje pred doplnením náhrady, nie po ňom. Inak by sa
        // podujatie bez rozpoznaného názvu zverejnilo pod „Nové podujatie" —
        // a to je presne ten prípad, keď sprievodca hlási „nedá sa uložiť".
        $detectedTitle = $this->stringOrNull($payload['title'] ?? null);
        $title = Str::limit($detectedTitle ?? 'Nové podujatie', 250, '');
        $body = $this->resolveBody($draft);

        // Rovnaké kritérium ako pri importe zo zdrojov
        // (`EventImportService::isComplete()`): termín, názov, popis a miesto.
        //
        // Miesto sa testuje podľa toho, čo sa naozaj prečítalo z plagátu, nie
        // podľa `$venue->id` — `ImportedVenueManager` vždy niečo vráti, v
        // najhoršom zberné „Celé Slovensko". Zverejniť podujatie s takým
        // miestom by znamenalo pustiť von záznam, o ktorom sprievodca
        // používateľovi tvrdil, že miesto chýba.
        $hasVenue = $this->stringOrNull($payload['venue']['name'] ?? null) !== null;

        $isComplete = $startAt !== null
            && $endAt !== null
            && $detectedTitle !== null
            && $hasVenue
            && $body !== null
            && trim(strip_tags($body)) !== '';

        $event = DB::transaction(function () use ($draft, $user, $canal, $venue, $payload, $startAt, $endAt, $title, $body, $isComplete) {
            $event = Event::query()->create([
                'name' => $title,
                'body' => $body,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => $isComplete ? ModelStatus::Published->value : ModelStatus::Draft->value,
                'published_at' => $isComplete ? now() : null,
                // Orezanie na dĺžku stĺpcov: hodnoty z AI nie sú validované
                // formulárom a dlhší reťazec by zápis zhodil.
                'email' => $this->limited($payload['email'] ?? null, 100),
                'phone' => $this->limited($payload['phone'] ?? null, 20),
                'venue_id' => $venue->id,
                'canal_id' => $canal->id,
                'user_id' => $user->id,
                'meta' => [
                    'poster_upload' => [
                        'draft_id' => $draft->id,
                        'source_kind' => $draft->source_kind,
                        'original_filename' => $draft->original_filename,
                        'created_at' => now()->toIso8601String(),
                        'detected' => $payload,
                    ],
                ],
            ]);

            // Verejný zoznam podujatí filtruje podľa stavu podujatia, ale meno
            // kanála pri ňom zobrazuje vždy. Publikované podujatie visiace na
            // koncepte kanála by teda odkazovalo na profil, ktorý sa nedá
            // otvoriť — kanál preto ide von spolu s ním.
            if ($isComplete && $canal->status === ModelStatus::Draft) {
                $canal->forceFill([
                    'status' => ModelStatus::Published->value,
                    'published_at' => $canal->published_at ?? now(),
                ])->save();
            }

            $draft->forceFill([
                'claimed_by_user_id' => $user->id,
                'event_id' => $event->id,
                'claimed_at' => now(),
                'email' => $draft->email ?? $user->email,
            ])->save();

            return $event;
        });

        $this->attachPoster($draft, $event);

        return $event->load('files');
    }

    /**
     * Údaje z AI prekryté tým, čo človek opravil v sprievodcovi. Opravy majú
     * vždy prednosť — sú to jediné hodnoty, ktoré niekto naozaj videl.
     *
     * @return array<string, mixed>
     */
    private function mergedPayload(PosterDraft $draft): array
    {
        $detected = (array) (($draft->detection['event_payload'] ?? []) ?: []);
        $overrides = (array) ($draft->overrides ?? []);

        foreach (['title', 'start_at', 'end_at', 'email', 'phone'] as $key) {
            if (array_key_exists($key, $overrides) && $this->stringOrNull($overrides[$key]) !== null) {
                $detected[$key] = trim((string) $overrides[$key]);
            }
        }

        foreach (['venue', 'organizer'] as $group) {
            if (! is_array($overrides[$group] ?? null)) {
                continue;
            }
            $detected[$group] = array_merge(
                (array) ($detected[$group] ?? []),
                array_filter($overrides[$group], static fn ($v) => is_string($v) && trim($v) !== ''),
            );
        }

        return $detected;
    }

    /**
     * Poradie: kanál, ktorý si používateľ vybral → jeho jediný existujúci kanál
     * → nový kanál pomenovaný podľa organizátora z plagátu.
     *
     * Nový používateľ nemá kanál žiadny, a bez kanála nesmie založiť podujatie
     * (EventPolicy::create). Založenie kanála je preto súčasťou tohto kroku,
     * nie samostatný formulár navyše.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveCanal(PosterDraft $draft, User $user, array $payload): Canal
    {
        $requestedId = $draft->overrides['canal_id'] ?? null;

        if (is_numeric($requestedId)) {
            $canal = $user->canals()->where('canals.id', (int) $requestedId)->first();
            if ($canal instanceof Canal && $user->canInCanal((int) $canal->id, 'event.create')) {
                return $canal;
            }
        }

        $owned = $user->canals()->wherePivot('is_owner', true)->orderBy('canals.id')->first();
        if ($owned instanceof Canal) {
            return $owned;
        }

        $name = $this->stringOrNull($draft->overrides['organizer']['name'] ?? null)
            ?? $this->stringOrNull($payload['organizer']['name'] ?? null)
            ?? $this->stringOrNull($payload['venue']['name'] ?? null)
            ?? 'Podujatia ' . Str::before((string) $user->email, '@');

        $city = $this->stringOrNull($payload['organizer']['city'] ?? null)
            ?? $this->stringOrNull($payload['venue']['city'] ?? null);

        $canal = Canal::query()->create([
            'municipality_id' => $this->resolveMunicipalityId($city),
            'name' => Str::limit($name, 250, ''),
            'email' => $user->email,
            // Kanál z plagátu je koncept: nemá popis, logo ani overený kontakt.
            // Zverejní ho organizátor sám, keď ho doplní.
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
            'registration_source' => RegistrationSource::SELF->value,
            'identity_mode' => CanalIdentityMode::Organization->value,
        ]);

        $this->membership->attach($canal, $user, CanalRole::Owner);

        // Kanál práve vznikol — nastavíme ho ako aktívny kontext, inak by
        // používateľ po presmerovaní do dashboardu videl prázdno.
        if ($user->canal_id === null) {
            $user->forceFill(['canal_id' => $canal->id])->save();
        }

        return $canal->refresh();
    }

    /**
     * Súbor sa k podujatiu vešia rovnako ako pri importe zo zdrojov
     * (`EventImportService`): originál ostáva prílohou a z PDF sa navyše
     * ukladá obrázok každej strany.
     *
     * Bez tých strán by podujatie z PDF plagátu nemalo v zozname žiadny
     * náhľad — samotné PDF sa ako obrázok nezobrazí. Prvý uložený obrázok
     * spraví `FileManager` automaticky hlavným.
     */
    private function attachPoster(PosterDraft $draft, Event $event): void
    {
        if ($draft->file_path === null || $draft->file_disk === null) {
            return;
        }

        $disk = Storage::disk($draft->file_disk);

        if (! $disk->exists($draft->file_path)) {
            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'poster_');
        if ($tmpPath === false) {
            return;
        }

        try {
            $binary = (string) $disk->get($draft->file_path);
            file_put_contents($tmpPath, $binary);

            $uploaded = new UploadedFile(
                $tmpPath,
                $draft->original_filename ?: 'plagat',
                $disk->mimeType($draft->file_path) ?: null,
                null,
                true,
            );

            $isImage = $draft->source_kind === 'image';

            $this->fileManager()->storeForEvent(
                $event,
                $uploaded,
                $isImage ? FileType::IMAGE : FileType::FILE,
                'public',
                null,
                $isImage,
                ['source' => 'poster_upload', 'draft_id' => $draft->id],
            );

            if ($draft->source_kind === 'pdf') {
                $this->attachPdfPages($draft, $event, $binary);
            }
        } catch (\Throwable $e) {
            // Príloha je bonus — podujatie už existuje a stratiť ho kvôli
            // zlyhanému uploadu by bolo horšie než chýbajúci plagát.
            Log::warning('PosterDraftMaterializer: plagát sa nepodarilo priložiť.', [
                'draft_id' => $draft->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Strany PDF ako obrázky — presne to, čo robí import v
     * `EventImportService::syncPdfPageImages()`.
     *
     * Konvertor sa volá druhýkrát (prvýkrát bežal pri analýze). Base64 obrázky
     * strán sa na koncept zámerne neukladajú — jeden riadok v `poster_drafts`
     * by narástol o megabajty a drvivá väčšina konceptov skončí nepotvrdená.
     * Radšej jedno volanie navyše pri uložení než trvalá záťaž databázy.
     */
    private function attachPdfPages(PosterDraft $draft, Event $event, string $binary): void
    {
        $filename = $draft->original_filename ?: 'plagat.pdf';
        $result = $this->pdfConverter->convertFromBinary($binary, $filename);

        if ($result === null) {
            Log::info('PosterDraftMaterializer: strany PDF sa nepodarilo priložiť.', [
                'draft_id' => $draft->id,
                'event_id' => $event->id,
            ]);

            return;
        }

        // Poradie strán z konvertora nie je zaručené a hlavnou fotkou sa stane
        // prvý uložený obrázok — bez zoradenia by obálkou podujatia mohla byť
        // druhá strana. (Rovnako to rieši `PdfPreviewRenderer`.)
        $pages = collect($result->pages)->sortBy(fn (array $page) => (int) ($page['page'] ?? 0));

        foreach ($pages as $page) {
            $pageNumber = (int) ($page['page'] ?? 1);
            $uploadedFile = $this->pdfConverter->pageToUploadedFile((array) $page, $filename, $pageNumber);

            if ($uploadedFile === null) {
                continue;
            }

            try {
                // `makePrimary: false` zámerne — hlavnú fotku určí FileManager
                // sám z prvého obrázka, takže ňou bude prvá strana plagátu.
                $this->fileManager()->storeForEvent(
                    $event,
                    $uploadedFile,
                    FileType::IMAGE,
                    'public',
                    null,
                    false,
                    [
                        'source' => 'pdf_conversion',
                        'draft_id' => $draft->id,
                        'page' => $pageNumber,
                    ],
                );
            } finally {
                @unlink($uploadedFile->getPathname());
            }
        }
    }

    /**
     * Telo podujatia: oprava z formulára → text od copywritera → surový text
     * z dokumentu. Rovnaké poradie drží aj `PosterAnalysisReport`, aby report
     * ukazoval presne to, čo sa naozaj uloží.
     */
    private function resolveBody(PosterDraft $draft): ?string
    {
        // Override píše človek a `body` sa na verejnom detaile renderuje cez
        // v-html — bez sanitizácie je to uložené XSS. Copywriterovmu výstupu
        // tiež neveríme naslepo; `HtmlBodyCleaner` má whitelist tagov a zahodí
        // script, img aj iframe.
        $override = $this->stringOrNull($draft->overrides['description'] ?? null);
        if ($override !== null) {
            return $this->toSafeHtml($override);
        }

        $corrected = $this->stringOrNull($draft->detection['corrected_text'] ?? null);
        if ($corrected !== null) {
            return $this->cleaner->cleanHtmlString($corrected) ?: null;
        }

        // Surový text z dokumentu nie je HTML nikdy. `fromPlainText()` z neho
        // spraví odstavce so zalomeniami — inak by sa harmonogram púte zlial
        // do jedného bloku, lebo v HTML sú nové riadky len medzery.
        $raw = $this->stringOrNull($draft->extracted_text);

        return $raw === null ? null : ($this->cleaner->fromPlainText($raw) ?: null);
    }

    /**
     * Text od človeka môže byť HTML (upravený výstup copywritera) aj obyčajný
     * text. Rozhoduje prítomnosť skutočného tagu — `strip_tags()` sa na to
     * použiť nedá, tá považuje za tag aj `<info@farnost.sk>` alebo `<15 rokov`
     * a zvyšok reťazca zahodí.
     */
    private function toSafeHtml(string $value): string
    {
        $hasRealTag = preg_match(
            '/<(?:p|br|div|h[1-6]|ul|ol|li|strong|em|b|i|a|span|blockquote)\b[^>]*>/i',
            $value,
        ) === 1;

        return $hasRealTag
            ? $this->cleaner->cleanHtmlString($value)
            : $this->cleaner->fromPlainText($value);
    }

    private function resolveMunicipalityId(?string $city): int
    {
        if ($city === null) {
            return 4209;
        }

        try {
            $resolved = $this->municipalityResolver->resolve($city);
            $id = $resolved['village_id'] ?? null;

            return is_numeric($id) ? (int) $id : 4209;
        } catch (\Throwable) {
            return 4209;
        }
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, config('app.timezone', 'Europe/Bratislava'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function limited(mixed $value, int $length): ?string
    {
        $value = $this->stringOrNull($value);

        return $value === null ? null : Str::limit($value, $length, '');
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function fileManager(): FileManager
    {
        return $this->fileManager ?? app(FileManager::class);
    }
}
