<?php
    /**
    * UploadHandlerInit.php
    * Entry point for fileupload widget. It is used in multi file uploads. 
    * For single file upload see fileUpload.php
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     6
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


error_reporting(E_ALL | E_STRICT);

require_once(dirname(__FILE__)."/../System.php");

$system = new System();
$system->initPathConstants(@$_REQUEST['db']);

require('UploadHandler.php');
$upload_handler = new UploadHandler();
