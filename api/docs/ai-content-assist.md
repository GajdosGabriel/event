# „Vyplniť pomocou AI" a kontrola obsahu po zverejnení

## Čo sa riešilo

Panel s AI bol vo formulári podujatia (vylepšenie textu) a vo formulári miesta
(detekcia údajov z názvu a obce). Kanál nemal nič, admin verzia formulára mala
vlastnú kópiu endpointu s inými limitmi validácie, a nikde nebolo napísané,
**kedy sa panel má ukázať** — v podujatí sa objavil pri 100 znakoch popisu,
v mieste vždy.

Chýbala aj spätná väzba: text sa zverejnil a či za niečo stojí, sa nikto
nedozvedel.

Tri veci, ktoré z toho vznikli, sú zámerne postavené na jednom základe:

1. **Jedna definícia pripravenosti** — čo znamená „záznam je hotový".
2. **Jeden panel** pre podujatie, miesto aj kanál, v dashboarde aj v admine.
3. **Kontrola po zverejnení**, ktorá vedie späť do toho istého panela.

---

## 1. Kedy je záznam „pripravený" (a prečo je to jedna tabuľka)

Otázka „je to hotové?" vzniká na dvoch miestach a **musí mať tú istú odpoveď**:

- vo formulári, priebežne pri písaní — riadi ukazovateľ a odomyká AI panel,
- na serveri, pri zverejnení — rozhoduje, či sa text pošle na kontrolu.

Kým bola napísaná dvakrát, ticho si odpovedali inak. Preto je podmienok
definovaná **len raz**, v [`config/content_review.php`](../config/content_review.php):

```php
'event' => [
    ['key' => 'name',     'rule' => 'filled',    'fields' => ['name']],
    ['key' => 'start_at', 'rule' => 'filled',    'fields' => ['start_at']],
    ['key' => 'venue',    'rule' => 'filled',    'fields' => ['venue_id']],
    ['key' => 'body',     'rule' => 'min_chars', 'fields' => ['body'], 'value' => 200],
    ['key' => 'image',    'rule' => 'filled',    'fields' => ['image']],
    ['key' => 'contact',  'rule' => 'any_of',    'fields' => ['website','email','phone']],
],
```

Pravidlá sú zámerne hlúpe — `filled`, `any_of`, `min_chars` — aby ich vedel
vyhodnotiť aj prehliadač. Server ich vyhodnocuje cez
[`PublishReadiness`](../app/Services/Publishing/PublishReadiness.php), prehliadač
si ten istý zoznam stiahne cez `GET {scope}/publish-readiness` a vyhodnotí ho
v [`usePublishReadiness.ts`](../../ui/src/composables/usePublishReadiness.ts).
Panel sa tak prekresľuje pri písaní, bez jediného dotazu na server.

### Nie je to validácia

**Nepripravený záznam sa zverejniť dá.** Je to merítko a odporúčanie, nie zámok —
organizátor, ktorý vie, čo robí, nemá naraziť na dvere. Ukazovateľ ukáže „hotové
4 zo 6" a vymenuje, čo chýba; tlačidlo Uložiť sa nikdy nevypne.

### Dve pasce, na ktoré si dať pozor

**`min_chars` sa počíta z textu bez značiek.** Popis je HTML a `strlen` by
počítal `<p>` — prázdny odsek s odkazom by prešiel ako stostranový text.
Rovnaký výpočet je na oboch stranách (`PublishReadiness::textLength()` /
`textLength()` v composable).

**`image` nie je stĺpec.** Obrázky sa neukladajú s formulárom, ale hneď pri
každej zmene, takže vo `form` nie sú. Formulár si hodnotu dopĺňa sám —
z `ImageManager` (úprava) alebo z `ImagePicker` (zakladanie). Preto
`ImageManager` odteraz vystavuje `imageCount`.

**Obec má dve mená.** Miesto ju drží v `village_id`, kanál v `municipality_id`.
Konfigurácia pozná len `municipality_id` a oba formuláre aj server si hodnotu
premenujú — rovnako, ako to už robí `addressFrom()`.

---

## 2. Panel: prečo sa ukáže až na hotovom zázname

[`AiAssistPanel.vue`](../../ui/src/components/ai/AiAssistPanel.vue) je jeden
komponent pre všetky tri formuláre a obe scope. Má tri poschodia a každé sa
objaví, až keď má čo povedať:

| poschodie | kedy |
|---|---|
| poznámky z kontroly po zverejnení | keď nejaké sú |
| ukazovateľ pripravenosti | kým záznam hotový nie je |
| samotný AI pomocník | až keď hotový je |

**Poradie nie je kozmetika.** Kým chýba obsah, je jediná zmysluplná rada
„doplňte ho"; ponúkať v tej chvíli vylepšenie štýlu by bolo vylepšovanie ničoho.
A hlavne: keby panel svietil nad prázdnym formulárom, prvé, čo by od neho ľudia
chceli, je „napíš mi popis" — a z názvu podujatia sa dá napísať len výmysel.

