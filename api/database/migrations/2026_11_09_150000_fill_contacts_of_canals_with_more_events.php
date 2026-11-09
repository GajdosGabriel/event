<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;
use App\Support\Url;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Druhá dávka ručne dohľadaných kontaktov (november 2026): kanály s tromi
 * a viac akciami, potom s dvoma — bez e-mailu, aj keď práve nemajú aktuálnu akciu.
 *
 * Automatické hľadanie ich nevyberie (beží len pre kanály s aktuálnou akciou),
 * takže kontakt pripravíme vopred na ďalšiu akciu. Weby a adresy nižšie sú ručne
 * dohľadané na oficiálnych stránkach organizácií — `found_on` je stránka,
 * na ktorej adresa doslova je.
 *
 * Pravidlá:
 *  - kanál sa hľadá podľa ID a musí sedieť aj názov (iná databáza = nič),
 *  - preskočí sa zmazaný, zlúčený a prevzatý kanál,
 *  - web a telefón sa zapíšu len do prázdneho poľa; `replace_website` opraví
 *    web, ktorý patrí niekomu inému (kampaň, spoločný portál, cudzia stránka),
 *  - e-maily idú cez CanalEmails::discover — neoverené, primárna len keď
 *    kanál žiadnu nemá, známe a potlačené adresy sa preskočia.
 */
