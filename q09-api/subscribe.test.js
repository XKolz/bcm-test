'use strict';
// Run: node --test q09-api/   (Node 18+, no dependencies)
const test = require('node:test');
const assert = require('node:assert');
const { syncSignup } = require('./subscribe');

/** Local mock of the newsletter provider. `script` lists what each subscribe call does. */
function mockProvider({ script = [], honoursIdempotency = true } = {}) {
  const records = new Map(); // email -> { id }
  const byKey = new Map(); // idempotencyKey -> { id }
  const calls = [];
  const created = []; // every record the provider ever created
  let n = 0;
  return {
    records, calls, created,
    async findByEmail(email) { calls.push('find'); return records.get(email) ?? null; },
    async subscribe(email, { idempotencyKey }) {
      calls.push('subscribe');
      const step = script[n++] ?? 'ok';
      if (step === 'rate_limit') throw Object.assign(new Error('429'), { code: 'RATE_LIMITED', status: 429, retryAfterMs: 2000 });
      if (step === 'invalid') throw Object.assign(new Error('422'), { code: 'INVALID_EMAIL', status: 422 });
      if (honoursIdempotency && byKey.has(idempotencyKey)) return byKey.get(idempotencyKey);
      // Otherwise it creates a record, even if this email already has one (a duplicate).
      const rec = { id: `sub_${created.length + 1}` };
      created.push({ email, ...rec });
      if (!records.has(email)) records.set(email, rec);
      byKey.set(idempotencyKey, rec);
      // "accept_then_timeout": the provider stored it, but our side never sees the reply.
      if (step === 'accept_then_timeout') throw Object.assign(new Error('timeout'), { code: 'TIMEOUT' });
      return rec;
    },
  };
}

function harness(signupOverrides = {}) {
  const row = { id: 'su_1', email: 'ada@example.com', idempotencyKey: 'key-su_1', status: 'pending', attempts: 0, ...signupOverrides };
  const lines = [];
  const store = { async update(id, patch) { Object.assign(row, patch); return row; } };
  const log = Object.fromEntries(['info', 'warn', 'error'].map((l) => [l, (event, data) => lines.push({ l, event, ...data })]));
  const sleeps = [];
  const sleep = async (ms) => { sleeps.push(ms); }; // no real waiting in tests
  return { row, store, log, lines, sleeps, sleep };
}

test('timeout AFTER the provider accepted: retry finds it, exactly one record', async () => {
  const api = mockProvider({ script: ['accept_then_timeout'] });
  const h = harness();
  await syncSignup(h.row, { api, ...h });
  assert.equal(h.row.status, 'subscribed');
  assert.equal(h.row.providerId, 'sub_1');
  assert.equal(api.created.length, 1, 'one record at the provider');
  assert.deepEqual(api.calls, ['subscribe', 'find'], 'second attempt looked up instead of re-sending');
});

test('same case with a provider that IGNORES idempotency keys: still no duplicate', async () => {
  const api = mockProvider({ script: ['accept_then_timeout'], honoursIdempotency: false });
  const h = harness();
  await syncSignup(h.row, { api, ...h });
  assert.equal(h.row.status, 'subscribed');
  assert.equal(api.created.length, 1, 'no duplicate created');
});

test('rate limit twice, then success: honours Retry-After, one record', async () => {
  const api = mockProvider({ script: ['rate_limit', 'rate_limit', 'ok'] });
  const h = harness();
  await syncSignup(h.row, { api, ...h });
  assert.equal(h.row.status, 'subscribed');
  assert.deepEqual(h.sleeps, [2000, 2000], 'waited Retry-After each time');
  assert.equal(api.created.length, 1);
});

test('rate limited on every attempt: kept as pending_retry, nothing lost', async () => {
  const api = mockProvider({ script: Array(10).fill('rate_limit') });
  const h = harness();
  await syncSignup(h.row, { api, ...h, maxAttempts: 3 });
  assert.equal(h.row.status, 'pending_retry');
  assert.equal(api.created.length, 0);
  // The background job runs it again later and it succeeds once:
  const later = mockProvider();
  await syncSignup(h.row, { api: later, ...h });
  assert.equal(h.row.status, 'subscribed');
  assert.equal(later.created.length, 1);
});

test('invalid email (local check): never sent, never retried', async () => {
  const api = mockProvider();
  const h = harness({ email: 'not-an-email' });
  await syncSignup(h.row, { api, ...h });
  assert.equal(h.row.status, 'rejected');
  assert.deepEqual(api.calls, []);
});

test('invalid email (provider says 422): rejected after one call, no retry', async () => {
  const api = mockProvider({ script: ['invalid'] });
  const h = harness({ email: 'typo@exmaple.con' });
  await syncSignup(h.row, { api, ...h });
  assert.equal(h.row.status, 'rejected');
  assert.deepEqual(api.calls, ['subscribe']);
});

test('running the job twice on a finished signup does nothing', async () => {
  const api = mockProvider();
  const h = harness();
  await syncSignup(h.row, { api, ...h });
  await syncSignup(h.row, { api, ...h });
  assert.equal(api.calls.length, 1);
  assert.equal(api.created.length, 1);
});

test('logs never contain the email address', async () => {
  const api = mockProvider({ script: ['accept_then_timeout'] });
  const h = harness();
  await syncSignup(h.row, { api, ...h });
  assert.ok(h.lines.length > 0);
  assert.ok(!JSON.stringify(h.lines).includes('ada@example.com'));
});
