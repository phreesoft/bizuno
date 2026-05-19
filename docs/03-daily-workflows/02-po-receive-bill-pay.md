---
title: PO → Receive → Vendor Invoice → Pay Bill
category: Daily Workflows
order: 2
status: stub
audience: [bookkeeper]
last-updated: 2026-05-15
---

# PO → Receive → Vendor Invoice → Pay Bill

> **Status:** Stub — not yet drafted.

## What this page will cover

The vendor-side mirror of the customer cycle:

1. **PO** (jID=7) — no GL impact; commits future spend
2. **Receive** — adds to inventory at PO price; debits inventory, credits accrued-receipts
3. **Vendor Invoice** (jID=6) — debits accrued-receipts, credits AP
4. **Pay Bill** (jID=20) — debits AP, credits cash

Common variations:
- Drop-ship PO (no inventory hop)
- Receive without PO (random walk-in vendor)
- Three-way match: PO ↔ receive ↔ invoice quantity/price discrepancies
- Vendor credit / return-to-vendor

## Why it matters

The accrued-receipts step trips up users coming from cash-basis bookkeeping —
they expect the inventory increase to debit AP directly. Showing the GL
clearly avoids confusion at month-end reconciliation.

## Related

- [Quote → SO → Invoice → Payment → Deposit](./01-quote-so-invoice-payment-deposit.md)
- [The journal_id taxonomy](../02-core-concepts/02-journal-id-taxonomy.md)
