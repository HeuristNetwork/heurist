<?php

    /**
    *  Website generator based on CMS records 99-51,52,53
    * 
    *  Parameters
    *  recID - home page record (99-51) or web page (99-53)
    *          if is is not defined it takes first record of type 'Home page'
    *  field - if defined it is assumed web page and it returns value of specified field (by default DT_EXTENDED_DESCRIPTION)
    * 
    * if home page has defined as template file it is loaded as body, otherwise default template
    * that includes header with main-logo, main-title, main-menu and 
    * main-content where content of particular page will be loaded
    *  
    * 
    * 
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
    
//Documentation    
/* Documentation

Default layout for Heurist CMS web site consists of 3 divs with absolute positions
.ent_wrapper
    .ent_header   #main_header
    .ent_content_full  #main-content-container

Main setting for these elements is height of header. To change it set
.ent_header{height:180px} .ent_content_full{top:190px}

HEADER:
    
#main_header.ent_header is hardcoded in websiteRecord.php. It has the following elements
    #main-logo      - content defined via field "Site logo" (99-51.2-38). On click it reloads main page
    #main-logo-alt  - content defined via field "Supplementary logo image" (99-51.2-926)
    #main-title>h2  - field "Website title" (99-51.2-1)
    #main-host      - information about host and heurist. Content defined in Heurist settings
    #main-menu      - generated based on linked Menu/Page records (99-52)
    #main-pagetitle>.webpageheading - loaded Page title "Menu label" (99-52.2-1)
        
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
2. If there is DT_POPUP_TEMPLATE field, it executes smarty template (both header and content), 
   If there is DT_CMS_HEADER field, it sets header content from this field, 
otherwise page html structure and cotent of #main-header is generated in websiteRecord.php

On client side
1. HAPI initialization, DB defintions load -> onHapiInit -> onPageInit
2. onPageInit: init LayoutMgr, init main menu in #main-menu element
3. loadHomePageContent(pageid): Loads content of page into #main-content and 
   calls widget initialization width LayoutMgr.appInitFromContainer
4. If database configration permits only:
   After widgets initialization it loads javascript (field 2-927) and incapsulate 
   this code into afterPageLoad function. The purpose of this script is additional 
   configuration of widgets on page (that can not be set via cms editor) - mainly
   addition of event listeners.
    
*/    
    
if(!defined('PDIR')) define('PDIR','../../../');  //need for proper path to js and css    


require_once(dirname(__FILE__).'/../../framecontent/initPageMin.php'); //without client hapi
require_once(dirname(__FILE__).'/../../../hsapi/dbaccess/db_recsearch.php');
require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_users.php');


/*
Workflow:
loads main page for logo, icon, banner, style


*/

$system->defineConstants();

$mysqli = $system->get_mysqli();

