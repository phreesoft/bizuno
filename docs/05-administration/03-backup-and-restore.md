---
title: Backup and Restore
category: Administration
order: 3
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# Backup and Restore

> **Status:** Stub — not yet drafted.

## What this page will cover

- What constitutes a complete Bizuno backup (DB **and** `BIZUNO_DATA` filesystem)
- Built-in backup tool (admin → Tools → Backup) — what it captures, where it writes
- Scheduling backups outside Bizuno (cron + mysqldump + tar)
- Off-site storage: S3, Backblaze, encrypted rsync
- Testing your backup — the unspoken requirement, the one most installs skip
- Restore procedure: stop traffic → drop/import DB → restore files → cache rebuild
- Restoring after a botched fiscal-year close
- Encrypted backups + key management (don't lose the key)

## Why it matters

This is the page that, when followed, prevents the worst possible Bizuno
day. Treat it accordingly.

## Related

- [Fiscal-year close](./04-fiscal-year-close.md)
