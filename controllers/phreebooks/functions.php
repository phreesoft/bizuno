<?php
/*
 * PhreeBooks support functions
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
 * @version    7.x Last Update: 2026-04-24
 * @filesource /controllers/phreebooks/functions.php
 */

namespace bizuno;

/**
 * Creates a drop down ready list of choices for bands used in the GL search
 * @return array - ready for a select DOM element
 */
function selChoices()
{
    return [
        ['id'=>'all', 'text'=>lang('all')],
        ['id'=>'band','text'=>lang('range')],
        ['id'=>'eq',  'text'=>lang('equal')],
        ['id'=>'not', 'text'=>lang('not_equal')],
        ['id'=>'inc', 'text'=>lang('contains')]];
}

/**
 * Creates a list of journals to use in a select DOM element
 * @return type
 */
function selGLTypes()
{
    $types = [
        ['id'=> 0, 'text'=>lang('gl_acct_type_0'), 'asset'=>true],  // Cash
        ['id'=> 2, 'text'=>lang('gl_acct_type_2'), 'asset'=>true],  // Accounts Receivable
        ['id'=> 4, 'text'=>lang('gl_acct_type_4'), 'asset'=>true],  // Inventory
        ['id'=> 6, 'text'=>lang('gl_acct_type_6'), 'asset'=>true],  // Other Current Assets
        ['id'=> 8, 'text'=>lang('gl_acct_type_8'), 'asset'=>true],  // Fixed Assets
        ['id'=>10, 'text'=>lang('gl_acct_type_10'),'asset'=>false], // Accumulated Depreciation
        ['id'=>12, 'text'=>lang('gl_acct_type_12'),'asset'=>true],  // Other Assets
        ['id'=>20, 'text'=>lang('gl_acct_type_20'),'asset'=>false], // Accounts Payable
        ['id'=>22, 'text'=>lang('gl_acct_type_22'),'asset'=>false], // Other Current Liabilities
        ['id'=>24, 'text'=>lang('gl_acct_type_24'),'asset'=>false], // Long Term Liabilities
        ['id'=>30, 'text'=>lang('gl_acct_type_30'),'asset'=>false], // Income
        ['id'=>32, 'text'=>lang('gl_acct_type_32'),'asset'=>true],  // Cost of Sales
        ['id'=>34, 'text'=>lang('gl_acct_type_34'),'asset'=>true],  // Expenses
        ['id'=>40, 'text'=>lang('gl_acct_type_40'),'asset'=>false], // Equity - Doesn't Close
        ['id'=>42, 'text'=>lang('gl_acct_type_42'),'asset'=>false], // Equity - Gets Closed
        ['id'=>44, 'text'=>lang('gl_acct_type_44'),'asset'=>false]];// Equity - Retained Earnings
    return sortOrder($types, 'text');
}

/**
 * Processes a value by format, used in PhreeForm
 * @global array $report - report structure
 * @param mixed $value - value to process
 * @param type $format - what to do with the value
 * @return mixed, returns $value if no formats match otherwise the formatted value
 */
