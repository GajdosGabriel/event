---
name: laravel-code-review-risk-first
description: "Use when: reviewing Laravel changes for bugs, regressions, security risks, missing tests, and production impact. Keywords: review, bug, regression, risk, security, tests, PR."
---

# Laravel Code Review Risk First

## Poradie priorít
1. Correctness a behavioral regressions
2. Security a authorization (policy/ownership na write endpointoch, middleware + policy vrstvenie)
3. Data integrity (migrácie up/down, backfill pred constraintom)
4. Performance (N+1)
5. Chýbajúce alebo slabé testy

## Špecifiká projektu
- Úplnosť FormRequest validácie, enum validácia a prechody statusov.
- Stabilita API kontraktu — pri zmene payloadu skontroluj request/response atribúty a BC riziká.

## Výstup
- Findings zoradené podľa severity: problém, dopad, `súbor:riadok`, návrh opravy.
- Krátke summary až po findings. Ak nič vážne, povedz to a uveď test gaps.
