<?php
/*
 * Render functions for forms in PDF format
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
 * @version    7.x Last Update: 2026-06-03 (bizHTMLCell: normalize <p>/<div> to line breaks + strip unsupported tags so "<p>..." descriptions aren't dropped; constrain Write() word-wrap to the cell column via a temp right margin and leave the cursor at content bottom so wrapped/multi-line cells wrap correctly and expand the row)
 * @filesource /controllers/phreeform/renderForm.php
 */

namespace bizuno;

use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * PhreeForm form-render PDF class.
 *
 * Extends tFPDF directly (not the FPDI/Tfpdf adapter) — form rendering doesn't need
 * page-import capability; that's only used by phreeformOutput in render.php for stitching
 * attachments. Keeping the base class shallow avoids pulling FPDI traits into every page
 * draw operation.
 */
class PDF extends \tFPDF
{
    public $moduleID  = 'phreeform';
    var $defaultSize  = "10";
    var $defaultColor = "#000000";
    var $defaultAlign = "L";
    var $y0;             // current y position
    var $x0;             // current x position
    var $pageY;          // y value of bottom of page less bottom margin
    var $PageCnt;        // tracks the page count for correct page numbering for multipage and multiform printouts
    var $NewPageGroup;   // variable indicating whether a new group was requested
    var $PageGroups;     // variable containing the number of pages of the groups
    var $CurrPageGroup;  // variable containing the alias of the current page group
    protected $last_page_flag = false;
    protected $first_page_flag= false;
    public $defaultFont;
    public $lastValues;
    public $firstValues;
    

    function __construct() {
        global $report;
        $this->defaultFont = getModuleCache('phreeform','settings','general','default_font','helvetica');
        $PaperSize = explode(':', $report->pagesize);
        // tFPDF constructor: (orientation, unit, format) — 3 args, not 6 like TCPDF.
        // UTF-8 is intrinsic to tFPDF (that's the whole point of the fork) so no
        // encoding parameter is needed. The defunct disk-cache flag (TCPDF's 6th arg)
        // is dropped — tFPDF has no equivalent.
        parent::__construct($report->pageorient, 'mm', strtoupper($PaperSize[0]));
        if ($report->pageorient == 'P') { // Portrait - calculate max page height
            $this->pageY = $PaperSize[2] - $report->marginbottom;
        } else { // Landscape
            $this->pageY = $PaperSize[1] - $report->marginbottom;
        }
        // Replaced PDF_MARGIN_LEFT/TOP/RIGHT (TCPDF-defined constants) with literal 10mm —
        // matches TCPDF's defaults. Immediately overridden below with the report's own margins.
        $this->SetMargins(10, 10, 10);
        $this->SetMargins($report->marginleft, $report->margintop, $report->marginright);
        // SetCellPadding/SetCellPaddings were TCPDF-only (control over per-side cell padding).
        // tFPDF has SetCellPadding(value) for symmetric padding, and that's also the FPDF
        // default of 1mm. Honoring the original intent ("move text away from border") by
        // keeping FPDF's default 1mm padding — no SetCellPadding call needed.
        $this->SetAutoPageBreak(0, $report->marginbottom);
        $this->SetFont($this->defaultFont);
        $this->SetDrawColor(128, 0, 0);
        $this->SetLineWidth(0.35); // 1 point
    }

    /**
     * Creates the header for every page of a PDF output file. Basically all page static data
     * @global type $report - complete $report structure with data
     */
    function Header()
    { // prints all static information on the page
        global $report;
        $tempValues = $report->FieldValues;
        $this->firstValues = $this->lastValues = [];
        foreach ($report->fieldlist->rows as $field) {
            if (!isset($field->settings)) { $field->settings = (object)[]; }
            // This flags an error for block types. Need to define as class for each level.
            if (empty($field->settings->font))  {
                $field->settings->font  = isset($field->settings->hfont) ? $field->settings->hfont : $this->defaultFont;
            }
            if (empty($field->settings->size))  { $field->settings->size  = $this->defaultSize; }
            if (empty($field->settings->color)) { $field->settings->color = $this->defaultColor; }
            if (empty($field->settings->align)) { $field->settings->align = $this->defaultAlign; }
            switch ($field->type) {
                case 'Data':
                    if (!isset($field->settings->display))    { $field->settings->display = 0; }
                    $cellValue = array_shift($tempValues);
                    $field->settings->text = $cellValue; // fill the data to display
                    if (!isset($field->settings->processing)) { $field->settings->processing = ''; }
                    if (!isset($field->settings->formatting)) { $field->settings->formatting = ''; }
                case 'Text':
                    // some special processing if display on first or last page only for Data and Text only
                    if (!empty($field->settings->display) && $field->settings->display==1) { $this->firstValues[]= $field; break; }
                    if (!empty($field->settings->display) && $field->settings->display==2) { $this->lastValues[] = $field; break; }
                case 'TBlk':
                case 'CDta':
                case 'CBlk':
                case 'LtrTpl':
                case 'PgNum':   $this->FormText($field);    break;
                case 'CImg':
                case 'Img':     $this->FormImage($field);   break;
                case 'ImgLink': $this->FormImgLink($field,  array_shift($tempValues)); break;
                case 'Line':    $this->FormLine($field);    break;
                case 'Rect':    $this->FormRect($field);    break;
                case 'BarCode': $this->FormBarCode($field, array_shift($tempValues)); break;
                case 'Tbl':     if (!empty($field->settings->fieldname)) { array_shift($tempValues); }  break; // scrap field as this is not static but is in the data array
                default: // do nothing
            }
        }
    }

