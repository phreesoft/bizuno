---
title: Label Printing
category: Shipping
order: 2
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# Label Printing

If you ship 50 times a day, the label path is where seconds add up. This page covers
the lifecycle from rate to printed label, the two print paths (plain-paper PDF vs.
direct-to-Zebra thermal), and the realities of voiding, reconciliation, and
international.

---

## The lifecycle

```
   rate  ──►  buy label  ──►  label saved + logged  ──►  print  ──►  reconcile
 (compare    (carrier API     (file on disk +         (PDF or      (match the
  carriers)   returns label)   shipping_log row)       thermal)     carrier invoice)
```

1. **Rate** the package (optionally [across carriers](./01-carriers.md#rate-shopping)).
2. **Buy** the label — Bizuno calls the carrier's label API, which returns the label
   (and tracking number).
3. Bizuno **saves the label file** under
   `data/shipping/labels/<carrier>/<year>/<month>/<day>/<tracking>.<ext>` and writes a
   **shipping-log** entry (tracking, service, booked vs. net cost, ship/delivery
   dates).
4. **Print** it (below).
5. Later, **reconcile** the carrier's invoice against what you booked.

### Buying postage

How you fund labels depends on the carrier:

- **Endicia** — buy postage into the account from inside Bizuno (and refund).
- **USPS** — funded through your EPS payment account at usps.com (not inside Bizuno).
- **UPS / FedEx** — no prepaid balance; each label charges your carrier account.

---

## Two print paths

A bought label exists as a file, and its **extension** decides how you print it:

| File   | Path                  | How it prints                                            |
|--------|-----------------------|---------------------------------------------------------|
| `.pdf` | plain-paper           | "Download/Print PDF" → print on any printer             |
| `.lpt` | thermal (ZPL/EPL)     | "Print Thermal" → sent straight to a Zebra label printer via [Browser Print](./03-zebra-browser-print.md) |
| `.gif` | image                 | shown in a popup to print                               |

The label view shows whichever buttons match the files that exist — if both a PDF and
a thermal file are present, you choose. Which format the carrier returns is driven by
the carrier's **printer-type / label-format** setting (`label_pdf` / `label_thermal`).

### Label sizes

The standard thermal label is **4×6**. Carriers expose their own size/stock codes
(e.g. FedEx and Canada Post offer doc-tab and letter half-page variants); set the
format in the carrier's settings. USPS labels are produced at 4×6.

---

## Reprint and void

- **Reprint** — the label file is on disk, so you can re-open the shipment and print
  it again without re-buying.
- **Void / refund** — supported for **UPS, USPS, FedEx, and Endicia** via the
  carrier's label-delete API. USPS, for example, reports back whether the label was
  *canceled* (never charged) or *disputed* (refund queued). Voiding removes the local
  label file and log entry. Mind each carrier's same-day/void-window rules.

---

## Reconciliation and end-of-day

- **FedEx invoice reconciliation** — upload the FedEx invoice summary CSV; Bizuno
  matches it to your shipping log and flags discrepancies, writing a report you can
  review. (See [Carriers → invoice reconciliation](./01-carriers.md#invoice-reconciliation).)
- **Bulk tracking** — USPS and UPS can refresh tracking status for many shipments at
  once.

> **What it doesn't do.** There's no carrier **end-of-day manifest / SCAN-form
> generation** built in — the workflow is buy-and-print per shipment, with the label
> files retained and the FedEx CSV reconciliation after the fact. If your operation
> depends on a daily close manifest, plan around that gap.

---

## International

International **rating** works (Canada Post and USPS carry full international service
codes; UPS/FedEx international services rate too). However, **customs-declaration form
generation is not implemented** — the customs payload is stubbed in code, not wired to
the form. For international shipments you'll rate and label, but produce customs
paperwork through the carrier's own portal. Don't promise CN22/CP72/commercial-invoice
generation from Bizuno today.

---

## Related

- [Carriers](./01-carriers.md)
- [Zebra Browser Print](./03-zebra-browser-print.md) — the thermal path
- [PDF rendering issues](../../09-troubleshooting/04-pdf-rendering-issues.md)
