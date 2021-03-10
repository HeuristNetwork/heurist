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
    fieldSelectorLast:null,
    fieldSelector:null,
    fieldSelectorOrig:null,
    
    is_new_icons: true,
    
    rst_links: null, //reference to links data $Db.rst_links

    usrPreferences:{},
    defaultPrefs:{
        width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
        height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
//rtyid,'ccode','addrec','filter','count','group','icon','edit','editstr','name','description','show','duplicate','fields','status'        
        fields:['icon','fields','edit','name','count','filter','addrec','show','duplicate','status','rtyid','ccode','description'] 
        //'editstr',
        },
    fields_width: {rtyid:30,ccode:80,addrec:34,filter:34,count:40,group:34,icon:40,edit:34,editstr:34,
                name:150,description:34,show:34,duplicate:34,fields:34,status:34},
    //fields_name:{rtyid:'ID',ccode:'Code',addrec:'Add',filter:'Filter',count:'Count',group:'Group',icon:'Icon',edit:'Attr',editstr:'Edit',
    //             name:'Name',description:'Description',show:'Show',duplicate:'Dup',fields:'Info',status:'Del'},


    //
    //                                                  
    //    
    _init: function() {
        
//this._time_debug = new Date().getTime() / 1000;
        this.options.default_palette_class = 'ui-heurist-design';
        
        this.options.innerTitle = false;
        
        //define it to load recordtypes from other server/database - if defined it allows selection only
        if(this.options.import_structure){ //for example https://heuristplus.sydney.edu.au/heurist/?db=Heurist_Reference_Set
            if(this.options.select_mode=='manager') this.options.select_mode='select_single';
            this.options.use_cache = true;
            this.options.use_structure = true; //use HEURIST4.rectypes for import structures    
        }else{
            this.options.use_cache = true;
            this.options.use_structure = false
        }
        
        
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
        if(this.options.select_mode!='manager' && this.options.select_mode!='select_multi'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                    
            //this.options.edit_mode = 'none'
        }
        
        if(this.options.select_mode=='select_multi'){
        //    this.options.width = 440;
        }
            
            
        this._super();
        
        
        var that = this;
        
        //
        if(!this.options.import_structure){        
            this.rst_links = $Db.rst_links_base();
        
            window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
                function(data) { 
                    if(!data || (data.source != that.uuid && data.type == 'rty'))
                    {
                        that._loadData();    
                    }else if(data && (data.type == 'dty' || data.type == 'rst')){
                        that.rst_links = $Db.rst_links_base();
                    }
                });
                
            window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_REC_UPDATE,
                function(data) { 

                    //console.log('ON_REC_UPDATE '+that.element.is(':visible'));                    
                    
                    if(that.element.is(':visible'))
                    $Db.get_record_counts(function(){
                        that._loadData();
                    });                                
                });
                
            if(this.options.isFrontUI && this.options.select_mode!='select_multi'){ //adjust table widths

                window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE, 
                    function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    });
            
            /*
                that._delayOnResize = 0;
                window.onresize = function(){
                    if(that._delayOnResize) clearTimeout(that._delayOnResize);
                    that._delayOnResize = setTimeout(function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    },500);
                };
                */
            }
            
        }
                
        
    },
    
    _destroy: function() {
        
       window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);        
       window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE);        
       window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_REC_UPDATE);        
        
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
        
        //this.searchForm.css({padding:'6px', 'min-width': '660px'});
        //this.recordList.css({'min-width': '660px'});
        this.searchForm.parent().css({'overflow-x':'auto'});

        this.options.ui_params = window.hWin.HEURIST4.util.cloneJSON( this.getUiPreferences() );
        
        //init viewer 
        var that = this;
        
        if(this.options.select_mode=='manager'){
   
            if(this.options.ui_params && $.isArray(this.options.ui_params.fields)){
                var fields = this.options.ui_params.fields;
                if(fields.indexOf('name')<0) fields.unshift('name');
                if(fields.indexOf('edit')<0) fields.unshift('edit');
            }    
            
            if(this.options.isFrontUI){
                
                this.searchForm.css({padding:'10px 5px 0 10px'});
                
                window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {'background-color':'#fff', opacity:1});   
                
                //add record type group editor
                this.element.addClass('ui-suppress-border-and-shadow');
                
                this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css('left',328);
                
                this.rectype_groups = $('<div>').addClass('ui-dialog-heurist')
                    .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:320, overflow: 'hidden'})
                    .appendTo(this.element);
            }                
            
          
            this.recordList.resultList({ 
                    show_toolbar: false,
                    list_mode_is_table: true,
                    rendererHeader:function(){ return that._recordListHeaderRenderer() },
                    draggable: function(){
                        
                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                                    revert: 'invalid',
                                    helper: function(){ 
                                        return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                            $(this).parent().attr('recid')
                                        +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                        +'>Drag and drop to group item to change record type group</div>'); 
                                    },
                                    zIndex:100,
                                    appendTo:'body',
                                    scope: 'rtg_change',
                                    containment: 'window'
                                    //containment: that.element,
                                    //delay: 200
                                });   
                    }});
                    
                    
            if(this.options.isFrontUI) {
                this._on( this.recordList, {        
                        "resultlistondblclick": function(event, selected_recs){
                                    this.selectedRecords(selected_recs); //assign
                                    
                                    if(window.hWin.HEURIST4.util.isRecordSet(selected_recs)){
                                        var recs = selected_recs.getOrder();
                                        if(recs && recs.length>0){
                                            var recID = recs[recs.length-1];
                                            //var isTrash = ($Db.rty(recID,'rty_RecTypeGroupID') == $Db.getTrashGroupId('rtg'));
                                            //if(isTrash) return;
                                            this._onActionListener(event, {action:'edit',recID:recID}); 
                                        }
                                    }
                                }
                        });

            }    
                    
                    
        }else if(this.options.select_mode=='select_multi'){
            this.recordList.resultList({ 
                    show_toolbar: true,
                    view_mode:'list',
                    show_viewmode: false,
                    list_mode_is_table: true});
                   
            this.searchForm.css({padding:'6px', 'min-width': '420px'});
            this.recordList.css({'min-width': '420px'});
                   
         
            this.options.ui_params.width = 440;
            this.options.ui_params.fields = ['icon','name','fields'];
        }
                    

        //may overwrite resultList behaviour
        if(this.options.recordList){
            this.recordList.resultList( this.options.recordList );
        }


        this.options.onInitCompleted =  function(){

//console.log( 'onInitCompleted  ' + (new Date().getTime() / 1000 - that._time_debug));
//that._time_debug = new Date().getTime() / 1000;

            
            if(that.options.isFrontUI){
                    var rg_options = {
                        isdialog: false, 
                        isFrontUI: true,
                        container: that.rectype_groups,
                        title: 'Record type groups',
                        layout_mode: 'short',
                        select_mode: 'manager',
                        reference_rt_manger: that.element,
                        onSelect:function(res){

                            if(window.hWin.HEURIST4.util.isRecordSet(res)){
                                res = res.getIds();                     
                            }
                            
                            if(res && $.isArray(res) && res.length>0){
                                that.options.rtg_ID = res[0];
                                that.searchForm.searchDefRecTypes('option','rtg_ID', that.options.rtg_ID);
                            }

                            if(!that.getRecordSet()){
                                that._loadData( true ); //not yet loaded
                            }
                        },
                        add_to_begin: true
                    };
                window.hWin.HEURIST4.ui.showEntityDialog('defRecTypeGroups', rg_options);
            }else{
                that._loadData( true );
            }

            
            var iheight = that.options.import_structure?40:80;
            that.searchForm.css({'height':iheight});
            that.recordList.css({'top':iheight});     
            //!!!! that.changeUI(null, that.options.ui_params);    
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
    _loadData: function(is_first){
        
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
                    /*
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
                    }*/
                    this._loadData();
                    
                    //take recordset from LOCAL HEURIST.rectypes format     
                    this._cachedRecordset = this.getRecordsetFromStructure();
                }
            }
            else{
//console.log('_loadData '+$Db.needUpdateRtyCount);
                if( is_first && $Db.needUpdateRtyCount>0 ){
                    $Db.get_record_counts(function(){
                        that._loadData(true);
                    });                                
                    return;
                }

                //usual via entity
                //shorter
                this.updateRecordList(null, {recordset:$Db.rty()});
                if(this.searchForm.searchDefRecTypes('instance')){ //is_first!==true && 

                    this.searchForm.searchDefRecTypes('option','rtg_ID', this.options.rtg_ID);                    
                    this.searchForm.searchDefRecTypes('startSearch');    
                }
                                

                
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
            //this.searchForm.searchDefRecTypes('reloadGroupSelector', rectypes); //get remote groups
            
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
        }else{
            rdata.fields.push('rty_RecCount');
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
                    var local_rtyID = $Db.getLocalID( 'rty', concept_code );
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

        var max_width = this.recordList.find('.div-result-list-content').width() - 33;
        var used_width = 0;
        
        function fld2(col_width, value, hint, style){
            
            if(!style) style = '';
            if(col_width>0){
                col_width = col_width - 1;
                used_width = used_width + col_width + 4;
                style += (';width:'+col_width+'px'); 
            }
            if(style!='') style = 'style="'+style+'"'; //border-left:1px solid gray;padding:0px 4px;
            
            if(!value){
                value = '';
            }
            return '<div class="item truncate" title="'
                        +hint+'" '+style+'>'
                        +window.hWin.HEURIST4.util.htmlEscape(value)
                    +'</div>';
        }
        
        var html = '';
        if (!(this.usrPreferences && this.usrPreferences.fields)) return '';
        var fields = this.options.ui_params.fields; //this.usrPreferences.fields;
        
        var i = 0;
        for (;i<fields.length;i++){
            switch ( fields[i] ) {
                case 'rtyid': html += fld2(30,'ID','Local ID','text-align:center'); break;
                case 'ccode': 
                    html += fld2(80,'ConceptID','Concept code','text-align:center');     
                    break;
                case 'addrec': 
                    html += fld2(34,'Add','Add record','text-align:center');
                    break;
                case 'filter':
                    html += fld2(34,'Filter','Filter records','text-align:center');
                    break;
                case 'count': 
                    html += fld2(40,'Count','Count','text-align:center');
                    break;
                case 'group': 
                    html += fld2(34,'Group','','text-align:center');
                    break;
                case 'icon': 
                    html += fld2(40,'Icon','','text-align:center');
                    break;
                case 'edit':  
                    html += fld2(34,'Edit','Edit','text-align:center;'); //font-size:12px
                    break;
//                case 'editstr': 
//                    html += fld2(34,'Fields','Edit structure','text-align:center');
//                    break;
                case 'name':  
                    html += '$$NAME$$';//fld2(150,'Name','text-align:left');
                    break;
                case 'description':  
                    html += '$$DESC$$'; //fld2(null,'Description',''); 
                    break;
                case 'show': 
                    html += fld2(34,'Show','Show','text-align:center;'); //font-size:12px
                    break;
                case 'duplicate': 
                    html += fld2(34,'Dup','Duplicate','text-align:center;'); //font-size:12px
                    break;
                case 'fields': 
                    html += fld2(34,'Fld list','List fields','text-align:center;'); //font-size:12px
                    break;
                case 'status': 
                    html += fld2(34,'Del','Delete / protected','text-align:center;'); //font-size:12px
                    break;
            }   
        }
        
        //if Description presents it takes all possible width
        // otherwise this is Name
        var w_desc = 0, i_desc = fields.indexOf('description');
        if(i_desc>=0){
            w_desc = max_width-used_width-250;
            if(w_desc<30) w_desc = 30;
//console.log(max_width+'  '+'  '+used_width+'  '+w_desc);            
            html = html.replace('$$DESC$$',fld2(w_desc, 'Description', 'Description', 'text-align:left'))
        }
        var name_width = 250; //max_width - used_width;
//console.log('  =>'+name_width);        
        html = html.replace('$$NAME$$',fld2(name_width, 'Name', 'Name', 'text-align:left'))
        
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
            if(col_width>0){
                style += (';max-width:'+col_width+'px;min-width:'+col_width+'px');
            }
            if(style!='') style = 'style="'+style+'"'; //padding:0px 4px;
            
            if(value==null){
                value = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        var recID   = fld('rty_ID');
        if(this.options.import_structure){

            var recTitle = recTitle + fld2('rty_Name','15em')
                + ' : <div class="item" style="font-style:italic;width:45em">'
                + window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, 'rty_Description'))+'</div>'

            
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
        var html_icon = '<div class="item" style="vertical-align: middle;max-width:40px;min-width:40px;text-align:center">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '"  class="rt-icon" style="background-image: url(&quot;'+rtIcon+'&quot;);">'       //opacity:'+recOpacity+'
        + '</div>';        

        var html = '';
        
        var fields = this.options.ui_params?this.options.ui_params.fields:['rtyid'];
//console.log(fields);
        //fields = ['rtyid','ccode','addrec','filter','count','group','icon','edit','editstr','name','description','show','duplicate','fields','status'];        

        var isTrash = false;//(recordset.fld(record, 'rty_RecTypeGroupID') == $Db.getTrashGroupId('rtg'));
        
        function __action_btn(action,icon,title,color){
            if(!color) color = '#555555';            
            var sbg = '';
            if(isTrash && action!='delete' && action!='') {
                color = '#d7d7d7';
                sbg = 'background:#f0f0f0';
                action = '';
            }
            
            return '<div class="item" style="min-width:34px;max-width:34px;text-align:center;'+sbg+'">'
                    +'<div title="'+title+'" '
                    +'class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
                    +'role="button" aria-disabled="false" data-key="'+action+'" style="height:18px;">'
                    +     '<span class="ui-button-icon-primary ui-icon '+icon+'" style="color:'+color+'"></span>'
                    + '</div></div>'            
        }
        
        var used_width = 0;

        var grayed = '';
        var i = 0;
        for (;i<fields.length;i++){
            
            if(fields[i]!='name' && fields[i]!='description'){
                used_width = used_width + this.fields_width[fields[i]] + 4;    
            }
            
            switch ( fields[i] ) {
                case 'rtyid': html += fld2('rty_ID',30,null,'text-align:center'); break;
                case 'ccode': 
                    var c1 = recordset.fld(record,'rty_OriginatingDBID');
                    var c2 = recordset.fld(record,'rty_IDInOriginatingDB');
                    c1 = (c1>0 && c2>0)?(c1+'-'+c2):' ';
                    html += fld2('',80, c1,'text-align:center');     
                    break;
                case 'addrec': 
                    html += __action_btn('addrec','ui-icon-plus','Click to add new '+fld('rty_Name'));    
                    break;
                case 'filter':
                    html += __action_btn('filter','ui-icon-search','Click to launch search for '+fld('rty_Name'));
                    break;
                case 'count': 
                    html += fld2('rty_RecCount',40,null,'text-align:center  '); break;
                case 'group': 
                    html += __action_btn('group','ui-icon-carat-d','Change group');
                    break;                         
                case 'icon': 
                    html += html_icon; 
                    break;
                case 'edit':  
                    html += __action_btn('edit','ui-icon-pencil','Click to edit record type');
                    break;
//                case 'editstr': 
//                    html += __action_btn('editstr','ui-icon-pencil','Click to edit structure');
//                    break;
                case 'name':  
                    html += '$$NAME$$'; 
                    break;
                case 'description':  
                    html += '$$DESC$$';
                    break;
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
                        html += __action_btn('','ui-icon-lock','This is a reserved record type common to all Heurist databases. It cannot be deleted.','gray');
                    }else{
                        if(recordset.fld(record,'rty_RecCount')>0){
                            html += __action_btn('','ui-icon-trash-b','To allow deletion, use Explore > Entities to find and delete all records.');    
                        }else{
                            //var links = window.hWin.HAPI4.EntityMgr.getEntityData('rst_Links')
                            //var is_referenced = (links['refs'] && links['refs'][recID]);
                            var is_referenced = !window.hWin.HEURIST4.util.isnull(this.rst_links[recID]);
                                        //(this.rst_links.reverse[recID] || this.rst_links.rel_reverse[recID]); 
                            if(is_referenced){
                                html += __action_btn('delete','ui-icon-trash-b','This record type is referenced. Click to show references');    
                            }else{
                                html += __action_btn('delete','ui-icon-trash','Click to delete this record type');        
                            }
                        }
                    }    
                
                    break;
            }    
        }
        
        //if Description presents it takes all possible width
        // otherwise this is Name
        var max_width = this.recordList.find('.div-result-list-content').width() 
                - ((this.options.select_mode=='select_multi') ?40:27);

        var w_desc = 0, i_desc = fields.indexOf('description');
        if(i_desc>=0){
            w_desc = max_width-used_width-250;
            if(w_desc<30) w_desc = 30;
            
            html = html.replace('$$DESC$$', fld2('rty_Description',null,null,
                    'min-width:'+w_desc+'px;max-width:'+w_desc+'px;font-style:italic;font-size:smaller')); 
        }
        
        var name_width = 250; //max_width - used_width - w_desc;
