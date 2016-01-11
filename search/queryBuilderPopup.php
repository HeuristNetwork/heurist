<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define ('SAVE_URI', 'DISABLED');
require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');

mysql_connection_select(USERS_DATABASE);
?>
<html>
    <head>
        <title>Advanced search builder</title>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../common/css/search.css">

        <script src='queryBuilder.js'></script>
        <script src='queryBuilderPopup.js'></script>

    </head>
    <body class="popup" width=700 height=500 style="overflow: hidden;" onLoad="load_query();">

        <!-- script type="text/javascript" src="../common/js/utilsUI.js"></script -->

        <div class="advanced-search-row" style="border-bottom: 1px solid #7f9db9;padding-bottom:10px;">

            <!-- TODO: Update Advanced Search to version 4 -->
            <div style="color: red; margin-bottom: 10px;">
                THIS FUNCTION NOT FULLY CONVERTED TO VSN 4. Some fields do not work correctly. You may need to edit search string manually.
            </div>

            <b>Search&nbsp;string: &nbsp;</b>
            <div class="searchInput" style="position:relative; display:inline-block;">
                <input id=q name=q type=text style="width:500px; border: none;">
                <button id="btnSearch" type="button" onClick="do_search();" class="button">Search</button>
            </div>
            <div style="width: 100%; text-align: left; padding-top: 6px; padding-left: 85px;">
                Build search using fields below, or edit the search string directly.
                <a href="#" onClick="clear_fields(); return false;">Clear search string</a>
            </div>

        </div>
        <div style="padding-left: 30px; color: #666;border-bottom: 1px solid #7f9db9;padding-bottom:10px;">
            <p>See also: <a href="#" onClick="top.HEURIST.util.popupURL(window, '<?=HEURIST_BASE_URL?>context_help/advanced_search.html'); return false;"><b>help for advanced search</b></a>.</p>
            <div>Use <b>tag:</b>, <b>type:</b>, <b>url:</b>, <b>notes:</b>, <b>owner:</b>, <b>user:</b>, <b>field:</b> and <b>all:</b> modifiers.<br>
                To find records with geographic objects that contain a given point, use <b>latitude</b> and <b>longitude</b>, e.g.
                <b>latitude:10 longitude:100</b><br>
                Use e.g. <b>title=</b><i>xxx</i> to match exactly, similarly <b>&lt;</b> or <b>&gt;</b>.<br>
                To find records that include either of two search terms, use an uppercase OR. e.g. <b>timemap OR &quot;time map&quot;</b><br>
                To omit records that include a search term, precede the term with a single dash. e.g. <b>-maps -tag:timelines</b></div>

            <div style="text-align:right;padding-right:10px;">
                <label for="setInd">Set individually: </label>
                <input id="setind" iname="setind" type="checkbox" style="display:inline-block !important;" checked="checked" onchange="setIndividually(this)" />
            </div>
        </div>

        <div class="advanced-search-row"  style="border-bottom: 1px solid #7f9db9;padding-bottom:10px;">
            <label for="sortby">Sort by:</label>
            <script>
                function setAscDescLabels(sortbyValue) {
                    var ascLabel = document.getElementById("asc-label");
                    var descLabel = document.getElementById("desc-label");

                    if (sortbyValue === "r"  ||  sortbyValue === "p") {
                        // ratings should have best first,
                        // popularity should have most-popular first
                        ascLabel.text = "decreasing";
                        descLabel.text = "increasing";
                    }
                    else {
                        ascLabel.text = "ascending";
                        descLabel.text = "descending";
                    }
                }
            </script>
            <select name=sortby id=sortby onChange="setAscDescLabels(options[selectedIndex].value); update(this);">
                <option value=t>record title</option>
                <option value=id>record id</option>
                <option value=rt>record type</option>
                <option value=u>record URL</option>
                <option value=m>date modified</option>
                <option value=a>date added</option>
                <option value=r>personal rating</option>
                <option value=p>popularity</option>
                <optgroup label="Detail fields">
                    <?php
                    $res = mysql_query('select dty_ID, dty_Name from '.DATABASE.'.defDetailTypes order by dty_Name');
                    while ($row = mysql_fetch_assoc($res)) {
                        ?>
                        <option value="f:&quot;<?= $row['dty_Name'] ?>&quot;"><?= htmlspecialchars($row['dty_Name']) ?></option>
                        <?php	}	?>
                </optgroup>
            </select>

            <!-- TODO: Throws out alignment and doesn't seem to work. May need to reverse sense to make checked = bibliographic
            <label for="sortby_multiple">Bibliographic: </label>
            <input type=checkbox id=sortby_multiple onClick="update(document.getElementById('sortby'));">
            -->



            <select id=ascdesc style="width: 100px;" onChange="update(document.getElementById('sortby'));">
                <option value="" selected id=asc-label>ascending</option>
                <option value="-" id=desc-label>descending</option>
            </select>
            <!-- <span>Bibliographic (sort by first value only)</span> -->

            <button type="button" style="visibility:visible; float: right;" onClick="add_to_search('sortby');" class="button" title="Add to Search">Add</button>
        </div>


        <div class="advanced-search-row">
            <label for="type">Record type:</label>
            <?php
            $res = mysql_query("select distinct rty_ID,rty_Name,rty_Description, rtg_Name
                from defRecTypes left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID
            where rty_ShowInLists = 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
            ?>
            <select name="type" id="type" onChange="update(this);">
                <option selected="selected" value="">(select record type)</option>
                <?php
                $section = "";
                while ($row = mysql_fetch_assoc($res)) {
                    if ($row["rtg_Name"] != $section) {
                        if ($section) print "</optgroup>\n";
                        $section = $row["rtg_Name"];
                        print '<optgroup label="' . htmlspecialchars($section) . ' types">';
                    }
                    ?>
                    <option value="<?= $row["rty_ID"] ?>" title="<?= htmlspecialchars($row["rty_Description"]) ?>"><?= htmlspecialchars($row["rty_Name"]) ?></option>
                    <?php
                }
                ?>
            </select>

            <button type="button" style="visibility:visible; float: right;" onClick="add_to_search('type');" class="button" title="Add to Search">Add</button>
        </div>

        <div class="advanced-search-row">
            <label for="tag">Tags:</label>
            <input name=tag id="tag" onChange="update(this);" onKeyPress="return keypress(event);">
            <?php
            $res = mysql_query('select concat('.GROUPS_NAME_FIELD.', "\\\\", tag_Text) from '.DATABASE.'.usrTags, '.USER_GROUPS_TABLE.', '.GROUPS_TABLE.' where tag_UGrpID='.USER_GROUPS_GROUP_ID_FIELD.' and '.USER_GROUPS_GROUP_ID_FIELD.'='.GROUPS_ID_FIELD.' and '.USER_GROUPS_USER_ID_FIELD.'=' . get_user_id() . ' order by '.GROUPS_NAME_FIELD.', tag_Text');
            if (mysql_num_rows($res) > 0) {
                ?>
                <span style="padding-left:28px;">or</span>
                <select id="wgtag" name="wgtag" onChange="update(this);" onKeyPress="return keypress(event);" style="width: 200px;">
                    <option value="" selected>(select...)</option>
                    <?php		while ($row = mysql_fetch_row($res)) {	?>
                        <option value="<?= htmlspecialchars($row[0]) ?>"><?= htmlspecialchars($row[0]) ?></option>
                        <?php		}	?>
                </select>
                <?php
            } else {
                ?>
                <?php
            }
            ?>
            <button type="button" style="visibility:visible; float: right;" onClick="{add_to_search('tag');}" class="button" title="Add to Search">Add</button>
        </div>

        <div class="advanced-search-row">
            <label>Fields:</label>
            <div style="width:200px;display:inline-block;text-align:right;">Title (the constructed title for the record):</div>
            <span>contains</span><input name=title id=title onChange="update(this);" onKeyPress="return keypress(event);">
            <button type="button" style="visibility:visible; float: right;" onClick="add_to_search('title');" class="button" title="Add to Search">Add</button>
        </div>
        <div class="advanced-search-row">
            <div style="width:313px;display:inline-block;text-align:right;">URL (the hyperlink for the record):</div>
            <span>contains</span><input name=url id=url onChange="update(this);" onKeyPress="return keypress(event);">
            <button type="button" style="visibility:visible; float: right;" onClick="add_to_search('url');" class="button" title="Add to Search">Add</button>
        </div>

        <div class="advanced-search-row">
            <div style="display:inline-block;">
                <label>&nbsp;</label>
                <select name=fieldtype id=fieldtype onChange="handleFieldSelect(event);">
                    <option value="" style="font-weight: bold;">Any field</option>
                    <optgroup id="rectype-specific-fields" label="rectype specific fields" style="display: none;"></optgroup>
                    <optgroup label="Generic fields">
                        <?php
                        $res = mysql_query('select dty_ID, dty_Name from '.DATABASE.'.defDetailTypes order by dty_Name');
                        while ($row = mysql_fetch_assoc($res)) {
                            print "<option value='".$row['dty_ID']."'>".htmlspecialchars($row['dty_Name'])."</option>";
                        }
                        ?>
                    </optgroup>
                </select>
                <span>contains</span>
            </div>
            <div id="ft-enum-container" style="display:none;width: 202px;"></div>
            <div id="ft-input-container" style="display:inline-block; width: 202px;"><input id="field" name="field" onChange="update(this);" onKeyPress="return keypress(event);" style="width: 200px;"></div>
            <span><button type="button" style="visibility:visible; float: right;" onClick="add_to_search('field');" class="button" title="Add to Search">Add</button></span>
        </div>

        <!--
        <div class="advanced-search-row">
        <label for="notes">Notes:</label>
        <input id=notes name=notes onChange="update(this);" onKeyPress="return keypress(event);">
        </div>
        -->

        <?php
        $groups = mysql__select_assoc(USERS_DATABASE.".".USER_GROUPS_TABLE." left join ".USERS_DATABASE.".".GROUPS_TABLE." on ".USER_GROUPS_GROUP_ID_FIELD."=".GROUPS_ID_FIELD, GROUPS_ID_FIELD, GROUPS_NAME_FIELD, USER_GROUPS_USER_ID_FIELD."=".get_user_id()." and ".GROUPS_TYPE_FIELD."='workgroup' order by ".GROUPS_NAME_FIELD);
        if ($groups  &&  count($groups) > 0) {
            ?>
            <div class="advanced-search-row">
                <label for="user">Owned&nbsp;by:</label>
                <select name="owner" id="owner" onChange="update(this);" style="width:200px;">
                    <option value="" selected="selected">(any owner or ownergroup)</option>
                    <option value="&quot;<?= get_user_username()?>&quot;"><?= get_user_name()?></option>
                    <?php	foreach ($groups as $id => $name) { ?>
                        <option value="&quot;<?= htmlspecialchars($name) ?>&quot;"><?= htmlspecialchars($name) ?></option>
                        <?php	} ?>
                </select>
                <button type="button" style="visibility:visible; float: right;" onClick="add_to_search('owner');" class="button" title="Add to Search">Add</button>
            </div>
            <?php
        }
        ?>


        <div class="advanced-search-row">
            <label for="user">Bookmarked&nbsp;by:</label>
            <input onChange="refilter_usernames()" onKeyPress="invoke_refilter()" value="(search for a user)" id=user_search onFocus="if (value == defaultValue) { value = ''; }">
            <span style="padding-left:15px;">then</span>
            <select name="user" id="user" onChange="style.color = 'black'; update(this);" onkeypress="return keypressRedirector(event)">
                <option value="" selected="selected">(matching users)</option>
            </select>
            <select name="users_all" id="users_all" style="display: none;">
                <?php
                $query = 'select '.USERS_ID_FIELD.', concat('.USERS_FIRSTNAME_FIELD.'," ",'.USERS_LASTNAME_FIELD.') as fullname from '.USERS_TABLE.
                ' where '.USERS_ACTIVE_FIELD.' = "Y" and '.GROUPS_TYPE_FIELD.'="user" order by fullname';
                $res = mysql_query($query);
                while ($row = mysql_fetch_row($res)) {
                    print '<option value="&quot;'.htmlspecialchars($row[1]).'&quot;">'.htmlspecialchars($row[1]).'</option>'."\n";
                }
                ?>
            </select>
            <button type="button" style="visibility:visible; float:right;" onClick="add_to_search('user');" class="button" title="Add to Search">Add</button>
            <!--
            <img src=<?=HEURIST_BASE_URL?>common/images/leftarrow.gif>
            <span style="color: green;">Type name to find users</span>
            -->
        </div>

    </body>
</html>
