<?php
use hserv\utilities\USystem;
use hserv\utilities\USanitize;

/**
*  Website generator based on CMS records 99-51,52,53
*
*  It is either generate home page from cmsTemplate file (inits main menu, header, footer)
*  or returns content for particular page
*
*  Parameters
*  recID - home page record (99-51) or web page (99-53)
*          if is is not defined it takes first record of type 'Home page'
*
* if home page has defined as template file it is loaded as body, otherwise default template
* that includes header with main-logo, main-title, main-menu and
* main-content where content of particular page will be loaded
*
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2025 Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @version     7.0
*/

if(!defined('PDIR')) {
    define('PDIR','../../../');//need for proper path to js and css
}
require_once dirname(__FILE__).'/../../framecontent/initPage.php';

if(!isset($params)){
    $params = USanitize::sanitizeInputArray();
}
$website_id = @$params['website'];
if(!isPositiveInt($website_id)){
    $website_id = 0; //default website
}
$page_id = @$params['pageid'];
if(!isPositiveInt($page_id)){
    $page_id = $website_id;
}

$editor_options = "{website_id:$website_id, page_id:$page_id}";
?>

<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/ui.tabs.paging.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.js" charset="utf-8"></script>
<link href="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.css" rel="stylesheet" type="text/css">
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.layout.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.css" />
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editorCodeMirror.js"></script>
<link rel="stylesheet" href="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.css">
<script src="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.js"></script>
<script src="<?php echo PDIR;?>external/codemirror-5.61.0/lib/util/formatting.js"></script>
<script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/xml/xml.js"></script>
<script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/javascript/javascript.js"></script>
<script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/css/css.js"></script>
<script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/htmlmixed/htmlmixed.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>layout_default.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/HCmsEditor.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/HCmsEditorPage.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/HCmsConfig.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/HCmsConfigWidget.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_SelectElement.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_WidgetCfg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/HCmsEditorElement.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/HCmsEditorMargin.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_SiteMenu.js"></script>
<script type="module" src="<?php echo PDIR;?>hclient/widgets/cms/HCmsCodeEditor.js"></script>

<script type="module" src="<?php echo PDIR;?>hclient/widgets/HRecordList/HRecordList.js"></script>
<!--
<script type="module" src="<?php echo PDIR;?>hclient/widgets/HMenu/HMenu.js"></script>
-->

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/navigation.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/svs_list.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchInput.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search_faceted.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectMultiValues.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/recordListExt.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListCollection.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/app_storymap.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/buttonsMenu.js"></script>

<!-- for record edit -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectFile.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrTags.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrTags.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/mediaViewer.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/baseAction.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAction.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAccess.js"></script>
    
    
<style>
.ui-heurist-publish .fancytree-active, .ui-heurist-publish .fancytree-editing, .ui-heurist-publish .fancytree-hover{
  background: rgba(201, 194, 249, 1) !important;
}
.ui-heurist-publish span.fancytree-node {
    padding: 3px 0px !important;
}
.ui-cms-mainmenu{
    background: rgb(135, 205, 118) !important;
}
</style>

<script type="text/javascript">

    window.cmsEditor = null;
    let tinymce;
    
    let isWebPage = false;

    function onPageInit(success){

        if(!success) {return;}
        
        let options = <?php echo $editor_options;?>;
        
        //set global constants
        window.hWin.RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];
        window.hWin.DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        window.hWin.DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
        
        window.cmsEditor = new HCmsEditor(options);
        
    }
