<?php
/*
 * Support functions for PhreeForm
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
 * @version    7.x Last Update: 2026-03-14
 * @filesource /controllers/phreeform/functions.php
 */

namespace bizuno;

/**
 * used to build drop downs for filtering
 * @return type
 */
function viewDateChoices()
{
    return [
        ['id'=>'a', 'text'=>lang('all')],
        ['id'=>'b', 'text'=>lang('range')],
        ['id'=>'c', 'text'=>lang('today')],
        ['id'=>'d', 'text'=>lang('dates_this_week')],
        ['id'=>'e', 'text'=>lang('dates_wtd')],
        ['id'=>'l', 'text'=>lang('dates_this_period')],
        ['id'=>'f', 'text'=>lang('dates_month')],
        ['id'=>'g', 'text'=>lang('dates_mtd')],
        ['id'=>'h', 'text'=>lang('dates_quarter')],
        ['id'=>'i', 'text'=>lang('dates_qtd')],
        ['id'=>'j', 'text'=>lang('dates_this_year')],
        ['id'=>'k', 'text'=>lang('dates_ytd')],
        ['id'=>'m', 'text'=>lang('dates_lfy')],
        ['id'=>'n', 'text'=>lang('dates_lfytd')]];
}
/**
 * This function adds a separator to a string for formatting addresses, etc. These are the default separators
 * @param mixed $value - value to add separator to
 * @param string $Process - Process to apply
 * @return mixed result after processing
 */
function viewSeparator($value, $Process)
{
    switch ($Process) {
        case "sp":     return "$value ";
        case "2sp":    return "$value  ";
        case "comma":  return "$value,";
        case "com-sp": return "$value, ";
        case "dash-sp":return "$value - ";
        case "sep-sp": return "$value | ";
        case "nl":     return "$value\n";
        case "semi-sp":return "$value; ";
        case "del-nl": return $value=='' ? '' : "$value\n";
    }
    return $value;
}

/**
 * Replaces template fields with working data
 * @global object $report - current working report
 * @param string $text_string - string to apply substitution
 * @return string - modified string
 */
function TextReplace($text_string='', $xKeys=[], $xVals=[])
{
    global $report;
    $keys   = array_merge(['%date%', '%reportname%', '%company%'], $xKeys);
    $values = array_merge([viewDate(biz_date('Y-m-d')), $report->title, getModuleCache('bizuno', 'settings', 'company', 'primary_name')], $xVals);
    return str_replace($keys, $values, $text_string); // if getting error here, probably $report->title is an array, problem converting
}

function getGroupTitle($group='') 
{
    $meta = getMetaCommon('phreeform_cache');
    foreach ($meta as $row) {
        if ($row['group_id']==$group && $row['mime_type']=='dir') { return lang($row['title']); }
    }
    return lang('group');
}

/**
 * Imports a report into the phreeform table
 * @param string $RptName - new title of the imported report
 * @param string $RptFileName - filename of the imported report, if specified by the user
 * @param string $import_path - where to find the report in the file system
 * @param boolean $verbose - [default true] whether to display a message if there is an error
 * @param boolean $replace - [default false] whether to replace a report if the name is the same
 * @return array - record ID and report title
 */
