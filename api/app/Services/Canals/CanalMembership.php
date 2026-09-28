<?php

namespace App\Services\Canals;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\CanalNotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Členstvo v tíme kanála — jediné miesto, kde sa mení canal_user.role.
 *
 * Okrem pivotu drží zosúladené dve veci:
 *  - `is_owner`, aby staršie dotazy (ownedCanals/owners) videli to isté,
 *  - globálnu spatie rolu používateľa, lebo dashboard routy sú zatiaľ chránené
 *    `permission:` middlewarom. Tá je len hrubé sito („smie to niekde"),
 *    o konkrétnom kanáli rozhoduje až policy cez User::canInCanal().
 */
class CanalMembership
{
    public function __construct(
        private CanalAuditor $auditor = new CanalAuditor(),
    ) {}

    /**
     * Pridá alebo preradí člena. Nový člen dostane pivot status Draft — aktívny
     * kanál si prepína používateľ sám (DashboardUserController::setActiveCanal)
     * a pozvánka mu nemá prepnúť rozpracovanú prácu inde.
     */
    public function attach(Canal $canal, User $user, CanalRole $role): void
    {
        $previous = $this->roleOf($canal, $user);

        DB::transaction(function () use ($canal, $user, $role) {
            $exists = $canal->users()->where('users.id', $user->id)->exists();

            $attributes = [
                'role' => $role->value,
                'is_owner' => $role->isOwner(),
                'updated_at' => now(),
            ];

            if ($exists) {
                $canal->users()->updateExistingPivot($user->id, $attributes);
            } else {
                $canal->users()->attach($user->id, $attributes + [
                    'status' => ModelStatus::Draft->value,
                    'created_at' => now(),
                ]);
            }
        });

        $user->forgetCanalRoles();
        $this->syncGlobalRoles($user);

        $this->auditor->memberChanged($canal, $user, $previous, $role);
    }

    /**
     * Odobratie člena. Posledného vlastníka pustiť nesmieme — kanál by ostal bez
     * nikoho, kto ho vie spravovať alebo pozvať niekoho ďalšieho.
     */
    public function detach(Canal $canal, User $user): void
    {
        $this->guardLastOwner($canal, $user);

        $previous = $this->roleOf($canal, $user);

        DB::transaction(function () use ($canal, $user) {
            $canal->users()->detach($user->id);

            // Nastavenia notifikácií patria členstvu — pri návrate do tímu
            // má člen začať od predvolieb svojej novej roly.
            CanalNotificationSetting::query()
                ->where('canal_id', $canal->id)
                ->where('user_id', $user->id)
                ->delete();

            // Odchádzajúcemu členovi nesmie kanál ostať ako aktívny kontext.
            if ((int) $user->canal_id === (int) $canal->id) {
                $fallback = $user->canals()->where('canals.id', '!=', $canal->id)->first();
                $user->canal_id = $fallback?->id;
                $user->save();
            }
        });

        $user->forgetCanalRoles();
        $this->syncGlobalRoles($user);

        $this->auditor->memberChanged($canal, $user, $previous, null);
    }

    /** Zmena role existujúceho člena. */
    public function changeRole(Canal $canal, User $user, CanalRole $role): void
    {
        if (! $role->isOwner()) {
            $this->guardLastOwner($canal, $user);
        }

        $this->attach($canal, $user, $role);
    }

    /**
     * Globálne role odvodené z členstiev. Ostatné role (super-admin) ostávajú
     * nedotknuté — spravuje ich admin, nie členstvo v kanáli.
     */
    public function syncGlobalRoles(User $user): void
    {
        $derived = collect($user->canalRoleMap())
            ->map(fn (CanalRole $role) => $role->globalRole())
            ->unique()
            ->values();

        // Rolu, ktorá v inštalácii nie je zavedená, nepriraďujeme — spatie by
        // spadla na RoleDoesNotExist a rozbila by tým prijatie pozvánky.
        $existing = Role::query()
            ->whereIn('name', $derived->all())
            ->pluck('name');

        $keep = $user->getRoleNames()
            ->reject(fn (string $name) => in_array($name, CanalRole::globalRoles(), true));

        $user->syncRoles($keep->merge($existing)->unique()->values()->all());
    }

    private function roleOf(Canal $canal, User $user): ?CanalRole
    {
        $role = $canal->users()->where('users.id', $user->id)->first()?->pivot->role;

        return $role === null ? null : CanalRole::tryFrom((string) $role);
    }

    private function guardLastOwner(Canal $canal, User $user): void
    {
        $isOwner = $canal->users()
            ->where('users.id', $user->id)
            ->wherePivot('is_owner', true)
            ->exists();

        if (! $isOwner) {
            return;
        }

        $otherOwners = $canal->owners()->where('users.id', '!=', $user->id)->count();

        if ($otherOwners === 0) {
            throw ValidationException::withMessages([
                'user_id' => __('canal_team.last_owner'),
            ]);
        }
    }
}
