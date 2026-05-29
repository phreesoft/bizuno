---
title: Payment — Agent Action Catalog
module: payment
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# Payment — Agent Action Catalog

Machine-readable actions for the `payment` module — the gateway-abstraction and
stored-card (wallet) layer that sits **underneath** the PhreeBooks cash-receipt
and credit-memo journals. Read the [catalog schema and conventions](./README.md)
first; this file assumes the route, auth-level, and field conventions defined
there.

Pages in this module: `main` (gateway dispatch + payment-method render),
`wallet` (stored card / e-check management, gateway-hosted), `admin` (module
settings + GL defaults), `adminNacha` (ACH bank-account CRUD that feeds NACHA
generation), and `nacha` (NACHA/ACH batch-file generation). Per-gateway code
lives under `gateways/<code>/<code>.php` (authorizenet, payfabric, converge,
cod, directdebit, moneyorder, paypal).

## The module does not own the money movement

This is the single most important fact for an acting agent: **no method in the
`payment` module posts a GL journal of its own.** The actual charge, the debit
to cash, and the credit/AR movement all ride inside the *host* PhreeBooks
journal post — cash receipt (jID 18), vendor payment (jID 20), the sales/AR
side (jID 19), and the credit memo / refund (jID 22). `paymentMain::sale()`,
`authorize()`, `void()`, and `refund()` are **verbs invoked from inside those
journal saves**: they reach out to the configured gateway, then stamp the
resulting `trans_code` and a status hint (`auth`/`cap`/`rfnd`) back onto the
`journal_item` `ttl` row. They are *not* `bizRt` routes — an agent cannot
trigger a charge by hitting a payment URL; it charges by posting the host
journal (see the PhreeBooks catalog), which calls these verbs internally.

## Gateway dispatch contract

Every installed gateway is a class `\bizuno\<code>` loaded from a `path` stored
in the `gateways` method-meta (`getMetaMethod('gateways')`, module cache — there
is no dedicated table). A gateway exposes three generic dispatchers, plus a
legacy direct-method fallback:

```yaml
gateway_dispatchers:           # invoked by paymentMain, never as a bizRt route
  payment($action, $data):     # card transactions
    actions: [capture, capAuth, wltCap, void, refund, authorize]
    return: {ok: bool, txID: '', code: '', msg: '', data: [], raw: null}
  wallet($action, $data):      # stored-card / payment-profile ops (delegates to walletList/walletAddURL/...)
  report($action, $data):      # reporting — not implemented on shipped gateways
legacy_fallback:               # un-ported gateways (typically client myExt) expose these directly
  paymentAuth($fields,$ledger): # → authorize
  sale($fields,$ledger):       # → sale
  void($rID):                  # → void
  refund($transCode,$amount):  # → refund
sale_action_radio:             # the posted <method>_action selects the dispatch
  c: capture-prior-auth   -> payment('capAuth', …)
  w: manual / "@gateway"  -> record locally, no gateway call
  s|n|'': new sale        -> payment('capture', …)
record_only_methods: [cod, moneyorder, directdebit]   # no radio, fall through to record-only
```

The wallet manager (`paymentWallet`) auto-resolves the **first active gateway
that exposes `walletList()`**, honoring the `wallet_provider` setting (or
`(auto)` to pick by `order`). Card data is **never** handled by Bizuno — add/edit
flows open a **gateway-hosted iframe** (`walletAddURL`/`walletEditURL`); only the
returned token / wallet ID (`C` + zero-padded contact id, via `getWalletID()`)
is stored.

## Data model summary