    /**
     * Generates the footer data for forms, generally the totals
     * @global array $report - Complete $report structure with data
     */
    public function Footer()
    {
        global $report;
        foreach ($report->fieldlist->rows as $field) {
            if (!isset($field->settings->display)){ $field->settings->display = 0; }
            if (!isset($field->settings->font))   { $field->settings->font  = $this->defaultFont; }
            if (!isset($field->settings->size))   { $field->settings->size  = $this->defaultSize; }
            if (!isset($field->settings->color))  { $field->settings->color = $this->defaultColor; }
            if (!isset($field->settings->align))  { $field->settings->align = $this->defaultAlign; }
            switch ($field->type) {
                case 'Data':
                case 'Text':
                    if ($this->first_page_flag && $field->settings->display==1) {
                        $field = array_shift($this->firstValues);
                        $this->FormText($field);
                    }
                    if ($this->last_page_flag  && $field->settings->display==2) {
                        $field = array_shift($this->lastValues);
                        $this->FormText($field);
                    }
                    break;
                case 'Ttl': $this->FormText($field); break;
                default: // nothing
            }
        }
    }

    public function Close() {
        parent::Close();
    }
    /**
     * Create a new page group; call this before calling AddPage()
     */
    function StartPageGroup($page='')
    { //
        $this->NewPageGroup = true;
    }

    /**
     * Returns the current page number being generated
     * @return integer
     */
    function GroupPageNo()
    { // current page in the group
        return $this->PageGroups[$this->CurrPageGroup];
    }

    /**
     * Alias of the current page group -- will be replaced by the total number of pages in this group
     * @return current page group
     */
    public function PageGroupAlias()
    { //
        return $this->CurrPageGroup;
    }

    protected function getPagePosition($display=0)
    {
        if (!$display) { return true; } // all pages
        msgDebug("\nSetting display value with display = $display");
        if ($display==1 && $this->GroupPageNo()==1) { return true; } // first page
        if ($display==2 && $this->GroupPageNo()==$this->PageGroupAlias()) { msgDebug("returning true last page"); return true; } // last page
        msgDebug("returning false, page ".$this->GroupPageNo()." of ".$this->PageGroupAlias());
        return false;
    }

    /**
     * Override of tFPDF/FPDF's protected `_beginpage` hook — starts rendering a page,
     * static content first then dynamic. We tap in here to maintain per-page-group
     * counters used by the multi-form page-numbering aliases (`{nb1}`, `{nb2}`, …).
     *
     * Signature matches tFPDF 1.33 / FPDF 1.85 (3 args: orientation, size, rotation).
     * Older Bizuno code had a 2-arg override which silently dropped `$rotation` and
     * tripped PHP 8 LSP warnings. Now LSP-clean and forwards `$rotation` to parent.
     *
     * @param string $orientation page orientation
     * @param string $size        page size
     * @param int    $rotation    page rotation in degrees (0/90/180/270)
     */
    public function _beginpage($orientation='', $size='', $rotation=0)
    {
        parent::_beginpage($orientation, $size, $rotation);
        if ($this->NewPageGroup) { // start a new group
            $n = sizeof((array)$this->PageGroups)+1;
            $alias = "{nb$n}";
            $this->PageGroups[$alias] = 1;
            $this->CurrPageGroup = $alias;
            $this->NewPageGroup = false;
        } else if ($this->CurrPageGroup) {
            $this->PageGroups[$this->CurrPageGroup]++;
        }
    }

    /**
     * Override of FPDF/tFPDF's internal _putpages() — performs page-group placeholder
     * substitution (replaces our `{nbN}` aliases with the per-group page count) before
     * delegating to the parent implementation. Signature matches tFPDF.
     */
    function _putpages() {
        $nb = $this->page;
        if (!empty($this->PageGroups)) { // do page number replacement
            foreach ($this->PageGroups as $k => $v) {
                for ($n = 1; $n <= $nb; $n++) { $this->pages[$n] = str_replace($k, $v, $this->pages[$n]); }
            }
        }
        parent::_putpages();
    }

    /**
     * Creates an image and places it on the page
     * @param array $Params - attributes of the image element
     */
    function FormImage($Params)
    {
        if (!isset($Params->abscissa)) { $Params->abscissa = 10; }
        if (!isset($Params->ordinate)) { $Params->ordinate = 10; }
        if (!isset($Params->width))    { $Params->width    = 0; }
        if (!isset($Params->height))   { $Params->height   = 0; }
        // tFPDF::Image() does integer math on w/h; an empty or non-numeric string (which the form
        // definition can supply) throws "Unsupported operand types" under PHP 8. Coerce to float so
        // a blank dimension becomes 0 — FPDF's auto-size sentinel — matching the old TCPDF behavior.
        $width  = (float)$Params->width;
        $height = (float)$Params->height;
        if (is_file(BIZUNO_DATA.'images/'.$Params->settings->img_file)) {
            $this->Image(BIZUNO_DATA.'images/'.$Params->settings->img_file, $Params->abscissa, $Params->ordinate, $width, $height);
        } else { // no image was found at the specified path, draw a box
            $this->SetXY($Params->abscissa, $Params->ordinate);
            $this->SetFont($this->defaultFont, '', '10');
            $this->SetTextColor(255, 0, 0);
            $this->SetDrawColor(255, 0, 0);
            $this->SetLineWidth(0.35);
            $this->SetFillColor(255);
            $this->Cell($width, $height, lang('no_image'), 1, 0, 'C');
        }
    }

