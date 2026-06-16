---
title: Override Hooks and myExt/
category: Administration
order: 5
status: draft
audience: [admin, developer]
last-updated: 2026-06-16
---

# Override Hooks and `myExt/`

This is the difference between "we have to fork Bizuno for this client" and "we
drop one file in `myExt/`." It's the load-bearing pillar of the multi-tenant
deploy story: the core library stays pristine and installable everywhere, while
per-install customization lives in a separate directory the core knows to look
in.

This page is the admin's-eye overview — what the mechanism is, where the files
go, and how loading works. The hands-on "how to write one" guide is
[The myExt/ pattern](../06-customization/01-the-myext-pattern.md) in the
Customization chapter.

---

## Two ways to customize without forking

Bizuno offers two distinct override mechanisms. Both keep your changes out of the
core tree.

### 1. Override functions (hooks)

At certain decision points, the core checks whether a userland function exists
and, if it does, calls yours instead of its own default. The canonical example is
scope detection:

```php
// in the core router
if (function_exists("\\bizuno\\portalGetScope")) {
    return portalGetScope($page, $userValidated);   // your version
}
// …otherwise fall through to the built-in logic
```

Define `\bizuno\portalGetScope()` in your `myExt/` and the core defers to it; omit
it and the core behaves normally. The function must be in the `\bizuno`
namespace and match the hook name exactly.

The hook points that exist today:

| Hook function       | Overrides                                          |
|---------------------|----------------------------------------------------|
| `portalGetScope()`  | How a request is classified (guest / auth / api / install) |
| `portalLogin()`     | The login workflow                                 |
| `portalLogout()`    | Logout actions                                     |
| `bizCsrfGet()`      | How the CSRF token is retrieved                    |
| `bizCsrfRotate()`   | When/how the CSRF token is rotated                 |

This is a curated set, not an open event bus — the core calls a hook only where
one is wired in. If you need a customization point that doesn't exist, that's a
core change, not a `myExt/` drop-in.

### 2. Module / method overrides (file shadowing)

The registry scans **core first, then `myExt/`**, when it builds the module and
method map. A file placed at the matching path under
`myExt/controllers/<module>/<folder>/<method>.php` **shadows** the core file of
the same path — the registry points at your version. The same scan lets `myExt/`
add an entirely new module (a folder with its own `admin.php`) that appears
alongside the built-in ones.

Shadowing is path-for-path: you override a method *within its own module*; you
can't relocate a method into a different module from `myExt/`.

---

## Where it lives and how it's laid out

Everything sits under **`BIZUNO_DATA/myExt/`** — inside the writable data
directory, separate from the read-only core library. A representative layout:

```
BIZUNO_DATA/myExt/
├── controllers/
│   ├── <module>/<folder>/<method>.php   # shadow or extend a core method
│   ├── myAdmin/admin.php                # custom admin-panel additions
│   ├── api/myAPI.php                    # custom (unauthenticated) API endpoints
│   └── <newModule>/admin.php            # an entirely new module
├── model/                               # custom PhreeForm processor classes
└── view/
    └── icons/<size>/                    # custom icon assets
```

A given install uses only the pieces it needs — most have a handful of files,
not the whole tree.

> **`myAPI.php` is intentionally unauthenticated.** It's meant for webhooks and
> cron callers, so it runs *outside* the login gate — it must do its own
> authentication. Treat anything you expose there as public-facing.

---

## Loading order

The sequence the core follows on each request:

1. **Core initializes first** — the registry scans
   `BIZUNO_FS_LIBRARY/controllers/` and builds the base module/method map.
2. **`myExt/` is scanned second** — `myExt/controllers/` entries are merged in,
   overwriting any core entry at the same path. (This is why "core → myExt" means
   myExt wins.)
3. **Hooks fire at call time** — when the core reaches a hook point, it checks
   `function_exists()` for your override and calls it if present.

Core always loads first so there's a default to fall back to; `myExt/` always
gets the last word.

---

## Operational notes for admins

- **`myExt/` is part of `BIZUNO_DATA`** — so it's only protected if your
  [backup](./03-backup-and-restore.md) captures the `BIZUNO_DATA` filesystem, not
  just the database. A database-only backup loses every customization.
- **After adding or removing a `myExt/` module**, clear the business cache so the
  registry re-scans — see [Cache mechanics](./06-cache-mechanics.md). A new module
  that "doesn't appear," or a removed one that "won't go away," is almost always a
  stale registry.
- **There's no PSR-4 magic.** Loading is explicit path resolution, not
  convention-based autoloading — get the path right and the file is found; get it
  wrong and it's silently ignored.

---

## Related

- [The myExt/ pattern](../06-customization/01-the-myext-pattern.md) — the hands-on developer guide
- [Backup and restore](./03-backup-and-restore.md) — why `BIZUNO_DATA` (and thus `myExt/`) must be backed up
- [Cache mechanics](./06-cache-mechanics.md) — clear the cache after changing `myExt/`
