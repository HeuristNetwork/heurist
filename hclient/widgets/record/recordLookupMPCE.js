/**
 * recordLookupMPCE.js
 *
 *  1) This file loads html content from recordLookupMPCE.html - define this file with controls as you wish
 *  2) Init these controls and define behaviour in _initControls
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

$.widget( "heurist.recordLookupMPCE", $.heurist.recordAction, {

    // default options
    options: {

        height: 1040,
        width:  1600,
        modal:  true,

        mapping: null, //configuration from record_lookup_configuration.json
        edit_fields:null,  //realtime values from edit form fields 
        edit_record:null,  //recordset of the record which is currently being edited (values before edit start)

        title:  'Work Dettails Update',

        htmlContent: 'recordLookupMPCE.html',
        helpContent: null, //help file in context_help folder

    },
    
    //constants for RTy and DTy
    RT_WORK: 56,
    

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        // Ensuring that the un-necessary session variables are cleared before use, particularly assoc_keywords
        sessionStorage.removeItem("assoc_keywords");
        sessionStorage.removeItem("rec_kywd");
        sessionStorage.removeItem("workTitle");
        sessionStorage.removeItem("keyword_search");
        sessionStorage.removeItem("rec_category");
        sessionStorage.removeItem("rec_basis");

        var that = this;


        //Passing data from the current record
        //specifically work title (text), Parisian keyword (term ID), and Project keywords (record ID) as examples of text, terms and record pointers
        console.log('EDIT FIELD VALUES');
        console.log(this.options.edit_fields); //array of current field values  {dty_ID:values,......}

        //record before edit status  {d:{dty_ID:values,......}}
        var record = this.options.edit_record.getFirstRecord(); //see core/recordset.js for various methods to manipulate a record

        /* Check if selected Work has: project keyword/s, parisian category, and a basis for classification */
        if (record.d[955] == null) // Project Keywords
        {
            sessionStorage.setItem("rec_kywd", "null");
        }
        else
        {
            sessionStorage.setItem("rec_kywd", JSON.stringify(record.d[955]));
        }

        if (record.d[1060] == null)  // Parisian Category
        {
            sessionStorage.setItem("rec_category", "null");
        }
        else
        {
            sessionStorage.setItem("rec_category", record.d[1060]);
        }

        if (record.d[1034] == null)  // Basis for Classification
        {
            sessionStorage.setItem("rec_basis", "null");
        }
        else
        {
            sessionStorage.setItem("rec_basis", record.d[1034]);
        }

        $('#title_field').val(record.d[1]); // Work Title
        $('#work-code_field').val(record.d[952]);   // Work MPCE_ID
        sessionStorage.setItem("workTitle", record[5]); // Un-necessary, value can get retrieved from #title_field

        /* Retrieve all Parisian Keywords (Term ID = 6380) */
        var parisian_Category = window.hWin.HEURIST4.ui.createTermSelectExt2( this.element.find('#category_field')[0],
            {datatype:'enum',
                // termIDTree:6380,    // Vocabulary ID/Term ID
                termIDTree:6953,    // Vocabulary ID/Term ID
                headerTermIDsList:null,  //array of disabled terms
                defaultTermID:sessionStorage.getItem("rec_category"),   //Default/Selected Term
                topOptions:[{key:0, title:" ", value:"null"}],      //Top Options  [{key:0, title:'...select me...'},....]
                useHtmlSelect:false}); // use native select of jquery select

        /* Retrieve all Basis for Classification (Term ID = 6498) */
        var basis = window.hWin.HEURIST4.ui.createTermSelectExt2( this.element.find('#basis_field')[0],
            {datatype:'enum',
                // termIDTree:6498,    // Vocabulary ID/Term ID
                termIDTree:6936,    // Vocabulary ID/Term ID
                headerTermIDsList:null,  //array of disabled terms
                defaultTermID:sessionStorage.getItem("rec_basis"),   // Default/Selected Term
                topOptions:null,      //Top Options  [{key:0, title:'...select me...'},....]
                useHtmlSelect:false}); // use native select of jquery select 

        /* Get all Project Keywords and grab the ones assigned to current Work, (Project Keyword Table = 56) */
        var query_request = {q:'t:56', w:'all'};

        query_request.detail = 'detail'; /* set the option for the search, these options can be found in reacordSearch.php */
        /* detail, to return all details about the searched records */

        /* 
            Indexes of Details: 
            1 -> Project Keyword/Work Title
            3 -> Keyword Definition
            952 -> Work MPCE Code
            1060 -> Parisian Category
            955 -> Project Keywords
            1034 -> Basis of Classification
            957 -> Project Keyword ID
            1128 -> Commonly Associated Keywords
        */

        if (sessionStorage.getItem('rec_kywd') != "null")   /* Check if Work has assigned Keywords */
        {
            /* Perform Search */
            window.hWin.HAPI4.RecordMgr.search(query_request,
                function( response ){
                    var assoc_kyrds = [];
                    if(response.status == window.hWin.ResponseStatus.OK){   /* Check if Record Search was successful */

                        var recordset = new hRecordSet(response.data);  /* Retieve Search Results */
                        var keywords_ID = JSON.parse(sessionStorage.getItem("rec_kywd"));   /* Array of Project Keyword's Record IDs */
                        var cnt = keywords_ID.length;   /* Number of Project Keywords */

                        var i = 0;
                        for (; i < cnt; i++)
                        {
                            var record = recordset.getRecord(keywords_ID[i]);   /* Retrieve Record+Details on ith Keyword, have to use Record ID */
                            var details = record.d;     /* Separate Details from the main Record */
                            var word = details['1'];    /* Project Keyword Title */
                            var id = record.rec_ID;     /* Project Keyword's Record ID */

                            showKeyword(word, id);  /* Add to Keyword Table */

                            var assoc_cnt = assoc_kyrds.length; /* Should be 0, just in case */
                            var j = 0;
                            if (details[1128] == null) { continue; } /* Check if current Keyword has Associated Keywords */
                            for (; j < details[1128].length; j++)
                            {
                                var rec = recordset.getRecord(details[1128][j]); /* Grab current Associated Keyword's record */

                                if (assoc_cnt == 0)
                                {
                                    assoc_kyrds.push([details[1128][j], 1, rec.d['1']]); /* ([Assoc Keyword's Record ID, Occurrances, Assoc Keyword Title]) */
                                }
                                else
                                {
                                    var k = 0, found = 0;
                                    for (; k < assoc_cnt; k++)
                                    {
                                        if (assoc_kyrds[k][0] == details[1128][j])   /* Check if current Associated Keyword is already accounted for */
                                        {
                                            assoc_kyrds[k][1] += 1;
                                            found = 1;
                                            break;
                                        }
                                    }
                                    if (found == 0)
                                    {
                                        assoc_kyrds.push([details[1128][j], 1, rec.d['1']]);
                                    }
                                    else { continue; }
                                }
                                assoc_cnt = assoc_kyrds.length;
                            }
                        }
                    }else{  /* Record Seach Failed */
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                    assoc_kyrds.sort(compareIndexes);   /* Sort Associated Keywords by Number of Occurrances */
                    sessionStorage.setItem("assoc_keywords", JSON.stringify(assoc_kyrds));  /* Save Associated Keyword Array for later use */
                    initialAssociatedKywrds(assoc_kyrds);   /* Initialise Associated Keyword Table */
                }
            );
        }

        if (sessionStorage.getItem('prev_classify') != null)    /* Check if Previous Keywords Table can be Initialised */
        {
            setupPreviousWork(sessionStorage.getItem('prev_classify'));
        }

        /* onClick links */
        /* Save Classification for future works */
        this._on(this.element.find('#btnSave').button(),{
            'click':this.saveClassification
        });

        /* Add new project keyword from Project Keywords DB Table, Heurist Form */
        this._on(this.element.find('#btnLookup').button(),{
            'click':this.keywordLookup
        });

        /* Lookup Keyword Definition, Heurist Form */
        this._on(this.element.find('#btnDefine').button(),{
            'click':this.lookupDefinition
        });

        /* External Searches for Work Title; Google Books, World Cat, Hathi Trust, Karlsruhe Portal */
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

        /* Set what the 'Save' button, bottom right of form, does */
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), false );

        return this._super();
    },

    /*    
        Extend list of dialog action buttons (bottom bar of dialog) or change their labeles or event handlers 
    */
    _getActionButtons: function(){
        var res = this._super(); //dialog buttons
        res[1].text = window.hWin.HR('Save');
        //res[1].disabled = null;
        return res;
    },

    /*
        Save Classifications and Return to previous form

        Param: None

        Return: VOID, closes classification tool
     */
    doAction: function(){
        var set_kywrds = 0;
        var keywords_ID = [];
        if (sessionStorage.getItem("rec_kywd") != "null")   /* Check if currently editing work has keywords */
        {
            set_kywrds = 1;
        }

        // use this function to show "in progress" animation and cover-all screen for long operation
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        // use this function to hide cover-all/loading
        window.hWin.HEURIST4.msg.sendCoverallToBack();

        if (set_kywrds == 1)    /* if work has keywords, retrieve them */
        {
            keywords_ID = JSON.parse(sessionStorage.getItem("rec_kywd"));
        }
        var category_ID = document.getElementById('category_field').value;
        var basis_ID = document.getElementById('basis_field').value;

        // assign values to be sent back to edit form - format is similar to this.options.edit_fields
        if (set_kywrds == 1)
        {
            this._context_on_close = {'955': keywords_ID, '1060': category_ID, '1034': basis_ID};
        }
        else
        {
            this._context_on_close = {'1060': category_ID, '1034': basis_ID};
        }

        this._as_dialog.dialog('close');
    },

    /*
        Saves the Work MPCE Code to retrieve its keywords for later use

        Param: None

        Return: VOID, displays message confirm work 'saved'
     */
    saveClassification: function(){
        sessionStorage.setItem("prev_classify", document.getElementById('work-code_field').value);
        window.hWin.HEURIST4.msg.showMsgDlg('Saved Work Details -> ' + document.getElementById('work-code_field').value);
        setupPreviousWork(document.getElementById('work-code_field').value);
    },

    /*
        Creates a Heurist popup capable of searching all Project Keywords,
        When an keyword is selected, it is added to Work's Master List+Table of Project Keywords

        Param: None

        Return: VOID, addition of new Project Keyword || closes the popup
     */
    keywordLookup: function(){

        var popup_options = {
            select_mode: (false)?'select_multi':'select_single',    // enable multi select or not, current set to single select
            select_return_mode: 'recordset', //or 'ids' (default)
            edit_mode: 'popup',
            selectOnSave: true, // true = select popup will be closed after add/edit is completed
            title: 'Select or Add record',
            rectype_set: 56, // record type ID, Project Keywords = 56
            pointer_mode: 'browseonly', // options = both, addonly or browseonly, self explanatory
            pointer_filter: null, // initial filter on record lookup, default = none
            parententity: 0,

            width: 700,
            height: 600,
            //new_record_params: {ro: rv:}


            // Selecting a Keyword in the popup, a recordset has been selected
            onselect:function(event, data){
                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                    var table = document.getElementById('keyword_field');
                    var recordset = data.selection;
                    var record = recordset.getFirstRecord();

                    var rec_Title = recordset.fld(record,'rec_Title');
                    if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                        // no proper selection 
                        // consider that record was not saved - it returns FlagTemporary=1 
                        return;
                    }
                    var targetID = recordset.fld(record,'rec_ID');  /* Gets Record ID of selected Keyword */

                    for (var i = 1, row; row = table.rows[i]; i++)
                    {
                        if (row.cells[1].getElementsByTagName('button')[0].value == targetID)   /* Check if Keyword is already a part of Work's Keyword Master List */
                        {
                            window.hWin.HEURIST4.msg.showMsgDlg('Project Keyword Already Allocated to Work');
                            return;
                        }
                    }

                    var query_request = {q: 't:56', w:'all'};   /* Retrieve Keyword's details */
                    query_request.detail = 'detail';

                    window.hWin.HAPI4.RecordMgr.search(query_request,
                        function( response ){
                            if(response.status == window.hWin.ResponseStatus.OK){

                                var recordset = new hRecordSet(response.data);

                                var record = recordset.getRecord(targetID);
                                var details = record.d;
                                var word = details['1'];

                                window.hWin.HEURIST4.msg.showMsgDlg('Adding Keyword -> '+ word);

                                addKeyword(targetID, word); /* Add Selected Keyword to Master Table+List */
                                updateAssociatedList(targetID); /* Update Associated Keywords for newly added Keyword */
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }
                    );
                }
            }
        };

        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);

    },

    /*
        Search for Work Title in Google Books

        Param: None

        Return: (false), opens new tab for Google Books search
     */
    lookupGoogle: function(){
        var title = sessionStorage.getItem('workTitle');    /* Could be Retrieved from Element */
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
        var title = sessionStorage.getItem('workTitle');    /* Could be Retrieved from Element */
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
        var title = sessionStorage.getItem('workTitle');    /* Could be Retrieved from Element */
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
        var title = sessionStorage.getItem('workTitle');    /* Could be Retrieved from Element */
        var url = 'http://kvk.bibliothek.kit.edu/index.html?lang=en&digitalOnly=0&embedFulltitle=0&newTab=0&TI=' + title;

        window.open(url, '_blank');

        return false;
    },

    /*
        Creates a Heurist popup capable of searching all Project Keywords,
        When an keyword is selected, its definition is displayed
        (TODO: Could be extended to display its associated keywords for assignment)

        Param: None

        Return: VOID, displays Keyword's Definition || closes the popup
     */
    lookupDefinition: function(){

        var popup_options = {
            select_mode: (false)?'select_multi':'select_single',
            select_return_mode: 'recordset', //or 'ids' (default)
            edit_mode: 'popup',
            selectOnSave: false, // true = select popup will be closed after add/edit is completed
            title: 'Select or Add record',
            rectype_set: '56', //  csv of record type ids
            pointer_mode: 'browseonly', // default behaviour = both, can be set to addonly  or browseonly
            pointer_filter: null, // initial filter on record lookup, default = none
            parententity: 0,

            width: 700,
            height: 600,
            //new_record_params: {ro: rv:}


            //Displays a list of all project keywords, then displays the definition of the selected word
            onselect:function(event, data){
                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                    var recordset = data.selection;
                    var record = recordset.getFirstRecord();

                    var rec_Title = recordset.fld(record,'rec_Title');
                    sessionStorage.setItem('keyword_search', recordset.fld(record,'rec_ID'));

                    if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                        // no proper selection 
                        // consider that record was not saved - it returns FlagTemporary=1 
                        return;
                    }

                    var query_request = {q:'t:56', w:'all'};

                    query_request.detail = 'detail'; // Definition of Keyword is located within the detail object, the above lookup returns only the Record

                    window.hWin.HAPI4.RecordMgr.search(query_request,
                        function( response ){
                            if(response.status == window.hWin.ResponseStatus.OK){

                                var recordset = new hRecordSet(response.data);
                                var keyword_ID = sessionStorage.getItem("keyword_search");

                                var record = recordset.getRecord(keyword_ID);
                                var details = record.d;
                                var word = details['1'];
                                var definition = details['3'];
                                window.hWin.HEURIST4.msg.showMsgDlg(word + ': ' + definition);

                                document.getElementById('searched_kywrd').value = word;
                                displayTempKeywords(recordset, keyword_ID);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }
                    );
                }
            }
        };

        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    },

});
//END recordLookupMPCE


