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
 * Fotky starších miest bez obrázka: zmenšeniny z Wikimedia Commons
 * v assets/canal-venue-photos, autor a licencia v meta. Miesto musí sedieť ID aj
 * názvom, nesmie byť archivované a ešte nesmie mať obrázok.
 */
return new class extends Migration
{
    private const SOURCE = 'migration:2026_12_06_older_venue_photo';

    /** ID miesta => [názov, súbor, názov na Commons, autor, licencia, odkaz na licenciu]. */
    private const PHOTOS = [
        75 => ['Dóm sv. Štefana (Viedeň)', 'miesto-75.jpg', 'Wien, Stephansdom, der Hochaltar.JPG', 'Dguendel', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0'],
        93 => ['Žiar nad Hronom', 'miesto-93.jpg', 'Námestie matice slovenskej.jpg', 'DuPePhotographer', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        890 => ['Kostol svätého Štefana (Bratislava)', 'miesto-890.jpg', 'Bratislava - Kostol svätého Štefana -01.jpg', 'Ladislav Luppa', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
        141 => ['Minoritský kostol (Levoča)', 'miesto-141.jpg', 'Levoča - minoritský kostol - pohľad z východu.jpg', 'Patrik Kunec', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        151 => ['Kostol Narodenia Panny Márie (Dlhé diely)', 'miesto-151.jpg', 'Bratislava Dlhé diely kostol 02.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        990 => ['Kalvársky vrch', 'miesto-990.jpg', 'Kalvária Bratislava IX.jpg', 'Filip769', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        757 => ['Katolícka univerzita v Ružomberku', 'miesto-757.jpg', 'Library and main building of Catholic University in Ruzomberok.jpg', 'FrankSK 2014', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1024 => ['Spolok svätého Vojtecha', 'miesto-1024.jpg', 'Slovakia-Trnava-Spolok svateho Vojtecha.JPG', 'No machine-readable author provided. Rado Bahna~commonswiki assumed (based on co', 'Public domain', ''],
        1027 => ['Kostol sv. Kataríny Alexandrijskej', 'miesto-1027.jpg', 'Lozorno kostol 04.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1030 => ['Rotunda Spiegelsaal (Trnava)', 'miesto-1030.jpg', 'Trnava Spiegelssal 01.jpg', 'Reptalubos', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0'],
        1033 => ['Kultúrny dom Borovce', 'miesto-1033.jpg', 'Borovce kultúrny dom.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1034 => ['Právnická a administratívna fakulta Jagelovskej univerzity (Krakov)', 'miesto-1034.jpg', 'Collegium Novum UJ.jpg', 'Michalpe96', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1035 => ['Kostol Všetkých svätých', 'miesto-1035.jpg', 'Gotický kostol Všetkých svätých v Ludrovej Kúte.jpg', 'LeffJoni', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        1052 => ['Kostol sv. Jozefa robotníka (Svit)', 'miesto-1052.jpg', 'Kostol sv. Jozefa Robotníka, Svit 22 Slovakia.jpg', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        804 => ['Burianova medzierka (Žilina)', 'miesto-804.jpg', 'Burianova medzierka1, Žilina.jpg', 'Jurgi10.22', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        1060 => ['Katedrála Najsvätejšej Trojice (Žilina)', 'miesto-1060.jpg', 'Žilina, Katedrála Najsvätejšej Trojice, večer.jpg', 'LiMr', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
        1064 => ['Premonštrátsky kostol Najsvätejšej Trojice (Košice)', 'miesto-1064.jpg', 'Premon.jpg', 'André Savetier', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        71 => ['Bazilika Zoslania Ducha Svätého (Michalovce)', 'miesto-71.jpg', 'Bazilika Zoslania svätého Ducha (Michalovce) Slovakia9.JPG', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        81 => ['TIPOS Aréna (Bratislava)', 'miesto-81.jpg', 'Ondrej Nepela Arena.jpg', 'Pavol Frešo', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0'],
        683 => ['Mníšek nad Hnilcom', 'miesto-683.jpg', 'MniseknadHnilcom11Slovakia4.jpg', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        951 => ['Hora Butkov (Ladce)', 'miesto-951.jpg', 'Butkov and Ladce from Horovce.jpg', 'Jakub CA', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0'],
        961 => ['Kostol Všetkých svätých', 'miesto-961.jpg', 'Hlohovec kostol 03 06.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        968 => ['Kostol sv. Jána Krstiteľa (Vrútky)', 'miesto-968.jpg', 'Vrútky - rímsko-katolícky kostol.jpg', 'Patrik Kunec', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        718 => ['Evanjelický kostol (Hlboké)', 'miesto-718.jpg', 'Hlboké - evanjelický kostol.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        722 => ['Domov bielych hláv', 'miesto-722.jpg', '2025 Stará Turá Domnov bielých hláv.jpg', 'Qasinka', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
        724 => ['Spievajúca fontána (Košice)', 'miesto-724.jpg', 'Spievajúca fontána, Košice, 2026.jpg', 'Antimuonium', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        725 => ['Kostol sv. Anny (Šenkvice)', 'miesto-725.jpg', 'Šenkvice - Rímsko-katolícky kostol sv. Anny.jpg', 'Patrik Kunec', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        981 => ['Záhorské múzeum (Skalica)', 'miesto-981.jpg', 'Skalica - Záhorské múzeum - Nám. Slobody č. 13.JPG', 'Photograph: Patrik Kunec (talk)', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        730 => ['Park sv. Cyrila a Metoda', 'miesto-730.jpg', 'Súsošie Cyril a Metod Kapušany 18 Slovakia9.jpg', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        994 => ['Kostol Preblahoslavenej Panny Márie', 'miesto-994.jpg', 'Vráble kostol 01.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        995 => ['Svätyňa vo Fatime (Portugalsko)', 'miesto-995.jpg', 'Santuário de Fátima - panoramio (10).jpg', 'Mister No', 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0'],
        1008 => ['Penzión Augustineum (Bardejovské Kúpele)', 'miesto-1008.jpg', 'Bardejovské Kúpele, Augustineum - panoramio.jpg', 'Draco QQQ', 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0'],
        760 => ['Mestská hala (Prešov)', 'miesto-760.jpg', 'Mestská hala Prešov 18 Slovakia3.jpg', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1020 => ['Symbolický cintorín v Malej Fatre (Vrátna)', 'miesto-1020.jpg', 'Malá Fatra - symbolický cintorín.jpg', 'Pudelek (Marcin Szala)', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        19 => ['Evanjelický kostol (Brdárka)', 'miesto-19.jpg', 'Brdárka.jpg', 'PosoniumAster', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        150 => ['Štrbské pleso', 'miesto-150.jpg', 'Vysoké Tatry, Nové Štrbské pleso.JPG', 'Michal Klajban', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        1040 => ['Evanjelický kostol (Liptovský Mikuláš)', 'miesto-1040.jpg', 'Liptovsky Mikulas 05.jpg', 'Paweł Kuźniar (Jojo_1, Jojo)', 'CC BY-SA 3.0', 'http://creativecommons.org/licenses/by-sa/3.0/'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        foreach (self::PHOTOS as $id => [$name, $file, $title, $author, $license, $licenseUrl]) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->name !== $name || $venue->status === ModelStatus::Archived || $this->hasImage($venue)) {
                continue;
            }

            $path = __DIR__.'/assets/canal-venue-photos/'.$file;
            if (! is_file($path)) {
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
