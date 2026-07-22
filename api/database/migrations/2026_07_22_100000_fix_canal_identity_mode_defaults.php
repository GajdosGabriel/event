<?php

use App\Enums\CanalIdentityMode;
use App\Enums\RegistrationSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Osobná identita patrí len kanálom, ktoré vznikli pri ručnej registrácii
 * používateľa. Importované kanály (farnosti, mestá, kluby zo scraperov) sa
 * doteraz kvôli defaultu stĺpca tvárili ako osobné — preklápame ich na
 * organizáciu a mením aj default, aby nový kanál nebol osobný "omylom".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('canals')
            ->where('registration_source', RegistrationSource::IMPORT->value)
            ->update(['identity_mode' => CanalIdentityMode::Organization->value]);

        DB::table('canals')
            ->where('registration_source', RegistrationSource::SELF->value)
            ->where('identity_mode', CanalIdentityMode::Pseudonymous->value)
            ->update(['identity_mode' => CanalIdentityMode::Personal->value]);

        Schema::table('canals', function (Blueprint $table) {
            $table->string('identity_mode', 32)
                ->default(CanalIdentityMode::Organization->value)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('canals', function (Blueprint $table) {
            $table->string('identity_mode', 32)
                ->default(CanalIdentityMode::Personal->value)
                ->change();
        });
    }
};
