# „V mojom okolí"

## Prečo to vzniklo

Verejný filter poznal len obec a štítok — teda len to, čo si človek vyberie zo
zoznamu. Na mobile je pritom prvá otázka „čo je dnes pri mne", nie „ktorá obec
sa volá tak, ako tá, v ktorej práve som". Miesta majú súradnice od
[`VenueCoordinateResolver`](../app/Services/Geocoding/VenueCoordinateResolver.php)
a Leaflet je v projekte od formulára adresy; chýbalo len ich spojiť.

## Súradnice sú na mieste, nie na podujatí

`events` stĺpce `latitude`/`longitude` **nemá** — adresa žije na `venues`
(a na kanáloch). Filter preto ide cez reláciu `venue` a podujatie bez miesta
alebo s miestom bez súradníc do okruhu nespadne.

Je to správne správanie, nie medzera: o podujatí bez adresy sa nedá povedať, že
je blízko. Mapa to hovorí nahlas — pod ňou je riadok „N podujatí nie je na mape",
aby nevznikol dojem, že mapa ukazuje menej než zoznam bez dôvodu.

## Dve sitá, nie jedno

[`HasCommonFilters::byDistance()`](../app/Models/Traits/HasCommonFilters.php)
najprv oreže miesta hrubým obdĺžnikom (`whereBetween` nad oboma súradnicami) a
až na tom, čo prejde, počíta haversine.

Bez predsita by musela databáza spočítať goniometrickú funkciu pre **každý**
riadok tabuľky a index by nemala ako použiť. S ním jej stačí zložený index
`venues_coordinates_index` a zvyšok doráta na hŕstke miest.

Obdĺžnik sám nestačí: v jeho rohoch je bod ďalej než polomer (10 km na oboch
osiach je uhlopriečne ~14 km). Preto haversine za ním — a preto na to existuje
vlastný test.

Jeden stupeň zemepisnej šírky je vždy ~111 km; pri dĺžke sa smerom k pólom
skracuje, preto delenie kosínusom šírky.

PostGIS ani `ST_Distance_Sphere` sme nebrali: na tomto objeme by nepriniesli nič
a viazali by nás na konkrétnu databázu.

## Neplatná poloha filter ticho vypne

Súradnice prichádzajú z `navigator.geolocation`, teda z prostredia, ktoré nemáme
pod kontrolou. Nezmyselná hodnota preto nekončí na 422, ale bežným výpisom:
prázdny zoznam s chybou by vyzeral ako porucha portálu, hoci zoznam sa dá ukázať
aj bez polohy.

Okruh je zhora obmedzený na 200 km. Nad tým už „okolie" nie je filter, ale celá
krajina, a databáze by zostalo len počítanie funkcie nad všetkým.

## Poloha sa neukladá

Ani do adresy, ani do `localStorage`. Zdieľaný odkaz s vlastnými súradnicami je
presne tá vec, ktorú človek nechce omylom poslať ďalej; po obnovení stránky je
lacnejšie spýtať sa prehliadača znova (povolenie si pamätá on) než ju držať u nás.

Ukladá sa len **okruh** — je to nastavenie, nie údaj o človeku.

Dôsledok: „v mojom okolí" sa nedá poslať odkazom ani založiť medzi obľúbené.
Náhradou je landing stránka obce (`/podujatia/mesto/{slug}`), ktorá je zdieľateľná
a indexovateľná — a práve preto ostáva.

## Vzdialenosť sa počíta na fronte

Odznak „3,2 km" na karte ráta [`utils/geo.ts`](../../ui/src/utils/geo.ts) z
súradníc miesta, ktoré v odpovedi už sú. API by ju inak muselo posielať pre každý
riadok len preto, že si niekto zapol filter.

Formát je zámerne nepresný: pod kilometer stovky metrov, do desiatich na desatinu,
ďalej celé kilometre. Presnosť na metre by pri polohe z prehliadača a mieste
zameranom na strede obce bola predstieraná.

## Mapa

Tretí pohľad vedľa agendy a mriežky. Komponent sa načítava
`defineAsyncComponent`om — Leaflet a jeho CSS by inak vyrástli v balíku každej
verejnej stránky vrátane homepage, aj keď mapu nikto neotvorí.

Bublina markera je obyčajné HTML s `<a>`, nie `RouterLink`: Leaflet ju vykresľuje
mimo Vue stromu, takže komponenty v nej nefungujú. Text sa preto escapuje ručne.

## Overenie

```bash
cd api && php artisan test tests/Feature/Events/PublicEventNearbyTest.php
```

Ručne: zapnúť „V mojom okolí" a skontrolovať, že sa **nič neobjaví v adrese** —
súradnice v URL sú chyba, nie funkcia.
