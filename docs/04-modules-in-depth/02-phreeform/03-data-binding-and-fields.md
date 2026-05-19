---
title: Data Binding and Fields
category: PhreeForm
order: 3
status: stub
audience: [admin, developer]
last-updated: 2026-05-15
---

# Data Binding and Fields

> **Status:** Stub — not yet drafted.

## What this page will cover

- The `fieldlist` model — every field on a report/form is a row
- Field types and their settings hash
- Token replacement: `%date%`, `%reportname%`, `%company%` (extend via `TextReplace`'s `$xKeys`/`$xVals`)
- Mapping a SQL column → a field on the layout
- Multi-row data (table block) vs. single-row (header/footer block)
- "Data" vs. "Text" fields — when each is right

## Why it matters

Form designer feels approachable; the data-binding under it is where the
real model lives. Doc has to bridge the visual UI and the JSON structure
underneath so power users can debug what's happening.

## Related

- [Form designer](./02-form-designer.md)
- [Processors and formatters](./04-processors-and-formatters.md)
