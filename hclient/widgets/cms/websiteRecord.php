<?php
if(!@$_REQUEST['field']){ 
    print '<!DOCTYPE html>';
}
    /**
    *  Website generator based on CMS records 99-51,52,53
    * 
    *  It is either generate home page from cmsTemplate file (inits main menu, header, footer) 
    *  or returns content for particular page
    * 
    *  Parameters
    *  recID - home page record (99-51) or web page (99-53)
    *          if is is not defined it takes first record of type 'Home page'
    *  field - if defined it is assumed web page and it returns value of specified 
    *          field (by default DT_EXTENDED_DESCRIPTION)
    * 
    * if home page has defined as template file it is loaded as body, otherwise default template
    * that includes header with main-logo, main-title, main-menu and 
    * main-content where content of particular page will be loaded
    * 
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
    
//Documentation    
/* Documentation

Default layout for Heurist CMS web site consists of 3 divs with absolute positions
.ent_wrapper
    .ent_header   #main_header
    .ent_content_full  #main-content-container

Main setting for these elements is height of header. To change it set
.ent_header{height:180px} .ent_content_full{top:190px}

HEADER:
    
#main_header.ent_header is hardcoded in cmsTemplate.php. It has the following elements
    #main-logo      - content defined via field "Site logo" (99-51.2-38). On click it reloads main page
    #main-logo-alt  - content defined via field "Supplementary logo image" (99-51.2-926)
    #main-title>h2  - field "Website title" (99-51.2-1)
    #main-host      - information about host and heurist. Content defined in Heurist settings
    #main-menu      - generated based on linked Menu/Page records (99-52)
    #main-pagetitle>.webpageheading - loaded Page title "Menu label" (99-52.2-1) hidden if 99-952
    
If your write your own cms template you have to define only 2 mandatory elements 
#main-menu and #main-content

        
You may overwrite default styles for these elements in field "Website CSS" (99-51.99-46).
Background image for #main_header is defiend in field "Banner image" (99-51.99-951).

CONTENT:
#main-content-container.ent_content_full cosists of sole element #main-content

    This element is emptied and reloaded for every page of website. Its content is 
    arbitrary and defined via CMS editor or directly via record editor in field
    "Website home page content"/"HTML content". (2-4)

    After load, Heurist invokes
    window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" )
    This method replaces all div elements with attribute "data-heurist-app-id" to 
    the appropriate Heurist widgets (search, map, result list etc)
    
    There are 2 fields per menu/page record "target css" and "target element". They are reserved 
    for future use. At the moment page content is always loaded into #main-content and applied 
    general Heurist color scheme unless the style is overdefined for particular 
    widget.
        
Content of website can be defined as custom smarty template in field 99-51.2-922.
In this case website designer has to define at least one element with id #main-content.
Element with this name will be used as layout container for widget initialization.
All other elements (#main-xxx) are optional.


INITIALIZATION workflow:
On server side:
1. It loads Home page record 
2. If there is DT_CMS_HEADER field, it sets header content from this field, 
otherwise page html structure and cotent of #main-header is generated in websiteRecord.php

On client side
1. HAPI initialization, DB defintions load -> onHapiInit -> onPageInit
2. onPageInit: init LayoutMgr, init main menu in #main-menu element
3. loadPageContent(pageid): Loads content of page into #main-content and 
   calls widget initialization width LayoutMgr.appInitFromContainer
4. If database configuration permits only:
   After widgets initialization it loads javascript (field 2-927) and incapsulate 
   this code into afterPageLoad function. The purpose of this script is additional 
   configuration of widgets on page (that can not be set via cms editor) - mainly
   addition of event listeners.
    
*/    
    
if(!defined('PDIR')) {
    define('PDIR','../../../');  //need for proper path to js and css           
}

require_once(dirname(__FILE__).'/../../framecontent/initPageMin.php'); //without client hapi
require_once(dirname(__FILE__).'/../../../hsapi/dbaccess/db_recsearch.php');
require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_users.php');


/*
Workflow:
loads main page for logo, icon, banner, style


*/
//print 'SORRY DEBUG in PROGRESS<br>';


$system->defineConstants();

$mysqli = $system->get_mysqli();

$isEmptyHomePage = false;
$open_page_on_init = @$_REQUEST['initid'];
if(!($open_page_on_init>0)) $open_page_on_init = @$_REQUEST['pageid'];
if(!($open_page_on_init>0)) $open_page_on_init = 0;

