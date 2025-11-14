# E2E Test Harness (Playwright)

This folder contains a lightweight Playwright-based rule test harness. It validates EU regulation logic using JSON snapshots (no browser required). Browser flows can be added later.

Contents:
- `e2e/flow.spec.ts` – sample spec loading a fixture and running the rule engine.
- `rules/eu.ts` – EU rules (Art. 12, 18, 19, 20) evaluation.
- `rules/national.ts` – National practice/exemptions stubs.
- `rules/scorer.ts` – Aggregation and assertion helpers.
- `utils/pdf.ts` – Optional PDF text extraction helper (uses `pdf-parse` if installed).
- `fixtures/cases` – Example input snapshots.

How to run (Windows PowerShell):
1) Install dev dependencies (one time):
   - npm install --save-dev @playwright/test typescript ts-node pdf-parse
   - npx playwright install

2) Execute tests:
   - npm run test:e2e

Optional:
- UI mode: npm run test:e2e:ui
- Debug mode: npm run test:e2e:debug

Note: The current spec does not require the PHP server to be running. Once browser tests are added, ensure the app runs locally (e.g., PHP built-in server or CakePHP server) and configure Playwright's `use.baseURL` accordingly.
