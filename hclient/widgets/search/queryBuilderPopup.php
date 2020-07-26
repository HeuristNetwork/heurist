<?php

/*
* Copyright (C) 2005-2020 University of Sydney
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
* Advanced query builder - TO BE REPLACED
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

define('PDIR','../../../');  //need for proper path to js and css    
require_once(dirname(__FILE__).'/../../../hclient/framecontent/initPage.php');

$mysqli = $system->get_mysqli();
?>
        <title>Advanced search builder</title>
        
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR ?>common/css/global.css">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR ?>common/css/search.css">

        <script src='queryBuilder.js'></script>
        <script src='queryBuilderPopup.js'></script>
        
        <script>
            function onPageInit(success){
                if(success){
                    load_query();
                }
            }        
        </script>

    </head>
    <body class="popup" width=700 height=500 style="overflow: hidden;">

        <div class="advanced-search-row" style="border-bottom: 1px solid #7f9db9;padding-bottom:10px;">

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
            <p>See also: <a href="<?=HEURIST_BASE_URL?>context_help/smarty_reports.html"
                        onClick="window.hWin.HEURIST4.msg.showDialog( this.href );return false;">
                        <b>help for advanced search</b></a>.</p>
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
                    $res = $mysqli->query('select dty_ID, dty_Name from defDetailTypes WHERE dty_Type!="separtor" order by dty_Name');
                    while ($row = $res->fetch_assoc()) {
                        ?>
                        <option value="f:&quot;<?= $row['dty_Name'] ?>&quot;"><?= htmlspecialchars($row['dty_Name']) ?></option>
                        <?php	}
                            $res->close();
                        ?>
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
            $res = $mysqli->query("select distinct rty_ID,rty_Name,rty_Description, rtg_Name
                from defRecTypes left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID
            where rty_ShowInLists = 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
            ?>
            <select name="type" id="type" onChange="update(this);">
                <option selected="selected" value="">(select record type)</option>
                <?php
                $section = "";
                while ($row = $res->fetch_assoc()) {
                    if ($row["rtg_Name"] != $section) {
                        if ($section) print "</optgroup>\n";
                        $section = $row["rtg_Name"];
                        print '<optgroup label="' . htmlspecialchars($section) . ' types">';
                    }
                    ?>
                    <option value="<?= $row["rty_ID"] ?>" title="<?= htmlspecialchars($row["rty_Description"]) ?>"><?= htmlspecialchars($row["rty_Name"]) ?></option>
                    <?php
                }
                $res->close();
                ?>
            </select>

            <button type="button" style="visibility:visible; float: right;" onClick="add_to_search('type');" class="button" title="Add to Search">Add</button>
        </div>

        <div class="advanced-search-row">
            <label for="tag">Tags:</label>
            <input name=tag id="tag" onChange="update(this);" onKeyPress="return keypress(event);">
            <?php
            $res = $mysqli->query('select concat(ugr_Name, "\\\\", tag_Text) from usrTags, sysUsrGrpLinks, '
            .'sysUGrps where tag_UGrpID=ugl_GroupID and ugl_GroupID=ugr_ID and ugl_UserID=' 
            . $system->get_user_id() . ' order by ugr_Name, tag_Text');
            if ($res->num_rows > 0) {
                ?>
                <span style="padding-left:28px;">or</span>
                <select id="wgtag" name="wgtag" onChange="update(this);" onKeyPress="return keypress(event);" style="width: 200px;">
                    <option value="" selected>(select...)</option>
                    <?php		while ($row = $res->fetch_row()) {	?>
                        <option value="<?= htmlspecialchars($row[0]) ?>"><?= htmlspecialchars($row[0]) ?></option>
                        <?php		}	?>
                </select>
                <?php
            } else {
                ?>
                <?php
            }
            $res->close();
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
                        $res = $mysqli->query('select dty_ID, dty_Name from defDetailTypes where dty_Type!="separtor" order by dty_Name');
                        while ($row = $res->fetch_assoc()) {
                            print "<option value='".$row['dty_ID']."'>".htmlspecialchars($row['dty_Name'])."</option>";    
                        }
                        $res->close();
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

        $groups = mysql__select_assoc2($mysqli, 
         'select ugr_ID, ugr_Name from sysUsrGrpLinks left join sysUGrps on ugl_GroupID=ugr_ID '
        .' where ugl_UserID='.$system->get_user_id().' and ugr_Type="workgroup" order by ugr_Name');
        
        $cuser = $system->getCurrentUser();
        
        if ($groups  &&  count($groups) > 0) {  //replace with createUserGroupsSelect
            ?>
            <div class="advanced-search-row">
                <label for="user">Owned&nbsp;by:</label>
                <select name="owner" id="owner" onChange="update(this);" style="width:200px;">
                    <option value="" selected="selected">(any owner or ownergroup)</option>
                    <option value="&quot;<?=$cuser['ugr_Name']?>&quot;"><?=$cuser['ugr_Name']?></option>
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
                <?php  //replace with user selector
                $query = 'select ugr_ID, concat(ugr_FirstName," ",ugr_LastName) as fullname from sysUGrps '.
                ' where ugr_Enabled = "Y" and ugr_Type="user" order by fullname';
                $res = $mysqli->query($query);
                while ($row = $res->fetch_row()) {
                    print '<option value="&quot;'.htmlspecialchars($row[1]).'&quot;">'.htmlspecialchars($row[1]).'</option>'."\n";
                }
                $res->close();
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
