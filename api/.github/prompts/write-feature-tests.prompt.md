---
mode: ask
model: GPT-5.4
description: "Use when: writing or updating focused Laravel Feature tests for API validation, authorization, regression coverage, or JSON response assertions in event-api. Keywords: feature test, validation test, authorization test, regression, phpunit."
tools: ['codebase','editFiles','runCommands','runTasks','search']
---

Use skill `laravel-test-first-api` and, when needed, `laravel-api-workflow`.

Input:
- Area under test: ${input:area}
- Endpoint or flow: ${input:endpoint}
- Required coverage: ${input:coverage}
- Expected status codes or JSON: ${input:expected}

Task:
1. Find the existing test pattern and relevant `tests/TestSupport` setup.
2. Add or update the smallest number of tests needed to cover the behavior.
3. Cover success, validation failure, and unauthorized or forbidden behavior when relevant.
4. For non-trivial rules, add a regression test for the exact business rule.
5. Run the narrowest relevant test set and return a short report covering coverage and open gaps.
