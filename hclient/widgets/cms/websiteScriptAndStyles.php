<?php
 $_is_new_cms_editor = true;

    /**
    *  Injection of Heuirst core scripts, styles and scripts to init CMS website template 
    * 
    *  It should be included in CMS template php sript in html header section
    *   
    *  include 'websiteScriptAndStyles.php'; 
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

if (($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1')&& !@$_REQUEST['embed'])  {
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
<?php 
    //PDIR.
    include dirname(__FILE__).'/../../framecontent/initPageCss.php'; 
    
    if(true || !$edit_OldEditor){ //creates new instance of heurist
        print '<script>window.hWin = window;</script>';
    }
?>
    
<script>
    var _time_debug = new Date().getTime() / 1000;
    var page_first_not_empty = 0;
    var home_page_record_id=<?php echo $home_page_on_init; ?>;
    var init_page_record_id=<?php echo $open_page_on_init; ?>;
    var current_page_id = 0;
    var _IS_NEW_CMS_EDITOR = <?php echo $_is_new_cms_editor; ?>;
    var isCMS_active = <?php echo $edit_ActivateEditor?$edit_ActivateEditor:'false'; ?>;
    var is_embed =<?php echo array_key_exists('embed', $_REQUEST)?'true':'false'; ?>;
</script>
    
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_collection.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/search_minimal.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/localization.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>layout_default.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/dropdownmenus/navigation.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/svs_list.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchInput.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search_faceted.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/recordListExt.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListCollection.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>external/tinymce5/tinymce.min.js"></script>

<?php
if($_is_new_cms_editor){
?>
<!-- @todo load these scripts dynamically if edit mode is ON -->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_SelectElement.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_WidgetCfg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_ElementCfg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_SiteMenu.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/hLayoutMgr.js"></script>
    
<style>
.tox-toolbar{
    background-color: #b4eeff !important;
}
</style>     
<?php
}
    
if(($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1')){
?>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/js/datatable/datatables.min.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/datatable/datatables.min.js"></script>        
<?php
}else{
?>
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css"/>
<?php 
}

if(is_array($external_files) && count($external_files)>0){
    foreach ($external_files as $ext_file){
        if(strpos($ext_file,'<link')===0 || strpos($ext_file,'<script')===0){
            print $ext_file."\n";
        }
    }
}

if($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'){
        print '<script type="text/javascript" src="'.PDIR.'external/jquery.fancytree/jquery.fancytree-all.min.js"></script>';
}else{
        print '<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.16.1/jquery.fancytree-all.min.js"></script>';
}   
    print '<link rel="stylesheet" type="text/css" href="'.PDIR.'external/jquery.fancytree/skin-themeroller/ui.fancytree.css" />';


//do not include edit stuff for embed 
if(!array_key_exists('embed', $_REQUEST)){
?>    
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-file-upload/js/jquery.iframe-transport.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-file-upload/js/jquery.fileupload.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/wellknown.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_geo.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/select_imagelib.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/ui.tabs.paging.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/evol.colorpicker.js" charset="utf-8"></script>
    <link href="<?php echo PDIR;?>external/js/evol.colorpicker.css" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/configEntity.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrTags.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrTags.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/mediaViewer.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAction.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAccess.js"></script>
    
    <!--
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/tinymce/jquery.tinymce.min.js"></script>
    -->
    
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>
    
    <script type="text/javascript" src="<?php echo PDIR;?>admin/structure/import/importStructure.js"></script>
<?php
}

if($_is_new_cms_editor || $edit_OldEditor){ //$edit_OldEditor defined in websiteRecord.php - if true we use old CMS editor
?>
    <link rel="stylesheet" href="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.css">
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/lib/util/formatting.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/xml/xml.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/javascript/javascript.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/css/css.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/htmlmixed/htmlmixed.js"></script>
    
    <style>
.cms-element-active{
    -webkit-box-shadow: inset 0px 0px 38px 10px rgb(201, 194, 249), 0px 0px 8px 10px rgba(0,0,0,0);
    box-shadow: inset 10px 10px 124px 14px rgb(201, 194, 249), 0px 0px 8px 10px rgba(0,0,0,0);
    
}   
/*     box-shadow: inset 0px 0px 38px 10px rgb(201, 194, 249), 0px 0px 8px 10px rgba(0,0,0,0); */
.cms-element-overlay{
  visibility: hidden;
  position: absolute;
  top: 0;
  left: 0;
  background: rgba(201, 194, 249, 0.5);
} 

