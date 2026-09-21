---
name: ai-detect-from-text
description: "Use when: working on `app:ai-detect-from-text` — extracting publishable event data (dates, organizer canal, venue, people) from free text. Keywords: AI detect, event text, canal, venue, import."
---

# AI Detect From Text

Z voľného textu pripraviť publikovateľný event: `php artisan app:ai-detect-from-text {id} [--save]`.

## Pravidlá
- Text gramaticky a štylisticky opraviť; ak je krátky, doplniť podľa pokynov.
- Získať začiatok a koniec eventu. Ak koniec chýba alebo je pred začiatkom → celodenný event (00:00:00–23:59:59 v deň začiatku).
- Organizátor → nájsť canal v DB, inak vytvoriť nový.
- Miesto → nájsť venue v DB, inak vytvoriť nové.
- Osoby a kontakty z textu → vytvoriť canal, ak neexistuje.

## Režimy
- Bez `--save`: iba analyzovať a vypísať, nič nezapisovať do DB.
- S `--save`:
  - uložiť do eventu `canal_id`, `venue_id` a `user_id=1` (system owner),
  - zabezpečiť pivot `canal_user` pre `user_id=1` a cieľový canal (`is_owner=1`, `is_active=1`),
  - ak nájdené venue patrí inému canalu, presunúť `venue.canal_id` na canal eventu.
