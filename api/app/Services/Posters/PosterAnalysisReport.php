<?php

namespace App\Services\Posters;

use Carbon\Carbon;

/**
 * Preloží surový výstup detektora na to, čo má vidieť človek: čo AI z plagátu
 * prečítala, čo nenašla a čo bez doplnenia zablokuje uloženie.
 *
 * Zámerne to nie je „skóre spoľahlivosti" — model žiadne poctivé neposkytuje
 * a vymyslené percento by len klamalo. Rozlišujeme preto len tri stavy:
 * našlo / nenašlo / odhadnuté (hodnota vznikla dopočítaním, nie z plagátu).
 */
class PosterAnalysisReport
{
    public const STATUS_FOUND = 'found';
    public const STATUS_MISSING = 'missing';
    public const STATUS_GUESSED = 'guessed';

    /**
     * @param  array<string, mixed>  $detection  výstup Detector::detectFromPoster()
     * @return array<string, mixed>
     */
    public function build(array $detection, PosterExtraction $extraction): array
    {
        $payload = (array) ($detection['event_payload'] ?? []);
        $venue = (array) ($payload['venue'] ?? []);
        $organizer = (array) ($payload['organizer'] ?? []);

        $startAt = $this->stringOrNull($payload['start_at'] ?? null);
        $endAt = $this->stringOrNull($payload['end_at'] ?? null);

        $fields = [
            $this->field('title', 'Názov podujatia', $this->stringOrNull($payload['title'] ?? null), required: true),
            $this->field('start_at', 'Začiatok', $this->formatDateTime($startAt), required: true),
            // Koniec sa v plagáte uvádza málokedy — keď ho AI doplnila podľa
            // typu akcie, nie je to nález, ale odhad, a človek to má vidieť.
            $this->field(
                'end_at',
                'Koniec',
                $this->formatDateTime($endAt),
                required: false,
                status: $endAt !== null && $startAt !== null ? self::STATUS_GUESSED : null,
                note: $this->endAtNote($startAt, $endAt),
            ),
            $this->field('venue', 'Miesto konania', $this->joinParts([
                $this->stringOrNull($venue['name'] ?? null),
                $this->stringOrNull($venue['street_and_number'] ?? null),
                $this->stringOrNull($venue['city'] ?? null),
            ]), required: true),
            $this->field('organizer', 'Organizátor', $this->joinParts([
                $this->stringOrNull($organizer['name'] ?? null),
                $this->stringOrNull($organizer['city'] ?? null),
            ]), required: false),
            $this->field('email', 'Kontaktný e-mail', $this->stringOrNull($payload['email'] ?? null), required: false),
            $this->field('phone', 'Telefón', $this->stringOrNull($payload['phone'] ?? null), required: false),
            // Popis má dva zdroje: text prepísaný copywriterom a — keď
            // copywriter zlyhá alebo sa preň text neposiela — surový text
            // z dokumentu. Kým sa tu pozeralo len na copywritera, hlásili sme
            // „nenašli sme popis" aj pri plagáte so 7 000 znakmi textu, ktorý
            // sa pri uložení aj tak stal telom podujatia. Report musí ukazovať
            // to isté, čo naozaj uložíme.
            $this->descriptionField($detection, $extraction),
        ];

        $missingRequired = array_values(array_map(
            static fn (array $field) => $field['key'],
            array_filter(
                $fields,
                static fn (array $field) => $field['required'] && $field['status'] === self::STATUS_MISSING,
            ),
        ));

        return [
            'fields' => $fields,
            'found_count' => count(array_filter($fields, static fn ($f) => $f['status'] !== self::STATUS_MISSING)),
            'total_count' => count($fields),
            'missing_required' => $missingRequired,
            'can_save' => $missingRequired === [],
            'matches' => [
                // Existujúci kanál/miesto sa nezakladá znova — používateľ má
                // vidieť, na čo sa podujatie napojí, ešte pred uložením.
                'canal' => $detection['organizer_canal']['existing'] ?? null,
                'venue' => $detection['venue_detect']['existing_venue'] ?? null,
            ],
            'source' => $extraction->toArray(),
            'notice' => $this->notice($extraction),
        ];
    }

