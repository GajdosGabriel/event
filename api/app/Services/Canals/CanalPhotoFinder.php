<?php

namespace App\Services\Canals;

use App\Enums\FileType;
use App\Models\Canal;
use App\Services\Files\FileManager;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;

/**
 * Typické fotky kanála organizácie z titulnej stránky jeho webu — do galérie,
 * ako miesta dostávajú fotky z Wikimedia (app:venues-find-images). Spúšťa
 * ho app:canals-find-photos.
 *
 * Kandidáti: `og:image`/`twitter:image` (obrázok, ktorý si web sám vybral
 * na zdieľanie), potom obrázky z úvodného pásu/slideru a nakoniec veľké
 * obrázky z obsahu. Logá, ikony, obrázky z hlavičky, navigácie a pätičky
 * sa vynechajú; prijme sa len rastrová fotka s rozumným pomerom strán
 * (žiadne úzke bannery) a bez duplicít.
 */
class CanalPhotoFinder
{
    public const ORIGIN = 'canal_website_photo';

    private const MAX_BYTES = 8_000_000;

    private const MAX_CANDIDATES = 12;

    private const MIN_EDGE = 300;

    /** Šírka/výška — mimo rozsahu je to banner, pásik alebo plagát na výšku. */
    private const MIN_RATIO = 0.7;

    private const MAX_RATIO = 2.4;

    private const RASTER_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const NOT_PHOTO = '/logo|icon|ikon|favicon|sprite|avatar|emoji|placeholder|loader|spinner|banner|pozvank|plagat|poster|letak|flyer|demo\d|\/themes\/|partner|sponsor|sponzor|button|badge|flag|vlajk|qr[-_]?code|pixel|blank|spacer|facebook|instagram|youtube|twitter|linkedin|whatsapp|tiktok|google|apple|play[-_]?store|mapa|map[-_]/i';

    private const HERO = '/hero|slider|slide|carousel|swiper|cover|jumbotron|intro|uvod|featured|header-image|page-header/i';

    public function __construct(
        private readonly CanalWebsitePages $pages,
        private readonly FileManager $files,
    ) {}

    /**
     * Najviac `$max` fotiek z webu kanála.
     *
     * @return array<int, array{url: string, source: string, page: string, body: string, mime: string, width: int, height: int, checksum: string}>
     */
    public function find(Canal $canal, int $max): array
    {
        $page = $this->pages->fetchPage((string) $canal->website);

        if ($page === null) {
            return [];
        }

        $known = $canal->files()->whereNotNull('checksum')->pluck('checksum')->all();
        $knownSources = $canal->files()->get(['meta'])->map(fn ($file) => data_get($file->meta, 'source_url'))->filter()->all();
        $photos = [];

        foreach (array_slice($this->candidates($page['url'], $page['body']), 0, self::MAX_CANDIDATES) as $candidate) {
            if (count($photos) >= $max) {
                break;
            }

            if (in_array($candidate['url'], $knownSources, true)) {
                continue;
            }

            $image = $this->pages->fetchImage($candidate['url'], self::MAX_BYTES);

            if ($image === null || ! in_array($image['mime'], self::RASTER_MIMES, true)) {
                continue;
            }

            $size = @getimagesizefromstring($image['body']);

            if ($size === false || ! in_array($size['mime'] ?? '', self::RASTER_MIMES, true)) {
                continue;
            }

            [$width, $height] = [(int) $size[0], (int) $size[1]];
            $ratio = $width / max(1, $height);

            if (min($width, $height) < self::MIN_EDGE || $ratio < self::MIN_RATIO || $ratio > self::MAX_RATIO) {
                continue;
            }

            $checksum = hash('sha256', $image['body']);

            if (in_array($checksum, $known, true)) {
                continue;
            }

            $known[] = $checksum;
            $photos[] = [
                'url' => $image['url'],
                'source' => $candidate['source'],
                'page' => $page['url'],
                'body' => $image['body'],
                'mime' => $size['mime'],
                'width' => $width,
                'height' => $height,
                'checksum' => $checksum,
            ];
        }

        return $photos;
    }

