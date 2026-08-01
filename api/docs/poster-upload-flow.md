# Nahranie plagátu → podujatie

„Nahrajte plagát, o všetko ostatné sa postaráme." Vstupný tok pre organizátora,
ktorý na portáli ešte nikdy nebol. Cieľom je, aby prvé podujatie vzniklo bez
vypĺňania formulára a bez toho, aby sa musel registrovať skôr, než uvidí výsledok.

Zodpovedá bodu **3.3** v [ROADMAP.md](../../ROADMAP.md) — backend `detect-from-text`
existoval, ale nič ho nevolalo.

---

## Tok

| # | Krok | Routa | Autentizácia |
|---|---|---|---|
| 1 | Nahratie a analýza | `POST /api/poster/analyze` | žiadna, `throttle:ai` |
| 2 | Návrat k rozpracovanému | `GET /api/poster/drafts/{id}?token=…` | token |
| 3 | Uloženie e-mailu + odkaz späť | `POST /api/poster/drafts/{id}/remember` | token, `throttle:public-write` |
| 4 | Vznik podujatia | `POST /api/poster/drafts/{id}/claim` | `auth:sanctum` + token |

Frontend: [`PosterUploadWizard.vue`](../../ui/src/components/poster/PosterUploadWizard.vue),
routa `/nahrat-plagat` a `/nahrat-plagat/:id`.

### Prečo je účet až v kroku 4

Kto ešte nevidel, čo AI z jeho plagátu vytiahla, nemá dôvod sa registrovať —
a to je presne tá bariéra, ktorú má tento tok odstrániť. Cenu AI volania drží
limiter `ai` (10/min, 100/deň na IP), rovnaký ako na ostatných AI endpointoch.

---

## Čítanie súboru

[`PosterTextExtractor`](../app/Services/Posters/PosterTextExtractor.php)

| Formát | Ako sa číta |
|---|---|
| PDF s textovou vrstvou | `PdfConverterService` → text |
| PDF bez textovej vrstvy (sken) | tie isté PNG strán → vision (max 3 strany) |
| DOCX | ZIP + `word/document.xml`, bez ďalšej závislosti |
| TXT / MD | priamo |
| JPG / PNG / WEBP | rovno vision |
| DOC (starý binárny) | **nepodporované** — zrozumiteľná hláška, nie 500 |

Hranica „použiteľnej" textovej vrstvy je 120 znakov. Pod ňou rozhoduje obrázok:
plagát máva v textovej vrstve len pätičku tlačiarne a extrakcia z nej vráti
nezmysly, ktoré by AI brala vážne.

### Text z PDF má dva zdroje

Konvertor ťahá text cez `pdftotext` (poppler). Keď poppler na jeho serveri chýba,
vráti obrázky strán a `text: null` — teda **na nerozoznanie od skenu**, hoci
dokument textovú vrstvu má. Vtedy prídeme o presný text aj o popis podujatia
a zbytočne zaplatíme vision.

Preto sa pred vision skúša ešte lokálna extrakcia cez `smalot/pdfparser`.
Balík je **voliteľný** (`class_exists()`), takže bez neho appka beží ako doteraz:

```bash
composer require smalot/pdfparser
```

Keď lokálny parser dodá viac textu než konvertor, zapíše sa to do logu
(`text z PDF dodal lokálny parser`) — to je signál, že na strane konvertora
chýba poppler.

### Keď PDF neprejde

Konvertor je externá služba za nginxom. Väčšie PDF odmietne **HTTP 413**
(`client_max_body_size`) — a to je pre daný súbor trvalý stav, opakovanie
nepomôže. `convertFromBinary()` preto vracia stav cez `&$failureStatus` a
extraktor podľa neho volí hlášku: pri 413 poradí zmenšiť súbor alebo nahrať
obrázok, inak ponúkne opakovanie.

Skutočná náprava je zdvihnúť `client_max_body_size` na hoste konvertora.
Kým sa tak nestane, nastav `PDF_CONVERTER_MAX_UPLOAD_BYTES` na jeho reálny
limit — väčšie PDF potom odmietneme hneď, bez zbytočného prenosu súboru.

### Vision

[`ChatGPT::extractDataFromPoster()`](../app/Services/OpenAI/ChatGPT.php) používa
**rovnaký prompt aj rovnakú JSON schému** ako textová cesta (`PromptData`) — mení
sa len to, že k poslednej `user` správe pribudnú bloky `image_url`. Bez obrázkov
metóda deleguje na `extractData()`, takže volajúci sa nemusí rozhodovať.

Timeout je 120 s (oproti 60 s pri texte): plagát na výšku v `detail: high` sa
rozpadá na desiatky dlaždíc a 60 s pravidelne nestíhalo.

---

## Čo vidí človek

[`PosterAnalysisReport`](../app/Services/Posters/PosterAnalysisReport.php) preloží
výstup detektora na zoznam polí so stavom:

- `found` — prečítané z plagátu
- `guessed` — dopočítané (dnes iba `end_at` podľa typu podujatia)
- `missing` — nenašlo sa

