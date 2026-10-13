# Q9 — Newsletter signup without losing or duplicating

Files: `subscribe.js` (the one function), `subscribe.test.js` (local mock provider + 8 tests).
Run: `node --test --test-reporter=spec q09-api/subscribe.test.js` (Node 18+, no packages). Result: 8 passed (`evidence/test-results.txt`).

## The approach in four lines

1. **Save first, send second.** The signup is written to our own table (`status = pending`, with a generated `idempotencyKey`) *before* calling the provider. If the call fails in any way, the row is still there and a background job retries it. That is what prevents loss.
2. **Same idempotency key on every retry.** Providers that support it (most do, via an `Idempotency-Key` header or similar) return the original result instead of creating a second record.
3. **After a timeout, look before re-sending.** A timeout means "unknown", not "failed". The next attempt first asks the provider whether the email is already subscribed. This prevents duplicates even with a provider that ignores idempotency keys. "Already exists" from the provider also counts as success.
4. **Classify errors:** timeout / 5xx → back off and retry; 429 → wait for `Retry-After` (or exponential backoff with jitter); invalid email (our check or the provider's 4xx) → `rejected`, never retried. After the last attempt the row is left as `pending_retry` for the job, flagged so the next run reconciles first.

## Handling each case

| Case | What happens | Test |
|---|---|---|
| Timeout after the provider accepted | Next attempt finds the subscriber by email and marks ours `subscribed`. One record. | ✔ (with and without provider idempotency support) |
| Rate limit | Waits `Retry-After` (2s in the mock), then succeeds. One record. | ✔ |
| Rate limited on every attempt | Row kept as `pending_retry`; the next job run succeeds. | ✔ |
| Invalid email | Rejected locally with no API call, or after the provider's 422 with no retry. | ✔ ✔ |
| Job runs twice on a finished signup | Nothing is sent again. | ✔ |

## What I store and log

- **Store (our signups table):** signup id, email (needed to do the job), idempotency key, status (`pending` / `subscribed` / `rejected` / `pending_retry`), attempt count, last attempt time, last error code, provider subscriber id, `needsReconcile` flag, consent timestamp and source (needed for data-protection compliance, e.g. NDPA/GDPR).
- **Log:** event name, signup id, attempt number, error code / HTTP status, provider id, duration. Enough to trace one signup end to end.
- **Do not log:** the email address or other personal data (logs are widely accessible and kept a long time; use the signup id instead, or a hash if correlation is needed), API keys / `Authorization` headers, full request or response bodies, IP addresses.

## Testing a retry after the provider has already accepted

The mock has an `accept_then_timeout` step: it saves the subscriber, then throws a timeout instead of replying. That reproduces the real failure exactly. The test asserts that our row ends up `subscribed`, the provider holds **one** record, and the second attempt was a lookup, not a second subscribe. I run it twice: once with a provider that honours idempotency keys and once with one that ignores them.

Against the real provider I would repeat it in their sandbox, using a test proxy (or a very short client timeout) that drops the response after the request is forwarded, then check the provider's list for that email.

## Not covered (would add in production)

Two workers picking up the same row at once: the job should claim rows atomically (`UPDATE … SET status='in_flight' WHERE id=? AND status IN ('pending','pending_retry')`) or use `SELECT … FOR UPDATE SKIP LOCKED`. And a cap / alert on rows that stay `pending_retry` too long.
