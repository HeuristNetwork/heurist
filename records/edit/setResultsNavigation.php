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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

$context = array("count" => 0);

if (is_logged_in() && @$_REQUEST["s"]  && @$_REQUEST["id"]) {

    $sid = $_REQUEST["s"];

    session_start();

    if (!@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["search-results"][$sid] ||
	    !@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]["infoByDepth"] ||
	    !@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]["infoByDepth"][0]) {

    }else{

        $results = $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["search-results"][$sid]["infoByDepth"][0]["recIDs"];

        if(count($results)>0){
            foreach ($results as $i => $rec_id) {
                if ($rec_id == $_REQUEST["id"]) {
                    $context = array("prev" => @$results[$i-1] ? $results[$i-1] : null,
                                     "next" => @$results[$i+1] ? $results[$i+1] : null,
                                     "pos" => $i + 1,
                                     "count" => count($results));
                    print json_format($context);
                    exit();
                }
            }
        }
    }

}
print json_format($context);
