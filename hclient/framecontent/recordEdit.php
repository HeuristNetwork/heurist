<?php

    /**
    *  Standalone record edit page. It may be used separately or wihin widget (in iframe)
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
    require_once(dirname(__FILE__)."/initPage.php");
?>
<!-- <?php echo PDIR;?> -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/rec_relation.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>ext/layout/jquery.layout-latest.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
        <!--script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script-->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/media_viewer.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>ext/yoxview/yoxview-init.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>common/js/temporalObjectLibrary.js"></script>
        
        
        <script type="text/javascript">
            var $container;
            // Callback function on page initialization
            function onPageInit(success){
                if(success){
                    
                    // OLD H3 stuff - need to call edit rectype structure
                    if(window.hWin){
                        win = window.hWin;
                    }else{
                        win = window;
                    }
                    
                    if(win.HEURIST && win.HAPI4.baseURL){
                        win.HEURIST.baseURL  = win.HAPI4.baseURL;
                        win.HEURIST.loadScript(win.HAPI4.baseURL+"common/php/loadUserInfo.php?db=" + win.HAPI4.database);
                        win.HEURIST.iconBaseURL = win.HAPI4.iconBaseURL;
                        win.HEURIST.database = {  name: win.HAPI4.database };
                    }
                    // end OLD H3 stuff
                    
                    
                    $container = $('<div>').appendTo($("body"));
                    
                    var isPopup = (window.hWin.HEURIST4.util.getUrlParameter('popup', window.location.search)==1);
                        
                    var rec_rectype = window.hWin.HEURIST4.util.getUrlParameter('rec_rectype', window.location.search);
                    var new_record_params = {};
                    if(rec_rectype>0){
                        new_record_params['rt'] = rec_rectype;
                        new_record_params['ro'] = window.hWin.HEURIST4.util.getUrlParameter('rec_owner', window.location.search);
                        new_record_params['rv'] = window.hWin.HEURIST4.util.getUrlParameter('rec_visibility', window.location.search);
                        new_record_params['tag'] = window.hWin.HEURIST4.util.getUrlParameter('tag', window.location.search);
                    }

//todo use ui.openRecordEdit                    
                    //hidden result list, inline edit form
                    var options = {
                        select_mode: 'manager',
                        edit_mode: 'editonly',
                        in_popup_dialog: isPopup,
                        new_record_params: new_record_params,
                        layout_mode:'<div class="ent_wrapper editor">'
                            + '<div class="ent_content_full recordList"  style="display:none;"/>'

                            + '<div class="ent_header editHeader"></div>'
                            + '<div class="editFormDialog ent_content">'
                                    + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                                    //+ '<div class="ui-layout-south><div class="editForm-toolbar"/></div>'
                            + '</div>'
                            + '<div class="ent_footer editForm-toolbar"/>'
                        +'</div>',
                        onInitFinished:function(){
                            
                            var q = window.hWin.HEURIST4.util.getUrlParameter('q', window.location.search);
                            var recID = window.hWin.HEURIST4.util.getUrlParameter('recID', window.location.search);
                            
                            if(!q && recID>0){
                                q = 'ids:'+recID;
                            }
                            
                            if(q){
                            
                                window.hWin.HAPI4.RecordMgr.search({q: q, w: "e",  //all records including temp
                                                limit: 100,
                                                needall: 1, //it means return all recors - no limits
                                                detail: 'ids'}, 
                                function( response ){
                                    //that.loadanimation(false);
                                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                        
                                        var recset = new hRecordSet(response.data);
                                        if(recset.length()>0){
                                            $container.manageRecords('updateRecordList', null, {recordset:recset});
                                            $container.manageRecords('addEditRecord', (recID>0)?recID:recset.getOrder()[0]);
                                        }else{ // if(isPopup){
                                            window.close();  //nothing found
                                        }
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                        if(isPopup){ window.close(); }
                                    }

                                });
                            
                            }else{
                                
                                $container.manageRecords('addEditRecord',-1);
                            }                            
                            
                        }
                    }
                    
                    $container.manageRecords( options ).addClass('ui-widget');
                }
            }
            
            function onBeforeClose(){
                $container.manageRecords('saveUiPreferences');
            }            
        </script>
    </head>
    <body>
        <script src="<?=HEURIST_BASE_URL?>common/js/utilsLoad.js"></script>
        <script src="<?=HEURIST_BASE_URL?>common/php/displayPreferences.php"></script>
    
    </body>
</html>
