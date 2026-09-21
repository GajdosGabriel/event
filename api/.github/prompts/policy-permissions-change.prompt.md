---
mode: ask
description: "Use when: changing Laravel policies, permission middleware, role-based access, or authorization behavior in event-api. Keywords: policy, permission, role, authorize, forbidden, access, Spatie."
tools: ['codebase','editFiles','runCommands','runTasks','search']
---

Use skills `laravel-api-workflow` and `laravel-test-first-api`.

- Entity or policy area: ${input:area}
- Authorization change: ${input:change}
- Affected endpoints or flows: ${input:endpoints}
- Required behavior: ${input:expected}

Test allowed, forbidden and unauthorized paths plus a regression test for the specific rule. Report impact, remaining risks and, if visibility or payload changes, the contract summary.
