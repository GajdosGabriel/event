<?php

namespace App\Support;

/**
 * Kontakty podujatia z odpovede AI: jeden primárny e-mail / telefón
 * (stĺpce `email`, `phone`) a zvyšok v `additional_emails` / `additional_phones`.
 *
 * AI vie vrátiť jeden reťazec s viacerými hodnotami ("a, b, c"), pole `emails` /
 * `phones`, alebo oboje. Primárny je vždy prvý — `email` / `phone` majú prednosť
 * pred poľom, lebo ich model dopĺňa ako "hlavný kontakt pre záujemcov".
 */
final class ContactList
{
    public const EMAIL_MAX = 100;

    public const PHONE_MAX = 30;

    private const MIN_PHONE_DIGITS = 6;

    /**
     * @param  array<string, mixed>  $payload  odpoveď AI (kľúče email, emails, phone, phones)
     * @return array{email: ?string, additional_emails: list<string>, phone: ?string, additional_phones: list<string>}
     */
    public static function fromPayload(array $payload): array
    {
        $emails = self::emails([$payload['email'] ?? null, $payload['emails'] ?? null]);
        $phones = self::phones([$payload['phone'] ?? null, $payload['phones'] ?? null]);

        return [
            'email' => $emails[0] ?? null,
            'additional_emails' => array_slice($emails, 1),
            'phone' => $phones[0] ?? null,
            'additional_phones' => array_slice($phones, 1),
        ];
    }

    /**
     * @return list<string> platné, malými písmenami, bez duplicít
     */
    public static function emails(mixed $value): array
    {
        $result = [];

        foreach (self::flatten($value) as $candidate) {
            foreach (preg_split('/[\s,;]+/u', $candidate) ?: [] as $part) {
                $part = mb_strtolower(trim($part, " \t\n\r\0\x0B<>()[].,;:"));

                if ($part !== ''
                    && mb_strlen($part) <= self::EMAIL_MAX
                    && filter_var($part, FILTER_VALIDATE_EMAIL) !== false) {
                    $result[$part] = $part;
                }
            }
        }

        return array_values($result);
    }

    /**
     * @return list<string> s aspoň 6 číslicami, bez duplicít (porovnáva sa podľa číslic)
     */
    public static function phones(mixed $value): array
    {
        $result = [];

        foreach (self::flatten($value) as $candidate) {
            foreach (preg_split('/\s*(?:,|;|\/|\|)\s*|\s+(?:alebo|or|a)\s+/iu', $candidate) ?: [] as $part) {
                $part = trim($part);
                $digits = preg_replace('/\D+/', '', $part) ?? '';

                if (strlen($digits) < self::MIN_PHONE_DIGITS || mb_strlen($part) > self::PHONE_MAX) {
                    continue;
                }

                $result[$digits] ??= $part;
            }
        }

        return array_values($result);
    }

    /**
     * @return list<string>
     */
    private static function flatten(mixed $value): array
    {
        if (is_string($value)) {
            return trim($value) === '' ? [] : [trim($value)];
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            array_push($out, ...self::flatten($item));
        }

        return $out;
    }
}
