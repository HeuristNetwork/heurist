/**
 * LRC18C.js
 *
 *  1) Loads html content from LRC18C.html
 *  2) User defines search parameters and searches Book(edition) (rt:30) in ESTC database
 *  3) Found records are show in list. User selects a record (var sels)
 *  4) doAction calls doImportAction with request  
 *          selected book ids and linked Agent(rt:10), Place (12) and Work (49)
 *      Imports data from ESTC_Helsinki_Bibliographic_Metadata to Libraries_Readers_Culture_18C_Atlantic
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney
 * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
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

mapDict = {}
$.widget("heurist.lookupLRC18C", $.heurist.recordAction, {

    //defintions mapping
    // source rectype: target rectype
    mapping_defs:{
        10:{rty_ID:10, key:253,  //Author->Agent
            details: { //source:target
                //Standarised Name
                250: 1,
                //Given Name
                18: 18,
                //Designation
                248: 999,
                //Family Name
                1: 1046,
                //ESTC Actor ID
                252: 1098,
                //ESTC Name Unified
                253: 1086, //KEY
                //Also Known As
                287: 132,
                //Birth Date
                10:  10,
                //Death Date
                11:  11,
                // Prefix
                249: 1049,   //ENUM!
                // Suffix to Name
                279: 1050,
                // Agent Type
                278: 1000   //ENUM!
        }},

        49:{rty_ID:56, key:271,  //Work->Work
            details: { 
                //Title
                1: 1,
                //Full/Extended title
                276: 1091,
                //Project Record ID
                271: 1092,
                //Helsinki Work Name
                273: 1093
                //272 => Helsinki Work ID
                //298 => Helsinki Work ID assignation
                //236 => Helsinki raw data
        }},    

        
        12:{rty_ID:12, key:268,  //Place->Place
            details: { 
                //Title
                1: 1,
                //Region
                260: 939,  //ENUM
                //Country
                264: 940,  //ENUM
                //ESTC location ID                
                265: 1089, 
                //Place type
                133: 133, //ENUM  Cleaned Upper Territory vocab
                //ESTC Place ID
                268: 1090  //KEY
        }},    
        
        
        30:{rty_ID:55, key:254,  //Book(edition)->Edition
            details: {
                //Title
                1: 1,
                // Year of First Volume
                9: 10,
                // Year of Final Volume
                275: 955,
                //Book Format
                256: 991,  //ENUM
                //Plase - POINTER
                259: 238,
                //Summary Publisher Statement
                285: 1096,
                //ESTC ID
                254: 1094,  //KEY
                //Author - POINTER
                15: 1106,
                //Work - POINTER
                284: 949,
                //Extended Edition title
                277: 1095,
                //No of volumes
                137: 962,
                // No of parts
                290: 1107,
                // Imprint details
                270: 652
        }},
        
        vocabularies:[
            5430, //1321-5430 book formats for 256 => 991  6891 ( 1323-6891 ) - SYNCED!
            5432,  //  ( 1321-5432 )region 18C for 260 => 939    6353
            5436,  //  ( 1321-5436 ) country 18C for 264 => 940   6499  BT Sovereign territory 18 century
            5039, //  ( 3-5039 ) place type for 133 =>    5039  ( 3-5039 )
            507,  //    ( 2-507 ) prefix/honorofic for 249:1049       7124 (1323-7124)  - SYNCED
            5848  // ( 1321-5848 now  1323-6901)  agent type for 278: 1000   6901 (1323-6901)   - SYNCED
         ]

    },

/*

hdb_estc_helsinki_bibliographic_metadata hdb_artem_lrc

update hdb_artem_lrc.defTerms t2, defTerms t1 set t2.trm_OriginatingDBID=t1.trm_OriginatingDBID, t2.trm_IDInOriginatingDB= t1.trm_IDInOriginatingDB
where t1.trm_Label=t2.trm_Label and t2.trm_ParentTermID=6891 and t1.trm_ParentTermID=5430;

honorific

update hdb_artem_lrc.defTerms t2, defTerms t1 set t2.trm_OriginatingDBID=t1.trm_OriginatingDBID, t2.trm_IDInOriginatingDB= t1.trm_IDInOriginatingDB
where t1.trm_Label=t2.trm_Label and t2.trm_ParentTermID=7124 and t1.trm_ParentTermID=507;



use hdb_ESTC_Helsinki_Bibliographic_Metadata;
SELECT t1.trm_Label, t1.trm_OriginatingDBID, t1.trm_IDInOriginatingDB, t2.trm_OriginatingDBID, t2.trm_IDInOriginatingDB FROM defTerms t1
left join hdb_Libraries_Readers_Culture_18C_Atlantic.defTerms t2 on t1.trm_Label=t2.trm_Label and t2.trm_ParentTermID=6891
where t1.trm_ParentTermID=5430 order by t1.trm_Label;


use hdb_ESTC_Helsinki_Bibliographic_Metadata;
SELECT t1.trm_Label, t1.trm_OriginatingDBID, t1.trm_IDInOriginatingDB, t2.trm_OriginatingDBID, t2.trm_IDInOriginatingDB FROM defTerms t1
left join hdb_artem_lrc.defTerms t2 on BINARY t1.trm_Label=t2.trm_Label and t2.trm_ParentTermID=7124
where t1.trm_ParentTermID=507 order by t1.trm_Label;

*/
    
    

    options: {
        height: 540,
        width: 820,
        modal: true,
        show_toolbar: true,   //toolbar contains menu,savefilter,counter,viewmode and paginathorizontalion
        show_menu: true,       //@todo ? - replace to action_select and action_buttons
        support_collection: true,
        show_savefilter: false,
        add_new_record: false,
        show_counter: true,
        show_viewmode: true,
        mapping: null, //configuration from record_lookup_configuration.json
        edit_fields: null,  //realtime values from edit form fields
        edit_record: false,  //recordset of the only record - currently editing record (values before edit start)
        title: 'Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record',
        htmlContent: 'lookupLRC18C.html',
        helpContent: null, //help file in context_help folder

    },
    recordList: null,

    //    
    //
    //
    _initControls: function () {

        var that = this;
        this.element.find('fieldset > div > .header').css({width: '100px', 'min-width': '100px'})
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

        this._on(this.element.find('#btnLookupLRC18C').button(), {
            'click': this._doSearch
        });
        this._on(this.element.find('input'), {
            'keypress': this.startSearchOnEnterPress
        });

        // Set search button status based on the existence of input
        this._on(this.element.find('input'), {
            'keyup': function(event){
                if($(event.target).val() != ''){
                    window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), false);
                }else{
                    window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), true);
                }
            }
        });
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btnLookupLRC18C'), true);

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
        fld('properties.edition', 15)

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
        
        var sels = this.recordList.resultList('getSelected', true); //ids of selected records

        if(sels && sels.length>0){
            
                window.hWin.HEURIST4.msg.bringCoverallToFront( that._as_dialog.parent() );
                
                //avoid sync on every request
                this.mapping_defs['import_vocabularies'] = window.hWin.HEURIST4.dbs.vocabs_already_synched?0:1;

/* Artem old            
                var request = { action: 'import_records',
                    source_db: 'ESTC_Helsinki_Bibliographic_Metadata',
                    q: 'ids:'+sels.join(','), 
                    rules: '[{"query":"t:10 linkedfrom:30-15"},{"query":"t:12 linkedfrom:30-259"},{"query":"t:49 linkedfrom:30-284"}]',
                    mapping: this.mapping_defs,
                    //session: session_id,
                    id: window.hWin.HEURIST4.util.random()
                };
                window.hWin.HAPI4.doImportAction(request, function( response ){
                
*/            
            
                var request = { 
                    serviceType: 'ESTC',
                    action: 'import_records',
                    source_db: 'ESTC_Helsinki_Bibliographic_Metadata',
                    org_db: window.hWin.HAPI4.database,
                    db: window.hWin.HAPI4.database,
                    q: 'ids:'+sels.join(','), 
                    rules: '[{"query":"t:10 linkedfrom:30-15"},{"query":"t:12 linkedfrom:30-259"},{"query":"t:49 linkedfrom:30-284"}]',
                    mapping: this.mapping_defs,
                    //session: session_id,
                    id: window.hWin.HEURIST4.util.random()
                };

                window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function( response ){

                    response = window.hWin.HEURIST4.util.isJSON(response);
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        var target_dty_ID = that.options.mapping.fields['properties.edition']
        
                        var cnt = response.data.count_imported;
                        var cnt_ex = response.data.cnt_exist;
                        var cnt_i = response.data.count_ignored;
                        var ids = response.data.ids;  //all
                        var ids_ex  = response.data.exists; //skipped
                        if(!ids_ex) ids_ex = [];
                        
                        var rec_ids = ids.concat(ids_ex);
                        
                        var query_request = { 
                            serviceType: 'ESTC',
                            org_db: window.hWin.HAPI4.database,
                            db: 'ESTC_Helsinki_Bibliographic_Metadata',
                            q: 'ids:"' + rec_ids.join(',') + '"', 
                            w: 'a',
                            detail: 'header' 
                        };

                        //find record titles
                        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request,
                            function(response){

                                response = window.hWin.HEURIST4.util.isJSON(response);

                                if(response.status == window.hWin.ResponseStatus.OK){                        
                                    
                                    var sImported = '', sExisted = '';
                                    
                                    var recordset = new hRecordSet(response.data);
                                    
                                    if(cnt>0){
                                        for(var i=0; i<ids.length; i++)
                                        if(ids_ex.indexOf(ids[i])<0)
                                        {
                                            var rec = recordset.getById(ids[i])  
                                            sImported += ('<li>' + ids[i]+': '+recordset.fld(rec,'rec_Title') + '</li>');
                                        }         
                                        sImported = '<ul>' + sImported + '</ul>';                                             
                                    }
                                    if(cnt_ex>0){
                                        for(var i=0; i<ids_ex.length; i++)
                                        {
                                            var rec = recordset.getById(ids_ex[i])  
                                            sExisted += ('<li>' + ids_ex[i]+': '+recordset.fld(rec,'rec_Title') + '</li>');
                                        }         
                                        sExisted = '<ul>' + sExisted + '</ul>';                                             
                                    }
                                    
                                    window.hWin.HEURIST4.msg.showMsgDlg('<p>Lookup has been completed.</p>'
                                    
                                    +cnt+' record'+(cnt>1?'s are':' is')+' imported.<br>'
                                        +sImported
                                        
                                    +(cnt_ex>0
                                    ?(cnt_ex+' record'+(cnt_ex>1?'s are':' is')+' already in database'):'')
                                        +sExisted
                                        
                                    +(cnt_i>0
                                    ?(cnt_i+' record'+(cnt_i>1?'s are':' is')
                                    +' skipped. Either record type is not set in mapping or is missing from this database'):''));
                                    
                                }
                            }
                        );

                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        //pass mapped values and close dialog
                        that._context_on_close = {};
                        that._context_on_close[target_dty_ID] = ids[0];
                        that._as_dialog.dialog('close');
                        
                        window.hWin.HEURIST4.dbs.vocabs_already_synched = true;
                        
                    }else{
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg('Select at least one source record');
        }        
    },

