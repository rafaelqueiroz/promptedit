# Implementation plan — Pixelforge image generation

Source: [assessment.md](assessment.md). Priority order from the brief: **correct money → robustness → UX → code quality**. The required part is sized at 2–4 h; stretch goals come after it works end to end.

Items marked **[confirm]** depend on details in the Pixelforge API docs and must be checked before coding that step.

## Architecture at a glance

```
Vue (Inertia)                 Laravel                              Pixelforge
──────────────                ───────────────────────────────      ──────────
Create page ──estimate──▶ PricingCalculator (server is the source of truth)
            ──submit────▶ GenerationController@store
                            └─ Generation{queued} + SubmitGeneration job ──POST /v1/generations──▶
                                                                    ◀── accepted {provider_id}
History page ◀──usePoll── GenerationController@index
                          PixelforgeWebhookController ◀────────── webhook (signed, maybe dup / out of order)
                            └─ WebhookEvent (unique) → ApplyProviderResult (state machine + settlement)
                          generations:reconcile (scheduler) ──GET /v1/generations/{id}──▶  (fallback if a webhook never arrives)
```

One code path (`ApplyProviderResult`) applies a provider state to a generation, whether it came from a webhook or from polling. That is where idempotency and money are enforced, so there is exactly one place to get right.

## Phase 0 — Groundwork (~20 min)

- Pixelforge account; **two API keys**: `local` (webhook URL = tunnel) and `cloud` (webhook URL = Laravel Cloud app). One key per environment keeps `GET /v1/usage` comparable per deployment.
- `config/pixelforge.php` → `base_url`, `api_key`, `webhook_secret`, `max_reference_images`. Env: `PIXELFORGE_API_KEY`, `PIXELFORGE_WEBHOOK_SECRET`, `PIXELFORGE_BASE_URL`. **(done)**
- `config/pricing.php` → token rates per size/quality, reference-image rate, prompt rate, `markup => '1.25'`. **All as strings**, never floats. **[confirm]** the published rate table and its unit (per token / per 1M tokens).
- Laravel Cloud: add env vars, run **`php artisan horizon`** as a single background process (it manages the workers itself) and enable the **scheduler** — both are needed: submission runs in the queue, reconciliation on the schedule. **(Horizon installed 2026-11-16: Redis queues `generations` + `default`, dashboard gated on `is_admin`.)**
- Local: `sail up`, tunnel on port 80 (Expose/ngrok/cloudflared), paste the tunnel URL into the local key's webhook URL.

## Phase 1 — Money and pricing (~30 min, do this first)

Rule: no floats anywhere money is computed or stored.

- Postgres `numeric(14,8)` columns (exact; 8 decimals = 1/1,000,000 of a cent, ample for "fractions of a cent").
- `App\Pricing\Money` — immutable value object wrapping a decimal string; `add`, `multiply(int)`, `markedUp()` (rounds up), `equals` via **bcmath** at scale 8. Rates come from config as strings. **(done; rates in `config/pricing.php` are placeholders until the docs are in)**
- `App\Pricing\PricingCalculator`
    - `providerCost(Usage $usage, size, quality): Money` — `Σ tokens × rate` from the actual usage reported by Pixelforge. This must reproduce Pixelforge's own number exactly; it is what the admin report is compared against.
    - `userPrice(Money $providerCost): Money` — `cost × 1.25`, **rounded up** to 8 decimals (never rounds below our cost). Rounding policy documented in the class and covered by a test.
    - `estimate(size, quality, referenceImageCount, prompt): Estimate{provider, user}` — uses the known-upfront components plus the documented typical value for the components only known after completion. **[confirm]** which token counts are known upfront vs only on completion, and whether the docs give a typical/expected output-token count.
- Unit tests with hand-computed expected values (including a fraction-of-a-cent case and a rounding-boundary case).

## Phase 2 — Data model (~20 min)

`generations`

- `id` (ulid), `user_id`, `prompt`, `size`, `quality`, `reference_image_urls` (json)
- `status` enum `GenerationStatus { Queued, Processing, Succeeded, Failed }`
- `provider_generation_id` **unique, nullable** (null until Pixelforge accepts it)
- `estimated_provider_cost`, `estimated_user_price` (set at submit)
- `actual_provider_cost`, `actual_user_price` (nullable; set exactly once on `Succeeded`; `0` on `Failed`)
- `usage` json (raw token counts as reported), `image_url`, `error_code`, `error_message`
- `submitted_at`, `completed_at`, timestamps; index on `(user_id, created_at)`

`webhook_events`

- `provider_event_id` **unique**, `generation_id` (nullable fk), `type`, `payload` json, `received_at`, `processed_at` (nullable)
- Every delivery is inserted (insert-or-ignore on the unique key). "An event delivered twice must never be recorded twice" is enforced by the database, not by code paths. **[confirm]** the event id field name and whether redeliveries reuse the same id (they should; if Pixelforge uses a per-delivery id, dedupe on `(generation_id, type)` instead).

