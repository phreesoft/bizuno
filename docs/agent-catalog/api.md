---
title: API / Integration — Agent Action Catalog
module: api
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# API / Integration — Agent Action Catalog

Machine-readable actions for the `api` module — Bizuno's integration surface for
e-commerce carts (WooCommerce, Amazon, BigCommerce, Google, Stripe), 3rd-party
shipping-rate lookups, EDI cron polling, sales-tax quoting, and the PhreeSoft
cloud control plane. Read the [catalog schema and conventions](./README.md)
first; this file assumes the route, auth-level, and field conventions defined
there.

This module has **two distinct entry surfaces** and they authenticate
differently — get this right before automating anything:

- **`api/<page>/<method>`** (e.g. `api/admin/adminHome`) — dispatched by the
  normal authenticated `compose()` path. These run **inside a logged-in Bizuno
  session**; the ones that guard themselves call `validateAccess($secID,$level)`.
- **`portal/api/<method>`** (e.g. `portal/api/orderAdd`) — dispatched by
  `portalCtl::goAPI()` *before* authentication. These are reachable by an
  anonymous HTTP caller. The state-changing ones (`orderAdd`, `ediCron`,
  `shipGetRates`) gate themselves with **`validateApiToken()`** — a constant-time
  `hash_equals()` check of the caller-supplied token (header `X-Bizuno-Token`,
  then POST `token`, then GET `token`) against `api.settings.phreesoft_api.api_token`.
  It **fails closed**: if no token is configured the route refuses all access.
  These routes do **not** call `validateAccess()`; instead `orderAdd`/`ediCron`
  bind the request to the configured `api_user` via `loadApiUserContext()` and
  run as that user's role.

## Data model summary

```yaml
two_surfaces:
  authed_compose:           # api/<page>/<method> — runs in a logged-in session
    dispatcher: portal/controller.php::goAuth → compose()
    auth: validateAccess(sec_id, level)   # only where the method calls it
  portal_pre_auth:          # portal/api/<method> — runs BEFORE login (anonymous HTTP reachable)
    dispatcher: portal/controller.php::goAPI() → portalApi
    auth: validateApiToken()              # X-Bizuno-Token | POST token | GET token; constant-time; fails closed
    token_setting: api.settings.phreesoft_api.api_token
    api_user_setting: api.settings.phreesoft_api.api_user   # email of ctype_u contact the call runs as
settings_keys:              # api.settings.bizuno_api.* drive GL posting + auto-detect
  auto_detect: ''           # ''=Auto (pick jID per stock), '10'=force Sales Order, '12'=force Sales Invoice
  gl_receivables: ''        # falls back to phreebooks customers.gl_receivables
  gl_sales:       ''        # falls back to phreebooks customers.gl_sales
  gl_discount:    ''
  gl_tax:         ''
  tax_rate_id:    0
channels:                   # funnel methods loaded dynamically by modID
  ifAmazon: Amazon
  ifBigCom: BigCommerce
  ifGoogle: Google
  ifStripe: Stripe
  ifWooCommerce: WooCommerce
  # ifWalmart present on disk, not enabled in $channels
gl_impact: SCOPED           # only api/order/add (via portal/api/orderAdd) posts a journal — see warning
inventory_impact: SCOPED    # only jID 12 (Sales Invoice) relieves stock + COGS
```

> **Key safety fact for an acting agent:** the **only** route in this module that
> directly posts to the general ledger and moves inventory is
> **`api.order.import`** (`api/order/add`, normally reached through the token
> wrapper `portal/api/orderAdd`). It translates a cart payload into a phreebooks
> `main/save` and posts journal **10 (Sales Order — no stock move)** or **12
> (Sales Invoice — relieves stock + COGS)**. The journal is chosen by the
> `auto_detect` setting, or — when `auto_detect` is blank — per-SKU stock levels
> via `getStockLevels()`. It is **NOT idempotent**: it forces `id=0` (always a
> new journal) and does **no PurchaseOrderID de-dup**, so re-sending the same
> cart posts a duplicate order. The channel dispatchers under `api/admin/*`
> (e.g. `ordersGo`, `reconcileGo`, `paymentProcess`) and `portal/api/ediCron`
> can also post indirectly through other modules. Everything else here is read-
> only / config / lookup.
>
> **`portal/api/myAPI` is intentionally unauthenticated** — it is a pass-through
> to a customer-supplied `myExt/controllers/api/myAPI.php::goAction()`. The
> portal layer runs it with **no token check**; the extension MUST do its own
> auth. It is not catalogued as a Bizuno action because its behavior is defined
> entirely in client `myExt/` code, not the core. Treat it as an untrusted,
> per-deployment surface.

