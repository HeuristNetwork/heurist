<?php
/**
 * index.php - Main entry point for the Heurist application.
 *
 * @fileOverview This script initializes the Heurist layout, handles various URL parameters for actions like
 * displaying records, CMS content, API requests, file downloads, and asset loading.
 * It performs initial setup and can trigger an initial search if query parameters are defined.
 * Workflow:
 * Apache
 *   ↓
 * root/index.php
 *   ↓
 * RequestRouter::route()
 *   ↓
 * (if REDIRECT → done)
 * (if INTERNAL → try RecordResolver + FileResolver)
 *   ↓
 * require <version>/index.php 
 *
 * In this design:
 *
 * RecordResolver returns redirect URLs
 *
 * FileResolver returns redirect URLs
 *
 * root/index.php executes the redirect
 *
 * <version>/index.php handles only UI + FrontController
 * 
 * 
 * @project     Heurist academic knowledge management system
 * @package Core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
use hserv\utilities\USystem;
use hserv\utilities\USanitize;
use hserv\controller\FrontController;
use hserv\controller\RecordResolver;
use hserv\controller\FileResolver;
use hserv\controller\RequestRouter;


require_once dirname(__FILE__).'/autoload.php';

$version = '';
// Base path for assets/scripts. Even for versionless pretty URLs, assets live under /<version>/.
// (websiteRecord.php and many legacy scripts expect PDIR to be set early.)
if (!array_key_exists('embed', $_REQUEST)) {

    $defaultVersion = 'heurist'; // change if needed

    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $reqPath = preg_replace('~/index\.php$~i', '/', $reqPath);
    $reqPath = preg_replace('~//+~', '/', $reqPath);

    if (preg_match('~^/(heurist|h7-alpha|h7-[A-Za-z0-9_-]+)(/|$)~', $reqPath, $m)) {
        $version = $m[1];
    } else {
        // For versionless pretty URLs (eg /db/web/...), choose your public default.
        // If your public default is "heurist", set it to "heurist" here.
        $version = $defaultVersion;
    }
    if(!defined('PDIR')){
        define('PDIR', '/' . $version . '/');    
    }
}

// ------------------------------------------------------------------
// Fallback routing when this version/index.php is hit directly
// (i.e. request did NOT go through root /index.php).
// This only injects DB/website from domainWebsites.json and DBREF mappings.
// ------------------------------------------------------------------
if ( empty($GLOBALS['HEURIST_ROUTED_VIA_ROOT']) ) {

    $opts = [
        'default_version' => $version,
        'mapping_file' => dirname(__DIR__) . '/domainWebsites.json',
        'allow_canonical_redirects' => false,  // IMPORTANT: no redirects here, just detect params
    ];

    $det = RequestRouter::detectDb($_SERVER, $opts);

    if (!empty($det['db'])) {
        $_REQUEST['db'] = $det['db'];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $_GET['db'] = $det['db'];
        }
        $GLOBALS['HEURIST_ROUTE_PARAMS']['db'] = $det['db'];
    }
}


$isLocalHost = isLocalHost();

//validate that instance is ok and database is accessible
if( @$_REQUEST['isalive']==1){

    $system = new hserv\System();
    $is_inited = $system->init(@$_REQUEST['db'], true, false);
    if($is_inited){
        $mysqli = $system->getMysqli();
        $mysqli->close();
        print 'ok';
    }else{
        $error = $system->getError();
        print 'error: '.@$error['message'];
    }
    exit;

}elseif( array_key_exists('website', $_REQUEST) || array_key_exists('embed', $_REQUEST)
    || (array_key_exists('field', $_REQUEST) && $_REQUEST['field']>0) ){
    // CMS WEBSITE MODE

    $controller = new FrontController();
    if(!$controller->isInited()){
        exit;
    }
    
    if(array_key_exists('ver', $_REQUEST)){
        $websiteVersion = $_REQUEST['ver'];
    }else{
        //auto detect version of website
        $websiteVersion = $controller->getWebsiteVersion();
        if($websiteVersion==3 && @$_REQUEST['edit']){
            $_REQUEST['edit'] = 'start';
        }
    }

    if($websiteVersion==3){
        if(@$_REQUEST['edit']=='start'){
            unset($_REQUEST['edit']);
            include_once dirname(__FILE__).'/hclient/widgets/cms/WebSiteEditor.php';
        }else{
            $controller->run();
        }
    }else{
        //embed - when heurist is run on page on non-heurist server
        if(array_key_exists('embed', $_REQUEST)){
            define('PDIR', HEURIST_INDEX_BASE_URL);
        }
        include_once dirname(__FILE__).'/hclient/widgets/cms/websiteRecord.php';
    }
    
    exit;

    return;


}elseif (@$_REQUEST['ent']){

    //to avoid "Open Redirect" security warning
    parse_str($_SERVER['QUERY_STRING'], $vars);
    $query_string = http_build_query($vars);

    redirectURL('hserv/controller/api.php?'.$query_string);
    return;
    
}elseif (@$_REQUEST['controller']=='ReportController' || array_key_exists('template',$_REQUEST) || array_key_exists('template_id',$_REQUEST)
        || @$_REQUEST['controller']=='ImportAnnotations'){

    //execute smarty template,  $_REQUEST may be composed in resolver.php
    $controller = new FrontController($_REQUEST);
    $controller->run();
    exit;

}elseif ($r = RecordResolver::resolve($version, $_REQUEST)) {
    header('Location: ' . $r['url'], true, (int)($r['status'] ?? 302));
    exit;
//  use FileResolver and remark array_key_exists('file',$_REQUEST)
}elseif ($r = FileResolver::resolve($version, $_REQUEST)) {
    
    $status = (int)($r['status']??302);
    if(empty($r['url']) || $status===404){
        http_response_code(404);
        $err = __DIR__ . '/../errors/404.html';
        if (is_file($err)) readfile($err); else echo '404 Not Found';
    }else{
        header('Location: ' . $r['url'], true, $status);
    }
    exit;

}elseif (@$_REQUEST['logo']){

    list($host_logo, $host_url, $mime_type) = USystem::getHostLogoAndUrl(false);

    if($host_logo!=null && file_exists($host_logo)){
        header('Content-type: image/'.$mime_type);
        readfile($host_logo);
        return;
    }
}elseif(@$_REQUEST['disclaimer']){
    // disclaimers are stored in either parent/root directory or movetoparent (backup)

    $params = USanitize::sanitizeInputArray();

    $name = $_REQUEST['disclaimer'];
    
    if($name=='association_membership.html'){
        $path = 'admin/verification/';
    }elseif ($name=='terms_and_conditions.html'){
        /*xtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if(empty($extension)){
            $name .= '.html';
        }*/
        $path = 'movetoparent/';
    }else{
        exit('Requested document is not allowed: ' . htmlspecialchars($name));
    }
    
    $file = $path.basename($name);

    if(file_exists($file)){
        header("Location: {$file}");
        return;
    }else{
        exit('Document not found: ' . htmlspecialchars($name));
    }
}




