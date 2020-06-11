<?php
/* 
THIS IS Heurist v.3. 
It is not used anywhere. This code either should be removed or re-implemented wiht new libraries
*/

/**
*
* annotatedTemplateProxy.php: Import rectype via annotated templates
* NOT USED at Sep 2016, pending having enough templates to make it worth putting back in tghe interface
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


// User must be system administrator or admin of the owners group for this database
require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');

if(isForAdminOnly("to modify database structure")){
    return;
}

if(@$_REQUEST['url']){

    $url = $_REQUEST['url'];
    $filename = HEURIST_UPLOAD_DIR."proxyremote_".str_replace("/","_",$url);
    downloadViaProxy($filename, "text/html", $url, false);
}
?>
