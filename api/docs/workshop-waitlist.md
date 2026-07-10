# Workshop Waitlist (čakačka na workshopy)

## Čo to robí

Keď je workshop plný, účastník sa môže zaradiť medzi **náhradníkov**. Len čo sa miesto uvoľní, prvý v poradí ho **automaticky** dostane a príde mu e-mail s QR kódom.

Model je prevzatý z Meetup / Whova / Sched: automatický posun bez potvrdzovania. Dáva zmysel, lebo v projekte nie je platobná brána — `TicketPaymentStatus::Paid` sa nikde nenastavuje, takže pridelenie miesta nikomu nič nestrhne.

Workshop je typ lístka s `kind = workshop` — pozri [`TicketTypeKind`](../app/Enums/TicketTypeKind.php).

---

## Prečo nebola potrebná nová tabuľka

Čakačka je nový stav existujúcej `Admission`:

```php
// app/Enums/AdmissionStatus.php
case Valid      = 'valid';
case Waitlisted = 'waitlisted';
case Cancelled  = 'cancelled';
```

Všetky výpočty kapacity a `sold_count` už filtrovali `status = valid`, takže náhradník **automaticky** nezaberá kapacitu workshopu ani podujatia a nikde sa nepočíta. Posun na miesto je len zmena statusu na `valid`.

Stĺpec `ticket_admissions.status` je `string(20)`, takže nová hodnota si nevyžiadala migráciu.

---

## Podmienky

### Zaradenie medzi náhradníkov

Nastane pri `POST /api/events/{event}/workshops/{type}`, keď sú splnené **všetky**:

| Podmienka | Pri porušení |
|-----------|--------------|
| Používateľ je prihlásený (`auth:sanctum`) | 401 |
| `event.tickets_enabled` je zapnuté | 422 |
| Typ patrí eventu, je `is_active` a má `kind = workshop` | 404 |
| Zmeny nie sú zamknuté (viď nižšie) | 422 |
| Používateľ ešte nemá na tomto workshope miesto ani miesto v čakačke | 422 „Na tento workshop ste už prihlásený." |
| Používateľ má platnú vstupenku na podujatie | 422 „Na workshopy sa môžu prihlásiť len účastníci registrovaní na podujatie." |
| Workshop má `capacity` a `remaining = 0` | — inak dostane miesto rovno (`status = valid`) |

Rozhodnutie „miesto vs. čakačka" prebieha v transakcii s `lockForUpdate` na `ticket_types`, aby dvaja súčasní záujemcovia nesadli na to isté posledné miesto.

Workshop **bez kapacity** (`capacity = null`) čakačku nikdy nepoužije.

### Posun z čakačky

`promoteFromWaitlist(TicketType $type)` sa volá po **každom uvoľnení platného miesta** na workshope:

- používateľ sa odhlási — `leaveWorkshop()`
- organizátor zruší jeden lístok — `cancelAdmission()`
- organizátor zruší celú objednávku — `cancel()`

Posunie sa **prvý náhradník podľa `admissions.id`** (FIFO — poradie prihlásenia). Celé to beží v transakcii s `lockForUpdate` na type aj na vybranej admission, takže dve súčasné odhlásenia neposunú toho istého človeka dvakrát.

Posun **neprebehne**, keď:

- typ už nie je `is_active`
- workshop medzitým nemá voľné miesto
- v čakačke nikto nie je
- `event.workshopChangesLocked()` je `true` (podujatie začalo alebo skončilo)

Pri posune sa admission prepne na `valid`, jej objednávka na `TicketStatus::Confirmed` a odošle sa [`WorkshopSeatGranted`](../app/Notifications/WorkshopSeatGranted.php).

### Opustenie čakačky

`DELETE /api/events/{event}/workshops/{type}` zruší používateľove miesta na workshope — platné aj miesto v čakačke.

**Odchod z čakačky neuvoľňuje miesto**, takže neposúva nikoho ďalšieho. Posun sa spustí len vtedy, keď mala zrušená admission status `valid`.

Objednávka, ktorej neostalo žiadne miesto (`valid` ani `waitlisted`), sa zruší celá.