    /**
     * Renders an image from a link on the PDF output file
     * @param object $Params - typically the settings of a specific field
     * @param string $path - relative path of where to find file in the images folder
     * @return updated class variables ready to render PDF element
     */
    function FormImgLink($Params, $path)
    {
        if (!isset($Params->abscissa)){ return; } // don't do anything if data array has not been set
        if (!isset($Params->abscissa)){ $Params->abscissa = '8'; }
        if (!isset($Params->ordinate)){ $Params->ordinate = '8'; }
        if (!isset($Params->width))   { $Params->width    = ''; }
        if (!isset($Params->height))  { $Params->height   = ''; }
        // Coerce to float so a blank/non-numeric dimension becomes 0 (FPDF auto-size) rather than
        // tripping tFPDF's "Unsupported operand types" under PHP 8. See FormImage() above.
        $width  = (float)$Params->width;
        $height = (float)$Params->height;
        if ( isset($Params->settings->processing)) { $path = viewProcess($path, $Params->settings->processing); }
        $ext = pathinfo(BIZUNO_DATA."images/$path", PATHINFO_EXTENSION);
        msgDebug("\nLooking for image at BIZUNO_DATA/images/$path");
        if (is_file(BIZUNO_DATA."images/$path") && (in_array(strtolower($ext), ['jpg', 'jpeg', 'png']))) {
            $this->Image(BIZUNO_DATA."images/$path", $Params->abscissa, $Params->ordinate, $width, $height);
        } elseif (!empty($Params->hideNone)) {
            $this->Cell($width, 5, lang('none'), 1, 0, 'C');
        } else { // no image was found at the specified path, draw a box
            $this->SetXY($Params->abscissa, $Params->ordinate);
            $this->SetFont($this->defaultFont, '', '10');
            $this->SetTextColor(255, 0, 0);
            $this->SetDrawColor(255, 0, 0);
            $this->SetLineWidth(0.35);
            $this->SetFillColor(255);
            $this->Cell('30', '20', lang('no_image'), 1, 0, 'C');
        }
    }

    /**
     * Creates and places a single line on the PDF page.
     * @param array $Params - field settings
     * @return updated  class variables ready to render.
     */
    public function FormLine($Params) {
        if (!isset($Params->abscissa))          { return; } // don't do anything if data array has not been set
        if (!isset($Params->settings->bsize))   { $Params->settings->bsize    = '1'; }
        if (!isset($Params->settings->bcolor))  { $Params->settings->bcolor  = $this->defaultColor; }
        if (!isset($Params->settings->linetype)){ $Params->settings->linetype= 'H'; }
        if (!isset($Params->settings->length))  { $Params->settings->length  = 0; }
        $FC = $this->convertRGB($Params->settings->bcolor);
        $this->SetDrawColor($FC[0], $FC[1], $FC[2]);
        $this->SetLineWidth($Params->settings->bsize * 0.35);
        if       ($Params->settings->linetype == 'H') { // Horizontal
            $XEnd = $Params->abscissa + $Params->settings->length;
            $YEnd = $Params->ordinate;
        } elseif ($Params->settings->linetype == 'V') { // Vertical
            $XEnd = $Params->abscissa;
            $YEnd = $Params->ordinate + $Params->settings->length;
        } elseif ($Params->settings->linetype == 'C') { // Custom
            $XEnd = $Params->endabscissa;
            $YEnd = $Params->endordinate;
        }
        $this->Line($Params->abscissa, $Params->ordinate, $XEnd, $YEnd);
    }

    /**
     * Places a rectangle in the page.
     * @param array $Params - fields attributes
     * @return updated session variables.
     */
    function FormRect($Params) {
        if (!isset($Params->abscissa)) { return; } // don't do anything if data array has not been set
        $DrawFill = '';
        if (isset($Params->settings->bshow) && $Params->settings->bshow == '1') { // Border
            $DrawFill = 'D';
            $FC = $this->convertRGB($Params->settings->bcolor);
            $this->SetDrawColor($FC[0], $FC[1], $FC[2]);
            $this->SetLineWidth($Params->settings->bsize * 0.35);
        } else {
            $this->SetDrawColor(255);
            $this->SetLineWidth(0);
        }
        if (isset($Params->settings->fshow) && $Params->settings->fshow == '1') { // Fill
            $DrawFill .= 'F';
            $FC = $this->convertRGB($Params->settings->fcolor);
            $this->SetFillColor($FC[0], $FC[1], $FC[2]);
        } else {
            $this->SetFillColor(255);
        }
        $this->Rect($Params->abscissa, $Params->ordinate, $Params->width, $Params->height, $DrawFill);
    }