function phreeformImport($RptName='', $RptFileName='', $import_path='', $verbose=true, $replace=false)
{
    global $io;
    msgDebug("\nEntering phreeformImport with RptName = $RptName and RptFileName = $RptFileName and path = $import_path");
    if ($RptFileName <> '') { // then a locally stored report was chosen
        if (strtolower(substr($RptFileName, -5)) <> '.json') { msgDebug("\nLocal stored, Invalid Extension"); return; }
        $path = $import_path . $RptFileName;
    } else if ($io->validateUpload('fileUpload', 'json', 'json', false)) {
        $path = $_FILES['fileUpload']['tmp_name'];
    } else {
        if ($verbose) { msgAdd(lang('err_phreeform_import')); }
        return;
    }
    if (!$contents = file_get_contents($path))  { msgDebug("\nFile had no contents, bailing!"); return; }
    // report is now json
    if (!$report = json_decode($contents, true)) { msgDebug("\nReport faile the JSON decode, must not have been encoded properly!"); return; }
    msgDebug("\nRead report = ".print_r($report, true));
    if ($RptName <> '') { $report['title'] = $RptName; } // replace the title if provided
    // error check - check for duplicate titles in the same folder and get the parent ID while we're here
    $rows=dbMetaGet('%', 'phreeform');
    $rID = $parentID = $defParent = 0;
    $cats= [];
    foreach ($rows as $row) {
        if (empty($row['group_id'])) { msgDebug("\nNo group ID category, skipping!"); continue; }
        $cats[$row['group_id']] = $row['_rID'];
        if ($row['title']==$report['title']) { $rID = $row['_rID']; }
        if ($row['mime_type']=='dir' && $row['group_id']=='misc'      && $report['mime_type']=='rpt') { $defParent = $row['_rID']; }
        if ($row['mime_type']=='dir' && $row['group_id']=='misc:misc' && $report['mime_type']=='frm') { $defParent = $row['_rID']; }
        if ($row['mime_type']=='dir' && $row['group_id']==$report['group_id']) { $parentID = $row['_rID']; }
    }
    if (!empty($rID) && !$replace) { // the report name already exists
        if ($verbose) { msgAdd(sprintf(lang('err_phreeform_title_dup'), $report['title'])); }
        return;
    }
    // Set the parent ID
    $report['parent_id'] = !empty($parentID) ? $parentID : $defParent;
    msgDebug("\nRead to write new report with added parent: ".print_r($report, true));
    dbMetaSet($rID, 'phreeform', $report);
    return ['title'=>$report['title']];
}

/**
 * Formats the output for a serial form (receipt)
 * @param string $value - encoded input to format
 * @param integer $width - width of a line of the receipt tape
 * @param char $align - alignment of the line
 * @param string $base_string - starting string before formatting
 * @param boolean $keep_nl - [default false] set to true to retain NL characters
 * @return string - formatted string
 */
function formatReceipt($value, $width = 15, $align = 'z', $base_string = '', $keep_nl = false)
{
    $temp   = explode("\n", $value);
    $output = NULL;
    foreach ($temp as $key => $value) {
        if ($key > 0) { $output .= "\n"; } // keep the new line chars
        switch ($align) {
            case 'L':
                if (strlen($base_string)) { $output .= $value . substr($base_string, $width - strlen($value)); }
                else { $output .= str_pad($value, $width, ' ', STR_PAD_RIGHT); }
                break;
            case 'R':
                if (strlen($base_string)) { $output .= substr($base_string, 0, $width - strlen($value)) . $value; }
                else { $output .= str_pad($value, $width, ' ', STR_PAD_LEFT); }
                break;
            case 'C':
                if (strlen($base_string)) {
                    $pad = (($width - strlen($value)) / 2);
                    $output .= substr($base_string, 0, floor($pad)) . $value . substr($base_string, -ceil($pad));
                } else {
                    $num_blanks = (($width - strlen($value)) / 2) + strlen($value);
                    $value   = str_pad($value, intval($num_blanks), ' ', STR_PAD_LEFT);
                    $output .= str_pad($value, $width,              ' ', STR_PAD_RIGHT);
                }
                break;
        }
    }
    return $output;
}

/**
 * This function formats the tables to a valid sql string
 * @param object $report - working report object
 * @return modified $report
 */
function sqlTable(&$report)
{
    $sqlTable = '';
    foreach ($report->tables->rows as $table) {
        if (!empty($table->relationship)) {
            if (!isset($table->joinopt)) { $table->joinopt = ' JOIN '; }
            $sqlTable .= " $table->joinopt ".BIZUNO_DB_PREFIX."$table->tablename ON ".prefixTables($table->relationship);
        } else {
            $sqlTable .= BIZUNO_DB_PREFIX.$table->tablename;
        }
    }
    $report->sqlTable = $sqlTable;
}

/**
 * This function formats the sort fields and returns the sql string, null string if no sort values
 * @param object $report - working report object
 * @return modified $report
 */
function sqlSort(&$report)
{
    $strSort = [];
    if (isset($report->sortlist->rows) && is_array($report->sortlist->rows)) { 
        foreach ($report->sortlist->rows as $sortline) {
            if (!empty($sortline->default) && $sortline->default == '1') { 
                $strSort[] = prefixTables($sortline->fieldname);
            }
        }
    }
    $report->sqlSort = implode(', ', $strSort);
}

/**
 * This function formats the filter fields and returns the sql string, null string if no filter values
 * @param object $report - working report object
 * @return modified $report
 */
