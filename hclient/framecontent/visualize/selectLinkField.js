
/*
* Copyright (C) 2005-2020 University of Sydney
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
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
$(document).ready(function() {

        var rty_ID, target_ID;

        window.hWin.HEURIST4.ui.initHelper( { button:$('#hint_more_info1'), 
                            title:'Link types', 
                            url:window.hWin.HAPI4.baseURL+'context_help/link_types.html #content_body',
                            position:{ my: "left top", at: "left top", of:$(window.frameElement)}, no_init:true} ); 
/*                            
        window.hWin.HEURIST4.ui.initHelper( $('#hint_more_info2'), 
                            'Field data type: Relationship marker', 
                            window.hWin.HAPI4.baseURL+'context_help/field_data_types.html #relmarker',
                            { my: "left+200 top+100", at: "center center", of:$(document.body)}, true);
*/                            
	    
        $('#btnSelect').click( editDetailType );
    

        //rectype new field to be added to
        rty_ID = window.hWin.HEURIST4.util.getUrlParameter("source_ID", document.location.search);
        //rectype to be related (constraint for pointers and relmarker target rectype)
        target_ID = window.hWin.HEURIST4.util.getUrlParameter("target_ID", document.location.search);

        var fidx = window.hWin.HEURIST4.rectypes.typedefs['commonNamesToIndex']['rty_Description'];
        
        $('#source_rectype').text(window.hWin.HEURIST4.rectypes.names[rty_ID]);
        $('#source_rectype_img').css('background-image', 'url("'+window.hWin.HAPI4.iconBaseURL+rty_ID+'")');
        $('#source_rectype_desc').text( window.hWin.HEURIST4.rectypes.typedefs[rty_ID].commonFields[fidx] );
        $('#lt_add_new_field').text('Add new field to '+window.hWin.HEURIST4.rectypes.names[rty_ID]);
        $('#lt_use_existing_field').text('Add existing field to '+window.hWin.HEURIST4.rectypes.names[rty_ID]);
        
        $('#t_resourse').change(updateUI);
        $('#t_relmarker').change(updateUI);
        $('#t_add_new_field').change(updateUI);
        $('#t_use_existing_field').change(updateUI);
        
        
        var rt_selector = $('#sel_target_rectype_id');
        window.hWin.HEURIST4.ui.createRectypeSelect(rt_selector[0], null, 'Select target record type');
        rt_selector.change(

                function(){
                    var sDialogTitle = 'Creating link from '+ window.hWin.HEURIST4.rectypes.names[rty_ID];
                    target_ID = $(this).val();
                    if(!target_ID){
                        window.hWin.HEURIST4.util.setDisabled($('.ft_selfield'), true);                        
                        window.hWin.HEURIST4.util.setDisabled($('#btnSelect'), true);
                        $('#btnSelect').css('color','lightgray');
                        $('.ft_selfield').css('color','lightgray');
                        
                    }else{
                        
                        //change title in parent dialog
                        sDialogTitle = sDialogTitle + ' to '+  window.hWin.HEURIST4.rectypes.names[target_ID];                                             
                        window.hWin.HEURIST4.util.setDisabled($('.ft_selfield'), false);                        
                        $('.ft_selfield').css('color', $('#sel_target_rectype_id').css('color'));
                        _getLinkFields();
                        $('#sel_resource_fields').val('');
                        $('#sel_relmarker_fields').val('');
                        $('#t_resourse').prop('checked', true);
                        updateUI();

                        window.hWin.HEURIST4.util.setDisabled($('#btnSelect'), false);
                        $('#btnSelect').css('color','black');
                    }
                    
                    $(window.frameElement).parents('.ui-dialog').find('.ui-dialog-title').text(sDialogTitle);
                    
                    $('#target_rectype_desc').text( window.hWin.HEURIST4.rectypes.typedefs[target_ID].commonFields[fidx] );
                }
        );
        //target rectype is already defined via parameter
        if(target_ID){
            rt_selector.val(target_ID); 
            
            $('#target_rectype').text(window.hWin.HEURIST4.rectypes.names[target_ID]);
            $('#target_rectype_img').css('background-image', 'url("'+window.hWin.HAPI4.iconBaseURL+target_ID+'")');
            $('#target_rectype_desc').text( window.hWin.HEURIST4.rectypes.typedefs[target_ID].commonFields[fidx] );
            $('#target_rectype_div').css('display', 'inline-block');
            rt_selector.hide(); 
        } else {
            $('#target_rectype_div').hide();
            rt_selector.show(); 
        }
        rt_selector.change();

        if(!window.hWin.HEURIST4.rectypes.typedefs[rty_ID]){
            window.hWin.HEURIST4.msg.showMsgErr('Parameter for record type "rty_ID" ( '
                +rty_ID+' ) is not defined or invalid');
            window.close(false);
        }

        
    //    
    function _getLinkFields(){    
        //find existing field types that already refer target_ID
        var dty_ID, detailTypes = window.hWin.HEURIST4.detailtypes.typedefs;
        var idx_Type = detailTypes['fieldNamesToIndex']['dty_Type'];
        var idx_Ptr  = detailTypes['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
        var idx_Name = detailTypes['fieldNamesToIndex']['dty_Name'];
        var idx_NameInRecType = window.hWin.HEURIST4.rectypes.typedefs['dtFieldNamesToIndex']['rst_DisplayName'];

        var aPointers = [], 
            aRelMarkers = [], 
            cnt_ptrs = 0, cnt_relmarkers = 0;

        for(dty_ID in detailTypes){
            if(dty_ID>0){
                var dty_Type = detailTypes[dty_ID].commonFields[idx_Type];
                if(dty_Type==='resource' || dty_Type==='relmarker'){

                    var rts = detailTypes[dty_ID].commonFields[idx_Ptr].split(',');
                    if(rts.indexOf(target_ID)>=0){

                        //if this field type already in rectype
                        var already_inuse = false;
                        if(window.hWin.HEURIST4.rectypes.typedefs[rty_ID]['dtFields'][dty_ID]){
                            already_inuse = true;
                        }

                        var option_item  = {key:dty_ID, title:detailTypes[dty_ID].commonFields[idx_Name], disabled:already_inuse};
                        if(already_inuse){
                            if (detailTypes[dty_ID].commonFields[idx_Name] == window.hWin.HEURIST4.rectypes.typedefs[rty_ID]['dtFields'][dty_ID][idx_NameInRecType])
                            { // field name and base field name are the same
                                option_item.title = option_item.title +' (already connected)';
                            }
                            else {                            
                                option_item.title = option_item.title
                                +' (connected as "'
                                +window.hWin.HEURIST4.rectypes.typedefs[rty_ID]['dtFields'][dty_ID][idx_NameInRecType]+'")';
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
        /*
        if(aPointers.length==1){
            aPointers[0].title  += ('.(No field types that refer to "'+window.hWin.HEURIST4.rectypes.names[target_ID]+'")');
        }else if(cnt_ptrs==0){
            aPointers[0].title  += ('. Other field types already in use in "'+window.hWin.HEURIST4.rectypes.names[rty_ID]+'")');
        }
        if(aRelMarkers.length==1){
            aRelMarkers[0].title  += ('.(No field types that refer to "'+window.hWin.HEURIST4.rectypes.names[target_ID]+'")');
        }else if(cnt_relmarkers==0){
            aRelMarkers[0].title  += ('. Other field types already in use in "'+window.hWin.HEURIST4.rectypes.names[rty_ID]+'")');
        }
        */
        
        if(aPointers.length==0) aPointers = [{key:0, title:'<none available>'}];
        if(aRelMarkers.length==0) aRelMarkers = [{key:0, title:'<none available>'}];
        window.hWin.HEURIST4.ui.createSelector($('#sel_resource_fields')[0], aPointers);
        window.hWin.HEURIST4.ui.createSelector($('#sel_relmarker_fields')[0], aRelMarkers);

        updateUI();        
    }

    //
    //
    //
    function editDetailType(){

        var dt_type = $('input[name="ft_type"]:checked').val();
        var dty_ID = 0;
        
        if(!$('#t_add_new_field').is(':checked')){
            if(dt_type=='resource'){
                dty_ID = $('#sel_resource_fields').val();
            }else{
                dty_ID = $('#sel_relmarker_fields').val();
            }
        }

        if(dty_ID>0){ //add already existing field type

            addDetailToRtyStructure(String(dty_ID), 0);

        }else{ //create new field type

            var url = window.hWin.HAPI4.baseURL 
            + "admin/structure/fields/editDetailType.html?db="
            + window.hWin.HAPI4.database 
            + '&dty_Type='+dt_type
            + '&dty_PtrTargetRectypeIDs='+target_ID;

            window.hWin.HEURIST4.msg.showDialog(url, 
                {    "close-on-blur": false,
                    "no-resize": false,
                    title: 'Edit field type',
                    height: 700,
                    width: 840,
                    callback: function(context) {

                        if(!window.hWin.HEURIST4.util.isnull(context)){
                            //refresh the local heurist
                            if(context.detailtypes)
                                window.hWin.HEURIST4.detailtypes = context.detailtypes;

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
        var detTypes = window.hWin.HEURIST4.detailtypes.typedefs,
        fi = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex,
        rst = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;

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
        arr_target[rst.rst_DisplayHeight] = "0";
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

        window.hWin.HEURIST4.rectypes.typedefs[rty_ID].dtFields[dty_ID] = arr_target;         

        //create and fill the data structure
        var orec = {rectype:{
            colNames:{common:[], dtFields:window.hWin.HEURIST4.rectypes.typedefs.dtFieldNames},
            defs: {}
        }};
        orec.rectype.defs[rty_ID] = {common:[], dtFields:{}};
        orec.rectype.defs[rty_ID].dtFields[dty_ID] = arr_target;

        var str = JSON.stringify(orec);


        var updateResult = function(response){
            var _structureWasUpdated = false;
            
            if(response.status == window.hWin.ResponseStatus.OK){

                //update local structure
                window.hWin.HEURIST4.rectypes = response.data.rectypes;
                window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
                _structureWasUpdated = true;
                                    
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }                                        
            
            window.close(_structureWasUpdated);
        };

        var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php"; //saveRTS
        var callback = updateResult;

        var request = {method:'saveRTS', db:window.hWin.HAPI4.database, data:orec};
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
    }

    //
    //
    //
    function updateUI(){
     
            var is_resource_selected = $('#t_resourse').is(':checked');
            var is_fields_available = false;
        
            if(is_resource_selected){
                    $('#sel_resource_fields').show();
                    $('#sel_relmarker_fields').hide();
                    
                    is_fields_available = 
                        ($('#sel_resource_fields > option').length>1 ||
                        $('#sel_resource_fields').val()>0)
                    
            }else{
                    $('#sel_resource_fields').hide();
                    $('#sel_relmarker_fields').show();
                    
                    is_fields_available = 
                        ($('#sel_relmarker_fields > option').length>1 ||
                        $('#sel_relmarker_fields').val()>0)
                    
            }
            
            var f_new = $('#t_add_new_field');
            var f_exs = $('#t_use_existing_field');
            if(is_fields_available){
                  f_exs.removeProp('disabled');
                  f_exs.removeClass('ui-state-disabled ui-button-disabled');
            }else{
                  f_new.prop('checked', true);
                  f_exs.prop('disabled', 'disabled');
                  f_exs.addClass('ui-state-disabled');
            }
            
            var is_add_new = f_new.is(':checked');
            var clr = (is_add_new)?'lightgray':'none';   
            $('#sel_resource_fields').css('background', clr);
            $('#sel_relmarker_fields').css('background', clr);
    }
    
});  