### Zámok po začiatku podujatia

`Event::workshopChangesLocked()` vráti `true`, keď:

- `end_at` už uplynul (vždy, bez ohľadu na nastavenie), **alebo**
- `workshop_lock_on_start` je zapnuté **a** `start_at` už uplynul

Pri zamknutom podujatí sa nedá prihlásiť, odhlásiť, ani sa neposúva čakačka. Prepínač `workshop_lock_on_start` (default `true`) je v nastaveniach lístkov podujatia.

---

## Náhradník nemá lístok

Náhradník má `Admission` bez platného miesta, takže:

| Miesto | Správanie |
|--------|-----------|
| Kapacita workshopu (`sold_count`, `remaining_capacity`) | neráta sa |
| Kapacita podujatia (`Admission::scopeMainSeats`) | neráta sa |
| `GET /api/admissions/{uuid}/qr` | **404** |
| Check-in cez QR aj manuálny | `['status' => 'invalid', 'reason' => 'waitlisted']` |
| Verejná stránka lístka | namiesto QR kódu vysvetlenie, že čaká na miesto |
| E-mail pri zaradení | [`WorkshopWaitlisted`](../app/Notifications/WorkshopWaitlisted.php) s poradím, **nie** `TicketIssued` |

---

## API

### `GET /api/events/{event}/ticket-types`

Verejný zoznam typov. Pri workshopoch dopĺňa (pre prihláseného používateľa):

```json
{
  "data": [
    {
      "id": 8,
      "kind": "workshop",
      "remaining_capacity": 0,
      "viewer_joined": false,
      "viewer_waitlisted": true,
      "viewer_waitlist_position": 2,
      "waitlist_count": 3
    }
  ],
  "meta": {
    "viewer_registered": true,
    "workshop_changes_locked": false
  }
}
```

`viewer_waitlist_position` je `1` pre toho, kto je najbližšie na rade. Pre neprihláseného návštevníka sú `viewer_*` polia `false` / `null`.

### `POST /api/events/{event}/workshops/{type}`

Prihlásenie. Vracia `201` s `AdmissionResource`; podľa `status` v odpovedi (`valid` vs `waitlisted`) sa pozná, či používateľ dostal miesto alebo šiel do poradia.

### `DELETE /api/events/{event}/workshops/{type}`

Odhlásenie / opustenie čakačky. Vracia `200 {"status":"ok"}`.

---

## Kde to žije

| Vrstva | Súbor |
|--------|-------|
| Stavy | [`app/Enums/AdmissionStatus.php`](../app/Enums/AdmissionStatus.php) |
| Logika | [`EloquentTicketRepository`](../app/Repositories/Eloquent/EloquentTicketRepository.php) — `joinWorkshop()`, `leaveWorkshop()`, `promoteFromWaitlist()` |
| Zámok | [`Event::workshopChangesLocked()`](../app/Models/Event.php) |
| Endpointy | [`Public/WorkshopRegistrationController`](../app/Http/Controllers/Public/WorkshopRegistrationController.php) |
| Stav pre UI | [`Public/TicketTypeController`](../app/Http/Controllers/Public/TicketTypeController.php) |
| Testy | [`tests/Feature/Events/WorkshopRegistrationTest.php`](../tests/Feature/Events/WorkshopRegistrationTest.php) |
| UI | `ui/src/components/EventWorkshops.vue` |

---

## Ak pribudnú platby

Auto-posun je bezpečný len dovtedy, kým sa reálne neplatí. S platobnou bránou treba prejsť na model Eventbrite — **pozvánka s expiráciou**: náhradník dostane e-mail „máš 24 h nárokovať si miesto", a keď neklikne, ide ďalší.

Zmena sa dotkne prakticky len `promoteFromWaitlist()`. Bude treba:

1. stĺpec s expiráciou pozvánky na `ticket_admissions` (napr. `offer_expires_at`) a stav `offered`,
2. claim endpoint, ktorým si náhradník miesto potvrdí,
3. scheduled command, ktorý prepadnuté pozvánky posunie na ďalšieho v poradí.
