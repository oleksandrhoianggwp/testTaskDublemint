# AI Prompt Log

This project was developed with Codex from **one comprehensive master prompt** supplied by the user. The implementation phases below describe autonomous planning, implementation, review and correction performed under that prompt; they are not presented as separate prompts that the user did not send.

## 1. Initial master prompt

### User instruction

The master prompt asked Codex to complete the technical assignment end-to-end in `testTaskDublemint`: inspect the environment, build a production-minded Laravel/Vue application, use PostgreSQL and Docker, authenticate with Sanctum, implement promo claim/history/revoke, protect financial invariants and ownership, create a polished responsive UI, test it, write the Part 2 review and AI log, document a clean-clone workflow, create real incremental Git history and push it to GitHub.

It explicitly prioritized exact money, transactionality, row locks, duplicate prevention, database constraints, auditability and IDOR protection. It also required truthful tool/test reporting and prohibited a single artificial final commit.

### Resulting approach

- Laravel conventions with Form Requests, API Resources, thin controllers and focused claim/revoke actions.
- Vue 3 Composition API, axios service modules, Tailwind CSS and accessible custom UI states.
- PostgreSQL integer minor units and database constraints rather than floating-point wallet storage.
- Docker Compose services for PHP-FPM, Nginx, PostgreSQL and Vite.
- Feature tests against PostgreSQL, plus lint, production build, formatter and live browser verification.

## 2. Bootstrap and environment phase

### Objective inherited from the master prompt

Create a cloneable project without overwriting useful repository state, and provide a Docker-first developer experience.

### Decisions and work

- Bootstrapped Laravel 13 and Vue 3/Vite in the existing repository.
- Added separate `app`, `nginx`, `postgres` and `node` services.
- Configured the application on `http://localhost:8090` because port 8080 was already occupied locally.
- Added safe Docker `.env.example` defaults, PostgreSQL health checking and a dedicated PostgreSQL test database.
- Kept `.env`, `vendor` and `node_modules` outside Git.

### Verification/corrections

- Verified container health and the Laravel `/up` endpoint.
- Added Docker context exclusions after identifying unnecessary OneDrive/scaffold content entering builds.
- Normalized the PHP entrypoint for cross-platform line endings and writable Laravel runtime directories.

## 3. Ticket 1 — promo claim and history

### Objective inherited from the master prompt

Implement authenticated promo claiming and paginated, filterable per-player history with explicit validation and business errors.

### Decisions and work

- Added Sanctum token login, current-user and logout endpoints with a seeded demo account.
- Added promo code and claim models, status enum, Form Requests and API Resources.
- Normalized submitted codes to uppercase and validated 6–12 ASCII letters/digits.
- Stored wallet and bonus values as integer minor units (`BIGINT`) and formatted them at the API boundary.
- Implemented `ClaimPromoAction` with a transaction, player row lock, server-side promo amount, eligibility checks and atomic claim/balance writes.
- Recorded valid-format business rejections (`not found`, `expired`, `inactive`, `already used`) without changing the wallet.
- Added a PostgreSQL partial unique index so applied **and revoked** claims consume a promo, while rejected attempts do not.
- Implemented newest-first history scoped to the authenticated user with status validation, pagination and bounded `per_page`.

### Verification/corrections

- Added tests for authentication, format validation, each business failure, exact credit, persisted claims, duplicate attempts, cross-player isolation and database invariants.
- Converted uniqueness conflicts into a stable `PROMO_ALREADY_USED` domain response instead of exposing SQL details.
- Confirmed rejected attempts leave balances unchanged and that a revoked promo cannot become reusable.

## 4. Ticket 2 — bonus revoke

### Objective inherited from the master prompt

Revoke a previously applied claim exactly once, only for its owner, subtract the original bonus safely and reject insufficient balances.

### Decisions and work

- Implemented `PATCH /api/promo/{claimId}/revoke` through `RevokePromoAction`.
- Scoped claim lookup to the authenticated user to prevent IDOR.
- Used the same lock order as claim operations: lock player first, then claim.
- Required `applied` status, preserved the original amount, set `revoked_at` and updated balance/status in one transaction.
- Returned `409` for already-revoked/non-revocable claims and insufficient wallet balance, with no mutation on failure.

