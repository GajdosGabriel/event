<?php

namespace App\Models;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Models\Traits\HasCheckedAttributes;
use App\Models\Traits\HasCommonFilters;
use App\Models\Traits\HasVerifiableEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fakturačná identita organizátora.
 *
 * Organizácia NIE je tenant a nemá vlastný tím — členstvo drží `canal_user`
 * aj s rolami a pozvánkami. Organizácia visí na kanáloch cez
 * `canals.organization_id` a ďalej na Account cez `account_uuid`:
 *
 *     User ──canal_user(role)── Canal ──organization_id──▶ Organization ──account_uuid──▶ Account
 *
 * Kanál bez organizácie je bežný stav (osobný kanál z registrácie) a znamená
 * neplatený režim — viď Canal::hasPaidAccess().
 */
class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasCheckedAttributes, HasCommonFilters, HasFactory, HasVerifiableEmail, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    protected $casts = [
        'title' => \App\Casts\StringLength250::class,
        'website' => \App\Casts\Website::class,
        'published_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'account_synced_at' => 'datetime',
        'status' => \App\Enums\ModelStatus::class,
    ];

    /** Firma má protipól v Accounte, takže sa dajú čítať fakturačné údaje. */
    public function isLinkedToAccount(): bool
    {
        return filled($this->account_uuid);
    }

    /**
     * Smie sa na túto organizáciu fakturovať?
     *
     * Jediné miesto, kde sa to rozhoduje. Nestačí `account_uuid` — archivovaná
     * ani zmazaná organizácia nesmie ďalej odomykať platené funkcie, inak by
     * kanál po archivácii firmy tíško fungoval ďalej bez protistrany na faktúre.
     */
    public function canBill(): bool
    {
        return $this->isLinkedToAccount()
            && $this->status === ModelStatus::Published
            && $this->deleted_at === null;
    }

    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    /** Kanály, ktoré fakturujú pod touto organizáciou. */
    public function canals(): HasMany
    {
        return $this->hasMany(Canal::class);
    }

    /**
     * Podujatia organizácie — cez jej kanály, lebo `events` nesie `canal_id`,
     * nie `organization_id`. Obsah patrí kanálu; organizácia je len fakturačná
     * strecha nad ním.
     */
    public function events(): HasManyThrough
    {
        return $this->hasManyThrough(
            Event::class,
            Canal::class,
            'organization_id', // canals.organization_id
            'canal_id',        // events.canal_id
            'id',
            'id',
        );
    }

    /**
     * Ľudia, ktorí majú k organizácii prístup.
     *
     * Zámerne to NIE je Eloquent relácia: členstvo nevisí na organizácii, ale
     * na jej kanáloch (`canal_user`), a Eloquent na dvojitý hop cez pivot
     * natívnu reláciu nemá. Vraciame query, aby sa dalo ďalej filtrovať —
     * eager loading (`with('members')`) tu preto nefunguje a ani nemá zmysel.
     *
     * @return Builder<User>
     */
    public function members(): Builder
    {
        return User::query()->whereIn('users.id', $this->memberIdsQuery());
    }

    /**
     * Vlastníci — komu sa posiela faktúra a kto smie meniť fakturačné údaje.
     *
     * @return Builder<User>
     */
    public function owners(): Builder
    {
        return User::query()->whereIn('users.id', $this->memberIdsQuery(CanalRole::Owner));
    }

    /**
     * Komu sa ozveme, keď firemný údaj (dnes web) prestane fungovať.
     *
     * Organizácia nie je Messageable — verejné „Poslať správu" na fakturačnú
     * identitu nemieri, píše sa kanálu. Upozornenie z overovania ale adresáta
     * potrebuje, a je ním vlastník: fakturačné údaje smie meniť len on.
     */
    public function attributeIssueRecipient(): ?User
    {
        return $this->owners()->get()->first(fn (User $owner) => $owner->canReceiveMessages());
    }

    /** Poddotaz s ID členov naprieč kanálmi organizácie. */
    private function memberIdsQuery(?CanalRole $role = null): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('canal_user')
            ->select('canal_user.user_id')
            ->join('canals', 'canals.id', '=', 'canal_user.canal_id')
            ->where('canals.organization_id', $this->getKey())
            ->whereNull('canals.deleted_at')
            ->where('canal_user.status', ModelStatus::Published->value);

        if ($role !== null) {
            $query->where('canal_user.role', $role->value);
        }

        return $query;
    }
}
