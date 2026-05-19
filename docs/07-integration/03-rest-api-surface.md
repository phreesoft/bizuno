---
title: REST API Surface
category: Integration
order: 3
status: stub
audience: [developer]
last-updated: 2026-05-15
---

# REST API Surface

> **Status:** Stub — not yet drafted.

## What this page will cover

- The `?bizRt=` routing convention (every URL is `module/page/method`)
- Authenticated vs. guest endpoints
- The `api` module — what it exposes, what it doesn't
- CSRF Layer 2: synchronizer-token enforcement (`X-Bizuno-Csrf` header), default-on as of 7.3.x
- Token handling for machine-to-machine calls (when interactive CSRF doesn't apply)
- Common response shapes: `{html, message: {error/success: [...]}}`
- Versioning policy — there isn't really one yet; breaking changes happen in major releases
- When to use `bizRt` vs. building a custom endpoint via `myExt/`

## Why it matters

The REST surface is the most-undocumented part of Bizuno. Integrators
currently reverse-engineer it; that's the wrong default state.

## Related

- [WooCommerce via bizuno-api](./01-woocommerce-via-bizuno-api.md)