/*
    Call Add Associated Keyword for each starting Associated Keyword

    Param: keywords[][] -> [Keyword's Record ID, Occurrances, Keyword Title]

    Return: VOID, starts the Associated Keywords Table 
 */
function initialAssociatedKywrds(keywords) {
    var kywrd_cnt = keywords.length;

    var i = 0;
    for (; i < 5 && i < kywrd_cnt; i++)
    {
        addAssociatedKywrd(keywords[i]);
    }
}

/*
    Add Associated Keyword Details to Table

    Param: keyword[][] -> [Keyowrd's Record ID, Occurrances, Keyword Title]

    Return: VOID, adds new row to Associated Keywords Table
 */
function addAssociatedKywrd(keyword){
    var table = document.getElementById('associated_field');
    var row = table.insertRow(-1);

    var col1 = row.insertCell(0);
    var col2 = row.insertCell(1);
    var col3 = row.insertCell(2);

    col1.innerHTML = "<input class='text ui-widget-content ui-corner-all' style='width:100%;' value='"+ keyword[2] +"' disabled/>";
    col2.innerHTML = "<label>"+ keyword[1] +"</label>";
    col3.innerHTML = "<button value="+ keyword[0] +" onclick='addAssocToAssign(this.value)' class='button btn-add'>Add Keyword</button>";
}

