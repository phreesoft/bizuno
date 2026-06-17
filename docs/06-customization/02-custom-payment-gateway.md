---
title: Custom Payment Gateway
category: Customization
order: 2
status: published
audience: [developer]
last-updated: 2026-06-16
---

# Custom Payment Gateway

Adding a payment processor is one of the most common reasons a consultant picks
up a Bizuno deployment. The goal of this page is a working skeleton in half an
hour. The gateway surface is small and consistent — once you've seen one gateway
class, you can write another.

---

## Where gateways live

Each gateway is a class in its own folder under
`controllers/payment/gateways/<code>/<code>.php`. The ones that ship today:

| Gateway        | Folder          | Notes                                  |
|----------------|-----------------|----------------------------------------|
| Authorize.Net  | `authorizenet`  | Full SDK integration, stored cards     |
| PayFabric      | `payfabric`     | EVO Payments, wallet support           |
| Converge       | `converge`      | Elavon                                 |
| PayPal         | `paypal`        | Hosted redirect (record-only)          |
| Cash on Delivery | `cod`         | Record-only, no external API           |
| Money Order    | `moneyorder`    | Record-only                            |
| Direct Debit   | `directdebit`   | Record-only (EFT)                      |

The record-only ones (`cod`, `moneyorder`, `directdebit`) are the best templates
to copy from — they show the full contract with none of the SDK noise.

---

## The class contract

There is **no base class to extend** — a gateway is a standalone class in the
`bizuno` namespace that implements a fixed set of methods. (This corrects an
earlier draft of this page: there is no `sale()`/`void()`/`refund()` method on
the gateway. Those are *controller* actions; the gateway implements a single
`payment($action, …)` dispatcher.)

**Properties:**

```php
public $moduleID  = 'payment';
public $methodDir = 'gateways';
public $code      = 'processorx';   // your gateway's folder/code
public $defaults;
public $settings;
public $lang      = ['title'=>'ProcessorX', 'description'=>'…'];
```

**Methods:**

| Method                              | Purpose                                                                 |
|-------------------------------------|-------------------------------------------------------------------------|
| `__construct()`                     | Load `$defaults`, merge saved `$settings` via `getMetaMethod()`          |
| `settingsStructure()`               | Return the admin config fields (API key, mode, GL account, sort order)  |
| `render($data, $values=[], $dispFirst=false)` | Emit the HTML/JS for the payment entry form                   |
| `payment($action, $data=[])`        | **The transaction dispatcher** — handles `capture`, `authorize`, `capAuth`, `refund`, `void` |
| `wallet($action, $data=[])`         | Stored-card / customer-profile actions (optional — stub it if unused)   |
| `report($action, $data=[])`         | Batch / transaction reporting (optional — stub it if unused)            |

### The normalized return shape

Every `payment()` / `wallet()` / `report()` call returns the **same array shape**,
so the caller never has to know which gateway answered:

```php
['ok'=>true|false, 'txID'=>'', 'code'=>'', 'msg'=>'', 'data'=>[], 'raw'=>null]
```

`ok` is the success flag, `txID` the processor's transaction id, `code`/`msg`
the result, `data` any structured payload, and `raw` the untouched SDK response
(or `null`). Return this shape for every action — including "not implemented":

```php
return ['ok'=>false, 'txID'=>'', 'code'=>'not_implemented',
        'msg'=>"not implemented: payment/$action", 'data'=>[], 'raw'=>null];
```

---

## A minimal skeleton — "ProcessorX"

Drop this at `controllers/payment/gateways/processorx/processorx.php`:

```php
<?php
namespace bizuno;

class processorx
{
    public $moduleID  = 'payment';
    public $methodDir = 'gateways';
    public $code      = 'processorx';
    public $defaults;
    public $settings;
    public $lang      = ['title'=>'ProcessorX', 'description'=>'ProcessorX gateway'];

    public function __construct()
    {
        $pmtDef         = getModuleCache($this->moduleID, 'settings', 'general', false, []);
        $this->defaults = ['cash_gl_acct'=>$pmtDef['gl_payment_c'], 'mode'=>'test', 'order'=>40];
        $userMeta       = getMetaMethod($this->methodDir, $this->code);
        $this->settings = array_replace($this->defaults, $userMeta['settings'] ?? []);
    }

    public function settingsStructure()
    {
        $modes = [['id'=>'test','text'=>'Test (Sandbox)'], ['id'=>'prod','text'=>'Production']];
        return [
            'cash_gl_acct'=> ['label'=>lang('gl_payment_c_lbl', $this->moduleID),
                              'attr'=>['type'=>'ledger', 'value'=>$this->settings['cash_gl_acct']]],
            'api_key'     => ['label'=>'API Key',
                              'attr'=>['type'=>'text', 'size'=>'40', 'value'=>$this->settings['api_key'] ?? '']],
            'mode'        => ['label'=>'Mode', 'values'=>$modes,
                              'attr'=>['type'=>'select', 'value'=>$this->settings['mode']]],
            'order'       => ['label'=>lang('order'),
                              'attr'=>['type'=>'integer', 'size'=>'3', 'value'=>$this->settings['order']]],
        ];
    }

    public function render($data, $values=[], $dispFirst=false)
    {
        // Emit the card-entry fields, or return '' for a record-only method.
        return '';
    }

    public function payment($action, $data=[])
    {
        switch ($action) {
            case 'authorize': // fall through if you auth+capture together
            case 'capture':
                $live = ($this->settings['mode'] === 'prod');
                // …call the ProcessorX SDK/API here using $this->settings['api_key']…
                return ['ok'=>true, 'txID'=>'PX-123', 'code'=>'', 'msg'=>'approved', 'data'=>[], 'raw'=>null];
            case 'refund':
            case 'void':
                // …call the SDK…
                return ['ok'=>true, 'txID'=>'', 'code'=>'', 'msg'=>'done', 'data'=>[], 'raw'=>null];
        }
        return ['ok'=>false, 'txID'=>'', 'code'=>'not_implemented',
                'msg'=>"not implemented: payment/$action", 'data'=>[], 'raw'=>null];
    }

    public function wallet($action, $data=[]) {
        return ['ok'=>false, 'txID'=>'', 'code'=>'not_implemented',
                'msg'=>"not implemented: wallet/$action", 'data'=>[], 'raw'=>null];
    }
    public function report($action, $data=[]) {
        return ['ok'=>false, 'txID'=>'', 'code'=>'not_implemented',
                'msg'=>"not implemented: report/$action", 'data'=>[], 'raw'=>null];
    }
}
```

That's a complete, loadable gateway. Flesh out `payment()` with real SDK calls
and you're done.

---

## Registration and the payment-method dropdown

You don't wire the gateway up by hand. On install/upgrade the payment module
**scans the `gateways/` directory**, instantiates each class, and records its
metadata (under the `methods_gateways` key). The payment-method dropdown is then
built from `getMetaMethod('gateways')`, showing every gateway whose status is
enabled, ordered by the `order` setting.

So the steps to make ProcessorX appear are: add the folder/class, then let the
module re-scan — in practice,
[clear the business cache](../05-administration/06-cache-mechanics.md) — and
enable + configure it in the payment settings (which renders your
`settingsStructure()` fields).

---

## Sandbox vs. production

The convention is a `mode` setting with values `test` (sandbox) and `prod`. Read
it in `payment()` to pick the SDK environment:

```php
$live = ($this->settings['mode'] === 'prod');
```

Develop and verify against the processor's sandbox before flipping a live site to
`prod`.

---

## Credentials and PCI — read this

Be precise about what Bizuno does and doesn't do here:

- **Gateway credentials are stored as method settings metadata** (the
  `settingsStructure()` fields, persisted via the module settings). They are
  **not** encrypted at rest by a built-in cipher — there is no `$mixer`
  encryption layer applied to gateway settings. Protect them at the
  hosting/database layer, and use a `password`-type field so the value isn't
  echoed back into the form.
- **Do not store raw card numbers.** Use the processor's **tokenization / wallet**
  (the `wallet()` actions) so a card becomes a token you can recharge — the PAN
  never lands in your database. The bundled Authorize.Net and PayFabric gateways
  do exactly this.
- **Stay out of PCI scope where you can.** Prefer processor-hosted fields or
  redirect flows for card capture. The less cardholder data touches your server,
  the smaller your compliance burden.

---

## Related

- [The myExt/ pattern](./01-the-myext-pattern.md) — the customization surface and the override-vs-upstream call
- [Custom journal type](./03-custom-journal-type.md) · [Custom PhreeForm processor](./04-custom-phreeform-processor.md)