return new class extends Migration
{
    /** @var array<int, array{name: string, website?: string, replace_website?: bool, phone?: string, emails?: array<int, array{email: string, found_on: string}>}> */
    private const CONTACTS = [
        313 => ['name' => 'Katolícke noviny', 'emails' => [['email' => 'posta@katolickenoviny.sk', 'found_on' => 'https://www.katolickenoviny.sk/kontakt.xhtml']]],
        693 => ['name' => 'Nové svitanie', 'emails' => [['email' => 'info@novesvitanie.sk', 'found_on' => 'https://novesvitanie.sk/kontakt/']]],
        694 => ['name' => 'Konferencia vyšších rehoľných predstavených na Slovensku', 'emails' => [['email' => 'kvrps@kvrps.sk', 'found_on' => 'https://www.kvrps.sk']]],
        771 => ['name' => 'Kongregácia Milosrdných sestier sv. Vincenta – Satmárok a Rodina Nepoškvrnenej', 'emails' => [['email' => 'satmarky@satmarky.sk', 'found_on' => 'https://www.satmarky.sk']]],
        794 => ['name' => 'Cirkevné konzervatórium v Bratislave', 'emails' => [['email' => 'sekretariat@ckba.sk', 'found_on' => 'https://www.ckba.sk']]],
        810 => ['name' => 'Akadémia Karola Wojtylu', 'emails' => [['email' => 'sekretariat@adkw.sk', 'found_on' => 'https://www.adkw.sk']]],
        815 => ['name' => 'Katolícke spoločenstvo Cor et Lumen Christi', 'emails' => [['email' => 'corlumenchristi@gmail.com', 'found_on' => 'http://www.coretlumenchristi.org/contact_us.php']]],
        849 => ['name' => 'Vydavateľstvo DON BOSCO', 'emails' => [['email' => 'donbosco@donbosco.sk', 'found_on' => 'https://www.donbosco.sk']]],
        851 => ['name' => 'Centrum konkrétnej pomoci Femina, n.o', 'emails' => [['email' => 'kancelaria@centrumfemina.sk', 'found_on' => 'https://www.centrumfemina.sk']]],
        856 => ['name' => 'Maltézska pomoc Slovensko – centrum Nitra', 'emails' => [['email' => 'office@maltezskapomoc.sk', 'found_on' => 'https://www.orderofmalta.sk']]],
        901 => ['name' => 'Matica slovenská', 'emails' => [['email' => 'info@matica.sk', 'found_on' => 'http://www.matica.sk/kontakty']]],
        940 => ['name' => 'MIVA Slovensko', 'emails' => [['email' => 'info@mivaslovensko.sk', 'found_on' => 'https://www.mivaslovensko.sk']]],
        967 => ['name' => 'Nadácia VÚB', 'emails' => [['email' => 'info@nadaciavub.sk', 'found_on' => 'https://www.nadaciavub.sk']]],
        1003 => ['name' => 'Domček bl. Anny Kolesárovej', 'emails' => [['email' => 'pute@domcek.org', 'found_on' => 'https://www.silvester.domcek.org']]],
        1023 => ['name' => 'Gospelová muzika, o.z', 'emails' => [['email' => 'info@verimpane.sk', 'found_on' => 'https://www.verimpane.sk/kontakt/']]],
        1040 => ['name' => 'Poradňa ALEXIS, n.o', 'emails' => [['email' => 'poradna@alexisporadna.sk', 'found_on' => 'https://www.alexisporadna.sk']]],
        1055 => ['name' => 'Teatro Colorato', 'emails' => [['email' => 'teatro.colorato@gmail.com', 'found_on' => 'https://www.colorato.sk']]],
        1072 => ['name' => 'Katolícke Rádio LUMEN', 'emails' => [['email' => 'lumen@lumen.sk', 'found_on' => 'http://www.lumen.sk']]],
        1087 => ['name' => 'Dom úľavy sv. Charbela', 'emails' => [['email' => 'info@domulavysvcharbela.sk', 'found_on' => 'https://www.domulavysvcharbela.sk']]],
        1090 => ['name' => 'CK Cestovná kancelária NOE TRAVEL', 'emails' => [['email' => 'info@pute.sk', 'found_on' => 'https://pute.sk']]],
        1127 => ['name' => 'Misijná kongregácia služobníc Ducha Svätého', 'emails' => [['email' => 'info@ssps.sk', 'found_on' => 'https://www.ssps.sk']]],
        1128 => ['name' => 'Združenie Stretnutie chrámových hudobníkov', 'emails' => [['email' => 'chramovihudobnici@gmail.com', 'found_on' => 'https://www.schh.sk']]],
        1168 => ['name' => 'Cor Sancti Martini, n.o', 'emails' => [['email' => 'info@odrobiny.sk', 'found_on' => 'https://www.odrobiny.sk']]],
        1194 => ['name' => 'Kurz Samuel', 'emails' => [['email' => 'kurzsamuelba@gmail.com', 'found_on' => 'https://www.kurzsamuel.sk']]],
        1221 => ['name' => 'Chlapské víkendovky', 'emails' => [['email' => 'info@chlapskevikendovky.sk', 'found_on' => 'https://www.chlapskevikendovky.sk/kontakt/']]],
        1248 => ['name' => 'Katolícke spoločenstvo EMfest tím', 'emails' => [['email' => 'emfest@emfest.sk', 'found_on' => 'https://www.emfest.sk/o-nas/']]],
        1353 => ['name' => 'Projekt 9 mesiacov za život', 'emails' => [['email' => 'info@9mesiacovzazivot.sk', 'found_on' => 'https://www.9mesiacovzazivot.sk']]],
        1389 => ['name' => 'Národný týždeň manželstva, o.z', 'emails' => [['email' => 'ntmslovensko@gmail.com', 'found_on' => 'https://www.ntm.sk']]],
        1413 => ['name' => 'Saleziánska rozvojová organizácia SAVIO o.z', 'emails' => [['email' => 'savio@savio.sk', 'found_on' => 'https://www.savio.sk']]],
        1418 => ['name' => 'Červená streda', 'emails' => [['email' => 'info@acnslovensko.sk', 'found_on' => 'https://www.cervenastreda.sk']]],
        1421 => ['name' => 'Divadlo Andreja Bagara', 'emails' => [['email' => 'dabnr@dab.sk', 'found_on' => 'http://www.dab.sk/kontakty']]],
        1422 => ['name' => 'Dobrota pre Afriku', 'emails' => [['email' => 'maria.galikova@dobrotapreafriku.sk', 'found_on' => 'https://www.dobrotapreafriku.sk']]],
        1446 => ['name' => 'Spoločenstvo Cleopas, o.z', 'emails' => [['email' => 'redakcia@biblica.sk', 'found_on' => 'https://biblica.sk/stbisl/kontakty/']]],
        1521 => ['name' => 'Gréckokatolícka charita Prešov', 'emails' => [['email' => 'gkcharita@gkcharita-po.sk', 'found_on' => 'https://www.gkcharita-po.sk']]],
        1609 => ['name' => 'Inštitút na podporu mladých publicistov', 'emails' => [['email' => 'info@journalistenschule-ifp.de', 'found_on' => 'https://www.journalistenschule-ifp.de/kontakt']]],
        1637 => ['name' => 'Združenie katolíckych škôl Slovenska', 'emails' => [['email' => 'prezident@zkss.sk', 'found_on' => 'https://zkss.sk/kontakt/']]],
        1695 => ['name' => 'Ponitrianske múzeum v Nitre', 'emails' => [['email' => 'info@muzeumnitra.sk', 'found_on' => 'http://muzeumnitra.sk']]],
        1743 => ['name' => 'Gymnázium svätej Uršule', 'emails' => [['email' => 'tajomnicka@ursula.sk', 'found_on' => 'https://www.ursula.sk']]],
        208 => ['name' => 'Evanjelická cirkev augsburského vyznania na Slovensku (ECAV)', 'emails' => [['email' => 'media@ecav.sk', 'found_on' => 'https://www.ecav.sk/aktuality/pozvanky/pozyvame-na-oslavy-cithary-sanctorum']]],
        245 => ['name' => 'Centrum pre štúdium biblického a blízkovýchodného sveta', 'emails' => [['email' => 'rlapko@gmail.com', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20260624035']]],
        338 => ['name' => 'Slovenská spoločnosť pre katolícku teológiu', 'emails' => [['email' => 'predseda@sskt.sk', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20260507002']]],
        359 => ['name' => 'iniciatíva Františkova ekonomika', 'emails' => [['email' => 'josef.zvonicek@frantiskovaekonomika.org', 'found_on' => 'https://www.vyveska.sk/konferencia-dobry-spravce.html']]],
        367 => ['name' => 'Tím RADOSTNÁ', 'emails' => [['email' => 'info@radostna.sk', 'found_on' => 'https://www.vyveska.sk/podporna-skupinka-online-pre-zeny-ktore-si-prechadzaju-narocnou-cestou-snazenia-sa-o-babatko.html']]],
        388 => ['name' => 'Kňazský seminár biskupa Jána Vojtaššáka', 'emails' => [['email' => 'rektorat@kapitula.sk', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20240415029']]],
        451 => ['name' => 'Občianske združenie SAVIO', 'emails' => [['email' => 'tehlicka@savio.sk', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20250305085']]],
        463 => ['name' => 'MiSeVi Slovakia', 'emails' => [['email' => 'formacia.misevi@gmail.com', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20230616054']]],
        473 => ['name' => 'Sestry Najsvätejšieho Spasiteľa', 'emails' => [['email' => 'duchovneaktivity@centrumsalvator.sk', 'found_on' => 'https://www.vyveska.sk/celodenne-duchovne-stretnutie-ticho-v-ktorom-hovori-boh.html']]],
        546 => ['name' => 'Komunita Emanuel', 'emails' => [['email' => 'cesta@emanuel.sk', 'found_on' => 'https://www.vyveska.sk/cesta-z-bolesti-potratu.html']]],
        576 => ['name' => 'Kysucká knižnica', 'emails' => [['email' => 'jurin@kniznica-cadca.sk', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20240308014']]],
        727 => ['name' => 'OZ Slobodní 28+', 'emails' => [['email' => 'slobodni28plus@gmail.com', 'found_on' => 'https://www.vyveska.sk/27609/chorvatsko-vodice-11-20-7-2025.html']]],
        891 => ['name' => 'DC pre bezdetných manželov', 'emails' => [['email' => 'milujucanaruc@gmail.com', 'found_on' => 'https://www.vyveska.sk/26977/milujuca-naruc-dc-pre-bezdetnych-manzelov.html']]],
        924 => ['name' => 'Rodina BB', 'emails' => [['email' => 'rachel@rodinabb.sk', 'found_on' => 'https://www.vyveska.sk/26758/rachelina-vinica.html']]],
        926 => ['name' => 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru', 'emails' => [['email' => 'tajomnik@kultura.kbs.sk', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20250929021']]],
        952 => ['name' => 'Subkomisia pre bioetiku Teologickej komisie Konferencie biskupov Slovenska', 'emails' => [['email' => 'tajomnik@bioetika.kbs.sk', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20240411021']]],
        1033 => ['name' => 'Tím zasvätených laikov Miles Jesu', 'emails' => [['email' => 'milesjesu.bb@gmail.com', 'found_on' => 'https://www.vyveska.sk/26209/kto-som.html']]],
        1203 => ['name' => 'Oravské centrum mládeže', 'emails' => [['email' => 'ocm@ozviac.sk', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20230824015']]],
        1243 => ['name' => 'Biskupský úrad VD ECAV na Slovensku', 'emails' => [['email' => 'tajomnik@vdecav.sk', 'found_on' => 'https://www.ecav.sk/aktuality/pozvanky/pastoralna-konferencia-vd-navrat-k-prvej-laske']]],
        1374 => ['name' => 'Biskupské úrady VD a ZD ECAV na Slovensku', 'emails' => [['email' => 'tajomnik@vdecav.sk', 'found_on' => 'https://www.ecav.sk/aktuality/pozvanky/online-kaznove-pripravky-k-postu-a-velkej-noci-2023']]],
        1297 => ['name' => 'Misionári Najsvätejšich Sŕdc Pána Ježiša a Panny Márie', 'emails' => [['email' => 'lukas@msscc.sk', 'found_on' => 'https://www.vyveska.sk/sv-omsa-a-odaracia-za-rozpoznanie-a-upevnenie-svojho-povolania.html']]],
        1348 => ['name' => 'CK Trinity Košice', 'emails' => [['email' => 'info@cktrinity.sk', 'found_on' => 'https://www.ecav.sk/aktuality/pozvanky/poznavaci-zajazd-do-izraela']]],
        1350 => ['name' => 'Kláštor Panny Márie Karmelskej a sv. Jozefa', 'emails' => [['email' => 'dommodlitby.lorincik@gmail.com', 'found_on' => 'https://www.vyveska.sk/24456/obnova-pre-zeny.html']]],
        52 => ['name' => 'Farnosť Oščadnica', 'emails' => [['email' => 'oscadnica@dcza.sk', 'found_on' => 'https://oscadnica.fara.sk/kontakt/']]],
        661 => ['name' => 'Slovensko na kolenách', 'emails' => [['email' => 'slovenskonakolenach@gmail.com', 'found_on' => 'https://slovenskonakolenach.sk']]],
        1730 => ['name' => 'Východný dištrikt Evanjelickej cirkvi augsburského vyznania na Slovensku', 'emails' => [['email' => 'sekretariat@vdecav.sk', 'found_on' => 'https://vdecav.sk/kontakt/']]],
        1255 => ['name' => 'Slovenská filharmónia', 'website' => 'https://www.filharmonia.sk', 'emails' => [['email' => 'filharmonia@filharmonia.sk', 'found_on' => 'https://filharmonia.sk']]],
        477 => ['name' => 'Vydavateľstvo Dobrá kniha', 'website' => 'https://www.dobrakniha.sk'],
        807 => ['name' => 'Bratia pallotíni', 'website' => 'https://pallotini.sk', 'emails' => [['email' => 'info@pallotini.sk', 'found_on' => 'https://pallotini.sk/kontakt/']]],
        871 => ['name' => 'Nadácia OPORA', 'website' => 'https://www.nadaciaopora.sk', 'emails' => [['email' => 'info@nadaciaopora.sk', 'found_on' => 'https://www.nadaciaopora.sk/kontakt/']]],
        637 => ['name' => 'Mesto Trnava', 'website' => 'https://www.trnava.sk', 'emails' => [['email' => 'info@trnava.sk', 'found_on' => 'https://trnava.sk/stranka/kontakt']]],
        44 => ['name' => 'Mesto Bardejov', 'website' => 'https://www.bardejov.sk', 'emails' => [['email' => 'info@bardejov.sk', 'found_on' => 'https://bardejov.sk']]],
        578 => ['name' => 'Obec Cífer', 'website' => 'https://www.cifer.sk', 'emails' => [['email' => 'sekretariat@cifer.sk', 'found_on' => 'https://cifer.sk']]],
        1046 => ['name' => 'Obecný úrad Kostoľany pod Tribečom', 'website' => 'https://www.kostolanypodtribecom.sk', 'emails' => [['email' => 'podatelna@kostolanypodtribecom.sk', 'found_on' => 'https://kostolanypodtribecom.sk']]],
        1120 => ['name' => 'Správa Tatranského národného parku', 'website' => 'https://www.tanap.sk', 'emails' => [['email' => 'sekretariat@tanap.sk', 'found_on' => 'https://www.tanap.sk/kontakt/']]],
        930 => ['name' => 'Incheba Expo Bratislava', 'website' => 'https://www.incheba.sk', 'emails' => [['email' => 'incheba@incheba.sk', 'found_on' => 'https://incheba.sk']]],
        1369 => ['name' => 'Prešovský samosprávny kraj', 'website' => 'https://www.psk.sk', 'emails' => [['email' => 'info@psk.sk', 'found_on' => 'https://psk.sk/domov/kontakty/']]],
        636 => ['name' => 'Prešovské arcibiskupstvo', 'website' => 'https://www.grkatpo.sk', 'emails' => [['email' => 'abu@grkatpo.sk', 'found_on' => 'https://grkatpo.sk']]],
        1725 => ['name' => 'Rožňavský biskup', 'website' => 'https://www.burv.sk', 'emails' => [['email' => 'kancelaria@burv.sk', 'found_on' => 'https://burv.sk']]],
        147 => ['name' => 'Prešovská gotika, o.z.', 'website' => 'https://www.presovskagotika.sk', 'emails' => [['email' => 'info@presovskagotika.sk', 'found_on' => 'https://www.presovskagotika.sk/kontakt/']]],
        403 => ['name' => 'Powertabory o.z', 'website' => 'https://www.powertabory.sk', 'emails' => [['email' => 'powertabory@powertabory.sk', 'found_on' => 'https://powertabory.sk']]],
        1455 => ['name' => 'Iniciatíva Kresťan na vidieku', 'website' => 'https://krestannavidieku.sk', 'emails' => [['email' => 'info@krestannavidieku.sk', 'found_on' => 'https://krestannavidieku.sk/kontakt/']]],
        1709 => ['name' => 'Združenie Amazi', 'website' => 'https://www.amazi.sk', 'emails' => [['email' => 'info@amazi.sk', 'found_on' => 'https://www.amazi.sk/kontakt-amazi/']]],
        914 => ['name' => 'Spoločenstvo Lachaj Roi', 'website' => 'https://lachajroi.sk', 'emails' => [['email' => 'hlavny.animator@lachajroi.sk', 'found_on' => 'https://lachajroi.sk/kontakt/']]],
        35 => ['name' => 'jezuiti', 'website' => 'https://jezuiti.sk'],
        89 => ['name' => 'Farnosť Gaboltov', 'website' => 'https://gaboltov.rimkat.sk'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canal_emails')) {
            return;
        }

        $emails = app(CanalEmails::class);

        foreach (self::CONTACTS as $id => $contact) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $contact['name']) {
                continue;
            }

            $changes = [];

            if (isset($contact['website']) && (($contact['replace_website'] ?? false) || Url::normalize($canal->website) === null)) {
                $changes['website'] = $contact['website'];
            }

            if (isset($contact['phone']) && trim((string) $canal->phone) === '') {
                $changes['phone'] = $contact['phone'];
            }

            if ($changes !== [] && $changes !== $canal->only(array_keys($changes))) {
                // Cez Eloquent — CanalObserver po novom webe zruší odklad hľadania adresy.
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'contacts_filled', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['changes' => $changes]);
            }

            if (! empty($contact['emails'])) {
                $emails->discover($canal->fresh(), $contact['emails'], 'manual');
            }

            // Adresa z importu mohla byť v zozname, no nie primárna (napr. Fórum života) —
            // discover ju ako známu preskočí, primárnu dorovná až refresh.
            $emails->refresh($canal->fresh());
        }
    }

    public function down(): void
    {
        // Doplnené kontakty sa nevracajú — sú overené na zdroji a ďalej sa s nimi pracuje.
    }
};
