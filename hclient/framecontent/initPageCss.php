<?php
/**
* Include into page minium set of css for Heurist
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

/*

There are 3 color themes in Heurist. 
Main (gray) with option of different bg (white) for lists and popups
Editor (light blue)
Header (iron head flower color)
Each theme has its own set for text/label, background, inputs bg and border colors.  Main and Editor share the same Color for buttons/clickable elements (default:lightgray; focus:gray with border; pressed:blue). Header’s buttons are always the same color as main background.
There are still some exception:
Result list color scheme - may I uniform colors with our general scheme?
Optgroup (group header in dropdown)  bg: #ECF1FB - can be changed to #95A7B7 (headers)
Resource selector (in edit form)  bg: #F4F2F4 - can be changed to button light gray or pressed button (light blue)
Select linked record button   bg:#f0ecf0 - can be changed to button light gray or pressed button (light blue)
Scrollbar tracks and thumbs  rgba(0,0,0,0.3)/#bac4cb

*/
if(isset($system) && $system->is_inited()){
    $current_user = $system->getCurrentUser();
    $layout_theme = @$current_user['ugr_Preferences']['layout_theme'];
}else{    
    $layout_theme = 'base';
}

if($layout_theme==null || $layout_theme=='' || $layout_theme=="heurist" || $layout_theme=="base"){
    $cssLink = PDIR.'external/jquery-ui-themes-1.12.1/themes/base/jquery-ui.css';
}else{
    //load one of standard themes from jquery web resource
    $cssLink = 'https://code.jquery.com/ui/1.12.1/themes/'.$layout_theme.'/jquery-ui.css';
}
?>
<!-- jQuery UI CSS -->
<link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>">
<!-- Heurist CSS -->
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
<?php 
    $lt = @$_REQUEST['ll'];
    if(!($lt=='H5Default' || $lt=='Beyond1914' ||  $lt=='UAdelaide')){?>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h6styles.css" />
<?php } ?>
<!-- Heurist Color Themes -->
<style id="heurist_color_theme">
<?php
//was PDIR.
    include dirname(__FILE__).'/initPageTheme.php';
?>
</style>
