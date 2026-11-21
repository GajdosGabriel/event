<?php

use App\Enums\CanalRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Rola `viewer` (Prehliadač) v tíme kanála: vidí podujatia, miesta, lístky
     * a objednávateľov, nič nemení. Rozšíri enum v pivote aj v pozvánkach
     * a doplní globálnu spatie rolu `canal-viewer` (seeder je idempotentný).
     */
    public function up(): void
    {
        $roles = array_column(CanalRole::cases(), 'value');

        Schema::table('canal_user', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->default(CanalRole::Editor->value)->change();
        });

        Schema::table('canal_invitations', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->default(CanalRole::Editor->value)->change();
        });

        (new RolesAndPermissionsSeeder)->run();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Späť len vtedy, keď rolu nikto nemá — inak by enum orezal hodnoty.
     */
    public function down(): void
    {
        $roles = ['owner', 'editor', 'checkin'];

        Schema::table('canal_user', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->default('editor')->change();
        });

        Schema::table('canal_invitations', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->default('editor')->change();
        });
    }
};
