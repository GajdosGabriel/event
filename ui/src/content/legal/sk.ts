import type { LegalDocuments } from './types'

/**
 * Záväzné znenie. Ostatné jazyky sú jeho preklad — pri každej zmene textu
 * treba upraviť aj cs/de/en a zvýšiť LEGAL_VERSION (a `config('legal.version')`).
 */
const sk: LegalDocuments = {
  terms: {
    title: 'Obchodné podmienky',
    perex:
      'Tieto obchodné podmienky upravujú práva a povinnosti pri používaní portálu {site}. ' +
      'Znenie je účinné od {effectiveFrom} (verzia {version}).',
    sections: [
      {
        heading: 'Čl. 1 — Prevádzkovateľ',
        paragraphs: [
          '1.1 Prevádzkovateľom portálu {site} (ďalej len „Portál“) je {name}, so sídlom {address}, IČO: {ico}, {dic}, {registration} (ďalej len „Prevádzkovateľ“).',
          '1.2 Kontaktné údaje: e-mail {email}, telefón {phone}.',
          '1.3 Orgánom dozoru je Slovenská obchodná inšpekcia — {soi}.',
        ],
      },
      {
        heading: 'Čl. 2 — Vymedzenie pojmov',
        paragraphs: [
          '2.1 Portál — webová aplikácia dostupná na adrese {site}, ktorá zverejňuje informácie o kultúrnych, spoločenských, športových a iných podujatiach a súvisiace služby.',
          '2.2 Používateľ — fyzická alebo právnická osoba, ktorá Portál používa, aj bez registrácie.',
          '2.3 Registrovaný používateľ — Používateľ, ktorý má na Portáli vytvorený účet.',
          '2.4 Organizátor — Registrovaný používateľ, ktorý na Portáli zverejňuje podujatia, prípadne cez Portál ponúka vstupenky.',
          '2.5 Návštevník — Používateľ, ktorý si cez Portál rezervuje alebo kúpi vstupenku, prípadne sa prihlási na podujatie.',
          '2.6 Obsah — texty, fotografie, plagáty, odkazy a ďalšie údaje, ktoré na Portál vloží Používateľ.',
          '2.7 Zmluva — zmluva o poskytovaní služieb Portálu medzi Prevádzkovateľom a Registrovaným používateľom, ktorej obsahom sú tieto podmienky.',
        ],
      },
      {
        heading: 'Čl. 3 — Registrácia a používateľský účet',
        paragraphs: [
          '3.1 Registrácia je bezplatná a dobrovoľná. Prehliadanie zverejnených podujatí je možné aj bez nej.',
          '3.2 Účet si môže vytvoriť osoba staršia ako 16 rokov. Za mladšiu osobu môže konať iba jej zákonný zástupca.',
          '3.3 Registráciu Používateľ dokončí vyplnením formulára a zaškrtnutím súhlasu s týmito podmienkami. Zmluva vzniká overením e-mailovej adresy, prípadne prvým prihlásením cez účet Google alebo Facebook.',
          '3.4 Používateľ uvádza pravdivé a aktuálne údaje a zodpovedá za utajenie prístupových údajov k účtu. Podozrenie na zneužitie účtu bezodkladne oznámi na {email}.',
          '3.5 Účet je neprenosný. Používateľ zodpovedá za konanie, ku ktorému došlo prostredníctvom jeho účtu.',
          '3.6 Ak sa Používateľ registruje ako Organizátor v mene právnickej osoby, vyhlasuje, že je oprávnený za ňu konať.',
        ],
      },
      {
        heading: 'Čl. 4 — Obsah zverejňovaný Používateľom',
        paragraphs: [
          '4.1 Za správnosť, zákonnosť a aktuálnosť Obsahu zodpovedá Používateľ, ktorý ho vložil. Prevádzkovateľ Obsah nevytvára a neručí za jeho úplnosť ani pravdivosť.',
          '4.2 Vložením Obsahu udeľuje Používateľ Prevádzkovateľovi bezodplatnú, nevýhradnú a územne neobmedzenú licenciu na jeho zobrazovanie, rozmnožovanie, úpravu veľkosti a šírenie v rozsahu potrebnom na prevádzku a propagáciu Portálu vrátane náhľadov vo vyhľadávačoch a na sociálnych sieťach, a to na čas zverejnenia Obsahu na Portáli.',
          '4.3 Používateľ vyhlasuje, že je oprávnený takúto licenciu udeliť a že Obsah neporušuje práva tretích osôb, najmä autorské práva, práva na ochranu osobnosti a práva k ochranným známkam.',
          '4.4 Zakázaný je Obsah, ktorý je v rozpore s právnym poriadkom Slovenskej republiky alebo s dobrými mravmi, najmä nenávistný, hanlivý, klamlivý, zásadne nesúvisiaci s podujatiami, propagujúci násilie alebo nevhodný pre maloletých.',
          '4.5 Prevádzkovateľ je oprávnený Obsah, ktorý porušuje tieto podmienky, nezverejniť, upraviť jeho zaradenie do kategórií alebo ho odstrániť. O odstránení Používateľa informuje; Používateľ môže proti tomu podať námietku na {email}.',
          '4.6 Prevádzkovateľ nie je povinný Obsah vopred kontrolovať. Na oznámenie o protiprávnom Obsahu doručené na {email} reaguje bez zbytočného odkladu.',
        ],
      },
      {
        heading: 'Čl. 5 — Vstupenky a platby',
        paragraphs: [
          '5.1 Ak Portál umožňuje rezerváciu alebo predaj vstupeniek, Prevádzkovateľ koná ako sprostredkovateľ v mene a na účet Organizátora. Zmluva o účasti na podujatí vzniká medzi Návštevníkom a Organizátorom, nie s Prevádzkovateľom.',
          '5.2 Cena vstupenky je uvedená vrátane dane z pridanej hodnoty. Prípadné servisné či transakčné poplatky sú uvedené pred záväzným odoslaním objednávky; celková suma na úhradu je Návštevníkovi zobrazená pred potvrdením objednávky.',
          '5.3 Vstupenka sa doručuje elektronicky na e-mailovú adresu uvedenú v objednávke a platí spolu s jedinečným kódom, ktorý sa pri vstupe kontroluje. Za ochranu vstupenky pred kopírovaním zodpovedá Návštevník; pri opakovanom použití toho istého kódu platí prvý uskutočnený vstup.',
          '5.4 Za konanie podujatia, jeho obsah, priebeh, zmenu termínu, miesta alebo zrušenie zodpovedá výlučne Organizátor. Vrátenie vstupného pri zrušenom alebo presunutom podujatí vybavuje Organizátor.',
          '5.5 Bezplatná rezervácia alebo prihlásenie na podujatie nezakladá nárok na vstup nad rámec kapacity oznámenej Organizátorom.',
        ],
      },
      {
        heading: 'Čl. 6 — Odstúpenie od zmluvy a reklamácie',
        paragraphs: [
          '6.1 Registrovaný používateľ, ktorý je spotrebiteľom, môže od Zmluvy o používaní Portálu kedykoľvek odstúpiť zrušením účtu, a to aj bez uvedenia dôvodu. Služba je bezplatná, preto s odstúpením nie sú spojené žiadne náklady.',
          '6.2 Pri vstupenkách na podujatie sa právo spotrebiteľa odstúpiť od zmluvy do 14 dní neuplatňuje. Ide o poskytnutie služby súvisiacej s činnosťami v rámci voľného času, pri ktorej sa poskytovateľ zaväzuje poskytnúť plnenie v dohodnutom čase — výnimka podľa § 19 zákona č. 108/2024 Z. z. o ochrane spotrebiteľa.',
          '6.3 Reklamáciu služieb Portálu možno uplatniť e-mailom na {email}. Prevádzkovateľ potvrdí jej prijatie, vybaví ju najneskôr do 30 dní od uplatnenia a o vybavení vydá písomný doklad.',
          '6.4 Reklamáciu samotného podujatia (jeho priebehu, kvality, zrušenia) uplatňuje Návštevník u Organizátora, ktorý je zmluvnou stranou.',
        ],
      },
      {
        heading: 'Čl. 7 — Prevádzka Portálu a zodpovednosť',
        paragraphs: [
          '7.1 Prevádzkovateľ vyvíja primerané úsilie o nepretržitú dostupnosť Portálu, negarantuje ju však. Portál môže byť na nevyhnutný čas nedostupný z dôvodu údržby, aktualizácie alebo výpadku na strane tretích osôb.',
          '7.2 Prevádzkovateľ je oprávnený meniť rozsah a podobu funkcií Portálu. Ak sa rozhodne prevádzku ukončiť, oznámi to Registrovaným používateľom najmenej 30 dní vopred.',
          '7.3 Prevádzkovateľ nezodpovedá za škodu spôsobenú nedostupnosťou Portálu, stratou vloženého Obsahu ani konaním tretích osôb, ak ju nespôsobil úmyselne alebo z hrubej nedbanlivosti. Tým nie je dotknutá zodpovednosť za škodu na zdraví a ďalšia zodpovednosť, ktorú nemožno vylúčiť.',
          '7.4 Používateľ nesmie Portál zaťažovať automatizovaným sťahovaním údajov nad bežnú mieru, obchádzať bezpečnostné prvky ani zasahovať do jeho technického riešenia.',
        ],
      },
      {
        heading: 'Čl. 8 — Trvanie a zánik zmluvy',
        paragraphs: [
          '8.1 Zmluva sa uzatvára na neurčitý čas.',
          '8.2 Používateľ môže účet kedykoľvek zrušiť v nastaveniach účtu alebo žiadosťou na {email}.',
          '8.3 Prevádzkovateľ môže účet obmedziť alebo zrušiť, ak Používateľ závažne alebo opakovane porušuje tieto podmienky, alebo ak je účet zjavne zneužívaný. Používateľa o tom informuje e-mailom spolu s dôvodom.',
          '8.4 Zrušením účtu Zmluva zaniká. Informácie o podujatiach, ktoré sa už uskutočnili, môžu zostať v archíve Portálu; osobné údaje sa spracúvajú ďalej len v rozsahu opísanom v zásadách ochrany osobných údajov.',
        ],
      },
      {
        heading: 'Čl. 9 — Ochrana osobných údajov',
        paragraphs: [
          '9.1 Osobné údaje spracúva Prevádzkovateľ v rozsahu a spôsobom opísaným v dokumente Ochrana osobných údajov, ktorý je informačnou súčasťou týchto podmienok.',
          '9.2 Spracúvanie údajov nevyhnutných na vedenie účtu a poskytovanie služieb Portálu sa zakladá na plnení tejto Zmluvy, nie na súhlase — nedá sa preto samostatne odvolať, kým účet trvá.',
        ],
      },
      {
        heading: 'Čl. 10 — Alternatívne riešenie sporov',
        paragraphs: [
          '10.1 Spotrebiteľ, ktorý nie je spokojný so spôsobom vybavenia reklamácie alebo sa domnieva, že Prevádzkovateľ porušil jeho práva, môže požiadať o nápravu na {email}.',
          '10.2 Ak Prevádzkovateľ na žiadosť odpovie zamietavo alebo na ňu neodpovie do 30 dní, spotrebiteľ má právo podať návrh na začatie alternatívneho riešenia sporu podľa zákona č. 391/2015 Z. z. o alternatívnom riešení spotrebiteľských sporov.',
          '10.3 Príslušným subjektom je Slovenská obchodná inšpekcia (Ústredný inšpektorát SOI, Bajkalská 21/A, 827 99 Bratislava, www.soi.sk) alebo iná oprávnená právnická osoba zapísaná v zozname subjektov alternatívneho riešenia sporov, ktorý vedie Ministerstvo hospodárstva SR. Spotrebiteľ si môže vybrať, na ktorý z nich sa obráti.',
          '10.4 Alternatívne riešenie sporu je pre spotrebiteľa bezplatné alebo spoplatnené sumou najviac 5 eur. Právo obrátiť sa na súd tým nie je dotknuté.',
        ],
      },
      {
        heading: 'Čl. 11 — Záverečné ustanovenia',
        paragraphs: [
          '11.1 Právne vzťahy neupravené týmito podmienkami sa spravujú právnym poriadkom Slovenskej republiky, najmä Občianskym zákonníkom, zákonom č. 108/2024 Z. z. o ochrane spotrebiteľa a zákonom č. 22/2004 Z. z. o elektronickom obchode.',
          '11.2 Prevádzkovateľ je oprávnený tieto podmienky meniť, najmä pri zmene právnych predpisov alebo rozsahu služieb. O zmene informuje Registrovaných používateľov e-mailom alebo oznamom na Portáli najmenej 15 dní pred účinnosťou nového znenia.',
          '11.3 Ak Používateľ so zmenou nesúhlasí, môže do dňa jej účinnosti zrušiť účet. Používaním Portálu po tomto dni zmenu prijíma.',
          '11.4 Ak sa niektoré ustanovenie týchto podmienok stane neplatným alebo neúčinným, ostatné ustanovenia zostávajú v platnosti.',
          '11.5 Tieto podmienky sú vyhotovené v slovenskom jazyku; znenia v iných jazykoch sú informatívnym prekladom a v prípade rozporu je rozhodujúce slovenské znenie.',
          '11.6 Toto znenie nadobúda účinnosť {effectiveFrom} a nahrádza všetky predchádzajúce znenia (verzia {version}).',
        ],
      },
    ],
  },

  privacy: {
    title: 'Ochrana osobných údajov',
    perex:
      'Tento dokument vysvetľuje, aké osobné údaje o vás portál {site} spracúva, na aký účel, ako dlho a aké máte práva. ' +
      'Postupujeme podľa nariadenia (EÚ) 2016/679 (GDPR) a zákona č. 18/2018 Z. z. o ochrane osobných údajov. ' +
      'Znenie je účinné od {effectiveFrom} (verzia {version}).',
    sections: [
      {
        heading: '1. Kto údaje spracúva',
        paragraphs: [
          'Prevádzkovateľom v zmysle GDPR je {name}, so sídlom {address}, IČO: {ico}, {registration}.',
          'Vo veciach ochrany osobných údajov nás kontaktujte na {email} alebo písomne na adrese sídla.',
        ],
      },
      {
        heading: '2. Aké údaje spracúvame',
        paragraphs: [
          'Registračné údaje — e-mailová adresa, meno alebo názov, heslo (uložené výhradne v podobe nečitateľného odtlačku) a spôsob registrácie (e-mail, Google, Facebook).',
          'Údaje o súhlase — dátum a verzia obchodných podmienok, s ktorými ste pri registrácii súhlasili.',
          'Profilové a fakturačné údaje organizátora — názov, kontaktné a fakturačné údaje, ak ich zadáte.',
          'Obsah, ktorý zverejníte — podujatia, miesta, fotografie, plagáty a texty vrátane kontaktov, ktoré v nich uvediete.',
          'Komunikácia — obsah správ odoslaných cez Portál a e-mailovej komunikácie s nami.',
          'Údaje o vstupenkách — objednané vstupenky, ich stav a záznam o vstupe na podujatie.',
          'Technické údaje — IP adresa, typ a jazyk prehliadača, dátum a čas prístupu, záznamy o prihlásení a o chybách aplikácie.',
        ],
      },
      {
        heading: '3. Na aký účel a na akom právnom základe',
        paragraphs: [
          'Vedenie používateľského účtu a poskytovanie funkcií Portálu — plnenie zmluvy podľa čl. 6 ods. 1 písm. b) GDPR. Poskytnutie týchto údajov je nevyhnutné; bez nich účet nevznikne.',
          'Zverejnenie vami vloženého obsahu vrátane jeho zobrazenia vo vyhľadávačoch — plnenie zmluvy podľa čl. 6 ods. 1 písm. b) GDPR.',
          'Sprostredkovanie vstupeniek, ich doručenie a kontrola pri vstupe — plnenie zmluvy podľa čl. 6 ods. 1 písm. b) GDPR; údaje potrebné na vstup sa odovzdávajú organizátorovi podujatia.',
          'Overenie e-mailovej adresy, zabezpečenie účtov a ochrana pred zneužitím a spamom — oprávnený záujem podľa čl. 6 ods. 1 písm. f) GDPR.',
          'Preukázanie udeleného súhlasu s obchodnými podmienkami — plnenie zákonnej povinnosti podľa čl. 6 ods. 1 písm. c) v spojení s čl. 7 ods. 1 GDPR.',
          'Vedenie účtovníctva a plnenie daňových povinností pri platených službách — zákonná povinnosť podľa čl. 6 ods. 1 písm. c) GDPR.',
          'Zasielanie noviniek a upozornení na podujatia, ak si ich vyžiadate — súhlas podľa čl. 6 ods. 1 písm. a) GDPR, ktorý môžete kedykoľvek odvolať bez vplyvu na zákonnosť spracúvania pred odvolaním.',
          'Uplatňovanie alebo obhajovanie právnych nárokov — oprávnený záujem podľa čl. 6 ods. 1 písm. f) GDPR.',
        ],
      },
      {
        heading: '4. Ako dlho údaje uchovávame',
        paragraphs: [
          'Údaje účtu — po dobu jeho trvania a následne najviac 1 rok od zrušenia účtu, kvôli prípadným nárokom z používania Portálu.',
          'Neoverená registrácia — najviac do uplynutia platnosti overovacieho odkazu (48 hodín), potom sa automaticky vymaže.',
          'Doklad o súhlase s podmienkami — po dobu trvania účtu a 1 rok po jeho zrušení, spolu s údajmi účtu.',
          'Zverejnený obsah o podujatiach — aj po zrušení účtu môže zostať v archíve Portálu, spravidla bez kontaktných údajov na fyzické osoby.',
          'Údaje o vstupenkách a účtovné doklady — 10 rokov, ak to vyžadujú daňové a účtovné predpisy.',
          'Technické záznamy — spravidla 12 mesiacov.',
        ],
      },
      {
        heading: '5. Komu údaje sprístupňujeme',
        paragraphs: [
          'Poskytovateľom technického zázemia — hosting a serverová infraštruktúra, odosielanie e-mailov, prípadne platobná brána a IT podpora. Títo príjemcovia konajú ako sprostredkovatelia na základe zmluvy podľa čl. 28 GDPR.',
          'Organizátorovi podujatia — v rozsahu potrebnom na rezerváciu, vstupenku a vstup na podujatie.',
          'Účtovníkovi a poradcom — v rozsahu potrebnom na plnenie zákonných povinností.',
          'Orgánom verejnej moci — iba ak nám to ukladá právny predpis.',
          'Osobné údaje nepredávame a neposkytujeme tretím stranám na ich vlastné marketingové účely.',
        ],
      },
      {
        heading: '6. Prenos do tretích krajín',
        paragraphs: [
          'Údaje spracúvame v rámci Európskej únie a Európskeho hospodárskeho priestoru.',
          'Ak by pri niektorej službe (napríklad pri prihlásení cez účet Google alebo Facebook) došlo k prenosu mimo EHP, deje sa tak na základe rozhodnutia Komisie o primeranosti alebo štandardných zmluvných doložiek schválených Európskou komisiou.',
        ],
      },
      {
        heading: '7. Vaše práva',
        paragraphs: [
          'Máte právo na prístup k svojim údajom a na kópiu spracúvaných údajov.',
          'Máte právo na opravu nesprávnych a doplnenie neúplných údajov.',
          'Máte právo na vymazanie údajov, ak už nie sú potrebné a nebráni tomu zákonná povinnosť ich uchovávať.',
          'Máte právo na obmedzenie spracúvania a právo na prenosnosť údajov, ktoré ste nám poskytli.',
          'Máte právo namietať proti spracúvaniu založenému na oprávnenom záujme a právo kedykoľvek odvolať súhlas tam, kde je právnym základom.',
          'Žiadosti posielajte na {email}. Odpovieme najneskôr do jedného mesiaca od doručenia žiadosti.',
          'Máte právo podať sťažnosť dozornému orgánu: Úrad na ochranu osobných údajov Slovenskej republiky, Hraničná 12, 820 07 Bratislava 27, www.dataprotection.gov.sk.',
        ],
      },
      {
        heading: '8. Automatizované rozhodovanie',
        paragraphs: [
          'Nevykonávame automatizované rozhodovanie ani profilovanie, ktoré by malo pre vás právne účinky alebo vás podobne významne ovplyvňovalo.',
        ],
      },
      {
        heading: '9. Súbory cookie a miestne úložisko',
        paragraphs: [
          'Portál používa nevyhnutné cookies a miestne úložisko prehliadača na prihlásenie, bezpečnosť a zapamätanie zvoleného jazyka. Bez nich by Portál nefungoval, preto sa na ne súhlas nevyžaduje.',
          'Ak nasadíme analytické alebo marketingové cookies, vyžiadame si na ne váš súhlas vopred a bude sa dať kedykoľvek odvolať.',
          'Uložené cookies môžete kedykoľvek vymazať v nastaveniach svojho prehliadača; niektoré funkcie Portálu potom nemusia fungovať.',
        ],
      },
      {
        heading: '10. Zmeny tohto dokumentu',
        paragraphs: [
          'Aktuálne znenie je vždy dostupné na tejto stránke. O podstatných zmenách informujeme registrovaných používateľov e-mailom alebo oznamom na Portáli.',
          'Toto znenie je účinné od {effectiveFrom} (verzia {version}). Rozhodujúce je slovenské znenie; ostatné jazykové verzie sú informatívnym prekladom.',
        ],
      },
    ],
  },
}

export default sk
