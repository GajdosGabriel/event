<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Účty založené automaticky z údajov účastníka na vstupenke.
        DB::statement("ALTER TABLE users MODIFY registered_via ENUM('local','google','facebook','ticket') NOT NULL DEFAULT 'local'");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET registered_via = 'local' WHERE registered_via = 'ticket'");
        DB::statement("ALTER TABLE users MODIFY registered_via ENUM('local','google','facebook') NOT NULL DEFAULT 'local'");
    }
};
