<?php
/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
 * Smarty Heurist translation modifier plugin
 * Type:     modifier
 * Name:     returns translated value for given array of field values
 * Purpose:  
 *
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system, Smarty
* @subpackage  PluginsModifier
 *
 * @return string translated string
 */
$path = (dirname(__FILE__).'/../../../../../hsapi/utilities/utils_locale.php'); 
//file_exists($path);
require_once ($path);

function smarty_modifier_translate($input, $lang, $field=null)
{
    return getTranslation($input, $lang, $field);
}
