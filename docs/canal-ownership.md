# Správa kanála: prevzatie, tím, notifikácie

## Pojmy
| Pojem | Kde | Význam |
|---|---|---|
| Pôvod | `canals.registration_source` | Ako kanál vznikol (`self`, `admin`, `import`, `system`). Nemení sa. |
| Správa | `canals.claimed_at`, `claimed_by_user_id` | Či za kanálom stojí človek. `Canal::isManaged()` = pôvod `self`/`admin` **alebo** prevzatý. |
| Členstvo | `canal_user.role` | `owner` / `editor` / `checkin`. Mení len `CanalMembership`. |
| Verejný kontakt | `canals.email`, `phone` | Údaj na stránke. Po fáze 2 už nerozhoduje, kto dostáva notifikácie. |
| Technický vlastník | super-admin (`ImportedCanalManager::systemOwner()`) | Vlastník neprevzatého importu. Neoslovuje sa. |
| Zberný kanál | `CollectionCanal::is()` | Importovaný kanál pomenovaný po zdroji (napr. vyveska.sk), obsahuje podujatia rôznych organizátorov. |

## Fáza 1: Prevzatie kanála (hotové)
- `CanalStewardship::complete($canal, $user)`: nový vlastník, odobratie technického vlastníka, `claimed_at`, zápis do denníka a e-mail.
- Spúšťa ho `CanalInviter::accept()` pri pozvánke `owner` na nespravovaný kanál. Ostatné pozvánky iba pridajú člena.
- Prevzatý kanál:
  - správy a prihlášky idú vlastníkom (`messageRecipient()`, `EventSignup::managingOwners()`),
  - import doň len priraďuje podujatia: nemení meno ani web a nevracia super-admina,
  - `CanalSeatDeriver` mu už neodvodzuje obec.
- Zberný kanál sa prevziať nedá (chyba `canal_team.collection_not_claimable`). `EventSignup` naň neposiela pozvánku na prevzatie.
- Migrácia `2026_09_28_100000_add_claimed_at_to_canals_table` označí kanály, ktoré už niekto prevzal cez prijatú pozvánku, a odoberie z nich super-admina. Každé takéto prevzatie zapíše ako `canals.claimed` s príznakom `backfill`.

## Denník zmien (`system_logs`, kanál `canals`)
Zapisuje `CanalAuditor`, volajú ho `CanalMembership`, `CanalInviter` a `CanalStewardship`.

| Udalosť | Kedy |
|---|---|
| `canals.member_added` / `member_removed` / `role_changed` | Každá zmena členstva. Kontext obsahuje `from`, `to`, `actor_id`, pri založení kanála aj `reason` (`canal_created`, `personal_canal`, `import_technical_owner`). |
| `canals.claimed` | Prevzatie kanála. Kontext obsahuje `removed_technical_owner_id`. |
| `canals.invitation_sent` / `resent` / `revoked` / `accepted` / `claim_created` | Pozvánky. |
| `mail.simulated` | E-mail vyskladaný, ale neodoslaný. |

## Simulované e-maily
- `MailSimulator::send()` e-mail naozaj vyskladá (`toMail`). Pri simulácii ho neodošle, iba zapíše adresáta, predmet, riadky a odkaz so stavom `simulated`.
- Prepínač `CANAL_OWNERSHIP_MAIL_SIMULATE` (`config/canals.php`), predvolene `true`. Pri `false` sa e-maily reálne posielajú.
- `CanalOwnershipChanged` ide len pri zmene **vlastníka spravovaného** kanála a nikdy nie tomu, kto zmenu urobil:

| Zmena | Komu |
|---|---|
| `claimed` | preberajúcemu + na `canals.email`, ak je iný (maskovaný e-mail a veta „ak o tom neviete…“) |
| `owner_added` | novému vlastníkovi + ostatným vlastníkom |
| `owner_removed` (odobratie alebo preradenie) | dotknutému + ostatným vlastníkom |

