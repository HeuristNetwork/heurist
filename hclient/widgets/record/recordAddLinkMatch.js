/**
* @file recordAddLinkMatch.js
* @brief Provides a widget to create links between records by matching values in specified fields (Foreign Key matching).
* @fileOverview This file defines the `recordAddLinkMatch` widget. It allows users to establish links
* (record pointers) from a set of source records to target records by matching the content of a
* specified text field in the source records with a text field in the target records. This is useful
* for automating the creation of links based on existing data that acts like a foreign key. The
* widget provides UI to select source scope, source and target record types, the fields to match,
* and the pointer field in the source to update.
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * @class heurist.recordAddLinkMatch
 * @augments $.heurist.recordAction
 * @description jQuery widget for creating links between records by matching field values.
 * This widget facilitates a "foreign key" style linking mechanism. It matches values
 * from a specified text field in source records against values in a text field of
 * target records (of a selected type). If a match is found, it updates a specified
 * record pointer field in the source record to link to the matched target record.
 *
 * @param {object} options - Configuration options for the widget.
 * @param {number} [options.height=520] - The height of the dialog.
 * @param {number} [options.width=800] - The width of the dialog.
 * @param {boolean} [options.modal=true] - Whether the dialog is modal.
 * @param {string} [options.init_scope='selected'] - Initial scope for source record selection.
 * @param {string} [options.title='Foreign Key matching. Add links between records by matching field values'] - Title of the dialog.
 * @param {string} [options.htmlContent='recordAddLinkMatch.html'] - The HTML file for the widget's content.
 * @param {?any} options.relationtype - (Inherited but seems unused in this widget's core logic, could be for future extension or specific configurations not shown).
 */
