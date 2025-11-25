<?php
/*
 * Contacts module dashboard - Dashboard for preventable returns
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
 * @version    7.x Last Update: 2025-11-24
 * @filesource /controllers/contacts/dashboards/return_prevent/return_prevent.php
 */

namespace bizuno;

class return_prevent
{
    public  $moduleID  = 'contacts';
    public  $methodID  = 'returns';
    public  $methodDir = 'dashboards';
    public  $code      = 'return_prevent';
    public  $category  = 'quality';
    public  $struc;
    private $dates;
    public  $lang      = ['title' => 'Preventable Returns',
        'description'=> 'Lists returns that are considered preventable (error with your business, marketing, and other factors that you control).',
        'total_open' => 'Total Open Returns:'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->dates = [0=>lang('dates_quarter'), 1=>lang('dates_lqtr'), 2=>lang('quarter_neg2'), 3=>lang('quarter_neg3'), 4=>lang('quarter_neg4'), 5=>lang('quarter_neg5')];
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $order = [['id'=>'asc','text'=>lang('decreasing')],['id'=>'desc','text'=>lang('decreasing')]];
        $this->struc = [
            // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',  'value'=>[0],],'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',  'value'=>[-1]],'admin'=>true],
            // User fields
            'range'   => ['order'=>40,'label'=>lang('range'),        'clean'=>'char',    'attr'=>['type'=>'select',  'value'=>0],     'values'=>viewKeyDropdown($this->dates)],
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5],     'options'=>['min'=>0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values'=>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @param array $opts - Personalized user/menu options
     * @return modified $layout
     */
    public function render($opts=[])
    {
        $rows  = [];
        $order = $opts['order']=='desc' ? 'invoice_num DESC' : 'invoice_num';
        $dates = dbSqlDatesQrtrs($opts['range'], 'post_date');
        $stmt  = dbGetResult("SELECT journal_main.id, post_date, journal_meta.ref_id AS metaID, meta_value FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
            WHERE post_date>='{$dates['start_date']}' AND post_date<'{$dates['end_date']}' AND meta_key='return' ORDER BY $order");
        $result= $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nrows result = ".print_r($result, true));
        foreach ($result as $entry) { // build the list
            $meta  = json_decode($entry['meta_value'], true);
            if (empty($meta['preventable'])) { continue; }
            msgDebug("\nmeta = ".print_r($meta, true));
            $left  = viewDate($meta['creation_date'])." - ".viewText($meta['caller_name'], $opts['trim']);
            $right = '';
            $action= html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=phreebooks/returns/manager&rID={$entry['metaID']}');"],'attr'=>['type'=>'button','value'=>"#{$meta['ref_num']}"]]);
            $rows[]= viewDashLink($left, $right, $action);
        }
        if (empty($rows)) { $rows[] = '<div><span>'.lang('no_results').'</span></div>'; }
        else {
            $output= sortOrder($rows, 'ref_num', strtolower($opts['order'])=='asc'?'asc':'desc');
            $rows[]= '<div><b>'.$this->lang['total_open']." ".sizeof($output)."</b></div>"; }
        return ['lists'=>$rows];
      }
}
