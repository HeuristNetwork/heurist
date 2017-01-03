
/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* selectLinkField.js
* select link field type (pointer or relationship marker) and add it recordtype structure
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
$.getMultiScripts = function(arr, path) {
    var _arr = $.map(arr, function(scr) {
        return $.getScript( (path||"") + scr );
    });

    _arr.push($.Deferred(function( deferred ){
        $( deferred.resolve );
    }));

    return $.when.apply($, _arr);
}    

$(document).ready(function() {

    var rty_ID, target_ID;

    if(!top){
        return;
    }else if($.isFunction(top.HEURIST.is_admin)){
        onScriptsReady();
    }else{

        dbname = top.HEURIST.getQueryVar("db");

        $.getMultiScripts([
            //'../../common/js/utilsLoad.js',
            '../../../common/php/displayPreferences.php?db='+dbname,
            '../../../common/php/getMagicNumbers.php?db='+dbname,
            '../../../common/php/loadUserInfo.php?db='+dbname,
            '../../../common/php/loadCommonInfo.php?db='+dbname,
            //'../../../records/edit/editRecord.js'
            //'../../common/php/loadHAPI.php?db='+dbname,
        ])
        .done(onScriptsReady)
        .fail(function(error) {
            console.log(error);         
            //alert('Error on script laoding '+error);
        }).always(function() {
            // always called, both on success and error
        }); 

    }


    function onScriptsReady(){

        $('#hint_more_info1').click(function(){ 
            top.HEURIST4.msg.showElementAsDialog(
                {elements: document.getElementById('div_more_info1'), title:'More info', 'close-on-blur':true}); 
        });
        $('#hint_more_info2').click(function(){ 
            top.HEURIST4.msg.showElementAsDialog(
                {elements: document.getElementById('div_more_info2'), title:'More info', 'close-on-blur':true}); 
        });

        $('input[name="ft_type"]').change(function(){
            if(!target_ID){
                top.HEURIST4.msg.showMsgDlg('Select target record type first');   
            }else{
                $('#btnSelect').removeProp('disabled');
                $('#btnSelect').css('color','black');
            }
        });

        $('#btnSelect').click( editDetailType );

        //rectype new field to be added to
        rty_ID = top.HEURIST4.util.getUrlParameter("rty_ID", document.location.search);
        //rectype to be related (constraint for pointers and relmarker target rectype)
        target_ID = top.HEURIST4.util.getUrlParameter("target_ID", document.location.search);

        var rt_selector = $('#sel_target_rectype_id');
        top.HEURIST4.ui.createRectypeSelect(rt_selector[0],null,'Select target record type');
        rt_selector.change(
            function(){
                var sDialogTitle = 'Creating link from '+ top.HEURIST4.rectypes.names[rty_ID];
                target_ID = $(this).val();
                if(!target_ID){
                    top.HEURIST4.util.setDisabled($('.ft_selfield'), true);                        
                    $('.ft_selfield').css('color','lightgray');

                }else{

                    //change title in parent dialog
                    sDialogTitle = sDialogTitle + ' to '+  top.HEURIST4.rectypes.names[target_ID];                                             
                    top.HEURIST4.util.setDisabled($('.ft_selfield'), false);                        
                    $('.ft_selfield').css('color', $('#sel_target_rectype_id').css('color'));
                    _getLinkFields();
                    $('#sel_resource_fields').val('');
                    $('#sel_relmarker_fields').val('');
                    $('#t_resourse').attr('checked', true);
                }
                $(window.frameElement).parents('.ui-dialog').find('.ui-dialog-title').text(sDialogTitle);
            }
        );
        if(target_ID){
            rt_selector.val(target_ID);  
        } 
        rt_selector.change();

        if(!top.HEURIST4.rectypes.typedefs[rty_ID]){
            top.HEURIST4.msg.showMsgErr('Parameter for record type ID ( '+top.HEURIST4.rectypes.typedefs[rty_ID]+' ) is not defined or invalid');
            window.close(false);
        }

        if(!top.HEURIST.detailTypes){
            top.HEURIST.rectypes = top.HEURIST4.rectypes;  
            top.HEURIST.detailTypes = top.HEURIST4.detailtypes;  
            top.HEURIST.terms = top.HEURIST4.terms;  
        }

    }

    function _getLinkFields(){    
        //find existing field types that already refer target_ID
        var dty_ID, detailTypes = top.HEURIST4.detailtypes.typedefs;
        var idx_Type = detailTypes['fieldNamesToIndex']['dty_Type'];
        var idx_Ptr  = detailTypes['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
        var idx_Name = detailTypes['fieldNamesToIndex']['dty_Name'];
        var idx_NameInRecType = top.HEURIST4.rectypes.typedefs['dtFieldNamesToIndex']['rst_DisplayName'];

        var aPointers = [{key:0, title:'Create new field'}], 
        aRelMarkers = [{key:0, title:'Create new field'}], 
        cnt_ptrs = 0, cnt_relmarkers = 0;

        for(dty_ID in detailTypes){
            if(dty_ID>0){
                var dty_Type = detailTypes[dty_ID].commonFields[idx_Type];
                if(dty_Type==='resource' || dty_Type==='relmarker'){

                    var rts = detailTypes[dty_ID].commonFields[idx_Ptr].split(',');
                    if(rts.indexOf(target_ID)>=0){

                        //if this field type already in rectype
                        var already_inuse = false;
                        if(top.HEURIST4.rectypes.typedefs[rty_ID]['dtFields'][dty_ID]){
                            already_inuse = true;
                        }

                        var option_item  = {key:dty_ID, title:detailTypes[dty_ID].commonFields[idx_Name], disabled:already_inuse};
                        if(already_inuse){
                            if (detailTypes[dty_ID].commonFields[idx_Name] == top.HEURIST4.rectypes.typedefs[rty_ID]['dtFields'][dty_ID][idx_NameInRecType])
                            { // field name and base field name are the same
                                option_item.title = option_item.title +' (already connected)';
                            }
                            else {                            
                                option_item.title = option_item.title
                                +' (connected as "'
                                +top.HEURIST4.rectypes.typedefs[rty_ID]['dtFields'][dty_ID][idx_NameInRecType]+'")';
                            }
                        }

                        if(dty_Type==='resource'){
                            aPointers.push(option_item);
                            if(!already_inuse) cnt_ptrs++;
                        }else{
                            aRelMarkers.push(option_item);
                            if(!already_inuse) cnt_relmarkers++;
                        }        
                    }
                }
            }
        }

        // Complicated message removed by Ian 3/1/17 - they are just confusing
        if(aPointers.length==1){
            aPointers[0].title /* += ('.(No field types that refer to "'+top.HEURIST4.rectypes.names[target_ID]+'")') */ ;
        }else if(cnt_ptrs==0){
            aPointers[0].title /* += ('. Other field types already in use in "'+top.HEURIST4.rectypes.names[rty_ID]+'")') */;
        }
        if(aRelMarkers.length==1){
            aRelMarkers[0].title /* += ('.(No field types that refer to "'+top.HEURIST4.rectypes.names[target_ID]+'")')*/ ;
        }else if(cnt_relmarkers==0){
            aRelMarkers[0].title /* += ('. Other field types already in use in "'+top.HEURIST4.rectypes.names[rty_ID]+'")') */;
        }

        top.HEURIST4.ui.createSelector($('#sel_resource_fields')[0], aPointers);
        top.HEURIST4.ui.createSelector($('#sel_relmarker_fields')[0], aRelMarkers);
    }

    //
    //
    //
    function editDetailType(){

        var dt_type = $('input[name="ft_type"]:checked').val();
        var dty_ID = 0;
        if(dt_type=='resource'){
            dty_ID = $('#sel_resource_fields').val();
        }else{
            dty_ID = $('#sel_relmarker_fields').val();
        }

        if(dty_ID>0){ //add already existing field type

            addDetailToRtyStructure(String(dty_ID), 0);

        }else{ //create new field type

            var url = top.HAPI4.basePathV3 
            + "admin/structure/fields/editDetailType.html?db="
            + top.HAPI4.database 
            + '&dty_Type='+dt_type
            + '&dty_PtrTargetRectypeIDs='+target_ID;

            top.HEURIST4.msg.showDialog(url, 
                {    "close-on-blur": false,
                    "no-resize": false,
                    title: 'Edit field type',
                    height: 700,
                    width: 700,
                    callback: function(context) {

                        if(!top.HEURIST4.util.isnull(context)){
                            //refresh the local heurist
                            top.HEURIST.detailTypes = context.detailTypes;
                            top.HEURIST4.detailtypes = context.detailTypes;

                            //new field type to be added
                            var dty_ID = Math.abs(Number(context.result[0]));
                            addDetailToRtyStructure(String(dty_ID), 0);
                        }
                    }
            });
        }


    }

    function addDetailToRtyStructure(dty_ID, insert_index){

        //create array to be send to server
        var detTypes = top.HEURIST4.detailtypes.typedefs,
        fi = top.HEURIST4.detailtypes.typedefs.fieldNamesToIndex,
        rst = top.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;

        var arrs = detTypes[dty_ID].commonFields;

        var arr_target = new Array();
        arr_target[rst.rst_DisplayName] = arrs[fi.dty_Name];
        arr_target[rst.rst_DisplayHelpText] = arrs[fi.dty_HelpText];
        arr_target[rst.rst_DisplayExtendedDescription] = arrs[fi.dty_ExtendedDescription];
        arr_target[rst.rst_DefaultValue ] = "";
        arr_target[rst.rst_RequirementType] = "optional";
        arr_target[rst.rst_MaxValues] = "1";
        arr_target[rst.rst_MinValues] = "0";
        arr_target[rst.rst_DisplayWidth] = "0";
        arr_target[rst.rst_RecordMatchOrder] = "0";
        arr_target[rst.rst_DisplayOrder] = insert_index;
        arr_target[rst.rst_DisplayDetailTypeGroupID] = "1";
        arr_target[rst.rst_FilteredJsonTermIDTree] = null;
        arr_target[rst.rst_PtrFilteredIDs] = null;
        arr_target[rst.rst_TermIDTreeNonSelectableIDs] = null;
        arr_target[rst.rst_CalcFunctionID] = null;
        arr_target[rst.rst_Status] = "open";
        arr_target[rst.rst_OrderForThumbnailGeneration] = null;
        arr_target[rst.dty_TermIDTreeNonSelectableIDs] = null;
        arr_target[rst.dty_FieldSetRectypeID] = null;
        arr_target[rst.rst_NonOwnerVisibility] = "viewable";

        top.HEURIST4.rectypes.typedefs[rty_ID].dtFields[dty_ID] = arr_target;         

        //create and fill the data structure
        var orec = {rectype:{
            colNames:{common:[], dtFields:top.HEURIST4.rectypes.typedefs.dtFieldNames},
            defs: {}
        }};
        orec.rectype.defs[rty_ID] = {common:[], dtFields:{}};
        orec.rectype.defs[rty_ID].dtFields[dty_ID] = arr_target;

        var str = JSON.stringify(orec);


        var updateResult = function(context){
            var _structureWasUpdated = false;
            if(!top.HEURIST4.util.isnull(context)){

                //update local structure
                top.HEURIST.rectypes = context.rectypes;
                top.HEURIST.detailTypes = context.detailTypes;

                top.HEURIST4.rectypes = context.rectypes;
                top.HEURIST4.detailtypes = context.detailTypes;

                _structureWasUpdated = true;
            }
            window.close(_structureWasUpdated);
        };

        var baseurl = top.HAPI4.basePathV3 + "admin/structure/saveStructure.php";
        var callback = updateResult;
        var params = "method=saveRTS&db="+top.HAPI4.database+"&data=" + encodeURIComponent(str);

        top.HEURIST.util.getJsonData(baseurl, callback, params);

    }

});  