function sqlFilter(&$report)
{
    $strCrit = [];
    $report->sqlCritDesc = '';
    if (!isset($report->datefield)) { $report->datefield = ''; }
    if (isset($report->datedefault)) {
        msgDebug("\nWorking with datedefault = $report->datedefault");
        $dates = dbSqlDates($report->datedefault, prefixTables($report->datefield));
        if ($dates['sql']) { $strCrit[] = $dates['sql']; }
        $report->sqlCritDesc.= $dates['description'];
        $report->datedefault = substr($report->datedefault, 0, 1).":{$dates['start_date']}:{$dates['end_date']}";
    }
    $criteria = phreeformCriteria($report);
    if (!empty($report->restrict_rep)) { sqlRestrictRep($strCrit, $report); }
    if ($criteria['sql'])         { $strCrit[] = $criteria['sql']; }
    if ($criteria['description']) { $report->sqlCritDesc .= lang('settings').": {$criteria['description']}; "; }
    $report->sqlCrit = implode(' AND ', $strCrit);
}

function sqlRestrictRep(&$strCrit, $report) {
    $cID = getUserCache('profile', 'userID', false, 0);
    if (empty($cID)) { return; }
    if (empty(getUserCache('profile', 'restrict_user', false, 0))) { return; }
    foreach ($report->tables->rows as $table) {
        if ('contacts'    ==$table->tablename) { $strCrit[] = "contacts.id=$cID";         return;}
        if ('journal_main'==$table->tablename) { $strCrit[] = "journal_main.rep_id=$cID"; return; }
    }
}

/**
 * Strips the table name from the report table value, if present
 * @param string $value - full string
 * @return string - table field name without the table. prefix
 */
function stripTablename($value)
{
    return (strpos($value, '.') !== false) ? substr($value, strpos($value, '.') + 1) : $value;
}

/*
 * This function add the BIZUNO_DB_PREFIX in front of the database tables
 * @param $field -
 * @return $field - with table prefixes added
 */
function prefixTables($field)
{
    global $report;
    foreach ($report->tables->rows as $table) { $field = str_replace($table->tablename.'.', BIZUNO_DB_PREFIX.$table->tablename.'.', $field); }
    return stripslashes($field);
}

/**
 * Tests for the existence of tables in variables (for formulas)
 * @param string $field- data to test
 * @param object $report - working report object
 * @return boolean - true if found, false otherwise
 */
function testTables($field, $report)
{
      $result = false;
      foreach ($report->tables->rows as $table) {
        if (strpos($field, $table->tablename.'.') !== false) { $result = true; }
      }
      return $result;
}

/**
 * Builds the back part of a SQL statement
 * @global array $critChoices - working criteria choices
 * @param object $report - current report/form
 * @param boolean $xOnly
 * @return array - sql string and description string
 */
