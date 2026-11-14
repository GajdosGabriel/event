# Audit: import podujatí, AI detekcia a preberanie kanála

Dátum: 2026-11-14. Audit vznikol čítaním kódu v `api/` — nič sa nespúšťalo ani nemenilo.
Každá úloha nižšie je samostatne zadateľná: obsahuje problém, miesto v kóde, návrh
riešenia a kritérium hotovosti.

Poradie = odporúčaná priorita.

| # | Úloha | Priorita | Oblasť |
|---|-------|----------|--------|
| 1 | E-mail z podujatia nesmie byť dôkazom vlastníctva kanála | kritická | preberanie |
| 2 | Adresy z webu: hostingy a cudzie domény | vysoká | hľadanie e-mailu |
| 3 | Zhoda názvov miest na jedno druhové slovo | vysoká | miesta, fotky |
| 4 | Organizácia vyhodnotená ako meno osoby | stredná | detekcia organizátora |
| 5 | Hľadanie kanála/miesta cez `LIKE %názov%` | stredná | detekcia |
| 6 | Duplicitné kanály si blokujú hľadanie e-mailu | stredná | hľadanie e-mailu |
| 7 | Chýbajúce zdroje adries (Cloudflare, poradie podstránok) | stredná | hľadanie e-mailu |
| 8 | Výpadok Wikidata sa cachuje 7 dní | stredná | web organizátora |
| 9 | Sťahovanie súborov vnútri DB transakcie | stredná | prílohy, fotky |
| 10 | Sťahovanie príloh bez kontroly cieľa a obsahu | stredná | bezpečnosť |
| 11 | `WebPageFetcher`: TLS, limity | nízka | bezpečnosť |
| 12 | Dvojité volanie Nominatimu | nízka | geokódovanie |
| 13 | Drobnosti | nízka | rôzne |

---

## 1. E-mail z podujatia nesmie byť dôkazom vlastníctva kanála

**Priorita: kritická**

### Problém

Importovaný kanál bez kontaktu sa dá prevziať cez e-mail, ktorý niekto uviedol
v texte podujatia:

1. `events.email` vyberá AI z textu článku — `api/app/Services/Imports/EventImportService.php:233`.
2. Keď kanál nemá e-mail, pozvánka na prevzatie ide na `$event->email` —
   `api/app/Services/Canals/CanalOutreachSender.php:279` (`contactOf`).
3. Nezávisle od toho `CanalEmailFinder::fromEvents`
   (`api/app/Services/Canals/CanalEmailFinder.php:323`) urobí z adresy z podujatia
   primárnu adresu kanála, bez ohľadu na doménu.
4. Pozvánku systému smie prijať ktorýkoľvek účet —
   `api/app/Services/Canals/CanalInviter.php:139` (`isClaimInvitation`).
5. Po prijatí sa adresa označí ako overená —
   `api/app/Services/Canals/CanalClaims.php:279` (`emails->confirm(..., create: true)`).
6. Upozornenie o prevzatí (s možnosťou námietky) ide podľa komentára
   v `api/app/Services/Canals/CanalStewardship.php:71` na doterajší kontakt kanála —
   teda na tú istú podstrčenú adresu. **Samotné odoslanie treba pri implementácii overiť.**

