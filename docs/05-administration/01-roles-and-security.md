---
title: Roles and Security
category: Administration
order: 1
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# Roles and Security

> **Status:** Stub — not yet drafted.

## What this page will cover

- The role-based access model: roles → users; security stored per-role as a flat map of `<screen_key => level>`
- Levels 0–4 (None, Read, Edit, Add, Delete) and what each enables
- The `administrate` super-flag and what it bypasses
- The role editor UI (Settings → Roles)
- Parent-menu access inheriting from max-child (7.3.9+) — why a screen like `inventory/build/manager` can be reached even though the role editor only exposes checkboxes for its children
- Restricting a user to a single store (`restrict_store`)
- Common patterns: bookkeeper, AR clerk, AP clerk, sales-only, read-only auditor
- Audit log of who-changed-what-when

## Why it matters

Most security mistakes come from over-granting. The doc has to teach
principle-of-least-privilege for the common roles before showing all the
levers.

## Related

- [Users vs. employees vs. contacts](./02-users-employees-contacts.md)
