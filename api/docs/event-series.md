# Séria opakovaných termínov

## Prečo to vzniklo

Klub, divadlo alebo kultúrny dom hrá ten istý program viackrát. Doteraz na to
bolo jediné riešenie: „Duplikovať" — a s ním osem samostatných podujatí, ktoré
o sebe nevedia. Zmena popisu znamenala osem úprav, verejný výpis zaplavilo osem
takmer rovnakých kariet a návštevník na detaile nemal ako zistiť, že sa to hrá
aj vo štvrtok.

## Model: každý termín je samostatné podujatie

Séria je **väzba medzi podujatiami**, nie nádoba s termínmi.

Alternatíva („jedno podujatie, tabuľka termínov") vyzerá čistejšie, ale
znamenala by prepísať lístky, `admissions`, check-in, ICS, JSON-LD aj štatistiky:
`start_at` je dnes v štyridsiatich súboroch a všade znamená „kedy sa to koná".
A hlavne — organizátor chce vedieť, **kto mu príde vo štvrtok**, nie kto príde
niekedy počas série. Samostatné podujatie mu to dá zadarmo.

Tabuľka [`event_series`](../database/migrations/2026_09_04_100000_create_event_series_table.php)
je preto takmer prázdna: `id`, `canal_id`, časy. Názov ani popis tu nie sú —
zdrojom pravdy zostáva podujatie. Keby tu bol názov, boli by dva a rozišli by sa.

## Čo je spoločné a čo nie

| Spoločné — prepíše sa do všetkých termínov | Vlastné — nikdy sa neprepisuje |
|---|---|
| popis, web, miesto a adresa, štítky, obrázky | termín, stav, typy lístkov a ceny, kapacita, prihlásení, check-in, otázky |

Zoznam spoločných polí drží
[`EventSeriesManager::SHARED_FIELDS`](../app/Services/Events/EventSeriesManager.php).

**Typy lístkov sa zámerne neprepisujú.** Pri sérii je bežné, že jeden termín je
vypredaný a druhý má miesta, alebo že premiéra stojí viac než repríza.
Prepisovať ceny do termínu, ktorý je už v predaji, by menilo podmienky ľuďom,
čo si lístok kúpili.

**Názov a slug tiež nie.** Meno je súčasťou verejnej adresy termínu; jeho zmena
by prepísala kanonické URL celej série naraz.

## Prepisuje sa len to, čo sa naozaj zmenilo

`EloquentEventRepository::update()` si po uložení vypýta `getChanges()` — teda
to, čo sa **zapísalo**, nie celý payload formulára — a pošle sériu prepísať len
tieto polia.

Rozdiel je podstatný. Keby sa zapisoval celý zoznam spoločných polí, uloženie
formulára v jednom termíne by prepísalo aj to, čo si niekto v inom termíne
vedome upravil, a nemal by ako zistiť prečo.

To isté platí pre štítky: prepíšu sa len vtedy, keď ich formulár naozaj poslal.
Bez tejto podmienky by uloženie akéhokoľvek iného poľa zmazalo štítky vo zvyšku
série, lebo `syncEventTags` sa volá s prázdnym zoznamom.

## Obrázky sa kopírujú, nezdieľajú

Prílohy sa neprepájajú na tú istú cestu na disku, ale kopírujú aj s dátami
([`FileDuplicator`](../app/Services/Files/FileDuplicator.php)). Dôvod je
prevádzkový: zmazanie súboru maže fyzický objekt podľa `path`
([`FileLifecycleService`](../app/Services/Files/FileLifecycleService.php)), takže
dva riadky nad jednou cestou by znamenali, že zmazanie plagátu v jednom termíne
rozbije náhľad v ostatných — a prejaví sa to mesiace po tom, čo to niekto spraví.

Kópia si do `meta.copied_from` poznačí zdrojový riadok. Vďaka tomu vie
`propagateImages()` rozoznať obrázok, ktorý prišiel zo série, od obrázka, ktorý
tam niekto nahral sám: **termín s vlastným plagátom sa preskočí** a oň neprípde.

Vedľajší efekt tejto práce: „Duplikovať" dovtedy obrázky nekopírovalo vôbec,
takže duplikát prišiel o plagát. Obe cesty dnes idú cez
[`EventContentCopier`](../app/Services/Events/EventContentCopier.php).

## Verejná časť

**Výpis ukáže zo série len najbližší termín.** Divadlo s ôsmimi reprízami by
inak zabralo celú prvú stranu agendy. Poddotaz v `collapseSeries()` hľadá
súrodenca, ktorý je *skôr a stále v budúcnosti* — preto sa výpis sám posúva:
keď najbližší termín prebehne, na jeho miesto nastúpi ďalší, bez akéhokoľvek
prepočtu. Karta k tomu dostane odznak „a ďalších N termínov".

**Detail ukáže ostatné termíny** ako zoznam odkazov pod dátumom. Sedí to tam,
lebo je to odpoveď na otázku, ktorú si človek kladie práve v tom mieste: „a keď
v stredu nemôžem?"

SEO to neuberá: každý termín má naďalej vlastnú adresu, vlastné JSON-LD aj
vlastný riadok v sitemape. Zbalenie sa deje len vo výpise.

## Vznik a zánik série

Séria vzniká pri **druhom** termíne — jeden termín ju nepotrebuje. Prvé volanie
`addOccurrence()` ju založí a zaradí do nej aj zdrojové podujatie.

Zaniká symetricky: keď v nej po vyradení termínu zostane menej než dva, séria sa
zmaže a zvyšný termín sa uvoľní. Jednoprvková séria by vo výpise stále hlásila
„a ďalšie termíny", hoci žiadne nie sú.

Nový termín je vždy **koncept bez dátumu**. Automatické publikovanie by
z preklepu spravilo verejnú stránku, a predvyplniť dátum by znamenalo hádať, či
je to o týždeň, o mesiac, alebo každý druhý štvrtok.

## Práva

Pridanie termínu vyžaduje `event.create` v kanáli (policy `duplicate`) — vzniká
tým nové podujatie. Vyradenie zo série vyžaduje `event.update`.

`series_id` nie je vo `FormRequest`och podujatia, takže sa nedá poslať cez bežný
formulár; členstvo v sérii mení výhradne `EventSeriesManager`. Inak by sa dalo
podujatie priviazať k cudzej sérii jedným poľom navyše v požiadavke.

## Overenie

```bash
cd api && php artisan test tests/Feature/Events/EventSeriesTest.php
```

Ručne stojí za pozretie práve to zbalenie: publikovať dva termíny série a
skontrolovať, že vo verejnom výpise je jedna karta s odznakom, ale obidve adresy
sú v `sitemap.xml`.
