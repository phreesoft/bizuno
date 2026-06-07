---
title: Work Orders / Production
category: Inventory
order: 4
status: draft
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# Work Orders / Production

A **work order** (WO) is the shop-floor side of building an
[assembly](./03-assemblies.md): it tracks the production steps, who did and checked
each one, and any data captured along the way — and when the build is done, it
posts the accounting automatically. This is where Bizuno crosses from "accounting
app" into **light MRP**.

> **Light** is the operative word. Work orders give you a stepped, signed-off
> build process with stock allocation — not work centers, routings, capacity
> planning, or labor capture. Read [Honest scope](#honest-scope) before you commit
> a real manufacturing operation to it.

---

## Two records, two jobs

| Record                 | Journal | Role                                                  |
|------------------------|:-------:|------------------------------------------------------|
| **Work Order**         | jID 32  | The workflow tracker — steps, signoffs, allocation   |
| **Assembly**           | jID 14  | The GL posting of the actual build                    |

The work order is **planning and execution**; the assembly is the **accounting**.
When a work-order step that's flagged to post completes, Bizuno creates and posts a
jID 14 [assembly](./03-assemblies.md#building-what-posts) for you — consuming
components and producing the finished item. The two are linked by reference number.

---

## The Production Manager

Work orders live under the Production Manager (`inventory/build`), with child
screens for the step **Designer** and the **Tasks** list. The controller methods
map to the lifecycle:

| Action            | What it does                                                      |
|-------------------|------------------------------------------------------------------|
| `add`             | Start a WO by choosing the assembly SKU to build                  |
| `edit` / `save`   | Set/adjust quantity and due date; re-allocates components if qty changes |
| `details`         | Show the production-steps table with the current step highlighted |
| `saveStep`        | Advance a step — record manufacturing & QA signoffs, capture any required data, and (if the step is flagged) auto-post the jID 14 build |
| `delete`          | Remove the WO — un-posts the jID 14 if it was already assembled   |
| `allocateAdj`     | Reserve component stock against open WOs (`qty_alloc`)            |

### Lifecycle and status

A work order's headline state is the `closed` flag (0 = open, 1 = closed), with
`post_date` as the create date, `terminal_date` as the due date, and `closed_date`
stamped when the final step completes.

Within a WO, the **production steps** are stored as metadata. Each step carries:

- a **complete** flag,
- **manufacturing** and **QA** signoffs (`mfg_id` / `qa_id`) with their dates,
- an optional **captured value** (`data_value`) when the step requires a reading
  or measurement.

So a build progresses step → step, each one signed off (and optionally
QA-checked and data-stamped), until the posting step fires the assembly and the
order closes.

### Component allocation

If a WO is set to allocate, opening it **reserves** its components by raising
`qty_alloc` on those items — so the stock shows as spoken-for and won't be
double-committed. Changing the WO quantity re-allocates; deleting or completing it
releases the reservation. This is how build-to-order coexists with normal sales
demand on the same stock.

---

## Security: the parent-menu access bridge

Work-order access is governed by the `woProd` security key. There's a wrinkle
worth knowing if you administer roles:

The role editor only exposes checkboxes for the **leaf** screens (the Designer and
Tasks children), not for the Production Manager parent. To keep the parent
reachable, Bizuno (since **7.3.9**, via `removeOrphanMenus()`) gives a routable
parent menu the **maximum access level of its children** — so granting a user the
child screens automatically grants the parent it lives under.

Practical upshot: **grant the child permissions and the Production Manager opens**;
you won't find (or need) a separate checkbox for the parent.

---

## Honest scope

What work orders **do**:

- Stepped builds with manufacturing + QA signoff per step
- Optional data capture per step
- Component allocation / reservation (`qty_alloc`)
- Automatic GL posting of the build (jID 14) on completion
- Tie a build to the assembly definition for repeatability

What they **don't** do — by design, so you can plan around it:

- **No labor or overhead capture.** Steps record *who* and *when*, but no labor
  hours or rates feed cost. Build cost is components only (see
  [Assemblies → Honest limits](./03-assemblies.md#honest-limits)).
- **No work centers, routings, or capacity/scheduling.** Steps are a checklist,
  not a routed operation through resources.
- **No automated Quality "stop-work" link.** The [Quality module](../05-quality/01-ca-pa-tickets.md)
  has its own stop-work tickets, but there is **no built-in tie** between a quality
  ticket and a work order — holding a WO for a quality issue is a manual,
  human-coordinated process, not an automated gate. (If you need that link,
  it's a customization, not a setting.)

For batch builds, kit assembly, and a documented sign-off trail, this is a genuine
asset. For full discrete or process manufacturing, treat it as a stepping stone,
not a destination.

---

## Related

- [Assemblies](./03-assemblies.md) — the bill of materials and build costing
- [History and costing](./02-history-and-costing.md) — how component cost is drawn
- [CA/PA tickets](../05-quality/01-ca-pa-tickets.md) — the (separate) Quality module
- [Roles and security](../../05-administration/01-roles-and-security.md) — the access model behind `woProd`
