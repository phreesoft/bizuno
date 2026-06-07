---
title: Inventory Types
category: Inventory
order: 1
status: draft
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# Inventory Types

Every inventory item carries a two-letter **type code** that decides almost
everything about how it behaves: whether it tracks stock quantity, whether it
posts Cost of Goods Sold, whether it can have a bill of materials, and where its
cost comes from. This page is the full per-code reference.

> **The type is effectively permanent.** Once an item has posted transactions,
> Bizuno locks its type (and its SKU and cost method) — the field becomes
> read-only. Changing type after you've transacted means rebuilding the item's
> history, not editing a dropdown. Pick deliberately. See
> [Why the type is permanent](#why-the-type-is-permanent).

---

## The two big questions

Two flags, derived from the type, drive the accounting:

- **Does it track stock?** Tracked types maintain a real `qty_stock` (and
  on-order `qty_po` / `qty_so`). Non-tracked types are set to a nominal quantity
  of 1 and never decrement.
- **Does it post COGS?** The constant `INVENTORY_COGS_TYPES` =
  `['ma','mi','ms','sa','si','sr']` lists the types that carry inventory cost and
  post to a COGS account when sold. Everything else expenses immediately or
  carries no cost at all.

Get those two right for what you sell and the rest follows.

---

## The full type reference

| Code | Name                   | Tracks stock | Posts COGS | BOM | Notes                                             |
|------|------------------------|:------------:|:----------:|:---:|---------------------------------------------------|
| `si` | Stock Item             | ✓            | ✓          | —   | The default. A physical good you buy and sell.    |
| `sr` | Serialized Stock       | ✓            | ✓          | —   | Like `si`, but each unit carries a serial number. |
| `ma` | Assembly               | ✓            | ✓          | ✓   | Built from component SKUs via a bill of materials. |
| `sa` | Serialized Assembly    | ✓            | ✓          | ✓   | An assembly whose finished units are serialized.  |
| `ms` | Master Stock           | ✓            | ✓          | —   | A template that generates variant items (`mi`) from options (size/color/…). |
| `mi` | Master Stock Sub-Item  | ✓            | (see note) | —   | Auto-generated variant of an `ms` master. Hidden type — you don't create these by hand. |
| `ns` | Non-stock              | —            | —          | —   | Bought/sold but not inventoried (drop-ship, one-offs). |
| `lb` | Labor                  | —            | —          | —   | Labor / hours.                                    |
| `sv` | Service                | —            | —          | —   | A service you sell.                               |
| `sf` | Flat-Rate Service      | —            | —          | —   | A fixed-price service charge.                     |
| `ci` | Charge                 | —            | —          | —   | A miscellaneous charge line.                      |
| `ai` | Activity               | —            | —          | —   | An activity/time line.                            |
| `ds` | Description            | —            | —          | —   | A text-only line — no price, no cost.             |
| `ia` | Assembly Part          | ✓            | —          | —   | Internal/hidden — used behind the scenes for assembly components. |

> **About `mi` and `ia`.** They relate to the COGS-types machinery because they
> track stock cost, but they're flagged `gl_cogs = false` — they don't post their
> own COGS legs and aren't types you choose from the dropdown. `mi` is spawned by
> a Master Stock item; `ia` is internal. You'll work with the other twelve.

---

## Tracked vs. non-tracked, in practice

**Tracked** types (`si`, `sr`, `ma`, `sa`, `ms`, `mi`) are the inventory you count.
They decrement on sale, increment on receipt, carry a cost layer in
`inventory_history`, and post COGS at sale time. Their value sits on the balance
sheet as an asset until sold. See [History and costing](./02-history-and-costing.md).

**Non-tracked** types (`ns`, `lb`, `sv`, `sf`, `ci`, `ai`, `ds`) don't hold a real
quantity — Bizuno parks `qty_stock` at 1 so they behave on documents but never
deplete. They have no COGS leg; the expense (if any) is recognized when you buy
the underlying thing, not when you sell the line. Use them for services, labor,
freight/charge lines, and pure description rows.

---

## Type-specific behavior

### Assemblies (`ma`, `sa`)

Only `ma` and `sa` can carry a **bill of materials** — the component list that
defines what the finished item is built from. The BOM lives in item metadata and
drives both build cost and "how many can I build" availability. Full detail in
[Assemblies](./03-assemblies.md).

### Master Stock (`ms` → `mi`)

A Master Stock item is **not** an assembly — it's a template. You define *options*
(size, color, etc.) and Bizuno generates a child **`mi`** variant per
combination, each with its own SKU and stock. You manage the master; the variants
are produced from it and are hidden as a standalone type.

---

## Why the type is permanent

Two safeguards enforce this:

1. **Edit lock.** When an item has any journal history, the entry form marks
   `inventory_type`, `sku`, and `cost_method` **read-only**. Because read-only
   fields aren't submitted, they can't be changed even by tampering with the form.
2. **Delete block.** You can't delete an item that has GL journal entries if it's
   a COGS-tracking type — its history would be orphaned.

The reason is integrity: the type determines whether each past transaction posted
COGS and tracked stock. Retyping retroactively would make historical postings
inconsistent with the item's nature. If you truly need a different type, create a
**new SKU** with the correct type and retire the old one (mark it inactive).

> **Decision shortcut.** Physical thing you stock and resell → `si` (or `sr` if
> serialized). Built from parts → `ma`/`sa`. Sized/colored variants → `ms`.
> Service, labor, or freight → `sv`/`lb`/`ci`. Bought-to-order, never stocked →
> `ns`. Pure text on a form → `ds`.

---

## Related

- [History and costing](./02-history-and-costing.md) — how tracked types are costed
- [Assemblies](./03-assemblies.md) — `ma`/`sa` bills of materials
- [Inventory types and COGS](../../02-core-concepts/05-inventory-types-and-cogs.md) — the concept
- [Journals (the journal_id reference)](../01-phreebooks/02-journals.md) — what posts the COGS legs
