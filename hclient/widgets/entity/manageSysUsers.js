/**
* @file manageSysUsers.js
* @brief Manages System User entities.
* @fileOverview Provides a UI for administrators to manage system user accounts. This includes creating users, editing user details (like name, email, password), assigning users to groups, and managing user status (active/inactive).
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
 * @widget heurist.manageSysUsers
 * @brief Widget for managing System User accounts.
 * @augments $.heurist.manageEntity
 * @description This widget provides an interface for administrators and authorized users
 * to manage system user accounts. It supports listing users, creating new users,
 * editing user details (including name, email, password - though password handling might be indirect),
 * assigning users to groups, and managing user status (enabled/disabled).
 *
 * @property {string} default_palette_class Default CSS class for theming, set to 'ui-heurist-admin'.
 * @property {string} layout_mode Defines the overall layout structure, set to 'short'.
 * @property {boolean} use_cache If true, client-side caching might be used for data; set to false.
 * @property {boolean} edit_need_load_fullrecord If true, a full record load is required for editing user details; set to true.
 * @property {number} edit_height Default height for the edit dialog of a user, set to 640.
 * @property {number} height Default height of the widget, set to 640.
 * @property {number} width Default width of the widget. Adjusted based on `options.edit_mode` and `options.select_mode` (e.g., 790 in 'editonly', ~750 in selection modes).
 * @property {?number} ugl_GroupID If provided, the widget contextually manages users for a specific group (e.g., listing members, adding users to this group).
 *                                 A negative value might indicate a mode for selecting users to add to the group.
 */