$open_page_on_init = @$_REQUEST['initid'];
$rec_id = @$_REQUEST['recID'];
if(!($rec_id>0)) $rec_id = @$_REQUEST['recid'];
if(!($rec_id>0)) $rec_id = @$_REQUEST['id'];
if(!($rec_id>0))
{
    $rec_id = mysql__select_value($mysqli, 'select rec_ID from Records '
    .' WHERE rec_FlagTemporary=0 AND rec_NonOwnerVisibility="public" '
    .' AND rec_RecTypeID='.RT_CMS_HOME.' limit 1');
    
    if(!($rec_id>0)){
        //@todo find first record of 99-51 rectype
        $message = 'Parameter recID not defined';
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
        

if($rec==null){
    //header('Location: '.ERROR_REDIR.'&msg='.rawurlencode('Record #'.$rec_id.' not found'));
    $message = 'Record #'.$rec_id.' not found';
    include ERROR_REDIR;
    exit();
}

$hasAccess = ($rec['rec_NonOwnerVisibility'] == 'public' || $system->is_admin() ||
    ($system->get_user_id()>0 && $rec['rec_NonOwnerVisibility'] !== 'hidden') ||    //visible for logged 
    $system->is_member($rec['rec_OwnerUGrpID']) );   //owner

if(!$hasAccess){
    $message = 'You are not a member of the workgroup that owns the Heurist record #'
        .$rec_id.', and cannot therefore view this information.';
    include ERROR_REDIR;
    exit();
} 

$hasAccess = ($system->is_admin() || $system->is_member($rec['rec_OwnerUGrpID']));

$site_owner = user_getDbOwner($mysqli); //info about user #2

//output content of particular page - just title and content
if(@$_REQUEST['field']){ 
    
    if($_REQUEST['field']>1){
        
        $field_content = __getValue($rec, $_REQUEST['field']);
        
        if (trim($field_content)!='' && $_REQUEST['field']==DT_CMS_SCRIPT && !$system->is_js_acript_allowed()) {
            exit('alert("Execution of custom script not allowed for this database")');   
        }
        
        exit( $field_content );
    }else{
        exit('<h2>'.__getValue($rec, DT_NAME).'</h2>'
                            .__getValue($rec, DT_EXTENDED_DESCRIPTION));
    }
}
//-----------------------

if(!($rec['rec_RecTypeID']==RT_CMS_HOME || $rec['rec_RecTypeID']==RT_CMS_PAGE || $rec['rec_RecTypeID']==RT_CMS_MENU)){
    $message = 'Record #'.$rec_id.' is not allowed record type. Expecting Website Home Page';
    include ERROR_REDIR;
    exit();
}

$website_title = __getValue($rec, DT_NAME);
$image_icon = __getFile($rec, DT_THUMBNAIL, HEURIST_BASE_URL.'favicon.ico');
$image_logo = __getFile($rec, DT_FILE_RESOURCE, null); 
$image_altlogo = null;
if(defined('DT_CMS_ALTLOGO')) $image_altlogo = __getFile($rec, DT_CMS_ALTLOGO, null); 
$image_banner = null;
if(defined('DT_CMS_BANNER')) $image_banner = __getFile($rec, DT_CMS_BANNER, null); 

$meta_keywords = htmlspecialchars(__getValue($rec, DT_CMS_KEYWORDS));
$meta_description = htmlspecialchars(__getValue($rec, DT_SHORT_SUMMARY));

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
        $file = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$file['fileid'];
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
?>
<html>
<head>
	<title><?php print $website_title;?></title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="keywords" content="Heurist, Digital Humanities, Humanities Data, Research Data, Database Management, Academic data, Open Source, Free software, FOSS, University of Sydney,<?=$meta_keywords?>">
    <meta name="description" content="<?=$meta_description?>">
	<link rel="icon" href="<?=$image_icon?>"> <!--  type="image/x-icon" -->
	<link rel="shortcut icon" href="<?=$image_icon?>">
    
<?php

if($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'){
    ?>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
    <?php
}else{
    ?>
    <script src="https://code.jquery.com/jquery-1.12.2.min.js" integrity="sha256-lZFHibXzMHo3GGeehn1hudTAP3Sc0uKXBXAzHX1sjtk=" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
    <?php
}
?>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.layout/jquery.layout-latest.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
    
    <!-- CSS -->
    <?php include PDIR.'hclient/framecontent/initPageCss.php'; ?>
   
    <!-- link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/tinymce/skins/lightgray/content.min.css"/ -->

<script>
    var _time_debug = new Date().getTime() / 1000;
//    console.log('webpage start');
</script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
  
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/search_minimal.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/localization.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>layout_default.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/dropdownmenus/navigation.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/svs_list.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search_faceted.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/recordListExt.js"></script>

    
    <!-- need only for symbology edit $system->is_member(2) -->    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editSymbology.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/evol.colorpicker.js" charset="utf-8"></script>
    <link href="<?php echo PDIR;?>external/js/evol.colorpicker.css" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/media_viewer.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>admin/structure/import/importStructure.js"></script>
    
<?php
$edit_Available = (@$_REQUEST['edit']==1);
if($edit_Available){
?>
    <script src="<?php echo PDIR;?>external/tinymce/tinymce.min.js"></script>
    <!--
    <script src="<?php echo PDIR;?>external/tinymce5/tinymce.min.js"></script>
    <script src="<?php echo PDIR;?>external/tinymce5/jquery.tinymce.min.js"></script>
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js"></script>
    <script src="<?php echo PDIR;?>external/tinymce/jquery.tinymce.min.js"></script>
    -->
    <script src="websiteRecord.js"></script>
    <?php
}else{
?>    
  <script>
  
//
// init page for publication version  
// for cms version see websiteRecord.js
//
function onPageInit(success)
{

//console.log('webpage onPageInit  '+(new Date().getTime() / 1000 - _time_debug));
_time_debug = new Date().getTime() / 1000;
        
    if(!success) return;
    
    $('#main-menu').hide();
    
    //cfg_widgets is from layout_defaults.js 
    window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, null);
    
    
    //reload home page content by click on logo
    $( "#main-logo").click(function(event){
              loadHomePageContent(<?php print $rec_id?>);
    });
    //$( "#main-logo").click(); 
    
    
    setTimeout(function(){
        //init main menu
        //add menu definitions to main-menu
        var bg_color = $('#main-header').css('background');
//console.log('>>>')+bg_color;           

        var topmenu = $('#main-menu');
        topmenu.attr('data-heurist-app-id','heurist_Navigation');
                
        window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-header",
            {heurist_Navigation:{menu_recIDs:"<?php print $rec_id;?>", use_next_level:true, 
            orientation:'horizontal',
            toplevel_css:{background:bg_color}, //'rgba(112,146,190,0.7)'
            aftermenuselect: afterPageLoad
            }} );
            
        $('#main-menu').show();
        //load home page content $( "#main-logo").click(); 
        loadHomePageContent(<?php print $rec_id?>);
        
        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);
        
        var itop = $('#main-header').height(); //[0].scrollHeight;
        $('#btn_editor').css({top:itop-70, right:40});
        
    },300);
    
    
}

