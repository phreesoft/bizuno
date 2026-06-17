---
title: WooCommerce via bizuno-api
category: Integration
order: 1
status: published
audience: [admin, developer]
last-updated: 2026-06-16
---

# WooCommerce via `bizuno-api`

Selling on WooCommerce and keeping the books in Bizuno is one of the most common
Bizuno topologies. The **`bizuno-api`** WordPress plugin is the bridge. The thing
to get right up front is **who owns what**: each resource flows in *one*
direction, and there is no two-way merge.

---

## Architecture

`bizuno-api` is a WP plugin installed on the **WooCommerce** site. Bizuno itself
may live:

- **on the same site** (the `bizuno-accounting` plugin is active) — the bridge
  short-circuits to an in-process call, or
- **on a separate back-office host** — the bridge talks to it over HTTPS, using a
  configured Bizuno URL and API token (Settings → Bizuno → General).

Communication is **two complementary directions**, each with its own transport:

- **Plugin → Bizuno**: HTTPS calls to Bizuno's portal API (`portal/api.php`) —
  e.g. `orderAdd`, `shipGetRates` — authenticated with the Bizuno API token.
- **Bizuno → Plugin**: Bizuno calls REST endpoints the plugin registers under
  `bizuno-api/v1/` — `product/update`, `product/refresh`, `product/sync`,
  `order/confirm` — authenticated with WordPress credentials.

---

## Who owns what (data flow)

Each resource is **unidirectional**. Memorize this table and most "why didn't it
sync?" questions answer themselves:

| Resource              | Direction        | How                                            |
|-----------------------|------------------|------------------------------------------------|
| **Orders**            | Woo → Bizuno     | Plugin posts the order to `orderAdd`           |
| **Customers**         | Woo → Bizuno     | Created/matched in Bizuno when their order imports |
| **Inventory (stock)** | Bizuno → Woo     | Bizuno pushes to `product/refresh`             |
| **Prices** (+ tiers)  | Bizuno → Woo     | Bizuno pushes to `product/refresh` / `product/update` |
| **Products** (create/update) | Bizuno → Woo | Bizuno pushes to `product/update`            |
| **Shipping rates**    | Woo → Bizuno (live) | At checkout, Woo asks Bizuno via `shipGetRates` |
| **Ship confirmation** | Bizuno → Woo     | Bizuno posts tracking/status to `order/confirm` |

**WooCommerce owns orders and customers; Bizuno owns products, stock, and
pricing.** The catalog is published *down* to the store; sales come *up* to the
books.

---

## Authentication

Two separate credentials, one per direction:

- **Plugin → Bizuno:** a **shared API token**, sent as the **`X-Bizuno-Token`**
  request header and checked by Bizuno's `validateApiToken()` with a constant-time
  compare. (Bizuno then binds the call to a configured API user so permissions
  apply.)
- **Bizuno → Plugin:** the plugin's REST endpoints authenticate with a WordPress
  **`email` + `pass`** (verified via `wp_authenticate`, requiring the
  `manage_woocommerce` capability).

> **The `decrypt_password` quirk — why tokens are handled oddly.** Older installs
> stored the token/password as plaintext; a naive base64-decode of a plaintext
> value produced **binary garbage**, which a reverse-proxy WAF (mod_security)
> then rejected as a malformed header — a 400 before PHP ever saw the request.
> The fix: `decrypt_password()` only decodes values that carry the encrypted-form
> `::` separator and otherwise **returns the value verbatim**. So a plaintext
> token stays printable and passes the WAF. If you're debugging auth failures,
> this is why the token isn't blindly decoded.

---

## Failure modes (mostly WAF, in practice)

The bridge's hard-won lessons are all about getting clean requests past a
hardened reverse proxy:

- **`Authorization: Basic` was dropped entirely** — some mod_security rule sets
  flag Basic-auth patterns and 400 at the proxy. The token rides in
  `X-Bizuno-Token` instead.
- **Empty query parameters are stripped** before the request is built, to avoid
  WAF fuzzing/enumeration false positives.
- **Non-printable header bytes are avoided** (the `decrypt_password` quirk above).

When a call fails, the plugin logs the HTTP error to the WordPress message stack
and merges any `message` block from Bizuno's JSON response — there's **no
automatic retry**, so a failed sync is re-triggered, not self-healed. An empty
response is surfaced as a likely TLS/connection issue.

---

## Sync triggers — no webhooks

Be precise here, because the topology invites the wrong assumption: **`bizuno-api`
does not use WooCommerce webhooks, and does not poll.** Sync happens by:

- **Order export:** automatically on the WooCommerce **thank-you** hook after
  checkout (if "Autodownload Orders" is enabled), or manually via an **Export to
  Bizuno** button on the order.
- **Catalog push (stock/price/products):** **manual** actions in Bizuno — a bulk
  upload, or a "Quick Refresh" that batches items through `product/refresh` in an
  AJAX loop. Not scheduled.
- **Shipping rates:** **live** at checkout (Woo calls `shipGetRates` in real time).
- **Ship confirmation:** **manual** from Bizuno (pick a date, confirm → posts to
  `order/confirm`).
- **The only cron** is a WordPress cron for image/thumbnail processing — not for
  order or inventory sync.

So "near-real-time" means "on checkout and on demand," not an event stream.

---

## Conflict resolution: there isn't any

There is **no conflict detection, locking, or versioning** — and because each
resource is one-directional, you rarely need it *if you respect ownership*:

- Edit a **customer or order** in WooCommerce; the next export overwrites Bizuno's
  copy. A Bizuno-side edit to those is discarded on the next import — **last write
  from Woo wins.**
- Edit **stock or price** in Bizuno; the next push overwrites WooCommerce's copy.
  A store-admin edit there is overwritten on the next refresh — **last write from
  Bizuno wins.**

The rule that keeps you out of trouble: **edit each resource only on its owning
side.** Editing a Bizuno-owned field in WooCommerce (or vice versa) isn't merged —
it's eventually overwritten.

---

## Related

- [REST API surface](./03-rest-api-surface.md) — the `portal/api` endpoints this plugin calls, and the token model
- [EDI X12](./02-edi-x12.md) — the other major external-system integration
