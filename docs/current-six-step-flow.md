# Six-step flow: functional and technical documentation

Scope: This document covers only the six-step complaint flow we worked on, as implemented in the current codebase, and the way it feeds the official EU PDF. It does not document OneFlow or other unrelated features.

Key components
- Controller: `src/Controller/ReimbursementController.php`
  - Entry point: `official()` builds the official EU/national PDF using FPDI/FPDF and session-backed answers.
- Field maps:
  - EU map: `config/pdf/reimbursement_map.json`
  - National maps: `config/pdf/forms/*.json` (auto-selected based on operator/country; if none match, EU map is used)
- Session state:
  - `flow.form`: in-flight answers from the UI steps
  - `flow.meta`: derived/meta information (incident structure, chosen path, etc.)

Data normalization helpers (inside `official()`)
- Values are stringified consistently for PDF (booleans → ja/nej, arrays → ", "-joined filenames)
- Incident inference: If `incident_main` is missing, try to infer from reason flags and missed-connection hints.
- Exclusive remedy mapping: `remedyChoice` enforces exactly one of the three options on the official form.
- Visibility: checkbox rendering draws a bold X and a thin border for the three reason checkboxes on page 1 for clarity.

---

## TRIN 1 — Status/Hændelse (incident)

Inputs (keys)
- `incident_main`: one of delay | cancellation | missed_connection (legacy `delayLikely60` token removed)
- `missed_connection`: boolean
- `missed_connection_station`: string (station name)
- Optional support flags: `reason_delay`, `reason_cancellation`, `reason_missed_conn`

Derivations and rules
- If `incident_main` is empty, infer it based on present reason flags or missed-connection hints (including `missed_connection_station`).
Derivations and rules
- If `incident_main` is empty, infer it based on present reason flags or missed-connection hints (including `missed_connection_station`).
- The three page-1 reason checkboxes are derived with override behavior:
  - Normalize `incident_main` to letters only (punctuation stripped).
  - A reason checkbox is considered empty if its current value is one of: "", null, false, "0", "nej", "no", "false".
  - If empty, it is set true when `incident_main` contains its token:
    - `reason_delay` if incident contains "delay"
    - `reason_cancellation` if incident contains "cancel"
    - `reason_missed_conn` if incident contains "missed" OR any missed-connection flag is set
  - If a reason is already true in input, it remains true. Explicit true always wins.

PDF mapping (EU template)
- `config/pdf/reimbursement_map.json` page 1:
  - `reason_delay` at (x:29, y:133)
  - `reason_cancellation` at (x:29, y:140)
  - `reason_missed_conn` at (x:29, y:147)
- Rendering draws a visible X and a thin rectangle for these three.

Notes
- These fields are also shown in the debug summary at the top of `official()` (for troubleshooting), but are not repeated on the consolidated page.

---

## TRIN 2 — Rejsedetaljer og billet

Inputs (keys)
- `operator`, `dep_date`, `dep_time`, `actual_dep_time`, `dep_station`, `arr_station`, `arr_time`, `actual_arr_time`
- `train_no`, `ticket_no`, `price`, `actual_arrival_date`

Rules
- Values are taken from request data; if missing, backfilled from `flow.form` or `flow.meta` in that order.
- `price` is parsed for a 3-letter currency code (used later as a fallback for assistance/expense currency labels).

PDF mapping (EU template)
- `config/pdf/reimbursement_map.json` page 2 provides coordinates for all fields above.

---

## TRIN 3 — Rettigheder/forhold (Art. 6, Art. 9(1), Art. 12, Art. 21–24)

Sub-areas and keys
- Art. 12 (kontrakt/oplysninger)
  - `through_ticket_disclosure`, `single_txn_operator`, `single_txn_retailer`, `separate_contract_notice`, `shared_pnr_scope`, `seller_type_operator`, `one_contract_schedule`, `contact_info_provided`, `responsibility_explained`, `continue_national_rules`
- Art. 9(1) (forudgående oplysninger, hurtigste rejse, priser/fleksibilitet, klasser/reservationer, information)
  - Info hooks: `info_requested_pre_purchase`, `coc_acknowledged`, `civ_marking_present`
  - Hurtigste rejse: `fastest_flag_at_purchase`, `mct_realistic`, `alts_shown_precontract`
  - Billetpriser/fleksibilitet: `multiple_fares_shown`, `cheapest_highlighted`, plus text: `fare_flex_type`, `train_specificity`
  - Klasse/reserveret: `fare_class_purchased`, `class_delivered_status`, `berth_seat_type`, `reserved_amenity_delivered`
  - Information: `preinformed_disruption`, `preinfo_channel`, `realtime_info_seen`
