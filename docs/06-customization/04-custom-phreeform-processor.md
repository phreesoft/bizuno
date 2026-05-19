---
title: Custom PhreeForm Processor
category: Customization
order: 4
status: stub
audience: [developer]
last-updated: 2026-05-15
---

# Custom PhreeForm Processor

> **Status:** Stub — not yet drafted.

## What this page will cover

- Declaring a new processor: add an entry to your module's `phreeformProcessing` array on `*Admin`
- The processor function signature — receives raw value, returns transformed value
- Common patterns: code-to-label lookups, computed columns, conditional formatting hooks
- Declaring a new formatter (same pattern, different array)
- How the registry picks them up (`initPhreeForm` walks every module's `*Admin` and merges)
- Testing: where in the form designer your new processor appears

## Why it matters

Most "I need this one column to look different" customizations are 10 lines
of processor, not a custom report. Doc has to surface this so users reach
for it before they reach for SQL.

## Related

- [Processors and formatters](../04-modules-in-depth/02-phreeform/04-processors-and-formatters.md)
- [The myExt/ pattern](./01-the-myext-pattern.md)
