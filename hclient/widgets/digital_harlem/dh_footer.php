<?php

/**
* dh_footer.php (Digital Harlem): Writes the footer with statistical information about the database
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__)."/../../../hsapi/System.php");
require_once(dirname(__FILE__)."/../../../hsapi/dbaccess/db_recsearch.php");

$statistics = "";
$system = new System();
// connect to given database
if(@$_REQUEST['db'] && $system->init(@$_REQUEST['db'])){
    include('dh_stats.php');
}
?>
<style>
    #footer {
        margin: 0px;
        padding: 0px;
        width: 100%;
        height: 40px;
        background-color: #111111;
        text-align: left;
        border-top: 1px dotted #BFBFBF;
        border-bottom: 1px dotted #BFBFBF;
        text-align:center;
    }


    .copyright {
        margin: 0px 0px 0px 20px;
        padding: 14px 0px 0px 0px;
        font-size: 9px;
        color: #BFBFBF;
        clear: left;
    }



    .copyright a, .copyright a:visited {
        font-family: Verdana, Arial, Helvetica, sans-serif;
        font-size: 9px;
        font-weight: bold;
        color: #ffffff;
        text-decoration: none;
    }



    .copyright a:hover {
        background-color: #ffffff;
        color: #111111;
        text-decoration: none;
    }

</style>

<div id="footer">
    <div class="copyright">
    License: <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank">Creative Commons (CC BY-NC-SA 4.0)</a>. Design Damian Evans 2007, redeveloped Ian Johnson & Artem Osmakov 2015. <a href="http://heuristnetwork.org/" target="_blank">Powered by
        <img src="hclient/assets/branding/h4logo16_small.png" alt="Heurist Academic Knowledge Management System" align="absmiddle"></a>.<!--Admin log in/out <a href="login.php"> here</a>.--></div>
</div>




