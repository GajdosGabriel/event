<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pripravenosť na zverejnenie
    |--------------------------------------------------------------------------
    |
    | Jediný zoznam podmienok „záznam je hotový" pre celú aplikáciu. Číta ho
    | formulár (cez GET {scope}/publish-readiness) aj server pri publikovaní,
    | takže sa nemá ako rozísť — kým bola tá istá otázka napísaná dvakrát,
    | v UI a v kontrolóre, ticho si odpovedali inak.
    |
    | Podmienka je zámerne hlúpa: `rule` + `fields` + prípadná `value`. Všetky
    | tri pravidlá vie vyhodnotiť aj prehliadač (viď usePublishReadiness.ts),
    | takže sa panel prekresľuje pri písaní bez jediného dotazu na server.
    |
    |   filled     — každé pole zo `fields` je vyplnené
    |   any_of     — aspoň jedno pole zo `fields` je vyplnené
    |   min_chars  — text poľa (po odstránení HTML) má aspoň `value` znakov
    |
    | Poradie je poradím v zozname „čo ešte chýba", preto ide od
    | najpodstatnejšieho. `image` nie je stĺpec — dopĺňa ho volajúci z prílohy
    | (formulár z výberu súborov, server z relácie `files`).
    */
    'readiness' => [
        'event' => [
            ['key' => 'name', 'rule' => 'filled', 'fields' => ['name']],
            ['key' => 'start_at', 'rule' => 'filled', 'fields' => ['start_at']],
            ['key' => 'venue', 'rule' => 'filled', 'fields' => ['venue_id']],
            // 200 znakov je hranica, pod ktorou popis nemá čo povedať ani
            // návštevníkovi, ani vyhľadávaču — a zároveň hranica, pod ktorou
            // nemá zmysel púšťať na text kontrolu (viď `min_body_chars`).
            ['key' => 'body', 'rule' => 'min_chars', 'fields' => ['body'], 'value' => 200],
            ['key' => 'image', 'rule' => 'filled', 'fields' => ['image']],
            ['key' => 'contact', 'rule' => 'any_of', 'fields' => ['website', 'email', 'phone']],
        ],

        'venue' => [
            ['key' => 'name', 'rule' => 'filled', 'fields' => ['name']],
            // Obec, nie ulica: bez obce miesto nie je kam zaradiť ani zobraziť
            // na mape, kým ulica pri kostole či námestí často neexistuje.
            ['key' => 'address', 'rule' => 'filled', 'fields' => ['municipality_id']],
            ['key' => 'body', 'rule' => 'min_chars', 'fields' => ['body'], 'value' => 150],
            ['key' => 'image', 'rule' => 'filled', 'fields' => ['image']],
        ],

        'canal' => [
            ['key' => 'name', 'rule' => 'filled', 'fields' => ['name']],
            ['key' => 'body', 'rule' => 'min_chars', 'fields' => ['body'], 'value' => 150],
            // Organizátor bez jediného kontaktu je slepá ulica — návštevník sa
            // nemá koho spýtať a „Poslať správu" mu odpovie len cez portál.
            ['key' => 'contact', 'rule' => 'any_of', 'fields' => ['website', 'email', 'phone']],
            ['key' => 'image', 'rule' => 'filled', 'fields' => ['image']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kontrola obsahu po zverejnení
    |--------------------------------------------------------------------------
    */

    /*
     * Vypnutím prestane vznikať aj bežať kontrola. Existujúce záznamy ostanú —
     * po zapnutí sa dobehnú, lebo výber si berie všetko, čo je splatné.
     */
    'enabled' => (bool) env('CONTENT_REVIEW_ENABLED', true),

    /*
     * Koľko záznamov sa skontroluje v jednom behu príkazu. Nižšie než pri
     * sondách odkazov: každý riadok je jedno volanie OpenAI (sekundy, nie
     * milisekundy) a všetky príkazy bežia sekvenčne v jednom webcron requeste.
     */
    'batch' => (int) env('CONTENT_REVIEW_BATCH', 5),

    /*
     * Koľko minút po zverejnení sa čaká, kým sa obsah skontroluje. Odklad nie
     * je technický — je to slušnosť: človek po publikovaní ešte pár minút
     * dolaďuje preklepy a e-mail o chybách, ktoré medzitým sám opravil, je
     * horší než žiadny. Každé ďalšie uloženie odklad posúva odznova.
     */
    'delay_minutes' => (int) env('CONTENT_REVIEW_DELAY_MINUTES', 15),

    /*
     * Pod touto dĺžkou textu sa kontrola nepúšťa. Krátky popis nepotrebuje
     * jazykový rozbor — to už povedala pripravenosť vyššie a povedala to
     * zadarmo, v formulári a okamžite.
     */
    'min_body_chars' => (int) env('CONTENT_REVIEW_MIN_BODY_CHARS', 120),

    /*
     * Od akej závažnosti sa ozveme majiteľovi. `notice` sú návrhy na zlepšenie
     * („dalo by sa rozviesť"), `warning` sú skutočné chyby (gramatika, rozbitá
     * štruktúra). E-mail za samotné „dalo by sa" by bol otravovanie.
     */
    'notify_from_severity' => env('CONTENT_REVIEW_NOTIFY_FROM', 'warning'),

    /*
     * Ako dlho po odoslaní e-mailu mlčíme o tom istom zázname, aj keď obsah
     * ostáva rovnaký. Bez toho by každá zmena nesúvisiaceho poľa vyvolala
     * ďalšiu kontrolu a ďalší e-mail.
     */
    'notice_cooldown_days' => (int) env('CONTENT_REVIEW_NOTICE_COOLDOWN_DAYS', 14),

    /*
     * Model. Kontrola je čítanie s porozumením nad jedným krátkym textom,
     * nie tvorba — `gpt-4o-mini` na ňu stačí a beží na nej celý zvyšok
     * integrácie (viď App\Services\OpenAI\ChatGPT).
     */
    'model' => env('CONTENT_REVIEW_MODEL', 'gpt-4o-mini'),
];