function phreebooksProcess($value, $format = '')
{
    global $report;
    switch ($format) {
        case 'AgeCur': // Calculates aging for a specific invoice
        case 'Age30':
        case 'Age60':
        case 'Age61':
        case 'Age90':
        case 'Age91':
        case 'Age120': return procInvAging($value, $format);
        // *********** Statement Processing ***************
        case 'age_00': // Calculates aging for the contact covering all invoices, assumes customers @TODO - this needs to be fixed!
        case 'age_30':
        case 'age_60':
        case 'age_61':
        case 'age_90':
        case 'age_91':
        case 'age_121':
        case 'begBal':
        case 'endBal':
        case 'ageTot':
            $cID = clean($value, 'integer');
            if (isset($report->datedefault) && !isset($GLOBALS['aging']['c'.$cID])) {
                $dates = explode(":", $report->datedefault); // encoded dates, type:start:end
                $output = calculate_aging($cID, $dates[1], $dates[2]);
                if (!empty($output['main'])) {
                    $GLOBALS['main'] = $output['main']; // save the record data for the table
                    unset($output['main']);
                }
                $GLOBALS['curBal'] = $output['beg_bal'];
                $GLOBALS['aging']['c'.$cID] = $output;
            }
            if ($format=='age_00') { return $GLOBALS['aging']['c'.$cID]['cust']['balance_0'];  } // current
            if ($format=='age_30') { return $GLOBALS['aging']['c'.$cID]['cust']['balance_30']; } // aging  1-30
            if ($format=='age_60') { return $GLOBALS['aging']['c'.$cID]['cust']['balance_60']; } // aging 31-60
            if ($format=='age_90') { return $GLOBALS['aging']['c'.$cID]['cust']['balance_90']; } // aging Over 60 past due date
            if ($format=='age_120'){ return $GLOBALS['aging']['c'.$cID]['cust']['balance_120'];} // aging Over 60 past due date
            if ($format=='age_61') { return $GLOBALS['aging']['c'.$cID]['cust']['balance_61']; } // aging 61-90
            if ($format=='age_91') { return $GLOBALS['aging']['c'.$cID]['cust']['balance_91']; } // aging 91-120
            if ($format=='age_121'){ return $GLOBALS['aging']['c'.$cID]['cust']['balance_121'];} // aging over 120
            if ($format=='begBal') { return $GLOBALS['aging']['c'.$cID]['cust']['beg_bal'];    } // beginning balance
            if ($format=='endBal') { return $GLOBALS['aging']['c'.$cID]['cust']['end_bal'];    } // ending balance, total balance oustanding
            break;
        // ************ Bank Processing *******************
        case 'bnkReg':
            // for this to work the report needs to have journal_id as an hidden field
            if (!empty($GLOBALS['currentRow']['journal_id']) && in_array($GLOBALS['currentRow']['journal_id'], [7,13,17,19,21,22])) { $value = -$value; }
            return $value;
        case 'bnkCard': // type of credit card, needs journal_item.description
            $vals = clean($value, 'bizunzip');
            $type = !empty($vals['hint']) ? substr($vals['hint'], 0, 1) : '0';
            $types= ['3'=>'American Express', '4'=>'Visa', '5'=>'MasterCard', '6'=>'Discover'];
            return  isset($types[$type]) ? $types[$type] : $type;
        case 'bnkCode': // credit card approval code, needs journal_item.description
            $vals = clean($value, 'bizunzip');
            return !empty($vals['code'])? $vals['code'] : '';
        case 'bnkHint': // last 4 of credit card, needs journal_item.description
            $vals = clean($value, 'bizunzip');
            return !empty($vals['hint'])? '**********'.substr($vals['hint'], -4) : '';
        // ************ Income Statement Processing *******************
        case 'isCur':  return !empty($report->currentValues['amount'])       ? $report->currentValues['amount']       : ''; // income_statement current period
        case 'isYtd':  return !empty($report->currentValues['amount_ytd'])   ? $report->currentValues['amount_ytd']   : ''; // income_statement year to date
        case 'isBdgt': return !empty($report->currentValues['budget'])       ? $report->currentValues['budget']       : ''; // income_statement budget current period
        case 'isBytd': return !empty($report->currentValues['budget_ytd'])   ? $report->currentValues['budget_ytd']   : ''; // income_statement budget year to date
        case 'isLcur': return !empty($report->currentValues['ly_amount'])    ? $report->currentValues['ly_amount']    : ''; // income_statement last year current period
        case 'isLytd': return !empty($report->currentValues['ly_amount_ytd'])? $report->currentValues['ly_amount_ytd']: ''; // income_statement last year to date
        case 'isLBgt': return !empty($report->currentValues['ly_budget'])    ? $report->currentValues['ly_budget']    : ''; // income_statement last year budget current period
        case 'isLBtd': return !empty($report->currentValues['ly_budget_ytd'])? $report->currentValues['ly_budget_ytd']: ''; // income_statement last year budget year to date
        // ************ Invoice Processing *******************
        case 'invBalance': // needs journal_main.id
            $rID  = intval($value);
            $main = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', ['journal_id', 'total_amount'], "id='$rID'");
            if (isset($GLOBALS['curBal'])) {
                if (in_array($main['journal_id'], [18])) { $main['total_amount'] = -$main['total_amount']; }
                $GLOBALS['curBal'] += $main['total_amount'];
                return $GLOBALS['curBal'];
            } else { // just a single, get the remaining balance
                return $main['total_amount'] + getPaymentInfo($rID, $main['journal_id']);
            }
        case 'invCOGS': // get journal post cogs given the journal main record id
            $rID = intval($value);
            return dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'debit_amount', "ref_id=$rID AND gl_type='asy'");
        case 'invRefNum': // needs journal_main.id
            $rID = intval($value);
            return dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'invoice_num', "id=$rID");
        case 'invUnit':
            $rID = intval($value);
            if (!$rID) { return ''; }
            $row =  dbGetValue(BIZUNO_DB_PREFIX.'journal_item', ['qty','credit_amount','debit_amount'], "id=$rID");
            return !empty($row['qty']) ? ($row['credit_amount'] + $row['debit_amount'])/$row['qty'] : 0;
        case 'itmTaxAmt': // needs journal_item.id, journal item tax rate amount
            $rID = intval($value);
            $row = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', ['tax_rate_id','credit_amount','debit_amount'], "id=$rID");
            if (empty($row)) { return 0; }
            return ($row['credit_amount'] + $row['debit_amount']) * getTaxRate($row['tax_rate_id']);
        // ************ Order Processing *******************
        case 'orderCOGS': return dbGetOrderCOGS($value);
        // ************ Payment Processing *******************
        case 'paymentDue': // needs journal_main.id
            $rID  = clean($value, 'integer');
            if (!$rID) { return ''; }
            $row  = dbGetValue(BIZUNO_DB_PREFIX."journal_main", ['journal_id','total_amount','post_date','terms'], "id=$rID");
            $dates= localeDueDate($row['post_date'], $row['terms']); //, $type);
            $disc = $row['post_date'] <= $dates['early_date'] ? roundAmount($dates['discount'] * $row['total_amount']) : 0;
            if ($format == 'pmtDisc') { return $disc; }
            return $row['total_amount'] - $disc;
        case 'paymentRcv': // needs journal_main.id
            $rID   = clean($value, 'integer');
            if (!$rID) { return ''; }
            $jID   = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'journal_id', "id=$rID");
            return -getPaymentInfo($rID, $jID);
        case 'paymentRef': // gets the payment transaction code, needs journal_main.id
            $invID = clean($value, 'integer');
            $pmtID = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'ref_id', "item_ref_id=$invID");
            if ($pmtID) { return dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'trans_code', "ref_id=$pmtID AND gl_type='ttl'"); }
            else        { return ''; }
        case 'pmtDate': // needs journal_main.id
            $rID   = clean($value, 'integer');
            $result= dbGetValue(BIZUNO_DB_PREFIX.'journal_main', ['post_date','journal_id','terms'], "id=$rID");
            if (!in_array($result['journal_id'], ['3','4','6','7','9','10','12','13'])) { return ''; }
            $temp  = localeDueDate($result['post_date'], $result['terms']);
            return $temp['net_date'];
        case 'pmtDisc':
        case 'pmtNet':
            $pmt = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', ['ref_id','description','debit_amount'], "id='$value'");
            $dsc = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'credit_amount', "ref_id='{$pmt['ref_id']}' AND gl_type='dsc' AND description='{$pmt['description']}'");
            if ($format=='pmtDisc') { return $dsc; }
            return $pmt['debit_amount'] - $dsc;
        case 'ship_bal': // pass table journal_item.id and check for quantites remaining to be shipped
            msgDebug("\nEntering ship_bal with value = $value");
            $refID = clean($value, 'integer');
            if (!$refID) { return 0; }
            $qtySO = dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'qty', "id=$refID");
            if ($qtySO) {
                $filled = dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'SUM(qty) as qty', "item_ref_id=$refID", false);
                return $qtySO - $filled;
            } else { return 0; }
        case 'shipBalVal': // pass table journal_item.id and check for quantites remaining to be shipped
            $refID = clean($value, 'integer');
            $ttlSO = dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'debit_amount+credit_amount', "id=$refID", false);
            if ($ttlSO) {
                $invSO = dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'SUM(debit_amount+credit_amount) as invSO', "item_ref_id=$refID", false);
                return $ttlSO - $invSO;
            } else { return 0; }
        case 'ship_prior': // pass table journal_item.id and check for quantites shipped prior
            if (!$value) { return 0; }
            if (strpos($value, ':')) {
                $tmp = explode(':', $value);
                $links = ['ref_id'=>$tmp[0], 'item_ref_id'=>$tmp[1]];
            } else {
                $links = dbGetValue(BIZUNO_DB_PREFIX."journal_item", ['ref_id', 'item_ref_id'], "id=$value");
            }
            if ($links['item_ref_id']) {
                return dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'SUM(qty)', "item_ref_id={$links['item_ref_id']} AND ref_id!={$links['ref_id']}", false);
            } else { return 0; }
        case 'soStatus': // pulls the entire Sales Order line items from a given Invoice #, rqd to pass (journal_main.id)
            $rID    = intval($value);
            $invRows= dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID AND gl_type='itm'");
            $soID   = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'so_po_ref_id', "id=$rID");
            $soRows = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$soID AND gl_type='itm'");
            foreach (array_keys($soRows) as $idx) { $soRows[$idx]['qty'] = 0; } // erase the quantity as actuals will be calculated later
            $invID  = 0;
            foreach ($invRows as $invRow) { // combine values
                $idx = pbGetRefRow($invRow['item_ref_id'], $soRows);
                if ($idx===false) { continue; } // reference not found, shouldn't happen
                $soRows[$idx] = $invRow;
                $invID = $invRow['ref_id'];
            }
            foreach ($report->fieldlist as $TableObject) { if ($TableObject->type <> 'Tbl') { continue; } else { break; } } // get the report table field
            $output = [];
            foreach ($soRows as $row) {
                $rowData = [];
                foreach ($TableObject->settings->boxfield as $cIdx => $col) {
                    $parts = explode('.', $col->fieldname, 2); // strip the table name
                    switch ($parts[1]) {
                        case 'credit_amount': $rowData["r$cIdx"] = $row['item_ref_id'] ? $row['credit_amount'] : 0; break;
                        case 'debit_amount':  $rowData["r$cIdx"] = $row['item_ref_id'] ? $row['debit_amount']  : 0; break;
                        default:
                            if (!empty($col->processing) && $col->processing == 'ship_prior'){
                                $rowData["r$cIdx"] = "$invID:".($row['item_ref_id'] ? $row['item_ref_id'] : $row['id']); // needs encoding current invoice ID:SO item ID
                            } elseif (!empty($col->processing) && $col->processing == 'ship_bal')  { // reindex so processing will yield proper results
                                if (!$row['sku']) { $rowData["r$cIdx"] = 0; }
                                else { $rowData["r$cIdx"] = $row['item_ref_id'] ? $row['item_ref_id'] : $row['id']; }
                            } else {
                                $rowData["r$cIdx"] = isset($row[$parts[1]]) ? $row[$parts[1]] : '';
                            }
                    }
                }
                $output[] = $rowData;
            }
            msgDebug("\nReturning processed soRows = ".print_r($output, true));
            return $output;
        case 'subTotal':
            $rID = clean($value, 'integer');
            return dbGetValue(BIZUNO_DB_PREFIX."journal_item", "SUM(debit_amount-credit_amount) AS F0", "ref_id=$rID AND gl_type='itm'", false);
        case 'taxJrnl':
            $rID = intval($value);
            $main = dbGetValue(BIZUNO_DB_PREFIX."journal_main", ['journal_id', 'sales_tax'], "id=$rID");
            return in_array($main['journal_id'], [7,13,18,19,20,21]) ? -$main['sales_tax'] : $main['sales_tax'];
        case 'taxRate': return (getTaxRate(intval($value)) * 100) . "%"; // needs tax rate id
        case 'ttlJrnl':
            $rID = intval($value);
            $main = dbGetValue(BIZUNO_DB_PREFIX."journal_main", ['journal_id', 'total_amount'], "id=$rID");
            return in_array($main['journal_id'], [7,13,18,19,20,21]) ? -$main['total_amount'] : $main['total_amount'];
        default:
    }
    return $value;
}

