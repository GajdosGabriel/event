<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stav overenia „živosti" jednej hodnoty jedného modelu — dnes webová adresa
 * kanála / miesta / podujatia / firmy, zajtra čokoľvek ďalšie.
 *
 * Prečo samostatná polymorfná tabuľka a nie stĺpce `website_status`,
 * `website_checked_at`… na štyroch tabuľkách: overovaných údajov bude
 * pribúdať a každý so sebou ťahá rovnakú päticu stĺpcov. Takto pridanie
 * ďalšieho atribútu neznamená ani migráciu, len novú sondu a zápis do
 * whitelistu (App\Models\AttributeCheck::TARGETS a ATTRIBUTES).
 *
 * Pozor na rozdiel oproti `contact_email_verifications`: tam sa overuje
 * **vlastníctvo** adresy (dokáž, že ju čítaš) a záznam po potvrdení zaniká.
 * Tu sa overuje **funkčnosť** hodnoty (odpovedá tá stránka?) a záznam žije,
 * kým žije hodnota — je to stav, nie rozpracovaný proces.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attribute_checks')) {
            return;
        }

        Schema::create('attribute_checks', function (Blueprint $table) {
            $table->id();
            // Plný názov triedy (default morph), rovnako ako `messages`.
            // Projekt zámerne nemá globálnu morph mapu — prepísať ju kvôli
            // jednej tabuľke by zmenilo zápis všetkým polymorfným vzťahom.
            // Krátke aliasy pre frontend a URL drží AttributeCheck::TARGETS.
            $table->string('checkable_type', 191);
            $table->unsignedInteger('checkable_id');
            // Názov atribútu na modeli, napr. 'website'.
            $table->string('attribute', 32);

            // Overovaná hodnota. Drží sa aj tu, lebo výsledok patrí ku
            // konkrétnej adrese — po zmene vo formulári je starý výsledok
            // neplatný a podľa tohto stĺpca sa to dá spoľahlivo zistiť.
            $table->string('value', 255);

            // pending / ok / failed — viď App\Enums\AttributeCheckStatus.
            // Reťazec, nie enum: pridanie stavu nemá vyžadovať zmenu schémy.
            $table->string('status', 16)->default('pending');

            // Počet neúspechov za sebou. Nuluje sa prvým úspechom. Podľa neho
            // sa určuje odstup ďalšieho pokusu aj to, kedy sa ozveme majiteľovi.
            $table->unsignedTinyInteger('failures')->default(0);

            // Posledná HTTP odpoveď a dôvod neúspechu (kľúč do prekladov,
            // nie surová hláška z knižnice — tú by nikto nečítal).
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('reason', 32)->nullable();

            $table->timestamp('checked_at')->nullable();
            // Kedy sa hodnota smie overiť znova. Riadi celý beh príkazu, preto
            // je indexovaný spolu so stavom.
            $table->timestamp('next_check_at')->nullable();
            // Kedy naposledy odišlo upozornenie majiteľovi.
            $table->timestamp('notified_at')->nullable();

            // Odkiaľ prišiel posledný podnet od návštevníka — cesta na našej
            // stránke, kde na odkaz klikol. Ide do e-mailu, aby majiteľ vedel,
            // kde presne odkaz visí. Nikdy nie cudzia adresa (viď controller).
            $table->string('reported_from', 191)->nullable();
            $table->timestamp('reported_at')->nullable();

            $table->timestamps();

            // Jedna hodnota = jeden riadok. Bráni tomu, aby súbežné uloženie
            // formulára a klik návštevníka založili dva stavy tej istej veci.
            $table->unique(['checkable_type', 'checkable_id', 'attribute'], 'attribute_checks_target_unique');
            $table->index(['status', 'next_check_at']);
        });

        $this->backfillWebsites();
    }

    /**
     * Zaeviduje adresy, ktoré v databáze už sú.
     *
     * Bez toho by sa overovali len tie, ktorých sa niekto po nasadení dotkne —
     * teda tie, o ktoré sa organizátor stará, kým roky ležiace odkazy
     * z importu by nikto nepreveril. Práve tie sú pritom najpodozrivejšie.
     *
     * Riadky idú ako `pending` s rozostupom: overenie je HTTP dotaz na cudzí
     * server a spustiť ich naraz stovky by vyzeralo ako útok. Príkaz berie
     * dávku každých päť minút, `next_check_at` mu určí poradie.
     */
    private function backfillWebsites(): void
    {
        $tables = [
            \App\Models\Canal::class => 'canals',
            \App\Models\Venue::class => 'venues',
            \App\Models\Event::class => 'events',
            \App\Models\Organization::class => 'organizations',
        ];

        $now = now();
        $offset = 0;

        foreach ($tables as $class => $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'website')) {
                continue;
            }

            DB::table($table)
                ->select('id', 'website')
                ->whereNotNull('website')
                ->where('website', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($class, $now, &$offset) {
                    $insert = [];

                    foreach ($rows as $row) {
                        $insert[] = [
                            'checkable_type' => $class,
                            'checkable_id' => $row->id,
                            'attribute' => 'website',
                            'value' => mb_substr((string) $row->website, 0, 255),
                            'status' => 'pending',
                            'failures' => 0,
                            // Dvadsať adries na päťminútovú dávku.
                            'next_check_at' => $now->copy()->addMinutes(intdiv($offset++, 20) * 5),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($insert !== []) {
                        DB::table('attribute_checks')->insert($insert);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_checks');
    }
};
