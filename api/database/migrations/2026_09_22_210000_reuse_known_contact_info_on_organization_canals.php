<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doplní web (a kde je isté, aj email/telefón/adresu) pre 17 ďalších
 * neosobných kanálov, ktoré sú duplicitami organizácií už overených
 * v predošlých backfill_contact_info_on_*_organization_canals.php
 * migráciách — žiadny nový prieskum, len opätovné použitie už
 * potvrdených údajov. Súčasť zúženého postupu pre zvyšok kanálov
 * (len kanály s aspoň 2 udalosťami, bez rozšíreného `body`).
 */
return new class extends Migration
{
    /**
     * @var array<int, array{
     *   name: string,
     *   municipality?: array{0:int,1:int},
     *   website?: array{0:?string,1:?string},
     *   email?: array{0:?string,1:string},
     *   phone?: array{0:?string,1:string},
     *   street?: array{0:?string,1:string},
     *   postcode?: array{0:?string,1:string},
     *   country?: array{0:?string,1:string},
     * }>
     */
    private const CANALS = [
        // duplicita id=845/533/259 "Saleziáni dona Bosca"
        153 => [
            'name' => 'Saleziáni don Bosca',
            'website' => [null, 'https://saleziani.sk'],
            'email' => [null, 'sekretariat@saleziani.sk'],
            'phone' => [null, '+421 2 554 22 800'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
        ],
        448 => [
            'name' => 'Saleziáni don Bosca a Saleziánsky pastoračný tím',
            'website' => [null, 'https://saleziani.sk'],
            'email' => [null, 'sekretariat@saleziani.sk'],
            'phone' => [null, '+421 2 554 22 800'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=360 "Saleziáni, Domka a spoločenstvo Tymian"
        976 => [
            'name' => 'Saleziáni, Domka, Spoločenstvo Tymian, diecézne duchovenstvo',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava
            'website' => [null, 'https://www.domka.sk'],
            'email' => [null, 'sekretariat@domka.sk'],
            'phone' => [null, '+421 903 296 526'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=926/584 "Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru"
        595 => [
            'name' => 'Rada pre vedu, vzdelanie a kultúru Konferencie biskupov Slovenska',
            'website' => [null, 'https://kultura.kbs.sk'],
            'email' => [null, 'tajomnik@kultura.kbs.sk'],
            'phone' => [null, '+421 2 5920 6501'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=218 "Biskupstvo Nitra"
        235 => [
            'name' => 'Nitrianske biskupstvo s farnosťou sv. Urbana',
            'website' => [null, 'https://www.biskupstvo-nitra.sk'],
            'email' => [null, 'nitra@kbs.sk'],
            'phone' => [null, '+421 37 772 17 47'],
            'street' => [null, 'Nám. Jána Pavla II. 7'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=67 "Košická eparchia"
        122 => [
            'name' => 'Gréckokatolícka eparchia Košice',
            'municipality' => [1455, 1565], // Klokočov (len pútnické miesto) -> Košice
            'website' => [null, 'https://www.grkatke.sk'],
            'email' => [null, 'eparchia@grkatke.sk'],
            'phone' => [null, '+421 940 985 460'],
            'street' => [null, 'Dominikánske námestie 2/A'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=675/184 "Rímskokatolícka bohoslovecká fakulta Univerzity Komenského"
        440 => [
            'name' => 'Rímskokatolícka cyrilometodská bohoslovecká fakulta Univerzity Komenského v Bratislave',
            'website' => [null, 'https://frcth.uniba.sk'],
            'email' => [null, 'sd@frcth.uniba.sk'],
            'phone' => [null, '+421 2 32 777 120'],
            'street' => [null, 'Kapitulská 26'], 'postcode' => [null, '814 58'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=1031 "Bratia Dominikáni – Farnosť Bratislavská Kalvária"
        400 => [
            'name' => 'Bratia dominikáni - farnosť Bratislava Kalvária, Komunita redemptoristov v Bratislave',
            'website' => [null, 'https://kalvaria.sk'],
            'email' => [null, 'kalvaria@kalvaria.sk'],
            'phone' => [null, '0908 090 799'],
            'street' => [null, 'Na Kalvárii 10'], 'postcode' => [null, '811 04'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=602/526 "Pastoračné centrum Anny Kolesárovej – Domček"
        132 => [
            'name' => 'Domček – Pastoračné centrum Anky Kolesárovej',
            'website' => [null, 'https://domcek.org'],
            'email' => [null, 'domcek@domcek.org'],
            'phone' => [null, '+421 911 912 598'],
            'street' => [null, 'Vysoká nad Uhom 27'], 'postcode' => [null, '072 14'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=686/355 "katRande"
        233 => [
            'name' => 'katRande',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava
            'website' => [null, 'https://katrande.org'],
            'email' => [null, 'office@katrande.org'],
            'phone' => [null, '+421 910 911 686'],
        ],
        // duplicita id=302/461 "Spoločenstvo Extrémnej krížovej cesty (EKC)"
        783 => [
            'name' => 'Spoločenstvo EKC na Slovensku',
            'website' => [null, 'https://ekc.sk'],
            'email' => [null, 'ekc.slovensko@gmail.com'],
            'phone' => [null, '+421 907 695 490'],
        ],
        785 => [
            'name' => 'Spoločenstvo Extrémnej Krížovej Cesty na Slovensku',
            'website' => [null, 'https://ekc.sk'],
            'email' => [null, 'ekc.slovensko@gmail.com'],
            'phone' => [null, '+421 907 695 490'],
        ],
        // duplicita id=1012 "Sestry Congregatio Jesu"
        530 => [
            'name' => 'Congregatio Jesu',
            'website' => [null, 'https://congregatiojesu.com'],
            'email' => [null, 'ruzomberokcj@gmail.com'],
            'phone' => [null, '+421 44 430 46 39'],
            'street' => [null, 'J. Sladkého 28'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=202/938 "CYRILOMETODIADA, o. z."
        1051 => [
            'name' => 'CYRILOMETODIADA, o. z., nadácia PRO PATRIA a Spolok Srbov na Slovensku',
            'municipality' => [3596, 251], // Trnava -> Bratislava - Petržalka
            'website' => [null, 'https://www.cyrilometodiada.sk'],
            'email' => [null, 'cyrilometodiada@cyrilometodiada.sk'],
            'phone' => [null, '+421 907 817 323'],
            'street' => [null, 'Vlastenecké námestie 1186/10'], 'postcode' => [null, '851 01'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=151 "Občianske združenie Nenápadní hrdinovia"
        408 => [
            'name' => 'OZ Nenápadní hrdinovia',
            'website' => [null, 'https://www.nenapadnihrdinovia.sk'],
            'email' => [null, 'info@nenapadnihrdinovia.sk'],
            'street' => [null, 'Robotnícka 7'], 'postcode' => [null, '831 03'], 'country' => [null, 'Slovensko'],
        ],
        // celoslovenský rád dominikánov (zdroj opakovane používaný pri výskume iných kanálov, napr. id=887, id=1031)
        954 => [
            'name' => 'Rehoľa bratov kazateľov (bratia dominikáni)',
            'website' => [null, 'https://dominikani.sk'],
        ],
        7 => [
            'name' => 'bratia dominikáni',
            'website' => [null, 'https://dominikani.sk'],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::CANALS as $id => $row) {
            $this->applyContacts($id, $row, forward: true);
            $this->applyMunicipality($id, $row, forward: true);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::CANALS as $id => $row) {
            $this->applyContacts($id, $row, forward: false);
            $this->applyMunicipality($id, $row, forward: false);
        }
    }

    private function applyContacts(int $id, array $row, bool $forward): void
    {
        $fields = ['website', 'email', 'phone', 'street', 'postcode', 'country'];
        $query = DB::table('canals')->where('id', $id)->where('name', $row['name']);
        $update = [];
        $touched = false;

        foreach ($fields as $field) {
            if (! isset($row[$field])) {
                continue;
            }

            [$old, $new] = $row[$field];
            $from = $forward ? $old : $new;
            $to = $forward ? $new : $old;

            $from === null
                ? $query->whereNull($field)
                : $query->where($field, $from);

            $update[$field] = $to;
            $touched = true;
        }

        if ($touched) {
            $update['updated_at'] = now();
            $query->update($update);
        }
    }

    private function applyMunicipality(int $id, array $row, bool $forward): void
    {
        if (! isset($row['municipality'])) {
            return;
        }

        [$old, $new] = $row['municipality'];
        $from = $forward ? $old : $new;
        $to = $forward ? $new : $old;

        DB::table('canals')->where('id', $id)->where('name', $row['name'])
            ->where('municipality_id', $from)
            ->update(['municipality_id' => $to, 'updated_at' => now()]);
    }
};
