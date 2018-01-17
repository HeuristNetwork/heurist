/**
* manageSysUsers.js - main widget to manage sysUGrps users
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


$.widget( "heurist.manageSysUsers", $.heurist.manageEntity, {
   
    _entityName:'sysUsers',

    //
    //
    //    
    _init: function() {
        
        this.options.layout_mode = 'short';
        this.options.use_cache = false;
        //this.options.edit_mode = 'popup';
        
        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = true;
        this.options.edit_height = 550;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                    
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
        if(this.options.isdialog){
            var title = null;
            var usr_ID = 0;
            if(this.options.ugl_GroupID>0){
                usr_ID = this.options.ugl_GroupID;
                title = 'Manage Users of Workgroup #'+this.options.ugl_GroupID+': ';
            }else if(this.options.ugl_GroupID<0){
                usr_ID = Math.abs(this.options.ugl_GroupID);
                title = 'Select Users to add to Workgroup #'+usr_ID+': ';
            }else{
                if(window.hWin.HAPI4.is_admin()){
                    title = 'Manage All Users as Database Administrator';    
                }else{                    
                    usr_ID = window.hWin.HAPI4.currentUser['ugr_ID'];
                    title = 'Manage workgroups for user #'+window.hWin.HAPI4.currentUser['ugr_ID']+': ';    
                }
            }
            
            if(usr_ID>0 && title){
                function __set_dlg_title(data){
                    if(data && data.status==window.hWin.HAPI4.ResponseStatus.OK){
                        this._as_dialog.dialog('option','title', title+data.res);    
                    }
                } 
                window.hWin.HAPI4.usr_names({UGrpID: usr_ID}, __set_dlg_title);
            }else{
                this._as_dialog.dialog('option','title', title);    
            }
        }
        
        
        // init search header
        this.searchForm.searchSysUsers(this.options);
        
        var iheight = 6;
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 6;
        }
        this.searchForm.css({'height':iheight+'em'});
        this.recordList.css({'top':iheight+0.5+'em'});
        //init viewer 
        var that = this;
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
            
            
            this.recordList.resultList('option','rendererHeader',
                    function(){
        return '<div style="width:60px"></div><div style="width:3em">ID</div>'
                +'<div style="width:7em">Name</div>'
                +'<div style="width:12em;">Full name</div>'
                +'<div style="width:7em;border:none">Institution/Organisation</div>'
                +'<div style="position:absolute;right:76px;width:80px;border-left:1px solid gray">Role</div>'
                +'<div style="position:absolute;right:4px;width:60px">Edit</div>';
                    }
                );
            //this.recordList.resultList('applyViewMode');
        }

        this._on( this.searchForm, {
                "searchsysusersonresult": this.updateRecordList,
                "searchsysusersonadd": function() { this.addEditRecord(-1); },
                "searchsysusersonfind": function() { 
                    
                        var ugl_GroupID = this.searchForm.find('#input_search_group').val(); 
                    
                        var options = {select_mode: 'select_multi',
                                ugl_GroupID: -ugl_GroupID,
                                isdialog: true,
                                edit_mode:'none',
                                title: ("Select Users to add to Workgroup #"+ugl_GroupID),
                                onselect:function(event, data){
                        
                                    if(data && window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection))
                                    {
                                    
                                        var request = {};
                                        request['a']        = 'action'; //batch action
                                        request['entity']   = 'sysGroups';
                                        request['role']     = 'member';
                                        request['userIDs']  = data.selection;
                                        request['groupID']  = ugl_GroupID;
                                        request['request_id'] = window.hWin.HEURIST4.util.random();
                                        
                                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                            function(response){
                                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                                    //reload
                                                    that.searchForm.searchSysUsers('startSearch');
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
                            //init role selector
                            this.recordList.find('select.user-role')
                                .each(function(idx,item){$(item).val($(item).attr('data-value'))})
                                .change(function(event){

                                        var ugl_GroupID = that.searchForm.find('#input_search_group').val(); 
                                        if(!(ugl_GroupID>0)) {
                                            
                                            this.recordList.find('.user-list-edit')
                                                .each(function(idx,item){
                                                    $(item).attr('title','Edit user membership');
                                                })
                                                .click(function(event){
                                                    var user_ID = $(event.target).parents('.recordDiv').attr('recid');
                                                    
                                                    var options = {select_mode: 'manager',
                                                            ugl_UserID: user_ID,
                                                            isdialog: true,
                                                            edit_mode:'none',
                                                            title: ("Manage Membership for User #"+user_ID)};
                                                    

                                                    window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', options);
                                                });

                                            return;   
                                        }
                                          
                                        var selector = $(event.target);
                                        var usr_ID = selector.parents('.recordDiv').attr('recid');  
                                        var newRole = selector.val();
                                          
                                        var request = {};
                                        request['a']        = 'action';
                                        request['entity']   = 'sysGroups';
                                        request['role']     = newRole;
                                        request['userIDs']  = usr_ID;
                                        request['groupID']  = ugl_GroupID;
                                        request['request_id'] = window.hWin.HEURIST4.util.random();
                                        
                                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                            function(response){             
                                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                                    //reload
                                                    //that.searchForm.searchSysUsers('startSearch');
                                                    if(newRole=='remove'){
                                                        var recset = that.recordList.resultList('getRecordSet');
                                                        recset.removeRecord(usr_ID);
                                                        that.recordList.resultList('refreshPage');  
                                                        window.hWin.HEURIST4.msg.showMsgFlash('User removed from group');
                                                    }else{
                                                        selector.attr('data-value', newRole);
                                                        window.hWin.HEURIST4.msg.showMsgFlash('New role applied');      
                                                    }
                                                }else{
                                                    //restore current value
                                                    selector.val( selector.attr('data-value') );
                                                    window.hWin.HEURIST4.msg.showMsgErr(response);      
                                                }
                                            });
                                });
                                

                        }
                        });
                        
        
        return true;
    },

    //----------------------
    //
    //
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('ugr_ID');
        
        //var recTitle = fld2('ugr_ID','3em')+fld2('ugr_Name','10em')+fld('ugr_FirstName')+' '+fld('ugr_LastName');
        var recTitleHint = fld('ugr_Organisation');
        
        var recTitle = fld2('ugr_ID','3em')
                    + '<a class="item" style="width:8em" href="mailto:'+fld('ugr_eMail')+'" title="'+fld('ugr_eMail')+'">'
                    + fld('ugr_Name')+'</a>'
                    + '<div class="item" style="width:15em">'+fld('ugr_FirstName')+' '+fld('ugr_LastName')+'</div>'
                    + fld2('ugr_Organisation','8em');
        
        var recOpacity = (fld('ugr_Enabled')=='y')?1:0.3;
        
        var rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');
        //var rtThumb = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'thumb');
        var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:'+recOpacity+'">'
        +'</div>';

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons" style="min-width:16px;">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);opacity:'+recOpacity+'">'
        + '</div>'
        + '<div class="recordTitle" title="'+recTitleHint+'">'
        +     recTitle
        + '</div>';
        
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
        
            var ugl_GroupID = this.searchForm.find('#input_search_group').val(); 
            if(window.hWin.HAPI4.is_admin() && !(ugl_GroupID>0)){  //show count of groups where user is a member
                html = html 
                    + '<div class="rec_actions user-list user-list-edit" style="width:50px;">AA'
                    + fld('ugr_Member') + '<span class="ui-icon ui-icon-pencil" style="font-size:0.8em;right:2px"/></div>';
            }
            
            html = html + '<div class="rec_actions user-list" style="top:4px;width:140px">'
            
            var ugl_GroupID = this.searchForm.find('#input_search_group').val();
            if(ugl_GroupID>0){
                if(window.hWin.HAPI4.has_access(ugl_GroupID)>0){    //admin of this group
                    html = html 
                        + '<select title="Role" style="width:70px;margin:0 4px" class="user-role" data-value="'
                        + fld('ugl_Role')+'">'
                        +'<option>admin</option><option>member</option><option>remove</option></select>';
                    
                }else{
                    html = html 
                        + '<div title="Role" style="min-width:78px;">'
                        + fld('ugl_Role')+'</div>';
                }
            }else{
                //placeholder
                html = html + '<div style="min-width:78px;"></div>';
            }
            
            if(window.hWin.HAPI4.has_access(recID)>0){
                
                html = html 
                    + '<div title="Click to edit user" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;&nbsp;';
               if(recID!=2){
                    html = html      
                    + '<div title="Click to delete user" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" style="height:16px">'
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
        var ugl_GroupID = this.searchForm.find('#input_search_group').val();
        if(ugl_GroupID>0){
            request['ugl_GroupID'] = ugl_GroupID;
        }
        
        
        request[this.options.entity.keyField] = arr_ids;
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    },
    
    
    //-----
    // addign group ID value for new user
    //
    _afterInitEditForm: function(){

        this._super();
        
        var ugl_GroupID = this.searchForm.find('#input_search_group').val();
        if(ugl_GroupID>0 && !this._currentEditRecordset){ //insert       

            var ele = this._editing.getFieldByName('ugl_GroupID');
            ele.editing_input('setValue', ugl_GroupID);
            //hide save button
            if(this._toolbar){
                this._toolbar.find('#btnRecSave').css('visibility', 'visible');
            }
        }else
        //hide after edit init btnRecRemove for group=2
        if(this._currentEditID==2){
            var ele = this._toolbar;
            ele.find('#btnRecRemove').hide();
        }
        

    },    
    
    // update list after save
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && this.options.select_mode=='select_single'){
            
                this._selection = new hRecordSet();
                //{fields:{}, order:[recID], records:[fieldvalues]});
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;        
        }
        this._super( recID, fieldvalues );
        
        this.getRecordSet().setRecord(recID, fieldvalues);
        this.recordList.resultList('refreshPage');  
    },
});
