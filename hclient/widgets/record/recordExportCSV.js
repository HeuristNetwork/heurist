/**
* recordExportCSV.js - select fields to be exported to CSV for current recordset
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

$.widget( "heurist.recordExportCSV", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Export records to comma or tab separated text files',
        default_palette_class: 'ui-heurist-publish', 
        
        htmlContent: 'recordExportCSV.html',
        helpContent: 'recordExportCSV.html' //in context_help folder
    },

    selectedFields:null,
    
    MAX_LEVEL:3,
    
    _destroy: function() {
        this._super(); 
        
        var treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        treediv.remove();
        
        if(this.toolbar)this.toolbar.remove();
    },
        
    _initControls: function() {


        var that = this;
        if(!$.isFunction($('body')['configEntity'])){ //OK! widget script js has been loaded

            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/entity/configEntity.js', 
                function(){ 
                    if(that._initControls()){
                        if($.isFunction(that.options.onInitFinished)){
                            that.options.onInitFinished.call(that);
                        }        
                    }
            } );
            return false;            
        }

        this._super();    


        this.element.find('#divLoadSettings').configEntity({
            entityName: 'defRecTypes',
            configName: 'csvexport',

            getSettings: function(){ return that.getSettings(false); }, //callback function to retieve configuration
            setSettings: function( settings ){ that.setSettings( settings ); }, //callback function to apply configuration

            //divLoadSettingsName: this.element
            divSaveSettings: this.element.find('#divSaveSettings'),  //element
            showButtons: true

        });

        this.element.find('#divLoadSettings').configEntity( 'updateList', this.selectRecordScope.val() );    


        // Initialize field advanced pane.
        this._resetAdvancedControls();
        //this.hideAdvancedPane();
        
        if(!this.options.isdialog && this.options.is_h6style){
            var fele = this.element.find('.ent_wrapper:first');
            fele.css({top:'36px',bottom:'40px'});
            $('<div class="ui-heurist-header">'+this.options.title+'</div>').insertBefore(fele);    

            var toolbar_height = '20px';
            if(navigator.userAgent.indexOf('Firefox') >= 0){
                toolbar_height = '40px';
            }
            this.toolbar = $('<div class="ent_footer button-toolbar ui-heurist-header" style="height:'+ toolbar_height +'"></div>').insertAfter(fele);    

            //append action buttons
            this.toolbar.empty();
            var btns = this._getActionButtons();
            for(var idx in btns){
                this._defineActionButton2(btns[idx], this.toolbar);
            }
        }
        
        this.element.find('.export-to-bottom-button').on('click', function () {
            var container = $(this).parent();
            container.scrollTop(container[0].scrollHeight);
        });

        // Reorder tree nodes
        this._on($('[name="tree_order"]'), {
            change: () => {
                let order = $('[name="tree_order"]:checked').val();
                sessionStorage.setItem('heurist_ftorder_exportcsv', order);

                var treediv = that.element.find('.rtt-tree');

                if(treediv.length > 0 && treediv.fancytree('instance')!==undefined){
                    window.hWin.HEURIST4.ui.reorderFancytreeNodes_rst(treediv, order);
                }
            }
        });

        return true;
    },

    //
    //
    //
    setSettings: function(settings){
        
        this.selectedFields = [];
        
        if(settings){
        
            var that = this;
            //restore selection
            that.selectedFields = settings.fields; 

            that._assignSelectedFields(null);

            // Set advanced options.
            if (settings.hasOwnProperty('advanced_options') && settings.advanced_options) {
                that._setFieldAdvancedOptions(settings.advanced_options);
            }
            
            /*
            var tree = that.element.find('.rtt-tree').fancytree("getTree");           
            tree.visit(function(node){
                node.setSelected(false);
                node.setExpanded(true);
            });            
            
            setTimeout(function(){
                that._assignSelectedFields();

                // Set advanced options.
                if (settings.hasOwnProperty('advanced_options') && settings.advanced_options) {
                    that._setFieldAdvancedOptions(settings.advanced_options);
                }
            },1000);
            */
            
            that.element.find('#delimiterSelect').val(settings.csv_delimiter);
            that.element.find('#quoteSelect').val(settings.csv_enclosure);
            that.element.find('#cbNamesAsFirstRow').prop('checked',(settings.csv_header==1));
            that.element.find('#cbIncludeTermIDs').prop('checked',(settings.include_term_ids==1));
            that.element.find('#cbIncludeTermCodes').prop('checked',(settings.include_term_codes==1));
            that.element.find('#cbIncludeTermHierarchy').prop('checked',(settings.include_term_hierarchy==1));
            that.element.find('#cbIncludeResourceTitles').prop('checked',(settings.include_resource_titles==1));
            that.element.find('#chkJoinRecTypes').prop('checked',(settings.join_record_types==1));
            that.element.find('#cbIncludeMediaURL').prop('checked',(settings.include_file_url==1));
            that.element.find('#cbIncludeRecURLHTML').prop('checked',(settings.include_record_url_html==1));
            that.element.find('#cbIncludeRecURLXML').prop('checked',(settings.include_record_url_xml==1));
            that.element.find('#cbIncludeTemporals').prop('checked',(settings.include_temporals==1));

        }
    },

    
    //
    // assign selected fields in tree
    //
    _assignSelectedFields: function(rnode){
        
            var that = this;
            var tree = that.element.find('.rtt-tree').fancytree("getTree");           

            if(rnode==null){
                rnode = tree.getRootNode();  
                rnode.setExpanded(true);
            } 
            
            rnode.visit(function(node){
                        node.setSelected(false);
                        var has_child = node.hasChildren();
                        if(has_child===undefined){
                            node.setExpanded(true);
                            setTimeout(function(){
                                    that._assignSelectedFields( node );
                            },500);

                        }else{
                            if(that.selectedFields && that.selectedFields.length>0 && has_child===false){
                                    for(var i=0; i<that.selectedFields.length; i++){
                                        if(that.selectedFields[i]==node.data.code){
                                            node.setSelected(true);
                                            break;
                                        }
                                    }
                            }
                        }
                });
            
            
/*
        if(this.selectedFields && this.selectedFields.length>0){
            tree.visit(function(node){
                    if(!window.hWin.HEURIST4.util.isArrayNotEmpty(node.children)){ //this is leaf
                        //find it among facets
                        for(var i=0; i<that.selectedFields.length; i++){
                            if(that.selectedFields[i]==node.data.code){
                                node.setSelected(true);
                                break;
                            }
                        }
                    }
                });
        }
*/                
    },
    
    //    
    //
    //
    _getActionButtons: function(){
        var res = this._super();
        var that = this;
        res[1].text = window.hWin.HR('Download');
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
    // overwrite parent's method
    //
    _fillSelectRecordScope: function (){

        this.selectRecordScope.empty();

        var opt, selScope = this.selectRecordScope.get(0);

        
        var scope_types = [];   
        
        var rectype_Ids = this._currentRecordset.getRectypes();
        
        if(rectype_Ids.length>1){
            
            var opt = window.hWin.HEURIST4.ui.addoption(selScope,'','select record type …');
            $(opt).attr('disabled','disabled').attr('visiblity','hidden').css({display:'none'});
        
            for (var rty in rectype_Ids){
                if(rty>=0 && $Db.rty(rectype_Ids[rty],'rty_Name') ){
                    rty = rectype_Ids[rty];
                    var name = $Db.rty(rty,'rty_Plural');
                    if(!name) name = $Db.rty(rty,'rty_Name');
                    window.hWin.HEURIST4.ui.addoption(selScope,rty,'only: '+name);
                }
            }
        }

        
        if (this._currentRecordset &&  this._currentRecordset.length() > 0) {
            
                var msg = (rectype_Ids.length>1)?'Basic record fields only':'Current result set';
                    
                window.hWin.HEURIST4.ui.addoption(selScope,
                    (rectype_Ids.length>1)?'current':rectype_Ids[0],
                    msg +' (count=' + this._currentRecordset.length()+')');
        }
        
        if (this._currentRecordsetSelIds &&  this._currentRecordsetSelIds.length > 0) {
                    
                window.hWin.HEURIST4.ui.addoption(selScope,'selected',
                    'Selected records only (count=' + this._currentRecordsetSelIds.length+')');
        }
        
        
        
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        //this.selectRecordScope.val(this.options.init_scope);    
        //if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        this._onRecordScopeChange();
        
        window.hWin.HEURIST4.ui.initHSelect(selScope);
        
        var wmenu = $(selScope).hSelect( "menuWidget" );  //was menu
        //wmenu.find('li.ui-state-disabled').css({'display':'none !important'});
        //$(selScope).hSelect('widget').text('select...');
        //this.element.find('li.ui-state-disabled').css({'display':'none !important'});
        
    },
            
    //
    // 0 - download, 1 - open in new window
    //
    doAction: function(mode){

            var scope_val = this.selectRecordScope.val();
            
            var scope = [], 
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else { //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }   
            }
            
            if(scope.length<1){
                window.hWin.HEURIST4.msg.showMsgFlash('No results found. '
                +'Please modify search/filter to return at least one result record.', 2000);
                return;
            }
            
            var settings = this.getSettings(true);
            if(!settings) return;

            var request = {
                'request_id' : window.hWin.HEURIST4.util.random(),
                'db': window.hWin.HAPI4.database,
                'ids'  : scope,
                'format': 'csv',
                'prefs': settings};
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
            
            var url = window.hWin.HAPI4.baseURL + 'hsapi/controller/record_output.php'
            
            //posting via form allows send large list of ids
            this.element.find('#postdata').val( JSON.stringify(request) );
            this.element.find('#postform').attr('action', url);
            this.element.find('#postform').submit();
                
            if(mode==1){ //open in new window
                
            }else{ //download
                
            }     
            /*
                var that = this;                                                
                
                window.hWin.HAPI4.RecordMgr.access(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.updated>0);
                            
                            that.closeDialog();
                            
                            var msg = 'Processed : '+response.data.processed + ' record'
                                + (response.data.processed>1?'s':'') +'. Updated: '
                                + response.data.updated  + ' record'
                                + (response.data.updated>1?'s':'');
                           if(response.data.noaccess>0){
                               msg += ('<br><br>Not enough rights (logout/in to refresh) for '+response.data.noaccess+
                                        ' record' + (response.data.noaccess>1?'s':''));
                           }     
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
      */  
    },
    
    //
    // mode_action true - returns fields for csv export, false - returns codes of selected nodes
    //
    getSettings: function( mode_action ){

            var header_fields = {ids:'rec_ID', title:'rec_Title', url:'rec_URL', modified:'rec_Modified', tag:'rec_Tags', 
                typeid: 'rec_RecTypeID', typename: 'rec_RecTypeName', added: 'rec_Added', addedby: 'rec_AddedByUGrpID', 
                owner: 'rec_OwnerUGrpID', access: 'rec_NonOwnerVisibility', notes: 'rec_ScratchPad'};

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
            var mainRecordTypeIDs = [];
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

                // Get main record type IDs.
                if (lvl === 1 && typeof ids[0] !== 'undefined' && mainRecordTypeIDs.indexOf(ids[0]) < 0) {
                    mainRecordTypeIDs.push(ids[0]);
                }
                
                //window.hWin.HEURIST4.util.findArrayIndex( dtid, selectedFields[rtid] )<0
                if( selectedFields[rtid].indexOf( dtid )<0 ) {
                    if(dtid == 'rec_Title' || dtid == 'rec_ID'){ // place title and id fields at the start
                        selectedFields[rtid].unshift(dtid);
                    }else{
                        selectedFields[rtid].push(dtid);
                    }
                } 
                //add resource field for parent recordtype
                __addSelectedField(ids, lvl+2, rtid);
            }
            
            //get selected fields from treeview
            var selectedFields = mode_action?{}:[];
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
                
                if(mode_action){
                    var ids = node.data.code.split(":");
                    __addSelectedField(ids, 1, 0);
                }else{
                    selectedFields.push(node.data.code);
                }
            }
        return {
            'fields': selectedFields,
            'main_record_type_ids': mainRecordTypeIDs,
            'join_record_types': this.element.find('#chkJoinRecTypes').is(':checked')?1:0,
            'advanced_options': this._getFieldAdvancedOptions(),
            'csv_delimiter':  this.element.find('#delimiterSelect').val(),
            'csv_enclosure':  this.element.find('#quoteSelect').val(),
            'csv_mvsep':'|',
            'csv_linebreak':'nix', //not used at tne moment
            'csv_header': this.element.find('#cbNamesAsFirstRow').is(':checked')?1:0,
            'include_term_ids': this.element.find('#cbIncludeTermIDs').is(':checked')?1:0,
            'include_term_codes': this.element.find('#cbIncludeTermCodes').is(':checked')?1:0,
            'include_file_url': this.element.find('#cbIncludeMediaURL').is(':checked')?1:0,
            'include_record_url_html': this.element.find('#cbIncludeRecURLHTML').is(':checked')?1:0,
            'include_record_url_xml': this.element.find('#cbIncludeRecURLXML').is(':checked')?1:0,
            'include_term_hierarchy': this.element.find('#cbIncludeTermHierarchy').is(':checked')?1:0,
            'include_resource_titles': this.element.find('#cbIncludeResourceTitles').is(':checked')?1:0,
            'include_temporals':  this.element.find('#cbIncludeTemporals').is(':checked')?1:0

        };
        
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
        
        $('#divSaveSettings').hide();
        $('#divLoadSettings').hide();
        
        if(rtyID=='' || rtyID==null){
            $('.rtt-tree').parent().hide();
            this.element.find('#export_format_container').hide();
        }else{
            $('.rtt-tree').parent().show();
            this.element.find('#export_format_container').show();
            if(rtyID>0){
                this.selectedFields = [];
            }
        }
        
        if(this.element.find('#divLoadSettings').configEntity('instance')){
            this.element.find('#divLoadSettings').configEntity( 'updateList', rtyID );    
        }
        
        this._resetAdvancedControls();
   
        return isdisabled;
    },
    
    //
    // show treeview with record type structure
    //
    _loadRecordTypesTreeView: function(rtyID){
        
        var that = this;

        if(this._selectedRtyID!=rtyID ){
            
            this._selectedRtyID = rtyID;
            
            let node_order = sessionStorage.getItem('heurist_ftorder_exportcsv');
            if(window.hWin.HEURIST4.util.isempty(node_order) || !Number.isInteger(+node_order)){
                node_order = 0; // default to form order
            }
            this.element.find('[name="tree_order"]').filter('[value="'+ node_order +'"]').prop('checked', true);
            
            //generate treedata from rectype structure
            var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, ['header_ext','all','parent_link'], null, node_order );
            
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
                        }else{

                        }
                        return false;
                    }
                },
                renderNode: function(event, data){

                    let order = that.element.find('[name="tree_order"]:checked').val();

                    if(data.node.parent && data.node.parent.data.type == 'resource' || data.node.parent.data.type == 'relmarker'){ // add left border+margin
                        $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                    }
                    if(data.node.data.type == 'separator'){
                        $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                        $(data.node.span.childNodes[1]).hide(); //checkbox for separators

                        if(order == 1){
                            $(data.node.li).addClass('fancytree-hidden');
                        }
                    }
                },
                lazyLoad: function(event, data){
                    var node = data.node;
                    var parentcode = node.data.code; 
                    var rectypes = node.data.rt_ids;
                    
                    if(parentcode.split(":").length< that.MAX_LEVEL*2+1){  //7 limit with 4 levels (till 2021-10-15 were 3 levels)
                    
                        let node_order = that.element.find('[name="tree_order"]:checked').val();

                        var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                                                            rectypes, ['header_ext','all','parent_link'], parentcode, node_order );
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
                        //that._assignSelectedFields( data.node );
                    },500);
                },
                select: function(e, data) {
                    if (data.node.isSelected()) {
                        that._addFieldAdvancedOptions(data.node.title, data.node.data.type, data.node.data.code, data.node.li);
                    } else {
                        that._removeFieldAdvancedOptionsByCode(data.node.data.code);
                    }
                    if (that.element.find('.export-advanced-list-item').length > 0) {
                        that.element.find('.export-advanced-list').show();
                    } else {
                        that.element.find('.export-advanced-list').hide();
                    }
                },
                click: function(e, data){

                    if(data.node.data.type == 'separator'){
                        return false;
                    }

                    var isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');
                    var setDefaults = !data.node.isExpanded();

                    if($(e.originalEvent.target).is('span') && !isExpander && data.node.children && data.node.children.length > 0){
                        data.node.setExpanded(!data.node.isExpanded());
                    }else if(data.node.lazy && !isExpander) {
                        data.node.setExpanded(true);
                    }
                },
                expand: function(e, data) {
                    if(data.node.children.length > 0){
                        for(var i = 0; i < data.node.children.length; i++){
                            var node = data.node.children[i];
                            if(node.key=='rec_ID' || node.key=='rec_Title'){
                                node.setSelected(true);
                            }
                        }
                    }
                    var selected_nodes = treediv.fancytree('getTree').getSelectedNodes();
                    for(var j = 0; j < selected_nodes.length; j++){
                        that._displayAdvOption(selected_nodes[j]['data']['code'], $(selected_nodes[j]['li']).is(':visible'), selected_nodes[j]['li']);
                    }
                },
                collapse: function(e, data) {
                    var selected_nodes = treediv.fancytree('getTree').getSelectedNodes();
                    for(var j = 0; j < selected_nodes.length; j++){
                        that._displayAdvOption(selected_nodes[j]['data']['code'], $(selected_nodes[j]['li']).is(':visible'), selected_nodes[j]['li']);
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
    
   /**
     * Get the content of a specified HTML template.
     *
     * @param {string} templateName The 'id' of the template HTML element.
     * @param {Object} variables The passed-in variables for the template. The variable
     *   placeholders in the template will be replaced to the value of the same
     *   property name.
     * @return {string}
     * @private
     */
    _getTemplateContent: function (templateName, variables) {
        var content = this.element.find('#' + templateName).html();
        if (typeof variables === 'object' && variables !== null) {
            for (var name in variables) {
                if (variables.hasOwnProperty(name)) {
                    content = content.replaceAll('{' + name + '}', variables[name]);
                }
            }
        }
        return content;
    },

    /**
     * Reset the advanced pane to its initial state.
     * @private
     */
    _resetAdvancedControls: function () {
        this.element.find('.export-advanced-list').html('');
        this.element.find('.export-advanced-list').hide();
    },

    /**
     * Populate the options for the total select control.
     *
     * @param {Object} totalSelectElement The DOM element of the total select control.
     * @param {bool} isNumeric Whether the field type is numeric.
     * @private
     */
    _populateFieldAdvancedTotalSelectOptions: function (totalSelectElement, isNumeric) {
        $(totalSelectElement).html('');
        $(totalSelectElement).append('<option value="" selected>Value</option>');
        $(totalSelectElement).append('<option value="group">Group By</option>');
        $(totalSelectElement).append('<option value="count">Count</option>');
        if (isNumeric) {
            $(totalSelectElement).append('<option value="sum">Sum</option>');
        }
    },

    _displayAdvOption: function(fieldCode, showField, item){
        var $ele = this.element.find('div[data-field-code="'+ fieldCode +'"]');
        if($ele.length == 0){
            return;
        }

        if(showField){
            $ele.show();
        }else{
            $ele.hide();
        }

        if(item){
            var pos_top = item.offsetTop;
            $ele.css({
                'position': 'absolute',
                'top': pos_top+'px',
                'left': '20px'
            });
        }
    },

    /**
     * Add the advanced options for a field in the UI.
     *
     * @param {string} fieldName The field label to display.
     * @param {string} fieldType The type of the field.
     * @param {string} fieldCode The code of the field.
     * @param {Object} item The selected node
     * @private
     */
    _addFieldAdvancedOptions: function (fieldName, fieldType, fieldCode, item) {

        if(this.element.find('div[data-field-code="'+ fieldCode +'"]').length != 0){
            this._displayAdvOption(fieldCode, true, item);
            return;
        }

        var content = this._getTemplateContent('templateAdvancedFieldOptions', {
            "fieldName": fieldName,
            "fieldType": fieldType,
            "fieldCode": fieldCode
        });
        var fieldElement = $(content);
        this.element.find('.export-advanced-list').append(fieldElement);
        this._populateFieldAdvancedTotalSelectOptions(fieldElement.find('.export-advanced-list-item-total-select')[0], fieldType === 'float');
        this.element.find('.export-advanced-list-item-total-select').on('change', function () {
            var itemElement = $(this).closest('.export-advanced-list-item');
            itemElement.find('.export-advanced-list-item-percentage-checkbox').prop('checked', false);
            if ($(this).val() === 'count' || $(this).val() === 'sum') {
                itemElement.find('.export-advanced-list-item-percentage').show();
            } else {
                itemElement.find('.export-advanced-list-item-percentage').hide();
            }
        });

        var pos_top = item.offsetTop;
        fieldElement.css({
            'position': 'absolute',
            'top': pos_top+'px',
            'left': '20px'
        });

        this._on(fieldElement.find('.ui-icon'), {
            'click': function(event){
                var val = null;
                if($(event.target).hasClass('ui-icon-circle-b-arrow-n')){
                    val = 'asc';
                }else if($(event.target).hasClass('ui-icon-circle-b-arrow-s')){
                    val = 'desc';
                }else if($(event.target).hasClass('ui-icon-minus')){
                    val = '';
                }
                if(val || val == ''){
                    this._handleSortByField($(event.target).parent().attr('data-code'), false, val);
                }
            }
        });
    },

    /**
     * Remove the options for a field in the UI by field code.
     *
     * @param {string} fieldCode The code of the field.
     * @private
     */
    _removeFieldAdvancedOptionsByCode: function (fieldCode) {
        this.element.find('.export-advanced-list-item').each(function () {
            if ($(this).data('field-code') === fieldCode) {
                $(this).remove();
            }
        });
    },

    _handleSortByField: function(code, isGet, new_value=''){

        var iconSet = $('div[data-code="'+code+'"]');
        if(iconSet.length == 0){
            return null;
        }

        var value = iconSet.attr('data-value');

        if(!value && value != ''){

            if(iconSet.find('span.ui-icon-circle-arrow-n').length == 1){
                value = 'asc';
            }else if(iconSet.find('span.ui-icon-circle-arrow-s').length == 1){
                value = 'desc';
            }else{
                value = '';
            }
            iconSet.attr('data-value', value);
        }

        if(isGet){
            return value;
        }

        iconSet.attr('data-value', new_value);
        new_value = !new_value || new_value == '' || (new_value != 'asc' && new_value != 'desc') ? '' : new_value;

        if(value == new_value){
            return value;
        }

        if(value == 'asc'){
            iconSet.find('span.ui-icon-circle-arrow-n').removeClass('ui-icon-circle-arrow-n').addClass('ui-icon-circle-b-arrow-n');
        }else if(value == 'desc'){
            iconSet.find('span.ui-icon-circle-arrow-s').removeClass('ui-icon-circle-arrow-s').addClass('ui-icon-circle-b-arrow-s');
        }else{
            iconSet.find('span.ui-icon-minusthick').removeClass('ui-icon-minusthick').addClass('ui-icon-minus');
        }

        if(new_value == 'asc'){
            iconSet.find('span.ui-icon-circle-b-arrow-n').removeClass('ui-icon-circle-b-arrow-n').addClass('ui-icon-circle-arrow-n');
        }else if(new_value == 'desc'){
            iconSet.find('span.ui-icon-circle-b-arrow-s').removeClass('ui-icon-circle-b-arrow-s').addClass('ui-icon-circle-arrow-s');
        }else{
            iconSet.find('span.ui-icon-minus').removeClass('ui-icon-minus').addClass('ui-icon-minusthick');
        }

        return new_value;
    },

    /**
     * Get the advanced options from the UI controls.
     *
     * @return {Object} The object is keyed by the field code. Each element is an object which
     *   contains the following possible keys:
     *   - total: The total functions applied to the field: 'group', 'sum' or 'count'.
     *   - sort: The sorting option for the field: 'asc' or 'des'.
     *   - use_percentage: boolean value when the total is 'sum' or 'count'.
     * @private
     */
    _getFieldAdvancedOptions: function () {
        var that = this;
        if (this.element.find('.export-advanced-list-item').length > 0) {
            var options = {};
            this.element.find('.export-advanced-list-item').each(function () {
                var option = {};
                var totalSelectValue = $(this).find('.export-advanced-list-item-total-select').val();
                if (totalSelectValue) {
                    option.total = totalSelectValue;
                }
                var sortSelectValue = that._handleSortByField($(this).attr('data-field-code'), true);
                if (sortSelectValue) {
                    option.sort = sortSelectValue;
                }
                if (totalSelectValue === 'sum' || totalSelectValue === 'count') {
                    option.use_percentage = $(this).find('.export-advanced-list-item-percentage-checkbox').prop('checked');
                }
                options[$(this).data('field-code')] = option;
            });
            return options;
        }
        return null;
    },

    /**
     * Set the advanced option UI controls by the options object.
     *
     * @param {Object} options
     * @private
     */
    _setFieldAdvancedOptions: function (options) {
        var that = this;
        if (options) {
            this.element.find('.export-advanced-list-item').each(function () {
                var fieldCode = $(this).data('field-code');
                var option;
                if (options.hasOwnProperty(fieldCode)) {
                    option = options[fieldCode];
                    if (option.hasOwnProperty('total')) {
                        $(this).find('.export-advanced-list-item-total-select').val(option.total);
                        if (option.total === 'sum' || option.total === 'count') {
                            $(this).find('.export-advanced-list-item-percentage').show();
                        }
                    }
                    if (option.hasOwnProperty('sort')) {
                        that._handleSortByField($(this).attr('data-code'), false, option.sort);
                    }
                    if (option.hasOwnProperty('use_percentage') && option.use_percentage) {
                        $(this).find('.export-advanced-list-item-percentage-checkbox').prop('checked', true);
                    }
                }
            });
        }
    }

});