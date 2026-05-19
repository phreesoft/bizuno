<?php
/*
 * Handles Input/Output operations generically for all modules
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/phreeform/io.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreeform/functions.php','phreeformImport', 'function');

class phreeformIo
{
    public $moduleID = 'phreeform';

    function __construct()
    {
    }

    /**
     * Manager to handle report/form management, importing, exporting and installing
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function manager(&$layout=[])
    {
        $selMods = [['id'=>'locale','text'=>'Bizuno']];
        $selLangs= [['id'=>'en_US', 'text'=>lang('language_title')]];
        $reports = $this->ReadDefReports();
        $fields= [
            'imp_name'    => ['order'=> 0,'attr'=>['type'=>'hidden']],
            'selLang'     => ['order'=>10,'label'=>lang('language'),'values'=>$selLangs,'attr'=>['type'=>'select']],
            'cbReplace'   => ['order'=>20,'label'=>lang('msg_replace_existing', $this->moduleID),'attr'=>['type'=>'checkbox']],
            'selModule'   => ['order'=>30,'label'=>lang('module'),  'values'=>$selMods, 'attr'=>['type'=>'select', 'readonly'=>true]],
            'new_name'    => ['order'=>40,'label'=>'('.lang('optional').') '.lang('msg_entry_rename'),'attr'=>['width'=>160]],
            'fileUpload'  => ['order'=>50,'label'=>lang('import_upload_report', $this->moduleID),'attr'=>['type'=>'file']],
            'btnUpload'   => ['order'=>60,'attr' =>['type'=>'button','value'=>lang('import')],'events'=>['onClick'=>"jqBiz('#imp_name').val(''); jqBiz('#frmImport').submit();"]],
            'selReports'  => ['order'=>70,'label'=>lang('phreeform_reports_available', $this->moduleID),'attr'=>['type'=>'select'], 'values'=>$reports],
            'btnImport'   => ['order'=>80,'attr' =>['type'=>'button','value'=>lang('btn_import_selected', $this->moduleID)],'events'=>['onClick'=>"bizTextSet('imp_name', bizSelGet('selReports')); jqBiz('#frmImport').submit();"]],
            'btnImportAll'=> ['order'=>90,'attr' =>['type'=>'button','value'=>lang('btn_import_all', $this->moduleID)],     'events'=>['onClick'=>"bizTextSet('imp_name', 'all'); jqBiz('#frmImport').submit();"]],
        ];
        $data  = [
            'title'=> lang('import'),
            'toolbars' => ['tbImport'=>['icons'=>[
                'back' => ['order'=>10,'events'=>['onClick'=>"location.href='".BIZUNO_URL_PORTAL."?bizRt=phreeform/main/manager'"]]]]],
            'divs'     => [
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key'=>'tbImport'],
                'heading'=> ['order'=>15,'type'=>'html',   'html'=>"<h1>dave - ".lang('phreeform_import', $this->moduleID)."</h1>"],
                'formBOF'=> ['order'=>20,'type'=>'form',   'key'=>'frmImport'],
                'body'   => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'import' => ['order'=>10,'type'=>'panel','classes'=>['block66'],'key'=>'import']]],
                'formEOF'=> ['order'=>90,'type'=>'html',   'html'=>"</form>"]],
            'forms'    => ['frmImport'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=phreeform/io/importReport"]]],
            'panels'  => [
                'import' => ['label'=>lang('phreeform_import', $this->moduleID), 'type'=>'fields','keys'=>array_keys($fields)]],
            'fields'   => $fields,
            'jsReady'  => ['init'=>"ajaxForm('frmImport');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Reads the default reports from a folder and builds an HTML DOM select element
     * @param integer $id - database record id
     * @param string $path - path where the reports/forms are stored
     * @param string $lang - [default en_US] language to search as default
     * @return string - HTML containing the list of default reports from the installation folder
     */
    function ReadDefReports()
    {
        $path = BIZUNO_FS_LIBRARY.'locale/en_US/reports';
        // build the report titles
        $titles = [];
        foreach (getModuleCache('phreeform', 'rptGroups') as $value) { $titles[$value['id']] = $value['text']; }
        foreach (getModuleCache('phreeform', 'frmGroups') as $value) { $titles[$value['id']] = $value['text']; }
        $ReportList = [];
        $files = @scandir($path);
        msgDebug("\nRead from path $path files: ".print_r($files, true));
        foreach ($files as $DefRpt) {
            if (in_array($DefRpt, ['.', '..'])) { continue; }
            $pinfo  = pathinfo("$path/$DefRpt");
            msgDebug("\nReading file: $DefRpt from pathinfo: ".print_r($pinfo, true));
            $strXML = file_get_contents("$path/$DefRpt");
            $report = json_decode($strXML);
            if (!is_object($report)) { continue; }
            $ReportList[] = ['id'=>$pinfo['basename'], 'text'=>$report->title.' - '.$report->description];
        }
        return $ReportList;
    }

    /**
     * Imports a report from either the default list of from an uploaded file
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function importReport(&$layout=[])
    {
        msgDebug("\nEntering importReport");
        if (!$security = validateAccess('phreeform', 2)) { return; }
        $path    = BIZUNO_FS_LIBRARY.clean('selModule','text', 'post');
        $lang    = clean('selLang',  ['format'=>'text', 'default'=>'en_US'], 'post');
        $replace = clean('cbReplace','boolean', 'post');
        $imp_name= clean('imp_name', 'filename', 'post');
        $new_name= clean('new_name', 'text', 'post');
        if ($imp_name == 'all') {
            $cnt = 0;
            $files = @scandir("$path/$lang/reports/");
            msgDebug("\nScanning reports at path = $path/$lang/reports/");
            foreach ($files as $imp_name) { 
                if (substr($imp_name, -5) <> '.json') { continue; } // Not the report we are looking for
                if (phreeformImport('', $imp_name, "$path/$lang/reports/", true, $replace)) { $cnt++; }
            }
            $title = lang('all')." $cnt ".lang('total');
        } else {
            if (!$result = phreeformImport($new_name, $imp_name, "$path/$lang/reports/", true, $replace)) { return; }
            $title = $result['title'];
        }
        msgLog(lang('phreeform_manager').': '.lang('import').": $title");
        msgAdd(lang('phreeform_manager').': '.lang('import').": $title", 'success');
    }

    /**
     * Retrieves and exports a specified report/form in XML format
     * @return type
     */
    public function export()
    {
        global $io;
        if (!$security = validateAccess('phreeform', 3)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd('The report was not exported, the proper id was not passed!'); }
        if (!$report = dbMetaGet($rID, 'phreeform')) { return; }
        metaIdxClean($report); // strip the db indexes
        unset($report['parent_id'], $report['ref_num']);
        // reset the security
        $report['users'] = [-1];
        $report['roles'] = [-1];
        $output=json_encode($report, JSON_PRETTY_PRINT);
        msgDebug("\nReady to write report = ".print_r($output, true));
        $io->download('data', $output, str_replace([' ','/','\\','"',"'"], '', $report['title']).'.json');
    }
}
