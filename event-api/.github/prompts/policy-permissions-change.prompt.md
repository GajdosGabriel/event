---
mode: ask
model: GPT-5.4
description: "Use when: changing Laravel policies, permission middleware, role-based access, or authorization behavior in event-api. Keywords: policy, permission, role, authorize, forbidden, access, Spatie."
tools: ['codebase','editFiles','runCommands','runTasks','search']
---

Use skill `laravel-api-workflow` and `laravel-test-first-api`.

Input:
- Entity or policy area: ${input:area}
- Authorization change: ${input:change}
- Affected endpoints or flows: ${input:endpoints}
- Required behavior: ${input:expected}

Task:
1. Find the existing policy, permission middleware, route wiring, and test pattern in the current codebase.
2. Propose the smallest change that preserves the current Laravel + Sanctum + Spatie permission architecture.
3. If an endpoint already uses both middleware and policy checks, do not remove one just because the other exists unless there is a clear reason.
4. Implement the change in policies, requests, controllers, or routes only where necessary.
5. Add or update focused Feature tests for allowed, forbidden, and unauthorized paths, plus a regression test for the specific business rule.
6. Run the narrowest relevant test set and return a short report covering impact and remaining risks.
7. If the authorization change affects endpoint contract or visibility, include a short API contract summary listing changed request attributes, changed response attributes, and any backward-compatibility or breaking-change risks.
