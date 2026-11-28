<?php

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\File;
use App\Models\Venue;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * Miesta: trojodsekové popisy namiesto krátkych či chybných (opis okresu, haly
 * alebo klubu pri obci), weby a e-maily z oficiálnych stránok a fotky z Wikimedia
 * Commons v assets/canal-venue-photos (autor a licencia v meta).
 *
 * Miesto musí sedieť ID aj názvom a nesmie byť archivované. Popis sa prepíše len
 * vtedy, keď má menej ako tri odseky, kontakt len do prázdneho poľa a fotku
 * dostane len miesto bez obrázka.
 */
return new class extends Migration
{
    private const SOURCE = 'migration:2026_12_09_venue_photo';

    /** ID => [názov, odseky] */
    private const BODIES = [
        6 => ['Kapitulská 1, Bratislava', [
            'Kapitulská 1 je adresa v historickom centre Bratislavy na Kapitulskej ulici, neďaleko Dómu svätého Martina.',
            'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        10 => ['Prešov, Konštantínova 2', [
            'Konštantínova 2 je adresa v centre Prešova, kde sa konajú kultúrne a duchovné podujatia.',
            'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        13 => ['Komenského ulica (Košice)', [
            'Komenského ulica v Košiciach nesie meno pedagóga Jána Amosa Komenského.',
            'Pod týmto miestom sú podujatia na adresách v tejto ulici. Konkrétnu budovu uvádza detail podujatia.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        17 => ['Poprad', [
            'Poprad je okresné mesto v Prešovskom kraji pod Vysokými Tatrami.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        51 => ['Galanta', [
            'Galanta je okresné mesto na juhozápade Slovenska v Trnavskom kraji.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        52 => ['Smižany', [
            'Smižany sú veľká obec v okrese Spišská Nová Ves v Košickom kraji.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        55 => ['Busseto, Italy, Námestie SNP, Bratislava', [
            'Námestie SNP je jedno z hlavných námestí v centre Bratislavy.',
            'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        61 => ['Ružomberok', [
            'Ružomberok je okresné mesto na dolnom Liptove v Žilinskom kraji a sídlo Katolíckej univerzity.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        73 => ['Spišské Podhradie', [
            'Spišské Podhradie je mesto v okrese Levoča pod Spišským hradom a Spišskou Kapitulou.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        90 => ['HC MONACObet Banská Bystrica', [
            'Toto miesto na Hronskom predmestí v Banskej Bystrici je spojené s areálom hokejového klubu HC 05.',
            'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        91 => ['Žilina', [
            'Žilina je krajské mesto na severozápade Slovenska a sídlo Žilinskej diecézy.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        93 => ['Žiar nad Hronom', [
            'Žiar nad Hronom je okresné mesto v Banskobystrickom kraji v údolí Hrona.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        122 => ['Dvojka', [
            'Dvojka je miesto v obci Kraľovany, ktorého názov používajú organizátori pri svojich podujatiach.',
            'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        129 => ['Trnava', [
            'Trnava je krajské mesto s bohatou cirkevnou históriou a sídlo Trnavskej arcidiecézy.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        131 => ['Jesenákov palác (Michalská)', [
            'Jesenákov palác je barokový palác na Michalskej ulici v historickom jadre Bratislavy.',
            'Na tejto adrese sa konajú duchovné programy, napríklad adorácie, sväté omše a chvály v Kaplnke svätej Kataríny.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        137 => ['Višňové', [
            'Višňové je obec v okrese Žilina, v ktorej sa konajú aj diecézne púte.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        147 => ['Brezno', [
            'Brezno je okresné mesto na Horehroní v Banskobystrickom kraji.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        157 => ['Turzovka', [
            'Turzovka je mesto na Kysuciach v okrese Čadca.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        170 => ['Párnica', [
            'Kultúrny dom v Párnici, nazývaný aj Kultúrno-správny dom, stojí v obci na Orave a sídli v ňom aj obecný úrad.',
            'Konajú sa tu napríklad duchovné cvičenia.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        184 => ['Detva', [
            'Detva je okresné mesto na Podpoľaní, známe ľudovou kultúrou.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        295 => ['Dolný Kubín', [
            'Dolný Kubín je okresné mesto na Orave v Žilinskom kraji.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        952 => ['Kostol povýšenia Svätého kríža', [
            'Kostol Povýšenia Svätého kríža je kostol na Námestí Štefana Moyzesa v Banskej Bystrici.',
            'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        1010 => ['S-klub; Teologická kaviareň Quo Vadis', [
            'S-klub s Teologickou kaviarňou Quo Vadis je priestor na prednášky, diskusie a kultúrne programy v Bratislave.',
            'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        1082 => ['Bratislava – mestská časť Dúbravka', [
            'Dúbravka je mestská časť Bratislavy na úpätí Devínskej Kobyly.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
        1086 => ['Vrbovce', [
            'Vrbovce sú obec v okrese Myjava pri hranici s Českou republikou.',
            'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
            'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
        ]],
    ];

    /** ID => [názov, web, e-mail] */
    private const CONTACTS = [
        2 => ['Kaplnka Provinciálneho domu (Trnava-Tulipán)', 'https://zdenka.sk', null],
        11 => ['Kláštor kapucínov (Pezinok)', 'https://www.kapucini.sk/bratstva/bratstvo-pezinok/', 'pezinok@kapucini.sk'],
        23 => ['Tipsport Aréna (Banská Bystrica)', 'https://www.tipsportarena.sk', 'info@tipsportarena.sk'],
        54 => ['Hotel Javorná (Drienica)', 'https://hoteljavorna.sk', 'info@hoteljavorna.sk'],
        59 => ['Martindom (Bratislava)', 'https://www.martindom.sk', 'infocentrum@martindom.sk'],
        95 => ['Biskupský úrad Západného dištriktu ECAV (Zvolen)', 'https://www.zdecav.sk', 'zdecav@zdecav.sk'],
        107 => ['Seminárny kostol na Hlavnej 89', 'https://nove.kske.sk', null],
        121 => ['Coworking Poprad', null, 'ahoj@coworkingpoprad.sk'],
        128 => ['Centrum pomoci pre rodinu (Trnava)', 'https://www.cppr.sk', 'cppr@cppr.sk'],
        719 => ['Aula sv. Jána Pavla II.', 'https://www.ku.sk', null],
        987 => ['Kláštor sestier redemptoristiek (Kežmarok)', 'https://redemptoristky.sk', null],
        1009 => ['Dom pútnika pallotínov (Spišská Nová Ves)', 'https://pallotini.sk', null],
        1118 => ['Aula Univerzity Komenského (Bratislava)', 'https://uniba.sk', null],
    ];

    /** ID miesta => [názov, súbor, názov na Commons, autor, licencia, odkaz na licenciu]. */
    private const PHOTOS = [
        6 => ['Kapitulská 1, Bratislava', 'miesto-6.jpg', 'Kapitulska in Bratislava.jpg', 'Krzysztof Golik', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        113 => ['Kostol Krista Spasiteľa (Praha-Barrandov)', 'miesto-113.jpg', 'Kostel Krista Spasitele Praha - Barrandov 1.jpg', 'Trasprd', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        125 => ['Kláštor minoritov pri sv. Jakubovi (Praha)', 'miesto-125.jpg', 'Praha, Staré Město, kostel sv. Jakuba (1).JPG', 'PatrikPaprika', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        773 => ['Kňazský seminár biskupa Jána Vojtaššáka', 'miesto-773.jpg', 'Seminár, Spišská Kapitula 21 Slovakia.jpg', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1019 => ['Aula Kňazského seminára sv. Gorazda', 'miesto-1019.jpg', 'Nitra - Seminár sv. Gorazda.jpg', 'Ladislav Luppa', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1050 => ['Bazilika sv. Petra', 'miesto-1050.jpg', '01 St Peter\'s Basilica Facade.jpg', 'NateBergin', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0'],
        1054 => ['Živčáková, Turzovka', 'miesto-1054.jpg', 'Korna Zivcakova new church.JPG', 'PetrS.', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        1066 => ['Kostol Svätej rodiny', 'miesto-1066.jpg', 'Ohaj, római katolikus templom 2025 01.jpg', 'Pasztilla aka Attila Terbócs', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
    ];

    public function up(): void
    {
        $this->describe();
        $this->contacts();
        $this->photos();
    }

    private function describe(): void
    {
        foreach (self::BODIES as $id => [$name, $paragraphs]) {
            $venue = $this->venue($id, $name);

            if ($venue && substr_count((string) $venue->body, '<p') < 3) {
                $this->save($venue, [
                    'body' => implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $paragraphs)),
                ]);
            }
        }
    }

    private function contacts(): void
    {
        foreach (self::CONTACTS as $id => [$name, $website, $email]) {
            $venue = $this->venue($id, $name);

            if (! $venue) {
                continue;
            }

            $changes = [];
            foreach (['website' => $website, 'email' => $email] as $field => $value) {
                if ($value !== null && trim((string) $venue->$field) === '') {
                    $changes[$field] = $value;
                }
            }

            $this->save($venue, $changes);
        }
    }

    private function photos(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        foreach (self::PHOTOS as $id => [$name, $file, $title, $author, $license, $licenseUrl]) {
            $venue = $this->venue($id, $name);
            $path = __DIR__.'/assets/canal-venue-photos/'.$file;

            if (! $venue || $this->hasImage($venue) || ! is_file($path)) {
                continue;
            }

            try {
                $venue->addFiles(
                    files: new UploadedFile($path, $file, 'image/jpeg', null, true),
                    type: FileType::IMAGE,
                    makePrimary: true,
                    meta: [
                        'source' => self::SOURCE,
                        'origin' => 'wikimedia_commons',
                        'commons_title' => 'File:'.$title,
                        'commons_page' => 'https://commons.wikimedia.org/wiki/File:'.rawurlencode(str_replace(' ', '_', $title)),
                        'author' => $author,
                        'license' => $license,
                        'license_url' => $licenseUrl,
                    ],
                );
            } catch (Throwable $e) {
                Recorder::warning('files', 'photo_skipped', "Fotka {$file} pre miesto #{$id} sa nenahrala",
                    subject: $venue, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function venue(int $id, string $name): ?Venue
    {
        $venue = Venue::query()->find($id);

        return $venue && $venue->name === $name && $venue->status !== ModelStatus::Archived ? $venue : null;
    }

    /** @param  array<string, string>  $changes */
    private function save(Venue $venue, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $venue->forceFill($changes)->save();
        Recorder::info('venues', 'profile_described', "Miesto {$venue->name}: doplnené ".implode(', ', array_keys($changes)),
            status: 'ok', subject: $venue, context: ['fields' => array_keys($changes)]);
    }

    private function hasImage(Venue $venue): bool
    {
        return File::query()
            ->where('fileable_type', $venue->getMorphClass())
            ->where('fileable_id', $venue->getKey())
            ->where('type', FileType::IMAGE->value)
            ->exists();
    }

    public function down(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        File::query()
            ->where('meta->source', self::SOURCE)
            ->get()
            ->each(fn (File $file) => $file->delete());
    }
};
