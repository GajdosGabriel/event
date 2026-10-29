<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Miesta zle pripnuté na Pečenice.
 *
 * Import dal piatim kostolom z celého Slovenska tú istú vymyslenú adresu:
 * obec Pečenice, „ulica" 1552, PSČ 935 06 (1552 je id obce Kostolná pri Dunaji,
 * 935 06 PSČ Jabloňoviec — z textu článku nič z toho nepochádza). Migrácia
 * 2026_09_13_110000 im vtedy len odobrala falošnú polohu; obec a adresa ostali,
 * takže nové porovnávanie duplicít podľa adresy ich teraz drží za jedno miesto.
 *
 * Opravujú sa len miesta, ktorých skutočnú obec dokladá názov aj podujatia
 * na nich (Brdárka, Dechtice, Ružomberok, Bratislava). „Františkánsky kostol"
 * (32) zbiera podujatia z Fiľakova, Nových Zámkov a ďalších — jednu obec nemá,
 * preto stráca len falošnú adresu; rozdeliť ho musí človek.
 *
 * Mení sa len riadok, ktorý ešte nesie pôvodnú chybu (id + názov + Pečenice),
 * takže ručnú opravu neprepíše a opakované spustenie nič nespraví.
 */
return new class extends Migration
{
    private const PECENICE = 2612;

    /** Stred Slovenska — zástupná poloha (NationwideCoordinates). */
    private const SLOVAKIA = [48.7411522, 19.4528646];

    /** id => [názov, nová obec (null = ostáva), nová poloha (null = ostáva), zdroj polohy] */
    private const FIXES = [
        // Poloha je už presná (budova v Brdárke) — chybná bola len obec.
        19 => ['brdársky kostol', 262, null, null],
        // Katarínka leží v katastri Dechtíc; poloha ostáva zástupná, kým ju nedohľadá geokóder.
        83 => ['ruiny kostola a kláštora sv. Kataríny', 531, null, null],
        102 => ['Univerzitný kostol Svätej Rodiny', 3053, [49.0815718, 19.3034168], 'municipality'],
        // Poloha bol stred Pečeníc vydávaný za adresu.
        4 => ['kostol sv. Františka Minoriti', 242, [48.1516988, 17.1093063], 'municipality'],
        32 => ['františkánsky kostol', null, self::SLOVAKIA, 'municipality'],
    ];

    public function up(): void
    {
        foreach (self::FIXES as $id => [$name, $villageId, $point, $source]) {
            $update = ['street' => null, 'postcode' => null, 'updated_at' => now()];

            if ($villageId !== null) {
                $update['village_id'] = $villageId;
            }

            if ($point !== null) {
                [$update['latitude'], $update['longitude']] = $point;
                $update['coordinates_source'] = $source;
            }

            DB::table('venues')
                ->where('id', $id)
                ->where('name', $name)
                ->where('village_id', self::PECENICE)
                ->where('street', '1552')
                ->update($update);
        }
    }

    public function down(): void
    {
        // Vrátiť vymyslenú adresu nemá zmysel.
    }
};
