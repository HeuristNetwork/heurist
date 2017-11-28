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
* updateTagsSearchPopup.php
* used in search.js to add or remove tags for given list of records.
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


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");

mysql_connection_select(DATABASE);

$tags = null;

if(@$_REQUEST['recid']){

    $rec_ID = $_REQUEST['recid'];

    $tags = mysql__select_array('usrRecTagLinks, usrTags',
        'tag_Text',
        "rtl_TagID=tag_ID and rtl_RecID=$rec_ID and tag_UGrpID = ".
        get_user_id()." order by rtl_Order");
}
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link rel=stylesheet href="../../common/css/autocomplete.css">
        <link rel=stylesheet href="../../common/css/global.css">
        <link rel=stylesheet href="../../common/css/edit.css">
        <script src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <title>Tags</title>
        <style>
            .input-row div.input-header-cell, .input-row label {width:45px;max-width:45px}
            .input-row .input-cell #tags {background-color:#ECF1FB;min-width:100%;border:1px solid #DCDCDC;width:100%; white-space:nowrap}
            .actionButtons {position:absolute; left:5px; right:5px; bottom:10px}
            .actionButtons > * {margin:0 5px;}
        </style>
    </head>
    <body class="popup" width=450 height=260>
        <script src="../../common/js/utilsLoad.js"></script>
        <script src="../../common/php/displayPreferences.php"></script>
        <script src="autocompleteTags.js"></script>

        <div id="no-tags" style="display: none;">
            <div class="prompt" style="font-weight: bold; padding: 1em; border: 1px solid black; margin: 1em 1em 0 1em;"> You don't have any personal tags set for this record.  Tags are optional, but useful. </div>
        </div>
        <div>
            <div class="help prompt" style="padding-top: 20px; padding-bottom: 20px;">
                Type as many tags as you like, separated by commas. Tags may contain spaces.<br/><br/>
                Matching tags are shown as you type. Click on a listed tag to add it.<br/>
                <br/>
                New tags are added automatically and are specific to each user. <br/></div>
        </div>
        <?php
        if($tags && count($tags)>0){
            ?>
            <div class="input-row" style="color: #6A7C99;font-weight:bold;">
                <h3>Tags:&nbsp&nbsp</h3>

                <!--<div class="input-header-cell"><h3>Tags</h3></div>
                <div class="input-cell" id="divCurrentTags"></div>-->
                <?php
                $k = 0;
                for ($i=0; $i < count($tags); ++$i) {
                    $tag = $tags[$i];
                    if(strpos($tag,'~')===0) continue;
                    if ($k > 0) print ',&nbsp;';
                    print htmlspecialchars($tag);
                    $k++;
                }
                ?>

            </div>
            <?php
        }
        ?>
        <form onSubmit="window.close(true, $('#tags').val()); return false;">
            <div class="input-row">
                <div class="input-header-cell" style="width:145px !important;max-width:145px !important"><strong>Tags to add or remove</strong></div>
                <div class="input-cell" style="min-width:250px">
                    <input id="tags" name="tagString" type="text">
                    <!--<input type="button" value="Save" style="display:none">-->
                </div>
            </div>
        </form>
        <div id="top-tags-cell">
            <span class="prompt">Top:</span>
            <a href="#" class="add-tag-link" onClick="addTag('Favourites'); return false;">Favourites</a> <a href="#" class="add-tag-link" onClick="addTag('To Read'); return false;">To Read</a>
        </div>
        <div id="recent-tags-cell">
            <span class="prompt">Recent:</span>
        </div>

        <div id="add-remove-buttons" style="display: none;"></div>
        <div class="actionButtons">
            <span id="more" class="prompt"><!-- onClick="top.HEURIST.util.popupURL(top, href, {'close-on-blur': true,'no-help':true, 'no-close':true}); return false;" -->
                <a href="<?=HEURIST_BASE_URL?>context_help/tags.html" onClick="top.HEURIST.util.popupURL(window, this.href); return false;">More about tags</a></span>

            <input type=button value="add tags" id=add-button>
            <input type=button value="remove tags" id=remove-button>
        </div>

        <script>
            if (location.search.match(/no-tags/))	// only show the no-tags row if requested
                document.getElementById("no-tags").style.display = "";
        </script>

        <script>

            var tags = document.getElementById("tags");
            tags.value = tags.defaultValue = "";

            function addTag(tag) {
                var tags = document.getElementById("tags");

                var tagPos = tags.value.indexOf(tag);
                if (tagPos != -1) {
                    if (tags.value.substring(0, tagPos).match(/(?:^|,)\s*$/)  &&
                        tags.value.substring(tagPos + tag.length).match(/^\s*(?:,|$)/)) {
                        // tag is already there
                        return;
                    }
                }

                if (tags.value) tags.value += "," + tag;
                else tags.value = tag;

                if (tags.onchange) tags.onchange();

                tags.focus();
            }

            function addTagLink(tag, parentNode) {
                var newLink = document.createElement("a");
                newLink.href = "#";
                newLink.className = "add-tag-link";
                newLink.onclick = function() { addTag(tag); return false; };
                newLink.appendChild(document.createTextNode(tag));

                parentNode.appendChild(newLink);
            }

            var alphasort = function(a,b) { a = a.toLowerCase(); b = b.toLowerCase(); return (a < b)? -1 : (a > b)? 1 : 0; };

            var topTagsCell = document.getElementById("top-tags-cell");
            var topTags = top.HEURIST.user.topTags.slice(0);
            topTags.sort(alphasort);
            for (var i=0; i < topTags.length; ++i) {
                if(topTags[i].indexOf('~')===0) continue;
                addTagLink(topTags[i], topTagsCell);
            }
            var recentTagsCell = document.getElementById("recent-tags-cell");
            var recentTags = [];//top.HEURIST.user.recentTags.slice(0, 5);
            for (var i=0; i < top.HEURIST.user.recentTags.length; ++i){
                if(top.HEURIST.user.recentTags[i].indexOf('~')===0) continue;
                recentTags.push(top.HEURIST.user.recentTags[i]);
            }
            recentTags.sort(alphasort);
            for (var i=0; i < recentTags.length; ++i) {    // only use the last 5 tags here, we are starved for space
                addTagLink(recentTags[i], recentTagsCell);
                if(i>5) break;
            }

            top.HEURIST.registerEvent(window, "load", function() {

                if (location.search.match(/show-remove/)) {
                    $("input:submit[value=Save]").hide();
                    $("#add-remove-buttons").show();
                }

                $("#add-button").click(function() {
                    if (autocomplete.currentWordOkay()) {
                        window.close(true, $('#tags').val());
                    }
                });

                $("#remove-button").click(function() {
                    if (autocomplete.currentWordOkay()) {
                        window.close(false, $('#tags').val());
                    }
                });

                var tagsElt = document.getElementById('tags');
                window.autocomplete = new top.HEURIST.autocomplete.AutoComplete(tagsElt, top.HEURIST.util.tagAutofill, { nonVocabularyCallback: top.HEURIST.util.showConfirmNewTag });
                setTimeout(function() { tagsElt.focus(); }, 0);
            });

        </script>
    </body>
</html>
