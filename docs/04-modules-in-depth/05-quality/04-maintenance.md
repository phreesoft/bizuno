---
title: Maintenance
category: Quality
order: 4
status: published
audience: [admin]
last-updated: 2026-06-07
---

# Maintenance

Preventive maintenance scheduling is the feature shops outgrow spreadsheets for:
recurring tasks that remind you *before* equipment is due for service. Bizuno models
maintenance as a **recurring task** (`journal_id = 35`) on the same engine as
[audits](./02-audits.md) and [training](./03-training.md).

> Maintenance is managed from the **Maintenance manager** (it lives in the
> administration module, surfaced alongside the Quality tools). The mechanics are
> identical to the other recurring tasks.

---

## Definition vs. instance

- **The definition** holds the maintenance task **title**, **frequency**, **lead
  time**, the assigned **technician**, who created it, and the next **maintenance
  date**.
- **The instance** is the dated record (`journal_id = 35`) for one performance of the
  task, created automatically when due and stamped complete when done.

The recurrence works exactly as
[described for audits](./02-audits.md#how-the-recurring-engine-works): set a
frequency and a lead time, and the `maint_tasks` dashboard creates the next due
instance ahead of time and rolls the date forward on completion. "Lubricate
conveyor — monthly" is one definition that regenerates each month.

---

## Scheduling fields

- **Frequency** — how often (weekly, monthly, quarterly, annual, …).
- **Lead time** — how far ahead the task surfaces as due (1d / 2d / 1w / 2w / 1m).
- **Assigned technician** — who's responsible.
- **Next maintenance date** — rolls forward automatically.

---

## Reporting

The `maint_tasks` dashboard lists maintenance coming due (and overdue) by applying
the lead-time offset to each task's date and showing the incomplete ones. Put it on a
home/landing dashboard so nothing slips.

---

## What maintenance does *not* do

- **No fixed-asset / equipment-record link.** This is the important one: maintenance
  tasks are **standalone** — they are **not** tied to a Fixed Assets record or an
  equipment master. A task names the equipment in its **title**; there's no
  structured `asset_id` linking the maintenance history to a depreciating asset. If
  you expected "click an asset, see its maintenance log," that integration isn't
  there.
- **No meter/usage-based triggers.** Scheduling is **calendar-based** (frequency +
  date), not "every 500 hours / 10,000 miles."

For time-based preventive maintenance with reminders and a dated completion log, it
does the job; for asset-linked or usage-metered maintenance, it's calendar
scheduling only.

---

## Related

- [Audits](./02-audits.md) · [Training](./03-training.md) — same recurring engine
- [Objectives](./05-objectives.md)
