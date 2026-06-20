---
description: "Use when writing Laravel API controllers, requests, policies, resources, routes/api.php changes, or repository-backed endpoint logic in event-api."
name: "Laravel API Flow"
applyTo:
  - "app/Http/Controllers/**/*.php"
  - "app/Http/Requests/**/*.php"
  - "app/Http/Resources/**/*.php"
  - "app/Policies/**/*.php"
  - "app/Repositories/**/*.php"
  - "routes/api.php"
---

# Laravel API Flow

- Follow the local flow: route -> controller -> FormRequest -> policy/authorization -> repository/service -> resource/JSON response.
- Keep controller actions thin. Prefer business rules in repositories, policies, services, or existing model methods.
- Preserve scope boundaries:
  - `App\Http\Controllers\Public\*` for public API
  - `App\Http\Controllers\Dashboard\*` for authenticated dashboard API
  - `App\Http\Controllers\Admin\*` for admin API
- Prefer dedicated `FormRequest` classes over inline validation when input is non-trivial or authorization-sensitive.
- In dashboard/admin flows, keep explicit `$this->authorize(...)` checks when sibling endpoints already do so.
- Reuse repository contracts from `App\Repositories\Contracts` before introducing direct controller queries.
- For index endpoints with filters, prefer `IndexFilterRequest`, `Resource::collection(...)`, and `meta.permissions.create` when nearby endpoints already expose it.
- For create/update responses, prefer API resources over raw arrays.
- Preserve existing permission middleware on `routes/api.php` when adding or changing protected endpoints.
- When changing endpoint structure, always keep request and response attributes visible in the implementation summary.
- Explicitly list attributes that were added, removed, renamed, type-changed, or had validation-rule changes.
- If the payload shape changes, update the related `FormRequest`, resource, route expectations, and Feature tests together.
- Prefer preserving backward compatibility; if that is not possible, call out the breaking change explicitly.
