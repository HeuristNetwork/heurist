/**
* @file recordTemplate.js
* @brief Create comma separated template files (CSV headers) for a specified record type.
* @fileOverview This file defines the `recordTemplate` widget. It allows users to select fields
* from a specific record type's structure using a tree view. Based on the selected fields, the
* widget generates and initiates a download for a CSV file containing only the header row. This
* template file can then be used as a basis for preparing data for CSV import into Heurist.
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @widget heurist.recordTemplate
 * @extends $.heurist.recordAction
 * @description jQuery widget for creating downloadable CSV template files (header row only) for a specific record type.
 * Users select fields from the record type's structure via a Fancytree, and the widget generates
 * a CSV header row for these fields, which can then be downloaded.
 *
 * @param {object} options - Configuration options for the widget.
 * @param {number} [options.height=520] - The height of the dialog.
 * @param {number} [options.width=800] - The width of the dialog.
 * @param {boolean} [options.modal=true] - Whether the dialog is modal.
 * @param {string} [options.title='Create comma separated template files'] - Title for the dialog.
 * @param {string} [options.htmlContent='recordTemplate.html'] - The HTML file for the widget's content.
 * @param {number} [options.recordType=0] - The ID of the record type for which to generate the template.
 * @param {any} [options.currentOwner=0] - Inherited, likely unused.
 * @param {any} [options.currentAccess=null] - Inherited, likely unused.
 * @param {any} [options.currentAccessGroups=null] - Inherited, likely unused.
 */
