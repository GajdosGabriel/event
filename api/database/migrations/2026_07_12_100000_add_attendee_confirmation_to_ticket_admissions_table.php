<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_admissions', function (Blueprint $table) {
            // Potvrdzovanie účasti pri vstupenkách objednaných pre iných účastníkov.
            // NULL = vstupenka nevyžaduje potvrdenie (patrí objednávateľovi).
            $table->string('confirmation_status', 20)->nullable()->after('status');
            $table->string('confirmation_token', 64)->nullable()->unique()->after('confirmation_status');
            $table->timestamp('confirmation_deadline_at')->nullable()->after('confirmation_token');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_deadline_at');

            // Rýchle vyhľadanie prošlých nepotvrdených rezervácií pre plánovač.
            $table->index(['confirmation_status', 'confirmation_deadline_at'], 'ticket_admissions_confirmation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_admissions', function (Blueprint $table) {
            $table->dropIndex('ticket_admissions_confirmation_idx');
            $table->dropColumn([
                'confirmation_status',
                'confirmation_token',
                'confirmation_deadline_at',
                'confirmed_at',
            ]);
        });
    }
};
