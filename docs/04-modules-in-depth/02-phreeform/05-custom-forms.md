---
title: Custom Forms
category: PhreeForm
order: 5
status: draft
audience: [admin]
last-updated: 2026-06-07
---

# Custom Forms

The number-one reason people customize PhreeForm is branded documents — your logo,
your layout, your terms on the invoice. You don't start from a blank page and you
don't need developer skills: copy a built-in form, tweak it, save it under a new
name. This page covers that flow, how forms attach to journals, moving forms between
installs, and what to check if a form renders wrong after the 7.3.9 PDF change.

---

## Start from a built-in

Never edit a shipped form directly — copy it first:

1. Open the form list and pick the built-in closest to what you want (e.g. the
   stock sales invoice).
2. **Copy** it. This duplicates the whole definition under a new title, leaving the
   original intact (your fallback).
3. Edit the **copy** in the [designer](./02-form-designer.md) — drop in your logo,
   adjust columns, add terms text.
4. Save. Your version now appears alongside the built-in for that document type.

> Keep the original built-in untouched. If your custom form ever breaks, you can
> copy the built-in again and re-apply your changes.

---

## Form groups

Every form/report has a **`group_id`** that decides where it shows up. The format is
`module:context`. When you print from a transaction, Bizuno offers the forms whose
group matches that journal (via `getDefaultFormID()`):

| Journal                  | Group       |
|--------------------------|-------------|
| Sales Quote (9)          | `cust:j9`   |
| Sales Order (10)         | `cust:j10`  |
| Sales / invoice (12)     | `cust:j12`  |
| Credit Memo (13)         | `cust:j13`  |
| Cash Receipt (18) / Vendor Refund (17) | `cust:j18` |
| Point of Sale (19)       | `cust:j19`  |
| RFQ (3)                  | `vend:j3`   |
| Purchase Order (4)       | `vend:j4`   |
| Purchase / bill (6)      | `vend:j6`   |
| Vendor Credit (7)        | `vend:j7`   |
| Pay Bills (20) / POP (21) / Customer Refund (22) | `bnk:j20` |
| Assembly (14)            | `inv:j14`   |
| Adjustment (16)          | `inv:j16`   |

Plus non-journal groups for statements (`cust:stmt`, `vend:stmt`), labels
(`cust:lblc`, `vend:lblv`), letters (`cust:ltr`, `vend:ltr`), returns (`cust:rtn`),
work-order forms (`mfg:wo`), and reports (`gl:rpt`, `inv:rpt`, `mfg:rpt`, `hr:rpt`,
`ship:rpt`, `misc:rpt`). **Keep a custom form in the same group as the built-in you
copied** so it's offered in the right place.

---

## Choosing which form prints

When a journal has more than one form in its group, you pick at print time. There is
a **default form per document type** (the group's default), so the common case is
one click.

> **No per-customer default form.** A frequent expectation is "assign customer X
> their own invoice template." Bizuno core does **not** have a per-customer
> default-form override — forms default by **document type/group**, not by contact.
> (There is a user-level *bookmark* mechanism for reports, which is a different
> thing.) If you genuinely need per-customer templates, that's a customization, not
> a setting — most shops instead make a multi-language or multi-brand form and
> select it at print time.

---

## Multi-language and multi-brand variants

Because forms are just definitions in a group, the practical pattern for "different
look for different situations" is **multiple forms in the same group** — e.g. an
English and a French invoice, or two brands' letterheads — and you choose the right
one when you print. Each is a copy with its own text fields and logo.

---

## Export / import

Forms move between installs as JSON:

- **Export** a form to download its definition as a `.json` file. (Export resets its
  access to "all users/roles" so it's portable.)
- **Import** a `.json` file (or one of the shipped definitions in
  `locale/<lang>/reports/`) to create the form here, optionally renaming it or
  replacing an existing one of the same name.

This is how you develop a form on one site and roll it to others.

---

## Troubleshooting after 7.3.9

The 7.3.9 move to tFPDF (from TCPDF) is mostly invisible, but if a *custom* form
looks off after upgrading, check these:

- **HTML in a cell isn't rendering.** Only a small tag set is supported by the
  in-tree shim: `<b>/<strong>`, `<i>/<em>`, `<u>`, `<font color="…">`, and
  `<p>`/`<div>`/`<br>` as line breaks. Richer HTML (tables, styles, lists) won't
  render — simplify the markup. Unsupported tags are stripped but their text is
  kept.
- **A barcode is blank or wrong.** Barcodes now come from
  `picqer/php-barcode-generator`. Make sure the field's symbology is one of the
  supported types (C128/A/B/C, C39, EAN-13/8, UPC-A/E, Interleaved 2-of-5, POSTNET)
  and that the data is valid for that symbology (e.g. EAN-13 needs the right digit
  count).
- **A logo/image shows a red placeholder.** The image file isn't found under the
  data `images/` folder — re-upload it or fix the `Img` field's file path.
- **Fonts look different.** tFPDF uses its own font set; a font referenced by an old
  TCPDF-era form may fall back. Re-pick a standard font on the affected fields.

For deeper PDF issues, see
[PDF rendering issues](../../09-troubleshooting/04-pdf-rendering-issues.md).

---

## Related

- [Form designer](./02-form-designer.md) — building the layout
- [Report engine overview](./01-report-engine-overview.md) — the 7.3.9 migration in context
- [PDF rendering issues](../../09-troubleshooting/04-pdf-rendering-issues.md)
