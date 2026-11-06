# Podujatie z plagátu, textu alebo e-mailu

Tri cesty k tomu istému konceptu (`poster_drafts`), z ktorého po potvrdení vznikne podujatie, kanál a miesto (`PosterDraftMaterializer`):

1. **Plagát alebo súbor** na `/nahrat-plagat` (PDF, Word, obrázok).
2. **Vložený text** — pozvánka, celý e-mail alebo pár viet. Na homepage tlačidlo „Vložiť text pozvánky" (`/nahrat-plagat?mode=text`).
3. **E-mail na kontaktnú adresu** (`PosterEmailIntake`).

Z e-mailu sa nikdy nič nezverejní. Podujatie vznikne až vtedy, keď odosielateľ otvorí odkaz na koncept a uloží ho (`PosterController::claim`, pri tom si vytvorí účet). Kanál mu odoslaním e-mailu nepatrí, platí bežné prevzatie (`canal-ownership.md`).

## E-mail na kontaktnú adresu

- Account pošle nový e-mail mimo konverzácie ako `message.unassigned`. Webhook ho odovzdá do fronty (`ProcessPosterEmail`).
- Na kontakt chodí aj bežná pošta, preto prejde len e-mail, ktorý vyzerá ako pozvánka: má dátum alebo prílohu (plagát, PDF, Word). Neprejde e-mail s odkazom na existujúce podujatie (`/akcie/{id}`), lebo to je žiadosť o opravu. Potom AI musí nájsť názov a termín alebo miesto. Inak e-mail ostane nepriradený v inboxe Accountu operátorovi.
- Z textu e-mailu a prvej čitateľnej prílohy vznikne koncept (`channel = email`). Odosielateľ dostane odpoveď do tej istej konverzácie (`PosterEmailReply`): čo sme pochopili, čo chýba a odkaz na koncept. Odpoveď si konverzáciu priradí (ref `poster_draft`).
- Odpoveď odosielateľa príde ako `message.received`. Doplnenie sa pridá k textu, rozbor sa zopakuje a ide ďalšia odpoveď, spolu najviac `POSTER_EMAIL_MAX_ROUNDS` kôl. Ďalšie odpovede ostávajú v inboxe Accountu.
- Limity proti nákladom na AI: `POSTER_EMAIL_PER_SENDER_DAILY` (predvolene 3) a `POSTER_EMAIL_DAILY` (30) konceptov za deň. Nad limitom ostane e-mail operátorovi.

## Nastavenie

- `POSTER_EMAIL_INTAKE=true`, `POSTER_EMAIL_INBOX=<adresa schránky Eventu v Accounte>`. Adresa sa ukáže na webe a spracúva sa len pošta doručená do nej.
- Vyžaduje `ACCOUNT_MESSAGING=true` a vo webhooku Eventu v Accounte odber `message.unassigned`.
- Migrácia `2026_11_07_120000_add_email_intake_to_poster_drafts.php`, bežiaca fronta.
