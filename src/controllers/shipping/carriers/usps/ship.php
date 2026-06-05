<?php
/*
 * Shipping extension for USPS - Label generation and Tracking
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please contact PhreeSoft for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-06-05
 * @filesource /controllers/shipping/carriers/usps/ship.php
 *
 * Endpoints:
 *   POST /labels/v3/label                — generate a label (requires X-Payment-Authorization-Token)
 *   POST /tracking/v3r2/tracking         — bulk track up to 35 numbers
 */

namespace bizuno;

class uspsShip extends uspsCommon
{
    private $usps_results = [];

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Bizuno's labelGet contract: returns an array of "log records" for the
     * shipping log table — one element per package shipped.
     */
    public function labelGet($request=[])
    {
        $this->getLabel($request);
        return $this->usps_results;
    }

    /**
     * Drives the label flow: get payment auth token, build payload, POST
     * /labels/v3/label asking for vendor JSON (so we get a single response
     * with base64-encoded labelImage), persist label to disk, optionally
     * check EPS balance.
     */
    private function getLabel($request=[])
    {
        global $io;
        msgDebug("\nUSPS getLabel with request = ".print_r($request, true));
        $paymentToken = $this->getPaymentAuthToken();
        if (empty($paymentToken)) { return; } // already messaged

        $payload = $this->getPayload($request);
        if (empty($payload)) { return; }
        msgDebug("\nUSPS label payload: ".print_r($payload, true));

        // Ask for the JSON-only response shape. The default multipart shape
        // is awful to parse from PHP without pulling in an extra dep — and
        // labelImage is already base64-encoded inside the JSON variant, which
        // is exactly what we need to write to disk anyway.
        $opts = ['headers' => [
            'X-Payment-Authorization-Token' => $paymentToken,
            'Accept'                        => 'application/vnd.usps.labels+json']];
        $resp = $this->queryREST('post', '/labels/v3/label', $payload, $opts);
        if (empty($resp) || empty($resp['trackingNumber'])) {
            // queryREST already wrote the structured error, but make sure the
            // operator sees a label-specific failure message too.
            msgAdd('USPS: label generation failed. See debug log for details.');
            return;
        }

        // Build the shipping log row. Field names must match what
        // controllers/shipping/main.php::shipManagerWrite() expects so the
        // log table populates correctly.
        // Total cost = postage + extra-service fees + per-package fees. USPS
        // splits surcharges across two arrays (extraServices and fees, e.g. a
        // fuel surcharge lands in fees); both must be summed or the booked cost
        // understates and won't reconcile against the rate quote.
        $addOns = 0;
        foreach (['extraServices', 'fees'] as $bucket) {
            if (!empty($resp[$bucket]) && is_array($resp[$bucket])) {
                foreach ($resp[$bucket] as $svc) { $addOns += (float)($svc['price'] ?? 0); }
            }
        }
        $totalCost = (float)($resp['postage'] ?? 0) + $addOns;
        // Delivery commitment: expectedDeliveryDate is the current field;
        // scheduleDeliveryDate (note: no "d") is the deprecated fallback.
        $commit = $resp['commitment'] ?? [];
        $deliveryDate = $commit['expectedDeliveryDate'] ?? ($commit['scheduleDeliveryDate'] ?? '');
        $this->usps_results[] = [
            'ref_id'       => $request['ship_ref_1'] ?? '',
            'method'       => $request['ship_method'] ?? '',
            'pkg_type'     => $request['ship_pkg']    ?? '',
            'ship_date'    => strtotime($request['ship_date'] ?? 'now'),
            'tracking'     => $resp['trackingNumber'],
            'book_cost'    => (float)($resp['postage'] ?? 0),
            'net_cost'     => $totalCost,
            'total_cost'   => $totalCost + (float)$this->settings['handling_fee'],
            'delivery_date'=> $deliveryDate,
            'notes'        => $resp['SKU'] ?? ''];

        // Persist the label binary. The vendor-JSON response carries the
        // bytes base64-encoded in the `labelImage` field. Write under a
        // dated path identical to Endicia's so the rest of the shipping
        // module's lookup/print code finds it.
        if (empty($resp['labelImage'])) { return msgAdd('USPS: no labelImage in response.', 'trap'); }
        $imageType = strtoupper($payload['imageInfo']['imageType'] ?? 'PDF');
        $ext = $this->extensionForImageType($imageType);
        $date= explode('-', biz_date('Y-m-d', strtotime($request['ship_date'] ?? 'now')));
        $fileName = $resp['trackingNumber'] . '.' . $ext;
        $filePath = "data/shipping/labels/$this->code/{$date[0]}/{$date[1]}/{$date[2]}/";
        if (!$io->fileWrite(base64_decode($resp['labelImage']), $filePath . $fileName, true)) { return; }
        msgDebug("\nUSPS: wrote label to $filePath$fileName");
        msgAdd(sprintf($this->lang['msg_label_retrieve'], $resp['trackingNumber']), 'success');

        // Best-effort low-balance reminder — non-blocking.
        $this->chkNeedToBuy();
    }

