import type { LegalDocuments } from './types'

/** Překlad slovenského znění (sk.ts). Rozhodující je slovenská verze. */
const cs: LegalDocuments = {
  terms: {
    title: 'Obchodní podmínky',
    perex:
      'Tyto obchodní podmínky upravují práva a povinnosti při používání portálu {site}. ' +
      'Znění je účinné od {effectiveFrom} (verze {version}). Rozhodující je slovenské znění.',
    sections: [
      {
        heading: 'Čl. 1 — Provozovatel',
        paragraphs: [
          '1.1 Provozovatelem portálu {site} (dále jen „Portál“) je {name}, se sídlem {address}, IČO: {ico}, {dic}, {registration} (dále jen „Provozovatel“).',
          '1.2 Kontaktní údaje: e-mail {email}, telefon {phone}.',
          '1.3 Orgánem dozoru je Slovenská obchodní inspekce — {soi}.',
        ],
      },
      {
        heading: 'Čl. 2 — Vymezení pojmů',
        paragraphs: [
          '2.1 Portál — webová aplikace dostupná na adrese {site}, která zveřejňuje informace o kulturních, společenských, sportovních a jiných akcích a související služby.',
          '2.2 Uživatel — fyzická nebo právnická osoba, která Portál používá, i bez registrace.',
          '2.3 Registrovaný uživatel — Uživatel, který má na Portálu vytvořený účet.',
          '2.4 Pořadatel — Registrovaný uživatel, který na Portálu zveřejňuje akce, případně přes Portál nabízí vstupenky.',
          '2.5 Návštěvník — Uživatel, který si přes Portál rezervuje nebo koupí vstupenku, případně se na akci přihlásí.',
          '2.6 Obsah — texty, fotografie, plakáty, odkazy a další údaje, které na Portál vloží Uživatel.',
          '2.7 Smlouva — smlouva o poskytování služeb Portálu mezi Provozovatelem a Registrovaným uživatelem, jejímž obsahem jsou tyto podmínky.',
        ],
      },
      {
        heading: 'Čl. 3 — Registrace a uživatelský účet',
        paragraphs: [
          '3.1 Registrace je bezplatná a dobrovolná. Prohlížení zveřejněných akcí je možné i bez ní.',
          '3.2 Účet si může vytvořit osoba starší 16 let. Za mladší osobu může jednat pouze její zákonný zástupce.',
          '3.3 Registraci Uživatel dokončí vyplněním formuláře a zaškrtnutím souhlasu s těmito podmínkami. Smlouva vzniká ověřením e-mailové adresy, případně prvním přihlášením přes účet Google nebo Facebook.',
          '3.4 Uživatel uvádí pravdivé a aktuální údaje a odpovídá za utajení přístupových údajů k účtu. Podezření na zneužití účtu neprodleně oznámí na {email}.',
          '3.5 Účet je nepřenosný. Uživatel odpovídá za jednání, ke kterému došlo prostřednictvím jeho účtu.',
          '3.6 Registruje-li se Uživatel jako Pořadatel jménem právnické osoby, prohlašuje, že je oprávněn za ni jednat.',
        ],
      },
      {
        heading: 'Čl. 4 — Obsah zveřejňovaný Uživatelem',
        paragraphs: [
          '4.1 Za správnost, zákonnost a aktuálnost Obsahu odpovídá Uživatel, který jej vložil. Provozovatel Obsah nevytváří a neručí za jeho úplnost ani pravdivost.',
          '4.2 Vložením Obsahu uděluje Uživatel Provozovateli bezúplatnou, nevýhradní a územně neomezenou licenci k jeho zobrazování, rozmnožování, úpravě velikosti a šíření v rozsahu potřebném pro provoz a propagaci Portálu včetně náhledů ve vyhledávačích a na sociálních sítích, a to po dobu zveřejnění Obsahu na Portálu.',
          '4.3 Uživatel prohlašuje, že je oprávněn takovou licenci udělit a že Obsah neporušuje práva třetích osob, zejména autorská práva, práva na ochranu osobnosti a práva k ochranným známkám.',
          '4.4 Zakázán je Obsah, který je v rozporu s právním řádem Slovenské republiky nebo s dobrými mravy, zejména nenávistný, hanlivý, klamavý, zásadně nesouvisející s akcemi, propagující násilí nebo nevhodný pro nezletilé.',
          '4.5 Provozovatel je oprávněn Obsah porušující tyto podmínky nezveřejnit, upravit jeho zařazení do kategorií nebo jej odstranit. O odstranění Uživatele informuje; Uživatel může proti tomu podat námitku na {email}.',
          '4.6 Provozovatel není povinen Obsah předem kontrolovat. Na oznámení o protiprávním Obsahu doručené na {email} reaguje bez zbytečného odkladu.',
        ],
      },
      {
        heading: 'Čl. 5 — Vstupenky a platby',
        paragraphs: [
          '5.1 Umožňuje-li Portál rezervaci nebo prodej vstupenek, Provozovatel jedná jako zprostředkovatel jménem a na účet Pořadatele. Smlouva o účasti na akci vzniká mezi Návštěvníkem a Pořadatelem, nikoli s Provozovatelem.',
          '5.2 Cena vstupenky je uvedena včetně daně z přidané hodnoty. Případné servisní či transakční poplatky jsou uvedeny před závazným odesláním objednávky; celková částka k úhradě je Návštěvníkovi zobrazena před potvrzením objednávky.',
          '5.3 Vstupenka se doručuje elektronicky na e-mailovou adresu uvedenou v objednávce a platí spolu s jedinečným kódem, který se při vstupu kontroluje. Za ochranu vstupenky před kopírováním odpovídá Návštěvník; při opakovaném použití téhož kódu platí první uskutečněný vstup.',
          '5.4 Za konání akce, její obsah, průběh, změnu termínu, místa nebo zrušení odpovídá výhradně Pořadatel. Vrácení vstupného u zrušené nebo přesunuté akce vyřizuje Pořadatel.',
          '5.5 Bezplatná rezervace nebo přihlášení na akci nezakládá nárok na vstup nad rámec kapacity oznámené Pořadatelem.',
        ],
      },
      {
        heading: 'Čl. 6 — Odstoupení od smlouvy a reklamace',
        paragraphs: [
          '6.1 Registrovaný uživatel, který je spotřebitelem, může od Smlouvy o používání Portálu kdykoli odstoupit zrušením účtu, a to i bez uvedení důvodu. Služba je bezplatná, proto s odstoupením nejsou spojeny žádné náklady.',
          '6.2 U vstupenek na akci se právo spotřebitele odstoupit od smlouvy do 14 dnů neuplatňuje. Jde o poskytnutí služby související s činnostmi v rámci volného času, u které se poskytovatel zavazuje poskytnout plnění v dohodnutém čase — výjimka podle § 19 zákona č. 108/2024 Z. z. o ochraně spotřebitele.',
          '6.3 Reklamaci služeb Portálu lze uplatnit e-mailem na {email}. Provozovatel potvrdí její přijetí, vyřídí ji nejpozději do 30 dnů od uplatnění a o vyřízení vydá písemný doklad.',
          '6.4 Reklamaci samotné akce (jejího průběhu, kvality, zrušení) uplatňuje Návštěvník u Pořadatele, který je smluvní stranou.',
        ],
      },
      {
        heading: 'Čl. 7 — Provoz Portálu a odpovědnost',
        paragraphs: [
          '7.1 Provozovatel vyvíjí přiměřené úsilí o nepřetržitou dostupnost Portálu, negarantuje ji však. Portál může být na nezbytnou dobu nedostupný z důvodu údržby, aktualizace nebo výpadku na straně třetích osob.',
          '7.2 Provozovatel je oprávněn měnit rozsah a podobu funkcí Portálu. Rozhodne-li se provoz ukončit, oznámí to Registrovaným uživatelům nejméně 30 dnů předem.',
          '7.3 Provozovatel neodpovídá za škodu způsobenou nedostupností Portálu, ztrátou vloženého Obsahu ani jednáním třetích osob, pokud ji nezpůsobil úmyslně nebo z hrubé nedbalosti. Tím není dotčena odpovědnost za škodu na zdraví a další odpovědnost, kterou nelze vyloučit.',
          '7.4 Uživatel nesmí Portál zatěžovat automatizovaným stahováním údajů nad běžnou míru, obcházet bezpečnostní prvky ani zasahovat do jeho technického řešení.',
        ],
      },
      {
        heading: 'Čl. 8 — Trvání a zánik smlouvy',
        paragraphs: [
          '8.1 Smlouva se uzavírá na dobu neurčitou.',
          '8.2 Uživatel může účet kdykoli zrušit v nastavení účtu nebo žádostí na {email}.',
          '8.3 Provozovatel může účet omezit nebo zrušit, pokud Uživatel závažně nebo opakovaně porušuje tyto podmínky, nebo je-li účet zjevně zneužíván. Uživatele o tom informuje e-mailem spolu s důvodem.',
          '8.4 Zrušením účtu Smlouva zaniká. Informace o akcích, které se již uskutečnily, mohou zůstat v archivu Portálu; osobní údaje se dále zpracovávají pouze v rozsahu popsaném v zásadách ochrany osobních údajů.',
        ],
      },
      {
        heading: 'Čl. 9 — Ochrana osobních údajů',
        paragraphs: [
          '9.1 Osobní údaje zpracovává Provozovatel v rozsahu a způsobem popsaným v dokumentu Ochrana osobních údajů, který je informační součástí těchto podmínek.',
          '9.2 Zpracování údajů nezbytných pro vedení účtu a poskytování služeb Portálu se zakládá na plnění této Smlouvy, nikoli na souhlasu — nelze je proto samostatně odvolat, dokud účet trvá.',
        ],
      },
      {
        heading: 'Čl. 10 — Alternativní řešení sporů',
        paragraphs: [
          '10.1 Spotřebitel, který není spokojen se způsobem vyřízení reklamace nebo se domnívá, že Provozovatel porušil jeho práva, může požádat o nápravu na {email}.',
          '10.2 Odpoví-li Provozovatel na žádost zamítavě nebo neodpoví-li do 30 dnů, má spotřebitel právo podat návrh na zahájení alternativního řešení sporu podle zákona č. 391/2015 Z. z. o alternativním řešení spotřebitelských sporů.',
          '10.3 Příslušným subjektem je Slovenská obchodní inspekce (Ústredný inšpektorát SOI, Bajkalská 21/A, 827 99 Bratislava, www.soi.sk) nebo jiná oprávněná právnická osoba zapsaná v seznamu subjektů alternativního řešení sporů, který vede Ministerstvo hospodářství SR. Spotřebitel si může vybrat, na který z nich se obrátí.',
          '10.4 Alternativní řešení sporu je pro spotřebitele bezplatné nebo zpoplatněné částkou nejvýše 5 eur. Právo obrátit se na soud tím není dotčeno.',
        ],
      },
      {
        heading: 'Čl. 11 — Závěrečná ustanovení',
        paragraphs: [
          '11.1 Právní vztahy neupravené těmito podmínkami se řídí právním řádem Slovenské republiky, zejména Občanským zákoníkem, zákonem č. 108/2024 Z. z. o ochraně spotřebitele a zákonem č. 22/2004 Z. z. o elektronickém obchodu.',
          '11.2 Provozovatel je oprávněn tyto podmínky měnit, zejména při změně právních předpisů nebo rozsahu služeb. O změně informuje Registrované uživatele e-mailem nebo oznámením na Portálu nejméně 15 dnů před účinností nového znění.',
          '11.3 Nesouhlasí-li Uživatel se změnou, může do dne její účinnosti zrušit účet. Používáním Portálu po tomto dni změnu přijímá.',
          '11.4 Stane-li se některé ustanovení těchto podmínek neplatným nebo neúčinným, ostatní ustanovení zůstávají v platnosti.',
          '11.5 Tyto podmínky jsou vyhotoveny ve slovenském jazyce; znění v jiných jazycích jsou informativním překladem a v případě rozporu je rozhodující slovenské znění.',
          '11.6 Toto znění nabývá účinnosti {effectiveFrom} a nahrazuje všechna předchozí znění (verze {version}).',
        ],
      },
    ],
  },

  privacy: {
    title: 'Ochrana osobních údajů',
    perex:
      'Tento dokument vysvětluje, jaké osobní údaje o vás portál {site} zpracovává, za jakým účelem, jak dlouho a jaká máte práva. ' +
      'Postupujeme podle nařízení (EU) 2016/679 (GDPR) a zákona č. 18/2018 Z. z. o ochraně osobních údajů. ' +
      'Znění je účinné od {effectiveFrom} (verze {version}).',
    sections: [
      {
        heading: '1. Kdo údaje zpracovává',
        paragraphs: [
          'Správcem ve smyslu GDPR je {name}, se sídlem {address}, IČO: {ico}, {registration}.',
          'Ve věcech ochrany osobních údajů nás kontaktujte na {email} nebo písemně na adrese sídla.',
        ],
      },
      {
        heading: '2. Jaké údaje zpracováváme',
        paragraphs: [
          'Registrační údaje — e-mailová adresa, jméno nebo název, heslo (uložené výhradně v podobě nečitelného otisku) a způsob registrace (e-mail, Google, Facebook).',
          'Údaje o souhlasu — datum a verze obchodních podmínek, se kterými jste při registraci souhlasili.',
          'Profilové a fakturační údaje pořadatele — název, kontaktní a fakturační údaje, pokud je zadáte.',
          'Obsah, který zveřejníte — akce, místa, fotografie, plakáty a texty včetně kontaktů, které v nich uvedete.',
          'Komunikace — obsah zpráv odeslaných přes Portál a e-mailové komunikace s námi.',
          'Údaje o vstupenkách — objednané vstupenky, jejich stav a záznam o vstupu na akci.',
          'Technické údaje — IP adresa, typ a jazyk prohlížeče, datum a čas přístupu, záznamy o přihlášení a o chybách aplikace.',
        ],
      },
      {
        heading: '3. Za jakým účelem a na jakém právním základě',
        paragraphs: [
          'Vedení uživatelského účtu a poskytování funkcí Portálu — plnění smlouvy podle čl. 6 odst. 1 písm. b) GDPR. Poskytnutí těchto údajů je nezbytné; bez nich účet nevznikne.',
          'Zveřejnění vámi vloženého obsahu včetně jeho zobrazení ve vyhledávačích — plnění smlouvy podle čl. 6 odst. 1 písm. b) GDPR.',
          'Zprostředkování vstupenek, jejich doručení a kontrola při vstupu — plnění smlouvy podle čl. 6 odst. 1 písm. b) GDPR; údaje potřebné ke vstupu se předávají pořadateli akce.',
          'Ověření e-mailové adresy, zabezpečení účtů a ochrana před zneužitím a spamem — oprávněný zájem podle čl. 6 odst. 1 písm. f) GDPR.',
          'Prokázání uděleného souhlasu s obchodními podmínkami — plnění právní povinnosti podle čl. 6 odst. 1 písm. c) ve spojení s čl. 7 odst. 1 GDPR.',
          'Vedení účetnictví a plnění daňových povinností u placených služeb — právní povinnost podle čl. 6 odst. 1 písm. c) GDPR.',
          'Zasílání novinek a upozornění na akce, pokud si je vyžádáte — souhlas podle čl. 6 odst. 1 písm. a) GDPR, který můžete kdykoli odvolat bez vlivu na zákonnost zpracování před odvoláním.',
          'Uplatňování nebo obhajoba právních nároků — oprávněný zájem podle čl. 6 odst. 1 písm. f) GDPR.',
        ],
      },
      {
        heading: '4. Jak dlouho údaje uchováváme',
        paragraphs: [
          'Údaje účtu — po dobu jeho trvání a následně nejvýše 1 rok od zrušení účtu, kvůli případným nárokům z používání Portálu.',
          'Neověřená registrace — nejdéle do uplynutí platnosti ověřovacího odkazu (48 hodin), poté se automaticky vymaže.',
          'Doklad o souhlasu s podmínkami — po dobu trvání účtu a 1 rok po jeho zrušení, spolu s údaji účtu.',
          'Zveřejněný obsah o akcích — i po zrušení účtu může zůstat v archivu Portálu, zpravidla bez kontaktních údajů na fyzické osoby.',
          'Údaje o vstupenkách a účetní doklady — 10 let, vyžadují-li to daňové a účetní předpisy.',
          'Technické záznamy — zpravidla 12 měsíců.',
        ],
      },
      {
        heading: '5. Komu údaje zpřístupňujeme',
        paragraphs: [
          'Poskytovatelům technického zázemí — hosting a serverová infrastruktura, odesílání e-mailů, případně platební brána a IT podpora. Tito příjemci jednají jako zpracovatelé na základě smlouvy podle čl. 28 GDPR.',
          'Pořadateli akce — v rozsahu potřebném pro rezervaci, vstupenku a vstup na akci.',
          'Účetnímu a poradcům — v rozsahu potřebném pro plnění zákonných povinností.',
          'Orgánům veřejné moci — pouze pokud nám to ukládá právní předpis.',
          'Osobní údaje neprodáváme a neposkytujeme třetím stranám pro jejich vlastní marketingové účely.',
        ],
      },
      {
        heading: '6. Předávání do třetích zemí',
        paragraphs: [
          'Údaje zpracováváme v rámci Evropské unie a Evropského hospodářského prostoru.',
          'Pokud by u některé služby (například při přihlášení přes účet Google nebo Facebook) došlo k předání mimo EHP, děje se tak na základě rozhodnutí Komise o odpovídající ochraně nebo standardních smluvních doložek schválených Evropskou komisí.',
        ],
      },
      {
        heading: '7. Vaše práva',
        paragraphs: [
          'Máte právo na přístup ke svým údajům a na kopii zpracovávaných údajů.',
          'Máte právo na opravu nesprávných a doplnění neúplných údajů.',
          'Máte právo na výmaz údajů, pokud již nejsou potřebné a nebrání tomu zákonná povinnost je uchovávat.',
          'Máte právo na omezení zpracování a právo na přenositelnost údajů, které jste nám poskytli.',
          'Máte právo vznést námitku proti zpracování založenému na oprávněném zájmu a právo kdykoli odvolat souhlas tam, kde je právním základem.',
          'Žádosti posílejte na {email}. Odpovíme nejpozději do jednoho měsíce od doručení žádosti.',
          'Máte právo podat stížnost dozorovému úřadu: Úrad na ochranu osobných údajov Slovenskej republiky, Hraničná 12, 820 07 Bratislava 27, www.dataprotection.gov.sk.',
        ],
      },
      {
        heading: '8. Automatizované rozhodování',
        paragraphs: [
          'Neprovádíme automatizované rozhodování ani profilování, které by pro vás mělo právní účinky nebo vás podobně významně ovlivňovalo.',
        ],
      },
      {
        heading: '9. Soubory cookie a místní úložiště',
        paragraphs: [
          'Portál používá nezbytné cookies a místní úložiště prohlížeče pro přihlášení, bezpečnost a zapamatování zvoleného jazyka. Bez nich by Portál nefungoval, proto se na ně souhlas nevyžaduje.',
          'Nasadíme-li analytické nebo marketingové cookies, vyžádáme si na ně váš souhlas předem a půjde jej kdykoli odvolat.',
          'Uložené cookies můžete kdykoli smazat v nastavení svého prohlížeče; některé funkce Portálu pak nemusí fungovat.',
        ],
      },
      {
        heading: '10. Změny tohoto dokumentu',
        paragraphs: [
          'Aktuální znění je vždy dostupné na této stránce. O podstatných změnách informujeme registrované uživatele e-mailem nebo oznámením na Portálu.',
          'Toto znění je účinné od {effectiveFrom} (verze {version}). Rozhodující je slovenské znění; ostatní jazykové verze jsou informativním překladem.',
        ],
      },
    ],
  },
}

export default cs