/**
 * Shows the aging amount of a single invoice if it falls within the proper aging column, Pass journal_main record ID
 * @param type $value
 */
function procInvAging($value=false, $format='') {
    msgDebug("\nEntering procInvAging with value = $value and format = $format");
    if (empty($value)) { return ''; }
    $vals = pbGetAging(intval($value));
    switch ($format) {
        case 'AgeCur': return $vals['bal0'];   // Current
        case 'Age30':  return $vals['bal30'];  // 1-30
        case 'Age60':  return $vals['bal60'];  // 31-60
        case 'Age90':  return $vals['bal90'];  // 61-90
        case 'Age120': return $vals['bal120']; // 91-120
        case 'Age61':  return $vals['bal61'];  // Over 60
        case 'Age91':  return $vals['bal91'];  // Over 90
        case 'Age121': msgDebug("\nAge121 with vals = ".print_r($vals, true)); return $vals['bal121']; // Over 120
    }
    return 0;
}

/**
 * This function calculates the contact aging summary entries for purchase/sales order and purchases/invoices
 * @param integer $cID - contact id to find aging
 * @param string $bb_date - starting date range, default today
 * @param string $eb_date - ending date range, default tomorrow
 * @return array $output - aging results
 */
function calculate_aging($cID, $bb_date=false, $eb_date=false)
{
    msgDebug("\nEntering calculate_aging with BB Date=$bb_date, EB Date=$eb_date");
    $defaults= ['data'=>[],'total'   =>0, 'past_due'   =>0, 'credit_limit'=>0,
        'terms_lang'=>'','beg_bal'   =>0, 'end_bal'    =>0,
        'balance_0' =>0, 'balance_30'=>0, 'balance_60' =>0, 'balance_61'  =>0,
        'balance_90'=>0, 'balance_91'=>0, 'balance_120'=>0, 'balance_121' =>0];
    if (empty($cID)) { return $defaults; }
    $result= dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['ctype_c', 'ctype_v', 'terms'], "id=$cID");
    // @TODO - terms is generic, need to separate into terms_c and terms_v
    $today = biz_date('Y-m-d');
    $terms = localeDueDate($today, $result['terms']);
    $output['cust'] = array_merge($defaults, ['credit_limit'=>$terms['credit_limit'],'terms_lang'=>viewTerms($result['terms'], false, 'c', true)]);
    $output['vend'] = array_merge($defaults, ['credit_limit'=>$terms['credit_limit'],'terms_lang'=>viewTerms($result['terms'], false, 'v', true)]);
    $addr  = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
    $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "contact_id_b=$cID AND journal_id IN (6, 7, 12, 13) AND closed='0'", 'post_date');
    foreach ($rows as $row) {
        $type = in_array($row['journal_id'], [6,7]) ? 'vend' : 'cust';
        $aging= pbGetAging($row['id']);
        $output[$type]['total']      += $aging['total'];
        $output[$type]['past_due']   += $aging['past_due'];
        $output[$type]['balance_0']  += $aging['bal0'];  // Current
        $output[$type]['balance_30'] += $aging['bal30']; // Up to 30 days past terms
        $output[$type]['balance_60'] += $aging['bal60']; // 31-60 days past terms
        $output[$type]['balance_90'] += $aging['bal90']; // 61-90 days past terms
        $output[$type]['balance_120']+= $aging['bal120'];// 91-120 days past terms
        $output[$type]['balance_61'] += $aging['bal61']; // Over 60
        $output[$type]['balance_91'] += $aging['bal91']; // Over 90
        $output[$type]['balance_121']+= $aging['bal121'];// Over 120
        $output[$type]['data'][]      = [$addr['primary_name'], viewFormat($row['post_date'], 'date'), $row['invoice_num'],
            $aging['bal0'], $aging['bal30'], $aging['bal60'], $aging['bal90'], $aging['bal120'], $aging['bal121']];
    }
    if (!empty($bb_date)) {
        $output[$type]['beg_bal'] = $output[$type]['total'] - pbGetBegBal($cID, !empty($result['ctype_v'])?'v':'c', $bb_date);
    }
    if (!empty($eb_date)) {
        $output[$type]['end_bal'] = $output[$type]['total'] - pbGetBegBal($cID, !empty($result['ctype_v'])?'v':'c', $eb_date);        
    }
    msgDebug("\nCalculated aging for cID = $cID, returning = ".print_r($output, true));
    return $output;
}

