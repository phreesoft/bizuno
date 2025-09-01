<?php
/*
 * Handles encryption functions for credit cards
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
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-04-24
 * @filesource /model/encrypter.php
 */

namespace bizuno;

final class encryption {
    var $scramble1;
    var $scramble2;
    var $adj;
    var $mod;

    /**
     * Sets some variables and the scramble sequences
     */
    function __construct() {
        $this->scramble1 = '! #$%&()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[]^_`abcdefghijklmnopqrstuvwxyz{|}~';
        $this->scramble2 = 'f^jAE]okIOzU[2&q1{3`h5w_794p@6s8?BgP>dFV=m D<TcS%Ze|r:lGK/uCy.Jx)HiQ!#$~(;Lt-R}Ma,NvW+Ynb*0X';
        if (strlen($this->scramble1) <> strlen($this->scramble2)) {
            trigger_error('** SCRAMBLE1 is not same length as SCRAMBLE2 **', E_USER_ERROR);
        }
        $this->adj = 1.75;
        $this->mod = 3;
    }

    /**
     * Decrypts a string using the provided key
     * @param string $key  - Key to use to decrypt the string
     * @param string $source - encrypted string
     * @return string - decrypted value
     */
    function decrypt($key, $source)
    {
        if (strlen($key) < 1) { return msgAdd(lang('err_encrypt_key_missing')); }
        if (!$fudgefactor = $this->_convertKey($key)) { return; }
        if (empty($source)) { return msgAdd('No value has been supplied for decryption'); }
        $target  = null;
        $factor2 = 0;
        for ($i = 0; $i < strlen($source); $i++) {
            $char2 = substr($source, $i, 1);
            $num2 = strpos($this->scramble2, $char2);
            if ($num2 === false) { return msgAdd("Source string contains an invalid character ($char2)"); }
            $adj     = $this->_applyFudgeFactor($fudgefactor);
            $factor1 = $factor2 + $adj;
            $tmp1    = $num2 - round($factor1);
            $num1    = $this->_checkRange($tmp1);
            $factor2 = $factor1 + $num2;
            $char1   = substr($this->scramble1, $num1, 1);
            $target .= $char1;
        }
        return rtrim($target);
    }

    /**
     * Encrypts the string based on the encryption key
     * @param string $key - the encryption key
     * @param string $source - The value to encrypt
     * @param integer $sourcelen - (Default: 0) Pads the string to a minimum length, a value of zero will skip padding
     * @return boolean -  encrypted value
     */
    function encrypt($key, $source, $sourcelen = 0)
    {
        if (strlen($key) < 1) { return msgAdd(lang('err_encrypt_key_missing')); }
        if (!$fudgefactor = $this->_convertKey($key)) { return; }
        if (empty($source)) { return msgAdd('No value has been supplied for encryption'); }
        while (strlen($source) < $sourcelen) { $source .= ' '; }
        $target = null;
        $factor2 = 0;
        for ($i = 0; $i < strlen($source); $i++) {
          $char1   = substr($source, $i, 1);
          $num1    = strpos($this->scramble1, $char1);
          if ($num1 === false) { return msgAdd("Source string contains an invalid character ($char1)"); }
          $adj     = $this->_applyFudgeFactor($fudgefactor);
          $factor1 = $factor2 + $adj;
          $tmp2    = round($factor1) + $num1;
          $num2    = $this->_checkRange($tmp2);
          $factor2 = $factor1 + $num2;
          $char2   = substr($this->scramble2, $num2, 1);
          $target .= $char2;
        }
        return $target;
    }

    /**
     * This method validates credit card numbers
     * @param string $ccNumber - credit card number
     * @return true on success, false on error with message
     */
    public function validate($ccNumber)
    {
        $cardNumber = strrev($ccNumber);
        $numSum = 0;
        for ($i = 0; $i < strlen($cardNumber); $i++) {
            $currentNum = substr($cardNumber, $i, 1);
            if ($i % 2 == 1) { $currentNum *= 2; } // Double every second digit
            if ($currentNum > 9) { // Add digits of 2-digit numbers together
                $firstNum  = $currentNum % 10;
                $secondNum = ($currentNum - $firstNum) / 10;
                $currentNum= $firstNum + $secondNum;
            }
            $numSum += $currentNum;
        }
        if ($numSum % 10 <> 0) { return msgAdd("Credit card failed validation!", 'caution'); }
        return true;
    }

    private function _applyFudgeFactor(&$fudgefactor)
    {
        $fudge = array_shift($fudgefactor);
        $fudge = $fudge + $this->adj;
        $fudgefactor[] = $fudge;
        if (!empty($this->mod)) { if ($fudge % $this->mod == 0) { $fudge = $fudge * -1; } }
        return $fudge;
    }

    private function _checkRange($num)
    {
        $num = round($num);
        $limit = strlen($this->scramble1);
        while ($num >= $limit) { $num = $num - $limit; }
        while ($num < 0)       { $num = $num + $limit; }
        return $num;
    }

    private function _convertKey($key)
    {
        if (empty($key)) { return msgAdd('No value has been supplied for the encryption key'); }
        $array[] = strlen($key);
        $tot = 0;
        for ($i = 0; $i < strlen($key); $i++) {
            $char = substr($key, $i, 1);
            $num = strpos($this->scramble1, $char);
            if ($num === false) { return msgAdd("Key contains an invalid character ($char)"); }
            $array[] = $num;
            $tot = $tot + $num;
        }
        $array[] = $tot;
        return $array;
    }
}
