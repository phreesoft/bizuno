<?php
/*
 * PhreeBooks dashboard - Cash Flow Forecast (running balance)
 *
 * Plots a single running cash-balance line covering N weeks back and M weeks
 * forward. Past weeks aggregate actual cash-movement journals (j18 customer
 * receipts, j20 vendor payments) by post_date. Future weeks project from open
 * AR (j12) and AP (j06) journal_main rows, using their terminal_date and a
 * partial-payment-aware open balance (total_amount + getPaymentInfo()). The
 * series starts from the current sum of cash-type GL account balances.
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
 * @version    7.x Last Update: 2026-04-28
 * @filesource /controllers/phreebooks/dashboards/cash_flow/cash_flow.php
 */

namespace bizuno;

class cash_flow
{
    public  $moduleID = 'phreebooks';
    public  $methodDir= 'dashboards';
    public  $code     = 'cash_flow';
    public  $secID    = 'j2_mgr';
    public  $category = 'banking';
    public  $struc;
    public  $lang     = ['title'=>'Cash Flow Forecast',
        'description'=>'Running cash balance combining cleared receipts/payments (past) with open AR/AP projected by due date (future). Reads cash on hand from the chart of accounts.'];

    function __construct()
    {
        $this->fieldStructure();
    }

    private function fieldStructure()
    {
        $this->struc = [
            'users'        => ['order'=>10,'label'=>lang('users'), 'clean'=>'array',  'attr'=>['type'=>'users',  'value'=>[0]],  'admin'=>true],
            'roles'        => ['order'=>20,'label'=>lang('groups'),'clean'=>'array',  'attr'=>['type'=>'roles',  'value'=>[-1]], 'admin'=>true],
            'weeks_back'   => ['order'=>30,'label'=>'Weeks back',  'clean'=>'integer','attr'=>['type'=>'integer','value'=>4]],
            'weeks_fwd'    => ['order'=>40,'label'=>'Weeks forward','clean'=>'integer','attr'=>['type'=>'integer','value'=>12]]];
        metaPopulate($this->struc, getMetaDashboard($this->code));
    }