```yaml
gateways_meta:                 # getMetaMethod('gateways') — module cache, not a DB table
  id: gateway code (payfabric|authorizenet|converge|cod|directdebit|moneyorder|paypal)
  status: 1 = active/enabled
  path: filesystem path to the gateway class (BIZUNO_FS_LIBRARY/BIZUNO_DATA placeholders)
  settings: {order, gl accounts, credentials, allowRefund, …}
trans_state_hints:             # stamped onto journal_item.ttl description by the verbs
  auth: authorized, not captured
  cap:  captured / settled
  rfnd: refunded
wallet:
  wallet_id: getWalletID(cID) = 'C' + str_pad(cID,9,'0')   # NOT stored locally; lives at the gateway
  ach_columns_on_contacts: [ach_enable, ach_bank, ach_routing(INT11), ach_account(VARCHAR16)]
  pci_note: card/e-check data only in gateway-hosted iframes; Bizuno stores only the token + wallet id
nacha_ach:
  ach_banks_meta: getMetaCommon('ach_banks')   # bank account rows (CRUD via adminNacha)
  nacha_files_dir: data/banking/nacha/         # generated 94-char fixed-width .txt batch files
  maps: controllers/phreebooks/nachaMaps/<format>.php  (ccd, ppd, …)
gl_impact: none                # NO method in this module posts a GL journal itself
inventory_impact: none         # this module never moves stock
```

> **Key safety fact for an acting agent:** the `payment` module is, by itself,
> bookkeeping-neutral — it posts no GL and moves no inventory. Money moves only
> when the **host PhreeBooks journal** (jID 18/19/20/22) is saved, which then
> calls the gateway verbs documented here. Treat `sale`/`authorize`/`void`/
> `refund` as *internal verbs of those journal posts*, not as actions an agent
> invokes directly. Wallet flows are PCI-scoped: card data lives only in
> gateway-hosted iframes, never in Bizuno.

---

## payment.method.render

```yaml
id: payment.method.render
title: Render the enabled-payment-method selector for a journal screen
route: payment/main/render
http_method: GET
ui_path: PhreeBooks ▸ Cash Receipt / Vendor Payment ▸ Payment Method panel
auth:
  sec_id: (none)
  min_level: 0   # UNGATED — render() has no validateAccess guard (main.php:43)
preconditions:
  - at least one gateway is active (status=1) in the gateways meta
inputs:
  required: []
  optional:
    - name: jID
      format: integer
      source: get
      notes: host journal id; 17/20/21 default the method list to vendor (type v), else customer (type c)
    - name: type
      format: char
      source: get
      notes: c|v — overrides the jID-derived default
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - injects a selMethod select (active gateways sorted by their order setting) into the passed layout
    - defaults method_code to the first/lowest-order active gateway
returns:
  success_signal: layout.fields.selMethod populated
  identifier: none
errors: []
idempotency: safe (read-only; mutates the in-memory layout only)
related: [payment.gateway.userSignup, payment.admin.settings.read]
confidence: high
source: src/controllers/payment/main.php:43 (render)
```

## payment.gateway.userSignup

```yaml
id: payment.gateway.userSignup
title: Fetch a gateway's hosted sign-up / onboarding redirect link
route: payment/main/userSignup
http_method: GET
ui_path: Payment Methods ▸ <gateway> ▸ Sign up
auth:
  sec_id: (none)
  min_level: 0   # UNGATED — userSignup() has no validateAccess guard (main.php:207)
preconditions:
  - the named gateway is installed and exposes a userSignup() method
inputs:
  required:
    - name: method_code
      format: text
      source: get
      notes: gateway code (e.g. payfabric). getGateway() loads gateways/<code>/<code>.php.
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - delegates to $gateway->userSignup($layout) — typically populates a redirect URL to the gateway's onboarding page
    - no-op message if the gateway has no userSignup() method
returns:
  success_signal: layout populated with the gateway sign-up link
  identifier: none
errors:
  - "method not installed: getGateway() emits 'method is not installed' and bails"
  - "'Houston, we have a problem.' if the gateway userSignup() returns falsy"
idempotency: safe (read-only)
related: [payment.method.render]
confidence: low   # behavior depends entirely on the per-gateway userSignup() impl; ungated
source: src/controllers/payment/main.php:207 (userSignup)
```

## payment.wallet.manager