.ui-heurist-publish .fancytree-active, .ui-heurist-publish .fancytree-node:hover {
  background: rgba(201, 194, 249, 1) !important;
}

.ui-heurist-publish span.fancytree-node {
    padding: 3px 0px !important;
}

/* use pseudo elmenent for overlay    
  right: 0;
  width: 100%;
  height: 100%;
  z-index: 3;
.image::before {
  content: '';
  visibility: hidden;
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  width: 100%;
  height: 100%;
  z-index: 3;
  background: rgba(100, 81, 246, 0.9);
  border-radius: 10px;
  -webkit-transition: all 0.7s ease-out;
  transition: all 0.7s ease-out;
}

.image:hover::before {
  visibility: visible;
}    
*/
    
    </style>
    
<?php
}

if($edit_OldEditor){  //old CMS editor
?>
    <!--
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="<?php echo PDIR;?>external/tinymce/tinymce.min.js"></script>
    <script src="<?php echo PDIR;?>external/tinymce5/jquery.tinymce.min.js"></script>
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js"></script>
    <script src="<?php echo PDIR;?>external/tinymce/jquery.tinymce.min.js"></script>
    -->
    <script src="websiteRecord.js"></script>
    <?php
}else{
?>    
<script>
function _dout(msg){      
    //console.log(msg);
}

// global 
var DT_NAME, DT_EXTENDED_DESCRIPTION, DT_CMS_SCRIPT, DT_CMS_CSS;
//
// init page for publication version  
// for old cms version see websiteRecord.js
// invoked from onHapiInit
//
function onPageInit(success)
{

DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
DT_CMS_SCRIPT = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_SCRIPT'];
DT_CMS_CSS = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_CSS'];

_dout('webpage onPageInit  '+(new Date().getTime() / 1000 - _time_debug));
_dout('webpage onPageInit  '+init_page_record_id);

_time_debug = new Date().getTime() / 1000;
        
    if(!success) return;
    
    $('#main-menu').hide();
    
    hLayoutMgr();
    
    //cfg_widgets is from layout_defaults.js
    window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, null);
    
    //reload website by click on logo, opens first page with content
    $("#main-logo").click(function(event){
            //location.reload();
            var load_initially = home_page_record_id;
            <?php if($isEmptyHomePage) echo 'if(not_empty_page){ load_initially=not_empty_page;}'; ?>
            loadPageContent( load_initially );
    });
    
    //fix bug for tinymce popups - it lost focus if it is called from dialog
    /*
    $(window.hWin.document).on('focusin', function(e) {
        if ($(e.target).closest(".tox-tinymce-aux").length) {
            e.stopImmediatePropagation();
        }
    });
    */

    $(document).on('focusin', function(e) {
        if ($(e.target).closest(".tox-tinymce-aux").length) {
            e.stopImmediatePropagation();
        }
    });

    
    setTimeout(function(){
        //init main menu in page header
        //add menu definitions to main-menu

        function __onInitComplete(not_empty_page){
            //load given page or home page content
            var load_initially = home_page_record_id;
            <?php if($isEmptyHomePage) echo 'if(not_empty_page){ load_initially=not_empty_page;}'; ?>
            
            loadPageContent( init_page_record_id>0 ?init_page_record_id :load_initially );
            
        }
        
        var topmenu = $('#main-menu');
        
        if(topmenu.length==0){ //menu-less page
            
            __onInitComplete();
            
        }else{
            
            initMainMenu( __onInitComplete );
            
        }
        
        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);
        
        var itop = $('#main-header').height(); //[0].scrollHeight;
        //MOVED to top right corner $('#btn_editor').css({top:itop-70, right:20});
        
    },300);
    
    
}

