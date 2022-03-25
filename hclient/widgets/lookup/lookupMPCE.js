/**
 * lookupMPCE.js
 *
 *  1) This file loads html content from lookupMPCE.html
 *  2) This file outlines the functionality of the MPCE toolkit, including:
 *      - Assigning New Keywords to a Super Book (Work) record
 *      - Looking up a list of keywords that have been assigned with a selected keyword in other works
 *      - Display a list of keywords assigned to previously viewed works
 *
 *
 *
 * @package     Heurist academic knowledge management system
 * @link        http://HeuristNetwork.org
 * @copyright   (C) 2005-2020 University of Sydney
 * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @author      Martin Yldh   <martinsami@yahoo.com>
 * @author      Staphanson Hudson   <staphanson98@hotmail.com>
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     5.0
 */

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.lookupMPCE", $.heurist.recordAction, {

        // default options
    options: {
        /* Setting popup size, based on user preference */
        height: (window.hWin.HAPI4.get_prefs_def('pref_lookupMPCE_h', 780)),
        width:  (window.hWin.HAPI4.get_prefs_def('pref_lookupMPCE_w', 1200)),
        modal:  true,

        mapping: null, //configuration from record_lookup_configuration.json

        title:  "Super Book (Work) Classification Tool for MPCE Project",

        htmlContent: "lookupMPCE.html",
        helpContent: null, //help file in context_help folder

        add_new_record: false,
    },

    id_map: {   // ID Map for Record Types, Detail Types, and Vocabulary. Assigned in mapIds
        RT_Editions: null,
        RT_Work: null,
        RT_Keyword: null,

        DT_MPCEId: null,
        DT_Category: null,
        DT_Keywords: null,
        DT_Basis: null,
        DT_Notes: null,

        VI_Category: null,
        VI_Basis: null
    },

    // Main Variables
    project_keywords: null, // array of keyword ids
    parisian_category: null,    // id of selected parisian category
    basis_for_classification: null, // id of selected basis for classification
    classification_notes: null, // entry for classification notes

    full_keywords_list: null, // object of all keywords

    // Associated Keyword Variables
    assoc_search_id: null,  // current keyword of interest
    assoc_keywords: null,   // the keywords in association with the selected keyword
    assoc_work_count: null, // the number of works that contain the selected keyword
    assoc_startindex: null, // the index of the current start of list 
    assoc_endindex: null,   // the index of the current end of list
    assoc_selected: null,   // array of selected keyword ids from the associated keyword list

    // Previously Assigned Keywords Variables
    prev_works: null,       // ids of the last 5 previous works
    prev_keywords: null,    // object of keywords assigned to previous works

    // Editions Variables
    editions_info: null,    // basic information about editions for this work

    _init: function(){
        this._super();
    },

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;

        this.mapIds(this.options.mapping.fields, this.options.mapping.rty_ID);    /* Set the session variable 'id_map' to be an object of all record, detail and vocab type ids to simplify the replacement task */

        //var record = this.options.edit_record.getFirstRecord(); /* Retrieve Record */
        var record = this.options.edit_fields;  /* Retrieve Edit Fields */

        /* Check if selected Work has: project keyword/s, parisian category, and a basis for classification */
        if (record[this.id_map.DT_Keywords] != null && record[this.id_map.DT_Keywords] != "") { // Project Keywords

            this.project_keywords = record[this.id_map.DT_Keywords];
        }

        if (record[this.id_map.DT_Category] != null && record[this.id_map.DT_Category] != "") { // Parisian Category

            this.parisian_category = record[this.id_map.DT_Category];
        }

        if (record[this.id_map.DT_Basis] != null && record[this.id_map.DT_Basis] != "") { // Basis for Classification

            this.basis_for_classification = record[this.id_map.DT_Basis];
        }

        if (record[this.id_map.DT_Notes] != null && record[this.id_map.DT_Notes] != "") { // Classification Notes

            this.classification_notes = record[this.id_map.DT_Notes];
            $('#notes_field').val(record[this.id_map.DT_Notes]);
        }

        if (localStorage.getItem("prev_classify") != null) {
            this.prev_works = window.hWin.HEURIST4.util.isJSON(localStorage.getItem("prev_classify"));
        }

        $('#title_field').text(record[1]); // Work Title
        $('#work-code_field').text(record[this.id_map.DT_MPCEId]);   // Work MPCE_ID

        /* Retrieve all Parisian Keywords (Term ID = 6380) */
        var parisian_Category = window.hWin.HEURIST4.ui.createTermSelect( this.element.find('#category_field')[0], {
                vocab_id: [this.id_map.VI_Category],    // Vocabulary ID/Term ID
                defaultTermID: this.parisian_category,   //Default/Selected Term
                topOptions: [{title:"Select a Parisian Classification...", key:''}],      //Top Options  [{key:0, title:'...select me...'},....]
                useHtmlSelect: false     // use native select of jquery select
            }
        );

        /* Retrieve all Basis for Classification (Term ID = 6498) */
        var basis = window.hWin.HEURIST4.ui.createTermSelect( this.element.find('#basis_field')[0], {
                vocab_id: [this.id_map.VI_Basis],    // Vocabulary ID/Term ID
                defaultTermID: this.basis_for_classification,   // Default/Selected Term
                useHtmlSelect: false    // use native select of jquery select
            }
        ); 

        this.getKeywords('assigned');
        
        /* Add msg next to save button */
        this.element.parents('.ui-dialog').find('#btnDoAction').before('<span id="save-msg" style="display:none;font-size:1.2em;">Add or Uncheck Selections</span>');        

        // NEXT >> handler
        $('#assoc_next').click(function(){
            $('#checkall-assoc').attr('checked', false);

            that.updateAssocDisplay(false);
        });

        /*$('#assoc_start').click(function(){
            that.updateAssocDisplay(true);
        });*/

        // << BACK handler
        $('#assoc_prev').click(function(){

            var jump = that.assoc_endindex - that.assoc_startindex;

            if(jump < 13) { jump = 13; }

            that.assoc_startindex = that.assoc_startindex - jump;
            that.assoc_endindex = that.assoc_startindex;
            that.assoc_startindex = that.assoc_startindex - (jump * 2);

            if(that.assoc_startindex < 0 && that.assoc_endindex < 13) {
                that.updateAssocDisplay(true);
            } else {
                that.updateAssocDisplay(false);
            }
            $('#checkall-assoc').attr('checked', false);            
        });


        /* onClick Handlers */

        /* Assigning Keywords to Work */
        this._on(this.element.find('#btnLookup').button(),{
            'click':this.keywordLookup
        });

        this._on(this.element.find('#btnPrevAssign').button(),{
            'click':this.addPrevtoAssigned
        });

        this._on(this.element.find('#btnAssocAssign').button(),{
            'click':this.addAssoctoAssigned
        });

        this._on(this.element.find('#btnAssocRemove').button(),{
            'click':this.unselectAssoc
        });

        /* External Searches for Work Title */
        this._on(this.element.find('#btnGoogle').button(),{
            'click':this.lookupGoogle
        });
        this._on(this.element.find('#btnWorldCat').button(),{
            'click':this.lookupWorldCat
        });
        this._on(this.element.find('#btnHathiTrust').button(),{
            'click':this.lookupHathiTrust
        });
        this._on(this.element.find('#btnKarlsruhePortal').button(),{
            'click':this.lookupKarlsruhePortal
        });

        /* Other */
        this._on(this.element.find('#btnEdition').button(),{
            'click':this.lookupEditions
        });

        this._on(this.element.find('#checkall-prev'),{
            'click': function(e){
                var check_status = $(e.target).is(':Checked');
                that.checkAllOptions($('#prev_field')[0], check_status);
            }
        });

        this._on(this.element.find('#checkall-assoc'),{
            'click': function(e){
                var check_status = $(e.target).is(':Checked');
                that.checkAllOptions($('#associated_field')[0], check_status, true);
            }
        });

        /* Set what the 'Update Record' button, bottom right of form, does */
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), false );

        /* Disable the 'X' button, located top-right corner */
        this.element.parent().find('.ui-dialog-titlebar-close').button().hide();
        /* Best Replaced with the same event used for the record editor */

        /* Detects the popup being resized, disable the mouseup as resize fires constantly */
        this.element.parent().resize(function() {
            that.element.parent().off("mouseup");

            that.element.parent().one("mouseup", function() {
                var width = that.element.parent().width();
                var height = that.element.parent().height();

                window.hWin.HAPI4.save_pref('pref_lookupMPCE_w', width);
                window.hWin.HAPI4.save_pref('pref_lookupMPCE_h', height);
            });
        });

        return this._super();
    },

    _getActionButtons: function(){
        var res = this._super(); //dialog buttons
        res[1].text = window.hWin.HR('Update Record');
        //res[1].disabled = null;
        return res;
    },

    /*
        Update Record with selected Classifications and move back to Record Editor popup

        Param: None

        Return: VOID, closes classification tool
     */
    doAction: function(){
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());    // use this function to show "in progress" animation and cover-all screen for long operation

        // assign values to be sent back to edit form - format is similar to this.options.edit_fields
        this._context_on_close = {};
        this._context_on_close[this.id_map.DT_Category] = $('#category_field').val();
        this._context_on_close[this.id_map.DT_Basis] = $('#basis_field').val();
        this._context_on_close[this.id_map.DT_Notes] = $('#notes_field').val();
        this._context_on_close[this.id_map.DT_Keywords] = this.project_keywords;

        /* Check if work can be added to list of recently viewed works */
        if (this.prev_works != null && !window.hWin.HEURIST4.util.isempty(this.project_keywords) &&  this.prev_works.find(e => e == $('#work-code_field').text()) == null) {

            this.prev_works.unshift($('#work-code_field').text());

            if (this.prev_works.length == 5) {
                this.prev_works.splice(4, 1);
            }

            localStorage.setItem("prev_classify", JSON.stringify(this.prev_works));        
        }
        
        window.hWin.HEURIST4.msg.sendCoverallToBack();  // use this function to hide cover-all/loading

        this._as_dialog.dialog('close');    // close popup
    },

    /** Keyword Assignment **/

    /*
        Creates a Heurist popup capable of searching all Project Keywords,
        When an keyword is selected, it is assigned to the work, if it is not already,
        this keyword is removed from the associated and previous (recent) keyword lists

        Param: None

        Return: VOID
     */
    keywordLookup: function(){

        var that = this;

        var popup_options = {
            select_mode: (false)?'select_multi':'select_single',    // enable multi select or not, current set to single select
            select_return_mode: 'recordset', //or 'ids' (default)
            edit_mode: 'popup',
            selectOnSave: true, // true = select popup will be closed after add/edit is completed
            title: 'Assign a New Keyword',
            rectype_set: [this.id_map.RT_Keyword], // record type ID
            pointer_mode: 'browseonly', // options = both, addonly or browseonly
            pointer_filter: null, // initial filter on record lookup, default = none
            parententity: 0,

            width: 700,
            height: 600,

            /* onselect Handler for pop-up */
            onselect:function(event, data){
                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){

                    var recordset = data.selection;
                    var record = recordset.getFirstRecord();

                    if(window.hWin.HEURIST4.util.isempty(record)){
                        // something is wrong with the record

                        msgToConsole('keywordLookup() Error: Selected Record is Invalid', record, 1);
                        window.hWin.HEURIST4.msg.showMsgErr('The selected keyword is invalid');

                        return;
                    }

                    var targetID = recordset.fld(record,'rec_ID');
                    
                    var keyword_IDs = that.project_keywords;

                    if (!window.hWin.HEURIST4.util.isempty(keyword_IDs)) {

                        var result = keyword_IDs.find(row => row == targetID);
                    
                        if (result != null) { // Check if Keyword is already a part of Work's Keyword Master List
                            window.hWin.HEURIST4.msg.showMsgDlg('Project Keyword Already Allocated to Work', null, 'Keyword already assigned');

                            return;
                        }
                    }

                    if(that.full_keywords_list == null){
                        that.getKeywords('add', targetID);
                        return;
                    }

                    var title = 'Record ID - ' + targetID;
                    if(that.full_keywords_list[targetID] !== undefined){
                        title = that.full_keywords_list[targetID];
                    }
                    that.addKeyword(targetID, title); // Add Selected Keyword to Master Table+List
                }
            }
        };

        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    },

    /*
        Retrieve the list of checked keywords that need to be moved to the assigned keyword list

        Param: None

        Return: VOID, assignment of selected keyword
     */
    addPrevtoAssigned: function(){

        var list = $('#prev_field')[0];

        var items = this.getAllChecked(list);

        if (items == null || items.length == 0) {
            window.hWin.HEURIST4.msg.showMsgDlg('There are No Recent Keywords Selected to Assign', null, 'No recent keywords');     
            return;
        }

        for (var i = 0; i < items.length; i++) {

            list = $('#prev_field');

            var title = 'Record ID - ' + items[i];
            if(this.full_keywords_list[items[i]] !== undefined){
                title = this.full_keywords_list[items[i]];
            }

            // Remove from Previous Keyword Table
            list.find('input#'+items[i]+'_r').parent().remove();

            this.addKeyword(items[i], title);   // Add to Assigned Table + List
        } 

        this.disableUpdateBtn();
    },

    /*
        Retrieve the list of checked keywords that need to be moved to the assigned keyword list

        Param: None

        Return: VOID
     */
    addAssoctoAssigned: function(){

        var list = $('#associated_field')[0];

        var items = this.getAllChecked(list, true);

        if(this.assoc_selected){
            items = mergeArraysUnique(items, this.assoc_selected);
        }

        if (items == null || items.length == 0) {
            window.hWin.HEURIST4.msg.showMsgDlg('There are No Associated Keywords Selected to Assign', null, 'No associated keywords');     
            return;
        }


        for (var i = 0; i < items.length; i++) {

            list = $('#associated_field')[0];

            /* Remove from Associated Keyword Table */
            $(list).find('input#'+items[i]+'_a').parent().remove();

            var title = 'Record ID - ' + items[i];
            if(this.full_keywords_list[items[i]] !== undefined){
                title = this.full_keywords_list[items[i]];
            }

            this.addKeyword(items[i], title);   // Add to Assigned Table + List
        }

        this.assoc_selected = [];

        this.updateAssocDisplay(true);

        this.disableUpdateBtn();
    },

    /** External Searches **/

    /*
        Search for Editions of the Work within Heurist, displaying the results in a separate popup

        Param: None

        Return: VOID
     */
    lookupEditions: function(){

        var that = this;

        if(this.editions_info == null){

            this.editions_info = [];

            var work_title = $('#title_field').html();

            var query_request = {q:'t:' + this.id_map.RT_Editions + ' linkedto:' + this.options.edit_fields.rec_ID, detail:"detail", limit: 10};

            window.hWin.HAPI4.RecordMgr.search(query_request,
                function( response ){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        if(window.hWin.HEURIST4.util.isempty(response.data)) { return; }

                        var recordset = new hRecordSet(response.data);
                        var records = recordset.getRecords();

                        for(i in records) {

                            var record = records[i];
                            var details = record.d;

                            that.editions_info.push([record[5], record[2]]);
                        }

                        if(that.editions_info != null && that.editions_info.length > 0){
                            that.showEditions();
                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
        }else{
            this.showEditions();
        }
    },


    /*
        Search for All Keywords

        Param: None

        Return: json -> all keywords, containing {id: 'keyword name', ...}
     */
    getKeywords: function(next_step='none', extra_ids){

        var that = this;

        if(this.full_keywords_list == null){
            this.full_keywords_list = {};
        }

        // Retrieve master list of project keywords, we need to display their titles for the user
        var query_request = {q:'t:' + this.id_map.RT_Keyword, detail: 'detail'};

        // Perform Search
        window.hWin.HAPI4.RecordMgr.search(query_request,
            function( response ){

                if(response.status == window.hWin.ResponseStatus.OK){   // Check if Record Search was successful

                    if(window.hWin.HEURIST4.util.isempty(response.data)) { return; }

                    var recordset = new hRecordSet(response.data);  // Retieve Search Results

                    recordset.each2(function(id, record){
                        if(record.d && record.d[1]){ // use just the keyword's name
                            that.full_keywords_list[id] = record.d[1];
                        }else{ // backup, use rec title
                            that.full_keywords_list[id] = record['rec_Title'];
                        }
                    });

                    switch (next_step) {
                        case 'assigned': // add assigned keywords to html list

                            if(!window.hWin.HEURIST4.util.isempty(that.project_keywords)){

                                for(var i = 0; i < that.project_keywords.length; i++){
                                    var id = that.project_keywords[i];

                                    var title = 'Record ID - ' + id;
                                    if(that.full_keywords_list[id] !== undefined){
                                        title = that.full_keywords_list[id];
                                    }

                                    that.showKeyword(title, id);  // Add to Keyword Table
                                }
                            }

                            that.setupRecentWorks(); // initialise recent keywords

                            break;
                        case 'associated': // retrieve associated keywords
                            
                            if(extra_ids != null){
                                that.setupAssocKeywords(extra_ids);
                            }

                            break;
                        case 'recent': // retrieve recent keywords

                            if(extra_ids != null){
                                that.getRecentKeywords(extra_ids);
                            }

                            break;
                        case 'add': // add new assigned keyword

                            if(extra_ids != null){

                                var title = 'Record ID - ' + extra_ids;
                                if(that.full_keywords_list[extra_ids] !== undefined){
                                    title = that.full_keywords_list[extra_ids];
                                }
                                that.addKeyword(extra_ids, title);
                            }

                            break;
                        default:
                            // Unknown/None, do nothing
                            break;
                    }

                    return;
                }else{  // Record Seach Failed
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
    },

    /*
        Search for Work Title in Google Books

        Param: None

        Return: (false), opens new tab for Google Books search
     */
    lookupGoogle: function(){
        var title = $($('#title_field')[0]).text();
        var url = 'https://books.google.com.au/?q=' + title;

        window.open(url, '_blank');

        return false;
    },

    /*
        Search for Work Title in World Cat

        Param: None

        Return: (false), opens new tab for World Cat search
     */
    lookupWorldCat: function(){
        var title = $($('#title_field')[0]).text();
        var url = 'http://www.worldcat.org/search?q=ti%3A' + title;

        window.open(url, '_blank');

        return false;
    },

    /*
        Search for Work Title in Hathi Trust

        Param: None

        Return: (false), opens new tab for Hathi Trust search
     */
    lookupHathiTrust: function(){
        var title = $($('#title_field')[0]).text();
        var url = 'http://catalog.hathitrust.org/Search/Advanced?adv=1&lookfor%5B%5D=' + title;

        window.open(url, '_blank');

        return false;
    },

    /*
        Search for Work Title in Karlsruhe Portal

        Param: None

        Return: (false), opens new tab for Karlsruhe Portal search form
     */
    lookupKarlsruhePortal: function(){
        var title = $($('#title_field')[0]).text();
        var url = 'http://kvk.bibliothek.kit.edu/index.html?lang=en&digitalOnly=0&embedFulltitle=0&newTab=0&TI=' + title;

        window.open(url, '_blank');

        return false;
    },

    /** Associated Keyword System **/

    /* 
        Searches for works that have the selected keyword,
        the program then goes through each returned work's keywords, 
        adding them to the associated keyword list, 
        finally displaying the result to the user.

        Param: id -> Keyword's Title Mask (or, the Keyword's ID)

        Return: VOID
     */
    setupAssocKeywords: function(id) {

        var that = this;

        if(this.full_keywords_list == null){
            this.getKeywords('associated', id);
            return;
        }

        var list = $('#associated_field');

        this.assoc_search_id = id;
        this.assoc_keywords = [];
        this.assoc_selected = [];

        $('#assoc_prev').hide();
        //$('#assoc_start').hide();
        $('#assoc_next').hide(); 

        list.html('<div style="font-size:1.5em">Loading List...</div>');

        var query_request = {q:'t:' + this.id_map.RT_Work + ' linkedto:'+ id, detail:'detail'};  /* Retrieve all works that have the selected keyword */

        window.hWin.HAPI4.RecordMgr.search(query_request,
            function(response){

                if (response.status == window.hWin.ResponseStatus.OK) {

                    if(window.hWin.HEURIST4.util.isempty(response.data)) { return; }

                    var recordset = new hRecordSet(response.data);
                    var records = recordset.getRecords();

                    var ids = [];

                    /* For displaying the total number of works to user, i.e. (n = ...) */
                    that.assoc_work_count = Object.keys(records).length;

                    /* Travel through results to retrieve the each work's list of keywords */
                    for (i in records) {

                        var record = records[i];

                        /* To avoid results that are not from the correct table, usually happens if query is wrong */
                        if (record[4] != that.id_map.RT_Work) { continue; }

                        var details = record.d;

                        if ((details[that.id_map.DT_Keywords] == null) || (details[that.id_map.DT_Keywords].length < 2)) {
                            continue;
                        }

                        ids.push(details[that.id_map.DT_Keywords]);
                    }

                    /* Add the complete list retrieved to the associated keyword */
                    that.updateAssocList(ids);
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );

        return;
    },
    
    /*
        Updates the Associate Keyword List (this.assoc_keywords)
        Performs a check if the current keyword is already part of the list, 
            if it is, increase occurances
            else, push into list
        The list is then sorted by occurrances
        Once completed, the corresponding table is updated

        Param: ids -> list of associated keywords

        Return: VOID
     */

    updateAssocList: function(ids){

        var that = this;

        // Go through 2d array of keywords
        for (var i = 0; i < ids.length; i++) {

            for (var j = 0; j < ids[i].length; j++) {

                var id = ids[i][j];

                var title = 'Record ID - ' + id;
                if(this.full_keywords_list[id] !== undefined){
                    title = this.full_keywords_list[id];
                }

                if(this.assoc_keywords.length < 0){ // don't need to check, if there are no associated keywords
                    this.assoc_keywords.push([id, 1, title]);
                    continue;
                }

                this.isAssocKeyword(id, title); // Check if current keyword is already in list
            }
        } 

        this.updateAssocDisplay(true);   // Update UI display of associated keywords
    },
    
    /*
        Update the Associated Keywords List, UI, and display which kyeword was selected

        Param: move_to_start -> whether this is the next set of keywords, or starting from the start of list

        Return: VOID
     */
    updateAssocDisplay: function(move_to_start=true){

        var that = this;

        var list = $('#associated_field');
        
        if (window.hWin.HEURIST4.util.isempty(this.assoc_keywords)) {
            return;
        }

        var keywords = this.assoc_keywords;

        if (window.hWin.HEURIST4.util.isempty(this.project_keywords)) {
            return;
        }

        var assigned = this.project_keywords;

        keywords.sort(compareIndexes);

        if(list.find('li').length != 0 && !move_to_start) {
            this.assoc_startindex = this.assoc_endindex;
        } else {
            this.assoc_startindex = 0;
        }

        var max = this.assoc_startindex + 13;

        /* Empty List, before use */
        list.empty();

        var name = $('#keyword_field').find('li#'+this.assoc_search_id+'_m').find('label').text();
        $('#assoc_kywd').text(name);
        $('#assoc_total').text("(n=" + this.assoc_work_count + ")");

        var setCheck = false;

        /* Now add items into the HTML list */
        var i = this.assoc_startindex;
        for (; i < max && keywords.length > i; i++) {

            var item = $('<li>');

            if (keywords[i] == null) {
                break; 
            }

            if (assigned.find(e => e == keywords[i][0])) {
                max++;
                continue;
            } else {

                item.html("<input type='checkbox' value='" + keywords[i][0] + "' style='vertical-align:middle;' id='" + keywords[i][0] + "_a'>"
                        + "<label for='" + keywords[i][0] + "_a' class='non-selectable key-label truncate' style='max-width:180px;vertical-align:middle;'"
                        + " title=\"" + keywords[i][2] + "\">" + keywords[i][2] + "</label>&nbsp;&nbsp;"
                        + "<button data-value='" + keywords[i][0] + "' class='btn btn-info ui-icon ui-icon-circle-b-info'"
                            + " style='float:right;font-size:1em;display:inline-block;width:20px;height:20px;color:white;' title='View keyword record'>&nbsp;"
                        +"</button>"
                        + "<label class='non-selectable' style='vertical-align:middle;float:right;margin-right:5px;'> [ " + keywords[i][1] + " ] </label>");

                item.find('.btn-info.ui-icon-circle-b-info').click(function(e){
                    var rec_id = $(e.target).parent().find('input').attr('value');

                    that.openRecordInTab(rec_id);
                });

                if(this.assoc_selected && this.assoc_selected.length != 0) {

                    if(this.assoc_selected.indexOf(keywords[i][0]) > -1){
                        $(item).find('input').prop('checked', true);
                    }
                }

                item.find('#'+keywords[i][0]+'_a').click(function(e){ 

                    var id = $(e.target).val();
                    if($(e.target).is(':Checked') == true) {

                        if(!that.assoc_selected) { that.assoc_selected = []; }
                        
                        that.assoc_selected.push(id);
                    } else {

                        if(!that.assoc_selected){ return; }

                        var index = that.assoc_selected.indexOf(id);
                        if(index > -1) {
                            that.assoc_selected.splice(index, 1);
                        }
                    }

                    that.disableUpdateBtn(); 
                });

                list.append(item);
            }
        }

        this.assoc_endindex = i;

        jump = this.assoc_endindex - this.assoc_startindex;

        if(this.assoc_startindex-jump < 0 && this.assoc_endindex+13 > keywords.length && this.assoc_endindex <= keywords.length ) {
            $('#assoc_prev').hide();
            //$('#assoc_start').hide();
            $('#assoc_next').hide();             
        } else if(this.assoc_startindex-jump < 0) { 
            $('#assoc_next').show();
            //$('#assoc_start').hide();
            $('#assoc_prev').hide(); 
        } else if(this.assoc_endindex+jump > keywords.length && this.assoc_endindex == keywords.length) { 
            $('#assoc_prev').show();
            //$('#assoc_start').show();
            $('#assoc_next').hide(); 
        } else {
            $('#assoc_prev').show();
            //$('#assoc_start').show();
            $('#assoc_next').show(); 
        }

        this.disableUpdateBtn();
    },
    
    /*
        Checks if keyword is already in arr,
            if it is, increase the counter
            else, push it in

        Param:
            arr -> arr of associated keywords
            id -> the current keyword
            title -> the keyword in english

        Return: VOID
     */
    isAssocKeyword: function(id, title) {
        
        var found = 0;

        for (var i = 0; i < this.assoc_keywords.length; i++) {

            if (this.assoc_keywords[i][0] == id) {
                this.assoc_keywords[i][1] += 1;
                found = 1;
                break;
            }
        }

        if (found == 0) {
            this.assoc_keywords.push([id, 1, title]);
        }
    },

    /*
        Unselects and removes all selected keywords from the associated keywords list

        Param: NONE

        Return: VOID
     */
    unselectAssoc: function(){

        this.assoc_selected = [];   /* Empty complete selection */

        $('#checkall-assoc').attr('checked', false);    /* Remove any checked boxes in current view */

        this.checkAllOptions($('#associated_field')[0], false); /* Uncheck the 'Check all' option */
    },
    
    /** Recent Keywords System **/

    /*
        Sets up the list of works and calls startup function for Recent Keywords,
        This will display a list of keywords from previously viewed works from within a session (life span of the tab),
        a list of 4 work titles is remembered and those four work's keywords are displayed to the UI.
        Keywords assigned to the current work are ignored, as are ones already displayed under the recent keyword section.

        Param: None

        Return: VOID
     */
    setupRecentWorks: async function(){

        this.prev_keywords = {};

        if (this.prev_works != null) { // Check if there are any previously viewed works

            var ch_set;

            /* Which work will have it's keywords automatically set to checked, each work can only appear once in the list */
            if (this.prev_works[0] != $('#work-code_field').text()) {
                ch_set = this.prev_works[0];
            } else if (this.prev_works.length != 1) {
                ch_set = this.prev_works[1];
            }

            /* Now retrieve and display the list */
            for (var i = 0; i < this.prev_works.length; i++) {

                if (this.prev_works[i] == $('#work-code_field').text()) { continue; }

                /* Additional information is sent, depending on whether the work's keywords are to be checked or not  */
                if (ch_set == this.prev_works[i]) {
                    this.startRecentWork(this.prev_works[i], true);
                } else {
                    this.startRecentWork(this.prev_works[i], false);
                }

                await sleep(50); // This is to allow the keywords to be displayed in the correct order, without this the default checked keywords can appear out of order
            }
        }else{
            this.prev_works = [];
        }

        /* Check if current work can be added to the list of previously (recent) works */
        if(this.prev_works.find(row => row == $('#work-code_field').text()) == null && !window.hWin.HEURIST4.util.isempty(this.project_keywords)){
            this.prev_works.unshift($('#work-code_field').text());
        }

        if(this.prev_works.length == 5){ 
            this.prev_works.splice(4, 1);
        }

        localStorage.setItem("prev_classify", JSON.stringify(this.prev_works)); 
    },

    /*
        Retrieves the supplied work's keywords and add them to the displayed list

        Param: 
            work_code -> Work Code of Interest
            is_checked -> Bool whether to check new option

        Return: VOID
     */
    startRecentWork: function(id, is_checked=false) {

        var that = this;   
        
        if (id == null) {
            msgToConsole('startRecentWork() Error: No Recent Works Saved', null);
            return;
        }

        var query_request = {q:'t:' + this.id_map.RT_Work + ' f:' + this.id_map.DT_MPCEId + ':"' + id + '"', detail:'detail'};  /* Retrieve the record for supplied work code */

        window.hWin.HAPI4.RecordMgr.search(query_request,
            function( response ){
                if(response.status == window.hWin.ResponseStatus.OK){

                    if(window.hWin.HEURIST4.util.isempty(response.data)) { return; }

                    var recordset = new hRecordSet(response.data);
                    var record = recordset.getFirstRecord();
                    var details = record.d;

                    /* Check if the record has project keywords to display */
                    if (details[that.id_map.DT_Keywords] != null) {
                        that.getRecentKeywords(details[that.id_map.DT_Keywords], is_checked);
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
    },

    /*
        From a list of keywords, retrieve their titles and display them to the user, or remove them if necessary

        Param: 
            keyword_IDs -> Array of Keyword IDs
            is_checked -> Bool whether to check new option

        Return: VOID
     */
    getRecentKeywords: function(keyword_IDs, is_checked=false) {
        
        var that = this;

        if(this.full_keywords_list == null){
            this.getKeywords('recent', keyword_IDs);
            return;
        }

        for (var j = 0; j < keyword_IDs.length; j++) {

            var id = keyword_IDs[j];

            var title = 'Record ID - ' + id;
            if(this.full_keywords_list[id] !== undefined){
                title = this.full_keywords_list[id];
            }

            var found = $('#keyword_field').find('li#'+id+'_m'); // Check if keyword is already assigned

            if (found.length == 0) {

                found = $('#prev_field').find('input#'+id+'_r'); // Check if keyword has been displayed as a recently used keyword

                if(found.length == 0){

                    that.updateRecentKeywordDisplay(id, title); // Add new keyword to recently used keyword list

                    this.prev_keywords[id] = title;

                    if(is_checked){
                        $('#prev_field').find('input#'+id+'_r').prop('checked', true);
                    }
                }
            }
        }
    },

    /*
        Add new keyword to recently used keyword list

        Param:
            id -> Keyword's Record ID
            title -> Keyword Title

        Return: VOID
     */
    updateRecentKeywordDisplay: function(id, title){

        var that = this;

        var list = $('#prev_field');
        var item = $('<li>');
        var html = '';

        html = "<input type='checkbox' style='vertical-align:middle;' value='" + id + "' id='" + id + "_r'>";

        html += "<label for='" + id + "_r' class='non-selectable key-label truncate' style='vertical-align:middle;' title=\""+ title +"\"> " + title + " </label>"
                + "<button class='btn btn-info ui-icon ui-icon-circle-b-info'"
                    + " style='float:right;font-size:1em;display:inline-block;width:20px;height:20px;color:white;' title='View keyword record'>&nbsp;"
                + "</button>";

        item.html(html);

        $(item).find('#'+id).click(function(e){ that.disableUpdateBtn(); });
        $(item).find('.btn-info').click(function(e){ that.openRecordInTab(id); });

        list.append(item);
        return;
    },

    /*
        Check if provided keyword is a displayed as a recently used keyword

        Param: id -> Keyword's ID

        Return: VOID
     */
    isRecentKeyword: function(id){

        var that = this;

        if (this.prev_works == null || $.isEmptyObject(this.prev_keywords) || this.prev_keywords[id] == undefined){ // Check for a list of previous keywords
            return;
        }

        this.updateRecentKeywordDisplay(id, this.prev_keywords[id]);
    },

    /** Assigned Keyword System **/

    /*
        Add Newly Assigned Keyword to Table UI

        Param: 
            keyword -> Keyword's Title
            id -> Keyword's Record ID

        Return: VOID
     */
    showKeyword: function(title, id){

        var that = this;

        var list = $('#keyword_field');

        var item = $('<li id="'+ id +'_m">');

        item.html("<label class='key-label truncate' title=\""+title+"\">"+ title +"</label>"

        + "<button data-value='" + id + "' class='btn btn-info ui-icon ui-icon-circle-b-info'"
        + " style='float:right;font-size:1em;display:inline-block;width:20px;height:20px;color:white;' title='View keyword record'>&nbsp;</button>"

        + "<button data-value='" + id + "' class='btn btn-info btn-lookup'"
        + " style='float:right;margin-right:5px;font-size:0.75em;display:inline-block;height:20px;' title='Search for associated keywords'>Find Associated</button>"
        
        + "<button data-value='" + id + "' class='btn btn-delete ui-icon ui-icon-close'"
        + " style='float:right;margin-right:5px;font-size:1.1em;display:inline-block;width:20px;height:20px;' title='Remove keyword from list'>&nbsp;</button>");

        $(item).find('.btn-info.ui-icon-circle-b-info').click(function(e){ that.openRecordInTab(id); });
        $(item).find('.btn-lookup').click(function(e){ that.setupAssocKeywords(id); });
        $(item).find('.btn-delete').click(function(e){ that.removeKeyword($(e.target), id); });

        list.append(item);
    },

    /*
        Add new Keyword to Assigned Keywords List

        Param:
            title -> Keyword Title
            id -> Keyword's Record ID

        Return: VOID
     */
    addKeyword: function(id, title){

        /* Remove from Associated List */
        if($('#associated_field').find('input#'+id+'_a').parent().length > 0){
            $('#associated_field').find('input#'+id+'_a').parent().remove();
        }
        /* Remove from Recent List */
        if($('#prev_field').find('input#'+id+'_r').parent().length > 0){
            $('#prev_field').find('input#'+id+'_r').parent().remove();
        }

        if($('#keyword_field').find('li#'+id+'_m').length == 0){

            this.showKeyword(title, id); // Add to Table

            if (window.hWin.HEURIST4.util.isempty(this.project_keywords)) {
                this.project_keywords = [];
            }

            this.project_keywords.push(id); // Add to List
        }
    },

    /*
        Remove selected keyword from Assigned Keyword Table and List

        Param: id -> Selected Keyword's Record ID

        Return: VOID
     */
    removeKeyword: function($ele, id) {

        $ele.parent().remove(); // Remove keyword from list

        // Remove keywords from saved list
        var index = this.project_keywords.indexOf(id);
        if(index >= 0){
            this.project_keywords.splice(index, 1);
        }
        
        this.isRecentKeyword(id);    /* Check if keyword was a recent keyword */
        this.updateAssocDisplay();   /* Check if keyword was a associated keyword */
    },

    /*
        Display all found editions for the selected work

        Param: works -> List of Edition's Title Masks

        Return: VOID
     */
    showEditions: function(){

        var max = 10;
        var editions = '';
        var title = 'List of Editions';

        for (var i = 0; i < this.editions_info.length && i < max; i++) {

            var cur_edition = this.editions_info[i];

            var rec_url = window.hWin.HAPI4.baseURL+"viewers/record/renderRecordData.php?db="+window.hWin.HAPI4.database+"&recID="+cur_edition[1];
            editions = editions.concat("<div style='font-size: 1.2em;'>" + cur_edition[0] + " - <a href='"+rec_url+"' target='_blank'> view record </a></div>", "<br /><br />");
        }

        if(editions == ''){
            editions = 'No Editions Found';
            title = 'No editions';
        }

        window.hWin.HEURIST4.msg.showMsgDlg(editions, null, title, {default_palette_class: 'ui-heurist-explore'});
    },

    /** Other Function **/

    /*
        Determines which keywords have been checked

        Param: list -> document element, unordered list

        Return: Array -> list of checked keywords
     */
    getAllChecked: function(list){

        var checked_items = [];

        var items = $(list).find('li');

        for (var i = 0; i < items.length; i++) {

            var chkbox = $(items[i]).find('input');

            if (chkbox.length == 1 && chkbox.is(':checked')) {
                checked_items.push(chkbox.val());
            }
        }

        return checked_items;
    },

    /*
        Disable the Update Record button when a checkbox is checked, displaying message to uncheck or add selections

        Param: NONE

        Return: VOID
    */
    disableUpdateBtn: function(){

        if($('.mpce').find('input:checked').not('.check-all').length > 0 || (this.assoc_selected && this.assoc_selected.length > 0)){
            $($('.mpce')[0].parentNode.parentNode).find('#btnDoAction').attr('disabled', true).css({'cursor': 'default', 'opacity': '0.5'});
            $($('.mpce')[0].parentNode.parentNode).find('#save-msg').css({'margin':'10px', 'display':'inline-block'});
        }
        else{
            $($('.mpce')[0].parentNode.parentNode).find('#btnDoAction').attr('disabled', false).css({'cursor': 'default', 'opacity': '1'});
            $($('.mpce')[0].parentNode.parentNode).find('#save-msg').css({'margin':'10px', 'display':'none'});
        }
    },

    /*
        Toggles the checkboxes based on whether the "Check All" options is set

        Param: 
            list -> list of interest
            isChecked -> whether we are checking all boxes or not
            isAssoc -> is this for the associated keywords, needs extra handling

        Return: VOID
    */
    checkAllOptions: function(list, isChecked, isAssoc=false){

        var items = $(list).find('li');

        for (var i = 0; i < items.length; i++) {

            var chkbox = $(items[i]).find('input');

            if(chkbox.length == 0) {
                continue;
            }

            if (chkbox.is(':checked') != isChecked) {
                chkbox.prop('checked', isChecked);
            }

            if (isChecked && isAssoc) {
                if(!this.assoc_selected){ this.assoc_selected = []; }
                
                this.assoc_selected.push(chkbox.val());
            }
            else if(!isChecked && isAssoc){
                if(!this.assoc_selected){ return; }

                var index = this.assoc_selected.indexOf(chkbox.val());
                if(index > -1) {
                    this.assoc_selected.splice(index, 1);
                }
            }
        }

        this.disableUpdateBtn();
    },

    /*
        Open the provided record in a separate tab

        Param: id -> record id

        Return: VOID
     */
    openRecordInTab: function(id) {

        var rec_url = window.hWin.HAPI4.baseURL+"viewers/record/renderRecordData.php?db="+window.hWin.HAPI4.database+"&recID=" + id;

        window.open(rec_url, "_blank");
    },

    /*
        Mapping Ids for Detail and Record Types (Heurist Specific)

        Param: mapping -> mapped options

        Return: VOID
     */
    mapIds: function(mapping, main_rty_id) {

        /* Detail Type, Record Type and Vocab Id Map, for now you need to replace all instances of each value with the database correct one */
        this.id_map.DT_MPCEId = (mapping['workID'] != null) ? mapping['workID'] : 952; /* MPCE ID Details Index */
        this.id_map.DT_Category = (mapping['parisianClassify'] != null) ? mapping['parisianClassify'] : 1060; /* Parisian Category Details Index */
        this.id_map.DT_Keywords = (mapping['projectKywds'] != null) ? mapping['projectKywds'] : 955; /* Project Keywords Details Index */
        this.id_map.DT_Basis = (mapping['basisClassify'] != null) ? mapping['basisClassify'] : 1034; /* Basis for Classifcation Details Index */
        this.id_map.DT_Notes = (mapping['classifyNotes'] != null) ? mapping['classifyNotes'] : 1035; /* Classification Notes Details Index */

        var dty_keywords = $Db.dty(this.id_map.DT_Keywords);
        var rty_keywords = (dty_keywords['dty_PtrTargetRectypeIDs'] != null) ? dty_keywords['dty_PtrTargetRectypeIDs'] : 56;

        this.id_map.RT_Editions = 54; /* Editions Table */ 
        this.id_map.RT_Work = (main_rty_id != null) ? main_rty_id : 55; /* Super Book (Works) Table */
        this.id_map.RT_Keyword = rty_keywords; /* Project Keywords Table */

        var dty_category = $Db.dty(this.id_map.DT_Category);
        var trm_category = (dty_category['dty_JsonTermIDTree'] != null) ? dty_category['dty_JsonTermIDTree'] : 6953;
        var dty_basis = $Db.dty(this.id_map.DT_Basis);
        var trm_basis = (dty_basis['dty_JsonTermIDTree'] != null) ? dty_basis['dty_JsonTermIDTree'] : 6936;

        this.id_map.VI_Category = trm_category; /* Parisian Category Vocab ID */
        this.id_map.VI_Basis = trm_basis; /* Basis for Classification Vocab ID */
    }
});

/** Misc Function **/

/*
    Comparison Function for the arr.sort() function for the 2D of associated keywords
    Note: arr[n][1] == the number of occurrances

    Param:
        a -> Array Index 1
        b -> Array Index 2

    Return: Boolean -> whether the second element within index A is larger than B
 */
function compareIndexes(a, b){
    if (a[1] == b[1]) {
        return 0;
    } else {
        return (a[1] > b[1]) ? -1 : 1;
    }
}

/*
    Merge two arrays, keeping only unqiue values

    Param:
        a -> base array
        b -> array to merge to 'a'

    Return: Array -> Merged array

 */
function mergeArraysUnique(a, b){
    
    if(!a && !Array.isArray(a)){
        msgToConsole('mergeArraysUnique() Error: arguement \'a\' is not an array', a, 1);
    }

    if(!b && !Array.isArray(b)){
        msgToConsole('mergeArraysUnique() Error: arguement \'b\' is not an array', b, 1);
    }

    if(a.length == 0){
        return b;
    }

    for(var i = 0; i < b.length; i++){
        if(a.indexOf(b[i]) == -1){
            a.push(b[i]);
        }
    }

    return a;
}

/*
    Message/Data pair for printing to the console

    Param:
        msg -> primary message to console log
        data -> can be null, additional information
        type -> type of message 0 = log, 1 = error

    Return: VOID
 */
function msgToConsole(msg, data, type=0){
    if (type == 0) {
        console.log(msg);
    }
    else {
        console.error(msg);
    }

    if (data != null) {
        console.log(data);
    }
}

/*
    Sleep Function

    Param: ms -> time to wait, in mmilliseconds

    Return: VOID
 */
function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}