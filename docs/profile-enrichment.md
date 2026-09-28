# Automatické dopĺňanie profilov Event

Príkaz `php artisan app:profiles-enrich` beží každých 15 minút cez existujúci webcron. V jednej dávke spracuje najviac dva profily a medzi profilmi kontroluje časový rozpočet 45 sekúnd. Jedno rozbehnuté volanie môže tento rozpočet prekročiť o čas svojho HTTP timeoutu.

Organizačné kanály a miesta sa kontrolujú po 120 minútach od vytvorenia, vrátane starších importovaných profilov. Osobné, pseudonymné, blokované, archivované a zberné miesta sa vynechajú. Stav publikovania sa nemení; dopĺňať sa môžu aj koncepty.

Dopĺňajú sa iba prázdne polia: web, e-mail, telefón, ulica, PSČ, krajina a stručný popis. Chýbajúca obec sa priradí len pri jednoznačnej zhode s číselníkom. Verejné kontakty hľadá `ProfileResearch` cez OpenAI Responses API s webovým vyhľadávaním. Každá prijatá hodnota musí mať oficiálny zdroj obsiahnutý vo výsledkoch vyhľadávania. Neistá identita alebo neplatná hodnota znamená prázdny výsledok. Nejde o ľudskú verifikáciu.

AI nedodáva GPS. `ProfileCoordinates` využíva existujúci Nominatim geokóder: zhodu adresy a obce, prípadne pri venue zhodu názvu budovy v rovnakej obci. Sídlo organizátora sa neodvodzuje z miesta jeho podujatia. Ručné a už presné súradnice zostávajú zachované. Zástupnú polohu alebo označený hrubý odhad možno spresniť. Súradnice sa ukladajú ako pár so zdrojom `address` alebo `venue`; bez zhody sa neodhadujú.

Pred uložením sa profil znovu načíta pod zámkom. Nové používateľské hodnoty majú prednosť; zmena identity alebo adresy počas vyhľadávania odloží zápis. Audit `profile_enrichments` drží zmeny, zdroje, stav pokusov a e-mailu. Zhrnutie a odkazy sú aj v administrátorskom denníku (`profiles.enriched`), spotreba v AI prehľade. Zdrojové kontakty sa nepoužijú ako príjemcovia správ.

Používateľskému vlastníkovi ide jeden e-mail až po skutočnom doplnení, s prehľadom a odkazom na úpravu. Príjemca sa určuje cez `attributeIssueRecipient()` (téma „kontroly“ v nastaveniach tímu, viď canal-ownership.md); technickému vlastníkovi importovaného kanála e-mail neodíde. Chyba e-mailu nespúšťa platené vyhľadávanie znova; e-mail má najviac tri pokusy po hodine. Vyhľadávanie má najviac tri pokusy s odstupom šesť hodín. Úspešná kontrola bez výsledku sa neopakuje automaticky.

Nastavenia: `PROFILE_ENRICHMENT_ENABLED` (predvolene true), `PROFILE_ENRICHMENT_MONTHLY_LIMIT_USD` (predvolene 1 USD; 0 bez limitu), `PROFILE_ENRICHMENT_MODEL` (gpt-4.1-mini), `PROFILE_ENRICHMENT_SEARCH_COST_USD` (odhad poplatku 0.01 USD za nástroj). Limit sa týka tejto funkcie a kontroluje sa pred volaním; posledné volanie ho môže prekročiť. Používa existujúci `OPENAI_API_KEY`.

Nasadenie: spustiť migráciu `2026_09_27_150000_create_profile_enrichments_table.php` a bežný deployment aplikácie vrátane UI prekladov. Nevyžaduje nového démona ani cron mimo existujúceho plánovača.

API: https://developers.openai.com/api/docs/guides/tools-web-search
