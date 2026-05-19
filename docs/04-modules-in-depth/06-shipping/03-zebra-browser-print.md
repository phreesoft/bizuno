---
title: Zebra Browser Print
category: Shipping
order: 3
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# Zebra Browser Print

> **Status:** Stub — not yet drafted.

## What this page will cover

- What Zebra Browser Print is — a small local app from Zebra that proxies the browser to the local Zebra printer over `https://127.0.0.1:9100`
- Per-OS install: Windows, macOS, Linux
- The Chrome trust-the-cert step (the screen most users get stuck on)
- Pairing the printer with the running app
- Common failure modes: printer not turned on, "no printer found" with the wrong device filter, secure-connection refused
- Testing the bridge from the browser console
- When to use it vs. the basic PDF-print path

## Why it matters

This is one of the most support-ticket-heavy setup paths in Bizuno. A good
doc page with screenshots cuts the support load in half.

## Related

- [Label printing](./02-label-printing.md)
