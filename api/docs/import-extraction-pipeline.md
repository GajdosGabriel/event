# Import extraction pipeline — ako funguje detekcja a kedy sa zapína AI

Tento dokument popisuje celý postup spracovania jedného importovaného článku: od stiahnutia HTML až po uloženie eventu. Osobitne vysvetľuje prioritu zdrojov a podmienky aktivácie AI.

## Celkový tok

```
importArticle($url)
  │
  ├─ EventDetailService::extract()          → dátum/čas, telo, obrázky, prílohy, text
  │
  ├─ ImportedCanalNameResolver::resolve()   → organizátor, venue (regex → AI ak niečo chýba)
  │
  ├─ ImportedCanalManager::resolveOrCreate() → nájde alebo vytvorí Canal
  │
  ├─ ImportedVenueManager::resolveOrDetect() → nájde alebo vytvorí/doplní Venue
  │
  └─ EventRepository::create() / Event::update()
```

---

## 1. EventDetailService — extrakcia dátumu a tela

Každý zdroj má vlastnú metódu:

| Zdroj       | Metóda                       | Regex vzor pre dátum                        |
|-------------|------------------------------|---------------------------------------------|
| ECAV        | `extractEcavDetail()`        | DD.M.YYYY / DD.MM.YYYY, rozsahy s `–`       |
| TK KBS      | `extractTkkbsDetail()`       | `DD. Mesiac YYYY o HH:MM` (kľúč: `o`)      |
| Výveska     | `extractVyveskaDetail()`     | HTML meta alebo RSS pubDate                 |

Výsledok `$detail['start_at']` je buď `Carbon` objekt (UTC) alebo `null`.

### Dôležité — TK KBS

Vzor pre dátum rozlišuje dátum udalosti od dátumu publikácie pomocou slova **`o`** pred časom:

```
"pondelok 29. júna 2026 o 18:00"  →  start_at = 2026-06-29 16:00:00 UTC
```

Bez `o` → ignoruje sa (ide o dátum publikovania).

---

## 2. ImportedCanalNameResolver — organizátor a venue

### Priorita: **regex vždy prvý, AI len ako záloha**

```
1. EventTextLabelExtractor::extractOrganizerName()   ← explicitné labely v texte
   ("Organizátor:", "Usporiadateľ:", ...)
2. extractByHeuristics()                             ← vzory pre ECAV, KBS, ...
3. AI (len ak 1. aj 2. vrátili null)
```

**Pozor — spravodajské agentúry nie sú organizátor.** „TK KBS“ / „Tlačová kancelária KBS“ sú
zámerne mimo heuristických vzorov: objavujú sa v hlavičke každého článku na tkkbs.sk
(`"Košice 8. júla (TK KBS) …"`) ako vydavateľ správy, nie ako organizátor podujatia, o ktorom
správa referuje. Keby tam boli, každý TK KBS článok by skončil s canalom „TK KBS“ a AI by
sa k organizátorovi nikdy nedostala.

```
1. EventTextLabelExtractor::extractVenue()           ← labely + prose vzory
   ("Miesto:", "o HH:MM v [Miesto] v [Mesto]", ...)
2. AI (len ak 1. vrátil null pre name alebo city)
```

### Podmienka aktivácie AI

AI sa zavolá **jedine** keď po regex analýze platí aspoň jedna z podmienok:

```php
$somethingMissing = $detectedName === null
                 || $detectedVenueName === null
                 || ! $startAtFound;   // $startAtFound príde z EventImportService
```

Ak regex našiel všetko (organizátor + venue + dátum) → **AI sa nezavolá vôbec**.

### Čo AI môže doplniť

AI **nikdy neprepíše** to čo regex úspešne našiel. Dopĺňa len prázdne polia:

| Pole               | Podmienka doplnenia                    |
|--------------------|----------------------------------------|
| `organizer`        | `$detectedName === null`               |
| `venue.name`       | `$detectedVenueName === null`          |
| `venue.city`       | `$detectedVenueCity === null`          |
| `venue.street`     | vždy (street regex neextrahuje)        |
| `start_at`         | `! $startAtFound`                      |
| `end_at`           | `! $startAtFound`                      |
| `email`            | vždy (ak AI bola aktivovaná)           |
| `phone`            | vždy (ak AI bola aktivovaná)           |

