# Otázky z publika (Q&A)

## Čo to robí

Organizátor premietne na plátno **snímku s QR kódom**. Kto ju naskenuje, dostane
na telefón formulár s jedným poľom a tlačidlom — napíše otázku a odošle ju.
Bez registrácie, bez appky, bez hľadania odkazu.

Prednášajúci si otázky pozerá buď v dashboarde, alebo na **premietacej stene**,
kde pribúdajú naživo a dajú sa označiť ako „práve odpovedáme".

Cieľ je jediný: skrátiť cestu od „chcem sa opýtať" po odoslanú otázku na
niekoľko sekúnd. Všetko ostatné je tomu podriadené.

---

## Nástenka je vlastná entita, nie stĺpce na evente

Nástenka sa dá zapnúť na **podujatí** aj na **jednotlivom workshope**. Workshop
pritom nie je samostatná entita — je to riadok v `ticket_types` s
`kind = workshop` (pozri [workshop-waitlist.md](workshop-waitlist.md)). Keby
nastavenia žili v stĺpcoch na `events`, workshop by potreboval vlastnú sadu
v druhej tabuľke.

Preto je nástenka polymorfná, presne podľa vzoru `Message::TARGETS`:

```php
// app/Models/QuestionBoard.php
public const TARGETS = [
    'event'    => Event::class,
    'workshop' => TicketType::class,
];
```

`boardable_type` sa ukladá ako **plný názov triedy** (default morph). Do
`Relation::morphMap` sa nesiaha — `files.fileable_type` už plné názvy má
a remapovanie by rozbilo načítanie súborov.

Model dopĺňa dve metódy z `App\Contracts\HasQuestionBoard`
(`questionBoardEvent()`, `questionBoardTitle()`), zvyšok rieši trait
`InteractsAsQuestionBoard`.

### Tabuľky

| Tabuľka | Čo drží |
|---|---|
| `question_boards` | polymorfný cieľ, token, nastavenia, časové okno, `questions_count` |
| `questions` | text, nepovinné meno, stav, počet hlasov, odpoveď organizátora, pseudonym pisateľa |
| `question_votes` | `question_id` + `voter_hash`, unikátny index = „jeden hlas na prehliadač" |

Nástenka sa zakladá **lenivo**, až keď o ňu organizátor v dashboarde požiada.
Inak by pri každom podujatí a každom workshope ležal v databáze riadok
s tokenom, ktorý nikto nikdy nepoužije (importovaných podujatí sú tisíce).

---

## Token je adresa aj kód

`question_boards.token` má 10 znakov z 30-znakovej abecedy bez `0/O/1/I/L/U` —
nula a písmeno O sú na projektore z desiatich metrov nerozoznateľné.

Ten istý token slúži na dve veci naraz:

- **autorizáciu** verejnej adresy `/q/{token}` (rovnaká konvencia ako RSVP odkaz
  v e-maile: token v odkaze *je* autorizácia),
- **kód na prepísanie** z plátna. Na snímke je vytlačený po piatich znakoch
  (`A7K2M-9QXBF`) a `BoardToken::normalize()` pomlčku aj veľkosť písmen pri
  príchode zahodí, takže adresa funguje presne tak, ako je napísaná.

Preto je na snímke jedna adresa a nie „adresa + samostatný kód".

`POST /dashboard/question-boards/{board}/rotate-token` je núdzová brzda: starý
odkaz okamžite prestane fungovať, otázky ostávajú, snímku treba stiahnuť znova.

---

## Ochrana anonymného zápisu

