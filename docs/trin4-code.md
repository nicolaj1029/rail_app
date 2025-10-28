# TRIN 4 (Upload + OCR/LLM extraction) – Code Map

This document lists where TRIN 4 logic lives (file upload, OCR/LLM extraction, auto-fill of section 3.2 fields), how parsed data flows through services, and how the UI updates live.

## Overview

- User uploads a ticket (PDF/image/TXT) in TRIN 4.
- We extract text (PDF parser → pdftotext fallback → Tesseract OCR → optional LLM Vision OCR), then run the extraction pipeline (Heuristics + LLM) to auto-fill section 3.2.
- We parse segments and identifiers (PNR/order, barcode) from the OCR text to support “missed connection” and add metadata for diagnostics.
- We reset stale fields and redirect with PRG to anchor at TRIN 4; the right-hand hooks panel is kept up-to-date via AJAX for subsequent changes.

## Key files and responsibilities

1) Controller (single-page flow)

- File: `src/Controller/FlowController.php`
- Method: `one()`
  - Handles TRIN 4 upload, field reset, OCR/LLM extraction, and UI refresh.
  - Important snippets:
    - Upload handling and reset before OCR
      - Saves to `webroot/files/uploads/`
      - Resets TRIN 3.2 fields, `meta['_auto']`, and journey scope flags to avoid stale data
    - OCR text extraction, with tiered fallbacks
      - PDF → `Smalot\PdfParser\Parser` → pdftotext `-layout` fallback
      - Images → optional Vision-first; else Tesseract with language inference; retry if low yield
    - Extraction pipeline
      - `ExtractorBroker([HeuristicsExtractor, LlmExtractor], threshold=0.66)` merges core fields
      - Populates `meta['_auto']` as `{ field: { value, source } }`
    - Parsing helpers
      - `TicketParseService::parseSegmentsFromText()` → `meta['_segments_auto']` and populates `journey['segments']`
      - `TicketParseService::extractIdentifiers()` → PNR/order; sets `journey['bookingRef']` when found
      - `TicketParseService::parseBarcode()` (optional ZXing CLI) → `meta['_barcode']`
      - `TicketParseService::extractDates()` → fallback for missing `dep_date`
    - Dataset logging
      - Appends normalized record to `webroot/data/tickets.ndjson` for offline queries
    - PRG + live updates
      - After upload, redirect with `#s4` anchor
      - For subsequent field changes, hooks panel updates via `?ajax_hooks=1` (AJAX partial render of `element/hooks_panel`)
    - Scope → exemptions → evaluators
      - After form state updates, `JourneyScopeInferer->apply()` runs before `ExemptionProfileBuilder->build()`
      - `Art12Evaluator` and others consume `journey` and `meta` to update right-side panel

2) Extraction pipeline

- File: `src/Service/TicketExtraction/ExtractorBroker.php`
  - Merges providers; requires a set of core keys to consider extraction “complete”:
    `dep_station`, `arr_station`, `dep_date`, `dep_time`, `arr_time`, `train_no`.
  - If first provider (heuristics) is both confident and complete → fast return
  - Otherwise merges missing fields and returns best or hybrid result

- Files: `src/Service/TicketExtraction/HeuristicsExtractor.php`, `src/Service/TicketExtraction/LlmExtractor.php`
  - Implement `ExtractorInterface` and return `TicketExtractionResult`

3) Ticket text parsing helpers

- File: `src/Service/TicketParseService.php`
  - `parseSegmentsFromText(string $text)`
    - Finds legs by arrow lines (e.g., “City 12:34 → City 13:56”) and DB-style tables
    - Filters “non-station” labels using a JSON dictionary
    - Attaches dep/arr dates if overall dates are found
  - `extractIdentifiers(string $text)` → `{ pnr?, order_no? }`
  - `parseBarcode(string $imagePath)` → optional ZXing CLI read
  - `extractDates(string $text)` → ISO date detection with guarded capture groups
  - Uses `config/ticket_labels.json` for non-station filtering

4) UI (TRIN 4 front-end)

- File: `templates/Flow/one.php`
  - TRIN 4 section (`#s4`)
    - File input with `onchange` autosubmit
    - Shows OCR debug logs from `meta['logs']`
    - Auto-filled 3.2 fields (dep/arr stations, times, train no, ticket no, price)
  - Connections card (only when “missed connection” is selected in TRIN 2)
    - Displays `meta['_segments_auto']` and offers radio choices for “missed connection in (station)”
  - Live hooks panel
    - Right-column element `templates/element/hooks_panel.php` re-renders via AJAX after changes
  - Gating and UX
    - PRG anchor `#s4` after upload; JS keeps TRIN visibility consistent with state (TRIN 1–2)
    - TRIN 6 (Art. 12) visible only if “missed connection” is checked

