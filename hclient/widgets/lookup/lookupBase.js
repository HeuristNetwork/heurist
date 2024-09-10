/**
* lookupBase.js - Base widgt for all lookup widgets
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
$.widget( "heurist.lookupBase", $.heurist.recordAction, {

    // dialog options, the default values and other available options can be found in hclient/widget/record/recordAction.js
    options: {

        height: 700,
        width:  800,
        modal:  true,
        
        title:  "External lookup",
        
        htmlContent: 'lookupBase.html', // in hclient/widgets/lookup folder
        helpContent: null, // in context_help folder

        mapping: null, // configuration from record_lookup_config.json
        edit_fields: null, // realtime values from edit form fields
        edit_record: false, // recordset of the current record being edited

        add_new_record: false, // if true it creates new record on selection

        resultList: {

            recordDivEvenClass: 'recordDiv_blue',
            eventbased: false,  //do not listent global events

            multiselect: false, // allow only one record to be selected
            select_mode: 'select_single', // only accept one record for selection
            selectbutton_label: 'select!!', // not used

            view_mode: 'list', // result list viewing mode [list, icon, thumb]
            show_viewmode: false,
            pagesize: 20, // number of records to display per page,

            entityName: this._entityName,

            empty_remark: '<div style="padding:1em 0 1em 0">No records match the search</div>' // For empty results
        }
    },

    recordList: null, // Result list

    action_button_label: 'Select', // dialog action button label

    _forceClose: true, // skip saving additional mapping and close dialog

    tabs_container: null, // jQuery tabs container, separates query and results
    results_tab: 1, // Tab that displays the result list

    action_timeout: null, // Timeout for certain actions

    //this.element => dialog inner content
    //this._as_dialog => dialog container

    _init: function(){
        this._super(); // bare in mind that the html hasn't been loaded yet
    },

    _initControls: function(){

        // Init record list
        this.recordList = this.element.find('#div_result');
        if(this.recordList.length > 0){

            this.options.resultList['renderer'] = this._rendererResultList // Record render function, is called on resultList updateResultSet

            this.recordList.resultList(this.options.resultList);
            this.recordList.resultList('option', 'pagesize', this.options.resultList.pagesize); // so the pagesize doesn't get set to a different value

            // Init select & double click events for result list
            this._on( this.recordList, {
                "resultlistonselect": function(event, selected_recs){
                    window.hWin.HEURIST4.util.setDisabled(
                        this.element.parents('.ui-dialog').find('#btnDoAction'),
                        selected_recs && selected_recs.length() != 1
                    );
                },
                "resultlistondblclick": function(event, selected_recs){
                    if(selected_recs && selected_recs.length()==1){
                        this.doAction();
                    }
                }
            });
        }

        // Init tabs
        if(this.element.find('#tabs-cont').length > 0){
            this.tabs_container = this.element.find('#tabs-cont').tabs();
        }

        // For capturing the 'Enter' key while typing
        this._on(this.element.find('input.search_on_enter'),{
            keypress: this.startSearchOnEnterPress
        });

        // Save extra settings before exiting
        this._on(this._as_dialog, {
            dialogbeforeclose: () => {
                if(!this._forceClose){
                    this._forceClose = true;
                    this._saveExtraSettings(true);
                    return false;
                }
            }
        });

        return this._super();
    },

    /**
     * Function handler for pressing the enter button while focused on input element
     *
     * @param {KeyboardEvent} e - keypress event
     */
    startSearchOnEnterPress: function(e){
        if (e.key === 'Enter' || e.key === 'NumpadEnter') {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }
    },

    /**
     * Initial dialog buttons on bottom bar, _getActionButtons() under recordAction.js
     * Get and customise dialog buttons
     * By default there are two 'Go' and 'Cancel', these can be edited or more buttons can be added
     * 
     * buttons[0] => Cancel/Close: Closes dialog
     * buttons[1] => Go/Select: Calls doAction
     * 
     * @returns {array} array of dialog buttons
     */
    _getActionButtons: function(){

        let buttons = this._super(); // setups and retrieves default dialog buttons

        buttons[1].text = window.hWin.HR(this.action_button_label);

        return buttons;
    },

    _rendererResultList: function(recordset, record){

        const rec_ID = recordset.fld(record, 'rec_ID');
        const rec_Title = recordset.fld(record, 'rec_Title');
        const rec_RecTypeID = recordset.fld(record, 'rec_RecTypeID');
        const rec_Icon = window.hWin.HAPI4.iconBaseURL + rec_RecTypeID;

        const thumb = `<div class="recTypeThumb" style="background-image: url(&quot;${rec_Icon}&version=thumb&quot;);"></div>`;

        const html = `<div class="recordDiv" id="rd${rec_ID}" recid="${rec_ID}" rectype="${rec_RecTypeID}">`
                    + thumb
                    + '<div class="recordIcons">'
                        + `<img src="${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif" class="rt-icon" `
                        +   `style="background-image: url(&quot;${rec_Icon}&quot;)" />`
                    + '</div>'
                    + rec_Title
                + '</div>';

        return html;
    },

    /**
     * Perform final actions before exiting popup
     *
     * @param {json|boolean} dlg_reponse - mapped values to fields, or false to return nothing
     */
    closingAction: function(dlg_response){

        if(dlg_response !== false && window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        window.hWin.HEURIST4.msg.sendCoverallToBack(true);

        // Pass mapped values back and close dialog
        this._context_on_close = dlg_response;
        this._as_dialog.dialog('close');
    },

    /**
     * Display result via the Heurist resultList widget
     *
     * @param {json|boolean|null} data - data to display
     * @param {boolean} is_record_set - whether the data is ready for HRecordSet, usually a response from Heurist
     */
    _onSearchResult: function(data, is_record_set=false){

        this.recordList.show();

        let invalid_data = data === false 
            || !window.hWin.HEURIST4.util.isObject(data) 
            || (!is_record_set && (!Object.hasOwn(data, 'fields') || !Object.hasOwn(data, 'order') || !Object.hasOwn(data, 'records')));

        if(invalid_data && data !== null){

            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Service did not return data in an appropriate format',
                error_title: 'No valid data'
            });

            return;
        }else if(!data){
            this.recordList.resultList('updateResultSet', null);
            return;
        }

        let res_recordset = null;
        if(!is_record_set){

            let fields = data.fields;
            let orders = data.order;
            let records = data.records;

            res_recordset = new HRecordSet({
                count: orders.length,
                offset: 0,
                fields: fields,
                rectypes: [this.options.mapping.rty_ID],
                records: records,
                order: orders,
                mapenabled: true
            });
        }else{
            res_recordset = new HRecordSet(data);
        }

        this.recordList.resultList('updateResultSet', res_recordset);

        if(this.tabs_container && this.tabs_container.tabs('instance') !== undefined){
            this.tabs_container.tabs('option', 'active', this.results_tab);
        }
    },

    _saveExtraSettings: function(close_dlg = false){
        if(close_dlg === true){
            this._forceClose = true;
            this._as_dialog.dialog('close');
        }
    },

    //
    // Create new records with provided field values
    //
    _addNewRecord: function(rec_RecTypeID, details){

        let that = this;

        let request = {
            a: 'save',
            ID: 0,
            RecTypeID: rec_RecTypeID,
            details: details
        };

        window.hWin.HAPI4.RecordMgr.saveRecord(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            if(response.status == window.hWin.ResponseStatus.OK){
                // ... Complete final tasks, then
                that._as_dialog.dialog('close'); // close dialog
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }
});