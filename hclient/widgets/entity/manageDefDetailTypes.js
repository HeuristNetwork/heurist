/**
* manageDefDetailTypes.js - main widget to manage defDetailTypes
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


$.widget( "heurist.manageDefDetailTypes", $.heurist.manageEntity, {
    
    // the widget's constructor
    _create: function() {
        
        this._super();

        this._entityName = 'Field Type',
        this._entityNames = 'Field Types',
        this._empty_remark = 'Please use the search field above to locate relevant field type (partial string match on title)',
        
        this._default_sel_actions = [
                          {key:'edit', title:'Edit', icon:'ui-icon-pencil', hint:'Edit field type'},
                          {key:'delete', title:'Delete', icon:'ui-icon-circle-close', hint:'Remove field type'}];
                          
        this._default_btn_actions = [{key:'add', title:'New Field Type'}];
        
    }, //end _create
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
        this._super();

        // init search header
        this.searchRecord.searchDefDetailTypes(this.options);
            
        this._on( this.searchRecord, {
                "searchdefdetailtypesonresult": this.updateRecordList
                });
        this.recordList.css('top','5.5em');
                
        this.recordList.resultList('option','hide_view_mode',true);
    },
    
    //----------------------
    //
    //
    //
    _rendererListItem:function(recordset, record){
        
        function fld(fldname){
            return recordset.fld(record, fldname);
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!top.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+top.HEURIST4.util.htmlEscape(fld(fldname))+'</div>';
        }
        
        var showActionInList = (top.HEURIST4.util.isArrayNotEmpty(this.options.action_select)); 
        //&& (this.options.select_mode=='manager')
        
        var recID   = fld('dty_ID');
        
        var recTitle = fld2('dty_ID','4em')
                + fld2('dty_Name','14em')
                + '<div class="item inlist" style="width:25em;">'+fld('dty_HelpText')+'</div>'
                + '<div class="item inlist" style="width:10em;">'+top.HEURIST4.detailtypes.lookups[fld('dty_Type')]+'</div>'
                + (showActionInList?this._rendererListAction('edit'):'');

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="min-height: 2.6em;">'  
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordTitle" style="left:24px">'
        +     recTitle;
        
        //actions
        if(showActionInList){
            
            //special case for show in list checkbox
            html = html 
            +  '<div title="Make type visible in user accessible lists" class="item inlist logged-in-only" '
            + 'style="width:3em;padding-top:5px" role="button" aria-disabled="false" data-key="show-in-list">'
            +     '<input type="checkbox" checked="'+(fld('dty_ShowInLists')==0?'':'checked')+'" />'
            + '</div>';
            
            var group_selectoptions = this.searchRecord.find('#sel_group').html();
                        
            html = html 
                //  counter and link to rectype + this._rendererListAction('duplicate')
                //group selector
            +  '<div title="Change group" class="item inlist logged-in-only"'
            +  ' style="width:8em;padding-top:3px" data-key2="group-change">'
            +     '<select style="max-width:7.5em;font-size:1em" data-grpid="'+fld('dty_DetailTypeGroupID')
            + '">'+group_selectoptions+'</select>'
            +  '</div>'
                + this._rendererListAction('delete');
        }
        
        html = html 
                + '</div>' //close recordTitle
                + '</div>'; //close recordDiv
        
        /* 
            html = html 
        +'<div title="Click to edit group" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>&nbsp;&nbsp;'
        + '<div title="Click to delete group" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
        + '</div>'
        + '</div>';*/


        return html;
        
    },

    //
    //
    //
    _searchFullData:function(arr_ids, pageno, callback){
        
        var request = {
                'a'          : 'search',
                'entity'     : 'defDetailTypes',
                'details'    : 'list',
                'request_id' : pageno,
                'dty_ID'     : arr_ids,
                //'DBGSESSID'  : '423997564615200001;d=1,p=0,c=0'
        };
        
        top.HAPI4.EntityMgr.doRequest(request, callback);
    }
    
});

//
// Show as dialog
//
function showManageDefDetailTypes( options ){

    var manage_dlg = $('#heurist-records-dialog');  //@todo - unique ID

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
        .appendTo( $('body') )
        .manageDefDetailTypes( options );
    }

    manage_dlg.manageDefDetailTypes
    ( 'popupDialog' );
}
