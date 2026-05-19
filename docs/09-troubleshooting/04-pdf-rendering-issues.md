---
title: PDF Rendering Issues
category: Troubleshooting
order: 4
status: stub
audience: [admin, developer]
last-updated: 2026-05-15
---

# PDF Rendering Issues

> **Status:** Stub — not yet drafted.

## What this page will cover

Post-7.3.9 (tFPDF migration) symptoms and fixes:

- **`\tFPDF` class not found** — composer install didn't run, or didn't refresh after the require change
- **Report titles render as `0`** — the report's `title1text`/`title2text` is empty/placeholder; the 7.3.9 Header() guard suppresses these but only after upgrade
- **Bold/italic in table cells rendered as plain text** — the `bizHTMLCell` shim handles `<b>/<i>/<u>/<br>/<font color>` only; other markup degrades to plain text
- **Barcode rendering as `[barcode err: …]`** — picqer threw on the symbology / data combo; check trap log; fall back to C128 by default
- **Non-ASCII characters render as `?`** — tFPDF supports UTF-8 but you must call `AddFont(..., true)` for a TrueType font; the bundled Helvetica/Times/Courier don't include extended glyphs
- **Multi-form page numbering wrong (`{nb1}` literal in output)** — the `_beginpage`/`_putpages` overrides not firing; usually means PDF base class is wrong (extends `\TCPDF` left over from old myExt)
- **PDF download starts then fails** — output buffer not clean; debug with `msgDebugWrite()` and look for stray `print_r` output

## Why it matters

The PDF migration is invisible when it works and confusing when it doesn't.
Concrete diagnostic table cuts triage time.

## Related

- [Custom forms](../04-modules-in-depth/02-phreeform/05-custom-forms.md)
- [Release 7.3.9](../08-migration-and-upgrade/03-release-notes/7-3-9.md)
