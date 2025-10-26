# Noosa Labs JD Analyzer — Agent Instructions (AGENTS.md)

This file guides agents contributing to this repository. It captures the agreed 3‑hour MVP scope, architecture, conventions, and operational notes so future changes stay consistent and fast to ship.

## Scope & Goals
- 3-hour MVP focused on JSON-only API (no UI).
- Compare a Job Description (PDF) to multiple Resumes (PDF) and rank candidates.
- Use Laravel 12 + Sail; Redis for queue; Postgres available (persistence optional for MVP).
- Parsing: spatie/pdf-to-text (Poppler `pdftotext` binary required).
- LLM: Prism client via an adapter; default to a Fake client for local/dev.
- One Job per Resume; optional Bus batching for progress tracking.

## Golden Path (High-Level)
1) Upload JD PDF (one per role).
2) Upload multiple Resume PDFs (one per candidate) for that JD.
3) Extract text via `spatie/pdf-to-text`, enqueue one job per resume to call LLM and compute fit.
4) Retrieve results sorted by `fit_score` as JSON; drill into a candidate’s detail.

Fallbacks:
- If `pdftotext` isn’t installed, return a meaningful error and document how to install it (see Setup).

## Architecture
- Controllers: thin orchestration for HTTP requests.
- Services: coordinate processes and core app logic.
- Actions: encapsulate discrete use cases (e.g., analyze candidate or rank list).
- Contracts: interfaces for external dependencies (LLM, PDF parser).
- Adapters: concrete implementations of the contracts (Prism client, Spatie PDF adapter).
- Jobs: one queue Job per resume to avoid request timeouts and scale up easily.

### Directory Layout
```
app/
 ├── Http/
 │   └── Controllers/
 │        └── CandidateFitController.php     (combined endpoint is fine)
 │
 ├── Services/
 │   ├── PdfParserService.php
 │   └── CandidateFitService.php
 │
 ├── Contracts/
 │   ├── PdfParserInterface.php
 │   └── LLMClientInterface.php
 │
 ├── Adapters/
 │   ├── PrismLLMClient.php          (real integration)
 │   └── SpatiePdfToTextAdapter.php  (spatie/pdf-to-text wrapper)
 │
 ├── Jobs/
 │   └── AnalyzeResumeJob.php
 │
 └── Providers/
     └── AppServiceProvider.php      (bindings via container)
```

Notes
- Keep Controllers very thin; move logic into Actions/Services.
- No DTOs for the MVP; return associative arrays with documented keys.
- Prefer dependency injection and interface bindings for swappable infra.

## Contracts (Interfaces)
- `PdfParserInterface::extractText(string $path): string`
- `LLMClientInterface::analyze(string $jobText, string $resumeText): array`

Return shape for `analyze()` (contractual array keys):
```
[
  'fit_score' => int (0..100),
  'strengths' => string[],
  'weaknesses' => string[],
  'summary'   => string,
  // Optional but recommended for explainability:
  'evidence'  => string[]
]
```

Guardrails
- Always clamp `fit_score` to 0–100.
- Default missing keys to safe values (e.g., empty arrays, empty string, score 0).

## Adapters
### PDF Parsing: Spatie
- Use `spatie/pdf-to-text` package.
- Ensure the Poppler `pdftotext` binary is installed in the Sail image/container.
- Escape paths and gracefully handle empty/failed outputs.

Example usage pattern (inside adapter):
```php
use Spatie\PdfToText\Pdf;

class SpatiePdfToTextAdapter implements PdfParserInterface {
    public function extractText(string $path): string
    {
        // Consider configuring binary path via config if needed
        return trim(Pdf::getText($path));
    }
}
```

### LLM Client: Prism
- Provide a `PrismLLMClient` that implements `LLMClientInterface`.
- For local/dev, default to `FakeLLMClient` returning deterministic data.
- Use an environment-driven driver switch.

