<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Traits\HasCommonFilters;
use Illuminate\Notifications\Notifiable;
use App\Models\Canal;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use App\Enums\CanalIdentityMode;
use App\Enums\CanalRole;
use App\Enums\ModelStatus;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes, HasCommonFilters;

    protected $appends = ['canal'];

    /** @var array<int, CanalRole>|null Memoizované členstvá, viď canalRoleMap(). */
    private ?array $canalRoleMap = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'registered_via',
        'provider_id',
        'blocked_at',
        'blocked_until',
        'blocked_reason',
        'last_login_at',
        'last_activity',
        'canal_id',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'email',
        'password',
        'remember_token',
        'provider_id',
        'blocked_reason',
    ];

    protected static function booted()
    {
        static::creating(function (self $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'blocked_at' => 'datetime',
            'blocked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'last_activity' => 'datetime',
            'status' => \App\Enums\ModelStatus::class,
        ];
    }

    public function canals()
    {
        return $this->belongsToMany(Canal::class)
            ->withPivot(['is_owner', 'status', 'role'])
            ->withTimestamps();
    }

    public function ownedCanals()
    {
        return $this->belongsToMany(Canal::class)
            ->wherePivot('is_owner', true);
    }

    /**
     * Rola používateľa v konkrétnom kanáli, alebo null ak v ňom nie je.
     *
     * Osobný kanál (users.canal_id) sa berie ako vlastnený aj bez pivotu —
     * dashboardCanalIds() ho tam púšťa tiež a bez toho by si používateľ nevedel
     * spravovať vlastný kanál, kým sa pivot nedoplní.
     */
    public function canalRole(int $canalId): ?CanalRole
    {
        $roles = $this->canalRoleMap();

        if (isset($roles[$canalId])) {
            return $roles[$canalId];
        }

        return (int) $this->canal_id === $canalId ? CanalRole::Owner : null;
    }

    /** Smie používateľ v tomto kanáli robiť danú vec? (viď CanalRole::abilities) */
    public function canInCanal(int $canalId, string $ability): bool
    {
        return $this->canalRole($canalId)?->allows($ability) ?? false;
    }

    /** Má aspoň jeden kanál, v ktorom smie danú vec? Pre policy `create`. */
    public function hasAnyCanalAbility(string $ability): bool
    {
        return $this->canalIdsWithAbility($ability)->isNotEmpty();
    }

    /**
     * ID kanálov, v ktorých smie danú vec. Pre policies nad vzťahmi, kde sa
     * kanál nedá zistiť z modelu priamo (napr. miesto zdieľané viacerými kanálmi).
     *
     * @return Collection<int, int>
     */
    public function canalIdsWithAbility(string $ability): Collection
    {
        $ids = collect($this->canalRoleMap())
            ->filter(fn (CanalRole $role) => $role->allows($ability))
            ->keys();

        if ($this->canal_id !== null && CanalRole::Owner->allows($ability)) {
            $ids->push((int) $this->canal_id);
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * Členstvá načítame raz za request — policies sa pýtajú na každý riadok
     * výpisu a inak by z toho bolo N+1 na canal_user.
     *
     * @return array<int, CanalRole>
     */
    public function canalRoleMap(): array
    {
        if ($this->canalRoleMap !== null) {
            return $this->canalRoleMap;
        }

        $rows = DB::table('canal_user')
            ->where('user_id', $this->id)
            ->pluck('role', 'canal_id');

        $map = [];
        foreach ($rows as $canalId => $role) {
            $map[(int) $canalId] = CanalRole::tryFrom((string) $role) ?? CanalRole::Editor;
        }

        return $this->canalRoleMap = $map;
    }

    /** Po zmene členstva sa musí zahodiť memoizácia z canalRoleMap(). */
    public function forgetCanalRoles(): void
    {
        $this->canalRoleMap = null;
    }

    /**
     * Je účet blokovaný? Trvalo (blocked_until = null), alebo dočasne kým
     * blocked_until neuplynie. Rovnaká sémantika ako is_blocked v UserResource.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null
            && ($this->blocked_until === null || $this->blocked_until->isFuture());
    }

    /**
     * Môže tento účet posielať správy cez „Poslať správu"?
     * Len overený (potvrdený e-mail) a neblokovaný — anti-spam: každá správa
     * má dohľadateľného odosielateľa, hostia neposielajú vôbec.
     */
    public function canSendMessages(): bool
    {
        return $this->email_verified_at !== null
            && ! $this->isBlocked();
    }

    /**
     * Môže tento účet správy prijímať (byť príjemcom „Poslať správu")?
     * Aktívny = má e-mail, sám si ho overil a nie je blokovaný. Neaktívne
     * účty (založené z lístka/importu, neoverené) správy nedostávajú.
     */
    public function canReceiveMessages(): bool
    {
        return $this->email !== null
            && $this->email_verified_at !== null
            && ! $this->isBlocked();
    }

    public function canal()
    {
        return $this->belongsTo(Canal::class);
    }

    public function getCanalAttribute()
    {
        $canal = $this->canal()->first();
        if ($canal) {
            return $canal;
        }

        return $this->canals()
            ->wherePivot('status', ModelStatus::Published->value)
            ->first();
    }

    public function dashboardCanalIds(bool $withTrashed = true): Collection
    {
        $canals = $this->canals();

        if ($withTrashed) {
            $canals = $canals->withTrashed();
        }

        $ids = $canals->pluck('canals.id');

        if ($this->canal_id !== null) {
            $ids->push((int) $this->canal_id);
        }

        return $ids
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Meno používateľa pre výpisy (tím kanála, členovia).
     *
     * Účet sám meno nedrží — nesie ho osobný kanál založený pri registrácii,
     * prípadne ešte nespracovaný PendingProfile. Adresa je až posledná záchrana
     * a aj tak len jej časť pred zavináčom, nikdy nie celá.
     */
    public function displayName(): string
    {
        $personal = $this->canals()
            ->where('canals.identity_mode', CanalIdentityMode::Personal->value)
            ->value('canals.name');

        $name = trim((string) ($personal ?? $this->pendingProfile()->value('display_name') ?? ''));

        if ($name !== '') {
            return $name;
        }

        $local = trim(Str::before((string) $this->email, '@'));

        return $local !== '' ? $local : 'Používateľ #' . $this->id;
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function pendingProfile()
    {
        return $this->hasOne(PendingProfile::class);
    }
}
