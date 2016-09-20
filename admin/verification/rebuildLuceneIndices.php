<?php

    /**
    * rebuildLuceneIndices.php: Rebuilds all Lucence (Elastic Search) indices for the database
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

    require_once(dirname(__FILE__).'/../../records/index/elasticSearchFunctions.php');

    if(isForAdminOnly("to rebuild the Lucene indices")){
        return;
    }

    mysql_connection_overwrite(DATABASE);
    if(mysql_error()) {
        die("MySQL error - unable to connect to database, MySQL error: "+mysql_error());
    }

    print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body class='popup'>
    Rebuilding Lucene indices for all tables ... ";

    $code = buildAllIndices(HEURIST_DBNAME);
    if ($code ==0) {
        print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body class='popup'>
        <h2> Database indices have been rebuilt, please check for errors above</h2>";
    } else {
        die('<p>Failed to rebuild indices, please consult Heurist support team (error code: ' + $code + ')</p>');
    }

    print "</body>";
    print "</html>";

?>
