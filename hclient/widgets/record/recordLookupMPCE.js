/**
 * recordLookupMPCE.js
 *
 *  1) This file loads html content from recordLookupMPCE.html
 *  2) And contains the functionality of the MPCE toolkit, including:
 *      - Assigning New Keywords to a Super Book (Work) record
 *      - Looking up a list of keywords that have been assigned with a selected keyword in other works
 *      - Display a list of keywords assigned to previously viewed works, checking those used last
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
        /* Setting popup size, based on user preference */
        height: (window.hWin.HAPI4.get_prefs_def('pref_lookupMPCE_h', 780)),
        width:  (window.hWin.HAPI4.get_prefs_def('pref_lookupMPCE_w', 1200)),
        modal:  true,

        mapping: null, //configuration from record_lookup_configuration.json

        title:  "Super Book (Work) Classification Tool for MPCE Project",

        htmlContent: "recordLookupMPCE.html",
        helpContent: null, //help file in context_help folder

        add_new_record: false,
    },

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;

        mapIds(that.options.mapping.fields);    /* Set the session variable 'id_map' to be an object of all record, detail and vocab type ids to simplify the replacement task */
        /* This method was chosen over using a global variable (originally accessed via id_map or window.id_map) */

        clearSessionStorage(); /* Clear Session Storage that is not shared between pop-ups */

        //var record = this.options.edit_record.getFirstRecord(); /* Retrieve Record */
        var record = this.options.edit_fields;  /* Retrieve Edit Fields */

        //msgToConsole('Record:', record);  /* The record being used */
        //msgToConsole('Mapped Fields:', that.options.mapping.fields);  /* The id map */

        if (sessionStorage.getItem('id_map') == null)
        {
            window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
            return;
        }

        var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));

        /* Check if selected Work has: project keyword/s, parisian category, and a basis for classification */
        if (record[id_map.DT_Keywords] != null && record[id_map.DT_Keywords] != "") // Project Keywords
        {
            sessionStorage.setItem("rec_kywd", JSON.stringify(record[id_map.DT_Keywords]));
        }

        if (record[id_map.DT_Category] != null && record[id_map.DT_Category] != "")  // Parisian Category
        {
            sessionStorage.setItem("rec_category", record[id_map.DT_Category]);
        }

        if (record[id_map.DT_Basis] != null && record[id_map.DT_Basis] != "")  // Basis for Classification
        {
            sessionStorage.setItem("rec_basis", record[id_map.DT_Basis]);
        }

        if (record[id_map.DT_Notes] != null && record[id_map.DT_Notes] != "") // Classification Notes
        {
            sessionStorage.setItem("rec_notes", record[id_map.DT_Notes]);
            $('#notes_field').val(record[id_map.DT_Notes]);
        }

        $('#title_field').text(record[1]); // Work Title
        $('#work-code_field').text(record[id_map.DT_MPCEId]);   // Work MPCE_ID

        /* Retrieve all Parisian Keywords (Term ID = 6380) */
        var parisian_Category = window.hWin.HEURIST4.ui.createTermSelect( this.element.find('#category_field')[0],
            {
                vocab_id: [id_map.VI_Category],    // Vocabulary ID/Term ID
                defaultTermID: sessionStorage.getItem("rec_category"),   //Default/Selected Term
                topOptions: [{key:0, title:"Select a Parisian Classification..."}],      //Top Options  [{key:0, title:'...select me...'},....]
                useHtmlSelect: false     // use native select of jquery select
            }
        );

        /* Retrieve all Basis for Classification (Term ID = 6498) */
        var basis = window.hWin.HEURIST4.ui.createTermSelect( this.element.find('#basis_field')[0],
            {
                vocab_id: [id_map.VI_Basis],    // Vocabulary ID/Term ID
                defaultTermID: sessionStorage.getItem("rec_basis"),   // Default/Selected Term
                useHtmlSelect: false    // use native select of jquery select
            }
        ); 

        var keyword_IDs = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("rec_kywd")); /* Array of Project Keyword's Record IDs */

        if (keyword_IDs)   /* Check if Work has assigned Keywords */
        {
            /* Retrieve master list of project keywords, we need to display their titles for the user */
            var query_request = {q:'t:' + id_map.RT_Keyword, detail: 'detail'};

            /* Perform Search */
            window.hWin.HAPI4.RecordMgr.search(query_request,
                function( response ){
                    if(response.status == window.hWin.ResponseStatus.OK){   /* Check if Record Search was successful */

                        var recordset = new hRecordSet(response.data);  /* Retieve Search Results */  
                        var cnt = keyword_IDs.length;   /* Number of Project Keywords */

                        for (var i = 0; i < cnt; i++)
                        {
                            var record = recordset.getRecord(keyword_IDs[i]);   /* Retrieve Record+Details on ith Keyword, have to use Record ID */
                            var details = record.d;     /* Separate Details from the main Record */
                            var title = details[1];     /* Project Keyword's Title */
                            var id = record.rec_ID;     /* Project Keyword's Record ID */

                            showKeyword(title, id);  /* Add to Keyword Table */
                        }

                    }else{  /* Record Seach Failed */
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
        }
        
        setupRecentWorks();

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

        /* Set what the 'Update Record' button, bottom right of form, does */
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction'), false );
        this.element.parents('.ui-dialog').find('#btnDoAction').before('<span id="save-msg" style="display:none;font-size:1.2em;">Add or Uncheck Selections</span>');

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

        if (sessionStorage.getItem('id_map') == null)
        {
            window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
            
            clearSessionStorage();
            window.hWin.HEURIST4.msg.sendCoverallToBack();
            this._as_dialog.dialog('close');
        }

        var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));

        // assign values to be sent back to edit form - format is similar to this.options.edit_fields
        this._context_on_close = {};
        this._context_on_close[id_map.DT_Category] = $('#category_field').val();
        this._context_on_close[id_map.DT_Basis] = $('#basis_field').val();
        this._context_on_close[id_map.DT_Notes] = $('#notes_field').val();

        if (sessionStorage.getItem("rec_kywd") != null)   /* Check if currently editing work has keywords */
        {
            var keyword_IDs = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("rec_kywd"));

            this._context_on_close[id_map.DT_Keywords] = keyword_IDs;
        }

        /* Check if work can be added to list of recently viewed works */
        var works = [];

        if (sessionStorage.getItem("prev_classify") != null)
        {
            works = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("prev_classify"));
        }

        if ((works.find(e => e == document.getElementById('work-code_field').innerText) == null) && 
            (sessionStorage.getItem('rec_kywd') != null) && (sessionStorage.getItem('rec_kywd') != "")) 
        {
            works.unshift(document.getElementById('work-code_field').innerText);

            if (works.length == 5)
            {
                works.splice(4, 1);
            }

            sessionStorage.setItem("prev_classify", JSON.stringify(works));        
        }

        clearSessionStorage();
        
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

        if (sessionStorage.getItem('id_map') == null)
        {
            window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
            return;
        }

        var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));

        var popup_options = {
            select_mode: (false)?'select_multi':'select_single',    // enable multi select or not, current set to single select
            select_return_mode: 'recordset', //or 'ids' (default)
            edit_mode: 'popup',
            selectOnSave: true, // true = select popup will be closed after add/edit is completed
            title: 'Assign a New Keyword',
            rectype_set: [id_map.RT_Keyword], // record type ID
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

                    var rec_Title = recordset.fld(record,'rec_Title');

                    if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                        // no selection 
                        // consider that record was not saved - it returns FlagTemporary=1 

                        msgToConsole('keywordLookup() Error: Selected Record is Invalid', null);
                        window.hWin.HEURIST4.msg.showMsgErr('Selected Record is Invalid');

                        return;
                    }

                    var targetID = recordset.fld(record,'rec_ID');
                    
                    var keyword_IDs = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("rec_kywd"));

                    if (keyword_IDs)
                    {
                        var result = keyword_IDs.find(row => row == targetID);
                    
                        if (result != null)   /* Check if Keyword is already a part of Work's Keyword Master List */
                        {
                            window.hWin.HEURIST4.msg.showMsgDlg('Project Keyword Already Allocated to Work');

                            return;
                        }
                    }

                    var query_request = {q:'t:' + id_map.RT_Keyword, detail:'detail'};

                    window.hWin.HAPI4.RecordMgr.search(query_request,
                        function( response ){
                            if(response.status == window.hWin.ResponseStatus.OK){

                                var recordset = new hRecordSet(response.data);

                                var record = recordset.getRecord(targetID);
                                var details = record.d;
                                var title = details[1];

                                addKeyword(targetID, title); /* Add Selected Keyword to Master Table+List */
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
        Search for Editions of the Work within Heurist, displaying the results in a seperate popup

        Param: None

        Return: VOID
     */
     lookupEditions: function(){
        if (sessionStorage.getItem('id_map') == null)
        {
            window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
            return;
        }

        var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));

        var work_title = document.getElementById('title_field').innerHTML;

        var query_request = {q:'t:' + id_map.RT_Editions + ' f:' + id_map.DT_Title + ':"' + work_title + '"', detail:"detail"};

        window.hWin.HAPI4.RecordMgr.search(query_request,
            function( response ){
                
                if(response.status == window.hWin.ResponseStatus.OK){

                    var recordset = new hRecordSet(response.data);

                    var records = recordset.getRecords();

                    var editions = [];

                    for(i in records)
                    {
                        var record = records[i];
                        var details = record.d;

                        editions.push(record[5]);
                    }

                    if (editions != null)
                    {
                        displayEditions(editions);
                    }
                    else
                    {
                        window.hWin.HEURIST4.msg.showMsgDlg("No Editions Found");
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
     },

    /*
        Retrieve the list of checked keywords that need to be moved to the assigned keyword list

        Param: None

        Return: VOID, assignment of selected keyword
     */
    addPrevtoAssigned: function(){
        var list = document.getElementById('prev_field');

        var items = getAllChecked(list);
        var title;

        if (items == null || items.length == 0)
        {
            window.hWin.HEURIST4.msg.showMsgDlg('There are No Recent Keywords Selected to Assign');     
            return;
        }

        for (var i = 0; i < items.length; i++)
        {
            list = document.getElementById('prev_field');

            title = searchList(list, items[i]);

            if (title == -1)
            {
                msgToConsole('addPrevtoAssigned() Error: title Not Found', null);
                return 0;
            }

            removeFromList(list, items[i]);    /* Remove from Previous Keyword Table */
            addKeyword(items[i], title);   /* Add to Assigned Table and List */
        } 

        disableUpdateBtn();      
    },

    /*
        Retrieve the list of checked keywords that need to be moved to the assigned keyword list

        Param: None

        Return: VOID
     */
    addAssoctoAssigned: function(){
        var list = document.getElementById('associated_field');

        var items = getAllChecked(list);
        var title;

        if (items == null || items.length == 0)
        {
            window.hWin.HEURIST4.msg.showMsgDlg('There are No Associated Keywords Selected to Assign');     
            return;
        }

        for (var i = 0; i < items.length; i++)
        {
            list = document.getElementById('associated_field');

            title = searchList(list, items[i]);

            if (title == -1)
            {
                console.log('addAssoctoAssigned() Error: title Not Found');
                return 0;
            }

            removeFromList(list, items[i]);    /* Remove from Associated Keyword Table */
            addKeyword(items[i], title);   /* Add to Assigned Table and List */
        }

        disableUpdateBtn();
    },

    /** External Searches **/

    /*
        Search for Work Title in Google Books

        Param: None

        Return: (false), opens new tab for Google Books search
     */
    lookupGoogle: function(){
        var title = document.getElementById('title_field').innerText;
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
        var title = document.getElementById('title_field').innerText;
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
        var title = document.getElementById('title_field').innerText;
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
        var title = document.getElementById('title_field').innerText;
        var url = 'http://kvk.bibliothek.kit.edu/index.html?lang=en&digitalOnly=0&embedFulltitle=0&newTab=0&TI=' + title;

        window.open(url, '_blank');

        return false;
    },  
});

/** Associated Keyword System **/

/* 
    Searches for works that have the selected keyword,
    the program then goes through each returned work's keywords, 
    adding them to the associated keyword list, 
    finally displaying the result to the user.

    Param: id -> Keyword's Title Mask (or, the Keyword's ID)

    Return: VOID
 */
function setupAssocKeywords(id) {

    if (sessionStorage.getItem('id_map') == null)  /* Check if Id map is available */
    {   /* Only possible if the session storage (cache) has been cleared since opening the popup */
        window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        return;
    }

    var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));

    var list = document.getElementById('associated_field');

    sessionStorage.setItem('assoc_kywd', id);

    list.innerHTML = '<div style="font-size:1.5em">Loading List...</div>';

    sessionStorage.removeItem('assoc_keywords');

    var query_request = {q:'t:' + id_map.RT_Work + ' linkedto:'+ id, detail:'detail'};  /* Retrieve all works that have the selected keyword */

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function(response){
            if (response.status == window.hWin.ResponseStatus.OK)
            {
                var recordset = new hRecordSet(response.data);
                var records = recordset.getRecords();

                var ids = [];

                /* For displaying the total number of works to user, i.e. (n = ...) */
                var len = Object.keys(records).length;
                sessionStorage.setItem('assoc_count', len);

                /* Travel through results to retrieve the each work's list of keywords */
                for (i in records)
                {

                    var record = records[i];

                    /* To avoid results that are not from the correct table, usually happens if query is wrong */
                    if (record[4] != id_map.RT_Work) { continue; }

                    var details = record.d;

                    if ((details[id_map.DT_Keywords] == null) || (details[id_map.DT_Keywords].length < 2))
                    {
                        continue;
                    }

                    ids.push(details[id_map.DT_Keywords]);
                }

                /* Add the complete list retrieved to the associated keyword */
                updateAssocList(ids);
            }
            else
            {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }
    );

    return;
}

/*
    Updates the Associate Keyword List (saved within session storage assoc_keywords)
    Performs a check if the current keyword is already part of the list, 
        if it is, increase occurances
        else, push into list
    The list is then sorted by occurrances
    Once completed, the corresponding table is updated

    Param: ids -> list of associated keywords

    Return: VOID
 */

function updateAssocList(ids){

    if (sessionStorage.getItem('id_map') == null)  /* Check if Id map is available */
    {   /* Only possible if the session storage (cache) has been cleared since opening the popup */
        window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        return;
    }

    var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));

    /* Retrieving the master list of project keywords, we need the titles for display */
    var query_request = {q:'t:' + id_map.RT_Keyword, detail:'detail'};

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
            if(response.status == window.hWin.ResponseStatus.OK)
            {
                var keywords = [];

                if (sessionStorage.getItem("assoc_keywords") != null) /* Double check for existing list of associated keywords, shouldn't be needed */
                {
                    keywords = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("assoc_keywords"));
                }

                var recordset = new hRecordSet(response.data);

                /* Go through 2d array of keywords */
                for (var i = 0; i < ids.length; i++)
                {
                    for (var j = 0; j < ids[i].length; j++)
                    {
                        var record = recordset.getRecord(ids[i][j]);
                        var details = record.d;
                        var title = details[1];

                        if (keywords.length < 0)
                        {
                            keywords.push([ids[i][j], 1, title]);
                            continue;
                        }

                        isAssocKeyword(keywords, ids[i][j], title); /* Check if current keyword is already in list */                   
                    }
                }               
            }
            else
            {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            sessionStorage.setItem("assoc_keywords", JSON.stringify(keywords));
            updateAssocDisplay();   /* Update UI display of associated keywords */
        }
    );
}