```yaml
id: payment.wallet.manager
title: Render a contact's stored-card wallet tab
route: payment/wallet/manager
http_method: GET
ui_path: Contacts ▸ open Customer/Vendor ▸ Wallet tab
auth:
  sec_id: j12_mgr
  min_level: 2
preconditions:
  - a wallet-capable gateway (exposes walletList) is active
  - rID is a valid contact id
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: contact id; wallet id resolves to getWalletID(rID) = 'C'+pad(rID,9)
  optional:
    - name: type
      format: char
      source: get
      notes: c|v — when c or v, also renders the contact's ACH bank/routing/account fields
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - calls $gateway->walletList() to fetch stored cards from the gateway (not from Bizuno's DB)
    - reads ach_bank/ach_routing/ach_account off the contacts row for c/v types
    - emits a guidance message (no error) when no wallet-capable gateway is enabled
returns:
  success_signal: divHTML layout with one collapsible panel per stored card
  identifier: each card panel keyed by the gateway card id
errors:
  - "permission denied if user lacks j12_mgr level 2"
idempotency: safe (read-only)
related: [payment.wallet.list, payment.wallet.add, payment.wallet.edit, payment.wallet.delete]
confidence: high
source: src/controllers/payment/wallet.php:106 (manager)
```

## payment.wallet.list

```yaml
id: payment.wallet.list
title: Fetch a contact's stored cards/e-checks from the gateway (data only)
route: payment/wallet/list
http_method: GET
ui_path: (AJAX backing the wallet tab and the payment-method card dropdown)
auth:
  sec_id: j12_mgr
  min_level: 2
preconditions:
  - a wallet-capable gateway is active
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: contact id → wallet id getWalletID(rID)
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - proxies $gateway->walletList($pfID); cards live at the gateway, not in Bizuno
returns:
  success_signal: array of card rows ({id, text, type, …}); empty array if none or no gateway
  identifier: each row's gateway card id
errors:
  - "permission denied (returns empty array) if user lacks j12_mgr level 2"
idempotency: safe (read-only)
related: [payment.wallet.manager, payment.wallet.reload]
confidence: high
source: src/controllers/payment/wallet.php:219 (list)
```

## payment.wallet.add

```yaml
id: payment.wallet.add
title: Open the gateway-hosted iframe to add a card to a contact's wallet
route: payment/wallet/add
http_method: GET
ui_path: Contacts ▸ Wallet tab ▸ Add Credit Card
auth:
  sec_id: j12_mgr
  min_level: 2
preconditions:
  - active wallet-capable gateway that exposes walletAddURL()
  - rID is a valid contact id
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: contact id the new card attaches to
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads the contact's billing address (contacts row) to pre-fill the hosted form
    - returns a popup layout embedding the gateway's walletAddURL() iframe — CARD DATA IS ENTERED AT THE GATEWAY, never posted to Bizuno
    - the actual card create happens gateway-side; Bizuno stores only the resulting token
returns:
  success_signal: popup layout with the gateway iframe
  identifier: none (card id assigned by the gateway on completion)
errors:
  - "info message if the gateway does not support walletAddURL (cards then saved during a payment instead)"
  - "permission denied if user lacks j12_mgr level 2"
idempotency: >
  NOT a direct write — opens a UI flow. The card is created at the gateway only
  when the human completes the iframe. Not agent-automatable end-to-end (PCI iframe).
related: [payment.wallet.manager, payment.wallet.reload, payment.wallet.edit]
confidence: high
source: src/controllers/payment/wallet.php:175 (add)
```

## payment.wallet.edit

```yaml
id: payment.wallet.edit
title: Open the gateway-hosted iframe to edit a stored card
route: payment/wallet/edit
http_method: GET
ui_path: Contacts ▸ Wallet tab ▸ card ▸ Edit
auth:
  sec_id: j12_mgr
  min_level: 2
preconditions:
  - active wallet-capable gateway exposing walletEditURL()
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: contact id (wallet owner)
    - name: cardID
      format: cmd
      source: get
      notes: gateway card id to edit
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - returns a popup embedding $gateway->walletEditURL(cardID) — edited at the gateway, not in Bizuno
returns:
  success_signal: popup layout with the gateway iframe
  identifier: none
errors:
  - "info message if the gateway does not support walletEditURL"
  - "permission denied if user lacks j12_mgr level 2"
idempotency: NOT a direct write — opens a UI flow; change applied gateway-side
related: [payment.wallet.manager, payment.wallet.add]
confidence: high
source: src/controllers/payment/wallet.php:206 (edit)
```

