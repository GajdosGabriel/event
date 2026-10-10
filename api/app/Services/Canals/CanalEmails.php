<?php

namespace App\Services\Canals;

use App\Enums\CanalEmailStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalEmail;
use App\Models\EmailSuppression;
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
 *  - človek vo formulári kanála (sync),
 *  - zápis do `canals.email` odinakiaľ, napr. import (adopt, cez CanalObserver),
 *  - vrátený e-mail (recordBounce) — adresa je nedoručiteľná a primárnou sa
 *    stane ďalšia najlepšia; ak žiadna nie je, kanál ostane bez kontaktu,
 *  - potvrdenie alebo odpoveď (confirm, recordReply) — overená adresa má
 *    prednosť pred neoverenou primárnou.
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
     * Zoznam z formulára: čo v ňom nie je, sa zmaže. Nedoručiteľná adresa,
     * ktorú človek sám vybral za primárnu, dostane novú šancu.
     *
     * @param  array<int, mixed>  $additional
     */
    public function sync(Canal $canal, ?string $primary, array $additional): void
    {
        if (! $this->ready()) {
            return;
        }

        $primary = self::normalize($primary);
        $wanted = collect([$primary, ...$additional])
            ->map(fn ($email) => self::normalize(is_string($email) ? $email : null))
            ->filter()
            ->unique()
            ->take(self::MAX)
            ->values();

        $canal->emails()->whereNotIn('email', $wanted->all())->delete();
        $known = $canal->emails()->get()->keyBy('email');

        foreach ($wanted as $email) {
            if (! $known->has($email)) {
                $known[$email] = $this->create($canal, $email, CanalEmail::SOURCE_MANUAL);
            }
        }

        if ($primary !== null && ! $known[$primary]->isUsable()) {
            $this->reactivate($known[$primary]);
        }

        $this->settle($canal, $primary);
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

        $row->forceFill([
            'status' => CanalEmailStatus::Verified,
            'verified_at' => now(),
            'last_seen_at' => now(),
            'bounced_at' => null,
            'bounce_type' => null,
            'bounce_reason' => null,
            'bounce_count' => 0,
        ])->save();

        // Živá schránka nie je nedoručiteľná; odhlásenie (unsubscribe) ostáva.
        EmailSuppression::query()->where('email', $email)->where('reason', 'bounced')->delete();

        $before = $this->primary($canal);
        $after = $this->settle($canal, $before === null || ! $before->isVerified() ? $email : null);

        Recorder::info('canals', 'email_confirmed', "Kanál {$canal->name}: adresa {$email} potvrdená",
            status: 'ok',
            recipient: $email,
            subject: $canal,
            context: ['via' => $via, 'primary_from' => $before?->email, 'primary_to' => $after?->email],
        );

        return true;
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

            $row->forceFill([
                'status' => CanalEmailStatus::Undeliverable,
                'bounced_at' => now(),
                'bounce_type' => $type,
                'bounce_reason' => $reason !== null && trim($reason) !== '' ? Str::limit(trim($reason), 240, '') : null,
                'bounce_count' => $row->bounce_count + 1,
            ])->save();

            $next = $this->settle($canal);

            Recorder::warning('canals', 'email_bounced', "Kanál {$canal->name}: e-mail na {$email} sa vrátil",
                status: $type,
                recipient: $email,
                subject: $canal,
                context: ['reason' => $row->bounce_reason, 'was_primary' => $wasPrimary, 'primary_to' => $next?->email],
            );
        });
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

        $usable = $rows->filter(fn (CanalEmail $row) => $row->isUsable());

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

        $rows = CanalEmail::query()->with('canal')->where('email', $email)->get()
            ->filter(fn (CanalEmail $row) => $row->canal !== null);

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