    /**
     * Creates and places a bar code image on the page
     * @param array $Params - field settings
     * @param string $data - data used to create new bar code image
     * @return updated variables
     */
    function FormBarCode($Params, $data) {
        if (!isset($Params->abscissa)) { return; } // don't do anything if data array has not been set
        if (!isset($Params->width))           { $Params->width  = ''; }
        if (!isset($Params->height))          { $Params->height = ''; }
        if (!isset($Params->settings->fshow)) { $Params->settings->fshow = '0'; }
        $style = [
            'position'    => '',
            'border'      => isset($Params->settings->bshow) && $Params->settings->bshow ? true : false,
//            'padding'     => 2, // in user units
            'text'        => true, // print text below barcode
            'font'        => $Params->settings->font == 'default' ? $this->defaultFont : $Params->settings->font,
            'fontsize'    => $Params->settings->size,
            'stretchtext' => 1, // 0 = disabled; 1 = horizontal scaling only if necessary; 2 = forced horizontal scaling; 3 = character spacing only if necessary; 4 = forced character spacing
            'fgcolor'     => $this->convertRGB($Params->settings->color),
            'stretch'     => false,
            'fitwidth'    => true,
//            'cellfitalign' => '',
//            'hpadding' => 'auto',
//            'vpadding' => 'auto',
        ];
        switch ($Params->settings->fshow) {
            default:
            case '0': $style['bgcolor'] = false; break;
            case '1': $style['bgcolor'] = $this->convertRGB($Params->settings->fcolor); break;
        }
        if (isset($Params->settings->processing)) { $data = viewProcess($data, $Params->settings->processing); }
        if (isset($Params->settings->formatting)) { $data = viewFormat ($data, $Params->settings->formatting); }
        $data1 = clean($data, 'alpha_num'); // need to remove all special characters
        // Was TCPDF's write1DBarcode(...). Now using picqer/php-barcode-generator which
        // renders to PNG and embeds via Image(). The `$style` array (border, fgcolor,
        // text-below, etc.) that TCPDF supported is largely gone — picqer is intentionally
        // minimal. We honor the foreground color; the rest defaults to picqer's clean look.
        // Text below the barcode is appended manually via Cell() in bizBarcode below.
        $this->bizBarcode($data1, $Params->settings->barcode, $Params->abscissa, $Params->ordinate, $Params->width, $Params->height, $style);
    }

    /**
     * Renders a 1D barcode on the current page using picqer/php-barcode-generator.
     *
     * Replaces TCPDF's write1DBarcode. Maps the legacy `type` argument (TCPDF symbology
     * codes like 'C128', 'C39', 'EAN13') onto picqer's TYPE_* constants. Renders to PNG
     * in memory, embeds via FPDF::Image() using a `data://` stream URI to avoid temp
     * files, then optionally writes the human-readable text underneath.
     *
     * @param string $data    cleaned barcode payload
     * @param string $type    TCPDF-style symbology code (defaults to C128 if unknown)
     * @param float  $x       page X in mm
     * @param float  $y       page Y in mm
     * @param float  $w       width in mm
     * @param float  $h       total height in mm (text label, if drawn, eats ~3mm of this)
     * @param array  $style   legacy style hash — only `text`, `fgcolor`, `font`, `fontsize` used
     */
    protected function bizBarcode($data, $type, $x, $y, $w, $h, $style=[])
    {
        if (empty($data) || empty($w) || empty($h)) { return; }
        // Map TCPDF symbology codes (the form designer still uses them) to picqer types.
        // Unknown types fall back to Code 128 which handles arbitrary alphanumeric payloads.
        $typeMap = [
            'C128'  => BarcodeGeneratorPNG::TYPE_CODE_128,
            'C128A' => BarcodeGeneratorPNG::TYPE_CODE_128,
            'C128B' => BarcodeGeneratorPNG::TYPE_CODE_128,
            'C128C' => BarcodeGeneratorPNG::TYPE_CODE_128,
            'C39'   => BarcodeGeneratorPNG::TYPE_CODE_39,
            'C39E'  => BarcodeGeneratorPNG::TYPE_CODE_39_CHECKSUM,
            'EAN13' => BarcodeGeneratorPNG::TYPE_EAN_13,
            'EAN8'  => BarcodeGeneratorPNG::TYPE_EAN_8,
            'UPCA'  => BarcodeGeneratorPNG::TYPE_UPC_A,
            'UPCE'  => BarcodeGeneratorPNG::TYPE_UPC_E,
            'I25'   => BarcodeGeneratorPNG::TYPE_INTERLEAVED_2_5,
            'POSTNET'=> BarcodeGeneratorPNG::TYPE_POSTNET,
        ];
        $picqerType = $typeMap[strtoupper($type)] ?? BarcodeGeneratorPNG::TYPE_CODE_128;
        $showText   = !empty($style['text']);
        $barH       = $showText ? max(1, $h - 3) : $h;   // reserve 3mm for human-readable line if requested
        $fgcolor    = (!empty($style['fgcolor']) && is_array($style['fgcolor'])) ? $style['fgcolor'] : [0, 0, 0];
        try {
            $gen = new BarcodeGeneratorPNG();
            // picqer width is per-bar (pixels). Use a rough conversion: target ~ (w mm * 4) px wide.
            $widthFactor = max(1, (int)round(($w * 4) / max(1, strlen($data))));
            $heightPx    = max(20, (int)round($barH * 8)); // 8 px/mm gives crisp print
            $png = $gen->getBarcode($data, $picqerType, $widthFactor, $heightPx, $fgcolor);
        } catch (\Exception $e) {
            msgDebug("\nbizBarcode failed for data='$data' type='$type': ".$e->getMessage(), 'trap');
            // Draw a labelled placeholder so the user sees something went wrong on the form.
            $this->SetXY($x, $y);
            $this->SetFont($this->defaultFont, '', 8);
            $this->Cell($w, $h, "[barcode err: $data]", 1, 0, 'C');
            return;
        }
        // FPDF::Image() accepts a data:// stream URI — no temp file needed.
        $this->Image('data://text/plain;base64,'.base64_encode($png), $x, $y, $w, $barH, 'PNG');
        if ($showText) {
            $font = ($style['font'] ?? 'default') === 'default' ? $this->defaultFont : $style['font'];
            $size = $style['fontsize'] ?? 8;
            $this->SetXY($x, $y + $barH);
            $this->SetFont($font, '', $size);
            $this->SetTextColor($fgcolor[0], $fgcolor[1], $fgcolor[2]);
            $this->Cell($w, 3, $data, 0, 0, 'C');
        }
    }