## payment.wallet.delete

```yaml
id: payment.wallet.delete
title: Delete a stored card/e-check from a contact's wallet
route: payment/wallet/delete
http_method: GET
ui_path: Contacts ▸ Wallet tab ▸ card ▸ Delete
auth:
  sec_id: j12_mgr
  min_level: 4
preconditions:
  - active wallet-capable gateway
  - cardID exists in the contact's wallet
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: contact id (wallet owner)
    - name: cardID
      format: cmd
      source: get
      notes: gateway card id to remove
  optional: []
  fixed: []
effects:
  db_writes: []   # the card lives at the gateway; nothing local is written
  gl_journal: none
  inventory: none
  side_effects:
    - calls $gateway->walletDelete(cardID, pfID); on success refreshes the wallet panel
returns:
  success_signal: layout eval action bizPanelRefresh('wallet')
  identifier: none
errors:
  - "'Error deleting the card!' if the gateway returns falsy"
  - "permission denied if user lacks j12_mgr level 4"
idempotency: idempotent (deleting an already-gone card is a no-op at the gateway)
related: [payment.wallet.manager, payment.wallet.list]
confidence: high
source: src/controllers/payment/wallet.php:195 (delete)
```

## payment.wallet.reload

```yaml
id: payment.wallet.reload
title: Reload the stored-card dropdown after a wallet change made off-tab
route: payment/wallet/reload
http_method: GET
ui_path: (AJAX from the payment-method card selector / iframe completion)
auth:
  sec_id: j12_mgr
  min_level: 2
preconditions:
  - active wallet-capable gateway exposing walletReload()
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: contact id (wallet owner)
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - calls $gateway->walletReload($layout, $pfID) to repopulate the card combo; no-op if unsupported
returns:
  success_signal: layout updated with refreshed card list
  identifier: none
errors:
  - "permission denied if user lacks j12_mgr level 2"
idempotency: safe (read-only refresh)
related: [payment.wallet.manager, payment.wallet.list, payment.wallet.add]
confidence: high
source: src/controllers/payment/wallet.php:227 (reload)
```

## payment.wallet.clean

```yaml
id: payment.wallet.clean
title: (stub) Purge expired stored cards
route: payment/wallet/clean
http_method: GET
ui_path: (not surfaced in the UI)
auth:
  sec_id: (none)
  min_level: 0   # UNGATED, but it is a pure no-op stub (wallet.php:188)
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
    - none — emits "This functionality is not yet working" and returns
returns:
  success_signal: caution message only
  identifier: none
errors: []
idempotency: safe (no-op)
related: [payment.wallet.delete]
confidence: high
source: src/controllers/payment/wallet.php:188 (clean)
```

## payment.wallet.modifyID

```yaml
id: payment.wallet.modifyID
title: Rename a gateway wallet/customer number (e.g. on contact merge)
route: payment/wallet/modifyID
http_method: GET
ui_path: (internal — invoked during contact merge / re-id, not a user button)
auth:
  sec_id: j12_mgr
  min_level: 2
preconditions:
  - active gateway exposing walletRename()
  - both srcID and destID supplied
inputs:
  required:
    - name: srcID
      format: (method argument — NOT read via clean())
      source: internal
      notes: current gateway customer number; passed as a function arg, not a posted field
    - name: destID
      format: (method argument)
      source: internal
      notes: new gateway customer number
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - calls $gateway->walletRename(srcID, ['NewCustomerNumber'=>destID]) at the gateway
returns:
  success_signal: boolean true on rename success
  identifier: none
errors:
  - "illegal_access message if srcID or destID empty"
  - "false if no gateway or gateway lacks walletRename"
  - "permission denied if user lacks j12_mgr level 2"
idempotency: >
  NOT a normal route — takes positional args rather than clean() inputs, so it
  is only safely invoked by internal callers (contact merge). Verify the caller
  contract before any direct use.
related: [payment.wallet.manager, contacts.merge]
confidence: low   # signature takes method args, not request fields — invocation path is internal
source: src/controllers/payment/wallet.php:234 (modifyID)
```

