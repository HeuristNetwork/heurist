<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* dispatcher for search multi record update functions
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



  define('SAVE_URI', 'disabled');

  require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
  if (!is_logged_in()) return;

  require_once("actionMethods.php");

  $result = null;

  // decode and unpack data
  if(@$_REQUEST['data']){
    $data = json_decode(urldecode(@$_REQUEST['data']), true);

    switch (@$_REQUEST['action']) {
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

      case 'replace_term':
        $result = replace_term($data);
        break;

      case 'replace_text':
        $result = replace_text($data);
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

  }else{
    $result = array("problem"=>"'data' parameter is missing for search result action '".@$_REQUEST['action'] ? $_REQUEST['action']:"action missing"."'");
  }


  header('Content-type: text/javascript');
  if($result){
    print json_format($result);
  }else{
    $result = array("problem"=>"'action' parameter is missing for search result action");
  }

  exit();
?>