$rec_id = @$_REQUEST['recID'];
if(!($rec_id>0)) $rec_id = @$_REQUEST['recid'];
if(!($rec_id>0)) $rec_id = @$_REQUEST['id'];
if(!($rec_id>0))
{
    //if recID is not defined - find fist available "CMS home" record
    
    $rec_id = mysql__select_value($mysqli, 'select rec_ID from Records '
    .' WHERE rec_FlagTemporary=0 AND rec_NonOwnerVisibility="public" '
    .' AND rec_RecTypeID='.RT_CMS_HOME.' limit 1');
    
    if(!($rec_id>0)){
        //@todo find first record of 99-51 rectype
        $message = 'Sorry, there are no publicly accessible websites defined for this database. '
        .'Please ask the owner to publish their website(s).';
        include ERROR_REDIR;
        exit();
    }
}

// check if this record has been replaced (merged)
$rec_id = recordSearchReplacement($mysqli, $rec_id, 0);

//validate permissions
//$rec = mysql__select_row_assoc($mysqli, 
//        'select rec_Title, rec_NonOwnerVisibility, rec_OwnerUGrpID from Records where rec_ID='.$rec_id);
$rec = recordSearchByID($system, $rec_id, true);

$home_page_on_init = $rec_id;        

if($rec==null){
    //header('Location: '.ERROR_REDIR.'&msg='.rawurlencode('Record #'.$rec_id.' not found'));
    $message = 'Record #'.$home_page_on_init.' not found';
    include ERROR_REDIR;
    exit();
}

$hasAccess = (($rec['rec_NonOwnerVisibility'] == 'public') || 
               $system->is_admin() ||
    ( ($system->get_user_id()>0) && 
            ($rec['rec_NonOwnerVisibility'] !== 'hidden' ||    //visible for logged 
             $system->is_member($rec['rec_OwnerUGrpID']) )) );   //owner

/*             
print $rec_id.'<br>';
print $rec['rec_NonOwnerVisibility'].'<br>';    
print $system->get_user_id().'  >'.($system->is_admin()===true).'<br>';
print $rec['rec_OwnerUGrpID'].'<br>'; 
print $hasAccess.'<br>'; 
print '--------<br>'; 
print ($rec['rec_NonOwnerVisibility'] === 'public').'<br>'; 
print ($system->is_admin()===true).'<br>'; 
print ($system->get_user_id()>0).'<br>'; 
print ($rec['rec_NonOwnerVisibility'] !== 'hidden').'<br>'; 
print  $system->is_member($rec['rec_OwnerUGrpID']);
exit();
*/

if(!$hasAccess){
//@todo The Heurist website at this address is not yet publicly accessible.        
    $message = 'The Heurist website at this address is not yet publicly accessible.';
    include ERROR_REDIR;
    exit();
} 

$edit_Available = (@$_REQUEST['edit']==1);
$showWarnAboutPublic = !$edit_Available && ($rec['rec_NonOwnerVisibility'] != 'public');

$hasAccess = ($system->is_admin() || $system->is_member($rec['rec_OwnerUGrpID']));

$site_owner = user_getDbOwner($mysqli); //info about user #2

$website_title = '';
$show_pagetitle = false;
$isWebPage = false;

if(!(@$_REQUEST['field']>1)){
    
    $website_title = __getValue($rec, DT_NAME);
    $isWebPage = ($rec['rec_RecTypeID']==RT_CMS_MENU && 
                defined('DT_CMS_PAGETYPE') &&
                __getValue($rec, DT_CMS_PAGETYPE)==ConceptCode::getTermLocalID('2-6254'));
    
    if(!$isWebPage){ //for standalone webpage always without title
        $show_pagetitle = __getValue($rec, DT_CMS_PAGETITLE);
        $show_pagetitle = ($show_pagetitle!==ConceptCode::getTermLocalID('2-531') && 
                           $show_pagetitle!==ConceptCode::getTermLocalID('99-5447'));
    }
}


