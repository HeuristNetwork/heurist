<?php

/**
* getDBFolders.php: returns zip archive with content of some folders from upload directory 
* it will be used for creation of new database that use this one as a template
*
* @param db = database name
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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
require_once(dirname(__FILE__).'/../../common/php/dbUtils.php');

$uploadPath = HEURIST_FILESTORE_DIR; //HEURIST_UPLOAD_ROOT.HEURIST_DBNAME;

$folders = array( "rectype-icons", "settings", "smarty-templates", "xsl-templates");

$zipfile = HEURIST_FILESTORE_DIR."folders.zip";

if(zip( HEURIST_FILESTORE_DIR, $folders, $zipfile, false)){

header('Content-type: binary/download'); 
header('Content-Disposition: attachment; filename="folders.zip"');
readfile($zipfile);
    
}else{
//header('Content-type: multipart/x-zip'); //'binary/download'
}
?>
