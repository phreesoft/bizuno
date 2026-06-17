---
title: PDF Rendering Issues
category: Troubleshooting
order: 4
status: published
audience: [admin, developer]
last-updated: 2026-06-16
---

# PDF Rendering Issues

Bizuno's forms and reports render to PDF through **tFPDF** (the UTF-8 fork of
FPDF), which replaced TCPDF as the sole PDF engine in **v7.4.0**. The migration
is invisible when it works and confusing when it doesn't. This is the diagnostic
table to cut triage time.

> **Version note.** If you've seen the tFPDF migration attributed to "7.3.9,"
> that release doesn't exist — it landed in **v7.4.0**. The two PHP-8 image
> hardening fixes came just after (width/height in 7.4.3, x/y in 7.4.4).

---

## Symptom → cause → fix

### `\tFPDF` class not found

**Cause:** `composer install` hasn't run, or wasn't re-run after the engine
change pulled in `setasign/tfpdf`. The renderers (`renderReport.php`,
`renderForm.php`) declare `class PDF extends \tFPDF`, so a missing vendor library
fatals immediately.
**Fix:** run `composer install` in the core directory so `vendor/setasign/tfpdf`
is present. On a shared host where you can't run composer, ensure the deploy
included the full `vendor/` tree.

### Report titles render as a literal `0`

**Cause:** the report's `title1text` / `title2text` resolved to empty or the
string `"0"`. TCPDF used to silently swallow that; tFPDF (FPDF) prints anything
that isn't an empty string — so a `"0"` title shows up verbatim.
**Fix:** v7.4.0 added a **`Header()` guard** that skips a title cell when the text
is empty *or* the literal `"0"`. If you still see it, the install is pre-v7.4.0 —
upgrade — or the report genuinely has `0` as title text; clear the title in the
report definition.

### Bold/italic/underline in table cells prints as plain text

**Cause:** cell HTML is rendered by the **`bizHTMLCell`** shim, which supports a
deliberately small subset: `<b>`/`<strong>`, `<i>`/`<em>`, `<u>`, `<br>`, and
`<font color="…">` (plus `<p>`/`<div>` normalized to line breaks). Everything
else is stripped.
**Fix:** this is by design — keep cell markup within that subset. Richer HTML
won't render; restructure the field instead of expecting full HTML support.

### Barcode prints as `[barcode err: …]`

**Cause:** the barcode generator (**picqer**) threw for that symbology/data
combination — e.g. data that isn't valid for the chosen type. The renderer
catches it and draws the `[barcode err: …]` placeholder rather than failing the
whole PDF.
**Fix:** check the [trace](./01-trap-and-trace-files.md) for the underlying
error, and verify the data is valid for the symbology. The default symbology is
**Code 128 (C128)**; fall back to it if a more specialized type rejects your data.

### Non-ASCII characters render as `?`

**Cause:** tFPDF is UTF-8 capable, but the **bundled core fonts** (Helvetica,
Times, Courier) only carry Latin glyphs. Characters outside their range can't be
drawn.
**Fix:** register a TrueType font that includes the glyphs you need with
`AddFont(<name>, …, true)` (the TrueType/Unicode form) and `SetFont` to it for
the affected text. The standard three won't gain extended glyphs no matter what.

### Multi-form page numbering shows a literal `{nb1}`

**Cause:** total-pages placeholders like `{nb1}` are resolved by the renderer's
`_beginpage` / `_putpages` overrides (which track per-form page groups). A
literal `{nb1}` in the output means those overrides didn't fire — classically
because a custom form's PDF class still extends the old `\TCPDF` base left over
from a pre-migration `myExt/` form.
**Fix:** update the custom form's class to extend the current tFPDF-based `PDF`
renderer so the page-group overrides are in effect.

### PDF download starts then fails / is corrupt

**Cause:** the output stream wasn't clean — stray output (a `print_r`, a notice,
a BOM) got written before the PDF bytes, corrupting the file.
**Fix:** look for debug/echo output leaking into the response; a
[trap/trace](./01-trap-and-trace-files.md) helps locate the offending step. Make
sure nothing prints before the PDF is sent.

### PHP 8 "Unsupported operand types" on an image

**Cause:** a form/report image passed a non-numeric x/y/width/height into
`tFPDF::Image()`. Hardened in two steps: width/height cast to numeric in
**7.4.3**, x/y in **7.4.4**.
**Fix:** upgrade to 7.4.4+, which coerces those values (a blank dimension becomes
auto-size instead of fataling).

---

## Related

- [Custom forms](../04-modules-in-depth/02-phreeform/05-custom-forms.md) — building forms on the tFPDF renderer
- [Trap and trace files](./01-trap-and-trace-files.md) — where barcode/output errors surface
