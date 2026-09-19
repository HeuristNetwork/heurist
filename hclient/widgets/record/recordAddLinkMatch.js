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
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @class recordAddLinkMatch
 * @augments recordAction
 * @memberof Widgets.Records
 *
 * @description jQuery widget for creating links between records by matching field values.
 * This widget facilitates a "foreign key" style linking mechanism. It matches values
 * from a specified text field in source records against values in a text field of
 * target records (of a selected type). If a match is found, it updates a specified
 * record pointer field in the source record to link to the matched target record.
 *
 * @param {object} options - Configuration options for the widget.
 */
$.widget( "heurist.recordAddLinkMatch", $.heurist.recordAction, {

    /**
     * @memberof Widgets.Records.recordAddLinkMatch
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
     * @memberof Widgets.Records.recordAddLinkMatch
     * @description The record type ID of the source records.
     */
    source_RecTypeID:null, 
    /**
     * @member {?number} target_RecTypeID
     * @memberof Widgets.Records.recordAddLinkMatch
     * @description The record type ID of the target records to match against.
     */
    target_RecTypeID:null,
    /**
     * @member {string} sSourceName
     * @memberof Widgets.Records.recordAddLinkMatch
     * @description Placeholder for source name (seems unused in this file).
     */
    sSourceName:'',
    /**
     * @member {string} sTargetName
     * @memberof Widgets.Records.recordAddLinkMatch
     * @description Placeholder for target name (seems unused in this file).
     */
    sTargetName:'',
    /**
     * @member {?jQuery} targetRtySelect
     * @memberof Widgets.Records.recordAddLinkMatch
     * @description jQuery object for the target record type selector dropdown.
     */
    targetRtySelect: null,
    /**
     * @member {?Object} _lastNoMatchResults
     * @memberof Widgets.Records.recordAddLinkMatch
     * @description JSON object containing the last report for unmatched values.
     */
    _lastNoMatchResults: null,

    /**
     * @function _initControls
     * @memberof heurist.recordAddLinkMatch
     * @private
     * @description Calls the parent widget's `_initControls` method.
     * Then adds an onchange handler to the behaviour checkboxes to alter the dialog action button.
     */
    _initControls: function(){

        if(!this._super()){
            return;
        }

        this._on(this._$('[name="to_replace"]'), {
            change: () => {
                let currentMethod = this._$('[name="to_replace"]:checked').val();
                this._setBtnLabels(currentMethod === 'nonmatch' ? 2 : 1);
            }
        });
    },

    /**
     * @function _getActionButtons
     * @memberof Widgets.Records.recordAddLinkMatch
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
     * @memberof Widgets.Records.recordAddLinkMatch
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
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Handles changes in the source record scope. Resets all fields and clears all selections.
     * This is an override of the parent widget's method.
     */
    _onRecordScopeChange: function (){

        if(this._$('.fieldtype_sources').find('.input-div').length > 0){
            this._clearAllSelectFieldTypes('source');
            this._clearAllSelectFieldTypes('target');
        }else{

            this._on(this._$('#repeat_source_input'), {
                click: () => this._addInputDiv('source')
            });

            this._$('.fieldtype_sources').sortable({
                axis: 'y',
                update: () => {
                    this._findMatchesCount();
                }
            });
        }

        this._$('#sel_pointer_field').attr('data-init', 0);

        this._addInputDiv('source');
    },

    /**
     * @function _addInputDiv
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Creates a set of field dropdown + count containers (if required) for the requested container.
     * For 'source', it also creates the corresponding 'target' input div.
     * @param {String} party - Which of 'source' or 'target' this input div if for
     */
    _addInputDiv: function(party){

        let $container = this._$(`.fieldtype_${party}s`);

        const randomID = crypto.getRandomValues(new Uint16Array(1));
        const elementID = `${party}_${randomID}`;

        let countEle = party === 'target' && $container.find('.input-div').length === 0 ? `<span id="count_target_matches" style="padding-left:5px;font-weight:bold"></span>` : '';

        countEle = party === 'source' ? `<span style="padding-left:5px;font-weight:bold"><span id="count_${elementID}"></span><span id="nomatch_${elementID}"></span></span>` : countEle;

        let $div = $('<div>', {
            class: 'input-div',
            'data-id': randomID,
            html: `<select id="select_fieldtype_${elementID}" class="ui-widget-content ui-corner-all" style="min-height: 24px;"></select>
            ${countEle}
            <span id="clear_${elementID}" style="cursor: pointer;" class="smallbutton ui-icon ui-icon-circlesmall-close show-onhover"></span>
            <span id="move_${elementID}" class="smallbutton ui-icon ui-icon-arrow-2-n-s show-onhover"></span>`
        }).appendTo($container);

        this._on($div.find(`#clear_${elementID}`), {
            click: () => this._removeInputDiv(party, randomID)
        });

        this._on($div.find('span[id^="nomatch_"]'), {
            click: (event) => {
                let $parent = $(event.target).parents('.input-div');
                this._displayNoMatchReport($parent.find('select').val());
            }
        });

        this._fillSelectFieldTypes(party, randomID, party === 'source' ? this.source_RecTypeID : this.target_RecTypeID);

        if(party === 'source'){ // for each source there is a target
            this._addInputDiv('target');
        }
    },

    /**
     * @function _removeInputDiv
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Removes the requested input div and it's corresponding input in the other party.
     * Also ensures that their is always: one source input, and that the target containers have one count container.
     * It then refreshes the field value counts, as required.
     * @param {String} party - Which of 'source' or 'target' started this removal
     * @param {String} elementID - Input div ID
     */
    _removeInputDiv: function(party, elementID){

        let $sourceContainer = this._$(`.fieldtype_sources`);
        let $targetContainer = this._$(`.fieldtype_targets`);

        let $originalContainer = party === 'source' ? $sourceContainer : $targetContainer;

        if($originalContainer.find('.input-div').length === 0){
            return;
        }else if($sourceContainer.find('.input-div').length === 1){

            let $sourceSelect = $sourceContainer.find('select');
            let $targetSelect = $targetContainer.find('select');

            if(party === 'source'){
                $sourceSelect.val('').trigger('change');
            }
            $targetSelect.val('').trigger('change');

            if($sourceSelect.hSelect('instance') !== undefined){
                $sourceSelect.hSelect('refresh');
            }

            if($targetSelect.hSelect('instance') !== undefined){
                $targetSelect.hSelect('refresh');
            }

            $sourceContainer.find('span[id^="count_"]').text('');
            $targetContainer.find('#target_match_count').text('');

            return;
        }

        let inputIDX = -1;
        $originalContainer.find('.input-div').each((idx, ele) => {
            if(ele.getAttribute('data-id') == elementID){
                inputIDX = idx;
                return false;
            }
        });

        if(inputIDX === -1){
            return;
        }

        let $sourceInputDiv = this._$($sourceContainer.find('.input-div')[inputIDX]);
        let $targetInputDiv = this._$($targetContainer.find('.input-div')[inputIDX]);

        let $sourceSelect = $sourceInputDiv.find('.select_fieldtype');
        $sourceSelect.empty();
        if($sourceSelect.hSelect("instance") != undefined){
            $sourceSelect.hSelect("destroy"); 
        }

        let $targetSelect = $targetInputDiv.find('.select_fieldtype');
        $targetSelect.empty();
        if($targetSelect.hSelect("instance") != undefined){
            $targetSelect.hSelect("destroy"); 
        }

        if($targetInputDiv.find('#count_target_matches').length > 0 && $targetContainer.find('.input-div').length > 1){
            $targetInputDiv.find('#count_target_matches').insertBefore($($targetContainer.find('.input-div')[1]).find('[id^="clear_target_"]'));
        }

        $sourceInputDiv.remove();
        $targetInputDiv.remove();

        this._findMatchesCount();
    },

    /**
     * @function _clearAllSelectFieldTypes
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Completely removes individual input divs from the party container.
     * It then refreshes the field value counts, as required.
     * @param {String} party - Which of 'source' or 'target' started this removal
     * @param {boolean} removeAll - Whether to remove all input divs from the party container (currently for 'target' only)
     */
    _clearAllSelectFieldTypes: function(party, removeAll = false){

        let $container = this._$(`.fieldtype_${party}s`);

        $container.find('.input-div').each((idx, ele) => {

            let $select = $(ele).find('.select_fieldtype');

            $select.empty();
            if($select.hSelect("instance")!=undefined){
                $select.hSelect("destroy"); 
            }

            if(idx > 0 || removeAll){
                ele.remove();
            }
        });

        this._findMatchesCount();
    },
 
    /**
     * @function _fillSelectFieldTypes
     * @memberof Widgets.Records.recordAddLinkMatch
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
    _fillSelectFieldTypes: function (party, inputID, recRecTypeID) {

        // create matching field
        let $container = this._$(`.fieldtype_${party}s`);
        let fieldSelect = $container.find(`#select_fieldtype_${party}_${inputID}`);
        
        let details = $Db.rst(recRecTypeID);

        if(!details){

            this._clearAllSelectFieldTypes(party);

            return;
        }

        let fieldPointerSel = this._$('#sel_pointer_field');
        if(party == 'source' && fieldPointerSel.attr('data-init') != 1){

            fieldPointerSel.attr('data-init', 1);
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
            //$('.fieldtype_sources .')
        }
                     
        window.hWin.HEURIST4.ui.initHSelect(fieldSelect, false);
        this._on(fieldSelect, {change: this._findMatchesCount});
        fieldSelect.trigger('change');

    },  

    /**
     * @function _findMatchesCount
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Event handler typically triggered when a source or target matching field is selected.
     * If the source field changes, it fetches and displays the total and unique value counts for that field
     * within the current source scope.
     * If both source and target matching fields are selected, it fetches and displays the number of potential
     * matches between the source and target records based on these fields. Enables the action button if matches are found.
     * @param {Event} event - The change event object from the field selector.
     */
    _findMatchesCount: function(event){
        
        let fieldSelect = event ? $(event.target) : null;
        const ID = fieldSelect ? fieldSelect.attr('id') : '';
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), true);

        if(ID.startsWith('select_fieldtype_source_')){

            let $countSource = this._$(`#${ID.replace('select_fieldtype', 'count')}`).text('');

            if(fieldSelect.val() > 0){

                $countSource.addClass('ui-icon ui-icon-loading-status-balls rotate')
            
                //search all and unique detail values
                window.HAPI4.RecordMgr.get_aggregations({a:'count_details',
                    rec_IDs: this._getRecordsScope().join(','),
                    rty_ID:this.source_RecTypeID, 
                    dty_ID:fieldSelect.val()}, 
                function(response){     
                    $countSource.removeClass('ui-icon ui-icon-loading-status-balls rotate')
                    if(response.status == window.hWin.ResponseStatus.OK){
                        $countSource.text(response.data.total+' values ('+response.data.unique+' unique)');                
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        }

        let nomatch_counts = this._$('span[id^="nomatch_"]').text('');

        if(this.target_RecTypeID <= 0){
            return;
        }

        let [fieldSources, fieldTargets] = this._getSelectedDetailFields();

        let cnt_info2 = this._$('#count_target_matches').text('');

        if(fieldSources && fieldTargets){
            
            cnt_info2.addClass('ui-icon ui-icon-loading-status-balls rotate');
            
            let that = this;
        
            window.HAPI4.RecordMgr.get_aggregations({
                a:'count_matches',
                rec_IDs: this._getRecordsScope().join(','),
                rty_src: this.source_RecTypeID, 
                dty_src: fieldSources,
                rty_trg: this.target_RecTypeID, 
                dty_trg: fieldTargets
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

            window.HAPI4.RecordMgr.get_aggregations({
                a:'count_matches',
                nonmatch: 1,
                rec_IDs: this._getRecordsScope().join(','),
                rty_src: this.source_RecTypeID,
                dty_src: fieldSources,
                rty_trg: this.target_RecTypeID,
                dty_trg: fieldTargets
            }, 
            function(response){

                if(response.status !== window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                that._lastNoMatchResults = response.data;

                nomatch_counts.each((idx, element) => {

                    let $element = $(element);
                    let dtyID = $element.parents('.input-div').find('select').val();

                    if(Object.hasOwn(that._lastNoMatchResults, dtyID)){
                        $element.html(`<span class="fake_link" style="margin-left: 1em;">(${that._lastNoMatchResults[dtyID].length} missing matches)</span>`);
                        $element.attr('data-dtyid', dtyID);
                    }
                });

            });
        }
        
    },
    
    /**
     * @function _fillTargetRecordTypes
     * @memberof Widgets.Records.recordAddLinkMatch
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
    
    /**
     * @function _onTargetRtySelectChange
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Event handler for when the target record type selector (`#target_record_type`) changes.
     * Updates `this.target_RecTypeID`, displays the count of records for the selected target type.
     * Clears target field selectors if no records exist for the selected type.
     */
    _onTargetRtySelectChange: function(){

        this._$('#count_target_rty').text('');
        this.target_RecTypeID = this.targetRtySelect.val();

        let hasUsage = false;
        if(this.target_RecTypeID > 0){
            let rty_usage_cnt = $Db.rty(this.target_RecTypeID,'rty_RecCount');
            if(rty_usage_cnt > 0){
                this._$('#count_target_rty').text( rty_usage_cnt + ' records' );
                hasUsage = true;
            }
        }

        this._clearAllSelectFieldTypes('target', true);
        this._enableActionButton();

        if(!hasUsage){
            this._$('.fieldtype_targets').text('Record type is not used');
            return;
        }

        let inputCount = this._$('.fieldtype_sources .input-div').length;
        while(inputCount > 0){
            this._addInputDiv('target');
            inputCount--;
        }
    },

    /**
     * @function getFieldValue
     * @memberof Widgets.Records.recordAddLinkMatch
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
     * @memberof Widgets.Records.recordAddLinkMatch
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
    
    /**
     * @memberof Widgets.Records.recordAddLinkMatch
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
     * @memberof Widgets.Records.recordAddLinkMatch
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

            let currentMethod = this._$('[name="to_replace"]:checked').val();
            this._setBtnLabels(currentMethod === 'nonmatch' ? 2 : 1);

            this._$('#div_result').hide();
            this._$('#div_fieldset').show();

            return;
        }

        let dty_ID = this._$('#sel_pointer_field').val();

        let currentScope = this._getRecordsScope();

        let div_res = this._$('#div_result');
        div_res.empty();

        let that = this;
        let [fieldSources, fieldTargets] = this._getSelectedDetailFields();

        if(!fieldSources || !fieldTargets){
            return;
        }

        if ($('input[name="to_replace"]:checked').val()=='nonmatch') {
                window.HAPI4.RecordMgr.get_aggregations({
                    a:'count_matches',
                    nonmatch: 1,
                    rec_IDs: this._getRecordsScope().join(','),
                    rty_src: this.source_RecTypeID,
                    dty_src: fieldSources,
                    rty_trg: this.target_RecTypeID,
                    dty_trg: fieldTargets
                }, 
                function(response){

                    if(response.status !== window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        return;
                    }

                    that.element.find('#div_fieldset').hide();
                    that._generateNoMatchesReport(div_res, response.data);

                    that._setBtnLabels(3);
                });
        }else{
            
            let session_id = Math.round((new Date()).getTime()/1000);
        
            let request = {
                a: 'add_links_by_matching',
                session: session_id,
                dty_ID:  dty_ID,
                //trm_ID: trm_ID,
                rec_IDs: currentScope.join(','),
                rty_src:  this.source_RecTypeID,
                dty_src: fieldSources,
                rty_trg: this.target_RecTypeID,
                dty_trg: fieldTargets,
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
                    
                    that._setBtnLabels(0);
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response); 
                }
            });
            
        }
    },
    
    /**
     * @function _setBtnLabels
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Sets the labels of the main action button and the cancel button
     * depending on whether the action is done or pending.
     * @param {Number} mode - If 0, sets labels to 'New Action' and 'Done'.
     *                        If 1, sets labels to 'Create links' and 'Cancel'.
     *                        If 2, sets labels to 'Get report' and 'Cancel'.
     */
    _setBtnLabels: function(mode){
        let lab1,
        lab2 = mode === 0 ? 'Done' : 'Cancel';
        if(mode === 0){
            lab1 = 'New Action';
        }else if(mode === 1){
            lab1 = 'Create links';
        }else if(mode === 2){
            lab1 = 'Get report';
        }else if(mode === 3){
            lab1 = 'Back to matching';
        }
        this.element.parents('.ui-dialog').find('.btnDoAction').button({label:window.hWin.HR(lab1)});
        this.element.parents('.ui-dialog').find('.btnCancel').button({label:window.hWin.HR(lab2)});
    },

    /**
     * @function _getSelectedDetailFields
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Gets the source fields and their corresponding target fields.
     * If their isn't a one to one matching, double false is returned.
     * @returns {Array} One to one field mapping, otherwise [false, false] with incomplete mapping
     */
    _getSelectedDetailFields: function(){

        let $sourceContainer = this._$(`.fieldtype_sources`);
        let $targetContainer = this._$(`.fieldtype_targets`);
        let $sourceFields = $sourceContainer.find('[id^="select_fieldtype_"]');
        let $targetFields = $targetContainer.find('[id^="select_fieldtype_"]');

        let sources = [];
        let targets = [];

        for(let idx = 0; idx < $sourceFields.length; idx++){

            let $source = $($sourceFields[idx]);
            let $target = $($targetFields[idx]);
            const source = $source.val();
            const target = $target.val();

            if(source === '' && target === ''){
                continue;
            }else if(source === '' || target === ''){
                return [false, false];
            }

            sources.push(source);
            targets.push(target);
        }

        return [sources, targets];
    },

    /**
     * @function _generateNoMatchesReport
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Creates the unmatched values report in HTML format and then injects it
     * into the provided DOM element.
     * Also provides controls to download the report in several formats.
     * @param {jQuery} $container - Container to place the generated report into
     * @param {Object} data - Unmatched values report data {field ID => [[Record ID, Record Title, Value], ...], ...}
     */
    _generateNoMatchesReport: function($container, data){

        // Base HTML + Export controls
        let html = '<div style="padding:0px 1em 1em;height:100%;overflow:auto;">';
        html += `<div class="export-buttons" style="margin: 1em 0px 2em;">
            Export report as: 
            <span class="fake_link" data-type="csv" style="display: inline-block; margin-right: 1em;">CSV</span>
            <span class="fake_link" data-type="json" style="display: inline-block; margin-right: 1em;">JSON</span>
            <span class="fake_link" data-type="html">HTML</span>
        </div>`;

        html += 'UNMATCHED VALUES<br>';

        // Add report details
        for(const dtyID in data){

            if(!Object.hasOwn(data, dtyID) || !$Db.rst(this.source_RecTypeID, dtyID)){
                continue;
            }

            html += `<br><strong>${$Db.rst(this.source_RecTypeID, dtyID, 'rst_DisplayName')}</strong><pre>H-ID&#9;Record title&#9;Value<br>`;

            let rows = data[dtyID];
            for(let idx in rows){

                let row = rows[idx];

                for(let idx2 in row){
                    row[idx2] = window.hWin.HEURIST4.util.stripTags(row[idx2]).trim();
                }

                html += `${row.join("&#9;")}<br>`;
            }

            html += '</pre><hr>';
        }

        // Add to HTML container
        $container.html(html + '</div>').show();

        // Handlers for export controls
        let dtyID = Object.keys(data);
        dtyID = dtyID.length === 1 ? dtyID.pop() : null;
        this._on($container.find('.export-buttons span.fake_link'), {
            click: (event) => this._downloadNoMatchReport(event.target.getAttribute('data-type'), dtyID)
        })
    },

    /**
     * @function _displayNoMatchReport
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Displays in a popup the unmatched values report for a specific field, or all fields, formatted by `_generateNoMatchesReport`
     * @param {Number} dtyID - Specific Field ID, on null it shows the complete report
     */
    _displayNoMatchReport: function(dtyID){

        let details = this._lastNoMatchResults;
        if(window.hWin.HEURIST4.util.isPositiveInt(dtyID)){
            if(Object.hasOwn(details, dtyID)){
                details = {[dtyID]: details[dtyID]};
            }else{
                details = null;
            }
        }

        if(!details){
            window.hWin.HEURIST4.msg.showMsgFlash('No unmatched values report to display...', 3000);
            return;
        }

        let $dlg, content = '', btn = {};

        btn[window.hWin.HR('Close')] = () => $dlg.dialog('close');

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btn, {title: 'Unmatched Values Report'}, {default_palette_class: 'ui-heurist-explore', dialogId: 'links-unmatched-values-report'});

        // Add report content
        this._generateNoMatchesReport($dlg, details);

        // Reset position to center
        let position = $dlg.dialog('option', 'position');
        $dlg.dialog('option', 'position', position);

    },

    /**
     * @function _downloadNoMatchReport
     * @memberof Widgets.Records.recordAddLinkMatch
     * @private
     * @description Downloads the unmatched values report, for the specified field or the complete report, in the given format.
     * @param {String} format - The output's format, ['csv', 'html', 'json']
     * @param {Number} dtyID - Specific Field ID, on null it uses the complete report
     */
    _downloadNoMatchReport: function(format, dtyID){

        let details = this._lastNoMatchResults;
        if(window.hWin.HEURIST4.util.isPositiveInt(dtyID)){
            if(Object.hasOwn(details, dtyID)){
                details = {[dtyID]: details[dtyID]};
            }else{
                details = null;
            }
        }

        if(!details){
            window.hWin.HEURIST4.msg.showMsgFlash('No unmatched values report to export...', 3000);
            return;
        }

        // Prepare data and create blob data
        let blob = null;
        if(format === 'html'){

            const $div = $('<div>');

            this._generateNoMatchesReport($div, details);
            $div.find('.export-buttons').remove();

            const html = $div.html();

            blob = new Blob([html], {type: 'text/html;charset=utf-8'});

        }else if(format === 'json'){

            details = Object.keys(details).reduce((newDetails, dtyID) => {

                const newKey = $Db.rst(this.source_RecTypeID, dtyID, 'rst_DisplayName') || dtyID;
                newDetails[newKey] = details[dtyID];

                return newDetails;
            }, {});

            const jsonString = JSON.stringify(details, null, 2);

            blob = new Blob([jsonString], {type: 'application/json'});

        }else if(format === 'csv'){

            const headers = ['H-ID', 'Record title', 'Value'];

            const tsvRows = [];
            for(const dtyID in details){

                tsvRows.push($Db.rst(this.source_RecTypeID, dtyID, 'rst_DisplayName'));
                tsvRows.push(headers.join('\t'));

                tsvRows.push(
                    ...details[dtyID].map(row => 
                        row.map(data => 
                            data.replace(/[\t\n\r]/g, ' ')
                        ).join('\t')
                    )
                );
            }

            const tsvContent = tsvRows.join('\n');

            blob = new Blob([tsvContent], {type: 'text/csv;charset=utf-8;'});
        }

        if(!blob){
            return;
        }

        // Generate Blob link and anchor tags
        const blobURL = URL.createObjectURL(blob);
        const $a = $('<a>', {
            href: blobURL,
            download: `unmatched-values.${format}`,
            style: 'display:none;'
        }).appendTo(this._$('#div_fieldset'));

        // Trigger click, jQuery doesn't work
        $a[0].click();

        // Clean up
        $a.remove();
        URL.revokeObjectURL(blobURL);
    }
        
});

