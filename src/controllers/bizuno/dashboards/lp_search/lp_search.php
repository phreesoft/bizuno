<?php
/*
 * Bizuno dsahboard - Search engine quicklink with search box
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
 * @version    7.x Last Update: 2026-03-15
 * @filesource /controllers/bizuno/dashboards/lp_search/lp_search.php
 */

namespace bizuno;

class lp_search
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'lp_search';
    public $category = 'general';
    public $struc = [];
    public $lang = ['title'=>'Web Search',
        'description' => 'Provides a quick link to popular search engines and opens results in a new window.',
        'google' => 'Search Google',
        'yahoo' => 'Search Yahoo',
        'bing' => 'Search Bing',];

    function __construct()
    {
    }

    public function render()
    {
        $js = "
jqBiz('#google').keypress(function(event) {
    var keycode = (event.keyCode ? event.keyCode : event.which);
    if (keycode == '13') { winHref('https://www.google.com/search?q='+jqBiz('#google').val()); }
});
jqBiz('#yahoo').keypress(function(event) {
    var keycode = (event.keyCode ? event.keyCode : event.which);
    if (keycode == '13') { winHref('https://search.yahoo.com?q='+jqBiz('#yahoo').val()); }
});
jqBiz('#bing').keypress(function(event) {
    var keycode = (event.keyCode ? event.keyCode : event.which);
    if (keycode == '13') { winHref('https://www.bing.com?q='+jqBiz('#bing').val()); }
});";
        $data = [
            'divs'  => [
                'google'=>['order'=>40,'type'=>'fields','keys'=>['imgGoogle','imgBrk','google','btnGoogle']],
                'yahoo' =>['order'=>50,'type'=>'fields','keys'=>['imgYahoo', 'imgBrk','yahoo', 'btnYahoo']],
                'bing'  =>['order'=>60,'type'=>'fields','keys'=>['imgBing',  'imgBrk','bing',  'btnBing']]],
            'fields'=> [
                'imgBrk'   => ['order'=>15,'html'=>"<br />",'attr'=>['type'=>'raw']],
                'google'   => ['order'=>20,'options'=>['width'=>250],'break'=>false,'attr'=>['type'=>'text']],
                'yahoo'    => ['order'=>20,'options'=>['width'=>250],'break'=>false,'attr'=>['type'=>'text']],
                'bing'     => ['order'=>20,'options'=>['width'=>250],'break'=>false,'attr'=>['type'=>'text']],
                'btnGoogle'=> ['order'=>30,'attr'=>['type'=>'button','value'=>$this->lang['google']],'styles'=>['cursor'=>'pointer'],
                    'events' => ['onClick'=> "winHref('https://www.google.com/search?q='+jqBiz('#google').val())"]],
                'btnYahoo' => ['order'=>30,'attr'=>['type'=>'button','value'=>$this->lang['yahoo']], 'styles'=>['cursor'=>'pointer'],
                    'events' => ['onClick'=> "winHref('https://search.yahoo.com?q='+jqBiz('#yahoo').val())"]],
                'btnBing'  => ['order'=>30,'attr'=>['type'=>'button','value'=>$this->lang['bing']],  'styles'=>['cursor'=>'pointer'],
                    'events' => ['onClick'=> "winHref('https://www.bing.com?q='+jqBiz('#bing').val())"]],
                'imgGoogle'=> ['order'=>10,'label'=>$this->lang['google'],'attr'=>['type'=>'img','src'=>BIZUNO_URL_FS.'0/controllers/bizuno/dashboards/lp_search/google.png','height'=>50]],
                'imgYahoo' => ['order'=>10,'label'=>$this->lang['yahoo'], 'attr'=>['type'=>'img','src'=>BIZUNO_URL_FS.'0/controllers/bizuno/dashboards/lp_search/yahoo.png', 'height'=>50]],
                'imgBing'  => ['order'=>10,'label'=>$this->lang['bing'],  'attr'=>['type'=>'img','src'=>BIZUNO_URL_FS.'0/controllers/bizuno/dashboards/lp_search/bing.jpg',  'height'=>50]]],
            'jsHead'=>[$this->code=>$js]];
        return ['data'=>$data];
    }
}