function phreeformCriteria($report, $xOnly=false)
{
    global $critChoices;
    $strCrit   = $filCrit = '';
    $crit_prefs= !$xOnly && isset($report->filterlist->rows) ? $report->filterlist->rows : [];
    if (isset($report->xfilterlist)) { $crit_prefs[]= $report->xfilterlist; }
    msgDebug("\nEntering phreeformCriteria with critchoices = ".print_r($critChoices, true));
    foreach ($crit_prefs as $settings) {
        msgDebug("\nSettings = ".print_r($settings, true));
        $sign = '';
        if (empty($settings->fieldname)) { continue; } // blank row
        if (!isset($settings->default)) { // if no selection was passed, assume it's the first on the list for that selection menu
            $temp = explode(':', $critChoices[$settings->type]);
            $settings->default = sizeof($temp) > 1 ? $temp[1] : '';
        }
        $sc = '';
        $fc = '';
        switch ($settings->default) {
            case 'range':
                if (!empty($settings->min)) { // a from value entered, check
                    $sc .= prefixTables($settings->fieldname).">='$settings->min'";
                    $fc .= $settings->title." >= ".$settings->min;
                }
                if (!empty($settings->max)) { // a to value entered, check
                    if (strlen($sc)>0) { $sc .= ' AND '; $fc .= ' '.lang('and').' '; }
                    $sc .= prefixTables($settings->fieldname)."<='$settings->max'";
                    $fc .= $settings->title." <= ".$settings->max;
                }
                break;
            case 'yes':
            case 'true':
            case 'inactive':
            case 'printed':
                $sc .= prefixTables($settings->fieldname)."='1'";
                $fc .= $settings->title."=$settings->default";
                break;
            case 'no':
            case 'false':
            case 'active':
            case 'unprinted':
                $sc .= prefixTables($settings->fieldname)."='0'";
                $fc .= $settings->title."=$settings->default";
                break;
            case 'equal':        $sign = " = "; // then continue
            case 'not_equal':    if (empty($sign)) { $sign = " <> "; }
                if (!empty($settings->min) && 'null'==strtolower($settings->min)) { $settings->min = ""; }  // special case for compare to null, then continue
            case 'greater_than': if (empty($sign)) { $sign = " > "; } // then continue
            case 'less_than':    if (empty($sign)) { $sign = " < "; }
                if (isset($settings->min)) { // a from value entered, check
                    $q_field = testTables($settings->min, $report) ? prefixTables($settings->min) : "'".prefixTables($settings->min)."'";
                    $sc .= prefixTables($settings->fieldname).$sign.$q_field;
                    $fc .= (isset($settings->title) ? $settings->title : $settings->fieldname).$sign.$settings->min;
                }
                break;
            case 'in_list':
                if (isset($settings->min)) { // a from value entered, check
                    $csv_values = explode(',', $settings->min);
                    for ($i = 0; $i < sizeof($csv_values); $i++) { $csv_values[$i] = trim($csv_values[$i]); }
                    $sc .= prefixTables($settings->fieldname)." IN ('".implode("','", $csv_values)."')";
                    $fc .= isset($settings->title) ? "$settings->title IN ($settings->min)" : '';
                }
                break;
            case 'all': // sql default anyway
            default:
        }
        if ($sc) {
            if (strlen($strCrit) > 0) {
                $strCrit .= ' AND ';
                if (isset($settings->visible)) { $filCrit .= ' '.lang('and').' '; }
            }
            $strCrit .= $sc;
            if (isset($settings->visible)) { $filCrit .= $fc; }
        }
    }
    return ['sql' => $strCrit, 'description' => $filCrit];
}

  /**
   * Reports Only - Executes the SQL statement and puts results into encoded array to render
   * @global object $report - working report object
   * @param string $sql - Contains the full SQL statement to retrieve data from
   * @param object $report - working report object
   * @return array - results of SQL database query formatted and ready to render
   */