/*
    Update the Associated Keywords List, UI, and display which kyeword was selected

    Param: None

    Return: VOID
 */
function updateAssocDisplay(){

    var max = 13;
    var list = document.getElementById('associated_field');
    
    if (sessionStorage.getItem("assoc_keywords") == null) 
    {
        return;
    }

    var keywords = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("assoc_keywords"));

    if (sessionStorage.getItem("rec_kywd") == null)
    {
        return;
    }

    var assigned = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("rec_kywd"));

    keywords.sort(compareIndexes);

    /* Empty List, before use */
    list.innerHTML = '';
    var selected_kywd = sessionStorage.getItem('assoc_kywd');
    
    $('#assoc_total').text("(n=" + sessionStorage.getItem("assoc_count") + ")");

    /* Now add items into the HTML list */
    for (var i = 0; list.getElementsByTagName('li').length < max && keywords.length > i; i++)
    {
        var item = document.createElement('li');

        if (keywords[i] == null) 
        {
            break; 
        }

        if (assigned.find(e => e == keywords[i][0]))
        {
            if (keywords[i][0] == selected_kywd) { $('#assoc_kywd').text(keywords[i][2]); }
            continue;
        }
        else
        {
            item.innerHTML = "<input type='checkbox' onclick='disableUpdateBtn();' value='" + keywords[i][0] + "' name='" + keywords[i][0] + "'><label for='" + keywords[i][0] + "'> " 
                                + keywords[i][2] + " </label>&nbsp;&nbsp;<label> [ " + keywords[i][1] + " ] </label>";

            list.appendChild(item);
        }
    }
}

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
function isAssocKeyword(arr, id, title) {
    
    var found = 0;

    for (var i = 0; i < arr.length; i++)
    {
        if (arr[i][0] == id)
        {
            arr[i][1] += 1;
            found = 1;
            break;
        }
    }

    if (found == 0)
    {
        arr.push([id, 1, title]);
    }
}