`missing_required` a `can_save` hovoria, či sa dá pokračovať. **Zámerne tu nie je
percentuálne skóre spoľahlivosti** — model žiadne poctivé neposkytuje a vymyslené
číslo by len klamalo.

### Popis má tri zdroje a jedno poradie

`overrides.description` → `detection.corrected_text` (copywriter) → surový
`extracted_text`. Toto poradie musia držať **všetky tri** miesta: report
([`PosterAnalysisReport`](../app/Services/Posters/PosterAnalysisReport.php)),
predvyplnenie formulára (`PosterController::draftPayload()`) aj zápis
([`PosterDraftMaterializer::resolveBody()`](../app/Services/Posters/PosterDraftMaterializer.php)).

Kým sa report pozeral len na copywritera, hlásil „nenašli sme popis" aj pri PDF
so 7 000 znakmi textu — ktorý sa pri uložení aj tak stal telom podujatia.

Copywriter zlyháva ľahko: má text **rozšíriť**, takže výstup je dlhší než vstup a
pri dlhom dokumente narazí na strop tokenov. Vstup sa preto **neoreZáva** (z
orezaného by ticho zmizol koniec programu) — nad `MAX_COPYWRITER_INPUT_CHARS` sa
o rozšírenie vôbec nepokúšame a použije sa celý surový text. Zlyhanie sa loguje;
predtým sa ticho prehltlo a navonok to vyzeralo, že dokument popis neobsahuje.

### Sanitizácia

`events.body` sa na verejnom detaile renderuje cez `v-html`, a popis je tu vstup
od **neprihláseného** návštevníka. Všetky tri zdroje preto idú cez
[`HtmlBodyCleaner`](../app/Services/Imports/HtmlBodyCleaner.php):

- surový text dokumentu vždy cez `fromPlainText()` — nikdy to nie je HTML, a bez
  prevodu na odstavce by sa harmonogram zlial do jedného bloku
- copywriter a text od človeka cez `cleanHtmlString()`, keď obsahujú skutočný tag

Detekcia tagu je regex, nie `strip_tags()`: tá považuje za tag aj
`<info@farnost.sk>` alebo `<15 rokov` a zvyšok reťazca zahodí.

---

## Vznik podujatia

[`PosterDraftMaterializer`](../app/Services/Posters/PosterDraftMaterializer.php)

1. **Payload** = to, čo vrátila AI, prekryté opravami z formulára. Opravy majú
   vždy prednosť — sú to jediné hodnoty, ktoré niekto naozaj videl.
2. **Kanál**: vybraný → existujúci vlastnený → nový podľa organizátora z plagátu.
   Nový používateľ nemá kanál žiadny a bez kanála nesmie založiť podujatie
   (`EventPolicy::create` → `hasAnyCanalAbility`), takže založenie kanála je
   súčasťou tohto kroku, nie ďalší formulár.
   `ImportedCanalManager` sa tu **nepoužíva** — ten dáva vlastníctvo systémovému
   účtu, čo je správne pre scraper a nesprávne pre nahratý plagát.
3. **Miesto**: `ImportedVenueManager::resolveOrDetect()` — hľadanie duplicít,
   dodetekovanie adresy a fallback „Celé Slovensko" sú tam už odladené.
4. **Podujatie**: kompletné (názov + `start_at` + `end_at` + neprázdny popis) ide
   rovno `published`, inak ostáva `draft`. Rovnaké kritérium ako import zo
   zdrojov — bez termínu alebo obsahu by na portáli visel nezaraditeľný záznam.
5. **Súbor** sa vešia rovnako ako pri importe: originál ako príloha a z PDF
   navyše obrázok každej strany (`meta.source = pdf_conversion`). Bez tých strán
   by podujatie z PDF nemalo v zozname náhľad — samotné PDF sa nezobrazí.
   Hlavnú fotku určí `FileManager` z prvého obrázka, teda z prvej strany.

   Konvertor sa pritom volá druhýkrát (prvýkrát pri analýze): base64 obrázky
   strán sa na koncept zámerne neukladajú, jeden riadok v `poster_drafts` by
   narástol o megabajty a väčšina konceptov skončí nepotvrdená.

Opakovaný `claim` nevytvorí druhé podujatie — vráti to isté `event_id`.

---

## Životnosť konceptu

`poster_drafts` je dočasná tabuľka:

- token v DB je uložený ako **sha256 hash**, nie v čitateľnej podobe
- súbor leží na **privátnom** disku (`local`), nie na verejnom — nahratie samo
  o sebe nič nezverejňuje
- `expires_at` = 7 dní (medzi analýzou a uložením je registrácia s overením
  e-mailu, čo môže trvať aj deň)
- `app:poster-drafts-prune` denne o 03:40 maže nepotvrdené koncepty **aj ich súbory**

---

## Testy

```bash
php artisan test tests/Feature/Posters
```

`Detector` je v testoch zamockovaný — testy nesmú volať platené API a odpoveď
modelu nie je deterministická. Overuje sa zapojenie, nie kvalita extrakcie.
