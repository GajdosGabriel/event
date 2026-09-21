---
mode: ask
description: "Use when: changing existing Laravel API behavior in event-api, including endpoint updates, validation changes, authorization tweaks, repository flow changes, or schema-coupled API work. Keywords: update endpoint, change API, validation, policy, repository, refactor, migration."
tools: ['codebase','editFiles','runCommands','runTasks','search']
---

Use skill `laravel-api-workflow`; add `laravel-test-first-api` or `migration-safety-laravel` when relevant.

- Entity or module: ${input:entity}
- Requested change: ${input:change}
- Endpoint or flow: ${input:endpoint}
- Authorization and permissions: ${input:auth}
- Expected response or behavior: ${input:expected}

Implement the smallest end-to-end change, run the relevant tests, and report changes, tests, remaining risks and the contract summary.
