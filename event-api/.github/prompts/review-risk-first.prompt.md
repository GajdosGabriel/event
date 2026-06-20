---
mode: ask
model: GPT-5.4
description: "Use when: reviewing Laravel changes for bugs, regressions, security risks, behavioral issues, authorization gaps, and missing tests in event-api. Keywords: review, bug, regression, risk, security, tests, PR."
tools: ['codebase','search','runCommands']
---

Use skill `laravel-code-review-risk-first`.

Task:
1. Review the current changes with a focus on correctness, security, data integrity, and test coverage.
2. Return findings ordered by severity.
3. For each finding, include the exact location and a recommended fix.
4. Call out behavioral regressions, authorization gaps, and missing or weak test coverage explicitly.
5. If API endpoints or payload structure changed, explicitly review request attributes, response attributes, validation changes, and any backward-compatibility or breaking-change risks.
6. If no serious findings are present, say that explicitly and include residual risks or test gaps.
