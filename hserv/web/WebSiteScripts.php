<?php
/**
* WebSiteScripts.php - minimal set of scripts and styles for Heurist CMS website
* It is included in website output by 
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     7.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
if(!defined('PDIR')){
    define('PDIR', HEURIST_BASE_URL);
}
    includeJQuery(true);
    
    $useOldCode = true;
?>
    <script>
        window.hWin = window; //isolated instances (to avoid mix with cmsEditor in parent)
    </script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_query.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HSystemMgr.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>

    <script type="module" src="<?php echo PDIR;?>hclient/widgets/HRecordList/HRecordView.js"></script>
    <script type="module" src="<?php echo PDIR;?>hclient/widgets/HRecordList/HRecordList.js"></script>
    <script type="module" src="<?php echo PDIR;?>hclient/widgets/HMenu/HMenu.js"></script>
    <script type="module" src="<?php echo PDIR;?>hclient/widgets/HMenu/HMenuPersonal.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>layout_default.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HLayoutMgr.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>
   
    <!-- 
    <script type="text/javascript" src="<?php echo PDIR;?>hserv/web/WebSite.js"></script>
    -->
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/ActionHandler.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/CmsManager.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/WebSite.js"></script>

    
<?php    
if($useOldCode){
    include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';
?>    
    <!-- old widgets -->
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/baseAction.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hRecordSearch.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utilsCollection.js"></script>
    
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.css" />
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.layout.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
<?php
}

if(@$_REQUEST['edit']){
?>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>hclient/assets/css/marching_ants.css" />

    <script type="text/javascript" src="<?php echo PDIR;?>external/tinymce5/tinymce.min.js"></script>
    
<style>
.cms-element-active{
    -webkit-box-shadow: inset 0px 0px 38px 10px rgb(201, 194, 249), 0px 0px 8px 10px rgba(0,0,0,0);
    box-shadow: inset 10px 10px 124px 14px rgb(201, 194, 249), 0px 0px 8px 10px rgba(0,0,0,0);
}
.cms-element-editing{
    /* frame around editing element */
    -webkit-box-shadow: 0px 0px 0px 5px rgb(201, 194, 249);
    box-shadow: 0px 0px 0px 5px rgb(201, 194, 249);
}
.cms-element-overlay{
  visibility: hidden;
  position: absolute;
  top: 0;
  left: 0;
  background: rgba(201, 194, 249, 0.5);
}
.tox-toolbar{
    background-color: #b4eeff !important;
}
</style>

    
<?php
}//edit

//include custom script and styles defined in CMS_HOME
echo $this->getCustomScriptsAndStyles();

//includes minimal info about website as json - title, descripton
echo $this->getWebSiteInfo();
?>
    
    <!-- move to WebSite.js -->
    <script>
    
        //let webSite;
    
        window.addEventListener('DOMContentLoaded', event => {

            try{
                //bootstrap workaround
                $.fn.button.noConflict();
                $.fn.tooltip.noConflict();
            }catch(e){
                console.error(e);
            }
            
            if(!window.hWin.HAPI4){
                window.hWin.HAPI4 = new hAPI('<?php echo htmlspecialchars($_REQUEST['db'])?>', onHapiInit);
            }else if(!window.isHapiInited){
                // Not standalone, use HAPI from parent window
                onHapiInit( true );
            }
        });
        
        function onHapiInit(success)
        {
            if(!success){
                return;
            }
            
            // Successfully initialized system
            
            window.hWin.HAPI4.is_publish_mode = true; //to avoid mandatory login and other checks for admin part

            window.isHapiInited = true;

            window.Hul = window.hWin.HEURIST4.util; //TBR: need only for consts in svs_list 

            <?php
                //main menu - json array 
                $menu_content = $this->getMenuTree();
                print 'let menuContentJSON = '.json_encode($menu_content).';'; //used in _editCMS_SiteMenu
                
                $page_content = $this->getPageContent(false);
                $page_content_json = json_decode($page_content, true);
                if($page_content_json){
                    //cms version 2 - json array
                    print 'let pageContentJSON = '.$page_content.';';
                    $page_content = '';
                }else{
                    print 'let pageContentJSON = null;';
                }
                
                print 'let siteId = '.$this->getSiteId().';';
                print 'let pageId = '.$this->getPageId().';';
            ?>
            
            window.hWin.webSite = new WebSite({siteId:siteId, pageId:pageId, siteMenu:menuContentJSON, pageContent:pageContentJSON});
            
            if(window.parent?.cmsEditor){
                //called once - on website init
                window.parent.cmsEditor.onWebSiteLoad();
            }
        }
        
        /*
        * global function to init proper image and links path 
        */ 
        function initLinksAndImages($container, search_data){
            
        }
    </script>
    
