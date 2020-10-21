/**
* manageSysDatabases.js - work with list of all databases on current server
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


$.widget( "heurist.manageSysDatabases", $.heurist.manageEntity, {
    
    
    _entityName:'sysDatabases',
    
    _init: function() {
        
        this.options.width = 800;
        this.options.height = 600;
        this.options.edit_mode = 'none';

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        this.options.use_cache = true;
        
        if(!this._super()){
            return false;
        }
        
        if(this.searchForm && this.searchForm.length>0){
            this.searchForm.searchSysDatabases(this.options);   
            if(this.options.subtitle){
                this.recordList.css('top',80);
            }
        }
        
        this.recordList.resultList('option','rendererHeader',
                    function(){
        sHeader = '<div style="width:60px"></div><div style="border-right:none">Db Name</div>';
        /*
                //+'<div style="width:3em">Ver</div>'
                +'<div style="width:3em">Reg#</div>'
                +'<div style="width:20em">Title</div>'
                +'<div style="width:5em">Role</div>'
                +'<div style="width:5em">Users</div>'; */
                
                return sHeader;
                    }
                );
        
        
        if(this.options.isdialog){
            window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parents('.ui-dialog')); 
        }
        

        //load at once everything to _cachedRecordset
        var that = this;
        function __onDataResponse(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                that._cachedRecordset = response;
                
                //that.filterRecordList(null, {});
                that.recordList.resultList('updateResultSet', response);
            };
            
        var entityData = window.hWin.HAPI4.EntityMgr.getEntityData2( this.options.entity.entityName );

        
        if($.isEmptyObject(entityData)){
        
            window.hWin.HAPI4.EntityMgr.doRequest(
                {a:'search', 'entity':this.options.entity.entityName, 'details':'ids'},
                       function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                entityData = new hRecordSet(response.data);
                                window.hWin.HAPI4.EntityMgr.setEntityData(
                                            that.options.entity.entityName,
                                            entityData);

                                __onDataResponse(entityData);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                       });
        
        }else{
            __onDataResponse(entityData);
        }
        
        /*
        window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                that._cachedRecordset = response;
                
                that.filterRecordList(null, {});
                //that.recordList.resultList('updateResultSet', response);
            });
        */    
            
        //and then filter locally    
        this._on( this.searchForm, {
                "searchsysdatabasesonfilter": this.filterRecordList
                });
                
        return true;
    },    
    
    //----------------------
    //
    // customized item renderer for search result list
    //
    _recordListItemRenderer: function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function frm(value, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            var val = window.hWin.HEURIST4.util.htmlEscape(value);
            return '<div class="item" '+swidth+'>'+value+'</div>';  //title="'+val+'"
        }
        
        
        var recID   = fld('sys_Database');
        
        var dbName = fld('sys_dbName');
        if(dbName=='Please enter a DB name ...') dbName = '';
        var regID = fld('sys_dbRegisteredID');
        regID = (regID>0?regID:'');
        
        var recTitle = frm(recID.substr(4),'40em'); //remove prefix hdb_
        /*
        var recTitle = frm(recID.substr(4),'14em')
                      //+frm(fld('sys_Version'),'4em')
                      +frm(regID, '4em')
                      +frm(dbName, '23em')
                      +frm(fld('sus_Role'), '6em')
                      +frm(fld('sus_Count'), '5em');
        */              
        var recTitleHint = ''//fld('sys_dbDescription');
        
        var rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');
        var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:1">'
        +'</div>';
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" data-value="'+ fld('sus_Role')+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);">'
        + '</div>'
        + '<div class="recordTitle">'  // title="'+recTitleHint+'"
        +     recTitle 
        + '</div>';

        
        if(false && this.options.edit_mode=='popup'){ //action button in reclist
            html = html
            + this._defineActionButton({key:'info',label:'Info', title:'', icon:'ui-icon-info'}, null,'icon_text')
            + this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'}, null,'icon_text');
        }
        

        return html+'</div>';
        
    },

    updateRecordList: function( event, data ){
        //this._super(event, data);
        if (data){
            if(this.options.use_cache){
                this._cachedRecordset = data.recordset;
                //there is n filter feature in this form - thus, show list directly
            }
            this.recordList.resultList('updateResultSet', data.recordset, data.request);
        }
    },
    
    filterRecordList: function(event, request){
        if(this.options.except_current==true){
            var subset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
            subset = subset.getSubSetByRequest({'sys_Database':'!=hdb_'+window.hWin.HAPI4.database}, this.options.entity.fields);
            this.recordList.resultList('updateResultSet', subset, request);   
        }else{
            this._super(event, request); 
        }
    },
    
});
