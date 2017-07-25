/**
* manageDefTerms.js - main widget to manage defTerms
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


$.widget( "heurist.manageRecUploadedFiles", $.heurist.manageEntity, {
   
    _entityName:'recUploadedFiles',
    
    _currentDomain:null,
    
    _editing2:null,
    
    _init: function() {
        this.options.layout_mode = 'short';
        this.options.use_cache = false;
        this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = true;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = 900;                    
            //this.options.edit_mode = 'none'
        }
    
        this._super();
    },
    
/* @todo - add this selector to search form
        this.select_order = $( "<select><option value='1'>"+
            window.hWin.HR("by name")+"</option><option value='5'>"+
            window.hWin.HR("by size")+"</option><option value='6'>"+
            window.hWin.HR("by usage")+"</option><option value='7'>"+
            window.hWin.HR("by date")+"</option><option value='8'>"+
            window.hWin.HR("marked")+"</option></select>", {'width':'80px'} )
*/    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        //this.options.list_mode = 'treeview';
        
        if(!this._super()){
            return false;
        }

        // init search header
        this.searchForm.searchRecUploadedFiles(this.options);
        
        var iheight = 4;
        //if(this.searchForm.width()<200){  - width does not work here  
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 8;
        }
        this.searchForm.css({'height':iheight+'em'});
        this.recordList.css({'top':iheight+0.4+'em'});
        
