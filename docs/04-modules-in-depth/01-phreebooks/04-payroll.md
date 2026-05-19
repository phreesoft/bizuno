---
title: Payroll
category: PhreeBooks
order: 4
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Payroll

> **Status:** Stub — not yet drafted.

## What this page will cover

- Bizuno's payroll model: which jurisdictions it's appropriate for, which it isn't (US-payroll-tax tables vs. "I just need to record the net pay")
- Setting up employees as contacts with `ctype_e` flag
- Pay periods, deductions, employer-side taxes
- Running a payroll: data-entry → review → post → print checks / record direct deposit
- GL postings: gross pay, tax withholdings, employer match, net pay to cash
- Year-end W-2 / 1099 report support (and where it stops short of being a full payroll service)
- Honest disclaimer: when you outgrow built-in payroll and should hand off to Gusto/ADP

## Why it matters

Payroll is the feature people most over-trust in self-hosted accounting
apps. Realistic scope-setting is more useful than over-selling.

## Related

- [Users vs. employees vs. contacts](../../05-administration/02-users-employees-contacts.md)