//
//
//
function loadHomePageContent(pageid){
        if(pageid>0){
              //window.hWin.HEURIST4.msg.bringCoverallToFront($('body').find('#main-content'));
              $('#main-content').empty().load(window.hWin.HAPI4.baseURL+'?db='
                        +window.hWin.HAPI4.database+'&field=1&recid='+pageid,
                  function(){
                      
//console.log('webpage load page content  '+(new Date().getTime() / 1000 - _time_debug));
                      
                      var pagetitle = $($('#main-content').children()[0]);
                      pagetitle.remove();
                      $('#main-pagetitle').empty();
                      window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
                      window.hWin.HEURIST4.msg.sendCoverallToBack();
                      
                      afterPageLoad( document, pageid );
              });
        }
}
</script>
<?php
}
?>
<script>
var page_scripts = {}; //pageid:functionname   cache to avoid call server every time on page load 
//
// Executes custom javascript defined in field DT_CMS_SCRIPT
//
function afterPageLoad(document, pageid){
    
    var DT_CMS_SCRIPT = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_SCRIPT'];

    if(DT_CMS_SCRIPT>0 && page_scripts[pageid] !== false){
        
        
        if(!page_scripts[pageid]){
            
            var surl = window.hWin.HAPI4.baseURL+'?db='
                +window.hWin.HAPI4.database+'&field='+DT_CMS_SCRIPT+'&recid='+pageid;
            
            $.get( surl, function( data ) {
                if(data==''){
                    page_scripts[pageid] = false;    
                }else{
                    var func_name = 'afterPageLoad'+pageid;
                    var script = document.createElement('script');
                    script.type = 'text/javascript';
                    script.innerHTML = 'function '+func_name +'(document, pageid){' + data + '}';
                    //s.src = "http://somedomain.com/somescript";
                    $("head").append(script);
                    page_scripts[pageid] = func_name;    
                    window[func_name]( document, pageid );
                }
            });            
                
            page_scripts[pageid] = false;
        }
        
        if(page_scripts[pageid] !== false){
            window[page_scripts[pageid]]( document, pageid );    
        }
        
    }
}

