---
name: laravel-api-workflow
description: "Use when: adding or changing Laravel API endpoints, controllers, FormRequest validation, API resources, route wiring, policies, and repository/service flow for event-api. Keywords: endpoint, controller, request, resource, route, policy, CRUD, API."
---

# Laravel API Workflow

## Purpose
Pouzi tento skill pre end-to-end API zmeny v projekte, aby bola implementacia konzistentna a bezpecna pre produkciu.

## Inputs
- Biznis poziadavka (create/update/list/delete alebo custom endpoint)
- Dotknute modely
- Ocekavane auth a role pravidla
- Ocekavany format odpovede

## Workflow
1. Najprv identifikuj zmeny v route a controller metode.
2. Pridaj alebo uprav FormRequest validaciu a authorization.
3. Implementuj logiku v model/service/repository s minimalnymi side effectmi.
4. Vrat API Resource alebo konzistentny JSON shape.
5. Zapoj policy kontroly a ownership obmedzenia.
6. Pridaj alebo uprav testy pre success a validation failure scenare.

## Project Rules
- Preferuj FormRequest pred inline validaciou.
- Controller nech je tenky, zlozitejsiu logiku presun do services/repositories.
- Zachovaj existujuci public response kontrakt, pokial poziadavka nepovie inak.
- Pouzivaj enum validaciu cez Rule::enum tam, kde su enumy.
- Ak sa robi viac suvisiacich DB zapisov, obal to do transaction.

## Done Checklist
- Route je pridana/upravena a je dohladatelna
- Validacia pokryva required/nullable/max/type/exists
- Authorization je vynutena
- Response shape je stabilny a konzistentny s kodom
- Testy lokalne prechadzaju
