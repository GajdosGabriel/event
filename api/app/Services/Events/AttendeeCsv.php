<?php

namespace App\Services\Events;

use App\Models\Admission;
use App\Models\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV zoznam prihlásených na podujatie.
 *
 * Bodkočiarka a BOM sú tu kvôli Excelu v slovenskom prostredí: s čiarkou
 * naleje celý riadok do jedného stĺpca a bez BOM zobrazí diakritiku ako
 * neznáme znaky. Odpoveď sa streamuje — zoznam môže mať tisíce riadkov
 * a nie je dôvod držať ho celý v pamäti.
 */
class AttendeeCsv
{
    private const DELIMITER = ';';

    private const HEADER = [
        'Objednávka',
        'Objednávateľ',
        'E-mail objednávateľa',
        'Telefón',
        'Účastník',
        'E-mail účastníka',
        'Typ lístka',
        'Stav vstupenky',
        'Potvrdenie účasti',
        'Vstup',
        'Cena',
        'Platba',
        'Objednané',
    ];

    public function __construct(private readonly AttendeeDirectory $directory)
    {
    }

    public function response(Event $event): StreamedResponse
    {
        $rows = $this->directory->rows($event);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');

            echo "\xEF\xBB\xBF";

            fputcsv($handle, self::HEADER, self::DELIMITER);

            foreach ($rows as $admission) {
                fputcsv($handle, $this->row($admission), self::DELIMITER);
            }

            fclose($handle);
        }, $this->filename($event), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<int, string> */
    private function row(Admission $admission): array
    {
        $ticket = $admission->ticket;

        return [
            (string) $ticket?->uuid,
            (string) $ticket?->holder_name,
            (string) $ticket?->holder_email,
            (string) $ticket?->holder_phone,
            (string) $admission->attendee_name,
            (string) $admission->attendee_email,
            (string) $admission->ticketType?->name,
            $admission->status?->label() ?? '',
            $admission->confirmation_status?->label() ?? '',
            $admission->checked_in_at?->format('d.m.Y H:i') ?? '',
            $this->price($ticket?->price_amount, $ticket?->price_currency),
            $ticket?->payment_status?->label() ?? '',
            $admission->created_at?->format('d.m.Y H:i') ?? '',
        ];
    }

    /** Ceny sú v centoch; desatinná čiarka je to, čo slovenský Excel čaká. */
    private function price(?int $amount, ?string $currency): string
    {
        if ($amount === null) {
            return '';
        }

        return number_format($amount / 100, 2, ',', '') . ' ' . ($currency ?? 'EUR');
    }

    private function filename(Event $event): string
    {
        $slug = trim((string) $event->slug) ?: 'podujatie';

        return 'ucastnici-' . $slug . '-' . now()->format('Y-m-d') . '.csv';
    }
}
