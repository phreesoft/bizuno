---
title: Report Engine Overview
category: PhreeForm
order: 1
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Report Engine Overview

> **Status:** Stub — not yet drafted.

## What this page will cover

- PhreeForm = reports + forms, sharing a designer and data-binding system
- The report lifecycle: SQL → result set → field merge → render (HTML | PDF | CSV | Excel)
- Built-in reports vs. user-created reports
- Per-field grouping, totaling, processing, formatting
- Output channels: download, email, print
- Where the report definition is stored (`phreeform` table) and what the JSON struct contains
- The 7.3.9 tFPDF migration — what changed for end users (mostly nothing visible; barcodes now via picqer; HTML in cells via the in-tree shim)

## Why it matters

PhreeForm is the *only* report engine in Bizuno. Mastery here unlocks a lot
of custom value; ignorance traps users in the built-in reports.

## Related

- [Form designer](./02-form-designer.md)
- [Data binding and fields](./03-data-binding-and-fields.md)
