---
title: Four Ways to Run It
category: Getting Started
order: 2
status: stub
audience: [admin]
last-updated: 2026-05-27
---

# Four Ways to Run It

> **Status:** Stub — not yet drafted.

## What this page will cover

- **Standalone** — composer install on your own LAMP/LEMP host (control, no WP needed)
- **WordPress plugin** — `bizuno-accounting` plugin riding inside WordPress (familiar admin, single login)
- **Docker** — dedicated container stack with TLS-terminating reverse proxy
  ([walkthrough already published](./04-docker-install-walkthrough.md))
- **PhreeSoft Cloud** — multi-tenant managed hosting (zero ops)
- Decision matrix: which path for which user (size, technical skill, integration needs)
- Hard requirements per path (PHP version, MySQL version, disk, RAM)
- What you can move between (yes: standalone ↔ WP, cloud → self-host; no: trivially back to QuickBooks)

## Why it matters

This is one of Bizuno's strongest differentiators — most ERPs lock you to a
single delivery model. Users don't always realize the choice exists.

## Related

- [What is Bizuno](./01-what-is-bizuno.md)
- [Docker install walkthrough](./04-docker-install-walkthrough.md) — full step-by-step for the Docker path
- [First-hour walkthrough](./03-first-hour-walkthrough.md)
