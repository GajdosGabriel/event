# Napojenie organizácií na Account

## Prečo

Fakturačná identita organizátora (IČO, DIČ, IČ DPH, sídlo, zápis v registri,
banka) je tá istá naprieč všetkými projektmi. Keby si ju každý projekt držal
sám, po prvej zmene adresy by existovali tri rozchádzajúce sa verzie tej istej
firmy a nikto by nevedel, ktorá platí na faktúre.

Preto to funguje takto:

| Kde | Čo vlastní |
|---|---|
| **Event** | verejný profil organizátora — názov, popis, avatar, obec, web, stav publikovania |
| **Account** | fakturačná identita — IČO, DIČ, IČ DPH, sídlo, register, banka, platobné podmienky |

Event si drží jediný stĺpec navyše: `organizations.account_uuid`. Fakturačné
polia **nie sú a nesmú byť** v tabuľke `organizations` — čítajú sa cez
`AccountClient` a držia v cache, ktorú invaliduje webhook.

Bez vyplneného `ACCOUNT_TOKEN` je celé napojenie ticho vypnuté a Event pracuje
len s lokálnym profilom. Nič sa nikam nevolá.

---

## Sprevádzkovanie

### 1. Produkt a token v Accounte

V Accounte musí existovať produkt s kľúčom `event` (založí sa v administrácii
Accountu v sekcii **Produkty**). Token sa potom vygeneruje takto:

```bash
php artisan accounts:issue-token event "event – produkcia"
```

Token sa zobrazí **iba raz**. Musí mať ability `organizations:read`
a `organizations:write` — bez `write` prejde čítanie a lookup, ale založenie
firmy skončí na 403.

### 2. `.env` v Evente

```env
ACCOUNT_URL=https://account.tvojafirma.sk
ACCOUNT_TOKEN=acc_xxxxxxxxxxxxxxxxxxxx
ACCOUNT_WEBHOOK_SECRET=whsec_xxxxxxxx
```

### 3. Migrácia

```bash
php artisan migrate
```

Pridá `organizations.account_uuid` a `organizations.account_synced_at`.

### 4. Webhook v Accounte

V Accounte (**API a webhooky → nový endpoint**) zaregistruj:

```
https://<event>/api/webhooks/account
```

Odoberané udalosti: `organization.updated`, `organization.deleted`.
Tajomstvo endpointu patrí do `ACCOUNT_WEBHOOK_SECRET`.

Bez webhooku to funguje tiež, len sa zmena z iného projektu prejaví v Evente
až po vypršaní cache (`ACCOUNT_ORGANIZATION_TTL`, predvolene hodina).

---

## Ako to beží

### Založenie organizácie

`POST /api/dashboard/organizations`

```json
{
  "title": "Kultúrny dom Trenčín",
  "status": "draft",
  "account": {
    "ico": "12345678",
    "street": "Hlavná 1",
    "city": "Trenčín",
    "postal_code": "91101",
    "country": "SK"
  }
}
```

1. Event uloží lokálny profil.
2. `OrganizationSync::push()` pošle blok `account` do Accountu.
3. Account **najprv hľadá podľa IČO**. Ak firma už existuje z iného projektu,
   iba sa na ňu naviaže a vráti jej uuid — nová sa nezakladá.
4. Event si uloží `account_uuid` a `account_synced_at`.

Krok 3 je celý dôvod, prečo sa nikdy nevolá vlastné „create“ v Accounte.

Zápis beží **v tej istej DB transakcii** ako lokálny insert. Keď Account IČO
odmietne, transakcia sa vráti a v Evente nezostane polovičná organizácia,
ktorú by musel niekto dohľadávať.

### Úprava

`PUT /api/dashboard/organizations/{id}` — ak je organizácia naviazaná, ide
`PUT` do Accountu, inak sa firma v Accounte založí (rovnaká cesta ako vyššie).

### Detail

`GET /api/dashboard/organizations/{id}` vráti navyše kľúč `account`
s fakturačnými údajmi z Accountu. **Vo výpise `account` nie je** — jedno HTTP
volanie na riadok by výpis položilo.

### Predvyplnenie z registra

`POST /api/dashboard/organizations/lookup-ico` s `{ "ico": "12345678" }`.
Register (RPO pre SK, ARES pre CZ) volá Account, nie Event. Zlyhanie nie je
chyba — vráti sa `found: false` a používateľ údaje dopíše ručne.

---

## Firma alebo súkromná osoba

Nie každý platiaci je podnikateľ. Event rozlišuje typ stĺpcom
`organizations.person`, Account enumom `subject_type` (`company` | `person`);
prekladá to [`OrganizationSync`](../app/Services/Account/OrganizationSync.php),
takže zvyšok Eventu o tom vedieť nemusí.

Pri súkromnej osobe:

- formulár nepýta IČO, DIČ, IČ DPH ani zápis v registri,
- Account tie polia pri uložení **vyprázdni** (observer, nie formulár — inak by
  stačilo na jednom mieste zabudnúť a občanovi by na faktúre zostalo IČO firmy,
  ktorou kedysi bol),
- `missingBillingFields()` IČO nepýta; na doklad stačí meno, adresa a e-mail.

## Overený e-mail na faktúry

