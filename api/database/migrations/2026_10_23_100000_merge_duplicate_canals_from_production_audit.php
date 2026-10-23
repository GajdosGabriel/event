<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zlúči duplicitné kanály nájdené kontrolou produkčnej databázy (23. 10. 2026).
 *
 * Tá istá organizácia bola importovaná viackrát pod odlišným názvom: právna
 * forma („Musica aeterna" / „Musica aeterna o.z"), preklep („Augustiáni",
 * „Pápežské misjné diela"), skratka v zátvorke, dovetok „na Slovensku" či
 * opisný názov („Televízia LUX" 2-krát, „TV LUX", „katolícka televízia LUX",
 * „Slovenská katolícka Televízia LUX"). Kandidáti vzišli zo zhody normalizovaných
 * názvov, podobnosti názvov a rovnakého webu; každá skupina bola posúdená ručne.
 * Nezlučujú sa odlišné zložky tej istej organizácie (regionálne pobočky,
 * farnosť vs. OZ, rôzne diecézy) ani podobné názvy rôznych subjektov.
 *
 * Kanonický kanál = ten so zverejnenými podujatiami, rozoslanou pozvánkou,
 * najviac podujatiami a vyplneným kontaktom.
 *
 * Overené pred spustením: všetky kanály v zozname sú z importu, bez
 * organization_id a prevzatia, a jediný riadok v canal_user patrí
 * administrátorskému účtu. Migrácia to pri každom zlúčení overuje znova
 * a dvojicu, ktorá podmienky nespĺňa, preskočí.
 *
 * Pre každú dvojicu (kanonický, duplicitný):
 *  1. prázdne polia kanonického kanála sa doplnia z duplicity,
 *  2. podujatia, série, pozvánky, rozosielky, tikety podpory a users.canal_id
 *     sa presunú na kanonický kanál,
 *  3. canal_venue väzby sa zlúčia (is_owner = max, publikovaná väzba vyhráva),
 *  4. e-maily duplicity pribudnú ako ďalšie adresy (primárnu určí CanalEmails),
 *  5. zobrazenia sa presunú a pripočítajú, kontroly obsahu a atribútov sa
 *     zmažú (kanonický sa prepočíta); história (system_logs, ai_usages,
 *     profile_enrichments) ostáva na duplicite,
 *  6. nerozhodnuté import-duplicity medzi kanálmi skupiny sa označia ako
 *     vybavené a duplicita sa soft-deletne.
 *
 * Duplicita si pamätá merged_into_id, takže jej názov ostáva aliasom
 * kanonického kanála pre ďalšie importy.
 *
 * down() duplicity obnoví (deleted_at = null); presuny sa nevracajú
 * (jednosmerný prepis ako pri predošlých zlúčeniach).
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0:int, 1:list<int>}> [kanonický_id, [duplicitné_id, ...]]
     */
    private const MERGES = [
        [154, [6]], // Musica aeterna o.z
        [9, [1080]], // Rada pre mládež a univerzity KBS
        [20, [1638]], // OZ Rodinné spoločenstvo ECAV na Slovensku
        [34, [1520]], // Občianske združenie KREDIV
        [174, [56]], // Hnutie Modlitby za kňazov
        [59, [333]], // Spoločenstvo Nádej
        [63, [493, 939, 1149, 1742]], // Pápežské misijné diela na Slovensku
        [635, [1451]], // Misijné dielo detí
        [79, [68]], // Občianske združenie Národný pochod za život
        [70, [875]], // Katolícke spoločenstvo Marana Tha
        [73, [1121]], // Občianske združenie Katarínka
        [75, [1401]], // Duchovné centrum Lukov dvor
        [80, [1463, 1476]], // Mesto Bardejov – oddelenie kultúry
        [1168, [82]], // Cor Sancti Martini, n.o
        [89, [1526]], // Farnosť Gaboltov
        [93, [1044, 822, 1000, 889]], // Ekumenické spoločenstvo cirkví a náboženských spoločností na území mesta Košice
        [208, [1736, 100]], // Evanjelická cirkev augsburského vyznania na Slovensku (ECAV)
        [67, [122, 470]], // Košická eparchia
        [627, [125, 629, 1493, 248, 314, 981]], // Bratia Augustiniáni
        [129, [1911]], // Zefýrove Príbehy OZ
        [573, [1057, 1767, 130]], // Arcidiecézne centrum pre mládež (ACM)
        [136, [381]], // Banská Bystrica - Centrum pre rodinu
        [146, [1293]], // Rímskokatolícka farnosť sv. Mikuláša (Prešov)
        [147, [590]], // Prešovská gotika, o.z.
        [149, [1805]], // IKONY OZ
        [153, [107, 533, 259, 1831, 1007, 464]], // Saleziáni don Bosca
        [156, [1640]], // eRko – Hnutie kresťanských spoločenstiev detí
        [157, [842]], // Slovenský skauting
        [158, [642]], // Kongregácia bratov Tešiteľov z Gethseman
        [161, [27, 445, 1629, 335, 955, 244, 504]], // Rehoľa menších bratov (františkáni)
        [162, [803, 609]], // Exercičný dom svätého Ignáca z Loyoly
        [1562, [344, 428, 1651]], // Bratia Dominikáni – farnosť Bratislava Kalvária
        [490, [168]], // Spoločenstvo Pavol z Levoče
        [276, [571, 202, 825, 1155]], // CYRILOMETODIADA
        [204, [615, 648]], // Bratislavský arcibiskup metropolita Mons. Stanislav Zvolenský
        [1368, [1213, 1726, 1728]], // spišský diecézny administrátor Mons. Ján Kuboš
        [347, [218, 888]], // Nitrianske biskupstvo
        [1023, [229]], // Gospelová muzika, o.z
        [232, [1311]], // Milosrdné sestry Svätého kríža
        [243, [1355, 1091, 61, 211, 182, 1114, 1908]], // Pápežská nadácia ACN – Pomoc trpiacej Cirkvi
        [245, [1642]], // Centrum pre štúdium biblického a blízkovýchodného sveta
        [258, [1321]], // Rímskokatolícka cyrilometodská bohoslovecká fakulta UK
        [268, [1403]], // občianske združenie Naše MesTTo
        [290, [817, 1578]], // Ekumenická rada cirkví v SR
        [1371, [297, 372]], // Gréckokatolícka evanjelizačná škola sv. Mikuláša
        [1460, [1858]], // Kongregácia sestier dominikánok bl. Imeldy
        [315, [853, 1312, 1123]], // Klub kresťanských lekárov a zdravotníkov Bratislava
        [322, [1827]], // Environmentálna subkomisia KBS
        [349, [724]], // Rodinné spoločenstvo FATIMA, o.z
        [351, [1551]], // Kňazský seminár sv. Karola Boromejského
        [1346, [353]], // Fórum kresťanských inštitúcií, o.z
        [354, [1142]], // Bratia kapucíni
        [361, [896]], // Občianske združenie Spolok priateľov hudby v Cíferi
        [732, [383, 692]], // Dom kultúry v Námestove
        [481, [1363, 1544, 386]], // Hnutie na pomoc rozvedeným kresťanom
        [802, [412]], // Pápežský slovenský ústav a kolégium sv. Cyrila a Metoda
        [430, [746]], // Konzervatívny klub v Nitre
        [1010, [431]], // Katedra hudby na Pedagogickej fakulte KU v Ružomberku
        [768, [433, 1576]], // OZ Domov je prístav
        [437, [1438]], // Občianske združenie Dobré srdce – Good heart
        [441, [534, 877]], // Ordinariát Ozbrojených síl a ozbrojených zborov SR
        [460, [1791]], // Univerzita tretieho veku na TF Trnavskej univerzity
        [1090, [471]], // CK Cestovná kancelária NOE TRAVEL
        [492, [826, 502, 1402, 1059]], // Kongregácia sestier Matky Božieho Milosrdenstva
        [524, [1065, 193, 1612, 1829]], // Televízia LUX
        [527, [1758]], // Diecézna charita Nitra
        [540, [1646]], // Farnosť sv. Alžbety (Košice)
        [788, [541]], // Centrum pastorácie mládeže v Spišskej diecéze
        [545, [1076]], // Rímskokatolícka farnosť Poprad
        [546, [1390, 1361]], // Komunita Emanuel
        [570, [597]], // Sto talentov o.z
        [602, [1472, 401, 1775]], // Pastoračné centrum Anny Kolesárovej – Domček
        [1906, [197, 676, 1845, 624]], // Slovenská a česká provincia Spoločnosti Božieho Slova (verbisti)
        [1796, [630]], // združenie Gotická cesta
        [823, [654]], // Katolícke pedagogické a katechetické centrum, n.o
        [771, [829, 666]], // Kongregácia Milosrdných sestier sv. Vincenta – Satmárky
        [684, [1531, 1692]], // Nadační fond CREDO
        [728, [1074, 813, 1365]], // farnosť Kráľovnej rodiny
        [1849, [747, 882, 1522, 735, 1595]], // Teologická fakulta KU v Košiciach
        [775, [1141]], // Univerzitné pastoračné centrum (UPeCe) sv. Jozefa Freinademetza
        [778, [1182]], // Komisia pre prípravu a slávenie Jubilejného roka 2025 na Slovensku
        [1922, [828]], // Miestny organizačný výbor Svetových dní mládeže v Soule 2027
        [1923, [1340, 1185]], // Slovenský tím Svetových dní mládeže (SDM)
        [833, [1024]], // Fraternity Jeruzalem
        [973, [295, 588, 866]], // Rímskokatolícka farnosť Bratislava – Prievoz
        [893, [1509, 375]], // KZ Sprevádzajúci – pútnické bratstvo
        [902, [1556, 1344, 1624]], // Edukačno-misijné centrum ECAV
        [998, [1473, 971]], // Bazilika Sedembolestnej Panny Márie v Šaštíne
        [682, [1286, 1461]], // Farnosť Sedembolestnej Panny Márie (Záhradné)
        [1016, [1305, 1716]], // Oratórium sv. Filipa Neriho pri Farnosti Svätej rodiny
        [1518, [1064]], // Centrum pre rodinu Nitra
        [1107, [1423]], // Ministerstvo kultúry SR
        [1128, [1437, 1566]], // Združenie Stretnutie chrámových hudobníkov
        [1134, [1910]], // Úrad pre apoštolát ochrany života v Košickej arcidiecéze
        [1281, [1795]], // Evanjelický cirkevný zbor v Modre-Kráľovej
        [1284, [1675]], // V.I.A.C. - Inštitút pre podporu a rozvoj mládeže
        [1867, [1287]], // Rímskokatolícky cirkevný hudobný spolok sv. Mikuláša (Trnava)
        [726, [1310, 1433, 1328]], // Farnosť Kežmarok
        [1320, [1186, 1391]], // Slovenské národné múzeum (SNM) – Historické múzeum
        [1326, [1384]], // Výbor cirkevnej hudby a hymnológie ECAV
        [1389, [1626, 1367]], // Národný týždeň manželstva, o.z
        [1394, [1772]], // združenie Laura
        [1407, [1656]], // Konrad-Adenauer-Stiftung
        [1432, [1110, 1313, 1756]], // Deti sv. Alžbety - Aliancia za život, o.z
        [1877, [1568]], // tím SingleKatolici.sk
        [1589, [1628]], // Farnosť Povýšenia Svätého Kríža (Bratislava)
        [1747, [1381, 1592]], // Evanjelická diakonia ECAV na Slovensku
        [1641, [1823]], // Spoločenstvo evanjelickej mládeže
        [1709, [1711]], // Združenie Amazi
        [1834, [1727]], // 3. ekumenický Kirchentag
        [1783, [1817]], // Veľvyslanectvo Poľskej republiky na Slovensku
        [1446, [1442, 575, 1104]], // Spoločenstvo Cleopas, o.z
        [954, [1917, 7, 542, 228, 425, 839]], // Rehoľa bratov kazateľov (bratia dominikáni)
        [1052, [15]], // Vydavateľstvo Tranoscius, a. s.
        [19, [1204]], // Ekumenický výbor ECAV na Slovensku
        [29, [1224]], // Bratislavská eparchia
        [30, [1574]], // Žilinská diecéza
        [373, [72]], // Univerzitné kolégium Antona Neuwirtha
        [92, [1786]], // Mary’s Meals
        [655, [95]], // Rímskokatolícka farnosť Skalka nad Váhom
        [150, [108]], // Centrum pomoci pre rodinu - Trnava
        [1166, [143]], // Pútnické miesto Skalka pri Trenčíne
        [1072, [175, 1620]], // Katolícke Rádio LUMEN
        [1300, [198]], // Hnutie kresťanských rodín na Slovensku
        [1078, [201, 1680]], // Poradenské centrum pre rodiny Family Garden
        [210, [1903]], // Veľvyslanectvo Slovenskej republiky pri Svätej stolici
        [266, [226]], // Rímskokatolícky farský úrad v Topoľčiankach
        [815, [230, 1441, 743]], // Katolícke spoločenstvo Cor et Lumen Christi
        [277, [1267]], // Arcidiecézne centrum pre rodinu v Košiciach
        [278, [411]], // Dom Quo Vadis
        [1387, [309]], // Katolícke biblické dielo na Slovensku
        [331, [843, 538, 1214]], // Rád bosých karmelitánov
        [1876, [343, 555]], // Slovenská provincia Spoločnosti Ježišovej
        [369, [879]], // Farnosť sv. Košických mučeníkov
        [429, [763, 864]], // Úsmev ako dar
        [1552, [436]], // Farnosť Trnava – Tulipán
        [537, [1636]], // Komunita Sant’Egidio
        [990, [565]], // Farnosť Nitra – Janíkovce
        [576, [1836]], // Kysucká knižnica
        [585, [1873]], // Farnosť Košice – Dóm
        [1695, [1694]], // Ponitrianske múzeum v Nitre
        [1878, [1014, 968]], // Združenie Chemin Neuf
        [1690, [1225]], // Dikastérium pre laikov, rodinu a život
        [1353, [1265]], // Projekt 9 mesiacov za život
        [1532, [1116]], // Múzeum obetí komunizmu v Košiciach
        [1147, [1733]], // 40 dní za život
        [1419, [1732]], // Rehoľa piaristov
        [1848, [1505, 1768]], // Nezisková organizácia Rádu premonštrátov – Opátstva Jasov
        [1563, [1707]], // Združenie evanjelických duchovných
        [1622, [1602]], // Združenie katolíckych vodkýň a skautov Európy na Slovensku
        [257, [377]], // Hudobná subkomisia Liturgickej komisie KBS
        [133, [434, 1671]], // Misionári Najsvätejšieho Srdca Ježišovho
        [439, [758]], // sestry redemptoristky
        [451, [1585]], // Občianske združenie SAVIO
        [639, [1222]], // Komisia pre mládež Bratislavskej arcidiecézy
        [1560, [887]], // Dominikánske mariánske centrum - DMC
        [903, [1393, 1847]], // Spoločenstvo kresťanských fotografov Človek a Viera
        [1249, [1600]], // Komisia pre mládež v Spišskej diecéze
        [261, [1855]], // Biskupstvo Banská Bystrica
        [1234, [753]], // Svetový apoštolát Fatimy na Slovensku
        [1427, [690]], // Hospitálska rehoľa sv. Jána z Boha – Milosrdní bratia
        [862, [884, 1097]], // Katedra spoločenských a humanitných vied (GTF PU)
        [1043, [1144]], // Aglow Slovakia
        [1018, [1069]], // Rehoľno-formačné centrum (Spišská Nová Ves)
        [1625, [838]], // Kolednícka akcia Dobrá novina
        // Dohľadané podľa ImportedNameMatcher::canalKey():
        [106, [681]], // Spolok svätého Vojtecha
        [279, [256]], // Farnosť Levoča
        [850, [406]], // Fórum života o.z
        [836, [1058]], // Občianske združenie Via regum
        [1271, [920]], // rímskokatolícka farnosť v Šaštíne
        [956, [1696]], // farnosť Devín
        [1053, [1108]], // Gréckokatolícke spoločenstvo vo farnosti Sedliská
        [1163, [1734]], // Školský výbor ECAV
        [1644, [1411]], // UPF Slovensko
        [1481, [1685]], // PRE ŠUŇAVU o. z
        [1513, [1832]], // Biskupský úrad Rožňava
        [1545, [1546]], // Centrum starej hudby, o.z
    ];

    /**
     * Duplicity, ktorým import priradil kontakt iného subjektu: #493 (PMD)
     * má adresu, telefón a e-mail farnosti Skačany, #1858 (sestry dominikánky)
     * údaje provincialátu bratov dominikánov. Z nich sa nič nedopĺňa.
     */
    private const FOREIGN_CONTACT = [493, 1858];

    private const FILLABLE = ['municipality_id', 'street', 'postcode', 'country', 'phone', 'website', 'body', 'title_prefix', 'title_suffix'];

    /** Tabuľky, ktorých canal_id sa jednoducho prepíše na kanonický kanál. */
    private const MOVABLE = ['events', 'event_series', 'canal_invitations', 'canal_outreach', 'support_tickets', 'users'];

    public function up(): void
    {
        if (! Schema::hasTable('canals') || ! Schema::hasTable('canal_venue')) {
            return;
        }

        foreach (self::MERGES as [$canonicalId, $duplicateIds]) {
            DB::transaction(function () use ($canonicalId, $duplicateIds) {
                $merged = [];

                foreach ($duplicateIds as $duplicateId) {
                    if ($this->merge($canonicalId, $duplicateId)) {
                        $merged[] = $duplicateId;
                    }
                }

                if ($merged === []) {
                    return;
                }

                $this->settleDecisions([$canonicalId, ...$merged]);

                if (Schema::hasTable('canal_emails') && ($canonical = Canal::query()->find($canonicalId)) !== null) {
                    app(CanalEmails::class)->refresh($canonical);
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        $duplicateIds = collect(self::MERGES)->flatMap(fn ($merge) => $merge[1])->all();

        $restore = ['deleted_at' => null, 'updated_at' => now()];

        if (Schema::hasColumn('canals', 'merged_into_id')) {
            $restore['merged_into_id'] = null;
        }

        DB::table('canals')->whereIn('id', $duplicateIds)->update($restore);
    }

    private function merge(int $canonicalId, int $duplicateId): bool
    {
        $canonical = DB::table('canals')->where('id', $canonicalId)->whereNull('deleted_at')->first();
        $duplicate = DB::table('canals')->where('id', $duplicateId)->whereNull('deleted_at')->first();

        if ($canonical === null || $duplicate === null || ! $this->safeToMerge($canonical, $duplicate)) {
            return false;
        }

        $update = ['updated_at' => now()];
        $fill = ! in_array($duplicateId, self::FOREIGN_CONTACT, true);

        foreach (self::FILLABLE as $column) {
            if ($fill && $this->blank($canonical->{$column}) && ! $this->blank($duplicate->{$column})) {
                $update[$column] = $duplicate->{$column};
            }
        }

        if ($fill && $canonical->latitude === null && $duplicate->latitude !== null) {
            $update['latitude'] = $duplicate->latitude;
            $update['longitude'] = $duplicate->longitude;
            $update['coordinates_source'] = $duplicate->coordinates_source;
        }

        $update['views_count'] = (int) $canonical->views_count + (int) $duplicate->views_count;

        DB::table('canals')->where('id', $canonicalId)->update($update);

        foreach (self::MOVABLE as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('canal_id', $duplicateId)->update(['canal_id' => $canonicalId]);
            }
        }

        $this->moveVenueLinks($canonicalId, $duplicateId);
        $this->moveEmails($canonicalId, $duplicateId, $fill);

        $this->moveViews($duplicateId, $canonicalId);

        if (Schema::hasTable('content_reviews')) {
            DB::table('content_reviews')->where('reviewable_type', Canal::class)
                ->whereIn('reviewable_id', [$canonicalId, $duplicateId])->delete();
        }

        if (Schema::hasTable('attribute_checks')) {
            DB::table('attribute_checks')->where('checkable_type', Canal::class)
                ->where('checkable_id', $duplicateId)->delete();
        }

        DB::table('canal_user')->where('canal_id', $duplicateId)->delete();
        $trash = ['deleted_at' => now(), 'updated_at' => now()];

        // Názov duplicity ostane aliasom kanonického kanála (DuplicateResolver::canalByKey).
        if (Schema::hasColumn('canals', 'merged_into_id')) {
            $trash['merged_into_id'] = $canonicalId;
        }

        DB::table('canals')->where('id', $duplicateId)->update($trash);

        return true;
    }

    /**
     * Zlučujú sa len importované kanály, ktoré nikto neprevzal a na ktoré nemá
     * prístup nikto, kto by ho nemal aj na kanonický kanál.
     */
    private function safeToMerge(object $canonical, object $duplicate): bool
    {
        if ($duplicate->registration_source !== 'import' || $duplicate->claimed_at !== null
            || $duplicate->organization_id !== $canonical->organization_id) {
            return false;
        }

        $canonicalUsers = DB::table('canal_user')->where('canal_id', $canonical->id)->pluck('user_id')->all();

        if (DB::table('canal_user')->where('canal_id', $duplicate->id)->whereNotIn('user_id', $canonicalUsers)->exists()) {
            return false;
        }

        foreach (['canal_claims', 'canal_notification_settings'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('canal_id', $duplicate->id)->exists()) {
                return false;
            }
        }

        return true;
    }

    private function moveVenueLinks(int $canonicalId, int $duplicateId): void
    {
        foreach (DB::table('canal_venue')->where('canal_id', $duplicateId)->get() as $link) {
            $existing = DB::table('canal_venue')
                ->where('canal_id', $canonicalId)->where('venue_id', $link->venue_id)->first();

            if ($existing === null) {
                DB::table('canal_venue')->insert([
                    'canal_id' => $canonicalId,
                    'venue_id' => $link->venue_id,
                    'is_owner' => $link->is_owner,
                    'status' => $link->status,
                    'created_at' => $link->created_at,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('canal_venue')
                    ->where('canal_id', $canonicalId)->where('venue_id', $link->venue_id)
                    ->update([
                        'is_owner' => max($existing->is_owner, $link->is_owner),
                        'status' => $existing->status === 'published' || $link->status === 'published' ? 'published' : $existing->status,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('canal_venue')->where('canal_id', $duplicateId)->delete();
    }

    /** Adresy duplicity pribudnú ako ďalšie; primárnu potom vyberie CanalEmails. */
    private function moveEmails(int $canonicalId, int $duplicateId, bool $move): void
    {
        if (! Schema::hasTable('canal_emails')) {
            return;
        }

        $known = DB::table('canal_emails')->where('canal_id', $canonicalId)->pluck('email')->all();
        $room = $move ? max(0, CanalEmails::MAX - count($known)) : 0;

        $moved = $room === 0 ? [] : DB::table('canal_emails')->where('canal_id', $duplicateId)->whereNotIn('email', $known)
            ->orderBy('id')->limit($room)->pluck('id')->all();

        DB::table('canal_emails')->whereIn('id', $moved)->update(['canal_id' => $canonicalId, 'is_primary' => false, 'updated_at' => now()]);
        DB::table('canal_emails')->where('canal_id', $duplicateId)->delete();
    }

    /**
     * Návštevník, ktorý v ten deň videl oba kanály, je jedno zobrazenie
     * (unikátny index views_unique_per_day) — jeho riadok duplicity sa zahodí.
     */
    private function moveViews(int $fromId, int $toId): void
    {
        if (! Schema::hasTable('views')) {
            return;
        }

        $conflicting = DB::table('views as d')
            ->join('views as k', fn ($join) => $join->on('k.visitor_hash', '=', 'd.visitor_hash')->on('k.viewed_on', '=', 'd.viewed_on'))
            ->where('d.viewable_type', Canal::class)->where('d.viewable_id', $fromId)
            ->where('k.viewable_type', Canal::class)->where('k.viewable_id', $toId)
            ->pluck('d.id')->all();

        DB::table('views')->whereIn('id', $conflicting)->delete();
        DB::table('views')->where('viewable_type', Canal::class)->where('viewable_id', $fromId)
            ->update(['viewable_id' => $toId]);
    }

    /** Nerozhodnuté import-duplicity medzi kanálmi skupiny sú týmto vybavené. */
    private function settleDecisions(array $groupIds): void
    {
        if (! Schema::hasTable('duplicate_decisions')) {
            return;
        }

        DB::table('duplicate_decisions')
            ->where('entity', 'canal')
            ->whereNull('reviewed_at')
            ->whereIn('candidate_id', $groupIds)
            ->whereIn('created_id', $groupIds)
            ->update(['reviewed_at' => now(), 'updated_at' => now()]);
    }

    private function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '' || in_array(strtolower(trim((string) $value)), ['null', '[]'], true);
    }
};
