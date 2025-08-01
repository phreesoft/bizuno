<?php
/*
 * PhreeForm designer methods for report/form designing
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
 * @version    7.x Last Update: 2025-08-01
 * @filesource /controllers/phreeform/design.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreeform/functions.php', 'phreeformFonts', 'function');

final class phreeformDesign extends mgrJournal
{
    public    $moduleID  = 'phreeform';
    public    $pageID    = 'design';
    protected $secID     = 'phreeform';
    protected $domSuffix = 'Design';
    protected $metaPrefix= 'phreeform';

    function __construct()
    {
        parent::__construct();
        $this->mgrTitle = lang('reports');
        $this->critChoices = [0=>'2:all:range:equal', 1=>'0:yes:no', 2=>'0:all:yes:no', 3=>'0:all:active:inactive', 4=>'0:all:printed:unprinted', 5=>'', // unused
            6=>'1:equal', 7=>'2:range', 8=>'1:not_equal', 9=>'1:in_list', 10=>'1:less_than', 11=>'1:greater_than'];
        $rID  = 'save'==$GLOBALS['bizunoMethod'] ? clean('_rID', 'integer', 'post') : clean('rID', 'integer', 'get'); // if save then post else get
        $type = clean('type','db_field','get');
        if (!empty($rID)) { 
            $meta = dbMetaGet($rID, $this->metaPrefix);
            $type = $meta['type'];
        }
        $this->fieldStructure($type);
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure($type='rpt')
    {
        $selFont = phreeformFonts();
        $selSize = phreeformSizes();
        
        $selAlign= phreeformAligns();
        $emailChoices = [['id'=>'user', 'text'=>$this->lang['phreeform_current_user']], ['id'=>'gen', 'text'=>lang('contact_m')],
            ['id'=>'ap', 'text'=>lang('contact_p')], ['id'=>'ar', 'text'=>lang('contact_r')]];
        $this->struc = [
            // Tab: general - Panel: info 
            '_rID'          => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'integer' ,'attr'=>['type'=>'hidden', 'value'=>0]],
            'parent_id'     => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'integer' ,'attr'=>['type'=>'hidden', 'value'=>0]],
            'group_id'      => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'cmd'     ,'attr'=>['type'=>'hidden', 'value'=>'']],
            'mime_type'     => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'db_field','attr'=>['type'=>'hidden', 'value'=>'']],
            'xChild'        => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'db_field','attr'=>['type'=>'hidden', 'value'=>'']],
            'type'          => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'db_field','attr'=>['type'=>'hidden', 'value'=>'rpt']],
            'tables'        => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'json',    'attr'=>['type'=>'hidden']],
            'fieldlist'     => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'json',    'attr'=>['type'=>'hidden']],
            'grouplist'     => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'json',    'attr'=>['type'=>'hidden']],
            'sortlist'      => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'json',    'attr'=>['type'=>'hidden']],
            'filterlist'    => ['tab'=>'general', 'panel'=>'info', 'order'=> 1,                               'clean'=>'json',    'attr'=>['type'=>'hidden']],
            'title'         => ['tab'=>'general', 'panel'=>'info', 'order'=>10,'label'=>lang('title'),        'clean'=>'text',    'attr'=>['type'=>'text','size'=>64,'maxlength'=>64]],
            'group_id'      => ['tab'=>'general', 'panel'=>'info', 'order'=>20,'label'=>lang('group_list'),   'clean'=>'cmd',     'attr'=>['type'=>'select']],
            'description'   => ['tab'=>'general', 'panel'=>'info', 'order'=>30,'label'=>lang('description'),  'clean'=>'text',    'attr'=>['type'=>'textarea', 'cols'=>60, 'rows'=>4]],
            // Tab: general - Panel: email
//            'emailsubject'  => ['tab'=>'general', 'panel'=>'email','order'=>10,'label'=>lang('email_subject'),'clean'=>'text',    'attr'=>['type'=>'text','size'=>64,'maxlength'=>64]],
            'emailmessage'  => ['tab'=>'general', 'panel'=>'email','order'=>20,                               'clean'=>'text',    'attr'=>['type'=>'editor']],
            // Tab: general - Panel: page
            'pagesize'      => ['tab'=>'general', 'panel'=>'page',      'order'=>50,'label'=>$this->lang['phreeform_paper_size'],   'options'=>['width'=>150],'values'=>phreeformPages($this->lang),
                'clean'=>'cmd', 'attr'=>['type'=>'select',  'value'=>'LETTER:216:279']],
            'pageorient'    => ['tab'=>'general', 'panel'=>'page',      'order'=>52,'break'=>true,'label'=>$this->lang['phreeform_orientation'],  'options'=>['width'=>100],'values'=>phreeformOrientation($this->lang),
                'clean'=>'char', 'attr'=>['type'=>'select',  'value'=>'P']],
            'margintop'     => ['tab'=>'general', 'panel'=>'page',      'order'=>54,'label'=>$this->lang['phreeform_margin_top'],   'options'=>['width'=>50],'styles'=>['text-align'=>'right'],
                'clean'=>'integer', 'attr'=>['size'=>'4','maxlength'=>'3','value'=>8]],
            'marginbottom'  => ['tab'=>'general', 'panel'=>'page',      'order'=>55,'label'=>$this->lang['phreeform_margin_bottom'],'options'=>['width'=>50],'styles'=>['text-align'=>'right'],
                'clean'=>'integer', 'attr'=>['size'=>'4','maxlength'=>'3','value'=>8]],
            'marginleft'    => ['tab'=>'general', 'panel'=>'page',      'order'=>56,'label'=>$this->lang['phreeform_margin_left'],  'options'=>['width'=>50],'styles'=>['text-align'=>'right'],
                'clean'=>'integer', 'attr'=>['size'=>'4','maxlength'=>'3','value'=>8]],
            'marginright'   => ['tab'=>'general', 'panel'=>'page',      'order'=>57,'label'=>$this->lang['phreeform_margin_right'], 'options'=>['width'=>50],'styles'=>['text-align'=>'right'],
                'clean'=>'integer', 'attr'=>['size'=>'4','maxlength'=>'3','value'=>8]],
            'users'         => ['tab'=>'settings','panel'=>'security',  'order'=>10,'label'=>lang('users'),'options'=>['multiple'=>'true'],'values'=>listUsers(),
                'clean'=>'array', 'attr'=>['type'=>'select','name'=>'users[]','value'=>[]]],
            'roles'         => ['tab'=>'settings','panel'=>'security',  'order'=>20,'label'=>lang('roles'),'options'=>['multiple'=>'true'],'values'=>listRoles(),
                'clean'=>'array', 'attr'=>['type'=>'select','name'=>'roles[]','value'=>[]]],
            'restrict_rep'  => ['tab'=>'settings','panel'=>'security',  'order'=>65,'label'=>$this->lang['lbl_restrict_rep'],'clean'=>'boolean', 'attr'=>['type'=>'checkbox','checked'=>false]],
            // Tab: filters - Panel:
            'dateperiod'    => ['tab'=>'filters', 'panel'=>'date_range','order'=>25,                     'clean'=>'char', 'attr'=>['type'=>'radio', 'value'=>'d']],
            'datelist'      => ['tab'=>'filters', 'panel'=>'date_range','order'=>25,'position'=>'after', 'clean'=>'array','attr'=>['value'=>['a']]],
            'datefield'     => ['tab'=>'filters', 'panel'=>'date_range','order'=>25,'label'=>$this->lang['phreeform_date_field'],'options'=>['url'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields'",'editable'=>'true','valueField'=>"'id'",'textField'=>"'text'",'mode'=>"'remote'",'width'=>300],
                'clean'=>'db_field', 'attr'=> ['type'=>'select']],
            'datedefault'   => ['tab'=>'filters', 'panel'=>'date_range','order'=>25,'label'=>$this->lang['date_default_selected'],'values'=>viewDateChoices(),  'clean'=>'char', 'attr'=>['type'=>'select', 'value'=>'c']],
            // Tab: settings - Panel: options
            'defaultemail'  => ['tab'=>'settings','panel'=>'options',   'order'=>35,'label'=>$this->lang['lbl_phreeform_email'],  'values'=>$emailChoices, 'clean'=>'db_field', 'attr' => ['type'=>'select','value'=>'user']],
            'filenamefield' => ['tab'=>'settings','panel'=>'options',   'order'=>45,'label'=>lang('fieldname'),'options'=>['url'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields'",'editable'=>'true','valueField'=>"'id'",'textField'=>"'text'",'mode'=>"'remote'",'width'=>300],
                'clean'=>'filename', 'attr'=> ['type'=>'select']],
            'filenameprefix'=> ['tab'=>'settings','panel'=>'options',   'order'=>50,'label'=>lang('prefix'),                 'clean'=>'filename', 'attr'=>['size'=>10]],
            // Tab: settings - Panel: advanced
            'special_class' => ['tab'=>'settings','panel'=>'advanced',  'order'=>90,'label'=>$this->lang['phreeform_special_class'], 'clean'=>'db_field', 'attr'=>[]]];
        if (in_array($type, ['rpt', 'lst'])) { $this->struc = array_merge($this->struc, [
            'truncate'      => ['tab'=>'settings','panel'=>'options','order'=>20,'label'=>lang('truncate_fit'),   'clean'=>'boolean', 'attr'=>['type'=>'selNoYes']],
            'totalonly'     => ['tab'=>'settings','panel'=>'options','order'=>25,'label'=>$this->lang['show_total_only'],'clean'=>'boolean', 'attr'=>['type'=>'selNoYes']],
            'headingshow'   => ['tab'=>'general', 'panel'=>'heading','order'=>10,'clean'=>'integer', 'attr'=>['type'=>'selNoYes']],
            'headingfont'   => ['tab'=>'general', 'panel'=>'heading','order'=>12,'values'=>$selFont, 'options'=>['width'=>150], 'clean'=>'db_field', 'attr'=>['type'=>'select','value'=>'helvetica']],
            'headingsize'   => ['tab'=>'general', 'panel'=>'heading','order'=>14,'values'=>$selSize, 'options'=>['width'=> 75], 'clean'=>'integer', 'attr'=>['type'=>'select','value'=>12]],
            'headingcolor'  => ['tab'=>'general', 'panel'=>'heading','order'=>16,'options'=>['width'=>70], 'clean'=>'text', 'attr'=>['type'=>'color','value'=>'#000000']],
            'headingalign'  => ['tab'=>'general', 'panel'=>'heading','order'=>18,'values'=>$selAlign,'options'=>['width'=>100], 'clean'=>'char', 'attr'=>['type'=>'select','value'=>'C']],
            'title1show'    => ['tab'=>'general', 'panel'=>'heading','order'=>20,'clean'=>'integer', 'attr'=>['type'=>'selNoYes']],
            'title1text'    => ['tab'=>'general', 'panel'=>'heading','order'=>22,'clean'=>'text',    'attr'=>['type'=>'text', 'value'=>'%reportname%']],
            'title1font'    => ['tab'=>'general', 'panel'=>'heading','order'=>24,'values'=>$selFont, 'options'=>['width'=>150],'clean'=>'db_field', 'attr'=>['type'=>'select','value'=>'helvetica']],
            'title1size'    => ['tab'=>'general', 'panel'=>'heading','order'=>26,'values'=>$selSize, 'options'=>['width'=> 75], 'clean'=>'integer', 'attr'=>['type'=>'select','value'=>10]],
            'title1color'   => ['tab'=>'general', 'panel'=>'heading','order'=>28,'options'=>['width'=>70], 'clean'=>'text', 'attr'=>['type'=>'color','value'=>'#000000']],
            'title1align'   => ['tab'=>'general', 'panel'=>'heading','order'=>30,'values'=>$selAlign,'options'=>['width'=>100], 'clean'=>'char', 'attr'=>['type'=>'select', 'value'=>'C']],
            'title2show'    => ['tab'=>'general', 'panel'=>'heading','order'=>32,'clean'=>'integer', 'attr'=>['type'=>'selNoYes']],
            'title2text'    => ['tab'=>'general', 'panel'=>'heading','order'=>34,'clean'=>'text',    'attr'=>['type'=>'text', 'value'=>'Report Generated %date%']],
            'title2font'    => ['tab'=>'general', 'panel'=>'heading','order'=>36,'values'=>$selFont, 'options'=>['width'=>150], 'clean'=>'db_field', 'attr'=>['type'=>'select','value'=>'helvetica']],
            'title2size'    => ['tab'=>'general', 'panel'=>'heading','order'=>38,'values'=>$selSize, 'options'=>['width'=> 75],  'clean'=>'integer', 'attr'=>['type'=>'select','value'=>10]],
            'title2color'   => ['tab'=>'general', 'panel'=>'heading','order'=>40,'options'=>['width'=>70], 'clean'=>'text',  'attr'=>['type'=>'color','value'=>'#000000']],
            'title2align'   => ['tab'=>'general', 'panel'=>'heading','order'=>42,'values'=>$selAlign,'options'=>['width'=>100], 'clean'=>'text',  'attr'=>['type'=>'select','C']],
            'filterfont'    => ['tab'=>'general', 'panel'=>'heading','order'=>44,'values'=>$selFont, 'options'=>['width'=>150], 'clean'=>'db_field',  'attr'=>['type'=>'select','value'=>'helvetica']],
            'filtersize'    => ['tab'=>'general', 'panel'=>'heading','order'=>46,'values'=>$selSize, 'options'=>['width'=> 75], 'clean'=>'integer',  'attr'=>['type'=>'select','value'=>8]],
            'filtercolor'   => ['tab'=>'general', 'panel'=>'heading','order'=>48,'options'=>['width'=>70],  'clean'=>'text', 'attr'=>['type'=>'color','value'=>'#000000']],
            'filteralign'   => ['tab'=>'general', 'panel'=>'heading','order'=>50,'values'=>$selAlign,'options'=>['width'=>100],  'clean'=>'char', 'attr'=>['type'=>'select','value'=>'L']],
            'datafont'      => ['tab'=>'general', 'panel'=>'heading','order'=>52,'values'=>$selFont, 'options'=>['width'=>150],  'clean'=>'db_field', 'attr'=>['type'=>'select','value'=>'helvetica']],
            'datasize'      => ['tab'=>'general', 'panel'=>'heading','order'=>54,'values'=>$selSize, 'options'=>['width'=> 75],  'clean'=>'integer', 'attr'=>['type'=>'select','value'=>10]],
            'datacolor'     => ['tab'=>'general', 'panel'=>'heading','order'=>56,'options'=>['width'=>70],    'clean'=>'text', 'attr'=>['type'=>'color','value'=>'#000000']],
            'dataalign'     => ['tab'=>'general', 'panel'=>'heading','order'=>58,'values'=>$selAlign,'options'=>['width'=>100], 'clean'=>'char', 'attr'=>['type'=>'select','value'=>'C']],
            'totalfont'     => ['tab'=>'general', 'panel'=>'heading','order'=>60,'values'=>$selFont, 'options'=>['width'=>150], 'clean'=>'db_field', 'attr'=>['type'=>'select','value'=>'helvetica']],
            'totalsize'     => ['tab'=>'general', 'panel'=>'heading','order'=>62,'values'=>$selSize, 'options'=>['width'=> 75], 'clean'=>'integer', 'attr'=>['type'=>'select','value'=>10]],
            'totalcolor'    => ['tab'=>'general', 'panel'=>'heading','order'=>64,'options'=>['width'=>70], 'clean'=>'text', 'attr'=>['type'=>'color','value'=>'#000000']],
            'totalalign'    => ['tab'=>'general', 'panel'=>'heading','order'=>66,'values'=>$selAlign,'options'=>['width'=>100], 'clean'=>'char', 'attr'=>['type'=>'select','value'=>'L']]]);
        }
        if (in_array($type, ['frm'])) { $this->struc = array_merge($this->struc, [
            // Tab: Settings - Panel: settings
            'formbreakfield'=> ['tab'=>'settings','panel'=>'options','order'=>10,'label'=>$this->lang['page_break_field'],'options'=>['url'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields'",'editable'=>'true','valueField'=>"'id'",'textField'=>"'text'",'mode'=>"'remote'",'width'=>300],
                'clean'=>'db_field', 'attr'=> ['type'=>'select']],
            'skipnullfield' => ['tab'=>'settings','panel'=>'options','order'=>15,'label'=>$this->lang['lbl_skip_null'],'options'=>['url'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields'",'editable'=>'true','valueField'=>"'id'",'textField'=>"'text'",'mode'=>"'remote'",'width'=>300],
                'clean'=>'db_field', 'attr'=> ['type'=>'select']],
            'contactlog'    => ['tab'=>'settings','panel'=>'options','order'=>20,'label'=>$this->lang['lbl_phreeform_contact'],'options'=>['url'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields'",'editable'=>'true','valueField'=>"'id'",'textField'=>"'text'",'mode'=>"'remote'",'width'=>300],
                'clean'=>'db_field', 'attr'=> ['type'=>'select']],
            'printedfield'  => ['tab'=>'settings','panel'=>'options','order'=>25,'label'=>$this->lang['lbl_set_printed_flag'],'options'=>['url'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields'",'editable'=>'true','valueField'=>"'id'",'textField'=>"'text'",'mode'=>"'remote'",'width'=>300],
                'clean'=>'db_field', 'attr'=> ['type'=>'select']],
            'breakfield'    => ['tab'=>'settings','panel'=>'options','order'=>30,'label'=>lang('phreeform_field_break'),  'clean'=>'db_field', 'attr'=>['maxlength'=>64]],
            'serialform'    => ['tab'=>'settings','panel'=>'options','order'=>35,'label'=>$this->lang['lbl_serial_form'], 'clean'=>'boolean', 'attr'=>['type'=>'checkbox','checked'=>false]]]);
        }
    }

    /**
     * Generates the structure to render a report/form editor
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function edit(&$layout=[])
    {
        $rID  = clean('rID', 'integer','get');
        $type = clean('type','cmd',    'get');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        $meta = ['title'=>lang('new_report'), 'type'=>$type, 'users'=>[], 'roles'=>[], 'group_id'=>in_array($type, ['frm', 'lst'])?"misc:misc":"misc:$type",
            'tables'=>[], 'fieldlist'=>[], 'grouplist'=>[], 'sortlist'=>[], 'filterlist'=>[]];
        if (!empty($rID)) { 
            $meta = dbMetaGet($rID, $this->metaPrefix);
            $type = $meta['type'];
        }
        $args = ['dom'=>'page', 'title'=>lang('design')." - {$meta['title']}"];
        parent::editMeta($layout, $security, $args);
        switch($type) {
            case 'frm':
            case 'lst': $groups = getModuleCache('phreeform', 'frmGroups'); break;
            default:    $groups = getModuleCache('phreeform', 'rptGroups'); break; // default to report
        }
        $layout['fields']['group_id']['values'] = $groups;
        unset($layout['fields']['xChild']['attr']['value']); // clear the preview flag
        unset($layout['tabs']['filters'], $layout['panels']['date_range'], $layout['tabs']["tab{$this->domSuffix}"]['divs']['filters']); // special overrides as they are generated manually
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['trash']);
        $data   = [ // 'reportType'=>$type,
            'toolbars'  => ["tb{$this->domSuffix}"=>['icons'=>[
                'back'   => ['order'=>10,'events'=>['onClick'=>"location.href='".BIZUNO_HOME."&bizRt=$this->moduleID/main/manager'"]],
                'preview'=> ['order'=>30,'events'=>['onClick'=>"jqBiz('#xChild').val('print'); jqBiz('#frm{$this->domSuffix}').submit();"]]]]],
            'tabs'      => ["tab{$this->domSuffix}"=>['divs'=>[
                'general' => ['divs'=>[ // 'order'=>10,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView'],
                    'heading' => ['order'=>40,'type'=>'panel','key'=>'heading', 'classes'=>['block66']]]],
                'db'      => ['order'=>20,'label'=>$this->lang['phreeform_title_db'],   'type'=>'datagrid', 'key'=>'tables'],
                'fields'  => ['order'=>30,'label'=>$this->lang['phreeform_title_field'],'type'=>'datagrid', 'key'=>'fields'],
                'filters' => ['order'=>40,'label'=>lang('filters'),'type'=>'divs','divs'=>[
                    'fields'  => ['order'=>10,'type'=>'panel',   'key'=>'dates','classes'=>['block99']], // ['order'=>20,'type'=>'html','html'=>$this->getViewFilters($this->struc, $type)],
                    'dgSort'  => ['order'=>40,'type'=>'datagrid','key'=>'sort'],
                    'dgGroups'=> ['order'=>50,'type'=>'datagrid','key'=>'groups'],
                    'dgFilter'=> ['order'=>60,'type'=>'datagrid','key'=>'filters']]]]]],
            'panels' => [
                'dates'   => ['label'=>$this->lang['phreeform_date_info'],  'type'=>'html','html'=>$this->getViewFilters($this->struc, $type)],
                'heading' => ['label'=>$this->lang['phreeform_header_info'],'type'=>'html','html'=>$this->getViewPage($this->struc)]],
            'datagrid'  => [
                'tables' => $this->dgTables ('dgTables'),
                'fields' => $this->dgFields ('dgFields', $type),
                'sort'   => $this->dgOrder  ('dgSort',   $type),
                'groups' => $this->dgGroups ('dgGroups', $type),
                'filters'=> $this->dgFilters('dgFilters',$type)],
            'jsHead'    => [
                'preSubmit'  => $this->getViewEditJS(),
                'fonts'      => "var dataFonts = "     .json_encode(phreeformFonts())     .";",
                'sizes'      => "var dataSizes = "     .json_encode(phreeformSizes())     .";",
                'aligns'     => "var dataAligns = "    .json_encode(phreeformAligns())    .";",
                'types'      => "var dataTypes = "     .json_encode($this->phreeformTypes()).";",
                'barcodes'   => "var dataBarCodes = "  .json_encode(phreeformBarCodes())  .";",
                'processing' => "var dataProcessing = ".json_encode(pfSelProcessing())    .";",
                'formatting' => "var dataFormatting = ".json_encode(phreeformFormatting()).";",
                'separators' => "var dataSeparators = ".json_encode(phreeformSeparators()).";",
                'bizData'    => "var bizData = "       .json_encode(phreeformCompany())   .";",
                'fTypes'     => "var filterTypes = "   .json_encode($this->filterTypes($this->critChoices)).";",
                'dataTables' => "var dataTables = "    .json_encode($meta['tables']).";",
                'dataFields' => "var dataFields = "    .json_encode($meta['fieldlist']).";",
                'dataGroups' => "var dataGroups = "    .json_encode($meta['grouplist']).";",
                'dataOrder'  => "var dataOrder = "     .json_encode($meta['sortlist']).";",
                'dataFilters'=> "var dataFilters = "   .json_encode($meta['filterlist']).";"],
            'jsReady'   => [
                'preflight'=> "pfTableUpdate();",
                'dragNdrop'=> "jqBiz('#dgTables').datagrid('enableDnd'); jqBiz('#dgFields').datagrid('enableDnd'); jqBiz('#dgGroups').datagrid('enableDnd'); jqBiz('#dgSort').datagrid('enableDnd'); jqBiz('#dgFilters').datagrid('enableDnd');",
                'secUser'  => "jqBiz('#users').combobox('setValue', ".json_encode($meta['users']).");",
                'secGroup' => "jqBiz('#roles').combobox('setValue', ".json_encode($meta['roles']).");"]];
        if ('rpt'<>$type) { unset($data['tabs']["tab{$this->domSuffix}"]['divs']['general']['divs']['heading']); }
        $tmp = []; // set the session tables for dynamic field generation
        if (isset($meta['tables']['rows']) && is_array($meta['tables']['rows'])) { foreach ($meta['tables']['rows'] as $table) { $tmp[] = $table['tablename']; } }
        setModuleCache('phreeform', 'designCache', 'tables', $tmp);
        $layout = array_replace_recursive($layout, $data);
    }

    private function getViewEditJS()
    {
        return "function preSubmit() {
    jqBiz('#dgTables').edatagrid('saveRow');
    if (jqBiz('#dgTables').length)  jqBiz('#tables').val(JSON.stringify(jqBiz('#dgTables').datagrid('getData')));
    jqBiz('#dgFields').edatagrid('saveRow');
    if (jqBiz('#dgFields').length)  jqBiz('#fieldlist').val(JSON.stringify(jqBiz('#dgFields').datagrid('getData')));
    jqBiz('#dgGroups').edatagrid('saveRow');
    if (jqBiz('#dgGroups').length)  jqBiz('#grouplist').val(JSON.stringify(jqBiz('#dgGroups').datagrid('getData')));
    jqBiz('#dgSort').edatagrid('saveRow');
    if (jqBiz('#dgSort').length)    jqBiz('#sortlist').val(JSON.stringify(jqBiz('#dgSort').datagrid('getData')));
    jqBiz('#dgFilters').edatagrid('saveRow');
    if (jqBiz('#dgFilters').length) jqBiz('#filterlist').val(JSON.stringify(jqBiz('#dgFilters').datagrid('getData')));
    return true;
}
function pfTableUpdate() {
    var table  = '';
    var rowData= jqBiz('#dgTables').edatagrid('getData');
    for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) table += rowData.rows[rowIndex].tablename + ':';
    jsonAction('$this->moduleID/$this->pageID/getTablesSession', 0, table);
}";
    }

    private function getViewPage($fields)
    {
        msgDebug("\nEntering getViewPage with type = {$fields['type']['attr']['value']}");
        if (!in_array($fields['type']['attr']['value'], ['rpt', 'lst'])) { return; }
        return '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">
    <thead class="panel-header">
        <tr><th>&nbsp;</th><th>'.lang('show') ."</th><th>".lang('font') ."</th><th>".lang('size') ."</th><th>".lang('color')."</th><th>".lang('align').'</th></tr>
    </thead>
    <tbody>
        <tr><td>'.$this->lang['name_business'].'</td><td>'.html5('headingshow', $fields['headingshow']) .'</td><td>'.html5('headingfont', $fields['headingfont']) .'</td><td>'.html5('headingsize', $fields['headingsize']) .'</td><td>'.html5('headingcolor',$fields['headingcolor']).'</td><td>'.html5('headingalign',$fields['headingalign']).'</td></tr>
        <tr><td>'.$this->lang['phreeform_page_title1'].' '.html5('title1text',$fields['title1text']).'</td><td>'.html5('title1show', $fields['title1show']) .'</td><td>'.html5('title1font', $fields['title1font']) .'</td><td>'.html5('title1size', $fields['title1size']) .'</td><td>'.html5('title1color',$fields['title1color']).'</td><td>'.html5('title1align',$fields['title1align']).'</td></tr>
        <tr><td>'.$this->lang['phreeform_page_title2'].' '.html5('title2text',$fields['title2text']).'</td><td>'.html5('title2show', $fields['title2show']) .'</td><td>'.html5('title2font', $fields['title2font']) .'</td><td>'.html5('title2size', $fields['title2size']) .'</td><td>'.html5('title2color',$fields['title2color']).'</td><td>'.html5('title2align',$fields['title2align']).'</td></tr>
        <tr><td colspan="2">'.$this->lang['phreeform_filter_desc'].'</td><td>'.html5('filterfont',$fields['filterfont']).'</td><td>'.html5('filtersize',$fields['filtersize']).'</td><td>'.html5('filtercolor',$fields['filtercolor']).'</td><td>'.html5('filteralign',$fields['filteralign']).'</td></tr>
        <tr><td colspan="2">'.$this->lang['phreeform_heading']    .'</td><td>'.html5('datafont',  $fields['datafont'])  .'</td><td>'.html5('datasize',  $fields['datasize'])  .'</td><td>'.html5('datacolor',  $fields['datacolor'])  .'</td><td>'.html5('dataalign',  $fields['dataalign'])  .'</td></tr>
        <tr><td colspan="2">'.lang('totals').'</td><td>'.html5('totalfont', $fields['totalfont']) .'</td><td>'.html5('totalsize', $fields['totalsize']) .'</td><td>'.html5('totalcolor',$fields['totalcolor']).'</td><td>'.html5('totalalign',$fields['totalalign']).'</td></tr>
    </tbody>
</table>';
    }

    private function getViewFilters($fields, $type)
    {
        // build the date checkboxes
        $dateList = '<tr>';
        $cnt = 0;
        msgDebug("\ndatelist coming in = ".print_r($fields['datelist'], true));
        foreach (viewDateChoices() as $value) {
            msgDebug("\nchoice value = ".print_r($value, true));
            $cbHTML = $fields['datelist'];
            $cbHTML['label']         = $value['text'];
            $cbHTML['attr']['type']  = 'checkbox';
            $cbHTML['attr']['value'] = $value['id'];
            $cbHTML['attr']['checked'] = in_array($value['id'], $fields['datelist']['attr']['value']) ? true : false;
            $dateList .= '<td>'.html5('datelist[]', $cbHTML).'</td>';
            $cnt++;
            if ($cnt > 2) { $cnt=0; $dateList .= "</tr><tr>\n"; } // set for 3 columns
        }
        $dateList.= "</tr>\n";
        $output  = '<table style="border-style:none;width:100%">'."\n";
        $output .= '  <tbody>'."\n";
        $dateType = $fields['dateperiod']['attr']['value'];
        if ($dateType == 'p') { $fields['dateperiod']['attr']['checked'] = true; }
        else                  { unset($fields['dateperiod']['attr']['checked']); }
        $fields['dateperiod']['attr']['value'] = 'p';
        $output .= '    <tr><td colspan="3">'.html5('dateperiod', $fields['dateperiod']).' '.$this->lang['use_periods']."</td></tr>\n";
        $output .= '    <tr><td colspan="3">'."<hr></td></tr>\n";
        if ($dateType == 'd') { $fields['dateperiod']['attr']['checked'] = true; }
        else                  { unset($fields['dateperiod']['attr']['checked']); }
        $fields['dateperiod']['attr']['value'] = 'd';
        $output .= '    <tr><td colspan="3">'.html5('dateperiod', $fields['dateperiod']).' '.$this->lang['phreeform_date_list']."</td></tr>\n";
        $output .= $dateList."\n";
        $output .= '    <tr><td colspan="2">'.html5('datedefault', $fields['datedefault'])."</td>\n";
        $output .= "        <td>".html5('datefield', $fields['datefield'])."</td></tr>\n";
        $output .= "  </tbody>\n";
        $output .= "</table>\n\n";
//      $output .= '<u><b>'.lang('notes').'</b></u>'; // no notes so don't show anything
        return $output;
    }

    /**
     * Generates the list of filters sourced by $arrValues
     * @param array $arrValues -
     * @return type
     */
    private function filterTypes($arrValues)
    {
        $output = [];
        foreach ($arrValues as $key => $value) {
            $value = substr($value, 2);
            $temp = explode(':', $value);
            $words = [];
            foreach ($temp as $word) { $words[] = !empty($this->lang[$word]) ? $this->lang[$word] : lang($word); }
            $output[] = ['id'=>"$key", 'text'=>implode(':', $words)];
        }
        return $output;
    }

    /**
     * Generates the structure for saving a report/form after editing
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function save(&$layout=[])
    {
        msgTrap();
        $rID   = clean('_rID', 'integer', 'post');
        $xChild= clean('xChild', ['format'=>'text', 'default'=>false], 'post'); // set if preview button was pressed
        $_POST['xChild']='';
        if (!$security = validateAccess($this->secID, empty($rID)?2:3)) { return; }
        $this->preProcessFields();
        parent::saveMeta($layout, $args=['_rID'=>$rID]);
        if (empty($rID)) { $rID = clean('id', 'integer', 'post'); } // for inserts
        $jsonAction = "jqBiz('#id').val($rID);";
        switch ($xChild) { // child screens to spawn
            case 'print': $jsonAction .= " winOpen('phreeformOpen', '$this->moduleID/render/open&rID=$rID');"; break;
        }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>$jsonAction]]);
    }

    /**
     * fieldlist setting comes in json encoded, need to decode it before processing.
     */
    private function preProcessFields()
    {
        if ('p'==clean('dateperiod', 'char', 'post')) { $_POST['datelist'] = ['z']; } // date choices
        $fields = clean('fieldlist', 'json', 'post');
        foreach ($fields['rows'] as $key => $field) {
            if (isset($field['settings']) && is_string($field['settings'])) { $fields['rows'][$key]['settings'] = json_decode($field['settings']); }
        }
        $_POST['fieldlist'] = json_encode($fields);
    }

    /**
     * Generates the structure for the datagrid for report/form tables
     * @param string $name - DOM field name
     * @return array - structure ready to render
     */
    private function dgTables($name)
    {
        return ['id'=>$name, 'type'=>'edatagrid', 'tip'=>$this->lang['tip_phreeform_database_syntax'],
            'attr'  => ['toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'singleSelect'=>true],
            'events'=> ['data'=> 'dataTables',
                'onAfterEdit' => "function(rowIndex, rowData, changes) { pfTableUpdate(); }"],
            'source' => [
                'actions'=>[
                    'new'   =>['order'=>10,'icon'=>'add',   'events'=>['onClick'=>"bizGridAddRow('$name');"]],
                    'verify'=>['order'=>20,'icon'=>'verify','events'=>['onClick'=>"verifyTables();"]]]],
            'columns' => [
                'action'      => ['order'=>1,'label'=>lang('action'),'attr'=>['width'=>45],
                    'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions' => [
                        'tblEdit' =>['order'=>40,'icon'=>'edit', 'events'=>['onClick'=>"var row = jqBiz('#$name').datagrid('getSelected'); jqBiz('#$name').edatagrid('editRow', jqBiz('#$name').datagrid('getRowIndex', row));"]],
                        'tblTrash'=>['order'=>80,'icon'=>'trash','events'=>['onClick'=>"alert('destroying row'); jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'join_type'   => ['order'=>10, 'label'=>$this->lang['join_type'], 'attr'=>['width'=>100, 'resizable'=>true],
                    'events'  => ['editor'=>"{type:'combobox',options:{editable:false,mode:'remote',url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getTablesJoin',valueField:'id',textField:'text'}}"]],
                'tablename'   => ['order'=>20, 'label'=>$this->lang['table_name'], 'attr'=>['width'=>200, 'resizable'=>true],
                    'events'  => ['editor'=>"{type:'combobox',options:{mode:'remote',url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getTables',valueField:'id',textField:'text'}}"]],
                'relationship'=> ['order'=>30, 'label'=>lang('relationship'), 'attr'=>['width'=>300,'resizable'=>true,'editor'=>'text']]]];
    }

    /**
     * Pulls the table fields used to build the selection list for report fields
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function getTables(&$layout)
    {
        $tables = [];
        $stmt   = dbGetResult("SHOW TABLES LIKE '".BIZUNO_DB_PREFIX."%'");
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        msgDebug("\nTables array returned = ".print_r($result, true));
        foreach ($result as $value) {
            $table = str_replace(BIZUNO_DB_PREFIX, '', array_shift($value));
            $tables[] = '{"id":"'.$table.'","text":"'.lang($table).'"}';
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>"[".implode(',',$tables)."]"]);
    }

    /**
     * This function collects the current list of tables during an edit in a session variable for dynamic field list generation
     */
    public function getTablesSession()
    {
        $data = clean('data', 'text', 'get');
        $tmp = [];
        $tables = explode(":", $data);
        if (sizeof($tables) > 0) { foreach ($tables as $table) {
            if ($table) { $tmp[] = $table; }
        } }
        setModuleCache('phreeform', 'designCache', 'tables', $tmp);
    }

    /**
     * Sets the selection choices for tables when one or more are added to the report/form
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function getTablesJoin(&$layout)
    {
        $content = '[
              { "id":"JOIN",                    "text":"JOIN", "selected":true},
              { "id":"LEFT JOIN",               "text":"LEFT JOIN"},
              { "id":"RIGHT JOIN",              "text":"RIGHT JOIN"},
              { "id":"INNER JOIN",              "text":"INNER JOIN"},
              { "id":"CROSS JOIN",              "text":"CROSS JOIN"},
              { "id":"STRAIGHT_JOIN",           "text":"STRAIGHT JOIN"},
              { "id":"LEFT OUTER JOIN",         "text":"LEFT OUTER JOIN"},
              { "id":"RIGHT OUTER JOIN",        "text":"RIGHT OUTER JOIN"},
              { "id":"NATURAL LEFT JOIN",       "text":"NATURAL LEFT JOIN"},
              { "id":"NATURAL RIGHT JOIN",      "text":"NATURAL RIGHT JOIN"},
              { "id":"NATURAL LEFT OUTER JOIN", "text":"NATURAL LEFT OUTER JOIN"},
              { "id":"NATURAL RIGHT OUTER JOIN","text":"NATURAL RIGHT OUTER JOIN"}
            ]';
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>$content]);
    }

    /**
     * Generates the structure for the datagrid for report/form item fields
     * @param string $name - DOM field name
     * @return array - datagrid structure ready to render
     */
    private function dgFields($name, $type='rpt')
    {
        $data = ['id'=>$name, 'type'=>'edatagrid', 'tip'=>$this->lang['tip_phreeform_field_settings'],
            'attr'  => ['toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'singleSelect'=>true],
            'events'=> ['data'=> "dataFields"],
            'source'=> ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]]];
        switch ($type) {
            case 'frm':
            case 'ltr':
                $data['columns'] = [
                    'action' => ['order'=>1,'label'=>lang('action'),'attr'=>['width'=>45],
                        'events'=>['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                        'actions'=> [
                            'flsProp' => ['order'=>20,'icon'=>'settings','events'=>['onClick'=>"jqBiz('#dgFields').datagrid('acceptChanges');
    var rowIndex= jqBiz('#$name').datagrid('getRowIndex', jqBiz('#$name').datagrid('getSelected'));
    var rowData = jqBiz('#dgFields').datagrid('getData');
    jsonAction('$this->moduleID/$this->pageID/getFieldSettings', rowIndex, JSON.stringify(rowData.rows[rowIndex]));"]],
                            'fldEdit' => ['order'=>40,'icon'=>'edit',    'events'=>['onClick'=>"var row = jqBiz('#$name').datagrid('getSelected'); jqBiz('#$name').edatagrid('editRow', jqBiz('#$name').datagrid('getRowIndex', row));"]],
                            'fldTrash'=> ['order'=>80,'icon'=>'trash',   'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                    'boxfield'=> ['order'=> 0,'attr'=>['type'=>'textarea', 'hidden'=>'true']],
                    'title'   => ['order'=>20,'label'=>lang('title'), 'attr'=>['width'=>200,'resizable'=>true,'editor'=>'text']],
                    'abscissa'=> ['order'=>30,'label'=>$this->lang['abscissa'],'attr'=>['width'=> 80,'resizable'=>true,'editor'=>'text']],
                    'ordinate'=> ['order'=>40,'label'=>$this->lang['ordinate'],'attr'=>['width'=> 80,'resizable'=>true,'editor'=>'text']],
                    'width'   => ['order'=>50,'label'=>lang('width'), 'attr'=>['width'=> 80,'resizable'=>true,'editor'=>'text']],
                    'height'  => ['order'=>60,'label'=>lang('height'),'attr'=>['width'=> 80,'resizable'=>true,'editor'=>'text']],
                    'type'    => ['order'=>70,'label'=>lang('type'),  'attr'=>['width'=>200,'resizable'=>true],
                        'events'=>  [
                            'editor'   =>"{type:'combobox',options:{editable:false,valueField:'id',textField:'text',data:dataTypes}}",
                            'formatter'=>"function(value,row){ return getTextValue(dataTypes, value); }"]]];
                break;
            case 'rpt':
            default:
                $data['columns'] = [
                    'action'     => ['order'=> 1, 'label'=>lang('action'),             'attr'=>['width'=>45],'events'=>['formatter'=>$name.'Formatter'],
                        'actions'=> [
                            'fldEdit' => ['order'=>40,'icon'=>'edit', 'events'=>['onClick'=>"var row = jqBiz('#$name').datagrid('getSelected'); jqBiz('#$name').edatagrid('editRow', jqBiz('#$name').datagrid('getRowIndex', row));"]],
                            'fldTrash'=> ['order'=>80,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                    'fieldname'  => ['order'=> 5, 'label'=>lang('fieldname'),          'attr'=>['width'=>200, 'resizable'=>true],
                        'events'=> ['editor'=>"{type:'combobox',options:{editable:true,mode:'remote',url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields',valueField:'id',textField:'text'}}"]],
                    'title'      => ['order'=>10, 'label'=>lang('title'),              'attr'=>['width'=>150, 'resizable'=>true, 'editor'=>'text']],
                    'break'      => ['order'=>20, 'label'=>$this->lang['column_break'],'attr'=>['width'=> 80, 'resizable'=>true],
                        'events'=>  ['editor'=>"{type:'checkbox',options:{on:'1',off:''}}"]],
                    'width'      => ['order'=>30, 'label'=>lang('width'),              'attr'=>['width'=> 80, 'resizable'=>true,'editor'=>'text']],
                    'widthTotal' => ['order'=>40, 'label'=>$this->lang['total_width'], 'attr'=>['width'=> 80, 'resizable'=>true]],
                    'visible'    => ['order'=>50, 'label'=>lang('show'),               'attr'=>['width'=> 50, 'resizable'=>true],
                        'events'=> ['editor'=>"{type:'checkbox',options:{on:'1',off:''}}"]],
                    'processing' => ['order'=>60, 'label'=> $this->lang['processing'], 'attr'=>['width'=>160, 'resizable'=>true],
                        'events'=> ['editor'=>"{type:'combobox',options:{editable:false,data:dataProcessing,valueField:'id',textField:'text',groupField:'group'}}"]],
//                  'meta_index' => ['order'=>61, 'label'=>lang('meta_index'),         'attr'=>['width'=>125, 'resizable'=>true, 'editor'=>'text']],
                    'formatting' => ['order'=>70, 'label'=>lang('format'),             'attr'=>['width'=>160, 'resizable'=>true],
                        'events'=> ['editor'=>"{type:'combobox',options:{editable:false,data:dataFormatting,valueField:'id',textField:'text',groupField:'group'}}"]],
                    'total'      => ['order'=>80, 'label'=>lang('total'),              'attr'=>['width'=> 50, 'resizable'=>true],
                        'events'=> ['editor'=>"{type:'checkbox',options:{on:'1',off:''}}"]],
                    'align'      => ['order'=>90, 'label'=>lang('align'),              'attr'=>['width'=> 75, 'resizable'=>true],
                        'events'=> ['editor'   =>"{type:'combobox',options:{valueField:'id',textField:'text',data:dataAligns}}",
                                    'formatter'=>"function(value){ return getTextValue(dataAligns, value); }"]]];
        }
        return $data;
    }

    /**
     * Generates the list of tables available to use in generating a report
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function getFields(&$layout=[])
    {
        $output = [];
        $output[] = '{"id":"","text":"'.lang('none').'"}';
        $tables = getModuleCache('phreeform', 'designCache', 'tables');
        foreach ($tables as $table) {
            $struct = dbLoadStructure(BIZUNO_DB_PREFIX.$table);
            foreach ($struct as $value) {
                $label = isset($value['label']) ? $value['label'] : $value['tag'];
                $output[] = '{"id":"'.$value['table'].'.'.$value['field'].'","text":"'.lang($table).'.'.$label.'"}';
            }
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>"[".implode(',',$output)."]"]);
    }

    /**
     * Pulls the field values from a json encoded string and sets them in the structure for the field pop up
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function getFieldSettings(&$layout=[])
    {
        $index     = clean('rID', 'integer','get');
        $fData     = clean('data','jsonObj','get');
        msgDebug("\njson decoded data = ".print_r($fData, true));
        if (!isset($fData->type)) { return msgAdd("No type received, I do not know what to display!"); }
        $settings  = is_string($fData->settings) ? json_decode($fData->settings) : $fData->settings;
        msgDebug("\nReceived index: $index and settings array: ".print_r($settings, true));
        $pageShow  = [['id'=>'0','text'=>$this->lang['page_all']],  ['id'=>'1','text'=>$this->lang['page_first']],['id'=>'2','text'=>$this->lang['page_last']]];
        $lineTypes = [['id'=>'H','text'=>$this->lang['horizontal']],['id'=>'V','text'=>$this->lang['vertical']],  ['id'=>'C','text'=>lang('custom')]];
        $linePoints= [];
        for ($i=1; $i<7; $i++) { $linePoints[] = ['id'=>$i,'text'=>$i]; }
        $selFont   = phreeformFonts();
        $data = ['type'=>'popup','title'=>lang('settings').(isset($settings->title)?' - '.$settings->title:''),'attr'=>['id'=>'win_settings','height'=>700,'width'=>1110],
            'toolbars'=> ['tbFields'=>['icons'=>[
                'fldClose'=> ['order'=> 10,'icon'=>'close','label'=>lang('close'),'events'=>['onClick'=>"bizWindowClose('win_settings');"]],
                'fldSave' => ['order'=> 20,'icon'=>'save', 'label'=>lang('save'), 'events'=>['onClick'=>"fieldIndex=$index; jqBiz('#frmFieldSettings').submit();"]]]]],
            'forms'   => ['frmFieldSettings'=>['attr'=>['type'=>'form']]],
            'divs'    => [
                'toolbar'       => ['order'=>30, 'type'=>'toolbar','key'=>'tbFields'],
                'field_settings'=> ['order'=>50, 'type'=>'divs',   'divs'=>[
                    'formBOF' => ['order'=>15,'type'=>'form','key' =>'frmFieldSettings'],
                    'formEOF' => ['order'=>85,'type'=>'html','html'=>"</form>"]]]],
            'fields'  => [
                'index'      => ['attr'   =>['type'=>'hidden','value'=>$index]],
                'type'       => ['attr'   =>['type'=>'hidden','value'=>$fData->type]],
                'boxField'   => ['attr'   =>['type'=>'hidden','value'=>'']],
                'fieldname'  => ['options'=>['url'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields'",'editable'=>'true','valueField'=>"'id'",'textField'=>"'text'",'mode'=>"'remote'",'width'=>300],
                    'attr'   => ['type'=>'select','value'=>isset($settings->fieldname)? $settings->fieldname:'']],
                'barcodes'   => ['options'=>['data'=>'dataBarCodes','valueField'=>"'id'",'textField'=>"'text'"],
                    'attr'   => ['type'=>'select','value'=>isset($settings->barcode)? $settings->barcode:'']],
                'processing' => ['values'=>pfSelProcessing(),'options'=>['groupField'=>"'group'"],
                    'attr'   => ['type'=>'select','value'=>isset($settings->processing)? $settings->processing:'']],
                'procFld'    => ['attr'=>['value'=>isset($settings->procFld) ? $settings->procFld  : '']],
                'formatting' => ['values'=>phreeformFormatting(),'options'=>['groupField'=>"'group'"],
                    'attr'   => ['type'=>'select','value'=>isset($settings->formatting)? $settings->formatting:'']],
                'text'       => ['attr'   =>['type'=>'textarea', 'size'=>'80',          'value'=>isset($settings->text)    ? $settings->text    : '']],
                'ltrText'    => ['attr'   =>['type'=>'textarea', 'size'=>'80',          'value'=>isset($settings->ltrText) ? $settings->ltrText : '']],
                'linetype'   => ['values' =>$lineTypes,    'attr'=>['type'=>'select',   'value'=>isset($settings->linetype)? $settings->linetype:'']],
                'length'     => ['label'  =>lang('length'),'attr'=>['size'=>'10',       'value'=>isset($settings->length)  ? $settings->length  : '']],
                'font'       => ['values' =>$selFont, 'attr'=>  ['type'=>'select',      'value'=>isset($settings->font)    ? $settings->font    :'']],
                'size'       => ['values' =>phreeformSizes(), 'attr'=>['type'=>'select','value'=>isset($settings->size)    ? $settings->size    :'10']],
                'align'      => ['values' =>phreeformAligns(),'attr'=>['type'=>'select','value'=>isset($settings->align)   ? $settings->align   :'L']],
                'color'      => ['attr'=>['type'=>'color','value'=>isset($settings->color) ? convertHex($settings->color) :'#000000', 'size'=>10]],
                'truncate'   => ['attr'   =>['type'=>'checkbox', 'value'=>'1']],
                'display'    => ['values' =>$pageShow, 'attr'=>['type'=>'select', 'value'=>isset($settings->display) ? $settings->display: '0']],
                'totals'     => ['attr'   =>['type'=>'checkbox', 'value'=>'1']],
                'bshow'      => ['attr'   =>['type'=>'checkbox', 'value'=>'1']],
                'bsize'      => ['values' =>$linePoints, 'attr'=>['type'=>'select', 'value'=>isset($settings->bsize)   ? $settings->bsize:'1']],
                'bcolor'     => ['attr'=>['type'=>'color','value'=>isset($settings->bcolor)  ? convertHex($settings->bcolor) :'#000000', 'size'=>10]],
                'fshow'      => ['attr'   =>['type'=>'checkbox', 'value'=>'1']],
                'fcolor'     => ['attr'=>['type'=>'color','value'=>isset($settings->fcolor)  ? convertHex($settings->fcolor) :'#000000', 'size'=>10]],
                'hfont'      => ['values' =>$selFont, 'attr'=>['type'=>'select','value'=>isset($settings->hfont)   ? $settings->hfont    :'']],
                'hsize'      => ['values' =>phreeformSizes(), 'attr'=>['type'=>'select','value'=>isset($settings->hsize)   ? $settings->hsize :'10']],
                'halign'     => ['values' =>phreeformAligns(),'attr'=>['type'=>'select','value'=>isset($settings->halign)  ? $settings->halign:'L']],
                'hcolor'     => ['attr'=>['type'=>'color','value'=>isset($settings->hcolor)  ? convertHex($settings->hcolor) :'#000000', 'size'=>10]],
                'hbshow'     => ['attr'   =>['type'=>'checkbox', 'value'=>'1']],
                'hbsize'     => ['values' =>$linePoints, 'attr'=>['type'=>'select', 'value'=>isset($settings->hbsize)  ? $settings->hbsize    :'1']],
                'hbcolor'    => ['attr'=>['type'=>'color','value'=>isset($settings->hbcolor) ? convertHex($settings->hbcolor):'#000000', 'size'=>10]],
                'hfshow'     => ['attr'   =>['type'=>'checkbox', 'value'=>'1']],
                'hfcolor'    => ['attr'=>['type'=>'color','value'=>isset($settings->hfcolor) ? convertHex($settings->hfcolor):'#000000', 'size'=>10]],
                'endAbscissa'=> ['label'  =>$this->lang['abscissa'],'attr'=>['size'=>5]],
                'endOrdinate'=> ['label'  =>$this->lang['ordinate'],'attr'=>['size'=>5]],
                'img_cur'    => ['attr'   =>['type'=>'hidden']],
                'img_file'   => ['attr'   =>['type'=>'hidden']],
                'img_upload' => ['attr'   =>['type'=>'file']]],
            'jsHead'  => ['init' => "var fieldIndex = 0;
jqBiz('#frmFieldSettings').submit(function (e) {
    const fData = {};
    if (jqBiz('#dgFieldValues').length) {
        jqBiz('#dgFieldValues').edatagrid('saveRow');
        var items = jqBiz('#dgFieldValues').datagrid('getData');
        if (items) fData.boxfield = items;
    }
    const formData = jqBiz('#frmFieldSettings').serializeArray();
    jqBiz.each(formData, function(index, field) { fData[field.name] = field.value; });
    jqBiz('#dgFields').datagrid('updateRow', { index: fieldIndex, row: { settings: JSON.stringify(fData) } });
    bizWindowClose('win_settings');
    e.preventDefault();
});"]];
        if (in_array($fData->type, ['CDta','CBlk'])) {
            $data['fields']['fieldname'] = ['options'=>['data'=>'bizData','valueField'=>"'id'",'textField'=>"'text'"],
                'attr'=>['type'=>'select', 'value'=>isset($settings->fieldname) ? $settings->fieldname : '']];
        }
        // set some checkboxes
        if (!empty($settings->truncate)) { $data['fields']['truncate']['attr']['checked']= 'checked'; }
        if (!empty($settings->totals))   { $data['fields']['totals']['attr']['checked']  = 'checked'; }
        if (!empty($settings->bshow))    { $data['fields']['bshow']['attr']['checked']   = 'checked'; }
        if (!empty($settings->fshow))    { $data['fields']['fshow']['attr']['checked']   = 'checked'; }
        if (!empty($settings->hbshow))   { $data['fields']['hbshow']['attr']['checked']  = 'checked'; }
        if (!empty($settings->hfshow))   { $data['fields']['hfshow']['attr']['checked']  = 'checked'; }
        if (!empty($settings->img_file)) {
            $data['fields']['img_cur'] = ['attr'=>['type'=>'img','src'=>BIZBOOKS_URL_FS.getUserCache('business', 'bizID')."/images/$settings->img_file", 'height'=>'32']];
            $data['fields']['img_file']['attr']['value'] = $settings->img_file;
        }
        $data['divs']['field_settings']['divs']['body'] = ['order'=>50,'type'=>'html','html'=>$this->getFieldProperties($data)];
        if (in_array($fData->type, ['Img'])) {
            $imgSrc = isset($data['fields']['img_file']['attr']['value']) ? $data['fields']['img_file']['attr']['value'] : "";
            $imgDir = dirname($imgSrc).'/';
            if ($imgDir=='/') { $imgDir = getUserCache('imgMgr', 'lastPath', false , '').'/'; } // pull last folder from cache
            $data['jsReady'][] = "imgManagerInit('img_file', '$imgSrc', '$imgDir', ".json_encode(['style'=>"max-height:200px;max-width:200px;"]).");";
        }
        if (in_array($fData->type, ['CBlk', 'LtrTpl', 'Tbl', 'TBlk', 'Ttl'])) {
            if (!isset($settings->boxfield)) { $settings->boxfield = (object)[]; }
            msgDebug("\nWorking with box data = ".print_r($settings->boxfield, true));
            $data['jsHead']['dgFieldValues']= formatDatagrid($settings->boxfield->rows, 'dataFieldValues');
            $data['datagrid']['fields'] = $this->dgFieldValues('dgFieldValues', $fData->type);
            $data['divs']['field_settings']['divs']['datagrid'] = ['order'=>60,'type'=>'datagrid','key'=>'fields'];
// @todo need to turn dg into accordion for forms/fields so properties drag-n-drop doesn't remove rows
// then renable drag-n-drop for types that require datagrid
// ALSO, field, processing and formatting drop downs are not working.
//            $data['jsReady']['fldSetDg'] = "jqBiz('#dgFieldValues').datagrid('enableDnd');";
        }
        unset($data['fields']);
        msgDebug("\nreached the end, data = ".print_r($data, true));
        $layout = array_replace_recursive($layout, $data);
    }

    private function getFieldProperties($viewData)
    {
        $output  = '';
        switch ($viewData['fields']['type']['attr']['value']) {
            case 'BarCode':
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">';
                $output .= ' <thead class="panel-header"><tr><th>'.lang('fieldname')."</th><th>".$this->lang['processing']."</th><th>".$this->lang['formatting']."</th></tr></thead>";
                $output .= " <tbody><tr>";
                $output .= '    <td>'.html5('fieldname',  $viewData['fields']['fieldname']) ."</td>";
                $output .= '    <td>'.html5('processing', $viewData['fields']['processing'])."</td>";
                $output .= '    <td>'.html5('formatting', $viewData['fields']['formatting'])."</td>";
                $output .= "   </tr>";
                $output .= '   <tr><td colspan="2">'.$this->lang['phreeform_barcode_type'].' '.html5('barcode', $viewData['fields']['barcodes'])."</td></tr>";
                $output .= "  </tbody></table>";
                $output .= $this->box_build_attributes($viewData, false, false);
                break;
            case 'CDta':
            case 'Data':
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">';
                $output .= ' <thead class="panel-header"><tr><th>'.lang('fieldname')."</th><th>".$this->lang['processing']."</th><th>".$this->lang['formatting']."</th></tr></thead>";
                $output .= " <tbody><tr>";
                $output .= '    <td>'.html5('fieldname',  $viewData['fields']['fieldname']) ."</td>";
                $output .= '    <td>'.html5('processing', $viewData['fields']['processing'])."</td>";
                $output .= '    <td>'.html5('formatting', $viewData['fields']['formatting'])."</td>";
                $output .= "  </tr><tr>";
                $output .= '    <td colspan="2">'.$this->lang['phreeform_encoded_field'].' '.html5('procFld', $viewData['fields']['procFld'])."</td><td>&nbsp;</td>";
                $output .= "  </tr></tbody></table>";
                $output .= $this->box_build_attributes($viewData);
                break;
            case 'ImgLink':
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">';
                $output .= ' <thead class="panel-header"><tr><th>'.lang('fieldname')."</th><th>".$this->lang['processing']."</th><th>".$this->lang['formatting']."</th></tr></thead>";
                $output .= " <tbody><tr>";
                $output .= '    <td>'.html5('fieldname',  $viewData['fields']['fieldname']) ."</td>";
                $output .= '    <td>'.html5('processing', $viewData['fields']['processing'])."</td>";
                $output .= '    <td>'.html5('formatting', $viewData['fields']['formatting'])."</td>";
                $output .= "  </tr></tbody></table>";
                $output .= $this->box_build_attributes($viewData, false, false);
                break;
            case 'CImg':
            case 'Img':
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;"><tbody>';
                $output .= '  <tr><td><div id="imdtl_img_file"></div>'.html5('img_file', $viewData['fields']['img_file']).'</td></tr></tbody></table>';
                break;
            case 'Line':
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">';
                $output .= ' <thead class="panel-header"><tr><th colspan="3">'.$this->lang['phreeform_line_type']."</th></tr></thead>";
                $output .= " <tbody>";
                $output .= "  <tr><td>".html5('linetype', $viewData['fields']['linetype']).' '.html5('length', $viewData['fields']['length'])."</td></tr>";
                $output .= "  <tr><td>".$this->lang['end_position'].' '.html5('endAbscissa', $viewData['fields']['endAbscissa']).' '.html5('endOrdinate', $viewData['fields']['endOrdinate'])."</td></tr>";
                $output .= " </tbody></table>";
                $output .= $this->box_build_attributes($viewData, false, false, true, false);
                break;
            case 'LtrTpl':
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">';
                $output .= ' <thead class="panel-header"><tr><th>'.$this->lang['phreeform_text_disp']."</td></tr></thead>";
                $output .= " <tbody><tr><td>".html5('ltrText', $viewData['fields']['ltrText'])."</td></tr></tbody>";
                $output .= "</table>";
                $output .= $this->box_build_attributes($viewData);
                break;
            case 'TDup':
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">';
                $output .= ' <tbody><tr><td style="text-align:center">'.$this->lang['msg_no_settings']."</td></tr></tbody>";
                $output .= "</table>";
                break;
            case 'Text':
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">';
                $output .= ' <thead class="panel-header"><tr><th>'.$this->lang['phreeform_text_disp']."</td></tr></thead>";
                $output .= " <tbody><tr><td>".html5('text', $viewData['fields']['text'])."</td></tr></tbody>";
                $output .= "</table>";
                $output .= $this->box_build_attributes($viewData);
                break;
            case 'Tbl':
                $output .= $this->box_build_attributes($viewData, false, true,  true, true, 'h', lang('heading'));
                $output .= $this->box_build_attributes($viewData, false, false, true, true, '',  lang('body'));
                $output .= '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">';
                $output .= ' <thead class="panel-header">';
                $output .= '  <tr><th colspan="3">'.$this->lang['encoded_table_title']."</th></tr>";
                $output .= "  <tr><th>".lang('fieldname')."</th><th>".$this->lang['processing']."</th><th>".$this->lang['formatting']."</th></tr>";
                $output .= " </thead>";
                $output .= " <tbody><tr>";
                $output .= '    <td>'.html5('fieldname',  $viewData['fields']['fieldname']) ."</td>";
                $output .= '    <td>'.html5('processing', $viewData['fields']['processing'])."</td>";
                $output .= '    <td>'.html5('formatting', $viewData['fields']['formatting'])."</td>";
                $output .= "  </tr><tr>";
                $output .= '    <td colspan="2">'.$this->lang['phreeform_encoded_field'].' '.html5('procFld', $viewData['fields']['procFld'])."</td><td>&nbsp;</td>";
                $output .= "  </tr></tbody></table>";
                break;
            case 'PgNum':  $output .= $this->box_build_attributes($viewData, false);        break;
            case 'Rect':   $output .= $this->box_build_attributes($viewData, false, false); break;
            case 'CBlk':
            case 'TBlk':   $output .= $this->box_build_attributes($viewData); break;
            case 'Ttl':    $output .= $this->box_build_attributes($viewData); break;
        }
        return $output;
    }

    // This function generates the bizuno attributes for most boxes.
    private function box_build_attributes($viewData, $showtrunc=true, $showfont=true, $showborder=true, $showfill=true, $pre='', $title='')
    {
        $output  = '<table style="border-collapse:collapse;margin-left:auto;margin-right:auto;">' . "";
        $output .= ' <thead class="panel-header"><tr><th colspan="5">'.($title ? $title : lang('settings'))."</th></tr></thead>";
        $output .= " <tbody>";
        if ($showtrunc) {
            $output .= " <tr>";
            $output .= '  <td colspan="2">'.lang('truncate_fit').html5('truncate',$viewData['fields']['truncate']) . "</td>";
            $output .= '  <td colspan="3">'.$this->lang['display_on']  .html5('display', $viewData['fields']['display']) . "</td>";
            $output .= " </tr>";
        }
        if ($showfont) {
            $output .= ' <tr class="panel-header"><th>&nbsp;'.'</th><th>'.lang('style').'</th><th>'.lang('size').'</th><th>'.$this->lang['align'].'</th><th>'.$this->lang['color']."</th></tr>";
            $output .= " <tr>";
            $output .= "  <td>".lang('font')."</td>";
            $output .= "  <td>".html5($pre.'font',  $viewData['fields'][$pre.'font']) . "</td>";
            $output .= "  <td>".html5($pre.'size',  $viewData['fields'][$pre.'size']) . "</td>";
            $output .= "  <td>".html5($pre.'align', $viewData['fields'][$pre.'align']). "</td>";
            $output .= "  <td>".html5($pre.'color', $viewData['fields'][$pre.'color']). "</td>";
            $output .= " </tr>";
        }
        if ($showborder) {
            $output .= " <tr>";
            $output .= "  <td>".$this->lang['border'] . "</td>";
            $output .= "  <td>".html5($pre.'bshow', $viewData['fields'][$pre.'bshow'])."</td>";
            $output .= "  <td>".html5($pre.'bsize', $viewData['fields'][$pre.'bsize']).$this->lang['points']."</td>";
            $output .= "  <td>&nbsp;</td>";
            $output .= "  <td>".html5($pre.'bcolor', $viewData['fields'][$pre.'bcolor'])."</td>";
            $output .= "</tr>";
        }
        if ($showfill) {
            $output .= "<tr>";
            $output .= '  <td>'. $this->lang['fill_area'] . "</td>";
            $output .= '  <td>'.html5($pre.'fshow',  $viewData['fields'][$pre.'fshow'])."</td>";
            $output .= "  <td>&nbsp;</td>";
            $output .= "  <td>&nbsp;</td>";
            $output .= "  <td>".html5($pre.'fcolor', $viewData['fields'][$pre.'fcolor'])."</td>";
            $output .= "</tr>";
        }
        $output .= "</tbody></table>";
        return $output;
    }

    /**
     * Generates the structure for the grid for form fields properties pop up
     * @param string $name - DOM field name
     * @return array - structure ready to render
     */
    private function dgFieldValues($name, $type)
    {
        $data = ['id'=>$name, 'type'=>'edatagrid',
            'attr'   => ['idField'=>'id','toolbar'=>"#{$name}Toolbar",'singleSelect'=>true],
            'events' => ['data'=>'dataFieldValues'],
            'source' => [
                'actions' => ['new'=>['order'=>10,'icon'=>'add','size'=>'small','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns' => [
                'action' => ['order'=>1, 'label'=>lang('action'),'attr'=>['width'=>45],
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['trash' => ['order'=>50,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'title'         => ['order'=>10, 'label'=>lang('title'), 'attr'=>['width'=>150, 'resizable'=>true, 'editor'=>'text']],
                'processing' => ['order'=>20, 'label' => $this->lang['processing'], 'attr'=>['width'=>160, 'resizable'=>true],
                    'events' => [
                        'formatter'=>"function(value,row){ return getTextValue(dataProcessing, value); }",
                        'editor'   =>"{type:'combobox',options:{editable:false,data:dataProcessing,valueField:'id',textField:'text',groupField:'group'}}"]],
                'formatting' => ['order'=>30, 'label' => lang('format'), 'attr'=>['width'=>160, 'resizable'=>true],
                    'events' => [
                        'formatter'=>"function(value,row){ return getTextValue(dataFormatting, value); }",
                        'editor'   =>"{type:'combobox',options:{editable:false,data:dataFormatting,valueField:'id',textField:'text',groupField:'group'}}"]],
                'separator'  => ['order'=>40, 'label'=>lang('separator'),
                    'attr'=>  ['width'=>160, 'resizable'=>true, 'hidden'=>in_array($type, ['CBlk','TBlk']) ? false : true],
                    'events' => [
                        'formatter'=>"function(value,row){ return getTextValue(dataSeparators, value); }",
                        'editor'   =>"{type:'combobox',options:{editable:false,data:dataSeparators,valueField:'id',textField:'text'}}"]],
                'font' => ['order'=>50, 'label'=>lang('font'), 'attr'=>['width'=>80, 'resizable'=>true],
                    'events' => [
                        'formatter'=>"function(value,row){ return getTextValue(dataFonts, value); }",
                        'editor'   =>"{type:'combobox',options:{valueField:'id',textField:'text',data:dataFonts}}"]],
                'size' => ['order'=>60, 'label'=>lang('size'), 'attr'=>['width'=>80, 'resizable'=>true],
                    'events' => ['editor'=>"{type:'combobox',options:{valueField:'id',textField:'text',data:dataSizes}}"]],
                'align' => ['order'=>70, 'label' => lang('align'), 'attr'=>['width'=>80, 'resizable'=>true],
                    'events' => [
                        'formatter'=>"function(value,row){ return getTextValue(dataAligns, value); }",
                        'editor'   =>"{type:'combobox',options:{valueField:'id',textField:'text',data:dataAligns,}}"]],
                'color' => ['order'=>80, 'label'=>lang('color'), 'attr'=>['width'=>80, 'resizable'=>true],
                    'events'=>  ['editor'=>"{type:'color',options:{value:'#000000'}}"]],
                'width' => ['order'=>90, 'label'=>lang('width'),
                    'attr'=>  ['width'=>50, 'editor'=>'text', 'resizable'=>true, 'align'=>'right', 'hidden'=>$type=='Tbl'?false:true]]],
            ];
        switch ($type) {
//          case 'CDta':  // N/A - no datagrid used for this
            case 'CBlk':
                $data['columns']['fieldname'] = ['order'=>5, 'label'=>lang('fieldname'), 'attr'=>  ['width'=>200, 'resizable'=>true],
                    'events' => ['editor'=>"{type:'combobox',options:{editable:false,data:bizData,valueField:'id',textField:'text'}}"]];
            case 'TBlk':
            case 'Ttl':
                if (!isset($data['columns']['fieldname'])) {
                    $data['columns']['fieldname'] = ['order'=>5, 'label'=>lang('fieldname'), 'attr'=>  ['width'=>200, 'resizable'=>true],
                        'events' => ['editor'=>"{type:'combobox',options:{editable:true,mode:'remote',url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields',valueField:'id',textField:'text'}}"]];
                }
                unset($data['columns']['title']);
                unset($data['columns']['font']);
                unset($data['columns']['size']);
                unset($data['columns']['align']);
                unset($data['columns']['color']);
                unset($data['columns']['width']);
                break;
            default:
                $data['columns']['fieldname'] = ['order'=>5, 'label'=>lang('fieldname'), 'attr'=>  ['width'=>200, 'resizable'=>true],
                    'events' => ['editor'=>"{type:'combobox',options:{mode:'remote',url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields',valueField:'id',textField:'text'}}"]];
                break;
        }
        return $data;
    }

    /**
     * Generates the structure for the grid for report/form groups
     * @param string $name - DOM field name
     * @param string $type - choices are rpt (report) OR frm (form)
     * @return array - structure ready to render
     */
    private function dgGroups($name, $type='rpt')
    {
        return ['id'=>$name,'type'=>'edatagrid',
            'attr'   => ['title'  =>lang('group_list'),'toolbar'=>"#{$name}Toolbar",'singleSelect'=> true,'idField'=>'id'],
            'events' => ['data'   =>'dataGroups'],
            'source' => ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action' => ['order'=>1,'label'=>lang('action'),'attr'=>['width'=>45],
                    'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'fldEdit' => ['order'=>40,'icon'=>'edit', 'events'=>['onClick'=>"var row = jqBiz('#$name').datagrid('getSelected'); jqBiz('#$name').edatagrid('editRow', jqBiz('#$name').datagrid('getRowIndex', row));"]],
                        'fldTrash'=> ['order'=>80,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'fieldname' => ['order'=>10, 'label' => lang('fieldname'), 'attr'=>['width'=>250,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'combobox',options:{editable:false,mode:'remote',url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields',valueField:'id',textField:'text'}}"]],
                'title'     => ['order'=>20, 'label' => lang('title'),     'attr'=>['width'=>150,'resizable'=>true, 'editor'=>'text']],
                'default'   => ['order'=>30, 'label' => lang('default'),   'attr'=>['width'=>120,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'checkbox',options:{on:'1',off:''}}"]],
                'page_break'=> ['order'=>40, 'label' => $this->lang['page_break'],'attr'=>['width'=>120,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'checkbox',options:{on:'1',off:''}}"]],
                'processing'=> ['order'=>50, 'label' => $this->lang['processing'],'attr'=>['width'=>200,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'combobox',options:{editable:false,data:dataProcessing,valueField:'id',textField:'text',groupField:'group'}}"]],
                'formatting'=> ['order'=>50, 'label' => lang('format'),'attr'=>['width'=>200,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'combobox',options:{editable:false,data:dataFormatting,valueField:'id',textField:'text',groupField:'group'}}"]]]];
    }

    /**
     * Generates the structure for the grid for report/form sort order selections
     * @param string $name - DOM field name
     * @param string - choices are report (rpt) or form (frm)
     * @return array grid structure
     */
    private function dgOrder($name)
    {
        return ['id'=>$name, 'type'=>'edatagrid',
            'attr'   => ['toolbar'=>"#{$name}Toolbar", 'title'=>$this->lang['sort_list'], 'idField'=>'id', 'singleSelect'=>true],
            'events' => ['data'   =>'dataOrder'],
            'source' => ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns' => [
                'action' => ['order'=>1,'label'=>lang('action'),'attr'=>['width'=>45],
                    'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'fldEdit' => ['order'=>40,'icon'=>'edit', 'events'=>['onClick'=>"var row = jqBiz('#$name').datagrid('getSelected'); jqBiz('#$name').edatagrid('editRow', jqBiz('#$name').datagrid('getRowIndex', row));"]],
                        'fldTrash'=> ['order'=>80,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'fieldname'=> ['order'=>10, 'label'=>lang('fieldname'), 'attr'=>  ['width'=>250, 'resizable'=>'true'],
                    'events'=> ['editor'=>"{type:'combobox',options:{editable:false,mode:'remote',url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields',valueField:'id',textField:'text'}}"]],
                'title'    => ['order'=>20, 'label'=>lang('title'), 'attr' => ['width'=>150, 'resizable'=>'true', 'editor'=>'text']],
                'default'  => ['order'=>30, 'label'=>lang('default'), 'attr'=>  ['width'=>120],
                    'events'=> ['editor'=>"{type:'checkbox',options:{on:'1',off:''}}", 'resizable'=>'true']]]];
    }

    /**
     * Generates the structure for the grid for report/form filter selections
     * @param string $name - DOM field name
     * @return array - structure
     */
    private function dgFilters($name)
    {
        return ['id' =>$name, 'type'=>'edatagrid',
            'attr'   => ['toolbar'=>"#{$name}Toolbar", 'title'=> $this->lang['filter_list'], 'singleSelect'=>true, 'idField'=>'id'],
            'events' => ['data'   =>'dataFilters'],
            'source' => ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action'    => ['order'=>1,'label'=>lang('action'),'attr'=>['width'=>45],
                    'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'fldEdit' => ['order'=>40,'icon'=>'edit', 'events'=>['onClick'=>"var row = jqBiz('#$name').datagrid('getSelected'); jqBiz('#$name').edatagrid('editRow', jqBiz('#$name').datagrid('getRowIndex', row));"]],
                        'fldTrash'=> ['order'=>80,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'fieldname' => ['order'=>10,'label'=>lang('fieldname'), 'attr'=>  ['width'=>250, 'resizable'=>true],
                    'events'=> ['editor'=>"{type:'combobox',options:{mode:'remote',url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getFields',valueField:'id',textField:'text'}}"]],
                'title'     => ['order'=>20,'label'=>lang('title'),'attr'=>['width'=>150, 'editor'=>'text', 'resizable'=>true]],
                'visible'   => ['order'=>30,'label'=>lang('show'), 'attr'=>['width'=>120, 'resizable'=>true],'events'=>['editor'=>"{type:'checkbox',options:{on:'1',off:''}}"]],
                'type'      => ['order'=>40,'label'=>lang('type'), 'attr'=>['width'=>200, 'resizable'=>true],'events'=>[
                    'editor'   =>"{type:'combobox',options:{editable:false,valueField:'id',textField:'text',data:filterTypes}}",
                    'formatter'=>"function(value,row){ return getTextValue(filterTypes, value); }"]],
                'min'       => ['order'=>50,'label'=>lang('min'),'attr'=>['width'=>100,'editor'=>'text','resizable'=>true]],
                'max'       => ['order'=>60,'label'=>lang('max'),'attr'=>['width'=>100,'editor'=>'text','resizable'=>true]]]];
    }

    /**
     * Creates a list of report/form field types which determine the properties allowed
     * @return type
     */
    function phreeformTypes()
    {
        return [
            ['id'=>'Data',   'text'=>$this->lang['fld_type_data_line']],
            ['id'=>'TBlk',   'text'=>$this->lang['fld_type_data_block']],
            ['id'=>'Tbl',    'text'=>$this->lang['fld_type_data_table']],
            ['id'=>'TDup',   'text'=>$this->lang['fld_type_data_table_dup']],
            ['id'=>'Ttl',    'text'=>$this->lang['fld_type_data_total']],
            ['id'=>'LtrTpl', 'text'=>$this->lang['fld_type_letter_tpl']],
            ['id'=>'Text',   'text'=>$this->lang['fld_type_fixed_txt']],
            ['id'=>'Img',    'text'=>$this->lang['fld_type_image']],
            ['id'=>'ImgLink','text'=>$this->lang['fld_type_image_link']],
            ['id'=>'Rect',   'text'=>$this->lang['fld_type_rectangle']],
            ['id'=>'Line',   'text'=>$this->lang['fld_type_line']],
            ['id'=>'CImg',   'text'=>$this->lang['fld_type_biz_logo']],
            ['id'=>'CDta',   'text'=>$this->lang['fld_type_biz_data']],
            ['id'=>'CBlk',   'text'=>$this->lang['fld_type_biz_block']],
            ['id'=>'PgNum',  'text'=>$this->lang['fld_type_page_num']],
            ['id'=>'BarCode','text'=>$this->lang['fld_type_barcode']]];
    }
}