$.widget( "heurist.recordTemplate", $.heurist.recordAction, {

    /**
     * @namespace options
     * @memberof heurist.recordTemplate
     * @type {object}
     * @property {number} [height=520] - Dialog height.
     * @property {number} [width=800] - Dialog width.
     * @property {boolean} [modal=true] - Is dialog modal.
     * @property {string} [title='Create comma separated template files'] - Dialog title.
     * @property {string} [htmlContent='recordTemplate.html'] - HTML content file.
     * @property {number} [recordType=0] - The ID of the target record type.
     * @property {any} [currentOwner=0] - Inherited, likely unused.
     * @property {any} [currentAccess=null] - Inherited, likely unused.
     * @property {any} [currentAccessGroups=null] - Inherited, likely unused.
     */
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        title:  'Create comma separated template files',
        currentOwner: 0,
        currentAccess: null,
        currentAccessGroups: null,
        
        htmlContent: 'recordTemplate.html',
        
        recordType: 0
    },

    /**
     * @function _getActionButtons
     * @memberof heurist.recordTemplate
     * @private
     * @description Gets action buttons for the dialog, setting labels to 'Download' and 'Close'.
     * @returns {Array<object>} Array of button definition objects.
     */
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Download');
        res[0].text = window.hWin.HR('Close');
        return res;
    },    
        
    /**
     * @function doAction
     * @memberof heurist.recordTemplate
     * @private
     * @description Performs the action of generating and downloading the CSV template file.
     * It retrieves the selected fields from the Fancytree, constructs a request object
     * with CSV preferences set for header-only output, and submits this request to the
     * server-side `record_output.php` controller via a hidden form to trigger the download.
     * @param {any} [mode] - (Unused in this implementation).
     */
    doAction: function(mode){

            let header_fields = {ids:'rec_ID',title:'rec_Title',url:'rec_URL',modified:'rec_Modified',tag:'rec_Tags'};
            function __removeLinkType(dtid){
                if(header_fields[dtid]){
                    dtid = header_fields[dtid];
                }else{
                    let linktype = dtid.substr(0,2); //remove link type lt ot rt  10:lt34
                    if(isNaN(Number(linktype))){
                        dtid = dtid.substr(2);
                    }
                }
                return dtid;
            }
            function __addSelectedField(ids, lvl, constr_rt_id){
                
                if(ids.length < lvl) return;
                
                //take last two - these are rt:dt
                let rtid = ids[ids.length-lvl-1];
                let dtid = __removeLinkType(ids[ids.length-lvl]);
                
                if(!selectedFields[rtid]){
                    selectedFields[rtid] = [];    
                }
                if(constr_rt_id>0){
                    dtid = dtid+':'+constr_rt_id;
                }
                
                //window.hWin.HEURIST4.util.findArrayIndex( dtid, selectedFields[rtid] )<0
                if( selectedFields[rtid].indexOf( dtid )<0 ) {
                    
                    selectedFields[rtid].push(dtid);    
                    
                    //add resource (record pointer) field for parent recordtype
                    __addSelectedField(ids, lvl+2, rtid);
                }
            }
            
            //get selected fields from treeview
            let selectedFields = {};
            let tree = $.ui.fancytree.getTree( this._$('.rtt-tree') );
            let fieldIds = tree.getSelectedNodes(false);
            const len = fieldIds.length;
            
            if(len<1){
                window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
            +'Please select at least one field in tree', 2000);
                return;
            }
            
            
            for (let k=0;k<len;k++){
                let node =  fieldIds[k];
                
                if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
                
                let ids = node.data.code.split(":");
                
                __addSelectedField(ids, 1, 0);
            }
            let request = {
                'request_id' : window.hWin.HEURIST4.util.random(),
                'rec_RecTypeID': this.options.recordType,
                'db': window.hWin.HAPI4.database,
                'q'  : 't:'+this.options.recordType,
                'format': 'csv',
                'prefs':{
                'fields': selectedFields,
                'csv_delimiter':  ',',//'\t',
                'csv_enclosure':  '"',
                'csv_mvsep':'|',
                'csv_linebreak':'nix', //not used at tne moment
                'csv_header': true,
                'csv_headeronly': true,
                'include_term_ids': 0,
                'include_term_codes': 0,
                'include_term_hierarchy': 0,
                'include_resource_titles': 0
                }};
                
            
            let url = window.hWin.HAPI4.baseURL + 'hserv/controller/record_output.php'
            
            this._$('#postdata').val( JSON.stringify(request) );
            this._$('#postform').attr('action', url);
            this._$('#postform').trigger('submit');
    },
    
    /**
     * @function _initControls
     * @memberof heurist.recordTemplate
     * @private
     * @description Initializes controls after HTML content is loaded.
     * Displays the name of the target record type (`options.recordType`).
     * Loads the Fancytree for field selection using `_loadRecordTypesTreeView`.
     * Sets up a "Select All" checkbox functionality for the tree.
     * Calls the parent widget's `_initControls` method.
     * @returns {boolean} True.
     */
    _initControls: function(){
        
        this._super();
        
        $('#sel_record_type').text( $Db.rty(this.options.recordType,'rty_Name') );
        
        this._loadRecordTypesTreeView();
        
        $('.rtt-tree').parent().show();
        
        let that = this;

        this._$('#selectAll').on("click", function(e){
            let treediv = that.element.find('.rtt-tree');

            let check_status = $(e.target).is(":checked");

            if(!treediv.is(':empty') && treediv.fancytree("instance")){
                let tree = $.ui.fancytree.getTree(treediv);
                tree.visit(function(node){
                    if(!node.hasChildren() && node.type != "relmarker" && node.type != "resource" 
                        && (node.getLevel()==2 || (!window.hWin.HEURIST4.util.isempty(node.span) && $(node.span.parentNode.parentNode).is(":visible")))
                    ){    
                        node.setSelected(check_status);
                    }
                });
            }
        });
        
        return true;
    },
    
    /**
     * @function _loadRecordTypesTreeView
     * @memberof heurist.recordTemplate
     * @private
     * @description Loads or reloads the Fancytree with the field structure for the `options.recordType`.
     * It generates tree data using `window.hWin.HEURIST4.dbs.createRectypeStructureTree`,
     * including fields like ID, URL, tags, and all other fields ('all').
     * Configures Fancytree options for selection, rendering, lazy loading, and event handling
     * (select, click, dblclick, keydown) to manage field selection for the template.
     * Enables/disables the download button based on whether fields are selected.
     */
    _loadRecordTypesTreeView: function(){
        
        let that = this;
        
        const rtyID = this.options.recordType;

            
            //generate treedata from rectype structure
            let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, ['ID','url','tags','all'] );
            
            treedata[0].expanded = true; //first expanded
            
            //load treeview
            let treediv = this._$('.rtt-tree');
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

                    if(data.node.parent && data.node.parent.type == 'resource' || data.node.parent.type == 'relmarker'){ // add left border+margin
                        $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                    }
                    if(data.node.type == 'separator'){
                        $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                        $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                    }
                },
                lazyLoad: function(event, data){
                    let node = data.node;
                    let parentcode = node.data.code; 
                    let rectypes = node.data.rt_ids;
                    
                    let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                                        rectypes, ['ID','url','tags','all'], parentcode );
                    if(res.length>1){
                        data.result = res;
                    }else{
                        data.result = res[0].children;
                    }
                    
                    return data;                                                   
                },
                select: function(e, data) {
                    let node = data.node;
                    let fieldIds = node.tree.getSelectedNodes(false);
                    let isdisabled = fieldIds.length<1;
                    window.hWin.HEURIST4.util.setDisabled( that.element.parents('.ui-dialog').find('.btnDoAction'), isdisabled );
                },
                click: function(e, data){

                    if(data.node.type == 'separator'){
                        return false;
                    }

                    let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                    if(isExpander){
                        return;
                    }

                    if(data.node.getLevel()<2){ //always expanded
                        data.node.setExpanded(true);
                    }else
                    if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){

                        data.node.setExpanded(!data.node.isExpanded());

                    }else if( data.node.lazy) {
                        data.node.setExpanded( true );
                    }
                },
                dblclick: function(e, data) {
                    if(data.node.type == 'separator'){
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
           
    },
    
  
});
