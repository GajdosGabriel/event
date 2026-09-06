# Prenos archívu podujatí z hlascirkvi

Starý projekt `hlascirkvi` má archív podujatí od januára 2019. Dva príkazy ho
prenesú do nového modelu (kanál / miesto / súbory na S3) so zachovaním
pôvodného `created_at`.

```
stará databáza hlascirkvi ──▶ app:hlascirkvi-import ──▶ app:hlascirkvi-import-images
        (alebo JSONL z app:hlascirkvi-export, keď na ňu server nevidí)
```

Obrázky sú zámerne druhý priebeh: podujatia sa nahrajú za minúty, sťahovanie
11 000 súborov je dlhá a zlyhávajúca operácia, ktorá sa musí dať opakovať bez
toho, aby sa znovu prepisovali podujatia.

Oba príkazy sú **dávkové a nadväzujúce**: `--max-seconds` beh ukončí a kurzor
si zapamätá, kde skončil. Bez toho by sa import nedal spustiť na hostingu bez
shellu, kde jediným spúšťačom je webcron s niekoľkosekundovým rozpočtom na
požiadavku (viď [Nasadenie na produkciu](#nasadenie-na-produkciu)).

Kurzor žije v cache, ale cache maže aj po-deploy endpoint `/api/artisan/run`.
Pri prázdnej cache sa preto dopočíta z už nahratých podujatí — po nasadení sa
import nevracia na začiatok.

## 1. Export do súboru (voliteľné)

Potrebné len vtedy, keď server na starú databázu **nevidí**. Ak vidí, tento
krok preskoč — import číta tie isté dáta priamo.

Do `.env` patrí spojenie na starú databázu:

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

Zdroj sa volí sám: keď je spojenie `hlascirkvi` nastavené, číta sa databáza,
inak JSONL. Vynútiť sa dá cez `--source=db` / `--source=file`; zadanie
`--file` samo znamená súbor.

Príkaz je idempotentný a prerušiteľný — beží bez zastrešujúcej transakcie
(tá je len nástrojom `--dry-run`), takže sa dá kedykoľvek spustiť znovu a už
nahraté podujatia preskočí. `--dry-run` a `--force` kurzor obchádzajú, aby
kontrolný beh nezmizol v prázdnej dávke.

### Ako sa dáta mapujú

**Deduplikačný kľúč** je `events.orginal_source`. Staršie podujatia zdrojovú
URL nemajú, tým sa doplní adresa detailu na starom webe
(`https://hlascirkvi.sk/akcie/{id}/{slug}`) — funkčný odkaz aj stabilný kľúč.
Logika je v `App\Services\Imports\HlascirkviSourceUrl`, spoločná pre import
podujatí aj obrázkov; keby sa rozišla, obrázky by sa nemali k čomu pripojiť.

**Záloha na duplicity.** Tá istá akcia je v starej databáze aj viackrát pod
rôznymi adresami — raz ako pôvodný článok, raz ako neskoršia pripomienka.
Zhoda podľa URL ich nechytí, preto rovnaká záloha ako v nočnom importe:
`canal_id` + slug názvu + presný začiatok. Slug porovnanie znesie aj rozdielne
veľké písmená („Kurz BIBLIA A PENIAZE" = „Kurz Biblia a peniaze").

Hľadá sa **v rámci kanála**. Naprieč kanálmi to robiť nemožno: „Adventná
obnova" o 16:00 môže v ten istý deň prebiehať v dvoch farnostiach a zlé
zlúčenie by jednu z nich zmazalo — duplicita je v archíve kozmetická chyba,
stratené podujatie je strata dát. Zostane tak niekoľko dvojíc, kde tá istá
akcia visí raz na zbernom kanáli a raz na rozpoznanom organizátorovi
(pri septembrovom prenose štyri); tie sa dajú spojiť ručne.

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

Obrázky sa sťahujú z hlascirkvi.sk a ukladajú cez `FileManager`, teda aj s
kontrolným súčtom, na disk z `FILESYSTEM_DISK` (S3 s prefixom `AWS_ROOT`) a s
dogenerovaním variantov `_thumb` / `_large`.

```bash
php artisan app:hlascirkvi-import-images
```

Zdrojom nie je JSONL ani stará databáza — cestu k obrázku uložil import
podujatí do `meta.import.image_path`, takže tento krok vystačí s vlastnou
databázou a dá sa spustiť kedykoľvek neskôr. Chce to bežiaci `queue:work`,
varianty sú frontovaná úloha.

Kurzor sa posúva aj cez neúspešné sťahovania, aby jeden nedostupný súbor
nezablokoval zvyšok. Na dobratie toho, čo zlyhalo, slúži záverečný beh
`--reset-cursor` — podujatia, ktoré obrázok už majú, preskočí.

Časť záznamov ukazuje na súbory, ktoré na starom webe medzičasom zmizli
(HTTP 404). Zo 40 náhodných ciest bolo dostupných všetkých 40, takže ide o
jednotlivé prípady — príkaz ich zaloguje a pokračuje ďalej.

Na skúšku sa hodí `--disk=public`, aby test nepísal do zdieľaného S3 bucketu.

## Nasadenie na produkciu

Hosting **nemá shell ani systémový cron** (viď [README](../../README.md)),
takže sa `php artisan …` nedá spustiť ručne. Import preto beží po dávkach cez
webcron, ktorý už na produkcii volá `schedule:run` každú minútu.

Naplánované úlohy sú v `routes/console.php` a existujú len vtedy, keď je
zapnutý prepínač — zapnutie aj vypnutie je teda zmena `.env`, nie nasadenie
kódu.

1. Nasadiť kód (`git pull`) a spustiť migrácie. Pribúda index na
   `events.orginal_source` — import naň robí 12 000 vyhľadaní.
2. Do `api/.env` doplniť spojenie na starú databázu — presne týchto päť
   riadkov, iné `HLASCIRKVI_DB_*` neexistujú (driver je v konfigurácii
   napevno `mysql`, takže sa `..._CONNECTION` nenastavuje):
   ```
   HLASCIRKVI_DB_HOST=localhost
   HLASCIRKVI_DB_PORT=3306
   HLASCIRKVI_DB_DATABASE=nazov_starej_databazy
   HLASCIRKVI_DB_USERNAME=pouzivatel
   HLASCIRKVI_DB_PASSWORD=heslo
   ```
   `HLASCIRKVI_DB_HOST=localhost` platí, keď starý web beží na tom istom
   serveri; inak sem patrí adresa databázového servera. Keď je spojenie zlé,
   import to napíše do logu zrozumiteľnou vetou aj s dôvodom od MySQL —
   nesype tam surovú výnimku.

   Ak server na starú databázu nevidí vôbec, namiesto toho nahraj JSONL
   z kroku 1 do `api/storage/app/import/` a do `.env` nedávaj nič.
3. Vyčistiť cache: `GET /api/artisan/run?token=<CRON_SECRET>`.
4. Zapnúť prenos: `HLASCIRKVI_IMPORT_ENABLED=true`, znovu vyčistiť cache.
5. Sledovať postup — počet podujatí rastie o ~2 500 za hodinu, obrázky
   pomalšie. Priebeh je vidieť v logu a v databáze:
   ```sql
   SELECT COUNT(*) FROM events
    WHERE JSON_EXTRACT(meta,'$.import.source') = 'hlascirkvi_legacy';
   ```
6. Keď import hlási „Nič nové na spracovanie", pustiť ešte jeden dobierací beh
   obrázkov (`--reset-cursor`) a potom vrátiť `HLASCIRKVI_IMPORT_ENABLED=false`
   plus vyčistiť cache. Spojenie na starú databázu už netreba.
7. Voliteľne `app:ai-detector` (reálni organizátori) a `app:events-ai-tag`.

Rollback: všetky prenesené podujatia majú `meta.import.source =
'hlascirkvi_legacy'`, takže sa dajú adresne zmazať.

## Overené na dočasnej databáze (6. 9. 2026)

Prenos celého archívu do čistej databázy `event_import` — v 15 dávkach po 25 s
čítaním priamo zo starej databázy, teda presne tak, ako to pobeží cez webcron.
Výsledok je zhodný s jednorazovým behom zo súboru:

| | |
|---|---|
| vytvorené podujatia | 10 856 |
| preskočené | 957 (duplicitná zdrojová URL) |
| duplicity (rovnaký názov a čas v kanáli) | 39 |
| bez dátumu (neimportované) | 11 |
| chybné | 0 |
| opravený rok | 130 |
| kanály | 26 (3 zberné + 23 organizácií) |
| miesta | 576, každé podujatie má miesto |
| stav | 10 748 `archived`, 108 `published` |
| `created_at` | 2019-01-24 až 2026-09-05 (zachované) |
| `start_at` | 2019-11-28 až 2028-06-20, žiadny mimo rozsahu |

Opakovaný beh nevytvoril nič nové a ohlásil „Nič nové na spracovanie".

Priepustnosť dávky bola ~750 podujatí za 20 s, čiže pri webcrone každú minútu
je celý archív nahratý zhruba za 20 minút.
