<?php

    /** 
    *  Main script
    * 
    *  1) System init on server side (see System.php) - connects to database , if db parameter is missed redirects to database selecion page
    *  2) System init on client side (see hapi.js) - init hAPI object
    *  3) Load localization, theme and basic database structure definition 
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
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


    //ini_set('include_path', ini_get('include_path').PATH_SEPARATOR,'/var/www/h4/');
    //ini_set('include_path', ini_get('include_path').PATH_SEPARATOR.'c:/xampp/htdocs/h4/');  //dirname(__FILE__));

    require_once(dirname(__FILE__)."/php/System.php");
    //require_once ('/common/db_structure.php');

    //FOR DEBUG $_REQUEST['db'] = 'artem_todelete11';

    // either init system or redirect to database selection
    $system = new System();
    if(@$_REQUEST['db']){
        // connrect to given database
        if(! $system->init(@$_REQUEST['db']) ){
            //can not connect to given database
            header('Location: php/databases.php');
            //echo "FATAL ERROR!!!! ".print_r($arr, $system->getError());
            exit();
        }
    }else{
        //db parameter is missed redirects to database selecion page 
        header('Location: php/databases.php');
        exit();
    }
    
?>
<html>
    <head>
        <title><?=(@$_REQUEST['db']?$_REQUEST['db']:'').'. '.HEURIST_TITLE ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <link rel=icon href="favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
        
        <link rel="stylesheet" type="text/css" href="ext/fancytree/skin-themeroller/ui.fancytree.css" />
        <link rel="stylesheet" type="text/css" href="ext/font-awesome/css/font-awesome.min.css" />
        <!-- <link rel="stylesheet" type="text/css" href="http://netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.min.css" /> -->
        
        <script type="text/javascript" src="ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>

        <script type="text/javascript" src="ext/layout/jquery.layout-latest.js"></script>

<!--        
        <link rel="stylesheet" type="text/css" href="ext/gridster/jquery.gridster.css" />
        <script type="text/javascript" src="ext/gridster/utils.js"></script>
        <script type="text/javascript" src="ext/gridster/jquery.collision.js"></script>
        <script type="text/javascript" src="ext/gridster/jquery.coords.js"></script>
        <script type="text/javascript" src="ext/gridster/jquery.draggable.js"></script>
        <script type="text/javascript" src="ext/gridster/jquery.gridster.js"></script>
-->
        <link rel="stylesheet" type="text/css" href="ext/gridster/jquery.gridster.all.css" />
        <script type="text/javascript" src="ext/gridster/jquery.gridster.all.js"></script>
        


        
        <!-- jquery-contextmenu (https://github.com/mar10/jquery-ui-contextmenu/) 
             src="//cdn.jsdelivr.net/jquery.ui-contextmenu/1/jquery.ui-contextmenu.min.js"
        -->
        <script type="text/javascript" src="ext/js/jquery.ui-contextmenu.min.js"></script>

        <script type="text/javascript" src="localization.js"></script>
        <script type="text/javascript" src="js/utils.js"></script>
        <!-- script type="text/javascript" src="js/utils_ajax.js"></script -->
        <script type="text/javascript" src="js/recordset.js"></script>
        <script type="text/javascript" src="js/hapi.js"></script>
        <script type="text/javascript" src="js/layout.js"></script>

        <!-- this scripts are loaded explicitely - for debug purposes -->
        <script type="text/javascript" src="apps/file_manager.js"></script>
        <script type="text/javascript" src="apps/viewers/recordDetails.js"></script>
        <script type="text/javascript" src="apps/viewers/recordListExt.js"></script>
        <script type="text/javascript" src="apps/svs_manager.js"></script>
        <script type="text/javascript" src="apps/search_faceted.js"></script>
        <script type="text/javascript" src="apps/search_faceted_wiz.js"></script>
        <script type="text/javascript" src="apps/viewers/app_timemap.js"></script>
        <script type="text/javascript" src="apps/viewers/connections.js"></script>
        <script type="text/javascript" src="apps/search/search.js"></script>
        <script type="text/javascript" src="apps/others/mainMenu.js"></script>
        <script type="text/javascript" src="apps/search/svs_edit.js"></script>
        <script type="text/javascript" src="apps/search/svs_list.js"></script>
        <!--
        <script type="text/javascript" src="apps/search/resultList.js"></script>
        <script type="text/javascript" src="apps/others/mainMenu.js"></script>
         script type="text/javascript" src="apps/search.js"></script>
        <script type="text/javascript" src="apps/profile.js"></script>
        <script type="text/javascript" src="apps/rec_list.js"></script>
        <script type="text/javascript" src="apps/profile_edit.js"></script>
        <script type="text/javascript" src="apps/pagination.js"></script>
        <script type="text/javascript" src="apps/rec_list.js"></script>
        -->
        <!-- DEBUG
        -->

        <script type="text/javascript" src="apps/search/resultListMenu.js"></script>
        <script type="text/javascript" src="apps/rec_actions.js"></script>
        <script type="text/javascript" src="apps/rec_search.js"></script>
        <script type="text/javascript" src="apps/rec_relation.js"></script>
        <script type="text/javascript" src="js/editing_input.js"></script>
        <script type="text/javascript" src="js/editing.js"></script>

        <!-- move to profile.js dynamic load -->
        <script type="text/javascript" src="ext/js/themeswitchertool.js"></script>
        <script type="text/javascript" src="ext/yoxview/yoxview-init.js"></script>

        <!-- to do -->
        <script type="text/javascript" src="layout_default.js"></script>

        <script type="text/javascript">

            $(function() {

                //overwrite the standard show method
                var orgShow = $.fn.show;
                $.fn.show = function()
                {
                    orgShow.apply( this, arguments );
                    $(this).trigger( 'myOnShowEvent' );
                    return this;
                };

                /*overwrite the standard empty method
                var _empty = $.fn.empty;
                $.fn.empty = function () {
                return this.each(function() {
                $( "*", this ).each(function() {
                $( this ).triggerHandler( "remove" );
                });
                return _empty.call( $(this) );
                });
                };
                */

                //Performs hAPI initialization for given database
                window.HAPI4 = new hAPI('<?=$_REQUEST['db']?>',
                    function(success) //callback function of hAPI initialization
                    {
                        if(success)  //system is inited
                        {
                            var prefs = window.HAPI4.get_prefs();
                            //loads localization
                            window.HR = window.HAPI4.setLocale(prefs['layout_language']); 

                            //loads theme (style for layout) - SINCE WE BACK TO H3 - always use default theme
                            if(prefs['layout_theme'] && !(prefs['layout_theme']=="heurist" || prefs['layout_theme']=="base")){
                                cssLink = $('<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/themes/'+
                                    prefs['layout_theme']+'/jquery-ui.css" />');
                            }else{
                                //default BASE theme
                                cssLink = $('<link rel="stylesheet" type="text/css" href="ext/jquery-ui-1.10.2/themes/'+prefs['layout_theme']+'/jquery-ui.css" />');
                            }
                            //add theme link to html header
                            $("head").append(cssLink);
                            $("head").append($('<link rel="stylesheet" type="text/css" href="style3.css?t='+(new Date().getTime())+'">'));
                            //$("head").append($('<link rel="stylesheet" type="text/css" href="../../common/css/global.css?t='+(new Date().getTime())+'">'));

                            
                            //top.HAPI4.database+'. HEURIST_TITLE
                            window.document.title = window.document.title+' v'+top.HAPI4.sysinfo.version;

                            //load database structure (record types, field types, terms) definitions
                            window.HAPI4.SystemMgr.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
                                if(response.status == top.HAPI4.ResponseStatus.OK){
                                    top.HEURIST4.rectypes = response.data.rectypes;
                                    top.HEURIST4.terms = response.data.terms;
                                    top.HEURIST4.detailtypes = response.data.detailtypes;

                                    //in layout.js - load layout #101
                                    
                                    var layoutid = '<?=@$_REQUEST['ll']?>';
                                    if(top.HEURIST4.util.isempty(layoutid)){
                                        layoutid = top.HAPI4.get_prefs('layout_id');
                                        if(top.HEURIST4.util.isempty(layoutid)){
                                            layoutid = "L01";
                                        }
                                    }
                                    
                                    appInitAll(layoutid, "#layout_panes");
                                    /*                        
                                    //get all terms and rectypes   terms:0,
                                    window.HAPI4.SystemMgr.get_defs({terms:'all'}, function(response){
                                    if(response.status == top.HAPI4.ResponseStatus.OK){
                                    top.HEURIST4.terms = response.data.terms;

                                    //in layout.js
                                    appInitAll("l01", "#layout_panes");

                                    }else{
                                    top.HEURIST4.util.redirectToError(response.message);
                                    }
                                    });
                                    */
                                    
                                    //perform search in case parameter "q" is defined
                                    var qsearch = '<?=@$_REQUEST['q']?>';
                                    if(!top.HEURIST4.util.isempty(qsearch)){
                                        var qdomain = '<?=@$_REQUEST['w']?>';
                                        if(top.HEURIST4.util.isempty(qdomain)) qdomain = 'a';
                                        var request = {q: qsearch, w: qdomain, f: 'map', source:'init' };
                                        //top.HEURIST4.query_request = request;
                                        setTimeout(function(){top.HAPI4.RecordMgr.search(request, $(document));}, 3000);
                                    }else{
                                        var init_search = top.HEURIST.displayPreferences['defaultSearch']; 
                                        if(!top.HEURIST4.util.isempty(init_search)){
                                            var request = {q: init_search, w: 'a', f: 'map', source:'init' };
                                            setTimeout(function(){top.HAPI4.RecordMgr.search(request, $(document));}, 3000);
                                        }
                                    }
                                    
                                }else{
                                    top.HEURIST4.util.redirectToError(response.message);
                                }
                            });
                            /*window.HAPI4.SystemMgr.get_defs({detailtypes:'all', mode:2}, function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){
                            top.HEURIST4.detailtypes = response.data.detailtypes;
                            }else{

                            }
                            });*/

                        }else{
                            //top.HEURIST4.util.redirectToError
                            alert("Can not initialize system");
                        }
                });

                //definition of ABOUT dialog
                $( "#heurist-about" ).dialog(
                    {
                        autoOpen: false,
                        height: 350,
                        width: 450,
                        modal: true,
                        resizable: false,
                        draggable: false,
                        hide: {
                            effect: "explode",
                            duration: 1000
                        }
                    }
                );
                
                
                // OLD H3 stuff
                top.HEURIST.loadScript(top.HEURIST.basePath+"common/php/loadUserInfo.php?db=" + window.HAPI4.database);
                top.HEURIST.baseURL = window.HAPI4.basePathOld;
                top.HEURIST.iconBaseURL = window.HAPI4.iconBaseURL;
                top.HEURIST.database = {  name: window.HAPI4.database };
                
            });
            
        </script>

    </head>
    <body style="background-color:#c9c9c9">
    
    
        <!-- These are old H3 stuff - it needs for support old features in popups
        <script src="../../common/php/loadHAPI.php"></script> -->
        <script src="../../common/js/utilsLoad.js"></script> 
        <script src="../../common/php/displayPreferences.php"></script>
        <!-- 
        <script src="../../common/php/getMagicNumbers.php"></script>
        These are old H3 stuff - it needs for support old features in popups -->
    
        <div id="layout_panes" style="height:100%">
            &nbsp;
        </div>

        <div id="heurist-about" title="About">
            <div class='logo'></div>
            <h4>Heurist academic knowledge management system</h4>
            <p style="margin-top: 1em;">version     <?=HEURIST_VERSION?></p>
            <p style="margin-top: 1em;">
                author: Dr Ian Johnson<br/>
                programmers: Stephen White and others...</p>

            <p style="margin-top: 1em;">Copyright (C) 2005-2015 <a href="http://sydney.edu.au/arts/eresearch/" target="_blank">University of Sydney</a></p>

            <p style="font-size: x-small;margin-top: 1em;">
                Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
                in compliance with the License. You may obtain a copy of the License at
                <a href="http://www.gnu.org/licenses/gpl-3.0.txt" target="_blank">http://www.gnu.org/licenses/gpl-3.0.txt</a></p>

            <p style="font-size: x-small;margin-top: 1em;">
                Unless required by applicable law or agreed to in writing, software distributed under the License
                is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
                or implied. See the License for the specific language governing permissions and limitations under
                the License.
            </p>
        </div>

        <div id="heurist-dialog">
        </div>
    </body>
</html>
