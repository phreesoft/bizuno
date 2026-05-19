---
title: WooCommerce via bizuno-api
category: Integration
order: 1
status: stub
audience: [admin, developer]
last-updated: 2026-05-15
---

# WooCommerce via `bizuno-api`

> **Status:** Stub — not yet drafted.

## What this page will cover

- Architecture overview: `bizuno-api` WP plugin acts as a bridge between WooCommerce on a public site and Bizuno on a (possibly private) back-office host
- Direction of data flow: orders + customers WooCommerce → Bizuno; inventory + prices Bizuno → WooCommerce
- Authentication: shared secret, encrypted token, the `decrypt_password` legacy quirk (binary-garbage avoidance from 2026-Q1)
- Endpoint reference (per-resource: customers, orders, inventory, prices, shipping rates)
- Common failure modes: WAF blocking, mod_security rules, header-encoding corner cases
- Webhook patterns for near-real-time sync
- Conflict resolution: what wins when both sides edit a customer

## Why it matters

Selling on WooCommerce and accounting in Bizuno is one of the most common
Bizuno deployment topologies. Doc has to be precise about who owns what.

## Related

- [REST API surface](./03-rest-api-surface.md)
