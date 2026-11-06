<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Oprava podujatia #11284 (konferencia o pátrovi Vendelínovi Javorkovi)
 * na žiadosť organizátora — prodekana Teologickej fakulty TU, e-mail 5. 11. 2026.
 * Údaje overené v oficiálnej pozvánke fakulty:
 * https://tf.truni.sk/aktuality/vendelin-javorka-konferencia-2026
 *
 *  - titulok: konferencia je v Bratislave, nie v Trnave,
 *  - miesto: AI z článku TK KBS založila miesto #1049 s názvom fakulty UK;
 *    patrí len tomuto podujatiu, preto sa opraví na Aulu TF TU, Kostolná 1
 *    (súradnice z NominatimGeocoder::lookupAddress),
 *  - koniec: 13:15, nie 12:30 (časy sú v UTC, november = UTC+1).
 *
 * Každá zmena sa robí len keď záznam stále drží pôvodný údaj — ručná úprava
 * medzitým má prednosť. Opakovaný import článok preskočí (podujatie je úplné).
 */
return new class extends Migration
{
    private const EVENT = 11284;

    private const VENUE = 1049;

    private const OLD_NAME = 'Konferencia v Trnave priblíži život a duchovný odkaz pátra Vendelína Javorku';

    private const NEW_NAME = 'Konferencia v Bratislave priblíži život a duchovný odkaz pátra Vendelína Javorku';

    private const OLD_END = '2026-11-07 11:30:00';

    private const NEW_END = '2026-11-07 12:15:00';

    private const OLD_VENUE = 'Rímskokatolícka cyrilometodská bohoslovecká fakulta Univerzity Komenského v Bratislave';

    private const NEW_VENUE = 'Aula Teologickej fakulty Trnavskej univerzity';

    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('venues')) {
            return;
        }

        DB::transaction(function () {
            $event = DB::table('events')->where('id', self::EVENT)->first();

            if ($event === null) {
                return;
            }

            $update = [];

            if ($event->name === self::OLD_NAME) {
                $update['name'] = self::NEW_NAME;
                $update['slug'] = Str::slug(self::NEW_NAME);
                // Titulok je aj v úvode popisu.
                $update['body'] = str_replace(self::OLD_NAME, self::NEW_NAME, (string) $event->body);
            }

            if ((string) $event->end_at === self::OLD_END) {
                $update['end_at'] = self::NEW_END;
            }

            if ($update !== []) {
                DB::table('events')->where('id', self::EVENT)->update($update + ['updated_at' => now()]);
            }

            DB::table('venues')
                ->where('id', self::VENUE)
                ->where('name', self::OLD_VENUE)
                ->update([
                    'name' => self::NEW_VENUE,
                    'slug' => Str::slug(self::NEW_VENUE),
                    'street' => 'Kostolná 1',
                    'postcode' => '811 01',
                    'country' => 'Slovensko',
                    'latitude' => 48.1441804,
                    'longitude' => 17.1090877,
                    'coordinates_source' => 'address',
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('venues')) {
            return;
        }

        DB::transaction(function () {
            $event = DB::table('events')->where('id', self::EVENT)->first();

            if ($event !== null && $event->name === self::NEW_NAME) {
                DB::table('events')->where('id', self::EVENT)->update([
                    'name' => self::OLD_NAME,
                    'slug' => Str::slug(self::OLD_NAME),
                    'body' => str_replace(self::NEW_NAME, self::OLD_NAME, (string) $event->body),
                ]);
            }

            DB::table('events')->where('id', self::EVENT)->where('end_at', self::NEW_END)
                ->update(['end_at' => self::OLD_END]);

            DB::table('venues')->where('id', self::VENUE)->where('name', self::NEW_VENUE)->update([
                'name' => self::OLD_VENUE,
                'slug' => Str::slug(self::OLD_VENUE),
                'street' => null,
                'postcode' => null,
                'latitude' => 48.1435026,
                'longitude' => 17.1082867,
                'coordinates_source' => 'municipality',
            ]);
        });
    }
};
