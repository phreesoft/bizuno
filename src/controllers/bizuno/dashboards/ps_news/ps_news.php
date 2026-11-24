<?php
/*
 * Bizuno dashboard - PhreeSoft News
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
 * @version    7.x Last Update: 2026-11-24
 * @filesource /controllers/bizuno/dashboards/ps_news/ps_news.php
 */

namespace bizuno;

class ps_news
{
    public  $moduleID  = 'bizuno';
    public  $methodDir = 'dashboards';
    public  $code      = 'ps_news';
    public  $category  = 'bizuno';
    public  $noSettings= true;
    public  $struc;
    public $lang = ['title'=>'PhreeSoft News',
        'description'=> 'PhreeSoft is no longer in business; this dashboard only shows that notice and can be removed.'];

    function __construct()
    {
    }

    public function render(&$layout=[])
    {
        // PhreeSoft, Inc. has closed; there is no news feed to fetch.
        $html = '<p><b>PhreeSoft is no longer in business.</b></p>'
              . '<p>There is no PhreeSoft news to show. You can remove this dashboard from your home page.</p>';
        return ['html'=>$html];
    }
}
