# AI-Assisted Development Log

## Prompting strategy

I started the project with one comprehensive master prompt for Codex. I deliberately structured that prompt as an engineering workflow rather than asking the AI to generate the whole application in one unreviewed pass.

The master prompt defined the product requirements, technical constraints, financial invariants, implementation order, verification gates and final deliverables. This let me use the AI as an implementation assistant while keeping the important architectural and security decisions explicit from the beginning.

The steps below are a decomposition of that original master prompt. The quoted instruction blocks are condensed from the master prompt; they are not presented as separate messages that were never sent.

## Step 1 — Inspect the repository and development environment

### Instruction block from the master prompt

> Inspect the current directory, Git status and configured remotes. Determine whether Laravel already exists, preserve useful work, and create a concise implementation plan before modifying files.

### Why I used this step

I wanted the AI to establish the real starting state instead of assuming an empty repository or overwriting existing work. This is especially important when an AI tool has filesystem and Git access: environment discovery must happen before code generation.

### Result

- Confirmed the repository state and target remote.
- Identified that the application needed to be bootstrapped.
- Checked the available Docker environment and occupied host ports.
- Created an incremental implementation order covering bootstrap, Ticket 1, Ticket 2, frontend, tests and documentation.

## Step 2 — Define architecture and financial safety constraints

### Instruction block from the master prompt

> Use Laravel, Vue 3, axios, PostgreSQL, Docker and Sanctum. Keep controllers thin and business logic in focused actions. Store money as integer minor units. Protect wallet operations with transactions, row locks and database invariants. Do not accept player identity or bonus amounts from the client.

### Why I used this step

The domain involves player balances, so a functionally correct UI was not enough. I included the financial and authorization constraints before implementation to prevent the AI from choosing convenient but unsafe defaults such as floats, client-controlled amounts or an unprotected read-modify-write balance update.

I also intentionally limited abstraction: this is a small technical assignment, so normal Laravel conventions were preferable to adding repositories, interfaces or DTO layers without a concrete benefit.

### Result

- Selected integer `BIGINT` minor units for balances and bonus amounts.
- Used `ClaimPromoAction` and `RevokePromoAction` for transactional domain behavior.
- Used Form Requests, API Resources, Eloquent models and a domain exception.
- Defined a consistent wallet lock order to reduce concurrency risk.
- Added database constraints for non-negative balances and positive promo amounts.

## Step 3 — Establish a reviewable Git workflow

### Instruction block from the master prompt

> Create real incremental commits that correspond to actual implementation milestones. Inspect changes before commits, do not commit secrets or generated dependencies, do not squash the history, and push the result to the configured repository.

### Why I used this step

The assignment explicitly evaluates AI-assisted development. A single generated commit would hide how the solution evolved and make review harder. I wanted the history to show clear boundaries between scaffolding, domain implementation, API behavior, tests, UI work and documentation.

During execution I reinforced this by publishing task-oriented branch pointers as well as step-oriented commits.

### Result

Published branches:

```text
chore/bootstrap
feat/ticket-1-api
feat/ticket-2-api
feat/ticket-1-ui
feat/ticket-2-ui
chore/final-polish-docs
```

The final `main` history remained linear and was updated by fast-forward. HTTPS credential interaction was unavailable during the first publication attempt, so the authenticated SSH remote was used for subsequent pushes.

## Step 4 — Bootstrap a Docker-first Laravel/Vue project

### Instruction block from the master prompt

> Create a Docker architecture with PHP-FPM, Nginx, PostgreSQL and a Node/Vite development service. Make the project runnable by a reviewer who has only Git and Docker. Provide safe environment defaults, health checks, seed data and documented log commands.

### Why I used this step

The employer needs to clone and run the project without reproducing my host setup. Docker also makes the PostgreSQL behavior used by financial constraints and locking consistent between development and review.

### Result

- Bootstrapped Laravel 13 and Vue 3 with Vite.
- Added `app`, `nginx`, `postgres` and `node` services.
- Added a PostgreSQL health check and a dedicated PostgreSQL test database.
- Added safe `.env.example` values and kept `.env`, `vendor` and `node_modules` out of Git.
- Exposed the application on `http://localhost:8090` because port 8080 was already occupied on the development machine.

### Iterations and corrections

- Excluded unnecessary local/OneDrive content from the Docker build context.
- Normalized the PHP entrypoint line endings for Windows/Linux compatibility.
- Ensured Laravel runtime directories are writable inside the container.
- Verified the built stack through `docker compose ps` and Laravel `/up`.

## Step 5 — Implement Ticket 1 authentication and promo domain