## Kto dostane aký e-mail (dnes)
| Udalosť | Spravovaný kanál | Nespravovaný kanál |
|---|---|---|
| Prihláška na akciu (`EventSignupOrganizerNotice`) | všetci vlastníci | kontakt (`event.email` → `canal.email`) + odkaz na prevzatie; pri zbernom kanáli nikto |
| Prihláška na akciu (`EventSignupAdminNotice`) | super-admini | super-admini |
| Správa od návštevníka (`MessageReceived`) | prvý vlastník, ktorý môže prijímať správy | nikto, formulár sa neponúkne |
| Doplnený profil (`ProfileCompleted`) | ako správa | nikto |
| Pozvánka do tímu (`CanalInvitationSent`) | pozvaná adresa | pozvaná adresa |
| Zmena vlastníka (`CanalOwnershipChanged`) | simulované, pozri vyššie | nikto |
| Ponuka prevzatia pred akciou / pri zobrazeniach / po akcii (Account Emailing) | nikto | kontakt kanála, najviac 3 za 90 dní (fáza 5) |

## Fáza 2: Príjemcovia notifikácií (hotové)
- Témy (`CanalNotificationTopic`): `signups`, `messages`, `questions`, `reviews` (kontroly obsahu, atribútov, doplnený profil).
- Predvoľby podľa roly: owner = všetko, editor = `signups` + `questions`, checkin = nič. V `canal_notification_settings` sú len odchýlky. Návrat na predvoľbu riadok zmaže, odchod z tímu zmaže všetky nastavenia člena.
- `CanalRecipients`:
  - `subscribers()`: prihlásení členovia spravovaného kanála s overeným e-mailom, zoradení podľa roly,
  - `primary()`: hlavný adresát témy; `Canal::messageRecipient()` = správy, `attributeIssueRecipient()` = kontroly,
  - `mayNotify()`: doterajší adresát dostane e-mail, ak si tému nevypol; inak `mail.skipped`,
  - `fanOut()`: kópia ďalším prihláseným členom.
- **Autor podujatia** dostáva e-maily k vlastnému podujatiu vždy. Nastavenia riadia len e-maily „za kanál“.
- **Simulácia** `CANAL_TEAM_NOTIFICATIONS_SIMULATE` (predvolene `true`): kópie novým adresátom idú len do denníka (`mail.simulated`) a hlavným adresátom správ môže byť len vlastník. Ak si všetci vlastníci vypnú správy, kanálu sa správa poslať nedá.
- Zmena nastavenia sa zapíše ako `canals.notifications_changed` (téma, stav, `actor_id`).
- API: `PUT /api/dashboard/canals/{canal}/team/{user}/notifications {topic, enabled}`. Svoje nastavenia mení každý člen, cudzie len ten, kto spravuje tím. UI: prepínače pri členoch v paneli tímu.

| Téma | Doterajší adresát (reálne) | Kópia tímu (simulovane) |
|---|---|---|
| `signups` | vlastníci (okrem tých, čo si tému vypli) | editori a ďalší prihlásení |
| `messages` | hlavný adresát (vlastník), autor podujatia pri správe k podujatiu | ďalší prihlásení |
| `questions` | autor podujatia, prípadne vlastník | prihlásení členovia |
| `reviews` | hlavný adresát témy kontroly, autor podujatia | ďalší prihlásení |

## Fáza 3: Prevzatie cez iný účet a zmena kontaktu (hotové)
Každé prevzatie má záznam v `canal_claims` (`CanalClaims`). Záznamy sa nemažú.

| Cesta (`method`) | Kto začne | Čo to dokazuje |
|---|---|---|
| `invitation` | systém (`ensureOwnerInvitation` pri prihláške) | token z kontaktnej schránky; prijať ho smie **ktorýkoľvek** prihlásený účet |
| `contact_email` | používateľ tlačidlom „Spravujete tento kanál?“ | kontaktná schránka potvrdí odkaz `/prevzatie/{token}` bez prihlásenia (platí 7 dní) |
| `admin_review` | používateľ, keď kontakt nie je alebo nevie | administrátor schváli vo fronte `/admin/prevzatia` |

