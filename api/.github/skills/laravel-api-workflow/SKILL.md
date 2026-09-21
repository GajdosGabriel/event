---
name: laravel-api-workflow
description: "Use when: adding or changing Laravel API endpoints, controllers, FormRequest validation, API resources, route wiring, policies, and repository/service flow for event-api. Keywords: endpoint, controller, request, resource, route, policy, CRUD, API."
---

# Laravel API Workflow

End-to-end API zmena podľa `copilot-instructions.md` (flow, scopes, hard rules, testy, contract summary).

## Navyše oproti hlavným inštrukciám
- Validácia musí pokryť required/nullable/max/type/exists.
- Policy musí vynútiť aj ownership, nie len rolu.
- Pred zmenou nájdi najbližší sesterský endpoint (route, controller, request, resource, policy, test) a skopíruj jeho vzor.
