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

if($rec['rec_RecTypeID']!=RT_CMS_HOME && $rec['rec_RecTypeID']!=RT_CMS_PAGE){
    $message = 'Record #'.$rec_id.' is not allowed record type';
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

if($rec['rec_RecTypeID']==RT_CMS_PAGE){
    print __getValue($rec, DT_EXTENDED_DESCRIPTION);
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
                if(count($menuitems2)>0){                          
                    $sub = __getMenuContent($rd['id'], $menuitems2, $lvl+1);
                    if($sub!='')
                        $res = $res. '<ul style="min-width:200px"'.($lvl==0?' class="level-1"':'').'>'.$sub.'</ul>';
                }
                $res = $res. '</li>';
    }
    return $res;
} //__getMenuContent
      
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
$edit_Available = true;//(@$_REQUEST['edit']==1);
if($edit_Available){
?>
    <script src="<?php echo PDIR;?>external/tinymce/tinymce.min.js"></script>
    <script src="<?php echo PDIR;?>external/tinymce/jquery.tinymce.min.js"></script>
    <script src="websiteRecord.js"></script>
    <?php
}else{
?>    
  <script>
//init page for publication version  
function onPageInit(success)
{
        
    if(!success) return;

    $( "#main-menu > ul" ).addClass('horizontalmenu').menu( {position:{ my: "left top", at: "left+20 bottom" }} );

    $('#main-menu').find('a').click(function(event){

        var pageid = $(event.target).attr('data-pageid');
        if(pageid>0){
              $('#main-content').empty().load("<?php print HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&fmt=web&recid='?>"+pageid);
        }
    });
    
    setTimeout(function(){
        var itop = $('#main-header')[0].scrollHeight;
        $('#main-header').css('height',itop+20);
        $('#main-content').css('top',itop+10);
        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);
    },1500);
    $('#btn_inline_editor').hide();
    
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
    padding-top: 10px;
}
#main-content{
    bottom:20px;
    padding: 5px;
}
</style>
    
</head>
<body>
    <div class="ent_wrapper">
    <div id="main-header" class="ent_header">
	    <div id="main-banner" style="width:100%;min-height:80px;">
        <?php print $image_banner?'<img style="max-height:80px" src="'.$image_banner.'">'
            :'<div style="display:block;width:250px;padding: 30px 10px;font-size:16px;background:white;color:red" >Logo / banner image</div>';?>
        </div>
	    <div id="main-menu" style="width:100%;min-height:40px;padding:4px 0"><ul><?php print $menu_content; ?></ul></div>
        <div id="btn_inline_editor" style="display:none;">Edit inline</div>
        <div id="btn_inline_cancel" style="display:none;">Close editor</div>
    </div>
    <div id="main-content" class="tinymce-body ent_content" data-homepageid="<?php print $rec_id;?>">
        <?php print __getValue($rec, DT_EXTENDED_DESCRIPTION);?>
    </div>
    <div id="main-foooter" class="ent_footer">
        <a href="http://HeuristNetwork.org" target="_blank" style="text-decoration:none;" 
        title="This website is generated by Heurist, an academic knowledge management system developed at the University of Sydney Faculty of Arts and Social Sciences under the direction of Dr Ian Johnson, chief programmer Artem Osmakov.">
            Powered by <img src="<?php echo HEURIST_BASE_URL ?>hclient/assets/h4_icon_16x16.png" style="vertical-align:sub"> Heurist
        </a> 
    </div>
    </div>
</body>
</html>

