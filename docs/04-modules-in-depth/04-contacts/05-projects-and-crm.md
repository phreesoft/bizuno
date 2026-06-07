---
title: Projects and CRM
category: Contacts
order: 5
status: draft
audience: [bookkeeper]
last-updated: 2026-06-07
---

# Projects and CRM

Bizuno's CRM and project tracking ride on the same contacts table as everything
else — a project is a contact (`ctype_j`), a lead is a contact (`ctype_i`). This
page covers the sales-pipeline and follow-up features, and is honest about where
"project accounting" stops.

> **Set expectations up front.** Project tracking here is a **pipeline and
> reference** tool, not a project-ledger. There is **no `project_id` on journal
> transactions**, so Bizuno does not natively roll invoices/POs/time into a
> per-project P&L. See [The limits of project accounting](#the-limits-of-project-accounting).

---

## Projects (`ctype_j`)

A project is created as a contact carrying the project role, with its detail stored
in metadata (`crm_project`). The project record holds:

- **`proj_num`** — an auto-assigned project number
- **`title`** — the project name
- **`contact_id`** — the customer the project belongs to
- **`status`** — a sales/delivery pipeline stage (prospect → contacting →
  point-of-contact identified → follow-up → quote → sale → engineering → doc
  development → closed / no-opportunity)
- **`market`**, **`assigned_to`**, **`working_by`** — classification and ownership
- **`reminder_date`** and **`notes`** — follow-up scheduling and free notes

So a project is essentially an opportunity/job attached to a customer, moved
through a status pipeline.

---

## CRM contacts and outreach

### Leads (`ctype_i`)

CRM contacts are leads/prospects flagged `ctype_i`, managed under the `mgr_i`
permission. They're contacts you're working but haven't necessarily transacted
with yet.

### Action codes

Outreach is logged with **CRM action codes**, configured in
`options_crm_actions`. The shipped set:

| Code   | Meaning       |
|--------|---------------|
| `new`  | New call      |
| `ret`  | Return call   |
| `flw`  | Follow up     |
| `lead` | New lead      |
| `trn`  | Training      |
| `inac` | Inactive      |

Each logged action is written to **`contacts_log`** (date, who, action, notes), so
a contact accumulates an outreach history.

### Campaigns

Promotional email **campaigns** live in their own table (`crmPromos`) — a campaign
has a title, start/end dates, subject, and body, and targets contacts who've opted
into the newsletter (or a chosen distribution). Sends are tracked per campaign in
`crmPromos_history`.

### Reminders / follow-ups

The **CRM Reminder dashboard** surfaces follow-ups whose `reminder_date` has
arrived, moving due items into the rep's current reminder list (with optional
recurrence). This is how sales follow-ups stay visible — set a reminder date on a
project or lead and it surfaces on the dashboard when due. (For how dashboards
appear per menu heading and on the home page, see
[Work orders → dashboards](../03-inventory/04-work-orders-production.md#quality-stop-work-and-the-dashboard).)

---

## The limits of project accounting

This is the part to be clear-eyed about. Projects are linked to a **customer**, not
to **transactions**:

- **No `project_id` on `journal_main`.** Invoices, POs, and other postings record a
  `contact_id`, not a project. So you cannot tag a specific invoice line to a
  project out of the box.
- **No built-in per-project P&L.** Because transactions aren't project-stamped,
  there's no native "profitability by project" report.
- **No core time tracking** feeding project cost.

What you *can* do: track the opportunity/job through its pipeline, schedule
follow-ups, and — if a customer maps one-to-one to a project — aggregate that
customer's transactions with a [PhreeForm](../02-phreeform/01-report-engine-overview.md)
report. True multi-project-per-customer cost rollups would require a customization
that stamps a project reference onto transactions.

> Use projects for **pipeline and CRM**. If you need job-costing across many
> projects per customer, scope that as an extension rather than expecting it from
> the box.

---

## Related

- [The contacts table](./01-the-contacts-table.md)
- [Customers](./02-customers.md)
- [PhreeForm](../02-phreeform/01-report-engine-overview.md) — for ad-hoc project/customer reports