Example contract usage pattern:
```php
class CandidateFitService {
    public function __construct(private LLMClientInterface $llm) {}

    public function evaluate(string $jobText, string $resumeText): array {
        $result = $this->llm->analyze($jobText, $resumeText);
        // Normalize and clamp
        $result['fit_score'] = max(0, min(100, (int)($result['fit_score'] ?? 0)));
        $result['strengths'] = array_values(array_map('strval', $result['strengths'] ?? []));
        $result['weaknesses'] = array_values(array_map('strval', $result['weaknesses'] ?? []));
        $result['summary'] = (string)($result['summary'] ?? '');
        $result['evidence'] = array_values(array_map('strval', $result['evidence'] ?? []));
        return $result;
    }
}
```

## Jobs
- `AnalyzeResumeJob` (queued): receives `jdText` and `resumePath` (or `resumeText`).
- Responsibility: parse resume text (if path) → call `CandidateFitService` → persist/emit result.
- Prefer passing `jdText` rather than re-parsing JD per job to save time.

Batching (optional but recommended):
- Use `Bus::batch([...AnalyzeResumeJob])` to get a `batch_id` for progress tracking.
- Aggregate results by batch ID (DB or cache). For pure MVP, cache in Redis by `batch:{id}` keys.

## Controllers & API
JSON-only API, three endpoints aligned to requirements (no batch status route):
- POST `/api/jds`
  - Upload a Job Description PDF (one per role).
  - Returns `{ jd_id }`.

- POST `/api/jds/{jd}/resumes`
  - Upload multiple Resume PDFs (one per candidate) for the given JD.
  - Validates PDF MIME, file size, and resume count (e.g., max 20).
  - Parses JD once; enqueues one job per resume.
  - Returns `{ jd_id, queued_count }` (and results immediately if using `sync`).

- GET `/api/jds/{jd}/candidates`
  - Returns all candidates for the selected JD, sorted by `fit_score` desc.
  - Each item includes `fit_score`, strengths, weaknesses, and summary.
  - Optional: `?candidateId=` to return a single candidate’s detail.

Alternative (sync/local):
- If `QUEUE_CONNECTION=sync`, return results directly from the `resumes` endpoint and you can skip the `GET /api/jds/{jd}/candidates` lookup.

## Validation
- Use FormRequest classes for clarity and reuse.
- Example rules:
  - `job_description`: `required|file|mimetypes:application/pdf,application/x-pdf|mimes:pdf|max:5120`
  - `resumes`: `required|array|min:1|max:20`
  - `resumes.*`: `required|file|mimetypes:application/pdf,application/x-pdf|mimes:pdf|max:5120`

## Config & Bindings
- Add config entries (e.g., `services.php`) for driver/bin configuration.
- Env vars:
  - `LLM_DRIVER=fake|prism` (default: `fake`)
  - `PRISM_API_KEY=...` (if using Prism)
  - `QUEUE_CONNECTION=redis` (or `sync` locally)

Bind in `AppServiceProvider::register()`:
```php
$this->app->bind(PdfParserInterface::class, SpatiePdfToTextAdapter::class);

$this->app->bind(LLMClientInterface::class, function ($app) {
    return match (config('llm.driver', env('LLM_DRIVER', 'fake'))) {
        'prism' => new PrismLLMClient(/* inject http client, key, etc */),
        default => new FakeLLMClient(),
    };
});
```

## LLM Prompting (JSON-Only)
Ask the LLM to return strict JSON with the target schema. If the response isn’t valid JSON, retry once with a stricter instruction. Keep prompts concise to save tokens.

System:
```
You compare a job description with a resume and output only strict JSON.
Return keys: fit_score (0-100 integer), strengths (array of strings), weaknesses (array of strings), summary (string), evidence (array of short strings).
Do not include commentary or markdown fences.
```

User (example):
```
JOB DESCRIPTION:\n{job_text}
---
RESUME:\n{resume_text}
```

## Error Handling & Observability
- Timeouts on LLM calls; return a safe result if timed out and log the error.
- Don’t log full documents; log only hashes or first ~500 chars for debugging.
- For PDF parsing failures (encrypted/scanned), surface a clear error.
- Clamp/normalize LLM outputs and validate required keys before use.

