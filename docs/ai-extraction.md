# Hybrid Ticket Extraction (Heuristics + AI)

This app can combine fast heuristics with optional AI-based extraction when formats vary (DB, SNCF, Eurostar, etc.).

## Pipeline
1. OCR text is normalized and sent to the Heuristics extractor (regex/labels).
2. If confidence ≥ threshold (default 0.66), we keep these fields.
3. If confidence < threshold, the broker can escalate to an AI extractor:
   - LLM (structured extraction prompt)
   - Azure Document Intelligence (prebuilt/document layout)

## Why hybrid?
- Heuristics are fast, cheap, and great for known layouts.
- AI handles long-tail layouts and noisy OCR.
- Confidence gating avoids unnecessary AI calls.

## Configuration
- By default, the broker includes Heuristics and an LLM extractor. The LLM extractor only runs if environment variables are set; otherwise it returns a low-confidence no-op.
- Enable the LLM extractor by setting the following environment variables (e.g., in `config/app_local.php` via `env()` or in your shell):

Environment variables
- LLM_PROVIDER: one of `openai`, `azure`, or `groq`. If unset/disabled, the LLM won’t be called.
- OPENAI_API_KEY: your API key (OpenAI or Azure OpenAI)
- OPENAI_BASE_URL:
  - For OpenAI: https://api.openai.com/v1
  - For Azure OpenAI: https://<your-resource>.openai.azure.com
  - For Groq (OpenAI-compatible): https://api.groq.com (normalized to /openai/v1 automatically)
- OPENAI_MODEL:
  - OpenAI: e.g., gpt-4o-mini
  - Azure OpenAI: the Deployment name you created (not the base model name)
  - Groq: e.g., llama-3.1-70b-versatile, mixtral-8x7b-32768
- OPENAI_API_VERSION (Azure only): e.g., 2024-08-01-preview
- LLM_TIMEOUT_SECONDS (optional): default 15
 - USE_LLM_STRUCTURING (optional): set to 1 to let an LLM structure journey segments when the parser finds none.
 - LLM_VERIFY_SEGMENTS (optional): set to 1 to also verify/augment segments even when some were found (or pass ?segments_verify=1 in the URL for a one-off run on TRIN 3).

Windows PowerShell examples
```powershell
# OpenAI
$env:LLM_PROVIDER = "openai"
$env:OPENAI_API_KEY = "sk-..."
$env:OPENAI_BASE_URL = "https://api.openai.com/v1"
$env:OPENAI_MODEL = "gpt-4o-mini"

# Azure OpenAI
$env:LLM_PROVIDER = "azure"
$env:OPENAI_API_KEY = "<your-azure-openai-key>"
$env:OPENAI_BASE_URL = "https://<your-resource>.openai.azure.com"
$env:OPENAI_MODEL = "<your-deployment-name>"
$env:OPENAI_API_VERSION = "2024-08-01-preview"

# Groq (OpenAI-compatible)
$env:LLM_PROVIDER = "groq"
$env:OPENAI_API_KEY = "gsk-..."
$env:OPENAI_BASE_URL = "https://api.groq.com"  # app normalizes to /openai/v1
$env:OPENAI_MODEL = "llama-3.1-70b-versatile"  # or "mixtral-8x7b-32768"
$env:USE_LLM_STRUCTURING = "1"
# Optional: also verify/augment even when segments exist
$env:LLM_VERIFY_SEGMENTS = "1"
```

## Suggested field schema
- dep_station, arr_station, dep_date (YYYY-MM-DD), dep_time (HH:MM), arr_time, train_no, ticket_no, price, operator, product

## Minimal integration example
```php
use App\Service\TicketExtraction\{ExtractorBroker, HeuristicsExtractor, LlmExtractor};

$broker = new ExtractorBroker([
    new HeuristicsExtractor(),
  new LlmExtractor(), // real implementation is included and gated by env
], 0.66);

$res = $broker->run($ocrText);
// $res->fields, $res->confidence, $res->provider
```

## Security & cost
- Avoid sending PII to third parties without consent.
- Cache AI results per file hash to reduce cost.
- Log provider, latency, and confidence for monitoring.

## Notes
- The broker’s default confidence threshold is 0.66. If the heuristics result is below this, the LLM is tried (when enabled).
- The controller stores `extraction_provider` and `extraction_confidence` in the session meta; surface them in the hooks panel for QA if helpful.
