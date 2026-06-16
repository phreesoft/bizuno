---
title: Cache Out of Sync
category: Troubleshooting
order: 2
status: draft
audience: [admin]
last-updated: 2026-06-16
---

# Cache Out of Sync

When a user describes a "weird" symptom — a menu item that won't appear, a
dropdown showing gibberish, a widget that won't go away — the right first
question is almost always **"did you clear the cache?"** Bizuno serves screens
from a cached copy of its configuration, and when that copy drifts from the
database, you get exactly these ghosts. This page is the symptom→fix reference.
The mechanism behind it is in
[Cache mechanics](../05-administration/06-cache-mechanics.md).

---

## Symptom → fix

| Symptom                                                                 | What's happening                                                                 | Fix                          |
|-------------------------------------------------------------------------|----------------------------------------------------------------------------------|------------------------------|
| **"The role I just edited doesn't show the new menu items."**           | The role menu tree is cached; your edit hasn't been re-applied to the live cache. | Clear the cache.             |
| **"A status dropdown shows raw keys like `qa_status_1`."**              | The `options_*` lists in `common_meta` are missing/stale, so the label can't resolve. | Clear the cache — the rebuild re-seeds and re-mirrors the option lists. |
| **"I added a dashboard widget but it doesn't appear" (or a removed one lingers).** | The dashboard registry is cached and hasn't picked up the module's `initialize()`. | Clear the cache; confirm the module's `…Admin::initialize()` registers the widget. |
| **"Reports/menu missing for a user who has the right role."**            | The cached business config has no mirrored `options`/menu entries for that area.  | Clear the cache (the rebuild's mirror step re-populates them). |
| **"After a database restore, everything looks weird."**                  | The cache predates the restored data and no longer matches it.                    | **Mandatory** cache clear after any shell restore. |

The common thread: the in-memory/persistent cache no longer matches the
database. Clearing forces a rebuild that reconciles them.

---

## How to clear the cache

**The button (preferred):** *Settings → Bizuno → Tools → **Clear Business
Cache***. *(since v7.4.0.)* It flags the cache stale; the next request does a full
rebuild. Needs admin access; safe to run anytime — worst case is one slightly
slower request.

**Manual (when you can't reach the UI — e.g. the bad cache is breaking the
screen):** flip the expiry flag directly in the database:

```sql
UPDATE common_meta SET meta_value = '0' WHERE meta_key = 'bizuno_cache_expires';
```

Setting it to `0` puts expiry in the past, so the very next authenticated request
rebuilds. This is the same thing the button does, just reached through SQL.

---

## What "clear" actually does

Clearing doesn't delete settings — it forces the **registry rebuild** that
re-derives the cached config from the database: re-load module settings, re-run
each module's `initialize()`, recompute every role's menu tree, and re-seed +
re-mirror the shared `options_*` lists. The result is written back and the next
request reads the fresh copy. The full walk is documented in
[Cache mechanics](../05-administration/06-cache-mechanics.md#what-a-rebuild-does).

> **If a clear didn't fix it, it probably isn't the cache.** A rebuild reconciles
> cache to *database*. If the database itself is wrong — a missing `options_*`
> row, a module whose `initialize()` doesn't register the thing, a genuinely
> absent menu permission — rebuilding faithfully reproduces the same wrong state.
> At that point, stop reclearing and look at the data (and check for a
> [trace file](./01-trap-and-trace-files.md)).

---

## Related

- [Cache mechanics](../05-administration/06-cache-mechanics.md) — the cache model and the rebuild chain
- [Roles and security](../05-administration/01-roles-and-security.md) — the role menus a rebuild recomputes
- [Trap and trace files](./01-trap-and-trace-files.md) — when it's not the cache after all