- Stavy: `pending` → `completed` → (`contested` → `reverted` / späť `completed`); ďalej `rejected` a `expired`. Pri dokončení prepadnú ostatné čakajúce žiadosti o ten istý kanál.
- **Námietka:** po prevzatí dostane kontaktná adresa (a adresa, ktorá prevzatie preukázala) e-mail s odkazom `/prevzatie/namietka/{token}`, platným 7 dní. Napadnuté prevzatie posúdi admin: **ponechať** alebo **vrátiť** (`CanalStewardship::revert`: späť technický vlastník, tím odíde, `claimed_at` = null).
- Jeden účet môže mať naraz najviac 3 nevybavené žiadosti (`CanalClaims::MAX_OPEN_PER_USER`). Žiadosť cez kontakt píše do cudzej schránky a toto je ochrana pred spamom.
- Prevziať sa nedá spravovaný, zmazaný ani zberný kanál. Verejný detail kanála má príznaky `claimable` a `claim_contact`.
- **Zmena kontaktného e-mailu** (`CanalContactVerifier`, pri uložení kanála): `email_verified_at` sa vynuluje, nová adresa dostane podpísaný odkaz (`/api/canals/{id}/contact/verify`, 7 dní) a pôvodná upozornenie. Pri nespravovanom kanáli sa zmena len zapíše.
- Denník: `canals.claim_requested|confirmed|approved|accepted|rejected|contested|contest_dismissed|reverted|expired`, `canals.contact_changed|contact_verified`.
- E-maily (`CanalClaimNotice`, `CanalContactNotice`) sú simulované: `CANAL_CLAIM_MAIL_SIMULATE`, `CANAL_CONTACT_MAIL_SIMULATE` (predvolene `true`). Odkaz na potvrdenie je v kontexte záznamu `mail.simulated`, takže sa dá vyskúšať aj počas simulácie.

| Udalosť | Komu |
|---|---|
| žiadosť `contact_email` | kontaktná adresa kanála (odkaz na potvrdenie) |
| žiadosť `admin_review`, námietka | super-admini |
| zamietnutie, vrátenie | žiadateľ |
| prevzatie | preberajúci + kontakt/adresa pozvánky (s odkazom na námietku) |
| zmena kontaktu | nová adresa (overenie) + pôvodná (upozornenie) |

## Fáza 4: Výber kandidátov na oslovenie (hotové)
Oslovenie samotné ide cez Account Emailing (fáza 5); tu je výber kandidátov a odhlásenie:
- Príkaz `app:canal-outreach` (`CanalOutreachSender`) beží denne o 10:15.
- **Kandidát** (`config/canals.php → outreach`):
  - kanál je importovaný, neprevzatý, nie zberný, s platným kontaktom (`canals.email`, inak e-mail podujatia),
  - kontakt nie je v `email_suppressions`,
  - berie sa najviac jeden trigger na kanál a najviac `limit` kanálov za beh.
- **Odhlásenie** (`GET/POST /api/email/unsubscribe`, podpísané) zapíše adresu do `email_suppressions`, zapíše `mail.unsubscribed` a presmeruje na `/odhlasenie`. Platí aj pre ponuku prevzatia pri prihláške (`EventSignup`).
- Organizátori podujatí v zbernom kanáli sa neoslovujú. Najprv ich treba presunúť do vlastného kanála (`EventOrganizerReassigner`).

## Fáza 5: Ponuka prevzatia cez Account Emailing
Príkaz `app:canal-outreach` (denne 10:15) hlási do Accountu udalosti; text, oneskorenie (`delay_hours`) aj zapnutie kampane spravuje operátor v Accounte (menu Emailing). Bez `ACCOUNT_MAILING` sa neoslovuje nikto. Pred zapnutím kampane overiť súlad s § 116 zákona č. 452/2021 Z. z. a skontrolovať výstup `--dry-run`.

