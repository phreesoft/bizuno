---
title: Training
category: Quality
order: 3
status: draft
audience: [admin]
last-updated: 2026-06-07
---

# Training

Training records are a compliance artifact in regulated industries — proof that the
right people were trained, on schedule. Bizuno models training as a **recurring
task** (`journal_id = 34`) on the same engine as [audits](./02-audits.md): a
definition that recurs, and a dated instance each time it's performed.

---

## Definition vs. instance

- **The definition** (admin side) holds the training **title**, **frequency**, **lead
  time**, the assigned **trainer**, the store, the next **training date**, and notes.
- **The instance** is the dated `journal_main` record (`journal_id = 34`) for one
  delivery of that training, linked back to its definition and stamped complete when
  done.

The recurring engine — frequency + lead time, with the `training_tasks` dashboard
auto-creating the next instance when due and rolling the date forward — is the same
one [described for audits](./02-audits.md#how-the-recurring-engine-works). A
"annual safety refresher" is one definition that regenerates every year.

---

## Assigning training to people

Training is tied to staff by **role**: in the role editor you select which training
definitions apply to a role, so everyone in that role inherits the requirement. (An
employee's training references are also held in their
[contact metadata](../04-contacts/04-employees.md).)

> **Recording who completed what.** Completion is captured at the instance level
> (the dated record, with the trainer assigned). The model is **schedule-and-record**
> — define the training, get reminded when it's due, log that it happened.

---

## What training does *not* do

So you don't plan around features that aren't there:

- **No certificate or expiration tracking.** There's no certificate store and no
  per-credential expiration date field. Recurrence ("annual") is how you approximate
  "re-certify yearly," but Bizuno won't track an individual certificate's expiry as a
  distinct artifact.
- **No per-trainee gradebook.** Training records the event and its schedule, not
  per-person scores or pass/fail rosters. Use the notes/attachments for evidence.

For "who is due for training and when," the module is solid; for formal
credential-lifecycle management, it's a scheduler and a log, not an LMS.

---

## Reporting

The `training_tasks` dashboard surfaces training that's coming due or overdue (it
lists incomplete instances past their lead-time threshold). Place it on a landing or
home page so required training stays visible.

---

## Related

- [Audits](./02-audits.md) — the shared recurring-task engine
- [Employees](../04-contacts/04-employees.md) — where staff training references live
- [Maintenance](./04-maintenance.md)
