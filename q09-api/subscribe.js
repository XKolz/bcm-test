'use strict';
/**
 * Q9: send one newsletter signup to the provider without losing or duplicating it.
 *
 * `signup` is a row we saved in OUR database before calling the provider:
 *   { id, email, idempotencyKey, status, attempts }
 * The row is the source of truth: if anything fails, it stays in the table and
 * a background job calls this function again later. Nothing is lost.
 *
 * `api` is the provider client:
 *   api.subscribe(email, { idempotencyKey, timeoutMs }) -> { id }
 *   api.findByEmail(email) -> { id } | null
 * Errors carry `err.code`: 'TIMEOUT' | 'RATE_LIMITED' (+ retryAfterMs) | 'INVALID_EMAIL' | 'ALREADY_EXISTS' | 'SERVER_ERROR'.
 */
async function syncSignup(signup, { api, store, log, sleep, maxAttempts = 5 }) {
  if (signup.status === 'subscribed' || signup.status === 'rejected') return signup; // Done already.

  // Invalid email: reject before calling anyone; never retried.
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(signup.email)) {
    return store.update(signup.id, { status: 'rejected', reason: 'invalid_email_local' });
  }

  // True once a request may have reached the provider without a reply (also carried over from an earlier run)
  let uncertain = Boolean(signup.needsReconcile);

  const previousAttempts = signup.attempts ?? 0;
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    await store.update(signup.id, { attempts: previousAttempts + attempt, lastAttemptAt: new Date().toISOString() });
    try {
      // After a timeout, ask before sending again: the first request may have succeeded.
      if (uncertain) {
        const existing = await api.findByEmail(signup.email);
        if (existing) {
          log.info('signup.reconciled', { signupId: signup.id, providerId: existing.id, attempt });
          return store.update(signup.id, { status: 'subscribed', providerId: existing.id });
        }
      }
      // Same idempotency key on every attempt: a provider that supports it returns
      // the original result instead of creating a second record.
      const res = await api.subscribe(signup.email, { idempotencyKey: signup.idempotencyKey, timeoutMs: 5000 });
      log.info('signup.subscribed', { signupId: signup.id, providerId: res.id, attempt });
      return store.update(signup.id, { status: 'subscribed', providerId: res.id });
    } catch (err) {
      log.warn('signup.attempt_failed', { signupId: signup.id, attempt, code: err.code, status: err.status });
      switch (err.code) {
        case 'ALREADY_EXISTS': // Our earlier attempt (or another path) got there first: success.
          return store.update(signup.id, { status: 'subscribed', providerId: err.providerId ?? null });
        case 'INVALID_EMAIL': // Provider says no: permanent, do not retry.
          return store.update(signup.id, { status: 'rejected', reason: 'invalid_email_provider' });
        case 'TIMEOUT':
          uncertain = true;
          await sleep(backoff(attempt));
          break;
        case 'RATE_LIMITED': // Honour Retry-After when given, otherwise back off.
          await sleep(err.retryAfterMs ?? backoff(attempt));
          break;
        default: // 5xx / network: transient.
          await sleep(backoff(attempt));
      }
    }
  }
  // Out of attempts: keep it queued for the background job. Not lost, not duplicated.
  log.error('signup.deferred', { signupId: signup.id, attempts: maxAttempts });
  return store.update(signup.id, { status: 'pending_retry', needsReconcile: uncertain });
}

// Exponential backoff with jitter: ~0.5s, 1s, 2s, 4s… capped at 30s.
function backoff(attempt) {
  const base = Math.min(30000, 500 * 2 ** (attempt - 1));
  return Math.round(base / 2 + Math.random() * (base / 2));
}

module.exports = { syncSignup };
