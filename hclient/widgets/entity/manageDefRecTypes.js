/**
* manageDefRecTypes.js - main widget to manage defRecTypes users
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
//"use_assets":["admin/setup/iconLibrary/64px/","HEURIST_ICON_DIR/thumb/"],

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
//rtyid,'ccode','addrec','filter','count','group','icon','edit','editstr','name','description','show','duplicate','fields','status'        
        fields:['count','icon','editstr','name','description','show','duplicate','fields','status'] 
        },
    
    //
    //                                                  
    //    
    _init: function() {
        
        //define it to load recordtypes from other server/database - if defined it allows selection only
        if(this.options.import_structure){ //for example https://heuristplus.sydney.edu.au/heurist/?db=Heurist_Reference_Set
            if(this.options.select_mode=='manager') this.options.select_mode='select_single';
            this.options.use_cache = true;
            this.options.use_structure = true; //use HEURIST4.rectypes    
        }else{
            this.options.use_cache = true;
            this.options.use_structure = false
        }
        
//console.log(this.options);        
        
        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        
        //this.options.select_return_mode = 'recordset';
        this.options.edit_height = 640;
        this.options.edit_width = 1000;
        this.options.height = 640;

        if(this.options.edit_mode=='editonly'){
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 1000;
            //this.options.height = 640;
        }else
        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                    
            //this.options.edit_mode = 'none'
        }
        
        this._super();
        
        
//console.log(this.options.is_h6style +'   '+ this.options.innerTitle);        

        if(this.options.innerTitle){
            
            if(this.options.ui_params) this.options.ui_params.groupsPresentation = 'none';
            //add record type group editor
            this.element.css( {border:'none', 'box-shadow':'none', background:'none'} );
            
            this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css('left',328);
            
            this.rectype_groups = $('<div>').addClass('ui-dialog-heurist')
                .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:320, overflow: 'hidden'})
                .appendTo(this.element);
        }                
        
        var that = this;
        
        $(window.hWin.document).on(
            window.hWin.HAPI4.Event.ON_REC_UPDATE
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
                if(!data || 
                   (data.source != that.uuid && data.type == 'rty'))
                {
                    that._loadData();
                }
            });
        
        
    },
    
    _destroy: function() {
        
       $(window.hWin.document).off(
            window.hWin.HAPI4.Event.ON_REC_UPDATE
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
        
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
        
        var iheight = 0;
        if(this.searchForm.css('display')=='none'){
        
        }else{
            iheight = this.options.simpleSearch?3:7;
            if(this.options.edit_mode=='inline'){            
                iheight = iheight + 6;
            }
            this.searchForm.css({'height':iheight+'em',padding:'6px', 'min-width': '530px'});
            iheight = iheight+0.5;
        }
        this.recordList.css({'top':iheight+'em', 'min-width': '530px'});
        
        //init viewer 
        var that = this;
        
        if(this.options.select_mode=='manager'){
            
            this.recordList.resultList({ 
                    show_toolbar: false,
                    list_mode_is_table: true,
                    rendererHeader:function(){ return that._recordListHeaderRenderer() },
                    draggable: function(){
                        
                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                                    revert: true,
                                    helper: function(){ 
                                        return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                            $(this).parent().attr('recid')
                                        +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                        +'>Drag and drop to group item to change record type group</div>'); 
                                    },
                                    zIndex:100,
                                    appendTo:'body',
                                    scope: 'rtg_change'
                                    //containment: that.element,
                                    //delay: 200
                                });   
                    }});
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
            
            if(that.options.innerTitle){
                var rg_options = {
                     isdialog: false, 
                     innerTitle: true,
                     container: that.rectype_groups,
                     title: 'Record type groups',
                     layout_mode: 'short',
                     select_mode: 'manager',
                     reference_rt_manger: that.element,
                     onSelect:function(res){
console.log('onSELECT!!!!');
                         if(window.hWin.HEURIST4.util.isRecordSet(res)){
                            res = res.getIds();                     
                            if(res && res.length>0){
                                that.options.rtg_ID = res[0];
                                that.searchForm.searchDefRecTypes('option','rtg_ID', res[0])
                            }
                         }
                     }
                };
                window.hWin.HEURIST4.ui.showEntityDialog('defRecTypeGroups', rg_options);
            }
            
        }
        
        this.searchForm.searchDefRecTypes(this.options);
        
        if(this.options.use_cache){
           
            //if there are many widgets need to use base searchentityonfilter
           this._on( this.searchForm, {
                "searchdefrectypesonfilter": this.filterRecordList,
                "searchdefrectypesonadd": function() {
                        this._onActionListener(null, 'add');
                },
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

                                
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                    });                    
                    
                }else{
                    if(!window.hWin.HEURIST4.rectypes.counts){
                        window.hWin.HAPI4.EntityMgr.doRequest({a:'counts',entity:'defRecTypes',
                                        mode: 'record_count',ugr_ID: window.hWin.HAPI4.user_id()}, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    window.hWin.HEURIST4.rectypes.counts = response.data;
                                }else{
                                    window.hWin.HEURIST4.rectypes.counts = {};
                                }
                                that._loadData();
                                
                            });
                            return;
                    }
                    
                    //take recordset from LOCAL HEURIST.rectypes format     
                    this._cachedRecordset = this.getRecordsetFromStructure();
                }
            }
            else{
                
                //usual via entity
                //shorter
                this.updateRecordList(null, {recordset:$Db.rty()});
                this.searchForm.searchDefRecTypes('startSearch');
                
                /*
                //longer but safer - since it reloads data if it is missed locally
                var that = this;
                window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
                    function(response){
                        that.updateRecordList(null, {recordset:response});
                        that.searchForm.searchDefRecTypes('startSearch');
                    });
                */
                
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
        rdata.fields.push('rty_RecCount');
        
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
                
                if(rectypes.counts) rectype.push(rectypes.counts[r_id]);
                
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
    _recordListHeaderRenderer: function(){

        function fld2(col_width, value, style){
            
            if(!style) style = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                style += (';width:'+col_width);  //;max-width: '+col_width+';
            }
            if(style!='') style = 'style="'+style+'"'; //border-left:1px solid gray;padding:0px 4px;
            
            if(!value){
                value = '';
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }
        
        var html = '';
        if (!(this.usrPreferences && this.usrPreferences.fields)) return '';
        var fields = this.usrPreferences.fields;
        
        var i = 0;
        for (;i<fields.length;i++){
           switch ( fields[i] ) {
                case 'rtyid': html += fld2('20px','ID','text-align:right'); break;
                case 'ccode': 
                    html += fld2('80px','Code','text-align:center');     
                    break;
                case 'addrec': 
                    html += fld2('30px','Add','text-align:center');
                    break;
                case 'filter':
                    html += fld2('30px','Filter','text-align:center');
                    break;
                case 'count': 
                    html += fld2('30px','Count','text-align:center');
                    break;
                case 'group': 
                    html += fld2('30px','Group','text-align:center');
                    break;
                case 'icon': 
                    html += fld2('40px','Icon','text-align:center');
                    break;
                case 'edit':  
                    html += fld2('30px','Attr','text-align:center');
                    break;
                case 'editstr': 
                    html += fld2('30px','Edit','text-align:center');
                    break;
                case 'name':  
                    html += fld2('120px','Name','text-align:left');
                    break;
                case 'description':  
                    html += fld2(null,'Description',''); break;
                case 'show': 
                    html += fld2('30px','Show','text-align:center');
                    break;
                case 'duplicate': 
                    html += fld2('30px','Dup','text-align:center');
                    break;
                case 'fields': 
                    html += fld2('30px','Fields','text-align:center');
                    break;
                case 'status': 
                    html += fld2('30px','Status','text-align:center');
                    break;
            }   
        }
        
        
        return html;
        
        /*
        var s = '<div style="width:40px"></div><div style="width:3em">ID</div>'
                    +'<div style="width:13em">Name</div>'
                    +'<div style="width:20em;border:none;">Description</div>';
            
            if (window.hWin.HAPI4.is_admin()){
                s = s+'<div style="position:absolute;right:4px;width:60px">Edit</div>';
            }
        */    
    },
    
    
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width, value, style){
            
            if(!style) style = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                style += (';max-width: '+col_width+';width:'+col_width);
            }
            if(style!='') style = 'style="'+style+'"'; //padding:0px 4px;
            
            if(!value){
                value = recordset.fld(record, fldname);
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('rty_ID');
        var recTitle = recTitle + fld2('rty_Name','15em')
            + ' : <div class="item" style="font-style:italic;width:45em">'
            + window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, 'rty_Description'))+'</div>'

        if(this.options.import_structure){

            //Ian dwi
            //recTitle = recTitle + fld2('rty_ID','3.5em')+fld2('rty_ID_local','3.5em');
            var rtIcon = this.options.import_structure.databaseURL
                                + '/hsapi/dbaccess/rt_icon.php?db='
                                + this.options.import_structure.database + '&id=';
            rtIcon = '';
            
            var html_icon = '<div class="recordIcons" style="min-width:16px;text-align:center;">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
            +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
            +     '"  class="rt-icon" style="background-image: url(&quot;'+rtIcon+'&quot;);">'       //opacity:'+recOpacity+'
            + '</div>';
            
            
            var html = '<div class="recordDiv" recid="'+recID+'">'
            + '<div class="recordSelector"><input type="checkbox" /></div>'
            + html_icon
            + '<div class="recordTitle recordTitle2" title="'+fld('rty_Description')
                            +'" style="right:10px">'
            +     recTitle
            + '</div>';
            
            return html;
        }
        
        var rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'icon');
        var recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        if(this.is_new_icons){
            rtIcon = window.hWin.HAPI4.iconBaseURL+recID; 
            recThumb = window.hWin.HAPI4.iconBaseURL+'thumb/th_'+recID; 
        }
        
        var html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);">'
        +'</div>';
        
        //recordIcons 
        var html_icon = '<div class="item" style="vertical-align: middle;width:40px;text-align:center">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '"  class="rt-icon" style="background-image: url(&quot;'+rtIcon+'&quot;);">'       //opacity:'+recOpacity+'
        + '</div>';        

        var html = '';
        
        var fields = this.usrPreferences.fields;
