<?php

use App\Enums\CanalClaimMethod;
use App\Enums\CanalClaimStatus;
use App\Enums\CanalIdentityMode;
use App\Models\Canal;
use App\Models\CanalClaim;
use App\Models\User;
use App\Services\Canals\CanalClaims;
use App\Services\Canals\CanalMembership;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dve organizácie, ktoré mali popri svojom kanáli druhý, založený pri registrácii.
 *
 * CYRILOMETODIADA: používateľ #8 (adresa na doméne cyrilometodiada.sk) si pri
 * registrácii založil kanál #1956 „CYRILOMETODIADA, o. z." a pridal doň podujatie,
 * hoci importovaný kanál #276 s 32 podujatiami už existoval. Pozvánka na prevzatie
 * #276 išla na inú adresu spolku. #8 dostane #276 ako prevzatie schválené adminom
 * (so lehotou na námietku ako pri bežnej žiadosti) a #1956 sa doň zlúči.
 *
 * Konfederácia politických väzňov Slovenska: účet #6 sa registroval Google
 * profilom organizácie cez pozvánku na #419, takže jeho osobný kanál #1948 dostal
 * názov organizácie. Osobný kanál nesie meno človeka — prázdny #1948 sa zmaže
 * a aktívnym kanálom účtu bude prevzatý #419. Ďalším takým registráciám bráni
 * PersonalCanalProvisioner.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->handOverCyrilometodiada();
        $this->dropOrganizationNamedPersonalCanal();
    }

    private function handOverCyrilometodiada(): void
    {
        $target = Canal::query()->find(276);
        $duplicate = Canal::query()->find(1956);
        $user = User::query()->find(8);
        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))->orderBy('id')->first();

        if (! $target || ! $duplicate || ! $user || ! $admin
            || $target->name !== 'CYRILOMETODIADA' || $duplicate->name !== 'CYRILOMETODIADA, o. z.'
            || ! str_ends_with(mb_strtolower((string) $user->email), '@cyrilometodiada.sk')
            || ! $duplicate->users()->where('users.id', $user->id)->wherePivot('is_owner', true)->exists()) {
            return;
        }

        $claims = app(CanalClaims::class);

        // Prevzatie je hotové, keď prebehne transakcia; chyba neskoršieho kroku
        // (synchronizácia adresára v Accounte) nesmie zastaviť zlúčenie.
        if ($claims->isClaimable($target)) {
            try {
                $claim = CanalClaim::query()->create([
                    'canal_id' => $target->id,
                    'user_id' => $user->id,
                    'method' => CanalClaimMethod::AdminReview->value,
                    'status' => CanalClaimStatus::Pending->value,
                    'message' => 'Účet z domény cyrilometodiada.sk; pri registrácii založil duplicitný kanál #1956.',
                ]);
                $claims->approve($claim, $admin, 'Adresa účtu na doméne spolku, duplicitný kanál #1956 sa zlučuje.');
            } catch (Throwable $e) {
                Recorder::warning('canals', 'claim_followup_failed', 'Prevzatie kanála #276: doplnkový krok zlyhal',
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }

        try {
            // Zlučuje sa len to, čo už spravuje ten istý človek.
            $target->refresh();
            if ((int) $target->claimed_by_user_id !== (int) $user->id) {
                return;
            }

            // Prevzatie zakladá členstvo ako koncept; merger chce rovnaké členstvá.
            $status = DB::table('canal_user')->where('canal_id', $target->id)->where('user_id', $user->id)->value('status');
            DB::table('canal_user')->where('canal_id', $duplicate->id)->where('user_id', $user->id)->update(['status' => $status]);

            app(ResourceMerger::class)->merge('canals', $duplicate->id, $target->id, $admin->id);
        } catch (Throwable $e) {
            Recorder::warning('admin', 'resource.merge_skipped', 'Odovzdanie kanála #276 a zlúčenie #1956 preskočené',
                subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
        }
    }

    private function dropOrganizationNamedPersonalCanal(): void
    {
        $personal = Canal::query()->find(1948);
        $organization = Canal::query()->find(419);
        $user = User::query()->find(6);

        if (! $personal || ! $organization || ! $user
            || $personal->identity_mode !== CanalIdentityMode::Personal
            || $personal->name !== $organization->name
            || (int) $organization->claimed_by_user_id !== (int) $user->id
            || DB::table('events')->where('canal_id', $personal->id)->exists()
            || DB::table('canal_user')->where('canal_id', $personal->id)->where('user_id', '!=', $user->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($personal, $organization, $user) {
            $user->forceFill(['canal_id' => $organization->id])->save();
            DB::table('canal_user')->where('canal_id', $personal->id)->delete();
            DB::table('canal_emails')->where('canal_id', $personal->id)->delete();
            $personal->delete();
        });

        $user->forgetCanalRoles();
        app(CanalMembership::class)->syncGlobalRoles($user);

        Recorder::info('canals', 'personal_canal_removed', "Osobný kanál #{$personal->id} s názvom organizácie zmazaný, aktívny kanál účtu je #{$organization->id}",
            status: 'ok', subject: $organization, userId: $user->id);
    }

    public function down(): void
    {
        // Prevzatie aj zlúčenie sa vracajú ručne (admin: námietka / obnova kanála).
    }
};
