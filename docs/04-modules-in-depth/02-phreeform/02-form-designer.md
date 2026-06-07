---
title: Form Designer
category: PhreeForm
order: 2
status: draft
audience: [admin]
last-updated: 2026-06-07
---

# Form Designer

The form designer is where you lay out pixel-positioned documents — invoices,
packing slips, statements, checks, labels — by placing fields on a page at exact
coordinates. It's a real differentiator: instead of Photoshopping a logo onto a
fixed template, you actually design the document.

This page covers the field palette, the page model, positioning, and saving/sharing
forms. The data side (binding fields to columns, tokens) is in
[Data binding and fields](./03-data-binding-and-fields.md).

---

## The field palette

Each thing you place on a form is a **field** of a given type:

| Type code | Field            | What it does                                              |
|-----------|------------------|----------------------------------------------------------|
| `Data`    | Data             | A single value bound to a SQL column (header/footer data) |
| `Text`    | Text             | Literal text (and [tokens](./03-data-binding-and-fields.md#tokens)) |
| `Tbl`     | Table            | A repeating block — one row per query result (the line items) |
| `TDup`    | Table (dup)      | A second copy of a table at different coordinates          |
| `TBlk`    | Text Block       | A multi-field single-row block                            |
| `Ttl`     | Total            | An aggregate/total value                                  |
| `Img`     | Image            | A static image from the images folder                    |
| `ImgLink` | Image Link       | A data-bound image (path comes from the query)           |
| `CImg`    | Company Logo     | Your business logo                                        |
| `CDta` / `CBlk` | Company Data / Block | Your business name/address fields                 |
| `Line`    | Line             | A horizontal/vertical/diagonal rule                      |
| `Rect`    | Rectangle        | A box with optional border and/or fill                    |
| `BarCode` | Barcode          | A 1-D barcode (C128, C39, EAN, UPC, I2of5, POSTNET)      |
| `PgNum`   | Page Number      | The page number, with multi-form batch aliases            |
| `LtrTpl`  | Letter Template  | A letter-body template                                    |

---

## The page model

A form definition carries the **page setup** — page size (e.g. Letter), orientation
(`P` portrait / `L` landscape), and the four margins (in mm) — plus default fonts
and colors for headings, data, totals, and filters.

There aren't rigid "header / body / footer" bands you draw into. Instead:

- **Single-render fields** (`Data`, `Text`, `Img`, `Line`, `Rect`, company fields,
  `PgNum`, `Ttl`) are drawn once per page at their coordinates. A field's
  **`display`** setting controls *which* pages: `0` = every page, `1` = first page
  only, `2` = last page only. That's how you get a remittance stub on the last page,
  or a logo only on page one.
- **Repeating fields** (`Tbl`) draw one row per query result and paginate
  automatically, adding pages when the rows overflow.

So "header" and "footer" are really "first/every-page single fields above/below the
table," expressed through coordinates and the `display` rule.

---

## Positioning fields

Every field has a position and size in millimetres:

- **`abscissa`** — X (distance from the left)
- **`ordinate`** — Y (distance from the top)
- **`width`** / **`height`** — the field's box (0 lets images auto-size)

Plus per-field appearance in its settings: `font`, `size`, `align` (L/C/R), and
`color`. Lines carry a `linetype` (H/V/C) and `length`; rectangles carry border
(`bshow`/`bsize`/`bcolor`) and fill (`fshow`/`fcolor`) options.

---

## Logos and images

There are three image fields, for three situations:

- **`Img`** — a fixed image you uploaded (your letterhead graphic). It references a
  file under the Bizuno data **`images/`** folder. If the file is missing, the form
  renders a visible red placeholder rather than failing silently.
- **`ImgLink`** — a *data-bound* image whose path comes from the query (e.g. a
  product photo per line). Accepts JPG/PNG.
- **`CImg`** — your company logo specifically.

> **Hi-DPI tip.** PDF scales the image into the box you define (in mm), so upload a
> logo at 2–3× the printed size for crisp output — a 30 mm-wide logo box looks best
> fed by an image a few hundred pixels wide, not a 30-pixel thumbnail.

---

## Page numbering across batched forms

`PgNum` prints the page number. For **batch printing** — many invoices in one PDF —
PhreeForm supports page-group aliases (`{nb1}`, `{nb2}`, …) so each document can show
"Page X of Y" counting *its own* pages, not the whole batch. The aliases are
resolved when the PDF is finalized.

---

## Saving, versioning, and sharing

- **Save** writes the whole definition (all fields and their settings) back to
  `common_meta` as JSON, stamping `last_update`. There's no built-in version
  history — save is a replace — so keep a copy before a big change.
- **Copy** a form to iterate safely: duplicate, rename, edit the copy, and leave the
  original untouched (see [Custom forms](./05-custom-forms.md#start-from-a-built-in)).
- **Export / import** moves a form between installs as a JSON file — see
  [Custom forms → export/import](./05-custom-forms.md#export--import).

---

## Related

- [Data binding and fields](./03-data-binding-and-fields.md) — binding fields to data and tokens
- [Processors and formatters](./04-processors-and-formatters.md) — shaping values
- [Custom forms](./05-custom-forms.md) — copy, groups, import/export, troubleshooting
- [Report engine overview](./01-report-engine-overview.md)
