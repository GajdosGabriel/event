<?php

namespace App\Services\Imports;

use App\Models\Canal;
use App\Models\Event;

/**
 * Presunie podujatie zo zberného kanála zdroja ku skutočnému organizátorovi.
 *
 * Import skladá meno organizátora z regexov nad zoškrabaným textom a z jedného
 * AI volania; keď z toho nič nevyjde, podujatie ostane visieť na zbernom kanáli
 * (`vyveska.sk`, `tkkbs.sk`, `ecav.sk`). `app:ai-detector` číta neskôr celý
 * článok naraz a o organizátorovi vie viac — tu sa jeho výsledok dostane
 * k slovu.
 *
 * Pravidlá sú zámerne úzke: hýbe sa len podujatiami zo zberného kanála a len
 * podľa `organizer.name`. Náhrada miestom konania sa nepoužíva (Detector si ju
 * pre svoj vlastný návrh kanála dopĺňa v `resolveOrganizerCanalName()`), inak
 * by z miest znovu vznikali kanály ako „kostol" či „Tipsport Aréna".
 */
class EventOrganizerReassigner
{
    public function __construct(
        private readonly ImportedCanalManager $canalManager = new ImportedCanalManager(),
    ) {}

    /**
     * @return Canal|null  cieľový kanál, keď sa podujatie naozaj presunulo
     */
    public function reassign(Event $event, ?string $organizerName): ?Canal
    {
        $canal = $event->canal;

        if (! CollectionCanal::is($canal)) {
            return null;
        }

        $name = OrganizerName::sanitize($organizerName);

        if ($name === null || OrganizerName::looksLikeHost($name)) {
            return null;
        }

        $target = $this->canalManager->resolveOrCreate($name, $name, $this->sourceOrigin($event, $canal));

        if ($target->id === $canal->id) {
            return null;
        }

        $event->forceFill(['canal_id' => $target->id])->save();

        return $target;
    }

    /**
     * Doména zdroja, z ktorej sa importovalo. Prednosť má adresa článku —
     * zberný kanál síce má `website` na tú istú doménu, ale nemusí (na starých
     * záznamoch chýbal).
     */
    private function sourceOrigin(Event $event, Canal $canal): string
    {
        $url = (string) ($event->orginal_source ?? '');
        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'https');
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host !== '') {
            return $scheme . '://' . $host;
        }

        return (string) ($canal->website ?? '');
    }
}