function BuildDataArray($sql, $report)
{
    global $report, $currencies;
    msgDebug("\nEntering BuildDataArray");
    // See if we need to group, fetch the group fieldname
    $GrpFieldName       = '';
    $GrpFieldProcessing = '';
    $ShowTotals         = false;
    if (isset($report->grouplist->rows) && is_array($report->grouplist->rows)) { foreach ($report->grouplist->rows as $key => $value) {
        if ($report->grouplist->rows[$key]->default) {
            $GrpFieldName       = $value->fieldname;
            $GrpFieldProcessing = !empty($value->processing) ? $value->processing : '';
            $GrpFieldFormatting = !empty($value->formatting) ? $value->formatting : '';
        }
    } }
    // Build the sequence map of retrieved fields, order is as user wants it
    $heading = $seq = [];
    $i       = 0;
    $GrpField= '';
    foreach ($report->fieldlist->rows as $fields) {
        if (empty($fields->visible)) { continue; }
        if ($fields->fieldname == $GrpFieldName) { $GrpField = 'c'.$i; }
        $heading[$i]= !empty($fields->title) ? $fields->title : '';
        $seq[$i]    = [
            'dbField'   => $fields->fieldname,
            'break'     => isset($fields->columnbreak)? $fields->columnbreak : '1',
            'fieldname' => 'c'.$i,
            'total'     => !empty($fields->total)     ? $fields->total     : 0,
            'processing'=> !empty($fields->processing)? $fields->processing: '',
            'formatting'=> !empty($fields->formatting)? $fields->formatting: '',
            'align'     => !empty($fields->align)     ? $fields->align     : 'L',
            'grptotal'  => 0,
            'rpttotal'  => 0];
        $i++;
    }
    if (!empty($report->special_class)) {
        msgDebug("\nIn BuildDataArray calling special class");
        $fqcn = "\\bizuno\\$report->special_class";
        $sp_report = new $fqcn($report);
        return $sp_report->load_report_data($report, $seq, $sql, $GrpField); // the special report formats all of the data, we're done
    }
    if (!$stmt = dbGetResult($sql)) { return msgAdd("Problem reading from the database!"); }
    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    if (sizeof($result) == 0) { return msgAdd(lang('phreeform_output_none'), 'caution'); }
    msgDebug("\nreturned number of rows = ".sizeof($result));
    msgDebug("\nProcessing control sequence = ".print_r($seq, true));
    // Generate the output data array
    if (!isset($report->totalonly)) { $report->totalonly = '0'; }
    $RowCnt     = 0;
    $ColCnt     = 1;
    $GrpWorking = false;
    $OutputArray= [];
    foreach ($result as $myrow) { // Check to see if a total row needs to be displayed
        $currencies->iso = !empty($report->iso) ? $report->iso : getDefaultCurrency(); // force iso if requested else set default
        unset($currencies->rate); // reset forces load at viewFormat to current rate
        $GLOBALS['currentRow'] = $myrow; // save the current row for processing
        $report->currentValues = false; // reset the stored processing values to save sql's
        if (isset($GrpField) && $GrpField) { // we're checking for group totals, see if this group is complete
            if (($myrow[$GrpField] <> $GrpWorking) && $GrpWorking !== false) { // it's a new group so print totals
                $gTmp = !empty($GrpFieldProcessing) ? viewProcess($GrpWorking, $GrpFieldProcessing) : $GrpWorking;
                $OutputArray[$RowCnt][0] = 'g:'.viewFormat($gTmp, $GrpFieldFormatting);
                foreach ($seq as $offset => $TotalCtl) {
                    // NOTE: Do not process here as this is just a total and the processing was used to get here, just display the total.
                    $OutputArray[$RowCnt][$offset+1] = $TotalCtl['total'] ? viewFormat($TotalCtl['grptotal'], $TotalCtl['formatting']) : ($report->totalonly ? $TotalCtl['grptotal'] : ' ');
                    $seq[$offset]['grptotal'] = 0; // reset the total
                }
                $RowCnt++; // go to next row
            }
            $GrpWorking = $myrow[$GrpField]; // set to new grouping value
        }
        if (!empty($myrow['currency']))     { $currencies->iso = $myrow['currency']; }
        if (!empty($myrow['currency_rate'])){ $currencies->rate= $myrow['currency_rate']; }
        foreach ($seq as $key => $TableCtl) {
            $processedData = !empty($TableCtl['processing']) ? viewProcess($myrow[$TableCtl['fieldname']], $TableCtl['processing']) : $myrow[$TableCtl['fieldname']];
            if (empty($report->totalonly)) { // insert data into output array and set to next column
                $OutputArray[$RowCnt][0] = 'd'; // let the display class know its a data element
                $OutputArray[$RowCnt][$ColCnt] = viewFormat($processedData, $TableCtl['formatting']);
            }
            $ColCnt++;
            if (!empty($TableCtl['total'])) { // add to the running total if need be
                $seq[$key]['grptotal'] += floatval($processedData);
                $seq[$key]['rpttotal'] += floatval($processedData);
                $ShowTotals = true;
            } else {
                $seq[$key]['grptotal']  = $report->totalonly ? $processedData : ' '; // set to last value for summary reports to show something
            }
        }
        $RowCnt++;
        $ColCnt = 1;
    }
    $currencies->iso = !empty($report->iso) ? $report->iso : getDefaultCurrency(); // force iso if requested else set default
    unset($currencies->rate); // reset forces load at viewFormat to current rate
    if ($GrpWorking !== false) { // if we collected group data show the final group total
        $gTmp = !empty($GrpFieldProcessing) ? viewProcess($GrpWorking, $GrpFieldProcessing) : $GrpWorking;
        $OutputArray[$RowCnt][0] = 'g:'.viewFormat($gTmp, $GrpFieldFormatting);
        foreach ($seq as $TotalCtl) {
            // NOTE: Do not process here as this is just a total and the processing was used to get here, just format the total.
            $OutputArray[$RowCnt][$ColCnt] = $TotalCtl['total'] ? viewFormat($TotalCtl['grptotal'], $TotalCtl['formatting']) : ' ';
            $ColCnt++;
        }
        $RowCnt++;
        $ColCnt = 1;
    }
    if ($ShowTotals) { // report total
        $OutputArray[$RowCnt][0] = 'r:' . $report->title;
        foreach ($seq as $TotalCtl) {
            // NOTE: Do not process here as this is just a total and the processing was used to get here, just display the total.
            $OutputArray[$RowCnt][$ColCnt] = $TotalCtl['total'] ? viewFormat($TotalCtl['rpttotal'], $TotalCtl['formatting']) : ' ';
            $ColCnt++;
        }
    }
    msgDebug("\nFirst 10 rows of output array = ".print_r(array_slice($OutputArray, 0, 10), true));
    return $OutputArray;
}

