<?php

namespace App\Repositories\Eloquent;

use App\Models\PendingProfile;
use App\Models\User;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\UserRepository;
use Carbon\Carbon;
use Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use LogicException;

class EloquentUserRepository extends AbstractRepository implements UserRepository
{
    public function entity(): string
    {
        return User::class;
    }

    public function createUserRegisterForm($data)
    {
        $user = $this->create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'registered_via' => $data['registered_via'] ?? 'local',
        ]);

        if (! empty($data['display_name'])) {
            PendingProfile::create([
                'user_id' => $user->id,
                'display_name' => $data['display_name'],
            ]);
        }

        return $user;
    }

    public function createUserBySocial($value)
    {
        $provider = $value->provider ?? 'google';

        $user = $this->create([
            'email' => $value->email,
            'password' => Hash::make(rand(8, 10)),
            'email_verified_at' => Carbon::now(),
            'registered_via' => $provider,
            'provider_id' => $value->id ?? null,
        ]);

        if (! empty($value->name)) {
            PendingProfile::create([
                'user_id' => $user->id,
                'display_name' => $value->name,
            ]);

            $canal = $user->canals()->first();
            if ($canal) {
                $canal->update(['name' => $value->name]);
            }
        }

        return $user;
    }

    public function checkIfUserAccountExist($request)
    {
        if (auth()->check()) {
            return;
        }

        if ($user = User::whereEmail($request->email)->first()) {
            return Auth::login($user, true);
        }

        $this->createNewUser($request);
    }

    protected function createNewUser($request)
    {
        $user = new User([
            'email' => $request->email,
            'password' => bcrypt('registracnyformularheslo'),
            'registered_via' => 'local',
        ]);
        $user->save();
        Auth::login($user, true);

        $this->sendConfirmEmail($user);
    }

    protected function sendConfirmEmail($user)
    {
        if ($user->email_verified_at == null) {
            // Notification::send($user, new ConfirmEmail($user));
        }
    }

    public function usersHasRoleAdmin()
    {
        return $this->model()->whereHas('roles', function ($query) {
            $query->whereId(2);
        })->get();
    }

    public function usersEmailable()
    {
        return $this->model()->whereSendEmail(1);
    }

    public function adminIndexQuery()
    {
        return $this->latestFirst($this->model()->withTrashed());
    }

    public function dashboardIndexQuery()
    {
        $authUser = auth('sanctum')->user();
        $canalIds = $authUser->canals()->pluck('canals.id');

        return $this->latestFirst(
            $this->model()->withTrashed()->where(function ($query) use ($authUser, $canalIds) {
                $query->where('id', $authUser->id)
                    ->orWhereIn('canal_id', $canalIds);
            })
        );
    }

    public function dashboardShow($id)
    {
        $user = $this->dashboardIndexQuery()->where('id', $id)->firstOrFail();
        Gate::authorize('view', $user);

        return $user;
    }

    /**
     * Verejný zoznam používateľov neexistuje a existovať nemá — na rozdiel od
     * kanálov či podujatí nie je používateľ verejný obsah. Metódu vyžaduje
     * InterfaceRepository, takže sa nedá vypustiť; namiesto ticho fungujúceho
     * dotazu radšej spadne, aby sa nedala omylom zavesiť na verejnú routu.
     */
    public function publicIndexQuery()
    {
        throw new LogicException('Používatelia nemajú verejný zoznam.');
    }

    /**
     * To isté pre detail — zdedená implementácia v AbstractRepository vracia
     * ktorýkoľvek účet podľa id, čo je bez prihlásenia to isté ako zoznam,
     * len po jednom.
     */
    public function publicShow($id)
    {
        throw new LogicException('Používatelia nemajú verejný detail.');
    }
}