/** Recent Keywords System **/

/*
    Sets up the list of works and calls startup function for Recent Keywords,
    This will display a list of keywords from previously viewed works from within a session (life span of the tab),
    a list of 4 work titles is remembered and those four work's keywords are displayed to the UI.
    Keywords assigned to the current work are ignored, as are ones already displayed under the recent keyword section.

    Param: None

    Return: VOID
 */
async function setupRecentWorks(){

    if (sessionStorage.getItem('id_map') == null)  /* Check if Id map is available */
    {   /* Only possible if the session storage (cache) has been cleared since opening the popup */
        window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        return;
    }

    var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));    
    
    var works = [];

    if (sessionStorage.getItem("prev_classify") != null)    /* Check if there are any previously viewed works */
    {   
        works = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("prev_classify"));

        var ch_set;

        /* Which work will have it's keywords automatically set to checked, each work can only appear once in the list */
        if (works[0] != document.getElementById('work-code_field').innerText)
        {
            ch_set = works[0];
        }
        else if (works.length != 1)
        {
            ch_set = works[1];
        }

        /* Now retrieve and display the list */
        for (var i = 0; i < works.length; i++)
        {
            if (works[i] == document.getElementById('work-code_field').innerText) { continue; }

            /* Additional information is sent, depending on whether the work's keywords are to be checked or not  */
            if (ch_set == works[i])
            {
                startRecentWork(works[i], 1);
            }
            else
            {
                startRecentWork(works[i], 0);
            }

            /* This is to allow the keywords to be displayed in the correct order, without this the default checked keywords can appear out of order */
            await sleep(50);
        }

        disableUpdateBtn(true);
    }

    /* Check if current work can be added to the list of previously (recent) works */
    if ((works.find(row => row == document.getElementById('work-code_field').innerText) == null) && 
        (sessionStorage.getItem('rec_kywd') != null) && (record[id_map.DT_Keywords] != ""))
    {
        works.unshift(document.getElementById('work-code_field').innerText);
    }

    if (works.length == 5)
    {
        works.splice(4, 1);
    }

    if (works == null)
    {
        return;
    }

    sessionStorage.setItem("prev_classify", JSON.stringify(works)); 
}

