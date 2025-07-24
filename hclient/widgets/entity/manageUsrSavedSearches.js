/**
* @file manageUsrSavedSearches.js
* @brief Manages User Saved Search entities.
* @fileOverview Provides a UI for users to manage their saved searches. This includes listing, (re)naming, updating, and deleting saved search configurations.
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/



/**
 * @widget heurist.manageUsrSavedSearches
 * @brief Widget for managing User Saved Searches.
 * @extends $.heurist.manageEntity
 * @description This widget allows users to manage their saved search configurations.
 * It supports listing saved searches, creating new ones (implicitly by saving a current search elsewhere),
 * editing their names and notes, and deleting them.
 *
 * @property {string} layout_mode Defines the overall layout structure, set to 'short'.
 * @property {boolean} use_cache If true, client-side caching might be used for data; set to false.
 * @property {boolean} edit_need_load_fullrecord If true, a full record load is required for editing saved search details; set to true.
 * @property {number} edit_height Default height for the edit dialog of a saved search, set to 640.
 * @property {number} height Default height of the widget, set to 640.
 * @property {number} width Default width of the widget. Adjusted based on `options.edit_mode` and `options.select_mode` (e.g., 790 in 'editonly', ~750 in selection modes).
 * @property {?number} svs_UGrpID If provided, the widget contextually manages saved searches for a specific user group. This can affect the title and filtering.
 */
