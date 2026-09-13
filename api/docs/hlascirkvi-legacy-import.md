# Prenos archívu podujatí z hlascirkvi (hotové, 6. 9. 2026)

Jednorazová migrácia archívu zo starého projektu `hlascirkvi` do tohto
projektu. **Prebehla a kód, ktorý ju robil, je zo stromu zmazaný** — dá sa
vytiahnuť z gitu (`git log -- api/app/Console/Commands/ImportHlascirkviEvents.php`,
posledný commit pred zmazaním je `b0f590f`).

Tento dokument zostáva preto, že prenesené podujatia nesú stopy, ktoré bez
neho nedávajú zmysel: prečo má vyše 10 000 podujatí zdrojovú URL na cudzí web,
prečo niektoré majú `meta.import.date_year_corrected` a prečo väčšina visí na
zberných kanáloch namiesto skutočného organizátora.

## Výsledok

| | |
|---|---|
| prenesené podujatia | 10 875 (10 689 archívnych + 186 nadchádzajúcich) |
| najstaršie | 28. 11. 2019 |
| kanály | 26 (3 zberné + 23 organizácií) |
| obrázky | ~91 % podujatí, na S3 pod prefixom `prod/` |
| zdroj | 11 863 zverejnených podujatí starej databázy |

## Ako sú dáta označené

Každé prenesené podujatie má v `meta.import`:

| kľúč | význam |
|---|---|
| `source` | vždy `hlascirkvi_legacy` — podľa toho sa dajú všetky adresne nájsť aj zmazať |
| `legacy_event_id` | id v starej databáze |
| `legacy_organization_id` / `_title` | pôvodná „organizácia" (často len zdroj scrapera) |
| `date_year_corrected` | rok začiatku bol prepísaný, viď nižšie |
| `end_at_estimated` | koniec sa nedal použiť a dopočítal sa ako začiatok + 2 h |
| `image_path` | cesta k obrázku na starom webe |

`events.orginal_source` obsahuje adresu pôvodného článku (tkkbs.sk, vyveska.sk,
ecav.sk), alebo — pri starších podujatiach, ktoré zdrojovú URL nemali — adresu
detailu na starom webe `https://hlascirkvi.sk/akcie/{id}/{slug}`. Slúžila ako
deduplikačný kľúč a je to zároveň funkčný odkaz.

## Rozhodnutia, ktoré je vidieť na dátach

**Organizátori.** V starej databáze bol pri 12 042 podujatiach ako
„organizácia" uvedený len zdroj scrapera, nie usporiadateľ. TKKBS, ECAV a
Výveska preto skončili v zberných kanáloch `tkkbs.sk`, `ecav.sk`, `vyveska.sk`
— tam, kam ich dáva aj nočný `app:import-event-sources`. Skutočných
organizátorov dohľadáva `app:ai-detector`.

**Obce.** Číselníky oboch projektov boli zhodné (4209 riadkov, rovnaké id),
takže `village_id` sa prebral priamo. Cez názov to prekladať nešlo — 211 obcí
má rovnaké meno. Podujatia s obcou „Celé Slovensko" majú zdieľané záložné
miesto. Miesta vznikli na úrovni obce, bez súradníc; tie dopĺňa
`app:backfill-venue-coordinates` (jednorazovo ich doplnila aj migrácia
`2026_09_13_100000_backfill_venue_and_canal_coordinates`).

**Dátumy.** Starý scraper občas prečítal rok zo znenia článku — podujatie
z roku 2022 malo `start_at` v roku 1452 a koniec v roku 8330, čo je nad stropom
MySQL TIMESTAMP-u a insert na tom padal. Pokazený bol pritom výlučne rok: deň,
mesiac aj hodina sedeli. Preto sa u 133 podujatí prepísal len rok na rok vzniku
záznamu (u 8 na nasledujúci — decembrová pozvánka na januárovú akciu) a koniec
dostal rovnaký posun, aby viacdňovej akcii ostalo trvanie. Podujatia bez
akéhokoľvek začiatku (11) sa nepreniesli.

**Duplicity.** Deduplikovalo sa podľa `orginal_source` a podľa
`canal_id` + slug názvu + presný začiatok. Naprieč kanálmi zámerne nie:
„Adventná obnova" o 16:00 môže v ten istý deň prebiehať v dvoch farnostiach.
Zostalo tak niekoľko dvojíc, kde tá istá akcia visí raz na zbernom kanáli a raz
na rozpoznanom organizátorovi — na tie je `app:events-merge-duplicates`.

## Ako to bežalo

Hosting nemá shell ani systémový cron, takže import nemohol byť jednorazový
príkaz. Bežal po dávkach cez webcron: naplánované úlohy v `routes/console.php`
zapínal prepínač `HLASCIRKVI_IMPORT_ENABLED` v `.env` a každá dávka mala strop
`--max-seconds=40` a kurzor, ktorý si pamätal, kde skončila. Podujatia
dobehli tempom ~800/min (~15 minút), obrázky ~65/min (niekoľko hodín).

Podrobnosti o tom, prečo sa na produkcii nedá spustiť `php artisan`, sú
v [README](../../README.md) a v [deploy/htaccess.md](../../deploy/htaccess.md).
