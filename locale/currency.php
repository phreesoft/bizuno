<?php
/*
 * Curencies class to handle the cleaning and formatting for locales
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
 * @version    7.x Last Update: 2025-05-14
 * @filesource /locale/currency.php
 */

namespace bizuno;

class currency
{
    public $iso = 'USD';
    public $rate = 1;

    function __construct()
    {
        $this->iso  = getDefaultCurrency();
        $this->rate = 1;
    }

    /**
     * Formatted currency per the ISO code, if not the default currency then the prefix and suffix will be added
     * @param float $number - number to be formatted
     * @param string $iso - {default: getDefaultCurrency()} Three character code for the ISO currency
     * @param float $xRate - exchange rate from the default ISO code
     * @return string - Converted number
     */
    public function format($number, $iso='', $xRate=1)
    {
        if (strlen($iso) <> 3) { $iso = getDefaultCurrency(); }
        $values = getModuleCache('phreebooks', 'currency', 'iso', $iso);
        $format_number = number_format($number * $xRate, $values['dec_len'], $values['dec_pt'], $values['sep']);
        $zero = number_format(0, $values['dec_len']); // to handle -0.00
        if ($format_number == '-'.$zero) { $format_number = $zero; }
        if ($iso <> getDefaultCurrency()) { // show prefix and sufix if not default
            if ($values['prefix']) { $format_number  = $values['prefix'].' '.$format_number; }
            if ($values['suffix']) { $format_number .= ' '.$values['suffix']; }
        }
        return $format_number;
    }
}
