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
* getDatabaseURL.php - requests URL for registered DB by its ID from Heurist Master Index
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
    define('ISSERVICE',1);

    require_once(dirname(__FILE__)."/../../../common/config/initialise.php");
    require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');

    mysql_connection_insert("hdb_Heurist_Master_Index");

    header("Content-type: text/javascript");

    $rec = array();
    $database_id = @$_REQUEST["id"];
    if($database_id){

        $res = mysql_query("select rec_Title, rec_URL from Records where rec_RecTypeID=22 and rec_ID=".$database_id);
        if ($res){
            $rec = mysql_fetch_assoc($res);
        }
    }

    print json_format($rec, true);
?>