## payment.admin.settings.read

```yaml
id: payment.admin.settings.read
title: Render the payment module settings (GL defaults, wallet provider, ACH tab)
route: payment/admin/adminHome
http_method: GET
ui_path: Settings ▸ Payment
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
    - builds the settings form: default payment/discount GL for customers (c) and vendors (v),
      a reference prefix, and the wallet_provider select (active wallet-capable gateways + "(auto)")
    - adds an "ACH Accounts" tab loaded from payment/adminNacha/manager
returns:
  success_signal: settings tab layout
  identifier: none
errors:
  - "permission denied if user lacks admin level 1"
idempotency: safe (read-only)
related: [payment.admin.settings.save, payment.ach.list]
confidence: high
source: src/controllers/payment/admin.php:97 (adminHome)
```

## payment.admin.settings.save

```yaml
id: payment.admin.settings.save
title: Persist payment module settings
route: payment/admin/adminSave
http_method: POST
ui_path: Settings ▸ Payment ▸ Save
auth:
  sec_id: (none)
  min_level: 0   # UNGATED — adminSave() persists settings with NO validateAccess guard (admin.php:108)
preconditions: []
inputs:
  required: []
  optional:
    - name: general_gl_payment_c
      format: text
      source: post
      notes: default cash/payment GL for customer payments
    - name: general_gl_discount_c
      format: text
      source: post
      notes: default discount GL (customer)
    - name: general_gl_payment_v
      format: text
      source: post
      notes: default payment GL (vendor)
    - name: general_gl_discount_v
      format: text
      source: post
      notes: default discount GL (vendor)
    - name: general_prefix
      format: text
      source: post
      notes: payment reference prefix (default DP)
    - name: general_wallet_provider
      format: text
      source: post
      notes: gateway code to prefer for wallet ops; '' = (auto) pick by order
  fixed: []
effects:
  db_writes:
    - table: (module cache settings — getModuleCache/setModuleCache for 'payment')
      op: update
  gl_journal: none
  inventory: none
  side_effects:
    - readModuleSettings() reads the posted fields against settingsStructure() and saves them
returns:
  success_signal: settings persisted (no explicit success message in this method)
  identifier: none
errors: []
idempotency: idempotent — re-saving the same values yields the same settings
related: [payment.admin.settings.read]
confidence: low   # ungated write worth a real fix; field-name mapping inferred from settingsStructure()
source: src/controllers/payment/admin.php:108 (adminSave), :55 (settingsStructure)
```

## payment.ach.list

```yaml
id: payment.ach.list
title: List configured ACH bank accounts (NACHA origination banks)
route: payment/adminNacha/manager
http_method: GET
ui_path: Settings ▸ Payment ▸ ACH Accounts
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
    - renders an EasyUI datagrid of ach_banks meta rows (copy action removed); use .list.rows for data
returns:
  success_signal: datagrid layout
  identifier: none
errors:
  - "permission denied if user lacks admin level 1"
idempotency: safe (read-only)
related: [payment.ach.list.rows, payment.ach.read, payment.ach.save]
confidence: high
source: src/controllers/payment/adminNacha.php:73 (manager)
```

## payment.ach.list.rows

```yaml
id: payment.ach.list.rows
title: Fetch ACH bank-account rows (data only)
route: payment/adminNacha/managerRows
http_method: GET
ui_path: (AJAX backing the ACH Accounts datagrid)
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
      notes: filters across title, biz_entry, biz_name
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: datagrid JSON rows
  identifier: each row carries its ach_banks meta id and mapID
errors:
  - "permission denied if user lacks admin level 1"
idempotency: safe (read-only)
related: [payment.ach.list, payment.ach.read]
confidence: high
source: src/controllers/payment/adminNacha.php:79 (managerRows)
```

## payment.ach.read