Ak zdroj uverejňuje príspevky od verejnosti (pri vyveska.sk to naznačuje kontrola
„pridal/a" v `Detector::resolveOrganizerCanalName`), stačí zverejniť oznam s menom
cudzej organizácie a vlastným e-mailom.

Ohrozené sú len kanály bez vlastného kontaktu — kontakt profilu má prednosť.

### Návrh

Zaviesť **silu kontaktu** na `CanalEmail` (napr. stĺpec `strength`: `strong` / `weak`):

- **silný**: adresa na registrovateľnej doméne webu kanála, alebo nájdená na jeho
  webe (titulka / kontaktná podstránka) a zároveň nie na doméne hostingu (úloha 2);
- **slabý**: iba z podujatia, freemail (gmail.com, azet.sk, centrum.sk, zoznam.sk…),
  cudzia doména.

Pravidlá:

- silný kontakt smie prevzatie dokončiť automaticky (ako dnes);
- slabý kontakt ponuku dostane, ale prijatie pozvánky / potvrdenie žiadosti vytvorí
  žiadosť `CanalClaimMethod::AdminReview` namiesto okamžitého `complete()`;
- fallback na `$event->email` v `CanalOutreachSender::contactOf` zrušiť — rozhoduje
  výlučne `CanalEmails` (primárna použiteľná adresa);
- upozornenie o prevzatí posielať na **všetky** známe použiteľné adresy kanála,
  nie len na tú, cez ktorú prevzatie prebehlo.

### Hotovo, keď

- test: kanál bez e-mailu + podujatie s adresou na freemaile → prijatie pozvánky
  neskončí `claimed_at`, vznikne žiadosť na posúdenie adminom;
- test: adresa na doméne webu kanála → prevzatie prebehne ako dnes;
- `contactOf` v `CanalOutreachSender` nečíta `$event->email`;
- zmena API kontraktu (ak pribudne stav „čaká na admina" v odpovedi
  `invitations/{token}/accept`) je výslovne popísaná — konzumuje ho `ui/`.

---

## 2. Adresy z webu: hostingy a cudzie domény

**Priorita: vysoká**

### Problém

- `CanalEmailFinder::registrable()` (`api/app/Services/Canals/CanalEmailFinder.php:469`)
  berie posledné dve časti domény. Web `farnost.webnode.sk` → doména `webnode.sk` →
  adresa `support@webnode.sk` dostane bonus +40 (`best()`, riadok 446).
- Adresa tvorcu webu v pätičke („webdesign: info@agentura.sk") sa stane primárnou,
  ak je na stránke jediná.

### Návrh

- Zoznam hostingov a platforiem (webnode.sk, webnode.page, wordpress.com, wixsite.com,
  weebly.com, sites.google.com, estranky.sk, blogspot.com, fara.sk…), pri ktorých sa
  bonus za doménu nedáva a `registrable()` vracia celý host.
- Adresy mimo domény webu kanála značiť ako slabé (nadväzuje na úlohu 1).
- Zvážiť Public Suffix List (napr. `jeremykendall/php-domain-parser`) namiesto „posledné
  dve časti" — rieši aj `gov.sk`, `co.uk`.

### Hotovo, keď

- test: web na `x.webnode.sk` + `support@webnode.sk` v pätičke → adresa nedostane
  bonus za doménu a nie je označená ako silná.

---

## 3. Zhoda názvov miest na jedno druhové slovo

**Priorita: vysoká** — nesprávna fotka je horšia než žiadna.

### Problém

`Detector::areVenueNamesCompatible` (`api/app/Services/OpenAI/Detector.php:777–820`)
vráti `true`, keď názvy zdieľajú ľubovoľné jedno slovo s aspoň 3 znakmi.
„Kostol sv. Martina" a „Kostol sv. Jána Krstiteľa" sú kompatibilné cez „kostol".

Dôsledok: miesto dostane z Wikipédie fotku, popis, web, e-mail, telefón a z geokódera
súradnice **inej budovy z tej istej obce** (riadky 532–597 a `mergeVenuePayload`).

### Návrh

- Zoznam druhových slov, ktoré sa do zhody nepočítajú: kostol, chram, kaplnka,
  bazilika, katedrala, dom, kultury, kulturny, centrum, stredisko, sala, hala, klub,
  namestie, park, skola, zakladna, materska, farsky, farnost, urad, obecny, mestsky,
  kino, divadlo, kniznica, muzeum, galeria, amfiteater, svateho, svatej.
- Zhoda vyžaduje aspoň jedno spoločné **rozlišujúce** slovo; ak ich referencia má
  dve a viac, žiadať zhodu väčšiny.

### Hotovo, keď

- test: „Kostol sv. Martina" × „Kostol sv. Jána Krstiteľa" → nekompatibilné;
- test: „Dom kultúry Ružinov" × „DK Ružinov" / „Dom kultúry Ružinov, Bratislava" → kompatibilné;
- existujúce testy Detectora prechádzajú.

---

## 4. Organizácia vyhodnotená ako meno osoby

**Priorita: stredná** (týka sa cesty cez plagát — `detectFromPoster`)

### Problém

`Detector::looksLikePersonalName` (`api/app/Services/OpenAI/Detector.php:335–377`):
kľúčové slová sú bez diakritiky (`nadacia`, `zdruzen`), text sa porovnáva
s diakritikou (`mb_strtolower`). „Nadácia Pontis", „Univerzita Komenského",
„Gymnázium Pavla Horova", „Divadlo Andreja Bagara" prejdú ako osobné meno
a organizátora nahradí názov miesta (`resolveOrganizerCanalName`, riadok 309).

### Návrh

- Porovnávať nad `Str::ascii(mb_strtolower(...))`.
- Doplniť slová: univerzita, fakulta, gymnazium, skola, divadlo, muzeum, galeria,
  kniznica, klub, zvaz, spolocenstvo, rad, rehola, klastor, diecez, biskupstvo,
  dekanat, komunita, hnutie, institut, akademia, agentura, kino, filharmonia, opera,
  festival, n.o., o.z.

### Hotovo, keď

- test: vyššie uvedené názvy → `false`; „Ján Novák", „Mária Kováčová-Horná" → `true`.

---

## 5. Hľadanie kanála/miesta cez `LIKE %názov%`

**Priorita: stredná**

### Problém

`Detector::lookupCanalByName` (`api/app/Services/OpenAI/Detector.php:228–251`)
a `lookupVenueByName` (riadky 258–283):

- presná zhoda (slug, názov) nemá prednosť — všetky tri podmienky sú v jednom `OR`
  a vyhráva najnovší záznam (`orderByDesc('created_at')`);
- `%` a `_` v názve nie sú escapované;
- krátky názov („Obec", „Fara") trafí čokoľvek.

### Návrh

- Postupne: presný slug → presný názov → `ImportedNameMatcher::firstByBaseName` →
  až potom `LIKE`, a to len pri názve s aspoň ~6 znakmi a s `addcslashes($name, '%_\\')`.
- Hľadať len medzi nezlúčenými kanálmi (`merged_into_id IS NULL`).

### Hotovo, keď

- test: existuje „Farnosť Košice" (staršia) a „Farnosť Košice-Sever" (novšia) →
  hľadanie „Farnosť Košice" vráti prvú.

---

## 6. Duplicitné kanály si blokujú hľadanie e-mailu

**Priorita: stredná**

### Problém

`CanalEmailFinder::sharedWebsite` (`api/app/Services/Canals/CanalEmailFinder.php:380`):
web, ktorý má aj iný kanál, sa berie ako spoločný portál a jeho adresy sa ignorujú.
Dva nezlúčené duplikáty tej istej organizácie (v repozitári sa zlučujú priebežne)
tak nenájdu adresu ani jeden.

### Návrh

- Za portál považovať web až od 3 kanálov s rovnakým hostom, alebo
- pri zhode hosta porovnať aj cestu (`ecav.sk/zbor-x` ≠ `ecav.sk/zbor-y`, ale dva
  kanály s presne rovnakou adresou sú skôr duplikát) a duplikát nahlásiť do
  `DuplicateResolver` / denníka.

### Hotovo, keď

- test: dva kanály s tým istým webom → hľadanie e-mailu prebehne;
- test: 3+ kanály na `ecav.sk` → web sa stále berie ako portál.

---

## 7. Chýbajúce zdroje adries

**Priorita: stredná**

### Problém

- Cloudflare ochrana e-mailov (`<a data-cfemail="…">`, `/cdn-cgi/l/email-protection#…`)
  sa v `CanalEmailFinder::extract` (riadok 502) nedekóduje — z takých webov nepríde nič.
- Kontaktné podstránky sa berú v poradí v HTML, prvé dve
  (`fromWebsite`, riadok 371); „o nás" môže predbehnúť „kontakt".

### Návrh

- Dekódovať `data-cfemail`: prvý bajt hex reťazca je kľúč, ostatné bajty XOR kľúč.
- Zoradiť `contactLinks()` podľa váhy: kontakt/contact → farský úrad/sekretariát →
  o nás/about → impressum.

### Hotovo, keď

- test: HTML s `data-cfemail` → `extract()` vráti dekódovanú adresu;
- test: stránka s odkazmi „O nás" a „Kontakt" v tomto poradí → prvá sa stiahne „Kontakt".

---

## 8. Výpadok Wikidata sa cachuje 7 dní

**Priorita: stredná**

### Problém

`OrganizerWebsiteFinder::fromWikidata` (`api/app/Services/Imports/OrganizerWebsiteFinder.php:125`)
cachuje výsledok `lookupWikidata()` na 7 dní. Tá vráti `null` pri chybe (timeout,
429, výnimka) aj pri „nenájdené" — jeden výpadok znamená týždeň bez webu organizátora.

### Návrh

`lookupWikidata` nech rozlišuje „nenájdené" (cachovať) a „chyba" (necachovať) —
rovnako ako `NominatimGeocoder::lookupDetailed` (`cacheable`).

### Hotovo, keď

- test: `Http::fake` s 500 → nič sa neuloží do cache, druhé volanie ide znova na sieť.

---

## 9. Sťahovanie súborov vnútri DB transakcie

**Priorita: stredná**

### Problém

`FileManager::storeRemoteForModel` (`api/app/Services/Files/FileManager.php:102`)
volá `RemoteAttachmentPersister::store` vnútri `DB::transaction`. Každé sťahovanie
má timeout 60 s → dlho držaná transakcia. Jedna zlyhaná príloha (výnimka) zahodí
všetky.

### Návrh

- Stiahnuť a zvalidovať obsah pred transakciou; v transakcii len zápis na disk a do DB.
- Zlyhanie jednej prílohy zalogovať a pokračovať ďalšou.

### Hotovo, keď

- test: 3 prílohy, druhá vráti 404 → uložia sa dve, chyba je v logu.

---

## 10. Sťahovanie príloh bez kontroly cieľa a obsahu

**Priorita: stredná**

### Problém

`RemoteAttachmentPersister::store` (`api/app/Services/Files/RemoteAttachmentPersister.php:39`):

- adresy z článkov sa sťahujú bez `Url::isSafeToProbe` a presmerovania sa nasledujú
  automaticky (`CanalWebsitePages::get` to robí správne, krok po kroku);
- typ obrázka sa overuje len podľa hlavičky `Content-Type`, nie podľa obsahu;
- pri `FileType::FILE` nie je limit veľkosti ani povolené typy; prípona sa berie
  z textu odkazu.

### Návrh

- Sťahovať cez spoločný bezpečný klient (vytiahnuť `CanalWebsitePages::get` do
  zdieľanej služby, alebo ju použiť priamo).
- Obrázky overiť cez `getimagesizefromstring` / `finfo`.
- Pre FILE: whitelist prípon a MIME (pdf, doc, docx, xls, xlsx, odt, txt), limit veľkosti.

### Hotovo, keď

- test: URL s presmerovaním na `127.0.0.1` → odmietnuté;
- test: HTML poslané ako `image/jpeg` → odmietnuté;
- test: príloha s príponou `.html` / `.svg` → odmietnutá.

---

## 11. `WebPageFetcher`: TLS a limity

**Priorita: nízka** — `orginal_source` sa cez API nastaviť nedá, URL sú z importu.

### Problém

`api/app/Services/OpenAI/WebPageFetcher.php:52–67`: `CURLOPT_SSL_VERIFYPEER => false`,
bez `CURLOPT_MAXREDIRS`, bez obmedzenia protokolov a veľkosti odpovede.

### Návrh

- Overenie TLS zapnúť; výnimku pre konkrétny zdroj riešiť zoznamom hostov v configu.
- `CURLOPT_MAXREDIRS => 5`, `CURLOPT_PROTOCOLS` a `CURLOPT_REDIR_PROTOCOLS` na http/https,
  limit veľkosti (napr. 5 MB cez `CURLOPT_PROGRESSFUNCTION` alebo `CURLOPT_MAXFILESIZE`).

### Hotovo, keď

- import zo všetkých štyroch zdrojov prejde so zapnutým overením TLS (alebo je výnimka
  zdokumentovaná v configu).

---

## 12. Dvojité volanie Nominatimu

**Priorita: nízka**

### Problém

`Detector::detectVenueDetails` (`api/app/Services/OpenAI/Detector.php:520–523`) volá
`lookup()` a hneď `lookupDetailed()`. Úspech ide druhýkrát z cache, ale neúspech (429)
sa necachuje — záťaž sa zdvojí práve vtedy, keď geokodér odmieta.

### Návrh

Volať len `lookupDetailed()` a `result` vziať z neho. Kontrolu `get_class(...) ===`
nahradiť rozhraním alebo `method_exists`, aby testovacie dvojníky fungovali.

### Hotovo, keď

- test: jeden beh `detectVenueDetails` = jedna séria dotazov na Nominatim.

---

## 13. Drobnosti

- [ ] **Vlastníctvo miesta po importe** — `api/app/Services/Imports/ImportedVenueManager.php:121`
  a `:180`: miesto vlastní kanál, ktorý ho prvý priniesol. Po prevzatí kanála môže nový
  vlastník upravovať spoločné miesto (dom kultúry, katedrálu). Zvážiť vlastníctvo len
  pri zhode názvu / adresy miesta s kanálom, inak `isOwner: false`.
- [ ] **Tichý `catch`** — `ImportedVenueManager.php:125`: prázdny `catch (\Throwable)`
  bez logu. Ak zlyhá až `assignCanal`, miesto sa založí druhýkrát vo vetve nižšie.
  Pridať `Log::warning` a `Venue::create` + `assignCanal` dať do transakcie.
- [ ] **Mŕtvy kód** — `api/app/Services/OpenAI/AttachmentDownloader.php`
  a `Detector::stiahniPrilohy` sa nikde nevolajú. Zmazať.
- [ ] **Simulácia e-mailov** — `api/config/canals.php:16`: `CANAL_CLAIM_MAIL_SIMULATE`
  je predvolene `true`. Overiť, že na produkcii je `false`, inak potvrdzovacie e-maily
  k žiadostiam o prevzatie neodchádzajú.
- [ ] **`.PDF`** — `api/app/Services/OpenAI/ContentExtractor.php:120`: `strpos($href, '.pdf')`
  rozlišuje veľkosť písmen. Použiť `stripos`.
- [ ] **Natvrdo `village_id => 4209`** — `ImportedVenueManager.php:212`: presunúť do configu.
- [ ] **Výkon oslovenia** — `CanalOutreachSender::eventsFor`
  (`api/app/Services/Canals/CanalOutreachSender.php:146`) načíta všetky podujatia v okne
  a až potom filtruje; `stats()` sú 4 dotazy na kandidáta. Pri raste dát pridať
  `limit` / chunk a zoskupené počty.

---

## Čo je v poriadku (netreba meniť)

- Potvrdenie žiadosti o prevzatie je `POST` — e-mailové skenery ho nespustia.
- `CanalWebsitePages` kontroluje každé presmerovanie cez `Url::isSafeToProbe`.
- Adresu od AI `CanalEmailFinder::fromResearch` prijme, len ak na zdrojovej stránke doslova je.
- Nominatim necachuje neúspešné odpovede.

## Poznámky k implementácii

- Testy púšťať cielene (`php artisan test --filter=…`), celá suite trvá ~10 min.
- Pri zmene `resources/lang` spustiť `php artisan test --filter=LocaleTest`.
- Úlohy 1 a 2 spolu súvisia (sila kontaktu) — odporúčam ich robiť v jednom kroku.
- Úlohy 9 a 10 sa dotýkajú toho istého súboru — robiť spolu.