/**
 * Replaces non-allowed characters with underscore to avoid db failures
 * @param string $string - raw data coming in
 * @return string - $string with problematic characters replaced
 */
function ReplaceNonAllowedCharacters($string)
{
    return str_replace(['"', ' ', '&', "'"], "_", $string);
}

/**
 * Creates a list of page types for a report/form, supported by PDF
 * @param array $lang - language translations for textual titles
 * @return array - list of page sizes
 */
function phreeformPages()
{ // TBD make the key just the PDF supported paper sizes, pull dimensions from array
    return [
        ['id'=>'LETTER:216:279', 'text'=>lang('paper_letter', 'phreeform')],
        ['id'=>'LEGAL:216:357',  'text'=>lang('paper_legal', 'phreeform')],
        ['id'=>'A3:297:420',     'text'=>'A3'],
        ['id'=>'A4:210:297',     'text'=>'A4'],
        ['id'=>'A5:148:210',     'text'=>'A5'],
        ['id'=>'A0:841x1189',    'text'=>'A0'],
        ['id'=>'A1:594:841',     'text'=>'A1'],
        ['id'=>'A2:420:594',     'text'=>'A2'],
        ['id'=>'A6:105:148',     'text'=>'A6'],
        ['id'=>'A7:74:105',      'text'=>'A7'],
        ['id'=>'A8:52:74',       'text'=>'A8'],
        ['id'=>'A9:37:52',       'text'=>'A9'],
        ['id'=>'TABLOID:279:432','text'=>lang('paper_tabloid', 'phreeform')]];
}

/**
 * Generates the list of page orientations, ready for select DOM element
 * @param array $lang - Locale language file
 * @return array - ready to render
 */
function phreeformOrientation()
{
    $output = [
        ['id'=>'P', 'text'=>lang('orient_portrait', 'phreeform')],
        ['id'=>'L', 'text'=>lang('orient_landscape', 'phreeform')]];
    return $output;
}

/**
 * Generates the list of PDF fonts available, typically from the default installation
 * @param boolean $show_default - [default true] set to false to include the null select option at the beginning
 * @return array - ready to render in select DOM element
 */
function phreeformFonts($show_default=true)
{
    $choices = glob(BIZUNO_3P_PDF."fonts/*.php");
    $output = $show_default ? [['id'=>'default', 'text'=>lang('default')]] : [];
    foreach ($choices as $choice) {
        $name = false;
        include($choice); // will set $name if it's a valid file
        if ($name) { $output[] = ['id'=>basename($choice, ".php"), 'text'=>$name]; }
    }
    return $output;
}

/**
 * Converts a RGB color to hexadecimal format (hash needed for -color widget)
 * @param string $value - value to convert
 * @return string - converted $value
 */
function convertHex($value)
{
    if (strpos($value, '#') === 0) { return $value; } // already in hex, strip the hash
    $colors = explode(':', $value);
    $output = NULL;
    foreach ($colors as $decimal) { $output .= str_pad(dechex($decimal), 2, "0", STR_PAD_LEFT); }
    return '#'.$output;
}

/**
 * Generates the list of font sizes available to use in a report/form
 * @return array - List ready to render
 */
function phreeformSizes()
{
    return [
        ['id'=> '8', 'text'=> '8'],
        ['id'=> '9', 'text'=> '9'],
        ['id'=>'10', 'text'=>'10'],
        ['id'=>'11', 'text'=>'11'],
        ['id'=>'12', 'text'=>'12'],
        ['id'=>'14', 'text'=>'14'],
        ['id'=>'16', 'text'=>'16'],
        ['id'=>'18', 'text'=>'18'],
        ['id'=>'20', 'text'=>'20'],
        ['id'=>'24', 'text'=>'24']];
}

/**
 * Generates the list of alignments available, choices are left, center, and right
 * @return array - list ready to render
 */
function phreeformAligns()
{
    return [
        ['id'=>'L', 'text'=>lang('left')],
        ['id'=>'R', 'text'=>lang('right')],
        ['id'=>'C', 'text'=>lang('center')]];
}