---

## api.order.import

```yaml
id: api.order.import
title: Import a cart order into Bizuno (posts a sales journal)
route: api/order/add
http_method: POST
ui_path: (no UI — REST/cart integration; normally reached via portal/api/orderAdd)
auth:
  sec_id: NONE at this route   # api/order/add itself does NOT call validateAccess
  min_level: n/a               # relies on the portal/api/orderAdd token wrapper for auth (see api.order.import.token)
preconditions:
  - api.settings.bizuno_api GL accounts resolvable (fall back to phreebooks customers.* defaults)
  - phreebooks journals 10/12 enabled; the posting user holds j10_mgr/j12_mgr/prices_c (the token wrapper escalates these to level 2)
  - cart payload mapped to the General/Billing/Shipping/Item/Payment array shape
inputs:
  required:
    - name: General
      format: array
      source: post
      notes: PurchaseOrderID, OrderDate, OrderTotal, ShippingTotal, ShippingCarrier, SalesTaxAmount, OrderNotes
    - name: Billing
      format: array
      source: post
      notes: PrimaryName, Contact, Address1/2, City, State, PostalCode, Country, Telephone, Email — Email matches an existing ctype_c contact, else contact_id_b=0
    - name: Shipping
      format: array
      source: post
      notes: same address shape as Billing
    - name: Item
      format: array
      source: post
      notes: list of {ItemID(sku), Description, Quantity, TotalPrice}; each SKU drives getStockLevels() jID selection when auto_detect blank
    - name: Payment
      format: array
      source: post
      notes: Method, Title, Status, TransactionID — status auth/authorize/on-hold/processing → 'auth', else 'cap'
  optional: []
  fixed:
    - name: id
      value: 0
      notes: forces a NEW journal entry every call — the source of non-idempotency
    - name: waiting
      value: 1
    - name: AddUpdate_b
      value: 1
    - name: journal_id
      value: 10 or 12
      notes: jID = api.settings.bizuno_api.auto_detect if set; if blank, getStockLevels() picks 12 when on-hand qty>=ordered (single store) or a store has stock (multi-store FIFO), else 10
    - name: store_id
      value: 0
      notes: overwritten to a stocked store id when multi-store FIFO finds coverage
    - name: terms
      value: 0
effects:
  db_writes:
    - table: journal_main
      op: insert
    - table: journal_item
      op: insert/update
      notes: setJournalPayment() appends ;method;title;status and trans_code to the ttl (total) line
    - table: inventory
      op: update (qty_stock)
      notes: only when jID resolves to 12 (Sales Invoice)
  gl_journal: >
    POSTS — jID 10 (Sales Order, no GL/stock effect beyond the order) or jID 12
    (Sales Invoice: debits AR / credits sales + tax + freight, relieves COGS).
    Receivable GL = api.settings.bizuno_api.gl_receivables (→ customers.gl_receivables);
    line GL = bizuno_api.gl_sales (→ customers.gl_sales). See Journal ID taxonomy.
  inventory: >
    MOVES STOCK only when jID=12 — relieves qty_stock and posts COGS per item.
    jID=10 reserves the order without a stock movement.
  side_effects:
    - resolves customer by Billing.Email (ctype_c) — unknown email posts with contact_id_b=0 (no contact attached)
    - guesses ship method from ShippingCarrier (fedex:GND/1DA/2DA/FRT) and payment method (payfabric/paypal/converge)
    - US sales tax routed to totals_tax_other (if tax_other enabled, sets tax_exempt=1) else totals_tax_rest
    - emits a 'caution' message that the payment must be completed manually in Bizuno (no auto-capture)
returns:
  success_signal: JSON {result:'Success', ID:<journal rID>, messages:[...]}
  identifier: ID = new journal_main rID (empty/Fail if save did not return an rID)
errors:
  - "result:'Fail' with empty ID if the phreebooks save rejected the payload"
  - "caution: order authorized but no Authorization Code — must be completed in Bizuno and at the merchant"
idempotency: >
  NOT idempotent. Forces id=0 and does NO de-dup on PurchaseOrderID — re-sending
  the same cart posts a SECOND order. Before retrying after a timeout, query
  journal_main by purch_order_id to confirm whether the prior call landed.
related: [api.order.import.token, api.shipping.rates, api.admin.settings.save]
confidence: high
source: src/controllers/api/order.php:50 (add), :63 (apiJournalEntry), :76 (mapPost), :165 (getStockLevels)
```

## api.order.import.token

