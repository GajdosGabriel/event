# Obnova zabudnutého hesla

## Prečo to vzniklo

Do 3. 9. 2026 portál obnovu hesla **nemal vôbec** — v repozitári nebola routa,
controller ani stránka, len prázdna tabuľka `password_reset_tokens` z Laravel
skeletonu. Kto si účet založil e-mailom a heslo zabudol, bol natrvalo mimo:
prihlásenie cez Google ani Facebook mu nepomôže, lebo účet má `registered_via`
= `local` a sociálne prihlásenie hľadá `provider_id`.

Pri portáli, kde účet drží vstupenky, to nie je nepohodlie, ale strata
zákazníka. A v momente, keď cez účty potečú peniaze (fáza 2 roadmapy), prestane
byť odpoveďou aj „napíšte nám, resetneme to ručne".

## Stojí to na Laravelovom brokeri, nie na vlastnom tokene

Registrácia si tokeny rieši sama (`pending_registrations.verification_token`,
sha256 + `expires_at`), lebo overuje **adresu bez účtu** — riadok v `users` ešte
neexistuje. Obnova hesla účet má, takže si vystačí s `Password` brokerom:
tabuľka, hašovanie tokenu, platnosť (`config/auth.php`, dnes 60 minút)
aj throttling (jeden e-mail za minútu na používateľa) sú hotové a odladené.

Vlastný mechanizmus by tu len zopakoval to isté horšie.

## Odpoveď na „zabudnuté heslo" je vždy rovnaká

[`PasswordResetController::forgot()`](../app/Http/Controllers/Auth/PasswordResetController.php)
vracia rovnaký status aj rovnakú hlášku pre adresu, ktorá účet má, aj pre tú,
ktorá ho nemá. Nie je to opatrnosť navyše: formulár, ktorý rozlišuje, je
verejný nástroj na overenie, kto na portáli má účet — presne to, čo útočník
potrebuje vedieť pred phishingom.

Z toho plynie niekoľko dôsledkov, ktoré vyzerajú ako chyby, a nie sú:

- `PasswordForgotRequest` **nemá** `exists:users`. Validačná chyba by rozdiel
  prezradila skôr, než by sa k slovu dostal controller.
- Hláška je vlastný kľúč `passwords.sent_blind` („ak k tejto adrese patrí
  účet…"), nie Laravelovo `passwords.sent` („poslali sme vám…"). To druhé pri
  neznámej adrese klame a pri známej ju potvrdzuje.
- Aj stav `RESET_THROTTLED` skončí rovnakou odpoveďou. Priznať ho znamená
  priznať, že účet existuje.
- Adresa, ktorá čaká na overenie registrácie, nedostane nič — nemá riadok
  v `users`, takže nemá čo obnovovať. Potrebuje overovací e-mail, nie tento.

Skutočný dôvod, prečo e-mail neodišiel, ide do logu (`Log::info` so stavom
brokera). Pri hlásení „nič mi neprišlo" je to jediné, z čoho sa dá zistiť prečo.

Pri samotnom `reset()` už neutralita nedáva zmysel — kto drží token, adresu
pozná — takže vypršaný token aj neznámy e-mail dostanú konkrétnu hlášku.

## Odkaz vedie do SPA, nie do API

Laravelova `ResetPassword` notifikácia mieri na pomenovanú routu
`password.reset`, ktorá tu neexistuje: formulár je stránka SPA. Preto má
`User::sendPasswordResetNotification()` vlastnú implementáciu a adresu skladá
[`PublicUrl::passwordReset()`](../app/Support/PublicUrl.php) —
`/obnova-hesla/{token}?email=…`, rovnako ako odhlásenie z odberu.

E-mail v query **nie je autorizácia**, len predvyplnenie poľa. Broker overuje
dvojicu token + adresa, takže samotný token bez zhodnej adresy neprejde.

## Obnova hesla je aj odhlásením zo všetkých zariadení

Po úspešnej zmene sa mažú všetky Sanctum tokeny používateľa. Kto sa dostal
k starému heslu, si mohol vyrobiť Bearer token — a ten by zmenu hesla prežil,
takže by obnova hesla útočníka nevyhodila. Cena je, že sa používateľ musí znova
prihlásiť na ostatných zariadeniach; to je pri obnove hesla očakávané.

## Limity

Nad brokerovým throttlingom beží ešte limiter `password-reset`
([AppServiceProvider](../app/Providers/AppServiceProvider.php)): 3 požiadavky
za minútu na dvojicu IP + e-mail a 10 za hodinu na IP. Minútový kôš je
kľúčovaný aj adresou, aby útok na jeden účet nezablokoval obnovu ostatným
z tej istej siete; hodinový už len IP, aby sa striedaním adries nedal obísť.

`reset` zdieľa ten istý limiter zámerne — skúšanie tokenov je to isté hádanie
ako skúšanie hesiel.

## Čo tým nie je vyriešené

- **Zmena hesla prihláseným používateľom** (so zadaním pôvodného) neexistuje.
  Kto si heslo chce zmeniť, musí prejsť týmto tokom cez e-mail.
- **Účet bez hesla** (registrovaný cez Google alebo Facebook) si cez obnovu
  heslo nastaviť môže a je to zámer — získa druhú cestu dnu. Nikde sa mu to
  však neponúka.

## Overenie

```bash
cd api && php artisan test tests/Feature/Auth/PasswordResetTest.php
```

Ručne stojí za pozretie e-mail: odkaz musí mieriť na `FRONTEND_URL`, nie na
`APP_URL` — pri zle nastavenom `FRONTEND_URL` vznikne odkaz, ktorý sa otvorí
v API a skončí na 404.