//console.log(fields);
        //fields = ['rtyid','ccode','addrec','filter','count','group','icon','edit','editstr','name','description','show','duplicate','fields','status'];        
        
        function __action_btn(action,icon,title){
            return '<div class="item" style="width:30px;text-align:center;"><div title="'+title+'" '
                    +'class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
                    +'role="button" aria-disabled="false" data-key="'+action+'" style="height:18px;">'
                    +     '<span class="ui-button-icon-primary ui-icon '+icon+'"></span>'
                    + '</div></div>'            
        }

        var grayed = '';
        var i = 0;
        for (;i<fields.length;i++){
            
            switch ( fields[i] ) {
                case 'rtyid': html += fld2('rty_ID','20px',null,'text-align:right'); break;
                case 'ccode': 
                    var c1 = recordset.fld(record,'rty_OriginatingDBID');
                    var c2 = recordset.fld(record,'rty_IDInOriginatingDB');
                    c1 = (c1>0 && c2>0)?(c1+'-'+c2):' ';
                    html += fld2('','80px', c1,'text-align:center');     
                    break;
                case 'addrec': 
                    html += __action_btn('addrec','ui-icon-plus','Click to add new '+fld('rty_Name'));    
                    break;
                case 'filter':
                    html += __action_btn('filter','ui-icon-search','Click to launch search for '+fld('rty_Name'));
                    break;
                case 'count': 
                    //var cnt = ((window.hWin.HEURIST4.rectypes.counts[recID]>0)?window.hWin.HEURIST4.rectypes.counts[recID]:' ');
                    html += fld2('rty_RecCount','40px',null,'text-align:right'); break;
                case 'group': 
                    html += __action_btn('group','ui-icon-carat-d','Change group');
                    break;
                case 'icon': 
                    html += html_icon; 
                    break;
                case 'edit':  
                    html += __action_btn('edit','ui-icon-pencil','Click to edit record type');
                    break;
                case 'editstr': 
                    html += __action_btn('editstr','ui-icon-list','Click to edit structure');
                    break;
                case 'name':  html += fld2('rty_Name','120px'); break;
                case 'description':  
                    html += fld2('rty_Description',null,null,
                        'min-width:320px;max-width:320px;width:50%;font-style:italic;font-size:smaller'); break;
                case 'show': 
                
                    if(recordset.fld(record, 'rty_ShowInLists')==1){
                        html += __action_btn('hide_in_list','ui-icon-check-on','Click to hide in lists');    
                    }else{
                        html += __action_btn('show_in_list','ui-icon-check-off','Click to show in lists');
                        grayed = 'background:lightgray';
                    }
                    
                    break;
                case 'duplicate': 
                    html += __action_btn('duplicate','ui-icon-copy','Duplicate record type');
                    break;
                case 'fields': 
                    html += __action_btn('fields','ui-icon-circle-b-info','List of fields');
                    break;
                case 'status': 
                    
                    if(recordset.fld(record, 'rty_Status')=='reserved'){
                        html += __action_btn('','ui-icon-lock','Status: Reserved');
                    }else{
                        html += __action_btn('delete','ui-icon-delete','Status: Open. Click to delete record type');
                    }    
                
                    break;
            }    
        }

        html = '<div class="recordDiv rt_draggable" recid="'+recID+'" style="display:table-row;height:28px;'+grayed+'">'
                    + '<div class="recordSelector item"><input type="checkbox" /></div>'
                    + html+'</div>';
        
