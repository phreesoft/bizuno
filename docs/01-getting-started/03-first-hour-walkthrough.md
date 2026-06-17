---
title: First-Hour Walkthrough
category: Getting Started
order: 3
status: published
audience: [bookkeeper, admin]
last-updated: 2026-05-19
---

# First-Hour Walkthrough

A guided 60 minutes from "Bizuno just installed" to "I have a working set
of books and posted my first invoice." If you follow this in order, you'll
hit the **two effectively-permanent settings** in the right order, with
enough context to choose well — then practice the daily-use motion before
making any other commitments.

**Total time: about 60 minutes.** Don't rush; the choices you make in the
first 15 minutes are the ones that hurt to change later.

---

## Before you start

You should already have:

- ☐ Bizuno installed (composer, manual, WP plugin, or PhreeSoft cloud)
- ☐ The installer's initial admin login working
- ☐ Decided on your **default currency** (this is permanent)
- ☐ Decided on your **first fiscal-year start date** (also permanent)

If you don't have answers to the last two, stop. Talk to your accountant.
Come back when you do. Picking these wrong is the most common reason
Bizuno deployments get rebuilt.

---

## Step 1 — Log in and set company info (5 min)

**Where:** *Settings → Company*

**What to set:**

- Legal business name
- DBA / trade name (if different)
- Address (used on every invoice, statement, and report header)
- Telephone, email, website
- Tax ID / EIN
- Logo (PNG, recommended 600×200 or similar; goes on every PDF form)

**Why first:** The company name + address appear on every PDF Bizuno
generates from this point forward. Setting them now saves you a
re-export-everything moment later.

**Don't worry about:** Default GL accounts, banking, anything in the
sub-tabs beyond Company. We'll come back.

---

## Step 2 — Chart of accounts (10 min)

**Where:** *PhreeBooks → Chart → Manage*

If the installer asked you to pick a COA template, your COA already
exists. Open *Chart* to see what you've got.

**What to do:**

- Scroll through the existing accounts. Is the structure roughly what
  you want? Cash, AR, Inventory, AP, Sales Tax Collected, Sales,
  COGS, Expenses, Retained Earnings — those are the load-bearing ones.
- **Rename freely.** Bizuno keys off account ID, not name. Renaming
  "Sales" to "Service Revenue" is fine.
- **Add accounts** you'll need (extra revenue categories, expense
  buckets, specific bank accounts).
- **Mark heading rows** if you want sub-account grouping in reports.
- **Set defaults per account type** in *PhreeBooks → Tools → Defaults*
  — the default AR, default Sales account, default COGS, default
  Inventory. Bizuno uses these when a transaction doesn't specify.

**Avoid:**

- Deleting accounts the templates ship with — even if you don't think
  you'll use them. Mark them inactive instead. Deletion after any
  posting is awkward.
- Building a 200-account COA on day one. Start with what you actually
  use; add more later when you find yourself wishing for a category.

**Why now:** Every invoice, payment, and journal you post next refers
to GL accounts. Setting up COA first means those references just work.

---

## Step 3 — Fiscal year start date (5 min) ⚠️ PERMANENT

**Where:** *Settings → PhreeBooks → Fiscal Year*

**This is one of the two effectively-permanent settings.** Changing it
after you've posted transactions means rebuilding `journal_periods` and
re-stamping `period` on every row in `journal_main`. Doable but painful.

**What to set:**

- **First day of your fiscal year** (e.g. `2026-01-01` for calendar-year
  US businesses, `2026-07-01` for fiscal-July starts, `2026-10-01` for
  US government / many education books)
- **Number of periods per year** (usually 12 = months; some retail
  businesses use 13 four-week periods)

**Verify:** *PhreeBooks → Chart → Periods* should now show 12 (or your
chosen number) rows for the current fiscal year. The current period
should be the one containing today.

**Why now:** Every transaction stamps its `period` from `post_date`
against this table. Get it set up before you post anything.

See [Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md)
for the full implications.

---

## Step 4 — Currency and locale (3 min)

**Where:** *Settings → Locale*

**What to set:**

- Default currency (⚠️ PERMANENT — should already match what you
  picked at install)
- Date format (`Y-m-d`, `m/d/Y`, `d/m/Y`, etc.)
- Number format (decimal separator, thousands separator)
- Time zone

**Multi-currency on/off:** Leave **off** unless you actively transact in
multiple currencies. Turning it on later works fine; just easier to keep
the UI simple when you don't need the second-currency fields.

**Why now:** Every form, report, and PDF respects locale settings.
Setting them once propagates everywhere.

---

## Step 5 — Stores (5 min, skip if single-location)

**Where:** *Settings → Stores*

If you operate from a **single location**, skip this. Bizuno uses
`store_id = 0` for the default store and you'll never see the field
again.

If you operate **multiple locations** (warehouses, retail stores,
jobsites):

- Create one store row per physical location
- Each store has its own name, address, default contact preferences,
  optional GL overrides
- Decide whether staff are restricted to "their" store — set the
  `restrict_store` flag per user in *Settings → Users*

**Why now:** Adding stores later is fine, but moving existing
transactions into the right store_id retroactively is awkward. Easier
to start with the right structure.

---

## Step 6 — Master data starter (5 min)

Create the smallest possible set of master data to test the workflow:

**One customer:** *Customers → Manage → New*
- Name, address, default terms (e.g. Net 30)
- Default GL accounts can stay blank — they'll fall back to the COA
  defaults

**One vendor:** *Vendors → Manage → New*
- Same fields, different `ctype` flag

