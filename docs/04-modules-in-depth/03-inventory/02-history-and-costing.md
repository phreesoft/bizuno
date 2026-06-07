---
title: History and Costing
category: Inventory
order: 2
status: draft
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# History and Costing

"What did this widget actually cost me?" is the question behind every margin on
every report. Bizuno answers it with a per-SKU cost ledger (`inventory_history`)
and a per-item costing method. Misunderstand the method and you misreport COGS on
every sale — so this page is worth reading before you go live.

---

## The cost ledger: `inventory_history`

Every time stock comes **in** (a purchase receipt, a positive adjustment, an
assembly build), Bizuno writes a **cost layer** to `inventory_history`. Each layer
records:

| Field        | Meaning                                                      |
|--------------|--------------------------------------------------------------|
| `sku`        | The item                                                     |
| `qty`        | Quantity received in this layer                              |
| `remaining`  | How much of this layer is still unsold (decrements over time)|
| `unit_cost`  | The cost per unit at receipt                                 |
| `avg_cost`   | The weighted-average cost as of this layer                   |
| `post_date`  | When it posted                                               |
| `ref_id` / `journal_id` | The transaction (and journal) that created it    |
| `trans_code` | Serial number, for serialized items                         |
| `store_id`   | The store, for multi-store                                   |

When stock goes **out** (a sale, a negative adjustment, component consumption in a
build), Bizuno *consumes* from these layers — drawing down `remaining` — and the
cost it pulls is your COGS. How it picks which layer to draw from is the costing
method.

---

## Costing methods

The method is a **per-item** setting (`cost_method` on the inventory record):

| `cost_method` | Method   | How COGS is drawn                                  |
|:-------------:|----------|----------------------------------------------------|
| `f`           | **FIFO** (default) | Oldest layer first (`post_date` ascending) |
| `l`           | **LIFO** | Newest layer first (`post_date` descending)        |
| `a`           | **Average** | Weighted average across available layers        |

### Setting the method

- **Per item:** each SKU stores its own `cost_method`. It's chosen at creation and
  **locks** once the item has transactions (read-only thereafter — see
  [Inventory types](./01-inventory-types.md#why-the-type-is-permanent)).
- **Per-type default:** new items inherit the default for their type from
  *Settings → Inventory* (`method_si`, `method_ma`, `method_ms`, `method_sr`,
  `method_sa`). Set these to your shop's convention so new SKUs come out right.

> **Be consistent.** Mixing methods across similar items makes margin reports hard
> to reason about. Most shops pick one (commonly FIFO) and standardize the
> per-type defaults to match.

---

## Cost on receive

When a purchase/bill (jID 6) posts with a positive quantity, Bizuno writes a new
`inventory_history` layer at the line's cost and, for average-cost items,
recomputes `avg_cost`:

```
avg_cost = (prior_avg × stock_on_hand + receipt_price × receipt_qty)
           ────────────────────────────────────────────────────────
                       (stock_on_hand + receipt_qty)
```

### Auto-cost from the document

The `auto_cost` setting (*Settings → Inventory*) can keep the item's standing cost
in step with what you actually pay:

| `auto_cost` | Behavior                                                   |
|:-----------:|------------------------------------------------------------|
| `0`         | Off — item cost is whatever you set manually               |
| `PO`        | Update item cost from the **Purchase Order** price (jID 4) |
| `PR`        | Update item cost from the **Purchase Receipt** price (jID 6) — `item_cost = line debit ÷ qty` |

There's no separate landed-cost field — whatever you put in the line cost is the
cost that's captured. To include freight/duty in unit cost, fold it into the line.

---

## COGS on sale

When a sale (jID 12) posts, Bizuno generates the COGS legs (`gl_type='cog'`)
automatically — you never type them. The cost comes from the layers:

- **FIFO / LIFO:** walk `inventory_history` layers in date order, consuming
  `remaining` from each until the sold quantity is covered. Each draw is recorded
  in **`journal_cogs_usage`** (which layer, how much) so the consumption is
  auditable and reversible.
- **Average:** use the weighted `avg_cost` as of the sale date.

### Selling stock you don't have yet

If a sale would drive stock negative:

- **FIFO/LIFO** allow it (when negative stock is permitted): Bizuno estimates COGS
  from the item's standing cost and queues the shortfall in **`journal_cogs_owed`**.
  When stock later arrives at a real cost, the affected sales **re-post** to
  correct their COGS. (This is part of the broader
  [re-post chain](../01-phreebooks/02-journals.md#the-re-post-chain).)
- **Average** does **not** allow negative stock — the post is rejected, because
  there's no meaningful average to draw from.

> Because an average-cost **receipt** changes the running average, posting one
> triggers a re-post of later sales of that SKU so their COGS reflects the updated
> average. Expect a small edit to ripple forward — by design.

---

## Adjustments (jID 16)

Inventory adjustments — cycle counts, shrinkage, write-ups/downs — post with
`gl_type='adj'`:

- A **positive** adjustment behaves like a receipt: it adds a cost layer and
  raises `qty_stock`.
- A **negative** adjustment behaves like a sale: it consumes layers and lowers
  `qty_stock`.

Use them to bring book quantity back in line with a physical count, or to
write inventory value up or down.

---

## When history drifts: repair tools

Stock quantity and the sum of layer `remaining` should always agree. If they
drift (interrupted posts, old data, a bad import), two tools under *Admin →
Inventory → Tools* fix it:

- **Repair Inventory History** (`historyTestRepair()`) — validates that each SKU's
  `qty_stock` equals the sum of its `inventory_history.remaining` (less anything
  owed), and corrects rounding/zero-out errors. Run in **test** mode first to see
  what it would change.
- **Recalculate History** (`recalcHistory()`) — the heavier rebuild: re-derives
  history from the journal entries for corruption recovery, batching through SKUs
  and logging mismatches to a CSV for review.

> Run the test/repair pass before trusting margin reports after any bulk import or
> a restore. The recalc is the bigger hammer — reach for it only when repair isn't
> enough.

---

## Related

- [Inventory types](./01-inventory-types.md) — which types are costed
- [Assemblies](./03-assemblies.md) — how a built item's cost is rolled up
- [Journals (the journal_id reference)](../01-phreebooks/02-journals.md) — the COGS legs and re-post chain
- [Inventory types and COGS](../../02-core-concepts/05-inventory-types-and-cogs.md)