    public function render($opts=[])
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php', 'phreebooksProcess', 'function');
        $weeksBack = max(0, (int)($opts['weeks_back'] ?? 4));
        $weeksFwd  = max(1, (int)($opts['weeks_fwd']  ?? 12));
        $struc     = $this->buildSeries($weeksBack, $weeksFwd);
        $title     = sprintf('Cash flow — %d weeks back, %d forward', $weeksBack, $weeksFwd);
        $layout    = [];
        googleLine1($layout, $this->code, ['title'=>$title, 'data'=>array_values($struc)]);
        return [
            'html'   => $layout['divs']['body']['html'] ?? '',
            'jsBody' => $layout['jsBody'][$this->code]  ?? '',
        ];
    }

    /**
     * Build the [[weekStartLabel, runningBalance], ...] series with a header row.
     *
     * Buckets are anchored at TODAY's cash on hand (which already reflects every
     * cleared past actual). Past weeks are computed by walking backward from the
     * anchor and SUBTRACTING that week's cleared activity (j18/j20) — the
     * historical balance at end-of-week-N is "today's cash minus everything that
     * has cleared since then." Future weeks are computed by walking forward from
     * the anchor and ADDING projected open AR/AP balances bucketed by their
     * terminal_date. Overdue items (terminal_date < today, still open) lump into
     * the current week.
     */
    private function buildSeries($weeksBack, $weeksFwd)
    {
        $today      = biz_date('Y-m-d');
        $startBal   = $this->getCashOnHand();
        $weekStarts = $this->buildWeekBuckets($today, $weeksBack, $weeksFwd);
        $weeks      = array_fill_keys($weekStarts,
            ['pastIn'=>0.0, 'pastOut'=>0.0, 'futIn'=>0.0, 'futOut'=>0.0]);
        $rangeStart = $weekStarts[0];

        // PAST: actual cleared cash movement (already reflected in $startBal).
        $past = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main',
            "journal_id IN (18, 20) AND post_date >= '$rangeStart' AND post_date <= '$today'",
            'post_date',
            ['journal_id', 'post_date', 'total_amount']);
        foreach ($past as $row) {
            $bucket = $this->bucketFor($row['post_date'], $weekStarts);
            if ($bucket === null) { continue; }
            $key = $row['journal_id'] == 18 ? 'pastIn' : 'pastOut';
            $weeks[$bucket][$key] += (float)$row['total_amount'];
        }

        // FUTURE: open AR (j12) + AP (j06), partial-paid aware.
        // Due date is always computed from post_date + terms via getTermsDate().
        // We intentionally do NOT use journal_main.terminal_date — on this
        // install it currently mirrors post_date instead of the actual due
        // date, so it can't be trusted. Revisit (see TODO scheduled for ~Nov
        // 2026) once the upstream terminal_date population is fixed.
        $open = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main',
            "journal_id IN (6, 12) AND closed='0'",
            'post_date',
            ['id', 'journal_id', 'total_amount', 'terms', 'post_date']);
        foreach ($open as $row) {
            $balance = (float)$row['total_amount'] + (float)getPaymentInfo($row['id'], $row['journal_id']);
            if ($balance <= 0) { continue; } // fully (or over-) paid via partials
            $isAR    = ($row['journal_id'] == 12);
            $dueDate = getTermsDate($row['terms'] ?? '', $isAR ? 'c' : 'v', $row['post_date'] ?? '');
            // Overdue (due date already past) lumps into the current week.
            $effDate = ($dueDate >= $today) ? $dueDate : $today;
            $bucket  = $this->bucketFor($effDate, $weekStarts);
            if ($bucket === null) { continue; } // beyond the chart window
            $key = $isAR ? 'futIn' : 'futOut';
            $weeks[$bucket][$key] += $balance;
        }

        // Anchor at today's bucket = current cash + projected rest-of-this-week.
        // Past actuals from this week are already in $startBal, so don't re-add.
        $weekKeys     = array_keys($weeks);
        $nowIdx       = (int)array_search($this->bucketFor($today, $weekStarts), $weekKeys, true);
        $balances     = [];
        $thisFut      = $weeks[$weekKeys[$nowIdx]]['futIn'] - $weeks[$weekKeys[$nowIdx]]['futOut'];
        $balances[$nowIdx] = $startBal + $thisFut;

        // Walk forward: add future net of each later week. (No past activity
        // exists in future weeks for j18/j20, but include defensively.)
        for ($i = $nowIdx + 1; $i < count($weekKeys); $i++) {
            $b = $weeks[$weekKeys[$i]];
            $balances[$i] = $balances[$i-1]
                + ($b['futIn']  - $b['futOut'])
                + ($b['pastIn'] - $b['pastOut']);
        }

        // Walk backward: subtract the FULL net of week (i+1) to derive end-of-i.
        // For i+1 == nowIdx this removes both past + future portions of this
        // week (giving end-of-last-week = startBal - thisWeek's past actuals).
        // For i+1 < nowIdx, futIn/futOut should be 0 so it's just past activity.
        $running = $balances[$nowIdx];
        for ($i = $nowIdx - 1; $i >= 0; $i--) {
            $b = $weeks[$weekKeys[$i+1]];
            $running -= ($b['pastIn'] - $b['pastOut']) + ($b['futIn'] - $b['futOut']);
            $balances[$i] = $running;
        }

        $struc = [[lang('week') ?: 'Week', lang('balance') ?: 'Balance']];
        for ($i = 0; $i < count($weekKeys); $i++) {
            $struc[] = [viewFormat($weekKeys[$i], 'date'), round($balances[$i], 2)];
        }
        return $struc;
    }

    /**
     * Build an ordered list of week-start dates (Mondays) covering the requested
     * window. The bucket for $today's week is included exactly once.
     */
    private function buildWeekBuckets($today, $weeksBack, $weeksFwd)
    {
        $monday = new \DateTime($today);
        $dow    = (int)$monday->format('N'); // 1=Mon..7=Sun
        if ($dow > 1) { $monday->modify('-' . ($dow - 1) . ' days'); }
        $first  = (clone $monday)->modify("-{$weeksBack} week");
        $last   = (clone $monday)->modify("+{$weeksFwd} week");
        $out    = [];
        for ($wk = clone $first; $wk <= $last; $wk->modify('+1 week')) {
            $out[] = $wk->format('Y-m-d');
        }
        return $out;
    }

    /**
     * Return the week-start key whose week contains $date, or null if $date
     * falls outside the bucket range.
     */
    private function bucketFor($date, array $weekStarts)
    {
        if (empty($date)) { return null; }
        $first = $weekStarts[0];
        $last  = end($weekStarts);
        if ($date < $first) { return null; }
        // last bucket covers its own 7 days; anything after is out of range
        $lastEnd = (new \DateTime($last))->modify('+7 days')->format('Y-m-d');
        if ($date >= $lastEnd) { return null; }
        // pick the latest bucket whose start <= date
        $pick = $first;
        foreach ($weekStarts as $start) {
            if ($start <= $date) { $pick = $start; }
            else                 { break; }
        }
        return $pick;
    }

    /**
     * Current sum of all cash-type (gl_type=0) account balances. Reads the
     * per-period running totals from journal_history in a single aggregate
     * query — same shape used by reconcile.php and the FY-roll path in
     * tools.php — so we skip the per-account dbGetGLBalance loop and the
     * journal_item walk it does internally.
     */
    private function getCashOnHand()
    {
        $period = (int)getModuleCache('phreebooks', 'fy', 'period');
        if (empty($period)) { return 0.0; }
        $cash = dbGetValue(BIZUNO_DB_PREFIX.'journal_history',
            'SUM(beginning_balance + debit_amount - credit_amount) AS cash',
            "gl_type=0 AND period=$period",
            false);
        return (float)$cash;
    }
}
