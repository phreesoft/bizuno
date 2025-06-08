<?php
/*
 * Bizuno dashboard - Admin summary for alerts
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
 * @filesource controllers/administrate/dashboards/adm_alert/adm_alert.php
 */

namespace bizuno;

class adm_alert
{
    public $moduleID  = 'administrate';
    public $methodDir = 'dashboards';
    public $code      = 'adm_alert';
    public $hidden    = true;
    public $noSettings= true;
    public $noCollapse= true;
    public $noReload  = true;
    public $noClose   = true;
    public  $struc;
    public $lang      = ['title' => 'Admin - Alerts',
        'description' => 'Alert administration dashboard with summary information and quick links.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
    }

    /**
     * Generates the structure for the dashboard view
     * @return modified $layout
     */
    function render()
    {
        $data = [ // Generate the content for this dashboard
            'divs'  => [
                'body' =>['order'=>50,'type'=>'divs','attr'=>['id'=>$this->code],'divs'=>[
                    'title'  => ['order'=>10,'type'=>'html',  'html'=>"<h1>{$this->lang['title']}</h1>"],
                    'formBOF'=> ['order'=>15,'type'=>'form',  'key' =>"frm{$this->code}"],
                    'content'=> ['order'=>50,'type'=>'fields','keys'=>['field0']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</div></form>"],
            ]]],
            'forms' => [
                "frm{$this->code}" => ['attr'=>['type'=>'form','method'=>'post','action'=>BIZUNO_AJAX."&bizRt=administrate/admin/$this->code"]],
            ],
            'fields'=> [
                'field0' => ['order'=>10,'label'=>lang('field0'),'options'=>['width'=>300,'height'=>30,'value'=>"'content'",'validType'=>"'text'"],'attr'=>['type'=>'text','value'=>'']],
            ],
            'jsReady'=> [$this->code=>"ajaxForm('frm{$this->code}');"],
            ];
        return ['data'=>$data];
    }
}
