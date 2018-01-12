/**
* manageSysGroups.js - main widget to manage sysUGrps workgroups
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


$.widget( "heurist.manageSysGroups", $.heurist.manageEntity, {
   
    _entityName:'sysGroups',

    //
    //
    //    
    _init: function() {
        
        this.options.layout_mode = 'short';
        this.options.use_cache = false;
        
        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = false;
        this.options.edit_height = 540;

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

        // init search header
        this.searchForm.searchSysGroups(this.options);
        
        var iheight = 3;
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 6;
        }
        this.searchForm.css({'height':iheight+'em'});
        this.recordList.css({'top':iheight+0.4+'em'});
        //init viewer 
        var that = this;
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }

        this._on( this.searchForm, {
                "searchsysgroupsonresult": this.updateRecordList,
                "searchsysgroupsonadd": function() { this.addEditRecord(-1); }
                });
        
        this._on( this.recordList, {
                        "resultlistonpagerender": function(event){
                            //init role selector
                            this.recordList.find('select.user-role')
                                .each(function(idx,item){$(item).val($(item).attr('data-value'))})
                                .change(function(event){console.log($(event.target).val())});
                            
                        }
                        });
        
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
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            var val = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
            return '<div class="item" '+swidth+' title="'+val+'">'+val+'</div>';
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('ugr_ID');
        
        var recTitle = fld2('ugr_ID','4em')
                      +fld2('ugr_Name','12em')
                      +fld2('ugr_Description','20em');
        var recTitleHint = '';//recID+' : '+fld('ugr_Description');
        
        var rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');
        //var rtThumb = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'thumb');
        var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:1">'
        +'</div>';
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);">'
        + '</div>'
        + '<div class="recordTitle">'
        +     recTitle + '(' + fld('ugr_Members') + ')'
        + '</div>';
        
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
            
            if(this.searchForm.find('#input_search_type').val()!='any'){
                if(window.hWin.HAPI4.has_access(recID)){ //current user is admin for this group
                    html = html 
                        + '<select title="Role" style="position:absolute;top:4px;right:56px;width:7em" class="user-role" data-value="'
                        + fld('ugl_Role')+'">'
                        +'<option>admin</option><option>member</option></select>';
                    
                }else{
                    html = html 
                        + '<div title="Role" style="position:absolute;top:4px;right:56px;width:5em">'
                        + fld('ugl_Role')+'</div>';
                }
            }
            
            html = html 
                + '<div title="Click to edit group" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="right:30px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>&nbsp;&nbsp;';
           if(recID!=1){
                html = html      
                + '<div title="Click to delete group" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div>';
           }
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
        this._super( recID, fieldvalues );
        
        this.getRecordSet().setRecord(recID, fieldvalues);
        this.recordList.resultList('refreshPage');  
    },
    
    
    _afterInitEditForm: function(){
        this._super();
        //hide after edit init btnRecRemove for group=2
        if(this._currentEditID==1){
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
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

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
    
    }
});