/*
    Update the Associated Keywords Table

    Param: None

    Return: VOID, updated Associated Keywords Table+List
 */
function updateAssociatedTable(){
    var keywords = JSON.parse(sessionStorage.getItem("assoc_keywords"));

    keywords.sort(compareIndexes);  /* Sort Associated List */
    var table = document.getElementById('associated_field');

    for (var i = 1; i < 6; i++)
    {
        if (keywords[i-1] == null)
        {
            var j = i;
            while(table.rows[j] != null)
            {
                table.deleteRow(j);
            }
            break;
        }
        else
        {
            if (table.rows[i] == null)  /* Check if Associated Keyword's Table is Empty */
            {
                var row = table.insertRow(-1);

                var col1 = row.insertCell(0);
                var col2 = row.insertCell(1);
                var col3 = row.insertCell(2);

                col1.innerHTML = "<input class='text ui-widget-content ui-corner-all' style='width:100%;' value='"+ keywords[i-1][2] +"' disabled/>";
                col2.innerHTML = "<label>"+ keywords[i-1][1] +"</label>";
                col3.innerHTML = "<button value="+ keywords[i-1][0] +" onclick='addAssocToAssign(this.value)' class='button btn-add'>Add Keyword</button>";
            }
            else    /* Override Table Entries */
            {
                var row = table.rows[i];

                var button = row.cells[2].getElementsByTagName('button')[0];
                var label = row.cells[1].getElementsByTagName('label')[0];
                var input = row.cells[0].getElementsByTagName('input')[0];


                button.value = keywords[i-1][0];
                label.innerText = keywords[i-1][1];
                input.value = keywords[i-1][2];
            }
        }
    }

    sessionStorage.setItem("assoc_keywords", JSON.stringify(keywords)); /* Save Updated List */
}