### Dve operácie a rozdiel medzi nimi

[`AiAssistController`](../app/Http/Controllers/AiAssistController.php) vie dve
veci a rozdiel je podstatný:

- **`improve`** — pracuje s textom, ktorý človek napísal. Režimy `grammar`,
  `style`, `expand`. Bezpečné pre všetky tri typy: model má z čoho vychádzať
  a prompt mu zakazuje pridávať fakty.
- **`draft`** — píše popis od nuly, len z názvu. Ponúka sa **len pri mieste
  a kanáli**. To sú trvalé subjekty, o ktorých sa dá napísať vecná informácia,
  a [`PromptProfile`](../app/Services/OpenAI/PromptProfile.php) radšej vráti
  `null`, keď subjekt nepozná. Pri podujatí by to bol výmysel — dátum, program
  ani cenu si model domyslieť nesmie.

Tlačidlo „Napísať popis za mňa" sa pri podujatí nezobrazí a server takú
požiadavku odmietne aj vtedy, keď ju niekto pošle ručne.

### Návrh sa nikdy nezapíše sám

Panel **nič neukladá**. Ukáže návrh vedľa pôvodného textu (náhľad aj zdrojový
kód) a do popisu ho vloží až tlačidlo „Použiť text". Text, ktorý človek písal,
mu nemá zmiznúť pod rukami.

`html` sa do režimov dopĺňa vždy na serveri — popis je HTML pole a návrh
v holom texte by sa po vložení rozsypal na jeden odsek. Výsledok prechádza cez
`HtmlBodyCleaner`, tou istou cestou ako popis pri importe.

### Čo sa zrušilo

`DashboardEventController::improveText()` a `Admin\EventController::improveText()`
boli tie isté štyri riadky s inou autorizáciou — a rozchádzali sa (admin verzia
mala iné limity validácie). Obe sú preč aj s routami `events/improve-text`.

---

## 3. Kontrola po zverejnení

Postavená zámerne rovnako ako
[`AttributeCheckService`](../app/Services/Attributes/AttributeCheckService.php),
lebo je to tá istá úloha s iným predmetom: niečo o zázname zistíme strojovo,
zapíšeme to a majiteľovi sa ozveme len vtedy, keď to má cenu.

### Hák visí na `saved()`, nie na controlleri

Zverejniť sa dá **tromi cestami**:

- poľom `status` vo formulári,
- tlačidlom (`RecordPublisher` / `EloquentEventRepository::publish`),
- príkazom `app:events-publish-scheduled` pri naplánovanom čase.

Kontrola visiaca na controlleri by dve z nich prehliadla. Preto je v traite
[`HasContentReview`](../app/Models/Traits/HasContentReview.php), ktorý používajú
`Event`, `Venue` a `Canal`.

### Plánovanie je lacné, beh je drahý

```
schedule()  ← saved(), pri každom uložení. Jeden zápis, nič nevolá von.
runDue()    ← príkaz app:content-reviews-run, malé dávky, volá OpenAI.
```

`schedule()` má hneď na začiatku **lacnú brzdu**:

```php
if (! $model->wasRecentlyCreated && ! $model->wasChanged(['status', 'body'])) {
    return;
}
```

Bez nej by nočný import, ktorý prepisuje desiatky polí na tisícoch podujatí,
zaplatil jeden dotaz navyše za každé z nich.

### Odklad po zverejnení nie je technický

`delay_minutes` (predvolene 15) je **slušnosť**: človek po publikovaní ešte pár
minút dolaďuje preklepy a e-mail o chybách, ktoré medzitým sám opravil, je horší
než žiadny. Každé ďalšie uloženie odklad posúva odznova.

### `content_hash` rozhoduje, čo sa opakuje

Riadok v `content_reviews` je **jeden na záznam, nie na beh** — nezaujíma nás
história posudkov, ale ako je na tom text teraz. `content_hash` (sha256
z normalizovaného tela) drží, ktorej verzii textu posudok patrí:

- rovnaký hash + `reviewed_at` → kontrola sa nepustí znova (uloženie kvôli inému
  poľu nič nestojí),
- iný hash → starý posudok sa **zahodí**, lebo výhrady k predošlej verzii by po
  prepise mátali a skóre by klamalo.

### Kontrola sa nikdy nedotkne obsahu

[`PromptContentReview`](../app/Services/OpenAI/PromptContentReview.php) model
**posudzuje, neprepisuje**. Prepis je samostatná operácia, ktorú púšťa človek
vo formulári. Kontrola, ktorá by rovno menila zverejnený text, by bola tichá
úprava cudzieho obsahu.

Prompt má ešte tri zámerné zákazy:

- nevyčítať chýbajúce údaje (dátum, cenu, adresu) — tie sú v iných poliach
  formulára, nie v popise; preto sa mu posiela kontext,
- neopravovať vlastné mená, obce a liturgické pojmy, ktoré nepozná,
- nehodnotiť samotné podujatie ani organizátora — posudzuje sa text.

---

## 4. Slučka, ktorá to spája: `mode`

