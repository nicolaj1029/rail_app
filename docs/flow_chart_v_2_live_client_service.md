## Flow – v2 (Live Client Service)

Denne side beskriver den operative flow/“trin for trin” der matcher filen `webroot/files/flow_chart_v_2_live_client_service.pdf`, med markering af hvor Groq (LLM + Vision OCR) indgår.

- PDF (flow chart):
  - Lokal fil: `webroot/files/flow_chart_v_2_live_client_service.pdf`
  - URL (lokal server): http://localhost:8765/files/flow_chart_v_2_live_client_service.pdf

### 1) Upload → Indlæsning
- Input: billede (PNG/JPG), PDF, PKPass eller screendump.
- Controller: `src/Controller/FlowController.php` (upload-handling, reset af felter).

### 2) OCR-ekstraktion (Vision-first)
- Vision OCR (Groq): `src/Service/Ocr/LlmVisionOcr.php`
  - Bruger OpenAI-kompatibel Chat Completions med `image_url` (data-URI) for at udtrække ren tekst fra billede.
  - Model (eksempel): `meta-llama/llama-4-scout-17b-16e-instruct`.
  - Aktiveres når `LLM_VISION_ENABLED=1` og `LLM_VISION_PRIORITY=first` i `config/.env`.
- Tesseract fallback: opskalering, sprog-inferens (eng+fra osv.), PSM/opts (konfigurerbart via env).

### 3) Heuristikker → Første feltafledning
- Service: `src/Service/TicketExtraction/HeuristicsExtractor.php`
- Formål: Hurtige regex/regler for operatør, produkt, dato/tid, PNR mv.

### 4) LLM-struktureret udtræk (JSON-mode)
- Service: `src/Service/TicketExtraction/LlmExtractor.php`
  - Base URL normaliseres til `https://api.groq.com/openai/v1` (når provider=groq).
  - JSON-mode håndhæves: `response_format = { type: "json_object" }` og `temperature=0`.
  - Robust fallback-parse hvis model returnerer ekstra tekst.
  - Model (eksempel): `openai/gpt-oss-120b`.

### 5) Broker/merge → Endelige felter
- Orkestrering: `src/Service/TicketExtraction/ExtractorBroker.php`
- Kombinerer heuristikker + LLM, vælger højeste dækning/tillid, og udfylder meta/logs.

### 6) Regler & berigelse
- RNE/operatør-API-stubs → senere udskiftning med live-API’er.
- Validering mod EU 2021/782 og CIV.

### 7) Formular 3.1–3.3 autofyld
- Felter med høj tillid låses; øvrige markeres og vises som mikro-prompts.
- PDF-udfyldning (FPDI) til officielle formularer.

### 8) Fuldmagt, eID, udbetaling
- eIDAS-kompatible trin (skitseret) → produktion: dedikeret gateway.

---

## Konfiguration (Groq + Vision)

Rediger `config/.env`:

- `LLM_PROVIDER=groq`
- `OPENAI_BASE_URL=https://api.groq.com`  (normaliseres til `/openai/v1`)
- `OPENAI_MODEL=openai/gpt-oss-120b` (tekst/JSON)
- `VISION_MODEL=meta-llama/llama-4-scout-17b-16e-instruct` (vision)
- `OPENAI_API_KEY=...` (Groq API nøgle)
- `LLM_VISION_ENABLED=1`
- `LLM_VISION_PRIORITY=first`
- `LLM_FORCE_JSON=1`

Se også: `config/bootstrap.php` (indlæser `.env` ubetinget under WAMP).

## Referencer

- Groq API Cookbook (eksempler): https://github.com/groq/groq-api-cookbook
- Tesseract dokumentation (CLI, PSM, kvalitet): https://tesseract-ocr.github.io/tessdoc/

## Groq – hurtig reference

- Base URL: `https://api.groq.com/openai/v1`
- Headers: `Authorization: Bearer <API_KEY>`, `Content-Type: application/json`
- JSON-mode: `response_format: {"type":"json_object"}`, `temperature: 0`
- Vision: `messages[].content = [{type:"text", text:"…"}, {type:"image_url", image_url:{url:"data:image/jpeg;base64,..."}}]`
- Multi-sprog: giv inputteksten direkte (OCR) – modellerne håndterer italiensk/engelsk/fransk/tysk; i prompten kan man skrive “labels may be in Italian (Partenza/Arrivo)”.