```yaml
id: api.order.import.token
title: Token-gated cart order import (the public REST entry)
route: portal/api/orderAdd
http_method: POST
ui_path: (no UI — public REST endpoint, e.g. WooCommerce → Bizuno)
auth:
  sec_id: NONE (validateApiToken instead of validateAccess)
  min_level: n/a
  token: X-Bizuno-Token header | POST token | GET token, vs api.settings.phreesoft_api.api_token (constant-time, fails closed)
preconditions:
  - api.settings.phreesoft_api.api_token configured (else the route refuses all access)
  - api.settings.phreesoft_api.api_user set to an existing ctype_u contact email (the call runs as that user)
inputs:
  required:
    - name: token
      format: cmd
      source: get/post/header
      notes: prefer the X-Bizuno-Token header to keep the secret out of URL logs/Referer
    # plus the full General/Billing/Shipping/Item/Payment payload — see api.order.import
  optional: []
  fixed:
    - name: (role escalation)
      value: prices_c>=2, j10_mgr>=2, j12_mgr>=2
      notes: forced onto the api_user's role cache so a token caller can post the order
effects:
  db_writes: (same as api.order.import — delegates to compose('api','order','add'))
  gl_journal: (same as api.order.import — POSTS jID 10 or 12)
  inventory: (same as api.order.import — stock moves only on jID 12)
  side_effects:
    - loadBusinessCache() then loadApiUserContext() binds the request to api_user before composing
returns:
  success_signal: JSON {result:'Success', ID:<rID>, messages}
  identifier: ID = journal_main rID
errors:
  - "'Illegal Access' if token missing/mismatched or not configured (returns before any write)"
  - "'API user not configured' / 'API user not found' if api_user is unset or unresolvable"
idempotency: NOT idempotent — same as api.order.import (forces id=0, no PO de-dup)
related: [api.order.import, api.ediCron.token, api.admin.settings.save]
confidence: high
source: src/portal/api.php:307 (orderAdd), :411 (validateApiToken), :442 (loadApiUserContext)
```

## api.shipping.rates

```yaml
id: api.shipping.rates
title: Quote live shipping rates for a destination (authed compose path)
route: api/shipping/getRates
http_method: GET
ui_path: (no UI — cart/checkout rate lookup)
auth:
  sec_id: NONE at this route   # getRates does NOT call validateAccess
  min_level: n/a               # public when reached directly; token-gated via portal/api/shipGetRates
preconditions:
  - shipping carriers configured in the shipping module
  - destination postcode supplied (method returns empty rates without it)
inputs:
  required:
    - name: postcode
      format: alpha_num
      source: get
      notes: required — no postcode yields an empty rates array
  optional:
    - name: country
      format: alpha_num
      source: get
      notes: ISO2 (US); normalized to ISO2 downstream
    - name: state
      format: alpha_num
      source: get
    - name: city
      format: alpha_num
      source: get
    - name: address
      format: text
      source: get
    - name: address_1
      format: text
      source: get
    - name: address_2
      format: text
      source: get
    - name: totalWeight
      format: float
      source: get
      notes: package weight; defaults to 1 downstream
  fixed:
    - name: num_boxes
      value: 1
    - name: ltl_class
      value: 60
    - name: residential
      value: 1
    - name: ship_date
      value: today
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - delegates to api/export/shippingRates → shipping/rate.php::rateAPI() (calls live carrier APIs)
returns:
  success_signal: JSON {pkg:{...}, rates:[...]}
  identifier: none (rate quotes, not stored)
errors:
  - "empty rates array if postcode missing or carriers return nothing"
idempotency: safe (read-only lookup; live carrier call but no Bizuno state change)
related: [api.shipping.rates.token, api.export.shippingRates, api.order.import]
confidence: high
source: src/controllers/api/shipping.php:60 (getRates)
```

## api.shipping.rates.token

```yaml
id: api.shipping.rates.token
title: Token-gated public shipping-rate lookup
route: portal/api/shipGetRates
http_method: GET
ui_path: (no UI — public REST rate endpoint)
auth:
  sec_id: NONE (validateApiToken instead of validateAccess)
  min_level: n/a
  token: X-Bizuno-Token | POST token | GET token vs phreesoft_api.api_token (constant-time, fails closed)
preconditions:
  - api.settings.phreesoft_api.api_token configured
inputs:
  required:
    - name: token
      format: cmd
      source: get/post/header
    - name: postcode
      format: alpha_num
      source: get
  optional: (same destination fields as api.shipping.rates)
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loadBusinessCache() then compose('api','shipping','getRates')
returns:
  success_signal: JSON {pkg, rates}
  identifier: none
errors:
  - "'Illegal Access' if token missing/mismatched/unconfigured"
idempotency: safe (read-only)
related: [api.shipping.rates]
confidence: high
source: src/portal/api.php:300 (shipGetRates)
```