    /**
     * Places a single text string on the page
     * @param array $Params - field settings
     * @return updated class variables
     */
    function FormText($Params) {
        if (!isset($Params->abscissa)) { return; } // don't do anything if data array has not been set
        $this->SetXY($Params->abscissa, $Params->ordinate);
        $this->SetFont($Params->settings->font == 'default' ? $this->defaultFont : $Params->settings->font, '', $Params->settings->size);
        $FC = $this->convertRGB($Params->settings->color);
        $this->SetTextColor($FC[0], $FC[1], $FC[2]);
        if (isset($Params->settings->bshow) && $Params->settings->bshow == '1') { // Border
            $Border = '1';
            $FC = $this->convertRGB($Params->settings->bcolor);
            $this->SetDrawColor($FC[0], $FC[1], $FC[2]);
            $this->SetLineWidth($Params->settings->bsize * 0.35);
        } else {
            $Border = '0';
        }
        if (isset($Params->settings->fshow) && $Params->settings->fshow == '1') { // Fill
            $Fill = '1';
            $FC = $this->convertRGB($Params->settings->fcolor);
            $this->SetFillColor($FC[0], $FC[1], $FC[2]);
        } else {
            $Fill = '0';
        }
        if ($Params->type <> 'PgNum') { $TextField = isset($Params->settings->text) ? $Params->settings->text : ''; }
        else { $TextField = $this->GroupPageNo().' '.lang('of').' '.$this->PageGroupAlias(); } // fix for multi-page multi-group forms
        $GLOBALS['pfFieldSettings'] = $Params;
        if (isset($Params->settings->processing)) { $TextField = viewProcess($TextField, $Params->settings->processing); }
        if (isset($Params->settings->formatting)) { $TextField = viewFormat ($TextField, $Params->settings->formatting); }
        if ($TextField) {
            // ─── Line-height calculation ──────────────────────────────
            // tFPDF's MultiCell takes a PER-LINE height, not the total
            // height of the box. Font size is in points; coordinates are
            // mm. 1pt = 0.3528mm, with ~1.2× leading typical for body
            // text. This gives a sensible per-line height regardless of
            // what $Params->height happens to be.
            $lineHeight = $Params->settings->size * 0.3528 * 1.2;

            if ($Params->height < $lineHeight * 1.5) {
                // ─── Single-line cell ─────────────────────────────────
                // Legacy path: the field is sized as a ONE-line label
                // (e.g. a "NOTES" header, customer name, page-number).
                // Passing $Params->height as MultiCell's line height
                // works fine here because the text always fits in one
                // line within $w, so no wrapping ever happens — the
                // height parameter simply controls the cell's box
                // dimensions. Preserve existing behavior so we don't
                // perturb hundreds of well-tuned label cells.
                $this->MultiCell($Params->width, $Params->height,
                    $TextField, $Border, $Params->settings->align, $Fill);
            } else {
                // ─── Multi-line "box" cell — fixes the truncation bug ─
                // Tall fields (Notes, long descriptions, address blocks)
                // are containers, not single lines. The old code passed
                // $Params->height (e.g. 35mm) as the per-line height,
                // which made tFPDF treat the whole box as one giant
                // line — no wrapping, text overflowing the right edge
                // and visually clipping at the page margin.
                //
                // Fix: draw the bounding rectangle (border + fill) as a
                // separate operation, then render the text inside with
                // the font-derived line height so MultiCell wraps
                // properly at word boundaries within $w.
                if ($Border === '1' || $Fill === '1') {
                    $rectStyle = ($Fill === '1' ? 'F' : '')
                               . ($Border === '1' ? 'D' : '');
                    $this->Rect($Params->abscissa, $Params->ordinate,
                        $Params->width, $Params->height, $rectStyle);
                    // Rect() moves the cursor; put it back at the box
                    // origin so MultiCell starts at the top-left.
                    $this->SetXY($Params->abscissa, $Params->ordinate);
                }
                $this->MultiCell($Params->width, $lineHeight,
                    $TextField, '0', $Params->settings->align, '0');
            }
        }
    }

    /**
     * Builds a data table and places it on the form.
     * @param array $Params - field settings
     */
    function FormTable($Params) {
        msgDebug("\nTable params = ".print_r($Params, true));
        if (!isset($Params->settings->hfcolor)) { $Params->settings->hfcolor = $this->defaultColor; }
        if (!isset($Params->settings->fcolor))  { $Params->settings->fcolor  = $this->defaultColor; }
        $hRGB = $Params->settings->hfcolor;
        $FC = $this->convertRGB($Params->settings->fcolor);
        $hFC = (!$hRGB) ? $FC : $this->convertRGB($hRGB);
        $MaxBoxY = $Params->ordinate + $Params->height; // figure the max y position on page
        $FillThisRow = false;
        $MaxRowHt = 0; //track the tallest row to estimate page breaks
        $HeadingHt= 0;
        $this->y0 = $Params->ordinate;
        $this->first_page_flag = true;
        $this->last_page_flag  = false;
        foreach ($Params->data as $index => $myrow) {
            // See if we are at or near the end of the table box size
            if (($this->y0 + $MaxRowHt) > $MaxBoxY) { // need a new page
                $this->DrawTableLines($Params, $HeadingHt); // draw the box and lines around the table
                $this->AddPage();
                $this->y0 = $Params->ordinate;
                $this->SetLeftMargin($Params->abscissa);
                $this->SetXY($Params->abscissa, $Params->ordinate);
                $MaxRowHt = $this->ShowTableRow($Params, $Params->data[0], true, $hFC, true); // new page heading
                $HeadingHt= $MaxRowHt;
                $this->first_page_flag = false;
            }
            $this->SetLeftMargin($Params->abscissa);
            $this->SetXY($Params->abscissa, $this->y0);
            // fill in the data
            if ($index == 0) { // its a heading line
                $MaxRowHt = $this->ShowTableRow($Params, $myrow, true, $hFC, true);
                $HeadingHt= $MaxRowHt;
            } else {
                $MaxRowHt = $this->ShowTableRow($Params, $myrow, $FillThisRow, $FC);
            }
            $FillThisRow  = !$FillThisRow;
        }
        $this->DrawTableLines($Params, $HeadingHt); // draw the box and lines around the table
        $this->last_page_flag = true;
    }

