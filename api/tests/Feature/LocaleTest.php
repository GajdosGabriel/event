<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    /**
     * Symfony dáva testovaciemu requestu implicitné „Accept-Language: en-us,en".
     * Keď testujeme prípad „klient si nič nepýta", musíme ju prebiť — inak by
     * sme namiesto defaultu merali práve tú hlavičku.
     */
    private function withoutLanguagePreference(): self
    {
        return $this->withHeader('Accept-Language', '');
    }

    #[Test]
    public function default_locale_is_used_when_client_asks_for_nothing(): void
    {
        $this->withoutLanguagePreference()
            ->getJson('/api')
            ->assertOk()
            ->assertHeader('Content-Language', 'sk');

        $this->assertSame('sk', app()->getLocale());
    }

    #[Test]
    public function x_locale_header_switches_the_locale(): void
    {
        $this->withHeader('X-Locale', 'cs')
            ->getJson('/api')
            ->assertOk()
            ->assertHeader('Content-Language', 'cs');

        $this->assertSame('cs', app()->getLocale());
    }

    #[Test]
    public function x_locale_beats_accept_language(): void
    {
        // SPA posiela obe; rozhoduje to, čo si používateľ vybral v prepínači.
        $this->withHeader('X-Locale', 'de')
            ->withHeader('Accept-Language', 'cs-CZ,cs;q=0.9')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'de');
    }

    #[Test]
    public function accept_language_is_used_when_x_locale_is_missing(): void
    {
        $this->withHeader('Accept-Language', 'cs-CZ,cs;q=0.9,en;q=0.8')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'cs');
    }

    #[Test]
    public function accept_language_respects_quality_over_order(): void
    {
        // Poradie v hlavičke nie je záväzné — rozhoduje q.
        $this->withHeader('Accept-Language', 'en;q=0.3,de;q=0.5,cs;q=0.9')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'cs');
    }

    #[Test]
    public function language_without_quality_beats_one_with_it(): void
    {
        // Chýbajúce q znamená 1.0, takže "en" má prednosť pred "cs;q=0.9".
        $this->withHeader('Accept-Language', 'cs;q=0.9,en')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'en');
    }

    #[Test]
    public function a_variant_does_not_lower_the_quality_of_its_base_language(): void
    {
        // Bežný tvar z prehliadača: "cs-CZ,cs;q=0.9,en;q=0.8". Obe položky
        // vedú na "cs", platiť musí tá vyššia — inak by vyhralo "en".
        $this->withHeader('Accept-Language', 'cs-CZ,cs;q=0.9,en;q=0.95')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'cs');
    }

    #[Test]
    public function language_rejected_with_q_zero_is_skipped(): void
    {
        // "q=0" znamená „tento jazyk nechcem" — musí prepadnúť na default.
        $this->withHeader('Accept-Language', 'cs;q=0,pl;q=0.9')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'sk');
    }

    #[Test]
    public function first_supported_language_in_accept_language_wins(): void
    {
        // Poľštinu nevieme, čeština je až druhá — aj tak sa má chytiť.
        $this->withHeader('Accept-Language', 'pl-PL,pl;q=0.9,cs;q=0.7')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'cs');
    }

    #[Test]
    public function unsupported_locale_falls_back_to_default(): void
    {
        $this->withoutLanguagePreference()
            ->withHeader('X-Locale', 'pl')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'sk');
    }

    #[Test]
    public function garbage_locale_header_cannot_change_the_lang_path(): void
    {
        // Nedôveryhodný vstup nesmie skončiť ako názov priečinka s prekladmi.
        $this->withoutLanguagePreference()
            ->withHeader('X-Locale', '../../etc/passwd')
            ->getJson('/api')
            ->assertHeader('Content-Language', 'sk');
    }

    #[Test]
    public function translations_follow_the_requested_locale(): void
    {
        $expected = [
            'sk' => 'Potvrdený',
            'cs' => 'Potvrzený',
            'de' => 'Bestätigt',
            'en' => 'Confirmed',
        ];

        foreach ($expected as $locale => $label) {
            $this->withHeader('X-Locale', $locale)->getJson('/api');

            $this->assertSame($label, __('tickets.status.confirmed'), "locale $locale");
        }
    }

    #[Test]
    public function every_supported_locale_has_a_complete_translation_set(): void
    {
        $supported = config('app.supported_locales');

        $this->assertSame(['sk', 'cs', 'de', 'en'], $supported);

        // Fallback zakrýva chýbajúce kľúče slovenčinou, takže neúplný preklad
        // by inak prešiel ticho až do produkcie. Tu musí sedieť kľúč na kľúč.
        $reference = $this->translationKeys('sk');
        $this->assertNotEmpty($reference);

        foreach ($supported as $locale) {
            $this->assertSame(
                $reference,
                $this->translationKeys($locale),
                "Jazyk $locale nemá rovnaké kľúče ako sk.",
            );
        }
    }

    /**
     * Ploché kľúče („tickets.status.confirmed") všetkých PHP súborov jazyka.
     *
     * @return array<int, string>
     */
    private function translationKeys(string $locale): array
    {
        $keys = [];

        foreach (glob(lang_path("$locale/*.php")) as $file) {
            $group = basename($file, '.php');

            foreach (array_keys(Arr::dot(require $file)) as $key) {
                $keys[] = "$group.$key";
            }
        }

        sort($keys);

        return $keys;
    }
}