function pbGetAging($rID=0)
{
    $today= biz_date('Y-m-d');
    if (empty($GLOBALS['pbAging']['id'.$rID])) {
        $result   = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', ['terms', 'post_date', 'total_amount', 'journal_id'], "id=$rID");
        if (empty($result)) { return ''; }
        $balance  = $result['total_amount'] + getPaymentInfo($rID, $result['journal_id']);
        if (in_array($result['journal_id'], [7,13])) { $balance = -$balance; }
        $term_date= localeDueDate($result['post_date'], $result['terms']);
        $due_date = $term_date['net_date'];
        $days_late= dateNumDaysDiff($today, $due_date);
        msgDebug("\nCalculated days late = $days_late");
        $output = ['total'=>$balance,'past_due'=>$balance,'bal0'=>0, 'bal30'=>0, 'bal60'=>0, 'bal90'=>0, 'bal120'=>0, 'bal61'=>0, 'bal91'=>0, 'bal120'=>0, 'bal121'=>0];
        if     ($days_late >120) { msgDebug("\nAdding to Over 121: $balance");$output['bal61'] = $balance; $output['bal91'] = $balance; $output['bal121']= $balance; }
        elseif ($days_late > 90) { msgDebug("\nAdding to 91-120: $balance");  $output['bal61'] = $balance; $output['bal91'] = $balance; $output['bal120']= $balance; }
        elseif ($days_late > 60) { msgDebug("\nAdding to 61-90: $balance");   $output['bal61'] = $balance; $output['bal90'] = $balance; }
        elseif ($days_late > 30) { msgDebug("\nAdding to 31-60: $balance");   $output['bal60'] = $balance; }
        elseif ($days_late >  0) { msgDebug("\nAdding to 1-30: $balance");    $output['bal30'] = $balance; }
        else                     { msgDebug("\nAdding to Current: $balance"); $output['bal0']  = $balance; $output['past_due'] = 0; } // current
        $GLOBALS['pbAging']['id'.$rID] = $output;
    }
    return $GLOBALS['pbAging']['id'.$rID];
}

