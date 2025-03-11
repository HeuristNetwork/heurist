<?php
/**
* WebSiteScripts.php - minimal set of scripts and styles for Heurist CMS website
* It is included in website output by 
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
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
    
/*
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha384-wsqsSADZR1YRBEZ4/kKHNSmU+aX8ojbnKUMN4RyD3jDkxw5mHtoe2z/T/n4l56U/" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js" integrity="sha384-/L7+EN15GOciWSd0nb17+43i1HKOo5t8SFtgDKGqRJ2REbp8N6fwVumuBezFc4qC" crossorigin="anonymous"></script>
    <link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">
*/ 
?>
    <script>
        window.hWin = window; //isolated
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>    

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

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/HRecordList/HRecordList.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>layout_default.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HLayoutMgr.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>
   
    <!-- 
    <script type="text/javascript" src="<?php echo PDIR;?>hserv/web/WebSite.js"></script>
    -->
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/ActionHandler.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/CmsManager.js"></script>
    
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
}
?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
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

            window.isHapiInited = true;

            window.Hul = window.hWin.HEURIST4.util;

            if(success) // Successfully initialized system
            {

                //webSite = new WebSite();

                //init layout - init Heurist widgets on this page
                //init layout
                const pageTreeData = window.hWin.HAPI4.layoutMgr.layoutInit(pageContentJSON, 'main', {});

                //init header
                window.hWin.HAPI4.layoutMgr.layoutInit(null, 'header', 
                        {HMenu:{onActionComplete:onPageLoad, onBeforeAction:onPageBeforeLoad}});
                
                onPageLoad(<?php echo $this->getPageRecord()?>, pageTreeData);
            }
        }
        
        function onPageBeforeLoad(){
            if(window.parent && window.parent.cmsEditor){
                return window.parent.cmsEditor.warningOnExit();    
            }
            return true;
        }
        
        //
        // for edit
        //        
        function onPageLoad(record, pageTreeData){
            if(window.parent && window.parent.cmsEditor){
                if(pageTreeData){
                    record['pageTreeData'] = pageTreeData;
                }
                if(window.parent && window.parent.cmsEditor){
                    window.parent.cmsEditor.onLoadPageContent(record);    
                }
            }
        }
        
    </script>
    