### Instruction block from the master prompt

> Add a minimal Sanctum Bearer-token login. Model the authenticated user as the player, add promo codes and promo claims, seed a demo player and valid, expired and inactive promo codes. Never derive the player from request data.

### Why I used this step

Ticket 1 says the player must come from the authentication token. I made authentication and the promo data model a separate foundation step so the claim endpoint could be built on a real ownership model rather than retrofitting security later.

### Result

- Added login, current-user and logout endpoints.
- Seeded `demo@example.com` / `password` with a `1000.00 USD` balance.
- Added `PromoCode`, `PromoClaim` and the `applied`, `rejected`, `revoked` status enum.
- Seeded `WELCOME10`, `BONUS50`, `OLD100` and `PAUSED25` for the demonstration flow.
- Added stable money formatting at the API boundary.

## Step 6 — Implement Ticket 1 promo claiming

### Instruction block from the master prompt

> Implement `POST /api/promo/claim`. Validate 6–12 ASCII letters or digits, normalize the code, check existence, active state, expiration and prior successful use, and return the updated balance and credited amount. Record business rejections with distinct codes. Make claim creation and wallet credit atomic and concurrency-safe.

### Why I used this step

I separated transport validation from business validation because they represent different events. Malformed input should return `422`, while a validly formatted but expired or already-used promo is a business attempt that belongs in the audit history.

The transaction, row locks and database uniqueness rule were included because an application-level “check then insert” is insufficient under concurrent requests.

### Result

- Added `ClaimPromoRequest` with the required format validation.
- Normalized promo codes to uppercase.
- Added distinct errors for not found, inactive, expired and already used promos.
- Locked the authenticated player and promo rows inside one transaction.
- Created the claim and credited the exact server-configured amount atomically.
- Added a PostgreSQL partial unique index for successful promo consumption.
- Treated both applied and revoked claims as consumed, while rejected attempts do not consume the promo.

### Iterations and corrections

- Converted PostgreSQL uniqueness conflicts into `PROMO_ALREADY_USED` instead of exposing SQL errors.
- Confirmed rejected attempts cannot mutate the wallet.
- Confirmed a revoked promo cannot later be claimed again.

## Step 7 — Implement Ticket 1 history API and Vue flow

### Instruction block from the master prompt

> Implement authenticated, newest-first promo history with pagination and status filtering. Build a Vue claim form with loading, disabled, success and error states. Update the balance and history without reloading the page.

### Why I used this step

I treated the history as an audit view rather than a simple list of successes. This makes rejected business attempts visible and lets the reviewer verify that failed operations did not change the wallet.

Keeping axios calls in service modules also prevents authentication and error handling from being duplicated across presentation components.

### Result

- Added `GET /api/promo/history` with `status`, `page` and bounded `per_page` parameters.
- Scoped every query to the authenticated user.
- Returned API Resource data with pagination metadata and `can_revoke`.
- Added axios authentication/promo service modules.
- Added claim validation, request loading, disabled actions and backend error messages.
- Added live balance and history refresh after a successful claim.

### Verification

Tests covered ownership isolation, newest-first ordering, all status filters, invalid filters, real pagination and limiting `per_page` to 50.

## Step 8 — Implement Ticket 2 revoke behavior

### Instruction block from the master prompt

> Implement `PATCH /api/promo/{claimId}/revoke`. Only the claim owner may revoke an applied claim. Deduct the exact original bonus once, reject repeated revocation, reject insufficient balance, and update balance and claim status atomically. Add a confirmation action in the Vue history.

### Why I used this step

Revoke is another financial operation, not just a status update. I explicitly required the original credited amount to be preserved and used for reversal so later promo configuration changes cannot alter the debit.

The insufficient-balance rule was also made explicit: the system must return a conflict and leave both wallet and claim unchanged rather than allowing a negative balance or silently clamping it to zero.

### Result

- Added `RevokePromoAction` and the protected PATCH endpoint.
- Scoped claim lookup to the authenticated player to prevent IDOR.
- Locked the player first and claim second inside one transaction.
- Allowed revocation only from `applied` status.
- Returned explicit conflicts for already-revoked, non-revocable and insufficient-balance cases.
- Added a confirmation modal showing the promo code and exact deduction.
- Updated the balance and history status without a full page reload.

### Verification

Tests covered successful revocation, exact original amount, timestamp/status persistence, repeated revoke, rejected claims, cross-player access and insufficient balance. The second revoke was confirmed not to debit the wallet again.

## Step 9 — Perform a dedicated frontend and browser QA pass

