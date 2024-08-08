/**
* recordDataTable.js - select fields to be visible in DataTable for particular record type
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
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


        let that = this;
        if(!window.hWin.HEURIST4.util.isFunction($('body')['configEntity'])){ //OK! widget script js has been loaded

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

        }).css('display', 'inline-block');

        $(this.element.find('#divLoadSettings').find('div')[0]).css({padding: '0px 16px', width: '510px'});

        this.element.find('#divLoadSettings').configEntity( 'updateList', this.selectRecordScope.val(), 
            this.options.initial_cfg?this.options.initial_cfg.cfg_name:null );    

        if(this.options.initial_cfg){
            this.setSettings(this.options.initial_cfg);
        }

        this._on(this.element.find('#selectAll'), {
            click: function(e){

                let treediv = that.element.find('.rtt-tree');

                let check_status = $(e.target).is(":checked");

                if(!treediv.is(':empty') && treediv.fancytree("instance")){
                    let tree = treediv.fancytree("getTree");
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

        this._on(this.element.find('#uncheckAll'), { //.button()
            click: function(e){

                let treediv = that.element.find('.rtt-tree');
                if(!treediv.is(':empty') && treediv.fancytree("instance")){
                    const tree = treediv.fancytree('getTree');
                    const selected = tree.getSelectedNodes();

                    for(const node of selected){
                        node.setSelected(false);
                        //node.setActive(false);
                    }
                }
            }
        });

        let $ele = $(this.element.find('#divLoadSettings').find('div')[0]).children();
        $($ele[0]).css('flex', '0 0 70px');
        $($ele[2]).css('flex', '0 0 70px');
        $($ele[1]).css('flex', '0 0 350px');
        this.element.find('label[for="sel_saved_settings"]').css('margin-right', '17px');
    },

    //
    // TO UI
    //
    setSettings: function(settings){
        
        this.selectedFields = [];
        
        if(settings){
        
            let that = this;
            //restore selection
            that.selectedColumns = settings.columns; 
            that.selectedFields = settings.fields; 
            
            let id = this._selectedRtyID;
            this._selectedRtyID = null;
            this._loadRecordTypesTreeView( id );
            
            let tree = that.element.find('.rtt-tree').fancytree("getTree");           
            //tree.render(true);
            tree.visit(function(node){
                node.setSelected(false); //reset
                node.setExpanded(true);
            });            
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
        
            let list = this.element.find('div.rtt-list');
            let tree = this.element.find('div.rtt-tree').fancytree("getTree");
            let that = this;

            tree.visit(function(node){
                    if(!window.hWin.HEURIST4.util.isArrayNotEmpty(node.children)){ //this is leaf
                        //find it among facets
                        for(let i=0; i<that.selectedFields.length; i++){
                            if(that.selectedFields[i]==node.data.code){
                                //that._addSelectedColumn(node.data.code, node.data.title);
                                node.setSelected(true);
                                break;
                            }
                        }
                    }
                });

            let cont = this.element.find('div.rtt-list');
            //set visibility and order   
            for(let i=0; i<that.selectedColumns.length; i++){
                let dtid = that.selectedColumns[i].data; 
                let ele =  cont.find('div[data-key="'+dtid+'"]');
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
        
            let ids = code.split(':');
            let rtid = ids[ids.length-2];
            let dtid = ids[ids.length-1];
            let parentcode = '';
            
            if(ids.length==4){
                //include parent resource field
                let parent_rtid = ids[0];
                let parent_dtid = ids[1];
                let linktype = parent_dtid.substr(0,2); //remove link type lt ot rt  10:lt34
                if(isNaN(Number(linktype))){
                    parent_dtid = parent_dtid.substr(2);
                }
                parentcode = parent_rtid+':'+parent_dtid;
                
                let fieldtitle = $Db.rst(parent_rtid, parent_dtid, 'rst_DisplayName');
                
                this._addSelectedColumn(parentcode, fieldtitle);    
                
                title = $Db.rty(rtid,'rty_Name') +'.'+ title;        
            }

            let header_fields = {id:'rec_ID',title:'rec_Title',url:'rec_URL',modified:'rec_Modified',tags:'rec_Tags'};
            if(header_fields[dtid]){
                dtid = header_fields[dtid];
            }
            if(rtid!=this._selectedRtyID){
                dtid = rtid+'.'+dtid;
            }
            
            let container = this.element.find('div.rtt-list');
            
            if(container.find('div[data-key="'+dtid+'"]').length==0){ //avoid duplication
                $('<div data-code="'+code+'" data-key="'+dtid+'"'+(parentcode?(' data-parent="'+parentcode+'"'):'')+'>'
                    +'<input type="checkbox" class="columnVisibility" title="Visibility in DataTable" checked>&nbsp;<span style="cursor:ns-resize">'
                    +title+'</span>'
                    +'<select class="columnWidth" title="Column width" style="width:50px;margin-left:10px;font-size:smaller;">'
                    +'<option></option><option>5</option><option>10</option><option selected>20</option><option>50</option><option>100</option>'
                    +'<option>200</option><option>300</option><option>400</option><option>500</option></select>'
                    +'</div>').appendTo(container);
                container.sortable();

                let $select = container.find('div[data-key="'+dtid+'"] select');

                let type = $Db.dty(dtid, 'dty_Type');
                let is_number = dtid == 'ids' || dtid == 'typeid' || type == 'float';
                let is_date = dtid == 'added' || dtid == 'modified' || type == 'date';
                let is_term = dtid == 'access' || dtid == 'tag' || type == 'enum';
                if(is_number || is_date){
                    $select.val(5);
                }else if(is_term){
                    $select.val(20);
                }else{
                    $select.val(100);
                }
            }
    },
  
    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        let that = this;
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

        let  selScope = this.selectRecordScope.get(0);
        
        let scope_types = [];   
        
        let rectype_Ids = this._currentRecordset.getRectypes();
        
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
                let opt = window.hWin.HEURIST4.ui.addoption(selScope,'','select record type …');
                $(opt).attr('disabled','disabled').attr('visiblity','hidden').css({display:'none'});
            }
            for (let rty in rectype_Ids){
                    if(rty>=0 && $Db.rty(rectype_Ids[rty],'rty_Name')){
                        rty = rectype_Ids[rty];
                        let name = $Db.rty(rty,'rty_Plural');

                        if(!name) name = $Db.rty(rty,'rty_Name');
                        window.hWin.HEURIST4.ui.addoption(selScope,rty,name); //'only: '+
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
    // getSettings, saveSetting and close with settings as context
    //
    doAction: function(mode){

            let settings = this.getSettings(true);            
            if(!settings) return;
            
            
            //compare if something changed autosave
            let ele = this.element.find('#divLoadSettings');
            let cfg_name = (ele.configEntity('instance'))?ele.configEntity( 'isSomethingChanged'):'';
            if(cfg_name===true)
            {
                let that = this;
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
    // 1) need to add "t" prefix for record types 10.1 will be t10.1 (to avoid mix with field id)
    // 2) remove "r" for relationship fields  r.6 will be 6
    //
    getSettings: function( mode_action ){

        //get selected fields from treeview
        let selectedFields = []; //false && mode_action?{}:[];
        let tree = this.element.find('.rtt-tree').fancytree("getTree");
        let fieldIds = tree.getSelectedNodes(false);
        let k, len = fieldIds.length;

        if(len<1){
            window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
                +'Please select at least one field in tree', 2000);
            return false;
        }

        for (k=0;k<len;k++){
            let node =  fieldIds[k];

            if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;

            selectedFields.push(node.data.code);
        }

        let selectedCols = [];
        let need_id = true, need_type = true;

        this.element.find('div.rtt-list > div').each(function(idx,item){

			let $item = $(item);
            let isVisible = $item.find('input.columnVisibility').is(':checked');
            let colName = $item.attr('data-key');

            if(colName == 'ids'){
                colName = 'rec_ID';
            }else if(colName == 'typeid'){
                colName = 'rec_RecTypeID';
            }else if(colName.indexOf('.')>0){
                //add "t" prefix
                let codes = colName.split('.');
                if(codes[1]=='r'){
                    colName = 't'+ codes[0] + '.' + codes[2];
                }else{
                    colName = 't'+colName;
                }
            }

            let colopts = {
                data: colName,                 
                title: $item.find('span').text(), 
                visible:  isVisible
            };
            if(isVisible && $item.find('select.columnWidth').val()>0){
                colopts['width'] = $item.find('select.columnWidth').val();
                colopts['className'] = 'truncate width'+colopts['width'];
            }

            selectedCols.push(colopts);
            if(need_id && colName == 'rec_ID') need_id = false;
            if(need_type && (colName=='rec_RecTypeID' || colName=='typename')) need_type = false;
        });

        if(need_id){
            selectedCols.push({data:'rec_ID',title:'ID', visible:false});
        }
        if(need_type){
            selectedCols.push({data:'rec_RecTypeID',title:'Record type ID', visible:false});
        }
        if(selectedCols.length==2){
            selectedCols = null;//.push({data:'rec_Title',title:'Title',visible:true});    
        }

        //fields for treeview, columns for datatable
        return { rty_ID:this.selectRecordScope.val(), fields: selectedFields, columns: selectedCols };
    },

    //
    // overwritten
    //
    _onRecordScopeChange: function() 
    {
        let isdisabled = this._super();
        
        //window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction2'), isdisabled );
        
        let rtyID = this.selectRecordScope.val();
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
        
        let that = this;

        if(this._selectedRtyID!=rtyID ){
            
            this._selectedRtyID = rtyID;
            
            this.element.find('div.rtt-list').empty();
            
            //generate treedata from rectype structure
            let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, ['header_ext','all','parent_link'] );
            
            treedata[0].expanded = true; //first expanded
            
            //load treeview
            let treediv = this.element.find('.rtt-tree');
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
                            for(let i=0; i<data.node.children.length; i++){
                                let node = data.node.children[i];
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
                    if(data.node.data.type == 'separator'){
                        $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                        $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                    }
                },
                lazyLoad: function(event, data){
                    let node = data.node;
                    let parentcode = node.data.code; 
                    let rectypes = node.data.rt_ids;

                    if(parentcode.split(":").length<4){  //limit with 2 levels
                    
                        let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
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
                            let cont = that.element.find('div.rtt-list');
                            let ele= cont.find('div[data-code="'+data.node.data.code+'"]');
                            
                            //remove parent link field
                            let parent_code = ele.attr('data-parent');
                            if(parent_code){
                                let parent_ele = cont.find('div[data-code="'+parent_code+'"]');
                                let same_level_ele = cont.find('div[data-parent="'+parent_code+'"]');
                                if(same_level_ele.length==1) parent_ele.remove();
                            }
                            ele.remove();    
                        }
                },
                click: function(e, data){

                    if(data.node.data.type == 'separator'){
                        return false;
                    }

                    let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

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
                    if(data.node.data.type == 'separator'){
                        return false;
                    }
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