## api.ediCron.token

```yaml
id: api.ediCron.token
title: Poll all EDI sources for new orders (token-gated cron)
route: portal/api/ediCron
http_method: GET
ui_path: (no UI — scheduled cron; e.g. ?bizRt=portal/api/ediCron&token=<api_token>)
auth:
  sec_id: NONE (validateApiToken instead of validateAccess)
  min_level: n/a
  token: X-Bizuno-Token | POST token | GET token vs phreesoft_api.api_token (constant-time, fails closed)
preconditions:
  - api.settings.phreesoft_api.api_token + api_user configured
  - EDI sources configured in phreebooks (ediAPI)
inputs:
  required:
    - name: token
      format: cmd
      source: get/post/header
  optional: []
  fixed: []
effects:
  db_writes:
    - table: (phreebooks journals — depends on what ediGet ingests)
      op: insert
      notes: delegates to compose('phreebooks','ediAPI','ediGet') — may post orders/invoices
  gl_journal: >
    MAY POST — ediGet ingests EDI documents which can create phreebooks journals.
    Effect lives in the phreebooks ediAPI controller, not this module. Verify
    against that controller before treating as GL-neutral.
  inventory: MAY MOVE — if ingested documents post invoices that relieve stock
  side_effects:
    - loadBusinessCache() + loadApiUserContext() binds the run to api_user
returns:
  success_signal: layout populated by ediGet (per-source result)
  identifier: depends on ediAPI
errors:
  - "'Illegal Access' if token invalid; 'API user not configured/found' if api_user unset"
idempotency: >
  Depends on the phreebooks ediAPI source dedup logic — NOT guaranteed idempotent
  at this layer. Confirm the ediAPI source de-dups documents before scheduling.
related: [api.order.import.token]
confidence: medium   # downstream posting behavior lives in phreebooks/ediAPI, not verified here
source: src/portal/api.php:328 (ediCron); delegates to controllers/phreebooks/ediAPI.php::ediGet
```

## api.export.shippingRates

```yaml
id: api.export.shippingRates
title: Compute carrier rates from a package structure (internal compose target)
route: api/export/shippingRates
http_method: POST
ui_path: (no UI — internal; backs api/shipping/getRates and cart rate calls)
auth:
  sec_id: NONE   # no validateAccess; reached via getRates or other composers
  min_level: n/a
preconditions:
  - $layout['pkg']['destination'] populated by the caller (this method reads from the layout, not clean())
  - shipping carriers configured
inputs:
  required:
    - name: layout.pkg.destination
      format: array
      source: (composer layout, not request)
      notes: address/address_1/city/state/postcode/country/totalWeight assembled by the caller
  optional:
    - name: layout.pkg.cart_subtotal
      format: float
      source: (composer layout)
      notes: order_total used for free-shipping thresholds; defaults 0
  fixed:
    - name: residential / num_boxes / ltl_class / verify_add
      value: 1 / 1 / 60 / true
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - instantiates shipping/rate.php::shippingRate and calls rateAPI() (live carrier APIs)
returns:
  success_signal: layout.rates populated with carrier/service/cost rows
  identifier: none
errors:
  - "empty rates if carriers reject the package or address fails verification"
idempotency: safe (read-only rate quote)
related: [api.shipping.rates, api.shipping.rates.token]
confidence: high
source: src/controllers/api/export.php:122 (shippingRates)
```

## api.export.sync

```yaml
id: api.export.sync
title: List SKUs flagged for cart sync (which products to push to a store)
route: api/export/apiSync
http_method: GET
ui_path: (no UI — channel sync driver)
auth:
  sec_id: NONE   # apiSync does NOT call validateAccess
  min_level: n/a
preconditions:
  - inventory rows carry a sync flag column (default 'cart_sync', overridable via layout.data.syncTag)
inputs:
  required: []
  optional:
    - name: syncDelete
      format: integer
      source: get
      notes: echoed into layout.data.syncDelete for the caller to act on deletions
    - name: layout.data.syncTag
      format: text
      source: (composer layout)
      notes: which inventory boolean column marks a SKU for sync; defaults to cart_sync
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads inventory WHERE <syncTag>='1' and returns the SKU list as JSON
returns:
  success_signal: layout.data.syncSkus = JSON array of SKUs
  identifier: SKU list (no per-row id)
errors: []
idempotency: safe (read-only)
related: [api.export.product, api.admin.channel.dispatch]
confidence: high
source: src/controllers/api/export.php:106 (apiSync)
```