`credit_transactions` (ledger — also what the Balances stretch goal needs)

- `user_id`, `generation_id` (nullable), `type` enum `{ purchase, charge, hold, release }`, `amount` numeric signed, `idempotency_key` **unique** (e.g. `generation:{id}:charge`), `description`, timestamps
- The unique key is the second guarantee that a generation is never charged twice.

`users` → `is_admin` boolean (for the admin report and Horizon). **(done)**

## Phase 3 — Pixelforge client (~30 min)

`App\Services\Pixelforge\PixelforgeClient` using the `Http` facade with `baseUrl`, bearer token, `timeout`, `acceptJson`.

- `createGeneration(CreateGenerationRequest): AcceptedGeneration`
- `getGeneration(string $id): ProviderGeneration` (for reconciliation)
- `usage(from, to): array` (for the admin report)
- Typed DTOs for responses; a `PixelforgeException` hierarchy: `RateLimited(retryAfter)`, `Overloaded`, `ConcurrencyLimited`, `InvalidRequest`, `Unavailable`. **[confirm]** status codes / error codes for each, and the `Retry-After` header.
- `[fail]`, `[slow]`, `[duplicate]` prompt keywords are the manual test fixtures. **[confirm]** full list.
- **[confirm]** whether the API accepts an `Idempotency-Key` header or a client reference (metadata). If yes, send our generation ULID so a retried submit can never create a second paid generation.

## Phase 4 — Generation lifecycle (~45 min)

Submit

1. `StoreGenerationRequest` validates prompt, size, quality (from the config table), `reference_image_urls[]` as URLs (max N **[confirm]**).
2. Controller computes the estimate server-side, creates `Generation{Queued}` with the estimate, dispatches `SubmitGeneration` job, redirects to history with a toast.
3. `SubmitGeneration` job (queue `generations`): `tries` 5, `backoff` `[10, 30, 60, 120]` — set on the job, since Horizon's supervisor `tries` is 1 so it never imposes a second retry policy; honours `Retry-After` on `RateLimited`/`Overloaded`/`ConcurrencyLimited` via `release($seconds)`. On `InvalidRequest` → mark `Failed` immediately (no retry). On final failure → `Failed` with a readable error. On success → store `provider_generation_id`, `submitted_at`. Without an idempotency key, only retry errors that guarantee the request was not accepted (connection refused, 429, 503); a timeout after send is reported as failed-to-submit rather than retried blindly. Not trying to be clever here is the safe default; see open questions.

State machine (`GenerationStatus::canTransitionTo`)

- `Queued → Processing → Succeeded | Failed`; terminal states never change.
- Rank order is used to reject stale events: a `processing` event arriving after `succeeded` is recorded in `webhook_events` but does not touch the generation.

Settlement (`ApplyProviderResult` action) — runs inside a DB transaction with `lockForUpdate()` on the generation row:

- `Succeeded`: compute `actual_provider_cost` from the reported usage, `actual_user_price` from it, store `usage` + `image_url`, insert the `charge` ledger row with key `generation:{id}:charge`. If the row already exists (unique violation) → the event is a duplicate, nothing changes.
- `Failed`: `actual_* = 0`, store error, no ledger charge (release any hold once balances exist).
- The lock + the unique keys make concurrent duplicate webhooks safe even if they hit two workers at once.

Reconciliation

- `generations:reconcile` artisan command, scheduled every minute: for generations non-terminal for more than 2 minutes (**[confirm]** typical durations, `[slow]` behaviour), call `getGeneration` and pass the result through `ApplyProviderResult`. Covers lost webhooks with the same logic, no special cases.

## Phase 5 — Webhooks (~30 min)

- `POST /webhooks/pixelforge` → `PixelforgeWebhookController`. Registered outside `auth`; CSRF excluded in `bootstrap/app.php` (`validateCsrfTokens(except: ['webhooks/*'])`).
- `VerifyPixelforgeSignature` middleware: HMAC over the **raw** body with the signing secret, compared with `hash_equals`; reject with 401 on mismatch. **[confirm]** header name, algorithm, whether a timestamp is included (if so, reject beyond a tolerance window).
- Controller: insert the `WebhookEvent` (insert-or-ignore on `provider_event_id`); if it was already processed → `200` immediately; otherwise run `ApplyProviderResult` synchronously (small, fast, transactional), set `processed_at`, return `200`. On an exception return `500` so Pixelforge redelivers; the `processed_at = null` row means the redelivery is processed, not skipped.
- Unknown `provider_generation_id` (webhook raced ahead of our job storing the id): return `500` so it is redelivered; the row lookup will succeed on the retry. **[confirm]** retry schedule.