/**
 * Finds the proper index of a sale referenced by the sales order. Lines can move since DnD is now allowed
 * @param integer $ref - index from the invoice to find
 * @param array $soRows - sales order item array
 * @return index or item array if found or false if not found (should never happen)
 */
function pbGetRefRow($ref, $soRows=[])
{
    foreach ($soRows as $idx => $row) {
        if ($ref == $row['id']) { return $idx; }
    }
    return false;
}

/**
 * Creates a 8 character reference used to index gl entries for re-posting entries. the idea here is to re-post journals that increase stock first to have enough stock to sell/assemble
 * @param integer $ts - date timestamp
 * @param integer $idx - table journal_main record id
 * @param integer $jID - journal ID used for balancing vendors vs customer transactions
 * @return string - format jID:ts:idx with padding
 */
function padRef($ts, $idx, $jID=8)
{
    switch ($jID) {
        case  7: $jID = 12; break; // like a sale, stock decreases
        case 13: $jID =  6; break; // like a purchase, stock increases
        case 14: $jID =  8; break; // assembly before sale, after adjustment/transfers. Stock decreases. 
        case 15:
        case 16: $jID =  7; break; // transfers/adjustments. Can be add or subtract, make neutral, after purchases, before assembly/sales. Typically used to increase stock
        default: // no mapping, use the journal id as is
    }
    return str_pad($jID, 2, '0', STR_PAD_LEFT).':'.substr($ts, 0, 10).':'.str_pad($idx, 8, '0', STR_PAD_LEFT);
}

/**
 * tests an order row to determine if it contains actionable data
 * @param array $row - datagrid row containing item information
 * @param array $testList - list of fields to test to decide if row should be skipped
 * @return true if row does not contain useful information, false otherwise
 */
function isBlankRow($row, $testList=[])
{
    $qtyOverride = getModuleCache('phreebooks', 'settings', 'customers', 'include_all') ? true : false;
    if (!isset($row['qty']) || $row['qty'] == 0) {
        if (isset($row['sku']) && $row['sku'] && $qtyOverride) {
            return false;
        } else {
            return true;
        }
    }
    foreach ($testList as $field) { if (isset($row[$field]) && $row[$field]) { return false; } }
    return true;
}