/*
    Reduce the number of occurrances for the associated keywords for the removed assigned keyword

    Param: id -> Keyword's Record ID

    Return: VOID, reduction of occurrances for all associated keywords || removal
 */
function updateAssocListReduce(id) {
    var query_request = {q:'t:56', w:'all'};
    query_request.detail = 'detail';

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
            var keywords = JSON.parse(sessionStorage.getItem("assoc_keywords"));    /* To check if there are any Associated Keywords */

            if (keywords == null)
            {
                return;
            }

            if(response.status == window.hWin.ResponseStatus.OK){
                var recordset = new hRecordSet(response.data);  /* Retrieve Results */
                var record = recordset.getRecord(id);   /* Retrieve Record+Details for Keyword */
                var details = record.d; /* Separate Associated Keywords, for ease of access */

                if (details[1128] == null) { return; }   /* Check if there are no Associated Keywords */

                var assoc_keywords = details['1128'];

                for (var i = 0; i < assoc_keywords.length; i++) /* Process found list of Associated Keywords */
                {
                    for (var j = 0; j < keywords.length; j++)   /* reduce the current associated keyword by 1, remove if zero(0) */
                    {
                        if (keywords[j][0] == assoc_keywords[i])
                        {
                            if (keywords[j][1] == 1)
                            {
//                              console.log('SPLICING -> ' + keywords[j][0]);
                                keywords.splice(j, 1);
                            }
                            else
                            {
                                keywords[j][1] -= 1;
                            }
                            break;
                        }
                    }
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            sessionStorage.setItem("assoc_keywords", JSON.stringify(keywords)); /* Save Associated List */
            updateAssociatedTable();    /* Update Table */
        }
    );
}

