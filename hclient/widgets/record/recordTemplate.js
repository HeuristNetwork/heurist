/**
* recordAccess.js - apply ownership and access rights
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

$.widget( "heurist.recordTemplate", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        title:  'Create comma separated template files',
        currentOwner: 0,
        currentAccess: null,
        currentAccessGroups: null,
        
        htmlContent: 'recordTemplate.html',
        helpContent: 'recordTemplate.html', //in context_help folder
        
        recordType: 0
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
    // 0 - download, 1 - open in new window
    //
    doAction: function(mode){

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
            
            //get selected fields from treeview
            var selectedFields = {};
            var tree = this.element.find('.rtt-tree').fancytree("getTree");
            var fieldIds = tree.getSelectedNodes(false);
            var k, len = fieldIds.length;
            
            if(len<1){
                window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
            +'Please select at least one field in tree', 2000);
                return;
            }
            
            
            for (k=0;k<len;k++){
                var node =  fieldIds[k];
                
                if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
                
                var ids = node.data.code.split(":");
                
                __addSelectedField(ids, 1, 0);
                
                //DEBUG console.log( node.data.code );
            }
            //DEBUG console.log( selectedFields );
            
            
            var request = {
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
                
            
            var url = window.hWin.HAPI4.baseURL + 'hsapi/controller/record_output.php'
            
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
    
    _initControls: function(){
        
        this._super();
        
        $('#sel_record_type').text( $Db.rty(this.options.recordType,'rty_Name') );
        
        this._loadRecordTypesTreeView();
        
        $('.rtt-tree').parent().show();
        
        var that = this;

        this.element.find('#selectAll').on("click", function(e){
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
        });
    },
    
    //
    // show treeview with record type structure as popup
    //
    _loadRecordTypesTreeView: function(rtyID){
        
        var that = this;
        
        var rtyID = this.options.recordType;

            
            //generate treedata from rectype structure
            var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, ['ID','url','tags','all'] );
            
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

                    if(data.node.parent && data.node.parent.data.type == 'resource' || data.node.parent.data.type == 'relmarker'){ // add left border+margin
                        $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                    }
                    if(data.node.data.type == 'separator'){
                        $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                        $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                    }
                },
                lazyLoad: function(event, data){
                    var node = data.node;
                    var parentcode = node.data.code; 
                    var rectypes = node.data.rt_ids;
                    
                    var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                                        rectypes, ['ID','url','tags','all'], parentcode );
                    if(res.length>1){
                        data.result = res;
                    }else{
                        data.result = res[0].children;
                    }
                    
                    return data;                                                   
                },
                select: function(e, data) {
                    var node = data.node;
                    var fieldIds = node.tree.getSelectedNodes(false);
                    var isdisabled = fieldIds.length<1;
                    window.hWin.HEURIST4.util.setDisabled( that.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );
                },
                click: function(e, data){

                    if(data.node.data.type == 'separator'){
                        return false;
                    }

                    var isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

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
           
    },
    
  
});
