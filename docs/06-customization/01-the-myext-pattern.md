---
title: The myExt/ Pattern
category: Customization
order: 1
status: published
audience: [developer, admin]
last-updated: 2026-06-16
---

# The `myExt/` Pattern

This is the pattern that lets Bizuno be *exactly* for one business without
forking the core repo. Client-specific code lives in a separate `myExt/`
directory that the core knows to look in; the public `bizuno/` library stays
pristine and installable everywhere. This page is the developer's deep dive —
the [Administration overview](../05-administration/05-override-hooks-and-myext.md)
is the shorter operational version.

---

## Why `myExt/` exists

The core library is public-facing and must stay installable on any platform.
Putting one client's battery-recycling fee or custom shipping carrier into
`bizuno/` would pollute everyone's install. So Bizuno gives customization a
**separate, parallel tree** under the data directory — `BIZUNO_DATA/myExt/` —
and the core loads from it after itself. Your code rides alongside the client's
data, not inside the shared library.

---

## The two mechanisms

There are exactly two ways to inject custom behavior, and it's worth knowing
which one you're using.

### 1. Path shadowing (the workhorse)

The registry scans the core `controllers/` tree **first**, then
`BIZUNO_DATA/myExt/controllers/`. A file placed at the **matching path** under
`myExt/` overrides the core file of the same path, because the second scan
overwrites the first in the method map. Your `myExt/` mirrors the core layout:

```
BIZUNO_DATA/myExt/
├── controllers/
│   └── phreebooks/totals/fee_battery/fee_battery.php   # a new "total" line
├── model/                                              # custom PhreeForm classes
└── view/icons/<size>/                                  # custom icon assets
```

A real example from a live client: `battery-store` adds a
`phreebooks/totals/fee_battery/` method — a state battery-recycling fee — by
dropping a class at that path. The registry discovers it exactly like a built-in
total (subtotal, tax, shipping); the implementation just happens to live in
`myExt/`. It implements the same method surface a core total would
(`__construct`, `settingsStructure`, `glEntry`, `render`).

### 2. Override-function hooks

At a handful of decision points the core calls a userland function *if it
exists*:

```php
if (function_exists("\\bizuno\\portalGetScope")) { return portalGetScope(...); }
// else fall through to default
```

Define the function in your `myExt/` (in the `\bizuno` namespace) and the core
defers to it. The wired-in hooks today are `portalGetScope`, `portalLogin`,
`portalLogout`, `bizCsrfGet`, and `bizCsrfRotate`. This is a curated set, not a
general event bus — see
[Override hooks and myExt/](../05-administration/05-override-hooks-and-myext.md)
for the full list and loading order.

> **No PSR-4 magic.** Loading is explicit path resolution via `bizAutoLoad`, not
> convention-based autoloading. Match the path exactly or the file is silently
> ignored. And after adding/removing a `myExt/` module,
> [clear the business cache](../05-administration/06-cache-mechanics.md) so the
> registry re-scans.

---

## The `bizuno-clients/` convention

Client customizations are version-controlled in a separate **`bizuno-clients/`**
repo (never in `bizuno/`). The layout is one folder per client, and — for
clients with more than one site — **one subfolder per domain**:

```
bizuno-clients/
├── battery-store/
│   ├── biz.batterystore.com/myExt/      # the ERP customizations
│   └── www.batterystore.com/plugins/    # the storefront WP plugins
├── canada-scooters/Bizuno/myExt/
├── keystone-tape/Bizuno/myExt/
└── phreesoft/
    ├── biz.phreesoft.com/myExt/
    └── portals/                         # the PhreeSoft cloud entry code
```

A client folder can hold a **`myExt/`** (deployed into that site's
`BIZUNO_DATA/myExt/`), a **`plugins/`** (custom WordPress plugins for its
storefront), or both. Keeping them together means one client's entire
customization surface — ERP overrides and storefront plugins — lives in one
place.

---

## Versioning your `myExt/`

There is **no** repo-level version file tying a client's `myExt/` to a core
version, and no automated constraint check. The convention is **per-file**: each
override carries a `@version` docstring with a "Last Update" date, matching the
core file-header style:

```php
/**
 * @version    7.x Last Update: 2026-04-17
 * @filesource /BIZUNO_DATA/myExt/controllers/.../myAPI.php
 */
```

A client's `myExt/` can legitimately contain files marked for different major
versions (a `6.x` total that still works next to a `7.x` API hook) — the date
tells you when each was last verified. Treat it as documentation discipline, not
a guard: when you bump a client's core version, re-test the overrides and bump
their dates.

---

## Deployment and the `prune-vendor` hook

**Deployment is deliberately simple, not tooled.** There's no bespoke deploy
system — a client's `myExt/` reaches the live site by `git` pull or rsync of the
client subfolder into the site's `BIZUNO_DATA/`. (The PhreeSoft cloud has a
`portals/standalone/install-standalone.sh` for hardening a standalone *core*
install, but it doesn't touch `myExt/`.)

One optional hook worth knowing about: the core `composer.json` runs a
**`prune-vendor.php`** step on `post-install-cmd` / `post-update-cmd`, *but only
if* `myExt/controllers/psTools/prune-vendor.php` exists:

```json
"post-install-cmd": [
  "@php -r \"if(file_exists(__DIR__.'/myExt/controllers/psTools/prune-vendor.php')){ ... }\""
]
```

It's a per-client opt-in — today only the PhreeSoft cloud ships one. The script
prunes unused TCPDF font sets (CJK/Arabic, source TTF archives) out of `vendor/`
to shrink the distribution. If you don't provide the file, the hook is a no-op.

---

## Override, or send a PR upstream?

A judgment call worth making deliberately:

| Override in `myExt/`                                  | Send a PR to `bizuno/`                                  |
|-------------------------------------------------------|--------------------------------------------------------|
| Genuinely client-specific (one business's fee, carrier, workflow) | A bug fix, or behavior every install would want |
| Depends on that client's data / accounts / branding  | A new general-purpose feature or hook point             |
| You need it live this week, on this site              | A missing customization point others will also hit      |

Rule of thumb: if the next client would want it too, it belongs upstream. If it
only makes sense for *this* client, it belongs in their `myExt/`. And if you find
yourself wanting to override something the hooks don't expose, that's a signal to
add a hook point upstream rather than shadow a large core file.

---

## Related

- [Override hooks and myExt/](../05-administration/05-override-hooks-and-myext.md) — the hook list and loading order
- [Custom payment gateway](./02-custom-payment-gateway.md) · [Custom journal type](./03-custom-journal-type.md) · [Custom PhreeForm processor](./04-custom-phreeform-processor.md) — concrete extensions built this way
- [Cache mechanics](../05-administration/06-cache-mechanics.md) — clear the cache after changing `myExt/`
