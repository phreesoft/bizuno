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
and overhead you've itemized.

> **One nuance to know.** Labor/charge items are non-stock, so at *build-post*
> time (jID 14) they aren't moved through inventory/COGS accounts the way stocked
> components are — they contribute to the item's **computed cost**, not to a
> separate GL inventory leg. For most shops the computed cost is exactly what you
> want on margin reports; just be aware the labor isn't capitalized as its own
> ledger entry during the build.

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
