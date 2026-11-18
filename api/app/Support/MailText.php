<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Jednotný zápis dátumov, názvov a citácií v e-mailoch.
 *
 * Dátumy: „št 25. 11. 2027 o 16:00", rozsah s „–". Vzor drží preklad
 * (`mail.common.date_*`), aby každý jazyk mal svoj poriadok slov. API
 * a frontend majú vlastný zápis ({@see EventDateRange}), tento je len pre poštu.
 */
final class MailText
{
    /** Zóna, v ktorej sa termíny zobrazujú; v DB je UTC. */
    private const TIMEZONE = 'Europe/Bratislava';

    /** Dĺžka názvu podujatia v predmete e-mailu, nech sa zmestí do schránky. */
    private const SUBJECT_NAME_LIMIT = 60;

    /** Okamih — „št 25. 11. 2027 o 16:00". */
    public static function moment(?CarbonInterface $value): ?string
    {
        return $value ? self::local($value)->isoFormat(__('mail.common.date_moment')) : null;
    }

    /** Deň bez času — „št 25. 11. 2027". */
    public static function day(?CarbonInterface $value): ?string
    {
        return $value ? self::local($value)->isoFormat(__('mail.common.date_day')) : null;
    }

    /** Termín podujatia: okamih, celý deň alebo rozsah s „–". */
    public static function range(?CarbonInterface $start, ?CarbonInterface $end): ?string
    {
        if (! $start) {
            return null;
        }

        $start = self::local($start);
        $end = $end ? self::local($end) : null;

        if (! $end) {
            return self::moment($start);
        }

        // Celodenné podujatie (00:00 – 23:59 alebo do polnoci) — stačí dátum.
        if ($start->format('H:i') === '00:00' && in_array($end->format('H:i'), ['23:59', '00:00'], true) && $end->greaterThan($start)) {
            $lastDay = $end->format('H:i') === '00:00' ? $end->copy()->subMinute() : $end;

            return $start->isSameDay($lastDay)
                ? self::day($start)
                : self::day($start).' – '.self::day($lastDay);
        }

        if ($start->isSameDay($end)) {
            return self::moment($start).' – '.$end->format('H:i');
        }

        return self::moment($start).' – '.self::moment($end);
    }

    /**
     * Termín a miesto podujatia pre šablónu `mail.partials.when`.
     *
     * @return array{when: ?string, where: ?string}
     */
    public static function eventPlace(?Event $event): array
    {
        if (! $event) {
            return ['when' => null, 'where' => null];
        }

        // Notifikácie bežia vo fronte — model je čerstvo načítaný bez relácií.
        $venue = $event->loadMissing('venue')->venue;
        $where = implode(', ', array_filter([trim((string) $venue?->name), trim((string) $venue?->street)]));

        return [
            'when' => self::range($event->start_at, $event->end_at),
            'where' => $where !== '' ? $where : null,
        ];
    }

    /**
     * Názov do vety „…**„:name"**." — bez koncovej bodky, inak by vzniklo
     * „…v Bošanoch."**. (importované názvy bývajú celé vety).
     */
    public static function name(?string $name): string
    {
        return rtrim(trim((string) $name), " \t.");
    }

    /** Názov do predmetu: bez koncovej bodky a skrátený na rozumnú dĺžku. */
    public static function subjectName(?string $name): string
    {
        return Str::limit(self::name($name), self::SUBJECT_NAME_LIMIT, '…', preserveWords: true);
    }

    /**
     * Citácia textu cez viac riadkov. MailMessage::line() riadky spája do
     * jedného, takže Markdown „> " by citoval len prvý riadok a zvyšok by
     * ostal s „>" uprostred vety.
     */
    public static function quote(string $text): HtmlString
    {
        $html = nl2br(e(trim(str_replace(["\r\n", "\r"], "\n", $text))), false);

        return new HtmlString('<blockquote style="margin:0 0 16px;padding:8px 16px;border-left:4px solid #d0d7de;color:#4b5563;">'.$html.'</blockquote>');
    }

    /** Odsek s viacriadkovým textom (zachová zalomenia riadkov). */
    public static function paragraph(string $text): HtmlString
    {
        return new HtmlString('<p>'.nl2br(e(trim(str_replace(["\r\n", "\r"], "\n", $text))), false).'</p>');
    }

    private static function local(CarbonInterface $value): CarbonInterface
    {
        return $value->copy()->setTimezone(self::TIMEZONE)->locale(app()->getLocale());
    }
}
