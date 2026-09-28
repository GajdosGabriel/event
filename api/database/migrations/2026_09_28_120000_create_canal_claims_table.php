<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Žiadosti o prevzatie kanála (App\Services\Canals\CanalClaims).
     *
     * Každé prevzatie — aj cez pozvánku — tu má riadok: je to doklad, kto
     * kanál prevzal, ako to preukázal a dokedy ho môže kontaktná adresa
     * napadnúť. Riadky sa nemažú.
     */
    public function up(): void
    {
        if (Schema::hasTable('canal_claims')) {
            return;
        }

        Schema::create('canal_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('canal_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');
            // Adresa, ktorá preukázala prístup (kontakt kanála / adresa pozvánky).
            $table->string('contact_email')->nullable();
            $table->text('message')->nullable();
            // Token potvrdenia z kontaktnej schránky a token námietky.
            $table->string('token', 64)->nullable()->unique();
            $table->string('contest_token', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('contest_until')->nullable();
            $table->timestamp('contested_at')->nullable();
            $table->text('contest_note')->nullable();
            $table->unsignedInteger('decided_by_user_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('invitation_id')->nullable();
            $table->timestamps();

            $table->foreign('canal_id')->references('id')->on('canals')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('invitation_id')->references('id')->on('canal_invitations')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['canal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_claims');
    }
};
