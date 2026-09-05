<?php

namespace Database\Seeders;

use App\Enums\TagGroup;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Číselník obsahových štítkov.
 *
 * Zoznam je zámerne v kóde, nie v admin CRUD: mení sa raz za čas, patrí do
 * verzovania a AI ho dostáva ako uzavretý enum v JSON schéme — takže rozšírenie
 * číselníka a preštítkovanie musia ísť spolu. Kandidátov na doplnenie ukazuje
 * tabuľka tag_suggestions (zbiera ich app:events-ai-tag).
 *
 * Seeder je idempotentný — dá sa spustiť opakovane aj na živej databáze,
 * priradenia v event_tag ostávajú nedotknuté.
 */
class TagSeeder extends Seeder
{
    /**
     * @var array<string, array<int, array{0: string, 1: string, 2: string}>>  facet => [[slug, názov, emoji], …]
     */
    private const TAGS = [
        TagGroup::Format->value => [
            ['koncert', 'Koncert', '🎤'],
            ['divadlo', 'Divadlo', '🎭'],
            ['vystava', 'Výstava', '🖼️'],
            ['festival', 'Festival', '🎪'],
            ['prednaska', 'Prednáška', '🎓'],
            ['workshop', 'Workshop', '🛠️'],
            ['turnaj', 'Turnaj', '🏆'],
            ['ples', 'Ples', '💃'],
            ['jarmok', 'Jarmok', '🎠'],
            ['svata-omsa', 'Svätá omša', '⛪'],
            ['premietanie', 'Premietanie', '🎬'],
            ['tura', 'Túra', '🥾'],
            ['sutaz', 'Súťaž', '🥇'],
            // Doplnené podľa tag_suggestions po prvom backfille — modelu tieto
            // formy v číselníku chýbali najčastejšie (púť 11×, seminár/kurz 11×,
            // modlitba 8×, diskusia 4×, duchovná obnova 4×).
            ['put', 'Púť', '🙏'],
            ['seminar', 'Seminár alebo kurz', '📖'],
            ['diskusia', 'Diskusia alebo beseda', '💬'],
            ['modlitba', 'Modlitbové stretnutie', '📿'],
            ['duchovna-obnova', 'Duchovná obnova', '🕯️'],
            // Druhá vlna podľa tag_suggestions. Formy, ktoré modelu chýbali
            // najčastejšie po tom, čo sa naimportovali cirkevné zdroje
            // (duchovné cvičenia 38×, spoločenstvo 34×, chvály 24×,
            // konferencia 21×, adorácia 13×).
            //
            // „Duchovné cvičenia" sú zámerne samostatne od „Duchovnej obnovy":
            // v bežnom úze je to viacdňové exercície verzus jednorazová obnova,
            // a model ich rozlišoval sám od seba.
            ['duchovne-cvicenia', 'Duchovné cvičenia', '🧘'],
            ['spolocenstvo', 'Stretnutie spoločenstva', '👥'],
            ['chvaly', 'Chvály', '🙌'],
            ['konferencia', 'Konferencia', '🏛️'],
            ['adoracia', 'Adorácia', '🕯️'],
        ],
        TagGroup::Topic->value => [
            ['hudba', 'Hudba', '🎵'],
            ['folklor', 'Folklór', '🪗'],
            ['rock', 'Rock', '🎸'],
            ['klasika', 'Klasická hudba', '🎻'],
            ['jazz', 'Jazz', '🎷'],
            ['dychovka', 'Dychovka', '🎺'],
            ['tanec', 'Tanec', '🩰'],
            ['sport', 'Šport', '⚽'],
            ['umenie', 'Umenie', '🎨'],
            ['historia', 'História', '🏛️'],
            ['priroda', 'Príroda', '🌳'],
            ['gastro', 'Jedlo a pitie', '🍲'],
            ['viera', 'Viera', '🙏'],
            ['literatura', 'Literatúra', '📚'],
            // Tiež z tag_suggestions (psychológia 23×, evanjelizácia 14×).
            // „Spiritualita" a „duchovné stretnutie" sa medzi návrhmi držali
            // vyššie, ale do číselníka nešli: prekrývajú sa s `viera`
            // a s formami vyššie natoľko, že by sa v UI nedali odlíšiť.
            ['psychologia', 'Psychológia', '🧠'],
            ['evanjelizacia', 'Evanjelizácia', '📣'],
        ],
        TagGroup::Audience->value => [
            ['pre-deti', 'Pre deti', '🧒'],
            ['pre-rodiny', 'Pre rodiny', '👨‍👩‍👧'],
            ['pre-mladez', 'Pre mládež', '🧑'],
            ['pre-seniorov', 'Pre seniorov', '👵'],
            ['pre-odbornikov', 'Pre odborníkov', '🧑‍💼'],
        ],
        TagGroup::Attribute->value => [
            // Tento facet sa neposiela do AI (odvádza ho EventAttributeDeriver
            // z termínu, ceny a kľúčových slov), takže názvy môžu byť krátke —
            // slúžia len ako popisky čipov v UI.
            ['vonku', 'Vonku', '🌤️'],
            ['vstup-volny', 'Vstup voľný', '🆓'],
            ['s-registraciou', 'S registráciou', '📝'],
            ['viacdnove', 'Viacdňové', '📅'],
            ['online', 'Online', '💻'],
        ],
    ];

    public function run(): void
    {
        foreach (self::TAGS as $group => $tags) {
            foreach ($tags as $index => [$slug, $name, $emoji]) {
                Tag::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'group' => $group,
                        'name' => $name,
                        'emoji' => $emoji,
                        'sort_order' => $index,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
