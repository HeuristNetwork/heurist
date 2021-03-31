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

        height: 780,
        width:  1600,
        modal:  true,

        mapping: null, //configuration from record_lookup_configuration.json

        title:  "Work Classification Toolkit",

        htmlContent: "recordLookupMPCE.html",
        helpContent: null, //help file in context_help folder

        add_new_record: false,
    },

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        var that = this;

        mapIds(that.options.mapping.fields);	/* Set the session variable 'id_map' to be an object of all record, detail and vocab type ids to simplify the replacement task */
        /* This method was chosen over using a global variable (originally accessed via id_map or window.id_map) */

        clearSessionStorage(); /* Clear Session Storage that is not shared between pop-ups */

        //var record = this.options.edit_record.getFirstRecord(); //see core/recordset.js for various methods to manipulate a record
        var record = this.options.edit_fields;

        msgToConsole('Record:', record);
        msgToConsole('Mapped Fields:', that.options.mapping.fields);

        if (sessionStorage.getItem('id_map') == null)
        {
        	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        	return;
        }

        var id_map = JSON.parse(sessionStorage.getItem('id_map'));

        /* Check if selected Work has: project keyword/s, parisian category, and a basis for classification */
        if (record[id_map.DT_Keywords] != null && record[id_map.DT_Keywords] != "") // Project Keywords
        {
            sessionStorage.setItem("rec_kywd", JSON.stringify(record[973]));
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
                topOptions: [{key:0, title:"Select a Parisian Classification...", value:"null"}],      //Top Options  [{key:0, title:'...select me...'},....]
                useHtmlSelect: false     // use native select of jquery select
            }
        );

        /* Retrieve all Basis for Classification (Term ID = 6498) */
        var basis = window.hWin.HEURIST4.ui.createTermSelect( this.element.find('#basis_field')[0],
            {
                vocab_id: [id_map.VI_Basis],    // Vocabulary ID/Term ID
                defaultTermID: sessionStorage.getItem("rec_basis"),   // Default/Selected Term
                topOptions: [{key:0, title:"Select Basis for Classification", value:"null"}],
                useHtmlSelect: false    // use native select of jquery select
            }
        ); 


        if (sessionStorage.getItem('rec_kywd') != null)   /* Check if Work has assigned Keywords */
        {
	        /* Get all Project Keywords and grab the ones assigned to current Work */
	        var query_request = {q:'t:' + id_map.RT_Keyword, detail: 'detail'}; /* set the option for the search, these options can be found in reacordSearch.php */

            /* Perform Search */
            window.hWin.HAPI4.RecordMgr.search(query_request,
                function( response ){
                    if(response.status == window.hWin.ResponseStatus.OK){   /* Check if Record Search was successful */

                        var recordset = new hRecordSet(response.data);  /* Retieve Search Results */
                        var keyword_IDs = JSON.parse(sessionStorage.getItem("rec_kywd"));   /* Array of Project Keyword's Record IDs */
                        var cnt = keyword_IDs.length;   /* Number of Project Keywords */

                        for (var i = 0; i < cnt; i++)
                        {
                            var record = recordset.getRecord(keyword_IDs[i]);   /* Retrieve Record+Details on ith Keyword, have to use Record ID */
                            var details = record.d;
                            var title = details[1];     /* Separate Details from the main Record */
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
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());	// use this function to show "in progress" animation and cover-all screen for long operation

        if (sessionStorage.getItem('id_map') == null)
        {
        	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        	
        	clearSessionStorage();
        	window.hWin.HEURIST4.msg.sendCoverallToBack();
        	this._as_dialog.dialog('close');
        }

        var id_map = JSON.parse(sessionStorage.getItem('id_map'));

        var category_ID = $('#category_field').val();
        var basis_ID = $('#basis_field').val();
        var notes = $('#notes_field').val();
        
        var keywords_ID;
        
        this._context_on_close = {};
        
        // assign values to be sent back to edit form - format is similar to this.options.edit_fields
        if (sessionStorage.getItem("rec_kywd") != null)   /* Check if currently editing work has keywords */
        {
            keywords_ID = JSON.parse(sessionStorage.getItem("rec_kywd"));

            this._context_on_close[id_map.DT_Keywords] = keywords_ID; 
            this._context_on_close[id_map.DT_Category] = category_ID;
            this._context_on_close[id_map.DT_Basis] = basis_ID;
            this._context_on_close[id_map.DT_Notes] = notes;
        }
        else
        {
            this._context_on_close[id_map.DT_Category] = category_ID;
            this._context_on_close[id_map.DT_Basis] = basis_ID;
            this._context_on_close[id_map.DT_Notes] = notes;
        }

        var works = [];

        if (sessionStorage.getItem("prev_classify") != null)
        {
	        works = JSON.parse(sessionStorage.getItem("prev_classify"));
	    }

        if ( (works.find(e => e == document.getElementById('work-code_field').innerText) == null ) && 
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
        
        window.hWin.HEURIST4.msg.sendCoverallToBack();	// use this function to hide cover-all/loading

        this._as_dialog.dialog('close');
    },

    /** Keyword Assignment **/

	/*
        Creates a Heurist popup capable of searching all Project Keywords,
        When an keyword is selected, it is assigned to the work, if it is not already

        Param: None

        Return: VOID
     */
    keywordLookup: function(){

    	if (sessionStorage.getItem('id_map') == null)
        {
        	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        	return;
        }

        var id_map = JSON.parse(sessionStorage.getItem('id_map'));

        var popup_options = {
            select_mode: (false)?'select_multi':'select_single',    // enable multi select or not, current set to single select
            select_return_mode: 'recordset', //or 'ids' (default)
            edit_mode: 'popup',
            selectOnSave: false, // true = select popup will be closed after add/edit is completed
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
                    var keyword_IDs = JSON.parse(sessionStorage.getItem("rec_kywd"));

                    if (keyword_IDs != null)
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
		Search for Editions of the Work within Heurist, displaying the results

		Param: None

		Return: VOID
     */
     lookupEditions: function(){
     	if (sessionStorage.getItem('id_map') == null)
        {
        	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        	return;
        }

        var id_map = JSON.parse(sessionStorage.getItem('id_map'));

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
		Retrieve the list of checked keywords that need to be assigned

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
	},

	/*
		Retrieve the list of checked keywords that need to be assigned

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
    Searches for works that have the retrieved list of keyword
    Note: Uses the keyword's record ID

    Param: id -> Keyword's Title Mask (or, the Keyword's ID)

    Return: VOID
 */
function setupAssocKeywords(id) {

	if (sessionStorage.getItem('id_map') == null)
        {
        	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        	return;
        }

    var id_map = JSON.parse(sessionStorage.getItem('id_map'));

	sessionStorage.removeItem('assoc_keywords');

    var query_request = {q:'t:' + id_map.RT_Work + ' linkedto:'+ id, detail:'detail'};

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function(response){
            if (response.status == window.hWin.ResponseStatus.OK)
            {
                var recordset = new hRecordSet(response.data);
                var records = recordset.getRecords();

                var ids = [];

                var len = Object.keys(records).length;

                sessionStorage.setItem('assoc_count', len);

                for (i in records)
                {

                    var record = records[i];

                    if (record[4] != id_map.RT_Work) { continue; }

                    var details = record.d;

                    if ((details[id_map.DT_Keywords] == null) || (details[id_map.DT_Keywords].length < 2))
                    {
                        continue;
                    }

                    ids.push(details[id_map.DT_Keywords]);

                }

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

	if (sessionStorage.getItem('id_map') == null)
    {
    	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
    	return;
    }

    var id_map = JSON.parse(sessionStorage.getItem('id_map'));

    var query_request = {q:'t:' + id_map.RT_Keyword, detail:'detail'};

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
        	if(response.status == window.hWin.ResponseStatus.OK)
        	{
	        	var keywords = [];

	        	if (sessionStorage.getItem("assoc_keywords") != null)
	        	{
	        		keywords = JSON.parse(sessionStorage.getItem("assoc_keywords"));
	        	}

				var recordset = new hRecordSet(response.data);

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

						isAssocKeyword(keywords, ids[i][j], title);						
					}
				}				
        	}
        	else
        	{
        		window.hWin.HEURIST4.msg.showMsgErr(response);
        	}
        	sessionStorage.setItem("assoc_keywords", JSON.stringify(keywords));
            updateAssocDisplay();
        }
    );
}

/*
    Update the Associated Keywords HTML List

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

	var keywords = JSON.parse(sessionStorage.getItem("assoc_keywords"));

	if (sessionStorage.getItem("rec_kywd") == null)
	{
		return;
	}

	var assigned = JSON.parse(sessionStorage.getItem("rec_kywd"));

	keywords.sort(compareIndexes);

	/* Empty List, before use */
	if (list.getElementsByTagName('li').length != 0)
	{
		list.innerHTML = '';
	}
	
	var count = sessionStorage.getItem("assoc_count");

	/* Now add items into the HTML list */
	for (var i = 0; list.getElementsByTagName('li').length < max && keywords.length > i; i++)
	{
	    var item = document.createElement('li');

	    if (keywords[i] == null) 
    	{ 
    		console.log(i);
    		break; 
    	}

		if (assigned.find(e => e == keywords[i][0]))
		{
			continue;
		}
		else
		{
			var percentage = (Math.round((keywords[i][1] + Number.EPSILON) * 100) / count).toFixed(2);

			item.innerHTML = "<input type='checkbox' value='" + keywords[i][0] + "' name='" + keywords[i][0] + "'><label for='" + keywords[i][0] + "'> " 
								+ keywords[i][2] + " </label>&nbsp;&nbsp;&nbsp;&nbsp;<label> ~ " + keywords[i][1] + " - " + percentage + "% </label>";

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
	Sets up the list of works and calls startup function for Recent Keywords

	Param: None

	Return: VOID
 */
async function setupRecentWorks(){

	if (sessionStorage.getItem('id_map') == null)
        {
        	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        	return;
        }

    var id_map = JSON.parse(sessionStorage.getItem('id_map'));	
    
    var works = [];

    if (sessionStorage.getItem("prev_classify") != null)    /* Check if Previous Keywords Table can be Initialised */
    {  	
        works = JSON.parse(sessionStorage.getItem("prev_classify"));

        var ch_set;

        if (works[0] != document.getElementById('work-code_field').innerText)
        {
        	ch_set = works[0];
        }
        else if (works.length != 1)
        {
        	ch_set = works[1];
        }

    	for (var i = 0; i < works.length; i++)
    	{
			if (works[i] == document.getElementById('work-code_field').innerText) { continue; }

			if (ch_set == works[i])
			{
				startRecentWork(works[i], 1);
			}
			else
			{
				startRecentWork(works[i], 0);
			}

			await sleep(50);
		}
    }

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
	Retrieves the recent works, and checks if they have keywords to add

	Param: work_code -> Current Work 

	Return: VOID
 */
function startRecentWork(id, set) {

	if (sessionStorage.getItem('id_map') == null)
        {
        	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
        	return;
        }

    var id_map = JSON.parse(sessionStorage.getItem('id_map'));	
	
	if (set == null || id == null)
	{
		msgToConsole('startRecentWork() Error: No Recent Works Saved', null);
		return;
	}

    var query_request = {q:'t:' + id_map.RT_Work + ' f:' + id_map.DT_MPCEId + ':"' + id + '"', detail:'detail'};  /* Retrieve the record for supplied work code*/

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
                	getRecentKeywords(details[id_map.DT_Keywords], set);
                }

            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }
    );
}

/*
    Retrieve the Recent Work's keywords for display

    Param: keywords_ID -> Array of Keyword IDs

    Return: VOID
 */
function getRecentKeywords(keywords_ID, set) {
	if (sessionStorage.getItem('id_map') == null)
    {
    	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
    	return;
    }

    var id_map = JSON.parse(sessionStorage.getItem('id_map'));

    var query_request = {q:'t:' + id_map.RT_Keyword, detail:'detail'};

    window.hWin.HAPI4.RecordMgr.search(query_request,
        function( response ){
            if(response.status == window.hWin.ResponseStatus.OK){

                var recordset = new hRecordSet(response.data);
                var table_kywrds = document.getElementById('keyword_field');
                var list_recent = document.getElementById('prev_field');

                var row_cnt = keywords_ID.length;

                for (var j = 0; j < row_cnt; j++)
                {

                    var record = recordset.getRecord(keywords_ID[j]);
                    var details = record.d;

                    var title = details[1];
                    var id = record.rec_ID;

					var setcb = 0;

                    var found = findTableRow(table_kywrds, id);

                    if (found < 0)
                    {
                        found = findListItem(list_recent, id);

                        if (found < 0)
                        {
                            addRecentKeywords(id, title[0], set);
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
    Add keyword to Recent Keyword List

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
		item.innerHTML = "<input type='checkbox' value='" + id + "' name='" + id + "' checked><label for='" + id + "'> " + title + " </label>";
	}
	else
	{
	    item.innerHTML = "<input type='checkbox' value='" + id + "' name='" + id + "'><label for='" + id + "'> " + title + " </label>";
	}

    list.appendChild(item);
	return;
}

/*
	Check if provided keyword was a recent keyword

	Param: id -> Keyword's ID

	Return: VOID
 */
function isRecentKeyword(id){
	if (sessionStorage.getItem('id_map') == null)
    {
    	window.hWin.HEURIST4.msg.showMsgErr("Error: Cannot Retrieve ID Map, please contact the administrator about this issue");
    	return;
    }

    var id_map = JSON.parse(sessionStorage.getItem('id_map'));

	if (sessionStorage.getItem('prev_classify') == null)
	{
		return;
	}

	var prev_works = JSON.parse(sessionStorage.getItem('prev_classify'));    

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
    Add New Assigned Keyword to Table

    Param: 
        keyword -> Keyword's Title
        id -> Keyword's Record ID

    Return: VOID
 */
function showKeyword(keyword, id){
    var table = document.getElementById('keyword_field');

    var row = table.insertRow(-1);

    var col1 = row.insertCell(0);
    var col2 = row.insertCell(1);

    col1.style.width = "70%";
    col2.style.width = "30%";
    col2.classList.add('center');

    col1.innerHTML = "<label class='name'>"+ keyword +"</label>";
    col2.innerHTML = "<button value='"+ id +"' onclick='removeKeyword(this.value)' class='btn btn-delete' style='float: left;'>X</button>" +
    "<button value='"+ id +"' onclick='setupAssocKeywords(this.value)' class='btn btn-info' style='float: right;'>Associated &rArr;</button>";
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

    var keywords_ID = [];

    if (sessionStorage.getItem("rec_kywd") != null)
    {
        keywords_ID = JSON.parse(sessionStorage.getItem("rec_kywd"));
    }


    keywords_ID.push(id);   /* Add to List */

    sessionStorage.setItem("rec_kywd", JSON.stringify(keywords_ID));
}

/*
    Remove selected keyword from Assigned Keyword Table and List

    Param: id -> Selected Keyword's Record ID

    Return: VOID
 */
function removeKeyword(id) {
    var table = document.getElementById('keyword_field');
    
    var table_in = findTableRow(table, id);

    if (table_in == -1)
    {
        msgToConsole('removeKeyword() Error: Keyword was not found', null);
        window.hWin.HEURIST4.msg.showMsgErr('Keyword was not found in Assigned Keyword Table');
        return;
    }

    table.deleteRow(table_in);  /* Remove from Table */

    var keywords_ID = JSON.parse(sessionStorage.getItem("rec_kywd"));
    keywords_ID.splice(table_in, 1);  /* Remove from List */

    sessionStorage.setItem("rec_kywd", JSON.stringify(keywords_ID));
    
    isRecentKeyword(id);	/* Check if keyword was a recent keyword */
    updateAssocDisplay();	/* Check if keyword was a associated keyword */
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
        console.log('searchList() Error: No table Was Provided');
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
    Search a table for the row of the supplied value,
    Note: Checks the first found button's value within each row

    Param:
        table -> table to be searched
        value -> value searching for (button value)

    Return: Number -> Row Index of the found value
 */

function findTableRow(table, value) {
    var index = -1;

    if (table == null)
    {
        console.log('findTableRow() Error: No table Was Provided');
        return -2;
    }
    else if (value == null)
    {
        console.log('findTableRow() Error: No value Was Provided');
        return -2;
    }

    var len = table.rows.length;

    for (var i = 0, row; i < len; i++)
    {
        var row = table.rows[i];

        if (row == null)
        {
            // Return Error/None Found
            console.log('findTableRow() Error: No Rows Found');
            return -2;
        }
        else if (row.cells[1] == null)
        {
            continue;
        }

        var elements = row.getElementsByTagName('button')[0];

        if (elements != null)
        {
            if (elements.value == value)
            {
                index = i;
                return index;
            }
        }
    }  

    return index;
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
        console.log('findListItem() Error: No table Was Provided');
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
    }  

    return index;
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

    sessionStorage.removeItem("recent_work");

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
 	Mapping Ids for Detail and Record Types (Heurist Specific), these need to be set for the specific database being used

 	Param: None

 	Return: VOID
 */
function mapIds(mapping) {

	var mapping_ids = {
	    /* Record Type, Detail Type and Vocab Id Map, for now you need to replace all instances of each value with the database correct one */

	    RT_Editions: 54,							/* Editions Table */	    
	    RT_Work: 55,								/* Super Book (Works) Table */
	    RT_Keyword: 56,								/* Project Keywords Table */
	    
	    DT_Title: 940,								/* Work Title Details Index, from outside the Super Book (Work) Table */
	    DT_MPCEId: (mapping['workID'] != null) ? mapping['workID'] : 970,						/* MPCE ID Details Index */
	    DT_Category: (mapping['parisianClassify'] != null) ? mapping['parisianClassify'] : 972,	/* Parisian Category Details Index */
	    DT_Keywords: (mapping['projectKywds'] != null) ? mapping['projectKywds'] : 973,			/* Project Keywords Details Index */
	    DT_Basis: (mapping['basisClassify'] != null) ? mapping['basisClassify'] : 974,			/* Basis for Classifcation Details Index */
	    DT_Notes: (mapping['classifyNotes'] != null) ? mapping['classifyNotes'] : 975,			/* Classification Notes Details Index */

	    VI_Category: 6953,							/* Parisian Category Vocab ID */
	    VI_Basis: 6936,								/* Basis for Classification Vocab ID */
	};

	sessionStorage.setItem('id_map', JSON.stringify(mapping_ids));
}