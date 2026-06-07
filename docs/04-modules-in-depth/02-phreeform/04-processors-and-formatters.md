---
title: Processors and Formatters
category: PhreeForm
order: 4
status: published
audience: [admin, developer]
last-updated: 2026-06-07
---

# Processors and Formatters

Every value PhreeForm prints can pass through **two transformation passes** before
it lands on the page:

1. **Processing** (`viewProcess`) — *first*. Turns a raw value into a more useful
   one: look up a related record, compute a balance, extract a piece, convert a
   number to words.
2. **Formatting** (`viewFormat`) — *second*. Presents the value: as currency, a
   date, a percentage, upper/lower case.

Order matters: process, then format. A contact ID might be **processed** into the
contact's name, then a number might be **processed** into a balance and then
**formatted** as currency. This is the seam where you customize output without
writing SQL gymnastics — and without forking Bizuno.

---

## Where they're applied

At render time, for each field, the engine runs `viewProcess(value,
field.processing)` and then `viewFormat(result, field.formatting)`. You choose the
processor and formatter per field in the [designer](./02-form-designer.md) from
dropdowns; they're stored as `settings.processing` and `settings.formatting` on the
field.

Either pass is optional — leave them blank and the raw value prints as-is.

---

## Formatters (the presentation pass)

Common built-in formatters:

| Formatter    | Output                                   |
|--------------|------------------------------------------|
| `currency`   | Localized currency                       |
| `number` / `precise` / `rnd0d` / `rnd2d` | Numbers at various precision |
| `percent`    | Percentage                               |
| `date` / `dateLong` / `datetime` | Localized dates              |
| `n2wrd`      | Number spelled out in words (for checks) |
| `uc` / `lc`  | Upper / lower case                       |
| `yesBno`     | Boolean → "Yes"/blank                    |
| `null0`      | Blank a zero                             |
| `storeID`    | Store id → store name                    |
| `contactID` / `contactName` | Contact id → contact info |
| `glType` / `glTypeLbl` / `glTitle` | GL account type / label / title |
| `j_desc`     | Journal id → its name (e.g. 12 → "Sales") |

(There are more — the designer dropdown lists every formatter registered on your
install.)

---

## Processors (the transformation pass)

Processors are richer and **module-specific** — each module contributes the ones
relevant to its data. A sampling:

| Processor      | From module | Does                                            |
|----------------|-------------|-------------------------------------------------|
| `inv_image` / `image_sku` | inventory | The item's image                       |
| `inv_shrt` / `sku_name`   | inventory | Item short description / name           |
| `contactID` / `contactName` / `cIDEmail` | contacts | Pull a contact field |
| `AgeCur` / `Age30` / `Age60` / `Age90` / `Age120` | phreebooks | Aging buckets |
| `paymentRcv` / `paymentDue` / `pmtNet` | phreebooks | Payment calculations  |
| `invBalance` / `invCOGS` / `subTotal` | phreebooks | Invoice math           |
| `shipTrack` / `shipReq`   | shipping  | Shipping/tracking values                |
| `n2wrd`        | bizuno      | Amount in words (checks)                         |

The exact set depends on which modules are installed.

---

## How the registry works

Processors and formatters aren't hard-coded into PhreeForm — each module **declares
its own** and PhreeForm assembles them:

- A module's `admin.php` exposes `$phreeformProcessing` and `$phreeformFormatting`
  arrays. Each entry names the processor/formatter, a display label, a group, the
  owning module, and the **handler function** to call.
- At startup, the registry (`initPhreeForm()`) merges every module's arrays into the
  PhreeForm module cache.
- `viewProcess()` / `viewFormat()` look a name up in that cache (formatters also have
  a core hard-coded set) and dispatch to its handler.

This is why installing a module can add new processors to the dropdown, and why
your own module can too.

> **Adding your own.** A custom processor or formatter is just another entry in a
> module's `$phreeformProcessing` / `$phreeformFormatting` array pointing at a
> function you write — no core changes. See
> [Custom PhreeForm processor](../../06-customization/04-custom-phreeform-processor.md)
> for the worked example.

---

## Related

- [Data binding and fields](./03-data-binding-and-fields.md) — where processing/formatting attach
- [Custom PhreeForm processor](../../06-customization/04-custom-phreeform-processor.md) — write your own
- [Form designer](./02-form-designer.md)