/*
    Update the Associated Keywords List

    Param: id -> Newly Added Keyword's Record ID

    Return: VOID, Updated Associated Keyword List
 */
function updateAssociatedList(id){

    checkAssocKeywords(id);

    var query_request = {q:'t:56', w:'all'};    /* Not searching for the specifically added keyword, */
    /* as we need the associated keyword details */

    query_request.detail = 'detail';

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
            var keywords = [];  /* To check if there are any Associated Keywords */
            var temp = JSON.parse(sessionStorage.getItem("assoc_keywords"));
            var assigned = JSON.parse(sessionStorage.getItem("rec_kywd"));

            if (temp != null)
            {
                keywords = temp;
            }

            if(response.status == window.hWin.ResponseStatus.OK){
                var recordset = new hRecordSet(response.data);  /* Retrieve Results */
                var record = recordset.getRecord(id);   /* Retrieve Record+Details for Keyword */
                var details = record.d; /* Separate Associated Keywords, for ease of access */

                if (details[1128] == null) { return; }   /* Check if there are no Associated Keywords */

                var assoc_keywords = details['1128'];

                for (var i = 0; i < assoc_keywords.length; i++) /* Process found list of Associated Keywords */
                {

                    var found = 0;
                    if (!keywords.length)   /* Check if master list of associated keywords are empty */
                    {
                        var rec = recordset.getRecord(assoc_keywords[i]);
                        keywords.push([assoc_keywords[i], 1, rec.d['1']]);
                        continue;
                    }

                    for (var j = 0; j < keywords.length; j++)   /* Check if current associated keyword is already in master list */
                    {
                        if (keywords[j][0] == assoc_keywords[i])
                        {
                            keywords[j][1] += 1;
                            found = 1;
                            break;
                        }

                        for (var k = 0; k < assigned.length; k++)
                        {
                            if (assigned[k] == assoc_keywords[i])
                            {
                                found = 1;
                                break;
                            }
                        }

                        if (found == 1) { break; }
                    }

                    if (found == 0) /* Not found */
                    {
                        var rec = recordset.getRecord(assoc_keywords[i]);
                        keywords.push([assoc_keywords[i], 1, rec.d['1']]);
                    }
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            sessionStorage.setItem("assoc_keywords", JSON.stringify(keywords)); /* Save Associated List */
            updateAssociatedTable();    /* Update Table */
        }
    );
}

/*
    Checks if an added keyword from the Associated Keywords Table
    is also present in the Previous Keyword Table, precautionary

    Param: id -> Keyword's Record ID selected from Associated Keyword Table

    Return: VOID, possible removal of a table row from Previous Keyword Table
 */
function checkPrevKeyword(id){
    var table = document.getElementById('previous_field');

    if (table.rows.length >= 4)
    {
        for (var i = 3, row; row = table.rows[i]; i++)
        {
            if (row.cells[1])
            {
                var cell = row.cells[1];
                var button = cell.getElementsByTagName('button')[0];
                var val = button.value;

                if (val == id)
                {
                    table.deleteRow(i);
                    break;
                }
            }
        }
    }
}

/*
    Checks if a keyword with the supplied ID is located in the Associated Keyword Table+List
    If found they are removed, this should be the only outcome
    However, a precaution has been set in case it is not found

    Param: ID -> Keyword's Record ID selected from Associated Keyword Table

    Return: VOID, remove keyword from Associated Keyword Table and List
 */
function removeAssociatedKywrd(id){
    var table_assoc = document.getElementById('associated_field');
    var table_in;

    for (var i = 1, row; row = table_assoc.rows[i]; i++)
    {
        var cell = row.cells[2];
        var button = cell.getElementsByTagName('button')[0];
        if (button.value == id)
        {
//          console.log("Value -> " + id + ", Found at -> " + i);
            table_in = i;

            break;
        }
    }
    if (table_in == null)
    {
        return;
    }

    table_assoc.deleteRow(table_in);

    checkPrevKeyword(id);   /* Check if ID exists in Previous Keywords Table */

    /* Remove Keyowrd from List */
    var keywords_ID = JSON.parse(sessionStorage.getItem("assoc_keywords"));

    keywords_ID.splice(table_in-1, 1);
    sessionStorage.setItem("assoc_keywords", JSON.stringify(keywords_ID));

    /* Update Table for New Assigned Keyword */
    updateAssociatedList(id);
}

