/**
* manageSysGroups.js - main widget mo manage sys groups
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


$.widget( "heurist.manageDefRecTypeGroups", $.heurist.manageEntity, {
    
    // the widget's constructor
    _create: function() {
        
        this._super();

        this._entityTitle = 'Record Type Group',
        this._entityTitlePlural = 'Record Type Groups',
        this._empty_remark = 'Please use the search field above to locate relevant group (partial string match on title)',
        
        this._default_sel_actions = [{key:'edit', title:'Edit'},
                          {key:'delete', title:'Delete'}];
                          
        this._default_btn_actions = [{key:'add', title:'Add New Group'}];
        
    }, //end _create
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
        this._super();

        // init search header
        this.searchForm.searchDefRecTypeGroups(this.options);
            
        this._on( this.searchForm, {
                "searchdefrectypegroupsonresult": this.updateRecordList
                });
                
        this.element.find('.ent_header').css('height',0);
        this.element.find('.ent_content_full').css('top',0);
        
        this.wrapper.css('min-width','300px');
        
        this.recordList.resultList('option','show_toolbar', false);
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
        
        var recID   = fld('rtg_ID');
        var recTitle = fld2('rtg_ID','4em')+fld2('rtg_Name');
        

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordTitle">'
        +     recTitle
        + '</div>'
        + '<div title="Click to edit group" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>&nbsp;&nbsp;'
        + '<div title="Click to delete group" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
        + '</div>'
        + '</div>';

        return html;
        
    },

    //
    //
    //
    _recordListGetFullData:function(arr_ids, pageno, callback){
        
        var request = {
                'a'          : 'search',
                'entity'     : 'defRecTypeGroups',
                'details'    : 'list',
                'request_id' : pageno,
                'ugr_ID'     : arr_ids
                //'DBGSESSID'  : '423997564615200001;d=1,p=0,c=0'
        };
        
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    }
    
});

//
// Show as dialog
//
function showManageDefRecTypeGroups( options ){

    var manage_dlg = $('#heurist-records-dialog');  //@todo - unique ID

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
        .appendTo( $('body') )
        .manageDefRecTypeGroups( options );
    }

    manage_dlg.manageDefRecTypeGroups( 'popupDialog' );
}
