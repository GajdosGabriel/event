<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Rozdelí importované kanály, ktoré majú v názve viac subjektov.
 *
 * Importér (ImportedCanalNameResolver) zobral z textu pozvánky celý zoznam
 * spoluorganizátorov a založil z neho jeden kanál — napr. id 1532
 * „Múzeum obetí komunizmu v Košiciach, Ústav pamäti národa (ÚPN), OZ
 * samizdat.sk a Dom Quo Vadis". Taký kanál sa zle hľadá a vydáva partnerov za
 * jedného organizátora. Zoznam nižšie vznikol prehliadkou všetkých kanálov
 * s viac-subjektovým názvom: pri každom je vybraný hlavný organizátor
 * (prvý uvedený, pri generických začiatkoch — „Rímskokatolícka cirkev" —
 * konkrétnejší subjekt) a kanál sa na neho premenuje. Samotné názvy so spojkou
 * „a", ktoré sú jedným subjektom („Rada pre mládež a univerzity KBS"), sa
 * nedotýkajú.
 *
 * Pravidlá:
 *  - upraví sa len kanál, ktorý je stále importovaný, neprevzatý, nezmazaný
 *    a jeho názov sa zhoduje s pôvodným (čo medzitým niekto upravil ručne,
 *    ostane nedotknuté),
 *  - ak už existuje iný živý kanál s rovnakým slugom, podujatia sa presunú
 *    doň a tento kanál sa soft-deletne (rovnaký postup ako
 *    2026_09_22_230000_merge_duplicate_organization_canals.php); zlúči sa
 *    len ak naň nič iné neodkazuje (nároky, pozvánky, oslovenia, tikety),
 *    inak sa len premenuje,
 *  - popis (`body`) písaný AI popisoval všetky subjekty naraz, preto sa
 *    nahradí neutrálnou vetou (jednosmerne, ako pri `body` v starších
 *    migráciách).
 *
 * down() vráti pôvodný názov a slug premenovaným kanálom a obnoví soft-deletnuté;
 * presun podujatí a pôvodný popis sa nevracia.
 */