//        this.recordList.resultList('option','view_mode','thumbs');

        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }

        this._on( this.searchForm, {
                "searchrecuploadedfilesonresult": this.updateRecordList
                });
        this._on( this.searchForm, {
                "searchrecuploadedfilesonaddext": function() { this.addEditRecord(-1); }
                });
        this._on( this.searchForm, {
                "searchrecuploadedfilesonaddlocal": this._uploadFileAndRegister
                });
        
        return true;
    },
 
    _initEditForm_step4: function(recordset){
        
        this._currentEditRecordset = recordset; 
        
        var isLocal = true;
        if(recordset!=null){
            isLocal = window.hWin.HEURIST4.util.isempty(this._getField('ulf_ExternalFileReference'));
        }else{
            //new record
            isLocal = false;
        }
        
        var i_url = this.getEntityFieldIdx('ulf_HeuristURL');
        var i_url_ext = this.getEntityFieldIdx('ulf_ExternalFileReference');
        var i_filename = this.getEntityFieldIdx('ulf_OrigFileName');
        
        if(isLocal){ //local
            this.options.entity.fields[i_url].dtFields['rst_DefaultValue'] = window.hWin.HAPI4.baseURL
                                                    + '?db=' + window.hWin.HAPI4.database 
                                                    + '&file='+this._getField('ulf_ObfuscatedFileID');
            this.options.entity.fields[i_url].dtFields['rst_Display'] = 'readonly';
            this.options.entity.fields[i_url_ext].dtFields['rst_Display'] = 'hidden'; 
            this.options.entity.fields[i_filename].dtFields['rst_Display'] = 'readonly';
            
            
        }else{ //remote
            this.options.entity.fields[i_url].dtFields['rst_Display'] = 'hidden';
            this.options.entity.fields[i_url_ext].dtFields['rst_Display'] = 'visible';
            this.options.entity.fields[i_filename].dtFields['rst_Display'] = 'hidden';
        }
        
        this._super(recordset);
    },
    
    //
    // close or cancel edit dialof
    //
    closeEditDialog:function(){

        if(this._edit_dialog && this._edit_dialog.dialog('isOpen')){
            this._edit_dialog.dialog('close');
        }else{ //for inline reload inline forms
            //reload current
            that._initEditForm_step3(that._currentEditID)
        }
    },    
    
    //-----
    // perform required after edit form init modifications (show/hide fields, assign event listener )
    // for example hide/show some fields based on value of field
    //
    _afterInitEditForm: function(){

        this._super();

        var isLocal = true;
        
        if(this._currentEditRecordset){ //edit       

            //add media viewer below edit form and load media content
            this.mediaviewer = $('<div>').addClass('media-content').css({
                'text-align': 'center',
                padding: '20px',
                'border-top': '1px solid lightgray',
                margin: '10px'});
            this.editForm.append( this.mediaviewer );
            this.mediaviewer.media_viewer({rec_Files:[[
                    this._editing.getValue('ulf_ObfuscatedFileID'), 
                    this._editing.getValue('ulf_MimeExt')]]}); //nonce + memtype
                
                
            var relations = this._currentEditRecordset.getRelations();    
            if(relations && relations.direct && relations.direct.length>0){
                $('<div class="detailRowHeader">Records that refer this file</div>').appendTo(this.editForm);
                
                var direct = relations.direct;
                var headers = relations.headers;
                var ele1=null;
                for(var k in direct){
                    var targetID = direct[k].targetID;
                    
                    window.hWin.HEURIST4.ui.createRecordLinkInfo(this.editForm, 
                                {rec_ID: targetID, 
                                 rec_Title: headers[targetID][0], 
                                 rec_RecTypeID: headers[targetID][1]}, true);
                }//for
            }
            
            
            isLocal = window.hWin.HEURIST4.util.isempty(this._getField('ulf_ExternalFileReference'));
        }else{
            //new record
            isLocal = false;
        }
        
        if(!isLocal){
            var that = this;
            var ele = that._editing.getFieldByName('ulf_ExternalFileReference');
            ele.editing_input('option', 'change', function(){ 
                var res = ele.editing_input('getValues'); 
                var ext = window.hWin.HEURIST4.util.getFileExtension(res[0]);
                var ele2 = that._editing.getFieldByName('ulf_MimeExt');
                ele2.editing_input('setValue', ext );
                that.onEditFormChange();
            });
        }        
                
                
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
        
        var recID   = fld('ulf_ID');
        
        var rectype = fld('ulf_ExternalFileReference')?'external':'local';
        //var isEnabled = (fld('ugr_Enabled')=='y');
        
        var recTitle = fld2((rectype=='external')?'ulf_ExternalFileReference':'ulf_OrigFileName','20em');
        var recTitleHint = fld((rectype=='external')?'ulf_ExternalFileReference':'ulf_OrigFileName');
        
        var recIcon = '';//@todo window.hWin.HAPI4.iconBaseURL + '../entity-icons/sysUGrps/' + rectype + '.png';


        var html_thumb = '<div class="recTypeThumb realThumb" style="background-image: url(&quot;'+ 
        window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
                    fld('ulf_ObfuscatedFileID') + '&quot;);opacity:1"></div>';

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectype+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+recIcon+'&quot;);">'   //class="rt-icon" 
        + '</div>'
        + '<div class="recordTitle" title="'+recTitleHint+'">'
        +     recTitle
        + '</div>'
        /* @todo
        + '<div title="Click to edit file" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>&nbsp;&nbsp;'
        + '<div title="Click to delete file" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
        + '</div>'
        */
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
    /*
    _recordListGetFullData:function(arr_ids, pageno, callback){
        
        var request = {
                'a'          : 'search',
                'entity'     : this._entityName,
                'details'    : 'list',
                'page_no'    : pageno,
                'ulf_ID'     : arr_ids
                //'DBGSESSID'  : '423997564615200001;d=1,p=0,c=0'
        };
        
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    }
    */  

    
    //
    // open empty edit dialog
    // upload file 
    // get new ulf_ID
    // open edit form with this new record
    //
    _uploadFileAndRegister: function(){
        //find file element
        var that = this;
        
            if(!this._editing2){

                var container = $('<div>').css({width:0,height:0}).appendTo(this.editForm.parent());
                
                this._editing2 = new hEditing({container:container, 
                 onchange:
                function(){
                    var ele = that._editing2.getFieldByName('ulf_FileUpload');
                    var res = ele.editing_input('getValues'); 
                    var ulf_ID = res[0];

                    if(ulf_ID>0){
                        that._currentEditID = null;//to prevent warn about save
                        that.addEditRecord(ulf_ID);
                    }
                }}); //pass container
            
            }
        
            this._editing2.initEditForm([                {
                    "dtID": "ulf_FileUpload",
                    "dtFields":{
                        "dty_Type":"file",
                        "rst_DisplayName":"File upload:",
                        "rst_FieldConfig":{"entity":"recUploadedFiles"},
                        "dty_Role":"virtual"
                    }
                }], null);
            this._editing2.getContainer().hide();
            var ele = this._editing2.getFieldByName('ulf_FileUpload');    
            ele.find('.fileupload').click();
        /*
        that._initEditForm_step1(-1, function(){
            var ele = that._editing.getFieldByName('ulf_FileUpload');
            ele.editing_input('option', 'change', function(){ 
                var res = this.getValues(); 
                var ulf_ID = res[0];
                that._currentEditID = null;//to prevent warn about save
                that.addEditRecord(ulf_ID);
            });
            ele.find('.fileupload').click();
        });
        */
    }
    
});

//
// Show as dialog - to remove
//
function showManageRecUploadedFiles( options ){

    var manage_dlg = $('#heurist-records-dialog');  //@todo - unique ID

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
                .appendTo( $('body') )
                .manageRecUploadedFiles( options );
    }

    manage_dlg.manageRecUploadedFiles( 'popupDialog' );
}
