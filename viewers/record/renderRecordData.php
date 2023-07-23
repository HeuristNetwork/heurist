<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* UI for viewing record
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/View
*/  
require_once(dirname(__FILE__)."/../../hsapi/System.php");
require_once(dirname(__FILE__).'/../../hsapi/utilities/Temporal.php');

$system = new System();
$inverses = null;

if(!$system->init(@$_REQUEST['db'])){
    include dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';
    exit();
}

//require_once(dirname(__FILE__).'/../../records/woot/woot.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_files.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_recsearch.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_users.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_rel_details_temp.php');

define('ALLOWED_TAGS', '<i><b><u><em><strong><sup><sub><small><br>'); //for record title see output_chunker for other fields
//'<a><u><i><em><b><strong><sup><sub><small><br><h1><h2><h3><h4><p><ul><li><img>'

$noclutter = array_key_exists('noclutter', $_REQUEST); //NOT USED
$is_map_popup = array_key_exists('mapPopup', $_REQUEST) && ($_REQUEST['mapPopup']==1);
$without_header = array_key_exists('noheader', $_REQUEST) && ($_REQUEST['noheader']==1);
$layout_name = @$_REQUEST['ll'];
$is_production = !$is_map_popup && $layout_name=='WebSearch';

$is_reloadPopup = array_key_exists('reloadPopup', $_REQUEST) && ($_REQUEST['reloadPopup']==1);

$hide_images = array_key_exists('hideImages', $_REQUEST) ? $_REQUEST['hideImages'] : 0; // 1 - No linked media, 2 - No images

$rectypesStructure = dbs_GetRectypeStructures($system); //getAllRectypeStructures(); //get all rectype names

$defTerms = dbs_GetTerms($system);
$defTerms = new DbsTerms($system, $defTerms);


$ACCESSABLE_OWNER_IDS = $system->get_user_group_ids();  //all groups current user is a member
if(!is_array($ACCESSABLE_OWNER_IDS)) $ACCESSABLE_OWNER_IDS = array();
array_push($ACCESSABLE_OWNER_IDS, 0);