define('IS_INDEX_PAGE',true);
if(!defined('PDIR')) {define('PDIR','');}

require_once dirname(__FILE__).'/hclient/framecontent/initPage.php';

?>

<!-- it is needed in preference dialog -->
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.layout.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.ui-contextmenu.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/ui.tabs.paging.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.js" charset="utf-8"></script>
<link href="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.css" rel="stylesheet" type="text/css">


<!-- script type="text/javascript" src="ext/js/moment.min.js"></script
<script type="text/javascript" src="ext/js/date.format.js"></script>
-->

<!-- array of possible layouts -->
<script type="text/javascript" src="<?php echo PDIR;?>layout_default.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/baseAction.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/baseConfig.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/database/dbAction.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAction.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAccess.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAdd.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAddLink.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordExportCSV.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordTemplate.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/report/reportViewer.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/report/reportEditor.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/recordListExt.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search_faceted.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search_faceted_wiz.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/app_timemap.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchByEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilder.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilderItem.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchBuilderSort.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/ActionHandler.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/controlPanel.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/buttonsMenu.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/slidersMenu.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/navigation.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/svsEdit.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/svs_list.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListMenu.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListCollection.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListDataTable.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/staticPage.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/connections.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profileLogin.js"></script>

<!-- edit entity -->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectFile.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectMultiValues.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectFolders.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editTheme.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/CmsManager.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/configEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefGroups.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/mediaViewer.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysDashboard.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysDashboard.js"></script>

