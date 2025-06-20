<?php
/**
 * index.php - Handles redirection for record viewing and file access.
 *
 * @fileOverview This script determines the appropriate target script based on the
 * query parameters. If 'file', 'thumb', or 'icon' parameters are present,
 * it redirects to file access scripts (`fileGet.php` or `fileDownload.php`).
 * Otherwise, it redirects to `renderRecordData.php` for displaying record information.
 * All original query parameters are preserved during redirection.
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\record
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson ian.johnson.heurist@gmail.com
 * @since       4.0
 */

// Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
// with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
// Unless required by applicable law or agreed to in writing, software distributed under the License is
// distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
// See the License for the specific language governing permissions and limitations under the License.
//
// REMARK: Removed redundant license block comment, as the license is already specified in the PHPDoc.
if (array_key_exists('file',$_REQUEST) || array_key_exists('thumb',$_REQUEST) ||
    array_key_exists('icon',$_REQUEST)){

    if(array_key_exists('icon',$_REQUEST))
    {
        //download entity icon or thumbnail
        $script_name = '../../hserv/controller/fileGet.php';
    }else {
        //download file, thumb or remote url for recUploadedFiles
        $script_name = '../../hserv/controller/fileDownload.php';
    }
}else{
    $script_name = 'renderRecordData.php';
}
//to avoid "Open Redirect" security warning
parse_str($_SERVER['QUERY_STRING'], $vars);
$query_string = http_build_query($vars);

header( 'Location: '.$script_name.'?'.$query_string );