Toto je kus, kvôli ktorému to celé dáva zmysel.

Každá výhrada nesie **`mode`** — ktorý režim panela ju vie vyriešiť
(`grammar`, `style`, `expand`). Z toho vyplýva:

```
kontrola nájde chyby
   ↓  ContentReview::suggestedModes() → ['grammar','expand']
e-mail ContentReviewNotice
   ↓  odkaz …/dashboard/events/12/edit?ai=grammar,expand
formulár
   ↓  AiAssistPanel prečíta ?ai=, otvorí sa rozbalený
      a zaškrtne presne tie režimy
```

Bez toho by človek po kliknutí pristál na stránke plnej polí a hádal, ktorý
prepínač je jeho.

Režimy do adresy sa skladajú z nájdených výhrad, nie z parametra zvonku, a panel
ich pri čítaní preosieva cez pevný zoznam `MODES` — parameter z adresného riadka
je čokoľvek a nemá čo skončiť v požiadavke na server.

### E-mail je pozvánka, nie výčitka

Text je vonku, funguje a nikto ho nestiahol. `ContentReviewNotice` vymenuje
**najviac tri** výhrady (e-mail nie je zoznam úloh — má presvedčiť, že sa oplatí
kliknúť) a povie hlavnú vec: *vo formulári nad popisom čaká AI, ktorá to vie
opraviť, a nič sa nezmení, kým to sami nepotvrdíte.*

### Kedy sa e-mail pošle

| podmienka | prečo |
|---|---|
| aspoň jedna výhrada so `severity >= warning` | `notice` sú návrhy („dalo by sa rozviesť"); e-mail za samotné „dalo by sa" je otravovanie |
| záznam má majiteľa | importovaný záznam z cudzieho zdroja nemá komu; posudok ostáva zapísaný pre admina |
| `notified_at` je prázdne alebo staršie než `notice_cooldown_days` (14) | inak by každá zmena nesúvisiaceho poľa vyvolala ďalší e-mail |

Majiteľ sa hľadá cez `contentReviewRecipient()` → `attributeIssueRecipient()` →
`messageRecipient()`. Je to tá istá otázka („kto sa o tento záznam stará?") a
odpoveď na ňu už raz padla, vrátane pravidiel, kedy nie je nikto.

### Poznámky žijú aj vo formulári

Posudok sa dá vytiahnuť cez `GET {scope}/ai/review/{kind}/{id}` a panel ho ukáže
nad popisom. Bez toho by výhrady žili len v schránke a kto príde upravovať
záznam z iného dôvodu, o nich nevie.

Keď človek text medzitým upravil, poznámky sa **stlmia, nie skryjú** — konkrétny
preklep v nich môže stále platiť a zahodiť ich potichu by bola strata informácie.

---

## Kde čo je

| vrstva | súbor |
|---|---|
| pravidlá + nastavenia | [`config/content_review.php`](../config/content_review.php) |
| vyhodnotenie pripravenosti | [`PublishReadiness`](../app/Services/Publishing/PublishReadiness.php) |
| životný cyklus kontroly | [`ContentReviewService`](../app/Services/Content/ContentReviewService.php) |
| hák na zverejnenie | [`HasContentReview`](../app/Models/Traits/HasContentReview.php) |
| prompt posudku | [`PromptContentReview`](../app/Services/OpenAI/PromptContentReview.php) |
| prompt prepisu | [`PromptTextEditor`](../app/Services/OpenAI/PromptTextEditor.php) |
| prompt popisu od nuly | [`PromptProfile`](../app/Services/OpenAI/PromptProfile.php) |
| endpointy | [`AiAssistController`](../app/Http/Controllers/AiAssistController.php) |
| e-mail | [`ContentReviewNotice`](../app/Notifications/ContentReviewNotice.php) |
| príkaz | [`RunContentReviews`](../app/Console/Commands/RunContentReviews.php) |
| panel | [`AiAssistPanel.vue`](../../ui/src/components/ai/AiAssistPanel.vue) |
| pripravenosť v UI | [`usePublishReadiness.ts`](../../ui/src/composables/usePublishReadiness.ts) |
| klient | [`api/ai.ts`](../../ui/src/api/ai.ts) |

## Prevádzka

`app:content-reviews-run` beží každých desať minút s dávkou 5 a časovým stropom
25 sekúnd. Hosting nemá shell — `schedule:run` volá webcron v HTTP requeste a
všetky príkazy v ňom bežia za sebou, takže jeden pomalý beh nesmie zjesť celé
okno. Kontrola textu nikam nespěchá; zvyšok dávky sa presunie do ďalšieho behu.

Vypnúť sa dá celá vetva cez `CONTENT_REVIEW_ENABLED=false`. Existujúce riadky
ostanú a po zapnutí sa dobehnú — výber si berie všetko, čo je splatné.

Pri chybe volania sa `due_at` posunie o šesť hodín a dôvod ostane v `last_error`.
Výpadok OpenAI by inak celú dávku držal a v každom behu by sa minula na tie isté
zlyhávajúce riadky.
