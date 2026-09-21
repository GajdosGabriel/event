# event-api — Copilot Instructions

Laravel + Sanctum + Spatie Permission API. Follow existing local patterns; don't introduce new ones when a sibling already solves it.

## Architecture
- Flow: route → thin controller → FormRequest → policy (`$this->authorize`) → repository/service → API Resource.
- Scopes in `routes/api.php` are strict: `Public\*`, `Dashboard\*` (auth user), `Admin\*` (`/api/admin`, `auth:sanctum` + role).
- Use repositories from `App\Repositories\Contracts` (`dashboardShow`, `adminShow`, `dashboardIndexWithFilters`, `restore`, `publish`…) — never bypass their dashboard/admin scoping with raw Eloquent in controllers.
- Index endpoints: `IndexFilterRequest` + `Resource::collection()` + `meta.permissions.create` where siblings expose it.
- Files go through `FileManager` and the existing fileable mapping.
- Multiple related DB writes → transaction. Enums → `Rule::enum`.

## Hard rules
- Permission middleware and policies are layered on purpose — never remove one because the other exists.
- Preserve soft-delete/restore semantics.
- Don't break public response contracts unless explicitly asked; flag any breaking change.
- Migrations must be rollback-safe with no hidden data loss in `down()`; backfill data before adding `NOT NULL`, FK or unique constraints. Prefer additive changes.
- Payload change ⇒ update FormRequest, Resource, factory, casts/fillable and tests in the same change.

## Tests
- Feature tests for every API change, hitting real public/dashboard/admin paths: success, validation failure, 401/403. Add a regression test for non-trivial rules (permissions, publish/restore, scoping, filters).
- Reuse `tests/TestSupport`.
- Tests require MySQL DB `event-api-test`. If it's missing, say so — don't mask the failure.

## Output
For any endpoint change, end with a contract summary:
- endpoint
- request attributes added/removed/renamed/retyped/revalidated
- response attributes (same)
- backward-compatibility risks

## Language
Respond in Slovak.