$.widget( "heurist.manageSysUsers", $.heurist.manageEntity, {
   
    _entityName:'sysUsers',
    
    _currentSaml: null,

    /**
     * @brief Initializes the widget.
     * @override
     * @memberof heurist.manageSysUsers
     * Sets default options for palette class, layout mode, dimensions, and other
     * configurations specific to managing system user accounts. It adjusts the width
     * based on the `edit_mode` and `select_mode`.
     */
    _init: function() {
        
        this.options.default_palette_class = 'ui-heurist-admin';
        
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
     * @memberof heurist.manageSysUsers
     * @returns {boolean} False if the parent `_initControls` fails, otherwise true.
     * Handles different initialization paths based on `options.edit_mode`.
     * If not 'editonly', it sets up the widget title (contextualized by `options.ugl_GroupID`),
     * initializes the search form (`searchSysUsers`), and configures the record list,
     * including a custom header and event listeners for role changes and group assignments.
     * If 'editonly', it calls `_initEditorOnly` (which is not defined in this snippet but assumed to exist or be inherited).
     */
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
        
        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly(); // Assumes _initEditorOnly() is defined in parent or will be mixed in.
            return;
        }
        
        //update dialog title
        let title = null;
        let usr_ID = 0;
        let that = this;
        
        
        if(this.options.title){
            title = this.options.title;
        }else
        if(this.options.select_mode=='select_single'){
           title = 'Select User'; 
        }else
        if(this.options.select_mode=='select_multi'){
           title = 'Select Users'; 
          
          if(this.options.ugl_GroupID<0){ 
                usr_ID = Math.abs(this.options.ugl_GroupID);
                title += ' to add to Workgroup #'+usr_ID+': ';
          }
           
        }else
        if(this.options.ugl_GroupID>0){
            usr_ID = this.options.ugl_GroupID;
            title = 'Manage Users of Workgroup #'+this.options.ugl_GroupID+': ';
        }else /*if(this.options.ugl_GroupID<0){
            usr_ID = Math.abs(this.options.ugl_GroupID);
            title = 'Select Users to add to Workgroup #'+usr_ID+': ';
        }else*/
        {
            if(window.hWin.HAPI4.is_admin()){
                title = 'Manage All Users as Database Administrator';    
            }else{                    
               
                title = 'Manage Users';    
            }
        }
        
        if(usr_ID>0 && title){
            function __set_dlg_title(res){
                if(res && res.status==window.hWin.ResponseStatus.OK){
                    that.setTitle( title+res.data[usr_ID] );    
                }
            } 
            window.hWin.HAPI4.SystemMgr.usr_names({UGrpID: usr_ID}, __set_dlg_title);
        }else{
            this.setTitle( title );    
        }
        
        // init search header
        this.searchForm.searchSysUsers(this.options);
        
        let iheight = 7;
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 6;
        }
        if(this.options.subtitle){
            iheight = iheight + 1.5;
        }
        
        this.searchForm.css({'height':iheight+'em',padding:'10px', 'min-width': '730px'});
        this.recordList.css({'top':iheight+0.5+'em', 'min-width': '730px'});
        //init viewer 
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
            
            let center_cols = 'border-left:1px solid gray;text-align:center;';
            this.recordList.resultList('option','rendererHeader',
                    function(){

                        let s = '<div style="display: flex;">'
                                    +'<div style="width:25px;border:none;"></div>'
                                    +'<div style="flex:0 2 3.5em;border-left:1px solid gray;padding-left:5px;">ID</div>'
                                    +'<div style="flex:0 1 8em;border-left:1px solid gray;padding-left:5px;">Name</div>'
                                    +'<div style="flex:0 3 10em;border-left:1px solid gray;padding-left:5px;">Full name</div>';

                        if (window.hWin.HAPI4.is_admin()){
                            s += `<div style="flex:0 0 4.5em;${center_cols}">Edit</div>`;
                            s += `<div style="flex:0 0 6em;${center_cols}">${!that.options.ugl_GroupID ? 'Delete' : 'Remove'}</div>`;
                        }

                        s += `<div style="flex:0 1 8em;${center_cols}">Membership</div>`;

                        if(!that.options.ugl_GroupID){
                            s += `<div style="flex:0 0 10em;${center_cols}">Edit membership</div>`;
                        }

                        s += '<div style="flex:0 5 20em;border-left:1px solid gray;padding-left: 10px;">Institution/Organisation</div>';
                        s += '</div>';
                        
                        return s;
                    }
                );
           
        }

        this._on( this.searchForm, {
            "searchsysusersonresult": this.updateRecordList,
            "searchsysusersonadd": function() { this.addEditRecord(-1); },
            "searchsysusersonfind": function() { 

                let ugl_GroupID = this.searchForm.find('#input_search_group').val(); 

                let options = {select_mode: 'select_multi',
                        ugl_GroupID: -ugl_GroupID,
                        edit_mode:'none',
                        title: ("Select Users to add to Workgroup #"+ugl_GroupID),
                        onselect:function(event, data){
                
                            if(data && window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection))
                            {
                                let request = {};
                                request['a']        = 'action'; //batch action
                                request['entity']   = 'sysGroups';
                                request['role']     = 'member';
                                request['userIDs']  = data.selection;
                                request['groupID']  = ugl_GroupID;
                                request['request_id'] = window.hWin.HEURIST4.util.random();
                                
                                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                    function(response){
                                        if(response.status == window.hWin.ResponseStatus.OK){
                                            //reload
                                            that.searchForm.searchSysUsers('startSearch');
                                            
                                            if(data.selection.indexOf(window.hWin.HAPI4.currentUser['ugr_ID'])>=0){
                                                window.hWin.HAPI4.currentUser['ugr_Groups'][ugl_GroupID] = 'member';
                                                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                                            }
                                            
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);      
                                        }
                                    });
                            
                            }                                    
                        }};                    
                
                window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options);
            }
        });

        this._on( this.recordList, {
            "resultlistonpagerender": function(event){

                //init role dropdown selector
                this.recordList.find('select.user-role')
                .each(function(idx,item){$(item).val($(item).attr('data-value'))})
                .on('change', function(event){

                    let ugl_GroupID = that.searchForm.find('#input_search_group').val(); 
                    if(!(ugl_GroupID>0)) return;
                    /*if(!(ugl_GroupID>0)) {
                        
                        this.recordList.find('.user-list-edit')
                        .each(function(idx,item){
                            $(item).attr('title','Edit user membership');
                        })
                        .on('click', function(event){
                            alert('Need open group mgr')
                        });
                        return;   
                    } */

                    //apply new role to user
                    
					let selector = $(event.target);
                    let usr_ID = selector.parents('.recordDiv').attr('recid');  
                    let newRole = selector.val();

                    let request = {};
                    request['a']        = 'action';
                    request['entity']   = 'sysGroups';
                    request['role']     = newRole;
                    request['userIDs']  = usr_ID;
                    request['groupID']  = ugl_GroupID;
                    request['request_id'] = window.hWin.HEURIST4.util.random();

                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){             
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //reload
                               
                                if(newRole=='remove'){
                                    let recset = that.recordList.resultList('getRecordSet');
                                    recset.removeRecord(usr_ID);
                                    that.recordList.resultList('refreshPage');  
                                    window.hWin.HEURIST4.msg.showMsgFlash('User removed from group');
                                }else{
                                    selector.attr('data-value', newRole);
                                    window.hWin.HEURIST4.msg.showMsgFlash('New role applied');      
                                }

                                if(usr_ID==window.hWin.HAPI4.currentUser['ugr_ID']){
                                    if(newRole=='remove'){
                                        window.hWin.HAPI4.currentUserRemoveGroup(ugl_GroupID);
                                    }else{
                                        window.hWin.HAPI4.currentUser['ugr_Groups'][ugl_GroupID] = newRole;
                                    }
                                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                                }
                                
                            }else{
                                //restore current value
                                selector.val( selector.attr('data-value') );
                                window.hWin.HEURIST4.msg.showMsgErr(response);      
                            }
                    });
                });

                //manage membership of user in all groups
                this.recordList.find('.edit-members')
                .on('click', function(event){
                    let user_ID = $(event.target).parents('.recordDiv').attr('recid');
                    let enabled = $(event.target).parents('.recordDiv').attr('usr_status');
                    
                    if (enabled=='n')
                    {
                        window.hWin.HEURIST4.msg.showMsgDlg('You need to enable the user before assigning them a group');
                        return;                        
                    }

                    let options = {select_mode: 'manager',
                        ugl_UserID: user_ID,
                        edit_mode:'popup',
                        title: ("Manage Membership for User #"+user_ID),
                        //before close - count for membership and refresh
                        beforeClose:function(){

                            let membership_popup = this;
                            //count user memebrship in groups
                            let request = {
                                'a'          : 'search',
                                'entity'     : 'sysUsers',
                                'details'    : 'count',
                                'ugr_ID'     : user_ID
                            };
                            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        let resp = new HRecordSet( response.data );
                                        let rec_updated = resp.getFirstRecord();
                                        let cnt = resp.fld(rec_updated, 'ugr_Member');
                                        if(cnt>0){
                                            let record = that.getRecordSet().getById(user_ID);
                                            that.getRecordSet().setFld(record, 'ugr_Member', cnt);
                                            that.recordList.resultList('refreshPage');
                                            $(membership_popup).manageSysGroups('closeDialog', true);
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr({
                                                message: 'User must belong to one group at least',
                                                error_title: 'Select a workgroup'
                                            });
                                        }
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                }
                            );
                            return false;
                        }
                    };

                    window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', options);
                });


            }
        });

        return true;
    }
    

    //----------------------
    /**
     * @brief Renders a single system user item in the list.
     * @override
     * @memberof heurist.manageSysUsers
     * @param {HRecordSet} recordset The recordset containing the item.
     * @param {object} record The specific record object for the item to render.
     * @returns {string} HTML string representing the user item.
     * Formats the display of a user, including their ID, name (as a mailto link if email exists),
     * full name, status icon (enabled/disabled), and action buttons (edit, delete/remove from group)
     * based on permissions and context (`options.ugl_GroupID`). It also shows group membership
     * information or controls for role assignment within a group.
     */
    , _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, sstyle){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(sstyle)){
                swidth = ` style="${sstyle}"`;
            }
            return `<div class="truncate" ${swidth}>${window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))}</div>`;
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        const recID = fld('ugr_ID');

        let recOpacity = (fld('ugr_Enabled')!='n')?1:0.3;

        let rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');

        let recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb', 2, this.options.database);

        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:'+recOpacity+'">'
        +'</div>';

        let name_width = navigator.userAgent.toLowerCase().includes('firefox') ? 7.5 : 8.5;

        let mail_link = !window.hWin.HEURIST4.util.isempty(fld('ugr_eMail')) ? ` href="mailto:${fld('ugr_eMail')}" ` : '';

        let user_content = window.hWin.HEURIST4.util.isempty(fld('ugr_Name'))
            ? ''
            : `<a class="truncate" style="flex:0 1 ${name_width}em"${mail_link}title="${fld('ugr_eMail')}">${fld('ugr_Name')}</a>`;

        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" usr_status="'+fld('ugr_Enabled')+'" style="display: flex;">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox"></div>'
        + '<div class="recordIcons" style="flex:0 0 20px;">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);opacity:'+recOpacity+'">'
        + '</div>'
        + fld2('ugr_ID','flex:0 2 4em;')
        + user_content
        + '<div class="truncate" style="flex:0 3 10em;">'+fld('ugr_FirstName')+' '+fld('ugr_LastName')+'</div>';

        let show_buttons = this.options.edit_mode !== 'none';

        // add edit/remove action buttons
        if(recID == 2 && window.hWin.HAPI4.has_access(2) && show_buttons){ // only db owner can edit db owner

            html += '<div title="Click to edit user" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" '
                  + 'style="height:16px;margin: 0px 15px;flex:0 0 25px;">'
                    +'<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                + '<div style="width: 75px;"></div>';

        }else
        if(window.hWin.HAPI4.is_admin() && show_buttons){//current user is admin of database managers

            let icon = !this.options.ugl_GroupID ? 'circle-close' : 'arrowrefresh-1-n';
            let action = !this.options.ugl_GroupID ? 'delete' : 'remove';

            html += '<div title="Click to edit user" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" '
                  + 'style="height:16px;margin: 0px 15px;flex:0 0 25px;">'
                    + '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                + `<div title="Click to ${action} user" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="${action}" `
                  + 'style="height:16px;margin: 0px 25px;flex:0 0 25px;">'
                    + `<span class="ui-button-icon-primary ui-icon ui-icon-${icon}"></span><span class="ui-button-text"></span>`
                + '</div>';

        }

        // add edit group memberships
        if(this.options.select_mode=='manager' && show_buttons){
        
            const ugl_GroupID = this.searchForm.find('#input_search_group').val(); 
            if(window.hWin.HAPI4.is_admin() && !(ugl_GroupID>0)){  //all groups - show count of groups where user is a member
                html += '<div class="truncate" style="flex: 0 1 8em;text-align: center;">'
                            + fld('ugr_Member') 
                    + '</div>'
                    + '<div class="edit-members ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" style="height:16px;margin: 0px 60px;" '
                      + 'title="Edit participation of user in groups">'
                        + '<span class="ui-icon ui-icon-pencil"></span>'
                    + '</div>';
            }

            if(ugl_GroupID>0){

                html += '<div class="rec_actions user-list" style="flex:0 1 8em;">';

                if(recID==2 && ugl_GroupID==window.hWin.HAPI4.sysinfo.db_managers_groupid){
                    html += '<div style="min-width:88px;text-align:center">admin</div>';
                }else 
                if(window.hWin.HAPI4.has_access(ugl_GroupID)){ // current user is admin of given group

                    html += '<select title="Role" style="min-width:70px;text-align:center;margin-right:18px;" class="user-role" data-value="'
                                + fld('ugl_Role')+'">'
                                +'<option>admin</option><option>member</option><option>remove</option></select>';

                }else{

                    html += '<div title="Role" style="min-width:88px;text-align:center">'
                                + fld('ugl_Role')+'</div>';
                }

                html += '</div>';
            }

        }
        
        html += '<div class="truncate" style="flex: 0 5 20em;">'+fld('ugr_Organisation')+'</div>';

        html += '</div>';

        return html;
        
    },

    /**
     * @brief Handles actions triggered from the record list, specifically the 'remove' action in group context.
     * @override
     * @memberof heurist.manageSysUsers
     * @param {Event} event The event object.
     * @param {object} action The action object, expected to have `recID` (user ID) and `action` type.
     * @returns {boolean} True if the action was handled, otherwise false or result of super call.
     * If the action is 'remove' and `options.ugl_GroupID` is set (managing users for a specific group),
     * it triggers a change on the role selector for that user to 'remove', effectively initiating
     * their removal from the group.
     */
    _onActionListener: function(event, action){

        let is_resolved = this._super(event, action);

        if(is_resolved){
            return true;
        }else if(!window.hWin.HEURIST4.util.isObject(action)){
            return false;
        }

        let usr_ID = action.recID;
        action = action.action;

        if(action == 'remove' && this.options.ugl_GroupID > 0){

            let $select = this.recordList.find(`.recordDiv[recID="${usr_ID}"] select.user-role`);
            $select.val('remove').trigger('change');

            is_resolved = true;
        }

        return is_resolved;
    },

    /**
     * @brief Fetches full data for specified user record IDs, potentially including group role information.
     * @override
     * @memberof heurist.manageSysUsers
     * @param {string[]} arr_ids An array of user record IDs to fetch.
     * @param {number} pageno The page number for pagination.
     * @param {function} callback The function to call with the server response.
     * Constructs a request to search for 'sysUsers' entities. If `ugl_GroupID` is specified
     * in the search form (i.e., viewing users within a specific group context), it includes
     * this group ID in the request to fetch role information for users within that group.
     */
    _recordListGetFullData:function(arr_ids, pageno, callback){

        let request = {
            'a': 'search',
            'entity': this.options.entity.entityName,
            'details': 'list',
            'pageno': pageno,
            'db': this.options.database                
        };
        let ugl_GroupID = this.searchForm.find('#input_search_group').val();
        if(ugl_GroupID>0){
            request['ugl_GroupID'] = ugl_GroupID;
        }
        
        request[this.options.entity.keyField] = arr_ids;
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    },
    
    
    //-----
    /**
     * @brief Performs actions after the edit form for a user is initialized.
     * @override
     * @memberof heurist.manageSysUsers
     * Calls the parent's `_afterInitEditForm`.
     * If adding a new user in the context of a specific group (`ugl_GroupID > 0`),
     * it pre-fills and potentially hides the group assignment field.
     * It hides the "Remove" button if editing the DB owner (user ID 2) or if the current
     * user is not an admin.
     * It adds a "Transfer Ownership" button if the current user is the DB owner (user ID 2)
     * and is not editing their own account.
     * It hides the "Enabled" field if the current user is not an admin or is editing their own account.
     * It also initializes SAML service provider selection an related fields.
     */
    _afterInitEditForm: function(){

        this._super();

        let ugl_GroupID = this.searchForm.find('#input_search_group').val();
        if(ugl_GroupID>0 && !this._currentEditRecordset){ //insert       

            let ele = this._editing.getFieldByName('ugl_GroupID');
            ele.editing_input('setValue', ugl_GroupID);
            //hide save button
            if(this._toolbar){
                this._toolbar.find('.btnRecSave').css('visibility', 'visible');
            }
        }else
        //hide after edit init btnRecRemove for dbowner (user #2)
        if(this._currentEditID==2 || !window.hWin.HAPI4.is_admin()){
            let ele = this._toolbar;
            ele.find('.btnRecRemove').hide();
        }
        
        let btnTrOwner = this._toolbar.find('.btnTransferOwnership');
        
        if(this._currentEditID>0 && this._currentEditID!=2 && window.hWin.HAPI4.user_id()==2){
            //add ownershup transfer button
            if(btnTrOwner.length==0){
                btnTrOwner = $('<button class="btnTransferOwnership">')
                        .appendTo(this._toolbar);
            }
            btnTrOwner.button({
                         label:'Transfer Ownership',icon:'ui-icon-transfer-e-w'})
                        .css({'float':'left',margin:'.5em .4em 0 .5em'}).show();
                        
            this._on(btnTrOwner, {click:this._transferDBOwner});
        }else{
            if(btnTrOwner) btnTrOwner.hide();
        }
        
        if(!window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.currentUser['ugr_ID']==this._currentEditID){
            let input_ele = this._editing.getFieldByName('ugr_Enabled');
            input_ele.hide();
           
        }
        
        //fill SAML service providers list
        let saml_sel = this._editing.getFieldByName('ugl_SpID').find('select');
        let has_saml = false;
        if($.isPlainObject(window.hWin.HAPI4.sysinfo.saml_service_provides)){
            let sp_keys = Object.keys(window.hWin.HAPI4.sysinfo.saml_service_provides);
            if(sp_keys.length>0){
                saml_sel.empty();
                
                for(let id of sp_keys){
                    window.hWin.HEURIST4.ui.addoption(saml_sel[0],id,window.hWin.HAPI4.sysinfo.saml_service_provides[id]);
                    has_saml = true;
                }
                                       
            }
        }
        
        this._currentSaml = null;
        if(has_saml){
            window.hWin.HEURIST4.ui.initHSelect(saml_sel[0], false);
            this._on(saml_sel, {change:function(event){
               //save current and load new one
               this._Saml_from_UI( this._currentSaml );
               
               //assign new one
               this._Saml_To_UI( $(event.target).val() );
            }});

            this._editing.setModified(0); // avoid triggering modified flag

            saml_sel.trigger('change');

        }else{
            let content = saml_sel.parents('.ui-accordion-content');
            content.hide();
            this.editForm.find('#'+content.attr('aria-labelledby')).hide(); // .ui-accordion-header 
        }

        this._setupORCIDField();
    },
    
    /**
     * @brief Saves SAML configuration from UI fields to the 'usr_ExternalAuthentication' field.
     * @memberof heurist.manageSysUsers
     * @param {?string} saml_id The ID of the SAML service provider whose settings are being saved.
     * If `saml_id` is provided, this method reads the 'ugl_SpUID' (SAML User ID) and
     * 'ugl_SpMail' (Use SAML email) fields from the form. It updates the JSON object
     * stored in 'usr_ExternalAuthentication' for the given `saml_id`. If both UID and
     * mail flag are empty/default, the entry for this SAML provider is removed.
     */
    _Saml_from_UI: function( saml_id ){
       
       if(saml_id){
           let auth = this._editing.getValue('usr_ExternalAuthentication');
           auth = window.hWin.HEURIST4.util.isJSON(auth);
           if(!auth) auth = {};
           
           let uid = this._editing.getValue('ugl_SpUID');
           let mail = this._editing.getValue('ugl_SpMail');

           if(uid!='' || mail=='y'){
               //replace SP
               auth[saml_id] = {uid:uid, mail:mail};
           }else if (auth[saml_id]){
               //remove SP
               auth[saml_id] = null;
               delete auth[saml_id];
           }
           
           this._editing.setFieldValueByName('usr_ExternalAuthentication', JSON.stringify(auth));
       }
    },
    
    /**
     * @brief Populates SAML configuration UI fields based on stored values in 'usr_ExternalAuthentication'.
     * @memberof heurist.manageSysUsers
     * @param {?string} saml_id The ID of the SAML service provider whose settings are to be loaded into the UI.
     * Sets `this._currentSaml` to `saml_id`.
     * Reads the JSON object from 'usr_ExternalAuthentication'. If an entry exists for `saml_id`,
     * it populates the 'ugl_SpUID' and 'ugl_SpMail' form fields with the stored values.
     * Otherwise, it clears these fields or sets them to default.
     */
    _Saml_To_UI: function( saml_id ){
        
       this._currentSaml = saml_id;

       let auth = this._editing.getValue('usr_ExternalAuthentication');

       auth = window.hWin.HEURIST4.util.isJSON(auth);
       if(auth && auth[saml_id]){
            this._editing.setFieldValueByName('ugl_SpUID', auth[saml_id]['uid'], false);
            this._editing.setFieldValueByName('ugl_SpMail', auth[saml_id]['mail'], false);
       }else{
            this._editing.setFieldValueByName('ugl_SpUID', '', false);
            this._editing.setFieldValueByName('ugl_SpMail', 'n', false);
       }
        
    },               
    
    /**
     * @brief Retrieves and validates form values, including SAML settings.
     * @override
     * @memberof heurist.manageSysUsers
     * @returns {object|null} The validated field values.
     * Calls `_Saml_from_UI` to ensure the latest SAML settings from the UI are captured
     * into the 'usr_ExternalAuthentication' field before calling the parent's
     * `_getValidatedValues` method.
     */
    _getValidatedValues: function(){

        this._Saml_from_UI( this._currentSaml );

        const ORCID = this._editing.getValue('ugr_ORCID');
        if(!window.hWin.HEURIST4.util.isempty(ORCID) && !ORCID.match(/^\d{4}-\d{4}-\d{4}-\d{4}$/)){
            this._editing.getFieldByName('ugr_ORCID').editing_input('showErrorMsg', 'Invalid ORCID, must be in format of XXXX-XXXX-XXXX-XXXX');
            return null;
        }

        return this._super();
    },
    
    
    /**
     * @brief Handles events after a user record is saved.
     * @override
     * @memberof heurist.manageSysUsers
     * @param {number} recID The ID of the saved user.
     * @param {object} fieldvalues The saved field values.
     * If a new user was added in 'select_single' mode, it selects the new user and closes.
     * If a new user was added, it sets their default role to 'member'.
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
        if (this._currentEditID<0) {
            fieldvalues['ugl_Role'] = 'member';    
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
     * @brief Handles the deletion of a user account, with a confirmation prompt.
     * @override
     * @memberof heurist.manageSysUsers
     * @param {boolean} [unconditionally=false] If true, deletes without confirmation.
     * If `unconditionally` is false (the default), it shows a confirmation dialog
     * asking "Are you sure you wish to delete this user?". If confirmed, or if
     * `unconditionally` is true, it calls the parent's `_deleteAndClose` method.
     */
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this user?', function(){ that._deleteAndClose(true); },
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },

    /**
     * @brief Handles the transfer of database ownership to the currently edited user.
     * @memberof heurist.manageSysUsers
     * @param {boolean} [unconditionally=false] If true, proceeds with the transfer without confirmation.
     * This function is typically triggered by a button available only to the current DB owner (user ID 2)
     * when editing another enabled user's account.
     * - Checks if the current record has unsaved modifications or if the target user is disabled, showing alerts if so.
     * - If `unconditionally` is false, it displays a warning dialog about the implications of ownership transfer
     *   and the need to log out and reload Heurist.
     * - If confirmed (or `unconditionally` is true), it sends a request to the server
     *   (`transferOwner` action on `sysUsers` entity) to perform the ownership change.
     * - On success, calls `_afterTransferOwnerHandler`.
     */
    _transferDBOwner: function(unconditionally){

        if(this._currentEditID==null || this._currentEditID<1) return;

        const isEnabled = this._editing.getValue('ugr_Enabled');
        const isModified = this._editing.isModified();
        let that = this;

        if(isModified){

            window.hWin.HEURIST4.msg.showMsgDlg('Please save or revert any changes made to this user to transfer ownership');
            return;
        }else if(isEnabled == 'n'){
            
            window.hWin.HEURIST4.msg.showMsgDlg('Unable to transfer database ownership to an disabled account.<br>Please enable it to transfer ownership');
            return;
        }

        if(unconditionally===true){
            
            const request = {
                'a': 'action',
                'transferOwner': true,
                'entity'     : this.options.entity.entityName,
                'request_id' : window.hWin.HEURIST4.util.random(),
                'recID'      : this._currentEditID 
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        let recID = that._currentEditID;
                        that._afterTransferOwnerHandler(recID);
                    
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
            
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to transfer the ownership of this database to the selected user? This action can only be undone by the new owner.<br>'
                +' <p style="font-size: 1.1em; font-weight: bold;">'
                + 'Note: Heurist will need to logout and reload once the changes have been made, ensure you save and complete any additional tasks before proceeding.</p>',
                function(){ that._transferDBOwner(true); },
                {title:'Warning',yes:'Proceed',no:'Cancel'});
        }
    },
    
    /**
     * @brief Handles actions after database ownership has been successfully transferred.
     * @memberof heurist.manageSysUsers
     * @param {number} recID The ID of the user who is now the new database owner.
     * Displays a success message indicating that ownership has been transferred and
     * that Heurist will now refresh. It then calls `HAPI4.SystemMgr.logout`
     * with a callback to reload the page, effectively forcing a new session login
     * which is necessary for the ownership change to take full effect system-wide.
     */
    _afterTransferOwnerHandler: function(recID){

        window.hWin.HEURIST4.msg.showMsgFlash(this.options.entity.entityTitle + ' ' + window.hWin.HR('ownership has been transfered') + '.'
            +'<br>Heurist will now refresh to set these changes.', 2000); // flash message

        /* Trigger Logout and Reload */
        window.hWin.HAPI4.SystemMgr.logout(
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    window.location.reload();  // page reload
                }
            }
        );
    },

    /**
     * @brief Setup additional controls for ugr_ORCID field.
     * @memberof heurist.manageSysUsers
     */
    _setupORCIDField: function(){

        let $orcidField = this._editing.getFieldByName('ugr_ORCID');

        if(!$orcidField || $orcidField.find('.orcid-link').length > 0){
            return;
        }

        // Link to ORCID.org
        $('<a>', {
            href: 'https://orcid.org',
            target: '_blank',
            class: 'fake_link orcid-link',
            style: 'display: block; font-size: smaller;',
            text: 'https://orcid.org'
        }).appendTo($orcidField.find('.header'));

        // Link to retrieve ORCID data
        let $getData = $('<span>', {
            class: 'fake_link get-orcid-details',
            style: 'font-size: smaller; padding-left: 30px; display: none;',
            text: 'retrieve data from ORCID'
        }).appendTo($orcidField.find('.input-div'));

        let $orcidInput = $orcidField.find('input');
        this._on($orcidInput, {
            keyup: () => {
                const currentValue = $orcidInput.val();
                currentValue !== '' ? $getData.show() : $getData.hide();
            }
        });
        if($orcidInput.val() !== ''){
            $getData.show();
        }

        this._on($getData, {
            click: () => {
                this._getDataFromORCID();
            }
        });
    },

    /**
     * @brief Lookup ORCID data and map back to entity fields.
     * @memberof heurist.manageSysUsers
     */
    _getDataFromORCID: function(){

        const ORCID = this._editing.getValue('ugr_ORCID');

        if(window.hWin.HEURIST4.util.isempty(ORCID)){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter an ORCID...', 3000);
            $getData.hide();
            return;
        }else if(!ORCID.match(/^\d{4}-\d{4}-\d{4}-\d{4}$/)){
            let error = {
                message: 'Invalid ORCID identifier provided.<br>It must be in the format of 0000-1111-2222-3333.',
                status: window.hWin.ResponseStatus.INVALID_REQUEST
            };
            window.hWin.HEURIST4.msg.showMsgErr(error);
            return;
        }

        let request = {
            serviceType: 'orcid',
            id: ORCID
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog?.closest('div[role="dialog"]'));

        window.hWin.HAPI4.RecordMgr.lookupService(request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            response = window.hWin.HEURIST4.util.isJSON(response);
            if(!response || Object.hasOwn(response, 'status') && response.status !== window.hWin.ResponseStatus.OK){
                response = !response ? {status: 'error', message: 'Heurist has failed to retrieve your data from ORCID.<br>Please submit a ticket.'} : response;
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            this._handleDataFromORCID(response);
        });
    },

    /**
     * @brief Process and display ORCID lookup data for the user.
     * @memberof heurist.manageSysUsers
     * @param {object} orcidData ORCID record data formatted from external lookup
     */
    _handleDataFromORCID: function(orcidData){

        // sysUGrps fields to ORCID lookup fields
        const ORCID_FIELD_MAPPING = {
            ugr_FirstName: 'given-names',
            ugr_LastName: 'family-name',
            ugr_Name: 'orcid',
            ugr_Organisation: 'employment',
            ugr_Interests: 'keywords'
        };

        if(!window.hWin.HEURIST4.util.isJSON(orcidData)){
            return;
        }

        // Setup table content
        let tableContent = '';
        for(const formField in ORCID_FIELD_MAPPING){

            if(!Object.hasOwn(ORCID_FIELD_MAPPING, formField)){
                continue;
            }

            const orcidField = ORCID_FIELD_MAPPING[formField];
            if(window.hWin.HEURIST4.util.isempty(orcidData[orcidField])){
                continue;
            }

            let fieldValue = this._editing.getValue(formField);
            fieldValue = window.hWin.HEURIST4.util.isempty(fieldValue) ? '' : fieldValue;
            let orcidValue = orcidData[orcidField];

            const fieldOption = fieldValue === ''
                ? '<em>No value</em>'
                : `<input type="radio" name="${formField}" value="0" style="vertical-align:top;" checked="checked" /> 
                <span style="display:inline-block;max-width:16em;padding-top:2px;cursor:default;" class="truncate">${fieldValue}</span>`;
            const checkboxImport = fieldValue === '' ? 'checked="checked" ' : '';

            const fieldIndex = this.getEntityFieldIdx(formField);
            tableContent += `<tr>
                <td style="padding-bottom: 10px;"><strong>${this.options.entity.fields[fieldIndex].dtFields['rst_DisplayName']}</strong></td>
                <td style="padding-bottom: 10px;" title="${fieldValue}">${fieldOption}</td>
                <td style="padding-bottom: 10px;" title="${orcidValue}">
                    <input type="radio" name="${formField}" value="1" style="vertical-align: top;" ${checkboxImport}/> 
                    <span style="display:inline-block;max-width:20em;padding-top:2px;cursor:default;" class="truncate">${orcidValue}</span>
                </td>
            </tr>`;
        }

        if(tableContent === ''){
            window.hWin.HEURIST4.msg.showMsgFlash('Heurist could not find any public data it could use for this form.', 5000);
            return;
        }

        // Setup dialog
        let $dlg;
        let content = `<div>
            Below is a list of data that Heurist has retrieved from your public ORCID record.<br><br>
            <table>
                <thead>
                    <tr>
                        <th style="width: 12.5em; padding: 7.5px 0px;">Field</th>
                        <th style="width: 20em; padding: 7.5px 0px;">Current values <span class="fake_link" style="font-size: smaller;">select all</span></th>
                        <th style="width: 30em; padding: 7.5px 0px;">ORCID values <span class="fake_link" style="font-size: smaller;">select all</span></th>
                    </tr>
                </thead>
                <tbody>
                    ${tableContent}
                </tbody>
            </table>
        </div>`;

        let btns = {};
        btns[window.hWin.HR('Import selected data')] = () => {

            for(const formField in ORCID_FIELD_MAPPING){

                if(!Object.hasOwn(ORCID_FIELD_MAPPING, formField)){
                    continue;
                }

                const option = $dlg.find(`input[name="${formField}"]:checked`);
                if(option.val() !== '1'){ // retain original value
                    continue;
                }

                const orcidField = ORCID_FIELD_MAPPING[formField];
                if(window.hWin.HEURIST4.util.isempty(orcidData[orcidField])){
                    continue;
                }

                this._editing.setFieldValueByName(formField, orcidData[orcidField]);
            }

            $dlg.dialog('close');
        };
        btns[window.hWin.HR('Cancel')] = () => {
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, {title: window.hWin.HR('Importing ORCID data')}, {default_palette_class: 'ui-heurist-admin', dialogId: 'import-orcid-data'});
    }
    
});