- Art. 6 (Cykel)
  - `bike_was_present`, `bike_caused_issue`, `bike_reservation_made`, `bike_reservation_required`, `bike_denied_boarding`, `bike_refusal_reason_provided`, `bike_refusal_reason_type`

### Bike question simplification (Art. 6)

The initial bike presence question has been simplified to a strict yes/no choice (removed legacy "Ved ikke"). When no reservation/count signals are detected in OCR/auto extraction, the system now preselects "Nej" (negative auto-default) while logging `AUTO: bike_booked=Nej (no bike signals detected)`.

Rules:
1. Positive detection (any reservation/count pattern) still sets `bike_booked=Ja` and populates `_auto.bike_booked`.
2. Negative auto-default does NOT create an `_auto.bike_booked` entry (to avoid falsely triggering downstream `hadBike` inference). Only `meta['bike_booked']='Nej'` is written.
3. User can override by selecting "Ja"; controller logic never overwrites an explicit user answer.
4. Hooks panel shows an "Auto: Ingen signaler" badge when the negative default was applied with zero evidence.
5. `hadBike` derivation remains unchanged (true only for explicit yes or positive auto `_auto.bike_booked`).

Server-side default of presence:
- When no bike evidence is detected and confidence is low (< 0.5), the controller also sets `meta['bike_was_present']='no'` and records `_auto.bike_was_present={ value:'no', source:'bike_detection' }`. This keeps the data layer aligned with the UI even før første submit; users can still override to "Ja".

Edge case: If later manual input sets `bike_count` without flipping presence to Ja, we still treat presence as Nej until the user selects Ja explicitly.
- Art. 21–24 (PMR)
  - Baseline: `pmr_user`, `pmr_booked`
  - Q-set: `pmrQBooked`, `pmrQDelivered`, `pmrQPromised`, `pmr_facility_details`

Consolidated page (Section 6) inclusion
- Items are grouped under TRIN 3 with subheaders by article.
- Exclusions (to reduce noise): `continue_national_rules`, `complaint_channel_seen`, `complaint_already_filed`, `submit_via_official_channel`, entitlement toggles (`request_refund`, `request_comp_60`, `request_comp_120`, `request_expenses`), bike flags `bike_res_required`, `bike_followup_offer`.
- Non-empty answers are included; blank answers are omitted (we don’t print explicit "nej" unless the value is present and boolean false).
- Ordering under TRIN 3: Art. 6 → Art. 9 → Art. 12 → Art. 21–24 → other; Art. 9(1) subgroups (Hurtigste rejse, Billetpriser, Klasse, Information) are clustered.
- Deduplication by field key prevents repeated lines (particularly relevant for Art. 12 when augmenting from multiple sources).

PDF mapping (EU template)
- Page 5 has checkboxes/text for many TRIN 3 fields, used for anchoring; however we do not write them one-by-one there.
- Instead, we collect all TRIN 3–6 items and render the full Section 6 on a dedicated blank page after the template pages.

---

## TRIN 4 — Afhjælpning og krav (Refusion/Omlægning)

Inputs (keys)
- Refusion: `trip_cancelled_return_to_origin`, `refund_requested`, `refund_form_selected`
- Omlægning: `reroute_same_conditions_soonest`, `reroute_later_at_choice`, `reroute_info_within_100min`, `self_purchased_new_ticket`, `reroute_extra_costs`, `downgrade_occurred`
- Exclusive main choice: `remedyChoice` ∈ {`refund_return`,`reroute_soonest`,`reroute_later`}

Derivations
- If only `remedyChoice` exists, we set the three official checkboxes on page 3 accordingly:
  - `remedy_cancel_return` (A: refund & return)
  - `remedy_reroute_soonest` (B1: reroute at earliest opportunity)
  - `remedy_reroute_later` (B2: reroute later at passenger’s choice)
- If `remedyChoice` is missing, infer from booleans in priority order:
  1) `reroute_later_at_choice` → `reroute_later`
  2) `reroute_same_conditions_soonest` → `reroute_soonest`
  3) `refund_requested` → `refund_return`
- We also map `chosenPath` (from rules/flow) to `remedyChoice` when present: refund → refund_return, reroute_soonest, reroute_later.

Consolidated page
- All TRIN 4 questions are included under the TRIN 4 section even if unanswered (printed as empty answers). This gives a complete overview of remedy decisions.

PDF mapping (EU template)
- `reimbursement_map.json` page 3: the three remedy checkboxes: `remedy_cancel_return`, `remedy_reroute_soonest`, `remedy_reroute_later`.

---

## TRIN 5 — Assistance og udgifter (Art. 20)

