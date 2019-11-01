<?php

/**
*
* dh_legend.php (Digital Harlem): Generates the special legend panel for Digital Harlem
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once (dirname(__FILE__).'/../../../hsapi/System.php');

define('PLACE_ICON', 4326);
define('PERSON_ROLE', 3306);
define('EVENT_TYPE', 3297);


$statistics = "";
$system = new System();

// connect to given database
if(!(@$_REQUEST['db'] && $system->init(@$_REQUEST['db']))){
    exit;
}
?>


<div id="legendtable">
    <p><b>PLACES</b></p>
    <table>
        <tbody>
            <tr>
                <td class="legend_icon"><img src="<?=HEURIST_TERM_ICON_URL.PLACE_ICON?>.png"></td>
                <td class="legend_text">Address in DB</td>
            </tr>
        </tbody></table>

    <p><b>EVENTS</b></p>                  
    <table>
        <tbody>
            <?php
            $query = 'SELECT trm_ID, trm_Label, trm_Code from defTerms where trm_ParentTermID='.EVENT_TYPE.' ORDER BY trm_Label';
            $res = $system->get_mysqli()->query($query);
            while($row = $res->fetch_assoc()) {
                $filename = HEURIST_TERM_ICON_URL.$row['trm_ID'].'.png';
                if(file_exists(HEURIST_TERM_ICON_DIR.$row['trm_ID'].'.png')){
                    print '<tr><td class="legend_icon"><img src="'.$filename.'"></td>';
                    print '<td class="legend_text">'.$row['trm_Label'].'</td></tr>';

                }
            }
            ?>
        </tbody>
    </table>

    <p><b>PEOPLE</b></p>
    <table>
        <tbody>
            <?php
            $query = 'SELECT trm_ID, trm_Label, trm_Code from defTerms where trm_ParentTermID='.PERSON_ROLE.' ORDER BY trm_Label';
            $res = $system->get_mysqli()->query($query);
            while($row = $res->fetch_assoc()) {
                $filename = HEURIST_TERM_ICON_URL.$row['trm_ID'].'.png';
                // TODO: Remove, enable or explain
                // print $filename.'<br>';
                // print HEURIST_TERM_ICON_DIR.$row['trm_ID'].'.png<br>';
                if(file_exists(HEURIST_TERM_ICON_DIR.$row['trm_ID'].'.png')){
                    print '<tr><td class="legend_icon"><img src="'.$filename.'"></td>';
                    print '<td class="legend_text">'.$row["trm_Label"].'</td></tr>';

                }
            }
            ?>
        </tbody>
    </table>

</div>

