---
title: REST API Surface
category: Integration
order: 3
status: draft
audience: [developer]
last-updated: 2026-06-16
---

# REST API Surface

The REST surface is the most-undocumented part of Bizuno — integrators tend to
reverse-engineer it. This page describes what's actually there: the routing
convention, the authenticated vs. unauthenticated split, the CSRF and token
models, and the response shape.

---

## The `bizRt` convention

Every authenticated request routes through one parameter:

```
?bizRt=<module>/<page>/<method>
```

`compose()` loads `controllers/<module>/<page>.php`, instantiates the
`<module><Page>` class, and calls `<method>`. There is no separate route table —
the path *is* the route. (`bizRt` must have exactly two slashes or it's rejected.)

Responses default to **JSON**, but a controller picks its own output type
(`json`, `html`, `divHTML`, `popup`, `page`, `raw`, `datagrid`, …) via the layout
it returns. So the same routing scheme serves both the browser UI and
machine callers — the difference is the `type` the controller emits.

---

## Three access tiers

### 1. The unauthenticated `api` scope (`portal/api.php`)

Requests to `portal/api/...` are handled **before login**. This is *not* the main
data API — it's a deliberately small public surface:

- **Static assets:** images, CSS, JS file serving.
- **Guest actions:** lost-password, logout, sales-tax lookup, role lookup.
- **Token-authenticated entry points:** `orderAdd`, `shipGetRates`, `ediCron` —
  reachable without a session but only with a valid **API token** (below).
- **`myAPI`:** a hook into `myExt/controllers/api/myAPI.php` for custom,
  self-authenticated endpoints (see [the myExt pattern](../06-customization/01-the-myext-pattern.md)).

### 2. The authenticated `api` module (`controllers/api/`)

After login, `?bizRt=api/<page>/<method>` reaches the real data API — `order`,
`shipping`, `payment`, `import`, `export`, `account`. These run with the logged-in
(or API-bound) user's permissions, like any other module route.

### 3. Everything else (the full app)

Any `bizRt=<module>/<page>/<method>` the user's role permits — the same routes the
UI uses. There's no separate "API version" of these; the UI *is* the API.

---

## CSRF protection

Browser-style requests are guarded in two layers, enforced in the auth path for
AJAX and form-POST requests:

1. **Origin/Referer check** — a cross-origin write attempt is rejected outright.
2. **Synchronizer token** — a per-session token (`bizCsrfGet()` →
   `bin2hex(random_bytes(16))`, rotated on login/logout) is verified with a
   constant-time compare.

The token is read, in order, from:

1. the **`X-Bizuno-Csrf`** request header (preferred — keeps the secret out of
   URLs and logs),
2. a POST `_csrf` field,
3. a GET `_csrf` parameter.

> **Version + enforcement, precisely.** This shipped **default-on in v7.4.0**
> (not "7.3.x" — there is no 7.3.9 release). By default it runs in **warn** mode
> for backward compatibility; define **`BIZUNO_CSRF_ENFORCE`** to `true` to make
> invalid/missing tokens actually reject. If you're building a browser client,
> echo back the `X-Bizuno-Csrf` token; if you're calling machine-to-machine, use
> the token model below instead.

---

## Machine-to-machine: the API token

Non-browser callers don't carry a session or a CSRF token. They authenticate with
the **API token**, sent as the **`X-Bizuno-Token`** header (POST/GET `token` also
accepted). Bizuno's `validateApiToken()` constant-time-compares it against the
configured token, then **binds the request to a configured API user** so the
normal role/permission checks apply downstream. This is exactly how the
[`bizuno-api` WooCommerce bridge](./01-woocommerce-via-bizuno-api.md) talks to
Bizuno.

For endpoints that don't fit the user/role model at all — third-party webhooks,
supplier crons — `myAPI` runs **unauthenticated by design**, and the extension
**must implement its own auth** (a shared secret header, an IP allowlist, etc.).
The portal can't know your contract, so it hands you the request and steps back.

---

## Response shape

A controller returns a layout; the renderer emits its `content`. For JSON, the
content typically carries:

- **`message`** — the message stack, grouped by level:
  `{error:[…], warning:[…], info:[…], success:[…]}`, each entry `{text, title}`.
- **`html`** / **`action`** / **`divID`** — for UI-driving responses (markup to
  inject, an action to run, the target element).
- plus any fields the controller adds.

The data-API endpoints return a tighter shape — e.g. order import answers with
`{result: 'Success'|'Fail', ID: <id>, messages: […]}`. When integrating, read
`result`/`ok` for status and `message`/`messages` for the human-readable detail.

---

## Versioning — there isn't one (yet)

There is **no API version marker** — no version in the URL, header, or response.
The contract is implicitly the running release. Treat breaking changes as
tied to **major version bumps**, pin the Bizuno version you integrated against,
and re-test on upgrade. Don't assume a stable versioned contract that doesn't
exist.

---

## `bizRt` vs. a custom `myExt/` endpoint

| Use `bizRt=<module>/<page>/<method>`            | Use `myExt/controllers/api/myAPI.php`              |
|-------------------------------------------------|----------------------------------------------------|
| Built-in business logic, with role/permission enforcement | A webhook/cron from an external system you control |
| You want Bizuno's auth, validation, and caching | You need a custom auth scheme (shared secret, IP allowlist) or a public endpoint |
| User-initiated or token-bound API-user calls    | System-to-system, outside the user/role model      |

Rule of thumb: **`bizRt` for user-facing/permissioned APIs; `myAPI` for
system-to-system webhooks where you own both ends and enforce your own auth.** See
[The myExt/ pattern](../06-customization/01-the-myext-pattern.md).

---

## Related

- [WooCommerce via bizuno-api](./01-woocommerce-via-bizuno-api.md) — the canonical token-authenticated consumer of this surface
- [The myExt/ pattern](../06-customization/01-the-myext-pattern.md) — building a custom `myAPI` endpoint
- [EDI X12](./02-edi-x12.md) — `ediCron` runs through the token-authenticated portal API