Inputs (keys)
- Assistance toggles: `meal_offered`, `hotel_offered`, `overnight_needed`, `blocked_train_alt_transport`, `alt_transport_provided`
- Evidence: `extra_expense_upload`, `delay_confirmation_received`, `delay_confirmation_upload`
- Expense breakdown: `expense_breakdown_meals`, `expense_breakdown_hotel_nights`, `expense_breakdown_local_transport`, `expense_breakdown_other_amounts`
- Legacy/aggregate amounts (fallback): `expense_meals`, `expense_hotel`, `expense_alt_transport`, `expense_other`

Derivations and display
- Currency: derived from `price` (first 3-letter currency code).
- Consolidated layout is standardized:
  - A) Tilbudt assistance: meal offered + amount + currency + upload; hotel offered + amount + currency + nights + upload; blocked train alt transport + amount + currency + upload.
  - B) Alternative transporttjenester: provided + amount + currency + upload.
- Amount and currency lines are printed only when the underlying value is non-empty; upload prints only if a filename exists.

PDF mapping (EU template)
- Page 5 contains anchors for these fields; the final rendered content appears in the dedicated Section 6 page.

---

## TRIN 6 — Kompensation (Art. 19)

Inputs (keys)
- Typical keys (when present): `delayAtFinalMinutes`, `compensationBand`, `voucherAccepted` — and entitlement toggles `request_comp_60`, `request_comp_120` (entitlement toggles are excluded from TRIN 3 listing).

Display
- Reserved in group titles as "TRIN 6 · Kompensation (Art. 19)". Present fields can be mapped and shown similarly to TRIN 4/5 when added to the map/summary in the future.

---

## Section 6 assembly and rendering

Where built
- In `official()`, when processing EU page 5, we collect TRIN 3–6 items into `$groups` and convert them into lines (`$allLines`). We then skip writing per-field values on page 5 and, after finishing template pages, render the Section 6 content on a dedicated blank page (A4) using `MultiCell`, with TRIN headers bolded.

What’s included
- TRIN 3: Art. 6 (Bike), Art. 9(1) (Info/Hurtigste rejse/Priser/Klasse/Information), Art. 12, PMR (21–24). Info items (info_requested_pre_purchase, coc_acknowledged, civ_marking_present) are included when answered.
- TRIN 4: All remedy questions are listed, even when unanswered.
- TRIN 5: Assistance + amounts + currency + uploads in a fixed A/B layout.
- TRIN 6: Reserved title; items appear if introduced in the map or in the future builder.

De-duplication and ordering
- Items are de-duplicated by the `field` key after all augmentation (avoids duplicate Art. 12 lines).
- Subheader ordering inside TRIN 3: Art. 6 → Art. 9 → Art. 12 → Art. 21–24 → rest.
- Art. 9(1) subheaders (Hurtigste rejse, Billetpriser, Klasse, Information) are grouped, with preferred question order inside Hurtigste rejse.

Exclusions to reduce noise
- From TRIN 3 only: `continue_national_rules`, complaint flow hints, entitlement toggles (`request_refund`, `request_comp_*`, `request_expenses`), selected bike flags (`bike_res_required`, `bike_followup_offer`).

Additional information
- If the user entered free text in `additional_info`, it’s appended to the Section 6 page as-is (paragraph split by newline).

Fallback path
- If the selected template doesn’t include page 5 configuration, a fallback builder (replicating the logic above) uses a data snapshot and still produces the summary page.

---

## Template selection and mapping resolution

- If `?eu=1` or `?force=eu`, we explicitly select the EU template from `webroot` or `webroot/files` using `findOfficialTemplatePath()`.
- Otherwise, we try a national template by consulting `FormResolver` based on country/operator/product; if none applies, we fall back to the EU template.
- Field map is loaded in this order: national field map (if national template), EU map (`config/pdf/reimbursement_map.json`), and finally a minimal built-in map (`officialFieldMap()`).

Debugging aids
- Add `?debug=1` to overlay a coordinate grid and draw the page-5 additional_info box rectangle.
- `?dx` and `?dy` allow nudging all coordinates; `?boxdy` allows vertical adjustments for the page-5 box in the debug overlay.

### Session inspection & developer JSON

- Append `?session=1` to the `official()` URL to short‑circuit PDF generation and return a JSON payload instead of a PDF.
  - Top-level object keys: `selected` (flattened + normalized dataset after inference), `form` (raw `flow.form`), `meta` (raw `flow.meta`).
  - Use this to distinguish absence of data in session from omission in the consolidated summary.
  - Safe, read-only: does not mutate stored session values.

### Recursive nested key flattening

