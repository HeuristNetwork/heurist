/**
* manageSysGroups.js - main widget to manage sysUGrps workgroups
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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


$.widget( "heurist.manageSysGroups", $.heurist.manageEntity, {

    _entityName: 'sysGroups',

    _select_roles: {},

    //
    //
    //    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-admin';

        this.options.layout_mode = 'short';
        this.options.use_cache = false;
        this.options.width = 800;

        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = false;
        this.options.edit_height = 540;
        this.options.height = 740;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<815)?900:this.options.width;                    
            //this.options.edit_mode = 'none'
        }

        this._super();
    },

    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        if(!this._super()){
            return false;
        }

        //update dialog title
        var title = null;
        var usr_ID = 0;
        
        if(this.options.title){
            title = this.options.title;
        }else
        if(this.options.select_mode=='select_single'){
           title = 'Select Workgroup'; 
        }else
        if(this.options.select_mode=='select_multi'){
           title = 'Select Workgroups'; 
        }else
        if(this.options.ugl_UserID>0){
            usr_ID = this.options.ugl_UserID;
            title = 'Manage Workgroups for User #'+this.options.ugl_UserID+': '
            /*}else if(this.options.ugl_UserID<0){
            usr_ID = Math.abs(this.options.ugl_UserID);
            title = 'Define Roles for User #'+usr_ID+': '; */
        }else{
            if(window.hWin.HAPI4.is_admin()){
                title = 'Manage Workgroups as Database Administrator';    
            }else{                    
                usr_ID = window.hWin.HAPI4.currentUser['ugr_ID'];
                title = 'Manage Workgroups for user #'+window.hWin.HAPI4.currentUser['ugr_ID']+': ';    
            }
        }

        if(usr_ID>0 && title){
            var that = this;
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
        this.searchForm.searchSysGroups(this.options);

        var iheight = 5;
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 6;
        }
        if(this.options.subtitle){
            iheight = iheight + 1.5;
        }
        this.searchForm.css({'height':iheight+'em',padding:'10px','min-width': '580px'});
        this.recordList.css({'top':iheight+0.4+'em'});
        //init viewer 
        var that = this;

        if(this.options.select_mode=='manager' || that.options.select_mode=='select_roles'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});

            this.recordList.resultList('option','rendererHeader',
                function(){
                    sHeader = '<div style="width:60px"></div><div style="width:3em">ID</div>'
                    +'<div style="width:11em">Name</div>'
                    +'<div style="width:16em;border:none">Description</div>';

                    if(that.options.ugl_UserID>0 || that.options.select_mode=='select_roles'){
                        sHeader = sHeader                
                        +'<div style="position:absolute;right:210px;width:60px;border-left:1px solid gray">Members</div>'
                        +'<div style="position:absolute;right:150px;width:45px">Admin</div>'
                        +'<div style="position:absolute;right:74px;width:60px">Member</div>';
                        //+'<div style="position:absolute;right:4px;width:60px">Edit</div>';
                    }else{
                        sHeader = sHeader                
                        +'<div style="position:absolute;right:150px;width:60px;border-left:1px solid gray">Members</div>'
                        +'<div style="position:absolute;right:74px;width:60px">Role</div>'
                        +'<div style="position:absolute;right:4px;width:60px">Edit</div>';
                    }

                    return sHeader;
                }
            );
        }

        this._on( this.searchForm, {
            "searchsysgroupsonresult": this.updateRecordList,
            "searchsysgroupsonadd": function() { this.addEditRecord(-1); }
        });

        this._on( this.recordList, {
            "resultlistonpagerender": function(event){
                
                
                //init role selector
                this.recordList.find('.edit-members')
                .each(function(idx,item){
                    $(item).attr('title','View users in a group or assign users to a group');
                    //To view users in a group or assign users to a group, click on the edit icon in the Edit Membership column

                    /*$(item).button({icon:'ui-icon-pencil', iconPosition:"end"})
                    .css({'background':'gray !important', 'max-height':'1em'});*/
                })
                .click(function(event){
                    var group_ID = $(event.target).parents('.recordDiv').attr('recid');

                    var options = {select_mode: 'manager',
                        ugl_GroupID: group_ID,
                        edit_mode:'popup',
                        title: ("Manage Users of Workgroup #"+group_ID),

                        //before close - count for membership and refresh
                        beforeClose:function(){
                            
                            if(window.hWin.HAPI4.has_access(group_ID)){ //current user is admin of given group
                                var request = {
                                    'a'          : 'search',
                                    'entity'     : 'sysGroups',
                                    'details'    : 'count',
                                    'ugr_ID'     : group_ID
                                };
                                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                    function(response){
                                        if(response.status == window.hWin.ResponseStatus.OK){
                                            var resp = new hRecordSet( response.data );
                                            var rec_updated = resp.getFirstRecord();
                                            var cnt = resp.fld(rec_updated, 'ugr_Members');

                                            var record = that.getRecordSet().getById(group_ID);
                                            that.getRecordSet().setFld(record, 'ugr_Members', cnt);
                                            that.recordList.resultList('refreshPage');  
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                    }
                                );
                            }
                            return true;
                        }




                    };


                    window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options);
                });

                //event listeners for adminSelector and memberSelector    
                function __onRoleSelectorClick(event){
                    var ele = $(event.target);

                    var newRole = 'remove';
                    var currentStatus = ele.is(':checked');                                                    
                    if(currentStatus){
                        if(ele.parent().hasClass('adminSelector')){
                            newRole = 'admin';
                        }else{
                            newRole = 'member';
                        }
                    }

                    var item = ele.parents('.recordDiv');
                    var group_ID = item.attr('recid');  


                    if(that.options.select_mode=='select_roles'){

                        if(newRole=='remove'){
                            that._select_roles[group_ID] = null;
                            delete that._select_roles[group_ID];
                        }else{
                            that._select_roles[group_ID] = newRole;
                        }
                        item.attr('data-value', newRole);
                        var cb2;
                        if(ele.parent().hasClass('adminSelector')){
                            cb2 = item.find('.memberSelector > input');
                        }else{
                            cb2 = item.find('.adminSelector > input');
                        } 
                        cb2.attr('checked',false);      

                        return;
                    }

                    var request = {};
                    request['a']        = 'action';
                    request['entity']   = 'sysGroups';
                    request['role']     = newRole;
                    request['userIDs']  = that.options.ugl_UserID;
                    request['groupID']  = group_ID;
                    request['request_id'] = window.hWin.HEURIST4.util.random();

                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){             
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //reload
                                //that.searchForm.searchSysUsers('startSearch');
                                if(newRole=='remove'){
                                    if(false && that.searchForm.find('#input_search_type').val()!='any'){
                                        var recset = that.recordList.resultList('getRecordSet');
                                        recset.removeRecord(group_ID);
                                        that.recordList.resultList('refreshPage');  
                                    }
                                    window.hWin.HEURIST4.msg.showMsgFlash('User removed from group');
                                }else{
                                    item.attr('data-value', newRole);
                                    var cb2;
                                    if(ele.parent().hasClass('adminSelector')){
                                        cb2 = item.find('.memberSelector > input');
                                    }else{
                                        cb2 = item.find('.adminSelector > input');
                                    } 
                                    cb2.attr('checked',false);      

                                    window.hWin.HEURIST4.msg.showMsgFlash('New role applied');      
                                }
                                
                                if(that.options.ugl_UserID==window.hWin.HAPI4.currentUser['ugr_ID']){
                                    if(newRole=='remove'){
                                        window.hWin.HAPI4.currentUserRemoveGroup(group_ID);
                                    }else{
                                        window.hWin.HAPI4.currentUser['ugr_Groups'][group_ID] = newRole;
                                    }
                                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                                }
                                
                            }else{
                                //restore current value - rollback
                                var restoreRole = item.attr('data-value');
                                item.find('.adminSelector > input').prop('checked', (restoreRole=='admin') );
                                item.find('.memberSelector > input').prop('checked',(restoreRole=='member') );
                                window.hWin.HEURIST4.msg.showMsgErr(response);      
                            }
                    });



                }
                this.recordList.find('.adminSelector').change( __onRoleSelectorClick );
                this.recordList.find('.memberSelector').change( __onRoleSelectorClick );


        }});

        return true;
    },

    //----------------------
    //
    //  custom renderer for recordList
    //
    _recordListItemRenderer:function(recordset, record){

        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, sstyle, tip_text){
            
            if(!window.hWin.HEURIST4.util.isempty(sstyle)){
                sstyle = ' style="'+sstyle+'"';
            }else{
                sstyle = '';
            }
            var val = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
            return '<div class="item" '+sstyle+' title="'+tip_text+'">'+val+'</div>';
        }

        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role

        var recID   = fld('ugr_ID');
        var tip_text = fld('ugr_Description');

        var recTitle = fld2('ugr_ID','width:4em', '')
        +fld2('ugr_Name','width:12em', tip_text)
        +fld2('ugr_Description','width:100%;position:absolute', tip_text);
        var recTitleHint = '';//recID+' : '+fld('ugr_Description');

        var rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');
        //var rtThumb = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'thumb');
        var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');

        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:1">'
        +'</div>';

        
        var is_user_roles = (this.options.ugl_UserID>0 || this.options.select_mode=='select_roles');

        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" data-value="'+ fld('ugl_Role')+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);">'
        + '</div>'
        + '<div class="recordTitle" style="right:'+(is_user_roles?270:210)+'px">'
        +     recTitle 
        + '</div>';

        if(this.options.select_mode=='select_roles'){


            html = html
            +'<div class="user-list-count">' + fld('ugr_Members') + '</div>'
            +'<div class="user-list-edit user-list-member">'
            +'<div class="adminSelector" style="padding-top:2px;width:60px"><input type="checkbox" id="adm'+recID
            +'" '+(this._select_roles[recID]=='admin'?'checked':'')
            +'/><label for="adm'+recID+'">Admin</label></div>' 
            +'<div class="memberSelector" style="padding-top:2px;width:60px"><input type="checkbox" id="mem'+recID
            +'" '+(this._select_roles[recID]=='member'?'checked':'')
            +'/><label for="mem'+recID+'">Member</label></div></div>';

            html = html + '<div class="rec_actions user-list" style="top:4px;width:60px;right:2px;position:absolute;"></div></div>';


        } else
            if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){

                // admin/member checkboxes
                if(this.options.ugl_UserID>0){ //select_role

                    html = html
                    +'<div class="user-list-count">' + fld('ugr_Members') + '</div>'
                    +'<div class="user-list-edit user-list-member">'
                    +'<div class="adminSelector" style="padding-top:2px;width:60px"><input type="checkbox" id="adm'+recID
                    +'" '+(fld('ugl_Role')=='admin'?'checked':'')
                    +'/><label for="adm'+recID+'">Admin</label></div>' 
                    +'<div class="memberSelector" style="padding-top:2px;width:60px"><input type="checkbox" id="mem'+recID
                    +'" '+(fld('ugl_Role')=='member'?'checked':'')
                    +'/><label for="mem'+recID+'">Member</label></div></div>';

                    html = html + '<div class="rec_actions user-list" style="top:4px;width:60px;right:2px;position:absolute;">';

                }else{

                    html = html 
                    + '<div class="user-list user-list-edit edit-members" style="right:150px;width:50px;">'
                    + fld('ugr_Members') + '<span class="ui-icon ui-icon-pencil" '
                    + ' style="font-size:0.8em;float:right;top:2px;right:2px"/></div>'  //'<span class="ui-icon ui-icon-pencil" style="font-size:0.8em"></span>

                    html = html + '<div class="rec_actions user-list" style="top:4px;width:140px;right:2px;position:absolute;">';

                    if(this.searchForm.find('#input_search_type').val()!='any'){

                        //current user is admin of given group
                        /*if(window.hWin.HAPI4.has_access(recID)){ 
                        html = html                        
                        + '<select title="Role" style="width:70px;margin:0 4px" class="user-role" data-value="'
                        + fld('ugl_Role')+'">'
                        +'<option>admin</option><option>member</option><option>remove</option></select>';

                        }else{}*/
                        html = html                      
                        + '<div title="Role" style="min-width:80px;text-align:center">'
                        + fld('ugl_Role')+'</div>';

                    }else{
                        //placeholder
                        html = html + '<div style="min-width:78px;"></div>';
                    }
                }


                if(window.hWin.HAPI4.has_access(recID)){ //current user is admin of given group
                    html = html                                    
                    + '<div title="Click to edit group" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;&nbsp;';
                    if(recID!=1){
                        html = html                                
                        + '<div title="Click to delete group" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" style="height:16px">'
                        +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                        + '</div>';
                    }
                }else{
                    html = html                                    
                    + '<div title="Status: not admin - locked" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-lock"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;&nbsp;';
                }
                html = html + '</div>';
            }


        html = html + '</div>';

        return html;

    },

    //overwritten    
    _recordListGetFullData:function(arr_ids, pageno, callback){

        var request = {
            'a'          : 'search',
            'entity'     : this.options.entity.entityName,
            'details'    : 'list',
            'pageno'     : pageno
        };

        //add additional parameter - need to get a Role
        if(this.options.ugl_UserID>0){
            request['ugl_UserID'] = this.options.ugl_UserID;
            request['ugl_Join'] = true;
        }else 
            if(this.searchForm.find('#input_search_type').val()!='any'){
                request['ugl_UserID'] = window.hWin.HAPI4.currentUser['ugr_ID'];
            }

        request[this.options.entity.keyField] = arr_ids;
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    },

    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && this.options.select_mode=='select_single'){
                this._selection = new hRecordSet();
                //{fields:{}, order:[recID], records:[fieldvalues]});
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;       
        }
        //addition of new group - update fields in recordset and change current user credentials
        if(this._currentEditID<0){
            fieldvalues['ugr_Members'] = 1;
            fieldvalues['ugl_Role'] = 'admin';
            window.hWin.HAPI4.currentUser['ugr_Groups'][recID] = 'admin';
            window.hWin.HAPI4.sysinfo.db_usergroups[recID] = fieldvalues['ugr_Name'];
            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
        }
