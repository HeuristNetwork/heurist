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

$.widget("heurist.lookupESTC_editions", $.heurist.lookupBase, {

    options: {

        height: 540,
        width: 820,

        title: 'Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record',

        htmlContent: 'lookupLRC18C.html'
    },

    //    
    //
    //
    _initControls: function () {

        let that = this;
        this.element.find('fieldset > div > .header').css({width: '85px', 'min-width': '85px'});

        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">Nothing found</div>'
        });

        this.element.parents('.ui-dialog').find('#btnDoAction').hide()

        this._on(this.element.find('#btnLookupLRC18C').button(), {
            'click': this._doSearch
        });

        // Set search button status based on the existence of input
        this._on(this.element.find('input'), {
            'keyup': function(event){

                let $inputs_with_value = that.element.find('input').filter(function(){ return $(this).val(); });

                if($(event.target).val() != ''){
                    window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), false);
                }else if($inputs_with_value.length == 0){
                    window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), true);
                }
            }
        });
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), true);

        //Populate Bookformat dropdown on lookup page
        let request = {
            serviceType: 'ESTC',
            db:'ESTC_Helsinki_Bibliographic_Metadata',
            a: 'search', 
            entity: 'defTerms', 
            details: 'list', 
            request_id: window.hWin.HEURIST4.util.random(),
            trm_ParentTermID: 5430
        };

        let selBf = this.element.find('#select_bf').empty();
        window.hWin.HEURIST4.ui.addoption(selBf[0], 0, 'select...'); //first option

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status == window.hWin.ResponseStatus.OK){
                let recordset = new HRecordSet(response.data);
                recordset.each2(function(trm_ID, term){
                     window.hWin.HEURIST4.ui.addoption(selBf[0], trm_ID, term['trm_Label']);
                });
            }
        });

        //by default action button is disabled
        window.hWin.HEURIST4.util.setDisabled(this.element.parents('.ui-dialog').find('#btnDoAction'), false);

        return this._super();
    },

    // getActionButtons

    /* Render Lookup query results */
    _rendererResultList: function (recordset, record) {

        recordset.setFld(record, 'rec_RecTypeID', this.options.mapping.rty_ID);

        const rec_Title = recordset.fld(record, 'rec_Title');
        recordset.setFld(record, 'rec_Title', `<div class="recordTitle" style="left:30px;right:2px">${rec_Title}</div>`);
    },

    /* Show a confirmation window after user selects a record from the lookup query results */
    /* If the user clicks "Check Author", then call method _checkAuthor*/
    doAction: function () {
        let that = this;
        
        let sels = this.recordList.resultList('getSelected', false); //get complete record that's been selected
        if(!sels){ return; }

        let dlg_response = {};
        let fields = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        if(sels.length() == 1){

            let record = sels.getFirstRecord();
            let details = record.d;

            if(!details){
                let sel_Rec_ID = sels.fld(record, 'rec_ID'); 
                const query_request = { 
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
            
            
            let recpointers = [];
            let term_id = '';

            for(const fld_Name of fields){

                let dty_ID = this.options.mapping.fields[fld_Name];

                if(dty_ID == '' || !dty_ID){
                    continue;
                }

                // defintions mapping can be found in the original version => lookupLRC18C.js
                switch (fld_Name) {
                    case 'originalID':
                        dlg_response[dty_ID] = sels.fld(record, 'rec_ID');
                        break;
                    case 'title':
                        dlg_response[dty_ID] = details[1] ? details[1] : '';
                        break;
                    case 'estcID':
                        dlg_response[dty_ID] = details[254] ? details[254] : '';
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

            const query_request = { 
                serviceType: 'ESTC',
                org_db: window.hWin.HAPI4.database,
                db: 'ESTC_Helsinki_Bibliographic_Metadata',
                q: `ids:"${recpointers.join(',')}"`, 
                detail: 'detail' 
            };
            
            window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

            window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){

                response = window.hWin.HEURIST4.util.isJSON(response);

                if(response.status == window.hWin.ResponseStatus.OK){

                    let recordset = new HRecordSet(response.data);
                    recordset.each2(function(id, record){
                        for(let i in dlg_response){
                            for(let j = 0; j < dlg_response[i].length; j++){
                                if(dlg_response[i][j] == id){
                                    dlg_response[i][j] = record['rec_Title'];
                                }
                            }
                        }
                    });

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

        let that = this;

        let query = {"t":"30"}; //search for Books

        if (this.element.find('#edition_name').val() != '') {
            query['f:1'] = `@${this.element.find('#edition_name').val()}`;
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
            query['f:137'] = `=${this.element.find('#vol_count').val()}`;

        }
        if (this.element.find('#vol_parts').val() != '') {
            query['f:290'] = `=${this.element.find('#vol_parts').val()}`;
        }
        if (this.element.find('#select_bf').val()>0) {
            query['f:256'] = this.element.find('#select_bf').val();
            //query['all'] = this.element.find('#select_bf option:selected').text();  //enum
        }
        if (this.element.find('#estc_no').val() != '') {
            query['f:254'] = `@${this.element.find('#estc_no').val()}`;
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
    // Build each Book(Edition) as a record to display list of records that can be selected by the user
    //
    _onSearchResult: function (response) {
        if(!response.data){
            response.data = response;
        }
        this._super(response.data, true);
    },
});
