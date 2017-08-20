/**
* manageDefFileExtToMimetype.js - main widget mo manage extension to mimetype 
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


$.widget( "heurist.manageDefFileExtToMimetype", $.heurist.manageEntity, {
    
    
    _entityName:'defFileExtToMimetype',
    
    _init: function() {
        
        this.options.width = 570;
        this.options.height = 600;

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<600)?600:this.options.width;                    
        }
        
        this.options.use_cache = true;
        //this.options.view_mode = 'list';
        
        /*if(this.options.edit_mode=='popup'){ //only inline allowed
            this.options.edit_mode='inline'
        }*/

        if(!this._super()){
            return false;
        }
        
        if(this.searchForm && this.searchForm.length>0){
            this.searchForm.searchDefFileExtToMimetype(this.options);   
            if(this.options.edit_mode=='inline') this.searchForm.height('7.5em').css('border','none');    
        }
        
        if(this.options.edit_mode=='inline') this.recordList.css('top','7.5em');
        this.recordList.resultList('option', 'show_toolbar', false);
        this.recordList.resultList('option', 'view_mode', 'list');

        var that = this;
        window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
            function(response){
                that._cachedRecordset = response;
                that.recordList.resultList('updateResultSet', response);
            });
            
        this._on( this.searchForm, {
                "searchdeffileexttomimetypeonfilter": this.filterRecordList
                });
        this._on( this.searchForm, {
                "searchdeffileexttomimetypeonaddrecord": function(){this._onActionListener(null, 'add');}
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
        
        var recID   = fld('fxm_Extension');
        var recTitle = '<span style="display:inline-block;width:4em">'+fld('fxm_Extension') + '</span>  ' 
                        + fld('fxm_FiletypeName'); //fld2('fxm_MimeType');
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="height:1.3em">';
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div><div class="recordTitle">';
        }else{
            html = html + '<div>';
        }
        
        html = html + recTitle + '</div>';
        
        if(false && this.options.edit_mode=='popup'){ //action button in reclist
            html = html
            + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil'}, null,'icon_text')
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
    
    _initEditForm_step3: function(recID){
        
        if(recID<0){
            this.options.entity.fields[0].dtFields['rst_Display'] = 'visible';
        }else{
            this.options.entity.fields[0].dtFields['rst_Display'] = 'readonly';
        }

        this._super(recID);
    },
    
    
});