//
// afterInitMainMenu - loads intial page (usually this is home page
//
function initMainMenu( afterInitMainMenu ){
    
    var topmenu = $('#main-menu');

    var lopts = {  
                menu_recIDs: home_page_record_id, 
                main_menu: true, //search for RT_CMS_HOME as root
                use_next_level: true, 
                orientation: 'horizontal',
                toplevel_css: {background:'none'}, //bg_color 'rgba(112,146,190,0.7)'
                onInitComplete: afterInitMainMenu
                };
    
    if(_IS_NEW_CMS_EDITOR){ 
        lopts['onmenuselect'] = loadPageContent;
    }else{
        lopts['aftermenuselect'] = afterPageLoad;
    }
    
    lopts = {heurist_Navigation:lopts};
    
    topmenu.attr('data-heurist-app-id','heurist_Navigation');
    window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, topmenu.parent(), lopts);
        //afterInitMainMenu);
    topmenu.show();
}

//
// Loads content of specified record to #main-content and inits all widgets 
//
function loadPageContent(pageid){
    
    /* @todo
    var args = null; //arguments that will be passed to afterPageLoad
    
    if(typeof pageid==='string' && pageid.indexOf('-')>0){
        args = pageid.split('-');
        pageid = args[0];
    }else{
        args = [pageid];
    }
    */
    
    
    if(pageid>0){
        //window.hWin.HEURIST4.msg.bringCoverallToFront($('body').find('#main-content'));
        
        
        var page_target = $('#main-content');
        //_dout('load page  '+pageid+'   '+page_footer.length);              
        
        var supp_options = {rec_ID:home_page_record_id};
        

<?php        
//style from field DT_CMS_CSS of home record 
if($site_css!=null){
        print 'supp_options = {heurist_resultListExt:{custom_css_for_frame:"'
            .htmlspecialchars(str_replace("\n",' ',$site_css)).'"}};';
}
?>          
        
        if(_IS_NEW_CMS_EDITOR){ 
        
            function __loadPageContent(){

                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                if(isCMS_active){
                    $('#btnOpenCMSeditor').hide();
                    if(!editCMS_instance2) editCMS_instance2 = editCMS2();
                    
                    if (! editCMS_instance2.startCMS({record_id:pageid, container:'#main-content',
                                    close: function(){
                                        isCMS_active = false;
                                        $('#btnOpenCMSeditor').show();
                                    }})) //see editCMS2.js    
                    {
                        //page is not loaded (previous page has been modified and not saved
                        return;
                    }

                }else{
                    layoutMgr.layoutInit( page_cache[pageid][DT_EXTENDED_DESCRIPTION], '#main-content', supp_options );    
                }
                
                current_page_id = pageid;
                
                var page_footer = page_target.find('#page-footer');
                if(page_footer.length>0){
                    page_footer.detach();
                    page_footer.appendTo( page_target );  
                    page_target.css({'min-height':page_target.parent().height()-page_footer.height()-10 });
                } 

                afterPageLoad( document, pageid ); //execute custom script and custom css
            }        
            
            if(page_cache[pageid]){
                __loadPageContent();
            }else{
                //var request = {recid:pageid, field:, db:window.hWin.HAPI4.database};
                //window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL, request, null, __loadPageContent);
                
                var server_request = {
                    q: 'ids:'+pageid,
                    restapi: 1,
                    columns: 
                    ['rec_ID', DT_NAME, DT_EXTENDED_DESCRIPTION, DT_CMS_SCRIPT, DT_CMS_CSS],
                    zip: 1,
                    format:'json'};
                
                //perform search see record_output.php       
                window.hWin.HAPI4.RecordMgr.search_new(server_request,
                    function(response){
//console.log(response);                       
                       if(window.hWin.HEURIST4.util.isJSON(response)) {
                           if(response['records'] && response['records'].length>0){
                               var res = response['records'][0]['details'];
                               var keys = Object.keys(res);
                               for(var idx in keys){
                                   var key = keys[idx];
                                   res[key] = res[key][ Object.keys(res[key])[0] ];
                               }
                               //res[DT_NAME] = res[DT_NAME]
                               //res[DT_NAME, DT_EXTENDED_DESCRIPTION, DT_CMS_SCRIPT, DT_CMS_CSS]
    //console.log(res);                           
                               page_cache[pageid] = res;
                               __loadPageContent();
                           }else if(pageid!=home_page_record_id){ //load home page by default
                               loadPageContent(home_page_record_id);
                           }else{
                               window.hWin.HEURIST4.msg.showMsgErr('Web Page not found (record #'+pageid+')');
                           }
                       }else {
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                       }
                    });
                
            }
            
        }else{
            //OLD VERSION page_target will have header (with class .webpageheading) and content  
            
            page_target.empty().load(window.hWin.HAPI4.baseURL+'?db='
                +window.hWin.HAPI4.database+'&field=1&recid='+pageid,
                function(){

                    //init al widgets on this page in #main-content
                    window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, '#main-content', supp_options );
                    window.hWin.HEURIST4.msg.sendCoverallToBack();

                    if(page_footer.length>0){
                        page_footer.appendTo( page_target );  
                        page_target.css({'min-height':page_target.parent().height()-page_footer.height()-10 });
                    } 

                    afterPageLoad( document, pageid ); //execute custom script and custom css
            });
            
        }

    }
}
</script>
<?php
}
?>
<script>
var page_cache = {};
var previous_page_id = -1;