**One inventory item:** *Inventory → Manage → New*
- SKU, description
- **Inventory type** — pick carefully. `si` = "Stock Item, Standard
  Cost" is a safe default for physical goods. `sa` = "Stock Assembly".
  Non-COGS types like `nc` are for services / non-stock items.
- Price (sale price), cost (purchase cost)
- Default GL accounts

**Why this much only:** You're about to test the invoice cycle. One of
each is enough to validate the GL posts correctly.

See [Inventory types and COGS](../02-core-concepts/05-inventory-types-and-cogs.md)
before picking the type — it's the third effectively-permanent decision
of the day, per SKU.

---

## Step 7 — Your first invoice (10 min)

**Where:** *Customers → Sales (Invoice) → New*

- Pick the customer you just created
- Add the inventory item, qty 1
- Confirm price, tax, total
- Save

**Then immediately check the GL:**

**Where:** *PhreeBooks → Register* (pick the AR account)

You should see one new line: a debit of the invoice total, dated today.

**And the inventory:**

**Where:** *Inventory → Manage → click the SKU → History*

You should see a quantity-out movement dated today.

**And the COGS:**

**Where:** *PhreeBooks → Register* (pick the COGS account)

You should see a debit of the item's cost.

If those three show as expected, your full Sales → AR → COGS → Inventory
plumbing is working. This is the moment Bizuno "clicks."

---

## Step 8 — Your first report (5 min)

**Where:** *PhreeForm → Reports → Trial Balance* (or any built-in report)

- Run the report as of today
- Confirm AR has the invoice total
- Confirm Sales has the revenue
- Confirm Inventory dropped by the item's cost
- Confirm COGS picked it up

Save the report's output (PDF or CSV) somewhere. It's your first
"snapshot" — useful baseline if you ever need to verify a future
restore.

**Bonus:** Generate a PDF of the invoice itself.

**Where:** From the invoice manager, click Print on the row.

If you get a clean PDF with your logo and company info, the PDF engine
is working end-to-end.

---

## Step 9 — Configure backups (5 min)

**Where:** *Settings → Bizuno → Tools → Backup*

- Set the backup destination (`BIZUNO_DATA/backups/` is the default;
  off-host is better)
- Choose schedule (daily is sensible for active books)
- **Test the backup tool now** — run one manually, find the file on disk

Then set up an **off-host copy** outside Bizuno. cron + `mysqldump` +
`rsync` / `aws s3 cp` is the durable pattern. See
[Backup and restore](../05-administration/03-backup-and-restore.md).

**Why now:** Once you start putting real data in, backups stop being
optional. Set them up *before* the first real day's posting.

---

## Step 10 — What to skip on day one

Resist the temptation to configure these now. They're more useful once
you've run a few weeks of routine transactions:

- **Payroll** — needs employees, tax tables, pay-period setup. Defer
  until you actually run a payroll.
- **EDI** — only relevant if you trade with EDI partners. Most don't.
- **Custom forms** — the built-in invoice and packing slip work. Brand
  them later.
- **PhreeForm custom reports** — start with the 50+ built-in reports.
  Build custom ones when you find yourself wanting something specific.
- **Custom payment gateways** — set up the built-in Authorize.net /
  Stripe path once you're actually taking online payments.
- **WooCommerce sync** — set up only if you have a WC store.
- **Quality module** — only relevant if you run ISO-9001-style
  processes. Most small businesses don't.

---

## Where to go next

You now have:

- A configured business with permanent decisions made
- One customer, one vendor, one inventory item
- One posted invoice with verified GL impact
- A working backup

Recommended next reads, in order of payoff:

1. **[The journal_id taxonomy](../02-core-concepts/02-journal-id-taxonomy.md)** —
   the conceptual key to the whole codebase. 15 minutes.
2. **[Quote → SO → Invoice → Payment → Deposit](../03-daily-workflows/01-quote-so-invoice-payment-deposit.md)** —
   the full customer-cycle workflow. Practice it end-to-end with your
   test customer.
3. **[PO → Receive → Vendor Invoice → Pay Bill](../03-daily-workflows/02-po-receive-bill-pay.md)** —
   the vendor-side mirror.
4. **[Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md)** —
   the nuance behind the choices you just made.

Then start posting real transactions. Best way to learn an accounting
system is to use it.

---

## If something doesn't work

| Symptom                                          | First check                                          |
|--------------------------------------------------|------------------------------------------------------|
| Login works but most menus are missing           | Wrong role assigned to your user; see Settings → Users |
| "No font has been set" PDF error                 | Composer didn't pull tFPDF — `composer install` |
| Invoice saves but GL doesn't update              | Cache out of sync — Settings → Bizuno → Tools → Clear Business Cache |
| Dropdown values show as raw keys (`qa_status_1`) | Same cache issue                                     |
| "Period not found" on save                       | Fiscal calendar doesn't cover today's date — `fyAdd` or check Step 3 |
| Logo not appearing on PDF                        | Logo path / file permissions on `BIZUNO_DATA/images/` |

For anything else, see
[Troubleshooting](../09-troubleshooting/) — start with
[Trap and trace files](../09-troubleshooting/01-trap-and-trace-files.md)
to read what Bizuno is actually complaining about.

---

## Related

- [What is Bizuno](./01-what-is-bizuno.md) — context, if you skipped it
- [Three ways to run it](./02-three-ways-to-run-it.md) — deployment options
- [Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md) — the conceptual chapter
- [Backup and restore](../05-administration/03-backup-and-restore.md) — Step 9 detail
- [Cache out of sync](../09-troubleshooting/02-cache-out-of-sync.md) — when things look weird
