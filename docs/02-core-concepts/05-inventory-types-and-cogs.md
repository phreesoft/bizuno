---
title: Inventory Types and COGS
category: Core Concepts
order: 5
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-16
---

# Inventory Types and COGS

Every inventory item carries a two-letter **type code**, and the single most
important thing that code decides is whether the item posts **Cost of Goods
Sold**. Pick the wrong type on day one and you're either over-reporting COGS,
under-reporting assets, or — worse — rebuilding history to fix it. This page is
the *why*: what COGS-tracking means, which types do it, and how to choose. The
full per-code table is in
[Inventory types](../04-modules-in-depth/03-inventory/01-inventory-types.md).

---

## The one distinction that matters: does it post COGS?

Bizuno splits every inventory type into two camps by a single constant:

```php
define('INVENTORY_COGS_TYPES', ['ma','mi','ms','sa','si','sr']);
```

A type in that list is **COGS-tracking**; everything else is not. The difference
is fundamental to the accounting:

- **COGS-tracking types are an asset until sold.** When you buy one, its cost
  sits on the balance sheet as inventory. When you sell it, Bizuno moves that
  cost from inventory to a COGS account *at the moment of sale* — matching the
  expense to the revenue it earned. These types carry a cost layer in
  `inventory_history` and decrement real stock quantity.
- **Non-COGS types expense (or cost nothing) up front.** A service, labor line,
  freight charge, or pure description never holds inventory value. There's no
  COGS leg at sale time; any cost was recognized when you bought the underlying
  thing, not when you sold the line. Their stock quantity is parked at a nominal
  1 and never depletes.

So the question "which type?" is really "does selling this thing relieve an
asset I'm carrying, or is it a service/charge with no inventory value?"

| Camp                | Type codes                          | Behavior                                   |
|---------------------|-------------------------------------|--------------------------------------------|
| **COGS-tracking**   | `si`, `sr`, `ma`, `sa`, `ms`, `mi`  | Carries cost, decrements stock, posts COGS on sale |
| **Not COGS-tracking** | `ns`, `lb`, `sv`, `sf`, `ci`, `ai`, `ds` | No inventory value, no COGS leg          |

(`mi` and `ia` are special — see the
[full reference](../04-modules-in-depth/03-inventory/01-inventory-types.md#the-full-type-reference).
You won't create them by hand.)

---

## How a COGS-tracking item is costed

When you sell a COGS-tracking item, Bizuno has to decide *which cost* to relieve
— the unit you bought in January cost something different from the one you
bought in June. Each item carries a **cost method** that answers this:

- **FIFO** (`f`, the default) — relieve the oldest cost layer first.
- **LIFO** (`l`) — relieve the newest cost layer first.
- **Average** (`a`) — maintain a running average cost across all layers.

The method is set **per item**, not globally — each SKU has its own
`cost_method`, defaulting from the inventory settings for its type. FIFO and LIFO
walk the `inventory_history` cost layers in date order (oldest-first or
newest-first); average keeps a single blended `avg_cost` that updates on each
receipt. Mechanics and worked examples are in
[History and costing](../04-modules-in-depth/03-inventory/02-history-and-costing.md).

---

## Why the type is effectively permanent

Once an item has posted transactions, Bizuno locks its type — the
`inventory_type` field (along with `sku` and `cost_method`) goes read-only on the
entry form, and a COGS-tracking item with journal history can't be deleted.

This isn't an arbitrary restriction. The type determines whether each *past*
transaction posted COGS and tracked stock. Retyping retroactively would make the
item's history inconsistent with its nature — old sales that relieved inventory
would suddenly belong to a type that never holds inventory, and vice versa. The
cost layers in `inventory_history` are immutable for the same reason.

If you truly need a different type, the move is to **create a new SKU** with the
correct type and retire (mark inactive) the old one — not to edit a dropdown.
This is why getting the type right on day one matters so much.

---

## A note on assemblies and labor

Assemblies (`ma`, `sa`) are COGS-tracking, and their cost is the sum of their
bill-of-materials components. But there's a deliberate rule worth knowing at the
concept level: **labor and charge lines on a BOM are not capitalized into the
assembly's inventory value.** When an assembly is built, only the COGS-tracking
component costs roll into the finished item's cost; a `lb` (labor) or `ci`
(charge) BOM line is skipped for capitalization.

That's intentional, not a bug — labor has its own GL path to COGS, and
capitalizing it into inventory too would double-count it. The full reasoning is
in [Assemblies](../04-modules-in-depth/03-inventory/03-assemblies.md).

---

## Choosing a type

A quick decision path for the common cases:

1. **A physical thing you stock and resell?** → `si` (or `sr` if each unit needs
   a serial number). These are most of your catalog.
2. **Built from component parts?** → `ma` (or `sa` for serialized finished
   units). Carries a bill of materials.
3. **Sized/colored variants of one product?** → `ms` (Master Stock); Bizuno
   generates the `mi` variants for you.
4. **A service, labor, or freight/charge line?** → `sv` / `lb` / `ci`. No
   inventory value.
5. **Bought to order, never stocked (drop-ship, one-off)?** → `ns`.
6. **Pure text on a form, no price?** → `ds`.

If you're unsure between a stocked good and a service, ask: *do I carry this on
the balance sheet as an asset before I sell it?* Yes → a COGS-tracking type.
No → a non-COGS type.

---

## Why this matters

The type code is a permanent accounting decision dressed up as a dropdown. It
decides whether an item is an asset you carry and relieve, or an expense/service
with no inventory weight — and once you've transacted, it's settled. Get the
COGS-vs-not question right for what you actually sell and the GL, the balance
sheet, and your margins all come out correct without further thought.

---

## Related

- [Inventory types](../04-modules-in-depth/03-inventory/01-inventory-types.md) — the full per-code reference
- [History and costing](../04-modules-in-depth/03-inventory/02-history-and-costing.md) — FIFO/LIFO/average mechanics
- [Assemblies](../04-modules-in-depth/03-inventory/03-assemblies.md) — BOMs and the labor-capitalization rule