    /**
     * Builds and places a single table row on the page.
     * @param array $Params - Field settings
     * @param array $myrow - a single row of data to place on page
     * @param boolean $FillThisRow - used for alternating background color to easier identify rows
     * @param integer $FC - fill color (in decimal 0 - 255)
     * @param boolean $Heading - [default false] set to true to generate a heading at top of table
     * @return integer - max row height used to control pagination and end of page position
     */
    public function ShowTableRow($Params, $myrow, $FillThisRow, $FC, $Heading=false)
    {
        if (!isset($Params->settings->hfshow)) { $Params->settings->hfshow = '0'; }
        if (!isset($Params->settings->fshow))  { $Params->settings->fshow  = '0'; }
        if (!isset($Params->settings->hbshow)) { $Params->settings->hbshow = '1'; }
        if (!isset($Params->settings->hfont))  { $Params->settings->hfont  = $this->defaultFont; }
        if (!isset($Params->settings->hsize))  { $Params->settings->hsize  = '12'; }
        if (!isset($Params->settings->halign)) { $Params->settings->halign = 'L'; }
        if (!isset($Params->settings->hcolor)) { $Params->settings->hcolor = $this->defaultColor; }
        $MaxBoxY = $Params->ordinate + $Params->height; // figure the max y position on page
        $fillReq = $Heading ? $Params->settings->hfshow : $Params->settings->fshow;
        if ($FillThisRow && $fillReq) {
            $this->SetFillColor($FC[0], $FC[1], $FC[2]);
        } else {
            $this->SetFillColor(255);
        }
        if ($fillReq) { $this->Cell($Params->width, $MaxBoxY - $this->y0, '', 0, 0, 'L', 1); } // sets background to white
        $maxY     = $this->y0; // set to current top of row
        $Col      = $MaxRowHt = $maxImgHt = 0;
        $NextXPos = $Params->abscissa;
        foreach ($myrow as $key => $value) {
            if (substr($key, 0, 1) == 'r') { $key = substr($key, 1); }
            $font  = ($Heading && $Params->settings->hfont  <> '') ? $Params->settings->hfont  : $Params->settings->boxfield->rows[$key]->font;
            $size  = ($Heading && $Params->settings->hsize  <> '') ? $Params->settings->hsize  : $Params->settings->boxfield->rows[$key]->size;
            $color = ($Heading && $Params->settings->hcolor <> '') ? $Params->settings->hcolor : $Params->settings->boxfield->rows[$key]->color;
            $align = ($Heading && $Params->settings->halign <> '') ? $Params->settings->halign : $Params->settings->boxfield->rows[$key]->align;
            $this->SetLeftMargin($NextXPos);
            $this->SetXY($NextXPos, $this->y0);
            $this->SetFont($font=='default' ? $this->defaultFont : $font, '', $size);
            $TC = $this->convertRGB($color);
            $this->SetTextColor($TC[0], $TC[1], $TC[2]);
            $CellHeight = ($size + 2) * 0.35;
//          if ($trunc) $value=$this->TruncData($value, $value->width);
            // special code for heading and data
            if ($Heading) {
                if ($align == 'A') { $align = $Params->settings->boxfield->rows[$key]->align; } // auto align
            } else {
                if (isset($Params->settings->boxfield->rows[$key]->processing)) { $value = viewProcess($value, $Params->settings->boxfield->rows[$key]->processing); }
                if (isset($Params->settings->boxfield->rows[$key]->formatting)) { $value = viewFormat ($value, $Params->settings->boxfield->rows[$key]->formatting); }
            }
            // if processing is image_sku OR inv_image, set the Params object and call formImgLink
            if (!$Heading && isset($Params->settings->boxfield->rows[$key]->processing) && in_array($Params->settings->boxfield->rows[$key]->processing, ['inv_image','image_sku'])) {
                $width = $Params->settings->boxfield->rows[$key]->width;
                $props = (object)['abscissa'=>$this->GetX(), 'ordinate'=>$this->GetY(), 'width'=>$width, 'height'=>$width*3/4, 'hideNone'=>true];
                $this->FormImgLink($props, $value);
                $maxImgHt = $width * 3 / 4;
            } else { // basic HTML support via the inline shim — see bizHTMLCell() below
                $this->bizHTMLCell($Params->settings->boxfield->rows[$key]->width, $CellHeight, $value, 0, $align, $fillReq ? true : false);
            }
            if ($this->GetY() > $maxY) { $maxY = $this->GetY(); }
            $NextXPos += $Params->settings->boxfield->rows[$key]->width;
            $Col++;
        }
        $ThisRowHt = max($maxImgHt, $maxY - $this->y0); // see how tall this row was
        if ($ThisRowHt > $MaxRowHt) { $MaxRowHt = $ThisRowHt; } // keep that largest row so far to track pagination
        $this->y0 = $this->y0 + $MaxRowHt; // set y position to largest value for next row
        if ($Heading && $Params->settings->hbshow) { // then it's the heading draw a line after if fill is set
            $this->Line($Params->abscissa, $maxY, $Params->abscissa + $Params->width, $maxY);
            $this->y0 = $this->y0 + ($Params->settings->hsize * 0.35);
        }
        return $MaxRowHt;
    }

