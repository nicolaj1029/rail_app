# PDF Forms Fallback Structure

This directory contains operator / country specific PDF claim form templates and a generic fallback used when no operator form is available.

Structure:
- forms/
  - README.md (this file)
  - default_claim_template.json (generic mapping of reimbursement_map.json keys to PDF fields)
  - <country>/
      <operator>/
         claim_form.pdf (binary – NOT tracked here unless stub)
         mapping.json (optional override or delta to reimbursement_map.json)
         notes.md (explanations / quirks)

Naming conventions:
- Country folder: ISO 2-letter lowercase (dk, de, se, fr)
- Operator folder: lowercase slug (dsb, sj, db, sncf)
- mapping.json: keys only for differences or additions. Fallback merges reimbursement_map.json then operator mapping.

Merging algorithm (proposed):
1. Load reimbursement_map.json base.
2. If country/operator mapping.json exists: deep merge (operator values override base).
3. If a country-level mapping.json exists (forms/<country>/mapping.json) apply before operator mapping.

Binary forms:
- Place filled PDF base (acroform) as claim_form.pdf; renderer will stamp coordinates using final merged mapping.

Next steps:
- Implement PdfTemplateResolver service:
  resolve(country, operator) -> { pdfPath, mapping }
- Add unit test ensuring fallback to default when country/operator not found.
