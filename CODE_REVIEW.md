# Part 2 — Code Review

## Verdict

**Request changes.** This endpoint performs an unauthenticated, caller-controlled financial mutation without authorization, validation, replay protection, an audit record, or transactional concurrency control. It should not be merged in its current form.

## Blocking findings

| Finding | Problem and risk | Recommended fix |
| --- | --- | --- |
| Mutation over `GET` | `GET /players/{player}/credit-bonus` changes wallet state. Browsers, crawlers, caches, link previewers and infrastructure may prefetch, cache or retry GET requests; a replay credits the wallet again. It also violates HTTP safe/idempotent semantics. | Use an authenticated mutation endpoint such as `POST /api/promo/claim`. Keep `GET` read-only and return an appropriate success status for the mutation. |
| No authentication | No authentication middleware or authenticated principal is shown. An anonymous caller may be able to credit a wallet. | Protect the route with Sanctum (or the project's established authentication) and reject unauthenticated calls with `401`. |
| IDOR / missing authorization | The client chooses `{player}`. Route model binding loads that player, but does not prove the caller may change that wallet. An attacker can substitute another player's ID. | Derive the player from `$request->user()` for a self-service claim. If this is an administrative action, require an explicit policy/ability and retain the actor in the audit record. |
| Client-controlled bonus amount | `amount` comes directly from the request. A caller can send `999999999`, a negative value, zero, or repeatedly choose any value they want. | Accept a promo/bonus identifier only. Resolve the amount from trusted, server-side promotion configuration. Never trust the browser to calculate a wallet mutation. |
| No validation or bounds | The code accepts missing values, strings, malformed numeric input, negative/zero values and excessively large values. PHP/database coercion can produce surprising results or overflow. | Use a Form Request for syntax validation and enforce domain and database constraints: valid promo format, positive configured amount, safe integer range and non-negative wallet balance. |
| Unsafe money representation | The type of `balance` and `amount` is not established. Floats/JavaScript numbers and implicit coercion are unsafe for financial values because of precision and rounding behavior. | Persist exact integer minor units (for example, cents in `BIGINT`) or an exact fixed-scale decimal. Format money only at API/UI boundaries. |
| Read-modify-write race | `$player->balance += ...; $player->save()` reads and later overwrites a value. Concurrent requests can lose another wallet update or apply inconsistent results. | In one database transaction, lock the wallet/player row with `lockForUpdate()`, validate the current state, create the ledger/claim record and update the exact integer balance. Use a consistent lock order. |
| No atomic transaction | A proper bonus flow needs at least a claim/audit write and a balance write. Without a transaction, partial state is possible: a balance can change without a durable explanation, or a claim can exist without its credit. | Commit the claim and balance mutation in the same database transaction; roll both back on failure. Convert expected database conflicts into stable domain errors. |
| No duplicate/replay protection | Every request credits again. Double clicks, retries or concurrent calls can produce duplicate bonuses. There is no claim identity, idempotency rule or database invariant. | Store a promo claim and enforce one successful consumption per player/promo with a database unique invariant. Treat both applied and revoked claims as consumed; rejected attempts must not consume the promo. |
| No audit trail | Only the aggregate balance is stored. There is no durable record of the promotion, amount, timestamp, status, actor, rejection or later revocation. This prevents reconciliation and incident investigation. | Record immutable claim/ledger data with submitted code, trusted amount, player, timestamps and `applied`/`rejected`/`revoked` status. Preserve the original amount for exact reversal. |
| No business model | The endpoint represents “credit arbitrary amount” rather than “claim an eligible promotion”. It cannot check existence, activation, expiry or prior use. | Model promotions server-side and validate existence, active state, expiry and player eligibility before crediting. Return distinct machine-readable business error codes. |
| Missing abuse controls | The route has no visible throttling and its operation is cheap to replay. This amplifies brute-force and wallet abuse. | Add a sensible authenticated rate limit in addition to authorization and database invariants. Rate limiting is not a substitute for correctness. |

## Frontend findings

- `axios.get` repeats the incorrect mutation-over-GET contract and sends both the target player ID and amount from mutable client state.
- The method has no `try/catch`, so API failures become unhandled rejections and the user receives no actionable message.
- There is no loading/disabled state, so repeated clicks can submit concurrent credits.
- The balance is replaced optimistically from a single response, but the UI has no recovery for `401`, validation, conflict or stale/concurrent wallet state.

The client should call a dedicated API service such as `promoApi.claim(code)`. The API should attach the authentication token centrally, while the component owns explicit idle/loading/success/error states and refreshes balance/history from the authoritative response. The client must send neither `player.id` nor a monetary amount.

## Required verification

Before approval I would require feature/integration tests for authentication, authorization/IDOR, invalid and negative inputs, exact credited amount, duplicate and concurrent claims, database uniqueness, rollback behavior, non-negative balances, audit persistence and frontend error/double-click states.

## Suggested safe contract

```http
POST /api/promo/claim
Authorization: Bearer <token>
Content-Type: application/json

{"code":"WELCOME10"}
```

The server should derive the player from the token, load the trusted promotion, perform the claim and wallet update atomically, and return the credited amount, updated balance and claim record.
