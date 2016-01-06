<?php

    /**
    * Page for popup modal dialog to perform various acvtions related to records
    *
    *    add/edit/delete details
    *    set relationship
    *    bookmark/unbookmark
    *    assign personal tags
    *    assign wg tags
    *    rating
    *    ownership(who can edit), access(visibility)
    *
    *    merge
    *    delete
    *    mailing list based on records
    *
    *    STRUCTURE:
    *    header that describes the action
    *    selector of records: all, selected, by record type
    *    widget to enter data
    *    request to server
    *    results
    *        given
    *        processed
    *        rejected (rights)
    *        error
    *
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

require_once(dirname(__FILE__)."/initPage.php");

//verify that user is logged in
loginRequired();

//verify parameter action
$action_type = @$_REQUEST['action'];
$allowed_actions = array('add_detail');

if(!in_array($action_type, $allowed_actions)){
    header('Location: '.ERROR_REDIR.'?msg=Action is not defined or not allowed');
    exit();
}

/*
    switch ($action_type) {
      case 'delete_bookmark':
        $result = delete_bookmarks($data);
        break;

      case 'add_wgTags_by_id':
        $result = add_wgTags_by_id($data);
        break;

      case 'remove_wgTags_by_id':
        $result = remove_wgTags_by_id($data);
        break;

      case 'add_tags':
        $result = add_tags($data);
        break;

      case 'remove_tags':
        $result = remove_tags($data);
        break;

      case 'bookmark_reference':
        $result = bookmark_references($data);
        break;

      case 'bookmark_and_tag':
      case 'bookmark_and_tags':   //save collection of ids with some tag
        $result = bookmark_and_tag_record_ids($data);
        break;

      case 'add_detail':
        $result = add_detail($data);
        break;

      case 'replace_detail':
        $result = replace_detail($data);
        break;

      case 'delete_detail':
        $result = delete_detail($data);
        break;

      case 'set_ratings':
        $result = set_ratings($data);
        break;

      case 'save_search':
        $result = save_search($data);
        break;

      case 'bookmark_tag_and_ssearch': //from saveCollectionPopup.html   NOT USED SINCE 2012-02-13
        $result = bookmark_tag_and_save_search($data);
        break;

      case 'set_wg_and_vis':
        $result = set_wg_and_vis($data);
        break;
    }
*/

?>
        <script type="text/javascript" src="recordAction.js"></script>
        <script type="text/javascript" src="../../apps/editing/editing_input.js"></script>

        <script type="text/javascript">
            // Callback function on map initialization
            function onPageInit(success){
                if(success){
                    var recordAction = new hRecordAction('<?php echo $action_type;?>');
                }
            }
        </script>
    </head>

    <!-- HTML -->
    <body>
        <div id="div_header" tyle="padding: 0.2em; min-width: 600px;">

        </div>

        <fieldset>
            <div tyle="padding: 0.2em; width: 100%;" class="input">
                    <div class="header" style="padding-left: 16px;"><label for="sel_record_scope">Records scope:</label></div>
                    <select id="sel_record_scope" class="text ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>
            <div id="div_sel_fieldtype" tyle="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding-left: 16px;"><label for="sel_record_scope">Select field:</label></div>
                <select id="sel_fieldtype" class="text ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>
        </fieldset>

        <div id="div_widget" tyle="padding: 0.2em; width: 100%;">
            <fieldset>
            </fieldset>
        </div>

        <div id="div_result" tyle="padding: 0.2em; width: 100%;">

        </div>

    </body>
</html>
