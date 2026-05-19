---
title: Custom Forms
category: PhreeForm
order: 5
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# Custom Forms

> **Status:** Stub — not yet drafted.

## What this page will cover

- Starting from a built-in form (copy → edit → save with new name)
- Per-customer form override (each customer can have a default form)
- Form folders (`cust:j10` for invoices, `cust:rtn` for returns, etc.)
- Common customizations: branded letterhead, custom line layouts, multi-language variants
- Sharing forms across installs — export/import JSON
- Troubleshooting a form that renders wrong post-7.3.9 (tFPDF migration considerations: HTML in cells, barcode types)

## Why it matters

Branded documents are the #1 reason customers ask for a custom form. Doc has
to walk through this without requiring developer skills.

## Related

- [Form designer](./02-form-designer.md)
- [PDF rendering issues](../../09-troubleshooting/04-pdf-rendering-issues.md)
