<?php

use App\Enums\CanalIdentityMode;
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
        Schema::table('canals', function (Blueprint $table) {
            $table->string('identity_mode', 32)
                ->default(CanalIdentityMode::Personal->value)
                ->after('registration_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canals', function (Blueprint $table) {
            $table->dropColumn('identity_mode');
        });
    }
};
