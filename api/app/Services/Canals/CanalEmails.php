<?php

namespace App\Services\Canals;

use App\Enums\CanalEmailStatus;
use App\Enums\CanalIdentityMode;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalEmail;
use App\Models\CanalEmailSearch;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Services\SystemLog\Recorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * E-mailové adresy kanála (tabuľka `canal_emails`).
 *
 * Kanál ich môže mať viac, každá má vlastný stav (CanalEmailStatus). Práve
 * jedna **použiteľná** je primárna a zrkadlí sa do `canals.email` /
 * `email_verified_at` — tie číta zvyšok aplikácie (oslovenie, otázky,
 * prevzatie, prihlášky), takže vždy píše na najlepšiu adresu, akú poznáme.
 *
 * Primárnu adresu mení:
 *  - super-admin vo formulári kanála (sync) — hneď,
 *  - tím kanála vo formulári (request) — adresu, ktorú kanál už má, hneď;
 *    novú až po potvrdení odkazom z e-mailu (čakajúca adresa, `pending_at`),
 *  - zápis do `canals.email` odinakiaľ, napr. import (adopt, cez CanalObserver),
 *  - vrátený e-mail (recordBounce) — adresa je nedoručiteľná a primárnou sa
 *    stane ďalšia najlepšia; ak žiadna nie je, kanál ostane bez kontaktu
 *    a čaká na hľadanie novej (CanalEmailFinder). Dočasné vrátenie (soft)
 *    adresu vyradí až po opakovaní (`canals.emails.soft_bounce_*`),
 *  - nájdená náhradná adresa (discover) — len keď kanál nemá použiteľnú,
 *  - potvrdenie alebo odpoveď (confirm, recordReply) — overená adresa má
 *    prednosť pred neoverenou primárnou aj pred adresou vlastníka,
 *  - nový vlastník spravovaného kanála (recordOwner) — jeho e-mail z účtu
 *    nahradí neoverenú adresu z importu či adresu predošlého vlastníka;
 *    vrátené prevzatie adresy vlastníkov odoberie (forgetOwners).
 *
 * recordBounce() a recordReply() sú vstupy pre spracovanie schránky portálu.
 */
class CanalEmails
{
    /** Najviac adries na kanál (rovnako ako pri podujatí). */
    public const MAX = 10;

    private static bool $ready = false;

