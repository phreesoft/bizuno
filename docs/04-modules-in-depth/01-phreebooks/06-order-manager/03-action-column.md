---
title: The Action Column — Row Actions by Journal
category: PhreeBooks
order: 63
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
last-verified-build: "7.x"
---

# The Action Column — Row Actions by Journal

The first column of the manager grid is the **action column**: the cluster of
icons on each row that let you act on that record without opening it. Like the
Save menu, its contents are **journal-dependent** — and additionally
**row-state-dependent** (open vs. closed, waiting vs. confirmed) and
**security-dependent**. This page enumerates every action and exactly when each
one shows.

> **Two layers of gating.** Each action has a `display` rule (which journals /
> row states it belongs to) *and* often a security minimum (which permission level
> the user needs). An icon is only drawn when **both** pass.

---

## The actions

### Print

- **Shows on:** every journal.
- Opens the journal's default PhreeForm document for that row in the print viewer.

### Edit

- **Shows on:** every journal.
- Opens the record in the [entry form](./01-order-entry-fields.md).

### Fill into Purchase (`fill_purchase`)

- **Shows on:** open RFQ (`jID=3`) and open PO (`jID=4`) rows (`closed=0`).
- **Security:** level 2+ on the purchase journal.
- Pulls the quote/order forward into the next document (RFQ→PO, PO→bill),
  prefilled.

### Fill into Sale (`fill_sale`)

- **Shows on:** open Sales Quote (`jID=9`) and open SO (`jID=10`) rows.
- **Security:** level 2+ on the sales journal.
- The customer-side mirror of Fill into Purchase (Quote→SO, SO→invoice).

### Toggle status (`toggle` / `toggle_j12`)

- **Shows on:** PO/SO (`jID` 4, 10) and Sales (`jID=12`).
- **Security:** level 3+ for orders (4/10); level 4+ for the sales toggle (12).
- Flips the **waiting** flag — confirming an order, or marking a sale
  shipped/unshipped — without opening it.

### Delivery dates (`dates`)

- **Shows on:** PO/SO (`jID` 4, 10).
- **Security:** level 2+.
- Opens the per-line delivery-date editor for managing expected ship/receipt
  dates on a multi-line order.

### Auto-assembly (`xAutoAssy`)

- **Shows on:** Sales Quote/SO/Sale (`jID` 9, 10, 12).
- Builds any assembly items on the order on demand (confirm prompt).

### Set invoice number (`invoice`)

- **Shows on:** **waiting** Purchase rows (`jID=6`, `waiting=1`).
- **Security:** level 3+.
- Prompts for the vendor's invoice number on a bill that was received before the
  paper invoice arrived.

### Set reference number (`reference`)

