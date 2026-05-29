# Bizuno Agent Action Catalog

A machine-readable catalog of every action a software agent can perform in
Bizuno, grounded in the actual controller code — not generic ERP knowledge.
The audience is **an automating AI agent** (and the humans wiring one up),
not an end user reading a manual. For prose walkthroughs aimed at people, see
the human manual under [`../`](../).

Each action documents its **entry route**, **inputs**, **preconditions**,
**effects** (including any general-ledger or inventory side effects), **success
and error signals**, and a **source pointer** back to the code it was derived
from. An agent should be able to plan and execute a business process from these
files without reading the PHP.

> **Status:** draft — all modules catalogued. The [`contacts`](./contacts.md)
> module was the pilot that validated the *format*; the remaining modules were
> generated against it. Files here are `status: draft` so they are **not**
> published to the human docs site by the BetterDocs sync until reviewed.

## How Bizuno actions are addressed

Every authenticated action is a route of the form:

```
<module>/<page>/<method>
```

dispatched by `compose()` in `portal/controller.php`, which loads
`controllers/<module>/<page>.php`, instantiates the class, and calls
`<method>()`. Agents invoke a route by POSTing (or GETing) to the portal's
AJAX endpoint with a `bizRt` parameter:

```
POST {BIZUNO_URL_AJAX}&bizRt=contacts/main/save&type=c
     (form-encoded body carries the input fields)
```

- `BIZUNO_URL_AJAX` resolves to `…/index.php?ajax=1` on a standalone install
  and `…/admin-ajax.php?action=bizuno_ajax` inside WordPress. The route and
  fields are identical across both.
- Most read/render methods accept `GET`; all create/update/delete methods
  expect `POST`. The `http_method` field of each action states which.
- Inputs are never read from `$_GET`/`$_POST` directly — every field passes
  through `clean($name, $format, $method)`. The catalog lists the exact
  `clean()` call so an agent knows the field name, its expected format, and
  whether it comes from the query string or the body.

## Authentication & permission levels

Each action calls `validateAccess($secID, $level)`. The level is an integer:

| Level | Grants | Typical methods |
|-------|--------|-----------------|
| `1` | view / read | `manager`, `managerRows`, `details`, exports |
| `2` | add / create | `save` on a new record, `apiImport` |
| `3` | edit | `save` on an existing record |
| `4` | delete | `delete`, destructive tools |

`$secID` is the module's security key (e.g. the contacts manager). An agent
acts under the session of an authenticated Bizuno user; if that user's role
lacks the required level the method returns early with a permission message
and **no change is made**. Some cross-cutting actions check `validateAccess('admin', …)`
instead of the module key — those are called out per action.

## Per-action schema

Every action is one `## ` heading followed by a single fenced `yaml` block.
An agent can extract all `yaml` blocks in a file and parse them directly. The
schema:

```yaml
id:            # stable dotted identifier, e.g. contacts.customer.create
title:         # short human label
route:         # module/page/method
http_method:   # GET | POST
ui_path:       # where a human finds it in the UI (orientation only)
auth:
  sec_id:      # security key checked
  min_level:   # 1..4 from the table above
preconditions: # list of things that must already be true/exist
inputs:
  required:    # fields that must be supplied
    - name:          # field name as posted
      format:        # the clean() format (text|integer|email|date|char|...)
      source:        # get | post
      schema_field:  # underlying db column, if any
      notes:         # constraints / enum values / meaning
  optional: []       # same shape
  fixed: []          # values the method forces regardless of input
effects:
  db_writes:   # tables written and the operation (insert/update/delete)
  gl_journal:  # GL posting created, or "none"
  inventory:   # inventory movement created, or "none"
  side_effects: # auto-numbering, file handling, cache reloads, etc.
returns:
  success_signal:  # how the agent knows it worked
  identifier:      # what id/handle comes back, if any
errors:        # named error conditions the method can emit
idempotency:   # is a retry safe? what's the natural key for upsert?
related:       # other action ids commonly chained with this one
confidence:    # high | medium | low — how certain the extraction is
source:        # file:line the action was derived from
```

### Field conventions

- **`gl_journal` / `inventory`** are the fields an acting agent must respect
  most: they state whether running the action moves money or stock. `none`
  means the action is bookkeeping-neutral (safe to run without accounting
  consequences). Anything else names the journal/movement and links to the
  relevant concept doc.
- **`confidence`** is honest about extraction certainty. `high` = the behavior
  is unambiguous in the code. `medium` = config- or data-dependent branches
  exist. `low` = inferred, verify before relying on it for automated posting.
- **`idempotency`** tells an agent whether a retry after a timeout is safe and,
  for create actions, what natural key (e.g. `short_name`) to use for an
  upsert instead of a blind insert.

## Shared concepts referenced by the catalog

- **Contact types** — a single contact row carries independent boolean role
  flags (`ctype_c` customer, `ctype_v` vendor, `ctype_b` branch, `ctype_i`
  CRM contact, `ctype_e` employee, `ctype_j` project, `ctype_u` user). One
  contact can hold several roles at once. The single-char `type` (`c/v/b/i/e/j`)
  used by routes and the import feed selects which role context you're acting
  in. See [Contacts as a universal entity](../02-core-concepts/04-contacts-as-universal-entity.md).
- **Journal taxonomy** — transactional modules reference journal IDs (jID).
  See [Journal ID taxonomy](../02-core-concepts/02-journal-id-taxonomy.md).
- **`getNextReference()`** — Bizuno's auto-numbering helper; produces the next
  `short_name` / document reference from a named counter.

## Index

| Module | Status | File |
|--------|--------|------|
| Bizuno (core/portal) | draft | [bizuno.md](./bizuno.md) |
| Contacts | draft (pilot) | [contacts.md](./contacts.md) |
| PhreeBooks (GL/journals) | draft | [phreebooks.md](./phreebooks.md) |
| Inventory | draft | [inventory.md](./inventory.md) |
| Payment | draft | [payment.md](./payment.md) |
| Shipping | draft | [shipping.md](./shipping.md) |
| PhreeForm (reports) | draft | [phreeform.md](./phreeform.md) |
| Quality | draft | [quality.md](./quality.md) |
| Office | draft | [office.md](./office.md) |
| Administration | draft | [administrate.md](./administrate.md) |
| API / integration | draft | [api.md](./api.md) |

## Generation method (for maintainers)

These files are derived by reading `src/controllers/<module>/*.php` and the
schema definitions in `src/controllers/bizuno/install/tables.php`. The `source`
pointer on each action records where it came from so the catalog can be
re-verified against the code after changes. When a controller method changes
signature or side effects, update the corresponding action and bump its
`source` line.
