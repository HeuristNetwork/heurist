/**
* manageDefRecTypes.js - main widget to manage defRecTypes
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
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


$.widget( "heurist.manageDefRecTypes", $.heurist.manageEntity, {
    
    // the widget's constructor
    _create: function() {
        
        this._super();

        this._entityTitle = 'Record Type',
        this._entityTitlePlural = 'Record Types',
        this._empty_remark = 'Please use the search field above to locate relevant record type (partial string match on title)',
        
        this._default_sel_actions = [
                          {key:'import_fromdb', title:'Import from DB'},
                          {key:'import_template', title:'Import from Template'},
                          {key:'edit', title:'Edit', icon:'ui-icon-pencil'},
                          {key:'delete', title:'Delete', icon:'ui-icon-circle-close', hint:'Click to remove record type'},
                          {key:'merge', title:'Merge'},
                          {key:'duplicate', title:'Duplicate', icon:'ui-icon-copy', hint:'Duplicate record type'},
                          {key:'structure', title:'Structure', icon:'ui-icon-file-txt', hint:'Edit structure (add/remove fields)'},
                          {key:'avatar', title:'Change Icon/Thumbnail'}];
                          
        this._default_btn_actions = [{key:'add', title:'New Record Type'}];
        
    }, //end _create
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
        this._super();

        // init search header
        this.searchForm.searchDefRecTypes(this.options);
            
        this._on( this.searchForm, {
                "searchdefrectypesonresult": this.updateRecordList
                });
        this.recordList.css('top','4.5em');
                
    },
    
    //----------------------
    //
    // NOT USED yet
    //    
    _recordListHeaderRenderer:function(){
        
        function fld_head(content, sclass, col_width){ //only for list
            var swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            return '<div class="'+sclass+'" style="display:inline-block;border-right:1px solid;'+swidth+'">'+content+'</div>';
        }
        
        var showActionInList = (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.action_select)); 
        
        var header = 
              ((this.options.select_mode!='select_multi')?'':
              fld_head('<input type="checkbox" style="vertical-align:-3px"/>','','width:3em'))
            + fld_head('&nbsp;','','5em')
            + fld_head('ID','','2em')
            + (showActionInList?this._rendererActionButton('edit', true):'')
            + fld_head('Name','','14em')
            + fld_head('Description','','25em');
            
        if(showActionInList){
            header = header 
                + fld_head('Actions','','15em')
                /*+ fld_head('Status','','3em')
                + this._rendererActionButton('duplicate', true)
                + this._rendererActionButton('structure', true)
                + fld_head('Group','','4.6em')
                + this._rendererActionButton('delete', true);*/
        }            
            
        header = header 
            +'</div>';
        
        return header;
    },
    
    //
    //
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return recordset.fld(record, fldname);
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(fld(fldname))+'</div>';
        }
        function fld3(fldname, col_width){ //only for list
            var s = fld2(fldname, col_width).replace('class="item"', 'class="item inlist"');
            return s;
        }
        
        var showActionInList = (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.action_select)); 
        //&& (this.options.select_mode=='manager')
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('rty_ID');
        var recIcon = window.hWin.HAPI4.iconBaseURL + recID + '.png';
        
        var recTitle = fld2('rty_ID','4em')
                + (showActionInList?this._rendererActionButton('edit'):'')
                + fld2('rty_Name','14em')
                + '<div class="item inlist" style="width:25em;">'+fld('rty_Description')+'</div>';
                //fld3('rty_Description','25em');

        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+ window.hWin.HAPI4.iconBaseURL + 'thumb/th_' + recID + '.png&quot;);"></div>';

        

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="min-height: 2.6em;">'  
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="margin-left:15px;background-image: url(&quot;'+recIcon+'&quot;);">'   //class="rt-icon" 
        + '</div>'
        + '<div class="recordTitle">'
        +     recTitle;
        //@todo editors - show in list, duplicate, structure, group, counter
        
        //actions
        if(showActionInList){
            
            //special case for show in list checkbox
            html = html 
            +  '<div title="Make type visible in user accessible lists" class="item inlist logged-in-only"'
            +  ' style="width:3em;padding-top:5px" role="button" aria-disabled="false" data-key="show-in-list">'
            +     '<input type="checkbox" checked="'+(fld('rty_ShowInLists')==0?'':'checked')+'" />'
            + '</div>';
            
            var group_selectoptions = this.searchForm.find('#sel_group').html();
                        
            html = html 
                + this._rendererActionButton('duplicate')
                + this._rendererActionButton('structure')
                //group selector
            +  '<div title="Change group" class="item inlist logged-in-only"'
            +  ' style="width:8em;padding-top:3px;" data-key2="group-change">'
            +     '<select style="max-width:7.5em;font-size:1em" data-grpid="'+fld('rty_RecTypeGroupID')
            + '">'+group_selectoptions+'</select>'
            +  '</div>'
                + this._rendererActionButton('delete');
        }
        
        html = html + '</div>'; //close recordTitle

        html = html 
            +  ''   //counter
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
    _recordListGetFullData:function(arr_ids, pageno, callback){
        
        var request = {
                'a'          : 'search',
                'entity'     : 'defRecTypes',
                'details'    : 'list',
                'request_id' : pageno,
                'rty_ID'     : arr_ids,
                //'DBGSESSID'  : '423997564615200001;d=1,p=0,c=0'
        };
        
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    }
    
});

//
// Show as dialog
//
function showManageDefRecTypes( options ){

    var manage_dlg = $('#heurist-records-dialog');  //@todo - unique ID

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
        .appendTo( $('body') )
        .manageDefRecTypes( options );
    }

    manage_dlg.manageDefRecTypes
    ( 'popupDialog' );
}