/**
 * Guesses the default GL account for a given type
 * @param integer $type - [Default: 34 (expense)] numeric GL account type
 */
function getGLAcctDefault($type=34) {
    $firstHit = $defHit = '';
    $glAccts  = getModuleCache('phreebooks', 'chart');
    foreach ($glAccts as $acct => $props) {
        if ($props['type']==$type) {
            if ( empty($firstHit)) { $firstHit = $acct; }
            if ($props['default']==$acct) { $defHit = $acct; }
        }
    }
    msgDebug("\nReturning from getGLAcctDefault with defHit = $defHit and firstHit = $firstHit");
    return !empty($defHit) ? $defHit : $firstHit;
}

/**
 * Retrieves the paid line items from a given journal entry
 * @param integer $mID - journal_main record id
 * @param integer $jID - journal ID
 * @return type
 */
function getPaymentInfo($mID, $jID) {
    // 2023-01-05 = Removed the dsc case as it keeps payment with discounts open. Can't find a case where the discount need to be included but
    // should a case appear, then it needs to be handled special or with a criteria added,
//    $crit = in_array($jID, [17,18,20,22]) ? "'pmt'" : "'dsc','pmt'"; // for payments, don't include the discount as it is tracked separately
    $crit = "'pmt'"; // for payments, don't include the discount as it is tracked separately
    $paid = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(debit_amount)-SUM(credit_amount) AS credits", "item_ref_id=$mID AND gl_type IN ($crit)", false);
    if (!$paid) { $paid = 0; }
    if (in_array($jID, [2,6,13])) { $paid = -$paid; } // 2023-02-27 removed jID==7 because VCM's were not closing
    $output = round($paid, getModuleCache('bizuno', 'settings', 'locale', 'number_precision'));
    msgDebug("\nIn getPaymentInfo with mID=$mID and jID=$jID, returning rounded precision paid = $output");
    return $output;
}

/**
 * Calculates the value items received on a PO/SO to subtract from the original total
 * @param integer $mID - journal_main record ID
 * @param integer $jID - journal ID
 * @return type
 */
function getInvoiceInfo($mID, $jID) {
    msgDebug("\nIn getInvoiceInfo with main ID = $mID and journal ID = $jID");
    $total= 0;
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$mID AND gl_type='itm'", 'id', ['id']);
    foreach ($rows as $row) {
        $total += dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(debit_amount)-SUM(credit_amount) AS credits", "item_ref_id={$row['id']} AND gl_type='itm'", false);
    }
    return $total;
}

function guessPaymentMethod($mID=0, $iDesc='') {
    $desc  = empty($iDesc) ? dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'description', "ref_id=$mID AND gl_type='ttl'") : $iDesc;
    msgDebug("\nread desc = $desc");
    $temp  = bizDecode($desc);
    msgDebug("\nDecoded = ".print_r($temp, true));
    $method= isset($temp['method']) ? $temp['method'] : '';
    msgDebug("\nIn guessPaymentMethod, returning method = $method");
    return $method;
}

/**
 * This function maps the contacts record and main address information to the journal_main table fields.
 * @param integer $cID - table contact field id of contact to retrieve
 * @param string $suffix - specifies billing or shipping fields to populate
 * @return array $output -  mapped fields of contact to journal
 */
function mapContactToJournal($cID = 0, $suffix='_b')
{
    if (!$cID) {
        msgAdd("function mapContactToJournal - Failed mapping contact to journal record");
        return [];
    }
    $aData = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
    $output = ['post_date'    => biz_date('Y-m-d'),
        'rep_id'              => getUserCache('profile', 'userID', false, 0),
        'contact_id'.$suffix  => $cID,
        'address_id'.$suffix  => !empty($aData['address_id']) ? $aData['address_id'] : 0,
        'primary_name'.$suffix=> $aData['primary_name'],
        'contact'.$suffix     => $aData['contact'],
        'address1'.$suffix    => $aData['address1'],
        'address2'.$suffix    => $aData['address2'],
        'city'.$suffix        => $aData['city'],
        'state'.$suffix       => $aData['state'],
        'postal_code'.$suffix => $aData['postal_code'],
        'country'.$suffix     => $aData['country'],
        'telephone1'.$suffix  => $aData['telephone1'],
        'email'.$suffix       => $aData['email']];
    $cData = dbGetRow(BIZUNO_DB_PREFIX."contacts", "id='$cID'");
    $output['type']    = !empty($cData['type']) ? $cData['type'] : 'c';
    $output['terms']   = isset($cData['terms']) && $cData['terms'] ? $cData['terms'] : '0';
    $output['currency']= isset($cData['currencyISO']) && $cData['currencyISO'] ? $cData['currencyISO'] : getDefaultCurrency();
    return $output;
}

/**
 * Extracts the accounts payable account for a posted journal entry to set default in an edit
 * @param array $row - journal_main structure
 * @return array modified $row - ap gl account added
 */