/*        
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
        
        // add edit/remove action buttons in record lisr
        if(has_buttons){
        
                
               html = html 
                + '<div class="rec_actions" style="top:4px;width:120px;">'
                    + '<div title="Click to edit record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Click to edit structure" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="structure" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-list"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Duplicate record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="duplicate" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-copy"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="List of fields" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="fields" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-b-info"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Click to delete record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                    + '</div></div>';
        }
        

        html = html + '</div>';
*/
        return html;
        
    },
    
    //
    //
    //
    _onActionListener:function(event, action){
        
        
        var isResolved = this._super(event, action);

        if(!isResolved){
            
            var recID = 0;

            if(action && action.action){
                recID =  action.recID;
                action = action.action;
            }
            if(recID>0){
                
                var that = this;
 
                if(action=='addrec'){
                    var new_record_params = {RecTypeID: recID};
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:new_record_params});
                    
                }else if(action=='filter'){
                    
                    window.hWin.HAPI4.SearchMgr.doSearch( this, 
                        {q:'{"t":"'+recID+'"}',detail:'ids',source:this.element.attr('id')} );
                    //this.closeDialog(true);
                    
                }else if(action=='group'){
                    
                }else if(action=='editstr'){

                    var new_record_params = {RecTypeID: recID};
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                        {new_record_params:new_record_params, edit_structure:true});

                }else if(action=='show_in_list' || action=='hide_in_list'){
                    
                    //window.hWin.HEURIST4.msg.bringCoverallToFront(this.recordList);
                    var newVal = (action=='show_in_list')?1:0;
                    this._saveEditAndClose({rty_ID:recID, rty_ShowInLists:newVal });
                    /*
                        function(){
                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                            window.hWin.HEURIST4.dbs.rtyField(recID, 'rty_ShowInLists', newVal);
                            that.recordList.resultList('refreshPage');  
                        });
                    */    
                    
                }else if(action=='duplicate'){
                    
                    //this._duplicateType(recID);
                    
                }else if(action=='fields'){
                    
                }
                
            }
        }

    },
    
    
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this.deleted_from_group_ID = $Db.rty(this._currentEditID,'rty_RecTypeGroupID');
            this._super(); 
        }else{
            this.deleted_from_group_ID = 0;
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this record type? Proceed?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    //
    //
    //
    _afterDeleteEvenHandler: function(recID){

        
            this._super(recID);
            
            this.updateGroupCount(this.deleted_from_group_ID, -1);
            
            //backward capability - remove later        
            var rectypes = window.hWin.HEURIST4.rectypes;
            if(recID>0 && rectypes.typedefs[recID]){
                    delete rectypes.names[recID];
                    delete rectypes.pluralNames[recID];
                    delete rectypes.typedefs[recID];
            }
           
    },
    
    //-----
    //
    // adding group ID value for new rectype
    // open select icon dialog for new record
    //
    _afterInitEditForm: function(){

        this._super();
        
        var rty_RecTypeGroupID = this.options.rtg_ID; //this.searchForm.find('#input_search_group').val();
        if(this._currentEditID<0){ //rty_RecTypeGroupID>0 && !this._currentEditRecordset){ //insert       

        
            if(!(rty_RecTypeGroupID>0)){ //take first from list of groups
                rty_RecTypeGroupID = $Db.rtg().getOrder()[0];                
            }
        
            var ele = this._editing.getFieldByName('rty_RecTypeGroupID');
            if(rty_RecTypeGroupID>0) ele.editing_input('setValue', rty_RecTypeGroupID);
            
            //open select icon
            ele = this._editing.getFieldByName('rty_Icon');
            ele.editing_input('openIconLibrary');
            
            //hide save button
            if(this._toolbar){
                this._toolbar.find('#btnRecSave').css('visibility', 'visible');
            }
            
            //hide title mask
            ele = this._editing.getFieldByName('rty_TitleMask');
            ele.editing_input('setValue', 'record [ID]');
            ele.hide();
            
        }else{
            //hide after edit init btnRecRemove for status locked 
            if(false){ //@todo
                var ele = this._toolbar;
                ele.find('#btnRecRemove').hide();
            }
            
            var that = this;
            var ele_mask = this._editing.getFieldByName('rty_TitleMask');

            function __extendTitleMaskInput( ){
                
                var inputs = ele_mask.editing_input('getInputs');
                var $input = inputs[0];

                $input.removeClass('text').attr('readonly','readonly');

                var $btn_editmask = $( '<span>', {title: 'Edit Mask'})
                .addClass('smallicon ui-icon ui-icon-pencil')
                .insertAfter( $input );

                that._on( $btn_editmask, { click: function(){

                    var maskvalue = ele_mask.editing_input('getValues');
                    maskvalue = maskvalue[0];

                    var sURL = window.hWin.HAPI4.baseURL +   
                    "admin/structure/rectypes/editRectypeTitle.html?rectypeID="
                            + that._currentEditID + "&mask="
                            + encodeURIComponent(maskvalue)+"&db="+window.hWin.HAPI4.database;

                    window.hWin.HEURIST4.msg.showDialog(sURL, {     
                        "close-on-blur": false,
                        "no-resize": true,
                        height: 800,
                        width: 800,
                        callback: function(newvalue) {
                            if(!window.hWin.HEURIST4.util.isnull(newvalue)){
                                ele_mask.editing_input('setValue', newvalue);
                                that._editing.setModified(true); //restore flag after autosave
                                that.onEditFormChange();
                            }
                        }
                    });

                }} );
                
            }

            //extent editing for record title
            ele_mask.editing_input('option', 'onrecreate', __extendTitleMaskInput);
            __extendTitleMaskInput(ele_mask);

        }
        
    },   
        
    //
    // show warning
    //
    addEditRecord: function(recID, is_proceed){
    
        if(recID<0 && is_proceed !== true){
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                    'Before defining new record (entity) types we suggest importing suitable '+
                    'definitions from templates (Heurist databases registered in the Heurist clearinghouse). '+
                    'Those with registration IDs less than 1000 are templates curated by the Heurist team. '
                    +'<br><br>'
    +'This is particularly important for BIBLIOGRAPHIC record types - the definitions in template #6 (Bibliographic definitions) are ' 
    +'optimally normalised and ensure compatibility with bibliographic functions such as Zotero synchronisation, Harvard format and inter-database compatibility.'                
                    +'<br><br>Use main menu:  Design > Browse templates'                
                    , function(){
                        that.addEditRecord(recID, true); 
                        //that._super(recID); 
                    }, {title:'Confirm',yes:'Continue',no:'Cancel'});
        
        }else{
               this._super(recID); 
        }
    },
    
    //overwritten     NOT USED
    _recordListGetFullData:function(arr_ids, pageno, callback){

console.log('_recordListGetFullData')        
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
    
    

    //
    // @todo
    //
    testTitleMask: function()
    {
        if(!rectypeID || rectypeID < 0){
            var val = "record [ID]";
            if(document.getElementById("definit").checked && window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']){
                val = "["+ $Db.dty(window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], 'dty_Name') +"]";
            }

            document.getElementById("rty_TitleMask").value = val
            titleMaskIsOk = true;
            updateRectypeOnServer_continue();

        }else{

            var mask = document.getElementById("rty_TitleMask").value;
            
            var baseurl = window.hWin.HAPI4.baseURL + "hsapi/controller/rectype_titlemask.php";

            var request = {rty_id:rectypeID, mask:mask, db:window.hWin.HAPI4.database, check:1}; //verify titlemask
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
                function (response) {
                    if(response.status != window.hWin.ResponseStatus.OK || response.message){
                        titleMaskIsOk = false;
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }else{
                        titleMaskIsOk = true;
                        updateRectypeOnServer_continue();
                    }                                        
                }
            );

        }
    },
    
    //
    //
    //
    _triggerRefresh: function( type ){
        window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            { source:this.uuid, type:type });    
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
        
        if(this.it_was_insert){
            this.searchForm.searchDefRecTypes('startSearch'); //refresh

            //select Fields For New RecordType
            var sURL = window.hWin.HAPI4.baseURL + "admin/structure/rectypes/editRectypeSelFields.html";

            var that = this;
            
            this._selected_fields = [];
            
            window.hWin.HEURIST4.msg.showDialog(sURL, {
                    "close-on-blur": false,
                    "no-resize": false,
                    height: 500, //(mode==0?200:250),
                    width: 700,
                    title:' Select fields for new record type',
                    afterclose:function(){
                        //add fields to structure in any case - by default DT_NAME and DT_DESCRIPTION
                        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selected_fields)) this._selected_fields = [];

                        var request = {};
                        request['a']        = 'action'; //batch action
                        request['entity']   = 'defRecStructure';
                        request['rtyID']    = recID;
                        //request['recID']    = dtyIDs;
                        request['newfields']  = that._selected_fields;
                        request['request_id'] = window.hWin.HEURIST4.util.random();
                        
                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    
                                    //show edit structure popup
                                    window.hWin.HEURIST4.dbs.rtyRefresh(recID, 
                                    function(){
                                        that._onActionListener(null, {recID:recID, action:'editstr'} );
                                        var rtg_ID = $Db.rty(recID,'rty_RecTypeGroupID');
                                        that.updateGroupCount(rtg_ID, 1);
                                    });
                                    
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);      
                                }
                            });
                    },
                    callback:function(context){
                        that._selected_fields = context;
                    } 
            });
            
        }else{
            this._triggerRefresh('rty');
        }
        
