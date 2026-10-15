<?php

use App\Enums\FileType;
use App\Models\Event;
use App\Models\File;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * Päť posledných minulých podujatí bez obrázka (stav k 15. 10. 2026) dostane
 * skutočnú fotku z Wikimedia Commons — miesto konania alebo hosťa podujatia.
 * Súbory ležia vedľa migrácie v assets/past-event-photos, zmenšené na 1500 px.
 *
 * Fotky sú pod licenciami CC, ktoré (okrem CC0) vyžadujú uviesť autora. Autor,
 * licencia a odkaz na zdroj sa preto ukladajú do `meta.attribution` súboru.
 *
 * Podujatia sa hľadajú podľa ID a zároveň názvu — vznikli dávno pred rozdielmi
 * medzi databázami, ale keby ID ukazovalo inam, radšej sa nestane nič. Kto už
 * medzitým obrázok má, toho sa migrácia nedotkne.
 */
return new class extends Migration
{
    private const SOURCE = 'migration:2026_10_15_commons_photo';

    private const PHOTOS = [
        10904 => [
            'name' => '20.výročie blahorečenia Sáry Salkaházi',
            'file' => 'sara-salkahazi-kosice.jpg',
            'attribution' => [
                'title' => 'Košice - Dóm sv. Alžbety - Sára Salkaházi',
                'author' => 'Ladislav Luppa',
                'license' => 'CC BY-SA 4.0',
                'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
                'source_url' => 'https://commons.wikimedia.org/wiki/File:Ko%C5%A1ice_-_D%C3%B3m_sv._Al%C5%BEbety_-_S%C3%A1ra_Salkah%C3%A1zi.jpg',
            ],
        ],
        10952 => [
            'name' => 'Modlitba chvál pred Eucharistiou',
            'file' => 'partizanske-kostol-interier.jpg',
            'attribution' => [
                'title' => 'Partizánske - kostol Božského Srdca Ježišovho interiér 1',
                'author' => 'Venca24',
                'license' => 'CC BY-SA 3.0',
                'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0',
                'source_url' => 'https://commons.wikimedia.org/wiki/File:Partiz%C3%A1nske_-_kostol_Bo%C5%BEsk%C3%A9ho_Srdca_Je%C5%BEi%C5%A1ovho_interi%C3%A9r_1.jpg',
            ],
        ],
        10534 => [
            'name' => 'Duchovná obnova pre bezdetné manželské páry',
            'file' => 'donovaly.jpg',
            'attribution' => [
                'title' => 'Donovaly 972 m.n.m',
                'author' => 'Michal Jakubský',
                'license' => 'CC BY 3.0',
                'license_url' => 'https://creativecommons.org/licenses/by/3.0',
                'source_url' => 'https://commons.wikimedia.org/wiki/File:Donovaly_972_m.n.m_-_panoramio.jpg',
            ],
        ],
        107 => [
            'name' => 'Online modlitby s pátrom Jamesom Manjackalom',
            'file' => 'james-manjackal.jpg',
            'attribution' => [
                'title' => '206 Manjackal teaches',
                'author' => 'Stebunik',
                'license' => 'CC0',
                'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                'source_url' => 'https://commons.wikimedia.org/wiki/File:206_Manjackal_teaches.jpg',
            ],
        ],
        300 => [
            'name' => 'Rádio LUMEN pozýva na Deň modlitieb a spoločenstva na Staré Hory',
            'file' => 'stare-hory-bazilika.jpg',
            'attribution' => [
                'title' => 'Staré Hory 22 Slovakia 8',
                'author' => 'Ing. Mgr. Jozef Kotulič',
                'license' => 'CC BY-SA 4.0',
                'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
                'source_url' => 'https://commons.wikimedia.org/wiki/File:Star%C3%A9_Hory_22_Slovakia_8.jpg',
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('files')) {
            return;
        }

        foreach (self::PHOTOS as $id => $photo) {
            $event = Event::query()->whereKey($id)->where('name', $photo['name'])->first();

            if ($event === null || $event->has_primary_image) {
                continue;
            }

            $path = __DIR__.'/assets/past-event-photos/'.$photo['file'];

            if (! is_file($path)) {
                continue;
            }

            $event->addFiles(
                files: new UploadedFile($path, $photo['file'], 'image/jpeg', null, true),
                type: FileType::IMAGE,
                makePrimary: true,
                meta: [
                    'source' => self::SOURCE,
                    'attribution' => $photo['attribution'],
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        File::query()
            ->where('fileable_type', (new Event)->getMorphClass())
            ->whereIn('fileable_id', array_keys(self::PHOTOS))
            ->where('meta->source', self::SOURCE)
            ->get()
            ->each(fn (File $file) => $file->delete());
    }
};
