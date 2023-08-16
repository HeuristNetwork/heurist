<?php

/**
* Redirection for images
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
if (array_key_exists('file',$_REQUEST) || array_key_exists('thumb',$_REQUEST) ||
    array_key_exists('icon',$_REQUEST)){
              
    if(array_key_exists('icon',$_REQUEST))
    {
        //download entity icon or thumbnail
        $script_name = 'fileGet.php';        
    }else {
        //download file, thumb or remote url
        $script_name = 'file_download.php';        
    }
        
    //to avoid "Open Redirect" security warning    
    parse_str($_SERVER['QUERY_STRING'], $vars);
    $query_string = http_build_query($vars);
    
    header( 'Location: ../../hsapi/controller/'.$script_name.'?'.$query_string );
    
    return;
}


