---
title: Cache Mechanics
category: Administration
order: 6
status: published
audience: [admin, developer]
last-updated: 2026-06-16
---

# Cache Mechanics

"It works after a cache clear" is a real Bizuno phenomenon, and this page exists
to make the cache visible so it's the *first* thing you try, not the last. The
short version: Bizuno caches a big chunk of its configuration — modules, menus,
role security, dropdown option lists — and serves screens from that cache instead
of rebuilding it on every request. When the cache and the database disagree, you
see ghosts.

---

## Why a cache exists

Assembling Bizuno's working configuration is expensive: scan every module,
initialize each one, compute every role's menu tree, gather all the option
lists. Doing that per request would make every page slow. Instead Bizuno builds
it once, stores it, and reuses it — rebuilding only when something says it's
stale.

---

## The two levels

**1. The persistent cache (the database).** The assembled configuration is
serialized into the `configuration` table; option lists and similar shared data
live in `common_meta`. This survives across requests and users.

**2. The in-memory copy (this request).** On each request Bizuno loads the
persistent cache into memory (the `$bizunoMod` registry, read through
`getModuleCache()`) and serves the page from it.

Tying them together is a single timestamp: **`bizuno_cache_expires`**, a row in
`common_meta`. At the start of an authenticated request Bizuno compares now
against that timestamp; if the cache has expired, it triggers a **full registry
rebuild** before serving the page.

So "clearing the cache" is really "setting `bizuno_cache_expires` so the next
request rebuilds."

---

## What a rebuild does

A rebuild re-runs the registry initialization end to end:

1. **`initRegistry()`** kicks it off.
2. **`initSettings()`** loads each module's stored settings.
3. **`initModule()`** for every module — calls that module's
   `…Admin::initialize()`, which re-registers its screens, settings, and option
   lists.
4. **`setRoleMenus()`** recomputes each role's menu tree and the
   [parent-from-child menu access](./01-roles-and-security.md#parent-menu-access-from-child-screens).
5. **`initBizuno()`** re-seeds the shared `options_*` lists in `common_meta` and
   mirrors them back into the in-memory cache.

The result is written back to the `configuration` table, and the next request
reads the fresh copy.

---

## Where the option lists live

Dropdown option lists — return statuses, QA statuses, contact statuses, lead
times, CRM actions, and the like — are stored as `options_*` rows in
`common_meta` (e.g. `options_qa_status`). Each module's `initialize()` seeds its
own lists during a rebuild, and `initBizuno()` mirrors them into the module cache
so screens read them fast.

This dual storage (canonical rows in `common_meta`, fast copy in the registry) is
why a corrupted or half-migrated option list heals itself on a cache clear: the
rebuild re-seeds the rows and re-mirrors them.

> **Version note.** The `common_meta`-backed option lists were repaired and
> rebuilt as of **7.3.8** — the upgrade clears the old `options_*` rows so the
> next rebuild reseeds them cleanly. (There is no 7.3.9 release; if you've seen
> that number associated with these changes, it's either 7.3.8 or v7.4.0.)

---

## Clearing the cache

**Settings → Bizuno → Tools → Clear Business Cache** forces a full rebuild on the
next request. *(since v7.4.0)* It needs admin access. Use it when:

- You've **edited the database directly** (manual SQL) and the UI doesn't reflect
  it.
- You've **added or removed a `myExt/` module or extension** and it isn't showing
  up / won't go away — see [Override hooks and myExt/](./05-override-hooks-and-myext.md).
- You've **restored a backup** from the shell (a UI restore clears it for you) —
  see [Backup and restore](./03-backup-and-restore.md).
- You're **recovering** from a configuration that's behaving inconsistently.

It's safe to run anytime — the worst case is one slightly slower request while the
rebuild happens.

---

## Symptoms of a stale cache

If you see any of these, clear the cache *first* before deeper debugging:

- **Dropdowns show raw keys** like `qa_status_1` instead of the translated label —
  the `options_*` lists are missing or stale.
- **Dashboard widgets** you removed still appear, or newly added ones don't.
- **A role's menu is missing items** the role should have (or vice versa) — the
  menu tree wasn't recomputed.
- **A `myExt/` module** doesn't appear, or a removed one lingers.

All four are the same root cause: the in-memory/persistent cache no longer
matches the database. The rebuild reconciles them.

---

## Related

- [Cache out of sync](../09-troubleshooting/02-cache-out-of-sync.md) — troubleshooting the symptoms above
- [Roles and security](./01-roles-and-security.md) — the role menus a rebuild recomputes
- [Override hooks and myExt/](./05-override-hooks-and-myext.md) — clear the cache after changing `myExt/`
