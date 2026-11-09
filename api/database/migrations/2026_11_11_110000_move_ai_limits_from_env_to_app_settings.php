<?php

use App\Support\AppSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limity AI vyhľadávania sa už nečítajú z .env — prenesú sa do app_settings,
 * odkiaľ ich mení admin na stránke Spotreba AI.
 *
 * Hodnoty sa berú z configu v čase migrácie. Pri nasadení je to ešte config
 * z cache predchádzajúceho nasadenia (deploy.sh spúšťa migrate pred
 * config:cache), teda s hodnotami z .env. Uloží sa len hodnota, ktorú .env
 * menil oproti predvolenej — predvolené netreba držať v DB. Už uložené
 * nastavenie sa neprepíše.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'profile_enrichment.enabled' => true,
        'profile_enrichment.monthly_limit_usd' => 1.0,
        'canals.emails.discovery.ai' => true,
    ];

    public function up(): void
    {
        $stored = DB::table('app_settings')->pluck('key')->all();

        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = config($key);
            if ($value === null || in_array($key, $stored, true)) {
                continue;
            }
            $changed = is_bool($default) ? (bool) $value !== $default : (float) $value !== $default;
            if ($changed) {
                $values[$key] = $value;
            }
        }

        if ($values !== []) {
            AppSettings::set($values);
        }
    }

    public function down(): void
    {
        // Hodnoty v app_settings ostávajú; config bez .env má predvolené hodnoty.
    }
};
