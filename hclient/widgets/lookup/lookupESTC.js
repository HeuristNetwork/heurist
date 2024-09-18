/**
* lookupESTC.js - Base widgt for ESTC lookups
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

$.widget("heurist.lookupESTC", $.heurist.lookupBase, {

    options: {

        height: 540,
        width: 820,

        title: 'Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record',

        htmlContent: ''
    },

    _is_works: false,

    _init: function(){

        this._is_works = this.options.mapping.service == 'ESTC_works';

        this.options.htmlContent = !this._is_works ? 'lookupLRC18C.html' : 'lookupESTC_works.html';

        return this._super();
    },

    _initControls: function(){

        let px = this._is_works ? 100 : 80;
        this.element.find('fieldset > div > .header').css({width: `${px}px`, 'min-width': `${px}px`});

        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">Nothing found</div>'
        });

        this._populateBookFormats();

        return this._super();
    },

    /**
     * Setting record type and title fields before formatting HTML
     *
     * @param {HRecordSet} recordset - complete record set
     * @param {array} record - current record being rendered
     * @returns 
     */
    _rendererResultList: function (recordset, record) {

        recordset.setFld(record, 'rec_RecTypeID', 1);

        const rec_Title = recordset.fld(record, 'rec_Title');
        recordset.setFld(record, 'rec_Title', `<div class="recordTitle" style="left:30px;right:2px">${rec_Title}</div>`);

        return this._super(recordset, record);
    },

    /**
     * Retrieve and add options to the book format dropdown, formats are retrieve from ESTC database
     *
     * @returns {void}
     */
    _populateBookFormats: function(){

        if(this.element.find('#select_bf').length == 0){
            return;
        }

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

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (response) => {

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                return;
            }

            let recordset = new HRecordSet(response.data);
            recordset.each2((trm_ID, term) => {
                window.hWin.HEURIST4.ui.addoption(selBf[0], trm_ID, term['trm_Label']);
            });
        });
    },

    /**
     * Retrieve the detail fields for the suppied record from the ESTC database
     *  These are not retrieved early due to how large and un-necessary 
     *  it would be to retrieve ALL details for ALL results
     *
     * @param {HRecordSet} recset - complete record set
     * @param {array} record - the relevent record missing record details
     */
    _getRecordDetails: function(recset, record){

        let that = this;

        let sel_Rec_ID = recset.fld(record, 'rec_ID'); 
        let query_request = { 
            serviceType: 'ESTC',
            org_db: window.hWin.HAPI4.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:${sel_Rec_ID}`, 
            detail: 'detail' 
        };
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){
            
            window.hWin.HEURIST4.msg.sendCoverallToBack();
            
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);
            let record = recordset.getFirstRecord();
            if(!record?.d){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'We are having trouble performing your request on the ESTC server. '
                            +`Impossible obtain details for selected record ${sel_Rec_ID}`,
                    error_title: 'Issues with ESTC server',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
                return;
            }

            // Update record + recordset
            let recset = that.recordList.resultList('getRecordSet');
            recset.addRecord2(sel_Rec_ID, record);

            that.doAction();
        });
    },

    /**
     * Import selected records from ESTC database
     *
     * @param {array|string} rec_IDs - the records selectd by the user to be imported from the ESTC database
     */
    _importRecords: function(rec_IDs){

        let that = this;

        //avoid sync on every request
        this.mapping_defs['import_vocabularies'] = window.hWin.HEURIST4.dbs.vocabs_already_synched ? 0 : 1;

        rec_IDs = !Array.isArray(rec_IDs) ? rec_IDs.join(',') : rec_IDs;

        let request = { 
            serviceType: 'ESTC',
            action: 'import_records',
            source_db: 'ESTC_Helsinki_Bibliographic_Metadata',
            org_db: window.hWin.HAPI4.database,
            db: window.hWin.HAPI4.database,
            q: `ids:${rec_IDs}`,
            rules: '[{"query":"t:10 linkedfrom:30-15"},{"query":"t:12 linkedfrom:30-259"},{"query":"t:49 linkedfrom:30-284"}]',
            mapping: this.mapping_defs,
            id: window.hWin.HEURIST4.util.random()
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function( response ){

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let target_dty_ID = that.options.mapping.fields['properties.edition']

            let ids = response.data.ids; //all
            this._reportResults(ids.concat(ids_ex), response.data);

            window.hWin.HEURIST4.dbs.vocabs_already_synched = true;

            this.closingAction({[target_dty_ID]: ids[0]});
        });
    },

    /**
     * Import additional record from the ESTC database,
     *  these records belong to record pointer fields
     *
     * @param {json} dlg_response - alreay mapped field results, new record ids to be added
     * @param {array|string} rec_IDs - record ID(s) to import from ESTC database
     * @param {integer|array} term_ID - term ID(s) to import from ESTC database, after the records
     */
    _importRecPointers: function(dlg_response, rec_IDs, term_ID){

        let that = this;

        rec_IDs = !Array.isArray(rec_IDs) ? rec_IDs.join(',') : rec_IDs;

        let query_request = { 
            serviceType: 'ESTC',
            org_db: window.hWin.HAPI4.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:"${rec_IDs}"`, 
            detail: 'detail' 
        };
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);
            recordset.each2(function(id, record){
                for(const i in dlg_response){
                    
                    let assigned_title = false;

                    for(const j in dlg_response[i]){

                        if(dlg_response[i][j] == id){

                            dlg_response[i][j] = record['rec_Title'];
                            assigned_title = true;
                            break;
                        }
                    }

                    if(assigned_title){
                        break;
                    }
                }
            });

            if(window.hWin.HEURIST4.util.isempty(term_ID)){
                that.closingAction(dlg_response);
                return;
            }

            that._importTerms(dlg_response, term_ID);
        });
    },

    /**
     * Import missing terms from the ESTC database
     *
     * @param {json} dlg_response - alreay mapped field results, new term IDs to be added
     * @param {integer|array} term_ID - term ID(s) to import from ESTC database
     */
    _importTerms: function(dlg_response, term_ID){

        if(window.hWin.HEURIST4.util.isempty(term_ID)){
            that.closingAction(dlg_response);
            return;
        }

        let request = {
            serviceType: 'ESTC',
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            a: 'search',
            entity: 'defTerms',
            details: 'list', //name
            request_id: window.hWin.HEURIST4.util.random(),
            trm_ID: term_ID
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
        
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }
            
            let recordset = new HRecordSet(response.data);
            recordset.each2(function(id, record){
                for(const i in dlg_response){

                    let assigned_label = false;

                    for(const j in dlg_response[i]){

                        if(dlg_response[i][j] == id){

                            dlg_response[i][j] = record['trm_Label'];
                            assigned_label = true;
                            break;
                        }
                    }

                    if(assigned_label){
                        break;
                    }
                }
            });

            that.closingAction(dlg_response);
        });
    },

    /**
     * Report results from importing ESTC records
     *
     * @param {array|string} rec_IDs - array or comma list of record IDs
     * @param {json} data - counts and new record IDs to display in report
     */
    _reportResults: function(rec_IDs, data){

        let cnt = data.count_imported;
        let cnt_ex = data.cnt_exist;
        let cnt_i = data.count_ignored;
        let ids = data.ids; //all
        let ids_ex = data.exists; //skipped
        if(!ids_ex) ids_ex = [];

        let query_request = { 
            serviceType: 'ESTC',
            org_db: window.hWin.HAPI4.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:"${rec_IDs.join(',')}"`, 
            w: 'a',
            detail: 'header' 
        };

        //find record titles
        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                return;
            }
            
            let sImported = '', sExisted = '';

            let recordset = new HRecordSet(response.data);

            if(cnt > 0){
                for(const rec_ID of ids){
                    if(ids_ex.indexOf(rec_ID) < 0){
                        let rec = recordset.getById(rec_ID);
                        sImported += (`<li>${rec_ID}: ${recordset.fld(rec,'rec_Title')}</li>`);
                    }
                }
                sImported = `<ul>${sImported}</ul>`;
            }
            if(cnt_ex > 0){
                for(let i = 0; i < ids_ex.length; i++){
                    let rec = recordset.getById(ids_ex[i]);
                    sExisted += (`<li>${ids_ex[i]}: ${recordset.fld(rec,'rec_Title')}</li>`);
                }
                sExisted = `<ul>${sExisted}</ul>`;
            }

            let imported_extra = cnt > 1 ? 's are' : ' is';
            let existed_extra = cnt_ex > 1 ? 's are' : ' is';
            let skipped_extra = cnt_i > 1 ? 's are' : ' is';

            window.hWin.HEURIST4.msg.showMsgDlg('<p>Lookup has been completed.</p>'
                +`${cnt} record${imported_extra} imported.<br>`
                    +sImported
                +(cnt_ex>0
                ?(`${cnt_ex} record${existed_extra} already in database`) : '')
                    +sExisted
                +(cnt_i>0
                ?(`${cnt_i} record${skipped_extra}`
                +' skipped. Either record type is not set in mapping or is missing from this database') : '')
            );
        });
    },

    /**
     * Send query search to Heurist ESTC database
     *
     * @param {json} query - Heurst JSON query for ESTC database
     */
    _doSearch: function(query){

        let that = this;

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

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            if(response.data.count>response.data.reccount){
                window.hWin.HEURIST4.msg.showMsgDlg(`Your request generated ${response.data.count} results. `
                + `Only first ${response.data.reccount} have been retrieved. `
                + 'You may specify more restrictive criteria to narrow the result.');
                response.data.count = response.data.reccount;
            }

            that._onSearchResult(response);
        });
    },

    /**
     * Build each Book(Edition) [LRC18C|ESTC_editions] or Works [ESTC_works]
     *  as a record to display list of records that can be selected by the user
     *
     * @param {json} response - JSON response from local/remote Heurist server
     */
    _onSearchResult: function (response) {
        if(!response.data){
            response.data = response;
        }
        this._super(response.data, true);
    }
});