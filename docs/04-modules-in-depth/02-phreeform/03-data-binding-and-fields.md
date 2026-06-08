---
title: Data Binding and Fields
category: PhreeForm
order: 3
status: published
audience: [admin, developer]
last-updated: 2026-06-07
---

# Data Binding and Fields

The [form designer](./02-form-designer.md) feels visual, but underneath every
report and form is a **data model**: a list of fields, each bound to a source and
each carrying a settings hash. Understanding that model is how you debug "why is
this value blank / wrong / unformatted." This page bridges the visual UI and the
JSON underneath.

---

## The `fieldlist` model

A definition's `fieldlist` is an array of field rows. Each row is an object with its
position, size, and a **`settings`** hash holding everything else:

| Property            | Meaning                                                  |
|---------------------|----------------------------------------------------------|
| `type`              | The field type (`Data`, `Text`, `Tbl`, … — see [palette](./02-form-designer.md#the-field-palette)) |
| `abscissa` / `ordinate` | X / Y position in mm                                  |
| `width` / `height`  | The field box in mm                                      |
| `settings.fieldname`| The bound SQL column (for data fields)                   |
| `settings.text`     | The literal text (for text fields)                       |
| `settings.processing` | The [processor](./04-processors-and-formatters.md) to apply first |
| `settings.formatting` | The [formatter](./04-processors-and-formatters.md) to apply second |
| `settings.font` / `size` / `align` / `color` | Appearance                     |
| `settings.display`  | `0` every page · `1` first page · `2` last page          |

For reports (tabular), the same field rows double as **columns**, carrying
`visible`, `width`, `align`, `break`, and `total` (sum this column).

---

## Where the data comes from

A definition also lists its **source tables** (`tables`) with their join options, and
the SQL builder turns the field list into the `SELECT`. So binding a value onto the
page is two steps:

1. Make sure the **table** is in the definition's table list (joined correctly).
2. Set a field's **`fieldname`** to that table's column (e.g.
   `journal_main.invoice_num`).

If a value is blank on the output, the usual cause is the field's `fieldname`
pointing at a column that isn't in the joined tables — check the table list first.

---

## Data fields vs. text fields

The distinction trips people up, so be deliberate:

- A **Data** field (`type: Data`) is **bound to a column** (`settings.fieldname`).
  Its value comes from the current result row. Use it for anything that varies per
  record — the invoice number, the customer name, a total.
- A **Text** field (`type: Text`) is **literal** (`settings.text`). It prints the
  same words every time — labels like "Invoice", "Remit to:", boilerplate terms.
  Text fields also run through [token replacement](#tokens).

Both can carry processing and formatting, but only Data pulls from the query.

---

## Single-row vs. multi-row (the table block)

Reports and forms mix two kinds of data:

- **Single-row** fields render once — the header info (invoice #, date, bill-to),
  the totals, the footer. These are `Data`/`Text`/`Ttl`/etc. placed at fixed
  coordinates.
- **Multi-row** data is the **`Tbl`** (table) field: it **repeats once per result
  row** — the line items. Inside the table field is a `boxfield` of column
  definitions; the engine draws a row per record and paginates when it overflows.

A form is typically: single-row header fields up top, one `Tbl` for the lines in the
middle, single-row totals/footer below (the footer on the last page via
`display: 2`).

---

## Tokens

**Text** fields support token substitution for common values:

| Token          | Resolves to              |
|----------------|--------------------------|
| `%date%`       | The current date         |
| `%reportname%` | The report/form title    |
| `%company%`    | Your company name        |

(See also the page-number aliases `{nb1}`, `{nb2}` for
[batched forms](./02-form-designer.md#page-numbering-across-batched-forms).)

### Extending tokens

Token replacement runs through a single helper (`TextReplace($text, $xKeys,
$xVals)`). Code paths that render text can pass **extra key/value pairs** via
`$xKeys`/`$xVals`, so a custom processor or a special report class can introduce its
own tokens beyond the three built-ins. That's the seam to add document-specific
placeholders without changing the engine.

---

## Related

- [Form designer](./02-form-designer.md) — placing these fields
- [Processors and formatters](./04-processors-and-formatters.md) — the two transformation passes
- [Custom forms](./05-custom-forms.md)
