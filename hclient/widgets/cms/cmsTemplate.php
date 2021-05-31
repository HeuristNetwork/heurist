<?php

    /**
    *  CMS website template 
    * 
    *  You may use several templates per server (for specific websites).
    *  We encourage the following of naming convention cmsTemplate_OrganisationName.php
    *  Copy to /HEURIST root folder and specify this name in the field "Website Template" (field 2-922) 
    *  in the Advanced tab of the CMS home page record.
    * 
    *  The template can also be specified as a relative path hclient/widgets/cms/<template name> but this
    *  should ONLY be used for development as it uses a path which might change and local changes could
    *  get overwritten by code updates.
    * 
    *  The template must contain at least two html elements main-header with main-menu and
    *  main-content
    * 
                <div id="main-header" style="width:100%;min-height:40px;">
                    <div id="main-menu" class="mceNonEditable header-element" 
                        style="width:100%;min-height:40px;color:black;font-size:1.1em;" 
                        data-heurist-app-id="heurist_Navigation" data-generated="1">
                        <?php print $page_header_menu; ?>
                    </div>
                </div>
                <div id="main-content" 
                    data-homepageid="<?php print $home_page_record_id;?>" 
                    <?php print ($open_page_on_init>0)?' data-initid="'.$open_page_on_init.'"':''; ?> 
                    data-viewonly="<?php print ($hasAccess)?0:1;?>">
                </div>
    *  
    * besides main-menu main-header may have main-logo, main-logo-alt, main-host, main-pagetitle divs
    * These divs will be filled with images and text defined in website home record.
    * 
    * main-content - is the target div for content of particular page to be loaded  
    * 
    * There are following variables (their values are defined in website home record) that can be used in html header
    * $website_title
    * $meta_keywords
    * $meta_description 
    * $image_icon
    * 
    * Other variables are 
    * $home_page_record_id  - record id of website home page 
    * $open_page_on_init - record id for cms menu/page to be loaded on init
    * 
    * $image_banner - header background banner image 
    * $page_header_menu - code to define main menu widget, leave it unchanged as content of main-menu div
    * $page_header - custom content for main-header defined in website home page record 
    * $page_footer - custom content for footer  
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
?>
<html>
<head>
    <title><?php print htmlspecialchars(strip_tags($website_title));?></title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="keywords" content="Heurist, Digital Humanities, Humanities Data, Research Data, Database Management, Academic data, Open Source, Free software, FOSS, University of Sydney,<?php echo $meta_keywords;?>">
    <meta name="description" content="<?php echo $meta_description;?>">
    <link rel="icon" href="<?php echo $image_icon;?>"> <!--  type="image/x-icon" -->
    <link rel="shortcut icon" href="<?php echo $image_icon;?>">
    
    <?php
        // add heurist/hclient/widgets/cms/ if you modify this file and copy to heurist root folder
        include 'websiteScriptAndStyles.php';  
    ?>
    
    
<style>
/* page (menu) title it is added to main-pagetitle */
.webpageheading {
    font-size:1.5em;
    /*color:black;
    position:absolute;*/
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
    height:144px; 
    padding: 0.5em;
    padding-bottom:0;
}
#main-title > h2{
    font-size:1.7em;
    margin-top:4px;
    padding:0 10px;
    max-height:80px;
    overflow: hidden;    
}
#main-menu .horizontalmenu > li.ui-menu-item > a{
   font-weight:bold !important; 
   font-size:1.3em !important;
}
#main-pagetitle{
    position: absolute;
    padding: 15 0 5 10;
    top:150px;
    bottom: 0;
    left: 0;
    right: 0;
    /*background: white;*/
    min-height: 19px;
    display:none;
}
/*
.horizontalmenu > li.ui-menu-item > a{
    background: green;
}
*/
#main-menu .ui-menu > li > a.selected{
    background: black !important;
    /*color: white !important;*/
}
.cms-button{
    font-size:0.8em;
    font-weight:bold;
    color:blue;
}