//
//
//
function onHapiInit(success){   
    
//    console.log('webpage hapi inited  '+(new Date().getTime() / 1000 - _time_debug));
    _time_debug = new Date().getTime() / 1000;
    
    if(!success){    
            window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
            return;
    }
    
    window.hWin.HAPI4.SystemMgr.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
        
//console.log('DBG execution time "get_defs" '+response.exec_time);                            
//console.log('DBG size "get_defs" '+response.zip_size);                            
        
//console.log('webpage db struct  '+(new Date().getTime() / 1000 - _time_debug));
_time_debug = new Date().getTime() / 1000;
        
        if(response.status == window.hWin.ResponseStatus.OK){
            window.hWin.HEURIST4.rectypes = response.data.rectypes;
            window.hWin.HEURIST4.terms = response.data.terms;
            window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
        }else{
            var sMsg = 'Cannot obtain database definitions (get_defs function). This is probably due to a network timeout. However, if the problem persists please report to Heurist developers as it could indicate corruption of the database.';                            
            
            if(response.message){
                 sMsg =  sMsg + '<br><br>' + response.message;
            }
            window.hWin.HEURIST4.msg.showMsgErr(sMsg);
            
            success = false;
        }
        
        //substitute values in header
        initHeaderElements();
        
        onPageInit(success);
        
        if(window.hWin.HAPI4.sysinfo.host_logo && $('#host_info').length>0){
            
            $('<div style="height:40px;padding-left:4px;float:right">'  //background: white;
                +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                +'" target="_blank" style="text-decoration:none;color:black;">'
                        +'<label>hosted by: </label>'
                        +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                        +'" height="40" align="center"></a></div>')
            .appendTo( $('#host_info') );
        }
    });
}


