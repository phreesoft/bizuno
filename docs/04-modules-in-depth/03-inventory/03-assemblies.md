---
title: Assemblies
category: Inventory
order: 3
status: draft
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
> manufacturing, but it is **not** a full MRP. In particular: **labor and overhead
> are not captured** — an assembly's cost is the sum of its components, nothing
> more. If you need labor/burden in standard cost, you'll add it through the GL by
> other means. See [Honest limits](#honest-limits).

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

## Building: what posts

A build is recorded by the **Assembly journal (jID 14)**. Its `Post()`:

1. Reads the BOM, and for each component creates a consumption leg
   (`gl_type='asi'`) — drawing the component's cost via its
   [costing method](./02-history-and-costing.md) (FIFO/LIFO/average) and
   **decrementing** its `qty_stock`.
2. Creates the assembled-item leg (`gl_type='asy'`) — **incrementing** the
   assembly's `qty_stock` and adding a cost layer.
3. Rolls the cost: **assembled cost = sum of the components' COGS**, and the
   finished unit cost = that total ÷ quantity built.
4. Marks the build closed.

The GL effect nets within your inventory/COGS accounts — value simply moves from
the component SKUs to the finished SKU. Reversing a build (un-post) puts the
components back and removes the finished units.

> You don't usually post a jID 14 by hand — it's created for you when a
> [work order](./04-work-orders-production.md) step completes. The jID 14 is the
> accounting record; the work order is the shop-floor workflow that drives it.

---

## Honest limits

What the assembly model **does**:

- Component bills of materials on `ma`/`sa` items
- Build / un-build with correct stock movement and component-cost rollup
- Multi-level **availability** (recursive "can I build N?")
- "How many can I build per store" capability view

What it **does not** do (so you don't design around features that aren't there):

- **No labor or overhead** in assembly cost — components only.
- **No recursive cost rollup** — parent cost reflects direct components only;
  build sub-assemblies first.
- **No phantom assemblies** — there's no "don't stock the assembly level" flag;
  every build moves real stock for both components and the finished item.

For kitting and straightforward builds this is plenty. For routings, work centers,
labor capture, and burden, you've crossed into territory a dedicated MRP handles
better.

---

## Related

- [Work orders / production](./04-work-orders-production.md) — the build workflow that drives jID 14
- [History and costing](./02-history-and-costing.md) — how component costs are drawn
- [Inventory types](./01-inventory-types.md) — `ma`/`sa` vs. `ms`
- [Journals (the journal_id reference)](../01-phreebooks/02-journals.md#jid-14--assembly-j14php)
