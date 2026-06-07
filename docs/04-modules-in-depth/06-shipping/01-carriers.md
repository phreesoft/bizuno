---
title: Carriers
category: Shipping
order: 1
status: draft
audience: [admin]
last-updated: 2026-06-07
---

# Carriers

The Shipping module rates, buys, and prints labels through real carrier APIs, plus a
set of rule-based "carriers" for when you don't need a live integration. The setup
screens are credential-heavy and look intimidating; this page de-mystifies what each
carrier needs and how the pieces fit.

---

## What's actually integrated

Bizuno ships more than the headline three. Two tiers:

**Live API carriers** (real rating, label purchase, tracking):

| Carrier      | Notes                                                       |
|--------------|------------------------------------------------------------|
| UPS          | REST API; account must be certified before live labels     |
| USPS         | OAuth API (developer.usps.com) with an EPS payment account  |
| FedEx        | REST API; optional separate Freight (LTL) account          |
| Endicia      | USPS postage via Stamps.com/Endicia; buy/refund postage     |
| Canada Post  | SOAP API; domestic + international rating                   |
| XPO, ODFL    | LTL freight carriers                                        |

**Rule-based methods** (no API — you set the math):

`Flat Rate`, `Free Shipper`, `Percent` (of order), `Item` (per-item formula),
`Best Way` (manual rate entry), and `Third Party` (bill a third-party account).
Use these for simple or negotiated shipping without wiring up a carrier API.

---

## Credentials

Carrier credentials are entered per carrier in its settings and stored **per
business** (globally), in the carrier-methods configuration — **not** per store. The
key fields by carrier:

| Carrier      | Required credentials                                                |
|--------------|--------------------------------------------------------------------|
| UPS          | account number, REST API key, REST secret, test/prod toggle        |
| USPS         | client ID + client secret (OAuth), EPS payment account, CRID, MID, price type |
| FedEx        | account number, REST API key, REST secret, (optional) Freight account |
| Endicia      | client key                                                         |
| Canada Post  | customer number, test user/pass, production user/pass              |

> **Test mode first.** Each live carrier has a test/sandbox toggle. Configure and
> validate in test mode, confirm you can rate and produce a label, then flip to
> production. UPS in particular requires **certification** with the carrier before
> production labels work.

---

## Per-store usage

Although credentials are global, an individual shipment chooses **which store's
addresses** to use — the ship-from (pickup) store and the bill-to store — so a
multi-store business ships from the right origin while sharing one set of carrier
API keys.

---

## Address validation

UPS, USPS, FedEx, and Endicia offer **address validation**; it runs when you check
**Verify Address** on the label form, and can correct the address before you buy the
label. (Canada Post's validation is not currently working — treat it as
rating/labeling only.)

---

## Rate shopping

You can **compare rates across carriers** for one shipment: select the carriers to
quote, and Bizuno calls each one's rate API and shows the results in a table you pick
from. There's also a programmatic rate path that quotes all default-enabled carriers
at once. This is the fastest way to land on the cheapest viable service per package.

---

## Pickup scheduling

Pickup handling is **partial**: the label form carries a pickup-type field (daily
pickup, on-call, drop-box, counter, etc.) that's sent **with the shipment**, but
Bizuno does not make a separate "schedule a pickup" API call or manage a pickup
calendar. Treat it as "tell the carrier how this package is tendered," not a pickup
scheduler.

---

## Invoice reconciliation

The Shipping module can **reconcile a carrier invoice** against what you booked —
currently implemented for **FedEx**: upload the FedEx invoice summary (CSV) and
Bizuno matches it to your shipping log, flags over-charges and mismatches, and writes
a reconciliation report. Other carriers expose **bulk tracking** but not invoice
reconciliation. See [Label printing → reconciliation](./02-label-printing.md#reconciliation-and-end-of-day).

---

## Related

- [Label printing](./02-label-printing.md)
- [Zebra Browser Print](./03-zebra-browser-print.md)
