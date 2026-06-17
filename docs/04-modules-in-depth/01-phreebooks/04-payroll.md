---
title: Payroll
category: PhreeBooks
order: 4
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# Payroll

Read this first, because it saves you a wrong turn:

> **Bizuno does not calculate payroll.** There is no tax engine, no withholding
> tables, no pay-period or deduction setup, no check printing, no W-2/1099
> generation. Bizuno's "payroll" is an **import** that takes the finished numbers
> from a real payroll service (Paychex, PayrollCentric, Patriot) and posts them to
> your General Ledger.

That's the honest scope. If you need software that runs payroll — computes gross
to net, withholds and remits taxes, files quarterly — Bizuno isn't it, and you
should keep using your payroll provider. What Bizuno gives you is a clean way to
get the *accounting* of each payroll run into your books without hand-keying a
journal entry every pay period.

---

## How it actually works

The flow is: **your payroll service runs payroll → you download a CSV → Bizuno
imports it as a General Journal entry.**

```
  Paychex / PayrollCentric / Patriot
            │  (run payroll, withhold & remit taxes, pay employees)
            ▼
        Download CSV
            │
            ▼
  Bizuno: General Journal (jID=2) toolbar → [provider] import
            │  parse CSV → build GL legs
            ▼
        Posted journal entry (Dr wages/taxes, Cr withholdings/net)
```

The provider does the work that matters legally — calculating taxes, paying
employees, filing returns. Bizuno records the result.

### Supported providers

Three import connectors ship in core (under
`controllers/phreebooks/payroll/`):

| Provider        | Import source                  |
|-----------------|--------------------------------|
| Paychex         | CSV export from Paychex        |
| PayrollCentric  | CSV export from PayrollCentric |
| Patriot Payroll | CSV export from Patriot        |

Each is a thin parser: read the provider's CSV, map columns to GL accounts, total
them, and post.

### Running an import

1. Open the **General Journal** (jID 2). Enabled payroll providers appear as
   buttons on its toolbar.
2. Click your provider. Upload the CSV you downloaded from them.
3. Bizuno parses it into debit/credit GL legs and posts a General Journal entry
   (requires journal-2 edit rights, `j2_mgr` level 2), described e.g. "Patriot
   Payroll for &lt;date&gt;".

### What posts to the GL

A typical run debits the wage and employer-tax **expense** accounts and credits
the **liability** accounts (taxes withheld, garnishments, benefits) and the
**cash/net-pay** account. For example, the Patriot connector maps to accounts like:

```
Dr  Salaries & Wages (expense)
Dr  Payroll Tax Expense (employer share)
  Cr  Federal Taxes Withheld (liability)
  Cr  State Unemployment / FUTA (liability)
  Cr  Employee deductions — medical, garnishments (liability)
  Cr  Net Payroll Paid from Checking (cash)
```

The entry balances like any journal entry. **No employee-level detail is kept** —
the import aggregates by GL account, so Bizuno stores the company's payroll
*accounting*, not a per-employee earnings record.

> The connectors carry **default account mappings** that won't match your chart
> out of the box (the Patriot connector, for instance, hard-codes a specific set
> of account numbers). Review and adjust the mapping to your
> [chart of accounts](./01-chart-of-accounts.md) before relying on it.

---

## Employees

Employees are [contacts](../04-contacts/04-employees.md) flagged with the employee
role (`ctype_e = 1`). The contact record holds basic identity — name, government
ID (SSN), address, hire/termination dates — but **no payroll setup** (no pay rate,
no withholding profile, no deduction templates). Those live in your payroll
provider, not Bizuno.

Because the payroll import aggregates by GL account, you do **not** need an
employee contact per person for the books to balance — employee records are for
your own reference and for non-payroll uses (expense reimbursements, contact
management).

---

## Year-end (W-2 / 1099)

Bizuno generates **no** year-end payroll forms. W-2s, 1099-NEC, quarterly 941s,
and state filings all come from your payroll provider, who has the per-employee
earnings and withholding detail Bizuno never stored.

---

## When to outgrow this

You've already outgrown "built-in payroll" the moment you need it to *calculate*
anything — Bizuno was never going to. The right architecture is:

- **Payroll provider** (Gusto, ADP, Paychex, Patriot, …) runs payroll and handles
  compliance.
- **Bizuno** records each run's accounting via the import (or a manual General
  Journal entry if your provider isn't one of the three connectors — the GL legs
  are simple enough to enter by hand).

If your provider isn't supported, you're not blocked: post the same debits and
credits as a normal [General Journal](./02-journals.md#jid-2--general-journal-j02php)
entry each pay period.

---

## Related

- [Journals (the journal_id reference)](./02-journals.md) — payroll posts to jID 2
- [Employees](../04-contacts/04-employees.md)
- [Users vs. employees vs. contacts](../../05-administration/02-users-employees-contacts.md)