    /**
     * Void a label / request a refund: DELETE /labels/v3/label/{trackingNumber}.
     *
     * USPS decides cancel-vs-refund by whether a Shipping Services File (SSF)
     * exists yet:
     *   - No SSF (label just created) → status CANCELED, no charge ever posts.
     *   - SSF created → status DISPUTED + disputeId, refund queued to the EPS
     *     account once USPS approves it.
     * Either outcome means the operator is done with this label, so we return
     * true and let the shipping manager drop the local label file + log row.
     *
     * Requires the same X-Payment-Authorization-Token as label creation. USPS
     * allows only one refund dispute per CRID per label per day — a duplicate
     * same-day void returns a 400, which we surface so the operator knows.
     *
     * @param string $trckNum  USPS tracking number of the label to void
     * @return bool true on CANCELED or DISPUTED, false (with a message) otherwise
     */
    public function labelDelete($trckNum='', $method='GND', $store_id=0)
    {
        msgDebug("\nUSPS labelDelete with tracking number = ".print_r($trckNum, true));
        if (empty($trckNum)) { msgAdd('USPS: no tracking number supplied to void.'); return false; }
        $paymentToken = $this->getPaymentAuthToken();
        if (empty($paymentToken)) { return false; } // already messaged
        $opts = ['headers' => ['X-Payment-Authorization-Token' => $paymentToken]];
        $resp = $this->queryREST('delete', '/labels/v3/label/'.rawurlencode($trckNum), null, $opts);
        if (empty($resp)) { return false; } // queryREST surfaced the error
        // CancelResponse: {trackingNumber, status: CANCELED|DISPUTED, disputeId}
        $status = strtoupper($resp['status'] ?? '');
        if ($status === 'CANCELED') {
            msgAdd("USPS label $trckNum canceled — no postage charge will post.", 'success');
            return true;
        }
        if ($status === 'DISPUTED') {
            $dispute = !empty($resp['disputeId']) ? " (refund request #{$resp['disputeId']})" : '';
            msgAdd("USPS label $trckNum refund requested$dispute. The credit posts to your EPS account once USPS approves it.", 'success');
            return true;
        }
        msgAdd("USPS: unexpected response voiding label $trckNum.");
        msgDebug("\nUSPS labelDelete unexpected response: ".print_r($resp, true));
        return false;
    }

    /**
     * Build the LabelRequest body for /labels/v3/label.
     *
     * USPS requires every label to carry a mailingDate in the future-or-today
     * window (0–7 days ahead). The shipping form posts ship_date; clamp it
     * to today if the operator typed something earlier.
     */
    private function getPayload($pkg)
    {
        // Resolve the USPS mailClass from the Bizuno carrier code the form
        // submitted. ship_method is the dropdown value (1DP/2DP/GND/3DP/4DP).
        $bizCode = $pkg['ship_method'] ?? 'GND';
        if (empty($this->options['mailClass'][$bizCode])) {
            msgAdd("USPS: unknown service code '$bizCode'.");
            return [];
        }
        $mailClass = $this->options['mailClass'][$bizCode];

        $shipPkg = clean('ship_pkg', ['format'=>'cmd','default'=>'package'], 'post');
        // Same mail-class-aware mapping the rate query uses — Priority Mail
        // Express needs PA (not SP), or USPS fails the label SKU lookup exactly
        // like the rate call did. Sharing the helper keeps quote and label in sync.
        $rateIndicator = $this->rateIndicatorFor($shipPkg, $mailClass);

        // Clamp ship_date to today if it's in the past (USPS won't accept
        // backdated mailingDate). Cap at +7 days so an operator who types a
        // date too far in the future doesn't get a 400.
        $today = strtotime(biz_date('Y-m-d'));
        $req   = strtotime($pkg['ship_date'] ?? 'now');
        if ($req < $today)              { $req = $today; }
        if ($req > $today + 7*86400)    { $req = $today + 7*86400; }
        $mailingDate = biz_date('Y-m-d', $req);

        // Weight: form posts pounds + ounces; USPS labels API takes pounds
        // (decimal). Convert ounces into a fractional pound.
        $lb = (float) clean('weight',   ['format'=>'float','default'=>0], 'post');
        $oz = (float) clean('weightOz', ['format'=>'float','default'=>0], 'post');
        $weight = $lb + ($oz / 16.0);
        if ($weight <= 0) {
            // Fall back to the package envelope's weight if the form fields
            // weren't populated (some flows skip them when calling labelGet
            // programmatically from rate-shopping).
            $weight = (float)($pkg['settings']['weight'] ?? 0.1);
        }

        $payload = [
            'imageInfo'   => [
                // ZPL printers and PDF are the realistic options; default
                // comes from the operator's label_thermal preference.
                'imageType'      => $this->settings['label_thermal'] ?: 'PDF',
                'labelType'      => '4X6LABEL',
                'receiptOption'  => 'NONE'],
            'fromAddress' => $this->mapAddress($pkg['shipper']),
            'toAddress'   => $this->mapAddress($pkg['destination']),
            'packageDescription' => [
                'mailClass'                    => $mailClass,
                'rateIndicator'                => $rateIndicator,
                'processingCategory'           => $this->options['processingCategory'],
                'destinationEntryFacilityType' => 'NONE',
                'mailingDate'                  => $mailingDate,
                'weightUOM'                    => 'lb',
                'weight'                       => round($weight, 2),
                'dimensionsUOM'                => 'in',
                'length'                       => (float) clean('length', ['format'=>'float','default'=>0], 'post'),
                'width'                        => (float) clean('width',  ['format'=>'float','default'=>0], 'post'),
                'height'                       => (float) clean('height', ['format'=>'float','default'=>0], 'post')]];

        // customerReference is an array of up to 4 reference strings printed
        // on the label. Map Bizuno's ship_ref_1/2 + the configured label
        // messages so existing customer expectations carry over from Endicia.
        $refs = [];
        foreach ([$pkg['ship_ref_1'] ?? '', $pkg['ship_ref_2'] ?? '',
                  $this->settings['lbl_msg_1'], $this->settings['lbl_msg_2'], $this->settings['lbl_msg_3']] as $r) {
            $r = trim((string)$r);
            if ($r !== '' && count($refs) < 4) { $refs[] = ['referenceNumber' => $r]; }
        }
        if (!empty($refs)) { $payload['packageDescription']['customerReference'] = $refs; }

        return $payload;
    }

