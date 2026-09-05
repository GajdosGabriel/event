# „Moje lístky" — účet návštevníka

## Prečo to vzniklo

K vydanej vstupenke sa dalo dostať **len odkazom `/tickets/{uuid}` z e-mailu**.
Kto si e-mail zmazal alebo ho nenašiel, o vstupenku prišiel, hoci ju v systéme
mal. Prihlásený účet zároveň nemal v aplikácii jedinú stránku, ktorá by patrila
jemu — `/dashboard` je celý o organizovaní a návštevníkovi nehovorí nič.

To robilo z registrácie jednorazovú formalitu pred objednávkou: nebol dôvod sa
vrátiť. `/moje-listky` je ten dôvod, a zároveň miesto, kam neskôr pribudnú
objednávky a doklady z platieb.

## Vlastníctvo lístka nie je len `user_id`

Objednať sa dá bez účtu — vtedy má objednávka iba `holder_email`. Ľudia si účet
často založia až potom (napríklad z pozvánky do tímu kanála), takže väzba len
cez `user_id` by z výpisu vyhodila presne tie lístky, kvôli ktorým človek na
stránku prišiel.

[`TicketOwnership`](../app/Services/Tickets/TicketOwnership.php) je jediné
miesto, ktoré na otázku „čie je to" odpovedá: `tickets.user_id` **alebo**
`LOWER(tickets.holder_email)` zhodné s adresou účtu. Nie je to nové pravidlo —
tú istú dvojicu používa kontrola limitu na osobu (`existingMainSeats`
v [EloquentTicketRepository](../app/Repositories/Eloquent/EloquentTicketRepository.php)),
len doteraz nemala meno.

Dôsledok, ktorý treba mať na pamäti: **zmena e-mailu na účte zmení, čo človek
vo výpise vidí.** Lístky viazané cez `user_id` zostanú, hosťovské objednávky na
starú adresu vypadnú. Alternatíva (kopírovať `user_id` do starých objednávok pri
registrácii) by znamenala zápis do cudzích riadkov na základe zhody adresy,
ktorú nikto neoveril — a to je horšie.

Stĺpce sú v dotaze kvalifikované (`tickets.status`, `tickets.user_id`), lebo
výpis si k nemu pripája `events` kvôli zoradeniu podľa termínu a obe tabuľky
majú `status`.

## Delenie na „nadchádzajúce" a „históriu"

- **Nadchádzajúce** — nezrušená objednávka na podujatie, ktoré sa nezačalo,
  plus objednávky na podujatia **bez termínu**: kým ho organizátor nedoplní, je
  to živý záznam a v histórii by ho nikto nehľadal.
- **História** — uplynulé podujatia a zrušené objednávky.

`start_at` sa porovnáva **od začiatku dňa**, nie od aktuálnej hodiny. Lístok na
dnešný večer tak nespadne do histórie ráno o deviatej — presne v deň podujatia
ho človek potrebuje najviac.

Zrušená objednávka z nadchádzajúcich zmizne (človek ju zrušil práve preto, aby
ju nemal pred sebou), ale nezanikne — ostáva v histórii ako doklad o tom, že sa
zrušenie podarilo.

## Detail vstupenky zostáva na `/tickets/{uuid}`

Výpis na detail len odkazuje. Jedna stránka pre oba vstupy (odkaz z e-mailu aj
z výpisu) znamená jedno miesto, kde sa vykresľuje QR kód a rieši čakačka či
potvrdzovanie účasti — dva takmer rovnaké pohľady by sa časom rozišli.

Detail preto **zostáva verejný**: kto pozná uuid, vstupenku otvorí aj
neprihlásený. Je to zámer, nie opomenutie — vstupenka sa preposiela ďalej a
príjemca účet nemá.

## Odbery sú na tej istej stránke

„Daj mi vedieť" ([Subscription](../app/Models/Subscription.php)) vzniká bez
účtu, takže sa páruje **cez e-mail**, nie cez `user_id`. Pre návštevníka je to
tá istá vec ako lístok — niečo, na čo čaká, len bez vstupenky — preto to nemá
vlastnú stránku.

Odhlásenie odkazom z pätičky e-mailu (`/odhlasenie/{token}`) funguje ďalej a je
jediná cesta pre toho, kto účet nemá; routa v `me/` je pohodlie navyše a volá tú
istú metódu modelu (adresa sa zahodí, riadok zostane). Druhé odhlásenie toho
istého odberu preto skončí na 404, nie na chybe — `active()` už riadok nevidí.

## Prečo `me/`, a nie `dashboard/`

Prefix `dashboard` patrí organizovaniu a jeho routy sa vetvia podľa rolí
a kanálov. Sem sa dostane každý prihlásený bez ohľadu na to, či nejaký kanál má
— jediná podmienka je `auth:sanctum`.

## Overenie

```bash
cd api && php artisan test tests/Feature/Tickets/MyTicketsTest.php
```

Ručne: objednať lístok **bez prihlásenia** na adresu, ktorou sa potom
zaregistrujete — objednávka musí byť vo výpise. Potom skontrolovať, že v ňom nie
je nič cudzie: `/api/me/tickets` nesmie vrátiť lístok iného účtu ani po zmene
`per_page` a `page`.
