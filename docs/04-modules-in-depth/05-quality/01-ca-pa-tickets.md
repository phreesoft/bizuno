---
title: CA/PA Tickets
category: Quality
order: 1
status: draft
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# CA/PA Tickets

Corrective and Preventive Action tickets are the heart of the Quality module — the
documented record that something went wrong (or might), what you did about it, and
proof you closed the loop. For shops running ISO-9001, this is often *the* reason
they chose Bizuno over a plain bookkeeping app.

A ticket is a journal record (`journal_id = 30`) plus a set of workflow panels held
in metadata. This page covers the corrective-vs-preventive distinction, the status
lifecycle, the stop-work flag and its dashboard, and what you can link a ticket to.

---

## Corrective vs. preventive

One field decides which kind of action a ticket is — the **Preventable** flag:

- **Corrective Action (CA)** — preventable = no. Something already happened; you're
  correcting it.
- **Preventive Action (PA)** — preventable = yes. A risk you're acting on *before*
  it happens.

Same ticket form, same lifecycle; the flag is how you (and your reports) tell them
apart.

---

## The status lifecycle

A ticket moves through a defined set of statuses (the `options_qa_status` list).
The shipped set:

| Code | Status                  |
|-----:|-------------------------|
| 1    | CA/PA Created           |
| 2    | In Process              |
| 3    | Created & Assigned      |
| 4    | Investigated            |
| 5    | Implemented             |
| 6    | Audited                 |
| 7    | Monitor                 |
| 8    | **Stop Work**           |
| 85   | Continuous Improvement  |
| 90   | Closed — Unsuccessful   |
| 99   | Closed — Successful     |

A typical flow: **created → assigned → investigated (root cause) → corrective action
implemented → audited/monitored → closed successful.** The status list is editable
(it's stored as a common option set), so you can tailor the wording to your QMS.

### The workflow panels

The ticket form is organized into panels that capture the *who/when/what* at each
stage, each stamped with the user and date who completed it:

- **Stop-work** — immediate containment (who stopped it, when, notes).
- **Work-around** — the interim fix while you investigate.
- **Root-cause analysis** — the investigation and findings.
- **Corrective action** — what was implemented, by whom, and the completion date.
- **Closure** — who closed it and the final disposition.

This panel-by-panel sign-off trail is what makes the ticket an audit artifact, not
just a note.

---

## Stop-work and the dashboard

**Stop-Work** (status 8) is the serious one — work has been halted over a quality
issue. Because that needs management eyes immediately, it has a dedicated **Stop-Work
dashboard** (`qa_stop_work`, in the Quality dashboard category) that lists current
stop-work tickets, each linking straight to the ticket.

> **Make it visible.** Dashboards in Bizuno attach to a menu heading by **category**
> and can also be placed on the **home page**. Putting the Stop-Work dashboard on the
> home page means a manager sees a halted line the moment they log in — which is the
> whole point of a stop-work. See the
> [dashboard concept](../03-inventory/04-work-orders-production.md#quality-stop-work-and-the-dashboard).

---

## What you can link a ticket to

- **A contact** (`contact_id`) — the customer or vendor the issue relates to.
- **A SKU** (`sku_id`) — the inventory item involved.
- **A store** — for multi-store operations.

> **No direct work-order link.** A ticket can reference a contact and a SKU, but
> there is **no built-in field tying a ticket to a specific work order** (jID 32).
> Cross-reference it in the notes if you need that association — it isn't a
> structured link.

---

## Reporting

Beyond the stop-work dashboard, the module ships several CA/PA views:

- **Open CA / open corrective** dashboards — what's still in flight.
- **Issues by vendor** (`qa_by_vendor`) and **issues by SKU** (`qa_by_sku`) — pie
  charts with quarterly trend, for spotting your worst offenders.
- The ticket manager itself filters by **status**, **open/closed**, **period**, and
  **store**, and shows the creation date column.

These cover the common quality questions: where are problems concentrated, what's
overdue, and is the trend improving.

> Tickets are **period-independent** in the manager (a CA opened in one month is
> often worked across several), so the default view is "all periods," not the
> current fiscal period.

---

## Related

- [Audits](./02-audits.md) — the recurring-task sibling
- [Returns and credit memos](../../03-daily-workflows/04-returns-and-credit-memos.md)
- [Work orders / production](../03-inventory/04-work-orders-production.md#quality-stop-work-and-the-dashboard) — the dashboard concept