$.widget( "heurist.recordAddLinkMatch", $.heurist.recordAction, {

    /**
     * @namespace options
     * @memberof heurist.recordAddLinkMatch
     * @type {object}
     * @property {number} [height=520] - Dialog height.
     * @property {number} [width=800] - Dialog width.
     * @property {boolean} [modal=true] - Is dialog modal.
     * @property {string} [init_scope='selected'] - Initial source scope.
     * @property {string} [title='Foreign Key matching. Add links between records by matching field values'] - Dialog title.
     * @property {string} [htmlContent='recordAddLinkMatch.html'] - HTML content file.
     * @property {?any} relationtype - Inherited option, potential for future use.
     */
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        init_scope: 'selected',
        title:  'Foreign Key matching. Add links between records by matching field values',
        
        htmlContent: 'recordAddLinkMatch.html',
        
        relationtype: null
        
    },

    /**
     * @member {?number} source_RecTypeID
     * @memberof heurist.recordAddLinkMatch
     * @description The record type ID of the source records.
     */
    source_RecTypeID:null, 
    /**
     * @member {?number} target_RecTypeID
     * @memberof heurist.recordAddLinkMatch
     * @description The record type ID of the target records to match against.
     */
    target_RecTypeID:null,
    /**
     * @member {string} sSourceName
     * @memberof heurist.recordAddLinkMatch
     * @description Placeholder for source name (seems unused in this file).
     */
    sSourceName:'',
    /**
     * @member {string} sTargetName
     * @memberof heurist.recordAddLinkMatch
     * @description Placeholder for target name (seems unused in this file).
     */
    sTargetName:'',
    /**
     * @member {?jQuery} targetRtySelect
     * @memberof heurist.recordAddLinkMatch
     * @description jQuery object for the target record type selector dropdown.
     */
    targetRtySelect: null,
    
    /**
     * @function _getActionButtons
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Gets action buttons for the dialog, setting the main action button text to 'Create links'.
     * @returns {Array<object>} Array of button definition objects.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Create links');
        return res;
    },

    /**
     * @function _fillSelectRecordScope
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Populates the source record scope selector. Enforces that the current query or selection
     * contains only a single record type to avoid accidental errors.
     * @returns {boolean|undefined} True if successful, false if mixed record types prevent operation.
     */
    _fillSelectRecordScope: function (){
        
        this._$('select').css({width:'30em','max-width':'35em'});

        let scope_types = this.options.scope_types;
        this.selectRecordScope.empty();
        
        if(scope_types=='none'){
            this.selectRecordScope.parent().hide();
            return;    
        }

        let selScope = this.selectRecordScope.get(0); //selector

        let rty_ID = 0;
        let rectype_Ids = this._currentRecordset.getRectypes();
        
        if(rectype_Ids.length==1){

            rty_ID = rectype_Ids[0];
            this.source_RecTypeID = rty_ID;
            
            window.hWin.HEURIST4.ui.addoption(selScope,
                'all', 'All records: ' + $Db.rty(rty_ID,'rty_Plural'));
                
            if(this._currentRecordsetSelIds.length>0){
                window.hWin.HEURIST4.ui.addoption(selScope,
                    'selected', 'Selected records: ' + $Db.rty(rty_ID,'rty_Plural'));
            }
        }
          
        if(!(rty_ID>0)){
            window.hWin.HEURIST4.msg.showMsgDlg(
        '<b>Mixed record types</b>'
        +'<p>The current query must contain only a single record type (this is enforced to avoid accidental errors).</p>' 
        +'<p>Please select records of a single type, either by individual selection or a revised filter, and repeat this action. </p>');

            return false;
        }
            
        
        this._on( this.selectRecordScope, { change: this._onRecordScopeChange} );        
        //this.selectRecordScope.val(rty_ID);    
        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        
        window.hWin.HEURIST4.ui.initHSelect(this.selectRecordScope, false);
        
        this._onRecordScopeChange();
        
        this.selectRecordScope.parent().hide();
        
        return true;
    },

    /**
     * @function _onRecordScopeChange
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Handles changes in the source record scope. Calls `_fillSelectFieldTypes` for the source.
     * This is an override of the parent widget's method.
     */
    _onRecordScopeChange: function () 
    {
        this._fillSelectFieldTypes('source', this.source_RecTypeID);
    },
 
    /**
     * @function _fillSelectFieldTypes
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Populates dropdowns for selecting fields.
     * For the 'source' party: populates a dropdown (`#sel_pointer_field`) with available record pointer fields
     * (resource type fields) that can be updated with matched record IDs. Also populates a dropdown
     * (`#sel_fieldtype_source`) with text fields from the source record type to be used for matching.
     * For the 'target' party: populates a dropdown (`#sel_fieldtype_target`) with text fields from the target
     * record type to be used for matching.
     * @param {string} party - 'source' or 'target'.
     * @param {number} recRecTypeID - The record type ID for which to list fields.
     */
    _fillSelectFieldTypes: function (party, recRecTypeID) {

        // create matching field
        let fieldSelect = $('#sel_fieldtype_'+party);
        
        let details = $Db.rst(recRecTypeID);
        if(details)
        {   

        if(party=='source')
        {
            
            let fieldPointerSel = this._$('#sel_pointer_field');
            fieldPointerSel.empty();

            let that = this;
            let has_fields = false;
            // get structures for both record types and filter out link and relation maker fields for links
            //                                      and text and numeric fields for matching             
            details.each2(function(dtyID, detail) {
            
                let field_type = $Db.dty(dtyID, 'dty_Type');
                let req_type  = detail['rst_RequirementType'];
                
                //|| field_type=='relmarker')
                if ( (field_type!='resource') || req_type=='forbidden' ) {
                     return true;//continue
                }
                
                //get name, contraints
                let dtyName = detail['rst_DisplayName'];
                if(!has_fields){
                    window.hWin.HEURIST4.ui.addoption(fieldPointerSel.get(0), 0, window.hWin.HR('select'));    
                }
                window.hWin.HEURIST4.ui.addoption(fieldPointerSel.get(0), dtyID, dtyName);
                has_fields = true;
            });//for fields
            
            if(!has_fields){
                //There are no record pointer fields in current query record type into which matched record IDs can be inserted
                window.hWin.HEURIST4.ui.addoption(fieldPointerSel.get(0), 0, 'There are no record pointer fields');
            }
        
            this._on( fieldPointerSel, { change: that._fillTargetRecordTypes} );        
            window.hWin.HEURIST4.ui.initHSelect(fieldPointerSel, false);
            fieldPointerSel.trigger('change');
            
        }//for source 
        
        window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect.get(0), recRecTypeID, 
                                    ['freetext','blocktext'], window.hWin.HR('select'));
                    
        if(fieldSelect.find('option').length==1){
            fieldSelect.empty();
            window.hWin.HEURIST4.ui.addoption(fieldSelect.get(0), 0, 'There are no text fields');
        }
                     
        window.hWin.HEURIST4.ui.initHSelect(fieldSelect, false);
        this._on(fieldSelect,{change:this._findMatchesCount});
        fieldSelect.trigger('change');
        
        }else{
            fieldSelect.empty();
            if(fieldSelect.hSelect("instance")!=undefined){
                fieldSelect.hSelect("destroy"); 
            }
        }
    },  

    /**
     * @function _findMatchesCount
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Event handler typically triggered when a source or target matching field is selected.
     * If the source field changes, it fetches and displays the total and unique value counts for that field
     * within the current source scope.
     * If both source and target matching fields are selected, it fetches and displays the number of potential
     * matches between the source and target records based on these fields. Enables the action button if matches are found.
     * @param {Event} event - The change event object from the field selector.
     */
    _findMatchesCount: function(event){
        
        let fieldSelect = $(event.target);
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), true);
        
        if(fieldSelect.attr('id')=='sel_fieldtype_source'){

            let cnt_info = this._$('#count_source_unique').text('');
            
            if(fieldSelect.val()>0){
                cnt_info.addClass('ui-icon ui-icon-loading-status-balls rotate')
            
                //search all and unique detail values
                window.HAPI4.RecordMgr.get_aggregations({a:'count_details',
                    rec_IDs: this._getRecordsScope().join(','),
                    rty_ID:this.source_RecTypeID, 
                    dty_ID:fieldSelect.val()}, 
                function(response){     
                    cnt_info.removeClass('ui-icon ui-icon-loading-status-balls rotate')
                    if(response.status == window.hWin.ResponseStatus.OK){
                        cnt_info.text(response.data.total+' values ('+response.data.unique+' unique)');                
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        }
        
        if(this.target_RecTypeID>0){

            let fieldSelectTrg = this._$('#sel_fieldtype_target');
            let fieldSelectSrc = this._$('#sel_fieldtype_source');

            let cnt_info2 = this._$('#count_target_matches').text('');
            
            if(fieldSelectSrc.val()>0 && fieldSelectTrg.val()>0){
                cnt_info2.addClass('ui-icon ui-icon-loading-status-balls rotate')
                
                let that = this;
            
                window.HAPI4.RecordMgr.get_aggregations({a:'count_matches',
                                rec_IDs: this._getRecordsScope().join(','),
                                rty_src:this.source_RecTypeID, 
                                dty_src:fieldSelectSrc.val(),
                                rty_trg:this.target_RecTypeID, 
                                dty_trg:fieldSelectTrg.val()
                                                        }, 
                function(response){     
                    cnt_info2.removeClass('ui-icon ui-icon-loading-status-balls rotate')
                    if(response.status == window.hWin.ResponseStatus.OK){
                        cnt_info2.text(response.data+' matches');                
                        that._enableActionButton();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        }
        
    },
    
    /**
     * @function _fillTargetRecordTypes
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Event handler triggered when a source pointer field (`#sel_pointer_field`) is selected.
     * It populates the target record type selector (`#target_record_type`) with record types that are valid
     * targets for the selected pointer field (based on its constraints).
     * @param {Event} event - The change event object from the source pointer field selector.
     */
    _fillTargetRecordTypes: function(event){

        let dtID = $(event.target).val();
        
        if(!(dtID>0)) return;
        
        let rt_constraints = (dtID>0)?$Db.dty(dtID, 'dty_PtrTargetRectypeIDs'):'';
        
        if(!Array.isArray(rt_constraints)){
            if(window.hWin.HEURIST4.util.isempty(rt_constraints)){
                rt_constraints = [];
            }else{
                rt_constraints = rt_constraints.split(',');        
            }
        }
        
        this.targetRtySelect = $('#target_record_type');
        window.hWin.HEURIST4.ui.createRectypeSelectNew(this.targetRtySelect.get(0), 
            {rectypeList:rt_constraints, useHtmlSelect:true, useCounts:true});
        
        this._on( this.targetRtySelect, {
                change: this._onTargetRtySelectChange} );        
        
        window.hWin.HEURIST4.ui.initHSelect(this.targetRtySelect, false);
        
        this._onTargetRtySelectChange();
        
    },
    
    _onTargetRtySelectChange: function(){
        this._$('#count_target_rty').text('');
        this.target_RecTypeID = this.targetRtySelect.val(); 
        if(this.target_RecTypeID>0){
            let rty_usage_cnt = $Db.rty(this.target_RecTypeID,'rty_RecCount');
            if(rty_usage_cnt>0){
                this._$('#count_target_rty').text( rty_usage_cnt + ' records' );
            }
        }     
        this._fillSelectFieldTypes('target', this.target_RecTypeID);
        if(this._$('#count_target_rty').text()==''){
            this._$('#sel_fieldtype_target').empty();
            this._$('#count_target_matches').empty();
            this._enableActionButton();
        }
        
    },
  
    /**
     * @function _onTargetRtySelectChange
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Event handler for when the target record type selector (`#target_record_type`) changes.
     * Updates `this.target_RecTypeID`, displays the count of records for the selected target type,
     * and calls `_fillSelectFieldTypes` to populate the target matching field selector.
     * Clears target field selectors if no records exist for the selected type.
     */
    _onTargetRtySelectChange: function(){
        this._$('#count_target_rty').text('');
        this.target_RecTypeID = this.targetRtySelect.val();
        if(this.target_RecTypeID>0){
            let rty_usage_cnt = $Db.rty(this.target_RecTypeID,'rty_RecCount');
            if(rty_usage_cnt>0){
                this._$('#count_target_rty').text( rty_usage_cnt + ' records' );
            }
        }
        this._fillSelectFieldTypes('target', this.target_RecTypeID);
        if(this._$('#count_target_rty').text()==''){
            this._$('#sel_fieldtype_target').empty();
            this._$('#count_target_matches').empty();
            this._enableActionButton();
        }

    },

    /**
     * @function getFieldValue
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Retrieves the value from an `editing_input` widget by its ID.
     * (Note: This function appears to be a copy from another widget and is not used in this file,
     * as `editing_input` is not the primary mechanism for field selection here. Standard jQuery `val()` is used on `<select>`s.)
     * @param {string} input_id - The ID of the `editing_input` container.
     * @returns {?any} The first value from the `editing_input`, or null if not found or no value.
     */
    getFieldValue: function (input_id) {
        let ele =  this._$('#'+input_id);
        if(ele.length>0){
            let sel = ele.editing_input('getValues');
            if(sel && sel.length>0){
                return sel[0];
            }
        }
        return null;
    },

    
    /**
     * @function _enableActionButton
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Enables or disables the main 'Create links' action button.
     * The button is enabled if the number of target matches found (displayed in `#count_target_matches`) is greater than 0.
     * It also checks (though the logic for `sel_field` seems incomplete/vestigial here) if a relation type is selected for relmarkers,
     * but this widget primarily deals with direct resource pointers.
     */
    _enableActionButton: function (){
        
        let isEnabled = (parseInt($('#count_target_matches').text())>0);
        
        if(isEnabled){
            let sel_field  = this._$('input[type="radio"][name="link_field"]:checked');
            
            if(sel_field.attr('data-type')=='relmarker'){
                //in case relmarker check if reltype selected
                let dtyID = sel_field.val()
                let termID = this.getFieldValue('rt_source_sel_'+dtyID);        
                isEnabled = (termID>0);
            }                
        }  
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), !isEnabled );
    }, 
    
    _getRecordsScope: function()
    {
        let isSelection = (this.selectRecordScope.val()=='selected');
        let currentScope = isSelection?this._currentRecordsetSelIds:this._currentRecordset.getIds();
        return currentScope;
        
    },
    
    /**
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Determines the array of source record IDs to be processed based on the
     * selection in the `selectRecordScope` dropdown ('selected' or 'all' within the current recordset).
     * @returns {Array<number>} An array of source record IDs.
     */
    _getRecordsScope: function()
    {
        let isSelection = (this.selectRecordScope.val()=='selected');
        let currentScope = isSelection?this._currentRecordsetSelIds:this._currentRecordset.getIds();
        return currentScope;

    },

    /**
     * @function doAction
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Performs the action of creating links by matching or displays unmatched values.
     * If the 'Show unmatched values' option is selected, it fetches and displays records from the source scope
     * that do not have a match in the target records.
     * Otherwise, it initiates the batch process to add links:
     * It gathers parameters (source/target record types, source/target matching fields, pointer field to update, scope of records).
     * It then calls the `HAPI4.RecordMgr.batch_details` API with `a: 'add_links_by_matching'`.
     * Shows progress and displays results (records updated, links added/existing).
     */
    doAction: function(){
        
        if(this._$('#div_result').is(':visible')){
            this._setBtnLabels(false);
            this._$('#div_result').hide();
            this._$('#div_fieldset').show();
            return;
        }

        let dty_ID = this._$('#sel_pointer_field').val();
        /*
        let ele = this._$('input[type="radio"][name="link_field"]:checked');
        let dty_ID = ele.val();
        let trm_ID = 0;
        let data_type = ele.attr('data-type');   //resource (record pointer) or relmarker
        if(data_type!='resource'){
            trm_ID = this.getFieldValue('rt_source_sel_'+dty_ID);
        }*/
        
        let currentScope = this._getRecordsScope();
        
        
        let div_res = this._$('#div_result');
        div_res.empty();
        let that = this;
        
        if ($('input[name="to_replace"]:checked').val()=='nonmatch') {
                window.HAPI4.RecordMgr.get_aggregations({a:'count_matches',
                                nonmatch: 1,
                                rec_IDs: this._getRecordsScope().join(','),
                                rty_src:this.source_RecTypeID, 
                                dty_src:$('#sel_fieldtype_source').val(),
                                rty_trg:this.target_RecTypeID, 
                                dty_trg:$('#sel_fieldtype_target').val()
                                                        }, 
                function(response){     
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.element.find('#div_fieldset').hide();
                        let csv_res = '<div style="padding:10px;height:100%;overflow:auto;">UNMATCHED VALUES<br><pre>H-ID&#9;Value&#9;Record title<br>';
                        for(let idx in response.data){
                            let row = response.data[idx];
                            for(let idx2 in row){
                                row[idx2] = window.hWin.HEURIST4.util.stripTags(row[idx2]).trim();
                            }
                            csv_res = csv_res + row.join("&#9;")+"<br>";
                        }
                        div_res.html(csv_res+'</pre></div>').show();
                        that._setBtnLabels(true);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
        }else{
            
            let session_id = Math.round((new Date()).getTime()/1000);
        
            let request = {a: 'add_links_by_matching',
                                session: session_id,
                                dty_ID:  dty_ID,
                                //trm_ID: trm_ID,
                                rec_IDs: currentScope.join(','),
                                rty_src:  this.source_RecTypeID,
                                dty_src: $('#sel_fieldtype_source').val(),
                                rty_trg: this.target_RecTypeID,
                                dty_trg: $('#sel_fieldtype_target').val(),
                                replace: ($('input[name="to_replace"]:checked').val()=='replace'?1:0)
                        };

            this._showProgress( session_id, false, 1000 );
            
            window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                
                that._hideProgress();
                
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    that.element.find('#div_fieldset').hide();
                    div_res.html(
    '<div style="padding:10px;display:table">'
    +`<span class="table-cell">Records passed to process</span><span class="table-cell">&nbsp;&nbsp;${currentScope.length}</span><br><br>`
    +`<span class="table-cell">Records updated</span><span class="table-cell">&nbsp;&nbsp;${response.data['records_updated']}</span><br><br>`
    +`<span class="table-cell">Links added</span><span class="table-cell">&nbsp;&nbsp;${response.data['added']}</span><br><br>`
    +`<span class="table-cell">Links already exist</span><span class="table-cell">&nbsp;&nbsp;${response.data['exist']}</span></div>`)
                    .show();
                    
                    that._setBtnLabels(true);
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response); 
                }
            });
            
        }
    },
    
    _setBtnLabels: function(is_done){
        let lab1, lab2;
        if(is_done){
            lab1 = 'New Action';
            lab2 = 'Done';
        }else{
            lab1 = 'Create links';
            lab2 = 'Cancel';
        }
        this.element.parents('.ui-dialog').find('.btnDoAction').button({label:window.hWin.HR(lab1)});
        this.element.parents('.ui-dialog').find('.btnCancel').button({label:window.hWin.HR(lab2)});
    }
    
        
});
/**
 * @function _setBtnLabels
 * @memberof heurist.recordAddLinkMatch
 * @private
 * @description Sets the labels of the main action button and the cancel button
 * depending on whether the action is done or pending.
 * @param {boolean} is_done - If true, sets labels to 'New Action' and 'Done'.
 *                           If false, sets labels to 'Create links' and 'Cancel'.
 */

