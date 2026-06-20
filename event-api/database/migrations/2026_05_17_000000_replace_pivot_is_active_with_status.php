<?php

use App\Enums\ModelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceIsActiveWithStatus('canal_user');
        $this->replaceIsActiveWithStatus('canal_venue');
        $this->replaceIsActiveWithStatus('organization_user');
    }

    public function down(): void
    {
        $this->replaceStatusWithIsActive('canal_user');
        $this->replaceStatusWithIsActive('canal_venue');
        $this->replaceStatusWithIsActive('organization_user');
    }

    private function replaceIsActiveWithStatus(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'status')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->enum('status', array_map(fn($status) => $status->value, ModelStatus::cases()))
                ->default(ModelStatus::Published->value)
                ->after('is_owner');
        });

        if (Schema::hasColumn($table, 'is_active')) {
            DB::table($table)->update([
                'status' => DB::raw("CASE WHEN is_active = 1 THEN 'published' ELSE 'draft' END"),
            ]);

            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }

    private function replaceStatusWithIsActive(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'is_active')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('is_owner');
        });

        if (Schema::hasColumn($table, 'status')) {
            DB::table($table)->update([
                'is_active' => DB::raw("CASE WHEN status = 'published' THEN 1 ELSE 0 END"),
            ]);

            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
    }
};
