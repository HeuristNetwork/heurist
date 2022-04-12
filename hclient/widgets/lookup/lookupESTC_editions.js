/**
 * lookupESTC_editions.js
 *
 *  1) Loads html content from lookupLRC18C.html
 *  2) User defines search parameters and searches Book(edition) (rt:30) in ESTC database
 *  3) Found records are show in list. User selects a record (var sels)
 *  4) doAction calls search for details about selected book ids and linked Agent(rt:10), Place (12) and Work (49)
 *      Retrieves data from ESTC_Helsinki_Bibliographic_Metadata
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

$.widget("heurist.lookupESTC_editions", $.heurist.recordAction, {

    allowed_dbs: ['Libraries_Readers_Culture_18C_Atlantic', 'MPCE_Mapping_Print_Charting_Enlightenment', 'ESTC_Helsinki_Bibliographic_Metadata'],

    options: {
        height: 540,
        width: 820,

        modal: true,

        mapping: null, //configuration from record_lookup_config.json
        edit_fields: null,  //realtime values from edit form fields
        edit_record: false,  //recordset of the current record being edited

        title: 'Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record',

        htmlContent: 'lookupLRC18C.html',
        helpContent: null, //help file in context_help folder

        add_new_record: false, //if true it creates new record on selection
    },
    recordList: null,

    //
    // Check that the current db has access to this look up
    //
    _init: function(){

        if(this.allowed_dbs.indexOf(window.hWin.HAPI4.database) < 0){
            window.hWin.HEURIST4.msg.showMsgErr('For licensing reasons this function is only accessible to authorised projects.<br>Please contact the Heurist team if you wish to use this look up.');
            return false;
        }

        this._super();
    },

    //    
    //
    //
    _initControls: function () {

        var that = this;
        this.element.find('fieldset > div > .header').css({width: '80px', 'min-width': '80px'})
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
        this.element.find('fieldset > div > .header').css({width: '80px', 'min-width': '80px'})
        this._on(this.element.find('#btnLookupLRC18C').button(), {
            'click': this._doSearch
        });
        this._on(this.element.find('input'), {
            'keypress': this.startSearchOnEnterPress
        });

        //Populate Bookformat dropdown on lookup page
        var request = {
            serviceType: 'ESTC',
            db:'ESTC_Helsinki_Bibliographic_Metadata',
            a: 'search', 
            entity: 'defTerms', 
            details: 'list', 
            request_id: window.hWin.HEURIST4.util.random(),
            trm_ParentTermID: 5430
        };

        var selBf = this.element.find('#select_bf').empty();
        window.hWin.HEURIST4.ui.addoption(selBf[0], 0, 'select...'); //first option

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status == window.hWin.ResponseStatus.OK){
                var recordset = new hRecordSet(response.data);
                recordset.each2(function(trm_ID, term){
                     window.hWin.HEURIST4.ui.addoption(selBf[0], trm_ID, term['trm_Label']);
                });
            }
        });

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
                    case 'yearFirstVolume':
                        dlg_response[dty_ID] = details[9] ? details[9] : '';
                        break;
                    case 'yearLastVolume':
                        dlg_response[dty_ID] = details[275] ? details[275] : '';
                        break;
                    case 'summary':
                        dlg_response[dty_ID] = details[285] ? details[285] : '';
                        break;
                    case 'extendedTitle':
                        dlg_response[dty_ID] = details[277] ? details[277] : '';
                        break;
                    case 'numOfVolumes':
                        dlg_response[dty_ID] = details[137] ? details[137] : '';
                        break;
                    case 'numOfParts':
                        dlg_response[dty_ID] = details[290] ? details[290] : '';
                        break;
                    case 'imprintDetails':
                        dlg_response[dty_ID] = details[270] ? details[270] : '';
                        break;
                    case 'place':
                        dlg_response[dty_ID] = details[259] ? details[259] : '';
                        break;
                    case 'author':
                        dlg_response[dty_ID] = details[15] ? details[15] : '';
                        break;
                    case 'work':
                        dlg_response[dty_ID] = details[284] ? details[284] : '';
                        break;
                    case 'bookFormat':
                        dlg_response[dty_ID] = details[256] ? details[256] : '';
                        break;
                    default:
                        break;
                }
            }

            if(details[259]){ // Place - Rec Pointer
                recpointers.push(details[259]);
            }
            if(details[15]){ // Author - Rec Pointer
                recpointers.push(details[15]);
            }
            if(details[284]){ // Works - Rec Pointer
                recpointers.push(details[284]);
            }

            if(details[256]){ // Book format - Term
                term_id = details[256][0];
            }

            var query_request = { 
                serviceType: 'ESTC',
                org_db: window.hWin.HAPI4.database,
                db: 'ESTC_Helsinki_Bibliographic_Metadata',
                q: 'ids:"' + recpointers.join(',') + '"', 
                detail: 'detail' 
            };

            window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){

                response = window.hWin.HEURIST4.util.isJSON(response);

                if(response.status == window.hWin.ResponseStatus.OK){

                    var recordset = new hRecordSet(response.data);
                    recordset.each2(function(id, record){
                        for(var i in dlg_response){
                            if(dlg_response[i] == id){
                                dlg_response[i] = [record['rec_Title']];
                            }
                        }
                    });

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

                        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

                            window.hWin.HEURIST4.msg.sendCoverallToBack(); 
                            response = window.hWin.HEURIST4.util.isJSON(response);

                            if(response.status == window.hWin.ResponseStatus.OK){

                                var recordset = new hRecordSet(response.data);
                                recordset.each2(function(id, record){
                                    for(var i in dlg_response){
                                        if(dlg_response[i] == id){
                                            dlg_response[i] = [record['trm_Label']];
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
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg('Select at least one source record');
        }        
    },

    /* Get the user input from lookupLRC18C.html and build the query string */
    /* Then lookup ESTC database if the query produces any search results */
    _doSearch: function () {

        var that = this;

        var query = {"t":"30"}; //search for Books

        if (this.element.find('#edition_name').val() != '') {
            query['f:1'] = '@'+this.element.find('#edition_name').val() ;
        }
        if (this.element.find('#edition_date').val() != '') {
            query['f:9'] = this.element.find('#edition_date').val();
        }
        if (this.element.find('#edition_author').val() != '') {
            //Standardised agent name  - 250
            query['linkedto:15'] = {"t":"10", "f:250":this.element.find('#edition_author').val()};
        }
        if (this.element.find('#edition_work').val() != '') {
            query['linkedto:284'] = {"t":"49","f:272":this.element.find('#edition_work').val()};
            //Helsinki work ID - 272
            //Project Record ID - 271
        }
        if (this.element.find('#edition_place').val() != '') {
            query['linkedto:259'] = {"t":"12", "title":this.element.find('#edition_place').val()};
        }
        if (this.element.find('#vol_count').val() != '') {
            query['f:137'] = '='+this.element.find('#vol_count').val();

        }
        if (this.element.find('#vol_parts').val() != '') {
            query['f:290'] = '='+this.element.find('#vol_parts').val();
        }
        if (this.element.find('#select_bf').val()>0) {
            query['f:256'] = this.element.find('#select_bf').val();
            //query['all'] = this.element.find('#select_bf option:selected').text();  //enum
        }
        if (this.element.find('#estc_no').val() != '') {
            query['f:254'] = '@'+this.element.find('#estc_no').val();
        }
        sort_by_key = "'sortby'"
        query[sort_by_key.slice(1, -1)] = 'f:9:'

        var missingSearch = (Object.keys(query).length <= 2); // query has t and sortby keys at minimum

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
            detail: 'detail' 
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status && response.status == window.hWin.ResponseStatus.OK){
                that._onSearchResult(response);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },    
    
    /* Build each Book(Edition) as a record to display list of records that can be selected by the user*/
    _onSearchResult: function (response) {
        this.recordList.show();
        var recordset = new hRecordSet(response.data);
        this.recordList.resultList('updateResultSet', recordset);
    },
});