### ENV prepínač

```
IMPORTS_DETECT_CANAL_WITH_AI=true
```

Ak je `false` → AI blok sa preskočí úplne, regex výsledky sú finálne.

---

## 3. ImportedVenueManager — vytvorenie venue záznamu

```
1. Hľadá existujúce Venue podľa name/slug v DB
2. Ak nenašlo a AI je zapnutá → Detector::detectVenueDetails() (geocoding)
3. Ak nenašlo → auto-vytvorí Draft Venue keď mesto matchuje Municipality
   (fuzzy prefix matching pre slovenský lokál: "Bratislave" → "Bratislava")
   Keď mesto chýba, ako mesto sa skúsi samotný názov venue — pútnické miesta
   sú často pomenované len obcou ("do Klokočova" → venue.name = "Klokočov").
4. Fallback → "Celé Slovensko" (venue.category = 'fallback')
```

---

## 4. EventImportService — skladanie výsledku

### Priorita dátumov

```php
$startAt = $detail['start_at'] ?? $resolvedCanal['ai_start_at'];
$endAt   = $detail['end_at']   ?? $resolvedCanal['ai_end_at']
                                ?? ($startAt?->copy()->addHours(2));
```

Poradie: **regex → AI → +2h default**

### Stav eventu (Published vs Draft)

Event sa publikuje automaticky len ak sú splnené všetky tri podmienky:

```php
$isComplete = $startAt !== null && $endAt !== null && trim($body) !== '';
```

---

## 5. AI prompt (ChatGPT::extractData)

Prompt v slovenčine požaduje štruktúrovaný JSON s poľami:

- `title`, `start_at`, `end_at` (formát `YYYY-MM-DD HH:MM:SS`, lokálny čas Europe/Bratislava)
- `organizer.name`
- `venue.name`, `venue.city`, `venue.street_and_number`
- `email`, `phone`

Dôležité pravidlá promptu:
- Venue name vráti v **nominatíve** (nie lokáli): "Katedrála svätého Martina", nie "Katedrále"
- Čas vráti ako lokálny slovenský čas, nie UTC
- Venue z prózy: `"o 18:00 v Katedrále svätého Martina v Bratislave"` → `venue.name = Katedrála svätého Martina`, `venue.city = Bratislava`

---

## 6. Súhrn — čo má prednosť

| Údaj        | 1. prednosť          | 2. prednosť (záloha)  | 3. prednosť (default) |
|-------------|----------------------|-----------------------|-----------------------|
| start_at    | regex (per-source)   | AI                    | —                     |
| end_at      | regex (per-source)   | AI                    | start_at + 2 hodiny   |
| organizer   | label regex / heurist.| AI                   | hostname zdroja       |
| venue name  | label regex / prose  | AI                    | —                     |
| venue city  | label regex / prose  | AI                    | —                     |
| email/phone | —                    | AI (ak bola aktivovaná)| —                    |

---

## 7. Relevantné súbory

| Súbor                                        | Zodpovednosť                                      |
|----------------------------------------------|---------------------------------------------------|
| `Services/Imports/EventDetailService.php`    | Per-source dátum/čas extrakcia                    |
| `Services/Imports/EventTextLabelExtractor.php`| Regex labely a prose vzory pre venue/organizer   |
| `Services/Imports/ImportedCanalNameResolver.php` | Orchestrácia regex → AI fallback               |
| `Services/Imports/ImportedVenueManager.php`  | DB lookup, geocoding, draft auto-create           |
| `Services/Imports/ImportedCanalManager.php`  | Resolve/create Canal záznamu                      |
| `Services/Imports/EventImportService.php`    | Hlavný orchestrátor, skladá finálny payload       |
| `Services/OpenAI/ChatGPT.php`               | OpenAI volanie + regex fallback pre dátumy        |
| `Services/OpenAI/PromptData.php`            | Systémový prompt a JSON schema pre extractData    |
