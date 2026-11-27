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
    private const SOURCE = 'migration:2026_12_05_older_venue_photo';

    /** ID miesta => [názov, súbor, názov na Commons, autor, licencia, odkaz na licenciu]. */
    private const PHOTOS = [
        167 => ['Bratislava', 'miesto-167.jpg', 'Bratislava - Hlavné námestie (radnica).jpg', 'Pudelek (Marcin Szala)', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        115 => ['Kostol Sedembolestnej Panny Márie (Žilina)', 'miesto-115.jpg', 'Farský kostol Sedembolestnej Panny Márie (Žilina, Vlčince).jpg', 'Vegetator', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        175 => ['Prešov', 'miesto-175.jpg', 'Námestie Prešov 17 Slovakia.jpg', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        4 => ['kostol sv. Františka Minoriti', 'miesto-4.jpg', 'Capital Bratislava Slovakia 0962.jpg', 'Jozef Kotulič', 'CC BY-SA 3.0', 'http://creativecommons.org/licenses/by-sa/3.0/'],
        23 => ['Tipsport Aréna (Banská Bystrica)', 'miesto-23.jpg', 'Zimný štadión Banská Bystrica 2022a.jpg', 'Matej Grochal', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        173 => ['Banská Bystrica', 'miesto-173.jpg', 'Banská Bystrica - Námestie SNP 004.jpg', 'Milan Bališin', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        61 => ['Ružomberok', 'miesto-61.jpg', 'Radnica Ružomberok.jpg', 'Branislav Turčina', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        73 => ['Spišské Podhradie', 'miesto-73.jpg', 'Mariánske námestie, Spišské Podhradie, Slovensko.jpg', 'Pedro', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0'],
        166 => ['Ábelová', 'miesto-166.jpg', 'Ábelová - Evanjelický kostol.jpg', 'Ladislav Luppa', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        538 => ['Malinovo', 'miesto-538.jpg', 'Kastiel Malinovo.jpg', 'Pavol Frešo', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0'],
        260 => ['Nové Mesto nad Váhom', 'miesto-260.jpg', 'Nové Mesto nad Váhom, námestie, fontána.jpg', 'Palickap', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        82 => ['Chrám Zosnutia Presvätej Bohorodičky (Klokočov)', 'miesto-82.jpg', 'Klokočov kostol 01.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        170 => ['Párnica', 'miesto-170.jpg', 'Párnica.JPG', 'Szeder László', 'CC BY-SA 3.0', 'http://creativecommons.org/licenses/by-sa/3.0/'],
        787 => ['Komunita Blahoslavenstiev (Okoličné)', 'miesto-787.jpg', 'Okoličné - kláštorný kostol sv. Petra z Alkantary - 2020.jpg', 'Patrik Kunec', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        184 => ['Detva', 'miesto-184.jpg', 'Detva múzeum.jpg', 'Lure', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        988 => ['Kaplnka sv. Kataríny (Bratislava)', 'miesto-988.jpg', 'Kaplnka sv Katariny.jpg', 'Lure', 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0'],
        1079 => ['Evanjelický kostol (Pezinok)', 'miesto-1079.jpg', 'Evanjelický a.v. kostol, Potočná ul., Pezinok, okres Pezinok 03.JPG', 'Horakvlado', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        863 => ['Evanjelický kostol (Trnava)', 'miesto-863.jpg', 'Evanjelický kostol Trnava.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        152 => ['Bazilika Mariazell (Rakúsko)', 'miesto-152.jpg', 'Basilika Mariazell 20171104 01.jpg', 'Uoaei1', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1037 => ['Kostol Svätej rodiny (Petržalka)', 'miesto-1037.jpg', 'Petržalka - kostol Svätej Rodiny.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1058 => ['Katedrála Božského Spasiteľa (Ostrava)', 'miesto-1058.jpg', 'Ostrava, katedrála Božského spasitele - pohled zepředu.JPG', 'Michal Klajban', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        1085 => ['Evanjelický kostol (Rožňava)', 'miesto-1085.jpg', 'Evanjelicky kostol (Roznava).jpg', 'IDD5000', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        168 => ['Košice', 'miesto-168.jpg', 'Hlavné námestie - panoramio (1).jpg', 'Mister No', 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0'],
        176 => ['Nitra', 'miesto-176.jpg', 'Nitra - Pribinovo namestie - St. Peter and Paul Church.jpg', 'Ingo Mehling', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        91 => ['Žilina', 'miesto-91.jpg', 'Žilina (Zsolna) - Mariánske námestie.jpg', 'Pudelek (Marcin Szala)', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        129 => ['Trnava', 'miesto-129.jpg', 'Slovakia - Trnava - Trojicne namestie a mestska veza RB01.jpg', 'Radovan Bahna', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        186 => ['Spišská Nová Ves', 'miesto-186.jpg', 'Spišská Nová Ves 16Slovakia82.JPG', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        52 => ['Smižany', 'miesto-52.jpg', 'Smižany kostol.jpg', 'Photograph: Robert Jahoda', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        265 => ['Šaštín - Stráže', 'miesto-265.jpg', 'Šaštín bazilika 14.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        269 => ['Badín', 'miesto-269.jpg', 'Badín - katolícky kostol Sv. Kataríny 01.JPG', 'Patrik Kunec (talk)', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        241 => ['Staré Hory', 'miesto-241.jpg', 'Slovakia Stare Hory 1.JPG', 'Jozef Kotulič', 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0'],
        157 => ['Turzovka', 'miesto-157.jpg', 'Turzovka, centrum mesta.jpg', 'Hornokysučan', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        733 => ['Kostol sv. Vincenta de Paul', 'miesto-733.jpg', 'Kostol Sv. Vincenta De Paul (43633857694).jpg', 'Janusz Jakubowski from Warsaw, Poland', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0'],
        356 => ['Michalovce', 'miesto-356.jpg', 'Námestie osloboditeľov Michalovce 19 Slovakia7.jpg', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        295 => ['Dolný Kubín', 'miesto-295.jpg', 'Dolný Kubín, Hviezdoslavovo námestie (1).jpg', 'Palickap', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        568 => ['Hrabovec', 'miesto-568.jpg', 'Hrabovec 25 Slovakia 4.jpg', 'Jozef Kotulič', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0'],
        147 => ['Brezno', 'miesto-147.jpg', 'Brezno namestie.jpg', 'Mišino Čech', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
        727 => ['Kostol Božieho milosrdenstva (Košice-KVP)', 'miesto-727.jpg', 'KostolKVP.jpg', 'John fitzburn', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        406 => ['Gelnica', 'miesto-406.jpg', 'Hauptplatz Gelnica 2011.jpg', 'Otzberger', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        823 => ['Bazilika svätého Mikuláša (Trnava)', 'miesto-823.jpg', 'Saint Nicolas\' Church, Trnava 2.jpg', 'Szombat78', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
        72 => ['Kostol sv. Mikuláša (Kovarce)', 'miesto-72.jpg', 'Kovarc templom 1.JPG', 'Taz666', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        998 => ['Kostol sv. Martina z Tours (Bošany)', 'miesto-998.jpg', 'Bošany - kostol.jpg', 'Mutichou', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        37 => ['Kalvária (Nitra)', 'miesto-37.jpg', 'Nitra kalvaria.jpg', 'Peter Porubcan (talk)', 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0'],
        973 => ['M-Aréna (Prešov)', 'miesto-973.jpg', 'M Aréna, Prešov 25 Slovakia 8.jpg', 'Jozef Kotulič', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0'],
        49 => ['S klub – Študentský domov Svoradov (Bratislava)', 'miesto-49.jpg', 'Bratislava Svoradova 13 01.jpg', 'Lure', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        982 => ['Námestie sv. Petra', 'miesto-982.jpg', 'Obelisk at the Saint Peter\'s Square (3395227787).jpg', 'Filip Maljković from Pancevo, Serbia', 'CC BY-SA 2.0', 'https://creativecommons.org/licenses/by-sa/2.0'],
        441 => ['Senec', 'miesto-441.jpg', 'Senec7.jpg', 'Wizzard', 'CC BY-SA 3.0', 'http://creativecommons.org/licenses/by-sa/3.0/'],
        413 => ['Poltár', 'miesto-413.jpg', 'Poltár - Námestie 1. mája -a.jpg', 'Ladislav Luppa', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        181 => ['Ľubietová', 'miesto-181.jpg', 'Lubietova.jpg', 'Lendert Van Laer', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        711 => ['Kostol Nanebovzatia Panny Márie – Notre Dame (Bratislava)', 'miesto-711.jpg', 'Bratislava - Kostol Nanebovzatia Panny Márie 20180510-01.jpg', 'Pymouss', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        967 => ['Konkatedrála Sedembolestnej Panny Márie (Poprad)', 'miesto-967.jpg', 'Poprad - konkatedrála Sedembolestnej Panny Márie.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        51 => ['Galanta', 'miesto-51.jpg', 'Galanta by night.jpg', 'Xmetov', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
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
