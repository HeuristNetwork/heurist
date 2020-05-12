/**
* manageDefRecTypes.js - main widget to manage defRecTypes users
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

/*
we may take data from 
1) use_cache = false  from server on every search request (live data) 
2) use_cache = true   from client cache - it loads once per heurist session (actually we force load)
3) use_cache = true + use_structure - use HEURSIT4.rectypes
*/
$.widget( "heurist.manageDefRecTypes", $.heurist.manageEntity, {
   
    _entityName:'defRecTypes',
    
    is_new_icons: true,

    usrPreferences:{},
    defaultPrefs:{
        width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
        height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
        groupsPresentation:'tab', //'none','tab',list','select'
//'id','ccode','addrec','filter','count','group','icon','edit','editstr','name',description','show','duplicate','fields','status'        
        fields:['editstr','name'] 
        },
    
    //
    //                                                  
    //    
    _init: function() {
        
        //define it to load recordtypes from other server/database - if defined it allows selection only
        if(this.options.import_structure){ //for example https://heuristplus.sydney.edu.au/heurist/?db=Heurist_Reference_Set
            if(this.options.select_mode=='manager') this.options.select_mode='select_single';
            this.options.use_cache = true;
            this.options.use_structure = true;    
        }else{
            this.options.use_cache = true;
            this.options.use_structure = true;
        }
        
//console.log(this.options);        
        
        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        
        //this.options.select_return_mode = 'recordset';
        this.options.edit_need_load_fullrecord = true;
        this.options.edit_height = 640;
        this.options.edit_width = 1000;
        this.options.height = 640;

        if(this.options.edit_mode=='editonly'){
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 790;
            //this.options.height = 640;
        }else
        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                    
            //this.options.edit_mode = 'none'
        }
    
        this._super();
        
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
        
        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly();
            return;
        }
        
        //update dialog title
        if(this.options.isdialog){ // &&  !this.options.title
            var title = null;
            
            if(this.options.title){
                title = this.options.title;
            }else
            if(this.options.select_mode=='select_single'){
               title = 'Select Record Type'; 
            }else
            if(this.options.select_mode=='select_multi'){
               title = 'Select Record Types'; 
              
              if(this.options.rtg_ID<0){ 
                    title += ' to add to group '+window.hWin.HEURIST4.rectypes.groups[Math.abs(this.options.rtg_ID)].name;
              }
               
            }else
            if(this.options.rtg_ID>0){
                title = 'Manage Record types of group '+window.hWin.HEURIST4.rectypes.groups[this.options.rtg_ID].name;
            }else{
                title = 'Manage Record Types';    
            }
            
            this._as_dialog.dialog('option','title', title);    
        }
        
        var iheight = 0;
        if(this.searchForm.css('display')=='none'){
        
        }else{
            iheight = this.options.simpleSearch?3:7;
            if(this.options.edit_mode=='inline'){            
                iheight = iheight + 6;
            }
            this.searchForm.css({'height':iheight+'em',padding:'10px', 'min-width': '530px'});
            iheight = iheight+0.5;
        }
        this.recordList.css({'top':iheight+'em', 'min-width': '530px'});
        
        //init viewer 
        var that = this;
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
            
            
            this.recordList.resultList({rendererHeader:
                    function(){
                    var s = '<div style="width:40px"></div><div style="width:3em">ID</div>'
                                +'<div style="width:13em">Name</div>'
                                +'<div style="width:20em;border:none;">Description</div>';
                        
                        if (window.hWin.HAPI4.is_admin()){
                            s = s+'<div style="position:absolute;right:4px;width:60px">Edit</div>';
                        }
                        
                        return s;
                    }/*,
                    groupMode:['none','tab','groups'],
                    groupByField:'rty_RecTypeGroupID',
                    //groupOnlyOneVisible: true,
                    groupByCss:'0 1.5em',
                    rendererGroupHeader: function(grp_val, grp_keep_status){

                        var rectypes = window.hWin.HEURIST4.rectypes;
                        var idx = rectypes.groups.groupIDToIndex[grp_val];

                        var is_expanded = (grp_keep_status[grp_val]!=0);

                        return rectypes.groups[idx]?('<div data-grp="'+grp_val
                            +'" style="font-size:0.9em;padding:14px 0 4px 0px;border-bottom:1px solid lightgray">'
                            +'<span style="display:inline-block;vertical-align:top;padding-top:10px;" class="ui-icon ui-icon-triangle-1-'+(is_expanded?'s':'e')+'"></span>'
                            +'<div style="display:inline-block;width:70%">'
                            +'<h2>'+grp_val+'  '+rectypes.groups[idx].name+'</h2>' //+grp_val+' '
                            +'<div style="padding-top:4px;"><i>'+rectypes.groups[idx].description+'</i></div></div></div>'):'';
                    }*/
            });
            //this.recordList.resultList('applyViewMode');
        }
        
        //may overwrite resultList behaviour
        if(this.options.recordList){
            this.recordList.resultList( this.options.recordList );
        }

        this.searchFormList = this.element.find('.searchForm-list');
        if(this.searchFormList.length>0){
            this.options.searchFormList =  this.searchFormList;
        }
        this.options.ui_params = this.getUiPreferences();
        this.options.onInitCompleted =  function(){
            that._loadData();
            that.changeUI(event, that.options.ui_params);    
        }
        
        this.searchForm.searchDefRecTypes(this.options);
        
        if(this.options.use_cache){
           
            //if there are many widgets need to use base searchentityonfilter
           this._on( this.searchForm, {
                "searchdefrectypesonfilter": this.filterRecordList,
                "searchdefrectypesonuichange": this.changeUI  //grouping, visible columns  
           });
        }else{
            this._on( this.searchForm, {
                "searchdefrectypesonresult": this.updateRecordList,
                "searchdefrectypesonadd": function() { this.addEditRecord(-1); },
                "searchdefrectypesonuichange": this.changeUI  //grouping, visible columns
                });
        }
        
        
        
        return true;
    },            
    
    //
    // invoked after all elements are inited 
    //
    _loadData: function(){
        
        var that = this;
      
        if(this.options.use_cache){
           
           /*this._on( this.searchForm, {
                "searchdefrectypesonfilter": this.filterRecordList  
           });*/
                

            if(this.options.use_structure){  //get recordset from HEURIST4.rectypes
                
                if(this.options.import_structure){
                    //take recordset from REMOTE HEURIST.rectypes format     
                    window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);
                    
                    this.recordList.resultList('resetGroups');
                    
                    window.hWin.HAPI4.SystemMgr.get_defs(
                            {rectypes:'all', detailtypes:'all', mode:2, remote:this.options.import_structure.database_url}, function(response){
                    
                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                            
                            if(response.status == window.hWin.ResponseStatus.OK){
                                
                                window.hWin.HEURIST4.remote = {};
                                window.hWin.HEURIST4.remote.rectypes = response.data.rectypes;
                                window.hWin.HEURIST4.remote.detailtypes = response.data.detailtypes;
                                //window.hWin.HEURIST4.remote.terms = response.data.terms;
                                
                                that._cachedRecordset = that.getRecordsetFromStructure( response.data.rectypes, false ); //change to true to hide where rty_ShowInList=0
                                /*
                                if(that.options.import_structure.load_detailstypes){
                                    window.hWin.HAPI4.SystemMgr.get_defs(
                                        {detailtypes:'all', mode:0, remote:that.options.import_structure.database_url},
                                        function(response){
console.log(response);                                            
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                window.hWin.HEURIST4.remote.detailtypes = response.data.detailtypes;
                                            }
                                        });
                                }*/
                                
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                    });                    
                    
                }else{
                    //take recordset from LOCAL HEURIST.rectypes format     
                    this._cachedRecordset = this.getRecordsetFromStructure();
                }
            }
            else{
                //this.options.database_url
                
                //usual way from server via entity
                var that = this;
                window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
                    function(response){
                        that._cachedRecordset = response;
                        that.recordList.resultList('updateResultSet', response);
                        that.searchForm.searchDefRecTypes('startSearch');
                    });
            }
                
        }    
        
    },

    //
    // get recordset from HEURIST4.rectypes
    //
    getRecordsetFromStructure: function( rectypes, hideDisabled ){
        
        var rdata = { 
            entityName:'defRecTypes',
            total_count: 0,
            fields:[],
            records:{},
            order:[] };
      

        if(!rectypes){
            //by default take from local definitions
            rectypes = window.hWin.HEURIST4.util.cloneJSON(window.hWin.HEURIST4.rectypes);
        }else{
            //reload groups for remote rectypes            
            //var ele = this.element.find('#input_search_group');   //rectype group
            rectypes = window.hWin.HEURIST4.util.cloneJSON(rectypes);
            this.searchForm.searchDefRecTypes('reloadGroupSelector', rectypes); //get remote groups
            
            //var ele = this.searchForm.find('#input_search_group');
            //window.hWin.HEURIST4.ui.createRectypeGroupSelect(ele[0],
            //                            [{key:'any',title:'any group'}], rectypes);
        }

        rdata.fields = rectypes.typedefs.commonFieldNames;
        rdata.fields.unshift('rty_ID');
        
        var idx_ccode = 0;
        if(this.options.import_structure){
            rdata.fields.push('rty_ID_local');
            idx_ccode = window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_ConceptID;
        }

        var idx_visibility = rectypes.typedefs.commonNamesToIndex.rty_ShowInLists;
        var hasRtToImport = false;

        for (var r_id in rectypes.typedefs)
        {
            if(r_id>0){
                var rectype = rectypes.typedefs[r_id].commonFields;

                if(hideDisabled && rectype[idx_visibility]=='0' ){
                    continue;
                }
                
                if(this.options.import_structure){
                    var concept_code =  rectype[ idx_ccode ];
                    var local_rtyID = window.hWin.HEURIST4.dbs.findByConceptCode( concept_code, 
                                                            window.hWin.HEURIST4.rectypes.typedefs, idx_ccode );
                    rectype.push( local_rtyID );
                    hasRtToImport = hasRtToImport || !(local_rtyID>0);
                }
                
                rectype.unshift(r_id);
                
                rdata.records[r_id] = rectype;
                rdata.order.push( r_id );
                
            }
        }
        rdata.count = rdata.order.length;
        
        
        if(this.options.import_structure){
            this.recordList.resultList('option', 'empty_remark',
                                        '<div style="padding:1em 0 1em 0">'+
                                        (hasRtToImport
                                        ?this.options.entity.empty_remark
                                        :'Your database already has all the entity types available in this source'
                                        )+'</div>');
        }
        
        this._cachedRecordset = new hRecordSet(rdata);
        this.recordList.resultList('updateResultSet', this._cachedRecordset);
        
        this.searchForm.searchDefRecTypes('startSearch');
        
        return this._cachedRecordset;
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
        
        var recID   = fld('rty_ID');
        
        var recTitleHint = fld('ugr_Organisation');
        
        var recTitle = ''
        
        if(false &&  this.options.import_structure){ //Ian dwi
            recTitle = recTitle + fld2('rty_ID','3.5em')+fld2('rty_ID_local','3.5em');
        }
            
        recTitle = recTitle + fld2('rty_Name','15em')
            + ' : <div class="item" style="font-style:italic;width:45em">'
            + window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, 'rty_Description'))+'</div>'
        
        var rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'icon');
        var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        if(this.is_new_icons){
            rtIcon = window.hWin.HAPI4.iconBaseURL+recID; 
            recThumb = window.hWin.HAPI4.iconBaseURL+'thumb/th_'+recID; 
        }
        
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);">'
        +'</div>';
        
        if(this.options.import_structure){
            //Ian dwi
            rtIcon = this.options.import_structure.databaseURL
                                + '/hsapi/dbaccess/rt_icon.php?db='
                                + this.options.import_structure.database + '&id=';
            recThumb = rtIcon+'thumb/th_'+recID;
            rtIcon = rtIcon + recID;
            
            html_thumb = '';
            rtIcon = '';
        }
        
        var html_icon = '<div class="recordIcons" style="min-width:16px;">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '"  class="rt-icon" style="background-image: url(&quot;'+rtIcon+'&quot;);">'       //opacity:'+recOpacity+'
        + '</div>';
        
        
        var has_buttons = (this.options.select_mode=='manager' && this.options.edit_mode=='popup');

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + html_icon
        + '<div class="recordTitle recordTitle2" title="'+fld('rty_Description')
                        +'" style="right:'+(has_buttons?'60px':'10px')
                        + (this.options.import_structure?';left:30px':'')+'">'
        +     recTitle
        + '</div>';
        
        // add edit/remove action buttons
        if(has_buttons){
        
                
               html = html 
                + '<div class="rec_actions" style="top:4px;width:60px;">'
                    + '<div title="Click to edit record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Click to delete record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                    + '</div></div>';
        }
        

        html = html + '</div>';

        return html;
        
    },
    
    //overwritten    
    _recordListGetFullData:function(arr_ids, pageno, callback){

        var request = {
                'a'          : 'search',
                'entity'     : this.options.entity.entityName,
                'details'    : 'list',
                'pageno'     : pageno,
                'db'         : this.options.database  
                
        };
        var rty_RecTypeGroupID = this.searchForm.find('#input_search_group').val();
        if(rty_RecTypeGroupID>0){
            request['rty_RecTypeGroupID'] = rty_RecTypeGroupID;
        }
        
        
        request[this.options.entity.keyField] = arr_ids;
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    },
    
    
    //-----
    //
    // adding group ID value for new rectype
    //
    _afterInitEditForm: function(){

        this._super();
        
        var rty_RecTypeGroupID = this.searchForm.find('#input_search_group').val();
        if(rty_RecTypeGroupID>0 && !this._currentEditRecordset){ //insert       

            var ele = this._editing.getFieldByName('rty_RecTypeGroupID');
            ele.editing_input('setValue', rty_RecTypeGroupID);
            //hide save button
            if(this._toolbar){
                this._toolbar.find('#btnRecSave').css('visibility', 'visible');
            }
        }else
        //hide after edit init btnRecRemove for status locked 
        if(false){ //@todo
            var ele = this._toolbar;
            ele.find('#btnRecRemove').hide();
        }

    },    
    
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        //this._currentEditID<0 && 
        if(this.options.select_mode=='select_single'){
            
                this._selection = new hRecordSet();
                //{fields:{}, order:[recID], records:[fieldvalues]});
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;    
                    
        }
        
        this._super( recID, fieldvalues );
        this.getRecordSet().setRecord(recID, fieldvalues);
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else{
            this.recordList.resultList('refreshPage');  
        }
    },
    
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this record type? Proceed?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    getUiPreferences:function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
        /*if(this.usrPreferences.width<600) this.usrPreferences.width=600;
        if(this.usrPreferences.height<300) this.usrPreferences.height=300;
        if (this.usrPreferences.width>this.defaultPrefs.width) this.usrPreferences.width=this.defaultPrefs.width;
        if (this.usrPreferences.height>this.defaultPrefs.height) this.usrPreferences.height=this.defaultPrefs.height;*/
        return this.usrPreferences;
    },
    
    saveUiPreferences:function(new_params){
        
        if(new_params){
            var params = this.getUiPreferences();
            
            params['fields'] = new_params['fields']; 
            params['groupsPresentation'] = new_params['groupsPresentation']; 
        
            window.hWin.HAPI4.save_pref('prefs_'+this._entityName, params);
        }
        return true;
    },
    
    //
    // update ui and call save prefs
    //
    changeUI: function( event, params ){
        
        if(this.options.edit_mode=='editonly') return;
        
        params['tabheight'] = this.searchForm.searchDefRecTypes('changeUI');
        
        var iheight = 40+(params['tabheight']>0?params['tabheight']:0);
        
        this.searchForm.css({'height':iheight});
        this.recordList.css({'top':iheight});     

        if(params['groupsPresentation']=='list'){
            this.recordList.css({'left':'171px'});
            this.searchFormList.css({'top':this.recordList.css('top')}).show();
        }else{
            this.searchFormList.hide();
            this.recordList.css('left',0);
        }
        
        
        this.saveUiPreferences( params );
        
    }
    
});
