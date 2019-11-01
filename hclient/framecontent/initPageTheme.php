<?php
/**
* Loads Heurist user custom theme from usr_Preferences
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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
Admin section/smarty editor - use H3 styles from admin/global.css

*/
require_once(dirname(__FILE__)."/../../hsapi/System.php");

if(!isset($system)){

    // init main system class
    $system = new System();

    if(@$_REQUEST['db']){
        //if database is defined then connect to given database
        $system->init(@$_REQUEST['db']);
    }
}
if($system->is_inited()){
    $user = $system->getCurrentUser();
    $ut = @$user['ugr_Preferences']['custom_theme'];
    if($ut!=null){
        $ut = json_decode($ut, true);    
    }
}
if(!isset($ut) || !is_array($ut)){
    $ut = array();    
}

$def_ut = array(
'cd_bg'=>'#e0dfe0',
'cl_bg'=>'#ffffff',
'cd_input'=>'#F4F2F4',
'cd_color'=>'#333333',
'cd_input'=>'#F4F2F4',
'cd_border'=>'#95A7B7',

'ca_bg'=>'#364050',
'ca_color'=>'#ffffff',
'ca_input'=>'#536077',

'ce_bg' =>'#ECF1FB',
'ce_color'=>'#6A7C99',
'ce_input'=>'#ffffff',
'ce_helper'=>'#999999',
'ce_readonly'=>'#999999',
'ce_mandatory'=>'#CC0000',

'cd_corner'=>'0',

'sd_color' =>'#555555',
'sd_bg'    =>'#f2f2f2',

'sh_border' =>'#999999',
'sh_color'  =>'#2b2b2b',
'sh_bg'     =>'#95A7B7',

'sa_border' =>'#aaaaaa',
'sa_bg'     =>'#95A7B7',
'sa_color'  =>'#212121', 

'sp_border' =>'#003eff', 
'sp_color'  =>'#ffffff', 
'sp_bg'     =>'#9CC4D9'

);    

function uout($idx, $def){
    global $ut;
    if(@$ut[$idx]==null || @$ut[$idx]==''){
        print $def;
    }else{
        print $ut[$idx];
    }
}
?>
/* MAIN SCHEME */
.ui-dialog .ui-dialog-buttonpane button.ui-state-hover,
.ui-dialog .ui-dialog-buttonpane button.ui-state-focus  {
    background: none;
    background-color: <?php uout('cd_bg', '#e0dfe0');?> !important;  
}
textarea.ui-widget-content, input.ui-widget-content, select.ui-widget-content{
    background: <?php uout('cd_input', '#F4F2F4');?> !important; 
}
.ui-widget-content {
    border: 1px solid <?php uout('cd_bg', '#e0dfe0');?>; 
    background: <?php uout('cd_bg', '#e0dfe0');?>;
    color: <?php uout('cd_color', '#333333');?>;
}
.ui-widget-content a {
    color: <?php uout('cd_color', '#333333');?>;  
}
.ui-heurist-bg-light{
    background-color: <?php uout('cl_bg', '#ffffff');?> !important;
}
/* BORDERS, HEADERS AND DIALOG TITLE */ 
.ui-dialog {
    border: 2px solid <?php uout('cd_border', '#95A7B7');?> !important;
}
/* .ui-heurist-header1 - rare use @todo remove */
.ui-dialog .ui-dialog-buttonpane, .ui-heurist-header1 {
    background-color: <?php uout('cd_border', '#95A7B7');?> !important;
}
.ui-dialog-titlebar, .ui-progressbar-value {
    background: none;
    background-color: <?php uout('cd_border', '#95A7B7');?> !important;    
}
.ui-menu, .ui-heurist-border{
    border: 1px solid <?php uout('cd_border', '#95A7B7');?>;
}
.ui-menu-divider {
    border-top: 1px solid <?php uout('cd_border', '#95A7B7');?> !important;
}
.svs-acordeon{
    border-bottom: <?php uout('cd_border', '#95A7B7');?> 1px solid;
}
.svs-header{
    color: <?php uout('cd_border', '#95A7B7');?>;
}
/* ALTERNATIVE SCHEME (HEURIST HEADER) */
select.ui-heurist-header2, input.ui-heurist-header2{
    background-color:<?php uout('ca_input', '#536077');?> !important;    
}                                                  

.ui-heurist-header2, .ui-heurist-btn-header1 {
    background:<?php uout('ca_bg', '#364050');?> !important;    
    color:<?php uout('ca_color', '#ffffff');?> !important;
}
.ui-heurist-btn-header1 {
    border: none !important;
}
.ui-heurist-header2 a{
    color:<?php uout('ca_color', '#ffffff');?> !important;
}