- **Shows on:** Cash Receipt (`jID=18`) and Pay Bills (`jID=20`).
- Prompts to set/correct the payment reference (check #, deposit ref).

### Create credit (`vcred`)

- **Shows on:** Purchase (`jID=6`) and Sales (`jID=12`).
- **Security:** level 2+ on the credit journal.
- Spins off a Vendor Credit (`jID=7`) / Credit Memo (`jID=13`) from the bill or
  invoice.

### Payment (`payment`)

- **Shows on:** open bills/invoices/credits — `jID` 6, 7, 12, 13 (`closed=0`).
- **Removed entirely** if the user lacks level 2 on the corresponding payment
  journal (Pay Bills `j20_mgr` for the vendor side; Cash Receipt `j18_mgr` for the
  customer side).
- Launches the payment journal prefilled to settle this row.

### Create PO / Drop-ship (`xDropShip`)

- **Shows on:** open customer rows — Sales Quote/SO/Sale (`jID` 9, 10, 12,
  `closed=0`).
- **Requires:** level 2+ on the Purchase Order journal (`j4_mgr`).
- Generates a drop-ship Purchase Order to the vendor straight from the customer
  order (confirm prompt).

### Create return / RMA (`return`)

- **Shows on:** shipped Sales rows (`jID=12`, `waiting=0`).
- **Requires:** level 2+ on the Sales journal (`j12_mgr`).
- Opens the returns/RMA intake form for that invoice.

### EDI actions (`ediConfirm`, `ediInvoice`, `ediTrack`)

- **Shows on:** customer trading rows (`jID` 9, 10, 12, 13) **for EDI-enabled
  trading partners only**.
- Transmit the X12 documents to the partner: **855** PO confirmation (from an SO),
  **810** invoice, and **856** advance ship notice / tracking (from a sale).

> The EDI actions are gated on the row's trading-partner being flagged for EDI.
> In the current build that partner check is hard-coded and slated to move to a
> per-contact setting — treat "EDI partner" as the condition, not a specific
> contact id. See [EDI X12 integration](../../../07-integration/02-edi-x12.md).

### Attachments (`attach`)

- **Shows on:** any row that has files attached (`attach=1`).
- Opens the attachment viewer for that record.

### Delete (`trash`)

- **Shows on:** every journal.
- **Security:** level 4+ (delete). Confirms before removing.

---

## The journal × action matrix

Customer- and vendor-side trading journals. ✓ = available, **w** = only when the
row is *waiting*, **o** = only when the row is *open* (not closed), — = never.
Security minimums are noted under the table.

| Action            | RFQ 3 | PO 4 | Purch 6 | VCred 7 | Quote 9 | SO 10 | Sales 12 | CrMemo 13 |
|-------------------|:-----:|:----:|:-------:|:-------:|:-------:|:-----:|:--------:|:---------:|
| Print             |   ✓   |  ✓   |   ✓     |   ✓     |   ✓     |  ✓    |   ✓      |    ✓      |
| Edit              |   ✓   |  ✓   |   ✓     |   ✓     |   ✓     |  ✓    |   ✓      |    ✓      |
| Fill → Purchase   |   o   |  o   |   —     |   —     |   —     |  —    |   —      |    —      |
| Fill → Sale       |   —   |  —   |   —     |   —     |   o     |  o    |   —      |    —      |
| Toggle status     |   —   |  ✓   |   —     |   —     |   —     |  ✓    |   ✓      |    —      |
| Delivery dates    |   —   |  ✓   |   —     |   —     |   —     |  ✓    |   —      |    —      |
| Auto-assembly     |   —   |  —   |   —     |   —     |   ✓     |  ✓    |   ✓      |    —      |
| Set invoice #     |   —   |  —   |   w     |   —     |   —     |  —    |   —      |    —      |
| Create credit     |   —   |  —   |   ✓     |   —     |   —     |  —    |   ✓      |    —      |
| Payment           |   —   |  —   |   o     |   o     |   —     |  —    |   o      |    o      |
| Create PO (drop-ship) | — |  —   |   —     |   —     |   o     |  o    |   o      |    —      |
| Create return/RMA |   —   |  —   |   —     |   —     |   —     |  —    | ✓ (shipped) |  —      |
| EDI (855/810/856) |   —   |  —   |   —     |   —     |   ✓     |  ✓    |   ✓      |    ✓      |
| Attachments       |   ✓*  |  ✓*  |   ✓*    |   ✓*    |   ✓*    |  ✓*   |   ✓*     |    ✓*     |
| Delete            |   ✓   |  ✓   |   ✓     |   ✓     |   ✓     |  ✓    |   ✓      |    ✓      |

\* Attachments shows only on rows that actually have a file attached.

**Security minimums:** Toggle (orders) 3, Toggle (sale 12) 4, Delivery dates 2,
Set invoice # 3, Fill 2, Create credit 2, Create PO 2 (on `j4_mgr`), Create RMA 2
(on `j12_mgr`), Delete 4. Payment is hidden unless the user has level 2 on the
matching payment journal.

### Payment journals

The payment and refund journals (Cash Receipt `jID=18`, Pay Bills `jID=20`,
Vendor Refund `jID=17`, Customer Refund `jID=22`) carry a slimmer action column —
Print, Edit, **Set reference number**, and Delete — and their status column reads
**Reconciled** rather than Paid/Closed.

---

## Status column, by journal

The action column's neighbor — the **Status** column — is also relabeled per
journal, which is worth knowing since several actions key off it:

| Journals                         | `closed=1` reads | `waiting=1` highlight        |
|----------------------------------|------------------|------------------------------|
| Quotes (3, 9)                    | Closed           | —                            |
| Orders (4, 10)                   | Closed           | Confirmed (yellow-green)     |
| Purchase / Sales (6, 12)         | Paid             | Waiting (6) / Unshipped (12) |
| Credits (7, 13)                  | Paid             | —                            |
| Payment & refund (17, 18, 20, 22)| Reconciled       | —                            |
| General Journal (2)              | Closed           | —                            |

The grid also color-codes the **reference** column by journal so a mixed list
(e.g. all vendor documents together) is scannable: green = invoice/bill,
orange = order, blue = quote, pink = credit.

---

## Related

- [The Save menu](./02-save-menu.md) — the same conversions, from inside an open
  record.
- [Order entry fields](./01-order-entry-fields.md)
- [Journals (the journal_id reference)](../02-journals.md)