/*
    Add New Assigned Keywords Table

    Param: 
        keyword -> Keyword Title
        id -> Keyword's Record ID

    Return: VOID, additional row to Assigned Keywords Table
 */
function showKeyword(keyword, id){
    var table = document.getElementById('keyword_field');

    var row = table.insertRow(-1);

    var col1 = row.insertCell(0);
    var col2 = row.insertCell(1);

    col1.innerHTML = "<input class='text ui-widget-content ui-corner-all' style='width:100%' value='"+ keyword +"' disabled/>";
    col2.innerHTML = "<button value='"+ id +"' onclick='removeKeyword(this.value)' class='button btn-delete'>Remove Keyword</a>";
}

/*
    Add new Keyword to Assigned Keywords Table+List

    Param:
        title -> Keyword Title
        id -> Keyword's Record ID

        Return: VOID, new keyword is added to Assigned Keyword Table and List
 */
function addKeyword(id, title){
    showKeyword(title, id); /* Add to Table */

    var keywords_ID = [];

    if (sessionStorage.getItem("rec_kywd") != "null")
    {
        keywords_ID = JSON.parse(sessionStorage.getItem("rec_kywd"));
    }

    keywords_ID.push(id);   /* Add to List */

    sessionStorage.setItem("rec_kywd", JSON.stringify(keywords_ID));
}

/*
    Identify the selected keyword's title, then
    Remove the row from the associated keywords table
    Add to assigned keywords table

    Param: id -> Selected Keyword's Record ID

    Return: VOID, selected keyword is now 'assigned' and removed from associated keyword table
 */
function addAssocToAssign(id){
    var table = document.getElementById('associated_field');
    var word;

    for (var i = 1, row; row = table.rows[i]; i++)
    {
        var col1 = row.cells[0];
        var col2 = row.cells[2];
        var button = col2.getElementsByTagName('button')[0];

        if (button.value == id) /* Retrieve Keyword Title */
        {
            word = col1.getElementsByTagName('input')[0].value;
            break;
        }
    }

    addKeyword(id, word);   /* Move to Assigned Table */
    removeAssociatedKywrd(id);  /* Remove from Associated Table */
}

/*
    Remove selected keyword from Assigned Keyword Table+List

    Param: id -> Selected Keyword's Record ID

    Return: VOID, Keyword Removed from Table and List
 */
function removeKeyword(id) {
    var table = document.getElementById('keyword_field');
    var table_in;

    for (var i = 1, row; row = table.rows[i]; i++)
    {
        var cell = row.cells[1];
        var button = cell.getElementsByTagName('button')[0];
        if (button.value == id)
        {
//          console.log("Value -> " + id + ", Found at -> " + i);
            table_in = i;
            break;
        }
    }

    table.deleteRow(table_in);  /* Remove from Table */

    var keywords_ID = JSON.parse(sessionStorage.getItem("rec_kywd"));
    keywords_ID.splice(table_in-1, 1);  /* Remove from List */

    sessionStorage.setItem("rec_kywd", JSON.stringify(keywords_ID));

    setupPreviousWork(sessionStorage.getItem('prev_classify')); /* Check if Keyword was a part of Saved Work */
    updateAssocListReduce(id);  /* Reduce the number of occurrances of associated keywords */
}

/*
    Retrieve and Display the Saved Work's details

    Param: work_code -> Saved Work's MPCE ID

    Return: VOID, displays the saved work's keywords
 */
function setupPreviousWork(work_code){
    var query_request = {q:'t:55 f:952:"' + work_code + '"', w:'all'};  /* Retrieve the record for supplied work code, 55 = Super Book (Work) Table ID*/

    query_request.detail = 'detail';

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
            if(response.status == window.hWin.ResponseStatus.OK){

                var recordset = new hRecordSet(response.data);  /* Retrieve Response */
                var record = recordset.getFirstRecord();    /* Retrieve Work Record */

                if (record == null)
                {
                    return;
                }

                var details = record.d;
                var title = details['1'];
                var project_kywrds = details['955'];

                document.getElementById("prev_title").value = title;
                if (project_kywrds != null) /* Check if Work has Keywords to Display */
                {
                    getPrevKeywords(project_kywrds);
                }
                else
                {
                    var row = document.getElementById('previous_field').insertRow(-1);
                    var col = row.insertCell(0);
                    col.innerHTML = "<div> This Work has no keywords to add</div>";
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }
    );
}

/*
    Retrieve the Saved Work's keywords for display

    Param: keywords_ID -> Array of Keyword 's Record IDs

    Return: VOID, display the saved work's keywords details
 */