function glFindAPacct(&$row, $type='ap')
{
    msgDebug("\nIn glFindAPacct");
    if (empty($row['id'])) { return ''; }
    $typeNum = $type=='ar' ? 2 : 20;
    $iRows = dbGetMulti(BIZUNO_DB_PREFIX."journal_item", "ref_id='{$row['id']}'");
    foreach ($iRows as $item) {
        $type = getModuleCache('phreebooks', 'chart', $item['gl_account'], 'type');
        msgDebug("\ngl_account = {$item['gl_account']} and type = $type");
        if ($type <> $typeNum) { continue; } // Accounts Payable type gl account
        if (empty($row['gl_acct_id'])) {
            $row['gl_acct_id']   = $item['gl_account'];
            $row['total_amount'] = $item['debit_amount'] + $item['credit_amount'];
        } else {
            msgAdd("More than one Accounts Payable account has been found for ref # {$row['invoice_num']}. When paying a vendor from a post to the general journal there can only be one line item assigned to an Accounts Payable type account. The general journal entry needs to be fixed!");
            $row['gl_acct_id'] = ''; // clear the GL since there are more than 1
            return;
        }
    }
}

/**
 * Gets the current tax rate for a specified database table record id
 * @param integer $taxID - tax_rate_id from journal_main or journal_item table
 * @return float - tax rate value
 */
function getTaxRate($taxID=0)
{
    $ratesC= getModuleCache('phreebooks', 'sales_tax', 'c', false, []); // try customers first
    foreach ($ratesC as $row) { if ($row['id'] == $taxID) { return $row['rate']/100; } }
    $ratesV= getModuleCache('phreebooks', 'sales_tax', 'v', false, []); // not found, try vendors
    foreach ($ratesV as $row) { if ($row['id'] == $taxID) { return $row['rate']/100; } }
    return 0; // not found return 0
}


/**
 * Loads the tax rate information from the database and creates a structure for the session cache
 * @param char $type - choices are c for customers or v for vendors
 * @param string $date - [default Y-m-d] date to use to limit results to start date before passed date
 * @return array - list of valid tax rates
 */
function loadTaxes($type, $date=false)
{
    if (!$date) { $date = biz_date('Y-m-d'); }
    $output  = [];
    $taxRates= getModuleCache('phreebooks', 'sales_tax', $type, false, []);
    foreach ($taxRates as $row) {
        $output[] = ['id'=>$row['id'],'text'=>$row['title'],'tax_rate'=>$row['rate']." %",'status'=>$row['status'],'auths'=>$row['settings']];
    }
    array_unshift($output, ['id'=>'0', 'text'=>lang('none'), 'status'=>0, 'tax_rate'=>"0 %", 'auths'=>[]]);
    return $output;
}

/**
 * Validates a fiscal year and creates entries in the journal_periods, used when creating a new fiscal year
 * @param integer $next_fy - Fiscal year to create
 * @param integer $next_period - first period of next fiscal year
 * @param string $next_start_date - first date of next fiscal year
 * @param integer $num_periods - number of periods in fiscal year [default 12]
 * @return integer - next period (for successive adds)
 */
function setNewFiscalYear($next_fy, $next_period, $next_start_date, $num_periods=12)
{
    $periods = [];
    for ($i = 0; $i < $num_periods; $i++) {
        $fy_array = [
            'period'     => $next_period,
            'fiscal_year'=> $next_fy,
            'start_date' => $next_start_date,
            'end_date'   => localeCalculateDate($next_start_date, $day_offset = -1, $month_offset = 1),
            'date_added' => biz_date('Y-m-d'),
            'last_update'=> biz_date('Y-m-d')];
        $periods[] = "('".implode("', '", $fy_array)."')";
        $next_period++;
        $next_start_date = localeCalculateDate($next_start_date, $day_offset = 0, $month_offset = 1);
    }
    dbGetResult("INSERT INTO ".BIZUNO_DB_PREFIX."journal_periods VALUES ".implode(",\n",$periods));
    return $next_period--;
}

function setRetainedEarningsDefault()
{
    if (!empty(getChartDefault(44))) { return; }
    $charts = getModuleCache('phreebooks', 'chart');
    foreach ($charts as $key => $acct) { 
        if ($acct['type']==44) { 
            $charts[$key]['default'] = 1;
// @TODO - This needs to be converted to new meta architecture
            setModuleCache('phreebooks', 'chart', '', $charts);
            break;
        }
    }
}

/**
 * Loads the journal_history table when adding a new fiscal year or chart of accounts value
 */
