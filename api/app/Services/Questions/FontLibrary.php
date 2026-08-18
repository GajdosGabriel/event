<?php

namespace App\Services\Questions;

/**
 * Cesty k TTF súborom pre kreslenie textu do snímky.
 *
 * GD vie kresliť text len z TTF súboru — systémové písma nepozná a `imagestring`
 * kreslí bitmapový font bez diakritiky, ktorý je na 1920 px nepoužiteľný.
 *
 * Poradie hľadania:
 *
 * 1. `resources/fonts/` — sem patrí písmo, ktoré chceme naozaj používať.
 *    Stačí doň položiť TTF s názvom z `CANDIDATES` a snímka ho začne používať
 *    bez zásahu do kódu. `resource_path()` je `base_path()`-relatívna, takže
 *    funguje rovnako v testoch, v artisane aj na produkcii.
 * 2. Písmo, ktoré so sebou nesie `endroid/qr-code` — núdzový režim, aby snímka
 *    fungovala aj bez ručného kroku pri nasadení. Je to Open Sans s úplným
 *    pokrytím slovenskej diakritiky, ale len v jednom reze, takže nadpis
 *    nebude tučný. Nič sa tým nedistribuuje navyše: balík je tvrdá závislosť
 *    projektu.
 *
 * Keď nie je ani jedno, renderer to musí ohlásiť zrozumiteľnou chybou —
 * `imagettftext` s neexistujúcou cestou nakreslí prázdno a nikto sa nedozvie
 * prečo.
 */
class FontLibrary
{
    /**
     * Preferované súbory pre jednotlivé rezy, od najžiadanejšieho.
     *
     * @var array<string, array<int, string>>
     */
    private const CANDIDATES = [
        'bold' => ['Inter-Bold.ttf', 'Figtree-Bold.ttf', 'SourceSans3-Bold.ttf', 'Bold.ttf'],
        'semibold' => ['Inter-SemiBold.ttf', 'Figtree-SemiBold.ttf', 'SourceSans3-SemiBold.ttf', 'SemiBold.ttf'],
        'regular' => ['Inter-Regular.ttf', 'Figtree-Regular.ttf', 'SourceSans3-Regular.ttf', 'Regular.ttf'],
    ];

    private const VENDOR_FALLBACK = 'endroid/qr-code/assets/open_sans.ttf';

    /** @var array<string, string|null> */
    private array $resolved = [];

    public function bold(): string
    {
        return $this->weight('bold');
    }

    /** Medzistupeň pre podnadpisy. Keď chýba, spadne na bold, potom na regular. */
    public function semibold(): string
    {
        return $this->weight('semibold');
    }

    public function regular(): string
    {
        return $this->weight('regular');
    }

    /** Je čím kresliť? Volá sa pred vykreslením, aby chyba prišla ako hláška. */
    public function isUsable(): bool
    {
        return function_exists('imagettftext') && $this->firstExisting() !== null;
    }

    private function weight(string $name): string
    {
        if (array_key_exists($name, $this->resolved)) {
            return (string) $this->resolved[$name];
        }

        foreach (self::CANDIDATES[$name] as $candidate) {
            $path = resource_path('fonts/' . $candidate);

            if (is_file($path)) {
                return $this->resolved[$name] = $path;
            }
        }

        // Tučný rez chýba → skúsime aspoň polotučný, potom obyčajný. Hierarchiu
        // na snímke potom nesie veľkosť a farba, nie hrúbka.
        $fallbackChain = match ($name) {
            'bold' => ['semibold', 'regular'],
            'semibold' => ['bold', 'regular'],
            default => [],
        };

        foreach ($fallbackChain as $next) {
            foreach (self::CANDIDATES[$next] as $candidate) {
                $path = resource_path('fonts/' . $candidate);

                if (is_file($path)) {
                    return $this->resolved[$name] = $path;
                }
            }
        }

        return $this->resolved[$name] = (string) $this->firstExisting();
    }

    private function firstExisting(): ?string
    {
        foreach (self::CANDIDATES as $candidates) {
            foreach ($candidates as $candidate) {
                $path = resource_path('fonts/' . $candidate);

                if (is_file($path)) {
                    return $path;
                }
            }
        }

        $vendor = base_path('vendor/' . self::VENDOR_FALLBACK);

        return is_file($vendor) ? $vendor : null;
    }
}