Formulár je bez účtu — to je celý zmysel veci, takže „prihláste sa" (ako pri
správach) tu nepripadá do úvahy. Platí to aj po tom, čo detail podujatia začal
ponúkať odpoveď e-mailom: adresa je **ponuka, nie podmienka**, a otázku sa dá
položiť bez nej (viď „Odpoveď e-mailom" nižšie). Namiesto účtu je ochrana
vrstvená; každá vrstva sa dá obísť sama o sebe:

| # | Vrstva | Kde |
|---|---|---|
| 1 | neuhádnuteľný token v adrese (2^49 možností) | `BoardLocator` |
| 2 | `is_open` **a** okno `opens_at`–`closes_at` | `QuestionBoard::acceptsQuestions()` |
| 3 | limiter `questions` — 8/min a 40/hod na IP | `AppServiceProvider` |
| 4 | honeypot + podpísaná známka s minimálnym časom vyplnenia | `QuestionStoreRequest`, `SubmissionTicket` |
| 5 | dedup rovnakého textu od toho istého pisateľa do 5 minút | `QuestionSubmitter` |
| 6 | moderovanie, keď si ho organizátor zapne | `QuestionBoard::statusForNewQuestion()` |

**Prečo podpísaná známka a nie `rendered_at`:** čas je vnútri šifrovaného
reťazca. Keby ho klient posielal ako obyčajné číslo, stačilo by ho v požiadavke
prepísať a poistka by bola dekorácia. Známku vydáva `GET /q/{token}` a odosielanie
ju pýta späť, takže bot, ktorý našiel len POST endpoint, ju nemá odkiaľ vziať.

Minútový limit je zámerne voľnejší než pri ostatných verejných zápisoch: v sále
je celá miestnosť za jednou NATovanou adresou. Skutočnú brzdu robí hodinové okno.

**Notifikácia organizátorovi sa neposiela** — počas prednášky by mu prišlo
štyridsať e-mailov.

### Tri rôzne identity toho istého človeka

| Kde | Čo | Prečo práve tak |
|---|---|---|
| `questions.author_hash` | `sha256(IP \| user-agent \| app key \| dnešný dátum)` — `App\Support\VisitorPseudonym` | IP sa nikde neukladá a pseudonym sa každý deň mení, takže sa z tabuľky nedá poskladať, čo kto písal naprieč akciami. Rovnaký prístup ako počítadlo zobrazení. |
| `question_votes.voter_hash` | `sha256(náhodný token z localStorage)` | Hlas musí prežiť prepnutie wifi na LTE a po reloade musí byť rozpoznaný ako „môj", aby sa dal odobrať. Hash z IP by pokazil oboje. |
| `questions.author_email` | adresa, ktorú človek sám zadal | Jediný priamy kontakt v celom Q&A. Vzniká len na verejnom detaile a len so zaškrtnutým „dajte mi vedieť"; **po odoslaní odpovede sa maže**, takže v tabuľke žije len od otázky po odpoveď. Nikdy neopúšťa server (`Question::$hidden`) — ani smerom k organizátorovi. |

`VisitorPseudonym` vzniklo vytiahnutím z `ViewRecorder`. Poradie polí v hashi
zostalo nedotknuté zámerne — inak by sa v deň nasadenia každému zmenil pseudonym
a počítadlo zobrazení by ten deň rátalo dvakrát.

---

## Generovaná snímka

Vykresľuje sa v **čistom GD**, rovnako ako zmenšovanie obrázkov v
`ImageVariantGenerator`. Žiadna nová composer závislosť: headless prehliadač ani
Imagick na tomto hostingu nie sú.

**Snímka sa nikde neukladá.** Vzniká pri každom stiahnutí nanovo, presne ako QR
kódy vstupeniek. Ukladanie príde na rad, až keď sa služba osvedčí.

| Endpoint | Čo vráti |
|---|---|
| `GET /api/q/{token}/slide.png?variant=&theme=&lang=` | PNG 1920×1080 (`slide`) alebo 1080×1080 (`square`) |
| `GET /api/q/{token}/slide.pptx?theme=&lang=` | `.pptx` s jednou širokouhlou snímkou |
| `GET /api/q/{token}/qr.png?size=` | samotný QR kód do rohu premietacej steny |

Endpointy sú verejné: obsah snímky je ten istý QR, ktorý sa premieta pred celú
sálu. Vďaka tomu je sťahovanie obyčajný `<a download>` a náhľad v dashboarde
obyčajný `<img>` — a náhľad je **doslova ten istý artefakt**, ktorý sa stiahne,
takže sa nemôžu rozísť.

Jazyk ide parametrom `?lang=`, nie hlavičkou `X-Locale`: sťahovacia adresa má
byť sebapopisná a odpadá `Vary` na verejnej cachovateľnej odpovedi.

Neznámy `variant`/`theme` vracia **422, nie tiché spadnutie na predvolený** —
preklep v odkaze by inak navždy potichu servíroval niečo iné.

### Pasce GD, kvôli ktorým kód vyzerá inak, než by človek čakal

Všetky sú odmerané, nie odhadnuté. Podrobnosti sú v komentároch pri metódach
`App\Services\Questions\SlideCanvas`.

- **Alfa:** `imagealphablending(true)` + `imagesavealpha(false)`. Je to **opak**
  toho, čo robí `ImageVariantGenerator` (ten zachováva alfu zdroja pri
  zmenšovaní). Pri vypnutom blendingu sa priesvitný závoj namiesto zmiešania
  *nahradí* a z celého stmavenia vyjde čierna doska.
- **Rozostrenie:** jadro je pevné 3×3, jeden prechod pri 1920×1080 stojí okolo
  pol sekundy. Preto sa fotka zmenší na 256 px, rozostrí sa tam piatimi
  prechodmi (~35 ms) a až potom sa roztiahne späť.
- **Zaoblený roh:** `imageantialias()` sa pri `imagefilledellipse` **ticho
  ignoruje** (dve farby v rohu namiesto šesťdesiatich) a rovnako pri priesvitnej
  výplni. Karta sa preto kreslí ako antialiasovaný `imagefilledpolygon`
  s nepriehľadnou farbou.
- **Uhlopriečny prechod:** `imagecopyresampled` z obrázka 2×2 nedá prechod, ale
  štyri ostro ohraničené štvrtiny. Semienko je preto 64×64 a interpoluje sa v PHP.
- **Riadkovanie:** `imagettfbbox` vracia výšku *konkrétneho textu* a mäkčene ju
  nafúknu (pri 48 pt o vyše desať pixelov). Výška riadku sa preto meria **raz na
  dvojicu (písmo, veľkosť)** referenčným reťazcom, nie pre každý riadok zvlášť.
- **Tichá zóna QR:** endroid dostáva `margin: 0`, takže výstup je presne `size`
  a QR sa nikdy neresampluje. Tichú zónu (4 moduly ≈ 14 % veľkosti) dodáva
  **vnútorné odsadenie bielej karty**. Test
  `every_variant_keeps_the_quiet_zone_around_the_qr` stráži, aby to neskoršie
  utiahnutie layoutu nezmenšilo — snímka by vyzerala rovnako a prestala by sa
  skenovať.
- **SVG:** predvolené obrázky v `public/images/*.svg` GD neprečíta. Kanál bez
  fotky preto dostane **monogram** — kruh v odtieni motívu a prvé písmená názvu.

Odmerané: `dark` 1920×1080 ≈ 0,55 s a 64 MB, `light`/`bold` ≈ 0,3 s. Cenu drží
limiter `render` (20/min na IP).

### Písmo

GD kreslí text len z TTF súboru. `App\Services\Questions\FontLibrary` hľadá
v tomto poradí:

1. `api/resources/fonts/` — sem stačí položiť TTF s očakávaným názvom
   (`Inter-Bold.ttf`, `Inter-SemiBold.ttf`, `Inter-Regular.ttf`, prípadne
   `Figtree-*` či `SourceSans3-*`) a snímka ho začne používať bez zásahu do kódu.
2. Písmo, ktoré so sebou nesie `endroid/qr-code` — núdzový režim, aby snímka
   fungovala aj bez ručného kroku pri nasadení. Je to Open Sans s úplným
   pokrytím slovenskej diakritiky, ale **len v jednom reze**, takže nadpis nie
   je tučný.

**Dnes beží núdzový režim.** Priloženie troch rezov Inter (OFL) je jediná
otvorená vec — vizuálne to snímke citeľne pomôže, funkčne nič nemení.

`FontCoverageTest` porovnáva vykreslený tvar znaku s náhradným rámčekom
(nie šírku — tá sa náhodou zhoduje, napr. pri `Ĺ`). Bez neho by výmena písma
mohla ticho premeniť každé `ľ` na prázdny štvorček.

### .pptx

`PptxPackager` skladá zip s dvanástimi časťami ručne — `.pptx` je pre jedinú
snímku s jediným obrázkom takmer celý statické XML a ďalšia composer závislosť
by bola neúmerná.

Čo sa **nesmie** „upratať" (každé z toho spôsobí v PowerPointe hlášku „Našiel sa
problém s obsahom" bez akéhokoľvek náznaku, kde je chyba):

- `ppt/theme/theme1.xml` je povinný a schéma je prísna na počty detí —
  `clrScheme` presne dvanásť prvkov v danom poradí, každý zoznam vo `fmtScheme`
  aspoň tri položky;
- `[Content_Types].xml` potrebuje `Default` pre `rels`, `xml` **a `png`**
  (bez `png` sa snímka otvorí, ale obrázok je prázdny rámik);
- vzťah `slideLayout` ↔ `slideMaster` musí byť obojsmerný;
- `sldMasterId`/`sldLayoutId` ≥ 2147483648, `sldId` v rozsahu 256–2147483647;
- `<p:sldSz>` **bez** atribútu `type` (`screen16x9` je predvoľba 10×5,625″
  a niektoré programy ju uprednostnia pred zadanými rozmermi);
- `r:embed` v snímke musí ukazovať na `rId` **obrázka**, nie layoutu — zámena
  dá snímku bez obrázka a žiadnu chybu.

Overené otvorením v PowerPointe 16 cez COM: jedna snímka, 960×540 pt, jeden
obrázkový tvar, bez opravnej hlášky. Test `pptx_is_a_single_slide_deck_...`
kontroluje všetkých dvanásť častí, platnosť XML aj krížový odkaz `r:embed`.

---

## Verejné rozhranie

`/q/{token}` je zámerne jediná verejná cesta, ktorá nie je slovenské slovo
(`podujatia`, `miesta`, `pozvanka`…). Táto adresa sa prepisuje z plátna rukou,
takže krátkosť je tu funkcia. Segment drží `PublicUrl::QUESTIONS` a jeho
dvojička v `ui/src/utils/publicUrl.ts`. Stránka nesie `<meta name="robots"
content="noindex">` — je to jednorazový vstup pre ľudí v sále, nie obsah do
katalógu.

Nad ohybom je len textarea a jedno veľké tlačidlo. Doterajšie otázky sú zbalené
za tlačidlom a nastavenie `show_questions` ich vie vypnúť úplne (na hlasovaní
o nápadoch má každý napísať to svoje bez ovplyvnenia ostatnými).

Vlastné otázky si stránka pamätá v `localStorage`. Pri zapnutom moderovaní
totiž vlastná otázka vo verejnom zozname nie je a človek by po obnovení stránky
nemal ako zistiť, že ju vôbec poslal — a napísal by ju znova.

### Aktualizácia bez websocketu

Hosting nemá shell ani démona (fronta beží cez externý webcron, viď
`routes/console.php`), takže trvalé spojenie nemá kto obsluhovať. Používa sa
polling `GET /q/{token}/stream?v={otisk}`:

- telefón každých **8 s**, premietacia stena každých **5 s**;
- pri skrytej záložke sa nepýta vôbec (`document.visibilityState`);
- `v` je otisk stavu (počet + najvyššie `id` + najnovší `updated_at`). Keď sa
  nezmenil, server vráti `{"changed": false}` a zoznam vôbec neserializuje.
  Otisk musí obsahovať aj `updated_at`, lebo schválenie, skrytie, hlas aj
  zvýraznenie menia zoznam bez pridania riadku.

Vracia sa celý zoznam, nie rozdiel: otázok je na akciu rádovo desiatky a poradie
sa mení hlasovaním, takže zliať rozdiel by na klientovi bolo viac kódu než
preposlať zoznam.

---

## Premietacia stena

`/q/{token}/stena` — tmavá stránka na celú obrazovku, QR natrvalo v rohu (kto
prišiel neskôr, nemusí čakať, kým sa snímka premietne znova), otázky v stĺpcoch,
zvýraznená ide hore a zväčší sa.

Stena je verejná na token, ale **moderátorské tlačidlá** (zvýrazniť / označiť za
zodpovedané / skryť) sa ukážu len prihlásenému a server ich aj tak overuje cez
`QuestionBoardPolicy`. Zvýraznená je vždy najviac jedna otázka — „práve
odpovedáme" v množnom čísle nedáva na stene zmysel.

---

## Práva

Nástenka nemá vlastného vlastníka, práva dedí od podujatia
(`QuestionBoardPolicy` → `User::canInCanal(..., 'event.update')`). Zámerne
**bez** `DeniesArchivedUpdate`: archivované podujatie je práve to, ktoré už
prebehlo, a dopisovanie odpovedí robí organizátor až potom.

Bežný typ lístka („Štandard", „VIP") nástenku dostať nemôže — pýtať sa dá na
program, nie na cenovú hladinu.

---

## Druhá cesta k tej istej nástenke: verejný detail podujatia

Nástenka bola pôvodne dostupná **len cez QR premietnutý v sále**. Kto sedel doma
nad stránkou podujatia, o jej existencii nevedel — hoci práve tam sa pýtajú tie
najužitočnejšie otázky („je vstup naozaj zadarmo?", „môžem prísť s deťmi?", „je
tam parkovanie?").

[`EventQuestionController`](../app/Http/Controllers/Public/EventQuestionController.php)
preto obsluhuje `GET|POST /api/events/{event}/questions`.

### Token sa touto cestou neposiela — nikdy

Token je autorizácia a dá sa rotovať (núdzová brzda). Keby ho verejný detail
dostal do payloadu, rotácia by stránku rozbila a token by sa šíril mimo QR — teda
presne to, čomu má rotácia zabrániť. Nástenka sa preto **hľadá cez podujatie**
a viditeľnosť si controller rieši sám cez `publicShow()`, rovnako ako verejný
detail.

Nástenka sa touto cestou ani **nezakladá**. Vzniká lenivo na žiadosť organizátora
a bolo by chybou, aby ju vyrobila návšteva verejnej stránky.

Každá cesta má vlastný rozsah `SubmissionTicket` (`question:{token}` vs.
`question:event:{id}`), takže známka vydaná pre jednu na druhej neprejde.

### Fáza namiesto druhej nástenky

Nástenka je jedna, ale plní dve úlohy — [`QuestionBoardPhase`](../app/Enums/QuestionBoardPhase.php)
ich rozlišuje podľa termínu, takže sa fáza nemôže rozísť s realitou a nikto ju
neprepína:

| fáza | komu | forma |
|---|---|---|
| `before` | organizátorovi | FAQ, zodpovedané hore (`inFaqOrder`) |
| `live` | prednášajúcemu | ako na plátne (`inWallOrder`) |
| `after` | — | archív |

Poradie je zámerne iné než na stene: tam je hore to, na čo sa **práve odpovedá**,
na detaile to, na čo sa **už odpovedalo** — návštevník prišiel pre odpoveď.

### Kanál rozhoduje aj o časovom okne

Nástenka má okno `opens_at`–`closes_at` a jeho **začiatok patrí plátnu**: kým sa
v sále skúša technika, adresa z QR nemá byť živá. Na verejnom detaile by to isté
pravidlo zavrelo formulár presne v období, na ktoré je určený — predvolené okno
je „dve hodiny pred začiatkom", takže FAQ by bolo dostupné len tie isté hodiny
ako QR, teda nikdy vtedy, keď sa človek pýta z gauča.

Preto [`QuestionChannel`](../app/Enums/QuestionChannel.php):

| | `is_open` | `opens_at` | `closes_at` |
|---|---|---|---|
| `Wall` — QR v sále | platí | **platí** | platí |
| `EventPage` — verejný detail | platí | ignoruje sa | platí |

Default parametra je `Wall`, teda prísnejší variant — kto o rozdiel nevie,
nechtiac neotvorí nástenku skôr, než mal.

### Odpoveď e-mailom

Otázka v sále odpoveď e-mailom nepotrebuje: prednášajúci ju povie nahlas
a pisateľ sedí v miestnosti. Otázka z detailu je iný prípad — položí sa týždeň
dopredu a odpoveď by človek musel chodiť hľadať späť na stránku. Preto si ju
môže vypýtať, zaškrtávacím poľom pri formulári.

- **Nepovinné a nezaškrtnuté.** Adresu pýtame len od toho, kto o odpoveď stojí;
  otázka bez nej je stále platná otázka aj SEO obsah.
- **Prihlásený nevypĺňa nič** — meno aj adresu doplní server z účtu, presne ako
  `TicketController` dopĺňa `holder_name`/`holder_email`. Klient by to ani
  nezvládol: `UserResource` posiela e-mail len na admin routách.
- **Adresa sa po odoslaní odpovede maže** a `answer_notified_at` zaručí, že
  prepísaná odpoveď už druhý e-mail nepošle. Je to jednorazová správa, nie odber
  — preto v nej nie je ani odhlasovací odkaz, nebolo by sa z čoho odhlásiť.
- **Organizátor adresu nevidí.** Odpovedá na stránke, nie do schránky, a cudzie
  adresy sa v tomto projekte nezobrazujú nikde.

Obe pravidlá o zahadzovaní vstupu (meno pri vypnutom `ask_for_name`, kontakt mimo
`EventPage`) sedia na jednom mieste — [`QuestionDraft`](../app/Services/Questions/QuestionDraft.php).
Formulár sa dá podstrčiť, server nie.

### Prečo to má zmysel: zodpovedané otázky sú SEO obsah

Bez tejto časti by bolo Q&A na detaile len pekná sekcia.

- [`JsonLd::faqPage()`](../app/Services/Seo/JsonLd.php) vydá `FAQPage`, ktorý
  Google zobrazuje **rozbaliteľne priamo vo výsledku vyhľadávania**.
- [`PrerenderController`](../app/Http/Controllers/Public/PrerenderController.php)
  vykreslí otázky do HTML — SPA obsah crawler nevidí.

Nezodpovedané otázky do `FAQPage` nejdú: schéma vyžaduje `acceptedAnswer`
a otvorená otázka by bola neplatný záznam. Prázdny `FAQPage` sa nevydá vôbec.

Odpovede píše organizátor sám, takže obsah rastie bez našej práce.

---

## Overenie

```bash
cd api && php artisan test tests/Feature/Questions tests/Unit/Questions tests/Feature/Seo/FaqPrerenderTest.php
```

Ručne stojí za pozretie:

1. `/dashboard/events/{id}/otazky` — zapnúť nástenku, prepnúť motív, stiahnuť
   PNG aj `.pptx`.
2. **PNG naozaj naskenovať telefónom** — to je jediná vec, ktorú automatický
   test nepokryje.
3. `.pptx` otvoriť **najskôr v PowerPointe**; je najprísnejší, čo otvorí on,
   otvorí Google Slides aj LibreOffice.
4. Z mobilu poslať otázku na `/q/{TOKEN}` a overiť, že sa do 8 s objaví na
   `/q/{TOKEN}/stena`.
5. Zapnúť moderovanie a overiť, že nezverejnená otázka na verejnej stránke nie je,
   ale odosielateľ ju vidí ako čakajúcu.
6. Na verejnom detaile položiť otázku so zaškrtnutým „dajte mi vedieť", dopísať
   odpoveď v dashboarde a overiť, že e-mail odišiel **raz** a `author_email` je
   v databáze prázdny. Druhá úprava odpovede už nesmie poslať nič.

Na produkcii navyše skontrolovať, že GD má FreeType — bez neho renderer nemá
čím kresliť text a endpoint vráti 503 s hláškou, nie rozbitý obrázok:

```bash
php -r "var_dump(function_exists('imagettftext'));"
```

Ak je nastavený `open_basedir`, musí zahŕňať `api/resources/`.
