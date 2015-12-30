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
* removeDatabaseLocks.php, Removes all locks on the database. Ian Johnson 20/9/12
* We can get away with no checkiong b/c locks are administrative and collisons are almost inconceivable
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


    define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    if(isForAdminOnly("to perform this action")){
        return;
    }

    mysql_connection_overwrite(DATABASE);

    $query="delete from sysLocks";
    $res = mysql_query($query);
    if (!$res) {
        die('<p>Invalid query, please report to developers: '.$query.'  Error: '.mysql_error());
    }

    if (mysql_affected_rows()==0) {
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body class='popup'>
        <h2> There were no database locks to remove</h2>";
    }
    else {
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body class='popup'>
        <h2>Database locks have been removed </h2>";
    }

    print "</body>";
    print "</html>";

?>
