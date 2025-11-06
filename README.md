## 🧾 INTRODUKTION – Juridisk Kompensationsportal (EU 2021/782)

Jeg er jurist med speciale i EU-passagerrettigheder og erstatningsret, og udvikler en automatisk portal, hvor togpassagerer kan få udbetalt kompensation eller refusion inden for 24 timer — i fuld overensstemmelse med EU-forordning 2021/782 og CIV-reglerne.

Systemet skal:

- 📥 Automatisk indlæse billetten (PDF, PNG, PKPass, screendump, osv.)
- 🧠 Udføre OCR-scanning og barcode-aflæsning; identificere operatør, rute, tognummer, pris, klasse, dato og PNR-kode
- 🔗 Berige oplysninger via åbne API’er (RNE, SNCF, DSB, DB, ÖBB m.fl.) for planlagt/faktisk køreplan, forsinkelser og aflysninger
- ⚖️ Automatisk anvende EU-reglerne om refusion (art. 18), kompensation (art. 19), assistance (art. 20) samt CIV-regler
- 🧾 Udfylde EU’s officielle kompensationsformular (bilag II, del 1-10) og markere felter med manglende/lav tillid
- 🧩 Vise præcise mikro-prompts kun når data mangler eller billet vs. RNE ikke stemmer
- 🧾 Beregne kompensationsbeløb (25 % / 50 %) inkl. nationale minimumssatser, gennemgående billetter og EU/ikke-EU-segmenter
- 🪪 Indhente digital fuldmagt og identifikation (eID) i henhold til eIDAS-forordningen (EU 910/2014)
- 🔐 Sikre GDPR-overholdelse: kryptering, dataminimering, audit-trail, automatisk sletning
- 📦 Generere komplet sagsdokumentation (bilag) med billet, RNE-snapshot, operatørpåtegning og brugerbilag
- 💶 Udbetale kompensation/refusion automatisk, minus gebyr, når ID og fuldmagt er verificeret

---

## ⚙️ Teknisk workflow (autonomt & API-drevet)

1) Upload billetten → OCR + barcode parser udtrækker data.
2) Berig med API-kald → RNE + operatør-API.
3) Autofyld EU-formular 3.1–3.3 → felter > 0.85 låses, øvrige får mikro-prompts.
4) Juridisk validering (Art. 12) → gennemgående vs. særskilte kontrakter.
5) Refusion (Art. 18) → aflysning/omlægning/refusion.
6) Kompensation (Art. 19) → 25 % / 50 %, EU-segmenter, force majeure.
7) Assistance (Art. 20) → mad, hotel, alternativ transport, bilag.
8) Fuldmagt + Identifikation → eID + signatur + hash-bevis.
9) Bevisførelse (Bilag II + CIV) → samlet PDF-sag.
10) Udbetaling → automatisk via betalingsgateway.

---

## 💡 Demo-mode og testdata

Realistiske demo-cases til test af autofyld og beregning:

| Demo-case         | Type            | Forsinkelse       | Forventet kompensation |
| ----------------- | --------------- | ----------------- | ---------------------- |
| `ice_125m`        | Gennemgående EU | 125 min           | 50 %                   |
| `tgv_30m`         | National (FR)   | 30 min            | Voucher (G30)          |
| `ter_missed_conn` | Regional        | Missed connection | Art. 12-ansvar         |
| `ic_no_rne`       | Data-mangler    | —                 | Mikro-prompts          |

API: `/api/demo/fixtures?case=ice_125m|tgv_30m|ter_missed_conn|ic_no_rne`

---

## 🚀 Kom i gang (lokalt)

Forudsætninger: PHP 8.1+, Composer, MySQL/MariaDB.

1) Installer afhængigheder

```powershell
cd c:\wamp64\www\rail_app
composer install
```

2) Konfigurer database i `config/app_local.php` (default: `train_app`, user `root`, no password).

3) Kør migrationer

```powershell
bin\cake.bat migrations migrate
```

4) Start server (eller brug WAMP)

```powershell
bin\cake.bat server -p 8765
```

5) Test links

- Forside: http://localhost:8765/
- Projektfiler (PDF’er): http://localhost:8765/project
- Reimbursement (demo): http://localhost:8765/reimbursement
	- Knappen “Indlæs eksempel (ICE 125 min)” autofylder felter via `/api/demo/fixtures`.
- Claims kalkulator: http://localhost:8765/claims
- Admin (Basic Auth): http://localhost:8765/admin/claims (user: `admin`, pass: `changeme`)

---

## 🔌 API-oversigt

- `GET /api/demo/fixtures?case=…` → JourneyRecord-demo
- `POST /api/ingest/ticket` → OCR/Barcode stub
- `GET /api/rne/trip` → RNE stub
- `GET /api/operator/{operatorCode}/trip` → Operator stub
- `POST /api/compute/compensation` → beregn minutter, pct og beløb fra JourneyRecord

---

## 📄 EU-formular (FPDI)

`/reimbursement/official` udfylder Kommissionens formular (EN, accessible) med FPDI ud fra formfelter. Feltkoordinater kan udvides i controlleren for præcis placering; test output og iterér.

---

## 🛡️ Compliance

Indbygget grundlag for Basic Auth på admin, dataminimering ved uploads, samt audit-venlig sagsoversigt. For produktionssikkerhed: migrér til rigtigt login, krypterede secrets, EU-datacenter og eIDAS-integration.

---

## 🧰 Udvikling

Kør tests:

```powershell
vendor\bin\phpunit.bat
```

Stil kodekvalitet: PHPStan/phpcs konfigurationer findes i repoet.


---

## 🆕 Nyheder (okt 2025)

- TRIN 3 hooks-panel udvidet med:
	- Billetype (pris-fleksibilitet + togspecificitet) med AUTO og manuelle valg
	- Klasse og reserverede faciliteter (1./2. klasse, sæde/fri/couchette/sleeper) med AUTO-evidence og hurtige dropdowns
- OCR auto-detektioner gemmer evidens og confidence i `meta` under `_ticket_type_detection` og `_class_detection`.
- One-page flow åbner automatisk relevante sektioner (cykel, afbrydelse, klasse) ved detektion.


---

## 🔗 Links

- Flow (v2 – Live Client Service): `docs/flow_chart_v_2_live_client_service.md`
- Flow chart (PDF): `webroot/files/flow_chart_v_2_live_client_service.pdf`  
	Lokal URL: http://localhost:8765/files/flow_chart_v_2_live_client_service.pdf
- Groq API Cookbook (JSON-mode, vision): https://github.com/groq/groq-api-cookbook
- Tesseract dokumentation (CLI/PSM/kvalitet): https://tesseract-ocr.github.io/tessdoc/