function getPrevKeywords(keywords_ID){
    var query_request = {q:'t:56', w:'all'};

    query_request.detail = 'detail';

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
            if(response.status == window.hWin.ResponseStatus.OK){

                var recordset = new hRecordSet(response.data);  /* Retrieve Response */
                var cnt = keywords_ID.length;
                var table_kywrds = document.getElementById('keyword_field');
                var table_prev = document.getElementById('previous_field');

                var i = 0;
                for (; i < cnt; i++)
                {
                    var record = recordset.getRecord(keywords_ID[i]);   /* Grab Record for current Keyword */
                    var details = record.d;
                    var word = details['1'];
                    var id = record.rec_ID;

                    var found = 0;
                    for (var j = 1, row; row = table_kywrds.rows[j]; j++)   /* Check if Keyword is already in Assigned Keyword Table */
                    {
                        var cell = row.cells[1];
                        var button = cell.getElementsByTagName('button')[0];

                        if (button.value == id)
                        {
                            found = 1;
                            break;
                        }
                    }

                    if (found == 0)
                    {
                        for (var k = 3, row; row = table_prev.rows[k]; k++) /* Precautionary, should be removable */
                        {
                            var col = row.cells[1];
                            var button = col.getElementsByTagName('button')[0];

                            if (button.value == id)
                            {
                                found = 1;
                                break;
                            }
                        }

                        if (found == 0)
                        {
                            addPrevKeywords(id, word);
                        }
                    }
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }
    );
}

/*
    Add Keyword to Previous Keyword Table

    Param:
        id -> Keyword's Record ID
        title -> Keyword Title

    Return: VOID, addition of supplied Keyword to Table
 */
function addPrevKeywords(id, title){
    var table = document.getElementById('previous_field');
    var row = table.insertRow(-1);

    var col1 = row.insertCell(0);
    var col2 = row.insertCell(1);

    col1.innerHTML = "<input class='text ui-widget-content ui-corner-all' style='width:100%' value='"+ title +"' disabled/>";
    col2.innerHTML = "<button value="+ id +" onclick='addPrevioustoAssigned(this.value)' class='button btn-add'>Add Keyword</button>";
}

/*
    Checks if an added keyword from the Previous Keywords Table+List
    is also present in the Associated Keyword Table, precautionary

    Param: id -> Keyword's Record ID selected from Previous Keyword Table

    Return: VOID, possible removal of a table row from Associated Keyword Table and List
 */
function checkAssocKeywords(id){
    var table = document.getElementById('associated_field');
    var keywords_ID = JSON.parse(sessionStorage.getItem("assoc_keywords"));
    var found = 0;

    if (keywords_ID == null)    /* Check if there are Associated Keywords */
    {
        return;
    }

    /* Check if ID exists in Associated Keyword List */
    for (var j = 0; j < keywords_ID.length; j++)
    {
        if (keywords_ID[j][0] == id)
        {
            keywords_ID.splice(j, 1);
            sessionStorage.setItem("assoc_keywords", JSON.stringify(keywords_ID));
            if (j < 5)
            {
                found = 1;
            }
            break;
        }
    }

    /* Check the Associated Keyword Table, this will remove the row */
    if (found == 1)
    {
        for (var i = 1, row; row = table.rows[i]; i++)
        {
            var cell = row.cells[2];
            var button = cell.getElementsByTagName('button')[0];

            if (button.value == id)
            {
                table.deleteRow(i);
                break;
            }
        }
    }
}

/*
    Retrieve the necessary value to move the keyword to the assigned table

    Param: id -> Selected Keyword's Record ID

    Return: VOID, assignment of selected keyword
 */
function addPrevioustoAssigned(id){
    var table = document.getElementById('previous_field');
    var word;

    /* Retrieve the keyword's title */
    for (var i = 3, row; row = table.rows[i]; i++)
    {
        var col1 = row.cells[0];
        var col2 = row.cells[1];
        var button = col2.getElementsByTagName('button')[0];

        if (button.value == id)
        {
            word = col1.getElementsByTagName('input')[0].value;
            break;
        }
    }

    addKeyword(id, word);   /* Add to Assigned Table and List */
    removePreviousKywrd(id);    /* Remove from Previous Keyword Table */
    checkAssocKeywords(id); /* Checks if Keyword was an Associated Keyword */
    updateAssociatedList(id);   /* Updates the Associated Keywords for the newly assigned one */
}

/*
    Removes supplied keyword from the Saved Work's Keywords

    Param: id -> Selected Keyword's Record ID

    Return: VOID, removal of keyword from table
 */
function removePreviousKywrd(id){
    var table = document.getElementById('previous_field');
    var table_in;

    for (var i = 3, row; row = table.rows[i]; i++)
    {
        var cell = row.cells[1];
        var button = cell.getElementsByTagName('button')[0];
        if (button.value == id)
        {
//          console.log("Value -> " + id + ", Found at -> " + i);
            table_in = i;
            break;
        }
    }
    if (table_in == null)
    {
        return;
    }


    table.deleteRow(table_in);
}

