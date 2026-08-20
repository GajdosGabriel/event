<?php

/*
|--------------------------------------------------------------------------
| Napojenie na Account
|--------------------------------------------------------------------------
|
| Account je centrálna evidencia firiem (IČO, DIČ, IČ DPH, sídlo, banka)
| spoločná pre viacero projektov. Event si drží iba väzbu — stĺpec
| `organizations.account_uuid`. Fakturačné údaje sa tu NEUKLADAJÚ,
| čítajú sa cez AccountClient a držia v cache.
|
| Bez `ACCOUNT_TOKEN` je celé napojenie ticho vypnuté a Event funguje
| ako doteraz — organizácia sa uloží len lokálne.
|
|   ACCOUNT_URL=https://account.tvojafirma.sk
|   ACCOUNT_TOKEN=acc_xxxxxxxxxxxxxxxx
|   ACCOUNT_WEBHOOK_SECRET=whsec_xxxxxxxx
|
*/

return [
    'url' => rtrim((string) env('ACCOUNT_URL', 'http://account.local'), '/'),
    'token' => env('ACCOUNT_TOKEN'),
    'webhook_secret' => env('ACCOUNT_WEBHOOK_SECRET'),

    // Timeout schválne krátky — Account nesmie brzdiť Event.
    'timeout' => (int) env('ACCOUNT_TIMEOUT', 4),

    // Vyhľadanie IČO je iná disciplína: Account pri ňom čaká na štátny register
    // (RPO/ARES) a sám si dáva 10 sekúnd. So spoločným 4-sekundovým stropom by
    // Event spojenie utínal skôr, než register stihne odpovedať — a hlásil by
    // „register je nedostupný“, hoci údaje sa práve našli.
    'lookup_timeout' => (int) env('ACCOUNT_LOOKUP_TIMEOUT', 15),

    // Koľko pokusov dostane jedno vyhľadanie. Prvé volanie po dlhšej
    // nečinnosti Account iba prebúdza a vie sa doň nezmestiť; register
    // však medzitým odpovie a Account si výsledok uloží, takže druhý
    // pokus príde obratom. Bez neho používateľ vidí „register neodpovedal“
    // a IČO zadáva druhý raz ručne.
    'lookup_attempts' => (int) env('ACCOUNT_LOOKUP_ATTEMPTS', 2),

    'cache' => [
        // Firemné údaje sa menia zriedka; cache invaliduje webhook.
        'organization_ttl' => (int) env('ACCOUNT_ORGANIZATION_TTL', 3600),
        // Ako dlho smieme použiť poslednú známu hodnotu, keď Account nebeží.
        'stale_ttl' => (int) env('ACCOUNT_STALE_TTL', 7 * 24 * 3600),
    ],
];
