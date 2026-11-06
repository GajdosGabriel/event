<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doplní kontaktný e-mail kanálom, ktoré ho nemali (ručné dohľadanie 6. 11. 2026).
 *
 * Najnovšie kanály bez e-mailu, ktoré automatické dopĺňanie profilov
 * (profile_enrichments) ešte nehľadalo, a kanonické kanály zo zlúčenia
 * 2026_11_06_100000. Zdroj je pri každej adrese: oficiálny web organizácie
 * alebo text jej vlastného podujatia. Brali sa len všeobecné adresy
 * organizácie, nie osobné adresy jednotlivcov.
 *
 * Adresa je neoverená (nikto sa z nej ešte neozval) — kanál ňou získava len
 * kontakt na ponuku prevzatia, nie „Opýtať sa organizátora".
 *
 * Mení sa len kanál, ktorý e-mail stále nemá, nie je zmazaný a adresa nie je
 * vyradená (email_suppressions). down() adresu odoberie, ak je stále tá istá.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: ?string}> id => [e-mail, web na doplnenie] */
    private const EMAILS = [
        1944 => ['charitaroznava@caritas.sk', 'https://charitaroznava.sk'], // výročná správa + text podujatia
        1942 => ['info@worshipster.sk', 'https://worshipster.sk'],          // worshipster.sk
        1940 => ['nadacia@slovakiachristiana.sk', null],                    // slovakiachristiana.sk
        1934 => ['info.ozlevada@gmail.com', 'https://www.levada.sk'],       // levada.sk
        1930 => ['info@malerodinkovo.sk', null],                            // malerodinkovo.sk
        1929 => ['mmpodujatia@gmail.com', null],                            // text podujatia
        1927 => ['klastorba@frantiskani.sk', 'https://frantiskani.sk/bratislava'], // frantiskani.sk/bratislava
        1923 => ['info@svetovednimladeze.sk', null],                        // svetovednimladeze.sk
        1922 => ['inquiries@wydseoul.org', null],                           // text podujatia
        1918 => ['info@biznisranajky.sk', null],                            // biznisranajky.sk
        1916 => ['wojtyla.shop@gmail.com', null],                           // wojtyla.sk
        1912 => ['rkfu.spisskebystre@gmail.com', null],                     // spisskebystre.fara.sk
        1906 => ['info@verbisti.sk', null],                                 // text podujatia
        1902 => ['biskupstvo@kapitula.sk', 'https://www.kapitula.sk'],      // kapitula.sk
        1895 => ['sklub@svoradov.org', null],                               // text podujatia
        1892 => ['kancelaria@maranathapo.sk', 'https://maranathapo.sk'],    // maranathapo.sk (Prešov)
        1891 => ['cliptime@mladezba.sk', null],                             // cliptime.sk
        1890 => ['info@hrdinarodinu.sk', null],                             // text podujatia
        1884 => ['info@donacia.sk', null],                                  // donacia.sk
        1872 => ['centrumabrahaminfo@gmail.com', null],                     // text podujatia
        1859 => ['info@pramen.sk', null],                                   // text podujatia
        // Kanonické kanály zo zlúčenia 2026_11_06_100000:
        70 => ['kosice@maranathapo.sk', null],                              // maranathapo.sk (pobočka Košice)
        802 => ['rettore@kolegiumrim.sk', null],                            // text podujatia
        48 => ['abu@grkatpo.sk', null],                                     // grkatpo.sk (arcibiskupský úrad)
        537 => ['info@santegidio.sk', 'https://www.santegidio.sk'],         // santegidio.sk
        562 => ['milujucanaruc@gmail.com', null],                           // text podujatia (Milujúca náruč)
        688 => ['kancelaria@slh.sk', null],                                 // slh.sk
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::EMAILS as $id => [$email, $website]) {
            DB::transaction(fn () => $this->fill($id, $email, $website));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::EMAILS as $id => [$email]) {
            if (Schema::hasTable('canal_emails')) {
                DB::table('canal_emails')->where('canal_id', $id)->where('email', $email)
                    ->whereNull('verified_at')->delete();
            }

            DB::table('canals')->where('id', $id)->where('email', $email)->whereNull('email_verified_at')
                ->update(['email' => null, 'updated_at' => now()]);
        }
    }

    private function fill(int $id, string $email, ?string $website): void
    {
        $canal = DB::table('canals')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();

        if ($canal === null || trim((string) $canal->email) !== '') {
            return;
        }

        if (Schema::hasTable('email_suppressions') && DB::table('email_suppressions')->where('email', $email)->exists()) {
            return;
        }

        $update = ['email' => $email, 'updated_at' => now()];

        if ($website !== null && trim((string) $canal->website) === '') {
            $update['website'] = $website;
        }

        DB::table('canals')->where('id', $id)->update($update);

        if (! Schema::hasTable('canal_emails')) {
            return;
        }

        // Kanál bez e-mailu nemá použiteľnú primárnu adresu — nová ňou bude.
        DB::table('canal_emails')->where('canal_id', $id)->update(['is_primary' => false]);

        if (DB::table('canal_emails')->where('canal_id', $id)->where('email', $email)->exists()) {
            DB::table('canal_emails')->where('canal_id', $id)->where('email', $email)->update(['is_primary' => true, 'updated_at' => now()]);
        } else {
            DB::table('canal_emails')->insert([
                'canal_id' => $id,
                'email' => $email,
                'is_primary' => true,
                'status' => 'unverified',
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Primárnu adresu a stĺpec canals.email dorovná CanalEmails.
        if (($model = Canal::query()->find($id)) !== null) {
            app(CanalEmails::class)->refresh($model);
        }
    }
};
