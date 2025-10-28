# Redegørelse: TRIN 6 (Art. 12 – gennemgående billet) i rail_app

Formålet med denne redegørelse er at give et samlet og operationelt overblik over TRIN 6 og relateret kode for vurdering af Art. 12 (gennemgående billet). Dokumentet er skrevet, så en LLM (GPT) kan foreslå forbedringer til auto-afledning, evaluator-logik, UI og PDF-output.

## Kort overblik (komponenter og filer)

- Evaluator: `src/Service/Art12Evaluator.php`
  - Metode: `evaluate(array $journey, array $meta = [])`
  - Output: `hooks`, `missing` (UI/AUTO), `art12_applies` (bool|null), `liable_party`, `reasoning` (array), `basis`, `notes`.
- AUTO-afledning: `src/Service/Art12AutoDeriver.php`
  - Afleder bl.a. `single_txn_operator`, `single_txn_retailer`, `shared_pnr_scope`, `seller_type_*`, `multi_operator_trip`, `single_booking_reference` uden at overskrive brugerinput.
- Flow (single-page): `src/Controller/FlowController.php::one()`
  - Persisterer TRIN 6 svar, kører OCR/afledning, evaluerer Art. 12 og håndterer AJAX-partial til hooks-panelet.
- TRIN UI (flow): `templates/Flow/one.php`
  - Viser TRIN 6-spørgsmål og skjulte/auto-felter; unified køb-spørgsmål mapper til evaluator hooks; hooks-panel i højre side opdateres live.
- Hooks-panel (diagnostik): `templates/element/hooks_panel.php`
  - Viser `art12_applies`, `classification/basis`, `missing_ui`, `missing_auto`, `reasoning` og en detaljeret "Vurderingsgrundlag" (alle hooks).
- PDF (official Section 6): `src/Controller/ReimbursementController.php`
  - Mapper udvalgte TRIN-svar til “Additional information” på en dedikeret fortsættelsesside (kun besvarede spørgsmål medtages).
- Dokumentation: `docs/art12_full_text.txt`, `docs/art12-code.md`, `docs/implementation_bundle_2025-10-14.md`.

