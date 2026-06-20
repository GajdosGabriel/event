# Správanie generovania variantov súborov a mazania redundantných originálov

Tento dokument popisuje, ako sa má správať upload alebo import súborov v prípade, že systém generuje obrazové varianty ako `thumb` a `large`.

Text je písaný tak, aby sa dal použiť aj ako zadanie alebo prompt v inom projekte.

## Cieľ správania

Pri súboroch, z ktorých systém vie vytvoriť obrazový náhľad, nechceme zbytočne držať na disku duplicitný originál, ak už neprináša dodatočnú hodnotu.

Požadované správanie:

- Ak systém vytvorí obrazový preview z PDF alebo z inej neobrázkovej prílohy, môže ponechať len vygenerované varianty `thumb` a `large` a pôvodný binárny originál po úspešnom spracovaní zmazať.
- Ak je zdrojom obrázok a systém z neho úspešne vytvorí `large`, originál sa po úspešnom spracovaní zmaže.
- Ak vznikne iba `thumb` a `large` nevznikne, originál sa ponechá.
- Ak sa generovanie variantov nepodarí, originál sa nesmie zmazať.

## Rozhodovacie pravidlá

### 1. PDF alebo iná neobrázková príloha

Ak systém:

- načíta originálny súbor,
- úspešne z neho vytvorí obrazový preview,
- úspešne uloží aspoň jeden variant,

potom:

- pôvodný originál môže zmazať,
- databázový záznam môže ďalej ukazovať na `thumb` alebo `large` cez fallback logiku,
- používateľské rozhranie má naďalej zobrazovať náhľad bez rozbitých URL.

Praktický dôvod:

- PDF alebo dokument slúžil len ako vstup na vyrobenie náhľadu,
- po úspešnom preview je držanie originálu na disku len dodatočná spotreba miesta,
- ak aplikácia nepotrebuje originál na download, je bezpečné ho odstrániť.

### 2. Obrázok, pri ktorom vznikne iba `thumb`

Ak systém spracúva priamo obrázok a úspešne z neho vytvorí iba `thumb`, ale nevznikne `large`, potom:

- originál sa ponechá,
- `thumb` slúži pre náhľadové použitie,
- originál ostáva k dispozícii ako väčší zdroj.

Praktický dôvod:

- ak obrázok nedosahuje veľkosť potrebnú na vytvorenie `large`, pôvodný súbor je stále najväčšia dostupná verzia,
- samotný `thumb` nestačí ako plná náhrada originálu.

### 3. Veľký obrázok

Ak je zdrojový obrázok dostatočne veľký na vytvorenie `large`, systém vytvorí `thumb` aj `large`. Po úspešnom vytvorení `large` sa originál zmaže.

Praktický dôvod:

- ak už existuje náš `large`, ten preberá rolu hlavného väčšieho výstupu,
- vtedy je originál redundantný a môže sa odstrániť.

### 4. Zlyhanie generovania

Ak sa nepodarí vytvoriť preview alebo uložiť varianty, originál sa nesmie zmazať.

Praktický dôvod:

- systém nesmie skončiť v stave, že nemá ani originál, ani variant,
- mazanie originálu je dovolené len po potvrdenom úspechu generovania.

## Požadovaný fallback v modelovej alebo resource vrstve

Ak bol originál po spracovaní zmazaný, aplikácia nesmie vracať neplatnú URL.

Odporúčané fallback poradie:

1. `large`, ak existuje
2. `thumb`, ak existuje
3. placeholder, ak neexistuje nič iné

To platí najmä pre:

- preview URL v admin rozhraní,
- zoznamy eventov alebo súborov,
- akékoľvek serializované API polia typu `original_file_url`, `thumb_image_url`, `large_image_url`.

## Odporúčaná meta informácia

Systém si môže do `meta` uložiť technický stav spracovania, napríklad:

```json
{
  "variant_generation": {
    "status": "generated",
    "generated_at": "2026-04-10 12:34:56",
    "original_deleted": true
  }
}
```

To pomáha pri debugovaní a pri spätnej analýze správania queue jobov.

## Stručná špecifikácia pre iný projekt

Ak chceš toto správanie zadať do iného projektu, môžeš použiť tento text takmer doslova:

```text
Implementuj generovanie obrazových variantov súborov takto:

1. Pri PDF alebo inej neobrázkovej prílohe:
- ak sa z originálu úspešne vytvorí preview obrázok a uložia sa varianty thumb alebo large, originálny vstupný súbor zmaž ako redundantný,
- ak sa preview nepodarí vytvoriť, originál ponechaj.

2. Pri obrázkoch:
- ak sa z originálu úspešne vytvorí `large`, originál zmaž ako redundantný,
- ak vznikne iba `thumb` a `large` nevznikne, originál ponechaj,
- ak sa varianty nepodaria vytvoriť, originál ponechaj.

3. V aplikačnej vrstve zabezpeč fallback URL:
- ak originál už neexistuje, používaj large,
- ak large neexistuje, používaj thumb,
- ak neexistuje nič, použi placeholder.

4. Originál maž iba po potvrdenom úspešnom vygenerovaní variantov.

5. Ulož do meta informáciu, či sa varianty vygenerovali a či bol originál zmazaný.
```

## Akceptačné pravidlá

- Po úspešnom preview z PDF nesmie na disku ostať zbytočný originálny dokument, ak aplikácia nepotrebuje jeho download.
- Po úspešnom spracovaní obrázka sa originál smie zmazať iba vtedy, keď aplikácia vytvorila `large`.
- Ak vznikol iba `thumb`, originál musí zostať zachovaný.
- URL pre preview musia fungovať aj po zmazaní originálu.
- Pri neúspechu spracovania musí originál zostať zachovaný.
- Správanie musí byť deterministické a ľahko testovateľné v queue jobe alebo service vrstve.
