# Timezone pravidlá pre import externých zdrojov

Tento dokument popisuje, ako importer interpretuje dátumy a časy zo zdrojových webov.

## Dôvod

Externé zdroje ako ECAV, Výveska a TKKBS uvádzajú dátumy a časy ako lokálny slovenský čas. V zdrojovom texte zvyčajne nie je explicitne uvedené timezone ID, takže backend musí vedieť, v akej zóne tieto údaje interpretovať.

Ak by sa tieto hodnoty skladali priamo v `UTC`, pri importe by vznikal časový posun oproti reálnemu času podujatia alebo publikácie.

## Pravidlo

Pri importe sa zdrojové dátumy z týchto zdrojov interpretujú v timezone:

- `Europe/Bratislava`

Týka sa to týchto zdrojov:

- `ecav.sk`
- `vyveska.sk`
- `tkkbs.sk`

Po interpretácii lokálneho času sa výsledný moment uloží ako štandardný UTC datetime, aby ostalo správanie konzistentné s konfiguráciou aplikácie.

## Praktický význam

To znamená, že napríklad čas `17:45` uvedený na slovenskom zdroji sa nechápe ako `17:45 UTC`, ale ako `17:45 Europe/Bratislava`.

Pri letnom čase sa tak do databázy uloží zodpovedajúci UTC moment, napríklad:

- zdroj: `14.4.2026 17:45` v `Europe/Bratislava`
- uložený moment: `2026-04-14 15:45:00` UTC

To isté platí aj pre viacdňové eventy, termíny uzávierok a publikované časy, pokiaľ sa skladajú z lokálneho textového zápisu bez explicitného offsetu.

## Zdrojovo-špecifické poznámky

### ECAV

Pri ECAV sa v `Europe/Bratislava` interpretujú najmä:

- rozsahy dátumov vytiahnuté z textu detailu
- uzávierka prihlášok
- prvý rozpoznaný dátum a čas v texte, ak sa používa ako `published_at_source`

### Výveska

Pri Výveske sa v `Europe/Bratislava` interpretujú najmä:

- dátum a čas z HTML detailu eventu
- dátumové rozsahy načítané z RSS fallbacku

`pubDate` v RSS už obsahuje vlastný offset, takže ten sa parsuje podľa údajov zo zdroja.

### TKKBS

Pri TKKBS sa v `Europe/Bratislava` interpretuje najmä publikovaný čas rozpoznaný z detail textu, ktorý sa ukladá do `published_at_source`.

## Čo to chráni

Toto pravidlo zabraňuje chybám v týchto oblastiach:

- posunutý `start_at` a `end_at`
- nesprávne vyhodnotenie „aktuálnych“ a „budúcich“ eventov
- posunuté archivačné rozhodnutia
- nesprávne porovnania proti `now()`

## Technická implementácia

Interpretácia lokálnych zdrojových časov je implementovaná v importných službách:

- `App\Services\Imports\EventDetailService`
- `App\Services\Imports\VyveskaRssService`

Tieto služby skladajú zdrojový dátum v `Europe/Bratislava` a následne ho prevádzajú na UTC moment pred ďalším použitím v aplikácii.

## Regresné pokrytie

Správanie je pokryté import testami pre:

- ECAV datetime parsing
- Výveska HTML datetime parsing
- Výveska RSS datetime fallback
- TKKBS `published_at_source`
