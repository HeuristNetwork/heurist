<?php

    /** 
    *  Standalone record edit page. It may be used separately or wihin widget (in iframe)
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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


    require_once(dirname(__FILE__)."/System.php");
    require_once(dirname(__FILE__).'/common/db_structure.php');

    $system = new System();

    if(@$_REQUEST['db']){
        if(! $system->init(@$_REQUEST['db']) ){
            //@todo - redirect to error page
            print_r($system->getError(),true);
            exit();
        }
    }else{
        header('Location: php/databases.php');
        exit();
    }
?>
<html>
    <head>
        <title><?=HEURIST_TITLE ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

        <link rel="stylesheet" type="text/css" href="../ext/jquery-ui-1.10.2/themes/base/jquery-ui.css" />
        <link rel="stylesheet" type="text/css" href="../style.css">

        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        <script type="text/javascript" src="../ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>

        <!-- @todo load this 4 scripts dynamically -->
        <script type="text/javascript" src="../apps/search/search.js"></script>
        <!-- script type="text/javascript" src="../apps/rec_list.js"></script -->
        <script type="text/javascript" src="../apps/editing/rec_search.js"></script>
        <script type="text/javascript" src="../apps/editing/rec_relation.js"></script>
        <script type="text/javascript" src="../apps/editing/editing_input.js"></script>

        <script type="text/javascript" src="editing.js"></script>

        <script type="text/javascript" src="../js/recordset.js"></script>
        <script type="text/javascript" src="../js/utils.js"></script>
        <script type="text/javascript" src="../js/hapi.js"></script>

        <script type="text/javascript">
            <?php
                //@ todo - load this stuff in hEditing
                print "top.HEURIST4.rectypes = ".json_encode( dbs_GetRectypeStructures($system, null, 0) ).";\n";
                print "top.HEURIST4.terms = ".json_encode( dbs_GetTerms($system ) ).";\n";
            ?>

            var editing;

            $(document).ready(function() {

                if(!top.HAPI4){
                    top.HAPI4 = new hAPI('<?=$_REQUEST['db']?>');//, <?=json_encode($system->getCurrentUser())?> );
                }

                var $container = $("<div>").appendTo($("body"));

                editing = new hEditing($container);

                var q = '<?=@$_REQUEST['q']?>';


                //t:26 f:85:3313  f:1:building
                if( q )
                {
                    top.HAPI4.RecordMgr.search({q: q, w: "all", f:"structure", l:1},
                        function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){

                                var recset = new hRecordSet(response.data);
                                editing.load(recset);

                            }else{
                                alert(response.message);
                            }
                        }
                    );

                }else{ //add new record

                    var rt = '<?=@$_REQUEST['rt']?>';

                    top.HAPI4.RecordMgr.add( {rt:rt}, //ro - owner,  rv - visibility
                        function(response){
                            if(response.status == top.HAPI4.ResponseStatus.OK){

                                var recset = new hRecordSet(response.data);
                                editing.load(recset);

                            }else{
                                alert(response.message);
                            }
                        }

                    );


                }

                /*
                $("#btn_save").on({
                click: editing.save()
                });*/


                //height:100%;

            });
        </script>

    </head>
    <body>
    </body>
</html>