$relRT = ($system->defineConstant('RT_RELATION')?RT_RELATION:0);
$relSrcDT = ($system->defineConstant('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
$relTrgDT = ($system->defineConstant('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);
$relTypDT = ($system->defineConstant('DT_RELATION_TYPE') ? DT_RELATION_TYPE : 0);
$intrpDT = ($system->defineConstant('DT_INTERPRETATION_REFERENCE') ? DT_INTERPRETATION_REFERENCE : 0);
$notesDT = ($system->defineConstant('DT_SHORT_SUMMARY') ? DT_SHORT_SUMMARY : 0);
$startDT = ($system->defineConstant('DT_START_DATE') ? DT_START_DATE : 0);
$endDT = ($system->defineConstant('DT_END_DATE') ? DT_END_DATE : 0);
$titleDT = ($system->defineConstant('DT_NAME') ? DT_NAME : 0);
$system->defineConstant('DT_GEO_OBJECT');
$system->defineConstant('DT_PARENT_ENTITY');
$system->defineConstant('DT_DATE');
$system->defineConstant('DT_WORKFLOW_STAGE');

$system->defineConstant('RT_CMS_MENU');
$system->defineConstant('RT_CMS_HOME');
$system->defineConstant('DT_EXTENDED_DESCRIPTION');
$system->defineConstant('DT_CMS_HEADER');
$system->defineConstant('DT_CMS_FOOTER');

$rec_id = intval(@$_REQUEST['recID']);

$already_linked_ids = array();
$group_details = array();

// if we get a record id then see if there is a personal bookmark for it.
if ($rec_id>0 && !@$_REQUEST['bkmk_id']) 
{
    $bkm_ID = mysql__select_value($system->get_mysqli(),
                    'select bkm_ID from usrBookmarks where bkm_recID = '
                        .$rec_id.' and bkm_UGrpID = '.$system->get_user_id());
}else{
    $bkm_ID = intval(@$_REQUEST['bkmk_id']);    
}
$sel_ids = array();
if(@$_REQUEST['ids']){
	$sel_ids = explode(',',$_REQUEST['ids']);
    $sel_ids = array_unique($sel_ids);
}
if(!($is_map_popup || $without_header)){
?>
<html>
    <head>
        <title>HEURIST - View record</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
    
        <script type="text/javascript" src="../../external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="../../external/jquery-ui-1.12.1/jquery-ui.js"></script>
        <link rel="stylesheet" type="text/css" href="../../external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
        <link rel="stylesheet" type="text/css" href="<?php echo HEURIST_BASE_URL;?>h4styles.css">

        <script type="text/javascript" src="../../hclient/core/hintDiv.js"></script> <!-- for mapviewer roolover -->
        <script type="text/javascript" src="../../hclient/core/detectHeurist.js"></script>
        
        <link rel="stylesheet" type="text/css" href="../../external/jquery.fancybox/jquery.fancybox.css" />

        <script type="text/javascript" src="../../hclient/widgets/viewers/mediaViewer.js"></script>

        <script type="text/javascript">
        
            var rec_Files = [];
            var rec_Files_IIIF_and_3D = [];
            var baseURL = '<?php echo HEURIST_BASE_URL;?>';                
            var database = '<?php echo HEURIST_DBNAME;?>';                
        
            function zoomInOut(obj,thumb,url) {
                var thumb = thumb;
                var url = url;
                var currentImg = obj;
            
                if (currentImg.parentNode.className != "fullSize"){
                    $(currentImg).hide();                    
                    currentImg.src = url;
                    currentImg.onload=function(){
                        $(currentImg).fadeIn(500);
                    }                    
                    currentImg.parentNode.className = "fullSize";
                    currentImg.parentNode.parentNode.style.width = '100%';

                }else{
                    currentImg.src = thumb;
                    currentImg.parentNode.className = "thumb_image";
                    currentImg.parentNode.parentNode.style.width = 'auto';
                    //currentImg.parentNode.parentNode.style.float = 'right';
                }
            }
            
            //
            //
            //
            function printLTime(sdate,ele){
                var date = new Date(sdate+"+00:00");
                ele = document.getElementById(ele)
                ele.innerHTML = (''+date.getHours()).padStart(2, "0")
                        +':'+(''+date.getMinutes()).padStart(2, "0")
                        +':'+(''+date.getSeconds()).padStart(2, "0");
            }

            function start_roll_open() {
                window.roll_open_id = setInterval(roll_open, 100);
            }

            function roll_open() {
                var wfe = window.frameElement;
                if (! wfe) return;
                var current_height = parseInt(wfe.style.height);

                var final_height = document.getElementById('bottom').offsetTop + 2;

                if (final_height > current_height + 30) {
                    // setTimeout(roll_open, 100);

                    // linear
                    //wfe.style.height = (current_height + 20) + 'px';

                    wfe.style.height = current_height + Math.round(0.5*(final_height-current_height)) + 'px';
                } else {
                    wfe.style.height = final_height + 'px';

                    clearInterval(window.roll_open_id);
                }
            }

            //
            // for edit link
            //
            function sane_link_opener(link) {
                if (window.frameElement  &&  window.frameElement.name == 'viewer') {
                    top.location.href = link.href;
                    return false;
                }
            }
            
            //
            //
            //
            function no_access_message(ele){                        
                var sMsg = 'Sorry, your group membership does not allow you to view the content of this record'
                if(window.hWin && window.hWin.HEURIST4){
                    //,null,ele position not work properly 1) long message 2) within iframe
                    window.hWin.HEURIST4.msg.showMsgFlash(sMsg,1000);                        
                }else{
                    alert(sMsg);
                }
                return false;
            }
            
            //
            // not used
            //
            function show_record(event, rec_id) 
            {
                $('div[data-recid]').hide();$('div[data-recid='+rec_id+']').show(); 
                return false
            }
            //
            // catch click on a href and opens it in popup dialog for ADMIN UI
            //
            function link_open(link) {
                <?php if($is_reloadPopup){ ?>
                    this.document.location.href = link.href+'&reloadPopup=1';
                    return false;
                <?php 
                }else{
                ?>    
                if(window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.msg){
                    try{
                       window.hWin.HEURIST4.msg.showDialog(link.href, { title:'.', width: 600, height: 500, modal:false });
                       return false;
                    }catch(e){
                       return true; 
                    }
                }else{
                    return true; 
                }
                <?php
                } 
                ?>
            }
            
            //
            // Display cms content within popup, when link clicked
            //
            function handleCMSContent(){
                var $eles = $('.cmsContent');

                if($eles.length > 0){

                    $eles.each(function(idx, ele){
                        var $ele = $(ele);

                        $ele.find('img').each(function(i,img){window.hWin.HEURIST4.util.restoreRelativeURL(img);});
                        
                        $('<div class="detail" style="cursor:pointer;text-decoration:underline;" title="Click to view web page content in a popup">View web page content</div>').on('click', function(){
                            window.hWin.HEURIST4.msg.showElementAsDialog({'element': $ele[0], 'default_palette_class': 'ui-heurist-explore', 'title': 'Web page content'});
                        }).insertBefore($ele);

                        $ele.hide();
                    })
                }
            }

            //
            //
            //
            function showHidePrivateInfo( event ){
                
                var prefVal = 0;
                if(window.hWin && window.hWin.HAPI4){
                    prefVal = window.hWin.HAPI4.get_prefs('recordData_PrivateInfo');
                }
                if(event!=null){
                    prefVal = (prefVal!=1)?1:0;
                }         
                
                if(prefVal==1){
                    $('#link_showhide_private').text('less...');
                    $('.morePrivateInfo').show();
                    if(event!=null){
                        setTimeout(function(){
                            window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
                            },200);
                    }
                }else{
                    $('#link_showhide_private').text('more...');
                    $('.morePrivateInfo').hide();
                }
                //$(event.target).parents('.detailRowHeader').hide();
                if(event!=null && window.hWin && window.hWin.HAPI4){
                    window.hWin.HAPI4.save_pref('recordData_PrivateInfo', prefVal);    
                }
            }

            //
            // Add group headers to record viewer
            //
            function createRecordGroups(groups){

                var $group_container = $('div#div_public_data');
                var $data = $group_container.find('div[data-order]');

                var $g_ele = null, $g_header = null;
                var current_type = null;

                if(groups == null || $data.length < 0 || $group_container.length < 0){
                    return;
                }else{
                    $.each(groups, function(idx, group){

                        var group_name = group[0];
                        var order = group[1];

                        var next_group = groups[Number(idx)+1];
                        var key = (next_group == null) ? null : next_group[0];
                        var next_order = (key == null) ? null : next_group[1];
                        var sep_type = (key == null) ? null : next_group[2];
                        var $field_container = $('<fieldset>').attr('id', order);
                        let inner_group = sep_type == 'group' || sep_type == 'accordion_inner' || sep_type == 'expanded_inner';

                        $.each($data, function(idx, detail){

                            var $detail = $(detail);
                            var detail_order = $detail.attr('data-order');
                            if(detail_order < order){ // detail belongs in previous group
                                return;
                            }else if(detail_order > order && (next_order == null || order < next_order)){
                                $detail.appendTo($field_container);
                            }else{ // detail belongs in next group
                                return false;
                            }
                        });

                        if(group_name != '-'){
                            if(inner_group){
                                $('<h5>').attr('data-order', order).css({'margin': '5px 15px 2px', 'font-size': '1em', 'font-style': 'italic'}).text(group_name).appendTo($group_container);
                            }else{
                                $('<h4>').attr('data-order', order).css({'margin': '5px 0px 2px', 'font-size': '1.1em', 'text-transform': 'uppercase'}).text(group_name).appendTo($group_container);
                            }
                        }else{
                            $('<hr>').attr('data-order', order).css({'margin': '5px 0px 5px', 'border-top': '1px solid black'}).appendTo($group_container);
                        }

                        $field_container.appendTo($group_container);
                    });

                    $.each($group_container.find('fieldset'), function(idx, fieldset){
                        if($(fieldset).find('div').length == 0){
                            $(fieldset).hide();
                            $group_container.find('h4[data-order="'+ $(fieldset).attr('id') +'"], h5[data-order="'+ $(fieldset).attr('id') +'"]').hide();
                        }
                    });
                }
            }

            //
            // Move related record details without particular relmarker field to the separated section
            //
            function moveRelatedDetails(related_records){
                
                var $rel_section = $('div.relatedSection');

                var $public_fields = $('div#div_public_data').find('fieldset[id], div[data-order]');

                if(related_records == null || $public_fields == null || $public_fields.length == 0){
                    return;
                }

                for(var key in related_records){

                    var rl_order = related_records[key][0];
                    var $pre_location = null;
                    var isAfter = false;

                    $last_fieldset = null;
                    
                    $.each($public_fields, function(idx, field){

                        var $cur_field = $(field);
                        var $next_field = $($public_fields[Number(idx)+1]);

                        if(idx != 0){
                            var $prev_field = $($public_fields[Number(idx)-1]);
                        }

                        var cur_order;
                        var next_order = null;

                        if($cur_field.is('fieldset')){
                            cur_order = $cur_field.attr('id');
                            $last_fieldset = $cur_field;
                        }else{
                            cur_order = $cur_field.attr('data-order');
                        }

                        if($next_field){

                            if($next_field.is('fieldset')){
                                next_order = $next_field.attr('id');
                            }else{
                                next_order = $next_field.attr('data-order');
                            }
                        }
                        if(cur_order < rl_order && (next_order == null || rl_order < next_order)){ 

                            if($next_field && $next_field.is('div')){
                                $pre_location = $next_field;
                            }else if($cur_field.is('div')){
                                $pre_location = $cur_field.parent();
                            }else{
                                $pre_location = $cur_field;
                            }
                            return false;
                        }
                    });

                    if($pre_location == null){
                        $pre_location = $last_fieldset;
                    }
                    if($pre_location != null){

                        for(var i = 1; i < Object.keys(related_records[key]).length; i++){

                            var $rel_field = $rel_section.find('div[data-id="'+ related_records[key][i] +'"]').hide();

                            if($pre_location.is('fieldset')){
                                $rel_field.clone().appendTo($pre_location).show();
                                $pre_location.show();
                            }else{
                                $pre_location.before($rel_field.clone().show());
                                $pre_location.parent().show();
                            }
                        }
                    }
                }

                // hide 'relation' section if there isn't any relmarkers to display
                if($rel_section.find('div[data-id]:visible').length == 0){
                    $rel_section.hide();
                }
            }//end moveRelatedDetails

            // 
            // Init thumbnails and assign mediaViewer
            //
            function showMediaViewer(){
                //2021-12-17 fancybox viewer is disabled IJ doesn't like it - Except iiif and 3dhop
                if(rec_Files_IIIF_and_3D.length>0){

                    if(window.hWin && window.hWin.HAPI4){
                        $('.thumbnail2').mediaViewer({rec_Files:rec_Files_IIIF_and_3D, 
                                showLink:true, database:database, baseURL:baseURL});    
                    }else{
                        $.getScript(baseURL+'external/jquery.fancybox/jquery.fancybox.js', function(){
                            $('.thumbnail2').mediaViewer({rec_Files:rec_Files_IIIF_and_3D, 
                                showLink:true, database:database, baseURL:baseURL});
                        });
                    }
                }
            }
            
            //
            // Init fancybox for "full screen" links
            //
            function initMediaViewer(){

                if(!$('.thumbnail').mediaViewer('instance')){
                    $('.thumbnail').mediaViewer({selector:'.mediaViewer_link', 
                        rec_Files:rec_Files, showLink:false, database:database, baseURL:baseURL });                
                        
                    //setTimeout(function(){$('.thumbnail').mediaViewer('show');},1000);
                }
                //init open in mirador links
                $('.miradorViewer_link').click(function(event){
                   
                    var ele = $(event.target)

                    if(!ele.attr('data-id')){
                        ele = ele.parents('[data-id]');
                    }
                    var obf_recID = ele.attr('data-id');

                    var url =  baseURL
                    + 'hclient/widgets/viewers/miradorViewer.php?db=' 
                    +  database
                    + '&recID='<?php echo $bib['rec_ID']; ?>
                    + '&iiif_image=' + obf_recID;

                    if(false && window.hWin && window.hWin.HEURIST4){
                        //borderless:true, 
                        window.hWin.HEURIST4.msg.showDialog(url, 
                            {dialogid:'mirador-viewer',
                                //resizable:false, draggable: false, 
                                //maximize:true, 
                                default_palette_class: 'ui-heurist-explore',
                                width:'90%',height:'95%',
                                allowfullscreen:true,'padding-content':'0px'});   

                        $dlg = $(window.hWin?window.hWin.document:document).find('body #mirador-viewer');

                        $dlg.parent().css('top','50px');
                    }else{
                        window.open(url, '_blank');        
                    }                      

                    //data-id
                });

                
            }

            //
            // Show/Hide media and linked media
            // @param {show_all_images} Boolean - was call triggered by clicking 'show all images'
            //
            function displayImages(show_all_images = false){

                let hide_images = 0; // 0 - show all (default), 1 - hide linked, 2 - hide all
                if(show_all_images && window.hWin && window.hWin.HAPI4){
                    hide_images = window.hWin.HAPI4.get_prefs_def('recordData_Images', 0);
                }else if(!show_all_images){
                    hide_images = $('#show-linked-media').is(':checked') ? 0 : 1;
                }

                if(hide_images == 2){
                    $('.media-content').hide();
                    return;
                }

                $('.media-content').show();
                if(hide_images == 1){ // hide linked media
                    $('.linked-media').hide();
                }

                if(!show_all_images && window.hWin && window.hWin.HAPI4){
                    window.hWin.HAPI4.save_pref('recordData_Images', hide_images);
                }
            }
            
            $(document).ready(function() {
                showHidePrivateInfo(null);
             
                initMediaViewer();
                
                showMediaViewer(); //init thumbs for iiif

                displayImages(false);
            });
            
            /*NOT USED
            //on document load onLoad="add_sid();"
            function add_sid() {
                try{
                if (top.HEURIST  &&  top.HEURIST.search  &&  top.HEURIST.search.results.querySid) {
                    var e = document.getElementById("edit-link");
                    if (e) {
                        e.href = e.href.replace(/editRecord\.html\?/, "editRecord.html?sid="+top.HEURIST.search.results.querySid+"&");
                    }
                }
                }catch(e){
                }
                
                //init image viewer
                //$('.mediacontent').yoxview({ skin: "top_menu", allowedUrls: /\?db=(?:\w+)&id=(?:\w+)$/i});
                
            }
            */

        </script>
        <style>
        .detailRowHeader{
            padding: 20px 0 20px;
            text-align: left;
            color: #7D9AAA;
            text-transform: uppercase;            
        }
        
        A:hover {
            text-decoration: underline !important;
        }
        A:link, A:active {
            color: #2080C0;
            text-decoration: none;
        }
        .detailRow {
            display: table;
            padding: 5px 0 5px 0;
            font-size: 11px;
            overflow: visible;
        }        

        #recID {
            float: right;
            font-size: 11px;
        }

        .external-link {
            background-image: url(../../hclient/assets/external_link_16x16.gif);
            background-repeat: no-repeat;
            padding-left: 16px;
            padding-top: 4px;
        }
        
        div.thumbnail{
            margin-left: 0px;
            margin-bottom: 25px;
        }

        div.thumbnail img {
            width: 80px;
            border: 2px solid #FFF;
            -moz-box-shadow: 0 2px 4px #BBB;
            -webkit-box-shadow: 0 2px 4px #bbb;
            box-shadow: 0 2px 4px #bbb;
        }

        img {
            border: 0 none;
            vertical-align: middle;
        }   
        .thumb_image {
            margin: 5px;
            cursor: url(../../hclient/assets/zoom-in.png),pointer;
        }
        div.thumbnail .fullSize img {
            margin: 0px;
            width: auto;
            max-width: 100%;
            cursor: url(../../hclient/assets/zoom-out.png),pointer;
        }        
        .download_link{
            float: left;
            text-align: right;
            padding: 15px 10px;
            font-size: 9px;
        }
        .prompt {
            color: #999999;
            font-size: 10px;
            font-weight: normal;
            padding-top: 0.3em;
        }      

        .detail p{
            margin-block: 0;
        }

        .detail img:not(.geo-image, .rv-magglass, .rv-editpencil, .rft){
            width: 50%;
        }
        
        .rft{
            width: 18px !important;
            height: 18px;
            background-repeat: no-repeat;
            background-position: center;        
        }

        .detail span.value:nth-last-child(n+2){
            display: block;
            padding-bottom: 5px;
        }

        .media-control {
            font-weight: normal;
            font-size: 11px;
            margin-left: 15px;
        }
        .media-control input {
            margin: 0;
            vertical-align: -2px;
        }
<?php if($is_production){
    print '.detailType {width:160px;}';
}?>        
        @media print {
          .download_link {
            visibility: hidden;
          }
          h4 {
            page-break-before:auto;    
          }
        }
        </style>
    </head>
    <body class="popup" style="overflow-x: hidden;">

        <script type="text/javascript" src="../../viewers/gmap/mapViewer.js"></script>
        <script>
            mapStaticURL = "<?php echo HEURIST_BASE_URL;?>viewers/gmap/mapStatic.php?width=300&height=300&db=<?php echo HEURIST_DBNAME;?>";
        </script>

        <?php
} //$is_map_popup
else if(!$is_map_popup){
//    print '<div style="font-size:0.8em">';
?>
<script>
            function printLTime(sdate,ele){
                var date = new Date(sdate+"+00:00");
                ele = document.getElementById(ele)
                ele.innerHTML = (''+date.getHours()).padStart(2, "0")
                        +':'+(''+date.getMinutes()).padStart(2, "0")
                        +':'+(''+date.getSeconds()).padStart(2, "0");
            }

</script>
<?php
} 
if ($bkm_ID>0 || $rec_id>0) {
       
        if ($bkm_ID>0) {
            $bibInfo = mysql__select_row_assoc($system->get_mysqli(),
            'select * from usrBookmarks left join Records on bkm_recID=rec_ID '
            .'left join defRecTypes on rec_RecTypeID=rty_ID where bkm_ID='
            .$bkm_ID.' and bkm_UGrpID='.$system->get_user_id()
            .' and (not rec_FlagTemporary or rec_FlagTemporary is null)');
            
        } else if ($rec_id>0) {
            $bibInfo = mysql__select_row_assoc($system->get_mysqli(), 
            'select * from Records left join defRecTypes on rec_RecTypeID=rty_ID where rec_ID='
            .$rec_id.' and not rec_FlagTemporary');
        }
    
        if( $layout_name=='Beyond1914' || $layout_name=='UAdelaide' ){
            
            exit('<div style="display: inline-block; overflow: auto; max-height: 369px; max-width: 260px;">'
                    .'<div class="bor-map-infowindow">'
                        .'<div class="bor-map-infowindow-heading">'.$bibInfo['rec_Title'].'</div>'
                        .'<a href="'
                        .HEURIST_BASE_URL.'place/'.$rec_id.'/a" '
                        .'onclick="{window.hWin.enResolver(event);}" class="bor-button bor-map-button">See connections</a>'
                    .'</div></div>'); 
                                      
        }

        /*if($is_map_popup){
            print '<div data-recid="'.$bibInfo['rec_ID'].'" style="max-height:250px;overflow-y:auto;">';// style="font-size:0.8em"
        }else{
        }*/
	        
            print '<div data-recid="'.$bibInfo['rec_ID'].'">'; // style="font-size:0.8em"
            print_details($bibInfo);
	        print '</div>';
            
            $opts = '';
            $list = '';
            
            if(is_array($sel_ids) && count($sel_ids)>1){
                    
                $cnt = 0;
                
                foreach($sel_ids as $id){
                    
                    $bibInfo = mysql__select_row_assoc($system->get_mysqli(),
                            'select * from Records left join defRecTypes on rec_RecTypeID=rty_ID'
                            .' where rec_ID='.intval($id).' and not rec_FlagTemporary');
                
                    if($id!=$rec_id){  //print details for linked records - hidden
                        print '<div data-recid="'.intval($id).'" style="display:none">'; //font-size:0.8em;
                        print_details($bibInfo);
                        print '</div>';
                    }
                    $opts = $opts . '<option value="'.$id.'">(#'.$id.') '.$bibInfo['rec_Title'].'</option>';
                    
                    $list = $list  //$id==$rec_id || $cnt>3
                        .'<div class="detailRow placeRow"'.($cnt>2?' style="display:none"':'').'>'
                            .'<div style="display:table-cell;padding-right:4px">'
                                .'<img class="rft" style="background-image:url('.HEURIST_RTY_ICON.$bibInfo['rec_RecTypeID'].')" title="'.$rectypesStructure['names'][$bibInfo['rec_RecTypeID']].'" src="'.HEURIST_BASE_URL.'hclient/assets/16x16.gif"></div>'
                        .'<div style="display: table-cell;vertical-align:top;max-width:490px;" class="truncate"><a href="#" '   
.'oncontextmenu="return false;" onclick="$(\'div[data-recid]\').hide();$(\'div[data-recid='.$id.']\').show();'
.'$(\'.gm-style-iw\').find(\'div:first\').scrollTop(0)">'
//.'$(event.traget).parents(\'.gm-style-iw\').children()[0].scrollTop()">'
.strip_tags($bibInfo['rec_Title'],ALLOWED_TAGS).'</a></div></div>';  //htmlspecialchars
                   
                    $cnt++;
                }
                
                //font-size:0.8em;
                print '<div class=detailType style="text-align:left;line-height:21px;">Linked</div><div style="font-size:0.8em" class="map_popup">'.$list;
                if($cnt>3){
                    ?>
                    <div class="detailRow"><div class="detailType">
                        <a href="#" oncontextmenu="return false;" onClick="$('.placeRow').show();$(event.target).hide
                            ()" style="color:blue">more... (n = <?php echo $cnt;?>)</a></div>
                        <div class="detail"></div>
                    </div>
                    <?php
                }
                print '</div>';
                
                /*Multiple entries here<br><br>
                print '<div style="font-size:0.8em"><select style="font-size:0.9em"'
                .' onclick="$(\'div[data-recid]\').hide(); $(\'div[data-recid=\'+$(event.target).val()+\']\').show();" '  
                .'>'.$opts;
                print '</select></div>';*/
                
                
                
            }
        } else {
            print 'No details found';
        }
 if($is_map_popup || $without_header){
//    print '</div>';
 }else{
       ?>
        <div id=bottom><div></div>

    </body>
</html>
<?php	
 }
flush();
ob_flush();
sleep(2);
exit(0);
 
/***** END OF OUTPUT *****/

// this functions outputs common info.
function print_details($bib) {
    global $is_map_popup, $without_header, $ACCESSABLE_OWNER_IDS, $system, $group_details;

    print_header_line($bib);

    $rec_visibility = $bib['rec_NonOwnerVisibility'];
    $rec_owner  = $bib['rec_OwnerUGrpID']; 
    $hasAccess = ($rec_visibility=='public') || 
                                    ($system->has_access() && $rec_visibility!='hidden') || 
                                    in_array($rec_owner, $ACCESSABLE_OWNER_IDS);
    if($hasAccess){

    
        print_public_details($bib);
        
        
//$_time_debug = new DateTime();
        
        $link_cnt = print_relation_details($bib);
/*        
$_time_debug2 = new DateTime('now');
print 'print_relation_details  '.($_time_debug2->getTimestamp() - $_time_debug->getTimestamp()).'<br>';
$_time_debug = $_time_debug2;
*/
        $link_cnt = print_linked_details($bib, $link_cnt); //links from
/*
$_time_debug2 = new DateTime('now');
print 'print_linked_details  '.($_time_debug2->getTimestamp() - $_time_debug->getTimestamp()).'<br>';
$_time_debug = $_time_debug2;
*/
        if($is_map_popup){ // && $link_cnt>3 //linkRow
        ?>
        <div class="map_popup"><div class="detailRow moreRow"><div class=detailType>
            <a href="#" oncontextmenu="return false;" 
                onClick='$(".fieldRow").css("display","table-row");$(".moreRow").hide();createRecordGroups(<?php echo json_encode($group_details, JSON_FORCE_OBJECT); ?>);' style="color:blue">
                more...
            </a>
            </div><div class="detail"></div></div></div>
        <?php
        }

        if(!$is_map_popup){
            print_private_details($bib);
            print_other_tags($bib);
            
            //print_text_details($bib);
        }
        
        $system->user_LogActivity('viewRec', $bib['rec_ID'], null, TRUE); // log action
    }else{
        
        print 'Sorry, your group membership does not allow you to view the content of this record';
    }
    
}


// this functions outputs the header line of icons and links for managing the record.
function print_header_line($bib) {
    global $is_map_popup, $without_header, $is_production, $system;
    
    $rec_id = $bib['rec_ID'];
                    //(($is_production)?'':'padding-top:21px')
    ?>

    <div class=HeaderRow style="margin-bottom:<?php echo $is_map_popup?5:15?>px;min-height:0px;">
        <h2 style="text-transform:none;line-height:16px;font-size:1.4em;margin-bottom:0;<?php echo ($is_map_popup)?'max-width: 380px;':'';?>">
                <?php echo (strip_tags($bib['rec_Title'],ALLOWED_TAGS)); ?>
        </h2>

        <div <?="style='padding:0 10px 0 22px;margin:10px 0 0;height:20px;background-repeat: no-repeat;background-image:url("
                    .HEURIST_RTY_ICON.$bib['rty_ID'].")' title='".htmlspecialchars($bib['rty_Description'])."'" ?> >
            &nbsp;<?= '<strong>'. htmlspecialchars($bib['rty_Name']) .'</strong>: id '. htmlspecialchars($bib['rec_ID']) ?>

        <?php if($system->has_access()){ ?>

            <span class="link"><a id=edit-link class="normal"
                onClick="return sane_link_opener(this);"
                target=_new href="<?php echo HEURIST_BASE_URL;?>?fmt=edit&db=<?=HEURIST_DBNAME?>&recID=<?= $bib['rec_ID'] ?>">
                <img class="rv-editpencil" src="<?php echo HEURIST_BASE_URL;?>hclient/assets/edit-pencil.png" title="Edit record" style="vertical-align: bottom"></a>
            </span>

        <?php } ?>
        </div>

    </div>
    <?php 
}

//
//this  function displays private info if there is any.
// ownereship, viewability, dates, tags, rate
//
function print_private_details($bib) {
    global $system, $is_map_popup;

    if($bib['rec_OwnerUGrpID']==0){
        
        $workgroup_name = 'Everyone';
        
    }else{
    
        $permissions = recordSearchPermissions($system, $bib['rec_ID']);
        if($permissions['status']==HEURIST_OK){
            $groups = @$permissions['edit'][$bib['rec_ID']];
            if(is_array($groups)){
                array_unshift($groups, $bib['rec_OwnerUGrpID']);
            }else{
                $groups = array($bib['rec_OwnerUGrpID']);
            }
            
            $workgroup_name = user_getNamesByIds( $system, $groups );
            $workgroup_name = implode(', ',$workgroup_name);
        
        }else{

            $row = mysql__select_row($system->get_mysqli(),    
                'select grp.ugr_Name,grp.ugr_Type,concat(grp.ugr_FirstName," ",grp.ugr_LastName) from Records, '
                    .'sysUGrps grp where grp.ugr_ID=rec_OwnerUGrpID and rec_ID='.$bib['rec_ID']);
            
            $workgroup_name = NULL;
            // check to see if this record is owned by a workgroup
            if ($row!=null) {
                $workgroup_name = $row[1] == 'user'? $row[2] : $row[0];
            }
        }
    }
    
    // check for workgroup tags
    $kwds = mysql__select_all($system->get_mysqli(),    
        'select grp.ugr_Name, tag_Text from usrRecTagLinks left join usrTags on rtl_TagID=tag_ID left join '
        .'sysUGrps grp on tag_UGrpID=grp.ugr_ID left join sysUsrGrpLinks on ugl_GroupID=ugr_ID and ugl_UserID='
        .$system->get_user_id().' where rtl_RecID='.$bib['rec_ID']
        .' and tag_UGrpID is not null and ugl_ID is not null order by rtl_Order',0,0);

    //show or hide private details depends on preferences            
    ?>
    <div class="detailRowHeader" style="float:left;padding:10px">
        <a href="#" oncontextmenu="return false;" id="link_showhide_private" 
            onClick="showHidePrivateInfo(event)">more...</a>
    </div>
    <div class="detailRowHeader morePrivateInfo" style="float:left;padding:0 0 20px 0;display:none;border:none;">
        
    <div class="detailRow fieldRow"<?php echo $is_map_popup?' style="display:none"':''?>>
        <div class=detailType>Cite as</div><div class="detail<?php echo ($is_map_popup?' truncate" style="max-width:400px;"':'"');?>>
            <a target=_blank class="external-link" 
                href="<?= HEURIST_SERVER_URL ?>/heurist/?recID=<?= $bib['rec_ID']."&db=".HEURIST_DBNAME ?>">XML
            </a>
            &nbsp;&nbsp;
            <a target=_blank class="external-link" 
            href="<?php echo $system->recordLink($bib['rec_ID']); ?>">HTML</a><?php echo ($is_map_popup?'':'<span class="prompt" style="padding-left:10px">Right click to copy URL</span>');?></div>    
    </div>
    <?php
    
    $add_date = DateTime::createFromFormat('Y-m-d H:i:s', $bib['rec_Added']); //get form database in server time
    
    //zero date not allowed by default since MySQL 5.7 default date changed to 1000
    if($add_date && $bib['rec_Added']!='0000-00-00 00:00:00' && $bib['rec_Added']!='1000-01-01 00:00:00') {
        $add_date = $add_date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); //convert to UTC
        $add_date_local = ' (<span id="lt0"></span><script type="text/javascript">printLTime("'.  //output in js in local time
                            $add_date.'", "lt0")</script> local)';

    }else{
        $add_date = false;
    }

    $mod_date = DateTime::createFromFormat('Y-m-d H:i:s', $bib['rec_Modified']); //get form database in server time
    if($mod_date){
        $mod_date = $mod_date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); //convert to UTC
        $mod_date_local = ' (<span id="lt1"></span><script type="text/javascript">printLTime("'.  //output in js in local time
                            $mod_date.'", "lt1")</script> local)';
    }else{
        $mod_date = false;
    }
    if($add_date){
        ?>
        <div class="detailRow fieldRow"<?php echo $is_map_popup?' style="display:none"':''?>>
            <div class=detailType>Added</div><div class=detail>
                <?php print $add_date.'  '.$add_date_local; ?>
            </div>
        </div>
        <?php
    }
    if($mod_date){
        ?>
        <div class="detailRow fieldRow"<?php echo $is_map_popup?' style="display:none"':''?>>
            <div class=detailType>Updated</div><div class=detail>
                <?php print $mod_date.' '.$mod_date_local; ?>
            </div>
        </div>
        <?php
    }
       
        
    if ( $workgroup_name || count($kwds) || @$bib['bkm_ID']) {
        if ( $workgroup_name) {
            ?>
            <div class=detailRow>
                <div class=detailType>Ownership</div>
                <div class=detail>
                    <?php
                    print '<span style="font-weight: bold; color: black;">'.htmlspecialchars($workgroup_name).'</span>';
                    switch ($bib['rec_NonOwnerVisibility']) {
                        case 'hidden':
                            print '<span> - hidden to all except owner(s))</span></div></div>';
                            break;
                        case 'viewable':
                            print '<span> - readable by other logged-in users</span></div></div>';
                            break;
                        case 'public':
                        default:
                            print '<span> - readable by anyone (public)</span></div></div>';
                    }
                }

                $ratings = array("0"=>"none",
                    "1"=> "*",
                    "2"=>"**",
                    "3"=>"***",
                    "4"=>"****",
                    "5"=>"*****");

                $rating_label = @$ratings[@$bib['bkm_Rating']?$bib['bkm_Rating']:"0"];
                ?>

                <div class=detailRow>
                    <div class=detailType>Rating</div>
                    <div class=detail>
                        <!-- <span class=label>Rating:</span> --> <?= $rating_label? $rating_label : 'none' ?>
                    </div>
                </div>

                <?php
                if ($kwds) {
                    ?>
                    <div class=detailRow>
                        <div class=detailType>Workgroup tags</div>
                        <div class=detail>
                            <?php
                            for ($i=0; $i < count($kwds); ++$i) {
                                $grp = $kwds[$i][0];
                                $kwd = $kwds[$i][1];
                                if ($i > 0) print '&nbsp; ';
                                $grp_kwd = $grp.'\\\\'.$kwd;
                                $label = 'Tag "'.$grp_kwd.'"';
                                if (preg_match('/\\s/', $grp_kwd)) $grp_kwd = '"'.$grp_kwd.'"';
                                print htmlspecialchars($grp.' - ').'<a class=normal style="vertical-align: top;" target=_parent href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&ver=1&amp;q=tag:'.urlencode($grp_kwd).'&amp;w=all&amp;label='.urlencode($label).'" title="Search for records with tag: '.htmlspecialchars($kwd).'">'.htmlspecialchars($kwd).'<img style="vertical-align: middle; margin: 1px; border: 0;" class="rv-magglass" src="'.HEURIST_BASE_URL.'hclient/assets/magglass_12x11.gif"></a>';
                            }
                            ?>
                        </div>
                    </div>

                    <?php
                }
    }
    if (is_array($bib) && array_key_exists('bkm_ID', $bib)) {
                print_personal_details($bib);
    }
    print '</div>';
}


//this function outputs the personal information from the bookmark
function print_personal_details($bkmk) {
    global $system;

    $bkm_ID = $bkmk['bkm_ID'];
    $rec_ID = $bkmk['bkm_RecID'];

    $query = 'select tag_Text from usrRecTagLinks, usrTags '
        .'WHERE rtl_TagID=tag_ID and rtl_RecID='.$rec_ID.' and tag_UGrpID = '.
        $bkmk['bkm_UGrpID'].' order by rtl_Order';
    $tags = mysql__select_list2($system->get_mysqli(), $query);
    ?>
    <div class=detailRow>
        <div class=detailType>Personal Tags</div>
        <div class=detail>
            <?php
            if ($tags) {
                for ($i=0; $i < count($tags); ++$i) {
                    if ($i > 0) print '&nbsp; ';
                    $tag = $tags[$i];
                    $label = 'Tag "'.$tag.'"';
                    if (preg_match('/\\s/', $tag)) $tag = '"'.$tag.'"';
                    print '<a class=normal style="vertical-align: top;" target=_parent href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&ver=1&amp;q=tag:'.urlencode($tag).'&amp;w=bookmark&amp;label='.urlencode($label).'" title="Search for records with tag: '.htmlspecialchars($tags[$i]).'">'.htmlspecialchars($tags[$i]).'<img style="vertical-align: middle; margin: 1px; border: 0;" class="rv-magglass" src="'.HEURIST_BASE_URL.'hclient/assets/magglass_12x11.gif"></a>';
                }
                if (count($tags)) {
                    print "<br>\n";
                }
            }
            ?>
        </div>
    </div>

    <?php
}

//
// prints recDetails
//
function print_public_details($bib) {
    global $system, $defTerms, $is_map_popup, $without_header, $is_production, $ACCESSABLE_OWNER_IDS, $relRT, $startDT, $already_linked_ids, $group_details, $hide_images;
    
    $has_thumbs = false;
    
    $mysqli = $system->get_mysqli();

    $query = 'select rst_DisplayOrder, dtl_RecID, dtl_ID, dty_ID,
        IF(rdr.rst_DisplayName is NULL OR rdr.rst_DisplayName=\'\', dty_Name, rdr.rst_DisplayName) as name,
        dtl_Value as val,
        dtl_UploadedFileID,
        dty_Type,
        if(dtl_Geo is not null, ST_asWKT(dtl_Geo), null) as dtl_Geo,
        if(dtl_Geo is not null, ST_asWKT(ST_Envelope(dtl_Geo)), null) as bd_geo_envelope,
        dtl_HideFromPublic
        from recDetails
        left join defDetailTypes on dty_ID = dtl_DetailTypeID
        left join defRecStructure rdr on rdr.rst_DetailTypeID = dtl_DetailTypeID
        and rdr.rst_RecTypeID = '.$bib['rec_RecTypeID'].'
        where dtl_RecID = ' . $bib['rec_ID'];
    
    
    
    $rec_owner  = $bib['rec_OwnerUGrpID']; 
    if(in_array($rec_owner, $ACCESSABLE_OWNER_IDS)){
        //owner of record can see any field
        $detail_visibility_conditions = ''; // .= ' OR rst_NonOwnerVisibility="hidden"';
    }else{
        $detail_visibility_conditions = array();
        if($system->has_access()){
            //logged in user can see viewable
            $detail_visibility_conditions[] = '(rst_NonOwnerVisibility="viewable")';
        }
        $detail_visibility_conditions[] = '(rst_NonOwnerVisibility="public")';
        $detail_visibility_conditions[] = '(rst_NonOwnerVisibility="pending" AND IFNULL(dtl_HideFromPublic, 0)!=1)';    
        
        
        $detail_visibility_conditions = ' AND ('.implode(' OR ',$detail_visibility_conditions).')';
    }
        
    
    $query = $query.$detail_visibility_conditions
        .' order by rdr.rst_DisplayOrder is null,
        rdr.rst_DisplayOrder,
        dty_ID,
        dtl_ID';      //show null last
    
    $bds = array();
    $bds_temp = array();
    $thumbs = array();

    $bds_res = $mysqli->query($query); //0.8 sec

//ok so far

    if($bds_res){
        
        $bds = array();
        
        while ($bd = $bds_res->fetch_assoc()) {
            $bds_temp[] = $bd;    
        }
        $bds_res->close();
  
        //get linked records with file fields
        $query = 'select 999 as rst_DisplayOrder, d2.dtl_RecID, d2.dtl_ID, dt2.dty_ID, "Linked media" as name, '
                .'d2.dtl_Value as val, '
                .'d2.dtl_UploadedFileID, '
                .'dt2.dty_Type, '
                .'null as dtl_Geo, '
                .'null as bd_geo_envelope '
        .' from recDetails d1, defDetailTypes dt1, recDetails d2, defDetailTypes dt2, Records '
        .' where d1.dtl_RecID = '. $bib['rec_ID'].' and d1.dtl_DetailTypeID = dt1.dty_ID and dt1.dty_Type = "resource" '
        .' AND d2.dtl_RecID = d1.dtl_Value and d2.dtl_DetailTypeID = dt2.dty_ID and dt2.dty_Type = "file" ' 
        .' AND rec_ID = d2.dtl_RecID and rec_RecTypeID != '.$relRT
        .' and (rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') OR '.
            ($system->has_access()?'NOT rec_NonOwnerVisibility = "hidden")':'rec_NonOwnerVisibility = "public")');

//print $query;            
        if(true){  //this query fails for maria db        
                
            $bds_res = $mysqli->query($query);     
            if($bds_res){   
                while ($bd = $bds_res->fetch_assoc()) {
                    $bds_temp[] = $bd;    
                }
                $bds_res->close();
            }
        }        
        
        foreach ($bds_temp as $bd) {

            if ($bd['dty_Type'] == 'enum' || $bd['dty_Type'] == 'relationtype') {
                
                $bd['val'] = output_chunker($defTerms->getTermLabel($bd['val'], true));
/*                

                if(array_key_exists($bd['val'], $terms['termsByDomainLookup']['enum'])){
                    $term = $terms['termsByDomainLookup']['enum'][$bd['val']];
                    $bd['val'] = output_chunker(getTermFullLabel($terms, $term, 'enum', false));
                    //$bd['val'] = output_chunker($terms['termsByDomainLookup']['enum'][$bd['val']][0]);
                }else{
                    $bd['val'] = "";
                }

            }else if ($bd['dty_Type'] == 'relationtype') {

                $term = @$terms['termsByDomainLookup']['relation'][$bd['val']];
                if($term){
                    $bd['val'] = output_chunker(getTermFullLabel($terms, $term, 'relation', false));    
                }else{
                    $bd['val'] = 'Term '.$bd['val'].' not found';
                }
*/                

            }else if ($bd['dty_Type'] == 'date') {

                if($bd['val']==null || $bd['val']==''){
                    //ignore empty date
                    continue;
                }else{
                    $bd['val'] = Temporal::toHumanReadable($bd['val'], true);
                    $bd['val'] = output_chunker($bd['val']);
                }

            }else if ($bd['dty_Type'] == 'blocktext') {

                $bd['val'] = nl2br(str_replace('  ', '&nbsp; ', output_chunker($bd['val'])));
                //replace link <a href="[numeric]"> to record view links
                
                $bd['val'] = preg_replace('/href=["|\']?(\d+)["|\']?/',
                        'onclick="return link_open(this);" href="'
                        .$system->recordLink('$1').'"',
                        $bd['val']);
                

            }else if ($bd['dty_Type'] == 'resource') {

                
                $rec_id = intval($bd['val']);                              
                $row = mysql__select_row($mysqli, 'select rec_Title, rec_NonOwnerVisibility, rec_OwnerUGrpID  from Records where rec_ID='.$rec_id);
                if($row){
                    $rec_title = $row[0];
                    $rec_visibility = $row[1];
                    $rec_owner = $row[2];
                    
                    $hasAccess = ($rec_visibility=='public') || 
                                    ($system->has_access() && $rec_visibility!='hidden') || 
                                    in_array($rec_owner, $ACCESSABLE_OWNER_IDS);
                    
                    if($hasAccess){
                        
                        $bd['val'] = '<a target="_new" href="'.$system->recordLink($rec_id)
                            .'" onclick="return link_open(this);">'
                            .strip_tags($rec_title,ALLOWED_TAGS).'</a>';
                        
                    }else{
                        
                        $bd['val'] = '<a href="#" oncontextmenu="return false;" onclick="return no_access_message(this);">'
                            .strip_tags($rec_title,ALLOWED_TAGS).'</a>';
                        
                    }

                    //find dates
                    $row = mysql__select_row($mysqli, 'SELECT rdi_estMinDate ' 
                            .' FROM recDetailsDateIndex'
                            .' WHERE rdi_RecID='.$rec_id .' AND rdi_DetailTypeID IN ('.DT_DATE.','.$startDT.')'); 

                    if($row){
                        $bd['order_by_date'] = $row[0];
                    }
                        
                    
					array_push($already_linked_ids, $rec_id);
                }

            }
            else if ($bd['dty_Type'] == 'file') {
                
                $fileinfo = null;
                
                if($hide_images == 2 || ($hide_images == 1 && $bd['dtl_RecID'] != $bib['rec_ID'])){ // skip all images || skip linked media
                    continue;
                }

                if(!($bd['dtl_UploadedFileID']>0)){
                     // FIX on fly - @todo  remove on 2022-08-22
                     $ruf_entity = new DbRecUploadedFiles($system, array('entity'=>'recUploadedFiles'));
                     $fileinfo = $ruf_entity->registerURL($bd['val'], false, $bd['dtl_ID']);
                }else{
                    $listpaths = fileGetFullInfo($system, $bd['dtl_UploadedFileID']); //see db_files.php
                    if(is_array($listpaths) && count($listpaths)>0){
                        $fileinfo = $listpaths[0]; //
                    }
                }
                
                if($fileinfo){
                    
                    $filepath = $fileinfo['fullPath'];  //concat(ulf_FilePath,ulf_FileName as fullPath
                    $external_url = $fileinfo['ulf_ExternalFileReference'];     //ulf_ExternalFileReference
                    $mimeType = $fileinfo['fxm_MimeType'];  // fxm_MimeType
                    $sourceType = $fileinfo['ulf_PreferredSource'];  
                    $file_Ext = $fileinfo['ulf_MimeExt'];
                    $originalFileName = $fileinfo['ulf_OrigFileName'];
                    $fileSize = $fileinfo['ulf_FileSizeKB'];
                    $file_nonce = $fileinfo['ulf_ObfuscatedFileID'];
                    $file_description = $fileinfo['ulf_Description'];

                    $file_playerURL = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$file_nonce.'&mode=tag';
                    $file_thumbURL  = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&offer_download=1&thumb='.$file_nonce;
                    $file_URL   = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$file_nonce; //download
                    
                    array_push($thumbs, array(
                        'id' => $bd['dtl_UploadedFileID'],
                        //'url' => $file_URL, //download
                        'external_url' => $external_url,      //external url
                        //'mediaType'=>$filedata['mediaType'], 
                        'params'=>null, 
                        'orig_name'=>$originalFileName,
                        'sourceType'=>$sourceType,
                        'mimeType'=>$mimeType, 
                        'file_size'=>$fileSize,
                        'mode_3d_viewer' => detect3D_byExt($file_Ext),
                        'thumb' => $file_thumbURL,
                        'player' => $file_playerURL,
                        'nonce' => $file_nonce,
                        'linked' => ($bd['dtl_RecID'] != $bib['rec_ID']),
                        'description' => $file_description
                    ));
                    
                     
                    $bd['val'] = '<a target="_surf" href="'.htmlspecialchars($external_url?$external_url:$file_URL).'">';

                    /* 2022-06 Moved file desc to popup linked from 'credits'
                    if($file_description!=null && $file_description!=''){
                        $bd['val'] = $bd['val'].htmlspecialchars($file_description).'<br>';
                    }*/
                    $bd['val'] .= '<span class="external-link" style="vertical-align: bottom;"></span>';
                    if(strpos($originalFileName,'_iiif')===0){
                        $bd['val'] = $bd['val'].'<img src="'.HEURIST_BASE_URL.'hclient/assets/iiif_logo.png" style="width:16px"/>';
                        $originalFileName = null;
                    }
              
                    
                    $bd['val'] .= '<span>'.htmlspecialchars(($originalFileName && $originalFileName!='_remote')
                                        ?$originalFileName
                                        :($external_url?$external_url:$file_URL)).'</span></a> '
                            .($fileSize>0?'[' .htmlspecialchars($fileSize) . 'kB]':'');
                    
                }

            } 
            else {
                if (preg_match('/^https?:/', $bd['val'])) {
                    if (strlen($bd['val']) > 100)
                        $trim_url = preg_replace('/^(.{70}).*?(.{20})$/', '\\1...\\2', $bd['val']);
                    else
                        $trim_url = $bd['val'];
                    $bd['val'] = '<a href="'.$bd['val'].'" target="_new">'.htmlspecialchars($trim_url).'</a>';
                } else if ($bd['dtl_Geo']){
                    
                    $minX = null;
                    
                    if (preg_match("/^POLYGON\s?[(][(]([^ ]+) ([^ ]+),[^,]*,([^ ]+) ([^,]+)/", 
                                $bd["bd_geo_envelope"], $poly))
                    {
                        list($match, $minX, $minY, $maxX, $maxY) = $poly;
                        
                    }else if (preg_match("/POINT\\((\\S+)\\s+(\\S+)\\)/i", $bd["bd_geo_envelope"], $matches)){
                        $minX = floatval($matches[1]);
                        $minY = floatval($matches[2]);
                    }else if ($bd["val"] == "l"  &&  preg_match("/^LINESTRING\s?[(]([^ ]+) ([^ ]+),.*,([^ ]+) ([^ ]+)[)]$/",
                                $bd["dtl_Geo"],$matches)) 
                    {
                        list($dummy, $minX, $minY, $maxX, $maxY) = $matches;
                    }
                    /*   redundant
                    $minX = intval($minX*10)/10;
                    $minY = intval($minY*10)/10;
                    $maxX = intval($maxX*10)/10;
                    $maxY = intval($maxY*10)/10;
                    */

                    switch ($bd["val"]) {
                        case "p": $type = "Point"; break;
                        case "pl": $type = "Polygon"; break;
                        case "c": $type = "Circle"; break;
                        case "r": $type = "Rectangle"; break;
                        case "l": $type = "Path"; break;
                        case "m": $type = "Collection"; break;
                        default: $type = "Collection";
                    }

                    if ($type == "Point")
                        $bd["val"] = "<b>Point</b> ".($minX!=null?round($minX,7).", ".round($minY,7):'');
                    else
                        $bd['val'] = "<b>$type</b> X ".($minX!=null?round($minX,7).", ".round($maxX,7).
                        " Y ".round($minY,7).", ".round($maxY,7):'');

                    $geoimage = 
                    "<img class='geo-image' style='vertical-align:top;' src='".HEURIST_BASE_URL
                    ."hclient/assets/geo.gif' onmouseout='{if(mapViewer){mapViewer.hide();}}' "
                    ."onmouseover='{if(mapViewer){mapViewer.showAtStatic(event, ".$bib['rec_ID'].");}}'>&nbsp;";

                    $bd['val'] = $geoimage.$bd['val'];

                } else {
                    $bd['val'] = output_chunker($bd['val']);
                }
            }

            array_push($bds, $bd);
    }//for

        
    }
    
    usort($bds, "__sortResourcesByDate");


    if($is_map_popup){
        print '<div class="map_popup">';
    }

    //print info about parent record
    if(true || !$is_production) {
        foreach ($bds as $bd) {
            if(defined('DT_PARENT_ENTITY') && $bd['dty_ID']==DT_PARENT_ENTITY){

                print '<div class="detailRow" style="width:100%;border:none 1px #00ff00;">'
                .'<div class=detailType>Parent record</div><div class="detail">'
                .' '.$bd['val'].'</div></div>';
                break;
            }
        }
    }
    
    print '<div id="div_public_data">';

    //2021-12-17 fancybox viewer is disabled IJ doesn't like it - Except iiif
    if(!($is_map_popup || $without_header)){
        print '<script>';
        foreach ($thumbs as $thumb) {
            if(strpos($thumb['orig_name'], '_iiif')===0 || $thumb['mode_3d_viewer']!=''){
                print 'rec_Files_IIIF_and_3D.push({rec_ID:'.$bib['rec_ID']
                                            .', id:"'.$thumb['nonce']
                                            .'",mimeType:"'.$thumb['mimeType']
                                            .'",mode_3d_viewer:"'.$thumb['mode_3d_viewer']
                                            .'",filename:"'.htmlspecialchars($thumb['orig_name'])
                                            .'",external:"'.htmlspecialchars($thumb['external_url']).'"});';
                //if($is_map_popup) break;
            }else{
                print 'rec_Files.push({rec_ID:'.$bib['rec_ID'].', id:"'.$thumb['nonce'].'",mimeType:"'.$thumb['mimeType'].'",filename:"'.htmlspecialchars($thumb['orig_name']).'",external:"'.htmlspecialchars($thumb['external_url']).'"});';
            }
        }
        print '</script>';
    }
    print '<div class="thumbnail2" style="text-align:center"></div>';

    
    $hasAudioVideo = '';
    if($is_production){
        print '<div class="thumbnail production">';
    }else{
        print '<div class="thumbnail">';
    }
        $has_thumbs = (count($thumbs)>0);        
      
    $several_media = count($thumbs);
    $hide_images = $system->user_GetPreference('recordData_Images', 0);
        
    if($hide_images != 2) // use/hide old thumbnails   
        foreach ($thumbs as $k => $thumb) {
            
            if(strpos($thumb['orig_name'],'_iiif')===0 || 
                      $thumb['mode_3d_viewer'] != '' ) continue;
            
            $isAudioVideo = (strpos($thumb['mimeType'],'audio/')===0 || strpos($thumb['mimeType'],'video/')===0);
            
            $isImageOrPdf = (strpos($thumb['mimeType'],"image/")===0 || $thumb['mimeType']=='application/pdf');
            
            if($thumb['player'] && !$is_map_popup && $isAudioVideo){
                print '<div class="fullSize media-content" style="text-align:left;'
                    .($is_production?'margin-left:100px':'')
                    .($k>0?'display:none;':'').'">';
            }else{
                print '<div class="thumb_image media-content'. ($thumb['linked'] == true ? ' linked-media' : '') .'"  style="'.($isImageOrPdf?'':'cursor:default;')
                    .($k>0?'display:none;':'').'">';
            }

            $media_control_chkbx = '';
            if($k == 0 && $thumb['linked'] != true && !$is_production && !$is_map_popup){
                $media_control_chkbx = ' <label class="media-control"><input type="checkbox" id="show-linked-media" onchange="displayImages(false);" '. ($hide_images == 0 ? ' checked="checked"' : '') .'> show linked media</label>';
            }

            $url = (@$thumb['external_url'] && strpos($thumb['external_url'],'http://')!==0) 
                        ?$thumb['external_url']            //direct for https
                        :(HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$thumb['nonce']);
            $download_url = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&debug=3&download=1&file='.$thumb['nonce'];

            print '<div class="download_link">';

            if(!$is_map_popup){
            
                if($k==0 && $several_media>1){
                    print '<a href="#" onclick="displayImages(true);">'
                    .'<span class="ui-icon ui-icon-menu" style="font-size:1.2em;display:inline-block;vertical-align: middle;"></span>&nbsp;all images</a><br><br>';
                }
                if(count($thumbs)>0 && !$isAudioVideo){
                    print '<a href="#" data-id="'.$thumb['nonce'].'" class="mediaViewer_link">'
                    .'<span class="ui-icon ui-icon-fullscreen" style="font-size:1.2em;display:inline-block;vertical-align: middle;"></span>&nbsp;full screen</a><br><br>';
                }
            }

            if(strpos($thumb['mimeType'],'image/')===0 || ($isAudioVideo &&
                 ( strpos($thumb['mimeType'],'youtube')===false && 
                   strpos($thumb['mimeType'],'vimeo')===false && 
                   strpos($thumb['mimeType'],'soundcloud')===false)) )
            {
                print '<a href="#" data-id="'.$thumb['nonce'].'" class="miradorViewer_link">'
                    .'<span class="ui-icon ui-icon-mirador" style="width:12px;height:12px;margin-left:5px;font-size:1em;display:inline-block;vertical-align: middle;'
                    .'filter: invert(35%) sepia(91%) saturate(792%) hue-rotate(174deg) brightness(96%) contrast(89%);'
                    .'"></span>&nbsp;Mirador</a><br><br>';
            }

            if(@$thumb['external_url']){
                print '<a href="' . htmlspecialchars($thumb['external_url']) 
                                . '" class="external-link" target=_blank>open in new tab'
                                . (@$thumb['linked']?'<br>(linked media)':'').'</a>';
            }else{
                print '<a href="' . htmlspecialchars($download_url) 
                                . '" class=" image_tool" target="_surf">'
                                . '<span class="ui-icon ui-icon-download" style="font-size:1.2em;display:inline-block;vertical-align: middle;"></span>&nbsp;'
                                . 'download' . (@$thumb['linked']?'<br>(linked media)':'').'</a>';
            }
            print '<br><br>';

            if(@$thumb['description'] != null && @$thumb['description'] != ''){
                if(filter_var($thumb['description'], FILTER_VALIDATE_URL)){ // just a url
                    print '<a href="'.htmlspecialchars($thumb['description']).'" target="_blank">&copy;&nbsp;credits</a>';
                }else{ // check for possible urls and linkify valid ones

                    $file_desc = str_replace(array("\r\n", "\r", "\n"), '<br>', $thumb['description']);

                    preg_match_all('/((?:https?|ftp|mailto))(\S)+/', $file_desc, $url_matches); // only urls that contain a protocol [http|https|ftp|mailto]

                    if(is_array($url_matches) && count($url_matches[0]) > 0){

                        foreach($url_matches[0] as $url){
                            if(mb_strpos($url, '<br>')){ // remove from first br onwards, in case
                                $url = explode('<br>', $url)[0];
                            }
                            if(strpos($file_desc, 'href=\'' . $url)!==false || strpos($file_desc, 'href="' . $url)!==false || strpos($file_desc, 'href=`' . $url)!==false){ // check if already part of element
                                continue;
                            }
                            if(ctype_punct(mb_substr($url, -1))){ // ensure last character isn't punctuation
                                $url = mb_substr($url, 0, -1);
                            }

                            if(!empty($url) && is_string($url) && filter_var($url, FILTER_VALIDATE_URL)){ // php validate url
                                $linked_url = '<a href='. $url .' target="_blank">'. $url .'</a>';
                                $file_desc = str_replace($url, $linked_url, $file_desc);
                            }
                        }
                    }

                    print '<a href="#" style="cursor: pointer; color: #2080C0; padding-left: 7.5px;" '
                        . 'onClick="window.hWin.HEURIST4.msg.showMsgDlg(\''.addslashes(htmlspecialchars($file_desc)).'\', null, \'Credits for '.htmlspecialchars($thumb['orig_name']).'\'); return false;">'
                        . '&copy;&nbsp;credits</a><br><br>';
                }
            }

            if(!$is_map_popup && $thumb['player'] && !$without_header){
                print '<a id="lnk'.$thumb['id'].'" href="#" oncontextmenu="return false;" style="display:none;" onclick="window.hWin.HEURIST4.ui.hidePlayer('
                        .$thumb['id'].', this.parentNode)">show thumbnail</a>';
            }

            print '</div><!-- CLOSE download_link -->';  //CLOSE download_link

            if($thumb['player'] && !$is_map_popup && $isAudioVideo){
                print '<div class="fullSize media-content" style="text-align:left;'
                    .($is_production?'margin-left:100px':'')
                    .($k>0?'display:none;':'').'">';
            }else{
                print '<div class="thumb_image media-content'. ($thumb['linked'] == true ? ' linked-media' : '') .'"  style="'.($isImageOrPdf?'':'cursor:default;')
                    .($k>0?'display:none;':'').'">';
            }

            if($thumb['linked'] == true){
                print "<h5 style='margin-block:0.5em;'>LINKED MEDIA</h5>";
            }else{
                print "<h5 style='margin-block:0.5em;'>MEDIA $media_control_chkbx</h5>";
            }

            if($thumb['player'] && !$is_map_popup){

                if($isAudioVideo){
                    //audio or video is maximized at once
                    
                    print '<div id="player'.$thumb['id'].'" style="min-height:100px;min-width:200px;text-align:left;">';

                    print fileGetPlayerTag($system, $thumb['nonce'], $thumb['mimeType'], $thumb['params'], $thumb['external_url']); //see db_files
                    
                    //print getPlayerTag($thumb['nonce'], $thumb['mimeType'], $thumb['url'], null); 
                    print '</div>';    
                }else{
                    print '<img id="img'.$thumb['id'].'" style="width:200px" src="'.htmlspecialchars($thumb['thumb']).'"';
                    if($isImageOrPdf && !$without_header){                        
                        print ' onClick="window.hWin.HEURIST4.ui.showPlayer(this,this.parentNode,'.$thumb['id'].',\''. htmlspecialchars($thumb['player'].'&origin=recview') .'\')"';
                    }
                    print '><div id="player'.$thumb['id'].'" style="min-height:240px;min-width:320px;display:none;"></div>';
                }
            }else{  //for usual image
                print '<img src="'.htmlspecialchars($thumb['thumb']).'" '
                    .(($is_map_popup || $without_header)
                        ?''
                        :'onClick="zoomInOut(this,\''. htmlspecialchars($thumb['thumb']) .'\',\''. htmlspecialchars($url) .'\')"').'>';
            }
            print '</div>';
            print '</div><!--CLOSE THUMB SECTION-->';
            if($is_map_popup){
                print '<br>';
                break; //in map popup show the only thumbnail
            }
            
        }//for
    
    print '</div><!--CLOSE ALL thumbnails-->';

//<div id="div_public_data" style="float:left; echo (($has_thumbs)?'max-width:900px':'')">

    //print url first
    $url = $bib['rec_URL'];
    if ($url  &&  ! preg_match('!^[^\\/]+:!', $url)){
        $url = 'http://' . $url;
    }
    /*
    $webIcon = mysql__select_value($system->get_mysqli(),
                    'select dtl_Value from recDetails where dtl_RecID='
                    .$bib['rec_ID'].' and dtl_DetailTypeID=347'); //DT_WEBSITE_ICON); 
    if ($webIcon) print "<img id=website-icon src='" . $webIcon . "'>";
   */                 
    if (@$url) {
        print '<div class="detailRow" style="border:none 1px #00ff00;">' //width:100%;
            .'<div class=detailType>URL</div>'
            .'<div class="detail'.($is_map_popup?' truncate style="max-width:400px;"':'"').'>'
            .'<span class="link">'
                .'<a target="_new" class="external-link" href="http://web.archive.org/web/*/'.htmlspecialchars($url).'">page history</a>&nbsp;&nbsp;&nbsp;'
                .'<a target="_new" class="external-link" href="'.htmlspecialchars($url).'">'.output_chunker($url).'</a>'
            .'</span>'
            .'</div></div>';
    } 

    $always_visible_dt = array(DT_SHORT_SUMMARY);
    if(defined('DT_GEO_OBJECT')){
        $always_visible_dt[] = DT_GEO_OBJECT;                                                 
    }

    $usr_font_size = $system->user_GetPreference('userFontSize', 0);
    $font_size = '';
    if(!$is_map_popup && $usr_font_size != 0){
        $usr_font_size = ($usr_font_size < 8) ? 8 
                                              : (($usr_font_size > 18) ? 18 : $usr_font_size);
        $font_size = 'font-size: ' . $usr_font_size . 'px;';
    }

    $prevLbl = null;
    foreach ($bds as $bd) {
        if (defined('DT_PARENT_ENTITY') && $bd['dty_ID']==DT_PARENT_ENTITY) {
            continue;
        }
        
        if(defined('DT_WORKFLOW_STAGE') && $bd['dty_ID']==DT_WORKFLOW_STAGE){
            continue;
        }

        $query = 'SELECT rst_ID FROM defRecStructure WHERE rst_DetailTypeID ='.$bd['dty_ID']
                .' AND rst_RecTypeID = '. $bib['rec_RecTypeID'];

        if(!(mysql__select_value($mysqli, $query)>0)) continue; //not in structure


        $is_cms_content = !$is_map_popup &&  
                          (defined('RT_CMS_MENU') && $bib['rec_RecTypeID']==RT_CMS_MENU ||
                           defined('RT_CMS_HOME') && $bib['rec_RecTypeID']==RT_CMS_HOME) &&
                           (defined('DT_EXTENDED_DESCRIPTION') && $bd['dty_ID']==DT_EXTENDED_DESCRIPTION || 
                            defined('DT_CMS_HEADER') && $bd['dty_ID']==DT_CMS_HEADER || 
                            defined('DT_CMS_FOOTER') && $bd['dty_ID']==DT_CMS_FOOTER) ? ' cmsContent' : '';

        $ele_id = ($bd['rst_DisplayOrder'] != '' || $bd['rst_DisplayOrder'] != null) ? ('data-order="' . $bd['rst_DisplayOrder']) . '"' : '';

        if($prevLbl != $bd['name']){ // start new detail row

            if($prevLbl != null){
                print '</div></div>'; // close previous detail row
            }

            // open new detail row
            print '<div class="detailRow fieldRow" '. $ele_id .' style="border:none 1px #00ff00;'   //width:100%;
                    .($is_map_popup && !in_array($bd['dty_ID'], $always_visible_dt)?'display:none;':'')
                    .($is_map_popup?'':'width:100%;')
                    .$font_size
                    .'"><div class=detailType>'.($prevLbl==$bd['name']?'':htmlspecialchars($bd['name']))
                . '</div><div class="detail'.($is_map_popup && ($bd['dty_ID']!=DT_SHORT_SUMMARY)?' truncate':'').$is_cms_content.'">';
        }

        print '<span class="value">' . $bd['val'] . '</span>'; // add value
        $prevLbl = $bd['name'];
    }

    if($prevLbl != null){
        print '</div></div>'; // close final detail row
    }

    $group_details = array();
    $current_type = null;
    $tabs_list = array();

    $query = "SELECT rst_DisplayName, rst_DisplayOrder, rst_DefaultValue 
              FROM defRecStructure
              LEFT JOIN defDetailTypes ON rst_DetailTypeID = dty_ID
              WHERE rst_RecTypeID = ". $bib['rec_RecTypeID'] ." AND dty_Type = 'separator' AND rst_RequirementType != 'forbidden'
              ORDER BY rst_DisplayOrder";

    $groups_res = $mysqli->query($query);

    if($groups_res){

        while ($group = $groups_res->fetch_row()) {
            $group_details[] = array($group[0], $group[1], $group[2]);
        }

    }
    if($is_map_popup){
        //echo '<div class=detailRow><div class=detailType><a href="#" onClick="$(\'.fieldRow\').show();$(event.target).hide()">more</a></div><div class="detail"></div></div>';
    }else{

        if(is_array($group_details) && count($group_details) > 0){
            echo '<script>createRecordGroups(', json_encode($group_details, JSON_FORCE_OBJECT), ');handleCMSContent();</script>';
        }

        echo '<div class="detailRow fieldRow">&nbsp;</div>';
    }

    echo '</div></div>';            
}


//@todo implement popup that lists all record's tags 
function print_other_tags($bib) {
    return;
}

//
//
//
function print_relation_details($bib) {

    global $system, $relRT,$relSrcDT,$relTrgDT,$ACCESSABLE_OWNER_IDS, $is_map_popup, $is_production, $rectypesStructure;

    $mysqli = $system->get_mysqli();
	
    $from_res = $mysqli->query('select recDetails.*
		from recDetails
		left join Records on rec_ID = dtl_RecID
		where dtl_DetailTypeID = '.$relSrcDT.
		' and rec_RecTypeID = '.$relRT.
		' and dtl_Value = ' . intval($bib['rec_ID']));        //primary resource

    $to_res = $mysqli->query('select recDetails.*
		from recDetails
		left join Records on rec_ID = dtl_RecID
		where dtl_DetailTypeID = '.$relTrgDT.
		' and rec_RecTypeID = '.$relRT.
		' and dtl_Value = ' . intval($bib['rec_ID']));          //linked resource

    if (($from_res==false || $from_res->num_rows <= 0)  &&  
		 ($to_res==false || $to_res->num_rows<=0)){
		   return 0;  
    } 

    $link_cnt = 0;    

    if($is_map_popup){
        print '<div class="detailType fieldRow" style="display:none;line-height:21px">Related</div>';
        print '<div class="map_popup">';
    }else{
        print '<div class="detailRowHeader relatedSection" Xstyle="float:left">Related'; 
    }

    $accessCondition = '(rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') OR '.
    ($system->has_access()?'NOT rec_NonOwnerVisibility = "hidden")':'rec_NonOwnerVisibility = "public")');

    $relfields_details = mysql__select_all($mysqli,
            'SELECT rst_DisplayName, rst_DisplayOrder, dty_PtrTargetRectypeIDs 
             FROM defRecStructure 
             INNER JOIN defDetailTypes ON rst_DetailTypeID = dty_ID 
             WHERE dty_Type = "relmarker" AND rst_RequirementType != "forbidden" AND rst_RecTypeID = '. $bib['rec_RecTypeID'] .'
             ORDER BY rst_DisplayOrder');

    $move_details = array();

    $extra_styling = (!$is_map_popup) ? 'style="max-width: max-content;"' : '';

    $usr_font_size = $system->user_GetPreference('userFontSize', 0);
    $font_size = '';
    if(!$is_map_popup && $usr_font_size != 0){
        $usr_font_size = ($usr_font_size < 8) ? 8 
                                              : (($usr_font_size > 18) ? 18 : $usr_font_size);
        $font_size = 'font-size: ' . $usr_font_size . 'px;';
    }

    if($from_res){
		while ($reln = $from_res->fetch_assoc()) {
			
			$bd = fetch_relation_details($reln['dtl_RecID'], true);

			// check related record
			if (!@$bd['RelatedRecID'] || !array_key_exists('rec_ID',$bd['RelatedRecID'])) {
				continue;
			}
			$relatedRecID = $bd['RelatedRecID']['rec_ID'];

			if(mysql__select_value($mysqli, 
				"select count(rec_ID) from Records where rec_ID =$relatedRecID and $accessCondition")==0){
				//related is not accessable
				continue;
			}

			$field_name = false;

			if($relfields_details && count($relfields_details) > 0){

				for($i = 0; $i < count($relfields_details); $i++){

					$ptrtarget_ids = explode(',', $relfields_details[$i][2]);
					$fld_name = $relfields_details[$i][0];

					if(in_array($bd['RelatedRecID']['rec_RecTypeID'], $ptrtarget_ids)){

						if(array_key_exists($fld_name, $move_details)){

							array_push($move_details[$fld_name], $bd['recID']);

							$field_name = '';
						}else{

							$move_details[$fld_name] = array();
							array_push($move_details[$fld_name], $relfields_details[$i][1], $bd['recID']);  //name of field, order, related recid

							$field_name = $fld_name;
						}

						break;
					}
				}
			}

            // get title mask for display
            if(array_key_exists('rec_Title',$bd['RelatedRecID'])){
                $recTitle = $bd['RelatedRecID']['rec_Title'];

                if($field_name !== false && array_key_exists('RelTerm',$bd)){
                    $recTitle = $bd['RelTerm'] . ' - > ' . $recTitle;
                }
            }else{
                $recTitle = 'record id ' . $relatedRecID;
            }

			print '<div class="detailRow fieldRow" data-id="'. $bd['recID'] .'" style="'.$font_size.($is_map_popup?'display:none':'').'">'; // && $link_cnt>2 linkRow
			$link_cnt++;
			//		print '<span class=label>' . htmlspecialchars($bd['RelationType']) . '</span>';	//saw Enum change

			if($field_name === false && array_key_exists('RelTerm',$bd)){
				print '<div class=detailType>' . htmlspecialchars($bd['RelTerm']) . '</div>';
			}else if($field_name !== false){
				print '<div class=detailType>' . $field_name . '</div>';
			}

			print '<div class="detail" '. $extra_styling .'>';
				if (@$bd['RelatedRecID']) {

					print '<img class="rft" style="vertical-align: top;background-image:url('.HEURIST_RTY_ICON.$bd['RelatedRecID']['rec_RecTypeID'].')" title="'.$rectypesStructure['names'][$bd['RelatedRecID']['rec_RecTypeID']].'" src="'.HEURIST_BASE_URL.'hclient/assets/16x16.gif">&nbsp;';

					print '<a target=_new href="'.$system->recordLink($bd['RelatedRecID']['rec_ID'])
                            .'" onclick="return link_open(this);">'
							.strip_tags($recTitle,ALLOWED_TAGS).'</a>';
				} else {
					print strip_tags($bd['Title'],ALLOWED_TAGS);
				}
				print '&nbsp;&nbsp;';
				if (@$bd['StartDate']) print htmlspecialchars(Temporal::toHumanReadable($bd['StartDate'], true));
				if (@$bd['EndDate']) print ' until ' . htmlspecialchars(Temporal::toHumanReadable($bd['EndDate'], true));
			print '</div></div>';
		}
		$from_res->close();
    }
    if($to_res){
        while ($reln = $to_res->fetch_assoc()) {

			$bd = fetch_relation_details($reln['dtl_RecID'], false);
			// check related record
			if (!@$bd['RelatedRecID'] || !array_key_exists('rec_ID',$bd['RelatedRecID'])) {
				continue;
			}
			$relatedRecID = $bd['RelatedRecID']['rec_ID'];

         
			if(mysql__select_value($mysqli, 
				"select count(rec_ID) from Records where rec_ID =$relatedRecID and $accessCondition")==0){
				//related is not accessable
				continue;
			}

			$field_name = false;

			if($relfields_details && count($relfields_details) > 0){

				for($i = 0; $i < count($relfields_details); $i++){

					$ptrtarget_ids = explode(',', $relfields_details[$i][2]);
					$fld_name = $relfields_details[$i][0];

					if(in_array($bd['RelatedRecID']['rec_RecTypeID'], $ptrtarget_ids)){

						if(array_key_exists($fld_name, $move_details)){

							array_push($move_details[$fld_name], $bd['recID']);

							$field_name = '';
						}else{

							$move_details[$fld_name] = array();
							array_push($move_details[$fld_name], $relfields_details[$i][1], $bd['recID']);

							$field_name = $fld_name;
						}

						break;
					}
				}
			}

            // get title mask for display
            if(array_key_exists('rec_Title',$bd['RelatedRecID'])){
                $recTitle = $bd['RelatedRecID']['rec_Title'];

                if($field_name !== false && array_key_exists('RelTerm',$bd)){
                    $recTitle = $bd['RelTerm'] . ' - > ' . $recTitle;
                }
            }else{
                $recTitle = 'record id ' . $relatedRecID;
            }

			print '<div class="detailRow fieldRow" data-id="'. $bd['recID'] .'" style="'.$font_size.($is_map_popup?'display:none':'').'">'; // && $link_cnt>2 linkRow
			$link_cnt++;

			if($field_name === false && array_key_exists('RelTerm',$bd)){
				print '<div class=detailType>' . htmlspecialchars($bd['RelTerm']) . '</div>';
			}else if($field_name !== false){
				print '<div class=detailType>' . $field_name . '</div>';
			}

			print '<div class="detail" '. $extra_styling .'>';
				if (@$bd['RelatedRecID']) {

					print '<img class="rft" style="background-image:url('.HEURIST_RTY_ICON.$bd['RelatedRecID']['rec_RecTypeID'].')" title="'.$rectypesStructure['names'][$bd['RelatedRecID']['rec_RecTypeID']].'" src="'.HEURIST_BASE_URL.'hclient/assets/16x16.gif">&nbsp;';

					print '<a target=_new href="'.$system->recordLink($bd['RelatedRecID']['rec_ID'])
                        .'" onclick="return link_open(this);">'
						.strip_tags($recTitle,ALLOWED_TAGS).'</a>';
				} else {
					print strip_tags($bd['Title'],ALLOWED_TAGS);
				}
				print '&nbsp;&nbsp;';
				if (@$bd['StartDate']) print htmlspecialchars($bd['StartDate']);
				if (@$bd['EndDate']) print ' until ' . htmlspecialchars($bd['EndDate']);
			print '</div></div>';
        }
        $to_res->close();
    }

    print '</div>';

    //$move_details - array of related records without particular relmarker field
    if(is_array($move_details) && count($move_details) > 0){
        echo '<script>moveRelatedDetails(', json_encode($move_details, JSON_FORCE_OBJECT), ');</script>';
    }

    return $link_cnt;
}

//
// print reverse link
//
function print_linked_details($bib, $link_cnt) 
{
    global $system, $relRT,$ACCESSABLE_OWNER_IDS, $is_map_popup, $is_production, $rectypesStructure, $already_linked_ids;
    
    /* old version without recLinks
    $query = 'select * '.
    'from recDetails '.
    'left join defDetailTypes on dty_ID = dtl_DetailTypeID '.
    'left join Records on rec_ID = dtl_RecID '.
    'where dty_Type = "resource" '.
    'and dtl_DetailTypeID = dty_ID '.
    'and dtl_Value = ' . $bib['rec_ID'].' '.
    'and rec_RecTypeID != '.$relRT.' '.
    'and (rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') OR '.
    ($system->has_access()?'NOT rec_NonOwnerVisibility = "hidden")':'rec_NonOwnerVisibility = "public")').
    ' ORDER BY rec_RecTypeID, rec_Title';
    */

    $ignored_ids = '';
    if(count($already_linked_ids) > 0){
        $ignored_ids = ' AND rl_SourceID NOT IN ('.implode(',', $already_linked_ids).')';
    }

    $mysqli = $system->get_mysqli();

    $query = 'SELECT rec_ID, rec_RecTypeID, rec_Title FROM recLinks, Records '
                .'where rl_TargetID = '.intval($bib['rec_ID'])
                .' AND (rl_RelationID IS NULL) AND rl_SourceID=rec_ID '
                .$ignored_ids
    .' and (rec_OwnerUGrpID in ('.join(',', $ACCESSABLE_OWNER_IDS).') OR '
    .($system->has_access()?'NOT rec_NonOwnerVisibility = "hidden")':'rec_NonOwnerVisibility = "public")')
                .' ORDER BY rec_RecTypeID, rec_Title';    
    
    $res = $mysqli->query($query);

    if ($res==false || $res->num_rows <= 0) return $link_cnt;
    
    if($is_map_popup){
       print '<div class="detailType fieldRow" style="display:none;line-height:21px">Linked from</div>';
       print '<div class="map_popup">';//
    }else{
       print '<div class="detailRowHeader" style="float:left">Linked from</div><div>'; 
       if(!$is_production){
    ?>
        <div style="position: relative;top: -7px;margin-bottom: 5px;">
            <div class=detailType style="width: auto;">Referenced by</div>
            <div class="detail"><a href="<?=HEURIST_BASE_URL?>?db=<?=HEURIST_DBNAME?>&w=all&q=linkedto:<?=$bib['rec_ID']?>" 
                    onClick="top.location.href = this.href; return false;"><b>Show list below as search results</b></a>
                <!--  <br> <i>Search = linkedto:<?=$bib['rec_ID']?> <br>(returns records pointing TO this record)</i> -->
            </div>
        </div>
    <?php
       }
    }

    $usr_font_size = $system->user_GetPreference('userFontSize', 0);
    $font_size = '';
    if(!$is_map_popup && $usr_font_size != 0){
        $usr_font_size = ($usr_font_size < 8) ? 8 
                                              : (($usr_font_size > 18) ? 18 : $usr_font_size);
        $font_size = 'font-size: ' . $usr_font_size . 'px;';
    }

    while ($row = $res->fetch_assoc()) {

        print '<div class="detailRow fieldRow" style="'.$font_size.($is_map_popup?'display:none':'').'">'; // && $link_cnt>2 linkRow
        $link_cnt++;
        
            print '<div style="display:table-cell;width:28px;height:21px;text-align: right;padding-right:4px">'
                    .'<img class="rft" style="background-image:url('.HEURIST_RTY_ICON.$row['rec_RecTypeID'].')" title="'.$rectypesStructure['names'][$row['rec_RecTypeID']].'" src="'.HEURIST_BASE_URL.'hclient/assets/16x16.gif"></div>';

            print '<div style="display: table-cell;vertical-align:top;'
            .($is_map_popup?'max-width:250px;':'').'" class="truncate"><a target=_new href="'
                            .$system->recordLink($row['rec_ID'])
                            .'" onclick="return link_open(this);">'
                .strip_tags($row['rec_Title'],ALLOWED_TAGS).'</a></div>';
            
        print '</div>';
    }
        
    print '</div>';
    return $link_cnt;
    
}

//
// functions below for WOOT and Comments are not used
//
function print_text_details($bib) {
        $cmts = getAllComments($bib["rec_ID"]);
        $result = loadWoot(array("title" => "record:".$bib["rec_ID"]));
        if (! $result["success"] && count($cmts) == 0) return;
        
        $content = "";
        $woot = @$result["woot"];
        if(is_array($woot) && is_array($woot["chunks"]))
        foreach ($woot["chunks"] as $chunk) {
            $content .= $chunk["text"] . " ";
        }
        if (strlen($content) == 0 && count($cmts) == 0) return;

        
        print '<div class=detailRowHeader>Text';
        print_woot_precis($content, $bib);
        print_threaded_comments($cmts);
        print '</div><br>&nbsp;'; // avoid ugly spacing
}

function output_chunker($val) {
    // chunk up the value so that it will be able to line-break if necessary
    //$val = htmlspecialchars($val);    The tags listed are the tags allowed.
    $val = strip_tags($val,'<a><u><i><em><b><strong><sup><sub><small><br><h1><h2><h3><h4><p><ul><li><img>');
    return $val;
    /* it adds word breaker incorrectly, so Arabic words are displayed incorrecly
    return preg_replace('/(\\b.{15,20}\\b|.{20}.*?(?=[\x0-\x7F\xC2-\xF4]))/', '\\1<wbr>', $val);
    */
}

/*
    loadWoot returns:

    {	success
    errorType?
    woot? : {	id
    title
    version
    creator
    permissions : {	type
    userId
    userName
    groupId
    groupName
    } +
    chunks : {	number
    text
    modified
    editorId
    ownerId
    permissions : {	type
    userId
    userName
    groupId
    groupName
    } +
    } +
    }
    }
    Array (
    [id] => 2372
    [title] => record:45171
    [version] => 4
    [creator] => 1
    [permissions] => Array (
    [0] => Array (
    [type] => RW
    [userId] => 1
    [userName] => johnson
    [groupId] =>
    [groupName] => ) )
    [chunks] => Array (
    [0] => Array (
    [number] => 1
    [text] => test private to Ian
    [modified] => 2010-03-08 16:46:08
    [editorId] => 1
    [ownerId] => 1
    [permissions] => Array (
    [0] => Array (
    [type] => RW
    [userId] => 1
    [userName] => johnson
    [groupId] =>
    [groupName] => ) ) ) ) )
    */
function print_woot_precis($content,$bib) {
        if (strlen($content) == 0) return;
        ?>
        <div class=detailRow>
            <div class=detailType>WYSIWYG Text</div>
            <div class=detail>
                <?php
                $content = preg_replace("/<.*?>/", " ", $content);
                if (strlen($content) > 500) {
                    print substr($content, 0, 500) . " ...";
                } else {
                    print $content;
                }
                ?>

                <div><a target=_blank href="<?=HEURIST_BASE_URL?>records/woot/woot.html?db=<?=HEURIST_DBNAME?>&w=record:<?= $bib['rec_ID'] ?>&t=<?= $bib['rec_Title'] ?>">Click here to edit</a></div>
            </div>
        </div>
        <?php
    }


function print_threaded_comments($cmts) {
        if (count($cmts) == 0) return;
        ?>
        <div class=detailRow>
            <div class=detailType>Thread Comments</div>
            <div class=detail>
                <?php
                $printOrder = orderComments($cmts);
                $level = 1;
                foreach ($printOrder as $pair) {
                    $level = 20 * $pair["level"];
                    print '<div style=" font-style:italic; padding: 0px 0px 0px ';
                    print $level;
                    print  'px ;"> ['.$cmts[$pair['id']]["user"]. "] " . $cmts[$pair['id']]["text"] . "</div>";
                }
                ?>
            </div>
        </div>
    <?php
}


function orderComments($cmts) {
    $orderedCmtIds = array();
    $orderErrCmts = array();
    foreach ($cmts as $id => $cmt) {
        //handle root nodes
        if ($cmt['owner'] == 0) {
            // skip deleted or children with deleted parents
            if ($cmt['deleted']) continue;
            $level = $cmts[$id]["level"] = 0;
            array_push($orderedCmtIds,$id);
        }else {	//note this algrithm assumes comments are ordered by date and that a child comment always has a more recent date
            // handle deleted or children of deleted
            if ($cmts[$cmt["owner"]]["deleted"]) $cmt["deleted"] = true;
            if ($cmt["deleted"]) continue;
            $ownerIndex = array_search($cmt["owner"],$orderedCmtIds);
            $insertIndex = count($orderedCmtIds);  //set insertion to end of array as default
            if($ownerIndex === FALSE) {  // breaks assumption write code to fix up the ordering here
                array_push($orderErrCmts,array( 'id' => $id, 'level' => 1));
            }else if ($ownerIndex +1 < $insertIndex) { //not found at the end of the array  note array index +1 = array offset
                if (array_key_exists($cmt["owner"],$cmts) && array_key_exists("level",$cmts[$cmt["owner"]])){
                    $cmts[$id]["level"]  = 1 + $cmts[$cmt["owner"]]["level"] ; //child so increase the level
                    for ($i = $ownerIndex+1; $i < $insertIndex; $i++) {
                        if ( $cmts[$orderedCmtIds[$i]]["level"] < $cmts[$id]["level"]) { //found insertion point
                            $insertIndex = $i;
                            break;
                        }
                    }
                    // insert id at index point
                    array_splice($orderedCmtIds,$insertIndex,0,$id);
                }else{
                    //something is wrong just add it to the end
                    array_push($orderErrCmts,array( 'id' => $id, 'level' => 1));
                }
            }else{ //parent node is at the end of the array so just append
                $cmts[$id]["level"]  = 1 + $cmts[$cmt["owner"]]["level"] ; //child so increase the level
                array_push($orderedCmtIds,$id);
            }
        }
    }
    $ret = array();
    foreach ( $orderedCmtIds as $id) {
        array_push($ret, array( 'id' => $id, 'level' => $cmts[$id]['level']));
    }
    if (count($orderErrCmts)) $orderedCmtIds = array_merge($orderedCmtIds,$orderErrCmts);
    return $ret;
}

//sort array by order_by_date for resource (pointer) details
function __sortResourcesByDate($a, $b)
{
    if($a['rst_DisplayOrder'] == $b['rst_DisplayOrder']){
        
        if($a['dty_ID'] == $b['dty_ID']){
            
            if(@$a['order_by_date']==null ||  @$b['order_by_date']==null){
                 return ($a['dtl_ID'] < $b['dtl_ID'])?-1:1;    
            }else{
                 return ($a['order_by_date'] < $b['order_by_date']) ? -1 : 1;
            }
            
            
        }else{
            return ($a['dty_ID'] < $b['dty_ID'])?-1:1;    
        }
        
    }else {
        return (@$a['rst_DisplayOrder']==null || $a['rst_DisplayOrder'] > $b['rst_DisplayOrder'])?1:-1;
    }
} 

?>
