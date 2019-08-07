<?php

    /**
    *  Website generator based on CMS records 99-51,52,53
    * 
    *  Parameters
    *  recID - home page record (99-51) or web page (99-53)
    *          if is is not defined it takes first record of type 'Home page'
    *  field - if defined it is assumed web page and it returns only DT_EXTENDED_DESCRIPTION
    * 
    * if home page has defined template file it is loaded as body, otherwise default template
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
if(!defined('PDIR')) define('PDIR','../../');  //need for proper path to js and css    


require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php'); //without client hapi
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_recsearch.php');
require_once (dirname(__FILE__).'/../../hsapi/dbaccess/db_users.php');


$system->defineConstants();

$mysqli = $system->get_mysqli();

$rec_id = @$_REQUEST['recID'];
if(!($rec_id>0)) $rec_id = @$_REQUEST['recid'];
if(!($rec_id>0)) $rec_id = @$_REQUEST['id'];
if(!($rec_id>0))
{
    $rec_id = mysql__select_value($mysqli, 'select rec_ID from Records where rec_RecTypeID='.RT_CMS_HOME.' limit 1');
    
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
    
    
    print '<h2>'.__getValue($rec, DT_NAME).'</h2>'
                        .__getValue($rec, DT_EXTENDED_DESCRIPTION);
    exit();
}
//-----------------------

if($rec['rec_RecTypeID']!=RT_CMS_HOME && $rec['rec_RecTypeID']!=RT_CMS_PAGE){
    $message = 'Record #'.$rec_id.' is not allowed record type. Expecting Website Home Page';
    include ERROR_REDIR;
    exit();
}

$image_icon = __getFile($rec, DT_THUMBNAIL, HEURIST_BASE_URL.'favicon.ico');
$image_logo = __getFile($rec, DT_FILE_RESOURCE, null); 
$image_banner = __getFile($rec, DT_CMS_BANNER, null); 

$meta_keywords = htmlspecialchars(__getValue($rec, DT_CMS_KEYWORDS));
$meta_description = htmlspecialchars(__getValue($rec, DT_SHORT_SUMMARY));


$layout_theme = 'heurist';//'le-frog'; //__getValue($rec, DT_CMS_THEME);
if(!$layout_theme) $layout_theme = 'heurist';
$cssLink = PDIR.'external/jquery-ui-themes-1.12.1/themes/'.$layout_theme.'/jquery-ui.css';


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
// returns fist value
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
	<title><?php print __getValue($rec, DT_NAME);?></title>
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
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>" /> <!-- theme css -->
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />  <!-- base css -->
    
    <!-- link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/tinymce/skins/lightgray/content.min.css"/ -->

<script>
    var _time_debug = new Date().getTime() / 1000;
//    console.log('webpage start');
</script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
  
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/search_minimal.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/localization.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/dropdownmenus/navigation.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>layout_default.js"></script>
    
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
//init page for publication version  
function onPageInit(success)
{

//console.log('webpage onPageInit  '+(new Date().getTime() / 1000 - _time_debug));
_time_debug = new Date().getTime() / 1000;
        
    if(!success) return;
    
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
        var topmenu = $('#main-menu');
        topmenu.attr('data-heurist-app-id','heurist_Navigation');
                
        window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-header",
            {heurist_Navigation:{menu_recIDs:"<?php print $rec_id;?>", use_next_level:true, 
            orientation:'horizontal',toplevel_css:{background:'rgba(112,146,190,0.7)'} }} );
            
        $('#main-menu').show();
        //load home page content
        $( "#main-logo").click(); 
        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);
        
        var itop = $('#main-header').height(); //[0].scrollHeight;
        $('#btn_editor').css({top:itop-70, right:40});
        
    },300);
    
    
}
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
              });
        }
}
</script>
<?php
}
?>
<script>
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
        
        onPageInit(success);
        
        if(window.hWin.HAPI4.sysinfo.host_logo){
            
            $('<div style="height:40px;background: white;padding-left:4px;float:right">'
                +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                +'" target="_blank" style="text-decoration: none;color:black;">'
                        +'<label>hosted by: </label>'
                        +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                        +'" height="40" align="center"></a></div>')
            .appendTo( $('#host_info') );
        }
    });
}

var gtag = null;//google log
//init hapi    
$(document).ready(function() {
    
        var ele = $('body').find('#main-content');
        window.hWin.HEURIST4.msg.bringCoverallToFront(ele);
        ele.show();
    
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
            onPageInit( true );
        }
});    
</script>    
    
<style>
body{
    /* A11 font-size:0.7em;*/
}
#main-foooter{
    font-size:0.8em;
    height:20px;
    padding: 5px 20px;
    border-top:1px solid rgb(112,146,190);
}
.webpageheading {
    font-size:1.5em;
    color:black;
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
    background:rgb(112,146,190);
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
    background: white;
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
    ?>        
</style>

</head>
<body>
<?php
//it can be html, html file or smarty report
$page_template = __getValue($rec, DT_POPUP_TEMPLATE, null); 

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
    <div id="main-header" class="ent_header" <?php print $image_banner?'style="background-image:url(\''.$image_banner.'\');background-repeat: repeat-x;background-size:auto 170px;"':'' ?>>
    
	    <div style="float:left;min-height:80px;" id="main-logo">
            <a href="#" style="text-decoration:none;">
        <?php print $image_logo?'<img style="max-height:80px" src="'.$image_logo.'">'
            :'<div style="text-align:center;display:block;width:250px;padding: 30px 10px;font-size:16px;background:white;color:red" >Logo / banner image</div>';?>
            </a>
        </div>
        <div id="main-title" style="float:left;padding-left:20px;vertical-align:middle;">
            <h2 style="font-size:1.7em;color:black"><?php print __getValue($rec, DT_NAME);?></h2>
        </div>
        
        <div style="float:right;margin-top: 15px">
            <div id="host_info" style="float:right;line-height:38px;height:40px;margin-right: 15px;">
            </div>
            <div style="float:right;padding:0 10px;background: white;height:40px;line-height: 38px;"> 
            <a href="http://HeuristNetwork.org" target="_blank" style="text-decoration:none;color:black;"
            title="This website is generated by Heurist, an academic knowledge management system developed at the University of Sydney Faculty of Arts and Social Sciences under the direction of Dr Ian Johnson, chief programmer Artem Osmakov.">
                Powered by <img src="<?php echo HEURIST_BASE_URL ?>hclient/assets/h4_icon_16x16.png" style="vertical-align:sub"> Heurist
            </a> 
            </div>
            <div style="text-align: right;margin-right: 15px;font-size:0.8em">
                <a href="mailto:<?php echo @$site_owner['ugr_eMail']; ?>">site owner</a>
            </div>    
        </div>
        
	    <div id="main-menu" style="float:left;display:none;width:100%;min-height:40px;padding-top:16px;color:black;font-size:1.1em;">
        </div>

<?php        
if(!$edit_Available && $system->is_member(2)){
        print '<a href="'.HEURIST_BASE_URL.'?db='.$system->dbname().'&cms='.$rec_id.'" id="btn_editor" target="_blank" '
        .'style="position:absolute;right:40px; top:100px;" class="cms-button">Web site editor</a>';
}
    ?>  
        <div id="main-pagetitle">loading...</div>       
    </div>
    <div class="ent_content_full" style="top:180px;padding: 5px;">
        <div id="main-content" data-homepageid="<?php print $rec_id;?>" data-viewonly="<?php print ($hasAccess)?0:1;?>">
        </div>
    </div>
    </div>
<?php
}
?>    
</body>
</html>