## api.export.product

```yaml
id: api.export.product
title: Enrich a single inventory item for export to a store (price, images, attributes)
route: api/export/apiInventory
http_method: POST
ui_path: (no UI — invoked per-product during channel push)
auth:
  sec_id: NONE (uses setSecurityOverride internally)
  min_level: n/a
  notes: forces prices_c=1 and j12_mgr=1 via setSecurityOverride to quote a price
preconditions:
  - product['RecordID'] is a valid inventory id
inputs:
  required:
    - name: product
      format: array
      source: (passed by reference from caller)
      notes: must contain RecordID (inventory id); enriched in place
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - calls inventory/prices/quote to compute Price/RegularPrice/SalePrice/PriceLevels
    - base64-encodes the primary image + invImages gallery into the product structure
    - resolves invAccessory ids to SKUs and bizProAttr attributes to labeled values
returns:
  success_signal: $product enriched with Price, Images[], Attributes[], etc. (by reference)
  identifier: none
errors:
  - "msgDebug 'Bad ID passed' if RecordID empty/invalid (no enrichment)"
  - "msgAdd if an image file referenced by the SKU is missing on disk"
idempotency: safe (read-only enrichment; setSecurityOverride is in-memory only)
related: [api.export.sync, api.admin.channel.dispatch]
confidence: medium   # invoked by funnel channels with a prepared $product array, not a clean request shape
source: src/controllers/api/export.php:37 (apiInventory)
```

## api.impexp.console

```yaml
id: api.impexp.console
title: Render the Import/Export console (aggregates per-module API tabs)
route: api/import/impExpMain
http_method: GET
ui_path: Tools ▸ Import / Export
auth:
  sec_id: impexp
  min_level: 2
preconditions:
  - one or more modules registered an API import/export hook in bizuno.api cache
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loops registered module API hooks (e.g. phreebooksAPI) and lets each add a tab to the console
returns:
  success_signal: layout with the Import/Export tabs UI
  identifier: none
errors:
  - "returns silently if user lacks impexp level 2"
idempotency: safe (read-only render)
related: [api.admin.settings]
confidence: high
source: src/controllers/api/import.php:45 (impExpMain)
```

## api.admin.settings

```yaml
id: api.admin.settings
title: Render the API module settings form
route: api/admin/adminHome
http_method: GET
ui_path: Settings ▸ API (Integration)
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - builds the settings structure: bizuno_api (auto_detect, gl_receivables/sales/discount/tax, tax_rate_id),
      phreesoft_api (api_user, api_pass, api_token), sales_tax_api (geocodio_key, ziptax_key)
returns:
  success_signal: settings form layout
  identifier: none
errors:
  - "returns silently if user lacks admin level 1"
idempotency: safe (read-only)
related: [api.admin.settings.save]
confidence: high
source: src/controllers/api/admin.php:296 (adminHome), :65 (settingsStructure)
```

## api.admin.settings.save

```yaml
id: api.admin.settings.save
title: Save the API module settings (GL defaults, token, sales-tax keys)
route: api/admin/adminSave
http_method: POST
ui_path: Settings ▸ API (Integration) ▸ Save
auth:
  sec_id: NONE   # adminSave does NOT call validateAccess — relies on the admin-gated UI to reach it
  min_level: n/a
preconditions:
  - posted under an authenticated admin session (the settings page that loads it is admin-gated)
inputs:
  required: []
  optional:
    - name: bizuno_api[auto_detect]
      format: select
      source: post
      notes: ''=Auto, 10=force Sales Order, 12=force Sales Invoice — directly controls api.order.import GL/stock behavior
    - name: bizuno_api[gl_receivables]
      format: ledger
      source: post
    - name: bizuno_api[gl_sales]
      format: ledger
      source: post
    - name: bizuno_api[gl_discount]
      format: ledger
      source: post
    - name: bizuno_api[gl_tax]
      format: ledger
      source: post
    - name: bizuno_api[tax_rate_id]
      format: select
      source: post
    - name: phreesoft_api[api_user]
      format: text
      source: post
      notes: email of the ctype_u contact that token-gated routes run as
    - name: phreesoft_api[api_pass]
      format: password
      source: post
    - name: phreesoft_api[api_token]
      format: password
      source: post
      notes: the shared secret validateApiToken() checks — changing it rotates the token for ALL portal/api callers
    - name: sales_tax_api[geocodio_key]
      format: password
      source: post
    - name: sales_tax_api[ziptax_key]
      format: password
      source: post
  fixed: []
effects:
  db_writes:
    - table: (module settings store — bizuno_settings / registry for module 'api')
      op: update
  gl_journal: none
  inventory: none
  side_effects:
    - changing api_token immediately invalidates the old token for every portal/api caller
    - changing auto_detect changes how future api.order.import calls choose jID 10 vs 12
returns:
  success_signal: settings persisted (readModuleSettings)
  identifier: none
errors: []
idempotency: idempotent — re-saving the same values yields the same settings
related: [api.admin.settings, api.order.import, api.order.import.token]
confidence: high
source: src/controllers/api/admin.php:305 (adminSave)
```