var datatable_custom_render = null;
//
// Executes custom javascript defined in field DT_CMS_SCRIPT
// it wraps this script into function afterPageLoad_[pageid](args) and adds this script into head
// then it executes this function
// args - are arguments to be passed to custom javascript function - first element of array is pageid
//
function afterPageLoad(document, args){
    
    
    if($.isArray(args)){
        pageid = args[0];
    }else{
        pageid = args;
    }
    
    //var pagetitle = $($(page_target).children()[0]);
    var pagetitle = $('#main-content > h2.webpageheading');
    var title_container = $('#main-pagetitle');
    var show_page_title = false;
    
    if(pagetitle.length>0){
        //remove redundant webpageheadings
        $.each(pagetitle, function(i,item){
           if(i>0) $(item.remove());
        });
    }
    
    if(pagetitle.length>0  && title_container.length>0)  //&& pagetitle.parent().attr('id')=='main-content'
    {
        //move page title to header - visibility is set in websiteRecord
        title_container.empty();
        pagetitle.detach().appendTo(title_container);
        show_page_title = pagetitle.is(':visible');
    }else{
        //pagetitle.remove();
    }
    
    if($('#main-header').length>0 && $('#main-content-container').length>0){
        title_container.show();
        $('#main-header').height(show_page_title?180:144);
        $('#main-content-container').css({top:show_page_title?190:152});
    }
    
    if(typeof pageid==='undefined') return;
    
    
    //remove old style and custom style per page ===========================
    if(DT_CMS_CSS>0){
        if(previous_page_id>0 && page_cache[previous_page_id] && page_cache[previous_page_id][DT_CMS_CSS]){
            //remove previous
            var style = page_cache[previous_page_id][DT_CMS_CSS];
            //style.innerHTML = ''; 
            document.getElementsByTagName('head')[0].removeChild(style);
        }
    
        if(page_cache[pageid][DT_CMS_CSS])
        {
            
            if(typeof page_cache[pageid][DT_CMS_CSS]==='string'){
                
                var style = document.createElement('style');
                style.type = 'text/css'; 
                style.innerHTML = page_cache[pageid][DT_CMS_CSS];
                page_cache[pageid][DT_CMS_CSS] = style;    
                document.getElementsByTagName('head')[0].appendChild(style);
                
            }else{
                //add style to page        
                document.getElementsByTagName('head')[0].appendChild(page_cache[pageid][DT_CMS_CSS]);            
            }
        }
    }
    previous_page_id = pageid;
    
    
    //execute custom javascript per loaded page =========================
    if(DT_CMS_SCRIPT>0){
        var func_name = 'afterPageLoad'+pageid;
    
        var script_code = page_cache[pageid][DT_CMS_SCRIPT];
        if(script_code && script_code !== false){ //false means it is already inited
        
            //add script to header
            
            var script = document.createElement('script');
            script.type = 'text/javascript';
            script.innerHTML = 'function '+func_name 
            +'(document, pageid){\n'
            //+' console.log("run script for '+pageid+'");\n'
            +'try{\n' + script_code + '\n}catch(e){}}';
            //s.src = "http://somedomain.com/somescript";
            $("head").append(script);
            
        }
        
        if($.isFunction(window[func_name])){  //window[func_name] && 
            //script may have event listener that is triggered on page exit
            //disable it
            $( "#main-content" ).off( "onexitpage");
            //execute the script
            window[func_name]( document, args );
        }
    }

    if(!is_embed){ 
        // add current page as url parameter in browser url

        var spath= location.pathname;
        while (spath.substring(0, 2) === '//') spath = spath.substring(1);
        
        var params = window.hWin.HEURIST4.util.getUrlParams(location.href);
        
        params['db'] = window.hWin.HAPI4.database;
        params['website'] = '';
        params['id'] = home_page_record_id;
        
        /* IJ Oct 2021 - Hide page id in URL, and cause reloads to move back to website homepage */
        if(pageid!=home_page_record_id){
            params['pageid'] = pageid;
        }
        

        s = [];        
        $.each(Object.keys(params),function(i,key){
            var v = encodeURIComponent(params[key]);
            if(v!='') v = '=' + v;
            s.push(key + v);
        });
        s = spath + '?' + s.join('&'); 
        
/* OLD       
        var s = spath + '?db='
                +window.hWin.HAPI4.database+'&website&id='+home_page_record_id;
        if(pageid!=home_page_record_id){
                s = s + '&pageid='+pageid;
        }
*/        
        //http://127.0.0.1/h6-ao/?db=osmak_9b&website&id=7&noticeID=789

        window.history.pushState("object or string", "Title", s);
        
    }
    
    
    //find all link elements for loading another page and define onclick handler - loadPageContent
    $('a').each(function(i,link){
        
        //var href = $(link).attr('data-href');
        //if(!href) 
        var href = $(link).attr('href');
        if (href && href!='#') 
        {
            if(  (href.indexOf(window.hWin.HAPI4.baseURL)===0 || href[0] == '?')
                && window.hWin.HEURIST4.util.getUrlParameter('db',href) == window.hWin.HAPI4.database
                && window.hWin.HEURIST4.util.getUrlParameter('id',href) == home_page_record_id)
            {
                var pageid = window.hWin.HEURIST4.util.getUrlParameter('pageid',href);
                if(pageid>0){
                    
                    $(link).attr('data-pageid', pageid);
                    
                    var scr = 'javascript:{loadPageContent('+pageid+');window.hWin.HEURIST4.util.stopEvent(event);}';
                    $(link).attr('href',scr);
                    //$(link).attr('data-href',scr);
                    
                    /*
                    $(link).on({click:function(e){
                        var pageid = $(e.target).attr('data-pageid');
                        loadPageContent(pageid);            
                        window.hWin.HEURIST4.util.stopEvent(e);
                    }});
                    */
                    
                }
            }
        }
        
    });
    
    //var ele = $('#mobilemenu');
    //_dout('MOBILE '+ele.find('a.extern').length);
    
}

