<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;
use App\Support\Url;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Jednorazové doplnenie kontaktov kanálov s aktuálnou akciou (november 2026).
 *
 * Dopĺňanie profilov cez OpenAI malo minutý rozpočet a fronta stála, takže
 * oslovenie na prevzatie nemalo komu písať. Weby a adresy nižšie sú ručne
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
        42 => ['name' => 'MEA GRATI', 'website' => 'https://www.meagrati.sk', 'emails' => [['email' => 'info@meagrati.sk', 'found_on' => 'https://www.meagrati.sk']]],
        63 => ['name' => 'Pápežské misijné diela na Slovensku', 'website' => 'https://misijnediela.sk', 'replace_website' => true, 'emails' => [['email' => 'info@misijnediela.sk', 'found_on' => 'https://misijnediela.sk']]],
        75 => ['name' => 'Duchovné centrum Lukov dvor', 'phone' => '0905 908 171', 'emails' => [['email' => 'hd.lukovdvor@gmail.com', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20250701005']]],
        98 => ['name' => 'Bratislavská arcidiecéza', 'website' => 'https://www.abu.sk', 'replace_website' => true, 'emails' => [['email' => 'abu@abu.sk', 'found_on' => 'https://www.abu.sk/kontakty-na-arcibiskupsky-urad']]],
        106 => ['name' => 'Spolok svätého Vojtecha', 'emails' => [['email' => 'ssv@ssv.sk', 'found_on' => 'https://www.ssv.sk']]],
        109 => ['name' => 'KBkouč - Mgr. Katarína Bujnová', 'website' => 'https://kbkouc.sk', 'phone' => '+421 904 682 878', 'emails' => [['email' => 'kbkouc@gmail.com', 'found_on' => 'https://kbkouc.sk/kontakt.html']]],
        152 => ['name' => 'Informačné centrum Mir Medjugorje CZ', 'website' => 'https://www.medjugorje.cz', 'emails' => [['email' => 'info@medjugorje.cz', 'found_on' => 'https://www.medjugorje.cz/kontakt']]],
        243 => ['name' => 'Pápežská nadácia ACN – Pomoc trpiacej Cirkvi', 'website' => 'https://acnslovensko.sk', 'replace_website' => true, 'emails' => [['email' => 'info@acnslovensko.sk', 'found_on' => 'https://acnslovensko.sk/kontakt/']]],
        278 => ['name' => 'Dom Quo Vadis', 'website' => 'https://www.domquovadis.sk', 'emails' => [['email' => 'info@domquovadis.sk', 'found_on' => 'https://www.domquovadis.sk/kontakt/']]],
        336 => ['name' => 'Milujúca náruč, n.o', 'phone' => '+421 940 619 889', 'emails' => [['email' => 'pomoc@milujucanaruc.sk', 'found_on' => 'https://www.milujucanaruc.sk/kontakt/'], ['email' => 'milujucanaruc@gmail.com', 'found_on' => 'https://www.vyveska.sk/duchovne-cvicenia-pre-bezdetnych-manzelov/']]],
        368 => ['name' => 'Trnavská univerzita v Trnave', 'emails' => [['email' => 'pr@truni.sk', 'found_on' => 'https://truni.sk/rektorat-trnavskej-univerzity-kontakty']]],
        475 => ['name' => 'saleziánska rodina', 'website' => 'https://saleziani.sk', 'emails' => [['email' => 'sekretariat@saleziani.sk', 'found_on' => 'https://saleziani.sk/kontakt/']]],
        482 => ['name' => 'Štátna vedecká knižnica', 'website' => 'https://www.svkbb.eu', 'emails' => [['email' => 'svkbb@svkbb.eu', 'found_on' => 'https://svkbb.eu/statna-vedecka-kniznica-v-banskej-bystrici/kontakt/']]],
        776 => ['name' => 'Liga pár páru', 'phone' => '0908 722 055'],
        812 => ['name' => 'Spoločenstvo Joel', 'emails' => [['email' => 'slavomir.palfi@gmail.com', 'found_on' => 'https://www.vyveska.sk/kurz-charizmy-sv-ducha-2/']]],
        850 => ['name' => 'Fórum života o.z', 'website' => 'https://forumzivota.sk', 'replace_website' => true, 'emails' => [['email' => 'kancelaria@forumzivota.sk', 'found_on' => 'https://forumzivota.sk']]],
        870 => ['name' => 'Rodinné centrum Drobček', 'emails' => [['email' => 'info@rcdrobcek.sk', 'found_on' => 'https://www.rcdrobcek.sk/kontakt/']]],
        873 => ['name' => 'Familiaris občianske združenie', 'website' => 'https://www.familiaris.sk', 'phone' => '0948 619 739', 'emails' => [['email' => 'info@familiaris.sk', 'found_on' => 'https://familiaris.sk/kontakt/']]],
        1012 => ['name' => 'Sestry Congregatio Jesu', 'emails' => [['email' => 'onlineduchovnecvicenia@gmail.com', 'found_on' => 'https://www.onlineduchovnecvicenia.sk'], ['email' => 'cjcentrum@gmail.com', 'found_on' => 'https://www.vyveska.sk/tri-dni-s-encyklikou-laudato-si.html']]],
        1056 => ['name' => 'Slovenský spevácky zbor ADOREMUS', 'phone' => '0905 420 360', 'emails' => [['email' => 'adoremus@adoremus.sk', 'found_on' => 'https://www.tkkbs.sk/view.php?cisloclanku=20230517023']]],
        1134 => ['name' => 'Úrad pre apoštolát ochrany života v Košickej arcidiecéze', 'website' => 'https://www.rodinake.sk', 'phone' => '0905 309 551', 'emails' => [['email' => 'rodina@abuke.sk', 'found_on' => 'https://www.rodinake.sk/kontakt-2/']]],
        1346 => ['name' => 'Fórum kresťanských inštitúcií, o.z', 'website' => 'https://fki.sk', 'replace_website' => true, 'emails' => [['email' => 'info@fki.sk', 'found_on' => 'https://fki.sk/uvod/kontakt/']]],
        1387 => ['name' => 'Katolícke biblické dielo na Slovensku', 'emails' => [['email' => 'kbd@kbd.sk', 'found_on' => 'https://www.kbd.sk']]],
        1460 => ['name' => 'Kongregácia sestier dominikánok bl. Imeldy', 'website' => 'https://www.dominikanky.sk', 'emails' => [['email' => 'generalat@dominikanky.sk', 'found_on' => 'https://dominikanky.sk/kontakt/']]],
        1883 => ['name' => 'Kongregácia sestier Služobníčok Najsvätejšej Panny Márie Nepoškvrnene počatej', 'website' => 'https://sluzobnicky.sk', 'emails' => [['email' => 'sluzobnicky@gmail.com', 'found_on' => 'https://sluzobnicky.sk/kontakt/']]],
        1896 => ['name' => 'Coworking Poprad', 'website' => 'https://coworkingpoprad.sk', 'phone' => '+421 903 733 057', 'emails' => [['email' => 'ahoj@coworkingpoprad.sk', 'found_on' => 'https://coworkingpoprad.sk']]],
        1933 => ['name' => 'Bratislava Mozart Festival', 'website' => 'https://bratislavamozartfestival.sk'],
        1945 => ['name' => 'Rodina sv. Šarbela', 'emails' => [['email' => 'rodinasarbela@gmail.com', 'found_on' => 'https://rodinasarbela.cz/kontakty/']]],
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