## api.admin.validateCreds

```yaml
id: api.admin.validateCreds
title: Validate a channel's API credentials
route: api/admin/validateCreds
http_method: POST
ui_path: Settings ▸ <channel> ▸ Validate Credentials
auth:
  sec_id: admin
  min_level: 1
preconditions:
  - modID names an enabled funnel channel (ifAmazon | ifBigCom | ifGoogle | ifStripe | ifWooCommerce)
inputs:
  required:
    - name: modID
      format: cmd
      source: get
      notes: channel id; resolves the funnel class via methods_funnels meta
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - delegates to <channel>::validateCreds($layout) — performs a test call against the channel's API
returns:
  success_signal: channel validation result in layout
  identifier: none
errors:
  - "'illegal_access' if user lacks admin level 1"
  - "'Bad channel ID!' if modID does not resolve to an installed channel"
idempotency: safe (read-only credential probe)
related: [api.admin.channel.home, api.admin.settings.save]
confidence: high
source: src/controllers/api/admin.php:123 (validateCreds), :95 (getMethod)
```

## api.admin.channel.home

```yaml
id: api.admin.channel.home
title: Render a channel's admin home page
route: api/admin/home
http_method: GET
ui_path: Customers (or channel menu) ▸ <channel>
auth:
  sec_id: NONE   # home() does NOT call validateAccess (the menu entry is admin-built, but the route is ungated)
  min_level: n/a
preconditions:
  - modID names an installed funnel channel
inputs:
  required:
    - name: modID
      format: cmd
      source: get
      notes: channel id
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - delegates to <channel>::home($layout) — renders that channel's control panel
returns:
  success_signal: channel home layout
  identifier: none
errors:
  - "'Bad channel ID!' if modID unresolved"
idempotency: safe (read-only render)
related: [api.admin.channel.dispatch, api.admin.validateCreds]
confidence: medium   # the rendered content and any lazy state depend on the per-channel funnel class
source: src/controllers/api/admin.php:111 (home), :95 (getMethod)
```

## api.admin.channel.dispatch

```yaml
id: api.admin.channel.dispatch
title: Dispatch a channel operation (cart sync, order/inventory pull, reconcile, payment, OAuth)
route: api/admin/{cartSync|cartConfirm|confirmGo|inventoryGo|inventoryNew|invRefresh|invRefreshNext|ordersGo|productToStore|apiInvCount|OAuthCallBack|reconcileGo|reconcileList|paymentFileForm|paymentProcess}
http_method: POST (most) / GET (OAuthCallBack, *List)
ui_path: <channel> control panel ▸ (Sync / Pull Orders / Pull Inventory / Reconcile / Process Payments)
auth:
  sec_id: NONE   # none of these dispatch methods call validateAccess
  min_level: n/a
  notes: >
    several escalate the in-memory role before delegating — inventoryGo/inventoryNew force
    prices_c=1 and inv_mgr=1; orderAdd's wrapper (not these) escalates j10/j12. Treat as
    privileged.
preconditions:
  - modID names an installed funnel channel; the channel's credentials validated
inputs:
  required:
    - name: modID
      format: cmd
      source: get
      notes: channel id selecting which funnel class handles the call
  optional: []  # per-method args vary; forwarded to the channel funnel class
  fixed: []
effects:
  db_writes:
    - table: (varies by channel + method — inventory, journal_main/journal_item, contacts)
      op: insert/update
      notes: ordersGo/confirmGo/reconcileGo/paymentProcess can ingest orders and post payments
  gl_journal: >
    MAY POST — ordersGo, reconcileGo, paymentProcess and similar can create
    phreebooks journals / cash receipts through the channel funnel. Effect lives
    in the per-channel funnel class, not this dispatcher. DO NOT assume GL-neutral.
  inventory: >
    MAY MOVE — inventoryGo/inventoryNew/invRefresh push/refresh stock; order
    ingestion can relieve stock. Verify the specific channel method.
  side_effects:
    - inventoryGo/inventoryNew escalate role (prices_c=1, inv_mgr=1) before delegating
    - OAuthCallBack completes an OAuth handshake and may store tokens for the channel
returns:
  success_signal: per-channel layout/result
  identifier: per-channel (e.g. ingested order ids)
errors:
  - "'Bad channel ID!' if modID unresolved"
idempotency: >
  Channel- and method-dependent and largely NOT idempotent (order/payment
  ingestion). Verify the specific funnel class behavior before automated use.
related: [api.admin.channel.home, api.export.sync, api.export.product]
confidence: low   # umbrella over many per-channel methods whose effects live outside this module
source: src/controllers/api/admin.php:117-269 (cartSync…paymentProcess), :95 (getMethod)
```

