---
title: Cache Mechanics
category: Administration
order: 6
status: stub
audience: [admin, developer]
last-updated: 2026-05-15
---

# Cache Mechanics

> **Status:** Stub — not yet drafted.

## What this page will cover

- The two-level cache: `bizuno_cache_expires` flag in `common_meta` + the in-memory registry rebuild
- What the registry rebuild does: `initRegistry` → `initSettings` → `initModule` (each `*Admin::initialize()`) → `setRoleMenus` → `initBizuno` (mirror)
- Why options like `options_qa_status` live in `common_meta` and what re-seeds them
- The 7.3.9 migration off `getModuleCache('bizuno', 'options', ...)` to `getMetaCommon('options_*')`
- The manual cache-clear button (Settings → Bizuno → Tools → Clear Business Cache, added 7.3.9)
- When to clear cache (after manual SQL, after dropping an extension, recovery)
- Symptoms of a stale cache: missing dropdown options, dashboards still showing removed widgets, role menu missing items

## Why it matters

"It works on my machine after a cache clear" is a real Bizuno phenomenon.
Doc has to make the cache visible so users know what to try first.

## Related

- [Cache out of sync](../09-troubleshooting/02-cache-out-of-sync.md)