$.widget( "heurist.manageUsrSavedSearches", $.heurist.manageEntity, {
   
    _entityName:'usrSavedSearches',

    /**
     * @brief Initializes the widget.
     * @override
     * @memberof heurist.manageUsrSavedSearches
     * Sets default options for layout mode, caching, dimensions, and other
     * configurations specific to managing user saved searches. It adjusts the width
     * based on `edit_mode` and `select_mode`.
     */
    _init: function() {
        
        this.options.layout_mode = 'short';
        this.options.use_cache = false;
       
        
       
        this.options.edit_need_load_fullrecord = true;
        this.options.edit_height = 640;
        this.options.height = 640;

        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 790;
           
        }else
        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                    
            //this.options.edit_mode = 'none'
        }
    
        this._super();
    },
    
    /**
     * @brief Initializes the controls for the widget.
     * @override
     * @memberof heurist.manageUsrSavedSearches
     * @returns {boolean} False if the parent `_initControls` fails, otherwise true.
     * Configures `options.resultList` with a custom header renderer.
     * Calls the parent `_initControls`. If in 'editonly' mode, it calls `_initEditorOnly` (assumed to be inherited).
     * Sets the widget title based on context (e.g., `options.svs_UGrpID`).
     * Initializes the search form (`searchUsrSavedSearches`) and sets up event listeners.
     */
    _initControls: function() {
        
        this.options.resultList = {
            view_mode: 'list',
            show_toolbar: false,  
            show_viewmode: false,  
            rendererHeader: function(){
                let s = '<div style="width:40px"></div>'
            +'<div style="width:12em;border:none;">Name</div>'
            +'<div style="width:12em;border-right:none;border-left:1px solid gray;">Notes</div>'
            +'<div style="position:absolute;width:7em;right:270px;border-right:none;border-left:1px solid gray">Group</div>'
                    
                    if (this.options.select_mode=='manager'){ // this.options refers to resultList options here
                        s = s+'<div style="position:absolute;right:4px;width:60px">Edit</div>';
                    }
                    
                    return s;
                }
        };
        
        
        if(!this._super()){
            return false;
        }

        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly(); // Assumed to be inherited or mixed in
            return;
        }

        let that = this;
        
        //update dialog title
        if(this.options.isdialog){ // &&  !this.options.title
            let title = null;
            let usr_ID = 0; // Corresponds to svs_UGrpID for context
            
            
            if(this.options.title){
                title = this.options.title;
            }else
            if(this.options.select_mode=='select_single'){
               title = 'Select Filter'; 
            }else
            if(this.options.select_mode=='select_multi'){
               title = 'Select Filters'; 
               
            }else
            if(this.options.svs_UGrpID>0){
                usr_ID = this.options.svs_UGrpID;
                title = 'Manage Saved Filters for Workgroup #'+this.options.svs_UGrpID+': '; 
            }else 
            {
                if(window.hWin.HAPI4.is_admin()){
                    title = 'Manage All Filters as Database Administrator';    
                }else{                    
                   
                    title = 'Manage Saved Filters';    
                }
            }
            
            if(usr_ID>0 && title){
                function __set_dlg_title(res){
                    if(res && res.status==window.hWin.ResponseStatus.OK){
                        that._as_dialog.dialog('option','title', title+res.data[usr_ID]);    
                    }
                } 
                window.hWin.HAPI4.SystemMgr.usr_names({UGrpID: usr_ID}, __set_dlg_title);
            }else{
                this._as_dialog.dialog('option','title', title);    
            }
        }
        
        // init search header
        this.searchForm.searchUsrSavedSearches(this.options);
        
        let iheight = 7;
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 6;
        }
        if(this.options.search_form_visible==false){
            iheight = 0;
        }
        
        
        this.searchForm.css({'height':iheight+'em',padding:'10px', 'min-width': '730px'});
        this.recordList.css({'top':iheight+0.5+'em', 'min-width': '730px'});
        //init viewer 
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }

        this._on( this.searchForm, {
                "searchusrsavedsearchesonresult": this.updateRecordList,
                "searchusrsavedsearchesonadd": function() { this.addEditRecord(-1); }
                });
        
        return true;
    }
    

    //----------------------
    /**
     * @brief Renders a single saved search item in the list.
     * @override
     * @memberof heurist.manageUsrSavedSearches
     * @param {HRecordSet} recordset The recordset containing the item.
     * @param {object} record The specific record object for the item to render.
     * @returns {string} HTML string representing the saved search item.
     * Formats the display of a saved search, including its name, an icon indicating
     * search type (e.g., simple, with rules), the group it belongs to, and notes.
     * Includes edit/delete buttons if in 'manager' select mode and 'popup' edit mode.
     */
    , _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            return '<div class="truncate" style="display:inline-block;'+swidth+'">'
                    +fld(fldname)+'</div>';
        }
        
        //rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq,rem_RecTitle
        //rem_ToWorkgroupName
        //rem_ToUserName        
        
        
        let recID   = fld('svs_ID');
        
        let qsearch = recordset.fld(record, 'svs_Query');
        let params = window.hWin.HEURIST4.query.parseHeuristQuery(qsearch);
        
        let iconBtn = 'ui-icon-search';
        if(params.type==3){ // Assuming type 3 is box query
            iconBtn = 'ui-icon-box';
        }else {
            if(params.type==1){ //withrules
                iconBtn = 'ui-icon-plus ui-icon-shuffle';
            }else if(params.type==2){ //rules only
                iconBtn = 'ui-icon-shuffle';
            }else  if(params.type<0){ //broken empty
                iconBtn = 'ui-icon-alert';
            }
        }
        
        let group_id = recordset.fld(record, 'svs_UGrpID');

        let group_name = (group_id==window.hWin.HAPI4.user_id())
                            ?window.hWin.HAPI4.currentUser['ugr_FullName']
                            :window.hWin.HAPI4.sysinfo.db_usergroups[group_id];
        
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
                + '<div class="recordSelector"><input type="checkbox" /></div>'
                + '<div class="recordIcons"><span class="ui-icon '+iconBtn+'"></span></div>'
                + fld2('svs_Name','39ex')
                + '<div class="truncate" style="display:inline-block;width:29ex">'
                    +group_name+'</div>'
                + '<div class="truncate" style="display:inline-block;width:30ex">'
                    +(window.hWin.HEURIST4.util.isempty(params.notes)?'':params.notes)+'</div>';
        
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
            html = html 
                + '<div class="rec_view_link logged-in-only" style="width:60px">'
                + '<div title="Click to edit filter" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                +'<div title="Click to delete filter" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
        }
        //<div style="float:right"></div>' + '<div style="float:right"></div>
        
        html = html + '</div>';

        return html;
        
    },
    
    /**
     * @brief Fetches full data for specified saved search record IDs.
     * @override
     * @memberof heurist.manageUsrSavedSearches
     * @param {string[]} arr_ids An array of saved search record IDs to fetch.
     * @param {number} pageno The page number for pagination.
     * @param {function} callback The function to call with the server response.
     * Constructs a request to search for 'usrSavedSearches' entities.
     * If `svs_UGrpID` is specified in the search form (context of a specific group),
     * it includes this group ID in the request.
     */
    _recordListGetFullData:function(arr_ids, pageno, callback){

        let request = {
                'a'          : 'search',
                'entity'     : this.options.entity.entityName,
                'details'    : 'list',
                'pageno'     : pageno,
                'db'         : this.options.database  
                
        };
        let svs_UGrpID = this.searchForm.find('#input_search_group').val();
        if(svs_UGrpID>0){
            request['svs_UGrpID'] = svs_UGrpID;
        }
        
        
        request[this.options.entity.keyField] = arr_ids;
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    },
    
    
    //-----
    /**
     * @brief Performs actions after the edit form for a saved search is initialized.
     * @override
     * @memberof heurist.manageUsrSavedSearches
     * Calls the parent's `_afterInitEditForm`.
     * The commented-out code suggests potential past functionality related to setting
     * group ID or hiding controls based on admin status/user ID, but it's currently inactive.
     */
    _afterInitEditForm: function(){

        this._super();
        /*
        var ugl_GroupID = this.searchForm.find('#input_search_group').val();
        if(ugl_GroupID>0 && !this._currentEditRecordset){ //insert       

            var ele = this._editing.getFieldByName('ugl_GroupID');
            ele.editing_input('setValue', ugl_GroupID);
            //hide save button
            if(this._toolbar){
                this._toolbar.find('.btnRecSave').css('visibility', 'visible');
            }
        }else
        //hide after edit init btnRecRemove for dbowner (user #2)
        if(this._currentEditID==2 || !window.hWin.HAPI4.is_admin()){
            var ele = this._toolbar;
            ele.find('.btnRecRemove').hide();
        }
        
        if(!window.hWin.HAPI4.is_admin()){
            var input_ele = this._editing.getFieldByName('ugr_Enabled');
            input_ele.hide();
           
        }
        */
    },    
    
    /**
     * @brief Handles events after a saved search record is saved.
     * @override
     * @memberof heurist.manageUsrSavedSearches
     * @param {number} recID The ID of the saved search.
     * @param {object} fieldvalues The saved field values.
     * If a new search was saved in 'select_single' mode, it selects it and closes.
     * If a new search was saved, it defaults `ugl_Role` to 'member' (though this seems like a leftover, as saved searches don't have user roles).
     * Calls the parent `_afterSaveEventHandler`.
     * If not in 'editonly' mode, updates the local recordset and refreshes the list.
     * Otherwise (in 'editonly' mode), closes the dialog.
     */
    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && this.options.select_mode=='select_single'){
            
                this._selection = new HRecordSet();
               
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;        
        }
        
        this._super( recID, fieldvalues );
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else{
            this.getRecordSet().setRecord(recID, fieldvalues);
            this.recordList.resultList('refreshPage');  
        }
    },
    
    /**
     * @brief Handles the deletion of a saved search, with a confirmation prompt.
     * @override
     * @memberof heurist.manageUsrSavedSearches
     * @param {boolean} [unconditionally=false] If true, deletes without confirmation.
     * If `unconditionally` is false (the default), it shows a confirmation dialog
     * asking "Are you sure you wish to delete this filter?". If confirmed, or if
     * `unconditionally` is true, it calls the parent's `_deleteAndClose` method.
     */
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this filter?', function(){ that._deleteAndClose(true); },
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
});
