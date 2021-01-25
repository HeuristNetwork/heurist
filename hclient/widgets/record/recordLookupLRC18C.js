/**
 * recordLookupLRC18C.js
 *
 *  1) Loads html content from recordLookupLRC18C.html
 *  2) Imports data from ESTC_Helsinki_Bibliographic_Metadata to Libraries_Readers_Culture_18C_Atlantic
 *  3) User will input lookup query details, a result list is rendered to user, a single item from results is selected
 *  4) Once the user clicks 'Check Author', this script imports Author, Work  and Book(Edition) record data to this DB
 *  5) The imported edition is mapped onto the Edition in Holding record(where lookup button is available to the user)
 *
 *
 * @package     Heurist academic knowledge management system
 * @link        http://HeuristNetwork.org
 * @copyright   (C) 2005-2020 University of Sydney
 * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
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

mapDict = {}
$.widget("heurist.recordLookupLRC18C", $.heurist.recordAction, {

    options: {
        height: 520,
        width: 800,
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
        title: 'Lookup values for Heurist record',
        htmlContent: 'recordLookupLRC18C.html',
        helpContent: null, //help file in context_help folder

    },
    recordList: null,

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
        this._on(this.element.find('input'), {
            'keypress': this.startSearchOnEnterPress
        });
        //Populate Bookformat dropdown on lookup page
/*
        var selBf = window.hWin.HEURIST4.ui.createTermSelectExt2(this.element.find('#select_bf')[0],
            {
                datatype: 'enum',
                termIDTree: 6891,
                headerTermIDsList: null,  //array of disabled terms
                defaultTermID: null,   //default/selected term
                topOptions: [{key: 0, title: 'Select Book Format'}],      //top options  [{key:0, title:'...select me...'},....]
                useHtmlSelect: false
            }
        );
*/
        var request = {db:'ESTC_Helsinki_Bibliographic_Metadata', a:'search', 'entity':'defTerms', 
                    'details':'list', 'trm_ParentTermID':5430};

        var selBf = this.element.find('#select_bf').empty();
        window.hWin.HEURIST4.ui.addoption(selBf[0], 0, 'select...'); //first option

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    var recordset = new hRecordSet(response.data);
                    
                    recordset.each2(function(trm_ID, term){
                         window.hWin.HEURIST4.ui.addoption(selBf[0], trm_ID, term['trm_Label']);
                    });
                }
        });

        /* artem remarked it
        var selBf = window.hWin.HEURIST4.ui.createTermSelect(this.element.find('#select_bf')[0],
            {
                vocab_id: 6891,
                defaultTermID: null,   //default/selected term
                topOptions: [{key: 0, title: 'Select Book Format'}],      //top options  [{key:0, title:'...select me...'},....]
                useHtmlSelect: false
            }
        );
        */
        
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
        var recIcon = window.hWin.HAPI4.iconBaseURL + 1 + '.png';
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
            + window.hWin.HAPI4.iconBaseURL + 'thumb/th_' + 1 + '.png&quot;);"></div>';
        fld('properties.edition', 15)

        var html = '<div class="recordDiv" id="rd' + record[2] + '" recid="' + record[2] + '" rectype="' + 1 + '">'
            + html_thumb
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

    startSearchOnEnterPress: function (e) {
        var code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }
    },

    /* Parse the output XML for a user selected Edition */
    /* Build estc_edition, author_dict, work_dict and ids_n_title of edition dict */
    _XMLParse: function (editionXML) {// Concept IDS for editions incoming from ESTC database
        // placeConceptID = "3-1009";
        placeConceptID = $Db.getConceptID('rty', 12)
        // authorConceptID = "2-10";
        authorConceptID = $Db.getConceptID('rty', 10)


        // Use 1322 on Intersect QA server as database registered ID for ESTC database is 1322 as opposed to 1321 on
        // Sydney Heurist Server
        workConceptID = '1321'+'-'+'49'
        editionConceptID = "3-102";

        estc_edition_dict = {};
        author_dict = {};
        place_dict = {};
        work_dict = {};
        ids_n_title = {};

        edition_xml_record = "";
        place_xml_record = "";
        author_xml_record = "";
        work_xml_record = "";
        valid_records = false;

        console.log("In XML Parse function and the Edition XML is: ")
        console.log(editionXML)
        records = editionXML.getElementsByTagName('records')[0].children
        resultCount = editionXML.getElementsByTagName('resultCount')[0].innerHtml
        ids_n_title['resultCount'] = resultCount;

        // Identify which record from the XML belong to Edition, Author, Place and Work;
        for (i = 0; i < records.length; i++) {
            for (j = 0; j < records[i].children.length; j++) {
                nodeName = records[i].children[j].nodeName
                if (nodeName == 'type' && records[i].children[j].getAttribute('conceptID') == editionConceptID) {
                    edition_xml_record = i;
                }
                if (nodeName == 'type' && records[i].children[j].getAttribute('conceptID') == placeConceptID) {
                    place_xml_record = i;
                }
                if (nodeName == 'type' && records[i].children[j].getAttribute('conceptID') == authorConceptID) {
                    author_xml_record = i;
                }
                if (nodeName == 'type' && records[i].children[j].getAttribute('conceptID') == workConceptID) {
                    work_xml_record = i;
                }
            }
        }

        //Generic function to build Edition, Author, Place and Work XML
        function buildDict(i, j, records, dict_name) {
            if (records[i].children[j].nodeName == 'detail') {
                dict_name[records[i].children[j].getAttribute('id')] = records[i].children[j].innerHTML
            }
        }

        for (i = 0; i < records.length; i++) {
            for (j = 0; j < records[i].children.length; j++) {
                if (i == edition_xml_record) {
                    if (records[i].children[j].nodeName == 'id') {
                        ids_n_title['editionID'] = records[i].children[j].innerHTML
                    }
                    if (records[i].children[j].nodeName == 'title') {
                        ids_n_title['editionTitle'] = records[i].children[j].innerHTML
                    }
                    buildDict(i, j, records, estc_edition_dict)

                } else if (i == author_xml_record) {
                    if (records[i].children[j].nodeName == 'id') {
                        ids_n_title['authorID'] = records[i].children[j].innerHTML
                    }
                    if (records[i].children[j].nodeName == 'title') {
                        ids_n_title['authorTitle'] = records[i].children[j].innerHTML
                    }
                    buildDict(i, j, records, author_dict)

                } else if (i == place_xml_record) {
                    if (records[i].children[j].nodeName == 'id') {
                        ids_n_title['placeID'] = records[i].children[j].innerHTML
                    }
                    if (records[i].children[j].nodeName == 'title') {
                        ids_n_title['placeTitle'] = records[i].children[j].innerHTML
                    }
                    buildDict(i, j, records, place_dict)
                } else if (i == work_xml_record) {
                    if (records[i].children[j].nodeName == 'id') {
                        ids_n_title['workID'] = records[i].children[j].innerHTML
                    }
                    if (records[i].children[j].nodeName == 'title') {
                        ids_n_title['workTitle'] = records[i].children[j].innerHTML
                    }
                    buildDict(i, j, records, work_dict)
                }
            }
        }

        // To remove after user testing. Or leave it as it for debugging
        console.log(estc_edition_dict)
        console.log(author_dict)
        console.log(place_dict)
        console.log(work_dict)
        return {
            "editionDict": estc_edition_dict,
            "authorDict": author_dict,
            "placeDict": place_dict,
            "workDict": work_dict,
            "idsNTitleDict": ids_n_title
        }
    },

    _showEditionFetchFailMsg: function (url) {
        msg = "ESTC database did not return any results. Are you logged into the ESTC database on a different window?" +
            "The XML used for the lookup is: <br><br>" + url
        window.hWin.HEURIST4.msg.showMsgErr(msg);
        window.hWin.HEURIST4.msg.sendCoverallToBack();
    },

    /* Show a confirmation window after user selects a record from the lookup query results */
    /* If the user clicks "Check Author", then call method _checkAuthor*/
    doAction: function () {
        var that = this;
        estcRecordListWindow = this;

        estc_edition_id = that.element.context.querySelector(".selected_last").getAttribute('recid');



        mapToHoldingRecord = that.options.mapping.fields['properties.edition']
        mapDict[mapToHoldingRecord] = ""

        var $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
            'Selected Edition does not exist in Libraries database. Importing now...',
            {
                'Proceed': function () {
                    $__dlg.dialog("close");
                    window.hWin.HEURIST4.msg.bringCoverallToFront(that._as_dialog.parent());
                    that._checkAuthor(estc_edition_id, estcRecordListWindow)
                    // checkIfAuthorExistsInLibraries(estc_edition_id, estcRecordListWindow);
                },
                'Cancel': function () {
                    $__dlg.dialog("close");
                }
            });
    },

    /* Get Term ID of a Vocabulary item that exists in the Libraries database*/
    _getTermThatExists: function(type, term) {
        that = this;
        termID = "";
        keysDict = {"prefix": 7124, "suffix": 7128, "agentType": 6901, "bookFormat": 6891, "religion": 6907};
        
        //get all terms for given vocabulary
        //var current_terms = window.hWin.HEURIST4.ui.getPlainTermsList('enum', keysDict[type]);
        
        var current_terms = $Db.trm_TreeData(keysDict[type], 'select');
        
        term = String(term).toLowerCase();
        
        for (i = 0; i < current_terms.length; i++) {
            if (current_terms[i].title.toLowerCase() == term) {
                termID = current_terms[i].key
                return current_terms[i].key
            }
        }
        return null;
    },

    /* Get query URL*/
    /* Return query URL for libraries or ESTC */
    _getQueryURL: function (query_string, database) {
        if (database == "libraries") {
            url = window.hWin.HAPI4.baseURL + '/export/xml/flathml.php?q=' + query_string + '&a=1&db=Libraries_Readers_Culture_18C_Atlantic&depth=all&linkmode=direct_links&rev=no';
        } else if (database == "ESTC") {
            url = window.hWin.HAPI4.baseURL + '/export/xml/flathml.php?q=' + query_string + '&a=1&db=ESTC_Helsinki_Bibliographic_Metadata&depth=all&linkmode=direct_links&rev=no';
        }
        return url;
    },

    /* Add Vocab Term if it does not exist in the Libraries database */
    /* Check if the term already exists in the Libraries Vocabulary list, if yes return its Term ID */
    /* If not create a term and call method postAddingTerm */
    _addTermThatDoesNotExist: function (type, term, id, rec_data) {
        that = this;
        var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php";
        var oTerms = {
            terms: {
                colNames: ['trm_Label', 'trm_InverseTermId', 'trm_Description', 'trm_Domain', 'trm_ParentTermID', 'trm_Status', 'trm_Code', 'trm_SemanticReferenceURL'],
                defs: {},
            }
        };
        // Key of the dict 'oTerms.terms.defs['0-2626']' needs to be a negative number other than -1.
        oTerms.terms.defs['0-2626'] = [term, 0, "Imported from ESTC database", "enum", keysDict[type], "open", "", ""];

        var request = {
            method: 'saveTerms',
            db: window.hWin.HAPI4.database,
            data: oTerms
        };

        term_id = that._getTermThatExists(type, term);

        if (term_id == null || term_id == "") {
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function (response) {
                // update Author Terms list
                term_id = response.data.result[0]
                postAddingTerm(type, term_id, id, rec_data, that);
            });
        } else {
            postAddingTerm(type, term_id, id, rec_data, that);
        }
    },

    /* Check if Edition exists in Libraries database */
    _checkIfEditionExistsInLibraries: function (estc_edition_id) {
        var that = this;
        query_string = 'ids%3A' + estc_edition_id;
        urlToCheckEditioninLibraries = that._getQueryURL(query_string, "libraries")

        return $.ajax({
            url: urlToCheckEditioninLibraries,
            dataType: 'text',
            async: false,
            error: function (error) {
                window.hWin.HEURIST4.msg.showMsgErr(error);
            },
            success: function (response) {
                //Returning Ajax success
            }
        });
    },

    /* Create Author Record if it does not exist in the Libraries database */
    /* After creating Author record, create religion record*/
    /* Once Author is created, create religion record. Author is parent record for a religion record */
    _createAuthorRecord: function (parsedXML, estcRecordListWindow) {
        that = this;
        author_details = {};
        author_details = parsedXML['authorDict'];

        //Todo: Talk to Artem about getting these IDS by quering ESTC database if possible
        authorDict = {
            "1": "", "18": "", "248": "", "249": "", "250": "", "252": "", "253": "", "278": "", "279": "", "280": "",
            "281": "", "282": "", "283": "", "286": "", "287": "", "288": ""
        };

        try{
            if(author_details[10].length > 5){
                authorBirthDate = jQuery.parseHTML(String(author_details[10]))
                authorDict[10] =authorBirthDate[3].innerHTML
            }
        }catch(e){
            authorDict[10] = ''
        }
        try{
            if(author_details[11].length > 5){
                authorDeathDate = jQuery.parseHTML(String(author_details[11]))
                authorDict[11] =authorDeathDate[3].innerHTML
            }
        }catch(e){
            authorDict[11] = ''
        }

        // If Author does not exist in ESTC database, proceed to check if Work exists in Libraries
        if (author_details == {}) {
            author_id = "";
            this._checkWorkRecordInLibraries(parsedXML, author_id, estcRecordListWindow)
            // return checkIfWorkExistsInLibraries(parsedXML, author_id, estcRecordListWindow)
        }

        //Author Record field ids
        ids = ["1", "18", "248", "249", "250", "252", "253", "278", "279", "280", "289", "281", "282", "283", "286",
            "287", "288", "300"];

        authorDict[249] = "";
        // check if author details[item] exists or not
        // if it does not exist then set author_details[item] == ""
        // For IDS 249, 279, 278, 288 get their termID if they exist in Libraries database
        function buildAuthorDict(item, index) {
            try {
                x = author_details[item]
                if (item == 249) {
                    prefix_term = author_details[249];
                    if(prefix_term != "") {
                        prefixTermId = that._getTermThatExists("prefix", prefix_term)
                        if (prefixTermId != null && prefixTermId != undefined) {
                            authorDict['249'] = prefixTermId
                        }
                    }
                }

                if (item == '279') {
                    suffix_term = author_details[279]
                    if(suffix_term != "") {
                        suffixTermId = that._getTermThatExists("suffix", suffix_term)
                        if(suffixTermId != null && suffixTermId!=undefined){
                            authorDict['279'] = suffixTermId
                        }
                    }
                }
                if (item == '278') {
                    agent_term = author_details[278]
                    if(agent_term !="") {
                        agentTermId = that._getTermThatExists("agentType", agent_term)
                        if(agentTermId != null && agentTermId !=undefined) {
                            authorDict['278'] = agentTermId
                        }
                    }
                }
                if (item == '288') {
                    religion_term = author_details[288]
                    religionTermId = that._getTermThatExists("religion", religion_term)
                    authorDict['288'] = religionTermId
                }

            } catch (err) {
                author_details[item] = "";
            }
        }

        ids.forEach(buildAuthorDict);
        var author_rec_data = {
            ID: 0, RecTypeID: 10,
            no_validation: true, //allows save without filled required field 1061
            details: {
                //Standarised Name
                1: author_details[250],
                //Given Name
                18: author_details[18],
                //Designation
                999: author_details[248],
                //Family Name
                1046: author_details[1],
                //ESTC Actor ID
                1098: author_details[252],
                //ESTC Name Unified
                1086: author_details[253],
                //Also Known As
                132: author_details[287],
                //Birth Date
                10: authorDict['10'],
                //Death Date
                11: authorDict['11'],
                // Prefix
                1049: authorDict['249'],
                // Suffix to Name
                1050: authorDict['279'],
                // Agent Type
                1000: authorDict['278']

            }
        }

        window.hWin.HAPI4.RecordMgr.saveRecord(author_rec_data,
            function (response) {
                if (response.status == window.hWin.ResponseStatus.OK) {
                    //response.data it returns new record id only
                    author_id = response.data;
                    religion_id = ""

                    // Add prefix term and get its term ID
                    if (prefixTermId == null && author_details['249'] != "" && author_details['249'] != undefined) {
                        that._addTermThatDoesNotExist("prefix", author_details[249], author_id, author_rec_data)
                    }
                    // Add suffix term and get its term ID
                    if (suffixTermId == null && author_details[279] != "" && author_details[279]!= undefined) {
                        that._addTermThatDoesNotExist("suffix", author_details[279], author_id, author_rec_data)
                    }
                    // Add Agent Type term and get its term ID
                    if (agentTermId== null && author_details[278] != "" && author_details[278] != undefined) {
                            that._addTermThatDoesNotExist("agentType", author_details[278], author_id, author_rec_data)
                    }

                    // Add religion term and get its term ID
                    // if (religionTermId != "") {
                    //         that._addTermThatDoesNotExist("religion", author_details[288], author_id, author_rec_data)
                    //     }
                    //  else {
                    //     createReligionRecord(authorDict[288], author_id)
                    // }
                    if(religionTermId== null && author_details[288] != "" && author_details[288] != undefined){
                        that._addTermThatDoesNotExist("religion", author_details[288], author_id, author_rec_data)
                    }
                    if(author_details[288] != "" && author_details[288] != undefined){
                        createReligionRecord(authorDict[288], author_id)
                    }

                    window.hWin.HEURIST4.msg.showMsgDlg('You\'ve added the Author. Checking if the work exists. ' + response.data);
                    that._checkWorkRecordInLibraries(parsedXML, author_id, estcRecordListWindow)

                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });

    },

    /* Create work record */
    /* If the work does not exist in the parsed Edition XML then call method _createEditionRecord */
    /* Else create work record in the Libraries database */
    _createWorkRecord: function (parsedXML, authorID, estcRecordListWindow) {
        that = this;
        work_ids = ["1", "271", "272", "273", "276"];
        buildWorkDict = {"1": "", "271": "", "272": "", "273": "", "276": ""}
        workDictKeys = Object.keys(parsedXML['workDict'])

        if (workDictKeys == {}) {
            workID = "";
            that._createEditionRecord(parsedXML, authorID, workID, estcRecordListWindow)
        }

        // For each element in workDictKeys
        // For each element in work_ids
        // If both the above element matches then buildWorkDict
        for (i = 0; i < workDictKeys.length; i++) {
            function checkIdInEditionWorkDetails(value, index, array) {
                if (workDictKeys[i] == value) {
                    buildWorkDict[value] = parsedXML['workDict'][value];
                }
            }

            work_ids.forEach(checkIdInEditionWorkDetails)
        }

        var work_record_data = {
            ID: 0, RecTypeID: 56,
            no_validation: false, //allows save without filled required field 1061
            details: {
                //Title
                1: buildWorkDict[1],
                //Full/Extended title
                1091: buildWorkDict[276],
                //Project Record ID
                1092: buildWorkDict[271],
                //Helsinki Work Name
                1093: buildWorkDict[273],
            }
        };

        //Save new Work Record
        window.hWin.HAPI4.RecordMgr.saveRecord(work_record_data,
            function (response) {
                if (response.status == window.hWin.ResponseStatus.OK) {
                    workID = response.data;
                    window.hWin.HEURIST4.msg.showMsgDlg('Work has been added');
                    that._createEditionRecord(parsedXML, authorID, workID, estcRecordListWindow)
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
    },

    /* Create Edition Record in the Libraries database */
    /* Check if Edition already exists in the Libraries database, if it exists assign it to Edition in base Holding
    *  record */
    /* Check if Places record exists in the Libraries database, if yes, map it to the Edition */
    _createEditionRecord: function (parsedXML, authorID, workID, estcRecordListWindow) {
        that = this;
        holdingKey = Object.keys(mapDict)[0];
        buildEditionDict = {}

        if (parsedXML['editionDict']['254'] && parsedXML['editionDict'][254] != "") {
            edition_title = parsedXML['editionDict'][1];
            edition_estc_id=parsedXML['editionDict'][254]
        } else {
            edition_title = parsedXML['idsNTitleDict']['editionTitle']
            edition_estc_id=''
        }

        query_string = 't:55 f:1094="' + edition_estc_id + '"'
        urlToCheckEditioninLibraries = that._getQueryURL(query_string, "libraries")
        edition_id = parsedXML['idsNTitleDict']['editionID'];

        $.ajax({
            url: urlToCheckEditioninLibraries,
            dataType: 'text',
            async: false,
            error: function (error) {
                window.hWin.HEURIST4.msg.showMsgErr(error);
            },
            success: function (response) {
                editionDetailsXML = $.parseXML(String(response));

                if (editionDetailsXML.getElementsByTagName('resultCount')[0].innerHTML.trim() == "0") {

                    edition_ids = ["1", "9", "15", "137", "254", "256", "257", "258", "259", "268", "270", "275","277", "284", "285","289" , "290", "955"];
                    if (parsedXML['placeDict'] == {}) {
                        buildEditionDict["268"] = "";
                    } else {
                        placeID = parsedXML['placeDict']['268']
                        query_string = 't:12 f:1090:"' + placeID + '"'
                        urlToCheckPlaceinLibraries = that._getQueryURL(query_string, "libraries")


                        $.ajax({
                            url: urlToCheckPlaceinLibraries,
                            dataType: 'text',
                            async: false,
                            error: function (error) {
                                window.hWin.HEURIST4.msg.showMsgErr(error);
                            },
                            success: function (response) {
                                placeDetailsXML = $.parseXML(String(response));
                                if (placeDetailsXML.getElementsByTagName('resultCount')[0].innerHTML.trim() == "0") {
                                    editionDict["268"] = ""
                                } else {
                                    editionDict["268"] = placeDetailsXML.getElementsByTagName('id')[0].innerHTML
                                }
                            }
                        });

                    }

                    editionDictKeys = Object.keys(parsedXML['editionDict']);

                    checkBookFormatTerm = false;
                    bookFormatTermID = ""
                    // For each element in EditionDictKey
                    // For each value in edition_ids
                    // If EditionDictKey[element] == each value in editionKey array
                    // Then build BuildEditionDict
                    // If value=256, then get Bookformat Term if it exists in Libraries database
                    for (i = 0; i < editionDictKeys.length; i++) {
                        function checkIdInEditionDetails(value, index, array) {
                            if (editionDictKeys[i] == value) {
                                if (value != 256) {
                                    buildEditionDict[value] = parsedXML['editionDict'][value];
                                } else if (!checkBookFormatTerm && value == '256') {
                                    bf_term = parsedXML['editionDict'][value]
                                    bfTermId = that._getTermThatExists("bookFormat", bf_term)
                                    buildEditionDict[256] = bfTermId

                                    checkBookFormatTerm = true;
                                }
                            }
                        }

                        edition_ids.forEach(checkIdInEditionDetails)
                    }

                    editionPlaceRecords = parsedXML['placeDict']

                    // For each edition_ids
                    // For each element in editionPlaceRecords
                    // Get place_id HTML tag has ID 268
                    for (i = 0; i < editionPlaceRecords.length; i++) {
                        function checkIdInEditionPlaceDetails(value, index, array) {
                            if (editionPlaceRecords[i].getAttribute('id') == '268') {
                                placeID = editionPlaceRecords[i].innerHTML;
                            }
                        }

                        edition_ids.forEach(checkIdInEditionPlaceDetails)
                    }


                    try{
                        if(estc_edition_dict[9].length > 5){
                            yearOf1stVol = jQuery.parseHTML(String(estc_edition_dict['9']))
                            buildEditionDict[9] =yearOf1stVol[3].innerHTML
                        }
                    }catch(e){
                        buildEditionDict[9] = ''
                    }
                    try{
                        if(estc_edition_dict[275].length > 5){
                            yearOf1stVol = jQuery.parseHTML(String(estc_edition_dict['275']))
                            buildEditionDict[275] =yearOf1stVol[3].innerHTML
                        }
                    }catch(e){
                        buildEditionDict[275] = ''
                    }

                    // Build Edition Record dict
                    var edition_rec_data = {
                        ID: 0, RecTypeID: 55,
                        no_validation: false,
                        details: {
                            //Title
                            1: buildEditionDict[1],
                            // Year of First Volume
                            10: buildEditionDict[9],
                            // Year of Final Volume
                            955: buildEditionDict[275],
                            //Book Format
                            991: buildEditionDict[256],
                            // 238: editionDict[259],
                            //Summary Publisher Statement
                            1096: buildEditionDict[285],
                            //ESTC ID
                            1094: buildEditionDict[254],
                            1106: authorID,
                            949: workID,
                            //Place
                            238: buildEditionDict[268],
                            //Extended Edition title
                            1095: buildEditionDict[277],
                            //No of volumes
                            // 962: buildEditionDict[289],
                            962: buildEditionDict[137],
                            // No of parts
                            1107: buildEditionDict[290],
                            // Imprint details
                            652: buildEditionDict[270]
                        }
                    };

                    window.hWin.HAPI4.RecordMgr.saveRecord(edition_rec_data,
                        function (response) {
                            if (response.status == window.hWin.ResponseStatus.OK) {
                                //response.data it returns new record id only
                                window.hWin.HEURIST4.msg.showMsgDlg('Edition has been added');
                                edition_id = response.data;

                                //Add Book Format Term if it does not exists in the Libraries database
                                if (bfTermId != "" || bfTermId == false || bfTermId == null) {
                                    if (!bfTermId) {
                                        that._addTermThatDoesNotExist("bookFormat", bf_term, edition_id, edition_rec_data)
                                    }
                                }

                                // Close the Lookup window and map the edition to base Holding record
                                mapDict[holdingKey] = edition_id
                                that._context_on_close = mapDict;
                                that._as_dialog.dialog('close');
                                // Refresh the base page when a new term is added
                                $("div[aria-describedby|='heurist-dialog-Records']").on('dialogclose', function (event) {
                                    window.location.reload()
                                });
                            } else {
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });

                } else {

                    edition_id = editionDetailsXML.getElementsByTagName('id')[0].innerHTML
                    window.hWin.HEURIST4.msg.showMsgDlg('Edition already exists in the Libraries database.');
                    mapDict[holdingKey] = edition_id
                    // Close the Lookup window and map the edition to base Holding record
                    estcRecordListWindow._context_on_close = mapDict;
                    estcRecordListWindow._as_dialog.dialog('close');

                }

            }
        });
    },

    /* Check if Work record exists in Libraries database */
    /* If Work does not exist in Libraries database, then call method _createWorkRecord */
    /* If Work exists in Libraries database, then call method _createEditionRecordx */
    _checkWorkRecordInLibraries: function (parsedXML, author_id, estcRecordListWindow, mapToHoldingRecord) {
        that = this;
        // work_title = parsedXML['idsNTitleDict']['workTitle']

        try{
            work_proj_id = parsedXML['workDict'][271]
        }
        catch(e){
            work_proj_id='';
        }
        workID = "";
        // query_string = 't:56 f:1:"' + work_title + '"'
        query_string = 't:56 f:1092:"' + work_proj_id + '"'
        urlToCheckWorkinLibraries = that._getQueryURL(query_string, "libraries")
        $.ajax({
            url: urlToCheckWorkinLibraries,
            async: false,
            dataType: 'text',

            error: function (error) {
                window.hWin.HEURIST4.msg.showMsgErr('Query '+query_string+' produced and error output. Server responds '+error);
            },
            success: function (response) {
                workDetailsXML = $.parseXML(String(response));
                if (workDetailsXML.getElementsByTagName('resultCount')[0].innerHTML.trim() == "0") {
                    window.hWin.HEURIST4.msg.showMsgDlg('Author has been identified/imported. Work does not exist, creating work now.');
                    that._createWorkRecord(parsedXML, author_id, estcRecordListWindow, mapToHoldingRecord);
                } else {
                    workID = workDetailsXML.getElementsByTagName('id')[0].innerHTML.trim()
                    window.hWin.HEURIST4.msg.showMsgDlg('Author has been identified/imported. Work for this Edition exists, creating Edition now.');
                    that._createEditionRecord(parsedXML, author_id, workID, estcRecordListWindow, mapToHoldingRecord)
                }
            }
        });

    },

    /* Lookup process begins with this function */
    /* Get Edition ID from the user selected record on query result list*/
    /* First execute a AJAX call get Edition XML and the parse it */
    /* Check if Author exists in parsed XML*/
    /* If Author exists in parsed XML, check if author is present in Libraries or create it*/
    /* After checking/creating author record, then call method _checkWorkRecordInLibraries*/
    _checkAuthor: function (estc_edition_id, estcRecordListWindow) {
        that = this;
        createAuthor = false;
        query_string = 'ids%3A' + estc_edition_id
        urlToGetEditionDetails = that._getQueryURL(query_string, "ESTC")
        console.log("URL TO GET EDITION DETAILS")
        console.log(urlToGetEditionDetails)
        return $.ajax({
            url: urlToGetEditionDetails,
            async: false,
            dataType: 'text',
            error: function (error) {
                console.log(error)
                window.hWin.HEURIST4.msg.showMsgErr(error);
            },
            success: function (editionXML) {
                getEditionDetails = false;
                try {
                    // Parse the output ESTC edition XML
                    editionXML = jQuery.parseXML(String(editionXML));
                    parsedXML = that._XMLParse(editionXML);
                    editionDict = parsedXML['editionDict'];
                    authorDict = parsedXML['authorDict'];

                    // Check if Author details exists in the edition XML
                    if (authorDict == {}) {
                        author_id = "";
                        getEditionDetails = false;
                        // If author is present in Edition XML, then check if Author already exists in Libraries database
                    } else if (parsedXML['idsNTitleDict']['authorTitle']) {
                        // Check if author exists by querying author's full name
                        full_name = author_dict['253']
                        query_string = 't:10 f:1086: "' + full_name + '"';
                        urlToCheckAuthorinLibraries = that._getQueryURL(query_string, "libraries")
                        console.log("URL to check author in libraries")
                        console.log(urlToCheckAuthorinLibraries);
                        getEditionDetails = true;

                    }
                    if (getEditionDetails) {
                        $.ajax({
                            url: urlToCheckAuthorinLibraries,
                            async: false,
                            dataType: 'text',
                            error: function (error) {
                                window.hWin.HEURIST4.msg.showMsgErr(error);
                            },
                            success: function (response) {
                                author_does_not_exist_in_libraries = false;
                                authorDetailsXML = $.parseXML(String(response));
                                if (authorDetailsXML.getElementsByTagName('resultCount')[0].innerHTML.trim() == "0") {
                                    author_does_not_exist_in_libraries = true;
                                }
                                // If author does not exists in Libraries database, then create it
                                if (author_does_not_exist_in_libraries) {
                                    createAuthor = true;
                                    that._createAuthorRecord(parsedXML, estcRecordListWindow);
                                    // If author exists in Libraries database, then call method _checkWorkRecordInLibraries
                                } else {
                                    createAuthor = false;
                                    author_id = authorDetailsXML.getElementsByTagName('id')[0].innerHTML.trim()
                                    that._checkWorkRecordInLibraries(parsedXML, author_id, estcRecordListWindow, mapToHoldingRecord);
                                }
                            }
                        });
                        // If author does not exists for a Edition, then call method _checkWorkRecordInLibraries
                    } else {
                        author_id = "";
                        if (parsedXML) {
                            that._checkWorkRecordInLibraries(parsedXML, author_id, estcRecordListWindow, mapToHoldingRecord);
                        }
                    }
                } catch (err) {
                    console.log(err)
                    console.log("Unable to export XML from Heurist: " + urlToGetEditionDetails)
                    that._showEditionFetchFailMsg(urlToGetEditionDetails)
                    getEditionDetails = false;
                }
            }
        });

    },

    /* Get the user input from recordLookupLRC18C.html and build the query string */
    /* Then lookup ESTC database if the query produces any search results */
    _doSearch: function () {
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
                query['linkedto:284'] = {"t":"","f:272":this.element.find('#edition_work').val()};
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
/*
            selectedBF = this.element.find('#select_bf option:selected').text()
            if (selectedBF != null && selectedBF != '' && selectedBF != "Select Book Format") {
                book_format = 'all: ' + selectedBF
            } else {
                book_format = ""
            }
            query_string = 't:30 ' + edition_name + edition_date + edition_author + edition_work + edition_place + book_format + estc_no + vol_count + vol_parts;
*/
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


        console.log("Query String is")
        console.log(query_string);


        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        var query_request = {db: 'ESTC_Helsinki_Bibliographic_Metadata', q: query_string};
        var that = this;
        query_request['detail'] = 'details';
        window.hWin.HAPI4.RecordMgr.search(query_request,
            function (response) {
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                if (response.status && response.status != window.hWin.ResponseStatus.OK) {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                } else {
                    that._onSearchResult(response);
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

/* Executed after adding a vocabulary term on the Libraries databaes */

/* Vocabulary list takes some time get added, therefore update the Author/Edition record after a Term has been added*/
function postAddingTerm(type, term_id, id, rec_data, that) {
    keysDict = {"prefix": 7124, "suffix": 7128, "agentType": 6901, "bookFormat": 6891, "religion": 6907}
    if (type == "prefix") {
        rec_data['ID'] = id;
        rec_data['details'][1049] = term_id;
        window.hWin.HAPI4.RecordMgr.saveRecord(rec_data,
            function (response) {
                if (response.status == window.hWin.ResponseStatus.OK) {
                    console.log("Prefix update ")
                }
            });
    }
    if (type == "suffix") {
        rec_data['ID'] = id;
        rec_data['details'][1050] = term_id;
        window.hWin.HAPI4.RecordMgr.saveRecord(rec_data,
            function (response) {
                if (response.status == window.hWin.ResponseStatus.OK) {
                    console.log("Suffix update ")
                } else {
                    console.log("Unable to create suffix term");
                }
            });
    }
    if (type == "agentType") {
        rec_data['ID'] = id;
        rec_data['details'][1000] = term_id;
        window.hWin.HAPI4.RecordMgr.saveRecord(rec_data,
            function (response) {
                if (response.status == window.hWin.ResponseStatus.OK) {
                    console.log("Agent Type update ")
                }
            });
    }
    if (type == "religion") {
        console.log("Creating new religion")
        createReligionRecord(term_id, id)
    }
    if (type == "bookFormat") {
        rec_data['ID'] = id;
        rec_data['details'][991] = term_id;
        window.hWin.HAPI4.RecordMgr.saveRecord(rec_data,
            function (response) {
                if (response.status == window.hWin.ResponseStatus.OK) {
                } else {
                    console.log("Unable to add Bookformat while importing edition data.")
                    console.log(response)
                }
            });
    }
}

/* Create religion record */

/* Author is parent record of the Religion record */
function createReligionRecord(religion, author_id) {

    religion_check = that._getTermThatExists("religion", religion)
    religion_data = {
        ID: 0,
        RecTypeID: 81,
        no_validation: true,
        details: {
            247: author_id,
            1003: religion
        }
    }

    if(religion_check != null || religion_check!=undefined){
        setTimeout(function () {
            console.log("Waiting 2 second before trying to create religion record again..")
        }, 2000);
    }
    if (religion != "" && religion != null) {
        window.hWin.HAPI4.RecordMgr.saveRecord(religion_data,
            function (response) {
                if (response.status == window.hWin.ResponseStatus.OK) {
                    console.log("Religion record was created")
                } else {
                    setTimeout(function () {
                        console.log("Waiting 2 second before trying to create religion record again..")
                    }, 2000);
                    console.log("Something went wrong while creating the religion record")
                    window.hWin.HAPI4.RecordMgr.saveRecord(religion_data,
                        function (response) {
                            console.log("Created religion record for the Author.")
                        });
                    console.log(response)
                }
            });
    }
}