```yaml
id: payment.ach.read
title: Open one ACH bank account for editing
route: payment/adminNacha/edit
http_method: GET
ui_path: Settings ▸ Payment ▸ ACH Accounts ▸ open row
auth:
  sec_id: admin
  min_level: 1
preconditions:
  - rID refers to an existing ach_banks meta row (0 = new)
inputs:
  required: []
  optional:
    - name: rID
      format: integer
      source: get
      notes: ach_banks meta id; 0/blank opens a blank form
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - resolves the linked vendor contact (cID) name for the contact picker
returns:
  success_signal: edit form layout (title, mapID, gl_acct, biz_route, biz_id, biz_entry, biz_name, cID)
  identifier: none
errors:
  - "permission denied if user lacks admin level 1"
idempotency: safe (read-only)
related: [payment.ach.save, payment.ach.list]
confidence: high
source: src/controllers/payment/adminNacha.php:87 (edit)
```

## payment.ach.save

```yaml
id: payment.ach.save
title: Create or update an ACH bank account
route: payment/adminNacha/save
http_method: POST
ui_path: Settings ▸ Payment ▸ ACH Accounts ▸ Save
auth:
  sec_id: admin
  min_level: 2   # 3 when an rID is posted (update); 2 when creating
preconditions:
  - a NACHA map (mapID) exists in controllers/phreebooks/nachaMaps/
inputs:
  required:
    - name: title
      format: text
      source: post
      notes: display name for the bank account
    - name: mapID
      format: db_field
      source: post
      notes: NACHA format/map id (ccd, ppd, …) selecting the file layout
  optional:
    - name: rID
      format: integer
      source: post
      notes: ach_banks meta id; presence switches save to update (level 3)
    - name: cID
      format: integer
      source: post
      notes: linked vendor contact id
    - name: gl_acct
      format: db_field
      source: post
      notes: GL cash account the ACH batch draws from (default vendors gl_cash)
    - name: biz_route
      format: db_field
      source: post
      notes: EFT transit routing number (assigned by your bank)
    - name: biz_id
      format: integer
      source: post
      notes: EFT company id (assigned by your bank)
    - name: biz_entry
      format: db_field
      source: post
      notes: company entry description
    - name: biz_name
      format: db_field
      source: post
      notes: originating company name
  fixed: []
effects:
  db_writes:
    - table: (ach_banks meta — mgrJournal saveDB)
      op: insert/update
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: msgStack 'success' = msg_record_saved; grid reloads
  identifier: the meta row id
errors:
  - "permission denied if user lacks admin level 2 (create) / 3 (update)"
idempotency: idempotent on update (rID); a blank-rID save inserts a new row each call
related: [payment.ach.read, payment.ach.delete, payment.nacha.manager]
confidence: high
source: src/controllers/payment/adminNacha.php:98 (save)
```

## payment.ach.delete

```yaml
id: payment.ach.delete
title: Delete an ACH bank account
route: payment/adminNacha/delete
http_method: GET
ui_path: Settings ▸ Payment ▸ ACH Accounts ▸ Trash
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - rID refers to an existing ach_banks meta row
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: ach_banks meta id to delete
  optional: []
  fixed: []
effects:
  db_writes:
    - table: (ach_banks meta — mgrJournal deleteDB)
      op: delete
  gl_journal: none
  inventory: none
  side_effects:
    - removes only the origination-bank config; does not touch generated NACHA files or journals
returns:
  success_signal: grid reloads
  identifier: none
errors:
  - "permission denied if user lacks admin level 4"
idempotency: idempotent (deleting an already-gone row is a no-op)
related: [payment.ach.list]
confidence: high
source: src/controllers/payment/adminNacha.php:104 (delete)
```

## payment.nacha.manager

