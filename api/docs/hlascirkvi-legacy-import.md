# Prenos archívu podujatí z hlascirkvi

Starý projekt `hlascirkvi` má archív podujatí od januára 2019. Tri príkazy ho
prenesú do nového modelu (kanál / miesto / súbory na S3) so zachovaním
pôvodného `created_at`.

```
[lokálne, stará DB]          [kdekoľvek, aj prod]           [kdekoľvek, aj prod]
app:hlascirkvi-export  →  hlascirkvi-events.jsonl  →  app:hlascirkvi-import  →  app:hlascirkvi-import-images
```

Obrázky sú zámerne druhý priebeh: podujatia sa nahrajú za minúty, sťahovanie
11 000 súborov je dlhá a zlyhávajúca operácia, ktorá sa musí dať opakovať bez
toho, aby sa znovu prepisovali podujatia.

## 1. Export (len lokálne)

Produkčný server na starú databázu nevidí, preto sa dáta vynesú do prenosného
JSONL súboru. Do `.env` patrí spojenie na starú databázu:

```
HLASCIRKVI_DB_DATABASE=hlascirkvi
HLASCIRKVI_DB_USERNAME=root
HLASCIRKVI_DB_PASSWORD=
```

```bash
php artisan app:hlascirkvi-export
```

Výsledok je `storage/app/import/hlascirkvi-events.jsonl` (~25 MB) plus
`.manifest.json` s počtami a kontrolným súčtom. Súbor je v `.gitignore`.

Exportujú sa len zverejnené a nezmazané podujatia (`published IS NOT NULL`,
`deleted_at IS NULL`) — k septembru 2026 ich je **11 863**, z toho 11 047 má
obrázok. Na podujatie sa berie jeden obrázok, veľká varianta (`images.url`,
1000 px). Typ `card` sú strojovo generované plagáty a neprenášajú sa.

## 2. Import podujatí

```bash
php artisan app:hlascirkvi-import --dry-run
```

```bash
php artisan app:hlascirkvi-import
```

Príkaz je idempotentný a prerušiteľný — beží bez zastrešujúcej transakcie
(tá je len nástrojom `--dry-run`), takže sa dá kedykoľvek spustiť znovu a už
nahraté podujatia preskočí.

### Ako sa dáta mapujú

**Deduplikačný kľúč** je `events.orginal_source`. Staršie podujatia zdrojovú
URL nemajú, tým sa doplní adresa detailu na starom webe
(`https://hlascirkvi.sk/akcie/{id}/{slug}`) — funkčný odkaz aj stabilný kľúč.
Logika je v `App\Services\Imports\HlascirkviSourceUrl`, spoločná pre import
podujatí aj obrázkov; keby sa rozišla, obrázky by sa nemali k čomu pripojiť.

**Organizátor.** V starej databáze je pri 12 042 podujatiach ako „organizácia“
uvedený len zdroj scrapera, nie usporiadateľ. TKKBS (101), ECAV (102) a Výveska
(271) preto idú do zberných kanálov `tkkbs.sk`, `ecav.sk`, `vyveska.sk` — presne
tam, kam ich dáva nočný `app:import-event-sources`. Zvyšných 23 organizácií
dostane vlastný kanál cez `ImportedCanalManager`. Reálneho usporiadateľa vie
neskôr dohľadať `app:ai-detector`.

**Miesto.** Číselníky obcí oboch projektov sú zhodné (4209 riadkov, rovnaké id),
takže `village_id` platí aj v novom projekte. Prekladať obec cez názov sa
nesmie: 211 obcí má rovnaké meno a 93 podujatí by skončilo inde. Podujatia s
obcou „Celé Slovensko“ idú na zdieľané záložné miesto. Miesta vznikajú na
úrovni obce, bez súradníc — tie doplní `app:venues-backfill-coordinates`.

**Stav.** Ukončené podujatie dostane `archived`, budúce `published`.

