---
title: Custom Payment Gateway
category: Customization
order: 2
status: stub
audience: [developer]
last-updated: 2026-05-15
---

# Custom Payment Gateway

> **Status:** Stub — not yet drafted.

## What this page will cover

- The gateway abstraction in `controllers/payment/gateways/`
- Required methods on a gateway class: `sale()`, `void()`, `refund()`, plus the optional `auth()` / `capture()` for split flows
- The new architecture (post-2026): unified interface, gateway-specific implementation
- Worked example: stubbing a fictional "ProcessorX" gateway
- Storing credentials safely (encryption via `$mixer`)
- Testing with a sandbox account
- PCI considerations — what credentials you store, what you must not
- Surfacing the gateway in the payment-method dropdown

## Why it matters

Custom gateway support is one of the most common reasons consultants pick up
a Bizuno deployment. Doc has to give them a working skeleton in 30 minutes.

## Related

- [The myExt/ pattern](./01-the-myext-pattern.md)
