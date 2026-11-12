<?php

namespace App\Support;

use App\Models\Canal;
use App\Models\Venue;

/**
 * Odtlačok podkladov, z ktorých sa hľadá profil, poloha alebo e-mail.
 *
 * Hľadanie s tými istými podkladmi vráti to isté — opakovať ho je plytvanie
 * (Nominatim s povinným odstupom, platené AI). Každé hľadanie si preto
 * pamätá odtlačok, s ktorým bežalo, a znova sa spustí, až keď pribudne nový
 * podklad (web, adresa, obec…) alebo ho admin spustí ručne.
 */
final class LookupInputs
{
    /** Kanál je organizátor — jeho poloha sa podľa názvu nehľadá, len podľa adresy a obce. */
    public static function coordinates(Canal|Venue $model): string
    {
        return self::hash([
            $model instanceof Venue ? $model->name : null,
            self::municipality($model),
            $model->street,
            $model->postcode,
            $model->country,
        ]);
    }

    /** Z čoho dopĺňanie profilu (ProfileResearch) hľadá chýbajúce údaje. */
    public static function profile(Canal|Venue $model): string
    {
        return self::hash([
            $model->name,
            self::municipality($model),
            $model->website,
            $model->street,
            $model->postcode,
            $model->country,
        ]);
    }

    /**
     * Zdroje adresy kanála: web a e-maily z jeho podujatí.
     *
     * @param  list<string>  $eventEmails
     */
    public static function email(Canal $canal, array $eventEmails): string
    {
        $eventEmails = array_map(fn ($email) => mb_strtolower(trim((string) $email)), $eventEmails);
        sort($eventEmails);

        return self::hash([Url::normalize($canal->website) ?? $canal->website, ...$eventEmails]);
    }

    private static function municipality(Canal|Venue $model): mixed
    {
        return $model instanceof Canal ? $model->municipality_id : $model->village_id;
    }

    /** @param  list<mixed>  $values */
    private static function hash(array $values): string
    {
        return sha1(json_encode(array_map(
            fn ($value) => $value === null ? null : mb_strtolower(trim((string) $value)),
            $values,
        ), JSON_UNESCAPED_UNICODE));
    }
}
