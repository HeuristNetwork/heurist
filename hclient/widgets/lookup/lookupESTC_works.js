/**
 * lookupESTC_works.js
 *
 *  1) Loads html content from lookupESTC_works.html
 *  2) User defines search parameters and searches Works (rt:49) in ESTC database
 *  3) Found records are show in list. User selects a record (var sels)
 *  4) doAction calls search for details about selected work's enum fields Retrieves data from ESTC_Helsinki_Bibliographic_Metadata
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     4.0
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget("heurist.lookupESTC_works", $.heurist.lookupBase, {

    options: {

        height: 540,
        width: 820,

        title: 'Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record',

        htmlContent: 'lookupESTC_works.html'
    },

    //    
    //
    //
    _initControls: function () {

        let that = this;
        this.element.find('fieldset > div > .header').css({width: '100px', 'min-width': '100px'});

        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">Nothing found</div>'
        });

        this.element.parents('.ui-dialog').find('#btnDoAction').hide()

        this._on(this.element.find('#btnLookupLRC18C').button(), {
            click: this._doSearch
        });

        // Set search button status based on the existence of input
        this._on(this.element.find('input'), {
            keyup: function(event){

                let $inputs_with_value = that.element.find('input').filter(function(){ return $(this).val(); });

                if($(event.target).val() != ''){
                    window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), false);
                }else if($inputs_with_value.length == 0){
                    window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), true);
                }
            }
        });
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), true);

        this.element.find('#btnLookupLRC18C').parent().parent().position({
            my: 'left center',
            at: 'right center',
            of: '#ent_header > fieldset'
        });

        //by default action button is disabled
        window.hWin.HEURIST4.util.setDisabled(this.element.parents('.ui-dialog').find('#btnDoAction'), false);

        return this._super();
    },

    // getActionButtons

    // Render Lookup query results
    _rendererResultList: function (recordset, record) {

        recordset.setFld(record, 'rec_RecTypeID', this.options.mapping.rty_ID);

        const rec_Title = recordset.fld(record, 'rec_Title');
        recordset.setFld(record, 'rec_Title', `<div class="recordTitle" style="left:30px;right:2px">${rec_Title}</div>`);
    },

    // Show a confirmation window after user selects a record from the lookup query results
    // If the user clicks "Check Author", then call method _checkAuthor
    doAction: function () {

        let that = this;

        let sels = this.recordList.resultList('getSelected', false); //get complete record that's been selected
        if(!sels || sels.length() != 1){ return; }

        let dlg_response = {};
        let fields = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        let record = sels.getFirstRecord();
        let details = record.d;

        if(!details){

            let sel_Rec_ID = sels.fld(record, 'rec_ID'); 
            let query_request = { 
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
                    
                    let recordset = new HRecordSet(response.data);
                    let record = recordset.getFirstRecord();
                    if(!record || !record.d){
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'We are having trouble performing your request on the ESTC server. '
                                    +`Impossible obtain details for selected record ${sel_Rec_ID}`,
                            error_title: 'Issues with ESTC server',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    }else{
                        let recset = that.recordList.resultList('getRecordSet');
                        recset.addRecord2(sel_Rec_ID, record);
                        that.doAction();        
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
            return;
        }

        for(const fld_Name of fields){

            let dty_ID = this.options.mapping.fields[fld_Name];

            if(dty_ID == '' || !dty_ID){
                continue;
            }

            // defintions mapping can be found in the original version => lookupLRC18C.js
            switch (fld_Name) {
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

        let term_id = '';

        if(details[298]){
            term_id = details[298][0];
        }

        if(term_id != ''){

            let request = {
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

                    let recordset = new HRecordSet(response.data);
                    recordset.each2(function(id, record){
                        for(let i in dlg_response){
                            for(let j = 0; j < dlg_response[i].length; j++){
                                if(dlg_response[i][j] == id){
                                    dlg_response[i][j] = record['trm_Label'];
                                }
                            }
                        }
                    });

                    that.closingAction(dlg_response);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        }else{
            that.closingAction(dlg_response);
        }
    },

    // Get the user input from lookupESTC_works.html and build the query string
    // Then lookup ESTC database if the query produces any search results
    _doSearch: function () {

        let that = this;

        let query = {"t":"49"}; //search for Works

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

        if (this.element.find('#sort_by_field').val() > 0) { // Sort by field
            let sort_by_key = "'sortby'"
            query[sort_by_key.slice(1, -1)] = `f:${this.element.find('#sort_by_field').val()}`;
        }

        let missingSearch = (Object.keys(query).length <= 2); // query has t and sortby keys at minimum

        if(missingSearch){
            window.hWin.HEURIST4.msg.showMsgFlash('Please specify some criteria to narrow down the search...', 1000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let query_request = { 
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
                    window.hWin.HEURIST4.msg.showMsgDlg(`Your request generated ${response.data.count} results. `
                        + `Only first ${response.data.reccount} have been retrieved. `
                        + 'You may specify more restrictive criteria to narrow the result.');
                    response.data.count = response.data.reccount;
                }
                that._onSearchResult(response);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },    

    //
    // Build each Works as a record to display list of records that can be selected by the user
    //
    _onSearchResult: function (response){
        if(!response.data){
            response.data = response;
        }
        this._super(response.data, true);
    },
});
