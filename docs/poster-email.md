# Podujatie z plagátu, textu alebo e-mailu

Tri cesty k tomu istému konceptu (`poster_drafts`), z ktorého po potvrdení vznikne podujatie, kanál a miesto (`PosterDraftMaterializer`):

1. **Súbory a/alebo text** na `/nahrat-plagat`: až 4 súbory (PDF, Word, obrázky) a sprievodný text v jednom formulári. Samotný text musí mať aspoň 30 znakov, pri súbore stačí krátka poznámka. Na homepage tlačidlo „Vložiť text pozvánky" (`/nahrat-plagat?mode=text`) dá kurzor do textu.
2. **E-mail na kontaktnú adresu** (`PosterEmailIntake`): text e-mailu a prílohy.

Obe cesty spájajú vstup rovnako (`PosterTextExtractor::combine`): texty za sebou, obrázky spolu najviac 4 na vision. Prvý prečítaný súbor sa uloží ako plagát.

## Organizátor a miesto zo zoznamu

- Sprievodca ponúka zverejnené miesta a kanály z vybranej obce (`GET /api/poster/places`, pri meste aj mestské časti).
- Vybrané miesto sa použije priamo (`overrides.venue_id`).
- Vybraný kanál (`overrides.canal_id`) dostane podujatie, len keď v ňom autor smie tvoriť. Inak podujatie vznikne pod kanálom autora a správca vybraného kanála dostane e-mail (`PosterOrganizerNotice`). Kanál bez správcu (import) nedostane nič. ID kanála sa zapíše do `events.meta.poster_upload.organizer_canal_id`.

Z e-mailu sa nikdy nič nezverejní. Podujatie vznikne až vtedy, keď odosielateľ otvorí odkaz na koncept a uloží ho (`PosterController::claim`, pri tom si vytvorí účet). Kanál mu odoslaním e-mailu nepatrí, platí bežné prevzatie (`canal-ownership.md`).

## E-mail na kontaktnú adresu

- Account pošle nový e-mail mimo konverzácie ako `message.unassigned`. Webhook ho odovzdá do fronty (`ProcessPosterEmail`).
- Na kontakt chodí aj bežná pošta, preto prejde len e-mail, ktorý vyzerá ako pozvánka: má dátum alebo prílohu (plagát, PDF, Word). Neprejde e-mail s odkazom na existujúce podujatie (`/akcie/{id}`), lebo to je žiadosť o opravu. Potom AI musí nájsť názov a termín alebo miesto. Inak e-mail ostane nepriradený v inboxe Accountu operátorovi.
- Z textu e-mailu a všetkých čitateľných príloh (najviac 4, spolu 4 obrázky na vision) vznikne koncept (`channel = email`). Príloha bez prípony sa spozná podľa MIME. Prvá prečítaná príloha sa uloží ako plagát.
- Keď pozvánka neuvádza kontaktný e-mail, kontaktom je odosielateľ (`PosterDraft::senderEmail()`): v reporte, v predvyplnenom formulári aj v uloženom podujatí. Odosielateľ dostane odpoveď do tej istej konverzácie (`PosterEmailReply`): čo sme pochopili, čo chýba a odkaz na koncept. Odpoveď si konverzáciu priradí (ref `poster_draft`).
- Odpoveď odosielateľa príde ako `message.received`. Doplnenie sa pridá k textu, rozbor sa zopakuje a ide ďalšia odpoveď, spolu najviac `POSTER_EMAIL_MAX_ROUNDS` kôl. Ďalšie odpovede ostávajú v inboxe Accountu.
- Limity proti nákladom na AI: `POSTER_EMAIL_PER_SENDER_DAILY` (predvolene 3) a `POSTER_EMAIL_DAILY` (30) konceptov za deň. Nad limitom ostane e-mail operátorovi.

## Nastavenie

- `POSTER_EMAIL_INTAKE=true`, `POSTER_EMAIL_INBOX=<adresa schránky Eventu v Accounte>`. Adresa sa ukáže na webe a spracúva sa len pošta doručená do nej.
- Vyžaduje `ACCOUNT_MESSAGING=true` a vo webhooku Eventu v Accounte odber `message.unassigned`.
- Migrácia `2026_11_07_120000_add_email_intake_to_poster_drafts.php`, bežiaca fronta.