function buildChartOfAccountsHistory()
{
    if (!$max_period = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', "MAX(period) AS period", '', false)) {
        msgDebug("\nBuilding chart of accounts history", 'trap');
        msgDebugWrite();
        die ('table journal_periods is not set!');
    }
    $records = [];
    foreach (getModuleCache('phreebooks', 'chart') as $glAccount) { if (!isset($glAccount['heading']) || !$glAccount['heading']) {
        $account_id = $glAccount['id'];
        for ($i = 0, $j = 1; $i < $max_period; $i++, $j++) {
            $record_found = dbGetValue(BIZUNO_DB_PREFIX.'journal_history', 'id', "gl_account='$account_id' AND period=$j");
            if (!$record_found) { $records[] = "('$account_id', '{$glAccount['type']}', '$j', NOW())"; }
        }
    } }
    if (sizeof($records) > 0) {
        dbGetResult("INSERT INTO ".BIZUNO_DB_PREFIX."journal_history (gl_account, gl_type, period, last_update) VALUES ".implode(",\n",$records));
    }
}

/**
 * this function creates the journal_history table records for a new GL account
 * @param string $glAcct - GL Account number
 * @param string $glType - GL Account type
 * @param integer $period - [Default: 1] Starting period
 */
function insertChartOfAccountsHistory($glAcct='', $glType='', $period=1)
{
    if (!$glAcct) { return msgAdd("Bad parameters sent to insertChartOfAccountsHistory()"); }
    $max_period = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', "MAX(period) AS period", '', false);
    $records = array();
    for ($i=$period; $i<=$max_period; $i++) {
        $record_found = dbGetValue(BIZUNO_DB_PREFIX.'journal_history', 'id', "gl_account='$glAcct' AND period=$i");
        if (!$record_found) { $records[] = "('$glAcct', '$glType', '$i', NOW())"; }
    }
    if (sizeof($records) > 0) {
        dbGetResult("INSERT INTO ".BIZUNO_DB_PREFIX."journal_history (gl_account, gl_type, period, last_update) VALUES ".implode(",\n",$records));
    }
}

function chartSales($jID, $range='c', $pieces=10, $reps=false)
{
    msgDebug("\nEntering chartSales with jID = $jID, range = $range, pieces = $pieces, and reps = $reps");
    switch ($jID) {
        default:
        case 12: $type='c'; $filter = "journal_id IN (12,13)";
    }
    $dates  = dbSqlDates($range);
    $filter.= " AND ".$dates['sql'];
    if ($reps==0) { // None by the select, so limit to current rep ID
        $filter.= " AND rep_id='".getUserCache('profile', 'userID', false, '0')."'";
    } elseif ($reps>0) { // Admin requesting Specific Rep
        $filter.= " AND rep_id='$reps'";
    } // else all sales
    if (getUserCache('profile', 'restrict_store') && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
        $filter.= " AND store_id=".getUserCache('profile', 'store_id');
    }
    $result = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, '', ['id','journal_id','total_amount','contact_id_b']);
    $totals = [];
    foreach ($result as $row) {
        if (!isset($totals[$row['contact_id_b']])) { $totals[$row['contact_id_b']] = 0; }
        $totals[$row['contact_id_b']] += $row['journal_id']==13 ? -$row['total_amount'] : $row['total_amount'];
    }
    arsort($totals);
    $cnt    = 1;
    $runTotal= $otherTotal = 0;
    $struc['chart'][]= $struc['data'][] = [lang('ctype_c'), lang('total')]; // headings
    msgDebug("\nFound total invoices count = ".sizeof($totals));
    foreach ($totals as $cID => $total) {
        $name = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name', "id=$cID");
        $runTotal       += $total;
        $struc['data'][] = [$name, $total];
        if ($cnt < $pieces-1) {
            if (defined('DEMO_MODE')) { $name = randomNames($type); }
            $struc['chart'][] = [$name, max($total, 0)];
        } else {
            $otherTotal += $total;
        }
        $cnt++;
    }
    $struc['chart'][]= [lang('other'), $otherTotal];
    $struc['data'][] = [lang('total'), $runTotal];
    $struc['count']  = $cnt;
    $struc['total']  = $runTotal;
    msgDebug("\nReturning output = ".print_r($struc, true));
    return $struc;
}

/**
 * Calculates the balance change of the contacts account since a certain date
 * @param integer $cID - Contact ID
 * @param char - Contact Type
 * @param date $bal_date - Date to get balance as of
 */
function pbGetBegBal($cID, $type='c', $bal_date=false)
{
    $balS  = $balP = 0;
    $jIDs  = $type=='v' ? '6,7'  : '12,13';
    $jIDp  = $type=='v' ? '17,20': '18,22';
    $sales = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "contact_id_b=$cID AND post_date>='$bal_date' AND journal_id IN ($jIDs)", '', ['journal_id', 'total_amount']);
    foreach ($sales as $sale) { $balS += in_array($sale['journal_id'], [7,13]) ? -$sale['total_amount'] : $sale['total_amount']; }
    $pmts  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "contact_id_b=$cID AND post_date>='$bal_date' AND journal_id IN ($jIDp)", '', ['journal_id', 'total_amount']);
    foreach ($pmts as $pmt) { $balP += in_array($pmt['journal_id'], [17,22]) ? -$pmt['total_amount'] : $pmt['total_amount']; }
    $output= $balS - $balP;
    msgDebug("\nReturning with adjustment to Current Balance = $output");
    return $output;
}
