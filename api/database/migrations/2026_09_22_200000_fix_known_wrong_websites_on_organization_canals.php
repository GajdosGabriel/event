<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menšia, cielenejšia migrácia: namiesto plného obohatenia (web, email,
 * telefón, adresa, `body`) len opravuje kanály, ktorých pôvodný web bol
 * preukázateľne chybný — buď priamo `duplicita` už spracovaného kanála
 * (id=14, id=88 — rovnaká organizácia ako id=20 a id=902), alebo zdroj
 * importu namiesto vlastnej stránky (id=194, rovnaký vzor ako
 * hlascirkvi.sk pri desiatkach kanálov v predošlých migráciách).
 */
return new class extends Migration
{
    private const CANALS = [
        14 => [
            // duplicita id=20 "Východný dištrikt ECAV na Slovensku"
            'name' => 'VD ECAV',
            'website' => ['https://www.ecav.sk', 'https://www.vdecav.sk'],
            'email' => [null, 'sekretariat@vdecav.sk'],
            'phone' => [null, '051/772 25 15'],
            'street' => [null, 'Hlavná 137'], 'postcode' => [null, '080 01'], 'country' => [null, 'Slovensko'],
        ],
        88 => [
            // duplicita id=902 "Edukačno-misijné centrum ECAV"
            'name' => 'EMC ECAV',
            'municipality' => [2229, 242], // Modra -> Bratislava (skutočné sídlo)
            'website' => ['https://www.ecav.sk', 'https://www.edumiscentrum.sk'],
            'email' => [null, 'info@edumiscentrum.sk'],
            'phone' => [null, '+421 918 828 317'],
            'street' => [null, 'Palisády 46'], 'postcode' => [null, '811 06'], 'country' => [null, 'Slovensko'],
        ],
        194 => [
            // pôvodný web bol zdroj importu (hlascirkvi.sk), nie vlastná stránka; náhrada sa nenašla
            'name' => 'Spoločenstvo Dobrého pastiera',
            'website' => ['https://hlascirkvi.sk', null],
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