//
//substitute values in header
//
function initHeaderElements(){   
/*
$image_logo  -> #main-logo
$image_altlogo -> #main-logo-alt
$website_title -> #main-title>h2 
*/
  //main logo image
  if($('#main-logo').length>0){
            $('#main-logo').empty();
            $('<a href="#" style="text-decoration:none;"><?php print $image_logo
            ?'<img style="max-height:80px" src="'.$image_logo.'">'
            :'<div style="text-align:center;display:block;width:250px;padding: 20px 10px;background:white;">'
            .'<h2 style="color:red;margin:4px">Logo</h2><div style="color:black">Set this as Website header/layout</div></div>';?></a>')
            .appendTo($('#main-logo'));
  }
  <?php if($image_altlogo){ ?>
  if($('#main-logo-alt').length>0){
            $('#main-logo-alt').css({'background-size':'contain','background-image':'url(\'<?php print $image_altlogo;?>\')'});
  }
  <?php } ?>
            
  <?php if($website_title){ ?>
  if($('#main-title').length>0){
      $('#main-title').empty();
      $('<h2 style="font-size:1.7em;"><?php print $website_title;?></h2>').appendTo($('#main-title'));
  }
  <?php } ?>

  /*main menu element not found - add default one
  if($('#main-menu').length==0 && $('#main-header').length>0){
      
        $('<div id="main-menu" class="mceNonEditable" style="float:left;width:100%;min-height:40px;padding-top:16px;color:black;font-size:1.1em;" data-heurist-app-id="heurist_Navigation" data-generated="1">'
            +'<div class="widget-design-header" style="padding: 10px;"><img style="vertical-align: middle;" src="../../assets/h4_icon_35x35.png" height="22" /> <strong>navigation</strong><a class="edit" style="padding: 0 10px;" title="Click to edit" href="#">edit</a>  <a class="remove" href="#">remove</a> height:50px width:100%</div>'
            +'<span class="widget-options" style="font-style: italic; display: none;">{"menu_recIDs":"<?php print $rec_id;?>","use_next_level":true,"orientation":"horizontal","init_at_once":true}</span>'
        +'</div>').appendTo($('#main-header'));
                    
  }*/

} //initHeaderElements

var gtag = null;//google log
//init hapi    
$(document).ready(function() {
    
        var ele = $('body').find('#main-content');
        window.hWin.HEURIST4.msg.bringCoverallToFront(ele);
        ele.show();
    
        $('body').find('#main-menu').hide(); //will be visible after menu init
        $('body').find('#main-pagetitle').show();
    
//console.log('webpage doc ready '+(window.hWin.HAPI4)+'    '+(new Date().getTime() / 1000 - _time_debug));
        _time_debug = new Date().getTime() / 1000;
    
        // Standalone check
        if(!window.hWin.HAPI4){
            window.hWin.HAPI4 = new hAPI('<?php echo $_REQUEST['db']?>', onHapiInit);
            
/*            
            // In case of standalone page
            //load minimum set of required scripts
            $.getMultiScripts(['localization.js'], '<?php echo PDIR;?>hclient/core/')
            .done(function() {
                // all done
                window.hWin.HAPI4 = new hAPI('<?php echo $_REQUEST['db']?>', onHapiInit);

            }).fail(function(error) {
                // one or more scripts failed to load
                onHapiInit(false);

            }).always(function() {
                // always called, both on success and error
            });
*/
        }else{
            // Not standalone, use HAPI from parent window
            initHeaderElements();
            onPageInit( true );
        }
});    
</script>    
    
<style>
body{
    /* A11 font-size:0.7em;*/
}
/* not used */
#main-foooter{
    font-size:0.8em;
    height:20px;
    padding: 5px 20px;
    border-top:1px solid rgb(112,146,190);
}
/* page (menu) title it is added to main-pagetitle */
.webpageheading {
    font-size:1.5em;
    /*color:black;*/
    position:absolute;
    left:10;
    margin: 0;
    width:auto;
}
#main-content{
    display:none;
    position:absolute;
    left:0;right:0;top:0;bottom:0;
    padding:10px;
}
#main-header{
    /*background:rgb(112,146,190);*/
    height:180px;   
    padding: 0.5em;
    padding-bottom:0;
}
#main-menu .horizontalmenu > li.ui-menu-item > a{
   font-weight:bold !important; 
}
#main-pagetitle{
    position: absolute;
    padding: 15 0 5 10;
    bottom: 0;
    left: 0;
    right: 0;
    /*background: white;*/
    min-height: 19px;
}
/*
.horizontalmenu > li.ui-menu-item > a{
    background: green;
}
*/
#main-menu .ui-menu > li > a.selected{
    background: black !important;
    color: white !important;
}
.ui-widget{
    font-size:1em;
}
.cms-button{
    font-size:0.8em;
    font-weight:bold;
    color:blue;
}
<?php
if(!$edit_Available){
?>
div.coverall-div {
    background-position: top;     
    background-color: white;
    opacity: 1;
}
<?php        
}
//style from field DT_CMS_CSS of home record 
if($site_css!=null){
    print $site_css;
}
?>          
</style>
</head>
<body>
<?php
/*
page structure can be defined in field DT_POPUP_TEMPLATE (of RT_CMS_HOME )
otherwise this is default content consists of 
    #main-header - header width logo, banner, hostinfo and main menu
        #main-logo, #main-logo-alt, #main-host, #main-menu, #main-pagetitle
        
    #main-content-container > #main-content - target the content of particular page will be loaded  
*/

//it can be html, html file or smarty report
$page_template = defined('DT_POPUP_TEMPLATE')?__getValue($rec, DT_POPUP_TEMPLATE, null):null; 