/*
    Retrieves the supplied work's keywords and add them to the displayed list

    Param: work_code -> Work Code of Interest

    Return: VOID
 */
function startRecentWork(id, set) {

    if (sessionStorage.getItem('id_map') == null)  /* Check if Id map is available */
    {   /* Only possible if the session storage (cache) has been cleared since opening the popup */
        window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        return;
    }

    var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));    
    
    if (set == null || id == null)
    {
        msgToConsole('startRecentWork() Error: No Recent Works Saved', null);
        return;
    }

    var query_request = {q:'t:' + id_map.RT_Work + ' f:' + id_map.DT_MPCEId + ':"' + id + '"', detail:'detail'};  /* Retrieve the record for supplied work code */

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
            if(response.status == window.hWin.ResponseStatus.OK){
            
                var recordset = new hRecordSet(response.data);
                var record = recordset.getFirstRecord();
                var details = record.d;

                /* Check if the record has project keywords to display */
                if (details[id_map.DT_Keywords] != null)
                {
                    getRecentKeywords(details[id_map.DT_Keywords], set);
                }

            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }
    );
}

/*
    From a list of keywords, retrieve their titles and display them to the user, or remove them if necessary

    Param: keyword_IDs -> Array of Keyword IDs

    Return: VOID
 */
function getRecentKeywords(keyword_IDs, set) {
    
    if (sessionStorage.getItem('id_map') == null)  /* Check if Id map is available */
    {   /* Only possible if the session storage (cache) has been cleared since opening the popup */
        window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        return;
    }

    var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));

    /* Retrieve master list of all project keywords, we need their titles for display */
    var query_request = {q:'t:' + id_map.RT_Keyword, detail:'detail'};

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
            if(response.status == window.hWin.ResponseStatus.OK){

                var recordset = new hRecordSet(response.data);
                var list_kywrds = document.getElementById('keyword_field');    /* Assigned Keywords */
                var list_recent = document.getElementById('prev_field');    /* Already Displayed Previously (Recently) used keywords */

                var row_cnt = keyword_IDs.length;

                for (var j = 0; j < row_cnt; j++)
                {

                    var record = recordset.getRecord(keyword_IDs[j]);
                    var details = record.d;

                    var title = details[1];
                    var id = record.rec_ID;

                    var setcb = 0;

                    var found = findListItem(list_kywrds, id); /* Check if keyword is already assigned */

                    if (found < 0)
                    {
                        found = findListItem(list_recent, id);  /* Check if keyword has been displayed as a recently used keyword */

                        if (found < 0)
                        {
                            addRecentKeywords(id, title[0], set);   /* Add new keyword to recently used keyword list */
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
    Add new keyword to recently used keyword list

    Param:
        id -> Keyword's Record ID
        title -> Keyword Title
        state -> whether the checkbox is set or not

    Return: VOID
 */
function addRecentKeywords(id, title, set){

    var list = document.getElementById('prev_field');

    var item = document.createElement('li');

    if (set == 1)
    {
        item.innerHTML = "<input type='checkbox' onclick='disableUpdateBtn();' value='" + id + "' name='" + id + "' checked><label for='" + id + "'> " + title + " </label>";
    }
    else
    {
        item.innerHTML = "<input type='checkbox' onclick='disableUpdateBtn();' value='" + id + "' name='" + id + "'><label for='" + id + "'> " + title + " </label>";
    }

    list.appendChild(item);
    return;
}

/*
    Check if provided keyword is a displayed as a recently used keyword

    Param: id -> Keyword's ID

    Return: VOID
 */
function isRecentKeyword(id){
    if (sessionStorage.getItem('id_map') == null)  /* Check if Id map is available */
    {   /* Only possible if the session storage (cache) has been cleared since opening the popup */
        window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        return;
    }

    var id_map = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('id_map'));

    if (sessionStorage.getItem('prev_classify') == null)   /* List of previous keywords */
    {
        return;
    }

    var prev_works = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem('prev_classify'));   

    /* Go through all previous works, searching for the keyword of interest */
    for (var i = 0; i < prev_works.length; i++)
    {
        var query_request = {q:'t:' + id_map.RT_Work + ' f:' + id_map.DT_MPCEId + ':"' + prev_works[i] + '"', detail:'detail'};

        window.hWin.HAPI4.RecordMgr.search(query_request,
            function( response ){
                if(response.status == window.hWin.ResponseStatus.OK){

                    var recordset = new hRecordSet(response.data);
                    var record = recordset.getFirstRecord();
                    var details = record.d;

                    if (record == null)
                    {
                        return;
                    }

                    if (details[id_map.DT_Keywords] != null)
                    {
                        if (details[id_map.DT_Keywords].find(e => e == id))
                        {
                            var ids = [];
                            ids.push(id);

                            getRecentKeywords(ids);
                        }
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
    }
}

/** Assigned Keyword System **/

/*
    Add Newly Assigned Keyword to Table UI

    Param: 
        keyword -> Keyword's Title
        id -> Keyword's Record ID

    Return: VOID
 */
function showKeyword(keyword, id){
    var list = document.getElementById('keyword_field');

    var item = document.createElement('li');

    item.innerHTML = "<label class='label-tag'>"+ keyword +"</label>"
    + "<button value='"+ id +"' onclick='setupAssocKeywords(this.value)' class='btn btn-info'"
    + " style='float:right;font-size:0.75em;display:inline-block;'>Find Associated</button>"
    + "<button value='"+ id +"' onclick='removeKeyword(this.value)' class='btn btn-delete'"
    + " style='float:right;margin-right:7px;font-size:0.75em;display:inline-block;'>X</button>";

    list.append(item);
}

/*
    Add new Keyword to Assigned Keywords List

    Param:
        title -> Keyword Title
        id -> Keyword's Record ID

    Return: VOID
 */
function addKeyword(id, title){
    showKeyword(title, id); /* Add to Table */

    removeFromList(document.getElementById('associated_field'), id); /* Remove from Associated List */
    removeFromList(document.getElementById('prev_field'), id);   /* Remove from Recent List */

    var keyword_IDs = [];

    if (sessionStorage.getItem("rec_kywd") != null)
    {
        keyword_IDs = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("rec_kywd"));
    }


    keyword_IDs.push(id);   /* Add to List */

    sessionStorage.setItem("rec_kywd", JSON.stringify(keyword_IDs));
}

/*
    Remove selected keyword from Assigned Keyword Table and List

    Param: id -> Selected Keyword's Record ID

    Return: VOID
 */
function removeKeyword(id) {
    var list = document.getElementById('keyword_field');
    
    var list_in = removeFromList(list, id);

    if (list_in == -2)
    {
        msgToConsole('removeKeyword() Error: Keyword was not found', null);
        window.hWin.HEURIST4.msg.showMsgErr('Keyword was not found in Assigned Keyword Table');
        return;
    }

    var keyword_IDs = window.hWin.HEURIST4.util.isJSON(sessionStorage.getItem("rec_kywd"));
    keyword_IDs.splice(list_in, 1);  /* Remove from List */

    sessionStorage.setItem("rec_kywd", JSON.stringify(keyword_IDs));
    
    isRecentKeyword(id);    /* Check if keyword was a recent keyword */
    updateAssocDisplay();   /* Check if keyword was a associated keyword */
}

/*
    Display all found editions for the selected work

    Param: works -> List of Edition's Title Masks

    Return: VOID
 */
function displayEditions(works){

    var max = 10;
    var editions = [];

    for (var i = 0; i < works.length && i < max; i++)
    {
        editions = editions.concat("<div style='font-size: 1.2em;'>" + works[i] + "</div>", "<br /><br />");
    }

    window.hWin.HEURIST4.msg.showMsgDlg(editions);
}

/** Other Function **/

/*
    Removes supplied keyword from the supplied list

    Param: 
        list -> document element, unordered list
        id -> Selected Keyword's Record ID

    Return: VOID
 */
function removeFromList(list, id){

    var list_in = findListItem(list, id);

    if (list_in == -2)
    {
        msgToConsole('removeFromList() Error: unable to remove row from list', list);
        return;
    }
    else if (list_in == -1)
    {
        msgToConsole('removeFromlist() Msg: List item does not exist', null);
        return;
    }
    
    list.getElementsByTagName('li')[list_in].remove();
}

/*
    Determines which keywords have been checked

    Param: list -> document element, unordered list

    Return: Array -> list of checked keywords
 */
function getAllChecked(list){
    var checked_items = [];

    var items = list.getElementsByTagName('li');

    for (var i = 0; i < items.length; i++)
    {
        var chkbox = items[i].getElementsByTagName('input')[0];

        if (chkbox.checked)
        {
            checked_items.push(chkbox.value);
        }
    }

    return checked_items;
}

/*
    Search a list for a checkbox containing the value

    Param:
        list -> list to be searched
        value -> value searching for (input element value)

    Return: Array -> label for the found checkbox
 */
function searchList(list, value) {
    var result;

    if (list == null)
    {
        console.log('searchList() Error: No list Was Provided');
        return -1;
    }
    else if (value == null)
    {
        console.log('searchList() Error: No value Was Provided');
        return -1;
    }

    var list_items = list.getElementsByTagName('li');
    var len = list_items.length;

    for (var i = 0; i < len; i++)
    {
        if (list_items[i] == null)
        {
            // Return Error/None Found
            console.log('searchList() Error: No Rows Found');
            return -1;
        }

        var elements = list_items[i].getElementsByTagName('input')[0];

        if (elements != null)
        {
            if (elements.value == value)
            {
                result = list_items[i].getElementsByTagName('label')[0].innerText;
                return result;
            }
        }
    }

    console.log('searchList() Error: id Not Found');
    return -1;
}

/*
    Search a List for the row of the supplied value,
    Note: Checks the first found input's value within each row

    Param:
        list -> list to be checked
        value -> value searching for

    Return: Number -> Row Index of the found value
 */
function findListItem(list, value) {
    var index = -1;

    if (list == null)
    {
        console.log('findListItem() Error: No list Was Provided');
        return -2;
    }
    else if (value == null)
    {
        console.log('findListItem() Error: No value Was Provided');
        return -2;
    }

    var list_items = list.getElementsByTagName('li');

    if (list_items == null)
    {
        msgToConsole('findListItem() Error: Unable to Retrieve list items from list', list);
        return;
    }

    var len = list_items.length;

    for (var i = 0; i < len; i++)
    {
        if (list_items[i] == null)
        {
            // Return Error/None Found
            console.log('findListItem() Error: No List Items Found');
            return -2;
        }

        var elements = list_items[i].getElementsByTagName('input')[0];

        if (elements != null)
        {
            if (elements.value == value)
            {
                index = i;
                return index;
            }
        }
        else
        {
            elements = list_items[i].getElementsByTagName('button')[0];
            if (elements != null)
            {
                if (elements.value == value)
                {
                    index = i;
                    return index;
                }
            }
        }
    }  

    return index;
}

/*
    Disable the Update Record button when a checkbox is checked, displaying message to uncheck or add selections

    Param:
        override(false) -> ignore usual check, recent keywords wasn't triggering the disable correctly

    Return: VOID
*/
function disableUpdateBtn(override = false){

    if(override){
        $($('.mpce')[0].parentNode.parentNode).find('#btnDoAction').attr('disabled', true).css({'cursor': 'default', 'opacity': '0.5'});
        $($('.mpce')[0].parentNode.parentNode).find('#save-msg').css({'margin':'10px', 'display':'inline-block'});
        return;
    }

    if($('.mpce').find('input:checked').not('#check-all').length > 0){
        $($('.mpce')[0].parentNode.parentNode).find('#btnDoAction').attr('disabled', true).css({'cursor': 'default', 'opacity': '0.5'});
        $($('.mpce')[0].parentNode.parentNode).find('#save-msg').css({'margin':'10px', 'display':'inline-block'});
    }
    else{
        $($('.mpce')[0].parentNode.parentNode).find('#btnDoAction').attr('disabled', false).css({'cursor': 'default', 'opacity': '1'});
        $($('.mpce')[0].parentNode.parentNode).find('#save-msg').css({'margin':'10px', 'display':'none'});
    }
}

/*
    Toggles the checkboxes based on whether the "Check All" options is set

    Param: 
        list -> list of interest
        isChecked -> 

    Return: VOID
*/
function checkAllOptions(list, isChecked){

    var items = list.getElementsByTagName('li');

    for (var i = 0; i < items.length; i++) {

        var chkbox = items[i].getElementsByTagName('input')[0];

        if (chkbox.checked != isChecked) {
            chkbox.checked = isChecked;
        }
    }

    disableUpdateBtn();
}

/*
    Comparison Function for the arr.sort() function for the 2D of associated keywords
    Note: arr[n][1] == the number of occurrances

    Param:
        a -> Array Index 1
        b -> Array Index 2

    Return: Boolean -> whether the second element within index A is larger than B
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

/*
    Clears all session storage, not need for other works

    Param: None

    Return: VOID
 */
function clearSessionStorage() {
    sessionStorage.removeItem("assoc_keywords");

    sessionStorage.removeItem("rec_kywd");
    sessionStorage.removeItem("rec_category");
    sessionStorage.removeItem("rec_basis");
    sessionStorage.removeItem("rec_notes");
}

/*
    Message/Data pair for printing to the console

    Param:
        msg -> primary message to console log
        data -> can be null, additional information

    Return: VOID
 */
function msgToConsole(msg, data){
    console.log(msg);

    if (data != null)
    {
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

/*
    Mapping Ids for Detail and Record Types (Heurist Specific)

    Param: None

    Return: VOID
 */
function mapIds(mapping) {

    var mapping_ids = {
        /* Record Type, Detail Type and Vocab Id Map, for now you need to replace all instances of each value with the database correct one */
        RT_Editions: 54,                            /* Editions Table */        
        RT_Work: 55,                                /* Super Book (Works) Table */
        RT_Keyword: 56,                             /* Project Keywords Table */
        
        DT_Title: 938,                              /* Work Title Details Index, from outside the Super Book (Work) Table */
        DT_MPCEId: (mapping['workID'] != null) ? mapping['workID'] : 952,                       /* MPCE ID Details Index */
        DT_Category: (mapping['parisianClassify'] != null) ? mapping['parisianClassify'] : 1060, /* Parisian Category Details Index */
        DT_Keywords: (mapping['projectKywds'] != null) ? mapping['projectKywds'] : 955,         /* Project Keywords Details Index */
        DT_Basis: (mapping['basisClassify'] != null) ? mapping['basisClassify'] : 1034,          /* Basis for Classifcation Details Index */
        DT_Notes: (mapping['classifyNotes'] != null) ? mapping['classifyNotes'] : 1035,          /* Classification Notes Details Index */

        VI_Category: 6953,                          /* Parisian Category Vocab ID */
        VI_Basis: 6936,                             /* Basis for Classification Vocab ID */
    };

    sessionStorage.setItem('id_map', JSON.stringify(mapping_ids));
}