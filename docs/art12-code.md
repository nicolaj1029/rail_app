# Article 12 (Through Tickets) – Code Map

This document lists where Article 12 logic lives in the codebase and how the UI hooks connect to evaluation and exemptions.

## Evaluator

- File: `src/Service/Art12Evaluator.php`
- Method: `evaluate(array $journey, array $meta = [])`
- Responsibilities:
  - Gathers hooks: `through_ticket_disclosure`, `single_txn_operator`, `single_txn_retailer`, `separate_contract_notice`, `shared_pnr_scope`, `seller_type_operator`, `seller_type_agency`, `multi_operator_trip`, `mct_realistic`, `one_contract_schedule`, `contact_info_provided`, `responsibility_explained`, `single_booking_reference`.
  - Computes `art12_applies` and `reasoning[]`:
    - Exemptions: if ExemptionProfile disables Art. 12 → applies=false.
    - If `separate_contract_notice === 'Ja'` → applies=false (Art. 12(5)).
    - If `through_ticket_disclosure === 'Gennemgående'` OR `shared_pnr_scope === 'yes'` → applies=true.
    - If `single_txn_operator === 'yes'` → applies=true (Art. 12(3)).
    - If `single_txn_retailer === 'yes'` → applies=true (Art. 12(4)).
    - If `seller_type_agency === 'yes'` AND no clear `separate_contract_notice` → default applies=true per Art. 12(4)/(5).
  - Returns: `{ hooks, missing, art12_applies, reasoning }`.

## UI (Single-page Flow)

- File: `templates/Flow/one.php`
- Renders the Art. 12 questions in TRIN 6; the hooks panel is injected via element:
  - `<?= $this->element('hooks_panel', compact('profile','art12','art9','claim','form','meta')) ?>`

- File: `templates/element/hooks_panel.php`
  - Shows: `applies`, `missing`, and now a detailed `Begrundelse` list and a collapsible section with key hook values.

## Hook auto-detection (Heuristics)

- File: `src/Service/OcrHeuristicsMapper.php`
- Detects from OCR text (examples):
  - `through_ticket_disclosure` ("gennemgående billet" / "through ticket")
  - `separate_contract_notice` ("separate contracts ... stated/indicated")
  - `seller_type` (operator vs agency keywords)
  - `contact_info_provided`, `responsibility_explained`, `mct_realistic`

## Controller wiring

- File: `src/Controller/FlowController.php`
- Where Art. 12 is evaluated:
  - In `entitlements()` and in the single-page `one()` method after scope inference and profile build:
    - `$art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $meta);`
  - Single-transaction flags are inferred when `seller_type` and `bookingRef` present (bookingRef can be auto-detected):
    - If `seller_type==='operator'` → `meta['single_txn_operator']='yes'`
    - If `seller_type==='agency'` → `meta['single_txn_retailer']='yes'`

## Scope and Exemptions (context for Art. 12)

- File: `src/Service/JourneyScopeInferer.php`
  - Infers `is_international_inside_eu`, `is_international_beyond_eu`, `is_long_domestic` from stations/products.
- File: `src/Service/ExemptionProfileBuilder.php`
  - Computes `profile.scope` and applies exemption matrix (`config/data/exemption_matrix.json`).
  - If an entry is `blocked`, sets flags and `ui_banners`.

## Data extraction helpers

- File: `src/Service/TicketParseService.php`
  - `extractIdentifiers(text)` → detects PNR / order numbers → used to set `journey['bookingRef']`.
  - `parseSegmentsFromText(text)` → helps with missed-connection UI and carriers list.

## Typical data flow

1. Upload ticket → OCR → Heuristics/LLM extraction → fills `meta['_auto']` and sometimes `seller_type`.
2. TicketParseService extracts `bookingRef`/PNR; journey gets `bookingRef`.
3. Flow sets `single_txn_*` flags from `seller_type` + `bookingRef`.
4. Scope inferred → Exemption profile built → Art. 12 evaluated.
5. Hooks panel shows applies/missing + detailed reasoning and hook values.

## Quick references

- Evaluator call sites:
  - `src/Controller/FlowController.php` → methods `entitlements()` and `one()`.
- UI fields (TRIN 6 – Art. 12):
  - `templates/Flow/one.php` lines ~330–410 (select/radio inputs for the hooks)
- Hooks panel (reasoning display):
  - `templates/element/hooks_panel.php` (TRIN 6 section)

This should be enough to navigate all Article 12 related logic and tweak either detection, UI, or the evaluator rules.