//
// ->initHeaderElements->onPageInit
//
function onHapiInit(success){   
    
    //_dout('webpage hapi inited  '+ (new Date().getTime() / 1000 - _time_debug));
    _time_debug = new Date().getTime() / 1000;
    
    if(!success){    
            window.hWin.HEURIST4.msg.showMsgErr('Cannot initialize system on client side, please consult Heurist developers');
            window.hWin.HEURIST4.msg.sendCoverallToBack();            
            return;
    }
    
    var res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.db_version_req, 
                                                window.hWin.HAPI4.sysinfo.db_version);   
    if(res==-2){ //-2= db_version_req newer
    window.hWin.HEURIST4.msg.showMsgErr('<h3>Old version database</h3>'
+'<p>You are trying to load a website using a more recent version of Heurist than the one used for the database being accessed.</p>'
+'<p>Please ask the owner of the database to open it in the latest version of Heurist which will apply the necessary updates.</p>');
        window.hWin.HEURIST4.msg.sendCoverallToBack();
        return;
    }
    
    function __init_completed(success){
        if(success){
            
    _dout('get defs  '+ (new Date().getTime() / 1000 - _time_debug));
    _time_debug = new Date().getTime() / 1000;

            //substitute values in header
            initHeaderElements();
            onPageInit(success);
            
            if(window.hWin.HAPI4.sysinfo.host_logo && $('#host_info').length>0){
                
                $('<div style="height:40px;padding-left:4px;float:right">'  //background: white;
                    +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                    +'" target="_blank" style="text-decoration:none;color:black;">'
                            +'<label>at: </label>'
                            +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                            +'" height="35" align="center"></a></div>')
                .appendTo( $('#host_info') );
            }
            //setTimeout(function(){window.hWin.HAPI4.EntityMgr.refreshEntityData('rst,trm');},1000);
    
            
<?php             
if(isset($customTemplateNotFound)){
    print 'window.hWin.HEURIST4.msg.showMsgDlg("Custom website template '
        .$customTemplateNotFound.' not found. Default template will be used");';
}?>
            
        }
    }

    //window.hWin.HAPI4.EntityMgr.refreshEntityData('rty,dty,rtg,dtg,vcg', __init_completed);
    window.hWin.HAPI4.EntityMgr.refreshEntityData('all', __init_completed);

}