**Dátumy.** Starý scraper občas prečítal rok zo znenia článku, takže podujatie
z roku 2022 má `start_at` v roku 1452 a koniec v roku 8330 — nad stropom MySQL
TIMESTAMP-u, na ktorom insert padne.

Pokazený je však výlučne **rok**. Deň, mesiac aj hodina sedia: po prepise roka
na rok vzniku záznamu vyjde vo všetkých 133 takých podujatiach odstup 0 až 160
dní od článku, teda presne to, ako pozvánka vyzerá. Preto sa dátum nezahadzuje
ani nehádže na deň vzniku:

- rok mimo okna `[rok vzniku − 1, rok vzniku + 3]` sa prepíše na rok vzniku; ak
  by tým podujatie vyšlo viac než 3 dni pred článkom, na rok nasledujúci
  (decembrová pozvánka na januárovú akciu — 8 prípadov),
- koniec dostane **rovnaký posun rokov** ako začiatok, takže viacdňovej akcii
  ostane trvanie aj hodiny (14. 2. 19:00 – 20. 2. 21:00 zostane šesťdňové),
- koniec skorší než začiatok alebo vzdialenejší než rok od neho sa nahradí
  začiatkom + 2 h,
- dopočítaný čas, ktorý padne do jarnej medzery posunu času (28. 3. 2021 o
  02:00 na Slovensku neexistuje), sa posunie o hodinu — inak ho MySQL odmietne.

Označuje sa to v `meta.import.date_year_corrected` a `meta.import.end_at_estimated`.

Podujatie **bez akéhokoľvek začiatku** sa neimportuje — deň ani mesiac
neexistujú, takže sa nedá ani opraviť, ani odhadnúť, a vo výpise by viselo na
dni vzniku článku. Týka sa to 11 podujatí.

## 3. Import obrázkov

Obrázky sa sťahujú z produkcie hlascirkvi.sk a ukladajú cez `FileManager`, teda
aj s kontrolným súčtom, na disk z `FILESYSTEM_DISK` (S3 s prefixom `AWS_ROOT`) a
s dogenerovaním variantov `_thumb` / `_large`.

```bash
php artisan app:hlascirkvi-import-images --limit=2000
```

Púšťa sa v dávkach, s bežiacim `queue:work` (varianty sú frontovaná úloha), a
opakuje sa, kým hlási nové sťahovania — podujatia, ktoré obrázok už majú, sa
preskočia. Na skúšku sa hodí `--disk=public`, aby test nepísal do zdieľaného
S3 bucketu.

## Nasadenie na produkciu

1. Lokálne `php artisan app:hlascirkvi-export`.
2. Nahrať JSONL do `api/storage/app/import/` na produkcii.
3. Nasadiť kód a spustiť migrácie (pribúda index na `events.orginal_source` —
   import robí 12 000 vyhľadaní na tomto stĺpci).
4. `php artisan app:hlascirkvi-import --dry-run`, skontrolovať súhrn.
5. `php artisan app:hlascirkvi-import`.
6. `php artisan app:hlascirkvi-import-images --limit=2000` v dávkach.
7. Voliteľne `app:ai-detector` (reálni organizátori) a `app:events-ai-tag`.

Rollback: všetky prenesené podujatia majú `meta.import.source =
'hlascirkvi_legacy'`, takže sa dajú adresne zmazať.

## Overené na dočasnej databáze (6. 9. 2026)

Import celého exportu do čistej databázy `event_import`:

| | |
|---|---|
| vytvorené podujatia | 10 894 |
| preskočené | 958 (duplicitné zdrojové URL už v starej databáze) |
| bez dátumu (neimportované) | 11 |
| chybné | 0 |
| opravený rok | 131 |
| kanály | 26 (3 zberné + 23 organizácií) |
| miesta | 580, každé podujatie má miesto |
| stav | 10 785 `archived`, 109 `published` |
| `created_at` | 2019-01-24 až 2026-09-05 (zachované) |
| `start_at` | 2019-11-28 až 2028-06-20, žiadny mimo rozsahu |

Opakovaný beh nevytvoril nič nové — všetkých 11 863 riadkov preskočil.