    public static function normalize(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' && mb_strlen($email) <= 191 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** @return Collection<int, CanalEmail> primárna prvá */
    public function all(Canal $canal): Collection
    {
        if (! $this->ready()) {
            return collect();
        }

        return $canal->emails()->orderByDesc('is_primary')->orderBy('id')->get();
    }

    public function primary(Canal $canal): ?CanalEmail
    {
        return $this->ready() ? $canal->emails()->where('is_primary', true)->first() : null;
    }

    /**
     * Zoznam z formulára super-admina (alebo systému): čo v ňom nie je, sa
     * zmaže a zmena platí hneď. Nedoručiteľná adresa, ktorú človek sám vybral
     * za primárnu, dostane novú šancu; čakajúcu adresu tímu tým schváli.
     *
     * @param  array<int, mixed>  $additional
     */
    public function sync(Canal $canal, ?string $primary, array $additional): void
    {
        if (! $this->ready()) {
            return;
        }

        $primary = self::normalize($primary);
        $wanted = $this->wanted([$primary, ...$additional]);

        $canal->emails()->whereNotIn('email', $wanted->all())->delete();
        $known = $canal->emails()->get()->keyBy('email');

        foreach ($wanted as $email) {
            if (! $known->has($email)) {
                $known[$email] = $this->create($canal, $email, CanalEmail::SOURCE_MANUAL);
            }
        }

        if ($primary !== null && $known[$primary]->isPending()) {
            $canal->emails()->where('pending_primary', true)->update(['pending_primary' => false]);
            $this->clearPending($known[$primary]);
        }

        if ($primary !== null && ! $known[$primary]->isUsable()) {
            $this->reactivate($known[$primary]);
        }

        $this->settle($canal, $primary);
    }

    /**
     * Zmena adries z formulára, ktorú robí tím kanála (nie super-admin).
     *
     * Adresa, ktorú kanál ešte nemal, pribudne ako čakajúca: primárnou sa
     * stane — ak ju tím za primárnu chce — až keď ju schránka potvrdí odkazom
     * (CanalContactVerifier). Inak by si tím mohol nastaviť cudziu alebo
     * preklepnutú adresu a cez ňu by kanál, keď ho tím opustí, mohol prevziať
     * ktokoľvek (CanalClaims, oslovenie). Adresu, ktorú kanál už má, si tím
     * za primárnu vyberie hneď.
     *
     * @param  array<int, mixed>|null  $additional  null = formulár posiela len primárnu, ostatné adresy ostávajú
     * @return Collection<int, CanalEmail> adresy, ktorým treba poslať odkaz na potvrdenie
     */
    public function request(Canal $canal, ?string $primary, ?array $additional): Collection
    {
        if (! $this->ready()) {
            return collect();
        }

        $primary = self::normalize($primary);

        if ($additional === null) {
            // Prázdne pole kontaktu adresu maže, ako pri zápise do stĺpca (adopt).
            if ($primary === null) {
                $canal->emails()->where('is_primary', true)->delete();
            }

            $additional = $canal->emails()->pluck('email')->all();
        }

        $wanted = $this->wanted([$primary, ...$additional]);

        $canal->emails()->whereNotIn('email', $wanted->all())->delete();
        $known = $canal->emails()->get()->keyBy('email');
        $notify = collect();

        foreach ($wanted as $email) {
            if (! $known->has($email)) {
                $row = $this->create($canal, $email, CanalEmail::SOURCE_MANUAL);
                $row->forceFill(['pending_at' => now(), 'pending_primary' => false])->save();
                $known[$email] = $row;
                $notify->push($row);
            }
        }

        $preferred = null;

        if ($primary !== null) {
            $row = $known[$primary];

            if ($row->isPending()) {
                // Čakajúca adresa, ktorú tím teraz prvý raz chce za primárnu — odkaz znova.
                if (! $row->pending_primary && ! $notify->contains(fn (CanalEmail $n) => $n->is($row))) {
                    $notify->push($row);
                }
            } else {
                if (! $row->isUsable()) {
                    $this->reactivate($row);
                }

                $preferred = $primary;
            }
        }

        // Želaná primárna je najviac jedna — a len čakajúca.
        foreach ($known as $email => $row) {
            $wants = $email === $primary && $row->isPending();

            if ((bool) $row->pending_primary !== $wants) {
                $row->forceFill(['pending_primary' => $wants])->save();
            }
        }

        $this->settle($canal, $preferred);

        return $notify->values();
    }

    /** Čakajúca adresa, ktorú tím za primárnu chce (najviac jedna). */
    public function pendingPrimary(Canal $canal): ?CanalEmail
    {
        return $this->ready()
            ? $canal->emails()->whereNotNull('pending_at')->where('pending_primary', true)->first()
            : null;
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return Collection<int, string>
     */
    private function wanted(array $emails): Collection
    {
        return collect($emails)
            ->map(fn ($email) => self::normalize(is_string($email) ? $email : null))
            ->filter()
            ->unique()
            ->take(self::MAX)
            ->values();
    }

    /**
     * `canals.email` (alebo jeho overenie) sa zmenil mimo formulára — stĺpec
     * je želaná primárna adresa. Pôvodná primárna ostáva ako ďalšia adresa.
     * Nedoručiteľná adresa sa touto cestou späť nedostane.
     */
    public function adopt(Canal $canal): void
    {
        if (! $this->ready()) {
            return;
        }

        $email = self::normalize($canal->email);

        if ($email === null) {
            // Vymazaný kontakt = kanál tú adresu už nemá. Neplatný text
            // v stĺpci (staré dáta) nechávame tak.
            if (trim((string) $canal->email) === '' && $canal->wasChanged('email')) {
                $canal->emails()->where('is_primary', true)->delete();
                $this->settle($canal);
            }

            return;
        }

        $row = $canal->emails()->where('email', $email)->first();
        // Overenie zo stĺpca patrí novej adrese len vtedy, keď prišlo spolu
        // s ňou — inak je to ešte overenie predošlej adresy.
        $carriesVerification = $canal->wasChanged('email_verified_at')
            || ($canal->wasRecentlyCreated && ! $canal->wasChanged('email'));
        $verifiedAt = $carriesVerification ? $canal->email_verified_at : null;

        if ($row === null) {
            $row = $this->create($canal, $email,
                $canal->registration_source === RegistrationSource::IMPORT ? CanalEmail::SOURCE_IMPORT : CanalEmail::SOURCE_MANUAL,
                $verifiedAt);
        } elseif ($carriesVerification && $row->isUsable()) {
            $row->forceFill([
                'status' => $verifiedAt !== null ? CanalEmailStatus::Verified : CanalEmailStatus::Unverified,
                'verified_at' => $verifiedAt,
            ])->save();
        }

        // Zápis do stĺpca robí systém (import, migrácia), nie tím — čakajúcu adresu tým schváli.
        if ($row->isPending()) {
            $this->clearPending($row);
        }

        $this->settle($canal, $email);
    }

    /** Adresy sa zmenili priamo v tabuľke (zlúčenie kanálov) — dorovná primárnu. */
    public function refresh(Canal $canal): void
    {
        if ($this->ready()) {
            $this->settle($canal);
        }
    }

    /**
     * Schránka sa ozvala (odkaz z e-mailu, prijatá pozvánka, prevzatie).
     * Overená adresa nahradí primárnu, ktorá overená nie je.
     *
     * @param  bool  $create  pridať adresu, ak ju kanál ešte nemá
     */
    public function confirm(Canal $canal, ?string $email, string $via, bool $create = false): bool
    {
        $email = self::normalize($email);

        if ($email === null || ! $this->ready()) {
            return false;
        }

        $row = $canal->emails()->where('email', $email)->first();

        if ($row === null) {
            if (! $create || $canal->emails()->count() >= self::MAX) {
                return false;
            }

            $row = $this->create($canal, $email, CanalEmail::SOURCE_CONFIRMED);
        }

        // Tím ju chcel za primárnu a teraz ju schránka potvrdila.
        $requested = $row->isPending() && $row->pending_primary;

        $row->forceFill([
            'status' => CanalEmailStatus::Verified,
            'verified_at' => now(),
            'last_seen_at' => now(),
            'pending_at' => null,
            'pending_primary' => false,
            'bounced_at' => null,
            'bounce_type' => null,
            'bounce_reason' => null,
            'bounce_count' => 0,
        ])->save();

        // Živá schránka nie je nedoručiteľná; odhlásenie (unsubscribe) ostáva.
        EmailSuppression::query()->where('email', $email)->where('reason', 'bounced')->delete();

        $before = $this->primary($canal);
        // Overená schránka organizácie má prednosť aj pred adresou vlastníka.
        $prefer = $requested || $before === null || ! $before->isVerified() || $before->source === CanalEmail::SOURCE_OWNER;
        $after = $this->settle($canal, $prefer ? $email : null);

        Recorder::info('canals', 'email_confirmed', "Kanál {$canal->name}: adresa {$email} potvrdená",
            status: 'ok',
            recipient: $email,
            subject: $canal,
            context: ['via' => $via, 'primary_from' => $before?->email, 'primary_to' => $after?->email],
        );

        return true;
    }

    /**
     * Vlastník spravovaného kanála (prevzatie, nový vlastník v tíme): jeho
     * e-mail z účtu pribudne medzi adresy kanála, overený ako účet.
     *
     * Primárnou sa stane namiesto neoverenej adresy z importu a namiesto
     * adresy predošlého vlastníka — tá ostáva ako ďalšia. Adresu, ktorú
     * tím zadal ručne, a overenú schránku organizácie nenahrádza.
     */
    public function recordOwner(Canal $canal, User $owner): void
    {
        $email = self::normalize($owner->email);

        if ($email === null || ! $this->ready()) {
            return;
        }

        $verifiedAt = $owner->email_verified_at;
        $row = $canal->emails()->where('email', $email)->first();
        $changed = true;

        if ($row === null) {
            if ($canal->emails()->count() >= self::MAX) {
                return;
            }

            $row = $this->create($canal, $email, CanalEmail::SOURCE_OWNER, $verifiedAt);
        } elseif (! ($verifiedAt !== null && ! $row->isVerified())) {
            $changed = false;
        } else {
            // Účet s touto adresou je overený — schránka teda žije.
            $row->forceFill([
                'status' => CanalEmailStatus::Verified,
                'verified_at' => $verifiedAt,
                'last_seen_at' => $verifiedAt,
                'pending_at' => null,
                'pending_primary' => false,
                'bounced_at' => null,
                'bounce_type' => null,
                'bounce_reason' => null,
            ])->save();
        }

        $before = $this->primary($canal);
        $prefer = $before === null || $before->is($row) || ! $before->isUsable() || $this->yieldsToOwner($before);
        $after = $this->settle($canal, $prefer ? $email : null);

        // Opakované volanie (napr. vnorené uloženie účtu pri registrácii)
        // nič nezmenilo — denník by zbytočne hlásil tú istú adresu znova.
        if (! $changed && $before?->is($after)) {
            return;
        }

        Recorder::info('canals', 'email_owner_added', "Kanál {$canal->name}: pribudla adresa vlastníka {$email}",
            status: 'ok',
            recipient: $email,
            userId: $owner->id,
            subject: $canal,
            context: ['primary_from' => $before?->email, 'primary_to' => $after?->email],
        );
    }

    /**
     * Adresa účtu, ktorý v kanáli už nie je (zmazaný účet, zmenený e-mail).
     * Odíde len adresa vlastníka; v osobnom kanáli aj ručná, lebo tam je
     * kontaktom kanála práve e-mail účtu. Adresa organizácie z importu,
     * ktorá sa s e-mailom účtu len zhoduje, ostáva.
     */
    public function forgetOwner(Canal $canal, ?string $email): void
    {
        $email = self::normalize($email);

        if ($email === null || ! $this->ready()) {
            return;
        }

        $sources = $canal->identity_mode === CanalIdentityMode::Personal
            ? [CanalEmail::SOURCE_OWNER, CanalEmail::SOURCE_MANUAL]
            : [CanalEmail::SOURCE_OWNER];

        $deleted = $canal->emails()->where('email', $email)->whereIn('source', $sources)->delete();

        if ($deleted === 0) {
            return;
        }

        if ($this->settle($canal) === null && self::normalize($canal->email) === $email) {
            $this->mirror($canal, null);
        }

        Recorder::info('canals', 'email_owner_removed', "Kanál {$canal->name}: odobratá adresa účtu {$email}",
            status: 'ok',
            recipient: $email,
            subject: $canal,
            context: ['primary_to' => $canal->email],
        );
    }

    /**
     * Prevzatie sa vrátilo — kanál znova nikto nespravuje, takže adresy
     * vlastníkov z neho odídu (inak by na ne chodili otázky návštevníkov).
     * Odídu aj nepotvrdené adresy, ktoré pridal tím: cez ne by sa kanál dal
     * prevziať znova bez toho, aby schránka čokoľvek potvrdila.
     */
    public function forgetOwners(Canal $canal): void
    {
        if (! $this->ready()) {
            return;
        }

        $leaving = fn () => $canal->emails()->where(fn ($q) => $q
            ->where('source', CanalEmail::SOURCE_OWNER)
            ->orWhereNotNull('pending_at'));
        $removed = $leaving()->pluck('email');

        if ($removed->isEmpty()) {
            return;
        }

        $leaving()->delete();

        if ($this->settle($canal) === null && $removed->contains($canal->email)) {
            $this->mirror($canal, null);
        }

        Recorder::info('canals', 'email_owners_removed', "Kanál {$canal->name}: odobraté adresy vlastníkov",
            status: 'ok',
            subject: $canal,
            context: ['emails' => $removed->values()->all(), 'primary_to' => $canal->email],
        );
    }

    /**
     * Z adresy nám prišla odpoveď od človeka (nie automatická odpoveď).
     *
     * @return int počet kanálov, ktorým adresa patrí
     */
    public function recordReply(?string $email): int
    {
        return $this->eachCanalOf($email, fn (Canal $canal, string $email) => $this->confirm($canal, $email, 'reply'));
    }

    /**
     * E-mail na adresu sa vrátil. Adresa je nedoručiteľná u každého kanála,
     * ktorý ju má; kde bola primárna, nastúpi ďalšia najlepšia. Trvalo
     * neexistujúca adresa (hard) končí aj v EmailSuppression, aby na ňu
     * nepísalo ani nič mimo kanálov.
     *
     * @param  string  $type  CanalEmail::BOUNCE_HARD | BOUNCE_SOFT
     * @return int počet kanálov, ktorým adresa patrí
     */
    public function recordBounce(?string $email, string $type = CanalEmail::BOUNCE_HARD, ?string $reason = null): int
    {
        $type = $type === CanalEmail::BOUNCE_SOFT ? CanalEmail::BOUNCE_SOFT : CanalEmail::BOUNCE_HARD;
        $address = self::normalize($email);

        if ($address !== null && $type === CanalEmail::BOUNCE_HARD) {
            EmailSuppression::add($address, 'bounced', 'canal_email');
        }

        return $this->eachCanalOf($email, function (Canal $canal, string $email, CanalEmail $row) use ($type, $reason) {
            $wasPrimary = $row->is_primary;
            $count = $this->bounceCount($row, $type);
            // Plná schránka býva dočasná — adresa ostáva v hre, kým sa
            // vrátenie neopakuje `soft_bounce_limit`-krát za `soft_bounce_days`.
            $undeliverable = $type === CanalEmail::BOUNCE_HARD
                || ! $row->isUsable()
                || $count >= max(1, (int) config('canals.emails.soft_bounce_limit', 3));

            $row->forceFill([
                'status' => $undeliverable ? CanalEmailStatus::Undeliverable : $row->status,
                'bounced_at' => now(),
                'bounce_type' => $type,
                'bounce_reason' => $reason !== null && trim($reason) !== '' ? Str::limit(trim($reason), 240, '') : null,
                'bounce_count' => $count,
            ])->save();

            $next = $this->settle($canal);

            Recorder::warning('canals', 'email_bounced', $undeliverable
                ? "Kanál {$canal->name}: e-mail na {$email} sa vrátil"
                : "Kanál {$canal->name}: e-mail na {$email} sa dočasne vrátil ({$count}×), adresa ostáva",
                status: $type,
                recipient: $email,
                subject: $canal,
                context: [
                    'reason' => $row->bounce_reason, 'was_primary' => $wasPrimary, 'primary_to' => $next?->email,
                    'undeliverable' => $undeliverable, 'bounce_count' => $count,
                ],
            );

            // Kanál bez použiteľnej adresy si zaslúži hľadanie náhradnej (CanalEmailFinder).
            if ($undeliverable && $next === null) {
                CanalEmailSearch::requestFor($canal);
            }
        });
    }

    /**
     * Pridá adresy nájdené inde (podujatia kanála, jeho web, vyhľadávanie)
     * ako neoverené, najlepšia prvá. Primárnou sa stane, len keď kanál
     * žiadnu použiteľnú adresu nemá. Adresy, ktoré kanál už pozná (aj
     * nedoručiteľné) alebo ktoré sú v EmailSuppression, sa preskočia.
     *
     * @param  array<int, array{email: string, found_on: ?string}>  $candidates
     * @return Collection<int, CanalEmail> pridané adresy
     */
    public function discover(Canal $canal, array $candidates, string $via): Collection
    {
        if (! $this->ready()) {
            return collect();
        }

        $known = $canal->emails()->pluck('email')->all();
        $added = collect();

        foreach ($candidates as $candidate) {
            $email = self::normalize($candidate['email'] ?? null);

            if ($email === null || in_array($email, $known, true) || EmailSuppression::has($email)
                || count($known) >= self::MAX) {
                continue;
            }

            $row = $this->create($canal, $email, CanalEmail::SOURCE_DISCOVERED);
            $row->forceFill(['found_on' => isset($candidate['found_on']) ? Str::limit((string) $candidate['found_on'], 250, '') : null])->save();
            $known[] = $email;
            $added->push($row);
        }

        if ($added->isEmpty()) {
            return $added;
        }

        $before = $this->primary($canal);
        $after = $this->settle($canal, $before === null ? $added->first()->email : null);

        Recorder::info('canals', 'email_discovered', "Kanál {$canal->name}: nájdené adresy ".$added->pluck('email')->implode(', '),
            status: 'ok',
            recipient: $added->first()->email,
            subject: $canal,
            context: [
                'via' => $via,
                'emails' => $added->map(fn (CanalEmail $row) => ['email' => $row->email, 'found_on' => $row->found_on])->all(),
                'primary_from' => $before?->email,
                'primary_to' => $after?->email,
            ],
        );

        return $added;
    }

    /** Opakované dočasné vrátenie v okne sa sčíta, inak sa počíta odznova. */
    private function bounceCount(CanalEmail $row, string $type): int
    {
        if ($type === CanalEmail::BOUNCE_HARD) {
            return $row->bounce_count + 1;
        }

        $recent = $row->bounce_type === CanalEmail::BOUNCE_SOFT && $row->bounced_at !== null
            && $row->bounced_at->gte(now()->subDays(max(1, (int) config('canals.emails.soft_bounce_days', 30))));

        return $recent ? $row->bounce_count + 1 : 1;
    }

    /**
     * Zabezpečí práve jednu primárnu adresu spomedzi použiteľných a zapíše ju
     * do `canals.email`. Poradie: želaná → doterajšia → overená → naposledy
     * živá → najstaršia.
     */
    private function settle(Canal $canal, ?string $preferred = null): ?CanalEmail
    {
        $rows = $canal->emails()->orderBy('id')->get();

        // Kanál bez adries: stĺpec nechávame, ako je.
        if ($rows->isEmpty()) {
            return null;
        }

        // Čakajúca adresa tímu primárna nebude, kým ju schránka nepotvrdí.
        $usable = $rows->filter(fn (CanalEmail $row) => $row->isUsable() && ! $row->isPending());

        $chosen = ($preferred !== null ? $usable->firstWhere('email', $preferred) : null)
            ?? $usable->firstWhere('is_primary', true)
            ?? $usable->sortByDesc(fn (CanalEmail $row) => [
                $row->isVerified() ? 1 : 0,
                $row->last_seen_at?->getTimestamp() ?? 0,
                -$row->id,
            ])->first();

        foreach ($rows as $row) {
            $isPrimary = $chosen !== null && $row->is($chosen);

            if ($row->is_primary !== $isPrimary) {
                $row->forceFill(['is_primary' => $isPrimary])->save();
            }
        }

        $this->mirror($canal, $chosen);

        return $chosen;
    }

    /**
     * Zápis mimo Eloquent udalostí: nespustí znova CanalObserver a nezmaže
     * modelu `wasChanged()`, podľa ktorého sa volajúci ešte rozhoduje.
     */
    private function mirror(Canal $canal, ?CanalEmail $primary): void
    {
        $verifiedAt = $primary?->isVerified() ? $primary->verified_at : null;

        if ($canal->email === $primary?->email
            && $canal->email_verified_at?->toDateTimeString() === $verifiedAt?->toDateTimeString()) {
            return;
        }

        $values = ['email' => $primary?->email, 'email_verified_at' => $verifiedAt];

        $canal->newQueryWithoutScopes()->whereKey($canal->getKey())->toBase()->update($values);
        $canal->forceFill($values)->syncOriginalAttributes(array_keys($values));
    }

    /** Neoverená adresa z importu a adresa predošlého vlastníka ustúpia vlastníkovi. */
    private function yieldsToOwner(CanalEmail $primary): bool
    {
        return $primary->source === CanalEmail::SOURCE_OWNER
            || ($primary->source === CanalEmail::SOURCE_IMPORT && ! $primary->isVerified());
    }

    private function create(Canal $canal, string $email, string $source, mixed $verifiedAt = null): CanalEmail
    {
        return $canal->emails()->create([
            'email' => $email,
            'source' => $source,
            'status' => $verifiedAt !== null ? CanalEmailStatus::Verified : CanalEmailStatus::Unverified,
            'verified_at' => $verifiedAt,
            'last_seen_at' => $verifiedAt,
        ]);
    }

    private function clearPending(CanalEmail $row): void
    {
        $row->forceFill(['pending_at' => null, 'pending_primary' => false])->save();
    }

    private function reactivate(CanalEmail $row): void
    {
        $row->forceFill([
            'status' => CanalEmailStatus::Unverified,
            'verified_at' => null,
            'bounced_at' => null,
            'bounce_type' => null,
            'bounce_reason' => null,
        ])->save();
    }

    /** @param  callable(Canal, string, CanalEmail): mixed  $callback */
    private function eachCanalOf(?string $email, callable $callback): int
    {
        $email = self::normalize($email);

        if ($email === null || ! $this->ready()) {
            return 0;
        }

        // Zmazaný (aj zlúčený) kanál už nikto nekontaktuje — zmena by len
        // pridala do denníka druhý záznam o tom istom e-maile.
        $rows = CanalEmail::query()->with('canal')->where('email', $email)->get()
            ->filter(fn (CanalEmail $row) => $row->canal !== null && ! $row->canal->trashed());

        foreach ($rows as $row) {
            $callback($row->canal, $email, $row);
        }

        return $rows->count();
    }

    /** Kód môže bežať skôr než migrácia (nasadenie `git pull`-om, staré migrácie). */
    private function ready(): bool
    {
        return self::$ready = self::$ready || Schema::hasTable('canal_emails');
    }
}
