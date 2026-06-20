# Copilot Instructions for event-api

This repository is a Laravel API project with a clear separation between public, dashboard, and admin flows. Prefer minimal changes that follow the existing architecture over introducing new patterns.

## Architecture

- Keep API changes aligned with the existing flow: route -> controller -> FormRequest -> policy/authorization -> repository/service -> API resource/JSON response.
- Reuse existing repository contracts from `App\Repositories\Contracts` and existing services before adding new abstractions.
- Preserve scope separation in `routes/api.php`:
  - `Public\*` controllers for public endpoints
  - `Dashboard\*` controllers for authenticated user dashboard endpoints
  - `Admin\*` controllers for admin-only endpoints
- When adding dashboard or admin endpoints, mirror the established route style with explicit `permission:*` middleware where the surrounding code already uses it.
- Keep controller actions thin. Business rules belong in repositories, services, policies, model methods, or dedicated actions if one already exists.

## Controllers and Requests

- Prefer dedicated `FormRequest` classes for validation and authorization-sensitive input instead of inline validation.
- In controllers, explicitly call `$this->authorize(...)` against the relevant model or class when the existing pattern does so.
- Prefer typed return values such as `JsonResponse` or `AnonymousResourceCollection` when the surrounding controller uses them.
- For create/update endpoints, prefer returning API resources instead of raw arrays.
- For index endpoints, follow the local pattern:
  - use `IndexFilterRequest` when filtering/pagination exists
  - return `Resource::collection(...)`
  - include `meta.permissions.create` when similar endpoints already expose it

## API Contract Visibility

- When changing an endpoint structure, always describe the API contract change explicitly.
- Always list request attributes and response attributes that were added, removed, renamed, type-changed, or had validation changes.
- If an endpoint payload changes, update the matching `FormRequest`, API resource, tests, and schema-related code in the same change when applicable.
- In the final report for endpoint changes, include a short contract summary with:
  - endpoint
  - changed request attributes
  - changed response attributes
  - backward-compatibility or breaking-change risks

## Repositories and Domain Rules

- Before writing direct Eloquent queries in controllers, check whether the model already has a repository with matching dashboard/admin methods such as `dashboardShow`, `adminShow`, `dashboardIndexWithFilters`, `restore`, or `publish`.
- Do not bypass repository-level scoping rules for dashboard/admin access.
- Preserve existing soft-delete behavior. If a resource supports restore, keep delete/update/restore semantics consistent with the existing implementation.
- For event, venue, canal, organization, user, and file flows, follow the conventions already present in sibling controllers and repositories.
- For file handling, prefer existing `FileManager` behavior and the current fileable mapping approach instead of inventing new upload flows.

## Authorization and Permissions

- Policies are a first-class part of this codebase. If behavior changes, review the matching policy in `app/Policies`.
- Permission middleware and policy authorization often work together in this project; do not remove one just because the other exists.
- Preserve the current admin boundary: admin routes are under `/api/admin` and typically require both `auth:sanctum` and role constraints.

## Tests

- Add or update focused Feature tests for API changes.
- PHPUnit and Artisan test runs in this repository expect the MySQL database `event-api-test` to exist; if it is unavailable, state that clearly instead of masking the failure.
- Cover at least:
  - success path
  - validation failure
  - unauthorized or forbidden behavior
  - regression cases when business rules are non-trivial
- Prefer extending existing test support infrastructure in `tests/TestSupport` when relevant.
- When changing permissions, policies, publish/restore flows, or filtering behavior, add regression coverage for that specific rule.
- Run the narrowest relevant test set first.

## Migrations and Schema Changes

- Make Laravel migrations rollback-safe.
- When tightening constraints such as `NOT NULL`, foreign keys, or unique indexes, backfill or normalize existing data before enforcing the constraint.
- If schema changes affect API payloads, update the matching FormRequest, factory, model casts/fillable behavior, and tests in the same change.

## Style and Change Scope

- Match the existing code style and naming in the touched area.
- Prefer small, targeted edits over broad refactors.
- Avoid introducing new dependencies unless there is a clear project-level need.
- Do not fix unrelated issues while implementing a focused task unless the fix is required to complete the change safely.

## Project-Specific Notes

- This repository already contains reusable Copilot assets in `.github/prompts` and `.github/skills`; prefer those workflows when the task matches them.
- Prefer `.github/copilot-instructions.md` as the single workspace-wide instruction file for this repository instead of adding a parallel `.github/AGENTS.md`.
- API responses are primarily JSON/resource based; avoid adding view-oriented patterns to API endpoints.
- Keep changes consistent with the existing Laravel + Sanctum + Spatie permission setup.

## Language

- Always respond to the user in Slovak unless the user explicitly asks for another language.
