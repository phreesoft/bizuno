---
title: Processors and Formatters
category: PhreeForm
order: 4
status: stub
audience: [admin, developer]
last-updated: 2026-05-15
---

# Processors and Formatters

> **Status:** Stub — not yet drafted.

## What this page will cover

- The two transformation passes a field value goes through: `processing` then `formatting`
- Built-in processors: `inv_image`, `image_sku`, `fa_type`, `glTypeLbl`, `n2wrd` (num to words), `lc` (lowercase), `null0`, `j_desc`, …
- Built-in formatters: `date`, `currency`, `number`, `percent`, `storeID`, `contactID`
- The full registry — each module declares its own processors/formatters via `*Admin::phreeformProcessing` and `phreeformFormatting`
- How to chain or add custom ones (see [Custom PhreeForm processor](../../06-customization/04-custom-phreeform-processor.md))

## Why it matters

This is the seam where reports get customized without forking. Knowing the
named processors saves users from writing SQL gymnastics to get a value
into the right shape.

## Related

- [Data binding and fields](./03-data-binding-and-fields.md)
- [Custom PhreeForm processor](../../06-customization/04-custom-phreeform-processor.md)
