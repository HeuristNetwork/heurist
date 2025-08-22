<?php
/**
* WebSiteScripts.php - minimal set of scripts and styles for Heurist CMS website
* It is included in website output by WebSiteTemplate.php that in turn is included in WebSite.php
*  $this - is instance of WebSite class
*
* @project     Heurist academic knowledge management system
* @package CMS
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
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

    <link href="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-html5-3.1.2/datatables.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-html5-3.1.2/datatables.min.js" integrity="sha384-naBmfwninIkPENReA9wreX7eukcSAc9xLJ8Kov28yBxFr8U5dzgoed1DHwFAef4y" crossorigin="anonymous"></script>
    
<?php    
if($useOldCode){
    include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';
?>    
    <!-- old widgets -->
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/baseAction.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hRecordSearch.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utilsCollection.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/svs_list.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchInput.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search_faceted.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectMultiValues.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/recordListExt.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListCollection.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/app_storymap.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/app_timemap.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/buttonsMenu.js"></script>
    
    
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

// this script is included into WebSiteTemplate that in turn in WebSite.php
// $this - is instance of WebSite class

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
                
                /* not used, it loads page content on client site in WebSite.#iniPage
                $page_content = $this->getPageContent(false);
                $page_content_json = json_decode($page_content, true);
                if($page_content_json){
                    //cms version 2 - json array
                    print 'let pageContentJSON = '.$page_content.';';
                    $page_content = '';
                }else{
                    print 'let pageContentJSON = null;';
                }
                print 'let menuContentJSON = '.json_encode($menu_content).';'; //used in _editCMS_SiteMenu
                print 'const siteId = '.$this->getSiteId().';';
                print 'const pageId = '.$this->getPageId().';';
                print 'const isWebPage = '.($this->isWebPage?'true':'false').';';
                //{siteId:siteId, pageId:pageId, siteMenu:menuContentJSON, pageContent:pageContentJSON, isWebPage:isWebPage}
                */

                $webSiteOptions = $this->getWebSiteOptions(false);
                $webSiteOptions = json_encode($webSiteOptions);
            ?>
            
            window.hWin.HAPI4.EntityMgr.initialLoadDatabaseDefintions('all', ()=>{
                
                window.hWin.webSite = new WebSite(<?php echo $webSiteOptions; ?>);
                
                if(window.parent?.cmsEditor){
                    //called once - on website init
                    window.parent.cmsEditor.onWebSiteLoad();
                }
                
            });
        }
        
        /*
        * global function to init proper image and links path 
        */ 
        function initLinksAndImages($container, search_data){
            
        }
    </script>
    
