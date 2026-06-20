---
mode: ask
model: GPT-5.4
description: "Use when: creating or updating a safe Laravel migration in event-api, including nullability, indexes, foreign keys, backfills, and payload-affecting schema changes. Keywords: migration, schema, nullable, index, foreign key, rollback, backfill."
tools: ['codebase','editFiles','runCommands','search']
---

Use skill `migration-safety-laravel`.

Input:
- Table: ${input:table}
- Schema change: ${input:change}
- Nullable, default, or index requirements: ${input:rules}

Task:
1. Design a safe migration approach with coherent `up()` and `down()` behavior.
2. Before tightening constraints, backfill or normalize existing data if needed.
3. Update related factories, requests, and models when the schema changes the payload.
4. Verify syntax, naming, and rollback compatibility.
5. Write a short report covering impact and risks.