</style>
</head>
<body>
<?php
/*
default content consists of 
    #main-header - header width logo, banner, hostinfo and main menu
        #main-logo, #main-logo-alt, #main-host, #main-menu, #main-pagetitle
        
    #main-content-container > #main-content - target the content of particular page will be loaded  
*/
if($isWebPage){
//WEB PAGE - EMBED
?>
<div class="ent_wrapper">
<?php
    if($showWarnAboutPublic){
        print '<div style="top:0;height:20px;position:absolute;text-align:center;width:100%;color:red;">Web page record is not public. It will not be visible to the public</div>';  
    }
?>
    <div class="ent_content_full ui-heurist-bg-light" style="top:<?php echo ($showWarnAboutPublic)?20:0; ?>px" 
                    id="main-content-container">
        <div id="main-content" data-homepageid="<?php print $home_page_record_id;?>" 
                               data-viewonly="<?php print ($hasAccess)?0:1;?>">
        </div>
    </div>
</div>    
<?php
        
//WEB SITE      
}else{
?>

    <div class="ent_wrapper">
    <div id="main-header" class="ent_header ui-heurist-header2" <?php print $image_banner?'style="background-image:url(\''.$image_banner.'\') !important;background-repeat: repeat-x !important;background-size:auto 170px !important;"':'' ?>>
    
<?php
    if($showWarnAboutPublic){
      print '<div style="position: absolute;text-align: center;width: 100%;color: red;">Web site record is not public. It will not be visible to the public</div>';  
    }

    if($page_header!=null && $page_header!=''){ //custom header content
        print $page_header;        
    } else {
?>                        
        <div id="main-logo" class="mceNonEditable header-element" style="position:absolute;top:20px;left:10px;max-height:90px;max-width:270px;border:2px none red;"></div>
        
        <div id="main-logo-alt" class="mceNonEditable header-element" style="position:absolute;top:20px;right:10px;height:70px;width:270px;border:2px none red;"></div>
        
        <div id="main-title" class="mceNonEditable header-element" style="position:absolute;top:20px;left:280px;right:280px;max-height:90px;"></div>
        
        <div id="main-menu" class="mceNonEditable header-element" style="position:absolute;top:110px;width:100%;min-height:40px;border:2px none yellow;color:black;font-size:1.1em;" data-heurist-app-id="heurist_Navigation" data-generated="1">
            <?php print $page_header_menu; ?>
        </div>

<?php        
    }//header

    if(!$edit_Available && $system->is_member(2)){
        print '<a href="'.HEURIST_BASE_URL.'?db='.$system->dbname().'&cms='.$home_page_record_id.'" id="btn_editor" target="_blank" '
        .'style="position:absolute;right:10px; top:5px;" class="cms-button">Heurist interface</a>';
    }
    ?>  
        <div id="main-pagetitle" class="ui-heurist-bg-light">loading...</div>       
    </div>
    <div class="ent_content_full  ui-heurist-bg-light"  id="main-content-container"
            style="top:152;<?php echo ($is_page_footer_fixed?'bottom:'.$page_footer_height.'px;':''); ?>padding: 5px;">
        <div id="main-content" data-homepageid="<?php print $home_page_record_id;?>" 
            <?php print ($open_page_on_init>0)?'data-initid="'.$open_page_on_init.'"':''; ?> 
            data-viewonly="<?php print ($hasAccess)?0:1;?>" 
            style="<?php echo (!$is_page_footer_fixed?'padding-bottom:'.$page_footer_height.'px;position:relative':'');?>">
<?php
            if(!$is_page_footer_fixed) print $page_footer;
?>        
        </div>
    </div>
<?php
        if($is_page_footer_fixed && $page_footer) print $page_footer;
?>        
    </div>
<?php
}
?>    
</body>
</html>