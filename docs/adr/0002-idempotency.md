# ADR 0002: Persist idempotency with the financial transaction

Status: Accepted

The `(api_client_id, idempotency_key)` uniqueness constraint and a canonical request hash are stored on the transfer. Compatible retries return the original; incompatible reuse returns HTTP 409. Wallet locks serialize competing requests for the same funds, and the unique constraint is the final race boundary.