    /**
     * Draws lines all the lines in a table
     * @param array $Params - field settings
     * @param float $HeadingHt - height of the heading to determine ordinate starting point
     * @return null - data is added to PDF output string
     */
    function DrawTableLines($Params, $HeadingHt) {
        if (!isset($Params->settings->hbshow)) { $Params->settings->hbshow = $Params->settings->bshow; }
        if (!isset($Params->settings->hbsize)) { $Params->settings->hbsize = $Params->settings->bsize; }
        if (!isset($Params->settings->hbcolor)){ $Params->settings->hbcolor= '0:0:0'; }
        $hRGB = $Params->settings->hbcolor;
        $DC = $this->convertRGB($Params->settings->bcolor);
        $hDC = (!$hRGB) ? $DC : $this->convertRGB($hRGB);
        $MaxBoxY = $Params->ordinate + $Params->height; // figure the max y position on page
        // draw the heading
        $this->SetDrawColor($hDC[0], $hDC[1], $hDC[2]);
        $this->SetLineWidth($Params->settings->hbsize * 0.35);
        if ($Params->settings->hbshow) {
            $this->Rect($Params->abscissa, $Params->ordinate, $Params->width, $HeadingHt);
            $NextXPos = $Params->abscissa;
            foreach ($Params->settings->boxfield->rows as $value) { // Draw the vertical lines
                $this->Line($NextXPos, $Params->ordinate, $NextXPos, $Params->ordinate + $HeadingHt);
                $NextXPos += $value->width;
            }
        }
        // draw the table lines
        $this->SetDrawColor($DC[0], $DC[1], $DC[2]);
        $this->SetLineWidth($Params->settings->bsize * 0.35);
        // Fill the remaining part of the table with white
        if ($this->y0 < $MaxBoxY) {
            $this->SetLeftMargin($Params->abscissa);
            $this->SetXY($Params->abscissa, $this->y0);
            $this->SetFillColor(255);
            if ($Params->settings->fshow) { $this->Cell($Params->width, $MaxBoxY - $this->y0, '', 0, 0, 'L', 1); }
        }
        if (isset($Params->settings->bshow)) {
            $this->Rect($Params->abscissa, $Params->ordinate + $HeadingHt, $Params->width, $Params->height - $HeadingHt);
            $NextXPos = $Params->abscissa;
            foreach ($Params->settings->boxfield->rows as $value) { // Draw the vertical lines
                $this->Line($NextXPos, $Params->ordinate + $HeadingHt, $NextXPos, $Params->ordinate + $Params->height);
                $NextXPos += $value->width;
            }
        }
    }

    /**
     * Truncates long data strings to fit within column width
     * @param sting $strData - data string to measure and operate on
     * @param float $ColWidth - width of a column in ems
     * @return string - truncated string if longer than 90% of the width, original string if not
     */
    function TruncData($strData, $ColWidth) {
        $percent = 0.90; //percent to truncate from max to account for proportional spacing
        $CurWidth = $this->GetStringWidth($strData);
        if ($CurWidth > ($ColWidth * $percent)) { // then it needs to be truncated
            // for now we'll do an approximation based on averages and scale to 90% of the width to allow for variance
            // A better aproach would be an recursive call to this function until the string just fits.
            $NumChars = strlen($strData);
            // Reduce the string by 1-$percent and retest
            $strData = $this->TruncData(substr($strData, 0, ($ColWidth / $CurWidth) * $NumChars * $percent), $ColWidth);
        }
        return $strData;
    }

