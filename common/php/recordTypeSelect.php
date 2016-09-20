<?php

/**
* recordTypeSelect.php
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
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


    require_once(dirname(__FILE__).'/../../common/config/initialise.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    mysql_connection_select(DATABASE);

    $res = mysql_query("select distinct rty_ID,rty_Name,rty_Description, rtg_Name
            from defRecTypes left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID
        where rty_ShowInLists = 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
?>
<select style="font-weight:bold; margin: 3px; margin-bottom:10px;" name="ref_type" id="rectype_elt"
    title="Select record type" >
    <option selected disabled value="0">select ...</option>
    <?php
        $section = "";
        while ($row = mysql_fetch_assoc($res)) {
            if ($row["rtg_Name"] != $section) {
                if ($section) print "</optgroup>\n";
                $section = $row["rtg_Name"];
                print '<optgroup label="' . htmlspecialchars($section) . '">';
            }
        ?>
         <option value="<?= $row["rty_ID"] ?>"
            title="<?= htmlspecialchars($row["rty_Description"]) ?>" ><?= htmlspecialchars($row["rty_Name"]) ?>                             </option>
        <?php
        }//while
        if ($section) print "</optgroup>\n";
    ?>
</select>