# Pixelforge webhook collection

Import `pixelforge-webhooks.postman_collection.json` into Postman.

## Setup

| Variable | Value |
| --- | --- |
| `app_url` | `http://localhost`, or your tunnel URL when testing real deliveries |
| `webhook_secret` | `PIXELFORGE_WEBHOOK_SECRET` from `.env` |
| `pixelforge_api_key` | your Pixelforge key (folder 0 only) |

Every webhook request is signed by a pre-request script using the same scheme
Pixelforge uses — HMAC-SHA256 over `<timestamp>.<raw body>`, sent as
`Pixelforge-Signature: t=…,v1=…`. The signature check is therefore exercised
for real, not bypassed. The script is verified byte-compatible with
`App\Services\Pixelforge\WebhookSignature`.

## What each folder proves

| Folder | What it checks |
| --- | --- |
| 0. Setup | Creates a `[slow]` generation (60–90s) and captures its id, leaving time to fire webhooks by hand |
| 1. Happy path | `processing` then `succeeded` in order |
| 2. Duplicate delivery | The **same event id** twice — the replay must not charge twice |
| 3. Out-of-order delivery | A late `processing` after `succeeded` must not undo the terminal state |
| 4. Failure | A failed generation costs the user nothing |
| 5. Signature rejection | Unsigned, tampered, and stale-timestamp deliveries must all be refused |
| 6. Fallback | No webhook at all — the reconciler must settle it by polling |

## Testing the fallback

This is the case the collection exists for:

1. Run **0 → Create a generation** (it uses `[slow]`).
2. Fire **no** webhooks.
3. Run `./vendor/bin/sail artisan generations:reconcile`, or wait for the scheduler.
4. The generation must reach `succeeded` with the correct actual cost, from polling alone.
5. Compare against **0 → Get the generation** and **0 → Usage report**: the stored
   provider cost must equal Pixelforge's `cost_usd` exactly.

## Note

Folder 2 pins the event id across two requests via a collection variable, so run
those requests **in order**. Folder 5's stale-timestamp request sets and then
clears `force_timestamp`; if you cancel it midway, clear that variable by hand.