- A deep scan now walks nested arrays/objects in `flow.form` and `flow.meta` to surface keys previously buried in card/list structures (e.g. `pmr_delivered_status`, `single_booking_reference`).
- Non-empty existing top-level values are never overwritten; only missing/empty slots are backfilled.

### Alias / synonym mapping (TRIN 3 augmentation)

| Incoming key              | Normalized target       | Purpose (Article)              |
|---------------------------|-------------------------|--------------------------------|
| `single_booking_reference`| `shared_pnr_scope`      | Contract scope disclosure (Art. 12) |
| `seller_type_agency`      | `seller_type_operator`  | Bookseller classification (Art. 12) |
| `pm_bike_involved`        | `bike_was_present`      | Bike presence (Art. 6) |
| `pmr_promised_missing`    | `pmrQPromised`          | Missing promised assistance (Art. 21–24) |
| `pmr_delivered_status`    | `pmrQDelivered`         | Delivered assistance status (Art. 21–24) |
| `pmr_booked_detail`       | merged into `pmr_facility_details` | Extra PMR detail text (Art. 21–24) |

### PMR field inclusion guarantee

- `pmr_user` and PMR Q-set (`pmrQBooked`, `pmrQDelivered`, `pmrQPromised`, `pmr_facility_details`) always considered; alias mapping ensures variants appear.
- Empty PMR answers are skipped (to avoid clutter); non-empty printed with canonical labels.

### Simplified delay inference

Legacy UI confirmation (`delayLikely60`) removed. Delay now inferred only from:
1. Explicit `incident_main=delay`
2. Missed connection + qualifying live delay minutes (when available)
Explicit false-like markers ("nej", `false`, `0`) on `reason_delay` block auto‑ticking.

### Ordering refinements

- Alias-mapped Art. 12 items maintain precedence; duplicates eliminated post-normalization.
- PMR (Art. 21–24) consistently placed after Art. 12 and before residual uncategorized fields.

---

## Edge cases and assumptions

- Incident inference tolerates minor token variants (case/punctuation). Deprecated token `delayLikely60` ignored. False-like explicit answers ("nej", "0", false) block auto‑ticking.

---

## PMR automatic detection (Art. 21–24)

`PmrDetectionService` evaluates OCR text + structured fields.

Hard signals (high weight)
- Booked assistance markers (Serviceauftrag, Mobilitätshilfe, Servizio di assistenza PMR, Assistance handicapée, Asistencia PMR, etc.)
- Handicap fare markers (Ermäßigung: Schwerbehindert, Carta Blu, handicap, discapacidad, mobilità ridotta)
- Icon / symbol hints (♿, PMR/PRM tokens, wheelchair terms)

Soft signals (low weight; applied only when assistance not booked)
- Generic assistance words (assistance, hjælp, hilfe, aide, ayuda, assistenza)
- Mobility phrases (mobilité réduite, persona con movilidad reducida, mobilità ridotta, reduced mobility)

Confidence heuristic
- Assistance booked: +0.85
- Discount hits: +0.35 each (cap 3)
- Icon hits: +0.25 each (cap 2)
- Soft hints: +0.08 each (cap 4, ignored if assistance booked)
- Capped at 1.00 (rounded 2 decimals)

Meta population
- `meta['_pmr_detection'] = {pmr_user, pmr_booked, discount_type, evidence[], confidence}`
- Auto flags mirrored to `meta['_auto']` with source=pmr_detection
- User overrides not overwritten once set

Safeguards
- Soft hints alone never set pmr_user unless paired with discount/icon evidence
- Refused assistance (`pmr_booked=refused`) sets `pmr_booked_detail=refused`
- Consolidated page prints only non-empty values for TRIN 3 areas to avoid cluttering with explicit negatives; TRIN 4 questions are listed regardless, so reviewers see the remedy set even if not yet answered.
- Amounts and currency lines show only if provided; currency derived from `price` is a convenience and may be blank if `price` lacks a 3-letter code.

---

## Quick code index (anchors)

- `ReimbursementController::official()`
  - Map selection and session backfill
  - Incident inference and reason_* normalization (page 1)
  - Exclusive remedy mapping from `remedyChoice`/`chosenPath` (page 3)
  - Recursive nested session flatten + alias normalization
  - PMR guaranteed inclusion and bike alias handling
  - Section 6 assembly (TRIN 3–6), dedup, ordering, exclusions (page 5 → extra page)
  - Fallback summary builder when map[5] is absent
- EU field map (coordinates): `config/pdf/reimbursement_map.json`
  - Page 1: reason_* checkboxes
  - Page 2: travel details / ticket
  - Page 3: remedy choices
  - Page 5: anchors for consolidated summary inputs, plus `additional_info` box

