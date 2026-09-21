---
name: laravel-test-first-api
description: "Use when: writing or updating Laravel Feature/Unit tests for API changes, validation rules, authorization, and regression prevention in event-api. Keywords: test, feature test, unit test, phpunit, validation test, policy test, regression."
---

# Laravel Test First API

Pri tomto skille platí TDD: najprv failing Feature test pre cieľové správanie, potom minimálna implementácia.

## Pokrytie
- Success, validation failure pre kľúčové nevalidné payloady, 401/403 pre role aj ownership.
- Regression test pre každý objavený edge case alebo netriviálne pravidlo.
- Unit testy pre service/repository logiku len tam, kde dávajú zmysel.

## Vzory
- Factories + explicitné payloady, `tests/TestSupport` helpery.
- Assertuj status aj JSON štruktúru/obsah; žiadne náhodné hodnoty v assertion.
