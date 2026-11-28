---
title: Monthly Customer Statements
category: Administration
order: 7
status: published
audience: [admin, bookkeeper]
last-updated: 2026-11-28
---

# Monthly Customer Statements

Bizuno can email a statement to every customer who asks for one, once a month,
without anyone opening the statement form. A server cron job starts the run;
each flagged customer gets their statement as a PDF attachment, and a summary
of the run is emailed to the company's Manager address.

---

## 1. Choose who gets a statement

Open the customer (**Customers → Manager → edit**) and, in the **Properties**
panel, set **Monthly Statement**:

| Setting                       | What the run does                                                                 |
|-------------------------------|-----------------------------------------------------------------------------------|
| None                          | Never emails this customer (the default).                                         |
| Email, skip if no activity    | Sends only when the statement has transactions in the date range.                 |
| Email, always                 | Always sends; with no activity in the range it sends the full account history.   |

The statement goes to the customer's second email address (normally the A/R
contact) and falls back to the main email address. Customers with neither are
listed as failed in the run summary. Inactive customers are skipped.

## 2. Set up the run

**Settings → PhreeBooks → Customers** has the statement settings:

| Setting                          | Use                                                                                         |
|----------------------------------|---------------------------------------------------------------------------------------------|
| Monthly Statement Form           | The PhreeForm form to send. Only forms in the customer statements folder are listed.       |
| Monthly Statement Dates          | **Last Month** (for a run on the 1st), **This Period**, or **All**.                          |
| Monthly Statement CC             | Optional address copied on every statement, e.g. the A/R clerk.                             |
| Monthly Statement Test Address   | While set, every statement goes here instead of to the customer. See *Test first*.        |
| Run Monthly Statements Now       | Starts a run immediately with the saved settings (administrator level 3).                  |

Also check **Settings → Bizuno → Company**:

- **Email: Accts/Rec** (or the main company email) is the From address.
- **Email: Store Mgr** receives the run summary: how many statements were sent,
  skipped or failed, with a line per customer and the reason for each skip or
  failure.

The email subject and body come from the statement form's own email settings
(PhreeForm → edit the form → email message), the same text the manual Email
button uses.

## 3. Test first

1. Enter your own address in **Monthly Statement Test Address** and save.
2. Click **Run Monthly Statements Now** (or call the cron URL below).
3. Check your inbox: one email per flagged customer, the subject starting with
   `[TEST for customer@address]`, with the PDF attached. The CC is not used and
   nothing is written to the customers' logs. The summary email is marked TEST.
4. When the statements look right, clear the Test Address and save. The next
   run sends to the customers.

## 4. Schedule the cron job

The run is started by a token-protected URL, so the server can call it
without anyone signed in:

```
https://biz.example.com/?bizRt=portal/api/stmtCron&token=YOUR_API_TOKEN
```

The token is the **API token** in **Settings → API → PhreeSoft API**. Add a
cron entry on the server to fetch the URL once a month, for example at 6:15 am
on the 1st:

```
15 6 1 * * curl -s "https://biz.example.com/?bizRt=portal/api/stmtCron&token=YOUR_API_TOKEN" > /dev/null
```

The token can also be sent in an `X-Bizuno-Token` header instead of the URL.

## Troubleshooting

| Summary says                                   | Fix                                                                               |
|-----------------------------------------------|-----------------------------------------------------------------------------------|
| The monthly statement form is not set          | Pick a form in Monthly Statement Form and save.                                   |
| No company email address to send from          | Fill in Email: Accts/Rec or the main email in Company settings.                  |
| no email address on the customer record        | Add an email to the customer.                                                    |
| no activity in the date range (skipped)        | Expected for "skip if no activity" customers.                                    |
| mail send failed: ...                          | Check the outgoing mail settings; the reason from the mail server is included.   |
| No summary email arrives                       | Set Email: Store Mgr in Company settings; the run is still in the audit log.     |

Every run is also written to the audit log as "PhreeForm - Monthly
statements ...", whether it was started by the cron or the Run Now button.
