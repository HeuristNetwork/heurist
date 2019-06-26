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
if(!($rec_id>0))
{
    //@todo find first record of 99-51 rectype
    $message = 'Parameter recID not defined';
    include ERROR_REDIR;
    exit();
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
$image_banner = __getFile($rec, DT_FILE_RESOURCE, HEURIST_BASE_URL.'hclient/assets/h4logo.png');
//$image_background = __getFile($rec, DT_THUMBNAIL, HEURIST_BASE_URL.'favicon.ico');
$meta_keywords = htmlspecialchars(__getValue($rec, DT_CMS_KEYWORDS));
$meta_description = htmlspecialchars(__getValue($rec, DT_SHORT_SUMMARY));


$topmenu = $rec['details'][DT_CMS_TOP_MENU];
$menu_content = __getMenuContent($rec['rec_ID'], $topmenu);

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
function __getMenuContent($parent_id, $menuitems)
{
    global $system;

    $res ='';

    foreach ($menuitems as $dtl_ID=>$rd){   
                                
                $menu_rec = recordSearchByID($system, $rd['id'], true);
                
                $page_id = __getValue($menu_rec,DT_CMS_PAGE);
              
                $res = $res.'<li><a href="#" data-pageid="'.@$page_id['id']
                                .'" title="'.htmlspecialchars(__getValue($menu_rec,DT_SHORT_SUMMARY)).'">'
                                .htmlspecialchars(__getValue($menu_rec,DT_NAME)).'</a>';
                
                $menuitems2 = @$menu_rec['details'][DT_CMS_MENU];
                if(count($menuitems2)>0){                          
                    $sub = __getMenuContent($rd['id'], $menuitems2);
                    if($sub!='')
                        $res = $res. '<ul style="min-width:200px">'.$sub.'</ul>';
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
    <meta name="keywords" content="<?=$meta_keywords?>">
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
    <link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>" /> <!-- theme css -->
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />  <!-- base css -->

  <script>
$( function() {
    $( "#main-menu > ul" ).addClass('horizontalmenu').menu({position:{ my: "left top", at: "left+40 bottom" }});

    $('#main-menu').find('a').click(function(event){

        var pageid = $(event.target).attr('data-pageid');

        if(pageid>0){
              $('#main-content').empty().load("<?php print HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&fmt=web&recid='?>"+pageid);
        }
        $('body').css('padding-top', $('#main-header').height()+20);
        
    });
    setTimeout(function(){
        $('body').css('padding-top', $('#main-header').height()+20);    
    },500);
    
} );
  </script>
  
  <style>
  #main-foooter,#main-header{
        width: 100%;
        position: fixed;        
        background: lightgray;
        padding: 10px;
        color: black;
  }
  #main-foooter{
        bottom: 0;
  }
  #main-header{
        top: 0;      
  }
  #main-content{
       padding: 10px;
  }
  body{
        Xpadding-top: 140px;              
        padding-bottom: 40px;      
  }
  </style>
    
</head>
<body>
    <div id="main-header">
	    <div id="main-banner" style="width:100%;min-height:80px"><img src="<?php print $image_banner; ?>"></div>
	    <div id="main-menu" style="width:100%;min-height:40px"><ul><?php print $menu_content; ?></ul></div>
    </div>
    <div id="main-content"><?php print __getValue($rec, DT_EXTENDED_DESCRIPTION);?></div>
    <div id="main-foooter">Footer</div>
</body>
</html>

