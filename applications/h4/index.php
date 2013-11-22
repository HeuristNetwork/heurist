<?php
/**
*  Main script
* 
*  1) System init on server side (see System.php) - connects to database , if db parameter is missed redirects to database selecion page
*  2) System init on client side (see hapi.js) - init hAPI object
*  3) Load localization, theme and basic database structure definition 
* 
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
        //@todo - redirect to error page
        echo "FATAL ERROR!!!! ".print_r($arr, $system->getError());
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
  <title><?=HEURIST_TITLE ?></title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

    <script type="text/javascript" src="ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
    <script type="text/javascript" src="ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>

    <script type="text/javascript" src="ext/layout/jquery.layout-latest.js"></script>

    <script type="text/javascript" src="localization.js"></script>
    <script type="text/javascript" src="js/utils.js"></script>
    <script type="text/javascript" src="js/recordset.js"></script>
    <script type="text/javascript" src="js/hapi.js"></script>
    <script type="text/javascript" src="js/layout.js"></script>

    <script type="text/javascript" src="apps/tag_assign.js"></script>
    <script type="text/javascript" src="apps/rec_viewer.js"></script>
    <script type="text/javascript" src="apps/search_links.js"></script>
    <script type="text/javascript" src="apps/profile.js"></script>
  <!-- DEBUG
  -->

    <script type="text/javascript" src="apps/rec_actions.js"></script>
    <script type="text/javascript" src="apps/rec_search.js"></script>
    <script type="text/javascript" src="apps/rec_relation.js"></script>
    <script type="text/javascript" src="js/editing_input.js"></script>
    <script type="text/javascript" src="js/editing.js"></script>

    <!-- move to profile.js dynamic load -->
    <script type="text/javascript" src="ext/js/themeswitchertool.js"></script>

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
        window.HAPI = new hAPI('<?=$_REQUEST['db']?>',
        function(success) //callback function of hAPI initialization
        {
            if(success)  //system is inited
            {
                var prefs = window.HAPI.get_prefs();
                //loads localization
                window.HR = window.HAPI.setLocale(prefs['layout_language']); 

                //loads theme (style for layout)
                if(prefs['layout_theme'] && prefs['layout_theme']!="base" ){
                    cssLink = $('<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/themes/'+
                            prefs['layout_theme']+'/jquery-ui.css" />');
                }else{
                    //default BASE theme
                    cssLink = $('<link rel="stylesheet" type="text/css" href="ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />');
                }
                //add theme link to html header
                $("head").append(cssLink);
                $("head").append($('<link rel="stylesheet" type="text/css" href="style3.css">'));


                //load database structure (record types, field types, terms) definitions
                window.HAPI.SystemMgr.get_defs({rectypes:'all', mode:2}, function(response){
                    if(response.status == top.HAPI.ResponseStatus.OK){
                        top.HEURIST.rectypes = response.data.rectypes;

                        //get all terms and rectypes   terms:0,
                        window.HAPI.SystemMgr.get_defs({terms:'all'}, function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){
                                top.HEURIST.terms = response.data.terms;

                                //in layout.js
                                appInitAll("l01", "#layout_panes");

                            }else{
                                top.HEURIST.util.redirectToError(response.message);
                            }
                        });

                    }else{
                        top.HEURIST.util.redirectToError(response.message);
                    }
                });
            }else{
                //top.HEURIST.util.redirectToError
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
    });

  </script>

</head>
<body>
  <div id="layout_panes">
  </div>

  <div id="heurist-about" title="About">
    <div class='logo'></div>
    <h4>Heurist academic knowledge management system</h4>
    <p style="margin-top: 1em;">version     3.1.0</p>
    <p style="margin-top: 1em;">
        author: Dr Ian Johnson<br/>
        programmers: Stephen White and others...</p>

    <p style="margin-top: 1em;">Copyright (C) 2005-2013 <a href="http://sydney.edu.au/arts/eresearch/" target="_blank">University of Sydney</a></p>

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
