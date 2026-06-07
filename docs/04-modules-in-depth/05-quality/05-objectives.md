---
title: Quality Objectives
category: Quality
order: 5
status: draft
audience: [admin]
last-updated: 2026-06-07
---

# Quality Objectives

ISO-9001 §6.2 requires documented quality objectives — the goals your QMS is driving
toward, with owners, target dates, and recorded results. Bizuno's Objectives manager
produces exactly that documentation.

Unlike the other Quality items, objectives are **not** journal records — they're
stored as standalone metadata (no `journal_id`). That fits their nature: an objective
is a tracked goal, not a dated transaction.

---

## Defining an objective

An objective record carries:

| Field            | Meaning                                             |
|------------------|-----------------------------------------------------|
| Reference / title | What the objective is                              |
| Owner            | The responsible person (who entered/owns it)        |
| Target date      | When it should be achieved                          |
| Actual date      | When it actually was                                |
| Description      | The objective statement                            |
| Test / criteria  | How you'll measure success                          |
| Result           | The outcome, recorded at closure                    |
| Status           | From the shared quality status list                 |
| Closed / closed-by | Closure flag and who closed it                    |

> **Date-and-criteria, not a numeric KPI gauge.** An objective is framed as a goal
> with a **target date**, **success criteria**, and a written **result** — plus an
> action-item list (below). It is *not* a numeric KPI widget with an automatic
> "actual vs. target value" rollup. You state the target and criteria in words and
> record the result; the documentation is the deliverable.

---

## Action items

Each objective carries an embedded **action-item grid** — the concrete steps to reach
it. Each row records the **action**, the **employee** responsible, a **step/sequence**,
a **target date**, and an **actual/completion date**. This turns an objective from a
statement into a tracked plan.

---

## Tracking and closing

Objectives use the shared quality **status** values, so an objective progresses
through the same lifecycle vocabulary as tickets (in-process → … → closed
successful/unsuccessful). To **close** an objective, set its result text, mark it
closed (closed-by/­date are stamped), and give it a closed status.

---

## Reporting

Two dashboards surface objectives:

- **Open objectives** (`open_qual_obj`) — objectives not yet closed, filtered by
  target date within a chosen range (week/month/year). The board-of-directors view:
  what are we working toward and by when.
- **My objectives** (`my_qual_obj`) — the objectives owned by the current user.

Put **Open objectives** on a management/home dashboard so the QMS goals stay in
front of leadership between audits.

---

## Related

- [CA/PA tickets](./01-ca-pa-tickets.md) · [Audits](./02-audits.md) — the rest of the QMS toolkit
