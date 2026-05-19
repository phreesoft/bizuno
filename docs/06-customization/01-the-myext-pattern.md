---
title: The myExt/ Pattern
category: Customization
order: 1
status: stub
audience: [developer, admin]
last-updated: 2026-05-15
---

# The `myExt/` Pattern

> **Status:** Stub — not yet drafted.

## What this page will cover

- Why `myExt/`: keep client-specific code out of the public core repo
- Directory layout: `myExt/controllers/<module>/<page>.php` mirrors core paths
- The override function discovery — how Bizuno finds and calls your code
- The `bizuno-clients/` convention: per-client subdirectories, deployment of `myExt/` and per-client WP plugins together
- Composer post-install pruning (the `prune-vendor.php` hook)
- Versioning your `myExt/` against a specific Bizuno core version
- When to override vs. when to file an upstream PR

## Why it matters

This pattern is the difference between "Bizuno is for us with concessions"
and "Bizuno is exactly for us, no compromise." Developers need to know it
exists from day one.

## Related

- [Override hooks and myExt/](../05-administration/05-override-hooks-and-myext.md)
- [Custom payment gateway](./02-custom-payment-gateway.md)
