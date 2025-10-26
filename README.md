# Candidate <> Job Fit Analyzer

This is a 3-hour JSON-only MVP that compares a Job Description (PDF) to multiple Resumes (PDF) and ranks candidates by fit. It follows the architecture and conventions in AGENTS.md and ships a simple, reliable Golden Path for recruiters.

- Upload one JD PDF per role
- Upload multiple Resume PDFs per JD
- Extract text with pdftotext, analyze with an LLM, and compute a 0–100 fit score
- Retrieve candidates sorted by priority, with strengths/weaknesses, summary, and evidence


## API Base URL: `http://localhost:8000/api/v1`

## My Thought Process

- Broke the problem into three steps: upload → analyze → display.
- Extracted text from PDFs using a parser service and specialized lib.
- Compared each resume to the job description using an LLM to get a fit score and insights.
- Returned all candidates sorted by fit score in a simple table.
- Focused on clarity, simplicity and modular design (controllers + services + interfaces).

## Tech Stack
- Laravel 12 + Sail (Dockerized dev)
- Redis (queues), Postgres (persistence)
- PDF parsing: `spatie/pdf-to-text` (Poppler `pdftotext` binary)
- LLM via Prism adapter; local/dev can use Fake client
- Token auth via Laravel Sanctum (required for Candidate Fit routes)


## Local Setup (Sail)
1) Prereqs: PHP 8+ and Docker.
2) Copy env and adjust as needed: `cp .env.example .env`
3) Start services: `./vendor/bin/sail up -d` (if vendor not installed yet, run `composer install` first)
5) Generate app key: `./vendor/bin/sail artisan key:generate`
6) Migrate + seed (adds a test user): `./vendor/bin/sail artisan migrate --seed`
7) Queue worker (required if `QUEUE_CONNECTION=redis`): `./vendor/bin/sail artisan queue:work`

The API is served on `http://localhost:8000` (from `.env` APP_PORT). Redis and Postgres run in the compose stack.

## Notes on pdftotext
- The Sail PHP image installs Poppler (`poppler-utils`), so `pdftotext` is available in dev.
- If running outside Sail:
  - macOS: `brew install poppler`
  - Ubuntu/Debian: `sudo apt-get update && sudo apt-get install -y poppler-utils`
  - Windows: install Poppler for Windows and add to PATH


## Environment
- `QUEUE_CONNECTION=redis` (or `sync` for synchronous local runs)
- `LLM_DRIVER=fake|prism` (use `fake` for deterministic local testing)
- If `LLM_DRIVER=prism`, set the provider and key (see `config/prism.php`):
  - Gemini: `GEMINI_API_KEY=...` and (optional) `LLM_MODEL=gemini-2.5-flash`
  - Other providers supported by Prism are configurable as needed


## API Reference
- POST `/jds`
  - multipart/form-data with:
    - `title` string (unique per JD)
    - `job_description` file (PDF)
  - Validates: PDF mime + size 5 MB
  - Returns: `{ jd_id, title }`
  - Code: `app/Http/Controllers/CandidateFitController.php:16`

- POST `/jds/{jd}/resumes`
  - multipart/form-data with:
    - `resumes[]` files (PDF), 1..20
  - Parses JD once, enqueues one `AnalyzeResumeJob` per resume (Bus batch)
  - Returns: `{ jd_id, queued_count, batch_id }`
  - Code: `app/Http/Controllers/CandidateFitController.php:36`, `app/Jobs/AnalyzeResumeJob.php:1`

- GET `/jds/{jd}/candidates`
  - Query: optional `candidateId` to fetch one
  - Returns candidates for the JD sorted by `fit_score` desc
  - Code: `app/Http/Controllers/CandidateFitController.php:61`

## Auth (optional)
- Endpoints under `/api/v1/auth` use Sanctum tokens.
- Seeded default user: `test@test.com` / `password` (from `database/seeders/UserSeeder.php:8`)
- Obtain a token: `POST /api/v1/auth/login { email, password }`
- Candidate Fit routes under `/api/v1/jds` require authentication.


## Postman Collection
- File: `jd-analyzer.postman_collection.json`
- Import into Postman and create a Local environment with:
  - `base_url`: `http://localhost:8000` (do not include `/api/v1`)
  - `token`: leave empty (login/refresh scripts will set it automatically)
- Included requests: `auth/login`, `auth/me`, `auth/logout`, `auth/refresh`.
- Test scripts have been translated to English and will:
  - Save `access_token` to the environment variable `token` on successful responses.
  - Log clear messages on success, missing fields, or non-200 responses.
- Usage tips:
  - Update the `login` request body to match your local credentials (the seeded user is `test@test.com` / `password`).
  - Subsequent `me` and `refresh` requests use `{{token}}` automatically.
  - The `logout` request in the collection uses `{{auth_token}}`; either change it to `{{token}}` in Postman or set an `auth_token` env var equal to `token`.
  - Set `{{base_url}}` to your server base (e.g., `http://localhost:8000`). The collection appends `/api/v1/...` paths automatically.


## LLM Usage
- Contract: `App\Contracts\LLMClientInterface::analyze(string $jobText, string $resumeText): array`
- Default local option: `FakeLLMClient` (deterministic token-overlap heuristic).
- Real adapter: `PrismLLMClient` using Prism structured output with an explicit JSON schema.
- Driver binding: `app/Providers/AppServiceProvider.php:14` (switch via `config('llm.driver')`).
- Output is normalized and clamped in `app/Services/CandidateFitService.php:11`.

