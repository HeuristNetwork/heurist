/**
* manageSysUsers.js - main widget mo manage sys users
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

    // the widget's constructor
    _create: function() {

        this._super();

        this._entityTitle = 'User',
        this._entityTitlePlural = 'Users',
        this._empty_remark = 'Please use the search field above to locate relevant user (partial string match on name)',
        
        this._default_sel_actions = [{key:'edit', title:'Edit'},
                          {key:'delete', title:'Delete'},
                          {key:'merge', title:'Merge'},
                          {key:'import', title:'Import'}];
                          
        this._default_btn_actions = [{key:'add', title:'Add New User'}];
        
    }, //end _create
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
        this._super();

        // init search header
        this.searchForm.searchSysUsers(this.options);
            
        this._on( this.searchForm, {
                "searchsysusersonresult": this.updateRecordList
                });
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
        var rectype = fld('ugr_Type');
        var isEnabled = (fld('ugr_Enabled')=='y');
        
        var recTitle = fld2('ugr_Name','10em')+
        '<div class="item" style="width:25em">'+fld('ugr_FirstName')+' '+fld('ugr_LastName')+'</div>'+fld2('ugr_Organisation')+fld2('ugl_Role');
        
        
        var recIcon = window.hWin.HAPI4.iconBaseURL + '../entity-icons/sysUGrps/' + rectype + '.png';


        var html_thumb = '';
        if(fld('ugr_ThumbnailURL')){
            html_thumb = '<div class="recTypeThumb realThumb" style="background-image: url(&quot;'+ fld('ugr_ThumbnailURL') + '&quot;);opacity:1"></div>';
        }else{
            html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+ 
                window.hWin.HAPI4.iconBaseURL + '../entity-icons/sysUGrps/thumb/' + rectype + '.png&quot;);"></div>';
        }

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectype+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+recIcon+'&quot;);">'   //class="rt-icon" 
        +     '<span class="ui-icon ui-icon-flag" style="color:'+(isEnabled?'#ff8844':'#dddddd')+';display:inline;left:4px">&nbsp;&nbsp;</span>'           
        + '</div>'
        + '<div class="recordTitle">'
        +     recTitle
        + '</div>'
        + '<div title="Click to edit user" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>&nbsp;&nbsp;'
        + '<div title="Click to delete user" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
        + '</div>'
        /*+ '<div title="Click to view record (opens in popup)" '
        + '   class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
        + '   role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span><span class="ui-button-text"></span>'
        + '</div>'*/
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
                //'DBGSESSID'  : '423997564615200001;d=1,p=0,c=0'
        };
        
        if(this.searchForm){
            var selectGroup = this.searchForm.find('#sel_group');
            if(selectGroup.val()!=''){
                    request['ugr_Type'] = 'user';
                    request['ugl_GroupID'] = selectGroup.val();
            }
        }
        
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    }
    
});

//
// Show as dialog
//
function showManageSysUsers( options ){

    var manage_dlg = $('#heurist-records-dialog');

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
        .appendTo( $('body') )
        .manageSysUsers( options );
    }

    manage_dlg.manageSysUsers( 'popupDialog' );
}