## api.admin.getRoles

```yaml
id: api.admin.getRoles
title: Return this business's role list (PhreeSoft admin-server pull)
route: api/admin/getRoles
http_method: GET
ui_path: (no UI — PhreeSoft control-plane call)
auth:
  sec_id: NONE   # only checks that bizID is non-empty — NO IP or token check on this twin
  min_level: n/a
  notes: >
    the portal twin (portal/api/getBizRoles) additionally enforces the PhreeSoft IP
    allowlist + optional token via validatePSrequest(); THIS api/admin/getRoles route
    only requires a non-empty bizID. Prefer the portal twin for the control plane.
preconditions:
  - bizID non-empty
inputs:
  required:
    - name: bizID
      format: alpha_num
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads bizuno_role meta and returns [{roleID, label}, ...]
returns:
  success_signal: layout.content.roles = role list
  identifier: roleID per role
errors:
  - "'Illegal Access!' if bizID empty"
idempotency: safe (read-only)
related: [api.account.wallet.list]
confidence: medium   # weakly gated (bizID-only); the portal twin getBizRoles is the hardened path
source: src/controllers/api/admin.php:276 (getRoles); cf. src/portal/api.php:250 (getBizRoles, IP+token gated)
```

## api.account.wallet.list

```yaml
id: api.account.wallet.list
title: List a contact's saved payment wallet (Bizuno-side REST responder)
route: api/account/account_wallet_list
http_method: GET
ui_path: (no UI — WooCommerce account "Wallet" tab calls this over REST)
auth:
  sec_id: NONE at the method   # auth handled by the WP REST registration / bizuno_open(), not validateAccess
  min_level: n/a
preconditions:
  - the request carries a contactID (a contact short_name) that resolves to a contacts row
inputs:
  required:
    - name: contactID
      format: text
      source: get (REST query param via bizuno_open)
      notes: matched against contacts.short_name
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - resolves contactID → contacts.id, then $io->accountWalletList(cID) to fetch stored cards/wallet
returns:
  success_signal: WP_REST_Response {wallet:[...]}
  identifier: wallet entries (no new id created)
errors:
  - "empty wallet if contactID does not resolve to a contact"
idempotency: safe (read-only)
related: [api.payment.wallet]
confidence: medium   # invoked through the WP REST bridge (bizuno_open/bizuno_close), not the AJAX bizRt path
source: src/controllers/api/account.php:183 (account_wallet_list)
```

## api.payment.wallet

```yaml
id: api.payment.wallet
title: Wallet add/delete/list REST bridge (PayFabric card vault)
route: api/payment/{wallet_add_response|wallet_delete_response|wallet_list_response}
http_method: GET
ui_path: (no UI — checkout / account wallet management over REST)
auth:
  sec_id: NONE at the method   # gated by $this->bizActive and a valid pfID, not validateAccess
  min_level: n/a
preconditions:
  - $this->bizActive true (Bizuno bridge initialized)
  - a valid PayFabric id (pfID) supplied via rest_open()
inputs:
  required:
    - name: pfID
      format: (resolved in rest_open)
      source: rest (request body/query)
      notes: PayFabric customer/wallet id; empty pfID returns HTTP 400
  optional: []
  fixed: []
effects:
  db_writes:
    - table: (payment wallet — via compose('payment','wallet', add|delete|list))
      op: insert/delete (add/delete responders) / none (list)
  gl_journal: none
  inventory: none
  side_effects:
    - delegates to the payment module's wallet controller; add/delete mutate the stored card vault
returns:
  success_signal: rest_close(data, 200) — HTTP 200 with wallet data
  identifier: wallet/card entries
errors:
  - "HTTP 400 'Bad payfabric ID passed' if pfID empty"
  - "no-op if bizActive is false"
idempotency: >
  list is safe; add/delete are NOT idempotent in general (a repeated add may store
  a duplicate card). Verify the payment/wallet controller before automating.
related: [api.account.wallet.list]
confidence: low   # behavior delegated to the payment module + PayFabric; reached via the WP REST bridge, not bizRt
source: src/controllers/api/payment.php:56 (wallet_add_response), :83 (wallet_delete_response), :114 (wallet_list_response)
```

