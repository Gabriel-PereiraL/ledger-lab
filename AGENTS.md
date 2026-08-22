# LedgerLab contributor guide

LedgerLab is an educational Laravel API demonstrating reliable financial-backend techniques. It is not a bank or a production payment product.

## Architecture

- Keep controllers thin; orchestration and transaction boundaries belong in `app/Services`.
- Use Eloquent directly inside the application service. Add interfaces only at genuine boundaries such as the external provider.
- Store money as integer minor units (`amount_cents`), never floating point.
- PostgreSQL constraints are the final guard; validation provides friendly errors.
- Dispatch secondary work only after the database transaction commits.

## Commands

```bash
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate:fresh --seed
docker compose run --rm app php artisan test
docker compose run --rm app composer analyse
docker compose run --rm app composer format:check
```

## Financial invariants

- Every posted transfer has exactly two equal-and-opposite ledger entries whose sum is zero.
- Wallet balance is a cached projection and must equal the sum of its ledger entries.
- Posted entries and transfers are append-only; corrections are linked compensating transfers.
- Source and destination differ, amount is positive, currency matches, and available balance cannot go negative.
- Wallet rows are locked in deterministic ID order to prevent double-spend and deadlocks.
- `(client_id, idempotency_key)` is unique; the same key with a different request hash is a conflict.
- A transfer can be reversed at most once.

## Change rules

- Add or update tests for every invariant or failure path.
- Do not log secrets, authorization headers, webhook signatures, or full request bodies.
- Never weaken constraints or static-analysis rules to make checks green.
- Prefer one migration per coherent schema change and use explicit indexes/foreign keys.
- Run all three quality commands before committing.

