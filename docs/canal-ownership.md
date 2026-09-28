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
| Oslovenie po akcii (`CanalOutreachNotice`) | nikto | kontakt kanála, raz za 90 dní, simulované (fáza 4) |

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

## Fáza 4: Oslovenie po akcii (hotové, simulované)
- Príkaz `app:canal-outreach` (`CanalOutreachSender`) beží denne o 10:15. Parametre: `--dry-run` len vypíše kandidátov, `--limit=N`.
- **Kandidát** (`config/canals.php → outreach`):
  - akcia skončila pred 1 až 14 dňami (`after_days`, `window_days`),
  - kanál je importovaný, neprevzatý, nie zberný, s platným kontaktom (`canals.email`, inak e-mail podujatia),
  - kontakt nie je v `email_suppressions`,
  - kanál nebol oslovený posledných 90 dní (`cooldown_days`),
  - akcia má aspoň `min_views` zobrazení; berie sa posledná akcia kanála, najviac `limit` kanálov za beh.
- **E-mail** (`CanalOutreachNotice`):
  - zobrazenia a počet ľudí z `views`, prihlásení z `admissions`,
  - tlačidlo „Prevziať profil“ = systémová pozvánka (`ensureOwnerInvitation`), ktorú prijme ktorýkoľvek účet (fáza 3),
  - vysvetlenie, prečo píšeme, a odhlásenie jedným klikom (podpísaný odkaz aj hlavičky `List-Unsubscribe` a `List-Unsubscribe-Post`).
- **Odhlásenie** (`GET/POST /api/email/unsubscribe`, podpísané) zapíše adresu do `email_suppressions`, zapíše `mail.unsubscribed` a presmeruje na `/odhlasenie`. Platí aj pre ponuku prevzatia pri prihláške (`EventSignup`).
- **Evidencia** `canal_outreach`: kanál, akcia, adresa, čísla, pozvánka a stav `sent`/`simulated`. Denník: `canals.outreach_sent|outreach_simulated`.
- **Simulácia** `CANAL_OUTREACH_MAIL_SIMULATE` (predvolene `true`): e-mail ide len do denníka. Kým simulácia beží, neopakuje sa ani simulované oslovenie; ostrý beh potom počíta odstup len od skutočne odoslaných.
- **Pred vypnutím simulácie** overiť súlad s § 116 zákona č. 452/2021 Z. z. a skontrolovať výstup `--dry-run`.
- Organizátori podujatí v zbernom kanáli sa neoslovujú. Najprv ich treba presunúť do vlastného kanála (`EventOrganizerReassigner`).

## Prehľad prepínačov simulácie
Všetky sú predvolene `true`, teda nič z nového neodchádza a všetko je v denníku ako `mail.simulated`.

| Premenná | Čo riadi |
|---|---|
| `CANAL_OWNERSHIP_MAIL_SIMULATE` | e-maily o zmene vlastníka (fáza 1) |
| `CANAL_TEAM_NOTIFICATIONS_SIMULATE` | kópie notifikácií ďalším členom tímu (fáza 2) |
| `CANAL_CLAIM_MAIL_SIMULATE` | žiadosti, potvrdenia, námietky, zamietnutia (fáza 3) |
| `CANAL_CONTACT_MAIL_SIMULATE` | overenie zmeneného kontaktu (fáza 3) |
| `CANAL_OUTREACH_MAIL_SIMULATE` | oslovenie po akcii (fáza 4) |
