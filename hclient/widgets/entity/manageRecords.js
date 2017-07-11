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


$.widget( "heurist.manageRecords", $.heurist.manageEntity, {
   
    _entityName:'records',
    
    _currentEditRecTypeID:null,
    
    _init: function() {
        this.options.layout_mode = 'short';
        this.options.use_cache = false;
        this.options.edit_height = 640;
        this.options.edit_width = 1200;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = 640;                    
            this.options.edit_mode = 'none'
        }else{
            this.options.width = 1200;                    
        }
    
        this._super();
        
        if(!(this.options.select_mode!='manager' || this.options.edit_mode!='inline')){
            //edit form is not visible
            this.recordList.parent().width(640);
            this.editForm.parent().css('left',641);
        }
        
        //-----------------
        this.recordList.css('top','5.5em');
        this.searchForm.height('7.5em').css('border','none');
        
    },
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        // init search header
        this.searchForm.searchRecords(this.options);
/*        
        var iheight = 2;
        //if(this.searchForm.width()<200){  - width does not work here  
        if(this.options.select_mode=='manager'){            
            iheight = iheight + 4;
        }
        
        this.searchForm.css({'height':iheight+'em'});
        this.recordList.css({'top':iheight+0.4+'em'});
*/        
        this.recordList.resultList({
                searchfull:null,
                renderer:true //use default renderer but custom onaction see _onActionListener
        }); //use default recordList renderer
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }

        this._on( this.searchForm, {
                "searchrecordsonresult": this.updateRecordList,
                "searchrecordsonaddrecord": function( event, _rectype_id ){
                    this._currentEditRecTypeID = _rectype_id;
                    this._addEditRecord(-1);
                }
        });
                
       //---------    EDITOR PANEL - DEFINE ACTION BUTTONS
       //if actions allowed - add div for edit form - it may be shown as right-hand panel or in modal popup
       if(this.options.edit_mode!='none'){
/*
           //define add button on left side
           this._defineActionButton({key:'add', label:'Add New Vocabulary', title:'', icon:'ui-icon-plus'}, 
                        this.editFormToolbar, 'full',{float:'left'});
                
           this._defineActionButton({key:'add-child',label:'Add Child', title:'', icon:''},
                    this.editFormToolbar);
           this._defineActionButton({key:'add-import',label:'Import Children', title:'', icon:''},
                    this.editFormToolbar);
           this._defineActionButton({key:'merge',label:'Merge', title:'', icon:''},
                    this.editFormToolbar);
               
           //define delete on right side
           this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'},
                    this.editFormToolbar,'full',{float:'right'});
*/                    
       }
        
       return true;
    },
    
    //override some editing methods
    //
    //
    //
    _initEditForm_continue: function(recID){
        
        //fill with values
        this._currentEditID = recID;
        
        var that = this;
        
        function __load(response){
            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                
                //@todo - move navigation for recordset into editing
                var recordset = new hRecordSet(response.data);
                var record = recordset.getFirstRecord();
                var rectypeID = recordset.fld(record, 'rec_RecTypeID');
                var rectypes = window.hWin.HEURIST4.rectypes;
                var rfrs = rectypes.typedefs[rectypeID].dtFields;
                
                //pass structure and record details
                that._currentEditID = recordset.fld(record, 'rec_ID');;
                that._currentEditRecTypeID = rectypeID;
                
                //@todo - move it inside editing
                //convert structure - 
                var fields = window.hWin.HEURIST4.util.cloneJSON(that.options.entity.fields);
                var fieldNames = rectypes.typedefs.dtFieldNames;
                var fi = rectypes.typedefs.dtFieldNamesToIndex;

                var fi_type = fi['dty_Type'],
                    fi_name = fi['rst_DisplayName'],
                    fi_order = fi['rst_DisplayOrder'],
                    fi_maxval = fi['rst_MaxValues']; //need for proper repeat
                
                var s_fields = []; //sorted fields
                for(var dt_ID in rfrs){
                    if(dt_ID>0){
                        rfrs[dt_ID]['dt_ID'] = dt_ID;
                        s_fields.push(rfrs[dt_ID]);
                    }
                }
                
                //sort by order
                s_fields.sort(function(a,b){ return a[fi_order]<b[fi_order]?-1:1});

                var group_fields = null;
                
                for(var k=0; k<s_fields.length; k++){
                    
                    rfr = s_fields[k];
                    
                    if(rfr[fi_type]=='separator'){
                        if(group_fields!=null){
                            fields[fields.length-1].children = group_fields;
                        }
                        var dtGroup = {
                            groupHeader: rfr[fi_name],
                            groupType: 'group', //accordion, tabs, group
                            groupStyle: {},
                            children:[]
                        };
                        fields.push(dtGroup);
                        group_fields = [];
                    }else {
                    
                        var dtFields = {};
                        for(idx in rfr){
                            if(idx>=0){
                                dtFields[fieldNames[idx]] = rfr[idx];
                                
                                if(idx==fi_type){ //fieldNames[idx]=='dty_Type'){
                                    if(dtFields[fieldNames[idx]]=='file'){
                                        dtFields['rst_FieldConfig'] = {"entity":"records", "accept":".png,.jpg,.gif", "size":200};
                                    }   
                                }else if(idx==fi_maxval){
                                    if(window.hWin.HEURIST4.util.isnull(dtFields[fieldNames[idx]])){
                                        dtFields[fieldNames[idx]] = 0;
                                    }
                                }
                            }
                        }//for
                        
                        if(group_fields!=null){
                            group_fields.push({"dtID": rfr['dt_ID'], "dtFields":dtFields});
                        }else{
                            fields.push({"dtID": rfr['dt_ID'], "dtFields":dtFields});
                        }
                    }
                }//for rfrs
                //add children to last group
                if(group_fields!=null){
                    fields[fields.length-1].children = group_fields;
                }
                
                that._editing.initEditForm(fields, recordset);
                that._afterInitEditForm();
            }else{
                window.hWin.HEURIST4.util.showMsgErr(response);
            }
        }        
        
        
        if(recID==null){
            this._editing.initEditForm(null, null); //clear and hide
        }else if(recID>0){ //edit existing record
            
            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "all", f:"detail", l:1}, __load);

        }else if(recID<0 && this._currentEditRecTypeID>0){ //add new record
            //this._currentEditRecTypeID is set in add button
            window.hWin.HAPI4.RecordMgr.add( {rt:this._currentEditRecTypeID, temp:1}, //ro - owner,  rv - visibility
                        __load);
        }

        return;
    },
    
    
    //  -----------------------------------------------------
    //
    //  send update request and close popup if edit is in dialog
    // OVERRIDE
    //
    _saveEditAndClose: function(){

            var fields = this._getValidatedValues(); 
            
            if(fields==null) return; //validation failed
       
            var request = {ID: this._currentEditID, RecTypeID: this._currentEditRecTypeID, 'details': fields};
        
                var that = this;                                                
                //that.loadanimation(true);
                window.hWin.HAPI4.RecordMgr.save(request, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                            var recID = ''+response.data[0];
                            
                            that._afterSaveEventHandler( recID, fields );
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
    },       
    
    
    
});

//
// Show as dialog - to remove
//
function showManageRecords( options ){

    var manage_dlg; // = $('#heurist-records-dialog');  //@todo - unique ID

    if(true){ //manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog-'+window.hWin.HEURIST4.util.random()+'">')
                .appendTo( $('body') )
                .manageRecords( options );
    }

    manage_dlg.manageRecords( 'popupDialog' );
}