<!-- autoload
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecStructure.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefDetailTypes.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefDetailTypes.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecTypes.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefRecTypes.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecTypeGroups.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefTerms.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefVocabularyGroups.js"></script>
-->

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/admin/importStructure.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapPublish.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/publishDialog.js"></script>

<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.css" />
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.js"></script>

<!-- loaded dynamically in editing.js
<script type="text/javascript" src="<?php echo PDIR;?>external/tinymce5/tinymce.min.js"></script>
-->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editorCodeMirror.js"></script>
<link rel="stylesheet" href="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.css">

<!-- os, browser detector -->
<script type="text/javascript" src="<?php echo PDIR;?>external/js/platform.js"></script>

<?php
if($isLocalHost){
    ?>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/js/datatable/datatables.min.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/datatable/datatables.min.js"></script>
    <?php
}else{
    ?>
    <link href="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-html5-3.1.2/datatables.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" integrity="sha384-VFQrHzqBh5qiJIU0uGU5CIW3+OWpdGGJM9LBnGbuIH2mkICcFZ7lPd/AAtI7SNf7" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" integrity="sha384-/RlQG9uf0M2vcTw3CX7fbqgbj/h8wKxw7C3zu9/GxcBPRKOEcESxaxufwRXqzq6n" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-html5-3.1.2/datatables.min.js" integrity="sha384-naBmfwninIkPENReA9wreX7eukcSAc9xLJ8Kov28yBxFr8U5dzgoed1DHwFAef4y" crossorigin="anonymous"></script>
     
    <?php
}
?>

<script src="<?php echo PDIR;?>hclient/widgets/repository/repositoryConfig.js"></script>


<!-- Driver.js
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@0.9.8/dist/driver.min.css">
<script src="https://cdn.jsdelivr.net/npm/driver.js@0.9.8/dist/driver.min.js"></script>
-->

<!-- Intro.js JS -->
<script src="https://cdn.jsdelivr.net/npm/intro.js@8.3.2/intro.min.js"></script>
<link href=" https://cdn.jsdelivr.net/npm/intro.js@8.3.2/minified/introjs.min.css " rel="stylesheet">

