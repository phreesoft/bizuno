---
title: Trap and Trace Files
category: Troubleshooting
order: 1
status: draft
audience: [admin, developer]
last-updated: 2026-06-16
---

# Trap and Trace Files

Trace files are how you actually diagnose a production problem in Bizuno. When
something goes wrong deep in a post or an import, the trace is the breadcrumb
trail — the SQL that ran, the path the code took, the point it gave up. Anyone
supporting an install should be able to read one.

---

## How tracing works: the debug stack and the "trap"

Throughout the code, `msgDebug($msg)` appends to an in-memory **debug trace** for
the current request. Most of the time that trace is simply discarded when the
request ends — it costs nothing and goes nowhere.

A **trap** is the signal that says "this request went wrong enough to keep the
trace." Two calls set it:

```php
msgDebug($message, 'trap');   // append AND flag the trace to be written
msgAdd($message, 'trap');     // raise a message AND flag the trace
```

Once the trap flag is set, at the end of the request Bizuno **writes the whole
accumulated trace to disk**. No trap, no file — so a trace file existing at all
means something asked to be noticed.

---

## Where the trace goes

The trace is written under the data directory:

```
BIZUNO_DATA/trace.txt
```

There's no automatic rotation or cleanup — the file is managed by the most recent
trap, and housekeeping is on you (see [below](#cleanup-and-noise)).

---

## Reading a trace file

A trace opens with a header identifying the request, then the step-by-step trail.
The shape:

```
Trace information for debug purposes. Bizuno release <ver>, ip: <addr>, domain <host> - generated <timestamp>
Trace Start Time: <ms since start>
GET Vars  = <scrubbed $_GET>
POST Vars = <scrubbed $_POST>
...
Time: <ms> SQLs <count> <elapsed>ms => <message>
Time: <ms> SQLs <count> <elapsed>ms => <message>
...
Page trace stats: Execution Time: <ms>, <SQL count> queries taking <ms>
```

How to read it:

- **The header** tells you the exact release, host, and the request inputs — so
  you can reproduce.
- **Each line** carries a running elapsed time and a cumulative SQL count. A line
  where the SQL count jumps sharply, or the elapsed time leaps, points at the
  expensive (or runaway) step.
- **The last message before the trail stops** is usually the failure — the trap
  fired right where the code bailed.

> **Sensitive data is redacted automatically.** Before the GET/POST vars are
> written, `bizScrubSensitive()` masks anything whose key looks like a secret —
> `pass`, `pwd`, `secret`, `token`, `api_key`, `txn_key`, `card`, `pan`, `cvv`,
> `cvc`, `otp`, `2fa`, and similar. *(since v7.4.0.)* So a trace is safe to send
> to support without leaking a password or card number — but still skim it before
> sharing, since the redaction is key-name based, not magic.

---

## Common trap triggers

A trace file most often comes from one of these:

| Trigger                          | What it means                                            |
|----------------------------------|----------------------------------------------------------|
| **Chart not loaded**             | The chart of accounts couldn't be read — usually a config/cache problem |
| **GL account type not set / missing default** | A posting needed a default GL account that isn't configured |
| **Caught fatal PHP error**       | The error handler trapped a fatal so you get a trace instead of a white screen |
| **Method / meta not found**      | A module method or its metadata is missing — often a stale [cache](./02-cache-out-of-sync.md) |
| **EDI parse errors**             | An incoming EDI document had an unexpected/invalid segment — see [EDI X12](../07-integration/02-edi-x12.md) |
| **Email / SFTP / cURL failure**  | An outbound mail or network/file transfer threw                          |
| **GL balance / total mismatch**  | A transaction's debits and credits (or totals) didn't reconcile          |

A trap is not automatically *fatal* — it's "worth recording." Many traps
accompany a caught error the user also saw; some are informational records of an
edge the code handled.

> **Informational ≠ trap.** Not every oddity should trap. Example: incoming EDI
> 850 **N9/MSG** segments (reference text) are now handled as an informational
> `$0` line on the order — an explicit no-op that deliberately **doesn't** trap.
> *(since v7.4.0.)* If you're staring at traces full of benign segment noise from
> an older build, that's the kind of thing later versions quieted.

---

## Cleanup and noise

- **There's no trap on/off switch.** Traps fire only where the code explicitly
  asks; you can't globally silence them, and you generally wouldn't want to — a
  trap means something asked to be seen.
- **There's no automatic cleanup.** Bizuno won't prune `trace.txt` for you.
  Delete or archive it as part of routine maintenance, and grab a copy *before*
  reproducing an issue so a fresh trap doesn't overwrite the one you wanted.
- **Treat a trace as a snapshot of one request.** Reproduce the problem, then read
  the trace that request produced — don't try to read history out of it.

---

## Related

- [Cache out of sync](./02-cache-out-of-sync.md) — a frequent cause of "method/meta not found" traps
- [EDI X12](../07-integration/02-edi-x12.md) — where EDI-segment traps come from
- [Cache mechanics](../05-administration/06-cache-mechanics.md) — the registry the traces reference
