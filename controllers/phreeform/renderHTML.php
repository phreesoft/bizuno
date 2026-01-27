<?php
/*
 * Renders a report in html format for screen display
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
 * @version    7.x Last Update: 2025-12-26
 * @filesource /controllers/phreeform/renderHTML.php
 */

namespace bizuno;

class HTML
{
    public $moduleID = 'phreeform';
    private $dataAligns = [];
    public $lang;
    public $defaultFont;
    public $FillColor;
    public $HdColor;
    public $ttlColor;
    public $fontHeading;
    public $fontTitle1;
    public $fontTitle2;
    public $fontFilter;
    public $fontData;
    public $output;
    public $tableHead;
    public $numColumns;

    function __construct($data, $report)
    {
        $this->lang       = getLang($this->moduleID);
        $this->defaultFont= getModuleCache('phreeform','settings','general','default_font','helvetica');
        $this->FillColor  = '#E0EBFF';
        $this->HdColor    = '#00BFFF';
        $this->ttlColor   = '#CCCCCC';
        // set some more deaults if not specified in $report
        if (!isset($report->filtercolor)){ $report->filtercolor = '0'; } // black
        if (!isset($report->filtersize)) { $report->filtersize  = '10'; }
        if (!isset($report->filteralign)){ $report->filteralign = 'L'; }
        if (!isset($report->data))       { $report->data = new \stdClass(); }
        if (!isset($report->datacolor))  { $report->datacolor   = '0'; } // black
        if (!isset($report->datasize))   { $report->datasize    = '10'; }
        if (!isset($report->dataalign))  { $report->dataalign   = 'L'; }
        $this->fontHeading= $report->headingfont== 'default' ? $this->defaultFont : $report->headingfont;
        $this->fontTitle1 = $report->title1font == 'default' ? $this->defaultFont : $report->title1font;
        $this->fontTitle2 = $report->title2font == 'default' ? $this->defaultFont : $report->title2font;
        $this->fontFilter = $report->filterfont == 'default' ? $this->defaultFont : $report->filterfont;
        $this->fontData   = $report->datafont   == 'default' ? $this->defaultFont : $report->datafont;
        $this->output     = '<table width="95%">';
        $this->addHeading($report);
        $this->addTableHead($report);
        $this->addTable($data, $report);
        $this->output    .= "</table>";
    }

    /**
     * Creates and adds a heading to the HTML report
     * @param object $report - report structure
     */
    private function addHeading($report)
    {
        $this->tableHead = [];
        $data = NULL;
        $align="C";
        foreach ($report->fieldlist->rows as $value) {
            $this->dataAligns[] = !empty($value->align)?$value->align : 'L';
            if (isset($value->visible) && $value->visible) {
                $data .= !empty($value->title) ? $value->title : '';
                if (isset($value->columnbreak) && $value->columnbreak) {
                    $data .= '<br />';
                    continue;
                }
                $this->tableHead[] = ['align' => $align, 'value' => $data];
                $data = NULL;
            }
        }
        if ($data !== NULL) { $this->tableHead[] = ['align'=>$align, 'value'=>$data]; }
        $this->numColumns = sizeof($this->tableHead);
        $rStyle = '';
        if (!empty($report->headingshow)) { // Show the company name
            $color  = convertHex($report->headingcolor);
            $dStyle = 'style="font-family:'.$this->fontHeading.'; color:'.$color.'; font-size:'.$report->headingsize.'pt; font-weight:bold;"';
            $this->writeRow([['align' => $report->headingalign, 'value' => getModuleCache('bizuno', 'settings', 'company', 'primary_name')]], $rStyle, $dStyle, $heading=true);
        }
        if (!empty($report->title1show)) { // Set title 1 heading
            $color  = convertHex($report->title1color);
            $dStyle = 'style="font-family:'.$this->fontTitle1.'; color:'.$color.'; font-size:'.$report->title1size.'pt;"';
            $this->writeRow([['align' => $report->title1align, 'value' => TextReplace($report->title1text)]], $rStyle, $dStyle, $heading=true);
        }
        if (!empty($report->title2show)) { // Set Title 2 heading
            $color  = convertHex($report->title2color);
            $dStyle = 'style="font-family:'.$this->fontTitle2.'; color:'.$color.'; font-size:'.$report->title2size.'pt;"';
            $this->writeRow([['align' => $report->title2align, 'value' => TextReplace($report->title2text)]], $rStyle, $dStyle, $heading=true);
        }
        $color  = convertHex($report->filtercolor);
        $dStyle = 'style="font-family:'.$this->fontFilter.'; color:'.$color.'; font-size:'.$report->filtersize.'pt;"';
        $this->writeRow([['align' => $report->filteralign, 'value' => TextReplace($report->filtertext)]], $rStyle, $dStyle, $heading=true);
    }

