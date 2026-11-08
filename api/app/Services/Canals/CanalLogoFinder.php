<?php

namespace App\Services\Canals;

use App\Enums\CanalIdentityMode;
use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\File;
use App\Services\Files\FileManager;
use App\Services\Imports\CollectionCanal;
use App\Support\Url;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;

/**
 * Nájde logo kanála organizácie na jeho webe a nahrá ho ako hlavný obrázok,
 * aby zoznamy neukazovali predvolený obrázok. Spúšťa ho app:canals-find-logos.
 *
 * Kandidáti z titulnej stránky, od najspoľahlivejšieho:
 *  1. `logo` v JSON-LD (schema.org Organization) — web ho tvrdí výslovne,
 *  2. `<img>`, ktorý sa volá/označuje „logo" (prednosť má hlavička stránky),
 *  3. apple-touch-icon — štvorcová ikona webu, na avatar ideálna,
 *  4. `og:image` — „typický obrázok" webu, keď logo chýba,
 *  5. veľká favicona (`sizes` aspoň 96).
 *
 * Prijme sa prvý, ktorý je naozaj rastrový obrázok (SVG nie — môže niesť
 * skript a variantový generátor ho nevie) a nie je priveľmi malý. Logo
 * sa položí na biely štvorec s okrajom: zoznamy obrázok orezávajú na
 * výrez, takže široké logo by inak prišlo o okraje a priehľadné by na
 * tmavom pozadí zmizlo. Biele logo (pre tmavú hlavičku webu) dostane
 * tmavý štvorec. Fotka z og:image ide bez úprav.
 */
class CanalLogoFinder
{
    public const SOURCE_JSON_LD = 'json_ld';

    public const SOURCE_IMG = 'img';

    public const SOURCE_TOUCH_ICON = 'touch_icon';

    public const SOURCE_OG_IMAGE = 'og_image';

    public const SOURCE_ICON = 'icon';

    private const MAX_BYTES = 5_000_000;

    private const MAX_CANDIDATES = 6;

    /** Najmenšia kratšia strana: logo/ikona vs. fotka z og:image. */
    private const MIN_LOGO_EDGE = 64;

    private const MIN_PHOTO_EDGE = 200;

    private const CANVAS = 512;

    /** Pozadie pre biele/svetlé logo (tmavá hlavička webu). */
    private const DARK_BACKGROUND = [31, 41, 55];

    private const LIGHT_LUMINANCE = 200;

    private const RASTER_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** `<img>` s „logo" v týchto miestach patrí partnerom, nie webu. */
    private const FOREIGN_LOGO = '/partner|sponsor|sponzor|donor|grant|eu[-_]?fond|nadacia|footer-logos|payment|visa|mastercard|facebook|instagram|youtube|twitter|linkedin|google|apple-app|play-store/i';

    public function __construct(
        private readonly CanalWebsitePages $pages,
        private readonly FileManager $files,
    ) {}

    /** Prečo sa kanálu logo nehľadá; null = hľadá sa. */
    public function skipReason(Canal $canal): ?string
    {
        return match (true) {
            $canal->trashed() || $canal->merged_into_id !== null => 'trashed',
            ! in_array($canal->status, [ModelStatus::Published, ModelStatus::Draft], true) => 'status',
            $canal->identity_mode !== CanalIdentityMode::Organization => 'personal',
            CollectionCanal::is($canal) => 'collection',
            blank($canal->website) => 'no_website',
            $canal->images()->exists() => 'has_image',
            $this->sharesWebsite($canal) => 'shared_website',
            default => null,
        };
    }

    /**
     * Web, ktorý uvádza viac kanálov (ecav.sk pri zboroch, portál farností…),
     * patrí nadradenej organizácii — jej logo by kanálu prisúdilo cudziu identitu.
     */
    public function sharesWebsite(Canal $canal): bool
    {
        $host = $this->bareHost($canal->website);

        if ($host === null) {
            return false;
        }

        return Canal::query()
            ->whereKeyNot($canal->getKey())
            ->whereNull('merged_into_id')
            ->where(fn ($q) => $q->where('website', 'like', '%://'.$host.'%')->orWhere('website', 'like', '%://www.'.$host.'%'))
            ->get(['id', 'website'])
            ->contains(fn (Canal $other) => $this->bareHost($other->website) === $host);
    }

