---
title: Work Orders / Production
category: Inventory
order: 4
status: published
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
> build process with stock allocation — not work centers, routings, or capacity
> planning. (Labor *cost* you can capture, via labor items on the
> [bill of materials](./03-assemblies.md#costing-labor-and-overhead).) Read
> [Honest scope](#honest-scope) before you commit a real manufacturing operation
> to it.

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

- **No work centers, routings, or capacity/scheduling.** Steps are a checklist,
  not a routed operation through resources.
- **Labor cost, but not labor *time*.** You can fold labor and overhead **cost**
  into the build by putting labor items on the
  [bill of materials](./03-assemblies.md#costing-labor-and-overhead); what steps
  don't do is capture actual hours worked or feed a time clock.

For batch builds, kit assembly, itemized labor cost, and a documented sign-off
trail, this is a genuine asset. For full discrete or process manufacturing with
routed work centers, treat it as a stepping stone, not a destination.

---

## Quality stop-work and the dashboard

A work order can be halted for a quality problem, and the place that surfaces is
the **Quality module's stop-work tickets** ([CA/PA tickets](../05-quality/01-ca-pa-tickets.md)).
There is **no automated data link** between a quality ticket and a specific work
order — holding a build for a quality issue is a human-coordinated process, not a
system gate. What gives management *visibility* into it is the **Stop-Work
dashboard** (`qa_stop_work`), which lists open stop-work tickets at a glance, each
linking through to the corrective-action record.

> **About dashboards.** A Bizuno dashboard is a summary widget that gives you
> at-a-glance data on the page where it's relevant. Each dashboard declares a
> **category** matching a major menu heading (Customers, Vendors, Inventory,
> Banking, General Ledger, Quality, …), and Bizuno shows it on that heading's
> landing page; dashboards can also be placed on the **home page** so management
> sees the most important numbers the moment they log in. A user adds the
> dashboards they want from the available set for that page.
>
> The Stop-Work dashboard ships under the **Quality** category. To put critical
> safety/quality status in front of management immediately, add it to the **home
> page** dashboards — that's exactly the kind of summary the home dashboard is for.

---

## Related

- [Assemblies](./03-assemblies.md) — the bill of materials and build costing
- [History and costing](./02-history-and-costing.md) — how component cost is drawn
- [CA/PA tickets](../05-quality/01-ca-pa-tickets.md) — the (separate) Quality module
- [Roles and security](../../05-administration/01-roles-and-security.md) — the access model behind `woProd`
