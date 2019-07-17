<?php

    /**
    *  Website generator based on CMS records 99-51,52,53
    * 
    *  Paramters
    *  recID - home page record (99-51)
    * 
    *  if is is not defined it takes first record of this type 
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
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php'); //without client hapi
require_once(dirname(__FILE__)."/../../hsapi/dbaccess/db_recsearch.php");


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

$hasAccess = ($rec['rec_NonOwnerVisibility'] == 'public' ||
    ($system->get_user_id()>0 && $rec['rec_NonOwnerVisibility'] !== 'hidden') ||    //visible for logged 
    $system->is_member($rec['rec_OwnerUGrpID']) );   //owner

if(!$hasAccess){
    $message = 'You are not a member of the workgroup that owns the Heurist record #'
        .$rec_id.', and cannot therefore view this information.';
    include ERROR_REDIR;
    exit();
} 

if(@$_REQUEST['field']){
    print __getValue($rec, DT_EXTENDED_DESCRIPTION);
    exit();
}

if($rec['rec_RecTypeID']!=RT_CMS_HOME && $rec['rec_RecTypeID']!=RT_CMS_PAGE){
    $message = 'Record #'.$rec_id.' is not allowed record type. Expecting Website Home Page';
    include ERROR_REDIR;
    exit();
}

$image_icon = __getFile($rec, DT_THUMBNAIL, HEURIST_BASE_URL.'favicon.ico');
$image_banner = __getFile($rec, DT_FILE_RESOURCE, null); //HEURIST_BASE_URL.'hclient/assets/h4logo.png'
//$image_background = __getFile($rec, DT_THUMBNAIL, HEURIST_BASE_URL.'favicon.ico');
$meta_keywords = htmlspecialchars(__getValue($rec, DT_CMS_KEYWORDS));
$meta_description = htmlspecialchars(__getValue($rec, DT_SHORT_SUMMARY));


$layout_theme = 'heurist';//'le-frog'; //__getValue($rec, DT_CMS_THEME);
if(!$layout_theme) $layout_theme = 'heurist';
$cssLink = PDIR.'external/jquery-ui-themes-1.12.1/themes/'.$layout_theme.'/jquery-ui.css';


$topmenu = $rec['details'][DT_CMS_TOP_MENU];
$menu_content = __getMenuContent($rec['rec_ID'], $topmenu, 0);

//
//
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
//
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

//
// create menu
//
function __getMenuContent($parent_id, $menuitems, $lvl)
{
    global $system;

    $res ='';

    foreach ($menuitems as $dtl_ID=>$rd){   
                                
                $menu_rec = recordSearchByID($system, $rd['id'], true);
                
                $page_id = __getValue($menu_rec,DT_CMS_PAGE);
              
                $res = $res.'<li><a href="#" style="padding:2px 1em" data-pageid="'.@$page_id['id']
                                .'" title="'.htmlspecialchars(__getValue($menu_rec,DT_SHORT_SUMMARY)).'">'
                                .htmlspecialchars(__getValue($menu_rec,DT_NAME)).'</a>';
                
                $menuitems2 = @$menu_rec['details'][DT_CMS_MENU];
                if(is_array($menuitems2) && count($menuitems2)>0){                          
                    $sub = __getMenuContent($rd['id'], $menuitems2, $lvl+1);
                    if($sub!='')
                        $res = $res. '<ul style="min-width:200px"'.($lvl==0?' class="level-1"':'').'>'.$sub.'</ul>';
                }
                $res = $res. '</li>';
    }
    return $res;
} //__getMenuContent
      
?>
<!DOCTYPE html>
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

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
  
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/search_minimal.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
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
        
    if(!success) return;
    
    var ele = $('body').find('#main-content');
    window.hWin.HEURIST4.msg.bringCoverallToFront(ele);
    ele.show();
    
    //cfg_widgets is from layout_defaults=.js 
    window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, null);
    

    $( "#main-menu > ul" ).addClass('horizontalmenu').menu( {position:{ my: "left top", at: "left+20 bottom" }} );

    $('#main-menu').show();
    $('#main-menu').find('a').addClass('truncate').click(function(event){

        var pageid = $(event.target).attr('data-pageid');
        if(pageid>0){
              $('#main-content').empty().load("<?php print HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&field='.DT_EXTENDED_DESCRIPTION.'&recid='?>"+pageid,
              function(){
                  window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
              });
        }
    });
    
    setTimeout(function(){
        /*var itop = $('#main-header')[0].scrollHeight;
        $('#main-header').css('height',itop-10);
        $('#main-content').css('top',itop+10);*/
        
        window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
        
        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);
        
        window.hWin.HEURIST4.msg.sendCoverallToBack();
    },1500);
    $('#btn_inline_editor2').hide();
    $('#btn_inline_editor').hide();
    
    $( "#main-banner > a").click(function(event){
              window.hWin.HEURIST4.msg.bringCoverallToFront($('body').find('#main-content'));
              $('#main-content').empty().load("<?php print HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&field='.DT_EXTENDED_DESCRIPTION.'&recid='.$rec_id?>",
              function(){
                  window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" );
                  window.hWin.HEURIST4.msg.sendCoverallToBack();
              });
    });
    
}
</script>
<?php
}
?>
<script>
function onHapiInit(success){   
    
    if(!success){    
            window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
            return;
    }
    
    window.hWin.HAPI4.SystemMgr.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
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
            
            $('<div style="height:40px;background:none;padding-left:4px;float:right;color:white">'
                +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                +'" target="_blank" style="text-decoration: none;color:white;">'
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
        // Standalone check
        if(!window.hWin.HAPI4){
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

        }else{
            // Not standalone, use HAPI from parent window
            onPageInit( true );
        }
});    
</script>    
    