</script>
</head>
<body style="background-color:#c9c9c9;">


    <div class="ui-layout-west">
        <div class="ent_wrapper editStructure" id="tabsEditCMS">
            <div class="ent_header" style="height:5.5em">

                <div class="btn-website-edit" style="font-weight:normal !important; width: fit-content;margin:0.7em 0px;">Website layout / properties</div>

                <div style="line-height: 1em;font-size: smaller;"><span class="btn-website-url" style="display:inline-block;color:black;padding-right:5px;">Website URL</span>
                    <a href="#" class="website-url truncate" style="color: blue;display: inline-block;width:70%;vertical-align: -1px;"></a>
                </div>

                <span style="position:absolute;top: 0.3em; width: 1em; height: 1em; font-size: 3em; cursor: pointer;right:0.05em"
                    class="bnt-cms-hidepanel ui-icon ui-icon-carat-2-w"></span>

            </div>
            <div class="ent_content_full" style="top:5.5em">

                <ul style="margin-right:40px;font-size:9px;">
                    <li><a href="#treeWebSite">Site</a></li><li><a href="#treePage">Page</a></li>
                </ul>

                <div id="treeWebSite" style="display:none;top:2em" class="ent_wrapper ui-cms-mainmenu">
                    <div class="toolbarWebSite ent_header" style="height:85px;padding-top:2px;">

                        <span style="display:block;border-top:1px solid gray;padding:4px 8px;margin:4px 0px;"></span>

                        <span style="display:inline-block;padding-top:7px" class="heurist-helper1" title="Select menu item and Dblclick (or F2) to edit menu title in place. Drag and drop to reorder menu">
                            Drag menu items to re-order
                        </span>
                        <br>
                        <span style="display:inline-block;padding-top:3px" class="heurist-helper1">
                            Click to edit the page
                        </span>

                        <div style="padding:5px 0px 5px 8px;" class="fancytree-node">
                            <a href="#" title="Edit website home page"
                                class="btn-website-homepage" style="text-decoration:none;">
                                <span class="ui-icon ui-icon-home"></span>&nbsp;Home page
                            </a>
                            <span  title="Add top level menu" class="btn-website-addpage ui-icon ui-icon-plus" 
                                style="display:none;float:right;cursor:pointer;color:black;margin-top:0px"></span>
                        </div>
                        <div style="padding:3px 32px;" class="fancytree-node">
                            <a href="#" title="Edit website header"
                                class="btn-website-header" style="text-decoration:none;">
                                Header
                            </a>
                            <span  class="btn-website-header ui-icon ui-icon-pencil" 
                                style="display:none;float:right;cursor:pointer;color:black;margin-top:0px"></span>
                        </div>
                        <div style="padding:3px 32px;" class="fancytree-node">
                            <a href="#" title="Edit website footer"
                                class="btn-website-footer" style="text-decoration:none;">
                                Footer
                            </a>
                            <span  class="btn-website-footer ui-icon ui-icon-pencil" 
                                style="display:none;float:right;cursor:pointer;color:black;margin-top:0px"></span>
                        </div>

                    </div>

                    <div class="treeWebSite ent_content_full" style="top:125px;padding:3px 10px;"></div>
                </div>

                <div id="treePage" style="font-size:0.9em;top:2em;" class="ent_wrapper ui-widget-content">

                    <div class="treePageHeader ent_header" style="height:85px;line-height:normal;">

                        <h3 class="truncate" style="margin-block-start: 0.3em; margin-block-end: 0.7em; font-size: 10px; font-family: revert; max-width: 65%; display: inline-block">
                            Page title
                        </h3>

                        <span style="float: right; padding-top: 2px;" class="heurist-helper1 element_edit">
                            <a href="?db=Heurist_Help_System&website&id=39&pageid=708" target="_blank">
                                <span class="ui-icon ui-icon-circle-help" style="font-size:12px;"></span>
                            </a>
                        </span>
                        
                        <select name="responsiveScreen" id="responsiveScreen" title="Responsive screen width" style="float: right; font-size: 10px;max-width:60px;">
                            <option value="100" selected>100%</option>
                            <option value="540">Small (540px)</option>
                            <option value="720">Medium (720px)</option>
                            <option value="960">Large (960px)</option>
                            <option value="1200">XLarge (1200px)</option>
                            <option value="1400">XXLarge (1400px)</option>
                        </select>
                        
                    </div>

                    <div class="treePage ent_content_full" style="top: 20px; padding: 0px 10px 5px; border-top: 1px solid gray; line-height: normal; font-size: 10px;"></div>

                    <div class="propertyView ent_content_full ui-widget-content-gray" 
                        style="top:190px;padding:10px 0px;display:none;"></div>

                </div>
            </div>
        </div>
    </div>

    <div class="ui-layout-center" style="text-align:center">
        <iframe id="webPageFrame" width="100%" height="100%" title="Web Page Preview"></iframe>
    </div>
    
</body>
</html>