---

## Common agent recipes

```yaml
recipe_push_order_from_cart:
  goal: Post an e-commerce order into Bizuno as a sales journal
  steps:
    - ensure: api.settings.phreesoft_api.api_token AND api_user are configured (api.admin.settings.save)
    - action: api.order.import.token
      with: {token: <secret via X-Bizuno-Token header>, General, Billing, Shipping, Item, Payment}
      capture: ID   # journal_main rID
  notes: >
    NOT idempotent — before retrying after a timeout, query journal_main by
    purch_order_id to avoid posting a duplicate order. Decide jID intent up front:
    set bizuno_api.auto_detect=10 (order only) or 12 (invoice + stock relief), or
    leave blank to let stock levels choose.

recipe_quote_shipping_for_cart:
  goal: Get live carrier rates for a checkout address (no Bizuno state change)
  steps:
    - action: api.shipping.rates.token   # or api.shipping.rates inside a session
      with: {token, postcode, country, state, city, totalWeight}
  notes: read-only; safe to call repeatedly.

recipe_sync_products_to_store:
  goal: Push flagged inventory items to an external store
  steps:
    - action: api.export.sync            # list SKUs flagged cart_sync
      capture: syncSkus
    - for each SKU: api.export.product   # enrich price/images/attributes
    - action: api.admin.channel.dispatch # productToStore / inventoryGo per channel (PRIVILEGED, verify channel)
  notes: the dispatch step can move stock and is channel-specific — verify the funnel class.

recipe_rotate_api_token:
  goal: Rotate the shared secret used by all portal/api callers
  steps:
    - action: api.admin.settings.save
      with: {phreesoft_api[api_token]: <new secret>}
  notes: rotating immediately breaks every integration still using the old token — coordinate the cutover.
```

## Open questions / verify-before-automating

- **Ungated `portal/api`-class writes via the compose surface.** `api/order/add`
  (`order.php:50`), `api/shipping/getRates` (`shipping.php:60`),
  `api/export/shippingRates` (`export.php:122`) and `api/export/apiSync`
  (`export.php:106`) do **not** call `validateAccess()` themselves. They are
  meant to be reached only through the token-gated `portal/api/*` wrappers
  (`validateApiToken()`), but the bare `api/<page>/<method>` routes still resolve
  through the authenticated `compose()` path. Confirm your deployment does not
  expose them to under-privileged sessions.
- **Ungated admin/channel routes.** `api/admin/adminSave` (`admin.php:305`),
  `api/admin/home` (`admin.php:111`), and the channel dispatchers
  `api/admin/{cartSync,cartConfirm,confirmGo,inventoryGo,inventoryNew,invRefresh,invRefreshNext,ordersGo,productToStore,apiInvCount,OAuthCallBack,reconcileGo,reconcileList,paymentFileForm,paymentProcess}`
  (`admin.php:117-269`) carry **no `validateAccess()` guard** — only
  `adminHome` (`:296`) and `validateCreds` (`:123`) do. Several escalate the
  in-memory role (`inventoryGo`/`inventoryNew` set `prices_c=1`, `inv_mgr=1`).
  Treat the dispatchers as privileged and confirm they are unreachable by
  low-privilege sessions before automating.
- **`api/admin/getRoles` (`admin.php:276`) is weakly gated** — it only checks
  that `bizID` is non-empty. Its portal twin `portal/api/getBizRoles`
  (`portal/api.php:250`) adds the PhreeSoft IP allowlist plus optional token via
  `validatePSrequest()`. Prefer the portal twin for any control-plane use.
- **`portal/api/myAPI` is unauthenticated by design** (`portal/api.php:359`) —
  the portal runs the customer's `myExt/.../myAPI.php::goAction()` with no token
  check. Anything that extension does is internet-reachable unless the extension
  authenticates itself. Audit the client `myExt/` before relying on it.
- **`api.ediCron.token`, `api.admin.channel.dispatch`, and `api.payment.wallet`
  delegate their real effects to other modules** (phreebooks `ediAPI`, the
  per-channel funnel classes, and `payment/wallet`). Their GL/inventory/idempotency
  behavior is *not* fully determinable from the `api` module alone — verify the
  downstream controller before wiring them into an automated posting flow
  (`confidence: medium`/`low` accordingly).
- **`api.order.import` is the single GL/stock-moving action here and is not
  idempotent** (forces `id=0`, no `PurchaseOrderID` de-dup). Any automated retry
  must pre-check `journal_main.purch_order_id`.