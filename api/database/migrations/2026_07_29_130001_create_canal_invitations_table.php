<?php

use App\Enums\CanalRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pozvánka do tímu kanála. Autorizáciou je token z e-mailu, prijať ju však
     * musí prihlásený účet s rovnakou adresou — preposlaný odkaz tak cudziemu
     * účtu prístup do kanála nedá.
     *
     * Prijaté pozvánky sa nemažú: sú dokladom, kto koho a kedy do kanála pustil.
     */
    public function up(): void
    {
        if (Schema::hasTable('canal_invitations')) {
            return;
        }

        Schema::create('canal_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('canal_id');
            $table->string('email');
            $table->enum('role', array_column(CanalRole::cases(), 'value'))
                ->default(CanalRole::Editor->value);
            $table->string('token', 64)->unique();
            // Kto pozval a kto pozvánku prijal. Pri zmazaní účtu záznam ostáva,
            // len stratí väzbu — inak by z auditu zmizla celá pozvánka.
            $table->unsignedInteger('invited_by_user_id')->nullable();
            $table->unsignedInteger('accepted_by_user_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('canal_id')->references('id')->on('canals')->cascadeOnDelete();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('accepted_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['canal_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_invitations');
    }
};
