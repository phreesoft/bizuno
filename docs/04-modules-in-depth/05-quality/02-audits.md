---
title: Audits
category: Quality
order: 2
status: draft
audience: [admin]
last-updated: 2026-06-07
---

# Audits

Audits give you the demonstrable, recurring inspection records an ISO-9001 program
depends on. An audit in Bizuno is a **scheduled, recurring task** (`journal_id =
31`) that the system reminds you about and turns into a dated, signed-off record when
it's due.

Audits, [training](./03-training.md), and [maintenance](./04-maintenance.md) all
share the same **recurring-task engine** described below — learn it once.

---

## Definition vs. instance

There are two halves to every recurring quality task:

- **The definition** (the template) — created on the admin side, stored as a
  reusable record. For an audit this holds the title, the **frequency**, a **lead
  time**, the assigned **auditor**, the store, the next **due date**, and notes.
- **The instance** (the executed record) — a dated `journal_main` entry
  (`journal_id = 31`) representing one actual performance of the audit. Instances
  link back to their definition.

So "Quarterly safety audit" is one definition; each quarter's actual audit is an
instance.

## How the recurring engine works

```
   definition (frequency + lead time + next due date)
            │
            │  the task dashboard checks daily
            ▼
   due date − lead time ≤ today ?  ──►  auto-create an open instance (post_date empty)
            │
            │  you perform the audit and complete it
            ▼
   instance stamped complete (post_date set)  +  definition's next due date rolls forward
```

- **Frequency** sets the recurrence (e.g. monthly, quarterly, annual).
- **Lead time** (e.g. 1 week) is how far *ahead* of the due date the task surfaces,
  so you have warning.
- The matching **task dashboard** (`audit_tasks`) is what watches the calendar,
  creates the open instance when it comes due, and — on completion — advances the
  definition's next due date. Put it on a landing or home page so due audits can't
  slip.

This same pattern drives training and maintenance; only the record type differs.

---

## Auditor, schedule, scope

The definition carries:

- **Auditor** — the assigned user.
- **Frequency** and **lead time** — as above.
- **Store** — which location the audit covers (multi-store).
- **Next due date** — when it's next expected; rolls forward automatically.

---

## What audits do *not* (yet) do

Be realistic about the depth here:

- **No structured checklist.** An audit captures a **notes** field, not a
  line-by-line checklist with per-item pass/fail. If you need a formal checklist,
  attach it as a document or record results in the notes.
- **No automated finding → CA/PA link.** When an audit turns up a problem, there is
  **no one-click "create corrective action from this finding."** You open a
  [CA/PA ticket](./01-ca-pa-tickets.md) separately and reference the audit. The two
  modules are independent.

These are honest limits — the module gives you the *cadence, assignment, and dated
record* an auditor wants to see, but the in-audit detail is freeform.

---

## Related

- [CA/PA tickets](./01-ca-pa-tickets.md) — where findings become tracked actions
- [Training](./03-training.md) · [Maintenance](./04-maintenance.md) — same recurring engine
- [Objectives](./05-objectives.md)
