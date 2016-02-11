/**
* manageDefDetailTypeGroups.js - main widget mo manage defDetailTypeGroups
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


$.widget( "heurist.manageDefDetailTypeGroups", $.heurist.manageEntity, {
    
    
    _entityName:'defDetailTypeGroups',
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        // init search header
        this.searchRecord.searchDefDetailTypeGroups( this.options );
            
        this._on( this.searchRecord, {
                "searchdefdetailtypegroupsonresult": this.updateRecordList
                });
                
        this.element.find('.ent_header').css('height',0);
        this.element.find('.ent_content_full').css('top',0);
        
        this.recordList.resultList('option','hide_view_mode',true);
        
        //move editor panel from element to div_content        
        if(this.ent_editor && !this.options.edit_dialog){
            
            var list_container = this.recordList.find('.div-result-list-content');
            
            list_container.removeProp('right').css('width','250px');
            
            this.ent_editor
                .detach()
                .appendTo(list_container.parent())
                .css({'min-width':'500px','left':'250px', top:list_container.position().top})
                .show();
        }
                
        return true;
    },    
    
    //----------------------
    //
    //
    //
    _rendererListItem: function(recordset, record){
        
        function fld(fldname){
            return top.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!top.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+top.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        var recID   = fld('dtg_ID');
        var recTitle = fld2('dtg_ID','4em')+fld2('dtg_Name');
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="height:1.3em">';
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div><div class="recordTitle">';
        }else{
            html = html + '<div>';
        }
        
        html = html + fld2('dtg_Name') + '</div>';
        
        if(this.options.edit_dialog){
            html = html
            + '<div title="Click to edit group" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
            + '</div>&nbsp;&nbsp;'
            + '<div title="Click to delete group" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
            + '</div>';
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

//
// Show as dialog
//
function showManageDefDetailTypeGroups( options ){

    var manage_dlg = $('#heurist-records-dialog');  //@todo - unique ID

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
        .appendTo( $('body') )
        .manageDefDetailTypeGroups( options );
    }

    manage_dlg.manageDefDetailTypeGroups( 'popupDialog' );
}
