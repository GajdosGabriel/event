# Výveska import a RSS fallback pre dátumy

Tento dokument popisuje, ako sa pri importe zo zdroja `vyveska.sk` dopĺňajú dátumy akcie cez RSS feed.

## Dôvod

HTML detail stránky na Výveske nemusí vždy obsahovať spoľahlivo parsovateľný dátum začiatku a konca akcie.

Preto importer používa aj RSS feed:

- `https://www.vyveska.sk/rss.xml`

RSS sa nepoužíva ako primárny zdroj celého eventu. Slúži len ako doplnkový fallback pre dátumové údaje a prípadne čas publikovania zdroja.

Zdrojové časy z Vývesky sa interpretujú ako lokálny čas `Europe/Bratislava` a až potom sa prevedú na výsledný UTC moment používaný aplikáciou.

## Aktuálne správanie

Pri importe detailu z Vývesky systém postupuje takto:

1. Načíta detail HTML stránky eventu.
2. Pokúsi sa z detailu vytiahnuť:
- názov
- popis
- odkazy
- obrázky a prílohy
- `start_at` a `end_at`
- `published_at_source`
3. Ak sa z HTML detailu nepodarí spoľahlivo získať `start_at` alebo `end_at`, importer vyhľadá zodpovedajúci záznam v RSS.
4. Párovanie sa robí podľa kanonického detail URL bez query parametrov, aby sa zhodovali aj RSS odkazy typu `?utm_source=...`.
5. Ak RSS obsahuje použiteľný dátumový rozsah, použije sa ako fallback.
6. Ak detail stránka nemá publikovaný čas zdroja, použije sa `pubDate` z RSS.

## Čo sa berie z RSS

Z RSS itemu sa aktuálne používajú tieto polia:

- `link`: na spárovanie RSS itemu s detail URL eventu
- `description`: fallback zdroj dátumového rozsahu
- `category`: doplnkový fallback zdroj dátumového rozsahu, keď je dátum uvedený skôr tam než v `description`
- `pubDate`: fallback pre `published_at_source`

## Čo sa z RSS nepoužíva ako primárny zdroj

Tieto údaje sa stále berú primárne z HTML detailu eventu:

- názov eventu
- telo/popis eventu
- odkazy v tele
- obrázky
- prílohy

Dôvod je jednoduchý: HTML detail je bohatší a presnejší pre obsah eventu, zatiaľ čo RSS je vhodný najmä na doplnenie štruktúrovaného dátumového rozsahu.

## Podporované formáty dátumu z RSS

RSS parser aktuálne počíta najmä s týmito formátmi, ktoré sa na Výveske reálne vyskytujú:

- `14.4.2026 - 17:45 - 19:45`
- `14.4.2026 17:45 - 16.4.2026 19:45`
- `14.4.2026 17:45 - 16.4.2026`
- `14.4.2026 - 16.4.2026`
- `14.4.2026 17:45 - 19:45`

To pokrýva jednodňové aj viacdňové podujatia vrátane prípadov, keď feed uvádza len deň bez presného koncového času.

## Pravidlá fallbacku

- Ak HTML detail poskytne `start_at` aj `end_at`, RSS ich neprepisuje.
- Ak HTML detail neposkytne `start_at`, použije sa `start_at` z RSS, ak je dostupný.
- Ak HTML detail neposkytne `end_at`, použije sa `end_at` z RSS, ak je dostupný.
- Ak HTML detail neposkytne `published_at_source`, použije sa `pubDate` z RSS.
- Ak RSS nie je dostupné alebo sa nepodarí spracovať, import pokračuje ďalej bez pádu a bez RSS fallbacku.

## Timezone pravidlo

- HTML dátumy a RSS dátumové rozsahy z Vývesky sa interpretujú v `Europe/Bratislava`.
- Výsledný datetime sa následne používa ako korektný UTC moment v rámci aplikácie.
- `pubDate` z RSS sa parsuje podľa offsetu uvedeného priamo v RSS položke.

## Technická poznámka

RSS helper je implementovaný ako samostatná služba, ktorá si počas jedného behu procesu drží feed v pamäti, aby sa RSS nenačítavalo opakovane pre každý jeden importovaný Výveska event.

Súvisiace triedy:

- `App\Services\Imports\VyveskaRssService`
- `App\Services\Imports\EventDetailService`

## Regresné pokrytie

Správanie je pokryté testami pre tieto scenáre:

- bežný Výveska import z detail stránky
- odfiltrovanie statických stránok z listingu
- použitie RSS dátumov, keď detail stránka dátum neobsahuje
- párovanie RSS itemu podľa kanonického detail URL
