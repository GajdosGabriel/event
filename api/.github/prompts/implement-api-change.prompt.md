---
mode: ask
model: GPT-5.4
description: "Use when: changing existing Laravel API behavior in event-api, including endpoint updates, validation changes, authorization tweaks, repository flow changes, or schema-coupled API work. Keywords: update endpoint, change API, validation, policy, repository, refactor, migration."
tools: ['codebase','editFiles','runCommands','runTasks','search']
---

Use skill `laravel-api-workflow` and, depending on the change, also `laravel-test-first-api` or `migration-safety-laravel`.

Input:
- Entity or module: ${input:entity}
- Requested change: ${input:change}
- Endpoint or flow: ${input:endpoint}
- Authorization and permissions: ${input:auth}
- Expected response or behavior: ${input:expected}

Task:
1. Find the existing route, controller, request, resource, policy, repository, and test pattern in the current codebase.
2. Propose the smallest change that fits the existing project architecture.
3. Implement the change end-to-end without introducing a new pattern if a suitable local pattern already exists.
4. Add or update relevant Feature tests for success, validation failure, and unauthorized or forbidden behavior.
5. If the schema changes, use a safe migration approach and update related requests, factories, models, and tests.
6. Run the narrowest relevant test set and return a short report covering changes, tests, and remaining risks.
7. For endpoint contract changes, include a short API contract summary listing changed request attributes, changed response attributes, and any backward-compatibility or breaking-change risks.
