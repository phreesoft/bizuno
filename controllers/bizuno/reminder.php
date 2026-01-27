<?php
/*
 * This method handles user profiles
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
 * @version    7.x Last Update: 2025-10-26
 * @filesource /controllers/bizuno/reminder.php
 */

namespace bizuno;

class bizunoReminder extends mgrJournal
{
    public    $moduleID   = 'bizuno';
    public    $pageID     = 'reminder';
    protected $secID      = 'profile';
    protected $domSuffix  = 'Reminder';
    protected $metaPrefix = 'reminder';

    public function __construct()
    {
        parent::__construct();
        $this->mgrTitle= lang('my_reminders');
        $this->freqs   = getModuleCache('bizuno', 'options', 'frequencies');
        $this->args    = ['type'=>'div', '_table'=>'contacts', '_refID'=>getuserCache('profile', 'userID')];
        $this->managerSettings();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $this->struc = [ 
            '_rID'     => ['panel'=>'general','order'=> 1,                              'clean'=>'integer', 'attr'=>['type'=>'hidden','value'=>0]],
            'title'    => ['panel'=>'general','order'=>10,'label'=>lang('title'),       'clean'=>'text',    'attr'=>['type'=>'text',  'value'=>'', 'size'=>60]],
            'recur'    => ['panel'=>'general','order'=>20,'label'=>lang('frequency'),   'clean'=>'char',    'attr'=>['type'=>'select','value'=>'m'],'values'=>viewKeyDropdown($this->freqs)],
            'dateStart'=> ['panel'=>'general','order'=>70,'label'=>lang('date_created'),'clean'=>'dateMeta','attr'=>['type'=>'date',  'value'=>biz_date(), 'readonly'=>true]],
            'dateNext' => ['panel'=>'general','order'=>70,'label'=>lang('date_next'),   'clean'=>'dateMeta','attr'=>['type'=>'date',  'value'=>biz_date()]]];
    }
    public function managerGrid($security, $args)
    {
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'source' => ['search' => ['title']],
            'columns'=> [
                'title'    => ['order'=>10,'label'=>lang('title'),       'attr'=>['width'=>300,'resizable'=>true]],
                'recur'    => ['order'=>20,'label'=>lang('frequency'),   'attr'=>['width'=>100,'resizable'=>true],'events'=>['formatter'=>"function(value,row) { return fmtFreqs[value]; }"]],
                'dateStart'=> ['order'=>30,'label'=>lang('date_created'),'attr'=>['width'=>100,'resizable'=>true], 'format'=>'date'],
                'dateNext' => ['order'=>50,'label'=>lang('next_date'),   'attr'=>['width'=>100,'resizable'=>true], 'format'=>'date']]]);
        if (getUserCache('profile', 'device') == 'mobile') {
            $data['columns']['recur']['attr']['hidden']     = true;
            $data['columns']['dateStart']['attr']['hidden'] = true;
        }
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort'] = clean('sort', ['format'=>'cmd', 'default'=>'post_date'], 'post');
    }
    /******************************** Meta Entries ********************************/
    public function manager(&$layout=[])
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        parent::managerMain($layout, 4, ['dom'=>'div']);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']); // Don't allow copy here
        $layout['jsHead']['init'] = "var fmtFreqs = ".json_encode($this->freqs).";";
    }
    public function managerRows(&$layout=[])
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        $this->defaults['sort'] = clean('sort', ['format'=>'cmd',     'default'=>'title'],'post');
        $this->defaults['order']= clean('order',['format'=>'db_field','default'=>'ASC'],  'post');
        parent::mgrRowsMeta($layout, 1, $this->args);
        msgDebug("\nready to fetch rows with $layout = ".print_r($layout, true));
    }
    public function edit(&$layout=[])
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        parent::editMeta($layout, 4, $this->args);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow copy here
    }
    public function save(&$layout=[])
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        parent::saveMeta($layout, $this->args);
    }
    public function delete(&$layout=[])
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        parent::deleteMeta($layout, $this->args);
    }
}
