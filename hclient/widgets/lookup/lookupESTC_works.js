/**
 * lookupESTC_works.js
 *
 *  1) Loads html content from lookupESTC_works.html
 *  2) User defines search parameters and searches Works (rt:49) in ESTC database
 *  3) Found records are show in list. User selects a record (var sels)
 *  4) doAction calls search for details about selected work's enum fields Retrieves data from ESTC_Helsinki_Bibliographic_Metadata
 *
 * @package     Heurist academic knowledge management system
 * @link        http://HeuristNetwork.org
 * @copyright   (C) 2005-2020 University of Sydney
 * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
 * @author      Brandon McKay   <blmckay13@gmail.com>
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

$.widget("heurist.lookupESTC_works", $.heurist.recordAction, {

    options: {
        height: 540,
        width: 820,

        modal: true,

        mapping: null, //configuration from record_lookup_config.json
        edit_fields: null,  //realtime values from edit form fields
        edit_record: false,  //recordset of the current record being edited

        title: 'Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record',

        htmlContent: 'lookupESTC_works.html',
        helpContent: null, //help file in context_help folder

        add_new_record: false, //if true it creates new record on selection
    },
    recordList: null,

    //    
    //
    //
    _initControls: function () {

        var that = this;
        this.element.find('fieldset > div > .header').css({width: '85px', 'min-width': '85px'})
        this.options.resultList = $.extend(this.options.resultList,
            {
                recordDiv_class: 'recordDiv_blue',
                eventbased: false,  //do not listent global events

                multiselect: false, //(this.options.select_mode!='select_single'),

                select_mode: 'select_single', //this.options.select_mode,
                selectbutton_label: 'select!!', //this.options.selectbutton_label, for multiselect

                view_mode: 'list',
                show_viewmode: false,

                entityName: this._entityName,
                //view_mode: this.options.view_mode?this.options.view_mode:null,

                pagesize: (this.options.pagesize > 0) ? this.options.pagesize : 9999999999999,
                empty_remark: '<div style="padding:1em 0 1em 0">Nothing found</div>',
                renderer: this._rendererResultList,
            });

        //init record list
        this.recordList = this.element.find('#div_result');
        this.recordList.resultList(this.options.resultList);
        this.element.parents('.ui-dialog').find('#btnDoAction').hide()
        this._on(this.recordList, {
            "resultlistonselect": function (event, selected_recs) {
                window.hWin.HEURIST4.util.setDisabled(
                    this.element.parents('.ui-dialog').find('#btnDoAction'),
                    (selected_recs && selected_recs.length() != 1));
            },
            "resultlistondblclick": function (event, selected_recs) {
                if (selected_recs && selected_recs.length() == 1) {
                    this.doAction();
                }
            }
        });
        this.element.find('fieldset > div > .header').css({width: '85px', 'min-width': '85px'})
        this._on(this.element.find('#btnLookupLRC18C').button(), {
            'click': this._doSearch
        });
        this._on(this.element.find('input'), {
            'keypress': this.startSearchOnEnterPress
        });

        // Set search button status based on the existence of input
        this._on(this.element.find('input'), {
            'keyup': function(event){

                var $inputs_with_value = that.element.find('input').filter(function(){ return $(this).val(); });

                if($(event.target).val() != ''){
                    window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), false);
                }else if($inputs_with_value.length == 0){
                    window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), true);
                }
            }
        });
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), true);

        this.element.find('#btnLookupLRC18C').parent().parent().css('display', 'inline-block').position({
            my: 'left center',
            at: 'right center',
            of: $('#ent_header > fieldset')
        })

        //by default action button is disabled
        window.hWin.HEURIST4.util.setDisabled(this.element.parents('.ui-dialog').find('#btnDoAction'), false);

        return this._super();
    },

    /* Render Lookup query results */
    _rendererResultList: function (recordset, record) {
        function fld(fldname, width) {
            var s = recordset.fld(record, fldname);
            s = s ? s : '';
            if (width > 0) {
                s = '<div style="display:inline-block;width:' + width + 'ex" class="truncate">' + s + '</div>';
            }
            return s;
        }

        var recID = fld('rec_ID');
        var rectypeID = fld('rec_RecTypeID');
        var recIcon = window.hWin.HAPI4.iconBaseURL + '1';
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
            + window.hWin.HAPI4.iconBaseURL + '1&version=thumb&quot;);"></div>';

        var html = '<div class="recordDiv" id="rd' + record[2] + '" recid="' + record[2] + '" rectype="' + 1 + '">'
            + html_thumb

            + '<div class="recordIcons">'
            + '<img src="' + window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif'
            + '" class="rt-icon" style="background-image: url(&quot;' + recIcon + '&quot;);"/>'
            + '</div>'

            + '<div class="recordTitle" style="left:30px;right:2px">'
            + record[5]
            + '</div>'
            + '</div>';
        return html;
    },

    //
    //
    //
    startSearchOnEnterPress: function (e) {
        var code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }
    },
    
    /* Show a confirmation window after user selects a record from the lookup query results */
    /* If the user clicks "Check Author", then call method _checkAuthor*/
    doAction: function () {
        var that = this;
        
        var sels = this.recordList.resultList('getSelected', false); //get complete record that's been selected
        if(!sels){ return; }

        var dlg_response = {};
        var fields = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        if(sels.length() == 1){

            var record = sels.getFirstRecord();
            var details = record.d;

            if(!details){
                var sel_Rec_ID = sels.fld(record, 'rec_ID'); 
                var query_request = { 
                    serviceType: 'ESTC',
                    org_db: window.hWin.HAPI4.database,
                    db: 'ESTC_Helsinki_Bibliographic_Metadata',
                    q: 'ids:' + sel_Rec_ID, 
                    detail: 'detail' 
                };
                
                window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

                window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){
                    
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    
                    response = window.hWin.HEURIST4.util.isJSON(response);

                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        var recordset = new hRecordSet(response.data);
                        var record = recordset.getFirstRecord();
                        if(!record || !record.d){
                            window.hWin.HEURIST4.msg.showMsgErr(
                            'We are having trouble performing your request on the ESTC server. '
                            +'Impossible obtain details for selected record '+sel_Rec_ID);
                        }else{
                            var recset = that.recordList.resultList('getRecordSet');
                            recset.addRecord2(sel_Rec_ID, record);
                            that.doAction();        
                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
                return;
            }
            
            var recpointers = [];
            var term_id = '';

            for(var i = 0; i < fields.length; i++){

                var dty_ID = this.options.mapping.fields[fields[i]];
                var field_name = fields[i];

                if(dty_ID == '' || !dty_ID){
                    continue;
                }

                // defintions mapping can be found in the original version => lookupLRC18C.js
                switch (field_name) {
                    case 'title':
                        dlg_response[dty_ID] = details[1] ? details[1] : '';
                        break;
                    case 'extendedTitle':
                        dlg_response[dty_ID] = details[276] ? details[276] : '';
                        break;
                    case 'projectId':
                        dlg_response[dty_ID] = details[271] ? details[271] : '';
                        break;
                    case 'helsinkiTitle':
                        dlg_response[dty_ID] = details[273] ? details[273] : '';
                        break;
                    case 'helsinkiId':
                        dlg_response[dty_ID] = details[272] ? details[272] : '';
                        break;
                    case 'helsinkiIdAssignation':
                        dlg_response[dty_ID] = details[298] ? details[298] : '';
                        break;
                    case 'helsinkiRawData':
                        dlg_response[dty_ID] = details[236] ? details[236] : '';
                        break;
                    default:
                        break;
                }
            }

            var term_id = '';

            if(details[298]){
                term_id = details[298][0];
            }

            if(term_id != ''){

                var request = {
                    serviceType: 'ESTC',
                    db: 'ESTC_Helsinki_Bibliographic_Metadata',
                    a: 'search',
                    entity: 'defTerms',
                    details: 'list', //name
                    request_id: window.hWin.HEURIST4.util.random(),
                    trm_ID: term_id
                };

                window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
                
                window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

                    window.hWin.HEURIST4.msg.sendCoverallToBack(); 
                    response = window.hWin.HEURIST4.util.isJSON(response);

                    if(response.status == window.hWin.ResponseStatus.OK){

                        var recordset = new hRecordSet(response.data);
                        recordset.each2(function(id, record){ console.log(record);
                            for(var i in dlg_response){
                                for(var j = 0; j < dlg_response[i].length; j++){
                                    if(dlg_response[i][j] == id){
                                        dlg_response[i][j] = record['trm_Label'];
                                    }
                                }
                            }
                        });

                        that._context_on_close = dlg_response;
                        that._as_dialog.dialog('close');
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }else{

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                that._context_on_close = dlg_response;
                that._as_dialog.dialog('close');
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgDlg('Select at least one source record');
        }        
    },

    /* Get the user input from lookupESTC_works.html and build the query string */
    /* Then lookup ESTC database if the query produces any search results */
    _doSearch: function () {

        var that = this;

        var query = {"t":"49"}; //search for Works

        if (this.element.find('#work_name').val() != '') { // work_name
            query['f:1'] = this.element.find('#work_name').val() ;
        }
        if (this.element.find('#project_id').val() != '') { // project_id
            query['f:271'] = this.element.find('#project_id').val();
        }
        if (this.element.find('#helsinki_name').val() != '') { // helsinki_name
            query['f:273'] = this.element.find('#helsinki_name').val();
        }
        if (this.element.find('#helsinki_id').val() != '') { // helsinki_id
            query['f:272'] = this.element.find('#helsinki_id').val();
        }

        sort_by_key = "'sortby'"
        // query[sort_by_key.slice(1, -1)] = 'f:1:' It kills MariaDB database

        var missingSearch = (Object.keys(query).length <= 1); // query has t and sortby keys at minimum

        if(missingSearch){
            window.hWin.HEURIST4.msg.showMsgFlash('Please specify some criteria to narrow down the search...', 1000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        var query_request = { 
            serviceType: 'ESTC',
            org_db: window.hWin.HAPI4.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: query, 
            limit: 1000,
            detail: 'header' 
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status && response.status == window.hWin.ResponseStatus.OK){
                if(response.data.count>response.data.reccount){
                    window.hWin.HEURIST4.msg.showMsgDlg('Your request generated '
                    + response.data.count+' results. Only first '
                    + response.data.reccount 
                    + ' have been retrieved. You may specify more restrictive criteria '
                    + ' to narrow the result.');        
                    response.data.count = response.data.reccount;
                }
                that._onSearchResult(response);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },    
    
    /* Build each Works as a record to display list of records that can be selected by the user*/
    _onSearchResult: function (response) {
        this.recordList.show();
        if(!response.data){
            response.data = response;
        }
        var recordset = new hRecordSet(response.data);
        this.recordList.resultList('updateResultSet', recordset);
    },
});
