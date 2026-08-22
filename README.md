# LedgerLab

LedgerLab is a **study/portfolio project designed to demonstrate backend engineering concepts in a financial domain** with PHP and Laravel. It models an internal wallet ledger; it does not move real money, implement PIX, or claim production banking readiness.

The small feature set goes deep on consistency, concurrent spending, idempotent APIs, compensating reversals, signed webhooks, queues, reconciliation, and engineering evidence.

## Core invariants

- Money is integer minor units; floating point never enters the domain.
- A posted transfer has one debit and one equal credit, summing to zero.
- Source and destination differ, share currency, belong to the client, and amount is positive.
- A wallet cannot become negative. PostgreSQL locks serialize competing spends.
- Posted history is append-only. A reversal is a linked compensating transfer and occurs once.
- An idempotency key is unique per client; reuse with another payload is a conflict.
- Webhook event IDs and fake-provider external IDs are unique and idempotent.

Foreign keys, unique indexes, restrictive deletes, and `CHECK` constraints are the final boundary; application validation supplies useful errors.

## Architecture

```text
HTTP -> Form Request / middleware -> thin controller
     -> TransferService (transaction boundary)
     -> Eloquent + PostgreSQL constraints and row locks
     -> after-commit queued job

signed webhook -> durable event -> queued processor -> fake provider store
reconciliation command -> ExternalProvider interface -> comparison report
```

This is a pragmatic Laravel monolith. Eloquent is used directly for local persistence because repositories would add translation without isolation. The external provider is a real boundary, so it has an interface and fake adapter. See [`docs/adr`](docs/adr).

## Stack

- PHP 8.4, Laravel 13, PostgreSQL 17, and Redis 8
- PHPUnit 12, Larastan/PHPStan level 7, Laravel Pint
- Docker Compose and GitHub Actions

## Setup

Docker with Compose is the only host requirement.

```bash
git clone https://github.com/OWNER/ledger-lab.git
cd ledger-lab
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate:fresh --seed
docker compose up -d
```

The API runs at `http://localhost:8000`. The seeder prints the local demo key `ledger-lab-demo-token`; wallets intentionally start at zero so later balances are ledger-explainable. Use `X-Api-Key` on client routes.

```bash
curl -X POST http://localhost:8000/api/wallets \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Key: ledger-lab-demo-token' \
  -d '{"name":"Operating","currency":"BRL"}'

curl -X POST http://localhost:8000/api/transfers \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Key: ledger-lab-demo-token' \
  -H 'Idempotency-Key: example-001' \
  -d '{"source_wallet_id":"UUID","destination_wallet_id":"UUID","amount_cents":2500,"currency":"BRL"}'
```

The study API intentionally has no mint endpoint. Tests create committed fixtures; production would credit wallets through a separately authorized deposit rail.

## Quality commands

```bash
docker compose run --rm app php artisan test
docker compose run --rm app composer analyse
docker compose run --rm app composer format:check
docker compose run --rm app composer quality
```

Tests use PostgreSQL, not SQLite. The concurrency test starts a second PHP process, holds a lock in the first, commits a competing debit, and proves the waiter observes the reduced balance and cannot double-spend.

## Idempotency and concurrency

The normalized payload is SHA-256 hashed. `TransferService` locks both wallets by sorted UUID before checking the client-scoped key, validating, inserting transfer and ledger rows, and updating projections in one transaction. Compatible retries return the resource; incompatible reuse returns 409. A unique constraint covers residual races. Sorted acquisition avoids opposite-direction deadlock, and Laravel retries transient deadlocks three times.

## Ledger and reversals

Entries contain signed minor units and explicit direction; constraints ensure sign agrees. Balance is a read projection updated atomically with the history that answers “why does this balance exist?”. Reversals swap wallets, create a new balanced pair, and link through a unique foreign key—never editing the original.

## Queues and failure isolation

Notification and webhook jobs dispatch after commit with retries/backoff. Secondary failure is logged and cannot roll back a posted transfer. Logs contain IDs and error classes, not tokens, HMACs, secrets, or request bodies. Run workers with `docker compose up worker` or `docker compose up`.

## Signed webhooks

`POST /api/webhooks/provider` verifies `X-Provider-Signature` as SHA-256 HMAC of the exact raw body. Invalid signatures return 401. Accepted IDs are durable before dispatch; duplicates are acknowledged without another job. Unsupported types fail and retry without being marked processed.

## Reconciliation

```bash
docker compose run --rm app php artisan ledger:reconcile
docker compose run --rm app php artisan ledger:reconcile --json
```

It reports missing operations, amount divergence, and state divergence. Differences produce a non-zero exit status suitable for scheduled monitoring.

## Security and observability

- API keys are stored as SHA-256 digests; raw keys are never persisted.
- Ownership is checked inside the transaction to prevent object-level bypasses.
- HMAC comparison is constant-time; API and webhook routes are rate limited.
- Correlation IDs are accepted/generated, added to structured log context, and returned.
- `.env`, runtime databases, caches, and vendor dependencies are ignored.

Portfolio authentication is intentionally narrow. Production needs key rotation, scopes, expiry/revocation, TLS, managed secrets, monitoring, and operational controls.

## Why Laravel?

Laravel solves concrete problems here: Form Requests centralize transport validation; middleware handles authentication and correlation; the service container swaps the provider; Eloquent expresses locks and relations; transactions protect the ledger; jobs provide after-commit work and retries; rate limiting protects ingress; Artisan provides reconciliation; and its test layer exercises HTTP, queues, commands, and PostgreSQL together. It reduces glue while leaving financial rules visible in one small service.

## Engineering Decisions

- **Relational ledger, not event sourcing:** traceability without replay infrastructure or CQRS.
- **Cached balance plus ledger:** fast reads and an audit explanation, with redundancy protected transactionally.
- **Integer cents:** precise in this BRL-only scope; multi-currency would model exponents and rounding.
- **Pessimistic locks:** wallet contention is expected and correctness dominates speculative throughput.
- **Database-backed fake provider:** deterministic, inspectable, and replaceable behind one interface.
- **After-commit jobs:** secondary work is isolated from the financial commit.
- **No local repository layer:** Eloquent is not treated as an imaginary interchangeable boundary.

## CI

GitHub Actions installs PHP 8.4 dependencies, migrates clean PostgreSQL 17, checks Pint, runs Larastan level 7, and executes PHPUnit. Workflow permissions are read-only.

## Known limitations

Educational scope: BRL only, no deposit rail, pagination, production identity, distributed outbox, or HTTP provider. After-commit dispatch still has a crash gap between DB commit and queue publication; production reliability would justify a transactional outbox.

## License

MIT. Changes should preserve the rules in [`AGENTS.md`](AGENTS.md).
