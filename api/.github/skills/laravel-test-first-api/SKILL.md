---
name: laravel-test-first-api
description: "Use when: writing or updating Laravel Feature/Unit tests for API changes, validation rules, authorization, and regression prevention in event-api. Keywords: test, feature test, unit test, phpunit, validation test, policy test, regression."
---

# Laravel Test First API

## Purpose
Pouzi tento skill na riadenie API zmien cez testy a znizenie regresii.

## Scope
- Feature testy pre endpointy a validation errors
- Authorization/policy spravanie
- Unit testy pre service/repository logiku tam, kde davaju zmysel

## Workflow
1. Pridaj failing Feature test pre cielove endpoint spravanie.
2. Pridaj validation failure testy pre klucove nevalidne payloady.
3. Pridaj authorization testy pre nepovolene role/ownership scenare.
4. Implementuj minimalnu zmenu kodu, aby testy presli.
5. Pridaj regression testy pre objavene edge case-y.

## Test Patterns
- Assertuj status a JSON structure/content.
- Pokry happy path aj failure path.
- Pouzivaj model factories a explicitne payloady.
- Drz testy deterministicke, vyhni sa nahodnym assertionom.

## Done Checklist
- Nove spravanie je pokryte testami
- Validation rules su otestovane
- Authorization je otestovana
- Existujuce relevantne testy stale prechadzaju