    /**
     * Najlepší použiteľný obrázok z webu kanála.
     *
     * @return array{url: string, source: string, page: string, body: string, mime: string, width: int, height: int}|null
     */
    public function find(Canal $canal): ?array
    {
        $page = $this->pages->fetchPage((string) $canal->website);

        if ($page === null) {
            return null;
        }

        foreach (array_slice($this->candidates($page['url'], $page['body']), 0, self::MAX_CANDIDATES) as $candidate) {
            $image = $this->pages->fetchImage($candidate['url'], self::MAX_BYTES);

            if ($image === null || ! in_array($image['mime'], self::RASTER_MIMES, true)) {
                continue;
            }

            $size = @getimagesizefromstring($image['body']);

            if ($size === false || ! in_array($size['mime'] ?? '', self::RASTER_MIMES, true)) {
                continue;
            }

            $minEdge = $candidate['source'] === self::SOURCE_OG_IMAGE ? self::MIN_PHOTO_EDGE : self::MIN_LOGO_EDGE;

            if (min($size[0], $size[1]) < $minEdge) {
                continue;
            }

            return [
                'url' => $image['url'],
                'source' => $candidate['source'],
                'page' => $page['url'],
                'body' => $image['body'],
                'mime' => $size['mime'],
                'width' => (int) $size[0],
                'height' => (int) $size[1],
            ];
        }

        return null;
    }

    /**
     * Uloží nájdený obrázok ako hlavný obrázok kanála.
     *
     * @param  array{url: string, source: string, page: string, body: string, mime: string, width: int, height: int}  $found
     */
    public function store(Canal $canal, array $found): ?File
    {
        $isLogo = $found['source'] !== self::SOURCE_OG_IMAGE;
        $body = $isLogo ? ($this->onSquareCanvas($found['body']) ?? $found['body']) : $found['body'];
        $extension = $isLogo && $body !== $found['body'] ? 'png' : match ($found['mime']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $tmp = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($tmp, $body);

        try {
            $upload = new UploadedFile($tmp, 'logo.'.$extension, null, null, true);

            return $this->files->storeForModel(
                model: $canal,
                files: $upload,
                type: FileType::IMAGE,
                makePrimary: true,
                meta: [
                    'origin' => 'canal_website_logo',
                    'logo_source' => $found['source'],
                    'source_url' => $found['url'],
                    'source_page' => $found['page'],
                ],
            )->first();
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Kandidáti v poradí spoľahlivosti, bez duplicít.
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
            if ($href === null || trim($href) === '' || str_starts_with(trim($href), 'data:')) {
                return;
            }

            $url = $this->pages->absolute($base, $href);

            if ($url === null || preg_match('/\.svgz?($|\?)/i', $url) || isset($found[$url])) {
                return;
            }

            $found[$url] = ['url' => $url, 'source' => $source];
        };

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $data = json_decode(trim($node->textContent), true);

            foreach ($this->jsonLdLogos(is_array($data) ? $data : []) as $logo) {
                $add($logo, self::SOURCE_JSON_LD);
            }
        }

        foreach ($this->logoImages($xpath) as $src) {
            $add($src, self::SOURCE_IMG);
        }

        foreach ($xpath->query('//link[@href]') as $node) {
            $rel = strtolower($node->getAttribute('rel'));

            if (str_contains($rel, 'apple-touch-icon')) {
                $add($node->getAttribute('href'), self::SOURCE_TOUCH_ICON);
            }
        }

        foreach ($xpath->query('//meta[@property="og:image" or @name="og:image" or @property="og:image:url" or @name="twitter:image"]') as $node) {
            $add($node->getAttribute('content'), self::SOURCE_OG_IMAGE);
        }

        foreach ($xpath->query('//link[@href][@sizes]') as $node) {
            $rel = strtolower($node->getAttribute('rel'));
            $edge = (int) preg_replace('/x.*$/i', '', $node->getAttribute('sizes'));

            if (preg_match('/(^|\s)icon(\s|$)/', $rel) && $edge >= 96) {
                $add($node->getAttribute('href'), self::SOURCE_ICON);
            }
        }

        return array_values($found);
    }

    /**
     * `logo` uzlov Organization (a príbuzných) kdekoľvek v JSON-LD.
     *
     * @param  array<mixed>  $data
     * @return array<int, string>
     */
    private function jsonLdLogos(array $data, int $depth = 0): array
    {
        if ($depth > 6) {
            return [];
        }

        $logos = [];

        if (isset($data['logo'])) {
            $logo = $data['logo'];
            $url = is_string($logo) ? $logo : (is_array($logo) ? ($logo['url'] ?? $logo['contentUrl'] ?? ($logo[0] ?? null)) : null);

            if (is_array($url)) {
                $url = $url['url'] ?? null;
            }

            if (is_string($url)) {
                $logos[] = $url;
            }
        }

        foreach ($data as $key => $value) {
            if ($key !== 'logo' && is_array($value)) {
                array_push($logos, ...$this->jsonLdLogos($value, $depth + 1));
            }
        }

        return $logos;
    }

