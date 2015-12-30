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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/**
* filename, brief description, date of creation, by whom
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristNetwork.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
**/



header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

session_cache_limiter('no-cache');
define('SAVE_URI', 'disabled');
define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__)."/../../search/getSearchResults.php");

$args = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

$result = loadSearch($args);
if (array_key_exists("records", $result)) {
    // turn the associative array into the non-associative one HAPI expects
    foreach ($result["records"] as $i => $record) {
        $result["records"][$i] = array(
            @$record["rec_ID"],                   //  0
            null,                                //  1 ("version")
            @$record["rec_RecTypeID"],            //  2
            @$record["rec_Title"],                //  3
            @$record["details"],                  //  4
            @$record["rec_URL"],                  //  5
            @$record["rec_ScratchPad"],           //  6
            @$record["rec_OwnerUGrpID"],          //  7
            @$record["rec_NonOwnerVisibility"],           //  8
            @$record["rec_URLLastVerified"],    //  9
            @$record["rec_URLErrorMessage"],            // 10
            @$record["rec_Added"],                // 11
            @$record["rec_Modified"],             // 12
            @$record["rec_AddedByUGrpID"],        // 13
            @$record["rec_Hash"],                // 14
            @$record["bkm_ID"],                  // 15
            null,               // 16
            @$record["bkm_Rating"],      // 17
            null, //@$record["pers_interest_rating"],     // 18  // pass null to not affect ordering HAPI expects
            null, //@$record["pers_quality_rating"],      // 19  //saw FIXME: should just change the internal interface removing old args.
            @$record["tags"],                     // 20
            @$record["wgTags"],                   // 21
            @$record["notifies"],                 // 22
            @$record["comments"]                  // 23
        );
    }
}

print json_format($result);

?>