### Instruction block from the master prompt

> Build a polished responsive betting/fintech interface rather than default CRUD. Inspect the running page on desktop and mobile. Review hierarchy, spacing, loading/error states, modal behavior, focus states and long messages. Capture genuine running-application screenshots if browser tooling is available.

### Why I used this step

AI-generated frontend code can be technically functional while still containing layout, state-consistency or accessibility problems. I required a separate visual pass after the features worked so UI quality would be evaluated against the running application, not only by reading Vue templates.

### Result

- Created a responsive dark dashboard with a prominent balance, claim form, status filters and audit history.
- Verified login, invalid format, expired promo, successful claim, already-used error, filtering, confirmation and revoke.
- Checked a 390 px viewport and confirmed there was no document-level horizontal overflow.
- Added mobile history cards and adjusted desktop detail visibility.
- Cleared stale claim-success feedback after a later revoke changed the balance.
- Captured the walkthrough in `docs/screenshots/`.

## Step 10 — Run a security and correctness review

### Instruction block from the master prompt

> Search the completed implementation for IDOR, missing authentication, client-controlled money, float arithmetic, negative values, duplicate claim/revoke, missing transactions or locks, uniqueness races, cross-user history, pagination abuse, SQL leakage, token handling and committed secrets. Fix real findings before finalizing.

### Why I used this step

The project is intentionally security-sensitive. I did not want the AI to treat passing happy-path tests as proof that the wallet logic was safe. The dedicated review revisited the implementation from an abuse and concurrency perspective after all features were integrated.

### Result

- Confirmed promo endpoints accept neither player IDs nor monetary values from the client.
- Confirmed all promo routes require Sanctum and all claim queries are owner-scoped.
- Confirmed wallet writes use exact integers, transactions, row locks and database constraints.
- Added rate limits for login and promo mutations.
- Bounded history pagination and prevented internal money/user fields from leaking through resources.
- Confirmed `.env` is ignored and no application secret or access token is committed.

### Documented trade-off

For this local technical demonstration, the Vue application stores the Bearer token in `localStorage`. I documented that a production first-party browser deployment should normally prefer an HttpOnly secure cookie/session model after a complete XSS/CSRF threat review.

## Step 11 — Complete the separate Part 2 code review

### Instruction block from the master prompt

> Review the supplied `GET /players/{player}/credit-bonus` backend and axios fragment as a colleague's pull request. For each major issue, explain the problem, risk and recommended fix. Cover HTTP semantics, authentication, IDOR, client-controlled amount, validation, money precision, races, transactions, replay protection, auditability, frontend error states and tests.

### Why I used this step

The second part of the assignment evaluates engineering judgment independently from the application implementation. I asked the AI to produce a concise PR-style review rather than an academic essay, and then checked that every security and financial concern in the supplied snippet was addressed.

### Result

The completed review is in [`CODE_REVIEW.md`](CODE_REVIEW.md). It recommends rejecting the supplied implementation and replacing it with an authenticated server-controlled promo claim flow.

## Step 12 — Final verification and reviewer handoff

### Instruction block from the master prompt

> Run migrations and seed data from scratch, execute the backend tests, PHP formatter check, frontend lint and production build, verify Docker services and health endpoints, inspect Git status and history, and write a README that another developer can follow without additional help.

### Why I used this step

The final response should report evidence, not assumptions. A clean-clone-oriented verification step catches missing environment instructions, stale build output, incorrect ports and uncommitted files before the repository is submitted.

### Final checks performed

```text
docker compose up -d --build     passed
php artisan migrate:fresh --seed passed
php artisan test                 42 passed, 145 assertions (PostgreSQL)
vendor/bin/pint --test           47 files passed
npm run lint                     passed
npm run build                    passed (Vite production build)
application and /up              HTTP 200
login / me / logout smoke test   passed
responsive browser flow          passed
```

### Deliverables produced

- Runnable Laravel/Vue project in the public Git repository.
- Real task/step-oriented commit history and published branch milestones.
- [`README.md`](README.md) with Docker startup, architecture and demo instructions.
- [`CODE_REVIEW.md`](CODE_REVIEW.md) for Part 2.
- This AI-assisted development log.
- Running-application screenshots under [`docs/screenshots/`](docs/screenshots/).

## Summary of how I used AI

I used Codex for repository inspection, scaffolding, implementation, test generation, browser-assisted UI verification, security review and documentation. The master prompt was intentionally detailed because the critical value in this assignment is not raw code generation; it is constraining the AI to produce reviewable, testable and financially safe behavior, then validating the result with real tools and incremental Git history.