<script type="text/javascript">

    /**
     * Initializes the page after basic setup is complete.
     * Sets up layout manager, closes 'about' dialog if open, sets system info,
     * and initializes the main application layout and Matomo tracking.
     * @param {boolean} success - Indicates if the initial PHP setup was successful.
     * @returns {void}
     */
    function onPageInit(success){

        if(!success) {return;}

        $(document).on('focusin', function(e) {
            if ($(e.target).closest(".mce-window, .moxman-window").length) {
                e.stopImmediatePropagation();
            }
        });

<?php
/*
if(@$_SERVER['REQUEST_METHOD']=='POST'){
    $req_params = filter_input_array(INPUT_POST);
    print 'window.hWin.HAPI4.postparams='.json_encode($req_params).';';
    print 'console.log(window.hWin.HAPI4.postparams)';
}
*/
?>

        //
        // cfg_widgets and cfg_layouts are defined in layout_default.js
        //
        window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, cfg_layouts);


        if($( "#heurist-about" ).dialog('instance')){
            $( "#heurist-about" ).dialog("close");
        }


        <?php
        //returns total records in db and counts of active entries in dashboard
        list($db_total_records, $db_has_active_dashboard, $db_workset_count) = $system->getTotalRecordsAndDashboard();
        echo 'window.hWin.HAPI4.sysinfo.db_total_records = '.$db_total_records.';';
        echo 'window.hWin.HAPI4.sysinfo.db_has_active_dashboard = '.$db_has_active_dashboard.';';
        echo 'window.hWin.HAPI4.sysinfo.db_workset_count = '.$db_workset_count.';';
        ?>

        var lt = window.hWin.HAPI4.sysinfo['layout'];
        window.hWin.HAPI4.is_publish_mode = (lt=='WebSearch'); //deprecated

        //
        // init layout
        //
        window.hWin.HAPI4.LayoutMgr.appInitAll( window.hWin.HAPI4.sysinfo['layout'], "#layout_panes");

        //2024-12-08 const layout_cfg = window.hWin.HAPI4.LayoutMgr2.layoutGetById('H6Default2');
        //2024-12-08 window.hWin.HAPI4.LayoutMgr2.layoutInit(layout_cfg, "#layout_panes" );

        window.hWin.HAPI4.SystemMgr.matomoTrackInit('adm');
        
        onInitCompleted_PerformSearch();
    }

    /**
     * Initializes the "About Heurist" dialog.
     * This dialog displays version and copyright information.
     * @returns {void}
     */
    function onAboutInit(){
        //definition of ABOUT dialog, called from Help > About, see content below
        $( "#heurist-about" ).dialog(
            {
                autoOpen: true,
                height: 180,
                width: 450,
                modal: true,
                resizable: false,
                draggable: false,
                create:function(){
                    $(this).parent().find('.ui-dialog-titlebar').addClass('fullmode').hide();
                }
                /*hide: {
                effect: "puff",
                duration: 500
                }*/
            }
        );

    }

    /**
     * Performs initial actions after the main system components are initialized.
     * This includes version checks, handling record editing/creation requests via URL parameters,
     * displaying dashboards, and platform-specific warnings (e.g., for mobile or unsupported browsers).
     * @returns {void}
     */
    function onInitCompleted_PerformSearch(){

        if(!window.hWin.HAPI4.is_publish_mode)
        {

            if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                //version is old
                return;
            }
            
            if('nonmember'==window.hWin.HAPI4.sysinfo['associationMembershipStatus'] 
            || 'viaowner'==window.hWin.HAPI4.sysinfo['associationMembershipStatus']){

                const lastcheck = window.hWin.HAPI4.get_prefs('association_teaser_last_shown');
                const currdate =  new Date().toISOString().slice(0, 10);
                if(lastcheck!=currdate){
                
                    window.hWin.HAPI4.save_pref('association_teaser_last_shown',  currdate);
                
                    window.hWin.HEURIST4.msg.showMsgDlgUrl(
                              `${window.hWin.HAPI4.baseURL}?disclaimer=association_membership.html #content`,
                               null, 'Heurist Network Association', 
                               {enable_buttons_after:2000, closeOnEscape:false, noClose:true,
                               container: 'dlg-association-teaser', open: (event, ui) => { $(event.target).css({height: '41em', padding: '2em 2em 0em'}); }});
                }
            }
                

            var editRecID = window.hWin.HEURIST4.util.getUrlParameter('edit_id', window.location.search);
            if(editRecID>0){
                //edit record
                window.hWin.HEURIST4.ui.openRecordEdit(editRecID, null);
            }else
                if(window.hWin.HEURIST4.util.getUrlParameter('rec_rectype', window.location.search) ||
                    (window.hWin.HEURIST4.util.getUrlParameter('t', window.location.search) &&
                        window.hWin.HEURIST4.util.getUrlParameter('u', window.location.search)))
                {
                    //add new record from bookmarklet  - see recordEdit.php as alternative, it opens record editor in separate window
                    var url = window.hWin.HEURIST4.util.getUrlParameter('u', window.location.search);

                    var new_record_params = {
                        RecTypeID: window.hWin.HEURIST4.util.getUrlParameter('rec_rectype', window.location.search)
                        || window.hWin.HEURIST4.util.getUrlParameter('rt', window.location.search),
                        OwnerUGrpID: window.hWin.HEURIST4.util.getUrlParameter('rec_owner', window.location.search),
                        NonOwnerVisibility: window.hWin.HEURIST4.util.getUrlParameter('rec_visibility', window.location.search),
                        tag: window.hWin.HEURIST4.util.getUrlParameter('tag', window.location.search)
                        ||window.hWin.HEURIST4.util.getUrlParameter('k', window.location.search),
                        Title:  window.hWin.HEURIST4.util.getUrlParameter('t', window.location.search),
                        URL:  url,
                        ScratchPad:  window.hWin.HEURIST4.util.getUrlParameter('d', window.location.search)
                    };

                    //default rectype for bookmarklet addition
                    if(url && !(new_record_params['RecTypeID']>0)){

                        if(window.hWin.HAPI4.sysinfo['dbconst']['RT_INTERNET_BOOKMARK']>0) {
                            new_record_params['RecTypeID']  = window.hWin.HAPI4.sysinfo['dbconst']['RT_INTERNET_BOOKMARK'];
                        }else if(window.hWin.HAPI4.sysinfo['dbconst']['RT_NOTE']>0) {
                            new_record_params['RecTypeID']  = window.hWin.HAPI4.sysinfo['dbconst']['RT_NOTE'];
                        }
                    }

                    //add new record
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:new_record_params});

                }else if(window.hWin.HAPI4.sysinfo.db_has_active_dashboard>0) {
                    /*
                    var _supress_dashboard = (window.hWin.HEURIST4.util.getUrlParameter('cms', window.hWin.location.search)>0);
                    if(_supress_dashboard!==true){
                    //show dashboard (another place - _performInitialSearch in controlPanel)
                    var prefs = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_on_startup:1, show_as_ribbon:1});
                    if(prefs.show_on_startup==1 && prefs.show_as_ribbon!=1)
                    {
                    var _keep = window.hWin.HAPI4.sysinfo.db_has_active_dashboard;
                    window.hWin.HAPI4.sysinfo.db_has_active_dashboard=0;
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);//hide button

                    window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard',
                    {onClose:function(){
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
                    }});
                    setTimeout(function(){window.hWin.HAPI4.sysinfo.db_has_active_dashboard = _keep;},1000);
                    }
                    }
                    */
                }

            $('body').css({'overflow':'hidden'});

        }

        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);

        var os = platform?platform.os.family.toLowerCase():'';
        if(os.indexOf('android')>=0 || os.indexOf('ios')>=0){ //test || os.indexOf('win')>=0
            window.hWin.HEURIST4.msg.showElementAsDialog(
                {element:document.getElementById('heurist-platform-warning'),
                    width:480, height:220,
                    title: 'Welcome',
                    buttons:{'Close':function(){ $(this).dialog( 'close' )} } });
        }else if (window.hWin.HEURIST4.util.isIE() ) {
            window.hWin.HEURIST4.msg.showMsgDlg('Heurist is not fully supported in Internet Explorer. Please use Chrome, Firefox or Edge.');
        }else if (platform.description.toLowerCase().indexOf('safari')>=0){
            window.hWin.HEURIST4.msg.showElementAsDialog(
                {element:document.getElementById('heurist-safari-warning'),
                    width:480, height:260,
                    title: 'Safari browser support',
                    buttons:{'Close':function(){ $(this).dialog( 'close' )} } });
        }

    } //onInitCompleted_PerformSearch
    