/*        
        this.getRecordSet().setRecord(recID, fieldvalues);
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else if(this._currentEditID<0){
            this.searchForm.searchDefRecTypes('startSearch');
        }else{
            this.recordList.resultList('refreshPage');  
        }
*/        
    },
    
    //
    //
    //
    updateGroupCount:function(rtg_ID,  delta){
    
        if(rtg_ID>0){
            var cnt = parseInt($Db.rtg(rtg_ID,'rtg_RtCount'));
            var cnt = (isNaN(cnt)?0:cnt)+delta;
            if(cnt<0) cnt = 0;
            $Db.rtg(rtg_ID,'rtg_RtCount',cnt);
            this._triggerRefresh('rtg');
        }
    },    
    
    //
    //
    //
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
            
            this.usrPreferences = params;
        }
        return true;
    },
    
    //
    // update ui and call save prefs
    //
    changeUI: function( event, params ){
        
        if(this.options.edit_mode=='editonly') return;
        
//console.log('changeUI');
//console.log(params);        
        
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
        
        //refresh result list
        this.recordList.resultList('applyViewMode','list', true);
        this.recordList.resultList('refreshPage');
        //this.searchForm.changeUI();
        
    },
    
    
    //
    // duplicate record type and then call edit type dialogue
    //
    _duplicateType: function (rectypeID) {

        window.hWin.HEURIST4.msg.showMsgDlg(
        "Do you really want to duplicate record type # "+rectypeID+"?"
        , function(){ 
                
        
                function _editAfterDuplicate(response) {

                    if(response.status == window.hWin.ResponseStatus.OK){

                        var rty_ID = Number(response.data.id);
                        if(rty_ID>0){   
                            //refresh the local heurist
                            this._refreshClientStructure(response.data);
                            
                            //detect what group
                            ind_grpfld = window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_RecTypeGroupID;
                            var grpID = window.hWin.HEURIST4.rectypes.typedefs[rty_ID].commonFields[ind_grpfld];

                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }                                        
                }

                var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/rectypes/duplicateRectype.php";
                
                window.hWin.HEURIST4.util.sendRequest(baseurl, { rtyID:rectypeID }, null, _editAfterDuplicate);

        }, {title:'Confirm',yes:'Continue',no:'Cancel'});
    },


    //
    //
    //                                
    changeRectypeGroup: function(params){                                    
        window.hWin.HEURIST4.msg.bringCoverallToFront(this.recordList);

        var that = this;
        this._saveEditAndClose( params ,
            function(){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.hWin.HAPI4.EntityMgr.refreshEntityData('rtg',
                    function(){
                        that._triggerRefresh('rtg');
                    }
                )
                /*            
                //change groups
                var id = params.rty_ID;
                var rtg = params.rty_RecTypeGroupID
                var new_id = window.hWin.HEURIST4.dbs.rtyField(id,'rty_RecTypeGroupID', rtg);
                window.hWin.HEURIST4.dbs.rtgRefresh(); //refresh groups counts after change group
                */            
        });
    }                                    

    
});