    /**
     * Uloží fotku do galérie kanála. Hlavnou sa stane len vtedy, keď kanál
     * hlavný obrázok ešte nemá.
     *
     * @param  array{url: string, source: string, page: string, body: string, mime: string, width: int, height: int, checksum: string}  $photo
     */
    public function store(Canal $canal, array $photo): void
    {
        $extension = match ($photo['mime']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $tmp = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($tmp, $photo['body']);

        try {
            $this->files->storeForModel(
                model: $canal,
                files: new UploadedFile($tmp, 'foto.'.$extension, null, null, true),
                type: FileType::IMAGE,
                makePrimary: ! $canal->images()->where('is_primary', true)->exists(),
                meta: [
                    'origin' => self::ORIGIN,
                    'photo_source' => $photo['source'],
                    'source_url' => $photo['url'],
                    'source_page' => $photo['page'],
                ],
            );
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Kandidáti v poradí: obrázok na zdieľanie, úvodný pás, obsah.
     *
     * @return array<int, array{url: string, source: string}>
     */
    public function candidates(string $baseUrl, string $html): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        $base = $baseUrl;
        foreach ($xpath->query('//base[@href]') as $node) {
            $base = $this->pages->absolute($baseUrl, $node->getAttribute('href')) ?? $baseUrl;
            break;
        }

        $found = [];
        $add = function (?string $href, string $source) use (&$found, $base) {
            $href = trim((string) $href);

            if ($href === '' || str_starts_with($href, 'data:')) {
                return;
            }

            $url = $this->pages->absolute($base, $href);

            if ($url === null || preg_match('/\.(svgz?|gif|ico)($|\?)/i', $url) || isset($found[$url])) {
                return;
            }

            if (preg_match(self::NOT_PHOTO, (string) parse_url($url, PHP_URL_PATH))) {
                return;
            }

            $found[$url] = ['url' => $url, 'source' => $source];
        };

        foreach ($xpath->query('//meta[@property="og:image" or @name="og:image" or @property="og:image:url" or @name="twitter:image"]') as $node) {
            $add($node->getAttribute('content'), 'og_image');
        }

        $hero = [];
        $content = [];

        foreach ($xpath->query('//img') as $img) {
            /** @var DOMElement $img */
            $context = $this->context($img);

            if ($context['skip']) {
                continue;
            }

            $own = implode(' ', [$img->getAttribute('class'), $img->getAttribute('id'), $img->getAttribute('alt')]);

            if (preg_match(self::NOT_PHOTO, $own)) {
                continue;
            }

            // Malé obrázky podľa atribútov (ikony, náhľady) netreba ani sťahovať.
            $declared = array_filter([(int) $img->getAttribute('width'), (int) $img->getAttribute('height')]);
            if ($declared !== [] && min($declared) < self::MIN_EDGE / 2) {
                continue;
            }

            $src = $this->largestSource($img);

            if ($src === null) {
                continue;
            }

            if ($context['hero']) {
                $hero[] = $src;
            } else {
                $content[] = $src;
            }
        }

        foreach ($hero as $src) {
            $add($src, 'hero');
        }

        foreach ($content as $src) {
            $add($src, 'content');
        }

        return array_values($found);
    }

    /** Najväčší variant zo `srcset`, inak `src` (aj lenivo načítaný). */
    private function largestSource(DOMElement $img): ?string
    {
        $best = null;
        $bestWidth = 0;

        foreach (['srcset', 'data-srcset', 'data-lazy-srcset'] as $attribute) {
            foreach (explode(',', $img->getAttribute($attribute)) as $part) {
                $bits = preg_split('/\s+/', trim($part));
                $width = isset($bits[1]) ? (int) $bits[1] : 0;

                if (($bits[0] ?? '') !== '' && $width > $bestWidth && $width <= 2000) {
                    [$best, $bestWidth] = [$bits[0], $width];
                }
            }
        }

        if ($best !== null) {
            return $best;
        }

        foreach (['data-src', 'data-lazy-src', 'data-original', 'src'] as $attribute) {
            $value = trim($img->getAttribute($attribute));

            if ($value !== '' && ! str_starts_with($value, 'data:')) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Hlavička, navigácia, pätička a bočné panely nesú logá a ikony, nie
     * fotky organizácie; úvodný pás/slider má prednosť pred obsahom.
     *
     * @return array{skip: bool, hero: bool}
     */
    private function context(DOMElement $img): array
    {
        $hero = false;
        $node = $img->parentNode;

        for ($depth = 0; $node instanceof DOMElement && $depth < 10; $depth++, $node = $node->parentNode) {
            $name = strtolower($node->nodeName);
            $markers = $node->getAttribute('class').' '.$node->getAttribute('id');

            // Úvodný pás býva vnorený do <header> — ten ho nevyradí.
            if (in_array($name, ['nav', 'footer', 'aside'], true) || ($name === 'header' && ! $hero)
                || preg_match('/(^|[\s_-])(footer|navbar|menu|sidebar|widget|partner|sponsor|sponzor|logo|social|cookie)/i', $markers)) {
                return ['skip' => true, 'hero' => false];
            }

            if (preg_match(self::HERO, $markers)) {
                $hero = true;
            }
        }

        return ['skip' => false, 'hero' => $hero];
    }
}
