/**
 * importStructure.js - Widget to browse template databases, select record types,
 * individual fields, or individual vocabularies, and import them into the current database.
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     4.0
 * @namespace   heurist.importStructure
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// TODOs:
// 0. Do not show current DB in the list of source databases.
// 1. Show/hide record types that are already in the current database.
// 2. Show/hide fields in the tree view based on whether they are already in the current database.
// 3. Form structures to add terms, fields, structure, record type.
// 4. Use functions from saveStructureLib.

$.widget( "heurist.importStructure", {

    /**
     * @typedef {object} heurist.importStructure.options
     * @property {boolean} [isdialog=false] - If true, the widget is displayed as a dialog.
     *                                        See {@link heurist.importStructure#_initDialog},
     *                                        {@link heurist.importStructure#popupDialog},
     *                                        {@link heurist.importStructure#closeDialog}.
     * @property {number} [height=600] - Height of the dialog.
     * @property {number} [width=1100] - Width of the dialog.
     * @property {boolean} [modal=true] - If true, the dialog is modal.
     * @property {string} [title='Import definitions into current database'] - Title of the dialog.
     * @property {number} [source_database_id=0] - Predefined source database ID. If > 0, skips the database list selection.
     * @property {number} [pagesize=2000] - Page size for the resultList of databases.
     * @property {function|null} [onClose=null] - Callback function executed when the dialog closes.
     * @property {string} [innerTitle] - If provided, an inner title bar will be displayed. (Implicit option, used in _init)
     * @property {object} [entity] - Entity configuration, typically `window.hWin.entityRecordCfg`. (Implicit option, used in _init)
     */
    options: {
        isdialog: false,
        height: 600,
        width:  1100,
        modal:  true,
        title:  'Import definitions into current database',
        source_database_id: 0,
        pagesize: 2000,
        onClose:null
    },

    /**
     * Cached HRecordSet of databases retrieved from the master index.
     * @private
     * @type {HRecordSet|null}
     */
    _cachedRecordset_dbs:null,

    /**
     * Flag indicating whether to rename target entities during import.
     * @private
     * @type {boolean}
     */
    _is_rename_target: false,

    /**
     * Flag indicating whether to perform a conservative import (e.g., only new record types).
     * @private
     * @type {boolean}
     */
    _is_conservative: false,

    /**
     * Registration ID of the currently selected source database.
     * @private
     * @type {number|null}
     */
    _selectedDB:null,

    /**
     * Flag to ensure local definitions are initialized only once.
     * @private
     * @type {boolean}
     */
    _init_local_defs_once:true,

    /**
     * The widget's constructor.
     * @private
     */
    _create: function() {
        // prevent double click to select text
    }, //end _create

    /**
     * Initializes the widget, sets up the layout, loads data, and initializes controls.
     * This is the main initialization function.
     * @private
     */
    _init: function() {
        const that = this;

        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }

        let layout = '', sTop = '0'; // REMARK: Initialized layout to empty string. This was noted in a previous turn.
        if(this.options.innerTitle){
            layout = ('<div class="ui-heurist-header">'+this.options['title']+'</div>');
            sTop = '38px';
        }

        this._selectedDB = null;
        this._init_local_defs_once = true;

        //init layout
        layout = layout +
        '<div class="ent_wrapper" style="top:'+sTop+'">' //;min-width:1000px

        //1. database selector
        +'<div class="ent_wrapper" id="panel_dbs">'
        +    '<div class="ent_header searchForm"></div>'
        +    '<div class="ent_content_full recordList"></div>'
        +'</div>'

        //2. List of record types to be imported
        +'<div class="ent_wrapper" id="panel_defs" style="display:none;margin-left:15px;">'

        +'<div class="ent_header" style="padding:4px;">'
        +'<div style="position:absolute;right:225px;left:0">' //450px
        +'<h4 style="margin:0;padding:4 0 0 4" id="h_source"></h4>'
        +'</div>'

        //+'<div style="border-left:1px solid lightgray;position:absolute;right:225px;width:224px;height:2.8em">'
        //     +'<h4 style="margin:0;padding:4 0 0 4">Entity to be imported</h4>'
        //+'</div>'
        +'<div style="border-left:1px solid lightgray;position:absolute;right:0px;width:223px;height:2.8em;">'
        +'<h4 style="margin:0;padding:4 0 0 4">Current entities in database</h4>'
        +'</div>'
        +'</div>'

        +'<div class="ent_wrapper" id="search_elements" style="top:2.8em;right:225px;border-right:1px solid lightgray;">'
            +'<div id="btn_back_to_databases" style="margin-right: 15px"></div>'
            +'<label>Filter<input id="search_names" class="text ui-widget-content ui-corner-all" style="width: 10em; margin-right:15px;margin-left:10px;"></label>' // general search
            +'<label><input id="show_all" class="text ui-widget-content ui-corner-all" style="margin-left:10px;vertical-align:-2px;" type="checkbox"> Show All (include items already in database)</label>' // show all
        +'</div>'

        //left - source
        +'<div class="ent_wrapper" id="entity_wrapper" style="top:6.8em;bottom:1em;right:225px;border-right:1px solid lightgray;">' //450px
            + '<ul>'
                + '<li><a href="#rty_container">Record / entity types</a></li>'
                + '<li><a href="#dty_container">Individual fields</a></li>'
                + '<li><a href="#trm_container">Individual vocabularies</a></li>'
            + '</ul>'
            + '<div id="trm_container" style="background:transparent;">'
                + '<div class="ui-heurist-bg-light" style="padding:5px 0px 0px 15px;">&#10003; shows terms already in database</div>'
                + '<div class="ent_content_full" id="panel_trm_list" style="position:relative;top:0px;height:97.25%;"></div>'
            + '</div>'
            + '<div id="dty_container" style="background:transparent;">'
                + '<div class="ent_content_full" id="panel_dty_list" style="position:relative;top:0px;height:100%;"></div>'
            + '</div>'
            + '<div id="rty_container"style="background:transparent;">'
                + '<div class="ent_content_full" id="panel_rty_list" style="position:relative;top:0px;height:100%;"></div>'
            + '</div>'
        +'</div>'

        //target
        +'<div id="panel_def_list_target" '
        +'style="position:absolute; top:2.8em;bottom:0;right:0px; overflow:hidden;width:225px;">'
        +'<select id="select_rty_list_target" size="500" style="width:100%;height:100%;border:lightgray 1px solid"></select>'
        +'<select id="select_dty_list_target" size="500" style="width:100%;height:100%;border:lightgray 1px solid;display:none;"></select>'
        +'<div id="select_trm_list_target" style="width:100%;height:100%;border:lightgray 1px solid;display:none;">'
            + 'Due to the potentially numerous amount of vocabulary and terms,<br>existing local vocabulary and terms are not displayed here'
        +'</div>'
        +'</div>'

        +'</div>'

        //3. report after completion of import
        +'<div class="ent_wrapper" id="panel_report" style="display:none">'
        +    '<div class="ent_content_full" style="bottom:2.8em;top:0;padding:10px"></div>'
        +    '<div class="ent_footer" style="text-align:center"><div id="btn_close_panel_report"></div></div>'
        +'</div>'

        +'</div>';

        $(layout).appendTo(this.element);

        this.panel_defs = this.element.find('#panel_defs');
        this.panel_def_list_target = this.element.find('#panel_def_list_target');

        this.panel_report = this.element.find('#panel_report');
        this.entity_wrapper = this.element.find('#entity_wrapper');
        this.general_search = this.element.find('#search_names');
        this.show_all = this.element.find('#show_all');

        this.panel_rty_list = this.element.find('#panel_rty_list');
        this.panel_dty_list = this.element.find('#panel_dty_list');
        this.panel_trm_list = this.element.find('#panel_trm_list');

        this.select_rty_list_target = this.element.find('#select_rty_list_target');
        this.select_dty_list_target = this.element.find('#select_dty_list_target');
        this.select_trm_list_target = this.element.find('#select_trm_list_target');

        this.panel_report.find('#btn_close_panel_report')
        .button({icon: 'ui-icon-carat-1-w', iconPosition:'right', label:'Back to Record Type List'})
        //.css({'line-height': '0.9em'})
        .on('click', function(){
            that.panel_report.hide();
            that.panel_defs.show();

            //refresh source
            that.panel_rty_list.manageDefRecTypes('getRecordsetFromStructure', window.hWin.HEURIST4.remote.rectypes );
            that.panel_dty_list.manageDefDetailTypes('getRecordsetFromRemote', window.hWin.HEURIST4.remote.detailtypes );
            that.panel_trm_list.manageDefTerms('getRecordsetFromRemote', window.hWin.HEURIST4.remote.terms );

            //refresh target
            window.hWin.HEURIST4.ui.createRectypeSelect(that.select_rty_list_target[0],null,null,true);
            window.hWin.HEURIST4.ui.createRectypeDetailSelect(that.select_dty_list_target[0], null, null, null, {useHtmlSelect: true});

            // refresh filter
            that._filterEntities();
        });

        this.entity_wrapper.tabs({
            heightStyle: 'fill',
            beforeActivate: function(event, ui){ // correct panel height
                let panel = ui.newPanel;
                let parent_height = panel.parent().height();
                panel.height(parent_height - 38);
            },
            activate: function(event, ui){
                let panel = ui.newPanel;
                let entity = panel.attr('id');

                that.panel_def_list_target.find('select').hide();
                window.hWin.HEURIST4.util.setDisabled(that.show_all, false);

                if(entity == 'dty_container'){
                    that.select_dty_list_target.show();
                    that.show_all.prop('checked', false);
                    window.hWin.HEURIST4.util.setDisabled(that.show_all, true);
                }else if(entity == 'trm_container'){
                    that.select_trm_list_target.show();
                    that.show_all.prop('checked', true);
                    window.hWin.HEURIST4.util.setDisabled(that.show_all, true);
                }else{ // rty_container
                    that.select_rty_list_target.show();
                }
                that._filterEntities();
            }
        });

        let ele = this.element.find('#btn_back_to_databases')
        .button({label:'<< Back to Databases'});
        if(that.options.source_database_id>0){
            ele.hide();
        }else{
            this._on( ele, {'click':this._backToDatabases} );
        }

        this._on(this.element.find('#show_all'), {
            change: this._filterEntities
        });
        this._on(this.element.find('#search_names'), {
            keyup: this._filterEntities
        });

        //find 3 elements searchForm, recordList+recordList_toolbar, editForm+editForm_toolbar
        this.recordList_dbs = this.element.find('#panel_dbs .recordList');
        this.searchForm_dbs = this.element.find('#panel_dbs .searchForm');

        //init record list for dbs and rty
        this.recordList_dbs
        .resultList({
            eventbased: false, //do not listen global events
            multiselect: false,
            select_mode: 'select_single', // none

            entityName: 'Records',
            view_mode: 'list',
            show_viewmode: false,

            recordDivEvenClass: 'recordDiv_blue',

            pagesize: (this.options.pagesize>0) ?this.options.pagesize: 9999999999999,
            empty_remark: '<div style="padding:1em 0 1em 0">No registered databases found</div>',

            groupByField: 'rec_RecTypeID',
            rendererGroupHeader: function(grp_val){
                if(grp_val==0){
                    return '<div style="border-bottom:1px solid lightgray">'
                    +'<div style="padding:24px 0 4px 40px;"><h2 style="margin:0">Curated templates</h2>'
                    +'<div style="padding-top:4px;"><i>Databases curated by the Heurist team as a source of useful entity types for new databases</i></div></div></div>';
                }else{
                    return '<div style="width:100%;border-bottom:1px solid lightgray">'
                    +'<div style="padding:24px 0 4px 40px;">'
                    +'<h2 style="margin:0">User databases</h2>'
                    +'<div style="padding-top:4px"><i>Databases registered by Heurist users - use with care, look for entity types with good internal documentation</i></div></div></div>';
                }
            },

            rendererHeader:  function(){
                let sHeader = '<div style="width:62px">Reg#</div><div style="width:23em">Database Name</div>'
                +'<div style="width:2em">&nbsp;</div>'
                +'<div style="width:52em">Description</div>';
                return sHeader;
            },
            renderer:
            function(recordset, record){
                return that._recordListItemRenderer_dbs(recordset, record);
            }
        });

        this._on( this.recordList_dbs, {
            "resultlistonselect": function(event, selected_recs){
                // show list of record types for selected database
                that._loadDefinitionsForDb( selected_recs );
            },
            "resultlistonaction": this._onActionListener
        });

        //help text
        $('<div>')
        .text('Please select a database in the list below to see entity (record) '
            +'types which you might wish to import. Rollover description for full details.')
        .addClass('heurist-helper1')
        .css({padding:'7px 30px'})
        .appendTo(this.recordList_dbs.find('.div-result-list-toolbar'));

        //init search panel
        this.searchForm_dbs.load(window.hWin.HAPI4.baseURL
            +'hclient/widgets/entity/searchSysDatabases.html?t='
            +window.hWin.HEURIST4.util.random(),
            function(response, status, xhr){
                //init buttons
                that.btn_search_start = that.searchForm_dbs.find('#btn_search_start')
                .show()
                //.css({'width':'6em'})
                .button({label: window.hWin.HR("Start search"), showLabel:false,
                    icon:"ui-icon-search", iconPosition:'end'});

                //this is default search field - define it in your instance of html
                that.input_search = that.searchForm_dbs.find('.input_search');

                that._on( that.input_search, { keypress: that.startSearchOnEnterPress });
                that._on( that.btn_search_start, { click: that.startSearch_dbs });

                that.input_search_type = that.searchForm_dbs.find('#input_search_type2');
                that._on(that.input_search_type,  { change:that.startSearch_dbs });

                that.input_sort_type = that.searchForm_dbs.find('#input_sort_type');
                that.input_sort_type.val('register');
                that._on(that.input_sort_type,  { change:that.startSearch_dbs });
        });

        //----------------------
        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        this.options.entity = window.hWin.entityRecordCfg;

        //retrieve all template databases from master index server
        let query_request = {remote:'master', detail: 'header'};
        if(that.options.source_database_id>0){
            query_request['q'] = 'ids:'+that.options.source_database_id;
        }

        window.hWin.HAPI4.RecordMgr.search(query_request,
            function( response ){
                if(response.status == window.hWin.ResponseStatus.OK){
                    response.data.fields.push('rec_ScratchPad');
                    that._cachedRecordset_dbs = new HRecordSet(response.data);

                    //prepare recordset - extract database name and transfer title to notes
                    that._cachedRecordset_dbs.each(function(recID, record){
                        let recURL  = this.fld(record, 'rec_URL');
                        let recDesc = this.fld(record, 'rec_Title');
                        let dbURL = '';
                        let dbName = 'Broken registration (Db URL is not defined)';

                        if(recURL){
                            let splittedURL = recURL.split('?');
                            if(splittedURL && splittedURL.length>0){
                                dbURL = splittedURL[0];
                                let matches = recURL.match(/db=([^&]{1,65}).*$/);
                                dbName = (matches && matches.length>1)?matches[1]:'';
                            }
                        }
                        let url_Error = this.fld(record, 'rec_URLErrorMessage');
                        if( !window.hWin.HEURIST4.util.isempty( url_Error ) ){
                            dbName = dbName + ' (unavailable)';
                            dbURL = '';
                        }

                        this.setFld(record, 'rec_URL', dbURL);
                        this.setFld(record, 'rec_Title', dbName);
                        this.setFld(record, 'rec_ScratchPad', recDesc);
                        this.setFld(record, 'rec_RecTypeID', recID<1000?0:1); // Group curated vs user dbs
                    });

                    window.hWin.HEURIST4.msg.sendCoverallToBack();

                    if(that.options.source_database_id>0){
                        let selected_recs = that._cachedRecordset_dbs.getSubSetByIds( [that.options.source_database_id] );
                        that._loadDefinitionsForDb( selected_recs );
                    }else{
                        that.startSearch_dbs();
                    }
                }else{
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );

        //----------------------------
        //show dialog if required
        if(this.options.isdialog){
            this.popupDialog();
            this._fixWidth();
        }

        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element);

    },

    /**
     * Handles the Enter key press in the search input for databases.
     * If Enter is pressed, it prevents default form submission and triggers `startSearch_dbs`.
     * @private
     * @param {jQuery.Event} e - The jQuery keypress event object.
     */
    startSearchOnEnterPress: function(e){
        let code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) { // Enter key
            window.hWin.HEURIST4.util.stopEvent(e); // Stop event propagation
            e.preventDefault(); // Prevent default form submission
            this.startSearch_dbs();
        }
    },

    /**
     * Initiates a search for databases based on criteria from the search form.
     * It constructs a request object using values from input fields for search text,
     * database type (curated/user), and sort order.
     * It excludes the current database from the search results if its ID is known.
     * Finally, it calls `filterRecordList_dbs` to apply the filters and update the displayed list.
     * @private
     */
    startSearch_dbs: function(){
        let request = {};

        if(!this.input_search) return; // Exit if search input element is not found

        // Add search term for database title if provided
        if(this.input_search.val()!=''){
            request['rec_Title'] = this.input_search.val();
        }

        // Add filter for database type (curated or user)
        if(this.input_search_type.val()!=''){
            if(this.input_search_type.val()=='curated'){
                request['rec_ID'] = '<1000'; // Curated DBs typically have lower IDs
            }else if(this.input_search_type.val()=='user'){
                request['rec_ID'] = '>999';  // User DBs typically have higher IDs
            }
        }

        // Exclude the current database from search results if its registered ID is available
        if(window.hWin.HAPI4.sysinfo['db_registeredid']>0){
            request['rec_ID'] = (request['rec_ID'] ? request['rec_ID'] + ';' : '') + ('!='+window.hWin.HAPI4.sysinfo['db_registeredid']); // Append if other ID filter exists
        }

        // Add sorting criteria
        if(this.input_sort_type.val()=='name'){
            request['sort:rec_Title'] = 1; // Sort by title ascending
        }else if(this.input_sort_type.val()=='register'){
            request['sort:rec_ID'] = 1; // Sort by registration ID ascending
        }else  if(this.input_sort_type.val()=='url'){
            request['sort:rec_URL'] = -1; // Sort by URL descending
        }

        this._selectedDB = null; // Reset currently selected database
        this.filterRecordList_dbs(request); // Apply filters and update the list
    },

    /**
     * Loads and displays definitions (record types, detail types, terms) from a selected remote database.
     * This function is typically called when a user selects a database from the list.
     * It initializes or updates three child widgets (`manageDefRecTypes`, `manageDefDetailTypes`, `manageDefTerms`)
     * to display the definitions from the chosen source database.
     * It handles special cases like password-protected databases.
     *
     * @private
     * @param {HRecordSet} db_ids - An HRecordSet containing the data of the selected source database.
     *                              Only the first record in the set is used.
     * @param {boolean} [skip_pass=false] - If true, password verification for special databases (like ID 99) is skipped.
     *                                      This is typically true after successful verification.
     */
    _loadDefinitionsForDb: function(db_ids, skip_pass){ // JSDoc for signature was applied in previous turn.
        const that = this;
        let panel_dbs = this.element.find('#panel_dbs');
        let record = db_ids.getFirstRecord();

        let sDB_ID = db_ids.fld(record, 'rec_ID');
        let sURL  = db_ids.fld(record, 'rec_URL');
        let sDB   = db_ids.fld(record, 'rec_Title');

        if(!sURL) return; // Database URL is missing, cannot proceed.

        if(this._selectedDB != sDB_ID){
            // Special case for password-protected database (ID 99 - Heurist construction site)
            if(sDB_ID==99 && skip_pass!==true){
                window.hWin.HAPI4.SystemMgr.verify_credentials(
                    function(){that._loadDefinitionsForDb( db_ids, true );},1,'ServerFunctions'); // Requires db admin and password
               return;
            }

            this._selectedDB = sDB_ID;
            this.element.find('#h_source').text('Entities available in '+sDB_ID+':'+sDB);

            // Options for manageDefRecTypes widget
            let rty_options = {
                isdialog: false,
                container: '#panel_rty_list',
                select_mode: 'select_single',
                groupsPresentation: 'none',
                simpleSearch: true,
                import_structure:{
                    database: sDB,      //database name
                    databaseURL: sURL,
                    database_url:  (sURL+'?db='+sDB),
                    load_detailstypes: false  // detail types will be loaded by their own widget
                },
                onaction:function(event, action){
                    let recID;
                    if(action && action.action){
                        recID =  action.recID;
                        action = action.action;
                    }
                    if(recID>0){
                        if(action=='expand'){
                            window.hWin.HEURIST4.remote._selectedRtyID = (window.hWin.HEURIST4.remote._selectedRtyID == recID)?null:recID;
                            that.panel_rty_list.manageDefRecTypes('refreshRecordList');
                        }else if(action=='import'){
                            that._preImportCheck('rectype', recID);
                        }
                    }
                },
                recordList:{
                    show_toolbar: false,
                    pagesize: 4999,
                    view_mode:'list',
                    simpleSearch:true,
                    groupByField:'rty_RecTypeGroupID',
                    groupOnlyOneVisible: true,
                    groupByCss:'0 1.5em',
                    rendererGroupHeader: function(grp_val, is_expanded){
                        let rectypes = window.hWin.HEURIST4.remote.rectypes;
                        let idx = rectypes.groups.groupIDToIndex[grp_val];
                        return rectypes.groups[idx]?('<div data-grp="'+grp_val
                            +'" style="font-size:0.9em;padding:14px 0 4px 0px;border-bottom:1px solid lightgray">'
                            +'<span style="display:inline-block;vertical-align:top;padding-top:15px;font-size:20px;" '
                            +'class="expand_button ui-icon ui-icon-triangle-1-'+(is_expanded?'s':'e')+'"></span>'
                            +'<div style="display:inline-block;width:70%">'
                            +'<h2 style="margin:0">'+grp_val+'  '+rectypes.groups[idx].name+'</h2>'
                            +'<div style="padding-top:4px;"><i>'
                            +(window.hWin.HEURIST4.util.isempty(rectypes.groups[idx].description)?'':rectypes.groups[idx].description)+'</i></div></div></div>'):'';
                    },
                    renderer: this._recordtypeListItemRenderer // Bound to 'this' (importStructure widget)
                }
            };
            this.panel_rty_list.empty();
            window.hWin.HEURIST4.ui.showEntityDialog('defRecTypes', rty_options);


            // Options for manageDefDetailTypes widget
            let dty_options = {
                isdialog: false,
                container: '#panel_dty_list',
                select_mode: 'select_single',
                groupsPresentation: 'none',
                simpleSearch: true,
                import_structure:{
                    database: sDB,
                    databaseURL: sURL,
                    database_url:  (sURL+'?db='+sDB)
                },
                onselect:function(event, data){
                    // Placeholder for treeview display or other actions on select
                },
                onaction:function(event, action){
                    let recID;
                    if(action && action.action){
                        recID =  action.recID;
                        action = action.action;
                    }
                    if(recID>0){
                        if(action=='expand'){
                            window.hWin.HEURIST4.remote._selectedDtyID = (window.hWin.HEURIST4.remote._selectedDtyID == recID)?null:recID;
                            that.panel_dty_list.manageDefDetailTypes('refreshRecordList');
                        }else if(action=='import'){
                            that._preImportCheck('detailtype', recID);
                        }
                    }
                },
                recordList:{
                    show_toolbar: false,
                    pagesize: 4999,
                    view_mode:'list',
                    simpleSearch:true,
                    groupByField:'dty_DetailTypeGroupID',
                    groupOnlyOneVisible: true,
                    groupByCss:'0 1.5em',
                    rendererGroupHeader: function(grp_val, is_expanded){
                        let detailtypes = window.hWin.HEURIST4.remote.detailtypes;
                        let idx = detailtypes.groups.groupIDToIndex[grp_val];
                        let output = '';
                        if(detailtypes.groups[idx]){
                            output = '<div data-grp="'+grp_val
                            +'" style="font-size:0.9em;padding:14px 0 4px 0px;border-bottom:1px solid lightgray">'
                            +'<span style="display:inline-block;vertical-align:top;padding-top:15px;font-size:20px;" '
                            +'class="expand_button ui-icon ui-icon-triangle-1-'+(is_expanded?'s':'e')+'"></span>'
                            +'<div style="display:inline-block;width:70%">'
                            +'<h2 style="margin:0">'+grp_val+' '+detailtypes.groups[idx].name+'</h2>'
                            +'<div style="padding-top:4px;"><i>'
                            +(window.hWin.HEURIST4.util.isempty(detailtypes.groups[idx].description)?'':detailtypes.groups[idx].description)
                            +'</i></div></div></div>';
                        }
                        return output;
                    },
                    renderer: this._detailtypeListItemRenderer // Bound to 'this'
                }
            };
            this.panel_dty_list.empty();
            window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', dty_options);

            // Options for manageDefTerms widget
            let trm_options = {
                isdialog: false,
                container: '#panel_trm_list',
                select_mode: 'select_single',
                groupsPresentation: 'none',
                hide_searchForm: true,
                simpleSearch: true,
                import_structure:{
                    database: sDB,
                    databaseURL: sURL,
                    database_url:  (sURL+'?db='+sDB)
                },
                onselect:function(event, data){
                    // Placeholder for treeview display
                },
                onaction:function(event, action){
                    let recID;
                    if(action && action.action){
                        recID =  action.recID;
                        action = action.action;
                    }
                    if(recID>0){
                        if(action=='expand'){
                            window.hWin.HEURIST4.remote._selectedTrmID = (window.hWin.HEURIST4.remote._selectedTrmID == recID)?null:recID;
                            that.panel_trm_list.manageDefTerms('refreshRecordList');
                        }else if(action=='import'){
                            that._preImportCheck('term', recID);
                        }
                    }
                },
                recordList:{
                    show_toolbar: false,
                    pagesize: 4999,
                    view_mode:'list',
                    simpleSearch:true,
                    groupByField:'trm_VocabularyGroupID',
                    groupOnlyOneVisible: true,
                    groupByCss:'0 1.5em',
                    rendererGroupHeader: function(grp_val, is_expanded){
                        let terms = window.hWin.HEURIST4.remote.terms;
                        return terms.groups[grp_val]?('<div data-grp="'+grp_val
                            +'" style="font-size:0.9em;padding:14px 0 4px 0px;border-bottom:1px solid lightgray">'
                            +'<span style="display:inline-block;vertical-align:top;padding-top:15px;font-size:20px;" '
                            +'class="expand_button ui-icon ui-icon-triangle-1-'+(is_expanded?'s':'e')+'"></span>'
                            +'<div style="display:inline-block;width:70%">'
                            +'<h2 style="margin:0">'+grp_val+'  '+terms.groups[grp_val].vcg_Name+'</h2>'
                            +'<div style="padding-top:4px;"><i>'
                            +(window.hWin.HEURIST4.util.isempty(terms.groups[grp_val].vcg_Description)?'':terms.groups[grp_val].vcg_Description)
                            +'</i></div></div></div>'):'';
                    },
                    renderer: this._termsListItemRenderer // Bound to 'this'
                }
            };
            this.panel_trm_list.empty();
            window.hWin.HEURIST4.ui.showEntityDialog('defTerms', trm_options);
        }
        panel_dbs.hide();
        this.panel_defs.show();

        if( this._init_local_defs_once ){ // If this is the first time loading definitions for any DB
            this._init_local_defs_once = false;
            // Populate the <select> elements that show the current (local) database's record types and detail types.
            // This is done only once as the local definitions don't change during the import process from a remote DB.
            window.hWin.HEURIST4.ui.createRectypeSelect(that.select_rty_list_target[0],null,null,true);
            window.hWin.HEURIST4.ui.createRectypeDetailSelect(that.select_dty_list_target[0], null, null, null, {useHtmlSelect: true});
        }
    },

    /**
     * Hides the definitions panel and shows the database selection panel.
     * Called when the "Back to Databases" button is clicked.
     * @private
     */
    _backToDatabases: function(){
        this.panel_defs.hide();
        this.element.find('#panel_dbs').show();
    },

    /**
     * Sets one or more options for the widget.
     * This is a standard jQuery UI widget method override.
     * It's useful for deferring processor-intensive changes for multiple option changes.
     * @private
     * @param {object} options - A map of option-value pairs to set.
     * @example // Set multiple options
     * $( ".selector" ).importStructure( "option", {
     *   height: 700,
     *   width: 1200
     * });
     * @example // Set a single option
     * $( ".selector" ).importStructure( "option", "modal", false );
     */
    _setOptions: function( options ) {
        this._superApply( arguments ); // Call the parent widget's method
    },

    /**
     * Refreshes the visual state of the widget.
     * Currently, this method is a placeholder and does not implement specific refresh logic.
     * It could be used, for example, to show/hide buttons based on the current login status or other state changes.
     * @private
     */
    _refresh: function(){
        // Placeholder: show/hide buttons depends on current login status or other dynamic conditions.
    },

    /**
     * Cleans up the widget, removing any elements it created.
     * This is a standard jQuery UI widget method override, called when the widget is destroyed.
     * @private
     */
    _destroy: function() {
        // Remove generated elements to prevent memory leaks
        if(this.searchForm_dbs) this.searchForm_dbs.remove();
        if(this.recordList_dbs) this.recordList_dbs.remove();
        // Other elements created by the widget (panels, specific buttons not part of forms) are removed when the main element is removed by jQuery UI.
        // If specific event handlers were bound to elements outside the main widget element (e.g., on `window` or `document`),
        // they should be explicitly unbound here using .off() or similar.
    },

    /**
     * Handles actions triggered from the list of databases (e.g., opening or cloning a database).
     * This function serves as a central listener for `resultlistonaction` events from the `recordList_dbs` resultList.
     *
     * @private
     * @param {jQuery.Event} event - The jQuery event object.
     * @param {object} actionData - Data associated with the action, typically containing `action` (string) and `recID` (number).
     * @returns {boolean} Returns `true` if the action was 'select-and-close' (though this action's implementation is minimal),
     *                    otherwise returns `false` after attempting to handle the action.
     */
    _onActionListener:function(event, actionData){
        let actionName = actionData.action;
        let recID = actionData.recID;

        if(actionName === 'select-and-close'){
            // TODO: Implement select and close functionality if needed for this context.
            // This action currently does very little.
            return true;
        } else {
            if (!recID || recID <= 0) { // recID is required for other actions and should be a positive number.
                // window.hWin.HEURIST4.msg.showMsgWarn({message: 'No record ID provided for the action.'}); // Optional: User feedback
                return false;
            }

            let record = this._cachedRecordset_dbs.getById(recID);
            if (!record) {
                window.hWin.HEURIST4.msg.showMsgErr({message: `Database record with ID ${recID} not found in cache.`});
                return false; // Record not found in cache
            }

            let dbName = this._cachedRecordset_dbs.fld(record, 'rec_Title');
            let recURL = this._cachedRecordset_dbs.fld(record, 'rec_URL');

            if(actionName === 'open'){
                if(!recURL || !dbName){ // Check if essential data is present
                    window.hWin.HEURIST4.msg.showMsgWarn({message: 'Database URL or Name is missing, cannot open.'});
                    return false;
                }
                // Show intermediate warning before opening external database link
                window.hWin.HEURIST4.msg.showMsgDlg(
                    'These links are intended only as a shortcut for the owner of this database and '
                    +'would require you to be able to log into the database. '
                    +'Please use download or clone links on the left if you are not the owner of the database.',
                    function(){ window.open(recURL+'?db='+dbName,'_blank'); },
                    {title:'Info',yes:'Proceed',no:'Cancel'});
            }else if(actionName === 'clone'){
                if(!recURL){ // URL is essential for cloning
                     window.hWin.HEURIST4.msg.showMsgWarn({message: 'Database URL is missing, cannot clone structure.'});
                    return false;
                }
                this._selectedDB = recID;
                this._preImportCheck('all', 'all'); // 'all' for both parameters signifies cloning the entire DB structure
            }
        }
        return false;
    },

    /**
     * Renderer for items in the database list (resultList_dbs).
     * Constructs the HTML for a single database item in the list.
     * @private
     * @param {HRecordSet} recordset - The HRecordSet containing the database records.
     * @param {object} record - The individual database record object to render.
     * @returns {string} HTML string representing the rendered list item.
     */
    _recordListItemRenderer_dbs:function(recordset, record){

        function fld(fldname){
            return recordset.fld(record, fldname);
        }

        let recID = fld('rec_ID');
        
        let dbName = fld('rec_Title');
        let recTitle = window.hWin.HEURIST4.util.stripTags(fld('rec_ScratchPad') || '', "u, i, b, strong, em");

        let rtIcon = window.hWin.HAPI4.getImageUrl('sysDatabases', 0, 'icon');
        let recThumb = window.hWin.HAPI4.getImageUrl('sysDatabases', recID, 'thumb');

        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:1">'
        +'</div>';
        
        let w = this.recordList_dbs.width()-550;
        if(w<150) w = 150;
        
            let url_Error = fld('rec_URLErrorMessage');
            let errorTitle = ''; // Renamed for clarity
            if(!window.hWin.HEURIST4.util.isempty(url_Error)){
                errorTitle = 'The indexed database is currently inaccessible. It returned '
                                +window.hWin.HEURIST4.util.htmlEscape(url_Error);
            }

        let recTitleContent = '<div class="item" style="width:3em">'+recID+'</div>'
        +'<div class="item" style="width:25em;'+(recID<1000?'font-weight:bold;':'')+ (errorTitle ?'color:lightgray':'') + '"' // Simplified check
        + ' title="' + errorTitle + '"'
        + '>'+dbName+'</div>'
        +'<div class="item" style="width:6.5em">'
        +   '<span data-key="clone" style="cursor:pointer;text-decoration:underline">add template</span>'
        +'</div>'
        +'<div class="item" style="width:'+w+'px"  title="'+recTitle+'">'+recTitle+'</div>';  //  description

        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img alt="" src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif"' // Added alt attribute
        +     ' style="background-image: url(&quot;'+rtIcon+'&quot;);">'
        + '</div>'
        + '<div style="left:40px !important" class="recordTitle">'
        +     recTitleContent 
        + '</div><div class="action-button-container">';
        
        
        let usr_exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
        if(usr_exp_level==0){ //advanced
            html = html
            + '<div title="Click to open database in new window" '
            + 'class="rec_edit_link_ext ui-button action-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
            + 'role="button" aria-disabled="false" data-key="open">'
            + '<span class="ui-button-icon-primary ui-icon ui-icon-extlink"></span><span class="ui-button-text"></span>'
            + '</div>';
        }

        html = html + '</div></div>';

        return html;

    },

    /**
     * Initializes the dialog properties if `options.isdialog` is true.
     * Sets up autoOpen, height, width, modal, title, position, and close behavior.
     * @private
     */
    _initDialog: function(){

        let options = this.options;
        let position = options.position || { my: "center", at: "center", of: window }; // Use option or default
        
        const that = this;

        let maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
        if(options['width']>maxw) options['width'] = maxw*0.95;
        let maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
        if(options['height']>maxh) options['height'] = maxh*0.95;

       
        this.element.addClass('ui-heurist-bg-light');

        let $dlg = this.element.dialog({
            autoOpen: false ,
            //element: this.element[0], // Not standard jQuery UI dialog option
            height: options['height'],
            width:  options['width'],
            modal:  (options['modal']!==false),
            title: window.hWin.HEURIST4.util.isempty(options['title'])?'':options['title'],
            position: position,
            resizeStop: function(){ that._fixWidth(); },
            close:function(){
                if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
                    that.options.onClose.call(that.element); // Call with widget element as context
                } 
                // $dlg.parent().remove(); // jQuery UI usually handles removal of dialog wrapper.
                that.destroy(); // Call widget's destroy method for proper cleanup.
            }
        }); 
        this._as_dialog = $dlg; 
    },

    /**
     * Adjusts the width of the main widget element and potentially child elements
     * in response to dialog resizing.
     * @private
     */
    _fixWidth: function() {
        let correctWidth = this.element.parent().width()-24; // 24 accounts for padding/borders from jQuery UI dialog
        this.element.css({overflow: 'hidden','width':correctWidth }); // Changed from 'none !important'

        /* Example of resizing internal panels, if needed:
        this.panel_rty_list.css({'width': correctWidth/2});
        this.panel_rty_list_target.css({'left': correctWidth/2+1});
        */
    },

    /**
     * Opens the widget as a popup dialog if `options.isdialog` is true.
     * Initializes help buttons for the dialog.
     * @public
     */
    popupDialog: function(){
        if(this.options.isdialog && this._as_dialog){ // Ensure _as_dialog is initialized
            this._as_dialog.dialog("open");
            let helpURL = window.hWin.HRes( 'importStructure.html' )+' #content'; // Assumes HRes resolves to a valid URL path
            window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog,  null, helpURL, false);
        }
    },

    /**
     * Filters the cached list of databases (`_cachedRecordset_dbs`) based on the provided request object.
     * Updates the `recordList_dbs` resultList instance with the filtered subset.
     * @private
     * @param {object} request - The filter request object. This object can contain field names as keys
     *                           and search terms as values, or sorting directives like `{'sort:fieldName': 1}`.
     * @returns {HRecordSet|null} The filtered HRecordSet, or null if `_cachedRecordset_dbs` is not set.
     */
    filterRecordList_dbs: function(request){
        let subset = null;
        if(this._cachedRecordset_dbs){
            // Ensure options.entity.fields is available for HRecordSet.getSubSetByRequest if it uses it
            let fieldsToSearch = (this.options.entity && this.options.entity.fields) ? this.options.entity.fields : undefined;
            subset = this._cachedRecordset_dbs.getSubSetByRequest(request, fieldsToSearch);
            this.recordList_dbs.resultList('updateResultSet', subset, request);   
        }
        return subset;
    },

    /**
     * Initiates the import of definitions (record types, detail types, terms) from the selected source database.
     * This is the main method that triggers the server-side import process.
     *
     * @public
     * @param {number|string|Array<number|string>} id - The ID(s) of the definition(s) to import.
     *        For 'rectype', 'detailtype', 'term', this is usually a single ID or an array of IDs.
     *        For 'all' (cloning), this parameter might be 'all' or an array of all relevant IDs.
     * @param {string} type - The type of definition being imported. Expected values are:
     *                        'rectype' (for record types),
     *                        'detailtype' (for base fields/detail types),
     *                        'term' (for vocabularies/terms),
     *                        'all' (for cloning an entire database structure).
     * @returns {void}
     */
    startImport: function(id, type){

        if(!id || (Array.isArray(id) && id.length === 0) || (typeof id !== 'number' && !Array.isArray(id) && parseInt(id, 10) < 1 && id !== 'all') || !type){ // Allow 'all' for id
            window.hWin.HEURIST4.msg.showMsgErr({message: 'Invalid ID or type provided for import.'});
            return;
        }

        const that = this;
        const style = {'font-size': '16px', 'background-color': '#FFF', 'opacity': 1};
        let msg = 'Downloading complete database structure...<br><br>'
                + 'This may take several minutes if the source database is not on your server and if there are a large number of definitions.<br>';

        if(type === 'rectype'){
            msg = 'Downloading structure...<br><br>'
                + 'This may take a couple of minutes if there are a number of record type linked to the one requested.<br>';
        }else if(type === 'detailtype'){
            msg = 'Downloading base field...<br><br>'
                + 'This may take a couple of minutes if there are a number of record type linked to the requested field.<br>';
        }else if(type === 'term'){
            msg = 'Downloading vocabulary...<br><br>';
        }

        msg += 'This is a very complex procedure and sensitive to errors in the configuration of the source database.<br><br>'
                + 'Please report a bug if it fails (either with or without a message)<br>'
                + 'including the database and the definition(s) you are trying to download, so that we can investigate and fix.';

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, style, msg);

        window.hWin.HAPI4.SystemMgr.import_definitions(this._selectedDB, id, type,
            this._is_rename_target, this._is_conservative,
            function(response){    
                window.hWin.HEURIST4.msg.sendCoverallToBack(); 
                if(response.status == window.hWin.ResponseStatus.OK){
                    that.panel_report.find('#btn_close_panel_report').trigger('click'); // Go back to the definitions panel

                    if(type === 'all'){ // If cloning entire DB
                        that._processCloneResponse(response);
                        return;
                    }

                    let report = '';
                    // Determine which entities might have been affected for refresh
                    let entitiesToRefresh = new Set();
                    if (type === 'detailtype') entitiesToRefresh.add('dty');
                    if (type === 'term') entitiesToRefresh.add('trm');
                    if (type === 'rectype') ['rty', 'trm', 'dty', 'rst'].forEach(e => entitiesToRefresh.add(e));

                    let add_trm_related = false, add_rty_related = false;

                    if(response.report){
                        if( window.hWin.HEURIST4.util.isArrayNotEmpty(response.report.added) ){
                            report = 'Added: ';
                            for(const idx in response.report.added){
                                const itemId = response.report.added[idx];
                                let label = '';
                                if(type === 'detailtype'){
                                    label = $Db.dty(itemId,'dty_Name');
                                    add_trm_related = add_trm_related || $Db.dty(itemId, 'dty_Type') == 'enum' || $Db.dty(itemId, 'dty_Type') == 'relmarker';
                                    add_rty_related = add_rty_related || $Db.dty(itemId, 'dty_Type') == 'resource' || $Db.dty(itemId, 'dty_Type') == 'relmarker';
                                }else if(type === 'term'){
                                    label = $Db.trm(itemId,'trm_Label');
                                }else{ // rectype
                                    label = $Db.rty(itemId,'rty_Name');
                                }
                                if(response.report.translations && response.report.translations[type] && response.report.translations[type].includes(itemId)){
                                    label += ' (translations retrieved)';
                                }
                                report += (label+', ');    
                            }
                            report = report.slice(0, -2)+'<br>';
                        }
                        if( window.hWin.HEURIST4.util.isArrayNotEmpty(response.report.updated) ){
                            report += '<br>Updated: ';
                            for(const idx in response.report.updated){
                                const itemId = response.report.updated[idx];
                                let label = '';
                                if(type === 'detailtype'){
                                    label = $Db.dty(itemId,'dty_Name');
                                    add_trm_related = add_trm_related || $Db.dty(itemId, 'dty_Type') == 'enum' || $Db.dty(itemId, 'dty_Type') == 'relmarker';
                                    add_rty_related = add_rty_related || $Db.dty(itemId, 'dty_Type') == 'resource' || $Db.dty(itemId, 'dty_Type') == 'relmarker';
                                }else if(type === 'term'){
                                    label = $Db.trm(itemId,'trm_Label');
                                }else{ // rectype
                                    label = $Db.rty(itemId,'rty_Name');
                                }
                                if(response.report.translations && response.report.translations[type] && response.report.translations[type].includes(itemId)){
                                    label += ' (translations retrieved)';
                                }
                                report += (label+', ');    
                            }
                            report = report.slice(0, -2);
                        }
                        if( window.hWin.HEURIST4.util.isArrayNotEmpty(response.report.broken_terms) ){
                            report += ('<p>'+response.report.broken_terms.length
                                +' terms were not properly imported.'
                                +' Error report has been sent to Heurist support.<ul>');
                            for(let i=0; i<response.report.broken_terms.length; i++){
                                report += ('<li>'+response.report.broken_terms[i][0]+'</li>');    
                                if(i>10){ // Limit displayed broken terms
                                    report += '<li>... and more</li>';
                                    break;
                                }
                            }
                            report += ('</ul></p>');
                        }

                        if(add_rty_related) ['rty','rst'].forEach(e => entitiesToRefresh.add(e));
                        if(add_trm_related) entitiesToRefresh.add('trm');
                    }

                    if(report !== ''){
                        window.hWin.HEURIST4.msg.showMsgDlg('<br>'+report,null,
                                {title:'Import templates report'},
                                {default_palette_class:'ui-heurist-design'});
                        if (entitiesToRefresh.size > 0) {
                            window.hWin.HAPI4.EntityMgr.refreshEntityData(Array.from(entitiesToRefresh).join(','), null);
                        }
                    }else{
                        report = 'Nothing imported. '+
                        'The definition you selected to be imported is already in this database, or no changes were made.';
                        window.hWin.HEURIST4.msg.showMsgDlg(report);
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
    },

    /**
     * Renderer for items in the record type list displayed in the import interface.
     * It shows the record type's name, description (as a tooltip), and details about its fields
     * if the item is expanded (controlled by `dbs._selectedRtyID`).
     * It also indicates which fields are already present in the local database.
     * Provides buttons to expand/collapse details and to import the record type.
     *
     * @private
     * @param {HRecordSet} recordset - The HRecordSet of remote record types (typically `window.hWin.HEURIST4.remote.rectypes`).
     * @param {object} record - The individual record type object from the recordset to render.
     * @returns {string} HTML string representing the list item for the record type.
     */
    _recordtypeListItemRenderer: function( recordset, record ){
        // Helper functions for clean field access and HTML generation
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+fld(fldname)+'</div>';
        }

        let dbs = window.hWin.HEURIST4.remote; // Access to remote definitions
        let recID = fld('rty_ID');

        // Action buttons (expand/collapse, import)
        let btn_actions = '<div style="width:60px;">'
        + '<div title="Click to show details" class="action-button-expand" role="button" data-key="expand" ' // Simplified class
        + ' style="display:inline-block;height:16px;">'
        +     '<span style="padding-top: 6px;" class="ui-button-icon-primary ui-icon ui-icon-carat-'
        + ((dbs._selectedRtyID==recID)?'d':'r')+'"></span>' // Dynamic icon based on expansion state
        + '</div>'
        + '<div title="Click to import this record type" class="action-button-import" role="button" data-key="import" ' // Simplified class
        + ' style="display:inline-block;height:16px;vertical-align:bottom;font-size:1.4em;padding-left:20px">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-arrowthick-1-s" style="cursor:pointer"></span>'
        + '</div></div>';

        let info = ''; // Holds detailed info when expanded
        if(dbs._selectedRtyID==recID){
            let rts = dbs.rectypes.typedefs[recID];
            info = '<div style="border:2px solid blue;padding:10px 4px;margin-top:10px"><i>' + fld('rty_Description') + "</i><br><br>";
            info += '<table style="text-align: left;font-size:0.9em; color:darkgray;width:100%"><tr>'; // Corrected font-color to color
            info += '<th style="padding-left:10px;" class="status"><b>Already in DB?</b></th>';
            info += '<th style="padding-left:10px;"><b>Field name (used for this record type)</b></th>';
            info += '<th style="padding-left:10px;"><b>Base field name (shared across record types)</b></th>';
            info += '<th style="width:100px; padding-left:10px;"><b>Field data type</b></th></tr>';

            let idx_rst_Name  = dbs.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayName;
            let idx_rst_Type  = dbs.rectypes.typedefs.dtFieldNamesToIndex.dty_Type;
            let idx_rst_ccode = dbs.rectypes.typedefs.dtFieldNamesToIndex.dty_ConceptID;

            for (const dty in rts.dtFields) { // Use const for block-scoped variable
                let rst_fld = rts.dtFields[dty];
                let local_id = $Db.getLocalID( 'dty', rst_fld[idx_rst_ccode]);
                info += "<tr"+ (local_id>0? ' style="background-color:#CCCCCC;"' : "") +">" // Closed tr tag correctly
                + "<td style='padding-left:20px;'>" + (local_id>0 ? "yes" : "NEW") + "</td>" // Simplified boolean check
                + "<td style='padding-left:10px; font-weight:bold'>" + rst_fld[idx_rst_Name] + "</td>"
                + "<td style='padding-left:10px;'>" + ((dbs.detailtypes)?dbs.detailtypes.names[dty]:'') + "</td>"
                + "<td style='padding-left:10px;'>" + $Db.baseFieldType[rst_fld[idx_rst_Type]] + "</td></tr>";
            }
            info += "</table></div>";
        }

        let recTitle = fld2('rty_Name','15em');
        let linked_rts = $Db.getLinkedRecordTypes(recID, dbs);
        let name_rts = [];
        for(let i=0;i<linked_rts.length;i++){
            if (dbs.rectypes.names[linked_rts[i]]) { // Check if name exists
                name_rts.push(dbs.rectypes.names[linked_rts[i]]);
            }
        }
        let linkedto = '';
        if(name_rts.length>0){ // Check name_rts instead of linked_rts
            linkedto = 'Links to: '+ window.hWin.HEURIST4.util.htmlEscape(name_rts.join(', ')); // Added space in join
        }
        recTitle += '<div class="item" style="font-style:italic;width:45em" title="Linked record types">' + linkedto +'</div>'; // Combined

        let html = '<div class="recordDiv" style="min-height:16px"'
        +' id="rd'+recID+'" recid="'+recID+'">'
        + btn_actions
        + '<div class="recordTitle recordTitle2" title="'+fld('rty_Description')
        +'" style="right:10px;left:94px">' // Title attribute for tooltip
        +     recTitle
        + '</div>'
        + info // Appended info div
        + '</div>';
        return html;
    },

    /**
     * Renderer for items in the detail type (base field) list displayed in the import interface.
     * It shows the field's name, concept code, data type, and help text.
     * Provides a button to import the base field.
     *
     * @private
     * @param {HRecordSet} recordset - The HRecordSet of remote detail types (typically `window.hWin.HEURIST4.remote.detailtypes`).
     * @param {object} record - The individual detail type object from the recordset to render.
     * @returns {string} HTML string representing the list item for the detail type.
     */
    _detailtypeListItemRenderer: function(recordset, record){
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width, left_padding){
            let value = fld(fldname); // Escape once
            let swidth = !window.hWin.HEURIST4.util.isempty(col_width) ? 'width:auto;max-width:' + col_width + ';' : '';
            let spadding = !window.hWin.HEURIST4.util.isempty(left_padding) ? 'padding-left:' + left_padding + ';' : '';
            let styling = (swidth || spadding) ? 'style="' + swidth + spadding + '"' : ''; // Simplified conditional
            return '<div class="item truncate" title="'+ value +'" '+styling+'>'+ value +'</div>'; // Added truncate class potentially
        }

        let recID = fld('dty_ID');
        // Action buttons (import)
        let btn_actions = '<div style="width:30px;">'
        + '<div title="Click to import this base field" class="action-button-import" role="button" data-key="import" '
        + ' style="display:inline-block;height:16px;vertical-align:bottom;font-size:1.4em;">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-arrowthick-1-s" style="cursor:pointer"></span>'
        + '</div></div>';

        let name = fld2('dty_Name', '20em', '');
        let concept_code = fld2('dty_ConceptID', '60px', '15px');
        let type = fld2($Db.baseFieldType[fld('dty_Type')] || fld('dty_Type'), '80px', '10px'); // Display resolved type or raw
        let help_text = fld2('dty_HelpText', '45em', '10px'); // Adjusted width slightly

        let html = '<div class="recordDiv" style="min-height:16px"'
        +' id="rd'+recID+'" recid="'+recID+'">'
        + btn_actions
        + '<div class="recordTitle recordTitle2" title="'+fld('dty_HelpText') // Tooltip for the whole row
        +'" style="right:10px;left:35px;">'
        +     name
        +     concept_code
        +     type
        +     help_text
        + '</div></div>';
        return html;
    },

    /**
     * Renderer for items in the vocabulary/terms list displayed in the import interface.
     * It shows the vocabulary's label, concept code, and domain. If expanded,
     * it lists child terms, indicating their presence in the local database, code, and description.
     * Provides buttons to expand/collapse vocabulary details and to import the vocabulary.
     *
     * @private
     * @param {HRecordSet} recordset - The HRecordSet of remote vocabularies/terms (typically `window.hWin.HEURIST4.remote.terms`).
     * @param {object} record - The individual vocabulary object from the recordset to render.
     * @returns {string} HTML string representing the list item for the vocabulary.
     */
    _termsListItemRenderer: function(recordset, record){
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width, left_padding){
            let value = fld(fldname);
            let swidth = !window.hWin.HEURIST4.util.isempty(col_width) ? 'width:' + col_width + ';' : '';
            let spadding = !window.hWin.HEURIST4.util.isempty(left_padding) ? 'padding-left:' + left_padding + ';' : '';
            let styling = (swidth || spadding) ? 'style="' + swidth + spadding + '"' : '';
            return '<div class="item truncate" title="'+ value +'" '+styling+'>'+ value +'</div>';
        }

        let dbs = window.hWin.HEURIST4.remote;
        let recID = fld('trm_ID'); // This is the Vocabulary ID

        let btn_actions = '<div style="width:60px;">'
        + '<div title="Click to show child terms" class="action-button-expand" role="button" data-key="expand" '
        + ' style="display:inline-block;height:16px;">'
        +     '<span style="padding-top: 6px;" class="ui-button-icon-primary ui-icon ui-icon-carat-'
        + ((dbs._selectedTrmID==recID)?'d':'r')+'"></span>' // Uses _selectedTrmID for expansion state
        + '</div>'
        + '<div title="Click to import this vocabulary" class="action-button-import" role="button" data-key="import" '
        + ' style="display:inline-block;height:16px;vertical-align:bottom;font-size:1.4em;padding-left:20px">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-arrowthick-1-s" style="cursor:pointer"></span>'
        + '</div></div>';

        let info = ''; // For child terms when expanded
        if(dbs._selectedTrmID==recID){ // If this vocabulary is selected for expansion
            info = '<div style="border:1px solid #ccc; padding:5px; margin-top:5px;">'; // Container for child terms
            info += '<table style="text-align: left;font-size:0.9em; margin: 10px 0px 15px 25px;width:95%">'
                + '<colgroup>'
                    + '<col style="width: 150px;max-width: 150px;"><col style="width: 15px;"><col style="width: 50px;"><col style="width: auto;">'
                + '</colgroup>'
                + '<thead><tr><th>Label</th><th>In DB?</th><th>Code</th><th>Description / Alt. Code</th></tr></thead><tbody>';

            let child_count = 0;
            const MAX_CHILD_DISPLAY = 40;

            if (dbs.terms && dbs.terms.termsByDomainLookup && dbs.terms.termsByDomainLookup.enum) {
                for(const termId in dbs.terms.termsByDomainLookup.enum){
                    if(termId <= 0 || !dbs.terms.termsByDomainLookup.enum[termId]){
                        continue;
                    }
                    const termData = dbs.terms.termsByDomainLookup.enum[termId];
                    const parentTermIDs = termData[dbs.terms.fieldNamesToIndex.trm_ParentTermID];

                    if(parentTermIDs && parentTermIDs.split(',').includes(recID)){ // Check if term belongs to current vocabulary
                        if(child_count >= MAX_CHILD_DISPLAY){
                            info += '<tr><td colspan="4">... and more</td></tr>';
                            break;
                        }

                        let child_Label = termData[dbs.terms.fieldNamesToIndex.trm_Label] || '';
                        let child_Description = termData[dbs.terms.fieldNamesToIndex.trm_Description] || '';
                        let child_Code = termData[dbs.terms.fieldNamesToIndex.trm_Code] || '';
                        let child_ccode = termData[dbs.terms.fieldNamesToIndex.trm_ConceptID] || '';
                        let child_semanticuri = termData[dbs.terms.fieldNamesToIndex.trm_SemanticReferenceURL] || '';
                        let has_term_in_local_db = ($Db.getLocalID('trm', child_ccode) > 0) ? '&#10003;' : '&#10005;';

                        let extra_dtls = child_Description || child_Code;
                        if (child_Description && child_Code) extra_dtls = `${child_Code} : ${child_Description}`;

                        info += '<tr>'
                            + '<td class="truncate" title="'+ child_Label +'">'+ child_Label +'</td>'
                            + '<td style="text-align:center;">'+ has_term_in_local_db +'</td>'
                            + '<td class="truncate" title="'+ child_ccode +'">'+ child_ccode +'</td>'
                            + '<td class="truncate" title="'+ extra_dtls +'">'+ extra_dtls +'</td>'
                        + '</tr>';

                        if(!window.hWin.HEURIST4.util.isempty(child_semanticuri)){
                            info += '<tr><td colspan="4" style="font-size:0.85em; color:gray;" class="truncate" title="'+child_semanticuri+'">'+child_semanticuri+'</td></tr>';
                        }
                        child_count++;
                    }
                }
            }
            if (child_count === 0) {
                info += '<tr><td colspan="4">No child terms found for this vocabulary or details unavailable.</td></tr>';
            }
            info += '</tbody></table></div>';
        }    

        // Displaying Vocabulary information
        let vocab_label = fld2('trm_Label', '15em'); // Vocabulary Name
        let vocab_concept_code = fld2('trm_ConceptID', '70px', '15px'); // Vocabulary Concept Code
        let vocab_domain = fld('trm_Domain') === 'relation' ? '<div class="item" style="width: 120px;">For Relations</div>' : '';

        let html = '<div class="recordDiv" style="min-height:16px"'
        +' id="rd'+recID+'" recid="'+recID+'">'
        + btn_actions
        + '<div class="recordTitle recordTitle2" title="'+fld('trm_Description') // Vocabulary description for tooltip
        +'" style="right:10px;left:75px">'
        +     vocab_label
        +     vocab_concept_code
        +     vocab_domain
        + '</div>'
        + info // Child terms table
        + '</div>';
        return html;
    },

    /**
     * Applies filters to the displayed entities (record types, detail types, or terms)
     * based on the current search text and "Show All" checkbox state.
     * This method is triggered by UI interactions such as typing in the filter input
     * or changing the state of the "Show All" checkbox, or when tabs are activated.
     * @private
     */
    _filterEntities: function(){
        let activeTabIndex = this.entity_wrapper.tabs('option', 'active'); // 0: rty, 1: dty, 2: trm
        let showAll = this.show_all.is(':checked');
        let searchText = this.general_search.val();
        let entityType = 'rty'; // Default to record types
        let targetPanelWidgetInstance; // jQuery object of the manageDef... widget panel

        if(activeTabIndex === 2){ // Terms tab
            entityType = 'trm';
            showAll = true; // For terms, always show all (includes existing ones marked with a check)
            targetPanelWidgetInstance = this.panel_trm_list;
        } else if(activeTabIndex === 1){ // Detail Types (Fields) tab
            entityType = 'dty';
            showAll = false; // For fields, default to hide already existing ones
            targetPanelWidgetInstance = this.panel_dty_list;
        } else { // Record Types tab
            targetPanelWidgetInstance = this.panel_rty_list;
        }

        // Forward the filter criteria to the respective child widget (manageDefRecTypes, manageDefDetailTypes, or manageDefTerms)
        // These child widgets are expected to have their own internal search/filter mechanisms.
        if (targetPanelWidgetInstance && targetPanelWidgetInstance.length) {
            let searchInputInPanel = targetPanelWidgetInstance.find('.searchForm #input_search, .searchForm .input_search'); // Common selector for search input in child widgets
            let showAllCheckboxInPanel = targetPanelWidgetInstance.find('.searchForm #chb_show_already_in_db'); // Common selector for checkbox in child widgets

            if (searchInputInPanel.length) {
                searchInputInPanel.val(searchText).trigger('keyup'); // Simulate user typing to trigger child's filter
            }
            if (showAllCheckboxInPanel.length) {
                showAllCheckboxInPanel.prop('checked', showAll).trigger('change'); // Simulate change to trigger child's filter
            }
        }

        // Adjust height of the active tab panel content to fill available space
        let activePanel = this.element.find(`#${entityType}_container`); // The direct container of the list (e.g. #rty_container)
        if (activePanel.length) {
            let parentHeight = activePanel.parent().height(); // Height of the #entity_wrapper (tabs widget)
            activePanel.height(parentHeight - 38); // Adjust for tab navigation bar (approx 38px)
        }
    },

    /**
     * Displays a confirmation dialog before proceeding with the import of definitions.
     * The message and options in the dialog vary based on the type of import (rectype, detailtype, term, or all).
     * It sets `_is_rename_target` and `_is_conservative` flags based on user choices in the dialog.
     * If the user proceeds, it calls {@link heurist.importStructure#startImport}.
     *
     * @private
     * @param {string} type - The type of definition being considered for import ('rectype', 'detailtype', 'term', 'all').
     * @param {number|string} id - The ID of the specific definition to import, or 'all' if cloning the entire database structure.
     */
    _preImportCheck: function(type, id){
        const that = this;
        let $dlg;
        let msg = '';
        let btns = {};
        let title = "Downloading structure";

        if(type === 'rectype' || type === 'all'){
            let points = '';
            if(type === 'rectype'){
                points = '<li>the selected record type, any fields, and vocabularies that are not yet in the database.</li>'
                + '<li>any unrecognised record types (and their fields and vocabularies) connected to the selected record type.</li>'
                + '<li>'
                    + 'additional fields and vocabularies defined for record types already in your database which are connected<br>'
                    + 'to any of the record types above (the fields will be added to the end of the record type and may be removed or<br>'
                    + 'customised as desired; they will have no effect on existing data).'
                + '</li>';
            }else{ // 'all'
                points = '<li><strong>ALL</strong> record types, fields and vocabularies/terms not yet in your database</li>'
                + '<li>additional fields may be added to existing record types - they will be added at the end and may be removed or customised as required</li>'
                + '<li>additional terms may be added to existing vocabularies</li>';
            }

            msg = 'If you proceed with download, Heurist will download three types of structural information:<br>'
            + '<ol>' + `${points}` + '</ol>'
            +'<p style="font-size:smaller">'
                +'<input type="checkbox" id="rename_target_entities" style="vertical-align: top;" />'
                +'<label for="rename_target_entities" style="display: inline-block;width: 90%;padding-left: 10px;">Check this box' // Adjusted width
                +' if you wish the record type names, field names and description, and term '
                +' labels to be replaced by the names and labels being imported. Use with care as this can overwrite existing '
                +'customisation with names which may be quite different and out-of-context with existing data. '
                +'If this is not a new database, we suggest cancelling and making a clone first (please'
                +' delete the clone once you are happy with the result of the import).</label>'
            +'</p>';

            if(type === 'all'){
                msg += '<p style="font-size:smaller">'
                    +'<input type="checkbox" id="import_new_rectypes_only" style="vertical-align: top;" checked="checked" />' // Default checked
                    +'<label for="import_new_rectypes_only" style="display: inline-block;width: 90%;padding-left: 10px;">Check this box'
                    +'  if you wish to import record types not yet in this database which are connected to the '
                    +' imported record type through record pointer or relationship marker fields.</label>'
                +'</p>';
            }
        }else if(type === 'detailtype'){
            title = "Downloading base field";
            let detailtypeData = (window.hWin.HEURIST4.remote && window.hWin.HEURIST4.remote.detailtypes && window.hWin.HEURIST4.remote.detailtypes.typedefs)
                ? window.hWin.HEURIST4.remote.detailtypes.typedefs[id] : null;

            let dtyType = detailtypeData ? detailtypeData.commonFields[window.hWin.HEURIST4.remote.detailtypes.typedefs.fieldNamesToIndex.dty_Type] : null;

            msg = '<p style="font-size:smaller">'
                +'<label><input type="checkbox" id="rename_target_entities"/>&nbsp;Check this box</label> '
                +' if you wish the field names and description '
                +' to be replaced by the names being imported. Use with care as this can overwrite existing '
                +'customisation. If this is not a new database, we suggest cancelling and making a clone first.</p>';

            if(dtyType && (dtyType === 'resource' || dtyType === 'enum' || dtyType === 'relmarker')){
                let extra = dtyType === 'resource' ? 'record type(s)' : (dtyType === 'enum' ? 'term(s)' : 'term(s) and record type(s)');
                msg = `If you proceed with the download, Heurist will also download missing related ${extra} for the selected field.<br>${msg}`;
            }
        }else if (type === 'term') { // Vocabulary import
            title = "Downloading vocabulary";
            msg = "If you proceed with the download, Heurist will download the selected vocabulary and any child terms within.<br>"
                + "Do you wish to continue?";
        }

        btns['Proceed'] = () => {
            that._is_rename_target = $dlg.find('#rename_target_entities').is(':checked');
            if (type === 'all') {
                 that._is_conservative = $dlg.find('#import_new_rectypes_only').is(':checked');
            } else {
                 that._is_conservative = false;
            }

            if(that._is_rename_target){
                let $dlg2, btn2 = {};
                btn2['Yes, overwrite'] = function(){
                    $dlg2.dialog('close'); $dlg.dialog('close'); that.startImport(id, type);
                };
                btn2['Get me out of here'] = function(){ $dlg2.dialog('close'); };
                $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg(
                    'Are you sure you want to overwrite existing record type names, field names and term labels?',
                    btn2, {title: 'Warning'}, { default_palette_class: 'ui-heurist-design' }
                );
            }else{
                $dlg.dialog('close'); that.startImport(id, type);
            }
        };
        btns['Cancel'] = () => { $dlg.dialog('close'); };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns,
            {title: title, yes:'Proceed', no:'Cancel'},
            {default_palette_class: 'ui-heurist-design', width: '60em'}
        );

        let $importNewRectypesOnlyCheckbox = $dlg.find('#import_new_rectypes_only');
        if ($importNewRectypesOnlyCheckbox.length) {
            let showWarningFlag = true;
            that._on($importNewRectypesOnlyCheckbox, {
                change: () => {
                    if(!showWarningFlag) return;
                    if(!$importNewRectypesOnlyCheckbox.is(':checked')){
                        let $wdlg, wbtns = {};
                        let warningMsg = 'Unchecking this means <strong>ALL</strong> connected record types, including those already in your database, might be affected or re-evaluated based on the source. This is generally not recommended unless you are trying to fully synchronize structures. <br><br>'
                                    + '<label for="confirm_choice_conservative"><input id="confirm_choice_conservative" type="checkbox"> I understand the implications and wish to proceed with this less conservative import.</label>';

                        wbtns[window.hWin.HR('Proceed with less conservative import')] = () => {
                            showWarningFlag = false;
                            $importNewRectypesOnlyCheckbox.prop('checked', false);
                            $wdlg.dialog('close');
                        };
                        wbtns[window.hWin.HR('Cancel and keep conservative')] = () => {
                            $importNewRectypesOnlyCheckbox.prop('checked', true);
                            $wdlg.dialog('close');
                        };

                        $wdlg = window.hWin.HEURIST4.msg.showMsgDlg(warningMsg, wbtns,
                            {title: 'Warning: Importing Connected Record Types'},
                            { default_palette_class: 'ui-heurist-design', dialogId: 'rectype-import-conservative-warning', width: '50em' }
                        );
                        let $proceedButton = $wdlg.parent().find('.ui-dialog-buttonset button:contains("Proceed")');
                        window.hWin.HEURIST4.util.setDisabled($proceedButton, true);
                        that._on($wdlg.find('#confirm_choice_conservative'), {
                            change: function() { window.hWin.HEURIST4.util.setDisabled($proceedButton, !$(this).is(':checked')); }
                        });
                    }
                }
            });
        }
    },

    /**
     * Processes the response from the server after a full database structure clone operation.
     * It takes the report from the response, which details added/updated record types,
     * detail types (fields), terms, and translations, and displays this information
     * in a tabbed dialog for the user.
     * After displaying the report, it triggers a refresh of the local entity data.
     *
     * @private
     * @param {object} response - The server response object from the `import_definitions` call.
     * @param {object} [response.report] - An object containing details of the import. Expected keys
     *                                     are `rectypes`, `detailtypes`, `terms`, `translations`,
     *                                     each holding HTML content (typically table rows) for the report.
     */
    _processCloneResponse: function(response){
        this._selectedDB = null; // Reset selected DB after clone

        if(!response.report || Object.keys(response.report).every(key => window.hWin.HEURIST4.util.isempty(response.report[key]))){
            window.hWin.HEURIST4.msg.showMsgDlg('No definitions imported.<br>All definitions available from the source database already exist in this database or no report was generated.');
            return;
        }

        let tabHeaders = '';
        let tabContents = '';
        const reportItems = {
            rectypes: {label: "Record types", content: response.report.rectypes},
            detailtypes: {label: "Base fields", content: response.report.detailtypes},
            terms: {label: "Terms", content: response.report.terms},
            translations: {label: "Translations", content: response.report.translations}
        };

        for (const key in reportItems) {
            if (!window.hWin.HEURIST4.util.isempty(reportItems[key].content)) {
                tabHeaders += `<li><a href="#${key}-tab">${reportItems[key].label}</a></li>`;
                // Assuming content is HTML table rows, wrap in a table
                tabContents += `<div id="${key}-tab" style="height: 600px; overflow-y: auto;"><h3>${reportItems[key].label}:</h3><br><table>${reportItems[key].content}</table><br></div>`;
            }
        }

        if (tabHeaders === '') { // Should not happen if first check passed, but good for safety
             window.hWin.HEURIST4.msg.showMsgDlg('No definitions were reported as imported.');
             return;
        }

        let msg = `<div id="handled-defs-tabs"><ul>${tabHeaders}</ul>${tabContents}</div>`;
        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, null,
            {title: 'Importing Template Results'},
            {default_palette_class: 'ui-heurist-design', height: 800, width: '70%'} // Adjusted width
        );

        if ($dlg.find('#handled-defs-tabs').length > 0) {
            $dlg.find('#handled-defs-tabs').tabs({
                heightStyle: "fill" // Make tabs fill the dialog height
            });
        }
        // Refresh all relevant entity data in the main application
        window.hWin.HAPI4.EntityMgr.refreshEntityData('rty,trm,dty,rst', null);
    }
});