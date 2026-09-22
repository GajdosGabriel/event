<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ďalšie 3 kanály, ktoré sú duplicitami organizácií už overených
 * v predošlých migráciách — žiadny nový prieskum.
 */
return new class extends Migration
{
    private const CANALS = [
        // duplicita id=717/261 "Banskobystrické biskupstvo"
        389 => [
            'name' => 'Banskobystrická diecéza',
            'website' => [null, 'https://bbdieceza.sk'],
            'email' => [null, 'sekretariat.bb@rcc.sk'],
            'phone' => [null, '048 472 08 00'],
            'street' => [null, 'Námestie SNP 19'], 'postcode' => [null, '975 90'], 'country' => [null, 'Slovensko'],
        ],
        // primárne dáta KPVS (Vincentíni sú vedení samostatne, pozri kanál id=499)
        419 => [
            'name' => 'Konfederácia politických väzňov Slovenska a Misijná spoločnosť sv. Vincenta de Paul',
            'website' => [null, 'https://kpvs.forma.sk'],
            'email' => [null, 'kpvs.kpvs@gmail.com'],
            'phone' => [null, '02 5244 2321'],
            'street' => [null, 'Košická 5590/56'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
        ],
        // duplicita id=688 "Spoločenstvo Ladislava Hanusa"
        622 => [
            'name' => 'Spoločenstvo a Inštitút Ladislava Hanusa, Inštitút pre ľudské práva a rodinnú politiku, Asociácia za život a rodinu, Pro',
            'website' => [null, 'https://www.slh.sk'],
            'email' => [null, 'kancelaria@slh.sk'],
            'phone' => [null, '0904 806 696'],
            'street' => [null, 'Pavlovova 20'], 'country' => [null, 'Slovensko'],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::CANALS as $id => $row) {
            $this->applyContacts($id, $row, forward: true);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::CANALS as $id => $row) {
            $this->applyContacts($id, $row, forward: false);
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
};
