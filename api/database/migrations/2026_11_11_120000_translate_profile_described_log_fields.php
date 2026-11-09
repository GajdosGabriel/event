<?php

use App\Models\SystemLog;
use Illuminate\Database\Migrations\Migration;

/**
 * Záznamy denníka „profile_described“ a „contacts_filled“ uvádzali v správe
 * názvy stĺpcov – napr. „doplnené body, postcode, latitude…“, čo sa čítalo
 * ako „body“ (bodovanie). Správu prepíšeme na slovenské názvy údajov;
 * technický zoznam stĺpcov ostáva v context.fields.
 */
return new class extends Migration
{
    private const LABELS = [
        'body' => 'popis',
        'street' => 'ulica',
        'postcode' => 'PSČ',
        'village_id' => 'obec',
        'municipality_id' => 'obec',
        'website' => 'web',
        'phone' => 'telefón',
        'latitude' => 'súradnice',
        'longitude' => 'súradnice',
        'coordinates_source' => 'súradnice',
    ];

    private const MARKER = ': doplnené ';

    public function up(): void
    {
        SystemLog::query()
            ->whereIn('event', ['canals.profile_described', 'venues.profile_described', 'canals.contacts_filled'])
            ->where('message', 'like', '%'.self::MARKER.'%')
            ->chunkById(500, function ($logs) {
                foreach ($logs as $log) {
                    $context = $log->context ?? [];
                    $fields = $context['fields'] ?? (is_array($context['changes'] ?? null) ? array_keys($context['changes']) : null);
                    $position = mb_strrpos($log->message, self::MARKER);

                    if (! is_array($fields) || $fields === [] || $position === false) {
                        continue;
                    }

                    $labels = array_values(array_unique(array_map(
                        fn (string $field) => self::LABELS[$field] ?? $field,
                        $fields,
                    )));

                    $log->message = mb_substr($log->message, 0, $position).' – doplnené: '.implode(', ', $labels);
                    $log->saveQuietly();
                }
            });
    }
};
