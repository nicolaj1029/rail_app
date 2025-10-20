# 2025 Ticket Templates (Web/Print)

Purpose: Ready-to-use modern layouts for tickets in 2025 style: minimalist, QR-centric, mobile-first.

## Sizes and formats
- Mobile e-ticket (PNG/HTML): 1080 × 1920 px (portrait)
- Web thumbnail/preview: 1200 × 630 px (landscape)
- Print card: 85 × 54 mm (credit-card size)
- Print A6: 105 × 148 mm (more fields)
- PDF: 300 DPI for print; RGB for web

## Required fields
- Operator & logo (top-left)
- Ticket type (One-way / Return / Day pass / Pass name)
- Travel date & time + validity window
- From → To (stations)
- Coach / seat / class (if relevant)
- Passenger name (if named ticket)
- Price & currency
- Ticket ID / PNR / booking code (6–8 chars typical)
- QR code (20–25 mm printed; 30–40% bottom area on mobile)
- T&Cs / refund policy microcopy
- Contact details / customer service

## Typography & accessibility
- Font: Inter / Roboto / Noto Sans
- Headings: 20–24 px (mobile), body 14–16 px
- Contrast: ≥ 4.5:1
- Colors (2025): primary #0B5FFF, accent #00C48C, neutral #0F1724, bg #FFFFFF/#F7FAFC
- Icons: thin line icons (time, coach, person, baggage)

## QR & security
- Embed Ticket ID, PNR, trip data + HMAC/hash
- Place QR bottom-right/center with “Scan ved ombordstigning / Scan at boarding”

## Files here
- style.css — shared stylesheet
- mobile-ticket.html — 1080×1920 portrait mobile ticket
- web-thumbnail.html — 1200×630 preview card
- print-a6.html — A6 print ticket

## Example text (EN + DA)
Header (EN): "DEPARTURE TICKET — Trenitalia | 2nd Class"
Header (DA): "Afgangsbillet — DSB | 2. klasse"

Body (EN):
- Valid: 2025-10-20 08:00–23:59
- From: Bologna Centrale → To: Milano Centrale
- Coach/Seat/Class: 5 / 12B / 2nd
- Passenger: Jane Doe
- Price: 49.90 EUR
- Ticket ID: AB12C3 — PNR: ZX8QK1

Body (DA):
- Gyldig: 2025-10-20 08:00–23:59
- Fra: København H → Til: Odense
- Vogn/Sæde/Klasse: 5 / 12B / 2.
- Passager: Jens Jensen
- Pris: 249,00 DKK
- Billet-ID: DK8899 — PNR: Q1W2E3
