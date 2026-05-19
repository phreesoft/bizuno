---
title: Journals (the journal_id reference)
category: PhreeBooks
order: 2
status: stub
audience: [bookkeeper, admin, developer]
last-updated: 2026-05-15
---

# Journals (the `journal_id` reference)

> **Status:** Stub — not yet drafted.

## What this page will cover

Full per-jID reference. For each journal ID:
- Name, purpose, where in the UI it surfaces
- The GL postings it produces (debits/credits)
- Required + optional fields on `journal_main`
- Common pitfalls
- The journal class file under `controllers/phreebooks/journals/jNN.php`

Plus:
- General journal entries (jID=2) — when to use, when not
- Read-only audit journals (which jIDs prevent edit after post)
- The `recur_id` linking pattern across all journal types

## Why it matters

Most existing Bizuno/PhreeBooks documentation gestures at journals without
enumerating them. Bookkeepers can't pick the right transaction type if they
don't know what each one *does*.

## Related

- [The journal_id taxonomy](../../02-core-concepts/02-journal-id-taxonomy.md)
- [Custom journal type](../../06-customization/03-custom-journal-type.md)