    /**
     * Zdroje `<img>`, ktoré sa tvária ako logo webu — z hlavičky skôr než zvyšok.
     *
     * @return array<int, string>
     */
    private function logoImages(DOMXPath $xpath): array
    {
        $header = [];
        $rest = [];

        foreach ($xpath->query('//img') as $img) {
            /** @var DOMElement $img */
            $src = $this->imageSource($img);

            if ($src === null) {
                continue;
            }

            $own = implode(' ', [$img->getAttribute('class'), $img->getAttribute('id'), $img->getAttribute('alt'), basename((string) parse_url($src, PHP_URL_PATH))]);
            $context = $this->ancestorMarkers($img);

            $isLogo = stripos($own, 'logo') !== false || stripos($context['logo'], 'logo') !== false;

            if (! $isLogo || preg_match(self::FOREIGN_LOGO, $own.' '.$context['all'].' '.$src)) {
                continue;
            }

            if ($context['header']) {
                $header[] = $src;
            } else {
                $rest[] = $src;
            }
        }

        return array_merge($header, $rest);
    }

    private function imageSource(DOMElement $img): ?string
    {
        foreach (['src', 'data-src', 'data-lazy-src', 'data-original'] as $attribute) {
            $value = trim($img->getAttribute($attribute));

            if ($value !== '' && ! str_starts_with($value, 'data:')) {
                return $value;
            }
        }

        $srcset = trim($img->getAttribute('srcset'));

        return $srcset !== '' ? trim(explode(' ', $srcset)[0]) : null;
    }

    /**
     * Triedy a id predkov: `logo` z blízkeho odkazu/obalu (zvyčajne
     * `<a class="logo">`), všetko pre filter partnerov, a či ide o hlavičku.
     *
     * @return array{logo: string, all: string, header: bool}
     */
    private function ancestorMarkers(DOMElement $img): array
    {
        $logo = '';
        $all = '';
        $header = false;
        $node = $img->parentNode;

        for ($depth = 0; $node instanceof DOMElement && $depth < 8; $depth++, $node = $node->parentNode) {
            $markers = $node->getAttribute('class').' '.$node->getAttribute('id');
            $all .= ' '.$markers;

            if ($depth < 3) {
                $logo .= ' '.$markers;
            }

            if (in_array(strtolower($node->nodeName), ['header', 'nav'], true) || preg_match('/header|masthead|navbar|branding/i', $markers)) {
                $header = true;
            }

            if (strtolower($node->nodeName) === 'footer' || preg_match('/footer/i', $markers)) {
                $all .= ' footer-logos';
            }
        }

        return ['logo' => $logo, 'all' => $all, 'header' => $header];
    }

    /** Logo na bielom štvorci s okrajom; null, keď ho GD nevie prečítať. */
    private function onSquareCanvas(string $body): ?string
    {
        $source = @imagecreatefromstring($body);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $inner = (int) round(self::CANVAS * 0.84);
        $ratio = min($inner / $width, $inner / $height, 4.0);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor(self::CANVAS, self::CANVAS);
        [$r, $g, $b] = $this->isLight($source) ? self::DARK_BACKGROUND : [255, 255, 255];
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));
        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas, $source,
            intdiv(self::CANVAS - $newWidth, 2), intdiv(self::CANVAS - $newHeight, 2), 0, 0,
            $newWidth, $newHeight, $width, $height,
        );

        ob_start();
        imagepng($canvas, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * Je logo svetlé (biele logo pre tmavú hlavičku webu)? Rozhodujú len
     * viditeľné body — priehľadné pozadie by priemer stiahlo k nule.
     */
    public function isLight(\GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, intdiv(max($width, $height), 120));
        $sum = 0.0;
        $count = 0;

        for ($x = 0; $x < $width; $x += $step) {
            for ($y = 0; $y < $height; $y += $step) {
                $rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));

                if ($rgba['alpha'] > 100) {
                    continue;
                }

                $sum += 0.299 * $rgba['red'] + 0.587 * $rgba['green'] + 0.114 * $rgba['blue'];
                $count++;
            }
        }

        // Úplne nepriehľadné svetlé logo má okolo seba vlastné pozadie,
        // tmavý štvorec by z neho urobil biely obdĺžnik — preto len pri
        // obrázku s priehľadnosťou.
        $opaqueShare = $count / max(1, intdiv($width + $step - 1, $step) * intdiv($height + $step - 1, $step));

        return $count > 0 && $opaqueShare < 0.9 && $sum / $count > self::LIGHT_LUMINANCE;
    }

    private function bareHost(?string $url): ?string
    {
        $host = Url::host($url);

        return $host === null ? null : preg_replace('/^www\./', '', strtolower($host));
    }
}