//console.log('  '+max_width+'  '+'  '+used_width+'  '+w_desc+'  =>'+name_width);        
        
        html = html.replace('$$NAME$$',fld2('rty_Name', name_width, null,'text-align:left'))
//padding:0.4em 0px;
        html = '<div class="recordDiv rt_draggable white-borderless" recid="'
            +recID+'" style="display:table-row;height:28px;padding:0.4em 0px;'+grayed+'">'
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
        

        if(action && action.action=='delete'){
            
            //if(this.rst_links.reverse[action.recID] || this.rst_links.rel_reverse[action.recID])
            if(!window.hWin.HEURIST4.util.isnull(this.rst_links[action.recID]))
            {            
                
                var res = this.rst_links[action.recID];
                /*
                if(this.rst_links.reverse[action.recID])
                    res = res.concat(Object.keys(this.rst_links.reverse[action.recID]));
                if(this.rst_links.rel_reverse[action.recID])
                    res = res.concat(Object.keys(this.rst_links.rel_reverse[action.recID]));
                */    
                var sList = '';
                for(var i=0; i<res.length; i++) if(res[i]!='all' && res[i]>0){
                    sList += ('<a href="#" data-dty_ID="'+res[i]+'">'+$Db.dty(res[i],'dty_Name')+'</a><br>');
                }
                
                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<p>Record type <b>'+$Db.rty(action.recID,'rty_Name')+'</b> is referenced by the following fields:</p>'
                + sList
                +'<p>Please remove these fields altogether, or click the links above <br>to modify base field (will affect all record types which use it).</p>'
                , null, {title:'Warning'},
                {default_palette_class:this.options.default_palette_class});        
                
                this._on($dlg.find('a[data-dty_ID]'),{click:function(e){
                    
                    var rg_options = {
                         isdialog: true, 
                         edit_mode: 'editonly',
                         select_mode: 'manager',
                         rec_ID: $(e.target).attr('data-dty_ID'),
                         onSelect:function(res){
                         }
                    };
                    window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', rg_options);
                    return false;                    
                }});
                
                return;
            }
        }

        
        var isResolved = this._super(event, action);

        if(!isResolved){
            
            var recID = 0;
            var target = null;

            if(action && action.action){
                recID =  action.recID;
                target = action.target;
                action = action.action;
            }
            if(recID>0){
                
                var that = this;
 
                if(action=='addrec'){
                    
                    var new_record_params = {RecTypeID: recID};
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                        {new_record_params:new_record_params,
                        onClose:function(){
                            /*   
                            if($Db && $Db.needUpdateRtyCount){
                                $Db.get_record_counts(function(){
                                    that._triggerRefresh('rty');
                                });                                
                            }
                            */
                        }});
                    
                }else if(action=='filter'){
                    
                    window.hWin.HAPI4.SearchMgr.doSearch( this, 
                        {q:'{"t":"'+recID+'"}',detail:'ids',source:this.element.attr('id')} );
                    //this.closeDialog(true);
                    
                }else if(action=='group'){
                    
                }else if(action=='editstr'){
                    //edit structure (it opens fake record and switches to edit structure mode)
                    var new_record_params = {RecTypeID: recID};
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                        {new_record_params:new_record_params, edit_structure:true});

                }else if(action=='show_in_list' || action=='hide_in_list'){
                    
                    //window.hWin.HEURIST4.msg.bringCoverallToFront(this.recordList);
                    var newVal = (action=='show_in_list')?1:0;
                    this._saveEditAndClose({rty_ID:recID, rty_ShowInLists:newVal });
                    
                }else if(action=='duplicate'){
                    
                    this._duplicateType(recID);
                    
                }else if(action=='fields'){
                    //show selectmenu with list of fields
                    if(this.fieldSelectorLast!=recID){
                        this.fieldSelectorLast   = recID;
                        var details = $Db.rst(recID); //get all fields for given rectype
                        if(!details) return;
                        var options = [];
                        details.each2(function(dty_ID, detail){
                            if($Db.dty(dty_ID,'dty_Type')!='separator')
                                options.push({key:dty_ID, title:detail['rst_DisplayName']});
                        });
                        
                        if(!this.fieldSelector){
                            this.fieldSelectorOrig = document.createElement("select");    
                            window.hWin.HEURIST4.ui.fillSelector(this.fieldSelectorOrig, options);
                            this.fieldSelector = window.hWin.HEURIST4.ui.initHSelect(this.fieldSelectorOrig, false);
                            
                            var menu = this.fieldSelector.hSelect( "menuWidget" );
                            menu.css({'max-height':'350px'});                        
                                this.fieldSelector.hSelect({change: function(event, data){
    //console.log('SEELCT >>> '+data.item.value);                        
                                var rg_options = {
                                     isdialog: true, 
                                     edit_mode: 'editonly',
                                     select_mode: 'manager',
                                     rec_ID: data.item.value,
                                     onSelect:function(res){
                                     }
                                };
                                window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', rg_options);
    
                            }});
                            
                        }else{
                            $(this.fieldSelectorOrig).empty();
                            window.hWin.HEURIST4.ui.fillSelector(this.fieldSelectorOrig, options);
                            this.fieldSelector.hSelect('refresh');
                        }
                    }
                    this.fieldSelector.hSelect('open');
                    this.fieldSelector.val(-1);
                    this.fieldSelector.hSelect('menuWidget')
                        .position({my: "left top", at: "left+10 bottom-4", of: $(target)});
                    
                    this.fieldSelector.hSelect('hideOnMouseLeave', $(target));
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
                'Are you sure you wish to delete this record type?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
    },
    
    //
    //
    //
    _afterDeleteEvenHandler: function(recID){

        
            this._super(recID);
            
            this.updateGroupCount(this.deleted_from_group_ID, -1);
            
            //select first
            this.selectRecordInRecordset();
            
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
            
            var ele = this._editing.getFieldByName('rty_ID');
            ele.find('div.input-div').html(this._currentEditID+'&nbsp;&nbsp;<span style="font-weight:normal">Code: </span>'
                                    +$Db.getConceptID('rty',this._currentEditID));
            
            
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
                        },
                        default_palette_class: this.options.default_palette_class
                    });

                }} );
                
            }

            // extent editing for record title
            ele_mask.editing_input('option', 'onrecreate', __extendTitleMaskInput);
            __extendTitleMaskInput(ele_mask);
            
            //
            // add edit structure button
            //
            if(this.options.suppress_edit_structure!==true){
                
            var $s = $('<div style="margin: 15px 0 20px 175px;' //border: 2px solid orange;border-radius: 10px;
            +'padding: 10px 10px 5px;display: block;width: 570px;">'
            +'<div class="input-cell"><button></button>'
            +'<span class="heurist-helper3" style="vertical-align: middle;padding-left: 20px;">'
                +'You can also modify the fields for this record type whenever you are editing data</span>'
            +'</div></div>');
            
            var edit_ele = this._editing.getFieldByName('rty_ShowURLOnEditForm');
            $s.insertAfter(edit_ele);
        
            var btn = $s.find('button');
            var new_record_params = {RecTypeID: this._currentEditID};
            btn.button({icon:'ui-icon-pencil',label:'Edit fields'})
                            .css({'font-weight': 'bold','font-size':'12px'})
                            .addClass('ui-heurist-button')
                            .width(150)
                            .click(function(){
                                //close this form and open edit structure
                                function __openEditStructure(){
                                    that._currentEditID = null;
                                    that._getEditDialog(true).dialog('close');
                                    
                                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                        {new_record_params:new_record_params, 
                                            edit_structure:true});
                                }
                                
                                if(that._editing.isModified()){
                                    that._saveEditAndClose( null , __openEditStructure );
                                }else{
                                    __openEditStructure();
                                }
                            });
            }
            
            edit_ele = this._editing.getFieldByName('rty_ID');
            $('<div style="display:block"><span style="margin-top: 15px;display: inline-block;font-size: 12px;">'
            +'Define: </span><h1 style="display: inline-block;margin:10px;">'
            +$Db.rty(this._currentEditID,'rty_Name')+'</h1></div>').insertBefore(edit_ele);            
        }
        
        
        var ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        ele = $('<div style="position:absolute;right:6px;top:4px;"><label><input type="checkbox" '
                        +(ishelp_on?'checked':'')+'/>explanations</label></div>').prependTo(this.editForm);
        this._on( ele.find('input'), {change: function( event){
            var ishelp_on = $(event.target).is(':checked');
            this.usrPreferences['help_on'] = ishelp_on;
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
        }});
        
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
        
        this.editForm.find('div.header').css({'min-widht':160, width:160});
        
        this._adjustEditDialogHeight();
    },   
    
    onEditFormChange: function(changed_element){
        
       this._super(changed_element);
       
       if(changed_element!==true && this._editing.isModified() && changed_element.options.dtID=='rty_Name'){
            var val = this._editing.getValue('rty_Name');
            var ele = this._editing.getInputs('rty_Plural');
            $(ele[0]).val(val+'s');
            //this._editing.setFieldValueByName('rty_Plural', val+'s', false);
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
                    }, {title:'Confirm',yes:'Continue',no:'Cancel'},
                    {default_palette_class:this.options.default_palette_class});
        
        }else{
               this._super(recID); 
        }
    },
    
    //overwritten     NOT USED
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
                this._triggerRefresh('rty');
                return;    
        }
        
        $Db.rty().setRecord(recID, fieldvalues);
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
                        //if(!window.hWin.HEURIST4.util.isArrayNotEmpty(that._selected_fields)) that._selected_fields = [];

                        that._selected_fields['values'] = {};
                        //get first 3 separator base fields
                        var k = 0;
                        $Db.dty().each(function(dty_ID, rec){
                           if($Db.dty(dty_ID,'dty_Type')=='separator'){
                               if(k==0){
                                   that._selected_fields['fields'].unshift(dty_ID);    
                                   that._selected_fields['values'][dty_ID] = {dty_Name:'Overview',rst_DefaultValue:'tabs'};
                               }else{
                                   that._selected_fields['fields'].push(dty_ID);    
                                   if(k==1){
                                        that._selected_fields['values'][dty_ID] = {dty_Name:'Details',rst_DefaultValue:'tabs'};
                                   }else{
                                        that._selected_fields['values'][dty_ID] = {dty_Name:'References',rst_DefaultValue:'tabs'};
                                   }
                               }
                               k++;
                               if(k>2) return false;
                           } 
                        });                        
                        
                        
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
                                    
                                    //refresh local defs and show edit structure popup
                                    window.hWin.HAPI4.EntityMgr.getEntityData('defRecStructure', true,
                                    function(){
                                        that._onActionListener(null, {recID:recID, action:'editstr'} );
                                    });

                                    /*
                                    window.hWin.HEURIST4.dbs.rtyRefresh(recID, 
                                    function(){
                                        that._onActionListener(null, {recID:recID, action:'editstr'} );
                                        var rtg_ID = $Db.rty(recID,'rty_RecTypeGroupID');
                                        that.updateGroupCount(rtg_ID, 1);
                                    });
                                    */
                                    
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);      
                                }
                            });
                    },
                    callback:function(context){
                        that._selected_fields = context;
                    },
                    default_palette_class: this.options.default_palette_class 
            });
            
        }else{
        }
        this._triggerRefresh('rty');
        
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
    /*
        if(rtg_ID>0){
            var cnt = parseInt($Db.rtg(rtg_ID,'rtg_RtCount'));
            var cnt = (isNaN(cnt)?0:cnt)+delta;
            if(cnt<0) cnt = 0;
            $Db.rtg(rtg_ID,'rtg_RtCount',cnt);
            this._triggerRefresh('rtg');
        }
    */
    },    
    
    //
    //
    //
    getUiPreferences:function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
        return this.usrPreferences;
    },
    
    saveUiPreferences:function(new_params){

        if(this.options.select_mode=='select_multi') return true;
        if(new_params){
            var params = this.getUiPreferences();
            
            params['fields'] = new_params['fields']; 
        
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

        if(event) this.saveUiPreferences( params );
        
        this.options.ui_params = window.hWin.HEURIST4.util.cloneJSON( this.usrPreferences);
        
        //refresh result list
        this.recordList.resultList('applyViewMode','list', true);
        this.recordList.resultList('refreshPage');
        
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
                            //window.hWin.HEURIST4.rectypes = response.data.rectypes; //REMARKED
                            
                            window.hWin.HAPI4.EntityMgr.refreshEntityData('rty,rst',function(){
                                 window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
                            }); 

                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }                                        
                }

                var baseurl = window.hWin.HAPI4.baseURL + "hsapi/utilities/duplicateRectype.php";
                
                window.hWin.HEURIST4.util.sendRequest(baseurl, { rtyID:rectypeID }, null, _editAfterDuplicate);

        }, {title:'Confirm',yes:'Continue',no:'Cancel'},
        {default_palette_class:this.options.default_palette_class});
    },


    // Change group for rectype
    // params:  {rty_ID:rty_ID, rty_RecTypeGroupID:rtg_ID }
    //                                
    changeRectypeGroup: function(params){                                    
        window.hWin.HEURIST4.msg.bringCoverallToFront(this.recordList);

        var that = this;
        this._saveEditAndClose( params ,
            function(){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                that.searchForm.searchDefRecTypes('startSearch');
                that._triggerRefresh('rty');
                /*
                window.hWin.HAPI4.EntityMgr.refreshEntityData('rtg',
                    function(){
                        that._triggerRefresh('rtg');
                    }
                )*/
        });
    }                                    

    
});
