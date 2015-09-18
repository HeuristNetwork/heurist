<?php
/*
* Copyright (C) 2005-2015 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Render Citation popup page.
*
* IMPORTANT! HARDCODE: for generated pages it refers to dictionaryofsydney.org
*
* It loads wml file (specified in detail) and applies 2 xsl
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

    if(!isset($record)){
	    $rec_id = @$_REQUEST['name'];

	    $record = null;

	    if(is_numeric($rec_id)){
            if(!$db_selected){
                $db_selected = mysql_connection_select();
            }
		    $record = getRecordFull($rec_id);
	    }

	    if(!$record){
		    echo "not found";
		    exit();
	    }
    }

            if ($is_generation){
                $human_url = $startupurl.getStaticFileName($record->type(), null, $record->getDet(DT_NAME), $record->id());
            }else {
                $human_url = $urlbase.$record->id();
            }


    //$human_url = getLinkTag($record);
    $a_s = getAuthors($record);
    $authors = $a_s[0];
    $out = "";
    $cnt = 0;
    $len = count($authors);
        foreach ($authors as $author){
            $cnt++;
            if($cnt==$len && $len>1){
                $out = $out." and ";
            }else if ($cnt>1){
                $out = $out.", ";
            }
            $out = $out.$author->getDet(DT_NAME);
        }

    $author = $out;
    $pubDate = date('Y', strtotime($record->getDet(DT_DATE)));
    $visDate = date("j M Y");
?>
<div class="citation-container">
            <div class="citation-close"> <a onclick="Boxy.get(this).hide(); return false;" href="#">[close]</a> </div>
            <div class="clearfix"></div>
            <div class="citation-heading balloon-entry">
                <h2>Citation</h2>
            </div>
            <div class="citation-content">

                <h4>Persistent URL for this entry</h4>
                <div class="citation-text"><?=$human_url ?></div>


                <h4>To cite this entry in text</h4>
                <div class="citation-text">
                    <?=$author ?>, <?=$record->getDet(DT_NAME) ?>, Dictionary of Sydney, <?=$pubDate ?>, <?=$human_url ?>, viewed <span class="date"><?=$visDate ?></span>
                </div>

                <h4>To cite this entry in a Wikipedia footnote citation</h4>
                    <div class="citation-text">
                       &lt;ref&gt;{{cite web |url= <?=$human_url ?> |title = <?=$record->getDet(DT_NAME) ?> | author = <?=$author ?> | date = <?=$pubDate ?> |work = Dictionary of Sydney |publisher = Dictionary of Sydney Trust |accessdate = <span class="date"><?=$visDate ?></span>}}&lt;/ref&gt;
                       </div>

                <h4>To cite this entry as a Wikipedia External link</h4>
                    <div class="citation-text">
                        * {{cite web | url = <?=$human_url ?> | title = <?=$record->getDet(DT_NAME) ?> | accessdate = <span class="date"><?=$visDate ?></span> | author = <?=$author ?> | date = <?=$pubDate ?> | work = Dictionary of Sydney | publisher = Dictionary of Sydney Trust}}</div>

                    <div class="clearfix"></div>
            </div>


        <div class="clearfix"></div>
</div>
