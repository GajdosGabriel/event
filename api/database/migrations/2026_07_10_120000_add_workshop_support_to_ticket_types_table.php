<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_types', 'kind')) {
                $table->string('kind', 20)->default('ticket')->after('name');
            }

            if (! Schema::hasColumn('ticket_types', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('description');
            }

            if (! Schema::hasColumn('ticket_types', 'ends_at')) {
                $table->dateTime('ends_at')->nullable()->after('starts_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            foreach (['kind', 'starts_at', 'ends_at'] as $column) {
                if (Schema::hasColumn('ticket_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
