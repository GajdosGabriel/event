---
name: laravel-code-review-risk-first
description: "Use when: reviewing Laravel changes for bugs, regressions, security risks, missing tests, and production impact. Keywords: review, bug, regression, risk, security, tests, PR."
---

# Laravel Code Review Risk First

## Purpose
Pouzi tento skill na review zmien v event-api so zameranim na rizika a regresie.

## Review Order
1. Correctness a behavioral regressions
2. Security and authorization risks
3. Data integrity and migration safety
4. Performance and N+1 patterns
5. Missing or weak tests

## Required Output Style
- Najprv findings, zoradene podla severity
- Pri kazdom findingu: problem, dopad, kde sa to deje, navrh opravy
- Kratke summary az po findings
- Ak nie su findings, povedz to explicitne a uved test gaps

## Focus Areas For This Project
- FormRequest validation completeness
- Policy/ownership checks on write endpoints
- Enum validation and status transitions
- Migration up/down symmetry
- API response contract stability