    /**
     * Sets the table header
     * @param object $report - report structure
     */
    private function addTableHead($report)
    {
        $color  = convertHex($report->datacolor);
        $rStyle = 'style="background-color:'.$this->HdColor.'"';
        $dStyle = 'style="font-family:'.$this->fontData.'; color:'.$color.'; font-size:'.$report->datasize.'pt;"';
        $this->writeRow($this->tableHead, $rStyle, $dStyle);
    }

    /**
     * Fill in all the data lines and add pages as needed
     * @param array $data - report data from the SQL
     * @param object $report - Report structure
     * @return null - data is added to TCPDF output file
     */
    private function addTable($data, $report)
    {
        if (!is_array($data)) {
            $this->output .= "<tr><td>".lang('phreeform_output_none')."</td></tr>";
            $this->output .= '</table>';
            return;
        }
        $color0 = convertHex($this->FillColor);
        $bgStyle= 'style="background-color:'.$color0.'"';
        $color  = str_replace(':', '', $report->datacolor);
        $dStyle = 'style="font-family:'.$this->fontData.';color:'.$color.';font-size:'.$report->datasize.'pt;"';
        // Ready to draw the column data
        $rowCnt = 0;
        $showHd = false;
        $fill   = false;
        foreach ($data as $myrow) {
            $Action = array_shift($myrow);
            $todo = explode(':', $Action, 2); // contains a letter of the date type and title/group_id
            if (!isset($todo[1])) { $todo[1] = ''; }
            switch ($todo[0]) {
                case "h": // Heading
                    $this->writeRow([['align'=>$report->dataalign, 'value'=>$todo[1]]], '', $dStyle);
                    break;
                case "r": // Report Total
                case "g": // Group Total
                    $Desc  = ($todo[0] == 'g') ? $this->lang['group_total'] : $this->lang['report_total'];
                    $rStyle = 'style="background-color:'.$this->ttlColor.'"';
                    $this->writeRow([['align' => 'C', 'value' => $Desc.' '.$todo[1]]], $rStyle, $dStyle, true);
                    if ($rowCnt > 25) { $showHd = true; $rowCnt = 0; }
                    // now fall into the 'd' case to show the data
                    $fill = false;
                case "d": // data element
                default:
                    $temp = [];
                    $data = NULL;
                    msgDebug("\nworking on myrow = ".print_r($myrow, true));
                    foreach ($myrow as $key => $value) {
                        $data .= ($value);
                        $temp[] = ['align'=>!empty($this->dataAligns[$key]) ? $this->dataAligns[$key] : 'C', 'value'=>$data];
                        $data = NULL;
                    }
                    if ($data !== NULL) { // catches not checked column break at end of row
                        $temp[] = ['align'=>'', 'value'=>$data];
                    }
                    $rStyle = $fill ? $bgStyle : ($todo[0]=='r' || $todo[0]=='g' ? 'style="background-color:'.$this->ttlColor.'"' : '');
                    $this->writeRow($temp, $rStyle, $dStyle);
                    if ($rowCnt > 40) { $showHd = true; $rowCnt = 0; } // for long lists or lists without group
                    if ($showHd) { $this->addTableHead($report); $showHd = false; }
                    break;
            }
            $fill = !$fill;
            $rowCnt++;
        }
    }

    /**
     * Adds a row the the HTML output string
     * @param array $aData - data to write on form page
     * @param string $rStyle - [default ''] style to add to the HTML tr element, if any
     * @param type $dStyle - [default ''] style to add to the HTLM td element, if any
     * @param boolean $heading - [default false] set to true if this row is a heading row.
     */
    private function writeRow($aData, $rStyle='', $dStyle='', $heading=false)
    {
        $output  = "  <tr";
        $output .= (!$rStyle ? '' : ' '.$rStyle).">";
        foreach ($aData as $value) {
            $params = NULL;
            if ($heading) { $params .= ' colspan="'.$this->numColumns.'"'; }
            $output .= '    <td';
            switch ($value['align']) {
                case 'C': $params .= ' align="center"'; break;
                case 'R': $params .= ' align="right"';  break;
                default:
                case 'L':
            }
            $output .= $params . (!$dStyle ? '' : ' '.$dStyle).'>';
            $html = str_replace("\n", '<br />', htmlspecialchars($value['value']));
            $output .= ($value['value'] == '') ? '&nbsp;' : $html;
            $output .= '</td>';
        }
        $output .= '  </tr>';
        $this->output .= $output;
    }
}
