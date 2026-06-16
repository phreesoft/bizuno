---
title: Backup and Restore
category: Administration
order: 3
status: draft
audience: [admin]
last-updated: 2026-06-16
---

# Backup and Restore

This is the page that prevents the worst possible Bizuno day. Read it before you
need it, and — the part most installs skip — actually *test* a restore.

The single most important thing to understand up front: **a complete Bizuno
backup is two parts**, and the built-in tool only covers one of them.

---

## What a complete backup is

| Part            | What's in it                                              | Covered by built-in tool? |
|-----------------|-----------------------------------------------------------|:-------------------------:|
| **The database**| Every transaction, contact, item, GL account, setting     | ✅ Yes                    |
| **`BIZUNO_DATA`** | Uploaded images, generated PDFs, attachments, quality/inventory documents, **and your `myExt/` customizations** | ❌ No — manual            |

Restore the database alone and the books are back, but product images, attached
documents, and any `myExt/` overrides are gone. You need **both** parts to be
whole. `BIZUNO_DATA` is the data directory defined in `portalCFG.php`; back up
the entire directory tree.

---

## The built-in backup tool

**Administrate → Backup.** It produces a **database** backup and nothing else:

- Runs `mysqldump` and writes a gzip-compressed `.sql.gz` file.
- Saves it under `BIZUNO_DATA/backups/`, named `<company>-<timestamp>.sql.gz`.
- Credentials are passed to `mysqldump` through a temporary, locked-down options
  file — they never appear on the command line.

Two requirements worth knowing:

- **PHP `exec()` must be enabled.** The tool shells out to `mysqldump`; if your
  host disables `exec()`, the built-in backup can't run and you must back up the
  database another way (below).
- **Access:** creating a backup needs security level 2; restoring needs Full
  (level 4).

> **The backups live inside what you're backing up.** Files written to
> `BIZUNO_DATA/backups/` are part of the `BIZUNO_DATA` tree — so they're only
> safe if you also copy them *off* the server. A backup that never leaves the box
> doesn't survive the box dying.

### Audit-log backup and prune

The same screen has a separate "backup and clean transaction history" function:
it writes a standalone gzip of the `audit_log` table, then lets you delete log
rows up to a chosen date. Use it to keep the audit log from growing without
bound — back up first, then prune.

---

## Filling the gaps: a real backup routine

Because the built-in tool is database-only and writes in-place, treat it as
convenience, not strategy. A dependable routine runs outside Bizuno:

```bash
# 1. Database
mysqldump --single-transaction --defaults-extra-file=/secure/my.cnf \
  bizuno_db | gzip > /backups/bizuno-db-$(date +%F).sql.gz

# 2. The BIZUNO_DATA filesystem (images, PDFs, attachments, myExt/)
tar czf /backups/bizuno-data-$(date +%F).tar.gz -C /path/to/bizuno data/

# 3. Off-site — pick one
rclone copy /backups remote:bizuno-backups        # S3 / Backblaze / etc.
# or: rsync -az -e ssh /backups/ backup-host:/bizuno/
```

Schedule it with `cron`. Keep several days of history, not just last night's —
corruption you don't notice for a week needs a backup from before it started.

> **Encrypt off-site copies — and don't lose the key.** A backup holds every
> customer record and financial detail you have. Encrypt it at rest off-server
> (e.g. `gpg`, or your object store's encryption). Then store the key somewhere
> *other* than the backups themselves — an encrypted backup whose key is gone is
> indistinguishable from no backup.

---

## Test the restore

A backup you've never restored is a hope, not a backup. Periodically:

1. Spin up a throwaway database and an empty `BIZUNO_DATA`.
2. Import the latest dump and unpack the data archive into it.
3. Point a scratch `portalCFG.php` at them and log in.
4. Confirm a few transactions, a report, and an uploaded image all come back.

Do this on a copy — never against production.

---

## Restoring

The backup screen can restore a database file (`.sql`, `.sql.gz`, or `.zip`)
directly — but understand that **restore overwrites the live database**: it
drops and rewrites it, then logs everyone out. It's a recovery operation, not an
"undo." For a full recovery:

1. **Stop traffic** so no one writes during the restore.
2. **Restore the database** — via the backup screen, or `gunzip | mysql` from the
   shell. This replaces the current database wholesale.
3. **Restore `BIZUNO_DATA`** from your filesystem archive (the built-in tool
   won't do this — unpack your `tar`).
4. **Clear the business cache** so the rebuilt registry matches the restored data
   — see [Cache mechanics](./06-cache-mechanics.md). (A restore through the UI
   clears it for you; a shell restore does not.)
5. Log back in and verify.

### Recovering from a botched fiscal-year close

A fiscal-year close is destructive — it renumbers and purges closed-period
detail. If a close goes wrong, **the clean way back is a pre-close database
restore**, which is exactly why you take a fresh backup immediately before
running one. Do that, and recovery is "restore last night's dump." Skip it, and
there may be no way back. See [Fiscal-year close](./04-fiscal-year-close.md).

---

## Related

- [Fiscal-year close](./04-fiscal-year-close.md) — always back up immediately before
- [Cache mechanics](./06-cache-mechanics.md) — clear the cache after a restore
- [Override hooks and myExt/](./05-override-hooks-and-myext.md) — why `BIZUNO_DATA` must be in your backup
