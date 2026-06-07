---
title: Assemblies
category: Inventory
order: 3
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# Assemblies

An **assembly** is an inventory item built from other items. You define what it's
made of (the bill of materials), and when you build it Bizuno consumes the
components and produces the finished good — moving cost from the component SKUs
into the assembled SKU. This page covers the data model and the costing; the
build workflow itself is in [Work orders / production](./04-work-orders-production.md).

> **Scope check.** Bizuno's assembly model is solid for kitting and light
> manufacturing, but it is **not** a full MRP — no work centers, routings, or
> capacity planning. You *can*, however, build **labor and overhead into the
> assembly cost** by adding labor- or charge-type lines to the bill of materials
> (see [Costing labor and overhead](#costing-labor-and-overhead)).

---

## Which types are assemblies

Only two types carry a bill of materials:

- **`ma` — Assembly**: the normal built item.
- **`sa` — Serialized Assembly**: the same, with serial numbers on finished units.

(Don't confuse these with **`ms` Master Stock**, which generates *variants* from
options and is not an assembly — see [Inventory types](./01-inventory-types.md).)

---

## The bill of materials

The BOM is stored as item metadata (`bill_of_materials`) on the assembly SKU — a
list of component rows, each with:

| Field         | Meaning                          |
|---------------|----------------------------------|
| `sku`         | The component item               |
| `description` | Component description            |
| `qty`         | How many per one assembled unit  |

Edit it on the assembly item's **Assembly** tab (visible only for `ma`/`sa`). As
you build the list, Bizuno shows the rolled-up component quantity and cost in the
footer (`managerBOMList`).

> **The BOM locks once you transact.** After the assembly SKU has any posted
> journal activity, its bill of materials is locked from editing — changing it
> would make past builds inconsistent with the recipe. To change a recipe in
> flight, version it as a new SKU.

### Multi-level BOMs

A component can itself be an assembly, and Bizuno **does** handle that for
**availability** — the "how many can I build" calculation recurses through
sub-assemblies to find the limiting component.

**Cost rollup, however, does not recurse.** An assembly's build cost is computed
from the **direct** components' costs (one level). If you nest assemblies, build
the sub-assemblies first so their finished cost is current before you build the
parent — otherwise the parent rolls up a stale sub-assembly cost.

---

## Costing labor and overhead

Labor and overhead don't need a separate cost field — model them as **inventory
items** and drop them into the bill of materials like any other line:

1. Create a labor- or charge-type item — e.g. a Labor (`lb`) item *"Assembly
   labor"* with an `item_cost` of `$3.00` representing 0.1 hours of work (or
   however you prefer to unitize it). Do the same for overhead/burden if you
   track it.
2. Add that item to the assembly's BOM with the **quantity** you need — e.g. 5 ×
   *"Assembly labor"* for 0.5 hours of build time.

Bizuno includes these lines when it rolls up the assembly's cost
(`dbGetInvAssyCost` sums `qty × item_cost` across **every** BOM line, regardless of
type), so the finished item's standing cost reflects materials **plus** the labor
and overhead you've itemized. The **build-post** capitalizes those same lines into
the finished item's actual inventory value — so the posted cost (and the COGS when
the assembly is later sold) matches the rolled-up estimate, not just the materials.

### GL treatment of labor/overhead lines

Stocked components move value *between* asset accounts (component inventory → finished
inventory), so they self-balance. A non-stock labor/charge line has no inventory layer
to draw from, so the build needs an offsetting credit. Bizuno credits the **labor
item's Inventory/Asset GL account** (`gl_inv`) for the line's `qty × item_cost` while
debiting that amount into the finished-goods inventory value:

| Leg | Account | Build (qty > 0) | Un-build (qty < 0) |
|-----|---------|-----------------|--------------------|
| Finished assembly | assembly `gl_inv` (finished inventory) | **Debit** materials + labor | **Credit** materials + labor |
| Each stocked component | component `gl_inv` | **Credit** layer cost | **Debit** layer cost |
| Each labor/charge line | labor `gl_inv` (clearing) | **Credit** `qty × item_cost` | **Debit** `qty × item_cost` |

> **Configure the labor item's Inventory/Asset account deliberately.** Point it at a
> **labor-applied / overhead-absorbed clearing account** (a contra to wherever the
> actual labor/overhead expense lands), *not* a raw stock account. The build credits
> this account to relieve the period's labor/overhead expense and rolls that cost into
> the finished asset; it becomes COGS when the assembly sells. Labor lines are still
> non-stock, so no `qty_stock`, inventory layer, or COGS-usage record is created for
> them — only the balanced GL leg above. By default a labor/charge item's `gl_inv` is
> the **non-stock** account, so set it explicitly if you want a dedicated clearing account.

