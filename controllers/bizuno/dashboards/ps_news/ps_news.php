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
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-11-21
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
    private $maxItems  = 4;
    public  $struc;
    public $lang = ['title'=>'PhreeSoft News',
        'description'=> 'Displays the latest PhreeSoft news and press releases.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
    }

    public function render(&$layout=[])
    {
/*
        global $io;
        $strXML = $io->cURL("https://www.phreesoft.com/feed/");
        $news   = parseXMLstring($strXML);
        msgDebug("\nNews object = ".print_r($news, true));
        $html   = '';
        $newsCnt= 0;
        if (!empty($news->channel->item)) {
            foreach ($news->channel->item as $entry) {
                $html .= '<a href="'.$entry->link.'" target="_blank"><h3>'.$entry->title."</h3></a><p>$entry->description</p>";
                if ($newsCnt++ > $this->maxItems) { break; }
            }s
        } else {
            $html .= "Sorry I cannot reach the PhreeSoft.com server. Please try again later.";
        }
 */
        $html = 'Coming soon!';
        return ['html'=>$html];
    }
}