return new class extends Migration
{
    /**
     * id kanála => [pôvodný názov, nový názov].
     *
     * @var array<int, array{0:string, 1:string}>
     */
    private const RENAMES = [
        20 => ['OZ Rodinné spoločenstvo ECAV na Slovensku a Východný dištrikt ECAV na Slovensku', 'OZ Rodinné spoločenstvo ECAV na Slovensku'],
        41 => ['Rada KBS pre Rómov a menšiny, Centrum rómskej misie Košickej arcidiecézy, Farský úrad Gaboltov', 'Rada KBS pre Rómov a menšiny'],
        100 => ['Evanjelická cirkev augsburského vyznania na Slovensku, Cirkevný zbor Evanjelickej cirkvi a. v. na Slovensku Liptovský Mi', 'Evanjelická cirkev augsburského vyznania na Slovensku'],
        107 => ['Saleziáni, Farnosť Vajnory, spoločenstvo Tymian a Domka', 'Saleziáni'],
        112 => ['Rímskokatolícka farnosť Štiavnické Bane a Pukanec', 'Rímskokatolícka farnosť Štiavnické Bane'],
        135 => ['M-Aréna a spoločenstvo MaranaTha', 'M-Aréna'],
        141 => ['Diecézne pastoračné centrum pre rodinu Banskobystrickej diecézy a o.z. Dobré dielo', 'Diecézne pastoračné centrum pre rodinu Banskobystrickej diecézy'],
        145 => ['OOCR Turiec, OOCR Malá Fatra, Rada pre kultúrnu cestu sv. Martina na Slovensku', 'OOCR Turiec'],
        173 => ['Pamätník Terezín, Ústav pre štúdium totalitných režimov, Arcibiskupstvo pražské', 'Pamätník Terezín'],
        178 => ['Enoia a Kríza identity muža a ženy', 'Enoia'],
        219 => ['Anton Chromík a Marek Nikolov', 'Anton Chromík'],
        257 => ['Hudobná subkomisia Liturgickej komisie Konferencie biskupov Slovenska a Spolok svätého Vojtecha', 'Hudobná subkomisia Liturgickej komisie Konferencie biskupov Slovenska'],
        314 => ['Rehoľa svätého Augustína na Slovensku a farnosť sv. Rity', 'Rehoľa svätého Augustína na Slovensku'],
        326 => ['Trenčiansky samosprávny kraj, Spolok Srbov na Slovensku, Nadácia PRO PATRIA, občianske združenie CYRILOMETODIADA', 'Trenčiansky samosprávny kraj'],
        329 => ['Spolok svätého Vojtecha a Vydavateľstvo Nové mesto', 'Spolok svätého Vojtecha'],
        331 => ['Rád bosých karmelitánov (OCD) a Svetský rád bosých karmelitánov (OCDS) v Bratislave', 'Rád bosých karmelitánov'],
        347 => ['Nitrianske biskupstvo a Kňazský seminár sv. Gorazda', 'Nitrianske biskupstvo'],
        357 => ['Pavlíni, saleziáni a spoločenstvo Tymian', 'Pavlíni'],
        359 => ['iniciatíva Františkova ekonomika a Nadační fond Teo via', 'iniciatíva Františkova ekonomika'],
        360 => ['Saleziáni, Domka a spoločenstvo Tymian', 'Saleziáni'],
        392 => ['iniciatíva Františkova ekonomika a Nadačný fond Teo', 'iniciatíva Františkova ekonomika'],
        396 => ['Miestny odbor Matice slovenskej v Trenčíne, Nadácia PRO PATRIA a občianske združenie CYRILOMETODIADA', 'Miestny odbor Matice slovenskej v Trenčíne'],
        405 => ['Jezuiti a spoločenstvá Magis a CVX', 'Jezuiti'],
        419 => ['Konfederácia politických väzňov Slovenska a Misijná spoločnosť sv. Vincenta de Paul', 'Konfederácia politických väzňov Slovenska'],
        426 => ['iniciatíva Františkova ekonomika a Nadačný fond Teo via', 'iniciatíva Františkova ekonomika'],
        448 => ['Saleziáni don Bosca a Saleziánsky pastoračný tím', 'Saleziáni don Bosca'],
        465 => ['Apoštolská nunciatúra na Slovensku, Ústav kánonického práva Právnickej fakulty UK v Bratislave, Konferencia biskupov Slo', 'Apoštolská nunciatúra na Slovensku'],
        466 => ['spoločenstva Nádej a portálu SingleKatolici.sk', 'Nádej - spoločenstvo slobodných'],
        474 => ['Centrum pre štúdium biblického a blízkovýchodného sveta spolu s Rímskokatolíckou farnosťou sv. Alžbety', 'Centrum pre štúdium biblického a blízkovýchodného sveta'],
        479 => ['COMECE, Nadácia Centesimus Annus Pro-Pontefice, Luxembourg School of Religion & Society (LSRS)', 'COMECE'],
        480 => ['Spolok svätého Vojtecha a Dom Quo Vadis', 'Spolok svätého Vojtecha'],
        493 => ['Pápežské misijné diela a Farnosť Skačany', 'Pápežské misijné diela'],
        495 => ['Misijná spoločnosť sv. Vincenta de Paul, Farnosť Bratislava-Prievoz', 'Misijná spoločnosť sv. Vincenta de Paul'],
        499 => ['Misijná spoločnosť sv. Vincenta de Paul a Farnosť Bratislava-Prievoz', 'Misijná spoločnosť sv. Vincenta de Paul'],
        511 => ['Konferencia biskupov Slovenska, Slovenská katolícka charita, Teologická fakulta Trnavskej univerzity, Vysoká škola zdrav', 'Konferencia biskupov Slovenska'],
        543 => ['Konferencia biskupov Slovenska a Ekumenická rada cirkví na Slovensku', 'Konferencia biskupov Slovenska'],
        555 => ['Spoločnosť Ježišova, Slovenská provincia', 'Spoločnosť Ježišova'],
        559 => ['Slovenská komora sestier a pôrodných asistentiek, Regionálna komora sestier a pôrodných asistentiek Prešovského kraja, A', 'Slovenská komora sestier a pôrodných asistentiek'],
        571 => ['CYRILOMEODIADA, o. z., a Nadácia PRO PATRIA', 'CYRILOMETODIADA, o. z.'],
        574 => ['Hnutie Svetlo-Život a Farnosť sv. Alžbety', 'Hnutie Svetlo-Život'],
        575 => ['Cleopas, o. z. a Rímskokatolícka cyrilometodská bohoslovecká fakulta UK', 'Cleopas, o. z.'],
        580 => ['Cleopas, o. z., Badín', 'Cleopas, o. z.'],
        582 => ['Rada pre vedu, vzdelanie a kultúru KBS', 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru'],
        586 => ['Rada pre mládež a univerzity Konferencie biskupov Slovenska a eRko – Hnutie kresťanských spoločenstiev detí', 'Rada pre mládež a univerzity Konferencie biskupov Slovenska'],
        592 => ['Rímskokatolícka cirkev, Farnosť Veľké Bielice', 'Rímskokatolícka farnosť Veľké Bielice'],
        611 => ['Taliansky kultúrny inštitút a bratia kapucíni', 'Taliansky kultúrny inštitút'],
        627 => ['bratia Augustiniáni a farnosť sv. Rity', 'Bratia Augustiniáni'],
        637 => ['Mesto Trnava, Bachova spoločnosť na Slovensku, Region Trnava', 'Mesto Trnava'],
        653 => ['Úrad pre apoštolát ochrany života v Košiciach a Plodar, o. z', 'Úrad pre apoštolát ochrany života v Košiciach'],
        663 => ['OZ Za krajšiu Radavu, Rímskokatolícky farský úrad Radava, Obec Radava, CYRILOMETODIADA, o. z., a Nadácia PRO PATRIA', 'OZ Za krajšiu Radavu'],
        672 => ['Dobrá kniha a Spoločnosť Ježišova', 'Dobrá kniha'],
        676 => ['Misionári verbisti a Misijné sestry služobnice Ducha Svätého', 'Misionári verbisti'],
        680 => ['Konfederácia politických väzňov Slovenska a Spoločnosť Ježišova', 'Konfederácia politických väzňov Slovenska'],
        698 => ['Občianske združenie Bratislavská Kalvária a Kresťanské združenie Sprevádzajúci', 'Občianske združenie Bratislavská Kalvária'],
        709 => ['Spišská diecéza a Mesto Ružomberok', 'Spišská diecéza'],
        712 => ['Slovenská a česká dominikánska provincia, Tomistický inštitút Slovensko a Vydavateľstvo Krystal OP', 'Slovenská a česká dominikánska provincia'],
        717 => ['Banskobystrické biskupstvo, Diecézne pastoračné centrum pre rodinu Banskobystrickej diecézy a Farnosť Staré Hory', 'Banskobystrické biskupstvo'],
        719 => ['Strapar, Aslanov stôl, Godzone projekt, ZKSM, YES, WE CAN platforma', 'Strapar'],
        734 => ['Nitrianske biskupstvo a farnosti mesta Nitra', 'Nitrianske biskupstvo'],
        745 => ['Castellum, n. o. a Nitrianske biskupstvo', 'Castellum, n. o.'],
        755 => ['Nadácia PRO PATRIA, CYRILOMETODIADA, o. z', 'Nadácia PRO PATRIA'],
        772 => ['Spoločnosť Božieho Slova a Dom Quo Vadis', 'Spoločnosť Božieho Slova'],
        781 => ['Saleziáni a Tymian', 'Saleziáni'],
        789 => ['Bratislavská arcidiecézna charita a Slovenská katolícka charita', 'Bratislavská arcidiecézna charita'],
        796 => ['Rada KBS pre vedu vzdelanie a kultúru', 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru'],
        798 => ['Spolok Slovákov v Poľsku, CYRILOMETODIADA, o. z., Nadácia PRO PATRIA', 'Spolok Slovákov v Poľsku'],
        801 => ['Ruský dom v Bratislave, Nadácia PRO PATRIA a CYRILOMETODIADA, o. z', 'Ruský dom v Bratislave'],
        809 => ['Slavistický ústav Jána Stanislava SAV, v.v.i. a Centrum pre štúdium biblického a blízkovýchodného sveta', 'Slavistický ústav Jána Stanislava SAV, v.v.i.'],
        845 => ['Saleziáni dona Bosca a Konfederácia politických väzňov Slovenska', 'Saleziáni don Bosca'],
        861 => ['Tomistický inštitút Slovensko, Rehoľa dominikánov a ADOM – dominikánska mládež', 'Tomistický inštitút Slovensko'],
        873 => ['Familiaris občianske združenie, Mesto Svit a Farnosť sv. Jozefa robotníka', 'Familiaris občianske združenie'],
        897 => ['Bratislavská Kalvária a Sprevádzajúci', 'Občianske združenie Bratislavská Kalvária'],
        901 => ['Matica slovenská, Katedra histórie a didaktiky dejepisu Pedagogickej fakulty UK, Spoločnosť Andreja Hlinku a Cyrilometod', 'Matica slovenská'],
        910 => ['Katolícka cirkev a Ústredný zväz židovských náboženských obcí na Slovensku', 'Ústredný zväz židovských náboženských obcí na Slovensku'],
        911 => ['Rímskokatolícka cirkev farnosť Kolačkov a farnosť Chmeľnica', 'Rímskokatolícka farnosť Kolačkov'],
        913 => ['Bratislavská Kalvária, Kresťanské združenie Sprevádzajúci', 'Občianske združenie Bratislavská Kalvária'],
        918 => ['Rodinkovo, Miesto prijatia pre rodiny', 'Rodinkovo'],
        933 => ['eRko – HKSD, ZKSM, ZMM, Modlitby matiek', 'eRko – Hnutie kresťanských spoločenstiev detí'],
        935 => ['eRko, ZKSM, ZMM, Modlitby matiek, Spišské biskupstvo', 'eRko – Hnutie kresťanských spoločenstiev detí'],
        936 => ['Hnutie Svetlo-Život a Kruciaty oslobodenia človeka', 'Hnutie Svetlo-Život'],
        949 => ['Slovenský dohovor za rodinu a Rytieri Nepoškvrnenej', 'Slovenský dohovor za rodinu,o.z'],
        958 => ['Košická arcidiecéza, Farnosť sv. Alžbety v Košiciach, Komisia pre cirkevnú hudbu Košickej arcidiecézy', 'Košická arcidiecéza'],
        973 => ['Farnosť Bratislava - Prievoz v Ružinove a katechisti neokatechumenátnej cesty', 'Rímskokatolícka farnosť Bratislava – Prievoz'],
        977 => ['Ústav pamäti národa a Kongregácia Milosdných sestier sv. Vincenta – Satmárok', 'Ústav pamäti národa'],
        984 => ['Univerzita sv. Cyrila a Metoda, Trnavská univerzita, OZ Lifestarter', 'Univerzita sv. Cyrila a Metoda'],
        993 => ['Pavlíni a spoločenstvo Tymian', 'Pavlíni'],
        1006 => ['Rada pre vedu, vzdelanie a kultúru pri Konferencii biskupov Slovenska', 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru'],
        1008 => ['arcidiecézy Viedeň a Salzburg, diecézy Linz, Korutánsko, Feldkirch a Innsbruck, diecéza Chur, Švajčiarska biskupská konf', 'arcidiecézy Viedeň a Salzburg'],
        1011 => ['Fórum života, o.z. v spolupráci s Áno pre život, n.o', 'Fórum života, o.z.'],
        1017 => ['farnosť Prešov – Nižná Šebastová, n. o. Oáza – nádej pre nový život, Park kultúry a oddychu v Prešove, mesto Prešov', 'Farnosť Prešov – Nižná Šebastová'],
        1021 => ['Bratia Dominikáni - Farnosť Bratislava Kalvária, Komunita redemptoristov v Bratislave a KZ Sprevádzajúci – pútnické brat', 'Bratia Dominikáni – farnosť Bratislava Kalvária'],
        1022 => ['Biskupská rada Latinskej Ameriky a Karibiku (CELAM), Konfederácia reholí Latinskej Ameriky a Karibiku (CLAR), Caritas La', 'Biskupská rada Latinskej Ameriky a Karibiku (CELAM)'],
        1027 => ['Vikariát Ordinariátu Ozbrojených síl a ozbrojených zborov SR a Spišské biskupstvo', 'Vikariát Ordinariátu Ozbrojených síl a Ozbrojených zborov SR'],
        1031 => ['Bratia Dominikáni – Farnosť Bratislavská Kalvária, OZ Bratislavská Kalvária', 'Bratia Dominikáni – farnosť Bratislava Kalvária'],
        1051 => ['CYRILOMETODIADA, o. z., nadácia PRO PATRIA a Spolok Srbov na Slovensku', 'CYRILOMETODIADA, o. z.'],
        1052 => ['Vydavateľstvo Tranoscius, a. s. a Múzeum Slovenského národného povstania', 'Vydavateľstvo Tranoscius, a. s.'],
        1058 => ['o. z. Via regum a Dobrá novina', 'o. z. Via regum'],
        1060 => ['Rada pre vedu, vzdelanie kultúru KBS', 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru'],
        1065 => ['Televízia LUX a Slovenská Katolícka charita', 'Televízia LUX'],
        1067 => ['Národné koordinačné stredisko pre riešenie problematiky násilia na deťach, Ústredie práce, sociálnych vecí a rodiny, Kat', 'Národné koordinačné stredisko pre riešenie problematiky násilia na deťach'],
        1073 => ['farnosť a mesto Rajecké Teplice', 'Farnosť Rajecké Teplice'],
        1085 => ['Spišská diecéza, Kňazský seminár biskupa Jána Vojtaššáka a Klub priateľov Ferka Skyčáka', 'Spišská diecéza'],
        1086 => ['Rada KBS pre históriu, Rímskokatolícka cyrilometodská bohoslovecká fakulta UK v Bratislave, Kňazský seminár sv. Gorazda', 'Rada KBS pre históriu'],
        1098 => ['Banskobystrický samosprávny kraj a Košický samosprávny kraj', 'Banskobystrický samosprávny kraj'],
        1109 => ['Farnosť Levice-Rybníky a Centrum Femina', 'Farnosť Levice-Rybníky'],
        1112 => ['Pro Patria, CYRILOMETODIADA, o. z., a vydavateľstvo Perfekt', 'Nadácia PRO PATRIA'],
        1115 => ['CYRILOMETODIADA, o. z., nadácia PRO PATRIA a rodina Gustáva Beláčka', 'CYRILOMETODIADA, o. z.'],
        1124 => ['františkánska rodina na Slovensku, KU v Ružomberku a Ústav pamäti národa', 'Františkánska rodina na Slovensku'],
        1154 => ['Biskupstvo Nitra, Seminár sv. Gorazda, CYRILOMETODIADA, o. z., Nadácia PRO PATRIA, Castellum, n. o', 'Nitrianske biskupstvo'],
        1159 => ['eRko — Hnutie kresťanských spoločenstiev detí a organizácia Laura', 'eRko – Hnutie kresťanských spoločenstiev detí'],
        1163 => ['Školský výbor ECAV, EMC ECAV a Asociácia evanjelických škôl Slovenska', 'Školský výbor ECAV'],
        1164 => ['Farnosti a obce Ladce a Beluša, Považská cementáreň, a. s. a Nadácia AGAPA Ladce', 'Farnosť Ladce'],
        1174 => ['Trnavská arcidiecézna charita, Mesto Trnava, Arcibiskupský úrad Trnava', 'Trnavská arcidiecézna charita'],
        1200 => ['Rímskokatolícka cirkev, Rímskokatolícka farnosť Topoľčany a Združenie mariánskych ctiteľov v Topoľčanoch', 'Rímskokatolícka farnosť Topoľčany'],
        1204 => ['Ekumenický výbor ECAV a Rada pre ekumenizmus Bratislavskej arcidiecézy', 'Ekumenický výbor ECAV'],
        1205 => ['Saleziáni don Bosca s Farnosťou Bratislava - Vajnory', 'Saleziáni don Bosca'],
        1216 => ['Občianske združenie Bratislavská Kalvária a Bratstvo Sprevádzajúci', 'Občianske združenie Bratislavská Kalvária'],
        1218 => ['Spoločnosť Andreja Hlinku, CYRILOMETODIADA, o. z., a Nadácia PRO PATRIA', 'Spoločnosť Andreja Hlinku'],
        1226 => ['Bosí karmelitáni a Svetský rád bosých karmelitánov na Slovensku', 'Rád bosých karmelitánov'],
        1227 => ['V.I.A.C. & Lámačské chvály', 'V.I.A.C. - Inštitút pre podporu a rozvoj mládeže'],
        1235 => ['Verbisti a farnosť Nitra - Kalvária', 'Misionári verbisti'],
        1239 => ['Trenčianske osvetové stredisko v Trenčíne, T-VIA, o. z. a Farský úrad Skalka nad Váhom', 'Trenčianske osvetové stredisko v Trenčíne'],
        1253 => ['Rada Konferencie biskupov pre históriu, Kňazský seminár sv. Gorazda v Nitre, Ústav pre výskum kultúrneho dedičstva Konšt', 'Rada KBS pre históriu'],
        1255 => ['Slovenská filharmónia, CYRILOMETODIADA, o. z., Nadácia PRO PATRIA', 'Slovenská filharmónia'],
        1256 => ['Obec a Farnosť Terchová', 'Obec Terchová'],
        1267 => ['Arcidiecézne centrum pre rodinu a Domček', 'Arcidiecézne centrum pre rodinu'],
        1285 => ['V.I.A.C. - Inštitút pre podporu a rozvoj mládeže a Spoločenstvo Ladislava Hanusa', 'V.I.A.C. - Inštitút pre podporu a rozvoj mládeže'],
        1291 => ['Farnosť a Obec Ladce, Farnosť a Obec Beluša, Považská cementáreň, a. s., Nadácia AGAPA Ladce', 'Farnosť Ladce'],
        1296 => ['Košický samosprávny kraj, Prešovský samosprávny kraj, Spišské biskupstvo, Mesto Spišské Podhradie, 64. skautský zbor Šte', 'Košický samosprávny kraj'],
        1314 => ['Rada pre vedu, vzdelanie a kultúru pri KBS', 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru'],
        1323 => ['Rád bosých karmelitánov na Slovensku a Svetský rád bosých karmelitánov', 'Rád bosých karmelitánov'],
        1327 => ['dobrovoľníci z farností Nižná Šebastová a Ľubotice, n.o. Oáza - nádej pre nový život, a PKO Prešov', 'Farnosť Prešov – Nižná Šebastová'],
        1329 => ['Bratia Dominikáni, komunita Redemptoristov, Kresťanské združenie Sprevádzajúci - pútnické bratstvo', 'Bratia Dominikáni – farnosť Bratislava Kalvária'],
        1331 => ['ZD ECAV, CZ ECAV Rim. Brezovo', 'ZD ECAV'],
        1332 => ['Rád bosých karmelitánov a Svetský rád bosých karmelitánov', 'Rád bosých karmelitánov'],
        1333 => ['Ekonomika spoločenstva a Komunitné a podnikateľské centrum Francis & Friends', 'Ekonomika spoločenstva'],
        1334 => ['Inštitút Communio a združenie Ekuza', 'Inštitút Communio'],
        1335 => ['Centrum pre štúdium biblického a blízkovýchodného sveta, Rímskokatolícka farnosť sv. Ondreja v Ružomberku, Mesto Ružombe', 'Centrum pre štúdium biblického a blízkovýchodného sveta'],
        1336 => ['Spoločenstvo mužov sv. Jozefa a Farnosť Tvrdošín', 'Spoločenstvo mužov sv. Jozefa'],
        1338 => ['Bratia Dominikáni - farnosť Bratislava Kalvária, Občianske združenie Bratislavská Kalvária, KZ Sprevádzajúci – pútnické', 'Bratia Dominikáni – farnosť Bratislava Kalvária'],
        1369 => ['Prešovský samosprávny kraj, Šarišské múzeum Bardejov, Spoločenstvo sv. Gabriela na Slovensku - bardejovskí členovia, Slo', 'Prešovský samosprávny kraj'],
        1370 => ['Občianske združenie CYRILOMETODIADA, Spolok Slovákov v Poľsku, nadácia PRO PATRIA', 'CYRILOMETODIADA, o. z.'],
        1373 => ['Saleziáni don Bosca a Pavlíni', 'Saleziáni don Bosca'],
        1376 => ['Občianske združenie CYRILOMETODIADA, Spolok Slovákov v Poľsku a Nadácia PRO PATRIA', 'CYRILOMETODIADA, o. z.'],
        1425 => ['Spoločenstvo Baránkovej krvi, Farnosť Ladce a Považská cementáreň, a. s. Ladce', 'Spoločenstvo Baránkovej krvi'],
        1457 => ['Rada pre vedu, vzdelanie a kultúru pri KBS, Teologický inštitút Teologickej fakulty Katolíckej univerzity v Spišskom Pod', 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru'],
        1458 => ['KZ Sprevádzajúci - pútnické bratstvo a OZ BRATISLAVSKÁ KALVÁRIA', 'KZ Sprevádzajúci – pútnické bratstvo'],
        1482 => ['Saleziáni don Bosca, Farnosť Vajnory a spoločenstvo Tymian', 'Saleziáni don Bosca'],
        1484 => ['Spoločnosť Andreja Hlinku, CYRILOMETODIADA, o. z., Nadácia PRO PATRIA a Občiansky výbor MČ Černová', 'Spoločnosť Andreja Hlinku'],
        1485 => ['Farnosť Nitra Kalvária a Verbisti z Misijného domu Matky Božej v Nitre', 'Farnosť Nitra – Kalvária'],
        1487 => ['Nadácia PRO PATRIA, Spolok Srbov na Slovensku a CYRILOMETODIADA, o. z', 'Nadácia PRO PATRIA'],
        1494 => ['Centrum pre štúdium biblického a blízkovýchodného sveta, Hornozemplínska knižnica vo Vranove nad Topľou, Slavistický úst', 'Centrum pre štúdium biblického a blízkovýchodného sveta'],
        1500 => ['CVX a MAGIS', 'CVX'],
        1506 => ['Západný dištrikt ECAV na Slovensku a Výbor cirkevnej hudby a hymnológie ECAV na Slovensku', 'Západný dištrikt ECAV na Slovensku'],
        1508 => ['Úsmev ako dar a Rád premonštrátov na Slovensku', 'Úsmev ako dar'],
        1512 => ['Sekcia pre rodinu Žilinskej diecézy, Familiae Locum - Rodinkovo n.o', 'Sekcia pre rodinu Žilinskej diecézy'],
        1519 => ['Tripy mladých a Bezhraničná láska', 'Tripy mladých'],
        1532 => ['Múzeum obetí komunizmu v Košiciach, Ústav pamäti národa (ÚPN), OZ samizdat.sk a Dom Quo Vadis', 'Múzeum obetí komunizmu v Košiciach'],
        1538 => ['Košický samosprávny kraj, Prešovský samosprávny kraj, Spišské biskupstvo, Mesto Spišské Podhradie, Združenie obcí Spišsk', 'Košický samosprávny kraj'],
        1547 => ['Farnosť a Obec Ladce, Farnosť a Obec Beluša, Považská cementáreň, a. s. a Nadácia AGAPA Ladce', 'Farnosť Ladce'],
        1554 => ['Spoločnosť katolíckeho apoštolátu (pallotíni) a Združenie katolíckeho apoštolátu', 'Spoločnosť katolíckeho apoštolátu (pallotíni)'],
        1559 => ['Bratia Dominikáni, rehoľa Redemptoristov, združenie Sprevádzajúci - pútnické bratstvo', 'Bratia Dominikáni – farnosť Bratislava Kalvária'],
        1565 => ['Misionári verbisti a Farnosť Nitra - Kalvária', 'Misionári verbisti'],
        1581 => ['Komisia biskupských konferencií Európskej únie (COMECE), Rada európskych biskupských konferencií (CCEE), Konferencia bis', 'Komisia biskupských konferencií Európskej únie (COMECE)'],
        1584 => ['Inštitút Communio a Filozofická fakulta Katolíckej univerzity v Ružomberku', 'Inštitút Communio'],
        1587 => ['Spoločenstvo Tymian a Centrum miništrantskej spirituality Titusa Zemana', 'Spoločenstvo Tymian'],
        1604 => ['Farnosť Kežmarok a farnosť Rozkvet', 'Farnosť Kežmarok'],
        1615 => ['Edukačno-misijné centrum ECAV a Metodicko-pedagogické centrum Trenčín', 'Edukačno-misijné centrum ECAV'],
        1625 => ['Kolednícka akcia Dobrá novina a Pápežské misijné diela', 'Kolednícka akcia Dobrá novina'],
        1635 => ['Bratislavská eparchia, Spoločnosť svätého Gorazda, Matica slovenská, Martin Spolok sv. Cyrila a Metoda Michalovce, Rada', 'Bratislavská eparchia'],
        1645 => ['Tripy mladých, projekt Bezhraničná láska, Vides Slovakia a Arcidiecézne centrum pre mládež (ACMko)', 'Tripy mladých'],
        1649 => ['Mládežnícke organizácie eRko, DOMKA, Laura, ZKSM a ZMM', 'eRko – Hnutie kresťanských spoločenstiev detí'],
        1654 => ['Katedra aplikovanej edukológie, Katedra systematickej teológie Gréckokatolíckej teologickej fakulty Prešovskej univerzit', 'Katedra aplikovanej edukológie'],
        1660 => ['Ústredný zväz židovských náboženských obcí na Slovensku, Židovská náboženská obec Bratislava a SNM - Múzem židovskej kul', 'Ústredný zväz židovských náboženských obcí na Slovensku'],
        1661 => ['Bratia dominikáni, KZ Sprevádzajúci - pútnické bratstvo, OZ BRATISLAVSKÁ KALVÁRIA', 'Bratia Dominikáni – farnosť Bratislava Kalvária'],
        1662 => ['Rád bosých karmelitánov na Slovensku a Svetský rád bosých karmelitánov v Bratislave', 'Rád bosých karmelitánov'],
        1669 => ['Saleziáni, FÚ Vajnory, Domka a Spoločenstvo Tymian', 'Saleziáni don Bosca'],
        1673 => ['Biskupstvo Nitra, Nitrianska sídelná kapitula a Castellum, n. o', 'Nitrianske biskupstvo'],
        1674 => ['Saleziáni don Bosca, Farský úrad vo Vajnoroch, Domka a Spoločenstvo Tymian', 'Saleziáni don Bosca'],
        1682 => ['Spoločnosť Andreja Hlinku, CYRILOMETODIADA, o. z., a Občiansky výbor MČ Černová', 'Spoločnosť Andreja Hlinku'],
        1685 => ['Občianske združenie PRE ŠUŇAVU, o.z., Kresťanská policajná asociácia', 'Občianske združenie PRE ŠUŇAVU, o.z.'],
        1687 => ['Bezhraničná láska & Tripy mladých', 'Tripy mladých'],
        1701 => ['Slavistický ústav Jána Stanislava SAV a Centrum pre štúidium biblického a blízkovýchodného sveta', 'Slavistický ústav Jána Stanislava SAV, v.v.i.'],
        1706 => ['OZ Chápať srdcom a OZ Nenápadní hrdinovia', 'OZ Chápať srdcom'],
        1713 => ['nitrianskeho diecézneho biskupa Mons. Viliama Judáka a primátora mesta Nitra Mareka Hattasa', 'Nitrianske biskupstvo'],
        1714 => ['Spoločenstvo evanjelických žien a Dunajsko-nitriansky seniorát', 'Spoločenstvo evanjelických žien'],
        1716 => ['Oratórium svätého Filipa Nériho a Farnosť Svätej rodiny v Bratislave-Petržalke', 'Oratórium svätého Filipa Nériho'],
        1717 => ['Farnosť Ladce a Farnosť Beluša, Obec Ladce a Obec Beluša, Považská cementáreň, a. s. a Nadácia AGAPA Ladce', 'Farnosť Ladce'],
        1721 => ['školský výbor a výbor misie ECAV', 'Školský výbor ECAV'],
        1751 => ['Spoločnosť katolíckeho apoštolátu (SAC) – pallotínmi', 'Spoločnosť katolíckeho apoštolátu (pallotíni)'],
        1754 => ['Pápežská rada na podporu jednoty kresťanov a Komisia pre vieru a poriadok Svetovej rady cirkví', 'Pápežská rada na podporu jednoty kresťanov'],
        1771 => ['Rímskokatolícka cirkev, Trnavská arcidiecéza', 'Trnavská arcidiecéza'],
        1782 => ['OC Kresťanskodemokratického hnutia, Miestny odbor Matice slovenská v Trnave a Spoločnosť Andreja Hlinku', 'OC Kresťanskodemokratického hnutia'],
        1783 => ['Veľvyslanectvo Poľskej republiky na Slovensku, Farnosť Levoča a primátor mesta Levoča', 'Veľvyslanectvo Poľskej republiky na Slovensku'],
        1789 => ['Spoločnosť katolíckeho apoštolátu (SAC) – pallotíni', 'Spoločnosť katolíckeho apoštolátu (pallotíni)'],
        1792 => ['Žilinský samosprávny kraj, Krajská knižnica v Žiline, Obec Terchová, Horská záchranná služba Malá Fatra, Krajská organiz', 'Žilinský samosprávny kraj'],
        1808 => ['Mesto Bardejov, Mestský úrad v Bardejove s oddelením kultúry a Rímskokatolícka farnosť sv. Egídia', 'Mesto Bardejov'],
        1812 => ['Veľvyslanectvo Poľskej republiky a Arcibiskupský úrad v Trnave', 'Veľvyslanectvo Poľskej republiky na Slovensku'],
        1851 => ['DEDIČSTVO OTCOV, o. z., RASTIC, o. z., Rímskokatolícky farský úrad Bratislava-Devín', 'DEDIČSTVO OTCOV, o. z.'],
        1858 => ['Kongregácia sestier dominikánov bl. Imeldy, Rehoľa dominikánov a Gymnázium sv. Tomáša Akvinského', 'Kongregácia sestier dominikánov bl. Imeldy'],
        1863 => ['Slovenský historický ústav v Ríme, Centrum Spirituality Východ – Západ Michala Lacka SJ, vedecko-výskumné pracovisko Teo', 'Slovenský historický ústav v Ríme'],
        1867 => ['Rímskokatolícky cirkevný hudobný spolok sv. Mikuláša, Farnosť sv. Mikuláša', 'Rímskokatolícky cirkevný hudobný spolok sv. Mikuláša'],
        1881 => ['Občianske združenie Vykročiť a Vrbovské chvály', 'Občianske združenie Vykročiť'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::RENAMES as $id => [$old, $new]) {
            $canal = DB::table('canals')
                ->where('id', $id)
                ->where('name', $old)
                ->whereNull('deleted_at')
                ->whereNull('claimed_at')
                ->where('registration_source', 'import')
                ->first();

            if ($canal === null) {
                continue;
            }

            $slug = Str::slug($new);

            $target = DB::table('canals')
                ->where('slug', $slug)
                ->where('id', '!=', $id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if ($target !== null && $this->canBeMerged($id)) {
                $this->mergeInto($id, (int) $target->id);

                continue;
            }

            DB::table('canals')->where('id', $id)->update([
                'name' => $new,
                'slug' => $slug,
                'body' => "{$new} — organizátor podujatí.",
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::RENAMES as $id => [$old, $new]) {
            DB::table('canals')
                ->where('id', $id)
                ->where('name', $new)
                ->update(['name' => $old, 'slug' => Str::slug($old), 'updated_at' => now()]);
        }
    }

    /** Kanál, na ktorý nič okrem podujatí, miest a technického vlastníka neodkazuje. */
    private function canBeMerged(int $id): bool
    {
        foreach (['canal_claims', 'canal_invitations', 'canal_outreach', 'support_tickets'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('canal_id', $id)->exists()) {
                return false;
            }
        }

        // Skutočný (nie systémový) člen tímu — zlúčením by prišiel o prístup.
        $realMembers = DB::table('canal_user')
            ->join('users', 'users.id', '=', 'canal_user.user_id')
            ->where('canal_user.canal_id', $id)
            ->whereNotIn('canal_user.user_id', function ($query) {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'super-admin')
                    ->where('model_has_roles.model_type', 'App\\Models\\User');
            })
            ->exists();

        return ! $realMembers;
    }

    private function mergeInto(int $duplicateId, int $canonicalId): void
    {
        DB::table('events')->where('canal_id', $duplicateId)->update(['canal_id' => $canonicalId, 'updated_at' => now()]);

        if (Schema::hasTable('event_series')) {
            DB::table('event_series')->where('canal_id', $duplicateId)->update(['canal_id' => $canonicalId]);
        }

        foreach (DB::table('canal_venue')->where('canal_id', $duplicateId)->get() as $link) {
            DB::table('canal_venue')->insertOrIgnore([
                'canal_id' => $canonicalId,
                'venue_id' => $link->venue_id,
                'is_owner' => $link->is_owner,
                'status' => $link->status,
                'created_at' => $link->created_at,
                'updated_at' => now(),
            ]);
        }
        DB::table('canal_venue')->where('canal_id', $duplicateId)->delete();

        DB::table('canal_user')->where('canal_id', $duplicateId)->delete();
        DB::table('users')->where('canal_id', $duplicateId)->update(['canal_id' => $canonicalId]);

        DB::table('canals')->where('id', $duplicateId)->update(['deleted_at' => now(), 'updated_at' => now()]);
    }
};
