<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kanál 1892 („M-Aréna a spoločenstvo MaranaTha") mal z importu z vyveska.sk
 * tri miesta, ktoré opisujú ten istý areál na Švábskej 22 v Prešove:
 *
 *  - 973 „Švábska 22/A, Prešov" – M Aréna (kanonické, ostáva),
 *  - 974 „Švábska 22, Prešov" – Komunitno-pastoračné centrum sv. Jána Pavla II.
 *    (duplicita, zlúči sa do 973),
 *  - 10 „Prešov, Konštantínova 2" – zástupná adresa mestského úradu zdieľaná
 *    s kanálmi 2, 114 a 147; podujatie „Kurz Manželské večery" sa tam dostalo
 *    omylom (v zdroji je „M Aréna, Švábska 22/A"). Miesto 10 ostáva zachované
 *    pre ostatné kanály, odpojí sa len od kanála 1892.
 *
 * Z duplicity sa do kanonického miesta preberie všetko užitočné: popis
 * (doplní sa o údaje o pastoračnom centre) a prípadné chýbajúce kontaktné
 * údaje. Duplicita sa soft-deletne, nie fyzicky vymaže.
 *
 * down() duplicitu obnoví a vráti eventy a väzbu na pôvodné miesta; popis
 * kanonického miesta sa späť nevracia (jednosmerný prepis).
 */
return new class extends Migration
{
    private const CANAL_ID = 1892;

    private const CANONICAL_VENUE_ID = 973;

    private const DUPLICATE_VENUE_ID = 974;

    private const PLACEHOLDER_VENUE_ID = 10;

    private const PLACEHOLDER_EVENT_ID = 11059;

    public function up(): void
    {
        if (! Schema::hasTable('venues') || ! Schema::hasTable('canal_venue')) {
            return;
        }

        $canonical = DB::table('venues')->where('id', self::CANONICAL_VENUE_ID)->whereNull('deleted_at')->first();
        $duplicate = DB::table('venues')->where('id', self::DUPLICATE_VENUE_ID)->whereNull('deleted_at')->first();

        if ($canonical !== null && $duplicate !== null) {
            $this->mergeDuplicate($canonical, $duplicate);
        }

        if ($canonical !== null) {
            $this->fixPlaceholderVenue();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('venues') || ! Schema::hasTable('canal_venue')) {
            return;
        }

        DB::table('venues')->where('id', self::DUPLICATE_VENUE_ID)->update(['deleted_at' => null]);

        DB::table('canal_venue')->insertOrIgnore([
            'canal_id' => self::CANAL_ID,
            'venue_id' => self::DUPLICATE_VENUE_ID,
            'is_owner' => 1,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pôvodne bola na 974 viazaná práve jedna udalosť.
        DB::table('events')->where('id', 11063)->update(['venue_id' => self::DUPLICATE_VENUE_ID]);

        DB::table('events')->where('id', self::PLACEHOLDER_EVENT_ID)
            ->update(['venue_id' => self::PLACEHOLDER_VENUE_ID]);

        DB::table('canal_venue')->insertOrIgnore([
            'canal_id' => self::CANAL_ID,
            'venue_id' => self::PLACEHOLDER_VENUE_ID,
            'is_owner' => 0,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mergeDuplicate(object $canonical, object $duplicate): void
    {
        $update = ['updated_at' => now()];

        // Prázdne polia kanonického miesta sa doplnia z duplicity.
        foreach (['street', 'postcode', 'website', 'email', 'phone', 'capacity', 'opening_hours', 'category'] as $column) {
            if (($canonical->{$column} === null || $canonical->{$column} === '') && $duplicate->{$column} !== null && $duplicate->{$column} !== '') {
                $update[$column] = $duplicate->{$column};
            }
        }

        $update['body'] = '<p>M Aréna je krytá športová hala v Prešove na Švábskej 22/A. Slúži na konanie športových podujatí a ďalších verejných podujatí. '
            .'V areáli na Švábskej sa nachádza aj Komunitno-pastoračné centrum sv. Jána Pavla II. (Švábska 22), ktoré slúži na komunitné a pastoračné aktivity, stretnutia a podujatia.</p>';

        DB::table('venues')->where('id', $canonical->id)->update($update);

        DB::table('events')->where('venue_id', $duplicate->id)
            ->update(['venue_id' => $canonical->id, 'updated_at' => now()]);

        // Iné kanály, ktoré by mali duplicitu, ju prepoja na kanonické miesto.
        foreach (DB::table('canal_venue')->where('venue_id', $duplicate->id)->get() as $link) {
            DB::table('canal_venue')->insertOrIgnore([
                'canal_id' => $link->canal_id,
                'venue_id' => $canonical->id,
                'is_owner' => $link->is_owner,
                'status' => $link->status,
                'created_at' => $link->created_at,
                'updated_at' => now(),
            ]);
        }
        DB::table('canal_venue')->where('venue_id', $duplicate->id)->delete();

        if (Schema::hasTable('content_reviews')) {
            DB::table('content_reviews')
                ->where('reviewable_type', 'like', '%Venue')
                ->whereIn('reviewable_id', [$duplicate->id, $canonical->id])
                ->delete();
        }

        DB::table('venues')->where('id', $duplicate->id)->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Podujatie „Kurz Manželské večery" je podľa zdroja v M Aréne, nie na
     * zástupnej adrese mestského úradu.
     */
    private function fixPlaceholderVenue(): void
    {
        $moved = DB::table('events')
            ->where('id', self::PLACEHOLDER_EVENT_ID)
            ->where('canal_id', self::CANAL_ID)
            ->where('venue_id', self::PLACEHOLDER_VENUE_ID)
            ->update(['venue_id' => self::CANONICAL_VENUE_ID, 'updated_at' => now()]);

        $stillUsed = DB::table('events')
            ->where('canal_id', self::CANAL_ID)
            ->where('venue_id', self::PLACEHOLDER_VENUE_ID)
            ->whereNull('deleted_at')
            ->exists();

        if ($moved > 0 || ! $stillUsed) {
            DB::table('canal_venue')
                ->where('canal_id', self::CANAL_ID)
                ->where('venue_id', self::PLACEHOLDER_VENUE_ID)
                ->delete();
        }
    }
};