//-----------------------------------------------
//if REQUEST has parameter "field" - this is special request for content of particular field 
// by default it returns value of DT_EXTENDED_DESCRIPTION - content of web page
if(@$_REQUEST['field']){ 
        
//error_log('set respone header in redirect '.$_REQUEST['field'].'  '.$_SERVER['REQUEST_URI']);
//error_log(' orig:'.@$_SERVER['HTTP_ORIGIN'].'  ref:'.@$_SERVER['HTTP_REFERER']);    
    
    $system->setResponseHeader('text/html');
    
    if($_REQUEST['field']>1){
        
        $field_content = __getValue($rec, $_REQUEST['field']);
        
        if (trim($field_content)!='' && $_REQUEST['field']==DT_CMS_SCRIPT && !$system->is_js_acript_allowed()) {
/*
window.hWin.HEURIST4.msg.showMsgDlg(
'<p>This website contains javascript in the custom javascript field of the home page record.' 
+'Execution of custom javascript is only permitted in specifically authorised databases.</p> '
+'<p>Please ask the database owner either to edit the CMS HomePage and MenuPage records and clear the custom javascript fields, or to ask their ' 
+'system administrator to add this database to js_in_database_authorised.txt</p>',null,'Warning');
*/
        }else{
            print $field_content;
        }
    }else{
        //default value - content of page
        $content = __getValue($rec, DT_EXTENDED_DESCRIPTION);
        
        $empty_mark = (trim($content)=='')?' date-empty="1"':'';
        $hide_mark = ($show_pagetitle) ?'' :' style="display:none;"';
        
        print '<h2 class="webpageheading" '.$empty_mark.$hide_mark.'>' 
            .strip_tags($website_title,'<i><b><u><em><strong><sup><sub><small><br>')
            .'</h2>';    
        
        print $content;
    }
    exit();
}
//-----------------------

if(!($rec['rec_RecTypeID']==RT_CMS_HOME || $isWebPage)){
    $message = 'Record #'.$rec_id.' is not allowed record type. Expecting Website Home Page or Standalone Web Page';
    include ERROR_REDIR;
    exit();
}                

$image_icon = __getFile($rec, DT_THUMBNAIL, (array_key_exists('embed', $_REQUEST)?PDIR:HEURIST_BASE_URL).'favicon.ico');
$image_logo = __getFile($rec, DT_FILE_RESOURCE, null); 
$image_altlogo = null;
$image_altlogo_url = null;
if(defined('DT_CMS_ALTLOGO')) $image_altlogo = __getFile($rec, DT_CMS_ALTLOGO, null); 
if(defined('DT_CMS_ALTLOGO_URL')) $image_altlogo_url = __getValue($rec, DT_CMS_ALTLOGO_URL); 
$image_banner = null;
if(defined('DT_CMS_BANNER')) $image_banner = __getFile($rec, DT_CMS_BANNER, null); 

$image_logo = $image_logo?'<img style="max-height:80px;max-width:270px;" src="'.$image_logo.'">'
            :('<div style="text-align:center;display:block;width:250px;padding: 20px 10px;background:white;">'
            .'<h2 style="color:red;margin:4px">Logo</h2><div style="color:black">Set this as Website header/layout</div></div>');

$meta_keywords = htmlspecialchars(__getValue($rec, DT_CMS_KEYWORDS));
$meta_description = htmlspecialchars(__getValue($rec, DT_SHORT_SUMMARY));
//2-532 - YES   2-531 - NO

if(!$isWebPage && __getValue($rec,DT_EXTENDED_DESCRIPTION)==''){
    //home page is empty
    $isEmptyHomePage = true;
}

//
// arbitrary external links and scripts
//
$external_files = @$rec['details'][DT_CMS_EXTFILES];
if($external_files!=null){
    if(!is_array($external_files)){
        $external_files = array($external_files);
    }
}
//custom styles - mainly to override positions/visibility for #main-xxx elements
$site_css = __getValue($rec, DT_CMS_CSS);

//color scheme for website
$site_colors = __getValue($rec, DT_SYMBOLOGY);

//
// returns link to uploaded file
//
function __getFile(&$rec, $id, $def){
    
    $file = @$rec['details'][$id];
    
    if(is_array($file)){
        $file = array_shift($file);
        $file = (array_key_exists('embed', $_REQUEST)?PDIR:HEURIST_BASE_URL).'?db='.HEURIST_DBNAME.'&file='.$file['fileid'];
    }else{
        $file = $def;
    }
    
    return $file;
}