    /**
     * @param  array<string, mixed>  $detection
     * @return array<string, mixed>
     */
    private function descriptionField(array $detection, PosterExtraction $extraction): array
    {
        $corrected = $this->stringOrNull($detection['corrected_text'] ?? null);

        if ($corrected !== null) {
            return $this->field('description', 'Popis', $corrected, required: false, preview: true);
        }

        // Prepis plagátu z vision je pri obrázkovom plagáte jediný text, ktorý
        // máme — dokument textovú vrstvu nemá, takže `extraction->text` je prázdny.
        $transcribed = $this->stringOrNull($detection['poster_text'] ?? null);
        $raw = $transcribed ?? $this->stringOrNull($extraction->text);

        return $this->field(
            'description',
            'Popis',
            $raw,
            required: false,
            status: $raw !== null ? self::STATUS_GUESSED : null,
            note: match (true) {
                $raw === null => null,
                $transcribed !== null => 'Prepísané z plagátu — skontrolujte a pokojne prepíšte.',
                default => 'Prevzaté z dokumentu bez úpravy — pokojne ho prepíšte.',
            },
            preview: true,
        );
    }

    /**
     * Celodenné podujatie (00:00–23:59) nevzniklo odhadom dĺžky, ale tým, že
     * plagát čas neuvádzal vôbec — „odhadnuté podľa typu podujatia" by tam
     * bola nepravda a človek by hľadal chybu tam, kde nie je.
     */
    private function endAtNote(?string $startAt, ?string $endAt): ?string
    {
        if ($startAt === null || $endAt === null) {
            return null;
        }

        return $this->isAllDay($startAt, $endAt)
            ? 'Plagát neuvádzal čas — nastavili sme celý deň.'
            : 'Odhadnuté podľa typu podujatia — skontrolujte.';
    }

    private function isAllDay(string $startAt, string $endAt): bool
    {
        try {
            return Carbon::parse($startAt)->format('H:i') === '00:00'
                && Carbon::parse($endAt)->format('H:i') === '23:59';
        } catch (\Throwable) {
            return false;
        }
    }

    private function notice(PosterExtraction $extraction): ?string
    {
        if ($extraction->kind === 'image') {
            return 'Plagát je obrázok, čítali sme ho zrakom modelu. Údaje si prosím prekontrolujte.';
        }

        // Pri PDF sa „nemá textovú vrstvu" tvrdiť nedá — dokument ju mať môže
        // a zlyhať mohla len extrakcia (konvertor bez poppleru vráti prázdny
        // text presne ako sken). Formulácia preto hovorí, čo vieme: text sme
        // neprečítali. Príčinu treba hľadať v logu, nie tipovať používateľovi.
        if ($extraction->usesVision() && ! $extraction->hasUsableText()) {
            return 'Text z dokumentu sa nepodarilo prečítať, čítali sme ho z obrázka strán. Údaje si prosím prekontrolujte.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function field(
        string $key,
        string $label,
        ?string $value,
        bool $required,
        ?string $status = null,
        ?string $note = null,
        bool $preview = false,
    ): array {
        $resolved = $value === null || trim($value) === ''
            ? self::STATUS_MISSING
            : ($status ?? self::STATUS_FOUND);

        return [
            'key' => $key,
            'label' => $label,
            'value' => $resolved === self::STATUS_MISSING ? null : $value,
            'status' => $resolved,
            'required' => $required,
            'note' => $resolved === self::STATUS_MISSING ? null : $note,
            'preview' => $preview,
        ];
    }

    /** @param array<int, string|null> $parts */
    private function joinParts(array $parts): ?string
    {
        $clean = array_values(array_filter($parts, static fn ($p) => is_string($p) && trim($p) !== ''));

        return $clean === [] ? null : implode(', ', $clean);
    }

    private function formatDateTime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
