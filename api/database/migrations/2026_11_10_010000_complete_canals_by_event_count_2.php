<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Šiesta dávka (november 2026): ďalších 200 kanálov podľa počtu akcií (2 – 4)
 * všetkého, čo obsahuje formulár kanála. Všeobecné jednoodsekové popisy
 * (staršie AI texty bez overených faktov) sa prepíšu na tri odseky podľa
 * podujatí a webu; bohatým dvojodsekovým popisom sa len pridá tretí odsek.
 *
 * Každý kanál môže dostať:
 *  - popis v troch odsekoch (kto je, čo pripravuje, komu je kanál určený),
 *    len ak má stále zástupný text,
 *  - web a telefón do prázdneho poľa,
 *  - e-mail cez CanalEmails::discover (adresa doslova na stránke `found_on`),
 *  - adresu sídla z kontaktnej stránky organizácie so súradnicami
 *    z Nominatim/OpenStreetMap (overené: ulica aj orientačné číslo sedia).
 *    Zapíše sa len kanálu s polohou na stred obce alebo Slovenska a ulica
 *    len do prázdneho poľa; obec len keď kanál mal „Celé Slovensko".
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        44 => [
            'name' => 'Mesto Bardejov',
            'address' => [
                'street' => 'Radničné námestie 16',
                'postcode' => '085 01',
                'municipality_id' => 80,
                'latitude' => 49.2922162,
                'longitude' => 21.2764475,
            ],
        ],
        118 => [
            'name' => 'Farnosť Krivá',
            'phone' => '0907 965 241',
        ],
        121 => [
            'name' => 'Spoločenstvo SION',
            'phone' => '+421 904 343 305',
        ],
        145 => [
            'name' => 'OOCR Turiec',
            'address' => [
                'street' => 'Nám. S. H. Vajanského 1',
                'postcode' => '036 49',
                'municipality_id' => 2119,
                'latitude' => 49.0684556,
                'longitude' => 18.9257016,
            ],
        ],
        147 => [
            'name' => 'Prešovská gotika, o.z.',
            'address' => [
                'street' => 'Jarková 77B',
                'postcode' => '080 01',
                'municipality_id' => 2822,
                'latitude' => 48.9975953,
                'longitude' => 21.2376793,
            ],
        ],
        235 => [
            'name' => 'Nitrianske biskupstvo s farnosťou sv. Urbana',
            'address' => [
                'street' => 'Nám. Jána Pavla II. 7',
                'postcode' => null,
                'municipality_id' => 2333,
                'latitude' => 48.3184475,
                'longitude' => 18.0863993,
            ],
        ],
        241 => [
            'name' => 'Mesto Nitra',
            'address' => [
                'street' => 'Štefánikova trieda 60',
                'postcode' => '950 06',
                'municipality_id' => 2333,
                'latitude' => 48.305636,
                'longitude' => 18.0838037,
            ],
        ],
        261 => [
            'name' => 'Biskupstvo Banská Bystrica',
            'address' => [
                'street' => 'Námestie SNP 19',
                'postcode' => '975 90',
                'municipality_id' => 73,
                'latitude' => 48.7359372,
                'longitude' => 19.1447913,
            ],
        ],
        326 => [
            'name' => 'Trenčiansky samosprávny kraj',
            'address' => [
                'street' => 'K dolnej stanici 7282/20A',
                'postcode' => '911 01',
                'municipality_id' => 3592,
                'latitude' => 48.887335,
                'longitude' => 18.0335441,
            ],
        ],
        354 => [
            'name' => 'Bratia kapucíni',
            'address' => [
                'street' => 'Župné námestie 10',
                'postcode' => '814 99',
                'municipality_id' => 242,
                'latitude' => 48.1454162,
                'longitude' => 17.1050719,
            ],
        ],
        369 => [
            'name' => 'Farnosť sv. Košických mučeníkov',
            'body' => [
                0 => 'Farnosť sv. Košických mučeníkov vo Fričovciach je rímskokatolícka farnosť v Prešovskom okrese. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Pripomína si aj osobnosti spojené s obcou.',
                1 => 'Pripravila kolokvium k 130. výročiu narodenia cirkevného historika a profesora Jozefa Špirka spojené s predstavením novej knihy. Spomienky približujú jeho život a dielo. Sú otvorené aj pre hostí z okolia.',
                2 => 'Kanál je určený veriacim z Fričoviec a všetkým, ktorých zaujímajú cirkevné dejiny. Sledovaním budete vedieť o podujatiach farnosti. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        420 => [
            'name' => 'farnosť Černová',
            'phone' => '+421 908 499 144',
        ],
        421 => [
            'name' => 'Združenie kresťanských seniorov Slovenska',
            'phone' => '0905 635 387',
            'address' => [
                'street' => 'Šafárikovo námestie 4',
                'postcode' => '811 02',
                'municipality_id' => 242,
                'latitude' => 48.1414892,
                'longitude' => 17.116786,
            ],
        ],
        427 => [
            'name' => 'Dubova Colonorum',
            'phone' => '+421 918 590 961',
        ],
        460 => [
            'name' => 'Univerzita tretieho veku na Teologickej fakulte Trnavskej univerzity',
            'body' => [
                0 => 'Univerzita tretieho veku pôsobí pri Teologickej fakulte Trnavskej univerzity v Bratislave. Ponúka seniorom štúdium teológie a príbuzných odborov. Podporuje celoživotné vzdelávanie.',
                1 => 'Každý rok otvára nové kurzy a prihlasovanie na štúdium. Prednášky vedú pedagógovia fakulty. Štúdium trvá niekoľko semestrov.',
                2 => 'Kanál je určený seniorom, ktorí sa chcú vzdelávať. Sledovaním budete vedieť o otvorení nových kurzov. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        477 => [
            'name' => 'Vydavateľstvo Dobrá kniha',
            'phone' => '0911 118 411',
            'address' => [
                'street' => 'Štefánikova 44',
                'postcode' => '917 01',
                'municipality_id' => 3596,
                'latitude' => 48.3785263,
                'longitude' => 17.5847852,
            ],
        ],
        613 => [
            'name' => 'tešitelia z Getseman',
            'body' => [
                0 => 'Tešitelia z Getseman sú rehoľná komunita v Marianke. Marianka je najstarším pútnickým miestom na Slovensku. Tešitelia sa starajú o pútnikov.',
                1 => 'Každoročne pripravujú mariánsku púť do Marianky, na ktorej predsedajú aj vzácni hostia, napríklad kardinál Duka. Púť sa koná cez víkend v septembri. Program spája sväté omše, modlitbu a sviatosť zmierenia.',
                2 => 'Kanál je určený pútnikom z Bratislavy a okolia. Sledovaním budete vedieť o programe pútí. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        634 => [
            'name' => 'Farnosť Bolešov',
            'body' => [
                0 => 'Farnosť Bolešov je rímskokatolícka farnosť v okrese Ilava. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Stará sa aj o starobylý kostolík v Pominovci.',
                1 => 'Každoročne v auguste pozýva na cyklopúť k románskemu Kostolu sv. Jána Krstiteľa v Pominovci. Púť spája pohyb, modlitbu a spoznávanie pamiatky. Zúčastňujú sa na nej rodiny aj jednotlivci.',
                2 => 'Kanál je určený veriacim a cyklistom z Bolešova a okolia. Sledovaním budete vedieť o termíne cyklopúte. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        655 => [
            'name' => 'Rímskokatolícka farnosť Skalka nad Váhom',
            'body' => [
                0 => 'Rímskokatolícka farnosť Skalka nad Váhom pôsobí pri pútnickom mieste Skalka. Na Veľkej Skalke sa nachádza historický kláštor. Farnosť sa podieľa na jeho oživení.',
                1 => 'Pripravuje nočné prehliadky kláštora na Veľkej Skalke a výstavy, napríklad ORA ET ARS. Organizuje aj stretnutia mládeže na Skalke. Podujatia spájajú históriu, umenie a vieru.',
                2 => 'Kanál je určený pútnikom a návštevníkom Skalky. Sledovaním budete vedieť o prehliadkach a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        657 => [
            'name' => 'Farnosť Trstená',
            'body' => [
                0 => 'Farnosť Trstená je rímskokatolícka farnosť na Orave. Pôsobí v meste Trstená. Je známa tradičnou škapuliarskou slávnosťou.',
                1 => 'Spolu s mestom Trstená každoročne v júli pozýva na slávnosť Panny Márie Škapuliarskej. Odpust spája sväté omše, procesiu a stretnutie farníkov. Prichádzajú naň aj veriaci z okolia.',
                2 => 'Kanál je určený veriacim z Trstenej a Oravy. Sledovaním budete vedieť o odpustoch a slávnostiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        663 => [
            'name' => 'OZ Za krajšiu Radavu',
            'body' => [
                0 => 'OZ Za krajšiu Radavu je občianske združenie z obce Radava. Pestuje cyrilo-metodské tradície a miestnu kultúru. Pripravuje spomienkové slávnosti.',
                1 => 'Organizuje oslavy sv. Cyrila a Metoda v Radave a Memoriál pátra Šebastiána Labu. Spolupracuje aj na spomienkovej slávnosti na sv. Metoda v Dolnom Ohaji. Podujatia spájajú bohoslužbu a kultúrny program.',
                2 => 'Kanál je určený obyvateľom Radavy a okolia. Sledovaním budete vedieť o ďalších slávnostiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        694 => [
            'name' => 'Konferencia vyšších rehoľných predstavených na Slovensku',
            'address' => [
                'street' => 'Gajova 8',
                'postcode' => '811 09',
                'municipality_id' => 242,
                'latitude' => 48.144247,
                'longitude' => 17.1202175,
            ],
            'body' => [
                0 => 'Konferencia vyšších rehoľných predstavených na Slovensku združuje predstavených reholí. Koordinuje ich spoluprácu a spoločné iniciatívy. Zastupuje rehoľníkov voči Cirkvi aj verejnosti.',
                1 => 'Pripravila Celoslovenskú jubilejnú púť zasvätených v Hronskom Beňadiku a formačné víkendy pre sestry Umenie starnúť v komunite. Organizuje aj spoločenské podujatia, napríklad hokejový turnaj kňazov a rehoľníkov. Podujatia posilňujú spoločenstvo zasvätených.',
                2 => 'Kanál je určený rehoľníkom, rehoľným sestrám a ich priateľom. Sledovaním budete vedieť o pútiach a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        704 => [
            'name' => 'Jezuiti a TF TU',
            'body' => [
                0 => 'Jezuiti spolupracujú s Teologickou fakultou Trnavskej univerzity v Bratislave. Spoločne pripravujú prednášky a duchovné programy. Spájajú akademický a duchovný rozmer.',
                1 => 'Pripravili sériu prednášok a duchovný večer počas Jubilejného roka. Organizovali aj konferenciu Pomoc stabilite rodiny. Jezuiti v Prešove pozývajú na obnovu pred sviatkom sv. Ignáca.',
                2 => 'Kanál je určený študentom a veriacim, ktorých zaujíma teológia. Sledovaním budete vedieť o prednáškach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        705 => [
            'name' => 'Farnosť sv. Kataríny Alexandrijskej',
            'body' => [
                0 => 'Farnosť sv. Kataríny Alexandrijskej pôsobí v Štefanove. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Venuje sa najmä rodinám.',
                1 => 'Každoročne pripravuje novénu za rodinu k sv. Jánovi Pavlovi II., ktorá má už viac ako desať ročníkov. Farnosť sa zapája aj do pútí rodín. Podujatia spájajú modlitbu a spoločenstvo.',
                2 => 'Kanál je určený rodinám zo Štefanova a okolia. Sledovaním budete vedieť o novénach a pútiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        730 => [
            'name' => 'Rímskokatolícky farský úrad Branč',
            'address' => [
                'street' => 'Cetínska 44/1',
                'postcode' => '951 13',
                'municipality_id' => 240,
                'latitude' => 48.2119502,
                'longitude' => 18.1491649,
            ],
            'append' => 'Farnosť pripravuje aj spomienkové slávnosti, napríklad k výročiu úmrtia sv. Metoda či k výročiu kňaza a básnika Janka Silana. Kanál je určený veriacim z Branča a okolia. Podrobnosti nájdete vždy v detaile podujatia.',
        ],
        768 => [
            'name' => 'OZ Domov je prístav',
            'body' => [
                0 => 'OZ Domov je prístav je občianske združenie, ktoré pripravuje duchovné programy. Venuje sa vnútornému uzdraveniu. Program ponúka verejnosti.',
                1 => 'Organizuje adorácie, napríklad Adorácia za vnútorné uzdravenie so sv. Jánom Pavlom II. či Ak chceš lietať. Pripravuje aj stretnutia o odpustení ako kľúči k vnútornej slobode. Stretnutia sú otvorené pre všetkých.',
                2 => 'Kanál je určený všetkým, ktorí hľadajú duchovné uzdravenie. Sledovaním budete vedieť o ďalších adoráciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        788 => [
            'name' => 'Centrum pastorácie mládeže v Spišskej diecéze',
            'body' => [
                0 => 'Centrum pastorácie mládeže v Spišskej diecéze pripravuje programy pre mladých na Spiši. Spolupracuje s farnosťami a dekanátmi. Organizuje diecézne aj dekanátne podujatia.',
                1 => 'Pripravuje dekanátne stretnutia mladých pri príležitosti Svetového dňa mládeže a diecézne výstupy mladých. Stretnutia sa konajú vo viacerých mestách diecézy. Spájajú modlitbu, svedectvá a spoločenstvo.',
                2 => 'Kanál je určený mladým zo Spišskej diecézy. Sledovaním budete vedieť o stretnutiach a výstupoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        790 => [
            'name' => 'katolícka iniciatíva Divine Renovation (Premena farnosti)',
            'append' => 'Iniciatíva pripravuje online diskusie o obnove farností a podujatia, napríklad Ako prinášať Krista v dnešnej dobe. Kanál je určený kňazom a farským tímom. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        798 => [
            'name' => 'Spolok Slovákov v Poľsku',
            'address' => [
                'street' => 'Martinengova 4880/26',
                'postcode' => '811 02',
                'municipality_id' => 242,
                'latitude' => 48.1593195,
                'longitude' => 17.0777758,
            ],
            'body' => [
                0 => 'Spolok Slovákov v Poľsku spája slovenskú menšinu v Poľsku. Pestuje slovenskú kultúru a tradície. Spolupracuje so Slovenskom.',
                1 => 'Spolupracoval na výstave ilustrácií Martina Oscitého Tajomstvo Zoborského kláštora o sv. Gorazdovi v Krakove. Výstava priblížila cyrilo-metodské dedičstvo. Podujatia spájajú Slovákov doma aj v zahraničí.',
                2 => 'Kanál je určený Slovákom v Poľsku a ich priateľom. Sledovaním budete vedieť o výstavách a podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        801 => [
            'name' => 'Ruský dom v Bratislave',
            'body' => [
                0 => 'Ruský dom v Bratislave je kultúrne centrum, ktoré prezentuje ruskú kultúru. Pripravuje výstavy a prednášky. Venuje sa aj slovanskému dedičstvu.',
                1 => 'Hostil výstavy Tajomstvo Zoborského kláštora a Obrazy svätých Cyrila a Metoda na ilustráciách, ikonách a poštových známkach. Pripravil aj prednášku Antona Hrnka o slovanských vierozvestcoch. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený záujemcom o kultúru a dejiny. Sledovaním budete vedieť o výstavách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        806 => [
            'name' => 'Generálny sekretariát Konferencie biskupov Slovenska (KBS)',
            'body' => [
                0 => 'Generálny sekretariát Konferencie biskupov Slovenska zabezpečuje činnosť KBS. Koordinuje spoločné iniciatívy biskupov. Pripravuje celoslovenské stretnutia.',
                1 => 'Organizuje stretnutia biskupov s laikmi a zasvätenými, napríklad Cestu nádeje v Nimnici v Jubilejnom roku. Stretnutia majú už niekoľko ročníkov. Spájajú biskupov, kňazov a laikov.',
                2 => 'Kanál je určený veriacim a pracovníkom Cirkvi. Sledovaním budete vedieť o celoslovenských stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        840 => [
            'name' => 'Česká biskupská konferencia',
            'body' => [
                0 => 'Česká biskupská konferencia združuje katolíckych biskupov v Českej republike. Koordinuje činnosť diecéz. Vyjadruje sa k udalostiam v spoločnosti.',
                1 => 'Vyzvala k zvoneniu zvonov na pamiatku obetí tragédie v Prahe aj 17. novembra. Podporila udelenie čestného doktorátu kardinálovi Kurtovi Kochovi na Karlovej univerzite. Jej iniciatívy prinášajú aj slovenské médiá.',
                2 => 'Kanál je určený veriacim, ktorých zaujíma Cirkev v Česku. Sledovaním budete vedieť o iniciatívach biskupov. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        846 => [
            'name' => 'Pontes (Hnutie fokoláre)',
            'body' => [
                0 => 'Pontes je slovenská vetva Hnutia fokoláre. Hnutie sa usiluje o jednotu a bratstvo. Spája ľudí rôzneho veku a povolaní.',
                1 => 'Každé leto pripravuje stretnutie Mariapoli v Ľubovnianskych kúpeľoch. Podporuje aj iniciatívu Františkova ekonomika, ktorá spája vieru so starostlivosťou o svet. Program je otvorený pre rodiny aj jednotlivcov.',
                2 => 'Kanál je určený všetkým, ktorým je blízka spiritualita jednoty. Sledovaním budete vedieť o Mariapoli a ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        858 => [
            'name' => 'Rímskokatolícka farnosť Kráľovnej Pokoja',
            'body' => [
                0 => 'Rímskokatolícka farnosť Kráľovnej pokoja pôsobí v Košiciach. Zabezpečuje bohoslužby a pastoráciu. Spolupracuje s hnutiami a spoločenstvami.',
                1 => 'S Hnutím Svetlo – Život pripravuje adventné večery. Organizovala farské misie. Požehnala aj Božiu muku sv. Floriána a sv. Jána Nepomuckého.',
                2 => 'Kanál je určený veriacim z Košíc. Sledovaním budete vedieť o podujatiach farnosti. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        861 => [
            'name' => 'Tomistický inštitút Slovensko',
            'body' => [
                0 => 'Tomistický inštitút Slovensko približuje mladým myslenie sv. Tomáša Akvinského. Pripravuje vzdelávacie školy. Spája filozofiu, teológiu a vieru.',
                1 => 'Organizuje stredoškolské tomistické školy, napríklad na tému Poznať a milovať Boha alebo o modlitbe. Pripravuje aj zimnú tomistickú školu o viere Boh, Alfa a Omega. Školy sú určené študentom.',
                2 => 'Kanál je určený stredoškolákom a študentom. Sledovaním budete vedieť o nových školách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        862 => [
            'name' => 'Katedra spoločenských a humanitných vied',
            'body' => [
                0 => 'Katedra spoločenských a humanitných vied pôsobí v Prešove. Venuje sa vzdelávaniu a výskumu. Pripravuje prednášky pre verejnosť.',
                1 => 'Organizuje prednášky, napríklad cyklus Človek vo víre premien, a diskusie o animácii voľného času. Venovala sa aj výučbe náboženstva metódou Márie Montessori. Prednášky sú otvorené pre študentov aj verejnosť.',
                2 => 'Kanál je určený študentom a pedagógom. Sledovaním budete vedieť o prednáškach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        914 => [
            'name' => 'Spoločenstvo Lachaj Roi',
            'phone' => '+421 905 478 551',
        ],
        921 => [
            'name' => 'farský úrad Slovenská Ves',
            'append' => 'Farnosť pripravuje aj spomienky na rodákov, napríklad na biskupa Štefana Barnáša, a podieľa sa na podujatí Gladioly v kežmarskej bazilike. Kanál je určený veriacim zo Slovenskej Vsi, Vojnian a Výbornej. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        925 => [
            'name' => 'Súkromná základná škola Citadela',
            'body' => [
                0 => 'Súkromná základná škola Citadela pôsobí v Bratislave. Vychádza z princípov klasického vzdelávania. Rozvíja aj prípravu pedagógov.',
                1 => 'Otvára Pedagogickú klasickú akadémiu pre záujemcov o učiteľstvo. Organizuje letné tábory, napríklad Noemova archa, a dni otvorených dverí. Podujatia sú otvorené rodičom a deťom.',
                2 => 'Kanál je určený rodičom, žiakom a pedagógom. Sledovaním budete vedieť o akadémii a táboroch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        930 => [
            'name' => 'Incheba Expo Bratislava',
            'address' => [
                'street' => 'Viedenská cesta 3',
                'postcode' => '851 01',
                'municipality_id' => 242,
                'latitude' => 48.1360775,
                'longitude' => 17.1034468,
            ],
            'body' => [
                0 => 'Incheba Expo Bratislava je výstavný a kongresový areál v Bratislave. Organizuje veľtrhy a výstavy. Hostí aj odborné podujatia.',
                1 => 'Organizuje medzinárodnú výstavu Sacroexpo Slovakia pre Cirkev, cirkevné umenie a služby. Výstava sa koná v septembri. Nadväzuje na Sacroexpo v poľských Kielcach.',
                2 => 'Kanál je určený farnostiam, rehoľným komunitám a dodávateľom. Sledovaním budete vedieť o termíne výstavy. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        932 => [
            'name' => 'Farnosť Domaniža',
            'body' => [
                0 => 'Farnosť Domaniža je rímskokatolícka farnosť v Považskobystrickom okrese. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Je známa mariánskymi púťami.',
                1 => 'Každoročne pripravuje púť k Panne Márii Karmelskej a púť k Nanebovzatej Panne Márii. Púte sa konajú v lete. Spájajú sväté omše, modlitbu a stretnutie.',
                2 => 'Kanál je určený veriacim z Domaniže a okolia. Sledovaním budete vedieť o pútiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        967 => [
            'name' => 'Nadácia VÚB',
            'phone' => '0918 386 902',
            'address' => [
                'street' => 'Mlynské nivy 1',
                'postcode' => '829 90',
                'municipality_id' => 242,
                'latitude' => 48.1469241,
                'longitude' => 17.1250964,
            ],
            'body' => [
                0 => 'Nadácia VÚB podporuje ochranu životného prostredia, kultúrne dedičstvo a talenty v umení. Pomáha aj ľuďom v núdzi. Pôsobí po celom Slovensku.',
                1 => 'Každoročne vyhlasuje iniciatívu Poklady Slovenska na obnovu pamiatok. O tom, ktorá zo šiestich pamiatok získa grant, rozhoduje verejnosť hlasovaním. Mnohé z pamiatok sú kostoly.',
                2 => 'Kanál je určený farnostiam, obciam a všetkým, ktorým záleží na pamiatkach. Sledovaním budete vedieť o hlasovaní a výzvach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        980 => [
            'name' => 'Minoriti Brehov',
            'body' => [
                0 => 'Minoriti v Brehove spravujú kláštor a pútnické miesto v obci Brehov. Patria do rehole minoritov. Ponúkajú duchovné programy.',
                1 => 'Pozývajú na jubilejné pobožnosti do Brehova. Pripravujú rodičovské večery Ako byť lepším rodičom a miništrantské tábory. Program je určený rodinám aj deťom.',
                2 => 'Kanál je určený veriacim z okolia Brehova. Sledovaním budete vedieť o pobožnostiach a programoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        984 => [
            'name' => 'Univerzita sv. Cyrila a Metoda',
            'body' => [
                0 => 'Univerzita sv. Cyrila a Metoda sídli v Trnave. Ponúka štúdium humanitných, prírodných a spoločenských vied. Spolupracuje s Trnavskou univerzitou.',
                1 => 'Spolu s Trnavskou univerzitou pripravuje letnú detskú univerzitu. Spolupodieľala sa aj na slávnostných akademických podujatiach v Trnave. Podujatia sú určené deťom, študentom aj verejnosti.',
                2 => 'Kanál je určený študentom a rodinám z Trnavy. Sledovaním budete vedieť o univerzitných podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        986 => [
            'name' => 'Farnosť Horné Orešany',
            'body' => [
                0 => 'Farnosť Horné Orešany je rímskokatolícka farnosť v Trnavskom okrese. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Je spojená s úctou k sv. Jánovi Pavlovi II.',
                1 => 'Pripravuje púť rodín a ctiteľov sv. Jána Pavla II. do Šaštína. Púť sa koná na jar. Spája rodiny z farnosti aj z okolia.',
                2 => 'Kanál je určený rodinám z Horných Orešian a okolia. Sledovaním budete vedieť o termíne púte. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        990 => [
            'name' => 'Farnosť Nitra – Janíkovce',
            'body' => [
                0 => 'Farnosť Nitra – Janíkovce je rímskokatolícka farnosť v Nitrianskej diecéze. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Stará sa o kaplnku Fatimskej Panny Márie Pri Dube.',
                1 => 'Každoročne pozýva na mariánsku púť ku kaplnke Pri Dube. Pripravila aj požehnanie pomníka nenarodeným deťom. Podujatia sú otvorené aj pre veriacich z Nitry.',
                2 => 'Kanál je určený veriacim z Janíkoviec a Nitry. Sledovaním budete vedieť o pútiach a slávnostiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        991 => [
            'name' => 'Rektorát Baziliky sv. Benedikta opáta',
            'body' => [
                0 => 'Rektorát Baziliky sv. Benedikta opáta spravuje baziliku v Hronskom Beňadiku. Kláštor patrí medzi najvýznamnejšie pamiatky Slovenska. Pri bazilike pôsobí Bratstvo Kristovej krvi.',
                1 => 'Pripravuje púte, napríklad púť historickej Tekovskej župy ku kaplnke. Slávi výročie obnovenia Bratstva Kristovej krvi a prijíma nových členov. Program spája sväté omše a modlitbu.',
                2 => 'Kanál je určený pútnikom a ctiteľom Kristovej krvi. Sledovaním budete vedieť o pútiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        998 => [
            'name' => 'Bazilika Sedembolestnej Panny Márie v Šaštíne',
            'replace_text' => [
                'Baziliku spravujú saleziáni.' => 'Baziliku spravujú otcovia pavlíni.',
            ],
        ],
        1003 => [
            'name' => 'Domček bl. Anny Kolesárovej',
            'body' => [
                0 => 'Domček bl. Anny Kolesárovej je pastoračné centrum vo Vysokej nad Uhom, rodisku blahoslavenej Anny. Pripravuje programy pre mladých aj rodiny. Je cieľom pútí.',
                1 => 'Každoročne organizuje Púť radosti, ktorá má už vyše sto ročníkov, Silvester pre mladých a stretnutia U Jozefa. Program spája modlitbu, svedectvá a spoločenstvo. Prichádzajú naň mladí z celého Slovenska.',
                2 => 'Kanál je určený mladým a pútnikom. Sledovaním budete vedieť o pútiach a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1016 => [
            'name' => 'Oratórium sv. Filipa Neriho pri Farnosti Svätej rodiny',
            'body' => [
                0 => 'Oratórium sv. Filipa Neriho pôsobí pri Farnosti Svätej rodiny v Petržalke. Inšpiruje sa odkazom sv. Filipa Neriho. Pripravuje duchovné a pútnické programy.',
                1 => 'Pozýva na tradičnú návštevu siedmich kostolov Bratislavy. Pripravuje aj 40-hodinovú adoráciu v Kostole Svätej rodiny. Program je otvorený pre všetkých.',
                2 => 'Kanál je určený veriacim z Petržalky a Bratislavy. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1023 => [
            'name' => 'Gospelová muzika, o.z',
            'body' => [
                0 => 'Gospelová muzika je občianske združenie z Námestova. Organizuje festival Verím Pane. Festival patrí medzi najväčšie kresťanské hudobné podujatia na Slovensku.',
                1 => 'Festival Verím Pane sa koná každé leto na nábreží Oravskej priehrady v Námestove. Program spája koncerty, sväté omše, svedectvá a program pre deti. Prichádzajú naň tisíce návštevníkov.',
                2 => 'Kanál je určený fanúšikom kresťanskej hudby a rodinám. Sledovaním budete vedieť o programe festivalu. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1077 => [
            'name' => 'Farnosť Kalvária',
            'body' => [
                0 => 'Farnosť Kalvária v Bratislave spravujú bratia dominikáni. Pripravuje programy aj pre slobodných. Je otvorená pre veriacich z celého mesta.',
                1 => 'Organizuje stretnutia pre slobodných, napríklad grilovačku, guláš či dobročinnú kapustnicu u dominikánov. Stretnutia spájajú spoločenstvo a dobrú vec. Sú otvorené pre nových ľudí.',
                2 => 'Kanál je určený slobodným a veriacim z Bratislavy. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1082 => [
            'name' => 'Opus Dei',
            'body' => [
                0 => 'Opus Dei je osobná prelatúra Katolíckej cirkvi. Pomáha ľuďom hľadať svätosť v práci a každodennom živote. Pôsobí aj na Slovensku.',
                1 => 'Ponúka duchovné obnovy, vrátane obnovy doma. Jej členovia prijímajú diakonát v rímskej Bazilike sv. Eugena. Informácie o podujatiach prináša aj slovenským veriacim.',
                2 => 'Kanál je určený veriacim, ktorí chcú žiť vieru v práci a rodine. Sledovaním budete vedieť o obnovách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1087 => [
            'name' => 'Dom úľavy sv. Charbela',
            'phone' => '0918 882 868',
            'body' => [
                0 => 'Dom úľavy sv. Charbela v Starej Ľubovni ponúka duchovné obnovy. Venuje sa ľuďom, ktorí hľadajú uzdravenie a oslobodenie. Inšpiruje sa sv. Charbelom.',
                1 => 'Pripravuje obnovy, napríklad Uzdravenie zo závislosti, Oslobodenie od úzkosti a strachu či obnovu pre rodičov s o. Marettom. Obnovy vedú skúsení kňazi. Trvajú zvyčajne cez víkend.',
                2 => 'Kanál je určený všetkým, ktorí hľadajú duchovnú pomoc. Sledovaním budete vedieť o nových obnovách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1106 => [
            'name' => 'Bratislavské UPeCe',
            'body' => [
                0 => 'Bratislavské UPeCe je univerzitné pastoračné centrum pre študentov v Bratislave. Ponúka duchovný program aj formáciu. Pripravuje diskusie a adorácie.',
                1 => 'Organizuje Bioetické večery, trojdňové adorácie a formáciu Záchranný koráb – rodina. Program je určený študentom aj mladým rodinám. Stretnutia sa konajú v priestoroch UPeCe.',
                2 => 'Kanál je určený vysokoškolákom a mladým. Sledovaním budete vedieť o diskusiách a adoráciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1107 => [
            'name' => 'Ministerstvo kultúry SR',
            'body' => [
                0 => 'Ministerstvo kultúry SR zodpovedá za kultúrnu politiku a ochranu pamiatok. Vypisuje dotačné programy. Oceňuje obnovu kultúrnych pamiatok.',
                1 => 'Vyhlasuje výzvy v programe Obnovme si svoj dom a súťaž Kultúrna pamiatka roka – Fénix. Do súťaže sa zapájajú aj cirkevné pamiatky. Program pomáha vlastníkom pamiatok.',
                2 => 'Kanál je určený farnostiam, obciam a vlastníkom pamiatok. Sledovaním budete vedieť o výzvach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1127 => [
            'name' => 'Misijná kongregácia služobníc Ducha Svätého',
            'phone' => '0903 983 323',
            'body' => [
                0 => 'Misijná kongregácia služobníc Ducha Svätého je medzinárodná rehoľa misijných sestier. Na Slovensku má viacero komunít. Venuje sa misiám a formácii.',
                1 => 'Ponúka program misijného dobrovoľníctva spojený s pobytom v misiách. Pripravuje aj jubilejné podujatia kongregácie. Programy sú určené mladým, ktorí chcú pomáhať.',
                2 => 'Kanál je určený mladým a priaznivcom misií. Sledovaním budete vedieť o dobrovoľníckych programoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1128 => [
            'name' => 'Združenie Stretnutie chrámových hudobníkov',
            'phone' => '+421 910 345 426',
            'body' => [
                0 => 'Združenie Stretnutie chrámových hudobníkov podporuje cirkevnú hudbu na Slovensku. Spája organistov, kantorov a zbory. Pripravuje festivaly.',
                1 => 'Organizuje festival chrámovej hudby Musica Sacra Samariensis v Šamoríne a festival v Senci. Vystupujú na nich aj študenti cirkevného konzervatória. Festivaly sa konajú na jeseň.',
                2 => 'Kanál je určený chrámovým hudobníkom a milovníkom sakrálnej hudby. Sledovaním budete vedieť o festivaloch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1166 => [
            'name' => 'Pútnické miesto Skalka pri Trenčíne',
            'body' => [
                0 => 'Pútnické miesto Skalka pri Trenčíne je spojené so svätými Andrejom-Svoradom a Beňadikom. Nachádza sa nad Váhom pri Trenčíne. Púte sem prichádzajú celý rok.',
                1 => 'Pripravuje viacero pútí, napríklad Púť zdravotníkov, a stretnutia mladých DOMa NA SKALkE. Program spája sväté omše, modlitbu a putovanie. Je otvorený pre všetkých.',
                2 => 'Kanál je určený pútnikom a mladým. Sledovaním budete vedieť o pútiach a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1191 => [
            'name' => 'Rehoľa milosrdných bratov',
            'body' => [
                0 => 'Rehoľa milosrdných bratov sa venuje chorým a núdznym. V Bratislave spravuje nemocnicu a kostol. Pripravuje aj kultúrne podujatia.',
                1 => 'Pokračuje v tradícii letných koncertov na podporu organa. Pozýva na sväté omše pri sviatkoch, napríklad patrónky chorých či bl. Karola Habsburského. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený veriacim a milovníkom organovej hudby. Sledovaním budete vedieť o koncertoch a slávnostiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1195 => [
            'name' => 'Železničná spoločnosť Slovensko, a. s',
            'emails' => [
                0 => [
                    'email' => 'info@slovakrail.sk',
                    'found_on' => 'https://www.zssk.sk',
                ],
            ],
            'address' => [
                'street' => 'Rožňavská 1',
                'postcode' => '832 72',
                'municipality_id' => 242,
                'latitude' => 48.1659472,
                'longitude' => 17.1548221,
            ],
        ],
        1197 => [
            'name' => 'Rehoľa Pavlínov',
            'phone' => '0903 160 904',
            'emails' => [
                0 => [
                    'email' => 'spravca@bazilika.sk',
                    'found_on' => 'https://www.bazilika.sk/kontakt/bazilika',
                ],
            ],
            'body' => [
                0 => 'Rehoľa pavlínov spravuje Národnú svätyňu Sedembolestnej Panny Márie v Šaštíne. Pavlíni sú jediná rehoľa, ktorá vznikla v Uhorsku. Starajú sa o pútnikov.',
                1 => 'Pripravujú novénu pred Národnou púťou k Sedembolestnej a jej program. Prenášajú aj sväté omše a modlitby zo Šaštína. Púte prichádzajú z celého Slovenska.',
                2 => 'Kanál je určený pútnikom a ctiteľom Sedembolestnej. Sledovaním budete vedieť o programe púte. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1200 => [
            'name' => 'Rímskokatolícka farnosť Topoľčany',
            'body' => [
                0 => 'Rímskokatolícka farnosť Topoľčany pôsobí v Nitrianskej diecéze. Je známa mariánskou púťou. Zabezpečuje bohoslužby a pastoráciu.',
                1 => 'Každoročne koncom augusta a začiatkom septembra pripravuje mariánsku púť v Topoľčanoch. Počas púte je možné získať úplné odpustky. Program trvá niekoľko dní.',
                2 => 'Kanál je určený veriacim z Topoľčian a pútnikom. Sledovaním budete vedieť o programe púte. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1212 => [
            'name' => 'Arcibratstvo sv. Filomény',
            'body' => [
                0 => 'Arcibratstvo sv. Filomény šíri úctu k sv. Filoméne. Na Slovensku pôsobí v Spišskom Podhradí. Pripravuje púte.',
                1 => 'Každoročne organizuje púť k sv. Filoméne v Spišskom Podhradí, ktorá má už vyše desať ročníkov. Púť spája sväté omše, modlitbu a uctenie relikvií. Prichádzajú na ňu veriaci z celého Slovenska.',
                2 => 'Kanál je určený ctiteľom sv. Filomény. Sledovaním budete vedieť o termíne púte. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1234 => [
            'name' => 'Svetový apoštolát Fatimy na Slovensku',
            'body' => [
                0 => 'Svetový apoštolát Fatimy šíri posolstvo Panny Márie z Fatimy. Na Slovensku pozýva k modlitbe ruženca a obeti. Je súčasťou medzinárodného hnutia.',
                1 => 'Pozval na inauguráciu repliky Capelinhy v Koclířove a k modlitbe ruženca za Ukrajinu. Informuje aj o medzinárodnom teologicko-pastoračnom kongrese vo Fatime. Iniciatívy sú otvorené všetkým veriacim.',
                2 => 'Kanál je určený ctiteľom Panny Márie Fatimskej. Sledovaním budete vedieť o modlitbových iniciatívach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1239 => [
            'name' => 'Trenčianske osvetové stredisko v Trenčíne',
            'body' => [
                0 => 'Trenčianske osvetové stredisko podporuje kultúru a záujmovú umeleckú činnosť v Trenčianskom kraji. Pripravuje výstavy a sympóziá. Spolupracuje s pútnickým miestom Skalka.',
                1 => 'Organizuje výtvarné sympózium ORA ET ARS – SKALKA, ktoré má už vyše pätnásť ročníkov. Výsledky sympózia predstavuje na vernisážach. Sympózium spája umenie a duchovné miesto.',
                2 => 'Kanál je určený výtvarníkom a milovníkom umenia. Sledovaním budete vedieť o vernisážach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1244 => [
            'name' => 'Škola sv. Michala',
            'body' => [
                0 => 'Škola sv. Michala je súkromná základná škola v Bratislave. Vznikla nedávno a buduje si svoju komunitu. Ponúka aj letné programy pre deti.',
                1 => 'Pripravuje dni otvorených dverí a letné denné tábory. Rodičia môžu školu spoznať osobne. Tábory sú otvorené aj pre deti z iných škôl.',
                2 => 'Kanál je určený rodičom a deťom z Bratislavy. Sledovaním budete vedieť o dňoch otvorených dverí a táboroch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1268 => [
            'name' => 'Farnosť Nitra - Horné mesto',
            'body' => [
                0 => 'Farnosť Nitra – Horné mesto pôsobí v historickom centre Nitry. K farnosti patrí aj Bazilika sv. Emeráma na Nitrianskom hrade. Zabezpečuje bohoslužby a pastoráciu.',
                1 => 'Pripravuje slávnosti, napríklad ku cti sv. Antona s požehnaním ľalií. V Bazilike sv. Emeráma zaznelo aj Mozartovo Requiem. Organizuje aj modlitby s adoráciou za kňazov.',
                2 => 'Kanál je určený veriacim z Nitry. Sledovaním budete vedieť o slávnostiach a koncertoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1271 => [
            'name' => 'rímskokatolícka farnosť v Šaštíne',
            'replace_text' => [
                'Spravujú ju saleziáni.' => 'Spravujú ju otcovia pavlíni.',
            ],
        ],
        1277 => [
            'name' => 'CZ ECAV Svätý Kríž - Lazisko',
            'body' => [
                0 => 'Cirkevný zbor ECAV Svätý Kríž – Lazisko je evanjelický zbor na Liptove. Zabezpečuje služby Božie a pastoráciu. Zapája sa aj do celoslovenských podujatí.',
                1 => 'Pripravuje Noc kostolov vo Svätom Kríži. Organizuje aj prednášky, napríklad Tajomstvá Veľkej noci. Zbor je známy drevenými artikulárnymi kostolmi.',
                2 => 'Kanál je určený veriacim a návštevníkom Liptova. Sledovaním budete vedieť o podujatiach zboru. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1280 => [
            'name' => 'Kňazský seminár sv. Cyrila a Metoda',
            'body' => [
                0 => 'Kňazský seminár sv. Cyrila a Metoda v Bratislave pripravuje budúcich kňazov Bratislavskej arcidiecézy. Bohoslovci sa podieľajú aj na práci s deťmi a mladými. Pripravujú vlastné podujatia.',
                1 => 'Bohoslovci organizujú Minfu, stretnutie miništrantov, ktoré prebehlo aj cez YouTube. Pripravujú aj stretnutia vedúcich mládeží. Program je určený miništrantom a animátorom.',
                2 => 'Kanál je určený miništrantom, mladým a farnostiam. Sledovaním budete vedieť o Minfe a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1296 => [
            'name' => 'Košický samosprávny kraj',
            'body' => [
                0 => 'Košický samosprávny kraj je vyšší územný celok, ktorý podporuje kultúru a cestovný ruch v regióne. Spolupracuje s cirkevnými inštitúciami. Podporuje festivaly.',
                1 => 'Podieľa sa na duchovno-kultúrnom festivale Dni Spišského Jeruzalema v Spišskej Kapitule. Festival má už desať ročníkov. Spája duchovný program, kultúru a históriu.',
                2 => 'Kanál je určený obyvateľom kraja a návštevníkom Spiša. Sledovaním budete vedieť o festivaloch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1302 => [
            'name' => 'Cirkevný zbor ECAV Modra',
            'body' => [
                0 => 'Cirkevný zbor ECAV Modra je evanjelický zbor v Modre. Pripomína si odkaz štúrovcov. Pripravuje aj kultúrne a diskusné podujatia.',
                1 => 'Organizuje Deň sirotinca v Modre a oslavy Tranoscia. Pripravil aj diskusiu Ako osloviť svet a nestratiť seba. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený veriacim z Modry a okolia. Sledovaním budete vedieť o podujatiach zboru. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1319 => [
            'name' => 'Duchovná správa vo Fakultnej nemocnici Trnava',
            'body' => [
                0 => 'Duchovná správa vo Fakultnej nemocnici Trnava zabezpečuje duchovnú službu pacientom a zamestnancom. Spája aj kresťanských zdravotníkov. Pripravuje pravidelné stretnutia.',
                1 => 'Organizuje stretnutia kresťanských zdravotníkov a dobrovoľníkov v trnavskej nemocnici, ktoré majú už vyše tridsať ročníkov. Stretnutia spájajú odborné témy a duchovný program. Sú určené lekárom, sestrám aj dobrovoľníkom.',
                2 => 'Kanál je určený zdravotníkom z Trnavy a okolia. Sledovaním budete vedieť o najbližších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1336 => [
            'name' => 'Spoločenstvo mužov sv. Jozefa',
            'body' => [
                0 => 'Spoločenstvo mužov sv. Jozefa pôsobí v Tvrdošíne. Spája mužov, ktorí chcú rásť vo viere. Pripravuje duchovné obnovy.',
                1 => 'Organizuje pôstne duchovné obnovy pre mužov, napríklad s o. Jánom Bucom. Obnovy sa konajú v Tvrdošíne. Spájajú slovo, modlitbu a spoločenstvo mužov.',
                2 => 'Kanál je určený mužom z Oravy. Sledovaním budete vedieť o ďalších obnovách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1349 => [
            'name' => 'Združenie katolíckeho apoštolátu',
            'body' => [
                0 => 'Združenie katolíckeho apoštolátu spája laikov, ktorí sa inšpirujú sv. Vincentom Pallottim. Pôsobí pri pallotínoch na Slovensku. Pripravuje formačné aktivity.',
                1 => 'Pripravilo aktivitu Pôst so zasvätenými a prvé Sympózium Združenia katolíckeho apoštolátu v Smižanoch. Podujatia podporujú apoštolát laikov. Sú otvorené pre všetkých.',
                2 => 'Kanál je určený laikom a priaznivcom pallotínov. Sledovaním budete vedieť o sympóziách a aktivitách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1353 => [
            'name' => 'Projekt 9 mesiacov za život',
            'body' => [
                0 => '9 mesiacov za život je celoslovenská mobilizácia za záchranu nenarodených detí. Prebieha pri miestach, kde sa vykonávajú umelé potraty. Spája modlitbu a pokojnú prítomnosť.',
                1 => 'Projekt má už niekoľko ročníkov a pozýva k akciám na ochranu nenarodených detí. Organizuje aj modlitbovú reťaz. Zapájajú sa doň dobrovoľníci z celého Slovenska.',
                2 => 'Kanál je určený všetkým, ktorí sa chcú zapojiť do ochrany života. Sledovaním budete vedieť o nových akciách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1374 => [
            'name' => 'Biskupské úrady VD a ZD ECAV na Slovensku',
            'body' => [
                0 => 'Biskupské úrady Východného a Západného dištriktu ECAV na Slovensku spolupracujú pri spoločných projektoch. Podporujú duchovných a zbory. Pripravujú materiály a webináre.',
                1 => 'Vydávajú online kázňové prípravky k Pôstu a Veľkej noci. Pripravujú webináre, napríklad o sprevádzaní umierajúcich a paliatívnej starostlivosti. Organizovali aj podujatie Pre Teba.',
                2 => 'Kanál je určený evanjelickým duchovným a pracovníkom zborov. Sledovaním budete vedieť o nových materiáloch a webinároch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1392 => [
            'name' => 'EKC Zborov',
            'phone' => '+421 907 695 490',
            'emails' => [
                0 => [
                    'email' => 'ekc.slovensko@gmail.com',
                    'found_on' => 'https://www.ekc.sk',
                ],
            ],
            'address' => [
                'street' => 'SNP 528',
                'postcode' => '086 33',
                'municipality_id' => 4095,
                'latitude' => 49.3714089,
                'longitude' => 21.3085998,
            ],
            'body' => [
                0 => 'EKC Zborov stojí za Extrémnou krížovou cestou na východnom Slovensku. Ponúka špecifické duchovné cvičenia, ktoré posúvajú človeka ďalej. Pôsobí v Zborove pri Bardejove.',
                1 => 'Organizuje Extrémnu krížovú cestu, nočné putovanie v tichu a modlitbe. Pripravuje aj vianočné trhy Betlehem v Zborove. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený mužom a ženám, ktorí hľadajú výzvu vo viere. Sledovaním budete vedieť o termínoch Extrémnej krížovej cesty. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1419 => [
            'name' => 'Rehoľa piaristov',
            'body' => [
                0 => 'Rehoľa piaristov sa venuje výchove a vzdelávaniu mladých. Na Slovensku má dlhú tradíciu škôl. Pozýva mladých spoznať život zasvätených.',
                1 => 'Pripravuje duchovné obnovy Skúste a presvedčte sa a stretnutia Príď skúsiť život zasvätených. Ponúka aj online stretnutia pre záujemcov o kňazské povolanie. Program je určený mladým mužom.',
                2 => 'Kanál je určený mladým, ktorí rozlišujú povolanie. Sledovaním budete vedieť o obnovách a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1428 => [
            'name' => 'Centrum Aletti',
            'body' => [
                0 => 'Centrum Aletti je duchovné centrum, ktoré pripravuje formačné stretnutia. Venuje sa modlitbe a vnútornému životu. Pôsobí v Bratislave.',
                1 => 'Pripravilo stretnutia ako Ježiš Kristus: živé Slovo Lásky, Modlitba príhovoru zrelého srdca či Nevesta Ducha Svätého. Stretnutia spájajú prednášku a modlitbu. Sú otvorené verejnosti.',
                2 => 'Kanál je určený veriacim, ktorí hľadajú hlbší duchovný život. Sledovaním budete vedieť o nových stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1435 => [
            'name' => 'Gréckokatolícke mládežnícke centrum Bárka',
            'body' => [
                0 => 'Gréckokatolícke mládežnícke centrum Bárka vo Vechci je centrom mládeže Prešovskej archieparchie. Pripravuje kurzy a stretnutia. Ponúka aj turistické podujatia.',
                1 => 'Organizuje kurzy Filip a Zbav nás zlého. Pripravilo aj turistický deň na Milpoš s metropolitom Jánom Babjakom. Programy spájajú vieru a spoločenstvo.',
                2 => 'Kanál je určený gréckokatolíckej mládeži. Sledovaním budete vedieť o kurzoch a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1475 => [
            'name' => 'Farnosť sv. Márie Magdalény',
            'body' => [
                0 => 'Farnosť sv. Márie Magdalény pôsobí vo Vyšnom Medzeve. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Je známa odpustovou slávnosťou.',
                1 => 'Pozýva na odpust v Kostole sv. Márie Magdalény. Vystavila relikvie sv. Pátra Pia a požehnala gotickú sochu Panny Márie s dieťaťom. Slávnosti sú otvorené pre veriacich z okolia.',
                2 => 'Kanál je určený veriacim z Medzeva a okolia. Sledovaním budete vedieť o slávnostiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1488 => [
            'name' => 'Rímskokatolícka farnosť sv. Egídia',
            'body' => [
                0 => 'Rímskokatolícka farnosť sv. Egídia pôsobí v Bardejove. Jej Bazilika sv. Egídia patrí medzi pamiatky UNESCO. Pripravuje aj koncerty.',
                1 => 'Každoročne organizuje Organové dni Jozefa Grešáka, ktoré majú už tridsať ročníkov. Vystupujú na nich organisti zo Slovenska aj zo zahraničia. Koncerty sa konajú v bazilike.',
                2 => 'Kanál je určený milovníkom organovej hudby a veriacim z Bardejova. Sledovaním budete vedieť o koncertoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1512 => [
            'name' => 'Sekcia pre rodinu Žilinskej diecézy',
            'body' => [
                0 => 'Sekcia pre rodinu Žilinskej diecézy sa venuje pastorácii rodín. Pripravuje obnovy pre manželov a rodiny. Spolupracuje s Rodinkovom.',
                1 => 'Pripravila program X. svetového stretnutia rodín v Žiline a obnovy Manželstvo Misia Možná. Organizovala aj obnovu rozVEDENÍ k BOHU v Rodinkove. Programy sú určené manželom aj rozvedeným.',
                2 => 'Kanál je určený rodinám Žilinskej diecézy. Sledovaním budete vedieť o obnovách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1518 => [
            'name' => 'Centrum pre rodinu Nitra',
            'body' => [
                0 => 'Centrum pre rodinu Nitra podporuje manželov a rodiny. Pripravuje kurzy a rodinné podujatia. Pôsobí v Nitre.',
                1 => 'Organizuje kurzy prípravy na manželstvo, Deň rodiny a charitatívny jarmok Veselého darcu Boh miluje na Klokočine. Podujatia sú otvorené rodinám s deťmi. Konajú sa počas celého roka.',
                2 => 'Kanál je určený snúbencom a rodinám z Nitry. Sledovaním budete vedieť o kurzoch a podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1521 => [
            'name' => 'Gréckokatolícka charita Prešov',
            'phone' => '0904 738 357',
            'address' => [
                'street' => 'Hlavná 2',
                'postcode' => '080 01',
                'municipality_id' => 2822,
                'latitude' => 48.9940459,
                'longitude' => 21.2438575,
            ],
            'body' => [
                0 => 'Gréckokatolícka charita Prešov pomáha ľuďom v núdzi a poskytuje sociálne služby. Jej heslo je byť blízko pri človeku. Pôsobí na východnom Slovensku.',
                1 => 'Oslávila 30. výročie obnovenia svojej činnosti a pripravuje Konferenciu charity. Otvorila aj sociálne centrum vo Svidníku. Podujatia spájajú pomoc a vzdelávanie.',
                2 => 'Kanál je určený klientom, dobrovoľníkom a podporovateľom charity. Sledovaním budete vedieť o službách a podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1545 => [
            'name' => 'Centrum starej hudby, o.z',
            'body' => [
                0 => 'Centrum starej hudby je občianske združenie, ktoré sa venuje historicky poučenej interpretácii. Organizuje festival Dni starej hudby. Pripravuje aj majstrovské kurzy.',
                1 => 'Organizuje kurzy SCHOLA s hosťami, napríklad Ronaldom Brautigamom na kladivkovom klavíri. Koncerty predstavujú starú hudbu na dobových nástrojoch. Sú otvorené verejnosti.',
                2 => 'Kanál je určený hudobníkom a milovníkom starej hudby. Sledovaním budete vedieť o kurzoch a koncertoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1554 => [
            'name' => 'Spoločnosť katolíckeho apoštolátu (pallotíni)',
            'body' => [
                0 => 'Spoločnosť katolíckeho apoštolátu – pallotíni sú rehoľná spoločnosť, ktorá sa inšpiruje sv. Vincentom Pallottim. Na Slovensku spravuje Svätyňu Božieho milosrdenstva v Smižanoch. Venuje sa aj misiám.',
                1 => 'Pozýva na oslavy Božieho milosrdenstva v Smižanoch, kam prišiel aj kardinál Dziwisz. Zbiera známky na pomoc Afrike. Ponúka duchovné cvičenia pre kňazov a zasvätené osoby.',
                2 => 'Kanál je určený veriacim a priaznivcom misií. Sledovaním budete vedieť o slávnostiach a výzvach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1587 => [
            'name' => 'Spoločenstvo Tymian',
            'body' => [
                0 => 'Spoločenstvo Tymian pripravuje programy pre miništrantov. Spolupracuje s projektom Miništranti.sk. Pôsobí po celom Slovensku.',
                1 => 'Organizuje pôstne výzvy #PAMin a online stretnutia pre miništrantov. Aktivity sú určené miništrantom z celého Slovenska. Pomáhajú im prežiť pôst a advent aktívne.',
                2 => 'Kanál je určený miništrantom a ich animátorom. Sledovaním budete vedieť o nových výzvach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1589 => [
            'name' => 'Farnosť Povýšenia Svätého Kríža',
            'body' => [
                0 => 'Farnosť Povýšenia Svätého Kríža pôsobí v Petržalke v Bratislave. Pri farnosti pôsobí aj združenie EFEZ. Pripravuje programy pre deti a rodiny.',
                1 => 'Pripravuje interaktívne adventné prípravy pre deti a pôstne projekty. Pozvala aj k novéne k Panne Márii Fatimskej za Ukrajinu. Program je otvorený pre rodiny z farnosti.',
                2 => 'Kanál je určený rodinám z Petržalky. Sledovaním budete vedieť o adventných a pôstnych projektoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1622 => [
            'name' => 'Združenie katolíckych vodkýň a skautov Európy na Slovensku',
            'body' => [
                0 => 'Združenie katolíckych vodkýň a skautov Európy (FSE) vychováva deti a mladých v duchu skautingu a katolíckej viery. Je súčasťou medzinárodnej federácie. Pôsobí na Slovensku.',
                1 => 'Pripravuje online modlitbové stretnutia, napríklad s vladykom Vasiľom, a novénu za vernosť skautskému sľubu. Organizovalo aj konferenciu o skautingu v Červenom Kláštore. Program je určený skautom a vodcom.',
                2 => 'Kanál je určený skautom, rodičom a vedúcim. Sledovaním budete vedieť o stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1637 => [
            'name' => 'Združenie katolíckych škôl Slovenska',
            'body' => [
                0 => 'Združenie katolíckych škôl Slovenska spája katolícke školy na Slovensku. Podporuje ich rozvoj a spoluprácu. Pripravuje súťaže pre žiakov.',
                1 => 'Vyhlasuje celoslovenskú výtvarnú súťaž ...a Slovo bolo u Boha... Pripravuje aj duchovné programy pre žiakov. Súťaž je určená žiakom katolíckych škôl.',
                2 => 'Kanál je určený katolíckym školám, učiteľom a žiakom. Sledovaním budete vedieť o súťažiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1641 => [
            'name' => 'Spoločenstvo evanjelickej mládeže',
            'body' => [
                0 => 'Spoločenstvo evanjelickej mládeže (SEM) spája mladých z Evanjelickej cirkvi a. v. Pripravuje semináre a stretnutia. Ponúka aj dobrovoľníctvo.',
                1 => 'Organizuje semináre, napríklad Ako horieť a nevyhorieť, a podujatie Roztrhnuté putá. Ponúka dobrovoľníctvo v Tanzánii. Program je určený mladým a vedúcim.',
                2 => 'Kanál je určený evanjelickej mládeži. Sledovaním budete vedieť o seminároch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1647 => [
            'name' => 'Miesto prijatia pre rodiny Rodinkovo',
            'phone' => '+421 911 911 780',
            'emails' => [
                0 => [
                    'email' => 'amoris@rodinkovo.sk',
                    'found_on' => 'https://www.amoris.sk/kontakt/',
                ],
            ],
            'body' => [
                0 => 'Rodinkovo v Belušských Slatinách je miesto prijatia pre rodiny. Ponúka pobyty, kurzy a programy pre manželov. Patrí k Žilinskej diecéze.',
                1 => 'Pripravuje program AMORIS pre manželov s cyklom motivačných prednášok. Vydáva aj kalendár pre priaznivcov dobrého manželstva. V Rodinkove sa konajú víkendy viacerých organizácií.',
                2 => 'Kanál je určený manželom a rodinám. Sledovaním budete vedieť o programe Amoris a pobytoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1684 => [
            'name' => 'Svetová rodina Rádio Mária',
            'body' => [
                0 => 'Svetová rodina Rádia Mária spája rádiá Mária na celom svete. Slovenské Rádio Mária je jej súčasťou. Pripravuje celosvetové modlitbové iniciatívy.',
                1 => 'Pozýva poslucháčov k pôstu a modlitbe Korunky z Medžugoria a k novéne k sv. Filoméne. Spája celý svet v spoločnej modlitbe. Do iniciatív sa môže zapojiť každý.',
                2 => 'Kanál je určený poslucháčom Rádia Mária. Sledovaním budete vedieť o modlitbových iniciatívach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1706 => [
            'name' => 'OZ Chápať srdcom',
            'body' => [
                0 => 'OZ Chápať srdcom pripravuje projekt Nenápadní hrdinovia v zápase s komunizmom. Je to kultúrno-vzdelávací projekt pre základné a stredné školy. Pôsobí zo Slovenskej Vsi.',
                1 => 'Projekt má viac ako desať ročníkov a zapájajú sa doň školáci z celého Slovenska. Pripravuje aj diskusie a sväté omše za prenasledovaných počas komunizmu. Pomáha mladým poznať dejiny.',
                2 => 'Kanál je určený učiteľom, žiakom a všetkým, ktorým záleží na pamäti národa. Sledovaním budete vedieť o novom ročníku projektu. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1725 => [
            'name' => 'Rožňavský biskup',
            'address' => [
                'street' => 'Námestie baníkov 20',
                'postcode' => '048 01',
                'municipality_id' => 3014,
                'latitude' => 48.6626556,
                'longitude' => 20.5339558,
            ],
        ],
        1730 => [
            'name' => 'Východný dištrikt Evanjelickej cirkvi augsburského vyznania na Slovensku',
            'phone' => '0918 828 379',
            'address' => [
                'street' => 'Hlavná 137',
                'postcode' => '080 01',
                'municipality_id' => 2822,
                'latitude' => 48.9989119,
                'longitude' => 21.2393984,
            ],
            'body' => [
                0 => 'Východný dištrikt Evanjelickej cirkvi a. v. na Slovensku spája evanjelické zbory východného Slovenska. Sídli v Prešove. Pripravuje podujatia pre učiteľov, duchovných a veriacich.',
                1 => 'Organizuje Deň učiteľov škôl Východného dištriktu a Ámosove dni. Vydáva kázňové prípravky. Mnohé podujatia prebiehajú aj online.',
                2 => 'Kanál je určený evanjelickým učiteľom, duchovným a veriacim. Sledovaním budete vedieť o podujatiach dištriktu. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1755 => [
            'name' => 'CZ ECAV Bratislava Legionárska',
            'body' => [
                0 => 'Cirkevný zbor ECAV Bratislava Legionárska je evanjelický zbor v Bratislave. Zabezpečuje služby Božie a pastoráciu. Venuje sa aj vzdelávaniu.',
                1 => 'Vysiela služby Božie online. Pripravuje školenia, napríklad o nových spôsoboch práce s deťmi a mládežou či o dištančnom vyučovaní náboženstva. Program je určený učiteľom a vedúcim.',
                2 => 'Kanál je určený veriacim a pracovníkom s mládežou. Sledovaním budete vedieť o službách Božích a školeniach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1802 => [
            'name' => 'Spolok Martina Rázusa',
            'phone' => '0915 964 424',
            'address' => [
                'street' => 'Námestie osloboditeľov 31',
                'postcode' => '031 01',
                'municipality_id' => 1892,
                'latitude' => 49.0836597,
                'longitude' => 19.6120835,
            ],
        ],
        1848 => [
            'name' => 'Nezisková organizácia Rádu premonštrátov – Opátstva Jasov',
            'body' => [
                0 => 'Premonštrátsky kláštor v Jasove patrí medzi najvýznamnejšie barokové pamiatky na Slovensku. Rehoľný život tu je doložený od roku 1243. Dnešný kláštor a Kostol sv. Jána Krstiteľa dal postaviť opát Andrej Sauberer od roku 1745.',
                1 => 'Nezisková organizácia rádu pripravuje organové koncerty v Opátskom chráme. Vydala aj CD s omšami Ľudovíta Skalníka. Rád slávil aj svoj Jubilejný rok.',
                2 => 'Kanál je určený návštevníkom Jasova a milovníkom hudby. Sledovaním budete vedieť o koncertoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1871 => [
            'name' => 'Confraternity Sancti Iacobi',
            'body' => [
                0 => 'Občianske združenie Priatelia Svätojakubskej cesty na Slovensku vzniklo v roku 2013. Obnovuje a propaguje Svätojakubskú cestu z Košíc do Bratislavy. Vydáva pútnické preukazy a sprievodcov.',
                1 => 'Organizuje spoločné putovania Jacobeo a Jacobeo YOUTH a Jakubské trojdnie. Putovania vedú po značených úsekoch Camina na Slovensku. Spájajú pútnikov všetkých vekových kategórií.',
                2 => 'Kanál je určený pútnikom a priaznivcom Camina. Sledovaním budete vedieť o spoločných putovaniach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1923 => [
            'name' => 'Slovenský tím Svetových dní mládeže (SDM)',
            'phone' => '0940 985 377',
            'body' => [
                0 => 'Slovenský tím Svetových dní mládeže pripravuje účasť mladých zo Slovenska na stretnutiach so Svätým Otcom. Prináša všetky informácie na jednom mieste. Koordinuje registráciu a program.',
                1 => 'Podporoval vznik Slovenského domu v Ríme a vyhlásil konkurz pre umelcov, ktorí vystúpili v Lisabone. Pripravuje mladých na ďalšie Svetové dni mládeže. Spolupracuje s diecézami.',
                2 => 'Kanál je určený mladým, ktorí sa chystajú na SDM. Sledovaním budete vedieť o registrácii a novinkách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
    ];

    public function up(): void
    {
        $emails = Schema::hasTable('canal_emails') ? app(CanalEmails::class) : null;
        $nationwide = \App\Models\Municipality::nationwideId();

        foreach (self::PROFILES as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $profile['name']) {
                continue;
            }

            $changes = [];

            // Prepíše sa len popis, ktorý ešte nemá tri odseky — upravený text ostane.
            $paragraphs = substr_count((string) $canal->body, '<p');

            if (isset($profile['body']) && $paragraphs !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $profile['body']));
            } elseif (isset($profile['append']) && $paragraphs === 2) {
                $changes['body'] = rtrim((string) $canal->body).'<p>'.e($profile['append']).'</p>';
            }

            // Cielená oprava vety v už zapísanom popise (napr. kto spravuje baziliku).
            foreach ($profile['replace_text'] ?? [] as $from => $to) {
                $current = $changes['body'] ?? (string) $canal->body;
                if (str_contains($current, e($from))) {
                    $changes['body'] = str_replace(e($from), e($to), $current);
                }
            }

            foreach (['website', 'phone'] as $field) {
                if (isset($profile[$field]) && trim((string) $canal->$field) === '') {
                    $changes[$field] = $profile[$field];
                }
            }

            if (isset($profile['address']) && in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true)) {
                $a = $profile['address'];
                $municipalityOk = $canal->municipality_id === $a['municipality_id']
                    || $canal->municipality_id === null || (int) $canal->municipality_id === $nationwide;

                if ($municipalityOk) {
                    $changes['municipality_id'] = $a['municipality_id'];
                    if (trim((string) $canal->street) === '') {
                        $changes['street'] = $a['street'];
                        $changes['postcode'] = $a['postcode'];
                    }
                    $changes += ['latitude' => $a['latitude'], 'longitude' => $a['longitude'], 'coordinates_source' => 'address'];
                }
            }

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }

            if ($emails && ! empty($profile['emails'])) {
                $emails->discover($canal->fresh(), $profile['emails'], 'manual');
                $emails->refresh($canal->fresh());
            }
        }
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }
};