## Security Notes
- Enforce MIME and size limits; reject non-PDFs or encrypted PDFs.
- Never pass unsanitized file paths to shell; rely on spatie’s library (which calls the binary) and/or `escapeshellarg` if shell is used.
- Do not expose raw uploaded file paths in responses.

## Setup (Sail + Dev)
- Require the package: `composer require spatie/pdf-to-text`.
- Ensure Poppler tools are installed in the Sail container (e.g., in Dockerfile):
  - `apt-get update && apt-get install -y poppler-utils && rm -rf /var/lib/apt/lists/*`
- Run queue worker in dev: `php artisan queue:work` (or use the existing Sail process manager).
- Local env defaults:
  - `QUEUE_CONNECTION=redis` (or `sync` if you prefer immediate results)
  - `LLM_DRIVER=fake`
- Available aliases:
  - `sail` for ./vendor/sail
  - `sa` for ./vendor/sail artisan

## Docs Access (for agents)
- Use Context7 MCP to fetch up-to-date docs when needed:
  - Laravel 12: resolve and fetch via the `context7` tools.
  - Prism: resolve and fetch adapter/client docs as needed.
- Prefer official docs and stable APIs; avoid bleeding-edge changes for the MVP.

## Trade-offs & Future Work
- No DTOs to stay fast; arrays with normalization guardrails.
- Parsing limited to text-based PDFs (no OCR).
- No UI right now; pure JSON endpoints.
- Future: persistence layer for results (SQLite/Postgres), evidence highlighting, auth, caching, retries/backoff, prompt tuning, and simple frontend (Inertia/Blade).

---

# Laravel & PHP Guidelines for AI Code Assistants (merged from AGENTS1.md)

This file contains Laravel and PHP coding standards optimized for AI code assistants like Claude Code, GitHub Copilot, and Cursor. These guidelines are derived from Spatie's comprehensive Laravel & PHP standards.

## Core Laravel Principle

**Follow Laravel conventions first.** If Laravel has a documented way to do something, use it. Only deviate when you have a clear justification.

## PHP Standards

- Follow PSR-1, PSR-2, and PSR-12
- Use camelCase for non-public-facing strings
- Use short nullable notation: `?string` not `string|null`
- Always specify `void` return types when methods return nothing

## Class Structure
- Use typed properties, not docblocks:
- Constructor property promotion when all properties can be promoted:
- One trait per line:

## Type Declarations & Docblocks
- Use typed properties over docblocks
- Specify return types including `void`
- Use short nullable syntax: `?Type` not `Type|null`
- Document iterables with generics:
  ```php
  /** @return Collection<int, User> */
  public function getUsers(): Collection
  ```

### Docblock Rules
- Don't use docblocks for fully type-hinted methods (unless description needed)
- **Always import classnames in docblocks** - never use fully qualified names:
- Use one-line docblocks when possible: `/** @var string */`
- Most common type should be first in multi-type docblocks:
  ```php
  /** @var Collection|SomeWeirdVendor\Collection */
  ```
- If one parameter needs docblock, add docblocks for all parameters
- For iterables, always specify key and value types:
  ```php
  /**
   * @param array<int, MyObject> $myArray
   * @param int $typedArgument
   */
  function someFunction(array $myArray, int $typedArgument) {}
  ```
- Use array shape notation for fixed keys, put each key on it's own line:
  ```php
  /** @return array{
     first: SomeClass,
     second: SomeClass
  } */
  ```

## Control Flow
- **Happy path last**: Handle error conditions first, success case last
- **Avoid else**: Use early returns instead of nested conditions
- **Separate conditions**: Prefer multiple if statements over compound conditions
- **Always use curly brackets** even for single statements
- **Ternary operators**: Each part on own line unless very short

```php
// Happy path last
if (! $user) {
    return null;
}

if (! $user->isActive()) {
    return null;
}

// Process active user...

// Short ternary
$name = $isFoo ? 'foo' : 'bar';

// Multi-line ternary
$result = $object instanceof Model ?
    $object->name :
    'A default value';

// Ternary instead of else
$condition
    ? $this->doSomething()
    : $this->doSomethingElse();
```

## Laravel Conventions