    /**
     * Minimal inline-HTML cell renderer — drop-in replacement for TCPDF's writeHTMLCell
     * used by ShowTableRow above.
     *
     * Supported tags (anything else is stripped via strip_tags fallback):
     *   <b>, <strong>            — bold
     *   <i>, <em>                — italic
     *   <u>                      — underline
     *   <br>, <br/>, <br />      — line break
     *   <font color="#rrggbb">   — text color (closing </font> resets to current color)
     *
     * Renders runs in sequence on the current line using FPDF::Write(), with line breaks
     * handled by Ln(). For cells that need fixed width + wrap (the table row use case),
     * the caller passes a width and the FPDF::Write() word-wrap engine breaks naturally.
     *
     * No support for nested complex layouts, block-level elements, tables, lists, or CSS.
     * If the form designer expected those (it shouldn't — the form-builder UI doesn't emit
     * them), the content will degrade to plain text without crashing.
     *
     * @param float   $w        cell width in mm
     * @param float   $h        single-line height in mm (also used as line spacing)
     * @param string  $html     cell content; may contain the tags above
     * @param int     $border   0/1 — draw cell border around the bounding box
     * @param string  $align    'L'|'C'|'R' — alignment hint (used only for plain-text fallback)
     * @param bool    $fill     fill background with current fill color
     */
    protected function bizHTMLCell($w, $h, $html, $border=0, $align='L', $fill=false)
    {
        $startX = $this->GetX();
        $startY = $this->GetY();
        // Quick path: no tag-like content → MultiCell handles wrap + alignment + fill natively.
        if (strpos($html, '<') === false) {
            $this->MultiCell($w, $h, (string)$html, $border, $align, $fill);
            return;
        }
        // Normalize line breaks: <br>, plus block-level <p>/<div> boundaries → newlines.
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(?:p|div)\s*>#i', "\n", $html);   // closing block tag ends the line
        $html = preg_replace('#<(?:p|div)\b[^>]*>#i', '', $html);  // opening block tag (its newline came from the close)
        // Strip every remaining tag EXCEPT the supported inline ones, so unsupported markup
        // (e.g. <p>, <span>, lists) degrades to plain text instead of being swallowed whole or
        // mis-parsed as an inline tag. This is the "strip_tags fallback" the header describes.
        // Without it, a value that merely STARTS with an unsupported tag (e.g. "<p>DLC: ___")
        // splits into a single token beginning with "<", which the loop below treats as a tag
        // and never prints — dropping the entire cell.
        $html = strip_tags($html, '<b><strong><i><em><u><font>');
        // Collapse the blank line that consecutive </p><p> boundaries would otherwise leave.
        $html = preg_replace("/\n[ \t]*\n+/", "\n", $html);
        $tokens = preg_split('#(</?(?:b|strong|i|em|u|font)[^>]*>)#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        // Capture state to restore at end so we don't leak style into the next cell.
        $savedFont    = $this->FontFamily;
        $savedStyle   = $this->FontStyle;
        $savedSize    = $this->FontSizePt;
        $savedColorR  = $this->TextColor;   // FPDF stores the pdf color command string; we restore via SetTextColor below
        $bold = $italic = $underline = false;
        $colorStack = []; // each push is [r,g,b]
        // Constrain FPDF::Write() word-wrap to THIS cell's column. The caller already set the
        // left margin to the column's left edge; we add a matching right margin so wrapped lines
        // break at the cell's right edge instead of flowing to the page edge / into the next column.
        $savedLMargin = $this->lMargin;
        $savedRMargin = $this->rMargin;
        $this->SetLeftMargin($startX);
        $this->SetRightMargin(max(0, $this->w - ($startX + $w)));
        if ($fill) { // paint the bounding box first; runs draw on top
            $this->Cell($w, $h, '', $border, 0, $align, true);
            $this->SetXY($startX, $startY);
        } elseif ($border) {
            $this->Cell($w, $h, '', $border, 0);
            $this->SetXY($startX, $startY);
        }
        $this->SetXY($startX, $startY); // start text at the cell's top-left
        foreach ($tokens as $tok) {
            if ($tok === '') { continue; }
            if ($tok[0] === '<') {
                $lower = strtolower($tok);
                if     (preg_match('#^<(b|strong)\b#', $lower))      { $bold = true; }
                elseif (preg_match('#^</(b|strong)\b#', $lower))     { $bold = false; }
                elseif (preg_match('#^<(i|em)\b#', $lower))          { $italic = true; }
                elseif (preg_match('#^</(i|em)\b#', $lower))         { $italic = false; }
                elseif (preg_match('#^<u\b#', $lower))               { $underline = true; }
                elseif (preg_match('#^</u\b#', $lower))              { $underline = false; }
                elseif (preg_match('#^<font[^>]*color\s*=\s*["\']?(#[0-9a-f]{6}|\d+:\d+:\d+)["\']?#i', $tok, $m)) {
                    $colorStack[] = $this->convertRGB($m[1]);
                    $rgb = end($colorStack);
                    $this->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
                }
                elseif (preg_match('#^</font\b#', $lower)) {
                    array_pop($colorStack);
                    if (empty($colorStack)) { $this->SetTextColor(0, 0, 0); }
                    else { $rgb = end($colorStack); $this->SetTextColor($rgb[0], $rgb[1], $rgb[2]); }
                }
                // Apply font style change (bold/italic/underline) to the current font.
                $st = ($bold?'B':'').($italic?'I':'').($underline?'U':'');
                $this->SetFont($savedFont, $st, $savedSize);
            } else {
                // Decode entities, honor explicit \n breaks via the <br> normalization above.
                $text  = html_entity_decode($tok, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $lines = explode("\n", $text);
                foreach ($lines as $i => $line) {
                    if ($line !== '') { $this->Write($h, $line); }
                    if ($i < count($lines) - 1) { $this->Ln($h); }
                }
            }
        }
        // Restore font + color so the next cell isn't bleeding our state.
        $this->SetFont($savedFont, $savedStyle, $savedSize);
        $this->TextColor = $savedColorR;
        // Restore the page margins we narrowed for wrapping.
        $this->SetLeftMargin($savedLMargin);
        $this->SetRightMargin($savedRMargin);
        // Leave the cursor at the bottom of the rendered content (mirrors the MultiCell quick
        // path) so the caller's row-height tracking grows the row to fit wrapped/multi-line cells.
        $this->SetXY($startX, $this->GetY() + $h);
    }

    /**
     * Converts a RGB color to hexadecimal format
     * @param string $value - value to convert
     * @return string - converted $value
     */
    private function convertRGB($value)
    {
        if (strpos($value, '#') === 0) {
            $output[] = hexdec(substr($value, 1, 2));
            $output[] = hexdec(substr($value, 3, 2));
            $output[] = hexdec(substr($value, 5, 2));
        } elseif (strpos($value, ':') !== false) {
            $output = explode(':', $value);
        } else { $output = [0, 0, 0]; }
        return $output; // black
    }
}