### Return shape (normalized)
```
{
  "fit_score": 0..100,
  "strengths": string[],
  "weaknesses": string[],
  "summary": string,
  "evidence": string[]
}
```


## Scoring & Explainability
- fit_score: 0–100, clamped.
- strengths/weaknesses: short bullet points surfaced by the LLM (or heuristic fallback).
- evidence: keyword/phrase evidence used to justify the score.
- Normalization Guardrails: Keys are defaulted to safe empty values and coerced to expected types.


## Architecture Overview
- Controllers: thin orchestration. See `app/Http/Controllers/CandidateFitController.php`.
- Services: core app logic. See `app/Services/CandidateFitService.php`, `app/Services/PdfParserService.php`.
- Contracts & Adapters: swappable infra for LLM and PDF. See `app/Contracts/*`, `app/Adapters/*`.
- Jobs: one queue job per resume. See `app/Jobs/AnalyzeResumeJob.php`.
- IoC Bindings: see `app/Providers/AppServiceProvider.php`.


## Validation
- JD upload: `title` required/unique, `job_description` must be a PDF (<= 5 MB).
- Resumes upload: `resumes` 1..20 files; each must be a PDF (<= 5 MB).
- See: `app/Http/Requests/StoreJobDescriptionRequest.php`, `app/Http/Requests/StoreResumesRequest.php`.


## Example cURL Flow
1) Upload JD
```
curl -X POST http://localhost:8000/api/v1/jds \
  -H "Authorization: Bearer <TOKEN>" \
  -F "title=Senior PHP Engineer" \
  -F "job_description=@/path/to/jd.pdf"
```
Response: `{ "jd_id": "<uuid>", "title": "Senior PHP Engineer" }`

2) Upload resumes for that JD
```
curl -X POST http://localhost:8000/api/v1/jds/<jd_id>/resumes \
  -H "Authorization: Bearer <TOKEN>" \
  -F "resumes[]=@/path/to/resume1.pdf" \
  -F "resumes[]=@/path/to/resume2.pdf"
```
Response: `{ "jd_id": "<uuid>", "queued_count": 2, "batch_id": "<id>" }`

3) List candidates for a JD (sorted)
```
curl -H "Authorization: Bearer <TOKEN>" \
  http://localhost:8000/api/v1/jds/<jd_id>/candidates
```
Optional detail: `curl "http://localhost:8000/api/v1/jds/<jd_id>/candidates?candidateId=<uuid>"`


## Tech Decisions & Trade-offs
- Timeboxed ~3 hours: focused on JSON API and core flow; no UI.
- PDF parsing via `spatie/pdf-to-text` (no OCR). Clear error if `pdftotext` missing.
- One queue job per resume to avoid request timeouts and scale horizontally.
- LLM via Prism with strict schema; Fake client available for deterministic, offline runs.
- Persistence with Postgres to retrieve results anytime; Redis queues for high throughput.
- Controllers and models intentionally thin; logic lives in services/jobs for clarity and testability.


## Security & Reliability
- Strict MIME/size validation for uploads; encrypted/unreadable PDFs fail with clear error.
- No raw upload paths exposed in responses; sensitive fields hidden on models (`stored_path`, `resume_text`).
- Early-return style and normalization guardrails on LLM outputs to prevent crashes.
- Logs redact full documents (see adapters/services).
- Rate limiter to avoid many requests.


## What I’d Improve With More Time
- OCR for scanned PDFs (e.g., [Tesseract](https://github.com/tesseract-ocr/tesseract)) for image PDFs.
- Batch progress endpoint and retry/backoff tuning; per-candidate status.
- Improve business logic by saving the resume and candidate data once and reusing it for different JDs. As the timebox was short, and this is just a POC, i've done it in the simplest way possible.
- Prompt tuning. I just made the simplest prompt I could.
- Robust persistence and caching (e.g., per-JD cache of parsed text; idempotent uploads). This would avoid hits on the LLMs, reducing costs.
- Improve decoupling by creating Actions or more specialized services.
- Improve tests, i just make the simplest tests I could, I would need to test all possible paths and improve test coverage.
- Create specialized JsonResponses to manage returned data more efficiently.
- Implement Github Actions or any other CI/CD pipeline.
- Use AWS S3 and AWS ECS/Fargate to store and deploy the app.
- Improve exception handling and standardize message returns by using trans() function and custom messages on bootstrap/app.php ->withExceptions() method.
- Creating an observability middleware to deal with Logs/Tracing and Metrics using Open Telemetry
- Install PHPStan to ensure type safety. I didn’t go deeper since setting up complex type assertions would be overkill for this short time frame.

## Deployment
- For local demo, `sail up -d` from this repo is sufficient.

## File Pointers (key code)
- Controller: `app/Http/Controllers/CandidateFitController.php`
- Job: `app/Jobs/AnalyzeResumeJob.php`
- IoC bindings: `app/Providers/AppServiceProvider.php`
- LLM adapter(s): `app/Adapters/PrismLLMClient.php`, `app/Adapters/FakeLLMClient.php`
- PDF adapter: `app/Adapters/SpatiePdfToTextAdapter.php`
- Services: `app/Services/CandidateFitService.php`, `app/Services/PdfParserService.php`