</script>
<?php
    USystem::insertLogScript();
?>     
</head>
<body style="background-color:#c9c9c9;">

    <div id="layout_panes">
        &nbsp;
    </div>

    <div id="heurist-about" style="width:300px;display:none;">
        <div class='logo'></div>
        <h4>Heurist Academic Knowledge Management System</h4>
        <p style="margin-top:1em;">version <?=HEURIST_VERSION?></p>
        <p style="margin-top: 1em;">Copyright (C) 2005-2023 University of Sydney, (C) 2024 - <a href="https://HeuristNetwork.org" style="outline:none;" target="_blank" rel="noopener">Heurist Network Association</a></p>
    </div>

    <div id="heurist-platform-warning" style="display:none;">
        <p style="padding:10px">Heurist is designed primarily for use with a keyboard and mouse. Tablets are not fully supported at this time, except for data collection on Android (see FAIMS in the Help system).</p>

        <p style="padding:10px">Please <?php echo CONTACT_HEURIST_TEAM;?> for further information or to express an interest in a tablet version</p>
    </div>

    <div id="heurist-safari-warning" style="display:none;">
        <p style="padding:10px">
            Heurist is not fully supported in Safari.
            Sorry, we no longer support Apple's Safari browser which was discontinued on Windows over a decade ago due to the appearance of widely used free cross-platform browsers such as Chrome and Firefox.
        </p>

        <p style="padding:10px">
            Please download Chrome or Firefox to use with Heurist (and perhaps with your other applications).
        </p>
    </div>

    <div id="heurist-dialog">
    </div>

</body>
</html>