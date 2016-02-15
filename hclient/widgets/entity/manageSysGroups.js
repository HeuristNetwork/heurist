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


$.widget( "heurist.manageSysGroups", $.heurist.manageEntity, {
    
    // the widget's constructor
    _create: function() {
        
        this._super();

        this._entityTitle = 'Group',
        this._entityTitlePlural = 'Groups',
        this._empty_remark = 'Please use the search field above to locate relevant group (partial string match on title)',
        
        this._default_sel_actions = [{key:'edit', title:'Edit'},
                          {key:'delete', title:'Delete'},
                          {key:'merge', title:'Merge'},
                          {key:'membership', title:'Membership'}];
                          
        this._default_btn_actions = [{key:'add', title:'Add New Group'}];
        
    }, //end _create
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
        this._super();

        // init search header
        this.searchRecord.searchSysGroups(this.options);
            
        this._on( this.searchRecord, {
                "searchsysgroupsonresult": this.updateRecordList
                });
                
    },
    
    //----------------------
    //
    //
    //
    _recordListItemRenderer:function(recordset, record){
        
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
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('ugr_ID');
        var rectype = fld('ugr_Type');
        
        var recTitle = fld2('ugr_ID','4em')+fld2('ugr_Name','14em')+fld2('ugr_Description','25em');
        
        
        var recIcon = top.HAPI4.iconBaseURL + '../entity-icons/sysUGrps/' + rectype + '.png';

        var html_thumb = '';
        if(fld('ugr_ThumbnailURL')){
            html_thumb = '<div class="recTypeThumb realThumb" style="background-image: url(&quot;'+ fld('ugr_ThumbnailURL') + '&quot;);opacity:1"></div>';
        }else{
            html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+ 
                top.HAPI4.iconBaseURL + '../entity-icons/sysUGrps/thumb/' + rectype + '.png&quot;);"></div>';
        }

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectype+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">'
        +     '<img src="'+top.HAPI4.basePathV4+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+recIcon+'&quot;);">'   //class="rt-icon" 
        + '</div>'
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
                'entity'     : 'sysUGrps',
                'details'    : 'list',
                'request_id' : pageno,
                'ugr_ID'     : arr_ids,
                'ugr_Type'   : 'workgroup'  //it is need to get members count
                //'DBGSESSID'  : '423997564615200001;d=1,p=0,c=0'
        };
        
        top.HAPI4.EntityMgr.doRequest(request, callback);
    }
    
});

//
// Show as dialog
//
function showManageSysGroups( options ){

    var manage_dlg = $('#heurist-records-dialog');  //@todo - unique ID

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
        .appendTo( $('body') )
        .manageSysGroups( options );
    }

    manage_dlg.manageSysGroups( 'popupDialog' );
}