<style>
body{
    font-size:0.7em;
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
}
<?php
    if(!$edit_Available){
?>
div.coverall-div {
    background-color: white;
    opacity: 1;
}
<?php        
}
    ?>        
</style>
    
</head>
<body>
    <div class="ent_wrapper">
    <div id="main-header" class="ent_header" style="background:rgb(112,146,190);height:140px">
	    <div id="main-banner" style="float:left;min-height:80px;">
            <a href="#" style="text-decoration:none;">
        <?php print $image_banner?'<img style="max-height:80px" src="'.$image_banner.'">'
            :'<div style="text-align:center;display:block;width:250px;padding: 30px 10px;font-size:16px;background:white;color:red" >Logo / banner image</div>';?>
            </a>
        </div>
        <div id="main-title" style="float:left;padding: 35px 0 0 20px;vertical-align:middle;">
            <h2 class="webpageheading"><?php print __getValue($rec, DT_NAME);?></h2>
        </div>
        
            <div id="host_info" style="float:right;vertical-align: middle;height:40px;margin-right: 15px;margin-top: 15px">
            </div>
            <div style="float:right;padding: 10px;background: white;line-height: 20px;margin-right: 4px;margin-top: 15px">
            <a href="http://HeuristNetwork.org" target="_blank" style="text-decoration:none;"
            title="This website is generated by Heurist, an academic knowledge management system developed at the University of Sydney Faculty of Arts and Social Sciences under the direction of Dr Ian Johnson, chief programmer Artem Osmakov.">
                Powered by <img src="<?php echo HEURIST_BASE_URL ?>hclient/assets/h4_icon_16x16.png" style="vertical-align:sub"> Heurist
            </a> 
            </div>
        
        
	    <div id="main-menu" style="float:left;display:none;width:100%;min-height:40px;padding-top:16px;color:black;font-size: 1.1em;font-weight:bold !important">
            <ul><?php print $menu_content; ?></ul>
        </div>
        
<?php        
if($edit_Available){        
    ?>        
        <a href="#" id="btn_inline_editor2" style="display:none;font-size:1.2em;font-weight:bold;color:blue;">Edit page headers</a>
        <a href="#" id="btn_inline_editor" style="display:none;font-size:1.2em;font-weight:bold;color:blue;">Edit page content</a>
        <a href="#" id="btn_inline_editor3" style="display:none;font-size:1.2em;font-weight:bold;color:blue;">source</a>
        <input id="edit_mode" type="hidden"/>
<?php        
}
    ?>        
    </div>
    <div class="ent_content_full" style="top:145px;padding: 5px;">
        <div id="main-content" data-homepageid="<?php print $rec_id;?>" style="display:none;position:absolute;left:0;right:0;top:0;bottom:0;">
            <?php print __getValue($rec, DT_EXTENDED_DESCRIPTION);?>
        </div>
<?php        
if($edit_Available){        
        print '<textarea class="tinymce-body" style="position:absolute;left:0;width:99.9%;top:0;bottom:0;display:none">'.__getValue($rec, DT_EXTENDED_DESCRIPTION).'</textarea>';
}
?>        
    </div>
    </div>
</body>
</html>

