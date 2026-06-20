---
description: "Use when writing or updating PHPUnit Feature or Unit tests for Laravel API endpoints, validation, authorization, repositories, policies, or regressions in event-api."
name: "Laravel API Tests"
applyTo:
  - "tests/**/*.php"
---

# Laravel API Tests

- Prefer focused Feature tests for API behavior changes.
- Cover at least success, validation failure, and unauthorized or forbidden behavior.
- Add regression coverage for non-trivial business rules such as publish, restore, scoping, or permissions.
- Reuse fixtures and helpers from `tests/TestSupport` before building new setup code.
- Match existing route scopes in tests: public, dashboard, and admin should be exercised through their real API paths.
- Run the narrowest relevant test set first.
- Keep assertions on JSON shape and status codes aligned with the touched controller/resource behavior.
