---
title: Trap and Trace Files
category: Troubleshooting
order: 1
status: stub
audience: [admin, developer]
last-updated: 2026-05-15
---

# Trap and Trace Files

> **Status:** Stub — not yet drafted.

## What this page will cover

- What a "trap" is — `msgDebug($msg, 'trap')` flushes the running debug trace to disk and may email it
- Where trace files live (`BIZUNO_DATA/...`)
- Reading a trace file: timestamps, SQL counts, the operation path
- Common trap triggers: chart-not-loaded, missing GL default, fatal PHP error caught by handler, EDI unexpected segment
- The `bizScrubSensitive` helper (post-2026): trap output redacts pass/pwd/token/card/cvv automatically before being written
- When a trap is fatal vs. informational (e.g. EDI N9/MSG used to be informational — fixed in 7.3.9 to not trap at all)
- Disabling traps temporarily for noisy debugging
- Cleaning up old trace files (they accumulate)

## Why it matters

Trace files are how you actually diagnose production problems in Bizuno.
Anyone supporting an install needs to read them fluently.

## Related

- [Cache out of sync](./02-cache-out-of-sync.md)
- [EDI X12](../07-integration/02-edi-x12.md)