## Phase 6 — UI (~60 min)

Landing page **(done 2026-11-16)**: `Welcome.vue` puts the composer first with a live server-priced estimate; Generate as a guest opens `AuthDialog.vue` (same Fortify endpoints as the pages) and `saveDraft()` keeps the prompt so `generations/Create.vue` can restore it with `takeDraft()`. `PromptComposer.vue` is the shared composer for both pages; `PriceTable` builds the size × quality × reference-image price grid for either.

Navigation: sidebar items **Generate**, **History** (+ **Report** for admins). `/dashboard` redirects to history.

`generations/Create.vue`

- Prompt textarea, size and quality selects (options from the server), reference image URLs as a repeatable list.
- Estimate card updated on every change via an Inertia partial reload (`router.reload({ only: ['estimate'], data })`, debounced) so the number always comes from `PricingCalculator`. Copy: "Estimated $0.0125 · final price is known when the generation finishes · failed generations are free".
- Submit → history with toast "Generation queued".

`generations/Index.vue`

- Card per generation: image (or skeleton while pending, broken-image fallback once URLs expire), prompt, status badge (queued / processing / succeeded / failed), estimated vs actual price, relative time, error message on failure.
- `usePoll(3000)` while any visible generation is non-terminal; stops when all are terminal. Paginated.

Formatting: `formatMoney()` helper shows USD with 4 decimals (`$0.0125`) and the full 8-decimal value in a tooltip.

## Phase 7 — Tests (~40 min)

Feature tests (Pixelforge faked with `Http::fake`):

- Submit stores the estimate and dispatches the job; the job stores `provider_generation_id`.
- Rate-limited response → job released with `Retry-After`; invalid request → `Failed`, cost `0`.
- Webhook: bad signature → 401; valid → generation updated.
- **Duplicate webhook**: same event twice → one `webhook_events` row, one `charge` row, costs unchanged.
- **Out of order**: `succeeded` then `processing` → still `succeeded`, costs unchanged.
- **Failed** generation → `actual_user_price = 0`, no charge.
- Reconcile command applies a polled result through the same path.
- Pricing unit tests from Phase 1.

Run `sail composer test` (pint + phpstan + phpunit).

## Phase 8 — Deploy (~20 min)

- Laravel Cloud: env vars for the `cloud` key, queue worker, scheduler, `php artisan migrate` on deploy.
- Register the cloud webhook URL on the cloud key. Create a handful of generations, including a `[fail]` and a `[duplicate]`, so reviewers can compare with Pixelforge's records.

## Stretch A — Admin report

- `is_admin` users only; `/admin/report`.
- Per day and per user: `Σ actual_user_price` (credit spend), `Σ actual_provider_cost` (provider cost), profit = difference. Pure SQL `GROUP BY` on `generations` where `status = succeeded`.
- "Provider reported" column from `GET /v1/usage` for the same range, with the delta highlighted, so a mismatch is visible to us before it is visible to them.

## Stretch B — Balances

- Balance = `Σ credit_transactions.amount` (cached on `users.balance` and updated inside the same transaction as the ledger write).
- "Buy credits" button → `purchase` ledger row (fake, fixed amounts).
- Submit: refuse when `balance < estimated_user_price` (422 with a clear message); otherwise insert a `hold` for the estimate. On `Succeeded`: `release` the hold and `charge` the actual price. On `Failed`: `release` the hold. Each with a unique idempotency key.

## Open questions — resolve against the Pixelforge docs before Phase 1

1. Rate table: values, units, and which token types exist (prompt, reference image, output).
2. Which usage numbers are known at submission vs only at completion; any documented typical value for the estimate.
3. Webhook: signature header/algorithm, event id field, event types, retry schedule, ordering guarantees.
4. Error responses: codes for rate limit, concurrency limit, overload; `Retry-After`.
5. Idempotency key or client reference on `POST` generations.
6. `GET /v1/usage` shape (per day? per generation? cost or tokens?) — drives the admin report comparison.
7. Image URL lifetime.

## Decisions to defend in the interview

- Exact decimals (`numeric` + bcmath) instead of integer cents: rates are fractions of a cent and the unit is dictated by Pixelforge's rate table, not by us.
- Rounding up the user price: our markup never rounds below our own cost.
- Idempotency enforced by unique constraints (`webhook_events.provider_event_id`, `credit_transactions.idempotency_key`) plus a row lock, not by "check then insert".
- Webhooks and polling share one `ApplyProviderResult`; reconciliation is a cron, not a special case.
- Submission goes through the queue so rate limits and overloads are retried with backoff instead of failing the user's request.
- Polling the history page with `usePoll` instead of websockets: simpler, honest, good enough for this scale.