    /**
     * File extension for the requested image type. USPS supports more types
     * than we expose in the settings dropdown, but the dropdown is the source
     * of truth; map exactly what's there + sensible fallback.
     */
    private function extensionForImageType($imageType)
    {
        switch ($imageType) {
            // Thermal (Zebra) formats are saved as .lpt — that is the extension
            // the shipping module's labelView() recognizes to render the "Print
            // Thermal" button (raw bytes streamed to the printer via Zebra
            // Browser Print). Writing .zpl produced a file the viewer didn't
            // recognize, so the popup showed only a Close button.
            case 'ZPL203DPI':
            case 'ZPL300DPI':
            case 'EPL300DPI': return 'lpt';
            case 'PNG':       return 'png';
            case 'TIFF':      return 'tif';
            case 'JPG':       return 'jpg';
            case 'GIF':       return 'gif';
            case 'SVG':       return 'svg';
            case 'PDF':
            default:          return 'pdf';
        }
    }

    /**
     * Bulk tracking — Bizuno calls this nightly from the shipping cron with
     * a date and log_id range. USPS /tracking/v3r2/tracking takes up to 35
     * tracking numbers per call as a JSON array; we batch our query results
     * into that limit.
     *
     * @param string $track_date YYYY-MM-DD limit for which ship-log rows to refresh
     * @param int    $log_id     ship_log starting id (cron pages forward)
     * @return array Updated rows: [['log_id'=>..., 'tracking'=>..., 'status'=>..., 'delivery_date'=>...], ...]
     */
    public function trackBulk($track_date, $log_id)
    {
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'shipment_log',
            "carrier='$this->code' AND id>$log_id AND ship_date<='$track_date' AND (delivery_date IS NULL OR delivery_date='0000-00-00')",
            'id ASC', ['id', 'tracking_id'], 35);
        if (empty($rows)) { return []; }
        $body = [];
        $byTracking = [];
        foreach ($rows as $r) {
            if (empty($r['tracking_id'])) { continue; }
            $body[] = ['trackingNumber' => $r['tracking_id']];
            $byTracking[$r['tracking_id']] = $r['id'];
        }
        if (empty($body)) { return []; }
        $resp = $this->queryREST('post', '/tracking/v3r2/tracking', $body);
        if (empty($resp) || !is_array($resp)) { return []; }

        // Response is an array of TrackingDetail. We surface delivery date
        // (when the package was delivered) plus a status string for the log.
        // The cron caller will map these into UPDATE rows.
        $updates = [];
        foreach ($resp as $detail) {
            $tn = $detail['trackingNumber'] ?? '';
            if (empty($tn) || empty($byTracking[$tn])) { continue; }
            $delivered = '';
            if (!empty($detail['statusCategory']) && stripos($detail['statusCategory'], 'delivered') !== false) {
                // First trackingEvent is most recent; pluck its date.
                if (!empty($detail['trackingEvents'][0]['eventTimestamp'])) {
                    $delivered = substr($detail['trackingEvents'][0]['eventTimestamp'], 0, 10);
                }
            }
            $updates[] = [
                'log_id'       => $byTracking[$tn],
                'tracking'     => $tn,
                'status'       => $detail['status'] ?? ($detail['statusSummary'] ?? ''),
                'delivery_date'=> $delivered];
        }
        msgDebug("\nUSPS trackBulk updates: ".print_r($updates, true));
        return $updates;
    }
}