Adresa, na ktorú doklady odchádzajú, sa potvrdzuje odkazom v e-maile. Account
ju po uložení pošle sám, stav vracia v `contact.billing_email_verified`
a Event ho ukazuje pri poli.

Preklep v adrese sa tak neprejaví ako „úspešne odoslané“ do cudzej schránky —
overovací e-mail jednoducho nikam nedôjde a firma zostane neoverená.

## Validácia

Pravidlá pre IČO (kontrolná číslica) a IČ DPH (overenie cez VIES) sú **len
v Accounte**. Event kontroluje iba hrubý tvar, aby sa neposielal nezmysel.

Chyby z Accountu prídu ako 422 a `OrganizationSync::prefixErrors()` ich
premenuje z `ico` na `account.ico`, aby sadli na polia formulára v SPA.
Používateľ netuší, že prišli z inej aplikácie.

---

## Keď Account nebeží

| Operácia | Správanie |
|---|---|
| čítanie (`pull`) | vráti poslednú známu hodnotu z cache (`ACCOUNT_STALE_TTL`, 7 dní), inak `null` |
| lookup IČO | `found: false` s hláškou, formulár sa vypĺňa ručne |

**Vyhľadanie IČO má vlastný, dlhší timeout** (`ACCOUNT_LOOKUP_TIMEOUT`, 15 s).
Account pri ňom čaká na štátny register a sám si dáva 10 s; studené volanie
trvá bežne 5–10 s. So spoločným 4-sekundovým stropom by Event spojenie utínal
skôr, než register odpovie — a hlásil by „register je nedostupný“ pri údajoch,
ktoré sa práve našli. Opakované volanie je rýchle, lebo Account úspešné
vyhľadanie cachuje na deň.
| zápis (`push`) | **zlyhá** a transakcia sa vráti |

Zápis sa zámerne nezaraďuje do fronty na neskôr. Tichý retry by znamenal, že
používateľ vidí uložené údaje, ktoré v Accounte nie sú — a pri ďalšej úprave
by sa obe verzie rozišli.

### Odkiaľ príde hláška o chybe

Zdrojom pravdy je odpoveď Accountu — on jediný vie, čo sa pokazilo:

| Čo sa stalo | Event vráti | Hláška |
|---|---|---|
| Account neodpovedá (`ConnectionException`) | 503 | vlastná: „Account neodpovedá“ |
| Account vrátil 422 | 422 | jeho chyby, premenované na `account.*` |
| Account vrátil inú chybu s hláškou | 502 | `Account: <jeho hláška>` |
| Account vrátil 5xx bez užitočnej hlášky | 502 | vlastná, s číslom stavu a odkazom do logu |

**Stav sa zámerne nepreposiela.** SPA Eventu pri 401 maže prihlasovací token —
odvolaný service token by tak odhlásil používateľa z Eventu, hoci s jeho
prihlásením to nemá nič spoločné. Navonok je to preto vždy 502.

Do logu Eventu ide **celé telo odpovede**, nie len `getMessage()` — Guzzle
správu po 120 znakoch odreže a odrezané býva práve to podstatné.

---

## Čo do Eventu nepridávať

**Neukladaj sem IČO, DIČ ani adresu**, ani „len pre istotu“, ani do cache
tabuľky. Vlastníkom je Account.

Tabuľka `organizations` v Evente drží presne toto a nič viac:

| Stĺpec | Načo |
|---|---|
| `account_uuid`, `account_synced_at` | väzba na Account |
| `title`, `slug` | názov a adresa profilu |
| `person` | osoba vs. organizácia — riadi, čo sa pýta |
| `description`, `village_id` | verejný profil organizátora |
| `email`, `phone`, `website` | kontakt pre **návštevníkov** portálu — nie fakturačný, ten je v Accounte |
| `published`, `status`, `deleted_at` | životný cyklus a práva v Evente |

V auguste 2026 sa odtiaľ odstránilo `street` a `psc` (adresu vlastní Account)
a `avatar`, `mod_title`, `phone_numeric`, `youtube_channel`, `youtube_playlist`
(nečítalo ich nič okrem validácie). Obrázky v tomto projekte drží tabuľka
`files`, nie stĺpec — preto tu `avatar` nemá čo robiť.

Výnimka, ak by raz bolo treba podľa firmy triediť alebo filtrovať v SQL:
tenká read-model tabuľka (`account_uuid`, `name`, `ico`, `synced_at`) plnená
**z webhooku**, a nič viac v nej.

---

## Súbory

| Súbor | Čo robí |
|---|---|
| [`config/account.php`](../config/account.php) | URL, token, timeouty, TTL cache |
| [`app/Services/Account/AccountClient.php`](../app/Services/Account/AccountClient.php) | HTTP klient — čítanie degraduje, zápis nie |
| [`app/Services/Account/OrganizationSync.php`](../app/Services/Account/OrganizationSync.php) | mapovanie polí, push/pull, premenovanie chýb |
| [`app/Http/Controllers/Webhooks/AccountWebhookController.php`](../app/Http/Controllers/Webhooks/AccountWebhookController.php) | príjem udalostí, overenie HMAC podpisu |
| `tests/Feature/Organizations/OrganizationAccountSyncTest.php` | testy — Account sa v nich nikdy nevolá naozaj |

Zdrojová predloha klienta je v Accounte v priečinku `klient-pre-projekty/`.