/* EDITOR CONTENT */
.ent_wrapper.editor{
    background-color:<?php uout('ce_bg', '#ECF1FB');?> !important;
    font-size:0.9em;
}
.ent_wrapper.editor .header, .header>label{
    color: <?php uout('ce_color', '#6A7C99');?>;
}
.ent_wrapper.editor .text{
    background: none repeat scroll 0 0 <?php uout('ce_input', '#ffffff');?>  !important;
    border: 1px solid  <?php uout('cd_bg', '#e0dfe0' );?>;;
}
.ent_wrapper.editor .separator{
    color: <?php uout('ce_helper', '#999999');?>;
    border-top: 1px solid <?php uout('cd_border', '#95A7B7');?>;
}
.ent_wrapper.editor .smallbutton{
    color:<?php uout('cd_color', '#333333');?>;
}
.ent_wrapper.editor .heurist-helper1{
    color: <?php uout('ce_helper', '#999999');?>;
}
.mandatory > label, .required, .required > label{
    color: <?php uout('ce_mandatory', '#CC0000');?>;
}
.readonly{
    color: <?php uout('ce_readonly', '#999999');?>;
}

/* Corner radius */
.ui-corner-all,
.ui-corner-top,
.ui-corner-left,
.ui-corner-tl {
    border-top-left-radius: <?php uout('cd_corner', '0');?>px;
}
.ui-corner-all,
.ui-corner-top,
.ui-corner-right,
.ui-corner-tr {
    border-top-right-radius: <?php uout('cd_corner', '0');?>px;
}
.ui-corner-all,
.ui-corner-bottom,
.ui-corner-left,
.ui-corner-bl {
    border-bottom-left-radius: <?php uout('cd_corner', '0');?>px;
}
.ui-corner-all,
.ui-corner-bottom,
.ui-corner-right,
.ui-corner-br {
    border-bottom-right-radius: <?php uout('cd_corner', '0');?>px;
}


/* CLICKABLE: DEFAULT */
.ui-state-default,
.ui-widget-content .ui-state-default,
.ui-widget-header .ui-state-default,
.ui-button,
/* We use html here because we need a greater specificity to make sure disabled
works properly when clicked or hovered */
html .ui-button.ui-state-disabled:hover,
html .ui-button.ui-state-disabled:active {
    /*heurist*/
    border: 1px solid <?php uout('sd_bg', '#f2f2f2');?>; 
    background: <?php uout('sd_bg', '#f2f2f2');?>;
    font-weight: normal;
    color: <?php uout('sd_color', '#555555');?>;
}
.ui-state-default a,
.ui-state-default a:link,
.ui-state-default a:visited,
a.ui-button,
a:link.ui-button,
a:visited.ui-button,
.ui-button {
    color: <?php uout('sd_color', '#555555');?>;
    text-decoration: none;
}

/*  CLICKABLE: HOVER AND FOCUS */
.ui-button:hover,
.ui-button:focus {
    border: 1px solid <?php uout('sh_border', '#999999');?>;  /*for buttons change border only*/
}
.ui-state-hover,
.ui-widget-content .ui-state-hover,
.ui-widget-header .ui-state-hover,
.ui-state-focus,
.ui-widget-content .ui-state-focus,
.ui-widget-header .ui-state-focus{
    border: 1px solid <?php uout('sh_border', '#999999');?>;
    background: <?php uout('sh_bg', '#95A7B7');?>; 
    font-weight: normal;
    color: <?php uout('sh_color', '#2b2b2b');?>;
}
.ui-state-hover a,
.ui-state-hover a:hover,
.ui-state-hover a:link,
.ui-state-hover a:visited,
.ui-state-focus a,
.ui-state-focus a:hover,
.ui-state-focus a:link,
.ui-state-focus a:visited,
a.ui-button:hover,
a.ui-button:focus {
    color: <?php uout('sh_color', '#2b2b2b');?>;
    text-decoration: none;
}

.ui-visual-focus {
    box-shadow: 0 0 3px 1px rgb(94, 158, 214);
}

/*  CLICKABLE: ACTIVE */
.ui-state-active,
.ui-widget-content .ui-state-active,
.ui-widget-header .ui-state-active{
    border: 1px solid <?php uout('sa_border', '#aaaaaa');?>;
    background: <?php uout('sa_bg', '#95A7B7');?>;
    color: <?php uout('sa_color', '#212121');?>;
    font-weight: normal;
}
/*  CLICKABLE: PRESED */
a.ui-button:active,
.ui-button:active,
.ui-button.ui-state-active:hover {
    background: <?php uout('sp_bg', '#9CC4D9');?>; 
    border: 1px solid <?php uout('sp_border', '#003eff');?>;
    color: <?php uout('sp_color', '#ffffff');?>;
    font-weight: normal;
}
.ui-icon-background,
.ui-state-active .ui-icon-background {
    border: <?php uout('sp_border', '#003eff');?>;
    background-color: <?php uout('sp_color', '#ffffff');?>;
}
.ui-state-active a,
.ui-state-active a:link,
.ui-state-active a:visited {
    color: <?php uout('sa_color', '#212121');?>;
    text-decoration: none;
}
.fancytree-active > .fancytree-title{
    background: <?php uout('sa_bg', '#95A7B7');?> !important;
}
span.fancytree-node.fancytree-active{
    background: <?php uout('sa_bg', '#95A7B7');?> !important;
    color: <?php uout('sp_color', '#ffffff');?> !important;
}
span.fancytree-node:hover{
    background: <?php uout('sa_bg', '#95A7B7');?> !important;
    color: <?php uout('sp_color', '#ffffff');?> !important;
}

