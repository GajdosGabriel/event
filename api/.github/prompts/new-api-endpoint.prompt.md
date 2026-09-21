---
mode: ask
description: "Use when: adding a brand new Laravel API endpoint in event-api with route wiring, controller, FormRequest, policy/resource integration, and focused tests. Keywords: new endpoint, route, controller, request, resource, CRUD, API."
tools: ['codebase','editFiles','runCommands','runTasks','search']
---

Use skills `laravel-api-workflow` and `laravel-test-first-api`.

- Model or entity: ${input:model}
- Operation: ${input:operation}
- Endpoint: ${input:endpoint}
- Auth rules: ${input:auth}
- Required JSON response: ${input:response}

Add the endpoint end-to-end, run the relevant tests, and report what changed plus the contract summary.
