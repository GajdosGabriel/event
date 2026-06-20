---
description: "Use when writing Laravel migrations, schema updates, nullability changes, indexes, foreign keys, or payload-affecting database changes in event-api."
name: "Laravel Migration Safety"
applyTo:
  - "database/migrations/**/*.php"
  - "database/factories/**/*.php"
  - "app/Models/**/*.php"
---

# Laravel Migration Safety

- Make migrations rollback-safe with coherent `up()` and `down()` behavior.
- Before enforcing `NOT NULL`, foreign key, or unique constraints, backfill or normalize existing rows.
- If schema changes affect request payloads or API responses, update related FormRequests, factories, models, and tests in the same change.
- Prefer additive migrations over risky in-place destructive changes.
- Keep naming and column intent consistent with existing tables and factories.
- Avoid hidden data-loss paths during rollback.
