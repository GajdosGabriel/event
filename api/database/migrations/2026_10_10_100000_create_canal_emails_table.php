<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kanál môže mať viac e-mailových adries (App\Services\Canals\CanalEmails).
 *
 * Každá adresa má vlastný stav doručiteľnosti; práve jedna použiteľná je
 * primárna a zrkadlí sa do `canals.email` / `email_verified_at`, ktoré ďalej
 * číta zvyšok aplikácie. Doterajší e-mail kanála sa prenesie ako primárny.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('canal_emails')) {
            return;
        }

        Schema::create('canal_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('canal_id');
            $table->string('email', 191);
            $table->boolean('is_primary')->default(false);
            // unverified = nič o nej nevieme, verified = schránka sa ozvala
            // (odkaz, odpoveď), undeliverable = e-mail sa vrátil.
            $table->string('status', 16)->default('unverified');
            // Odkiaľ adresu máme: manual, import, confirmed.
            $table->string('source', 16)->nullable();
            $table->timestamp('verified_at')->nullable();
            // Posledný dôkaz, že schránku niekto číta.
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            // hard = adresa neexistuje, soft = dočasné (plná schránka).
            $table->string('bounce_type', 8)->nullable();
            $table->string('bounce_reason', 250)->nullable();
            $table->unsignedSmallInteger('bounce_count')->default(0);
            $table->timestamps();

            $table->unique(['canal_id', 'email']);
            $table->index('email');
            $table->foreign('canal_id')->references('id')->on('canals')->cascadeOnDelete();
        });

        DB::table('canals')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($canals) {
                $rows = [];

                foreach ($canals as $canal) {
                    $email = mb_strtolower(trim((string) $canal->email));

                    if (mb_strlen($email) > 191 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    $rows[] = [
                        'canal_id' => $canal->id,
                        'email' => $email,
                        'is_primary' => true,
                        'status' => $canal->email_verified_at !== null ? 'verified' : 'unverified',
                        'source' => $canal->registration_source === 'import' ? 'import' : 'manual',
                        'verified_at' => $canal->email_verified_at,
                        'last_seen_at' => $canal->email_verified_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('canal_emails')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_emails');
    }
};
