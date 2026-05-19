---
title: Cache Out of Sync
category: Troubleshooting
order: 2
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# Cache Out of Sync

> **Status:** Stub — not yet drafted.

## What this page will cover

Common symptoms and their fix:

- "The role I just edited doesn't show the new menu items" → manual cache clear
- "A status dropdown shows raw lang keys like `qa_status_1`" → cache rebuild + check `options_*` rows in `common_meta`
- "I added a new dashboard widget but it doesn't appear" → cache clear; check the module's `*Admin::initialize()`
- "Reports menu missing for a user with the right role" → cache mirror loop in `initBizuno` (transitional shim)
- "After a database restore, everything looks weird" → mandatory cache clear

How to clear:
1. Settings → Bizuno → Tools → Clear Business Cache (added 7.3.9)
2. Or manual: `UPDATE common_meta SET meta_value=0 WHERE meta_key='bizuno_cache_expires'`

What rebuild actually does (the registry walk).

## Why it matters

When users describe a "weird" symptom, "did you clear the cache?" is the
right first question 60% of the time. Doc has to teach this reflex.

## Related

- [Cache mechanics](../05-administration/06-cache-mechanics.md)