Supplerende (modulær reference til GPT/POC):
- TypeScript-udgave af det nye TRIN 6-flow: `mocks/art12.ts` (kan køres med ts-node i mocks-miljøet; bruger "ja"/"nej"/"unknown`).

## “Kontrakt” (inputs/outputs) for Art. 12

- Inputkilder
  - Formular (TRIN 6 i flowet) → `meta[...]`
  - AUTO-afledning (OCR, 3.2.7, segmenter) → `meta[...]` og `journey[...]`
- Centrale hooks (nøgler i `meta`, med typiske værdier)
  - `through_ticket_disclosure`: `Gennemgående | Særskilte | unknown`
  - `single_txn_operator`: `yes | no | unknown`
  - `single_txn_retailer`: `yes | no | unknown`
  - `separate_contract_notice`: `Ja | Nej | unknown`
  - `shared_pnr_scope`: `yes | no | unknown` (auto)
  - `seller_type_operator`, `seller_type_agency`: `yes | no | unknown` (auto/afledt af `journey.seller_type`)
  - `multi_operator_trip`: `yes | no | unknown` (auto)
  - `connection_time_realistic` (alias “mct_realistic” i legacy UI/PDF): `yes | no | unknown` (primært debug/kontekst)
  - `one_contract_schedule`, `contact_info_provided`, `responsibility_explained`: `yes | no | unknown` (auto/skjult)
  - `single_booking_reference`: `yes | no | unknown` (auto)
  - `exemption_override_12`: bool/flag i profil der kan slå Art. 12 fra
- Output (fra evaluator)
  - `art12_applies`: bool|null
  - `liable_party`: `operator | agency | mixed | unknown`
  - `hooks`, `missing_ui`, `missing_auto`, `reasoning[]`, `classification`, `basis`, `notes`

## TRIN 6 UI (flow) – Hvad brugeren ser

- “Hvordan blev hele rejsen købt?” (unified prompt)
  - Mapper til skjulte felter: `single_txn_operator`, `single_txn_retailer`, samt `seller_type_*` og en samlet `seller_type` til backend-konsistens.
- Kontrakt-type disclosure
  - `through_ticket_disclosure`: Gennemgående | Særskilte | Ved ikke (normaliseres til `unknown`).
  - `separate_contract_notice`: Ja | Nej | Ved ikke (normaliseres til `unknown`).
- AUTO/skjulte felter (kun debug/konflikt)
  - `shared_pnr_scope`, `single_booking_reference`, `multi_operator_trip`, `connection_time_realistic`, `one_contract_schedule`, `contact_info_provided`, `responsibility_explained` m.fl.
- Live opdatering
  - Ændringer i TRIN 4/6 trigger en debounced AJAX-opdatering af hooks-panelet.
  - Seneste fix: skjulte (ikke-viste) TRIN 9-radioer bliver disabled, så de ikke overskriver TRIN 6-valg (fx `through_ticket_disclosure`).

## AUTO-afledning (kilder og regler)

- 3.2.7 `ticket_no` → `journey.bookingRef`
  - Enkelt token: bruges som bookingRef (PNR).
  - Flere forskellige tokens: sætter `shared_pnr_scope = 'Nej'` (hint: separate ordrer) hvis uafklaret.
- OCR/tekst
  - Mapper operator, produkt, stationer, tider, segmentliste, passagerer og identifikatorer (PNR/order_no) til `_auto` og/eller journey.
- Seller-type
  - Afledes fra `purchaseChannel` (“station/onboard” → operator, “web_app” → agency) eller billetter/PNR.
- Art12AutoDeriver
  - Afleder `single_txn_*`, `shared_pnr_scope`, `seller_type_*`, `multi_operator_trip`, `single_booking_reference` m.m. uden at overskrive eksplicitte brugerinputs.

## Evaluator-regler (kort)

- Kortslutning: `exemption_override_12` kan sætte `art12_applies = false`.
- `separate_contract_notice === 'Ja'` → `art12_applies = false` (Art. 12(5)).
- Positivt grundlag (et eller flere af nedenstående kan være nok):
  - `through_ticket_disclosure === 'Gennemgående'`
  - `shared_pnr_scope === 'yes'`
  - `single_txn_operator === 'yes'` (Art. 12(3))
  - `single_txn_retailer === 'yes'` (Art. 12(4))
  - `seller_type_agency === 'yes'` og `separate_contract_notice !== 'Ja'` → default til applies=true (agent-ansvar)
- Missing-split
  - UI-mangler: spørgsmål der bør vises/brugerbesvares
  - AUTO-mangler: felter vi forventer kan afledes fra data (fx PNR/bookingRef)

## PDF – Official Section 6 (fortsættelsesside)

- Kun besvarede TRIN-svar medtages under “Additional information”.
- Spørgsmålstekster styres i `ReimbursementController.php` (fx “Blev det oplyst at billetten var gennemgående?”).
- Side placeres på separat blank A4 (fortsættelse). Dansk sprog, single-byte output med transliteration.

## Live diagnostik (hooks-panel)

- Viser: `art12_applies`, `classification/basis`, `missing_ui`, `missing_auto`, `reasoning[]` og detaljerede hooks.
- AJAX-refresh på TRIN 4/6 inputændringer.
- Seneste UX-fix: For at undgå at skjulte sektioner overskriver TRIN 6-valg, disables de skjulte radiofelter i TRIN 9.

## Kendte begrænsninger og edge cases

- PNR-gruppering på tværs af flere billetter/kanaler kan være tvetydig (samlet vs separate køb).
- `seller_type` kan være usikker ved tredjepartsportaler eller white-labels.
- OCR-støj og varierede billetlayouts kan give mangelfulde `_auto`-felter.
- Alias: `connection_time_realistic` vs. legacy `mct_realistic` (PDF/tekster). Systemet normaliserer nu til `connection_time_realistic` i evaluatorstien.
- Normalisering: “Ved ikke”/tomt → `unknown` for ensartet logik.

## Ideer og forbedringsforslag (til GPT)

- Auto-afledning
  - Forbedr PNR/ordrenummer-heuristik for at skelne “samme reference i hele rejsen” vs. “separate køb” (inkl. navnelister, tidsstempler, beløb).
  - Udvid `TicketParseService` til at genkende flere operatør-/agentmønstre og kanaler (fx white-label domæner/brands) → bedre `seller_type`.
  - Afled `multi_operator_trip` fra segmenternes carrier/operator felter; vægt i reasoning.
- Evaluator-logik
  - Indfør vægtet score, hvor stærke signaler (PNR fælles + single transaction) kan opveje svage modsignaler.
  - Uddyb “liable_party” beslutning ved tvetydighed (forklaringsstreng, sandsynligheds-score).
  - Konsistenschecks: konflikt mellem `shared_pnr_scope` og `single_booking_reference` giver tydelig “missing_auto” guidance.
- UI/UX
  - Vis “usikkerhedsindikator” i hooks-panelet ved svage/konfliktende signaler og foreslå den mest værdifulde næste oplysning.
  - Saml TRIN 6 og relevante TRIN 4 hints i én kompakt “Kontrakt & køb”-kort, der skifter til debug-visning ved konflikt.
- PDF
  - Gruppér TRIN 6 svar med kort forklaring (1 linje) af hvorfor de er vigtige for Art. 12; bevar kun besvarede.
  - Tilføj (valgfrit) en “diagnostisk fodnote” med reference til uploadede billetter (ikke følsomme data), der understøtter påstande.
- Data-kvalitet og observability
  - Log nøgledisagreements mellem UI og AUTO (fx bruger siger “Gennemgående”, men PNR viser flere ref’er) og brug dette til forbedringsloop.
  - Føj en lille “confidence” til hver AUTO-afledt hook baseret på kildemængde og entydighed.
- Test og fixtures
  - Tilføj enhedstests for blandede cases (agency-salg + uoplyste separate kontrakter; flere billetter – samme PNR osv.).
  - Udvid mocks/fixtures til flere sprog og billettyper (mobil-app kvitteringer, QR-only, webemails).

## Næste skridt (konkrete)

1. Harmonisér alias: gennemgå PDF-teksterne og tilføj `connection_time_realistic` som primær nøgle; behold `mct_realistic` som alias for bagudkompatibilitet.
2. Udvid `TicketParseService` med flere agent/brand-signaler; map til `seller_type` og `single_txn_*`.
3. Indfør en “evidence strength” score pr. hook i `Art12AutoDeriver` og vis kort indikator i hooks-panel.
4. Implementér konfliktindikator + anbefalet næste svar i TRIN 6 (guided missing_ui → action).
5. Tilføj tests for: (a) multi-ticket med samme PNR → applies=true; (b) tydeligt oplyste “Særskilte” → applies=false; (c) agency-salg uden notice → applies=true.
6. Forbedr PNR-tokenizer (hyphen/space-varianter) og robusthed over for OCR-fejl.

## Appendix: værdidomæner og normalisering

- Ja/Nej/“Ved ikke” → `yes`/`no`/`unknown`
- “Gennemgående”/“Særskilte”/“Ved ikke” → `Gennemgående`/`Særskilte`/`unknown`
- Tomme værdier normaliseres til `unknown` i controller, før evaluator kaldes.
- Alias: `mct_realistic` (legacy) → `connection_time_realistic` (evaluator)