/**
 * Generates the list of company data from the cache, data is sourced in Settings -> Bizuno -> General
 * @return array - list ready to render
 */
function phreeformCompany()
{
    $output = [];
    $company = array_keys(getModuleCache('bizuno', 'settings', 'company'));
    foreach ($company as $key) { $output[] = ['id'=>$key, 'text'=>lang($key)]; }
    return $output;
}

/**
 * Generates the list of bar code types supported by PDF used in the fields of a form
 * @return array - ready to render
 */
function phreeformBarCodes()
{
    return [
        ['id'=>'C39',     'text'=>'CODE 39 - ANSI MH10.8M-1983 - USD-3 - 3 of 9.'],
        ['id'=>'C39+',    'text'=>'CODE 39 with checksum'],
        ['id'=>'C39E',    'text'=>'CODE 39 EXTENDED'],
        ['id'=>'C39E+',   'text'=>'CODE 39 EXTENDED + CHECKSUM'],
        ['id'=>'C93',     'text'=>'CODE 93 - USS-93'],
        ['id'=>'S25',     'text'=>'Standard 2 of 5'],
        ['id'=>'S25+',    'text'=>'Standard 2 of 5 + CHECKSUM'],
        ['id'=>'I25',     'text'=>'Interleaved 2 of 5'],
        ['id'=>'I25+',    'text'=>'Interleaved 2 of 5 + CHECKSUM'],
        ['id'=>'C128',    'text'=>'CODE 128'],
        ['id'=>'C128A',   'text'=>'CODE 128 A'],
        ['id'=>'C128B',   'text'=>'CODE 128 B'],
        ['id'=>'C128C',   'text'=>'CODE 128 C'],
        ['id'=>'EAN2',    'text'=>'2-Digits UPC-Based Extention'],
        ['id'=>'EAN5',    'text'=>'5-Digits UPC-Based Extention'],
        ['id'=>'EAN8',    'text'=>'EAN 8'],
        ['id'=>'EAN13',   'text'=>'EAN 13'],
        ['id'=>'UPCA',    'text'=>'UPC-A'],
        ['id'=>'UPCE',    'text'=>'UPC-E'],
        ['id'=>'MSI',     'text'=>'MSI (Variation of Plessey code)'],
        ['id'=>'MSI+',    'text'=>'MSI + CHECKSUM (modulo 11)'],
        ['id'=>'POSTNET', 'text'=>'POSTNET'],
        ['id'=>'PLANET',  'text'=>'PLANET'],
        ['id'=>'RMS4CC',  'text'=>'RMS4CC (Royal Mail 4-state Customer Code) - CBC (Customer Bar Code)'],
        ['id'=>'KIX',     'text'=>'KIX (Klant index - Customer index)'],
        ['id'=>'IMB',     'text'=>'Intelligent Mail Barcode - Onecode - USPS-B-3200'],
        ['id'=>'CODABAR', 'text'=>'CODABAR'],
        ['id'=>'CODE11',  'text'=>'CODE 11'],
        ['id'=>'PHARMA',  'text'=>'PHARMACODE'],
        ['id'=>'PHARMA2T','text'=>'PHARMACODE TWO-TRACKS']];
}

/**
 * Generates a list of separators pulled from the cache, built at initialization
 * @return array - ready to render
 */
function phreeformSeparators()
{
    $output = [['id'=> '', 'text'=> lang('none')]];
    foreach (getModuleCache('phreeform', 'separators') as $key => $value) { $output[] = ['id'=> $key, 'text'=>$value['text']]; }
    return $output;
}

/**
 * Generates the list for the pull down to process report data, cache is loaded at initialization
 * @return array of choices for pull down
 */
function pfSelProcessing()
{
    $output = [['id'=> '', 'text'=> lang('none')]];
    foreach (getModuleCache('phreeform', 'processing') as $key => $value) {
        $output[] = ['id'=> $key, 'text'=>$value['text'], 'group'=>$value['group']];
    }
    return $output;
}

/**
 * Generates the list for the pull down to format report data from the cache
 * @return array of choices for pull down
 */
function phreeformFormatting()
{
    $output = [['id'=>'', 'text'=>lang('none')]];
    foreach (getModuleCache('phreeform', 'formatting') as $key => $value) {
        $output[] = ['id'=> $key, 'text'=>$value['text'], 'group'=>isset($value['group']) ? $value['group'] : ''];
    }
    return $output;
}