```yaml
id: payment.nacha.manager
title: Render the NACHA batch-file generation screen
route: payment/nacha/manager
http_method: GET
ui_path: Banking / ACH ▸ NACHA file
auth:
  sec_id: nacha
  min_level: 3
preconditions:
  - at least one ACH bank account (payment.ach.*) is configured
  - vendors flagged ach_enable with valid routing/account exist for the batch
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - renders the NACHA generation form (description + Go) plus an attachments grid of previously
      generated files; the form submits to bizRt=bizuno/nacha/save (cross-module post that drives
      openACH/process/closeACH → writes the .txt file under data/banking/nacha/)
returns:
  success_signal: manager layout
  identifier: none
errors:
  - "permission denied if user lacks nacha level 3"
idempotency: safe (read-only; the actual file write happens on the bizuno/nacha/save submit)
related: [payment.nacha.files, payment.ach.list]
confidence: medium   # generation itself is driven via bizuno/nacha/save, a different module route
source: src/controllers/payment/nacha.php:54 (manager)
```

## payment.nacha.files

```yaml
id: payment.nacha.files
title: List previously generated NACHA files (data only)
route: payment/nacha/mgrRows
http_method: POST
ui_path: (AJAX backing the NACHA-files attachment grid)
auth:
  sec_id: nacha
  min_level: 3
preconditions: []
inputs:
  required: []
  optional:
    - name: rows
      format: integer
      source: post
      notes: rows per page (default 10)
    - name: page
      format: integer
      source: post
      notes: page number (default 1)
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - globs data/banking/nacha/ for .txt files (newest first) and paginates the list
returns:
  success_signal: JSON {total, rows}
  identifier: each row is a stored NACHA filename (download via bizuno/main/fileDownload)
errors:
  - "permission denied if user lacks nacha level 3"
idempotency: safe (read-only)
related: [payment.nacha.manager]
confidence: high
source: src/controllers/payment/nacha.php:82 (mgrRows)
```

---

## Internal verbs (NOT bizRt routes — invoked from PhreeBooks journal saves)

These four are public PHP methods on `paymentMain`, but they are **not reachable
as `<module>/<page>/<method>` routes**. They are called from inside the host
PhreeBooks journal post (cash receipt jID 18, sales/AR jID 19, vendor payment
jID 20, credit memo jID 22). An agent triggers them only by posting the
corresponding journal. None of them posts a GL row itself — they reach the
gateway and stamp `trans_code` + status onto the `journal_item` `ttl` row.

```yaml
payment.internal.authorize:
  signature: paymentMain::authorize($ledger=[])   # NOT a route
  auth: {sec_id: j12_mgr, min_level: 2}
  posts: gateway payment('authorize', …) or legacy paymentAuth(); returns txID or '1'
  gl_journal: none (rides inside the host journal)
  inventory: none
  confidence: high
  source: src/controllers/payment/main.php:69 (authorize)

payment.internal.sale:
  signature: paymentMain::sale($method='', $ledger=[])   # NOT a route
  posts: dispatch on the <method>_action radio (c=capAuth, w=record-only, s/n/''=capture);
         legacy gateways use sale($fields,$ledger). Stamps trans_code + status 'cap' on journal_item ttl.
  gl_journal: none (rides inside the host cash-receipt/payment journal, jID 18/20)
  inventory: none
  confidence: high
  source: src/controllers/payment/main.php:99 (sale)

payment.internal.void:
  signature: paymentMain::void($method='', $rID=0)   # NOT a route
  posts: gateway payment('void', …) or legacy void($rID); non-fatal if unsupported so the journal delete proceeds
  gl_journal: none (the GL reversal is the journal delete itself)
  inventory: none
  confidence: high
  source: src/controllers/payment/main.php:149 (void)

payment.internal.refund:
  signature: paymentMain::refund(&$j22ttlRow, $j22pmtRow, $amount=0)   # NOT a route
  posts: traces the original capture's trans_code (refundTrnsCode), pulls last4 from the
         description hint, calls gateway payment('refund', …); always returns true so the
         credit memo proceeds even if the gateway refund is skipped/fails. Stamps status 'rfnd'.
  gl_journal: none (rides inside the credit-memo journal, jID 22)
  inventory: none
  confidence: low   # multi-hop trans_code tracing (CM → invoice → cash receipt); verify before automating
  source: src/controllers/payment/main.php:173 (refund), :218 (refundTrnsCode)
```

---

## Common agent recipes

