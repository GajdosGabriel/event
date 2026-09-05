# Widget na cudzom webe

## Prečo to vzniklo pred platbami

Roadmapa mala embed až za platobnou bránou (4.1), ale prvá polovica na ňu nečaká:
zoznam podujatí organizátora a registrácia na bezplatné podujatie sa dajú
postaviť nad dnešným verejným API.

Zmysel je návyk, nie funkcia. Organizátor si widget nasadí na svoj web hneď a od
tej chvíle mu registrácie chodia cez nás — keď brána príde, mení sa len obsah
widgetu, nie to, kde ľudia klikajú. Toto je typicky moment, keď platforma
prestane byť nahraditeľná.

## Ako to organizátor použije

V dashboarde na detaile kanála je panel „Program na váš web"
([`EmbedSnippetPanel`](../../ui/src/components/EmbedSnippetPanel.vue)) — pár
prepínačov a hotový kód na skopírovanie:

```html
<script src="https://<portal>/embed.js"
        data-canal="divadlo-12"
        data-limit="5"></script>
```

`data-event="nazov-42"` namiesto `data-canal` vloží jedno podujatie aj
s registračným formulárom.

## Loader, nie iframe rovno

[`embed.js`](../../ui/public/embed.js) vloží iframe sám. Dôvod je výška: iframe
si ju nastaviť nevie, takže by widget musel mať pevnú výšku a program by v ňom
skroloval v okienku. Embed stránka preto hlási výšku cez `postMessage`
a loader ju dopasuje.

Správa má `targetOrigin: '*'` — doménu organizátorovho webu nepoznáme a poznať
ju nepotrebujeme; nesie jediné číslo. Opačný smer je prísny: loader prijme len
správu z **nášho** originu, od **svojho** iframe a s očakávaným typom. Na cudzej
stránke môžu bežať aj iné widgety a tie nemajú čo meniť náš rám.

Súbor je zámerne bez závislostí a bez build kroku: kopíruje sa do `dist` ako je
a musí fungovať aj na starom webe s cudzím frameworkom.

## Odkazy von, nie dovnútra

Každý odkaz v embed stránkach má `target="_blank"`. Bez toho by sa portál načítal
do widgetu širokého 300 px na cudzom webe — teda do priestoru, ktorý na detail
podujatia nikdy nebude stačiť.

Preto má iframe aj `sandbox` bez `allow-top-navigation`: widget navigáciu na
najvyššej úrovni nerobí, takže mu ju nepovoľujeme.

## Rámovanie je inak zakázané

Pri tejto práci sa ukázalo, že portál **nemal nastavené nič** — dal sa teda celý,
vrátane dashboardu a prihlásenia, natiahnuť do priehľadného rámu na cudzej
stránke a nechať človeka klikať naslepo (clickjacking).

`ui/public/.htaccess` preto odteraz posiela `X-Frame-Options: SAMEORIGIN`
a `Content-Security-Policy: frame-ancestors 'self'`, a pre `/embed/...` obe
uvoľňuje. Premennú nastavuje rewrite pravidlo; po internom prepise na
`index.html` ju Apache prefixuje `REDIRECT_`, preto sa testujú obe mená.

**Súbor je mimo gitu** — po nasadení sa musí preniesť ručne, inak widget na
cudzom webe zostane prázdny (rám sa nenačíta) a clickjacking zostane otvorený
([deploy/htaccess.md](../../deploy/htaccess.md)).

## Bezpečnosť a súkromie

- Widget beží **nad verejným API**, bez prihlásenia a bez tokenu. Ukazuje presne
  to, čo je aj tak verejné na portáli — cez iframe sa nedá vytiahnuť nič navyše.
- Registrácia v iframe je pre prehliadač **tretia strana**, takže cookies môžu
  byť zablokované. Nevadí: verejná objednávka lístka beží bez session — je to
  stateless požiadavka, presne ako od hosťa na portáli.
- Rate limity platia rovnaké (`public-write`), takže widget nie je cesta okolo
  nich.

## Čo tým nie je vyriešené

- **Platba vo widgete** — až s bránou (fáza 2). Dovtedy má zmysel pre podujatia
  s bezplatnou registráciou, čo je väčšina katalógu.
- **Vzhľad na mieru** (farby organizátora) — widget dnes prevezme len to, či
  ukázať obrázky a názov. Vlastná téma je ďalší krok, nie podmienka nasadenia.
- **Štatistika, koľko registrácií prišlo z widgetu.** Zdroj registrácie sa
  nerozlišuje; keď to bude treba pre províziu, patrí to k 4.3.

## Overenie

Widget je celý na frontende, takže ho nekryje PHPUnit. Ručne:

1. `/embed/organizator/{slug}-{id}?limit=3` musí vrátiť holý zoznam bez hlavičky
   portálu.
2. Vložiť `embed.js` do ľubovoľnej HTML stránky a overiť, že sa **výška iframe
   dopasuje** obsahu.
3. Po nasadení: `curl -I https://<portal>/podujatia` musí mať
   `X-Frame-Options: SAMEORIGIN`, kým `curl -I https://<portal>/embed/organizator/x-1`
   ho mať **nesmie**.
