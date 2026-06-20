<?php

use App\Enums\ModelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'status')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', array_map(fn ($s) => $s->value, ModelStatus::cases()))
                ->default(ModelStatus::Published->value)
                ->after('canal_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
