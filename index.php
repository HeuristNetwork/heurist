<?php

/**
* Main script initializing Heurist layout and performing initial search of parameter q is defined
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

if( @$_REQUEST['recID'] || @$_REQUEST['recid'] ){
    if(@$_REQUEST['recID']){
        $recid = $_REQUEST['recID'];    
    }elseif(@$_REQUEST['recid']){
        $recid = $_REQUEST['recid'];        
    }
    if(@$_REQUEST['fmt']){
        $format = $_REQUEST['fmt'];    
    }elseif(@$_REQUEST['format']){
        $format = $_REQUEST['format'];        
    }else{
        $format = 'xml';
    }
    header('Location: redirects/resolver.php?db='.@$_REQUEST['db'].'&recID='.$recid.'&fmt='.$format);
    return;
}


define('IS_INDEX_PAGE',true);
define('PDIR','');

require_once(dirname(__FILE__)."/hclient/framecontent/initPage.php");

if($_SERVER["SERVER_NAME"]=='localhost'||$_SERVER["SERVER_NAME"]=='127.0.0.1'){
        print '<script type="text/javascript" src="ext/fancytree/jquery.fancytree-all.min.js"></script>';
}else{
        print '<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.16.1/jquery.fancytree-all.min.js"></script>';
}   
?>

        <!-- it is needed in preference dialog -->
        <link rel="stylesheet" type="text/css" href="ext/fancytree/skin-themeroller/ui.fancytree.css" />

        <script type="text/javascript" src="ext/layout/jquery.layout-latest.js"></script>

        <!-- Gridster layout is an alternative similar to Windows tiles, not useful except with small
        number of widgets. Currently it is commented out of the code in layout_default.js -->

        <!-- for gridster layout, development version - remove comments to use
        <link rel="stylesheet" type="text/css" href="ext/gridster/jquery.gridster.css" />
        <script type="text/javascript" src="ext/gridster/utils.js"></script>
        <script type="text/javascript" src="ext/gridster/jquery.collision.js"></script>
        <script type="text/javascript" src="ext/gridster/jquery.coords.js"></script>
        <script type="text/javascript" src="ext/gridster/jquery.draggable.js"></script>
        <script type="text/javascript" src="ext/gridster/jquery.gridster.js"></script>
        -->
        <!-- for gridster layout, production (minimised) version - remove comments to use
        <link rel="stylesheet" type="text/css" href="ext/gridster/jquery.gridster.all.css" />
        <script type="text/javascript" src="ext/gridster/jquery.gridster.all.js"></script>
        -->

        <script type="text/javascript" src="ext/js/jquery.ui-contextmenu.min.js"></script>
        <!-- script type="text/javascript" src="ext/js/moment.min.js"></script -->
        <script type="text/javascript" src="ext/js/date.format.js"></script>

        <!-- init layout and loads all apps.widgets -->
        <script type="text/javascript" src="hclient/core/layout.js"></script>
        <!-- array of possible layouts -->
        <script type="text/javascript" src="layout_default.js"></script>

        <!-- script type="text/javascript" src="js/hintDiv.js"></script -->

        <script type="text/javascript" src="hclient/widgets/dropdownmenus/help_tips.js"></script>

        <script type="text/javascript" src="common/js/temporalObjectLibrary.js"></script>

        <!-- DOCUMENTATION TODO: explain this -->
        <!-- these scripts are loaded explicitely - for debug purposes -->
        <script type="text/javascript" src="hclient/widgets/viewers/recordListExt.js"></script>
        <script type="text/javascript" src="hclient/widgets/search/search_faceted.js"></script>
        <script type="text/javascript" src="hclient/widgets/search/search_faceted_wiz.js"></script>
        <script type="text/javascript" src="hclient/widgets/viewers/app_timemap.js"></script>
        <script type="text/javascript" src="hclient/widgets/search/search.js"></script>
        <script type="text/javascript" src="hclient/widgets/dropdownmenus/mainMenu.js"></script>
        <script type="text/javascript" src="hclient/widgets/search/svs_edit.js"></script>
        <script type="text/javascript" src="hclient/widgets/search/svs_list.js"></script>
        <script type="text/javascript" src="hclient/widgets/viewers/resultList.js"></script>

        <script type="text/javascript" src="hclient/widgets/viewers/staticPage.js"></script>
        
        <script type="text/javascript" src="hclient/widgets/digital_harlem/dh_search.js"></script>
        <script type="text/javascript" src="hclient/widgets/digital_harlem/dh_maps.js"></script>
        <script type="text/javascript" src="hclient/widgets/viewers/connections.js"></script>

        <!-- DEBUG -->

        <script type="text/javascript" src="hclient/widgets/profile/profile_login.js"></script>
        <script type="text/javascript" src="hclient/widgets/viewers/resultListMenu.js"></script>
        <script type="text/javascript" src="hclient/widgets/editing/editing_input.js"></script> <!-- move to common js???? -->
        <!-- todo: load dynamically
        <script type="text/javascript" src="hclient/widgets/editing/rec_search.js"></script>
        <script type="text/javascript" src="hclient/widgets/editing/rec_relation.js"></script>
        -->

        <!-- move to profile.js dynamic load -->
        <script type="text/javascript" src="ext/js/themeswitchertool.js"></script>

        <!--  media viewer - however it is not used at the moment 
        <script type="text/javascript" src="ext/yoxview/yoxview-init.js"></script>
        -->

        <!-- os, browser detector -->
        <script type="text/javascript" src="ext/js/platform.js"></script>
        
        <script type="text/javascript">

           function onPageInit(success){

                if(!success) return;
                
                // OLD H3 stuff
                if(window.HEURIST){
                    window.HEURIST.baseURL_V3  = window.HAPI4.basePathV3;
                    window.HEURIST.loadScript(window.HAPI4.basePathV3+"common/php/loadUserInfo.php?db=" + window.HAPI4.database);
                    window.HEURIST.iconBaseURL = window.HAPI4.iconBaseURL;
                    window.HEURIST.database = {  name: window.HAPI4.database };
                }
                
                //
                // cfg_widgets and cfg_layouts are defined in layout_default.js
                //
                window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, cfg_layouts);

                
                $( "#heurist-about" ).dialog("close");
                
                //
                // init layout
                //
                window.hWin.HAPI4.LayoutMgr.appInitAll( window.hWin.HAPI4.sysinfo['layout'], "#layout_panes");
                
//console.log('ipage layout '+(new Date().getTime() / 1000 - _time_debug));
_time_debug = new Date().getTime() / 1000;
                
                onInitCompleted_PerformSearch();
           }
           
           //
           // init about dialog
           //
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

           //
           // Performs inital search: parameters from request or from user preferences
           //
           function onInitCompleted_PerformSearch(){
               
<?php
     $db_total_records = mysql__select_value($system->get_mysqli(), 'select count(*) from Records');
     echo 'var db_total_records='.($db_total_records>0?$db_total_records:0).';';  
?>
               
               window.hWin.HAPI4.sysinfo.db_total_records = db_total_records;
               
                if(window.hWin.HAPI4.sysinfo['layout']=='H4Default'){
                    //switch to FAP tab if q parameter is defined
                    if(db_total_records<1){
                        showTipOfTheDay(false);   
                    }else{
                        window.hWin.HAPI4.LayoutMgr.putAppOnTopById('FAP');
                    }
                }else if(db_total_records<1){
                    showTipOfTheDay(false);
                }
                //perform search in the case that parameter "q" is defined
                var qsearch = '<?php echo str_replace("'","\'",@$_REQUEST['q']); ?>';
                if(db_total_records>0 && !window.hWin.HEURIST4.util.isempty(qsearch)){
                    var qdomain = '<?=@$_REQUEST['w']?>';
                    var rules = '<?=@$_REQUEST['rules']?>';
                    if(window.hWin.HEURIST4.util.isempty(qdomain)) qdomain = 'a';
                    var request = {q: qsearch, w: qdomain, f: 'map', rules: rules, source:'init' };
                    //window.hWin.HEURIST4.query_request = request;
                    setTimeout(function(){
                            window.hWin.HAPI4.SearchMgr.doSearch(document, request);
                    }, 3000);
                }else if(!(window.hWin.HAPI4.sysinfo['layout']=='DigitalHarlem' 
                        || window.hWin.HAPI4.sysinfo['layout']=='DigitalHarlem1935')){
                            
                    var init_search = window.hWin.HEURIST?window.hWin.HEURIST.displayPreferences['defaultSearch']:'';
                    if(!window.hWin.HEURIST4.util.isempty(init_search)){
                        var request = {q: init_search, w: 'a', f: 'map', source:'init' };
                        setTimeout(function(){
                            window.hWin.HAPI4.SearchMgr.doSearch(document, request);
                        }, 3000);
                    }else{
                        //trigger search finish to init some widgets
                        $(document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, null );   
                    }
                }

                //if database is empty show welcome screen
                //if(!(window.hWin.HAPI4.sysinfo.db_total_records>0)){
                //    showTipOfTheDay(false);
                //}
 
var fin_time = new Date().getTime() / 1000;
//console.log('ipage finished '+( fin_time - _time_debug)+ '  total: '+(fin_time-_time_start));

                $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);

                var os = platform?platform.os.family.toLowerCase():'';
                if(os.indexOf('android')>=0 || os.indexOf('ios')>=0){ //test || os.indexOf('win')>=0
                    window.hWin.HEURIST4.msg.showElementAsDialog(
                        {element:document.getElementById('heurist-platform-warning'),
                         width:480, height:220,
                         title: 'Welcome',
                        buttons:{'Close':function(){ $(this).dialog( 'close' )} } });                                  }

            }

        </script>

    </head>
    <body style="background-color:#c9c9c9">


    <?php if(@$_REQUEST['ll']!='WebSearch'){?>
        <!-- These are old H3 stuff - needed to support existing features in popups -->
        <script>top.installDirFromH4="<?=HEURIST_BASE_URL?>";</script>
        <script src="<?=HEURIST_BASE_URL?>common/js/utilsLoad.js"></script>
        <script src="<?=HEURIST_BASE_URL?>common/php/displayPreferences.php"></script>
    <?php } ?>
        <div id="layout_panes" style="height:100%">
            &nbsp;
        </div>

        <div id="heurist-about" style="width:300px;display:none;">
            <div class='logo'></div>
            <h4>Heurist Academic Knowledge Management System</h4>
            <p style="margin-top:1em;">version <?=HEURIST_VERSION?></p>
            <p style="margin-top:1em; display:none;" class="fullmode">
                author: Dr Ian Johnson<br/>
                programmers: Artem Osmakov, Tom Murtagh, Kim Jackson, Stephen White and others...</p>

            <p style="margin-top: 1em;">Copyright (C) 2005-2016 <a href="http://sydney.edu.au/arts/" target="_blank">University of Sydney</a></p>

            <p style="font-size: x-small; margin-top:1em; display:none;" class="fullmode">
                Licensed under the GNU General Public License Version 3.0 (the "License"); you may not use this file except
                in compliance with the License. You may obtain a copy of the License at
                <a href="http://www.gnu.org/licenses/gpl-3.0.txt" target="_blank">http://www.gnu.org/licenses/gpl-3.0.txt</a></p>

            <p style="font-size: x-small; margin-top:1em; display:none;" class="fullmode">
                Unless required by applicable law or agreed to in writing, software distributed under the License
                is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
                or implied. See the License for the specific language governing permissions and limitations under
                the License.
            </p>
        </div>
        
        <div id="heurist-platform-warning" style="display:none;">
            <p style="padding:10px">Heurist is designed primarily for use with a keyboard and mouse. Tablets are not fully supported at this time, except for data collection on Android (see FAIMS in the Help system).</p>

            <p style="padding:10px">Please contact the Heurist developers (info at HeuristNetwork dot org) for further information or to express an interest in a tablet version</p>
        </div> 

        <div id="heurist-dialog">
        </div>
    </body>
</html>
