/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* selectLinkField.js
* selects link field type (pointer or relationship marker) and add it recordtype structure
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

class linkFields{

    #rty_ID = 0;
    #target_ID = 0;

    constructor(){

        window.hWin.HEURIST4.ui.initHelper({
            button: $('#hint_more_info1'),
            title: 'Link types',
            url: window.hWin.HRes('link_types #content_body'),
            position: {my: 'left top', at: 'left top', of: $(window.frameElement)},
            no_init: true
        });

        $('#btnSelect').button().addClass('ui-button-action').on('click', () => this.#editDetailType());

        //rectype new field to be added to
        this.#rty_ID = window.hWin.HEURIST4.util.getUrlParameter('source_ID', document.location.search);
        //rectype to be related (constraint for pointers and relmarker target rectype)
        this.#target_ID = window.hWin.HEURIST4.util.getUrlParameter('target_ID', document.location.search);

        if(!$Db.rty(this.#rty_ID)){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: `Parameter for record type "rty_ID" (${this.#rty_ID}) is not defined or invalid`,
                error_title: 'Missing record type'
            });
            window.close(false);
        }

        $('#source_rectype').text($Db.rty(this.#rty_ID,'rty_Name'));
        $('#source_rectype_img').css('background-image', `url("${window.hWin.HAPI4.iconBaseURL}${this.#rty_ID}")`);
        $('#source_rectype_desc').text( $Db.rty(this.#rty_ID,'rty_Description') );
        $('#lt_add_new_field').text(`Add new field to ${$Db.rty(this.#rty_ID,'rty_Name')}`);
        $('#lt_use_existing_field').text(`Add existing field to ${$Db.rty(this.#rty_ID,'rty_Name')}`);

        $('#t_resourse').on('change', () => this.#updateUI());
        $('#t_relmarker').on('change', () => this.#updateUI());
        $('#t_add_new_field').on('change', () => this.#updateUI());
        $('#t_use_existing_field').on('change', () => this.#updateUI());

        let rt_selector = $('#sel_target_rectype_id');
        let rt_selector2 = window.hWin.HEURIST4.ui.createRectypeSelect(rt_selector[0], null, 'Select target record type');
        rt_selector2.on('change', (event) => {

            let sDialogTitle = `Creating link from ${$Db.rty(this.#rty_ID,'rty_Name')}`;
            this.#target_ID = $(event.target).val();

            if(window.hWin.HEURIST4.util.isPositiveInt(this.#target_ID)){

                //change title in parent dialog
                sDialogTitle += ` to ${$Db.rty(this.#target_ID,'rty_Name')}`;
                window.hWin.HEURIST4.util.setDisabled($('.ft_selfield'), false);

                $('.ft_selfield').css('color', $('#sel_target_rectype_id').css('color'));
                $('#target_rectype_desc').text($Db.rty(this.#target_ID,'rty_Description'));

                this.#getLinkFields();
                $('#sel_resource_fields').val('');
                $('#sel_relmarker_fields').val('');
                $('#t_resourse').prop('checked', true);
                this.#updateUI();

                window.hWin.HEURIST4.util.setDisabled($('#btnSelect'), false);
                $('#btnSelect').css('color','black');

            }else{

                window.hWin.HEURIST4.util.setDisabled($('.ft_selfield'), true);
                window.hWin.HEURIST4.util.setDisabled($('#btnSelect'), true);

                $('.ft_selfield').css('color', 'lightgray');
                $('#target_rectype_desc').text('');
            }

            $(window.frameElement).parents('.ui-dialog').find('.ui-dialog-title').text(sDialogTitle);
        });

        //target rectype is already defined via url parameter
        if(window.hWin.HEURIST4.util.isPositiveInt(this.#target_ID)){

            rt_selector.val(this.#target_ID);

            $('#target_rectype').text($Db.rty(this.#target_ID, 'rty_Name'));
            $('#target_rectype_img').css('background-image', `url("${window.hWin.HAPI4.iconBaseURL}${this.#target_ID}")`);
            $('#target_rectype_desc').text($Db.rty(this.#target_ID, 'rty_Description'));
            $('#target_rectype_div').css('display', 'inline-block');

            $('#sel_target_rectype_id-button').hide();
            rt_selector.hide();

        }else{

            $('#target_rectype_div').hide();
            $('#sel_target_rectype_id-button').show();
            rt_selector.show();
        }

        rt_selector2.hSelect('refresh');
        rt_selector2.trigger('change');
    }

    #editDetailType(){

        let dty_Type = $('input[name="ft_type"]:checked').val();
        let dty_ID = 0;

        if(!$('#t_add_new_field').is(':checked')){

            if(dty_Type == 'resource'){
                dty_ID = $('#sel_resource_fields').val();
            }else{
                dty_ID = $('#sel_relmarker_fields').val();
            }

            if(!window.hWin.HEURIST4.util.isPositiveInt(dty_ID)){
                window.hWin.HEURIST4.msg.showMsgFlash('Select field to be added');
                return;
            }
        }

        if(window.hWin.HEURIST4.util.isPositiveInt(dty_ID)){
            //add already existing field type
            this.#addDetailToRtyStructure(dty_ID, 0);
        }else{

            //create new field type
            let popup_options = {
                select_mode: 'manager',
                edit_mode: 'editonly', //only edit form is visible, list is hidden
                rec_ID: -1,
                title: `Define new ${$Db.baseFieldType[dty_Type]} field for ${$Db.rty(this.#rty_ID,'rty_Name')}`,
                newFieldForRtyID: this.#rty_ID,
                newFieldType: dty_Type,
                newFieldResource: this.#target_ID,
                selectOnSave: true,
                onselect: (event, res) => {
                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(res.selection)){
                        let dty_ID = res.selection[0];
                        this.#addDetailToRtyStructure(dty_ID, 0);
                    }
                }
            };

            window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', popup_options);
        }
    }

    #addDetailToRtyStructure(dty_ID, insert_index){

        let fields = {
            rst_ID: dty_ID,
            rst_RecTypeID: this.#rty_ID,
            rst_DisplayOrder: insert_index,
            rst_DetailTypeID: dty_ID,
            rst_DisplayName: $Db.dty(dty_ID,'dty_Name'),
            rst_DisplayHelpText: $Db.dty(dty_ID,'dty_HelpText'),
            rst_DisplayExtendedDescription: $Db.dty(dty_ID,'dty_ExtendedDescription'),
            rst_RequirementType: 'optional',
            rst_MaxValues: 1,
            rst_DisplayWidth: '0'
        };

        let request = {
            a: 'save',
            entity: 'defRecStructure',
            request_id: window.hWin.HEURIST4.util.random(),
            fields: fields,
            isfull: false
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

            let _structureWasUpdated = false;

            if(response.status == window.hWin.ResponseStatus.OK){

                //update local structure
                let recID = response.data[0];
                if(window.hWin.HEURIST4.util.isPositiveInt(recID)){
                    fields['rst_ID'] = recID.toString();
                    $Db.rst(this.#rty_ID).addRecord(recID, fields); // update cached record
                }

                _structureWasUpdated = true;

            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }

            window.close(_structureWasUpdated);
        });
    }

    #getLinkFields(){

        //find existing field types that already refer target_ID
        let aPointers = [],
            aRelMarkers = [],
            cnt_ptrs = 0, cnt_relmarkers = 0;

        let all_structs = $Db.rst_idx2();

        $Db.dty().each2((dty_ID, detailType) => {

            let dty_Type = detailType['dty_Type'];
            if(dty_Type !== 'resource' && dty_Type !== 'relmarker'){
                return;
            }

            let rts = detailType['dty_PtrTargetRectypeIDs'].split(',');
            if(window.hWin.HEURIST4.util.findArrayIndex(this.#target_ID, rts) < 0){
                return;
            }

            //if this field type already in rectype
            let already_inuse = all_structs[this.#rty_ID].getById(dty_ID);

            let option_item  = {key: dty_ID, title: detailType['dty_Name'], disabled: already_inuse};
            if(already_inuse){

                let rst_Name = $Db.rst(this.#rty_ID, dty_ID, 'rst_DisplayName');
                if (detailType['dty_Name'] == rst_Name){ // field name and base field name are the same

                    option_item.title += ' (already connected)';
                    option_item.disabled = true;
                }else{

                    option_item.title += ` (connected as "${window.hWin.HEURIST4.util.stripTags(rst_Name)}")`;
                    option_item.disabled = true;
                }
            }

            if(dty_Type==='resource'){

                aPointers.push(option_item);
                if(!already_inuse){ cnt_ptrs++; }
            }else{

                aRelMarkers.push(option_item);
                if(!already_inuse){ cnt_relmarkers++; }
            }
        });

        if(aPointers.length==0){ aPointers = [{key:0, title:'<none available>'}]; }
        else{ aPointers.unshift({key:0, title:'select...'}); }

        if(aRelMarkers.length==0){ aRelMarkers = [{key:0, title:'<none available>'}]; }
        else{ aRelMarkers.unshift({key:0, title:'select...'}); }

        window.hWin.HEURIST4.ui.createSelector($('#sel_resource_fields')[0], aPointers);
        window.hWin.HEURIST4.ui.createSelector($('#sel_relmarker_fields')[0], aRelMarkers);

        this.#updateUI();
    }

    #updateUI(){

        let is_resource_selected = $('#t_resourse').is(':checked');
        let is_fields_available = false;

        if(is_resource_selected){

            $('#sel_resource_fields').show();
            $('#sel_relmarker_fields').hide();

            is_fields_available = $('#sel_resource_fields > option').length > 1 || $('#sel_resource_fields').val() > 0
        }else{

            $('#sel_resource_fields').hide();
            $('#sel_relmarker_fields').show();

            is_fields_available = $('#sel_relmarker_fields > option').length > 1 || $('#sel_relmarker_fields').val() > 0
        }

        window.hWin.HEURIST4.util.setDisabled($('#t_use_existing_field'), !is_fields_available);

        if(!is_fields_available){
            $('#t_add_new_field').prop('checked', true);
        }

        let colour = $('#t_add_new_field').is(':checked') ? 'lightgray' : 'none';
        $('#sel_resource_fields').css('background', colour);
        $('#sel_relmarker_fields').css('background', colour);

        $('#sel_resource_fields')[0].selectedIndex = 0;
        $('#sel_relmarker_fields')[0].selectedIndex = 0;
    }
}