//
//substitute values in header
//
function initHeaderElements(){   
/*
$image_logo  -> #main-logo
$image_altlogo -> #main-logo-alt
$website_title -> #main-title>h2
$title_alt -> #main-title-alt 
$title_alt2 -> #main-title-alt2 
*/
  //main logo image
  if($('#main-logo').length>0){
            $('#main-logo').empty();
            $('<a href="#" style="text-decoration:none;"><?php print $image_logo;?></a>')
            .appendTo($('#main-logo'));
  }
  
  if($('#main-logo-alt').length>0){
  <?php if($image_altlogo){ ?>
      var ele = $('#main-logo-alt').css({'background-size':'contain',
                'background-position': 'top',
                'background-repeat': 'no-repeat',
                'background-image':'url(\'<?php print $image_altlogo;?>\')'}).show();
  <?php if($image_altlogo_url){ ?>
        ele.css('cursor','pointer').on({click:function(){window.open("<?php print $image_altlogo_url;?>",'_blank')}});
  <?php }}else{ ?>
      $('#main-logo-alt').hide();
  <?php } ?>
  }
  if($('#main-title-alt').length>0){
      $('#main-title-alt').html('<?php print str_replace("'",'&#039;', $title_alt);?>');
  }
  if($('#main-title-alt2').length>0){
      $('#main-title-alt2').html('<?php print str_replace("'",'&#039;', $title_alt2);?>');
  }
  
  <?php if($website_title){  ?>
  
  var ele = $('#main-title');
  if(ele.length>0){
      ele.empty().hide();
  <?php       
  print '$(\'<h2 '.($image_banner?' style="text-shadow: 3px 3px 5px black"':'').'>'
        . str_replace("'",'&#039;',strip_tags($website_title,'<i><b><u><em><strong><sup><sub><small><br>'))
        .'</h2>\').appendTo(ele);';
  ?>
      if(ele.parent().is('#main-header'))
      {
          if(!$('#main-logo-alt').is(':visible')){
                ele.css({right:10}); 
          }
          setTimeout(function(){ ele.css({left:$('#main-logo').width()+10 });ele.fadeIn(500); },2000);
      }
      
      
  }
  <?php } ?>

  $('.header-element').css({'border':'none'});
  
} //initHeaderElements

//
// load popup with simple math problem, success leads to the creation of the report email
//
function performCaptcha(){

    var rand1 = Math.floor(Math.random() * 9) + 1; 
    var rand2 = Math.floor(Math.random() * 9) + 1; 
    var res = rand1 + rand2 + 1;

    window.hWin.HEURIST4.msg.showPrompt(rand1 +" + "+ rand2 +" + 1 = <input id=\'dlg-prompt-value\' class=\'text ui-corner-all\'"
        +" style=\'max-width: 250px; min-width: 10em; width: 250px; margin-left:0.2em\' autocomplete=\'off\'/>",
        function(val){
            if(res != val){ 
                
                window.hWin.HEURIST4.msg.showMsgFlash("Report Failed, Incorrect Answer", 2500); 
            }else{

                window.hWin.HEURIST4.msg.showMsgFlash("Preparing Email", 2500);

                var url = window.location.href;
                url = url.replace(/&/g, '%26');

                var subject = "Heurist Website Content report for DB: " + window.hWin.HAPI4.database;
                var body = "Content reported: " + url;
                
                var link = encodeURI("mailto:support@heuristnetwork.org?subject=" + subject + "&body=" + body);

                window.open(link, '_blank');
            }
        }, {title: "Captcha Test", yes: "Proceed", no: "Cancel"});
}


//
//  open/hides side panel with NEW CMS editor controls  (see link #btnOpenCMSeditor in cmsTemplate.php)
//
function _openCMSeditor(event){
    
    if(!_IS_NEW_CMS_EDITOR) return;
    
    var btn = $(event.target);
    
    if(isCMS_active){
        //close
        isCMS_active = false;
        editCMS_instance2.closeCMS();
        btn.show();
    }else{
        isCMS_active = true;
        if(!editCMS_instance2) editCMS_instance2 = editCMS2();
        editCMS_instance2.startCMS({record_id: current_page_id, 
                                    //content: page_cache[current_page_id],  //html or json
                                    container:'#main-content',
                                    close: function(){
                                        isCMS_active = false;
                                        btn.show();
                                    }}); //see editCMS2.js    
        btn.hide();
    }
    
    //$(event.target).html(isCMS_active?'Close CMS':'CMS');
}



var gtag = null;//google log - DO NOT REMOVE

//
//init hapi    
//
$(document).ready(function() {
    
        var ele = $('body').find('#main-content');
        window.hWin.HEURIST4.msg.bringCoverallToFront(ele);
        ele.show();
    
        $('body').find('#main-menu').hide(); //will be visible after menu init
                
/*          
        if(is_show_pagetitle){
            $('body').find('#main-pagetitle').show();
        }else{
            $('body').find('#main-pagetitle').hide();
        }

_dout('webpage doc ready ');
*/
//+(window.hWin.HAPI4)+'    '+(new Date().getTime() / 1000 - _time_debug));
        _time_debug = new Date().getTime() / 1000;
    
        // Standalone check
        if(!window.hWin.HAPI4){
            window.hWin.HAPI4 = new hAPI('<?php echo $_REQUEST['db']?>', onHapiInit<?php print (array_key_exists('embed', $_REQUEST)?",'".PDIR."'":'');?>);
        }else{
            // Not standalone, use HAPI from parent window
            initHeaderElements();
            onPageInit( true );
        }
});    
</script>    

<style>
<?php
if(!$edit_OldEditor){
?>
div.coverall-div {
    background-position: top;     
    background-color: white;
    opacity: 1;
}

div.CodeMirror{
    height:100%;
}
.CodeMirror *{
    /* font-family: Courier, Monospace !important; */
    font-family: Arial, sans-serif !important;
    font-size: 14px;
}
.CodeMirror div.CodeMirror-cursor {
    visibility: visible;
}

<?php        
}
//style from field DT_CMS_CSS of home record 
if($site_css!=null){
    print $site_css;
}
?>          
</style>  
<?php
    
//generate main menu on server side - for bootstrap menu     
$mainmenu_content = null;

$ids_was_added = array();
$resids = array();
$records = recordSearchMenuItems($system, array($home_page_on_init), $resids, true, true);
if(is_array($records) && !@$records['status']){
$mainmenu_content = _getMenuContent(0, array($home_page_on_init), 0);     
$mainmenu_content = '<ul>'.$mainmenu_content.'</ul>';
}

function _getFld($record,$dty_ID){
    $res = @$record['details'][$dty_ID];
    return (is_array($res)&&count($res)>0)?array_shift($res):null;
}

function _getMenuContent($parent_id, $menuitems, $lvl){
   global $system, $records, $ids_was_added, $home_page_on_init;         
   
            $res = '';
            $resitems = array();
            
            $fields = array(DT_NAME,DT_SHORT_SUMMARY,DT_CMS_TARGET, //DT_CMS_CSS,
            DT_CMS_PAGETITLE,DT_EXTENDED_DESCRIPTION,DT_CMS_TOP_MENU,DT_CMS_MENU );
        
            foreach($menuitems as $page_id){
                
                if(in_array($page_id, $ids_was_added)){
                    //already was included - recursion
                    //ids_recurred.push(menuitems[i]);
                }else{
                
                    $record = recordSearchByID($system,$page_id,$fields,'rec_ID,rec_RecTypeID');
                
                    $menuName = _getFld($record, DT_NAME);
                    $menuTitle = _getFld($record, DT_SHORT_SUMMARY);
                    $recType = $record['rec_RecTypeID'];
                    
                    //target and position
                    $pageTarget = _getFld($record,DT_CMS_TARGET);
                    //$pageStyle = _getFld($record,DT_CMS_CSS);
                    $showTitle = _getFld($record,DT_CMS_PAGETITLE); 
                    
                    $showTitle = true; //($showTitle!==TERM_NO && $showTitle!==TERM_NO_old);
                    
                    $hasContent = (_getFld($record,DT_EXTENDED_DESCRIPTION)!=null);
                    
                    array_push($ids_was_added, $page_id);
                        
                    //$url = '?db='.HEURIST_DBNAME.'&id='.$home_page_on_init.'&pageid='.$page_id;
                    $url = 'javascript:{loadPageContent('.$page_id.');window.hWin.HEURIST4.util.stopEvent(event);}';
                        
                    $res = $res.'<li><a href="'.$url.'" data-pageid="'.$page_id.'"'
                                        .($pageTarget?' data-target="'.$pageTarget.'"':'')
                                        .($showTitle?' data-showtitle="1"':'')
                                        .($hasContent?' data-hascontent="1"':'')
                                        .' title="'.htmlspecialchars($menuTitle).'">'
                                        .htmlspecialchars($menuName).'</a>';
                        
                    $subres = '';
                    $submenu = @$record['details'][DT_CMS_MENU];
                    if(!$submenu){
                        $submenu = @$record['details'][DT_CMS_TOP_MENU];
                    }
                    //has submenu
                    if($submenu){
                        //if(!is_array($submenu)) $submenu = explode(',',$submenu);
                        
                        if(count($submenu)>0){ 
                            
                            $subrec = array();
                            foreach($submenu as $id=>$rec){
                                $subrec[] = $rec['id'];
                            }

                            //next level                         
                            $subres = _getMenuContent($page_id, $subrec, $lvl+1);
                            
                            if($subres!='') {
                                $res = $res.'<ul>'.$subres.'</ul>'; //'.($lvl==0?' class="level-1"':'').'
                            }
                        }
                    }
                    
                    $res = $res.'</li>';
                    
                    if($lvl==0 && count($menuitems)==1){ // && that.options.use_next_level
                            return $subres;    
                    }
                    
                
                }
            }//for
            
            return $res;
}//_getMenuContent   

?>