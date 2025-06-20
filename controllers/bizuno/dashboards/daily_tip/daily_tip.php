<?php
/*
 * Bizuno dashboard - Daily tip
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
 * @filesource /controllers/bizuno/dashboards/daily_tip/daily_tip.php
 */

namespace bizuno;

class daily_tip
{
    public $moduleID  = 'bizuno';
    public $methodDir = 'dashboards';
    public $code      = 'daily_tip';
    public $category  = 'bizuno';
    public $noSettings= true;
    public $noCollapse= true;
    public  $struc;
    public $lang = ['title'=>'Tip of the Day',
        'description'=>'Displays a helpful tip, varies randomly.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
    }

    public function render()
    {
        $tip = 'Coming soon!';
        return ['html'=>$tip];
    }
}