```yaml
recipe_charge_a_customer:
  goal: Take a credit-card payment from a customer
  steps:
    - note: There is NO payment-module route that charges a card directly.
    - action: (PhreeBooks) post a cash receipt journal (jID 18) with method_code set to an active gateway
      note: the journal save internally calls payment.internal.sale, which charges the gateway and stamps trans_code
  note: see the PhreeBooks catalog; the payment module only renders the method picker and dispatches to the gateway

recipe_store_a_card_on_a_contact:
  goal: Save a reusable card to a customer's wallet
  steps:
    - action: payment.wallet.manager
      with: {rID: <contactId>, type: c}
    - action: payment.wallet.add
      with: {rID: <contactId>}
      note: opens a gateway-hosted iframe — the human enters card data at the gateway; Bizuno never sees the PAN
    - action: payment.wallet.reload
      with: {rID: <contactId>}      # refresh the stored-card list after the iframe completes
  note: not fully agent-automatable — the PCI iframe step requires a human or the gateway's own API

recipe_pay_vendors_by_ach:
  goal: Generate a NACHA batch to pay ACH-enabled vendors
  steps:
    - action: payment.ach.save
      with: {title, mapID: ccd, biz_route, biz_id, biz_entry, biz_name, gl_acct}   # one-time bank setup
    - precondition: vendor contacts flagged ach_enable with valid ach_routing/ach_account
    - action: payment.nacha.manager      # opens the generation screen
    - submit: bizRt=bizuno/nacha/save     # actually writes the 94-char fixed-width file to data/banking/nacha/
    - action: payment.nacha.files         # confirm the file was created, then download it
  note: NACHA generation posts NO GL; the GL cash movement is the vendor-payment journals (jID 20) themselves

recipe_configure_wallet_provider:
  goal: Pin which gateway handles stored cards
  steps:
    - action: payment.admin.settings.read     # see the available wallet-capable gateways
    - action: payment.admin.settings.save
      with: {general_wallet_provider: payfabric}   # '' leaves it on (auto) = first by order
```

## Open questions / verify-before-automating

- **Ungated routes (no `validateAccess`).** `payment/main/render`
  (main.php:43), `payment/main/userSignup` (main.php:207), and
  `payment/wallet/clean` (wallet.php:188, a no-op) carry no permission guard.
  `clean` is harmless, but `render`/`userSignup` should be reviewed.
- **`payment/admin/adminSave` is ungated and persists settings**
  (admin.php:108) — `readModuleSettings()` runs with no `validateAccess('admin', …)`
  check, so any authenticated session can rewrite the module's default GL
  accounts and wallet provider. This is a real fix worth making (add
  `validateAccess('admin', 1)` like `adminHome`).
- **Dead UI link.** `contacts/main.php:373` builds a tab pointing at
  `bizRt=payment/main/manager&rID=$rID`, but `paymentMain` has **no `manager()`
  method** — that link is dead. The live wallet entry point is
  `payment/wallet/manager` (referenced correctly at contacts/main.php:297).
- **`payment.wallet.modifyID`** takes positional method arguments (`$srcID`,
  `$destID`) rather than `clean()` request fields, so it is only meaningfully
  invoked by internal callers (contact merge). Confirm the caller contract
  before treating it as a route (`confidence: low`).
- **`payment.internal.refund`** traces the original capture's `trans_code`
  through several hops (credit memo → invoice → cash receipt) and **always
  returns true** so the credit memo proceeds even when the gateway refund is
  skipped or fails. Verify the trans_code resolution for your transaction
  topology before relying on automated refunds (`confidence: low`).
- **NACHA generation** is driven by `bizRt=bizuno/nacha/save` (a `bizuno`-module
  route that calls `paymentNacha::openACH/process/closeACH`), not by a
  `payment/nacha/...` route — confirm that cross-module entry point when wiring
  an automated payment run.
- **Gateway-dependent behavior.** Every `effects`/`returns` line for wallet and
  transaction verbs depends on the resolved gateway's implementation (payfabric
  vs authorizenet vs record-only cod/moneyorder/directdebit). The dispatcher
  contract is uniform, but the side effects are not — re-verify against the
  specific `gateways/<code>/<code>.php` in use.