### Verification/corrections

- Added tests for the successful amount/status/timestamp update, duplicate revoke, rejected-claim revoke, cross-player access and insufficient balance.
- Added database protection for non-negative balances and positive promo amounts.
- Confirmed a second revoke cannot debit the wallet again.

## 5. Frontend and design pass

### Objective inherited from the master prompt

Build an intentional betting/fintech-style Vue interface with complete interaction states and live balance/history updates.

### Decisions and work

- Created a responsive dark dashboard with login, player identity, prominent balance, claim form, history filters, status badges and pagination.
- Centralized Bearer-token attachment and `401` handling in the axios service.
- Added client validation, disabled/loading states and backend error messages.
- Added an accessible confirmation modal with the exact promo and deduction amount.
- Updated balance and claim status without a page reload after claim/revoke.

### Browser verification and corrections

- Exercised login, invalid format, expired promo, valid claim, modal confirmation and revoke in the running application.
- Checked desktop and a 390 px mobile viewport; the document had no horizontal overflow.
- Improved responsive table detail visibility and mobile record descriptions.
- Cleared stale claim-success feedback after a later revoke changed the balance.
- Removed a production font optimization warning and added consistent base rendering styles.
- Captured only running-application states in `docs/screenshots/`.

## 6. Additional user iterations

These are the separate follow-up prompts actually sent during development.

### Iteration 1 — commit granularity

> важливе уточнення. Кожен коміт під таску. АБо під самий step. Будь ласка не забувай про це, так як завдання не буде прийняте

**Response in the work:** implementation was split into real bootstrap, domain, API, test, UI, polish and documentation commits. Relevant checks were run before milestone commits; commits were not squashed.

### Iteration 2 — visible GitHub progress

> я щось не бачу поки ні одного коміта. Ти впевнений, що ти їх робиш? Дай будь ласка репо на яке ти пушеш

**Problem found:** local commits existed, but the HTTPS push had been interrupted by a credential prompt, so GitHub was still empty.

**Correction:** SSH authentication was verified, `origin` was switched to `git@github.com:oleksandrhoianggwp/testTaskDublemint.git`, the existing commits were pushed, and every later milestone was pushed immediately. Repository: <https://github.com/oleksandrhoianggwp/testTaskDublemint>.

### Iteration 3 — task branches

> а різні бренчі під різні задачі не вчили розхставляти?))) Будь ласка і над цим задумайся

**Response in the work:** task boundaries were published as `chore/bootstrap`, `feat/ticket-1-api`, `feat/ticket-2-api`, `feat/ticket-1-ui`, `feat/ticket-2-ui` and `chore/final-polish-docs`. The branch pointers preserve reviewable milestones while `main` receives the final tested linear history by fast-forward.

## 7. Security and quality review

### Review focus inherited from the master prompt

IDOR, missing auth, client-controlled money, float arithmetic, negative amounts, duplicate claim/revoke, transaction/lock gaps, uniqueness races, cross-user history, pagination abuse, token handling, SQL leakage and committed secrets.

### Findings/corrections

- No promo endpoint accepts a player/user ID or bonus amount from the client.
- All promo routes require Sanctum; resources expose only the authenticated player's records.
- Wallet writes use exact integers inside transactions and database row locks.
- Duplicate consumption is guarded by both application checks and a PostgreSQL invariant.
- Expected business conflicts use stable public codes/messages; raw database errors are not returned.
- `per_page` is bounded, login/promo operations are rate-limited, and frontend tokens are cleared on `401`.
- The demo stores its Bearer token in local storage as a documented test-application trade-off; production deployment should prefer an HttpOnly first-party session/cookie model with a full XSS/CSRF threat review.
- `.env` remained ignored and no plaintext API token or application secret was committed.

## 8. Verification performed

The following checks were actually run during implementation:

```text
php artisan test             42 passed, 145 assertions (PostgreSQL)
npm run lint                 passed
npm run build                passed
vendor/bin/pint --test       passed
browser flow                 login, claim errors, successful claim, filters, modal and revoke verified
responsive browser check     390 px viewport, no document overflow
```

The final README and handoff report use the results of a final repeat of these checks rather than assuming earlier output stayed valid.
