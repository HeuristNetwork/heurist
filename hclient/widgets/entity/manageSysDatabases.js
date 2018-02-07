/**
* manageSysDatabases.js - work with list of all databases on current server
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
        }
        
        var that = this;
        window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
            function(response){
                that._cachedRecordset = response;
                that.recordList.resultList('updateResultSet', response);
            });
            
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
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            var val = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
            return '<div class="item" '+swidth+'>'+val+'</div>';  //title="'+val+'"
        }
        
        
        var recID   = fld('sys_Database');
        
        var recTitle = fld2('sys_Database','10em')
                      +fld2('sys_Version','4em')
                      +fld2('sys_dbRegisteredID','4em')
                      +fld2('sys_dbName','12em');
                      
        var recTitleHint = fld('sys_dbDescription');
        
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
        + '<div class="recordTitle" title="'+recTitleHint+'">'
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
    
});
