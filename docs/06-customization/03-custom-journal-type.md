---
title: Custom Journal Type
category: Customization
order: 3
status: stub
audience: [developer]
last-updated: 2026-05-15
---

# Custom Journal Type

> **Status:** Stub — not yet drafted.

## What this page will cover

- When you'd want one (client-specific transaction types that don't fit existing jIDs)
- Picking a journal_id that won't collide with future core additions (40+ range is conventionally safe for custom)
- The journal class skeleton (`controllers/phreebooks/journals/jNN.php`)
- Required methods: `loadJournal`, `unPost`, `Post`, `journalize`
- Registering the new journal in cache / menu / role-security
- Producing the correct GL postings — the part that's easy to get wrong
- Audit-log integration

## Why it matters

Adding a custom journal is the *deepest* extension surface Bizuno exposes.
Doc has to give a clear scaffold so attempts don't end as "this kind of
works but the GL doesn't balance."

## Related

- [The journal_id taxonomy](../02-core-concepts/02-journal-id-taxonomy.md)
- [The myExt/ pattern](./01-the-myext-pattern.md)
