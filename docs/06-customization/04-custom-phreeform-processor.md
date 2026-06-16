---
title: Custom PhreeForm Processor
category: Customization
order: 4
status: draft
audience: [developer]
last-updated: 2026-06-16
---

# Custom PhreeForm Processor

Most "I need this one column to look different" requests are about ten lines of
code, not a custom report. PhreeForm lets you register a **processor** (transform
a field's raw value) or a **formatter** (format the output), and it shows up in
the report/form designer's dropdowns for anyone to use. Reach for this before you
reach for SQL.

This is the developer how-to; the user-facing description of what processors and
formatters *are* lives in
[Processors and formatters](../04-modules-in-depth/02-phreeform/04-processors-and-formatters.md).

---

## How registration works

Each module's `…Admin` class exposes up to three arrays, and the registry merges
them across all modules during a cache rebuild
(`initPhreeForm()` in `model/registry.php`):

| Array on `…Admin`        | Becomes                                 | Purpose                         |
|--------------------------|-----------------------------------------|---------------------------------|
| `$phreeformProcessing`   | `bizunoMod['phreeform']['processing']`  | Value transforms (lookups, computed) |
| `$phreeformFormatting`   | `bizunoMod['phreeform']['formatting']`  | Output formatting               |
| `$phreeformSeparators`   | `bizunoMod['phreeform']['separators']`  | Trailing separators (space, comma) |

So "adding a processor" = adding an entry to `$phreeformProcessing` on a module's
admin class and providing the function it points at. After that, a
[cache rebuild](../05-administration/06-cache-mechanics.md) makes it appear in the
designer.

---

## Declaring a processor

In your module's `…Admin` constructor:

```php
public $phreeformProcessing;

function __construct()
{
    // …
    $this->phreeformProcessing = [
        'myStatus' => ['text'=>lang('order_status'), 'module'=>$this->moduleID, 'function'=>'myModuleProcess'],
    ];
    setProcessingDefaults($this->phreeformProcessing, $this->moduleID, lang('title', $this->moduleID));
}
```

Each entry is keyed by the processor's id and carries:

- **`text`** — the label the user sees in the designer dropdown.
- **`module`** — which module's `functions.php` holds the handler.
- **`function`** — the handler function name.
- **`group`** — optional; defaults to the module title.

`setProcessingDefaults()` fills in sensible defaults, so a bare
`'myStatus' => ['text'=>'…']` becomes `module = <your module>`,
`function = <module>Process`, `group = <module title>`. Declare the keys
explicitly when you want a specific handler.

---

## The handler signature

A processor (and a formatter) is a plain function that receives the **value** and
the **key**, and dispatches on the key — one function typically handles several
keys via a `switch`:

```php
namespace bizuno;

function myModuleProcess($value, $process)
{
    switch ($process) {
        case 'myStatus':
            // code-to-label lookup
            $map = ['o'=>lang('open'), 'c'=>lang('closed'), 'h'=>lang('hold')];
            return $map[$value] ?? $value;
    }
    return $value;   // always return something
}
```

Put it in your module's `functions.php` (the file the registry auto-loads when
the processor fires). The function runs once per cell as the report/form renders;
return the transformed value. For row-aware logic, the current row is available
via `$GLOBALS['currentRow']` — that's how the core `buySell` inventory formatter
reaches sibling columns, for example.

Common shapes:

- **Code → label** — turn a stored `'o'` into "Open" (above).
- **Computed column** — derive a value from the row (`$GLOBALS['currentRow']`).
- **Conditional output** — return different text/markup based on the value.

---

## Formatters and separators — same pattern

A **formatter** is declared the same way, in `$phreeformFormatting`, with a
handler of the same `($value, $format)` shape:

```php
$this->phreeformFormatting = [
    'buySell' => ['text'=>lang('buy_sell_title', $this->moduleID),
                  'module'=>$this->moduleID, 'function'=>'myModuleView'],
];
```

The difference is intent and ordering: a field is **processed first, then
formatted**. Note that the formatter dispatcher also has ~80 built-in formats
(`currency`, `date`, …) before it falls through to the registered ones, so pick a
key that doesn't collide with a built-in. **Separators** (`$phreeformSeparators`)
follow the identical structure for trailing punctuation.

---

## Keeping it in `myExt/`

A processor is client-specific code, so it belongs in
[`myExt/`](./01-the-myext-pattern.md), not the core repo. Two routes:

- **Shadow the module's admin + functions** under
  `myExt/controllers/<module>/` so your `$phreeformProcessing` entry and its
  handler load with that module.
- **A custom class** at `myExt/model/<Class>.php` — PhreeForm's renderer has a
  fallback that loads a "special class" from `BIZUNO_DATA/myExt/model/` when it
  can't find one in the core extensions — useful for heavier custom logic.

Either way, the registry only picks up the declaration on a
[cache rebuild](../05-administration/06-cache-mechanics.md), so clear the business
cache after adding it.

---

## Testing: where it shows up

Open the **report/form designer** and edit a field — your processor appears in the
**Processing** dropdown and your formatter in the **Formatting** dropdown, under
its group. (The dropdowns are populated straight from the merged cache.) If it's
*not* there, you almost certainly skipped the cache clear, or the handler function
isn't where the entry's `module`/`function` says it is.

---

## Related

- [Processors and formatters](../04-modules-in-depth/02-phreeform/04-processors-and-formatters.md) — what they do, from the user's side
- [The myExt/ pattern](./01-the-myext-pattern.md) — where a client-specific processor belongs
- [Cache mechanics](../05-administration/06-cache-mechanics.md) — why a new processor needs a cache rebuild to appear
