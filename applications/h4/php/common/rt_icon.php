<?php

    /** 
    *  Proxy for rectype icons
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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
    require_once (dirname(__FILE__).'/../System.php');

    $system = new System();   
    
    $db = @$_REQUEST['db'];
    $rectype_id = @$_REQUEST['id'];
    
    $system->initPathConstants($db);
    
    if(substr($rectype_id,-4,4) != ".png") $rectype_id = $rectype_id . ".png";
    
    $filename = HEURIST_ICON_DIR . $rectype_id;

//print $filename;    
    
    if(file_exists($filename)){
        ob_start();    
        header('Content-type: image/png');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        ob_clean();
        flush();        
        readfile($filename);
    }
    
?>