//implemented for template only
if ($page_template!=null && substr($page_template,-4,4)=='.tpl') {
    
    $_REQUEST['publish'] = '1';
    $_REQUEST['debug'] = '0';
    $_REQUEST['q'] = 'ids:'.$rec_id;
    $_REQUEST['template'] = $page_template;

    include dirname(__FILE__).'/../../viewers/smarty/showReps.php';
    
}else{
?>

    <div class="ent_wrapper">
    <div id="main-header" class="ent_header ui-heurist-header2" <?php print $image_banner?'style="background-image:url(\''.$image_banner.'\');background-repeat: repeat-x;background-size:auto 170px;"':'' ?>>
    
<?php
        
    $page_header = defined('DT_CMS_HEADER')?__getValue($rec, DT_CMS_HEADER, null):null; 
    if($page_header!=null && $page_header!=''){
        print $page_header;        
    } else {
?>    
	    <div id="main-logo" class="mceNonEditable" style="float:left;min-height:80px;"></div>
        
        <div id="main-logo-alt" class="mceNonEditable" style="float:right;min-height: 73px;min-width: 130px;margin: 7px 4px 0 0;"></div>
        
        <div id="main-title" class="mceNonEditable" style="float:left;padding-left:20px;vertical-align:middle;"></div>
        
        <div id="main-host" style="float:right;margin-top: 15px">
            <div id="host_info" style="float:right;line-height:38px;height:40px;margin-right: 15px;">
            </div>
            <div style="float:right;padding:0 10px;height:40px;line-height: 38px;">  <!-- background: white; color:black;-->
            <a href="http://HeuristNetwork.org" target="_blank" style="text-decoration:none;"   
            title="This website is generated by Heurist, an academic knowledge management system developed at the University of Sydney Faculty of Arts and Social Sciences under the direction of Dr Ian Johnson, chief programmer Artem Osmakov.">
                Powered by <img src="<?php echo HEURIST_BASE_URL ?>hclient/assets/h4_icon_16x16.png" style="vertical-align:sub"> Heurist
            </a> 
            </div>
            <div style="text-align: right;margin-right: 15px;font-size:0.8em">
                <a href="mailto:<?php echo @$site_owner['ugr_eMail']; ?>">site owner</a>
            </div>    
        </div>
        
        <div id="main-menu" class="mceNonEditable" style="float:left;width:100%;min-height:40px;padding-top:16px;color:black;font-size:1.1em;" data-heurist-app-id="heurist_Navigation" data-generated="1">
            <div class="widget-design-header" style="padding: 10px;"><img style="vertical-align: middle;" src="../../assets/h4_icon_35x35.png" height="22" /> <strong>navigation</strong><a class="edit" style="padding: 0 10px;" title="Click to edit" href="#">edit</a>  <a class="remove" href="#">remove</a> height:50px width:100%</div>
            <span class="widget-options" style="font-style: italic; display: none;">{"menu_recIDs":"<?php print $rec_id;?>","use_next_level":true,"orientation":"horizontal","init_at_once":true}</span>
        </div>

<?php        
    }//header

if(!$edit_Available && $system->is_member(2)){
        print '<a href="'.HEURIST_BASE_URL.'?db='.$system->dbname().'&cms='.$rec_id.'" id="btn_editor" target="_blank" '
        .'style="position:absolute;right:40px; top:100px;" class="cms-button">Web site editor</a>';
}
    ?>  
        <div id="main-pagetitle" class="ui-heurist-bg-light" style="display:none">loading...</div>       
    </div>
    <div class="ent_content_full ui-heurist-bg-light" style="top:190px;padding: 5px;" id="main-content-container">
        <div id="main-content" data-homepageid="<?php print $rec_id;?>" 
            <?php print ($open_page_on_init>0)?'data-initid="'.$open_page_on_init.'"':''; ?> 
            data-viewonly="<?php print ($hasAccess)?0:1;?>">
        </div>
    </div>
    </div>
<?php
}
?>    
</body>
</html>

