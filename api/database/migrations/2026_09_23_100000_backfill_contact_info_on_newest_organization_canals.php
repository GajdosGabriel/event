<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pokračovanie predošlých backfill_contact_info_on_*_organization_canals.php
 * migrácií — doplní web/email/telefón/adresu a stručný popisný `body` pre
 * 34 najnovšie vzniknutých neosobných kanálov (id 1843–1882, vytvorené
 * 18. 9. – 22. 9. 2026), zistené z ich oficiálnych webov a spoľahlivých
 * zdrojov (Wikipedia a pod.) 23. 9. 2026. Rovnaký guard (match na id +
 * name), rovnaká štruktúra dát [pôvodná_hodnota, nová_hodnota]. `body` sa
 * rovnako ako v predošlých migráciách prepisuje jednosmerne — len ak
 * kanál už nejaký (naimportovaný) `body` má, a `down()` ho nevracia späť.
 *
 * Popri doplnení chýbajúcich polí táto migrácia opravuje aj 3 kanály, kde
 * bol pôvodne uložený web preukázateľne chybný (rovnaký vzor ako
 * `2026_09_22_200000_fix_known_wrong_websites_on_organization_canals.php`):
 *  - id=1864: face2face2020.at je zastaraná stránka jednorazového podujatia
 *    → nahradené oficiálnou stránkou Diecézy Graz-Seckau (Rakúsko).
 *  - id=1866: vstupenky.opatstvojasov.sk je len predaj vstupeniek
 *    → nahradené hlavnou stránkou opátstva (rovnaký kontakt ako id=1848).
 *  - id=1869: kurzrut.sk nesúvisí s centrom → nahradené centrumsigord.sk.
 *
 * Vynechané kanály (nedoplnené vôbec, potrebná manuálna kontrola):
 *  - id=1846 "Evanjelická základná škola": uložený web evlyceum.sk patrí
 *    inej škole (Evanjelické lýceum), správna stránka sa nenašla.
 *  - id=1854 "Ekumenické kresťanské spoločenstvo mesta Prešov": samostatný
 *    subjekt s vlastným webom sa nepodarilo dohľadať.
 *  - id=1859 "Misijné centrum Prameň": viacero podobných, ale odlišných
 *    subjektov s týmto/podobným názvom — bez istoty, ktorý je správny.
 *  - id=1870 "Združenie obcí Termál": nenašiel sa samostatný oficiálny
 *    kontakt združenia (len kontakt na predsedajúcu obec).
 *  - id=1872 "CENTRUM ABRAHAM": nepodarilo sa dohľadať žiadny zodpovedajúci
 *    subjekt.
 *  - id=1875 "DC pre zranených umelým potratom": nízka istota, či ide o
 *    n.o. Milujúca náruč (Centrum Božieho milosrdenstva) alebo iný subjekt
 *    — riziko priradenia kontaktu k nesprávnej organizácii.
 *
 * Zistené duplicity (nezlučujú sa v tejto migrácii, len poznámka pre
 * prípadnú budúcu merge_duplicate_organization_canals.php migráciu):
 *  - id=1855 "Diecéza Banská Bystrica" duplicita id=389 (rovnaké dáta).
 *  - id=1856 "EBF UK" duplicita id=1857 (tá istá inštitúcia).
 */