//
// returns first value
//
function __getValue(&$menu_rec, $id){
    
    $val = @$menu_rec['details'][$id];
    
    if(is_array($val)){
        return array_shift($val);
    }else if($val==null){
        return '';
    }else{
        return $val;    
    }
}


$custom_template = null;

$page_header = null;
$page_header_menu = null;

$page_footer = null;
$is_page_footer_fixed = true;
$page_footer_height = 0;

if(!$isWebPage){
    
    $page_header_menu =     
            '<div class="widget-design-header" style="padding: 10px;"><img style="vertical-align: middle;" height="22" />'
            .'<strong>navigation</strong><a class="edit" style="padding: 0 10px;" title="Click to edit" href="#">edit</a> '
            .' <a class="remove" href="#">remove</a> height:50px width:100%</div>'
            .'<span class="widget-options" style="font-style: italic; display: none;">{"menu_recIDs":"'.$rec_id
            .'","use_next_level":true,"orientation":"horizontal","init_at_once":true}</span>';
    
    $custom_template = defined('DT_POPUP_TEMPLATE')?__getValue($rec, DT_POPUP_TEMPLATE, null):null; 

    $page_header = defined('DT_CMS_HEADER')?__getValue($rec, DT_CMS_HEADER, null):null; 

    $page_footer_type = defined('DT_CMS_FOOTER_FIXED')?__getValue($rec, DT_CMS_FOOTER_FIXED, null):null; 
    
    if($page_footer_type !== ConceptCode::getTermLocalID('3-5029'))
    //== ConceptCode::getTermLocalID('2-532') || $page_footer_type == ConceptCode::getTermLocalID('2-531'))
    {
    
        $page_footer = defined('DT_CMS_FOOTER')?__getValue($rec, DT_CMS_FOOTER, null):null; 
        $is_page_footer_fixed = ($page_footer_type != ConceptCode::getTermLocalID('2-531'));
        if($page_footer==null){
            $page_footer = '';
            $page_footer_height = 48;
        }else{
            $page_footer_height = 80;
        }
        
        $page_footer = '<div id="page-footer" class="ent_footer"' //.($is_page_footer_fixed?'ent_footer':'').'"'  
                .' style="height:'.$page_footer_height.'px;border-top:2px solid rgb(112,146,190);background:lightgray;">'
                .'<div class="page-footer-content" style="float:left">'
                .$page_footer.'</div>'
            
            
            .'<div id="main-host" style="position:absolute;bottom:4;right:10;" class=" header-element">'
                 .(@$site_owner['ugr_eMail']?'<div style="float:right;line-height:38px;padding:0 5px;">'
                    .'&bull;&nbsp;&nbsp;<a href="mailto:'.@$site_owner['ugr_eMail'].'">site owner</a>'
                 .'</div>':'')    
                 .'<div id="host_info" style="float:right;line-height:38px;height:40px;margin-right: 0px;"></div>'
                 .'<div style="float:right;padding:0 5px;height:40px;line-height: 38px;">'
                        .'<a href="http://HeuristNetwork.org" target="_blank" style="text-decoration:none;" '  
                            .'title="This website is generated by Heurist, an academic knowledge management system developed at the University of Sydney Faculty of Arts and Social Sciences under the direction of Dr Ian Johnson, chief programmer Artem Osmakov.">'
                            .'powered by <img src="'.HEURIST_BASE_URL.'favicon.ico" style="vertical-align:sub"> Heurist'
                        .'</a></div>'
            .'</div>'
            .'</div>';
            //hclient/assets/h4_icon_16x16.png
    }
}

$home_page_record_id = $rec_id;

if($custom_template){
    
    if(substr( $custom_template, -4 ) !== '.php'){
            $custom_template = $custom_template.'.php';
    }
    
    if(strpos($custom_template,'/')!==false){
        //otherwise this is relative to code directory
        $custom_template = HEURIST_DIR.$custom_template;
    }else{
        //if there are not slashes - it is assumed it is in heurist root folder
        $custom_template = PDIR.'../'.$custom_template;    
    }
}

if($custom_template){
    if(file_exists($custom_template)){
        //use custom template for website
        include ($custom_template);
        exit;
    }else{
        $cutomTemplateNotFound = $custom_template;
    }
}        
if(file_exists(PDIR.'../cmsTemplate.php')){
    //use server custom template
    include '../cmsTemplate.php';
    
}else{
    //use default template
    include 'cmsTemplate.php';
} 
?>
