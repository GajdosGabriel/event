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
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use App\Enums\ModelStatus;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes, HasCommonFilters;

    protected $appends = ['canal'];

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
            ->withPivot(['is_owner', 'status'])
            ->withTimestamps();
    }

    public function ownedCanals()
    {
        return $this->belongsToMany(Canal::class)
            ->wherePivot('is_owner', true);
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

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function pendingProfile()
    {
        return $this->hasOne(PendingProfile::class);
    }
}