---

## Building: what posts

A build is recorded by the **Assembly journal (jID 14)**. Its `Post()`:

1. Reads the BOM, and for each **stocked** component creates a consumption leg
   (`gl_type='asi'`) — drawing the component's cost via its
   [costing method](./02-history-and-costing.md) (FIFO/LIFO/average) and
   **decrementing** its `qty_stock`.
2. For each **labor/charge** (non-stock) line, creates a balanced clearing leg
   (also `gl_type='asi'`) at `qty × item_cost`, crediting the line's `gl_inv`
   clearing account — no `qty_stock` or layer movement (see
   [Costing labor and overhead](#costing-labor-and-overhead)).
3. Creates the assembled-item leg (`gl_type='asy'`) — **incrementing** the
   assembly's `qty_stock` and adding a cost layer.
4. Rolls the cost: **assembled cost = components' COGS + labor/overhead**, and the
   finished unit cost = that total ÷ quantity built.
5. Marks the build closed.

The GL effect nets to zero — stocked value moves from the component SKUs to the
finished SKU, and labor/overhead moves from its clearing account into the finished
SKU. Reversing a build (un-post) puts the components back, debits the clearing
account back, and removes the finished units.

> You don't usually post a jID 14 by hand — it's created for you when a
> [work order](./04-work-orders-production.md) step completes. The jID 14 is the
> accounting record; the work order is the shop-floor workflow that drives it.

### Verifying labor is capitalized

To confirm a labor line flows into actual cost and COGS (not just the estimate):

1. Create a stocked component (e.g. `WIDGET-PART`, `item_cost` $5) and **receive 10**
   so it has a cost layer. Create a Labor (`lb`) item *"Assembly labor"*, `item_cost`
   $3, with its Inventory/Asset GL account set to a labor-applied clearing account.
2. Create an assembly `ma` item *"Widget"* with BOM: 2 × `WIDGET-PART` + 5 × *Assembly
   labor*. The cost roll-up should read **$25** (`2×5 + 5×3`).
3. **Build 1** Widget (post the jID 14). Verify:
   - `inventory_history` for `WIDGET`: one new layer, `unit_cost = 25` (not 10).
   - `journal_item` rows for the build: a `gl_type='asy'` debit of $25 to the Widget's
     `gl_inv`; an `asi` credit of $10 to the part's `gl_inv`; an `asi` credit of **$15**
     to the labor clearing account. Debits = credits = $25.
4. **Sell 1** Widget. The sale's COGS leg (`gl_type='cog'`) should be **$25**, including
   the $15 labor — confirm via the GL or `dbGetCOGSj12()`.
5. **Un-build** (post a jID 14 with qty −1, or un-post the build). Confirm the legs
   reverse exactly: part `gl_inv` debited $10, labor clearing debited $15, Widget
   `gl_inv` credited $25 — net zero, and the Widget layer/`qty_stock` is removed.

---

## Honest limits

What the assembly model **does**:

- Component bills of materials on `ma`/`sa` items
- Build / un-build with correct stock movement and component-cost rollup
- **Labor and overhead** in cost, via labor/charge items on the BOM (above)
- Multi-level **availability** (recursive "can I build N?")
- "How many can I build per store" capability view

What it **does not** do (so you don't design around features that aren't there):

- **No work centers, routings, or capacity planning** — the build is a recipe,
  not a routed operation.
- **No recursive cost rollup** — parent cost reflects direct components only;
  build sub-assemblies first so their cost is current.
- **No phantom assemblies** — there's no "don't stock the assembly level" flag;
  every build moves real stock for both components and the finished item.

For kitting and light builds — including itemized labor and overhead — this is
plenty. For routed operations through work centers with capacity scheduling,
you've crossed into territory a dedicated MRP handles better.

---

## Related

- [Work orders / production](./04-work-orders-production.md) — the build workflow that drives jID 14
- [History and costing](./02-history-and-costing.md) — how component costs are drawn
- [Inventory types](./01-inventory-types.md) — `ma`/`sa` vs. `ms`
- [Journals (the journal_id reference)](../01-phreebooks/02-journals.md#jid-14--assembly-j14php)