// config    
//{"ESTC":{"service":"ESTC","rty_ID":"94","label":"Lookup ESTC","description":"",
//"dialog":"LRC18C","fields":{"properties.edition":"1028"}}}


    /* Get the user input from lookupLRC18C.html and build the query string */
    /* Then lookup ESTC database if the query produces any search results */
    _doSearch: function () {

        var that = this;

        edition_name = "";
        edition_date = "";
        edition_author = "";
        edition_work = "";
        edition_place = "";
        estc_no = "";
        vol_count = "";
        vol_parts = "";

        if(true){ //use json format and fulltext search

            var query = {"t":"30"}; //search for Books

            if (this.element.find('#edition_name').val() != '') {
                query['f:1 '] = '@'+this.element.find('#edition_name').val() ;
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

            if (this.element.find('#sort_by_field').val() > 0) { // Sort by field
                sort_by_key = "'sortby'"
                query[sort_by_key.slice(1, -1)] = 'f:' + this.element.find('#sort_by_field').val();
            }

            query_string = query;

        }else{
            if (this.element.find('#edition_name').val() != '') {
                edition_name = ' f:1: ' + '"' + this.element.find('#edition_name').val() + '" '
            }
            if (this.element.find('#edition_date').val() != '') {
                edition_date = ' f:9: ' + '"' + this.element.find('#edition_date').val() + '"'
            }
            if (this.element.find('#edition_author').val() != '') {
                edition_author = ' f:15: ' + '"' + this.element.find('#edition_author').val() + '"'
            }
            if (this.element.find('#edition_work').val() != '') {
                edition_work = ' f:284: ' + '"' + this.element.find('#edition_work').val() + '"'
            }
            if (this.element.find('#edition_place').val() != '') {
                edition_place = ' f:259: ' + '"' + this.element.find('#edition_place').val() + '"'
            }
            if (this.element.find('#vol_count').val() != '') {
                vol_count = ' f:137: ' + '"' + this.element.find('#vol_count').val() + '"'

            }
            if (this.element.find('#vol_parts').val() != '') {
                vol_parts = ' f:290: ' + '"' + this.element.find('#vol_parts').val() + '"'
            }

            selectedBF = this.element.find('#select_bf option:selected').text()
            if (selectedBF != null && selectedBF != '' && selectedBF != "Select Book Format") {
                book_format = 'all: ' + selectedBF
            } else {
                book_format = ""
            }
            if (this.element.find('#estc_no').val() != '') {
                estc_no = ' f:254: ' + '"' + this.element.find('#estc_no').val() + '"'
            }
            query_string = 't:30 ' + edition_name + edition_date + edition_author + edition_work + edition_place + book_format + estc_no + vol_count + vol_parts;
        }

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
    
    /* Build each Book(Edition) as a record to display list of records that can be selected by the user*/
    _onSearchResult: function (response) {
        this.recordList.show();
        var recordset = new hRecordSet(response.data);
        this.recordList.resultList('updateResultSet', recordset);
    },
});