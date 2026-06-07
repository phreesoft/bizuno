---
title: Zebra Browser Print
category: Shipping
order: 3
status: published
audience: [admin]
last-updated: 2026-06-07
---

# Zebra Browser Print

This is the bridge that lets a **web app print to a local Zebra label printer** — and
it's one of the most support-heavy setup paths in Bizuno. Get it right once per
workstation and thermal labels print in a click; get it wrong and you'll chase "no
printer found" all day. This page walks the setup and the failure modes.

---

## What it is

Zebra **Browser Print** is a small desktop application (from Zebra) that runs on the
shipping workstation and exposes the local printer to the browser over a localhost
service. Bizuno's web page talks to that service, which relays the label data to the
USB/network Zebra printer.

```
  Bizuno web page ──HTTP──► Zebra Browser Print service ──► Zebra printer
   (labelThermal)          (localhost 127.0.0.1:9100)        (USB / network)
```

- The service listens on **`http://127.0.0.1:9100`** (and **`https://127.0.0.1:9101`**
  for Safari / secure-context cases).
- The browser loads Zebra's **BrowserPrint SDK** JavaScript, which Bizuno injects on
  the shipping page.

> **The SDK is not bundled.** Bizuno expects the Zebra `BrowserPrint-3.x.x.min.js`
> file to be present (downloaded from Zebra's support site) — it is not redistributed
> in the repo. If thermal printing says "Browser Print is not loaded," the SDK file is
> the first thing to check.

---

## Per-workstation setup

Browser Print is installed **on each PC that prints labels**, not on the server:

1. **Install Zebra Browser Print** for the workstation OS (Windows / macOS / Linux)
   from Zebra.
2. **Connect and power on** the Zebra printer (USB or network) and confirm the OS
   sees it.
3. **Register the printer** in the Browser Print app so the service reports it.
4. If the browser uses the **HTTPS** endpoint (`:9101`), you may have to **trust the
   local certificate** once — visit `https://127.0.0.1:9101/available` and accept the
   certificate so the browser will talk to the service. (The default
   `http://127.0.0.1:9100` path avoids this, but mixed-content/secure-context rules in
   some browsers push you to HTTPS.)

---

## How printing works

When you click **Print Thermal** on a bought label:

1. Bizuno reads the thermal label file(s) — raw **ZPL/EPL** returned by the carrier
   and stored as `.lpt` — and hands them to the page's `labelThermal()` routine.
2. The SDK's device discovery (`getLocalDevices()`) returns the registered devices;
   Bizuno uses the **first printer** reported.
3. Each label is sent to that printer via the device's `send()` method. Multiple
   labels from one shipment are sent in sequence on a single click.

There's no printer-picker UI — it prints to the first Zebra device the service
reports. If you have several, the first registered one wins.

---

## When it fails — and how to tell

The thermal routine surfaces specific alerts; match the message to the fix:

| Symptom / message                          | Cause & fix                                              |
|--------------------------------------------|---------------------------------------------------------|
| "Browser Print is not loaded"              | The Zebra SDK JS isn't present/loaded — reload; confirm the SDK file is installed |
| "No printer found" (with a device list)    | Printer not registered/powered, or reported under a different category — open Browser Print, register the device, check power/USB |
| "Could not reach Zebra Browser Print"      | The service isn't running or localhost is blocked — start the app; load `https://127.0.0.1:9101/available` to confirm; check firewall/AV on localhost |
| "Print failed on \<printer\>"              | The job reached the printer but errored — check media/ribbon, printer status |

**Quick bridge test:** browse to **`https://127.0.0.1:9101/available`** (or the
`:9100` HTTP equivalent). If it returns device JSON, the service and printer are
reachable and the problem is elsewhere; if it doesn't load, the service/cert/firewall
is the issue.

---

## When to use it vs. plain PDF

- **Use Browser Print (thermal)** when you print volume on a dedicated 4×6 Zebra — it
  prints a ZPL label directly, no PDF dialog, no scaling surprises.
- **Use the PDF path** for occasional shipping or when you print labels on a regular
  laser/inkjet — the carrier returns a `.pdf` you print like any document, with no
  desktop app to install. See [Label printing → two print paths](./02-label-printing.md#two-print-paths).

If a workstation only ever prints to a laser printer, you don't need Browser Print at
all — stick with PDF labels.

---

## Related

- [Label printing](./02-label-printing.md)
- [Carriers](./01-carriers.md)