//console.log(fieldvalues);
        this._super( recID, fieldvalues );

        this.getRecordSet().setRecord(recID, fieldvalues);
        this.recordList.resultList('refreshPage');  
        
    },
    
    _afterDeleteEvenHandler: function( recID )   {
        window.hWin.HAPI4.currentUserRemoveGroup(recID, true);
        
        this._super( recID );
        
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
    },

    _afterInitEditForm: function(){
        this._super();
        //hide after edit init btnRecRemove for group=1
        if(this._currentEditID==window.hWin.HAPI4.sysinfo.db_managers_groupid){ //sys_OwnerGroupID
            var ele = this._toolbar;
            ele.find('#btnRecRemove').hide();
        }

    },


    _changeUserRole: function(group_id, user_ids, new_role){

        var request = {
            'a'          : 'action',
            'entity'     : this.options.entity.entityName,
            'request_id' : window.hWin.HEURIST4.util.random(),
            'groupID'    : group_id,
            'userIDs'    : user_ids,
            'role'       : new_role, //admin,member,remove
        };

        var that = this;                                                
        //that.loadanimation(true);
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    var recID = response.data[0];
                    fields[ that.options.entity.keyField ] = (''+recID);

                    //update record in cache
                    if(that.options.use_cache){
                        this._cachedRecordset.addRecord(recID, fields);
                    }else{
                        //add/update record in recordset in _afterSaveEventHandler depends on entity
                    }

                    that._afterSaveEventHandler( recID, fields );

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });

    },

    //
    // event handler for select-and-close (select_multi)
    // or for any selection event for select_single
    // triger onselect event
    //
    _selectAndClose: function(){

        if(this.options.select_mode=='select_roles'){ //special case - select roles for any set of users
            if( $.isEmptyObject(this._select_roles)){
                window.hWin.HEURIST4.msg.showMsgErr('You have to allocate membership in at last one group');
            }else{
                this._trigger( "onselect", null, {selection: this._select_roles});
                this.closeDialog();
            }
        }else{
            this._super(); 
        }


    }
    
    ,_deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this group?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    }

});