/*
    Loops the addPrevioustoAssigned process for all keywords within Saved Work's Keywords

    Param: NONE

    Return: VOID, assignment of all keywords within table || message informing that there are no keywords to add
 */
function addAllPrevtoAssigned(){
    var table = document.getElementById('previous_field');

    if (table.rows.length >= 4)
    {
        for (var i = 3, row; row = table.rows[i];)
        {
            if (row.cells[1])
            {
                var cell = row.cells[1];
                var button = cell.getElementsByTagName('button')[0];
                var val = button.value;

                addPrevioustoAssigned(val);
            }
            else
            {
                window.hWin.HEURIST4.msg.showMsgDlg("The Table Contains No Keywords to Add");
                break;
            }
        }
    }
    else
    {
        window.hWin.HEURIST4.msg.showMsgDlg("The Table Contains No Keywords to Add");
    }
}

/*
    Adds the Associated Keywords for the recently searched Keyword

    Param:
        recordset -> all the project keywords
        id -> the recently searched keyword

    Return: VOID, display all the associated keyword
 */

function displayTempKeywords(recordset, id) {
    var record = recordset.getRecord(id);
    var details = record.d;
    var associated_ID = details[1128];

    var tmp_table = document.getElementById('temp_field');
    var assigned_table = document.getElementById('keyword_field');

    emptyTemporaryTable();

    if (associated_ID == null)  /* Check if the searched keyword, has any associated keywords */
    {
        var row = tmp_table.insertRow(-1);
        var cell = row.insertCell(0);
        cell.innerHTML = "<div> No Associated keywords to Display! </div>";

        console.log('NO Associated Keywords for ' + details[1]);
        return;
    }

    var i = 0;
    for (; i < associated_ID.length; i++)
    {
        var j = 1, found = 0;
        /* Check if associated keyword is already a part of assigned table */
        for (var row; row = assigned_table.rows[j]; j++)
        {
            var cell = row.cells[1];
            var button = cell.getElementsByTagName('button')[0];

            if (button.value == associated_ID[i])
            {
                found = 1;
                break;
            }
        }

        if (found == 0)
        {
            var rec = recordset.getRecord(associated_ID[i]);

            var row = tmp_table.insertRow(-1);
            var col1 = row.insertCell(0);
            var col2 = row.insertCell(1);

            col1.innerHTML = "<input class='text ui-widget-content ui-corner-all' style='width:100%;' value='"+ rec.d[1] +"' disabled/>";
            col2.innerHTML = "<button value="+ associated_ID[i] +" onclick='addTemptoAssigned(this.value)' class='button btn-add'>Add Keyword</button>";

            console.log(associated_ID[i] + ' -> ' + rec.d[1]);
        }
    }
}

/*
    Add selected temporary keyword to assigned list+table

    Param: id -> Selected Keyword's Record ID

    Return: VOID, addition of keyword to assigned list
 */

function addTemptoAssigned(id) {
    var table = document.getElementById('temp_field');
    var word;

    for (var i = 2, row; row = table.rows[i]; i++)
    {
        var col1 = row.cells[0];
        var col2 = row.cells[1];
        var button = col2.getElementsByTagName('button')[0];

        if (button.value == id) /* Retrieve Keyword Title */
        {
            word = col1.getElementsByTagName('input')[0].value;
            break;
        }
    }

    addKeyword(id, word);   /* Move to Assigned Table */
    checkPrevKeyword(id);   /* Remove from Previous Table */
    checkAssocKeywords(id); /* Remove from Associated Table */
    removeTempKeyword(id);  /* Remove from Temporary Table */
    updateAssociatedList(id);   /* Updates the Associated Keywords */
}

/*
    Remove selected keyword from temporary table

    Param: id -> Keyword ID to be removed

    Return: VOID, keyword is removed from list
 */

function removeTempKeyword(id) {
    var table = document.getElementById('temp_field');
    var table_in;

    for (var i = 2, row; row = table.rows[i]; i++)
    {
        var cell = row.cells[1];
        var button = cell.getElementsByTagName('button')[0];
        if (button.value == id)
        {
//          console.log("Value -> " + id + ", Found at -> " + i);
            table_in = i;
            break;
        }
    }
    if (table_in == null)
    {
        return;
    }

    table.deleteRow(table_in);
}

/*
    Clear temporary associated keywords table before use 

    Param: NONE

    Return: VOID, table is clear
 */

function emptyTemporaryTable() {
    var table = document.getElementById('temp_field');

    if (table.rows.length < 2)
    {
        return;
    }

    var holder;
    while (holder = table.rows[2])
    {
        table.deleteRow(2)
    }
}

/*
    Comparison Function for the arr.sort() function for the 2D of associated keywords

    Param:
        a -> Array Index 1
        b -> Array Index 2

    Return: Whether the second element within index A is larger than B
 */
function compareIndexes(a, b){
    if (a[1] == b[1])
    {
        return 0;
    }
    else
    {
        return (a[1] > b[1]) ? -1 : 1;
    }
}