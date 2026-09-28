<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oslovenie organizátorov po akcii (App\Services\Canals\CanalOutreachSender).
     *
     *  - `email_suppressions` — adresy, ktoré si nevyžiadané e-maily odhlásili.
     *    Platí naprieč portálom pre všetky nevyžiadané správy (oslovenie,
     *    ponuka prevzatia pri prihláške), nie pre transakčné e-maily.
     *  - `canal_outreach` — kedy a s čím bol kanál oslovený; drží odstup
     *    medzi osloveniami a je dokladom, čo presne sme poslali.
     */
    public function up(): void
    {
        if (! Schema::hasTable('email_suppressions')) {
            Schema::create('email_suppressions', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('reason', 32);
                $table->string('source', 32)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('canal_outreach')) {
            Schema::create('canal_outreach', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('canal_id');
                $table->unsignedInteger('event_id')->nullable();
                $table->string('email');
                // `sent` = naozaj odišlo, `simulated` = len zapísané do denníka.
                $table->string('status', 16);
                $table->json('stats')->nullable();
                $table->unsignedBigInteger('invitation_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('canal_id')->references('id')->on('canals')->cascadeOnDelete();
                $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
                $table->foreign('invitation_id')->references('id')->on('canal_invitations')->nullOnDelete();
                $table->index(['canal_id', 'status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_outreach');
        Schema::dropIfExists('email_suppressions');
    }
};
