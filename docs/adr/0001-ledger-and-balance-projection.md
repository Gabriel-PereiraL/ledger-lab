# ADR 0001: Append-only ledger with a locked balance projection

Status: Accepted

Every posted transfer writes two immutable, equal-and-opposite ledger entries and updates both wallet balance projections inside one PostgreSQL transaction. Wallet rows are locked in deterministic UUID order. Reads stay cheap and history explains the balance; tests and reconciliation guard the redundant projection. This is deliberately not event sourcing: normal relational state remains authoritative and no replay machinery exists.
