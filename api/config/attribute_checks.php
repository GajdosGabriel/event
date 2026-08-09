<?php

return [
    /*
     * Sondy — jedna na overovaný údaj. Pridanie ďalšieho overovania (telefón,
     * profil na sociálnej sieti…) je otázka novej triedy s rozhraním
     * App\Contracts\AttributeProbe a riadku v tomto zozname. Evidencia,
     * opakovanie aj upozornenie majiteľovi sú spoločné a menia sa nulovo.
     */
    'probes' => [
        App\Services\Attributes\Probes\WebsiteProbe::class,
    ],

    /*
     * Vypnutím prestane bežať sonda aj upozorňovanie. Samotné evidovanie
     * hodnôt beží ďalej — po zapnutí sa teda overí aj to, čo medzitým pribudlo.
     */
    'enabled' => (bool) env('ATTRIBUTE_CHECKS_ENABLED', true),

    /*
     * Strop jedného HTTP dotazu v sekundách. Krátky zámerne: cieľom nie je
     * počkať na pomalý server, ale zistiť, či doména vôbec odpovedá. Pomalá
     * odpoveď sa navyše dorieši opakovaním — jeden timeout web nezhodí,
     * upozornenie odchádza až po `failures_before_notice` neúspechoch.
     */
    'timeout' => (int) env('ATTRIBUTE_CHECKS_TIMEOUT', 8),

    /*
     * Koľko hodnôt sa overí v jednom behu príkazu. Hosting nemá shell a
     * schedule:run beží cez webcron v HTTP requeste, takže dávka musí zmestiť
     * do minúty aj v najhoršom prípade (batch × timeout).
     */
    'batch' => (int) env('ATTRIBUTE_CHECKS_BATCH', 20),

    /*
     * Ako často sa preveruje hodnota, ktorá je v poriadku. Odkazy neumierajú
     * zo dňa na deň — mesačný cyklus stačí a nerobí z nás otravného robota.
     */
    'recheck_ok_days' => (int) env('ATTRIBUTE_CHECKS_RECHECK_OK_DAYS', 30),

    /*
     * Odstupy medzi opakovaniami po neúspechu (v hodinách), podľa poradia
     * neúspechu. Prvé opakovanie príde rýchlo — výpadok hostingu na pár hodín
     * je bežný a nemá kvôli nemu odísť e-mail. Posledná hodnota platí aj pre
     * všetky ďalšie pokusy.
     */
    'retry_hours' => [6, 24, 72, 168],

    /*
     * Koľko neúspechov po sebe musí nastať, kým sa ozveme majiteľovi.
     * Jeden neúspech nič neznamená; dva s odstupom hodín už áno.
     */
    'failures_before_notice' => (int) env('ATTRIBUTE_CHECKS_FAILURES_BEFORE_NOTICE', 2),

    /*
     * Ako dlho po odoslaní upozornenia mlčíme, aj keď hodnota ostáva pokazená.
     * Bez toho by majiteľ, ktorý sa opraviť nechystá, dostával e-mail pri
     * každom ďalšom kole. Nové upozornenie ide skôr len vtedy, keď hodnotu
     * zmení a pokazená je aj tá nová.
     */
    'notice_cooldown_days' => (int) env('ATTRIBUTE_CHECKS_NOTICE_COOLDOWN_DAYS', 30),

    /*
     * Ako sa sonda predstavuje. Vlastný identifikátor s odkazom na portál je
     * slušnosť voči prevádzkovateľom cieľových stránok — v logu vidia, kto sa
     * pýta, a vedia nás prípadne zablokovať cielene namiesto plošne.
     */
    'user_agent' => env('ATTRIBUTE_CHECKS_USER_AGENT', 'PodujatiaBot/1.0 (link check)'),

    /*
     * Najkratší odstup medzi dvoma nahláseniami toho istého odkazu z verejnej
     * stránky (v minútach). Klik je len podnet na prednostné overenie —
     * bez brzdy by sa dal jedným odkazom vyvolať ľubovoľný počet sond.
     */
    'report_cooldown_minutes' => (int) env('ATTRIBUTE_CHECKS_REPORT_COOLDOWN_MINUTES', 60),
];
