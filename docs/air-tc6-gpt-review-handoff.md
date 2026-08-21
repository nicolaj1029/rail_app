# AIR TC6 follow-up: GPT review handoff

## Review target

Review branch `feature/air-progressive-reveal` against deployed base
`eb5603c78196134bc4a09a4db961a2ad99319d47`.

For the full candidate:

```bash
git diff eb5603c78196134bc4a09a4db961a2ad99319d47..HEAD
```

For only this UAT follow-up, compare the first candidate commit with `HEAD`:

```bash
git diff 9fc4294f8d1e1b69649d14b0d91989f1e3792802..HEAD
```

The original dirty worktree at `C:\wamp64\www\rail_app` was not edited or
wholesale-copied. The candidate lives in the clean worktree
`C:\wamp64\www\rail_app_air_progressive`.

## UAT reports addressed

1. AIR Step 1 looked like the verbose legacy/development presentation instead
   of the cleaner launch presentation.
2. The remedies screen had the wrong step model and exposed too much legacy UI
   at once.
3. The next screen emitted PHP warnings because two AIR review-table config
   files were absent from the candidate.
4. The remedies JavaScript called expense-row helpers outside the scope where
   they had been declared.

## Implementation summary

- Kept the canonical seven-step AIR sidebar and made remedies step 5 of 7.
- Removed/hid AIR-TC6-only verbose Step 1 intro/status/scope copy while keeping
  the existing CakePHP form, canonical airport inputs, and progressive groups.
- Made the AIR refund remedies branch progressive in this order:
  remedy choice -> refund scope -> current/return airports -> return-expense
  decision -> optional expense type -> existing submit.
- Removed the duplicate central AIR estimate; the existing right rail remains
  the single estimate source.
- Removed the AIR-TC6 power-user toggle and optional Google Maps helper from the
  passenger presentation. They were optional presentation helpers, not claim
  inputs required by business rules.
- Replaced the AIR remedies legacy three-button row with the canonical TC6
  back/next action bar.
- Moved AIR expense-row helper functions to stable script scope to eliminate the
  runtime `updateAirExpenseRowButtons is not defined` failure.
- Clear every refund-only value when the user changes away from the refund
  branch, while preserving independent incident and journey answers.
- Reset inactive progressive groups so returning to a branch resumes the
  intended question order, and fail open if the focused module cannot validate
  or evaluate its markup.
- Added an AIR-only `<noscript>` fallback on remedies so critical fields and the
  canonical action remain available with JavaScript disabled.
- Added the missing static, non-secret review tables:
  `config/air/air_airport_cost_zones.php` and
  `config/air/air_expense_review_bands.php`.
- These tables contain substantive operational review thresholds in EUR and
  airport-zone assignments. They influence estimate/manual-review guidance,
  but are explicitly not statutory caps and do not determine legal entitlement.
- Extended the French AIR remedies presentation and browser assertions.

## Scope and architecture

- Progressive disclosure remains a client-side presentation layer over the
  existing forms and routes.
- No new CakePHP step, endpoint, controller logic, rights calculation, claim
  calculation, provider logic, persistence model, or database change.
- No Composer or npm dependency change.
- The new remedies attributes and JavaScript coordination are guarded by
  `transport_mode=air` plus TC6 preview state.
- The legacy action-row replacement is AIR-only. RAIL and FERRY retain their
  previous legacy markup and behavior.
- The two config files repair a genuine existing missing-file bug in the AIR
  review-band service; they do not add a new calculation path.

## Files to inspect closely

- `templates/Flow/remedies.php`
- `templates/Flow/tc6/remedies.php`
- `templates/Flow/tc6/air_entitlements.php`
- `config/air/air_airport_cost_zones.php`
- `config/air/air_expense_review_bands.php`
- `tests/e2e/air-progressive-reveal.spec.ts`

## Reviewer checklist

Please check specifically for:

1. Any RAIL/FERRY behavior change caused by a condition that is not AIR+TC6
   guarded.
2. Hidden stale refund values when `remedyChoice` changes away from refund.
3. Progressive enhancement failure: critical fields must remain available when
   the focused AIR progressive module does not initialize.
4. French translation accidentally mutating stable names, IDs, or data
   attributes.
5. Duplicate estimate calculations or duplicated business/legal rules in UI
   code.
6. The config-table schema matching `AirExpenseReviewBandService` and containing
   no secret/environment-specific values.
7. Remedies restoration after back navigation and absence of browser page
   errors.
8. Step 1 retaining local-only airport autocomplete and zero AeroDataBox calls.

## Verification commands

```powershell
vendor\bin\phpunit tests\TestCase\Service\Air\AirExpenseReviewBandServiceTest.php tests\TestCase\Controller\Tc6PresentationReleaseTest.php tests\TestCase\View\PageContentTranslatorTest.php
npx playwright test tests/e2e/air-progressive-reveal.spec.ts tests/e2e/tc6-presentation-release.spec.ts --reporter=dot
git diff --check
```

Latest local results on this candidate:

- PHPUnit: 21 tests, 410 assertions, all passing.
- Main Playwright release suite: 8 tests, all passing in 29.7 seconds.
- Supplemental AIR Step 1 airport suite: 4 tests, all passing in 21.2 seconds.
- Measured progressive reveal update: 2.00 ms.
- PHP syntax checks passed for all modified PHP/config files.
- JavaScript syntax check passed for the modified progressive module.
- `git diff --check` passed.

The browser suite also proves that switching away from refund clears the
hidden refund scope, airport, return-expense and expense-type values; that the
remedies form remains usable without JavaScript; that the canonical action is
reachable at 390 x 844 without horizontal overflow; and that browser
`pageerror` plus AIR-progressive console-error counts are both zero.

The live AIR-provider canary was run for the first candidate commit. It was not
repeated for this follow-up because no provider or integration code changed.

Manual owner/reviewer path:

```text
/fly-ny?lang=fr
-> completed AIR flow
-> Step 1
-> incident: delay, 5+ hours
-> remedies: refund
```

Expected remedies reveal: only the remedy choice initially; after refund, only
refund scope next; then airports; then the return-expense question; then the
existing next-step action. No `Warning (2)` output should appear.
