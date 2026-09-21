---
mode: ask
description: "Use when: creating or updating a safe Laravel migration in event-api, including nullability, indexes, foreign keys, backfills, and payload-affecting schema changes. Keywords: migration, schema, nullable, index, foreign key, rollback, backfill."
tools: ['codebase','editFiles','runCommands','search']
---

Use skill `migration-safety-laravel`.

- Table: ${input:table}
- Schema change: ${input:change}
- Nullable, default, or index requirements: ${input:rules}

Report impact and risks.