### Routes
- URLs: kebab-case (`/open-source`)
- Route names: camelCase (`->name('openSource')`)
- Parameters: camelCase (`{userId}`)
- Use tuple notation: `[Controller::class, 'method']`

### Controllers
- Plural resource names (`PostsController`)
- Stick to CRUD methods (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`)
- Extract new controllers for non-CRUD actions

### Configuration
- Files: kebab-case (`pdf-generator.php`)
- Keys: snake_case (`chrome_path`)
- Add service configs to `config/services.php`, don't create new files
- Use `config()` helper, avoid `env()` outside config files

### Artisan Commands
- Names: kebab-case (`delete-old-records`)
- Always provide feedback (`$this->comment('All ok!')`)
- Show progress for loops, summary at end
- Put output BEFORE processing item (easier debugging):
  ```php
  $items->each(function(Item $item) {
      $this->info("Processing item id `{$item->id}`...");
      $this->processItem($item);
  });

  $this->comment("Processed {$items->count()} items.");
  ```

## Strings & Formatting

- **String interpolation** over concatenation

## Enums

- Use PascalCase for enum values

## Comments

- **Avoid comments** - write expressive code instead
- When needed, use proper formatting:
  ```php
  // Single line with space after //

  /*
   * Multi-line blocks start with single *
   */
  ```
- Refactor comments into descriptive function names

## Whitespace

- Add blank lines between statements for readability
- Exception: sequences of equivalent single-line operations
- No extra empty lines between `{}` brackets
- Let code "breathe" - avoid cramped formatting

## Validation

- Use array notation for multiple rules (easier for custom rule classes):
  ```php
  public function rules() {
      return [
          'email' => ['required', 'email'],
      ];
  }
  ```
- Custom validation rules use snake_case:
  ```php
  Validator::extend('organisation_type', function ($attribute, $value) {
      return OrganisationType::isValid($value);
  });
  ```

## Blade Templates

- Indent with 4 spaces
- No spaces after control structures:
  ```blade
  @if($condition)
      Something
  @endif
  ```

## Authorization

- Policies use camelCase: `Gate::define('editPost', ...)`
- Use CRUD words, but `view` instead of `show`

## Translations

- Use `trans()` function over `@lang`

## API Routing

- Use plural resource names: `/errors`
- Use kebab-case: `/error-occurrences`
- Limit deep nesting for simplicity:
  ```
  /error-occurrences/1
  /errors/1/occurrences
  ```

## Testing

- Keep test classes in same file when possible
- Use descriptive test method names
- Follow the arrange-act-assert pattern

## Quick Reference

### Naming Conventions
- Classes: PascalCase (`UserController`, `OrderStatus`)
- Methods/Variables: camelCase (`getUserName`, `$firstName`)
- Routes: kebab-case (`/open-source`, `/user-profile`)
- Config files: kebab-case (`pdf-generator.php`)
- Config keys: snake_case (`chrome_path`)
- Artisan commands: kebab-case (`php artisan delete-old-records`)

### File Structure
- Controllers: plural resource name + `Controller` (`PostsController`)
- Views: camelCase (`openSource.blade.php`)
- Jobs: action-based (`CreateUser`, `SendEmailNotification`)
- Events: tense-based (`UserRegistering`, `UserRegistered`)
- Listeners: action + `Listener` suffix (`SendInvitationMailListener`)
- Commands: action + `Command` suffix (`PublishScheduledPostsCommand`)
- Mailables: purpose + `Mail` suffix (`AccountActivatedMail`)
- Resources/Transformers: plural + `Resource`/`Transformer` (`UsersResource`)
- Enums: descriptive name, no prefix (`OrderStatus`, `BookingType`)

### Migrations
- Do not write down methods in migrations, only up methods

### Code Quality Reminders

#### PHP
- Use typed properties over docblocks
- Prefer early returns over nested if/else
- Use constructor property promotion when all properties can be promoted
- Avoid `else` statements when possible
- Use string interpolation over concatenation
- Always use curly braces for control structures

---

These guidelines are maintained by Spatie and optimized for AI code assistants; merged here for single-file discoverability.
