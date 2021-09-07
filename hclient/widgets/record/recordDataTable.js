/**
* recordDataTable.js - select fields to be visible in DataTable for particular record type
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

$.widget( "heurist.recordDataTable", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Configure DataTable columns',
        
        htmlContent: 'recordDataTable.html',
        helpContent: 'recordDataTable.html', //in context_help folder
        
        initial_cfg: null
    },

    selectedFields:null,
    selectedColumns:null,
    
    _initControls: function() {


        var that = this;
        if(!$.isFunction($('body')['configEntity'])){ //OK! widget script js has been loaded

            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/entity/configEntity.js', 
                function(){ 
                    that._initControls();            

            } );
            return;            
        }

        this._super();    


        this.element.find('#divLoadSettings').configEntity({
            entityName: 'defRecTypes',
            configName: 'datatable',

            getSettings: function(){ return that.getSettings(false); }, //callback function to retrieve configuration
            setSettings: function( settings ){ that.setSettings( settings ); }, //callback function to apply configuration

            //divLoadSettingsName: this.element
            divSaveSettings: this.element.find('#divSaveSettings'),  //element
            showButtons: true,
            buttons: {rename:false, remove:'delete'}, //hide rename button
            saveOnExit: true  //auto save on exit

        });

        this.element.find('#divLoadSettings').configEntity( 'updateList', this.selectRecordScope.val(), 
            this.options.initial_cfg?this.options.initial_cfg.cfg_name:null );    

        if(this.options.initial_cfg){
            this.setSettings(this.options.initial_cfg);
        }

        this._on(this.element.find('#selectAll'), {
            click: function(e){

                var treediv = that.element.find('.rtt-tree');

                var check_status = $(e.target).is(":checked");

                if(!treediv.is(':empty') && treediv.fancytree("instance")){
                    var tree = treediv.fancytree("getTree");
                    tree.visit(function(node){
                        if(!node.hasChildren() && node.data.type != "relmarker" && node.data.type != "resource" 
                            && (node.getLevel()==2 || (!window.hWin.HEURIST4.util.isempty(node.span) && $(node.span.parentNode.parentNode).is(":visible")))
                        ){    
                            node.setSelected(check_status);
                        }
                    });
                }
            }
        });
        this.element.find('#selectAll_container').hide();
    },

    //
    // TO UI
    //
    setSettings: function(settings){
        
        this.selectedFields = [];
        
        if(settings){
        
            var that = this;
            //restore selection
            that.selectedColumns = settings.columns; 
            that.selectedFields = settings.fields; 
            
            var id = this._selectedRtyID;
            this._selectedRtyID = null;
            this._loadRecordTypesTreeView( id );
            
            var tree = that.element.find('.rtt-tree').fancytree("getTree");           
            //tree.render(true);
            tree.visit(function(node){
                node.setSelected(false); //reset
                node.setExpanded(true);
            });            
            
            /*
            var timerId = setInterval(function(){
                if(tree.isLoading()){
                    clearInterval(timerId);
                    that._assignSelectedFields();
                }
            } ,200); */
            setTimeout(function(){
                that._assignSelectedFields();
            },1000);
            
            /*
            that.element.find('#delimiterSelect').val(settings.csv_delimiter);
            that.element.find('#quoteSelect').val(settings.csv_enclosure);
            that.element.find('#cbNamesAsFirstRow').prop('checked',(settings.csv_header==1));
            that.element.find('#cbIncludeTermIDs').prop('checked',(settings.include_term_ids==1));
            that.element.find('#cbIncludeTermCodes').prop('checked',(settings.include_term_codes==1));
            that.element.find('#cbIncludeTermHierarchy').prop('checked',(settings.include_term_hierarchy==1));
            that.element.find('#cbIncludeResourceTitles').prop('checked',(settings.include_resource_titles==1));
            */
        }
    },
    
    //
    // assign selected fields in tree
    //
    _assignSelectedFields: function(){

        if(this.selectedFields && this.selectedFields.length>0){
        
            var list = this.element.find('div.rtt-list');
            var tree = this.element.find('div.rtt-tree').fancytree("getTree");
            var that = this;

            tree.visit(function(node){
                    if(!window.hWin.HEURIST4.util.isArrayNotEmpty(node.children)){ //this is leaf
                        //find it among facets
                        for(var i=0; i<that.selectedFields.length; i++){
                            if(that.selectedFields[i]==node.data.code){
                                //that._addSelectedColumn(node.data.code, node.data.title);
                                node.setSelected(true);
                                break;
                            }
                        }
                    }
                });

            var cont = this.element.find('div.rtt-list');
            //set visibility and order   
            for(var i=0; i<that.selectedColumns.length; i++){
                var dtid = that.selectedColumns[i].data; 
                var ele =  cont.find('div[data-key="'+dtid+'"]');
                if(ele.length>0){
                    ele.attr('data-order',i);
                    ele.find('input.columnVisibility').prop('checked', that.selectedColumns[i].visible);
                    ele.find('select.columnWidth').val(that.selectedColumns[i].width>0?that.selectedColumns[i].width:20);
                }
            }
            cont.find('div').sort(function(a,b){
                return $(a).attr('data-order')<$(b).attr('data-order')?-1:1;
            }).appendTo(cont);
        }
    },

    //
    // add selected field to right hand column list
    // code: 3:lt134:12:id
    //    
    _addSelectedColumn: function(code, title){
        
            var ids = code.split(':');
            var rtid = ids[ids.length-2];
            var dtid = ids[ids.length-1];
            var parentcode = '';
            
            if(ids.length==4){
                //include parent resource field
                var parent_rtid = ids[0];
                var parent_dtid = ids[1];
                var linktype = parent_dtid.substr(0,2); //remove link type lt ot rt  10:lt34
                if(isNaN(Number(linktype))){
                    parent_dtid = parent_dtid.substr(2);
                }
                parentcode = parent_rtid+':'+parent_dtid;
                
                var fieldtitle = $Db.rst(parent_rtid, parent_dtid, 'rst_DisplayName');
                
                this._addSelectedColumn(parentcode, fieldtitle);    
                
                title = $Db.rty(rtid,'rty_Name') +'.'+ title;        
            }

            var header_fields = {id:'rec_ID',title:'rec_Title',url:'rec_URL',modified:'rec_Modified',tags:'rec_Tags'};
            if(header_fields[dtid]){
                dtid = header_fields[dtid];
            }
            if(rtid!=this._selectedRtyID){
                dtid = rtid+'.'+dtid;
            }
            
            var container = this.element.find('div.rtt-list');
            
            if(container.find('div[data-key="'+dtid+'"]').length==0){ //avoid duplication
                $('<div data-code="'+code+'" data-key="'+dtid+'"'+(parentcode?(' data-parent="'+parentcode+'"'):'')+'>'
                    +'<input type="checkbox" class="columnVisibility" title="Visibility in DataTable" checked>&nbsp;<span style="cursor:ns-resize">'
                    +title+'</span>'
                    +'<select class="columnWidth" title="Column width" style="width:50px;margin-left:10px;font-size:smaller;">'
                    +'<option></option><option>10</option><option selected>20</option><option>50</option><option>100</option>'
                    +'<option>200</option><option>300</option><option>400</option><option>500</option></select>'
                    +'</div>').appendTo(container);
                container.sortable();
            }
    },
  
    //    
    //
    //
    _getActionButtons: function(){
        var res = this._super();
        var that = this;
        res[1].text = window.hWin.HR('Apply');
        res[0].text = window.hWin.HR('Close');
        /*
        res.push({text:window.hWin.HR('Export'),
                    id:'btnDoAction2',
                    disabled:'disabled',
                    css:{'float':'right'},  
                    click: function() { 
                            that.doAction( 1 ); 
                    }});
       */ 
        return res;
    },    
        
    //
    // overwrite parent's method (called fron super._initControls)
    //
    _fillSelectRecordScope: function (){

        this.selectRecordScope.empty();

        var opt, selScope = this.selectRecordScope.get(0);

        
        var scope_types = [];   
        
        var rectype_Ids = this._currentRecordset.getRectypes();
        
        if(rectype_Ids.length>0 && 
           this.options.initial_cfg && 
           window.hWin.HEURIST4.util.findArrayIndex(this.options.initial_cfg.rty_ID,rectype_Ids)<0)
        {
             rectype_Ids.push(this.options.initial_cfg.rty_ID);
        }

        if(rectype_Ids.length==0){
            window.hWin.HEURIST4.ui.createRectypeSelect(selScope,null,'select record type …',true);
        }else{
            if(rectype_Ids.length>1){
                var opt = window.hWin.HEURIST4.ui.addoption(selScope,'','select record type …');
                $(opt).attr('disabled','disabled').attr('visiblity','hidden').css({display:'none'});
            }
            for (var rty in rectype_Ids){
                    if(rty>=0 && $Db.rty(rectype_Ids[rty],'rty_Plural')){
                        rty = rectype_Ids[rty];
                        window.hWin.HEURIST4.ui.addoption(selScope,rty,
                                $Db.rty(rty,'rty_Plural')); //'only: '+
                    }
            }
        }

/*        
        if (this._currentRecordset &&  this._currentRecordset.length() > 0 && rectype_Ids.length>1) {
                var msg = 'Any recordtype: Basic record fields only';
                window.hWin.HEURIST4.ui.addoption(selScope, '', msg);
        }
*/        
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        

        if(this.options.initial_cfg){
            this.selectRecordScope.val(this.options.initial_cfg.rty_ID);
        }
                
        this._onRecordScopeChange();
        
        window.hWin.HEURIST4.ui.initHSelect(selScope);
    },
            
    //
    // 0 - download, 1 - open in new window
    //
    doAction: function(mode){

            var settings = this.getSettings(true);            
            if(!settings) return;
            
            
            //compare if something changed autosave
            var ele = this.element.find('#divLoadSettings');
            var cfg_name = (ele.configEntity('instance'))?ele.configEntity( 'isSomethingChanged'):'';
            if(cfg_name===true)
            {
                var that = this;
                ele.configEntity( 'saveSettings', function(cfg_name){
                        //close dialog 
                        settings.cfg_name = cfg_name; 
                        that._context_on_close = settings;
                        that._as_dialog.dialog('close');
                    });    
                
            }else{
                settings.cfg_name = cfg_name; 
                this._context_on_close = settings;
                this._as_dialog.dialog('close');
            }
    },
    
    // FROM UI
    // mode_action true - returns columns for DataTable, false - returns codes of selected nodes
    //
    getSettings: function( mode_action ){
            /*
            var header_fields = {id:'rec_ID',title:'rec_Title',url:'rec_URL',modified:'rec_Modified',tags:'rec_Tags'};
            function __removeLinkType(dtid){
                if(header_fields[dtid]){
                    dtid = header_fields[dtid];
                }else{
                    var linktype = dtid.substr(0,2); //remove link type lt ot rt  10:lt34
                    if(isNaN(Number(linktype))){
                        dtid = dtid.substr(2);
                    }
                }
                return dtid;
            }
            function __addSelectedField(ids, lvl, constr_rt_id){
                
                if(ids.length < lvl) return;
                
                //take last two - these are rt:dt
                var rtid = ids[ids.length-lvl-1];
                var dtid = __removeLinkType(ids[ids.length-lvl]);
                
                if(!selectedFields[rtid]){
                    selectedFields[rtid] = [];    
                }
                if(constr_rt_id>0){
                    dtid = dtid+':'+constr_rt_id;
                }
                
                //window.hWin.HEURIST4.util.findArrayIndex( dtid, selectedFields[rtid] )<0
                if( selectedFields[rtid].indexOf( dtid )<0 ) {
                    
                    selectedFields[rtid].push(dtid);    
                    
                    //add resource field for parent recordtype
                    __addSelectedField(ids, lvl+2, rtid);
                }
            }
            */
            //get selected fields from treeview
            var selectedFields = false&&mode_action?{}:[];
            var tree = this.element.find('.rtt-tree').fancytree("getTree");
            var fieldIds = tree.getSelectedNodes(false);
            var k, len = fieldIds.length;
            
            if(len<1){
                window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
                    +'Please select at least one field in tree', 2000);
                return false;
            }
            
            
            for (k=0;k<len;k++){
                var node =  fieldIds[k];
                
                if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
                
                if(false && mode_action){
                    var ids = node.data.code.split(":");
                    __addSelectedField(ids, 1, 0);
                }else{
                    selectedFields.push(node.data.code);
                }
                
                //DEBUG console.log( node.data.code );
            }
            
            var selectedCols = [];
            var need_id = true, need_type = true;
            
            this.element.find('div.rtt-list > div').each(function(idx,item){
                var $item = $(item);
                var isVisible = $item.find('input.columnVisibility').is(':checked');
                var colopts = {
                    data: $item.attr('data-key'),                 
                    title: $item.find('span').text(), 
                    visible:  isVisible
                };
                if(isVisible && $item.find('select.columnWidth').val()>0){
                    colopts['width'] = $item.find('select.columnWidth').val();
                    colopts['className'] = 'truncate width'+colopts['width'];
                }
                
                selectedCols.push(colopts);
                if(need_id && $item.attr('data-key')=='rec_ID') need_id = false;
                if(need_type && $item.attr('data-key')=='rec_RecTypeID') need_type = false;
            });
            if(need_id){
                selectedCols.push({data:'rec_RecTypeID',title:'Record type',visible:false});    
            }
            if(need_type){
                selectedCols.push({data:'rec_ID',title:'ID',visible:false});    
            }
            if(selectedCols.length==2){
                selectedCols = null;//.push({data:'rec_Title',title:'Title',visible:true});    
            }
            
            //DEBUG 
        //console.log( selectedFields )
        //fields for treeview, columns for datatable
        return { rty_ID:this.selectRecordScope.val(), fields: selectedFields, columns: selectedCols };
        
    },

    //
    // overwritten
    //
    _onRecordScopeChange: function() 
    {
        var isdisabled = this._super();
        
        //window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction2'), isdisabled );
        
        var rtyID = this.selectRecordScope.val();
        //reload treeview
        this._loadRecordTypesTreeView( rtyID );
        
        this.element.find('#divSaveSettings').hide();
        this.element.find('#divLoadSettings').hide();
        
        if(rtyID==''){
            this.element.find('.rtt-tree').parent().hide();
        }else{
            this.element.find('.rtt-tree').parent().show();
            if(rtyID>0){
                this.selectedFields = [];
            }
        }
        
        if(this.element.find('#divLoadSettings').configEntity('instance')){
            this.element.find('#divLoadSettings').configEntity( 'updateList', rtyID );    
        }
        
        return isdisabled;
    },
    
    //
    // show treeview with record type structure
    //
    _loadRecordTypesTreeView: function(rtyID){
        
        var that = this;

        if(this._selectedRtyID!=rtyID ){
            
            this._selectedRtyID = rtyID;
            
            this.element.find('div.rtt-list').empty();
            
            //generate treedata from rectype structure
            var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, ['header_ext','all','parent_link'] );
            
            treedata[0].expanded = true; //first expanded
            
            //load treeview
            var treediv = this.element.find('.rtt-tree');
            if(!treediv.is(':empty') && treediv.fancytree("instance")){
                treediv.fancytree("destroy");
            }
            
            treediv.addClass('tree-csv').fancytree({
                //extensions: ["filter"],
                //            extensions: ["select"],
                checkbox: true,
                selectMode: 3,  // hierarchical multi-selection
                source: treedata,
                beforeSelect: function(event, data){
                    // A node is about to be selected: prevent this, for folder-nodes:
                    if( data.node.hasChildren() ){
                        
                        if(data.node.isExpanded()){
                            for(var i=0; i<data.node.children.length; i++){
                                var node = data.node.children[i];
                                if(node.key=='rec_ID' || node.key=='rec_Title'){
                                    node.setSelected(true);
                                }
                            }
                        }
                        return false;
                    }
                },
                renderNode: function(event, data){
                    
                    if(data.node.data.is_generic_fields) { // hide blue arrow for generic fields
                        $(data.node.span.childNodes[1]).hide();
                    }
                },
                lazyLoad: function(event, data){
                    var node = data.node;
                    var parentcode = node.data.code; 
                    var rectypes = node.data.rt_ids;

                    if(parentcode.split(":").length<4){  //limit with 2 levels
                    
                        var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                                                            rectypes, ['header_ext','all'], parentcode );
                        if(res.length>1){
                            data.result = res;
                        }else{
                            data.result = res[0].children;
                        }
                    }else{
                        data.result = [];
                    }                            
                        
                    return data;                       
                    
                },
                loadChildren: function(e, data){
                    setTimeout(function(){
                        //that._assignSelectedFields();
                    },500);
                },
                select: function(e, data) {
                        
                        if(data.node.isSelected()){
                            that._addSelectedColumn(data.node.data.code, data.node.data.name);
                        }else{
                            var cont = that.element.find('div.rtt-list');
                            var ele= cont.find('div[data-code="'+data.node.data.code+'"]');
                            
                            //remove parent link field
                            var parent_code = ele.attr('data-parent');
                            if(parent_code){
                                var parent_ele = cont.find('div[data-code="'+parent_code+'"]');
                                var same_level_ele = cont.find('div[data-parent="'+parent_code+'"]');
                                if(same_level_ele.length==1) parent_ele.remove();
                            }
                            ele.remove();    
                        }
                },
                click: function(e, data){

                    var isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                    if(isExpander){
                        return;
                    }

                    if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                        data.node.setExpanded(!data.node.isExpanded());
                    }else if( data.node.lazy) {
                        data.node.setExpanded( true );
                    }
                },
                dblclick: function(e, data) {
                    data.node.toggleSelected();
                },
                keydown: function(e, data) {
                    if( e.which === 32 ) {
                        data.node.toggleSelected();
                        return false;
                    }
                }
            });
        }   
    },
    
});