## Data flow (TRIN 4)

1. User selects a ticket file → controller saves to `webroot/files/uploads/` and resets prior values
2. Extract text (PDF/TXT/Image)
   - PDF: `Smalot\PdfParser` → or `pdftotext -layout`
   - Image: Vision-first (optional) → else Tesseract (language inference) → optional retry if low yield
3. Run extraction: `ExtractorBroker(Heuristics, LLM)` → build `meta['_auto']`
4. Parse segments/identifiers/dates with `TicketParseService`
   - Save `meta['_segments_auto']`, extend `journey['segments']`, set `journey['bookingRef']` if PNR found
5. Persist form echoes for section 3.2 fields
6. Append a normalized record to `webroot/data/tickets.ndjson`
7. Redirect (PRG) to `#s4` and refresh; hooks panel recomputes scope → profile → evaluators

## Important variables and env toggles

- OCR/CLI paths
  - `PDFTOTEXT_PATH` (optional, else `pdftotext` in PATH)
  - `TESSERACT_PATH` (Windows default attempted: `C:\\Program Files\\Tesseract-OCR\\tesseract.exe`)
  - `TESSERACT_LANGS` (override language packs, else inferred from filename)
  - `TESSERACT_OPTS` (default `--psm 6`)
- Vision OCR
  - `LLM_VISION_PRIORITY=first` or `LLM_VISION_FIRST=1` to try Vision first for images
- Barcode
  - `ZXING_CLI_JAR` or fallback to `tools/zxing-cli.jar` (requires `JAVA_BIN` in PATH or `JAVA_BIN` env)
- Safety around env
  - Duplicate dotenv keys are skipped in loader to avoid “key already defined” errors (see load order in bootstrap if relevant)

## UI bindings and live updates

- Upload input autosubmits the form on change
- After upload, the controller performs PRG and redirects to `#s4`
- Subsequent field/question changes trigger `queueRecalc()` → AJAX POST to `?ajax_hooks=1`
  - The controller renders `element/hooks_panel` only, so the right-hand side updates live without scrolling

## Troubleshooting checklist

- PDF yields no text
  - Ensure `pdftotext` is installed or available via `PDFTOTEXT_PATH`
  - Some PDFs are image-only; rely on image path below
- Image OCR not working
  - Verify Tesseract is installed (Windows: `C:\\Program Files\\Tesseract-OCR\\tesseract.exe`)
  - Check `TESSERACT_LANGS`; language inference uses filename hints (e.g., “db_*.jpg” → `eng+deu`)
  - Very short OCR results trigger a second pass with broader langs (e.g., `eng+fra`)
  - Optionally enable Vision OCR (`LLM_VISION_PRIORITY`/`LLM_VISION_FIRST`) for complex tickets
- Incorrect stations (e.g., “Gültigkeit” interpreted as a station)
  - Edit `config/ticket_labels.json` to add stop words/prefixes/contains; TRIN 4 parser uses this dynamically
- Denmark domestic scope misclassification
  - `JourneyScopeInferer` has a DK stations map; add missing cities if needed
- Barcode not detected
  - Place ZXing CLI jar at `tools/zxing-cli.jar` or set `ZXING_CLI_JAR`; ensure `java` available or configure `JAVA_BIN`
- Stale values after uploading a new file
  - The controller resets TRIN 3.2 fields and scope flags on each successful upload; confirm the reset block executes (look for `RESET: cleared ...` in OCR debug logs)

## Quick references

- Controller upload + OCR logic
  - `src/Controller/FlowController.php` → method `one()` (TRIN 4 section: upload, OCR, extraction, parsing, PRG, AJAX hooks)
- Extraction pipeline
  - `src/Service/TicketExtraction/ExtractorBroker.php`
  - `src/Service/TicketExtraction/HeuristicsExtractor.php`
  - `src/Service/TicketExtraction/LlmExtractor.php`
- Parsing helpers
  - `src/Service/TicketParseService.php`
  - `config/ticket_labels.json` (non-station filtering)
- UI
  - `templates/Flow/one.php` (TRIN 4 form fields and OCR debug)
  - `templates/element/hooks_panel.php` (live entitlements, scope, Art. 12 reasoning)

## Success criteria (TRIN 4)

- After upload, the page lands back at TRIN 4 with 3.2 fields pre-filled where available
- OCR debug logs visible and informative (which extractor used, any fallbacks tried)
- If multi-leg detected, the connections list appears when “missed connection” is checked
- Right-hand hooks panel reflects updated scope/profile/evaluations without manual reload
