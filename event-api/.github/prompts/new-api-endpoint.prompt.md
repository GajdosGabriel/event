---
mode: ask
model: GPT-5.4
description: "Use when: adding a brand new Laravel API endpoint in event-api with route wiring, controller, FormRequest, policy/resource integration, and focused tests. Keywords: new endpoint, route, controller, request, resource, CRUD, API."
tools: ['codebase','editFiles','runCommands','runTasks','search']
---

Use skill `laravel-api-workflow` and `laravel-test-first-api`.

Input:
- Model or entity: ${input:model}
- Operation: ${input:operation}
- Endpoint: ${input:endpoint}
- Auth rules: ${input:auth}
- Required JSON response: ${input:response}

Task:
1. Find the existing route, controller, request, resource, and policy pattern.
2. Add the new endpoint end-to-end using the current architecture without introducing a new pattern if a suitable local pattern already exists.
3. Preserve the `public`, `dashboard`, or `admin` route scope based on the surrounding code and use the repository or service layer when it already exists.
4. Do not make unrelated endpoint changes unless they are required for the new flow.
5. Add Feature tests for success, validation failure, and unauthorized or forbidden behavior.
6. Run relevant tests and write a short report describing what changed.
7. Include a short API contract summary listing the request attributes, response attributes, and any compatibility constraints for the new endpoint.
