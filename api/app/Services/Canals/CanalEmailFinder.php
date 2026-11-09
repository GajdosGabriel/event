<?php

namespace App\Services\Canals;

use App\Enums\CanalEmailStatus;
use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalEmailSearch;
use App\Models\EmailSuppression;
use App\Models\Event;
use App\Services\Imports\CollectionCanal;
use App\Services\Profiles\ProfileEnricher;
use App\Services\Profiles\ProfileResearch;
use App\Services\SystemLog\Recorder;
use App\Support\AppSettings;
use App\Support\Url;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Hľadá náhradnú adresu kanála, ktorý nemá žiadnu použiteľnú (po vrátenom
 * e-maile alebo ju nikdy nemal). Spúšťa ju app:canal-emails-find.
 *
 * Postup od lacného k drahému:
 *  1. e-maily z podujatí kanála (`events.email`, `additional_emails`),
 *  2. web kanála — titulná stránka a najviac `pages` kontaktných podstránok
 *     (odkazy `mailto:` aj adresy v texte vrátane „[at]"),
 *  3. len po vrátenom e-maile, keď 1. a 2. nič nedali: vyhľadávanie cez
 *     OpenAI (ProfileResearch). Adresa sa prijme, len keď na zdrojovej
 *     stránke doslova je — model ju nesmie vymyslieť.
 *
 * Hľadá sa len kanálom s aktuálnou akciou (Canal::scopeWithCurrentEvent) —
 * len tým môže prísť ponuka prevzatia — a kanálom po vrátenom e-maile.
 *
 * Kanál bez webu počká, kým ho prejde dopĺňanie profilu (app:profiles-enrich)
 * — to mu web často nájde a bez neho by 2. krok nemal kam ísť. Čaká sa len
 * pri voľnom rozpočte AI a najviac `profile_wait_hours` od zverejnenia akcie,
 * aby kontakt stihol okno oslovenia. Po vrátenom e-maile sa nečaká, tam hľadá
 * aj AI. Keď kanál web dostane neskôr, CanalObserver zruší odklad a hľadá sa znova.
 *
 * Nájdené adresy pribudnú ako neoverené (CanalEmails::discover), najlepšia
 * ako primárna; ďalšie sú záloha pre prípad, že sa vráti aj tá. Hľadá sa
 * len pri nespravovanom kanáli organizácie — spravovaný kanál má tím, ktorý
 * si kontakt určuje sám, a zberný kanál drží podujatia cudzích organizátorov.
 */
class CanalEmailFinder
{
    /** Odkazy, za ktorými býva kontakt. */
    private const CONTACT_LINK = '/kontakt|contact|o-nas|o_nas|about|impressum|farsk[yý][-_ ]?[uú]rad|sekretari/iu';

    /** Všeobecná schránka organizácie má prednosť pred osobnou. */
    private const GENERIC_LOCAL = '/^(info|kontakt|kontakty|contact|office|sekretariat|podatelna|farnost|farsky\.?urad|fara|obec|mesto|ou|mu|recepcia|hello|ahoj|mail|email|admin)$/';

    /** Adresy, ktoré nie sú kontaktom organizácie (šablóny, služby webu). */
    private const JUNK_LOCAL = '/^(no-?reply|do-?not-?reply|mailer-daemon|postmaster|abuse|webmaster|wordpress|example|test|name|meno|vas|your)$/';

    private const JUNK_DOMAIN = '/(^|\.)(example\.(com|org|sk)|domain\.(com|sk)|sentry\.io|sentry-next\.wixpress\.com|wixpress\.com|wix\.com|godaddy\.com|schema\.org|w3\.org|email\.com|mail\.test)$/';

    public function __construct(
        private readonly CanalEmails $emails,
        private readonly CanalWebsitePages $pages,
        private readonly ProfileEnricher $enricher,
    ) {}

    /** Prečo sa kanálu adresa nehľadá; null = hľadá sa. */
    public function skipReason(Canal $canal): ?string
    {
        return match (true) {
            $canal->trashed() || $canal->merged_into_id !== null => 'trashed',
            ! in_array($canal->status, [ModelStatus::Published, ModelStatus::Draft], true) => 'status',
            $canal->identity_mode !== CanalIdentityMode::Organization => 'personal',
            $canal->isManaged() => 'managed',
            CollectionCanal::is($canal) => 'collection',
            $this->hasUsableEmail($canal) => 'has_email',
            ! $this->requested($canal) && ! Canal::query()->whereKey($canal->id)->withCurrentEvent()->exists() => 'no_current_event',
            $this->awaitsProfile($canal) => 'awaiting_profile',
            default => null,
        };
    }

    /**
     * Kanály na rad: najprv tie po vrátenom e-maile (`requested_at`), potom
     * ostatné bez kontaktu s aktuálnou akciou, ktoré sa ešte nehľadali alebo
     * im uplynulo opakovanie — bližšia akcia prvá.
     *
     * @return Builder<Canal>
     */
    public function dueQuery(): Builder
    {
        $maxAttempts = max(1, (int) config('canals.emails.discovery.max_attempts', 3));

        return Canal::query()
            ->where('identity_mode', CanalIdentityMode::Organization->value)
            ->whereIn('status', [ModelStatus::Published->value, ModelStatus::Draft->value])
            ->whereNull('merged_into_id')
            ->whereNull('claimed_at')
            ->whereNotIn('id', CollectionCanal::ids())
            ->whereNotIn('registration_source', [RegistrationSource::SELF->value, RegistrationSource::ADMIN->value])
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
            ->where(fn ($q) => $q
                ->whereExists(fn ($s) => $s->selectRaw('1')->from('canal_email_searches')
                    ->whereColumn('canal_email_searches.canal_id', 'canals.id')->whereNotNull('requested_at'))
                ->orWhereNotExists(fn ($s) => $s->selectRaw('1')->from('canal_email_searches')
                    ->whereColumn('canal_email_searches.canal_id', 'canals.id')
                    ->where(fn ($w) => $w->where('attempts', '>=', $maxAttempts)->orWhere('retry_at', '>', now()))))
            ->where(fn ($q) => $q->withCurrentEvent()->orWhereExists(fn ($s) => $this->requestedSearch($s)))
            ->where(fn ($q) => $this->profileSettled($q))
            ->orderByRaw('(select requested_at from canal_email_searches where canal_email_searches.canal_id = canals.id) is null')
            ->orderByCurrentEvent()
            ->orderBy('id');
    }

    /** @return string CanalEmailSearch::FOUND | NONE | SKIPPED */
    public function run(Canal $canal): string
    {
        return Cache::lock('canal-email-find:'.$canal->id, 300)->get(fn () => $this->search($canal)) ?? CanalEmailSearch::SKIPPED;
    }

    private function search(Canal $canal): string
    {
        $search = CanalEmailSearch::query()->firstOrCreate(['canal_id' => $canal->id], ['attempts' => 0]);
        $afterBounce = $search->requested_at !== null;
        // Adresy kanála sa mohli zmeniť priamo v tabuľke — primárna sa dorovná skôr, než sa hľadá.
        $this->emails->refresh($canal);
        $reason = $this->skipReason($canal);

        // Čakanie na akciu či doplnenie profilu nie je pokus — odklad by kanál zbytočne podržal.
        if (in_array($reason, ['no_current_event', 'awaiting_profile'], true)) {
            return CanalEmailSearch::SKIPPED;
        }

        if ($reason !== null) {
            $this->finish($search, CanalEmailSearch::SKIPPED, null, $reason);

            return CanalEmailSearch::SKIPPED;
        }

        $error = null;
        $via = null;
        $added = collect();

        try {
            foreach (['events' => fn () => $this->fromEvents($canal), 'website' => fn () => $this->fromWebsite($canal)] as $step => $find) {
                $added = $this->emails->discover($canal, $this->best($canal, $find()), $step);

                if ($added->isNotEmpty()) {
                    $via = $step;
                    break;
                }
            }

            if ($added->isEmpty() && $afterBounce && $this->aiAvailable()) {
                $added = $this->emails->discover($canal, $this->fromResearch($canal), 'ai');
                $via = $added->isNotEmpty() ? 'ai' : null;
            }
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 250);
            Recorder::warning('canals', 'email_search_failed', "Kanál {$canal->name}: hľadanie novej adresy zlyhalo",
                subject: $canal, context: ['error' => $error]);
        }

        $result = $added->isNotEmpty() ? CanalEmailSearch::FOUND : CanalEmailSearch::NONE;
        $this->finish($search, $result, $via, $error);

        if ($result === CanalEmailSearch::NONE && $error === null) {
            Recorder::info('canals', 'email_not_found', "Kanál {$canal->name}: náhradná adresa sa nenašla",
                status: 'skipped', subject: $canal, context: ['after_bounce' => $afterBounce, 'attempt' => $search->attempts]);
        }

        return $result;
    }

    private function finish(CanalEmailSearch $search, string $result, ?string $via, ?string $error): void
    {
        $search->forceFill([
            'attempts' => $result === CanalEmailSearch::SKIPPED ? $search->attempts : $search->attempts + 1,
            'requested_at' => null,
            'searched_at' => now(),
            'retry_at' => $result === CanalEmailSearch::FOUND ? null : now()->addDays(max(1, (int) config('canals.emails.discovery.retry_days', 30))),
            'result' => $result,
            'found_via' => $via,
            'last_error' => $error,
        ])->save();
    }

    /** Kanálu sa vrátil e-mail a hľadanie si vyžiadal (CanalEmailSearch::requestFor). */
    private function requested(Canal $canal): bool
    {
        return CanalEmailSearch::query()->where('canal_id', $canal->id)->whereNotNull('requested_at')->exists();
    }

    /** @param  \Illuminate\Database\Query\Builder  $s */
    private function requestedSearch($s): void
    {
        $s->selectRaw('1')->from('canal_email_searches')
            ->whereColumn('canal_email_searches.canal_id', 'canals.id')->whereNotNull('requested_at');
    }

    /** Čakanie na profil má zmysel: dopĺňanie beží a má rozpočet. */
    private function profileCanWait(): bool
    {
        return $this->enricher->enabled() && $this->enricher->budgetAvailable();
    }

    /** Hranica čakania: akcia zverejnená skôr už na profil nečaká. */
    private function waitLimit(): \Carbon\CarbonInterface
    {
        return now()->subHours(max(0, (int) config('canals.emails.discovery.profile_wait_hours', 48)));
    }

    /**
     * Kanál bez webu, ktorému ešte len dopĺňanie profilu môže web nájsť —
     * kým jeho aktuálna akcia nie je staršia než `profile_wait_hours`.
     */
    private function awaitsProfile(Canal $canal): bool
    {
        return Url::normalize($canal->website) === null
            && ! $this->requested($canal)
            && $this->profileCanWait()
            && $this->enricher->pending($canal)
            && ! Canal::query()->whereKey($canal->id)->whereExists(fn ($q) => $this->staleCurrentEvent($q))->exists();
    }

    /**
     * SQL náprotivok awaitsProfile(): kanál má web, vrátil sa mu e-mail,
     * dopĺňanie profilu skončilo (či vyčerpalo pokusy), alebo už čakal dosť.
     *
     * @param  Builder<Canal>  $q
     */
    private function profileSettled(Builder $q): void
    {
        if (! $this->profileCanWait()) {
            return;
        }

        $maxAttempts = (int) config('profile_enrichment.max_attempts', 3);

        $q->where(fn ($w) => $w->whereNotNull('website')->where('website', '!=', ''))
            ->orWhereExists(fn ($s) => $this->requestedSearch($s))
            ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('profile_enrichments')
                ->where('subject_type', (new Canal)->getMorphClass())
                ->whereColumn('profile_enrichments.subject_id', 'canals.id')
                ->where(fn ($w) => $w->whereNotNull('completed_at')->orWhere('attempts', '>=', $maxAttempts)))
            ->orWhereExists(fn ($s) => $this->staleCurrentEvent($s));
    }

    /**
     * Aktuálna akcia zverejnená pred viac než `profile_wait_hours`.
     *
     * @param  \Illuminate\Database\Query\Builder  $q
     */
    private function staleCurrentEvent($q): void
    {
        Canal::currentEvents($q->selectRaw('1'))
            ->whereRaw('COALESCE(events.published_at, events.created_at) <= ?', [$this->waitLimit()]);
    }

    private function hasUsableEmail(Canal $canal): bool
    {
        return $canal->emails()->where('status', '!=', CanalEmailStatus::Undeliverable->value)->whereNull('pending_at')->exists()
            || CanalEmails::normalize($canal->email) !== null;
    }

    /* ---------------------------------------------------------------
     | Zdroje
     |---------------------------------------------------------------*/

    /** @return array<string, array{score: int, found_on: string}> */
    private function fromEvents(Canal $canal): array
    {
        $found = [];

        Event::query()->where('canal_id', $canal->id)
            ->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('additional_emails'))
            ->latest('start_at')->limit(50)->get(['id', 'email', 'additional_emails'])
            ->each(function (Event $event) use (&$found) {
                foreach ([$event->email, ...(array) $event->additional_emails] as $email) {
                    $email = CanalEmails::normalize(is_string($email) ? $email : null);

                    if ($email === null) {
                        continue;
                    }

                    $found[$email] ??= ['score' => 0, 'found_on' => "podujatie #{$event->id}", 'events' => 0];

                    // Opakovaná adresa naprieč podujatiami je skôr kontakt organizátora než jednej akcie.
                    if ($found[$email]['events'] < 3) {
                        $found[$email]['events']++;
                        $found[$email]['score'] += 10;
                    }
                }
            });

        return array_map(fn ($c) => ['score' => $c['score'], 'found_on' => $c['found_on']], $found);
    }

    /** @return array<string, array{score: int, found_on: string}> */
    private function fromWebsite(Canal $canal): array
    {
        $home = Url::normalize($canal->website);

        // Web, ktorý uvádza aj iný kanál, je spoločný portál (ecav.sk, kbs.sk) —
        // jeho adresy patria celku, nie tomuto kanálu.
        if ($home === null || $this->sharedWebsite($canal, $home)) {
            return [];
        }

        $html = $this->pages->fetch($home);

        if ($html === null) {
            return [];
        }

        $found = [];
        $this->collect($found, $html, $home, 20);

        foreach (array_slice($this->contactLinks($html, $home), 0, max(0, (int) config('canals.emails.discovery.pages', 2))) as $url) {
            if (($page = $this->pages->fetch($url)) !== null) {
                $this->collect($found, $page, $url, 25);
            }
        }

        return $found;
    }

    private function sharedWebsite(Canal $canal, string $home): bool
    {
        $host = preg_replace('/^www\./i', '', (string) Url::host($home));

        return Canal::query()->whereKeyNot($canal->id)->whereNull('merged_into_id')
            ->where('website', 'like', '%'.addcslashes($host, '%_\\').'%')
            ->get(['id', 'website'])
            ->contains(fn (Canal $other) => preg_replace('/^www\./i', '', (string) Url::host($other->website)) === $host);
    }

    /** @return array<int, array{email: string, found_on: ?string}> */
    private function fromResearch(Canal $canal): array
    {
        $bounced = $canal->emails()->where('status', CanalEmailStatus::Undeliverable->value)->pluck('email')->all();
        $fields = app(ProfileResearch::class)->research($canal, ['email'], ['undeliverable_emails' => $bounced]);
        $email = CanalEmails::normalize($fields['email']['value'] ?? null);
        $source = $fields['email']['source_url'] ?? null;

        if ($email === null || ! is_string($source) || in_array($email, $bounced, true) || $this->isJunk($email)) {
            return [];
        }

        // Model musí adresu nájsť, nie odhadnúť: na stránke, ktorú uvádza, doslova je.
        $page = $this->pages->fetch($source);

        if ($page === null || ! in_array($email, $this->extract($page), true)) {
            Recorder::info('canals', 'email_ai_rejected', "Kanál {$canal->name}: adresu {$email} od AI sa na zdroji nepodarilo overiť",
                status: 'skipped', recipient: $email, subject: $canal, context: ['source_url' => $source]);

            return [];
        }

        return [['email' => $email, 'found_on' => $source]];
    }

    private function aiAvailable(): bool
    {
        return (bool) AppSettings::get('canals.emails.discovery.ai', true)
            && (string) config('openai.api_key') !== ''
            && app(ProfileEnricher::class)->budgetAvailable();
    }

    /* ---------------------------------------------------------------
     | Výber
     |---------------------------------------------------------------*/

    /**
     * Najlepší kandidáti podľa skóre: adresa na doméne webu kanála, potom
     * všeobecná schránka. Vyradí známe, nedoručiteľné a šablónové adresy.
     *
     * @param  array<string, array{score: int, found_on: string}>  $found
     * @return array<int, array{email: string, found_on: string}>
     */
    private function best(Canal $canal, array $found): array
    {
        $domain = $this->registrable(Url::host($canal->website));
        $known = $canal->emails()->pluck('email')->all();
        $ranked = [];

        foreach ($found as $email => $candidate) {
            if ($this->isJunk($email) || in_array($email, $known, true) || EmailSuppression::has($email)) {
                continue;
            }

            [$local, $host] = explode('@', $email, 2);
            $score = $candidate['score']
                + ($domain !== null && $this->registrable($host) === $domain ? 40 : 0)
                + (preg_match(self::GENERIC_LOCAL, $local) ? 5 : 0);

            $ranked[] = ['email' => $email, 'found_on' => $candidate['found_on'], 'score' => $score];
        }

        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($c) => ['email' => $c['email'], 'found_on' => $c['found_on']],
            array_slice($ranked, 0, max(1, (int) config('canals.emails.discovery.keep', 3))));
    }

    private function isJunk(string $email): bool
    {
        [$local, $host] = explode('@', $email, 2) + [1 => ''];

        return preg_match(self::JUNK_LOCAL, $local) === 1
            || preg_match(self::JUNK_DOMAIN, $host) === 1
            // „logo@2x.png" a pod. z názvov súborov.
            || preg_match('/\.(png|jpe?g|gif|svg|webp|css|js)$/', $host) === 1;
    }

    /** Doména bez `www.` a subdomén — `farnost.sk` aj pre `mail.farnost.sk`. */
    private function registrable(?string $host): ?string
    {
        if ($host === null || $host === '') {
            return null;
        }

        $parts = explode('.', strtolower(preg_replace('/^www\./i', '', $host)));

        return implode('.', array_slice($parts, -2));
    }

    /* ---------------------------------------------------------------
     | HTML
     |---------------------------------------------------------------*/

    /** @param  array<string, array{score: int, found_on: string}>  $found */
    private function collect(array &$found, string $html, string $url, int $score): void
    {
        foreach ($this->extract($html) as $email) {
            if (! isset($found[$email])) {
                $found[$email] = ['score' => $score, 'found_on' => $url];
            } else {
                $found[$email]['score'] = min($found[$email]['score'] + 5, 60);
            }
        }
    }

    /**
     * Adresy na stránke: `mailto:` odkazy, text vrátane bežného maskovania
     * („info [at] farnost.sk", „info(zavináč)farnost.sk", `&#64;`).
     *
     * @return array<int, string>
     */
    public function extract(string $html): array
    {
        // Staršie slovenské weby (napr. tkkbs.sk) posielajú windows-1250 — regex s /u
        // by na neplatnom UTF-8 vrátil null a stránka by nedala žiadnu adresu.
        if (! mb_check_encoding($html, 'UTF-8')) {
            $html = (string) @iconv('Windows-1250', 'UTF-8//IGNORE', $html);
        }

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // %xx z odkazov môže dať bajty mimo UTF-8 — tie sa zahodia, adresy sú ASCII.
        $text = mb_scrub(rawurldecode(preg_replace('/%(?![0-9a-f]{2})/i', '%25', $text)), 'UTF-8');
        $text = preg_replace('/\s*[\[\(\{]\s*(at|zavin[aá][cč]|@)\s*[\]\)\}]\s*/iu', '@', $text);
        $text = preg_replace('/\s*[\[\(\{]\s*(dot|bodka|\.)\s*[\]\)\}]\s*/iu', '.', $text);

        preg_match_all('/[a-z0-9][a-z0-9._%+-]{0,63}@[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,24}/i', $text, $matches);

        $emails = [];

        foreach ($matches[0] as $match) {
            if (($email = CanalEmails::normalize(rtrim($match, '.'))) !== null) {
                $emails[$email] = true;
            }
        }

        return array_keys($emails);
    }

    /** @return array<int, string> kontaktné podstránky na tom istom webe */
    private function contactLinks(string $html, string $base): array
    {
        $host = $this->registrable(Url::host($base));
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $links = [];

        foreach ((new DOMXPath($dom))->query('//a[@href]') ?: [] as $anchor) {
            $href = (string) $anchor->getAttribute('href');

            if (! preg_match(self::CONTACT_LINK, $href.' '.$anchor->textContent)) {
                continue;
            }

            $url = $this->pages->absolute($base, $href);

            if ($url !== null && $url !== $base && $this->registrable(Url::host($url)) === $host) {
                $links[$url] = true;
            }
        }

        return array_keys($links);
    }
}
