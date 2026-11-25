<?php

namespace App\Services\Users;

use App\Enums\CanalIdentityMode;
use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Jobs\ImportSocialAvatarJob;
use App\Models\Canal;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Canals\CanalAuditor;
use App\Services\Canals\CanalEmails;
use App\Services\Canals\CanalMembership;
use Illuminate\Support\Str;

/**
 * Vytvorí používateľovi osobný kanál, ak ešte žiadny nemá.
 *
 * Jediné miesto, kde osobný kanál vzniká — volá ho UserObserver po overení
 * e-mailu aj AttendeeRegistrar pri založení účtu účastníka. Guard na
 * existujúci kanál zabezpečí, že sa nevytvorí druhýkrát.
 *
 * Kto sa registroval za organizáciu (PendingProfile::organization_name),
 * dostane popri osobnom kanáli aj organizačný. Osobný nesie meno človeka
 * (User::displayName, oslovenie v e-mailoch), organizačný názov spolku.
 */
class PersonalCanalProvisioner
{
    public function ensureFor(User $user): ?Canal
    {
        if ($user->canals()->exists()) {
            return null;
        }

        $pending = $user->pendingProfile()->first();
        $displayName = $pending?->display_name;
        if (! $displayName) {
            $displayName = Str::before($user->email, '@');
        }
        if ($displayName === '') {
            $displayName = 'User '.$user->id;
        }

        // Google/Facebook profil organizácie nesie jej názov, nie meno človeka
        // („Konfederácia politických väzňov Slovenska" prišla cez pozvánku na
        // svoj kanál). Osobný kanál s názvom organizácie by bol jej duplicitou —
        // účet dostane za aktívny kanál organizáciu až pri prevzatí
        // (CanalStewardship::complete).
        $canal = null;
        if (! ($pending?->display_name && $this->namesOrganization($displayName))) {
            $canal = Canal::create([
                'name' => $displayName,
                'email' => $user->email,
                'status' => 'published',
                'body' => 'Osobný kanál používateľa '.$displayName,
                'published_at' => now(),
                'registration_source' => RegistrationSource::SELF->value,
                'identity_mode' => CanalIdentityMode::Personal->value,
            ]);

            $this->attachOwner($user, $canal, 'personal_canal');
        }

        $organization = null;
        $organizationName = trim((string) ($pending?->organization_name ?? ''));
        if ($organizationName !== '') {
            $organization = Canal::create([
                // Obec sa ešte nevie — doplní ju CanalSeatDeriver podľa podujatí,
                // rovnako ako pri importovaných organizátoroch.
                'municipality_id' => Municipality::nationwideId(),
                'name' => $organizationName,
                'email' => $user->email,
                'status' => 'published',
                'published_at' => now(),
                'registration_source' => RegistrationSource::SELF->value,
                'identity_mode' => CanalIdentityMode::Organization->value,
            ]);

            $this->attachOwner($user, $organization, 'organization_registration');
        }

        $user->forgetCanalRoles();
        // Bez globálnej roly by nový vlastník neprešiel `permission:` middlewarom
        // na dashboard routách (viď CanalMembership).
        app(CanalMembership::class)->syncGlobalRoles($user);

        // users.canal_id ostáva osobný kanál — nesie meno človeka.
        $created = $canal ?? $organization;
        if ($created !== null) {
            $user->canal_id = $created->id;
            $user->save();
        }

        // Profilovka z Google/Facebooku. Sťahuje ju až job vo fronte, aby
        // prihlásenie nečakalo na cudzí server.
        $avatarUrl = trim((string) ($pending?->avatar_url ?? ''));
        if ($avatarUrl !== '' && $created !== null) {
            ImportSocialAvatarJob::dispatch((int) $created->id, $avatarUrl);
        }

        $pending?->delete();

        return $created;
    }

    /** Názov existujúceho organizačného kanála — nie je to meno človeka. */
    private function namesOrganization(string $name): bool
    {
        $slug = Str::slug($name);

        return $slug !== '' && Canal::query()
            ->where('identity_mode', CanalIdentityMode::Organization->value)
            ->where('slug', $slug)
            ->exists();
    }

    private function attachOwner(User $user, Canal $canal, string $reason): void
    {
        $user->canals()->attach($canal->id, [
            'is_owner' => true,
            'role' => CanalRole::Owner->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(CanalAuditor::class)->memberChanged($canal, $user, null, CanalRole::Owner, notify: false, context: ['reason' => $reason]);

        // Adresa kanála je e-mail účtu — overená, ak je overený účet.
        app(CanalEmails::class)->recordOwner($canal, $user);
    }
}
