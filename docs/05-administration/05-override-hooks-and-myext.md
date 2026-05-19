---
title: Override Hooks and myExt/
category: Administration
order: 5
status: stub
audience: [admin, developer]
last-updated: 2026-05-15
---

# Override Hooks and `myExt/`

> **Status:** Stub — not yet drafted.

## What this page will cover

- The override-function pattern: core calls `\bizuno\portalGetScope()` if it exists, falls back to default otherwise
- Where override functions live: `BIZUNO_DATA/myExt/...`
- Module-level overrides (controllers, models, views)
- The `myExt/` directory layout convention
- Common override targets: scope detection, payment-gateway routing, custom journal flow, dashboard widget visibility
- Loading order: core → myExt → executed

## Why it matters

This is the difference between "we have to fork the repo for this client" and
"we drop one file in `myExt/`." It's the load-bearing pillar of Bizuno's
multi-tenant deploy story.

## Related

- [The myExt/ pattern](../06-customization/01-the-myext-pattern.md)