return new class extends Migration
{
    /**
     * @var array<int, array{
     *   name: string,
     *   website?: array{0:?string,1:?string},
     *   email?: array{0:?string,1:string},
     *   phone?: array{0:?string,1:string},
     *   street?: array{0:?string,1:string},
     *   postcode?: array{0:?string,1:string},
     *   country?: array{0:?string,1:string},
     *   body?: string,
     * }>
     */
    private const CANALS = [
        1843 => [
            'name' => 'ECAV Turany',
            'email' => [null, 'turany@ecav.sk'],
            'phone' => [null, '+421 944 409 389'],
            'street' => [null, 'Komenského 313/2'], 'postcode' => [null, '038 53'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Evanjelický cirkevný zbor v Turanoch siaha do 16. storočia; v roku 1639 prišiel počas protireformácie o kostol, faru aj školu, ktoré mu vojaci zemepána Mikuláša Révaia násilne odobrali. Po tolerančnom patente Jozefa II. si veriaci v roku 1786 postavili vlastný kostol, na mieste ktorého v rokoch 1933 – 1934 vyrástol súčasný chrám podľa návrhu architekta Jozefa Pešeka z Ružomberka so 42-metrovou vežou a štyrmi zvonmi z roku 1932.</p>',
        ],
        1844 => [
            'name' => 'Kňazský seminár sv. Františka Xaverského',
            'website' => [null, 'https://www.xaver.sk'],
            'phone' => [null, '+421 48 418 26 02'],
            'street' => [null, 'Banská 28, Badín'], 'postcode' => [null, '976 32'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kňazský seminár sv. Františka Xaverského v Badíne nadväzuje na prvý seminár banskobystrickej diecézy, zriadený v roku 1805 biskupom Gabrielom Zerdahelyim, ktorý komunistický režim 14. júla 1950 zatvoril a skonfiškoval. Biskup Rudolf Baláž ho po páde komunizmu v roku 1990 obnovil a nová budova v Badíne pri Banskej Bystrici bola slávnostne otvorená 4. októbra 1993; seminár dnes pripravuje kandidátov kňazstva pre banskobystrickú diecézu.</p>',
        ],
        1845 => [
            'name' => 'Misionári Spoločnosti Božieho Slova – Verbisti',
            'website' => [null, 'https://www.verbisti.sk'],
            'phone' => [null, '+421 37 77 69 411'],
            'street' => [null, 'Kalvária 3'], 'postcode' => [null, '949 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rehoľnú Spoločnosť Božieho Slova (verbistov) založil v roku 1875 v holandskom Steyli svätý Arnold Janssen na prípravu misionárov. Na Slovensko prišli v roku 1925 a ich hlavným strediskom sa stal Misijný dom v Nitre na Kalvárii, otvorený v roku 1928 zásluhou biskupa Karola Kmeťku; dnes pôsobia aj v Bratislave a Ružomberku a venujú sa misijnej animácii, pastorácii mládeže a vydavateľskej činnosti.</p>',
        ],
        1847 => [
            'name' => 'spolok Človek a viera',
            'website' => [null, 'https://www.clovekaviera.sk'],
            'email' => [null, 'clovekaviera.sk@gmail.com'],
            'country' => [null, 'Slovensko'],
            'body' => '<p>Spoločenstvo fotografov Človek a viera založil v roku 2011 fotograf Roman Albrecht s cieľom rozvíjať kresťanskú fotografiu a kultivovať prácu fotografov počas cirkevných obradov. Z pôvodných troch členov narástlo na vyše 230 fotografov v Česku, na Slovensku i v zahraničí, ktorí dokumentujú bohoslužby a púte a svoje práce každoročne predstavujú na výstave na Pražskom hrade.</p>',
        ],
        1848 => [
            // rovnaký kontakt ako id=1866 "Jasovská kanónia premonštrátov" — prevádzkuje opátstvo
            'name' => 'Nezisková organizácia Rádu premonštrátov – Opátstva Jasov',
            'website' => [null, 'https://opatstvojasov.sk'],
            'email' => [null, 'info@opatstvojasov.sk'],
            'phone' => [null, '+421 910 649 345'],
            'street' => [null, 'Podzámok 166/28'], 'postcode' => [null, '044 23'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Premonštrátsky kláštor v Jasove vznikol ako dcérsky kláštor kláštora vo Veľkom Varadíne v druhej polovici 12. storočia; priamo je doložený od roku 1243, keď pod vedením prepošta Alberta obnovil rehoľný život a v roku 1255 získal od kráľa Belu IV. postavenie hodnoverného miesta. Novú kapitolu jeho dejín otvoril v roku 1745 opát Andrej Sauberer, ktorý dal postaviť dnešný barokový kláštor a Kostol sv. Jána Krstiteľa s jasovskou knižnicou a záhradou.</p>',
        ],
        1849 => [
            'name' => 'Teologická fakulta KU v Košiciach',
            'website' => [null, 'https://www.tf.ku.sk'],
            'email' => [null, 'sekretariat.tf@ku.sk'],
            'phone' => [null, '+421 55 68 36 100'],
            'street' => [null, 'Hlavná 89'], 'postcode' => [null, '041 21'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Teologická fakulta Katolíckej univerzity so sídlom v Košiciach vznikla v roku 2003 zriaďovacou listinou rektora Katolíckej univerzity a dekrétom Kongregácie pre katolícku výchovu, pričom nadväzuje na odkaz Košickej univerzity založenej v roku 1657. Ponúka bakalárske, magisterské a doktorandské štúdium katolíckej teológie, sociálnej práce a cirkevnej hudby a popri formácii kňazov pripravuje aj laických odborníkov.</p>',
        ],
        1850 => [
            'name' => 'Linka Valentín',
            'email' => [null, 'valentinskespolocenstvo@centrum.sk'],
            'street' => [null, 'P.O. Box B-43'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Linka Valentín vznikla vo februári 1999 ako pastoračná iniciatíva Gréckokatolíckej eparchie Košice, pomenovaná po svätcovi spájanom s pravou láskou. Poskytuje duchovnú pomoc kresťanom s homosexuálnou orientáciou a ľuďom riešiacim otázky rodovej identity, ktorí sa v duchu evanjelia snažia žiť podľa Božieho slova.</p>',
        ],
        1851 => [
            // názov spája 3 subjekty (DEDIČSTVO OTCOV o.z., RASTIC o.z., farský úrad) — použitý kontakt farského úradu Devín
            'name' => 'DEDIČSTVO OTCOV, o. z., RASTIC, o. z., Rímskokatolícky farský úrad Bratislava-Devín',
            'website' => [null, 'https://devin.fara.sk'],
            'email' => [null, 'devin@fara.sk'],
            'phone' => [null, '0905 614 610'],
            'street' => [null, 'Štítová 2'], 'postcode' => [null, '841 10'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Svätého kríža v Bratislave-Devíne patrí do Bratislavskej arcidiecézy. Jej farský Kostol Svätého kríža je pôvodne gotická stavba postavená v 30. až 40. rokoch 13. storočia, ktorá bola postupne rozšírená z jednoloďového na trojloďový chrám. V rokoch 1672 – 1673 prešiel obnovou, v roku 1772 bola upravená veža a vybudovaný hlavný a bočný portál, v roku 1788 bola nad presbytériom vybudovaná klasicistická klenba a medzi rokmi 1810 – 1820 bol hlavný oltár zasvätený úcte Svätého kríža. V 70. rokoch 20. storočia prešiel kostol archeologickým výskumom a dôkladnou reštauráciou.</p>',
        ],
        1852 => [
            'name' => 'Cirkev bratská',
            'website' => [null, 'https://www.cb.sk'],
            'phone' => [null, '+421 2 5443 2586'],
            'street' => [null, 'Cukrová 14'], 'postcode' => [null, '811 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Cirkev bratská vznikla v roku 1882 v Čechách zlúčením Slobodnej evanjelickej cirkvi českej (založenej 1868 v Náchode) a Slobodnej reformovanej cirkvi (založenej 1880). Na Slovensko prenikla začiatkom 20. storočia, keď vznikli prvé zbory v Prešove (1923) a v Bratislave (1926); súčasný názov Cirkev bratská prijala v roku 1967. Dnes pôsobí ako samostatná cirkev v Slovenskej republike, združuje 22 zborov a je členom Ekumenickej rady cirkví na Slovensku.</p>',
        ],
        1853 => [
            'name' => 'jezuitské spoločenstvo MAG+S',
            'website' => [null, 'https://magisslovensko.sk'],
            'email' => [null, 'magis.slovensko@gmail.com'],
            'street' => [null, 'Františkánske námestie 4'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Jezuitské spoločenstvo MAG+S vzniklo po Svetových dňoch mládeže v Madride v roku 2011, ktorých sa zúčastnilo vyše 70 mladých ľudí zo Slovenska. Po návrate domov založili pri bratislavskom jezuitskom kolégiu prvé stretko, pri zrode ktorého stáli jezuiti Peter Girašek SJ a Jaroslav Mudroň SJ. Spoločenstvo je určené vysokoškolákom a mladým pracujúcim vo veku 18 až 30 rokov a jeho duchovným základom je ignaciánska spiritualita; názov Magis pochádza z latinského slova znamenajúceho „viac" alebo „plnšie".</p>',
        ],
        1855 => [
            // duplicita id=389 "Banskobystrická diecéza" — rovnaké dáta
            'name' => 'Diecéza Banská Bystrica',
            'website' => [null, 'https://bbdieceza.sk'],
            'email' => [null, 'sekretariat.bb@rcc.sk'],
            'phone' => [null, '048 472 08 00'],
            'street' => [null, 'Námestie SNP 19'], 'postcode' => [null, '975 90'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Banskobystrická diecéza vznikla 13. marca 1776, keď cisárovná Mária Terézia bulou Regalium principum spolu s pápežom Piom VI. zriadili nové biskupstvo. Prvým banskobystrickým biskupom bol František Berchtold, diecéza pri vzniku mala 77 farností a okolo 290 filiálok a jej patrónom je svätý František Xaverský, ktorému je zasvätená aj katedrála v centre mesta.</p>',
        ],
        1856 => [
            // duplicita id=1857 "Evanjelická bohoslovecká fakulta UK v Bratislave" — rovnaká inštitúcia
            'name' => 'EBF UK',
            'website' => [null, 'https://fevth.uniba.sk'],
            'email' => [null, 'sd@fevth.uniba.sk'],
            'phone' => [null, '02/9020 2181'],
            'street' => [null, 'Bartókova 8'], 'postcode' => [null, '811 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Evanjelická bohoslovecká fakulta vznikla v roku 1919 v Bratislave ako Vysoká škola bohoslovecká evanjelická; vyučovanie sa začalo 20. októbra 1919 v budove bývalého evanjelického lýcea na Konventnej ulici a jej prvým dekanom bol prof. J. Bodnár. Súčasťou Univerzity Komenského sa stala 1. júla 1990 a odvtedy nesie názov Evanjelická bohoslovecká fakulta UK. Poskytuje bakalárske, magisterské a doktorandské štúdium teológie, prijíma študentov všetkých vierovyznaní i bez vyznania a vydáva vedecký časopis Testimonia theologica.</p>',
        ],
        1857 => [
            'name' => 'Evanjelická bohoslovecká fakulta UK v Bratislave',
            'website' => [null, 'https://fevth.uniba.sk'],
            'email' => [null, 'sd@fevth.uniba.sk'],
            'phone' => [null, '02/9020 2181'],
            'street' => [null, 'Bartókova 8'], 'postcode' => [null, '811 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Evanjelická bohoslovecká fakulta vznikla v roku 1919 v Bratislave ako Vysoká škola bohoslovecká evanjelická; vyučovanie sa začalo 20. októbra 1919 v budove bývalého evanjelického lýcea na Konventnej ulici a jej prvým dekanom bol prof. J. Bodnár. Súčasťou Univerzity Komenského sa stala 1. júla 1990 a odvtedy nesie názov Evanjelická bohoslovecká fakulta UK. Poskytuje bakalárske, magisterské a doktorandské štúdium teológie, prijíma študentov všetkých vierovyznaní i bez vyznania a vydáva vedecký časopis Testimonia theologica.</p>',
        ],
        1858 => [
            // názov spája 3 subjekty — použitý kontakt provincialátu dominikánov (SK) ako najbližší spoločný
            'name' => 'Kongregácia sestier dominikánov bl. Imeldy, Rehoľa dominikánov a Gymnázium sv. Tomáša Akvinského',
            'email' => [null, 'provincialat@dominikani.sk'],
            'phone' => [null, '02 5479 2165'],
            'street' => [null, 'Na Kalvárii 10'], 'postcode' => [null, '811 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rehoľu dominikánov (Rehoľu kazateľov, Ordo Praedicatorum) založil v 13. storočí svätý Dominik Guzmán a jej vznik potvrdil v roku 1216 pápež Honorius III. Do Uhorska, ktorého súčasťou bolo vtedy aj územie Slovenska, priniesol rád brat Pavol; uhorská provincia vznikla v roku 1221 a prvý kláštor na Slovensku bol založený v Košiciach v roku 1235, nasledovaný ďalšími v Banskej Štiavnici, Trnave, Kremnici, Veľkom Šariši a Komárne. Po úpadku spôsobenom reformáciou sa podarilo obnoviť len košický konvent a po zániku Rakúsko-Uhorska sa slovenskí dominikáni stali súčasťou českej provincie. Dnes rehoľa na Slovensku pôsobí na piatich miestach — v Bratislave, Košiciach, Zvolene, Žiline a Dunajskej Lužnej.</p>',
        ],
        1860 => [
            'name' => 'ProChrist',
            'country' => [null, 'Slovensko'],
            'body' => '<p>Ekumenický misijný projekt ProChrist vznikol v Nemecku v roku 1993, keď sa evanjelizačné podujatia s rečníkmi ako Billy Graham a Ulrich Parzany prenášali satelitom do desiatok krajín sveta. Od roku 2015 nemecké centrum v Kasseli prešlo na formát ProChrist LIVE, v ktorom jedno centrálne podujatie nahradilo viac ako 120 menších lokálnych evanjelizácií v Nemecku a Európe. Na Slovensku organizuje regionálne podujatia Východný dištrikt Evanjelickej cirkvi augsburského vyznania, doteraz sa konali napríklad v Ružomberku (2016), Liptovskom Hrádku (2017 a 2019) a Dolnom Kubíne (2025).</p>',
        ],
        1861 => [
            'name' => 'Českobratská cirkev evanjelická',
            'website' => [null, 'https://www.e-cirkev.cz'],
            'email' => [null, 'sekretariat@e-cirkev.cz'],
            'phone' => [null, '+420 224 999 211'],
            'street' => [null, 'Jungmannova 9, Praha 1'], 'postcode' => [null, '110 00'], 'country' => [null, 'Česko'],
            'body' => '<p>Českobratská cirkev evanjelická vznikla v roku 1918 zlúčením českojazyčných zborov evanjelickej cirkvi augsburského vyznania (luteránov) a helvétskeho vyznania (reformovaných); jednotu spečatil generálny synod 17. decembra 1918. Cirkev sa hlási aj k odkazu českej reformácie — husitského hnutia, cirkvi podobojej a Jednoty bratskej — a je prvou zjednotenou cirkvou v strednej Európe. V súčasnosti je po Rímskokatolíckej cirkvi druhou najväčšou cirkvou v Českej republike a prevádzkuje okrem farských zborov aj Diakonii ČCE, Evanjelickú akadémiu a vydáva časopis Český bratr.</p>',
        ],
        1862 => [
            // v registri vedená ako "Nadácia Antona Srholca ANTÓNIO" — nejde o chybu dát
            'name' => 'Nadácia Antona Srholca António',
            'website' => [null, 'https://antonsrholec.sk'],
            'street' => [null, 'Nezábudková 807/12'], 'postcode' => [null, '821 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Nadácia Antona Srholca vznikla 17. novembra 2017 z iniciatívy priateľov a príbuzných pri prvom výročí úmrtia kňaza a disidenta Antona Srholca, aby uchovávala a šírila jeho odkaz pre ďalšie generácie. Anton Srholec sa narodil 12. júna 1929 v Skalici, za pokus o útek z republiky s cieľom vyštudovať teológiu ho komunistický súd odsúdil na 12 rokov väzenia a do amnestie v roku 1960 pracoval v uránových a uhoľných baniach; za kňaza bol vysvätený v máji 1970 v Ríme pápežom Pavlom VI. Nadácia sa venuje reflexii historického aj súčasného spoločenského diania a úlohe kresťanov vo verejnom živote v duchu jeho odkazu.</p>',
        ],
        1863 => [
            // názov spája Slovenský historický ústav v Ríme a Centrum spirituality Východ-Západ — použitý kontakt Košice
            'name' => 'Slovenský historický ústav v Ríme, Centrum Spirituality Východ – Západ Michala Lacka SJ, vedecko-výskumné pracovisko Teo',
            'website' => [null, 'https://tf.truni.sk/centrum-spirituality-vychod-zapad-michala-lacka'],
            'phone' => [null, '+421 2 5277 5410'],
            'street' => [null, 'Komenského 14'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Centrum spirituality Východ – Západ Michala Lacka je vedecko-výskumné pracovisko Teologickej fakulty Trnavskej univerzity so sídlom v Košiciach, zamerané na kresťanský Východ, byzantskú tradíciu, spiritualitu, históriu a ekumenický dialóg. Jeho korene siahajú k jezuitovi Michalovi Fedorovi, ktorý od roku 1980 usiloval o vznik inštitúcie pre štúdium kresťanského Východu; koncom roka 1992 tak vzniklo Stredisko pre štúdium a výskum Východ – Západ, ktoré 1. decembra 2001 dostalo súčasný názov po jezuitovi Michalovi Lackovi. Centrum spravuje odbornú knižnicu s vyše 30-tisíc zväzkami, organizuje sympóziá, semináre a konferencie, podporuje doktorandské štúdium a od roku 2011 ho vedie Šimon Marinčák.</p>',
        ],
        1864 => [
            // pôvodný web bol zastaraná stránka podujatia (face2face2020.at); ide o rakúsku diecézu Graz-Seckau
            'name' => 'evanjelická, reformovaná a metodistická cirkev a rímskokatolícka diecéza v Graz-Seckau',
            'website' => ['https://www.face2face2020.at', 'https://www.katholische-kirche-steiermark.at'],
            'email' => [null, 'ordinariat@graz-seckau.at'],
            'phone' => [null, '+43 316 8041-0'],
            'street' => [null, 'Bischofplatz 4, Graz'], 'postcode' => [null, '8010'], 'country' => [null, 'Rakúsko'],
            'body' => '<p>Rímskokatolícka diecéza Graz-Seckau vznikla v roku 1218, keď salzburský arcibiskup Eberhard II. založil diecézu Seckau ako tretie sufragánne biskupstvo Salzburgu po Gurku (1072) a Chiemsee (1215); pápež Honorius III. jej vznik potvrdil 22. júna 1218. Sídlom biskupov bol pôvodne augustiniánsky, neskôr benediktínsky kláštor v Seckau, od roku 1786 sa sídlo presunulo do Grazu a 15. júna 1963 bola diecéza premenovaná na Graz-Seckau. Dnes zahŕňa 388 farností s približne 727-tisíc katolíkmi a od roku 2015 ju vedie biskup Wilhelm Krautwaschl.</p>',
        ],
        1865 => [
            'name' => 'CZ ECAV Zvolen',
            'website' => [null, 'https://www.ecavzvolen.sk'],
            'email' => [null, 'zvolen@ecav.sk'],
            'phone' => [null, '+421 45 5335302'],
            'street' => [null, 'Námestie SNP 11/17'], 'postcode' => [null, '960 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Cirkevný zbor ECAV Zvolen spravuje Evanjelický kostol Svätej Trojice na Námestí SNP vo Zvolene, ktorý vznikol v roku 1785 prestavbou meštianskeho Bossaniovského domu zakúpeného evanjelikmi. Veža bola pristavaná v rokoch 1856 – 1857 staviteľom Františkom Mikšom z Tuhára a v rokoch 1921 – 1922 prešiel kostol prestavbou do novogotického slohu, ktorú realizovala slovensko-česká stavebná spoločnosť z Banskej Bystrice.</p>',
        ],
        1866 => [
            // pôvodný web bol len predaj vstupeniek (vstupenky.opatstvojasov.sk); rovnaký kontakt ako id=1848
            'name' => 'Jasovská kanónia premonštrátov',
            'website' => ['https://www.vstupenky.opatstvojasov.sk', 'https://opatstvojasov.sk'],
            'email' => [null, 'info@opatstvojasov.sk'],
            'phone' => [null, '+421 910 649 345'],
            'street' => [null, 'Podzámok 166/28'], 'postcode' => [null, '044 23'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Premonštrátsky kláštor v Jasove vznikol ako dcérsky kláštor kláštora vo Veľkom Varadíne v druhej polovici 12. storočia; priamo je doložený od roku 1243, keď pod vedením prepošta Alberta obnovil rehoľný život a v roku 1255 získal od kráľa Belu IV. postavenie hodnoverného miesta. Novú kapitolu jeho dejín otvoril v roku 1745 opát Andrej Sauberer, ktorý dal postaviť dnešný barokový kláštor a Kostol sv. Jána Krstiteľa s jasovskou knižnicou a záhradou.</p>',
        ],
        1867 => [
            // názov spája hudobný spolok a Farnosť sv. Mikuláša — použitý kontakt farnosti Trnava (historicky prepojená)
            'name' => 'Rímskokatolícky cirkevný hudobný spolok sv. Mikuláša, Farnosť sv. Mikuláša',
            'website' => [null, 'http://www.cirkevnahudba.sk'],
            'email' => [null, 'farnost.trnava@abu.sk'],
            'phone' => [null, '0914 555 664'],
            'street' => [null, 'Mikuláša Schneidera Trnavského 3'], 'postcode' => [null, '917 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rímskokatolícky cirkevný hudobný spolok sv. Mikuláša v Trnave, pôsobiaci pri Farnosti (Bazilike) sv. Mikuláša, založili v roku 1833 prepošt Ignác Kunszta a mešťan Ján Pitroff nadväzujúc na tradíciu chrámovej hudobnej produkcie siahajúcu do prvej polovice 19. storočia. Je najstarším spolkom svojho druhu na Slovensku; po roku 1948 bola jeho činnosť potláčaná a v roku 1951 bol formálne zrušený, no naďalej pôsobil ako katedrálny zbor a orchester sv. Mikuláša. Svoju činnosť legálne obnovil registráciou na Ministerstve vnútra SR v auguste 1993 pod dnešným názvom.</p>',
        ],
        1868 => [
            'name' => 'Žilinská Galéria IKONY',
            'website' => [null, 'https://www.ikony.hour.sk'],
            'email' => [null, 'ikony@hour.sk'],
            'phone' => [null, '+421 905 275 948'],
            'street' => [null, 'M. R. Štefánika 33'], 'postcode' => [null, '010 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Žilinská Galéria IKONY vznikla zo zberateľskej vášne manželov Milana a Marianny Urbaníkovcov, ktorí svoju rozsiahlu súkromnú zbierku byzantských ikon sprístupnili verejnosti v priestoroch budovy firmy Hour na Štefánikovom námestí v Žiline. Zbierka dnes obsahuje takmer tristo pôvodných ikon zo 16. až 19. storočia, pochádzajúcich z Ruska, Grécka, Bulharska a Rumunska, pričom väčšina z nich je pre nedostatok výstavného priestoru uložená v depozitári.</p>',
        ],
        1869 => [
            // pôvodný web nesúvisel s centrom (kurzrut.sk)
            'name' => 'CPR Sigord',
            'website' => ['http://www.kurzrut.sk', 'https://centrumsigord.sk'],
            'email' => [null, 'centrum.rodina@gmail.com'],
            'phone' => [null, '+421 903 983 316'],
            'street' => [null, 'Zlatá Baňa 134, Sigord'], 'postcode' => [null, '082 52'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Centrum pre rodinu Sigord vzniklo v roku 2008 z iniciatívy gréckokatolíckeho arcibiskupa Jána Babjaka SJ prestavbou budovy bývalej lesníckej školy v Kokošovciach a slávnostne bolo posvätené 14. novembra 2008. Ide o duchovné a formačné centrum Gréckokatolíckej cirkvi určené pre snúbencov, manželov a rodiny, ktoré organizuje kurzy, semináre, prednášky a duchovné obnovy. Doteraz centrom prešlo približne 15-tisíc návštevníkov, absolvovalo ho vyše 1 500 manželských párov a 400 párov snúbencov.</p>',
        ],
        1871 => [
            'name' => 'Confraternity Sancti Iacobi',
            'website' => [null, 'https://www.caminodesantiago.sk'],
            'email' => [null, 'info@caminodesantiago.sk'],
            'phone' => [null, '+421 944 570 252'],
            'street' => [null, 'Jasovská 9'], 'postcode' => [null, '040 11'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Priatelia Svätojakubskej cesty na Slovensku vzniklo 28. júna 2013 s cieľom obnovy a propagácie Svätojakubskej cesty (Camino de Santiago) na Slovensku. Slovenský úsek vedie približne z Košíc do Bratislavy a je rozdelený na sedem značených úsekov napájajúcich sa na európsku sieť Camina smerujúcu do Santiaga de Compostela. Združenie sa venuje značeniu a údržbe trasy, vydávaniu sprievodcov, organizovaniu spoločných pútí a vydávaniu pútnických preukazov, pôsobí so súhlasom a požehnaním Konferencie biskupov Slovenska a zastupuje stredoeurópsky región v španielskej federácii camino spolkov založenej v roku 1987.</p>',
        ],
        1873 => [
            'name' => 'Rímskokatolícka farnosť Košice – Dóm',
            'website' => [null, 'https://domsvalzbety.sk'],
            'email' => [null, 'domsvalzbety@gmail.com'],
            'phone' => [null, '055/62 215 55'],
            'street' => [null, 'Hlavná 26'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Dóm svätej Alžbety v Košiciach, sídlo Rímskokatolíckej farnosti Košice – Dóm, je najväčším kostolom na Slovensku. Jeho výstavba sa začala okolo roku 1380 a trvala viac ako 130 rokov, dokončený bol začiatkom 16. storočia v gotickom slohu. Kostol meria 60 metrov na dĺžku a 36 metrov na šírku, severná veža dosahuje výšku 59 metrov a vnútorný priestor pojme vyše 5000 ľudí.</p><p>Hlavný oltár zasvätený svätej Alžbete obsahuje 48 gotických malieb a patrí medzi najvýznamnejšie gotické pamiatky strednej Európy. Dnes je Dóm katedrálou Košickej arcidiecézy.</p>',
        ],
        1874 => [
            // viacero obcí "Belá" na Slovensku — použitá Belá (okres Žilina), má vlastnú farskú stránku
            'name' => 'Farnosť Belá',
            'website' => [null, 'https://bela.fara.sk'],
            'phone' => [null, '041/56 93 338'],
            'street' => [null, 'Cintorínska 297'], 'postcode' => [null, '013 05'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Belá v okrese Žilina patrí do dekanátu Varín v Žilinskej diecéze. Farským kostolom je Kostol svätej Márie Magdalény, pôvodne neskorogotická stavba z poslednej tretiny 13. storočia, ktorú v roku 1683 v renesančnom slohu dal prestavať nitriansky kanonik a bývalý varínsky farár Michal Szmutko. V roku 1802 udelil pápež Pius VII. kostolu apoštolský breve, na základe ktorého tu veriaci mohli získať plnomocné odpustky.</p><p>K farnosti patrí aj Kaplnka Nepoškvrneného počatia Panny Márie v Kubíkovej z roku 1974. Farnosť má dnes približne 4160 obyvateľov, z toho vyše 3900 rímskych katolíkov.</p>',
        ],
        1876 => [
            'name' => 'Slovenská provincia Spoločnosti Ježišovej',
            'website' => [null, 'https://jezuiti.sk'],
            'email' => [null, 'svkprov@jezuiti.sk'],
            'phone' => [null, '+421 2 59 200 418'],
            'street' => [null, 'Panská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Spoločnosť Ježišovu založil Ignác z Loyoly v roku 1534, pápež Pavol III. rád potvrdil v roku 1540. Prví jezuiti prišli na Slovensko už v polovici 16. storočia a ich prvá komunita pôsobila v Trnave v rokoch 1561 až 1567. Po zrušení rehole v 18. storočí sa jezuiti na Slovensko vrátili v roku 1853, keď v Trnave vznikol noviciátny dom, a o rok neskôr, v roku 1854, aj kolégium v Bratislave.</p><p>Slovenskí jezuiti spočiatku patrili do rakúsko-uhorskej provincie, od roku 1909 do samostatnej uhorskej provincie.</p>',
        ],
        1877 => [
            'name' => 'tím SingleKatolici.sk',
            'country' => [null, 'Slovensko'],
            'body' => '<p>Tím SingleKatolici.sk vznikol okolo roku 2021 s cieľom prepájať aktivity pre slobodných katolíkov na Slovensku a informovať ich o podujatiach určených práve pre nich. Organizuje a propaguje duchovné, formačné a spoločenské podujatia pre single ľudí, vedie ich kalendár a archív a podporuje vznik modlitbových spoločenstiev.</p><p>Stretnutia a aktivity sa konajú vo viacerých slovenských mestách vrátane Žiliny, Bratislavy, Levíc, Brezna a Prešova; v roku 2026 si tím pripomenul päť rokov fungovania.</p>',
        ],
        1878 => [
            'name' => 'Združenie Chemin Neuf',
            'website' => [null, 'https://chemin-neuf.sk'],
            'email' => [null, 'sekretariat@chemin-neuf.sk'],
            'street' => [null, 'Hapákova 7'], 'postcode' => [null, '080 06'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Komunita Chemin Neuf je katolícke spoločenstvo s ekumenickým poslaním, ktoré v roku 1973 v Lyone založil jezuitský kňaz Laurent Fabre. Na Slovensku sa jej začiatky datujú od roku 2000, keď sa konalo prvé stretnutie manželských párov s názvom Kána.</p><p>Slovenská vetva komunity dnes organizuje najmä formačné víkendy a týždne Kána pre manželov, pravidelné modlitbové stretnutia v Prešove a duchovné filmové večery.</p>',
        ],
        1879 => [
            'name' => 'Farnosť Butkov a cementáreň Ladce',
            'website' => [null, 'https://ladce.fara.sk'],
            'street' => [null, 'Farská ulica 151/1'], 'postcode' => [null, '018 63'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Ladce slávnostne zriadil žilinský biskup Mons. Tomáš Galis v roku 2011. Pôvodný farský kostol pochádza z roku 1747, keď ho na starších základoch dalo postaviť panstvo Motešických; v roku 1924 bol adaptovaný na kláštor sestier vincentiek, ktorý zanikol v roku 1950. Nový Farský kostol Božieho milosrdenstva vznikol po požehnaní základného kameňa 15. marca 2014 a žilinský biskup Tomáš Galis ho konsekroval 8. októbra 2016.</p><p>Farnosť spravuje aj pútnické miesto Božieho milosrdenstva na kopci Butkov pri Ladcoch, kde v roku 2013 vybudovala Nadácia AGAPA v priestore bývalého vápencového lomu 12-metrový kríž zakotvený v skale. Podnet naň dostal počas návštevy maltský exorcista páter Elias Vella 9. júla 2012; kríž obsahuje relikviu Kristovho kríža a úlomok skaly z Golgoty a je od roku 2013 cieľom pravidelných pútí.</p>',
        ],
        1880 => [
            'name' => 'Zariadenie pre seniorov a špecializované zariadenie VIVENTI',
            'website' => [null, 'https://viventi.sk'],
            'email' => [null, 'viventi@viventi.sk'],
            'phone' => [null, '0909 250 051'],
            'street' => [null, 'Biskupická 64/C'], 'postcode' => [null, '821 06'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Zariadenie pre seniorov a špecializované zariadenie VIVENTI v Bratislave-Podunajských Biskupiciach otvorila Bratislavská arcidiecéza na prelome rokov 2024 a 2025 ako svoje účelové zariadenie. Jeho poslaním je pomoc, podpora a starostlivosť o seniorov a osoby so zdravotným znevýhodnením, vrátane ľudí s Alzheimerovou a Parkinsonovou chorobou a inými typmi demencie.</p><p>Zariadenie pre seniorov poskytuje starostlivosť 22 klientom v dôchodkovom veku, ktorí potrebujú pomoc inej osoby, a špecializované zariadenie ďalším 17 klientom. Zariadenie vedie riaditeľka JUDr. Bc. Zuzana Bošnáková.</p>',
        ],
        1881 => [
            // "Vrbovské chvály" sa na webe OZ Vykročiť nepodarilo overiť ako súčasť
            'name' => 'Občianske združenie Vykročiť a Vrbovské chvály',
            'email' => [null, 'info@vykrocit.sk'],
            'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Vykročiť je kresťanské spoločenstvo, ktorého poslaním je sprevádzať mužov a ženy na ceste k hlbšiemu a autentickejšiemu vzťahu s Bohom. Medzi jeho hlavné aktivity patrí Mužská ekumenická konferencia určená mužom a stretnutia žien s názvom Pri studni.</p>',
        ],
        1882 => [
            'name' => 'Vydavateľstvo novaJAR',
            'website' => [null, 'https://novajar.sk'],
            'email' => [null, 'info@novajar.sk'],
            'phone' => [null, '+421 915 874 590'],
            'street' => [null, 'Šrobárova 2668/19'], 'postcode' => [null, '058 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Vydavateľstvo novaJAR so sídlom v Poprade založila Jaroslava Pytelová s cieľom prinášať hodnotné knihy a video materiály, ktoré čitateľov približujú k pravde, kráse a dobru. Vlajkovým projektom vydavateľstva je Biblia za rok, sprevádzaná podcastom a sprievodnými materiálmi.</p><p>Vydavateľstvo vydalo aj tituly Raduj sa!, Sprievodca Pôstom a Sprievodca Narniou a okrem kníh pripravuje podcasty a komunitné podujatia a diskusie.</p>',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::CANALS as $id => $row) {
            $this->applyContacts($id, $row, forward: true);
            $this->applyBody($id, $row);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::CANALS as $id => $row) {
            $this->applyContacts($id, $row, forward: false);
            // body sa zámerne nevracia späť (jednosmerný prepis)
        }
    }

    private function applyContacts(int $id, array $row, bool $forward): void
    {
        $fields = ['website', 'email', 'phone', 'street', 'postcode', 'country'];
        $query = DB::table('canals')->where('id', $id)->where('name', $row['name']);
        $update = [];
        $touched = false;

        foreach ($fields as $field) {
            if (! isset($row[$field])) {
                continue;
            }

            [$old, $new] = $row[$field];
            $from = $forward ? $old : $new;
            $to = $forward ? $new : $old;

            $from === null
                ? $query->whereNull($field)
                : $query->where($field, $from);

            $update[$field] = $to;
            $touched = true;
        }

        if ($touched) {
            $update['updated_at'] = now();
            $query->update($update);
        }
    }

    private function applyBody(int $id, array $row): void
    {
        if (! isset($row['body'])) {
            return;
        }

        DB::table('canals')->where('id', $id)->where('name', $row['name'])
            ->whereNotNull('body')
            ->update(['body' => $row['body'], 'updated_at' => now()]);
    }
};