- **Triggery** (`MailingTrigger`, poradie priority, v jednom behu najviac jeden na kanál):

| Trigger | Kedy (`config/canals.php → outreach`) |
|---|---|
| `canal_event_upcoming` | zverejnená akcia začína najneskôr o `upcoming_days` (10) dní; importovaná neskôr dostane mail hneď |
| `canal_views_milestone` | zverejnená akcia pred koncom dosiahla prah z `view_milestones` (50, 200); každý prah raz |
| `canal_event_finished` | skončila pred 1–14 dňami (`after_days`, `window_days`), aspoň `min_views` zobrazení |

- **Strop postupnosti:** najviac `max_per_cooldown` (3) oslovení za `cooldown_days` (90), medzi dvoma aspoň `min_gap_days` (5). Každý trigger k akcii len raz (`dedupe_key` `canal:{id}:{trigger}:{akcia}[:{prah}]`).
- **Hlásenie** (`ReportCanalOutreach`, cez frontu): najprv kontakt do adresára (`POST /mailing/contacts`, štítok `canal_contact`, súhlas „verejný kontakt organizátora“), potom udalosť s `ref` `{type: canal}` — odpoveď sa v Inboxe spáruje s kanálom.
- **Premenné do textu:** `{kanal}`, `{podujatie}`, `{termin}`, `{miesto}`, `{odkaz_podujatia}`, `{odkaz_kanala}`, `{odkaz_prevzatia}`, `{zobrazenia}`, `{ludia}`, `{prihlaseni}`, `{dni_do_zaciatku}`, `{prah_zobrazeni}`. Odkaz na prevzatie je systémová pozvánka s platnosťou `invitation_days` (30); ďalší mail ju len predĺži.
- **Stavy** v `canal_outreach`: `reported` → `queued` (kampaň mail zaradila) / `not_queued` (žiadna aktívna kampaň — do stropu sa neráta a nahlási sa znova) → podľa webhooku `sent` / `failed` / `bounced`.
- **Zastavenie:**
  - prevzatie kanála (`CanalStewardship::complete`) zruší čakajúce maily (`/mailing/events/cancel`, ref `canal`),
  - odhlásenie v kampani → webhook `mailing.unsubscribed` → `EmailSuppression`; odhlásenie v Evente sa nahlási do adresára (`unsubscribed: true`),
  - nedoručiteľnosť → webhook `mailing.delivery.bounced` → `CanalEmails::recordBounce` (adresa je vyradená a v `EmailSuppression`).
- **Nastavenie v Accounte:** po nasadení `php artisan emailing:register-triggers`; webhook Eventu musí odoberať `mailing.unsubscribed` a `mailing.delivery.*`. Kampaň na každý trigger (napr. `canal_event_upcoming` s `delay_hours` 0, `canal_event_finished` s 2 h). Simuláciu tu nahrádza stav kampane v Accounte a `MAILING_DRY_RUN`.
- Parametre príkazu: `--dry-run`, `--limit=N`, `--trigger=canal_event_upcoming`.

## Prehľad prepínačov simulácie
Všetky sú predvolene `true`, teda nič z nového neodchádza a všetko je v denníku ako `mail.simulated`.

| Premenná | Čo riadi |
|---|---|
| `CANAL_OWNERSHIP_MAIL_SIMULATE` | e-maily o zmene vlastníka (fáza 1) |
| `CANAL_TEAM_NOTIFICATIONS_SIMULATE` | kópie notifikácií ďalším členom tímu (fáza 2) |
| `CANAL_CLAIM_MAIL_SIMULATE` | žiadosti, potvrdenia, námietky, zamietnutia (fáza 3) |
| `CANAL_CONTACT_MAIL_SIMULATE` | overenie zmeneného kontaktu (fáza 3) |
