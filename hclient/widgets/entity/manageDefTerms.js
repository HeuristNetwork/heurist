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


$.widget( "heurist.manageDefTerms", $.heurist.manageEntity, {
   
    _entityName:'defTerms',
    
    _treeview:null,
    _currentDomain:null,
    _currentParentID:null,
    
    _init: function() {
        this.options.layout_mode = 'short';
        this.options.use_cache = true;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = 390;                    
            this.options.edit_mode = 'none'
        }
    
        this._super();
        
        //if(this.options.edit_mode=='inline'){
        if(this.options.select_mode!='manager'){
            //hide form 
            this.editForm.parent().hide();
            this.recordList.parent().css('width','100%');
        }
        
    },
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        this.options.list_mode = 'treeview';
        
        if(!this._super()){
            return false;
        }

        // init search header
        this.searchForm.searchDefTerms(this.options);
        
        var iheight = 2;
        //if(this.searchForm.width()<200){  - width does not work here  
        if(this.options.select_mode=='manager'){            
            iheight = iheight + 2;
        }
        if(top.HEURIST4.util.isempty(this.options.filter_groups)){
            iheight = iheight + 2;    
        }
        this.searchForm.css({'height':iheight+'em'});
        this.recordList.css({'top':iheight+0.4+'em'});

        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }

        
        this._on( this.searchForm, {
                "searchdeftermsonresult": this.updateRecordList
                });
        this._on( this.searchForm, {
                "searchdeftermsonfilter": this.filterRecordList
                });
                
        if(this.options.list_mode=='default'){
            this.recordList.resultList('hideHeader',true);
        }
        
       //---------    EDITOR PANEL - DEFINE ACTION BUTTONS
       //if actions allowed - add div for edit form - it may be shown as right-hand panel or in modal popup
       if(this.options.edit_mode!='none'){

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
       }
        
       return true;
    },
    
    //
    //
    //
    updateRecordList: function( event, data ){
        
        this._super(event, data);
        
        if (this.options.list_mode=='treeview' && this._cachedRecordset && this.options.use_cache){
            //prepare treeview data (@todo keep in cache on client side)
            var treeData = this._cachedRecordset.getTreeViewData('trm_Label','trm_ParentTermID');
            this._initTreeView( treeData );
        }
    },
    
    
    _keepRequest:null,
    //
    //
    //
    filterRecordList: function(event, request){
        //this._super();
        
        if(this._cachedRecordset && this.options.use_cache){
            this._keepRequest = request;
            var subset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
            this._filterTreeView( subset );
            //show/hide items in list according to subset
            //this.recordList.resultList('updateResultSet', subset, request);   
        }
    },
    
    refreshRecordList:function(){
        this.filterRecordList(null, this._keepRequest);
    },
    
    //----------------------
    //
    //
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return recordset.fld(record, fldname);
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!top.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+top.HEURIST4.util.htmlEscape(fld(fldname))+'</div>';
        }
        
        var recID   = fld('trm_ID');
        var recTitle = fld2('trm_ID','4em')+fld2('trm_Label');
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="height:1.3em">';
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div><div class="recordTitle">';
        }else{
            html = html + '<div>';
        }
        
        html = html + fld2('trm_Label') + '</div>';
        
        if(this.options.edit_mode=='popup'){
            html = html
            + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil'}, null,'icon_text')
            + this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'}, null,'icon_text');
        }
        return html+'</div>';
        
        
    },
    
    
    //----------------------
    //
    // listener of action button/menu clicks - central listener for action events
    //
    _onActionListener:function(event, action){
        
        var isresolved = this._super(event, action);   //action is already defined in parent's method
        
        //keep paret id and domain 
        this._currentDomain = this.searchForm.searchDefTerms('currentDomain');
        this._currentParentID = null;
                
        if(isresolved) return;                        
        
        if(action && action.action){
             recID =  action.recID;
             action = action.action;
        }
                
        if(action=='add-child'){
            if(this._currentEditID>0){
                this._currentParentID = this._currentEditID;
                this._addEditRecord(-1);
            }else{
                top.HEURIST4.msg.showMsgFlash('Save current term before add a child term');
            }
        }
        
    },
    
    //  -----------------------------------------------------
    //
    // 1. returns values from edit form
    // 2. performs special action for virtual and hidden fields 
    // fill them with constructed and/or predefined values
    // EXTEND this method to set values for hidden fields (for example parent term_id or group/domain)
    //
    _getValidatedValues: function(){
        
        //fieldvalues - is object {'xyz_Field':'value','xyz_Field2':['val1','val2','val3]}
        var fieldvalues = this._super();
/* to implement        
        if(fieldvalues!=null){
            var data_type =  fieldvalues['dty_Type'];
            if(data_type=='freetext' || data_type=='blocktext' || data_type=='date'){
                var val = fieldvalues['dty_Type_'+data_type];
                
                fieldvalues['dty_JsonTermIDTree'] = val;
                delete fieldvalues['dty_Type_'+data_type];
            }
        } 
*/        

        if(fieldvalues && this._currentEditID<0){ //for new term assign domain and parent term id
            fieldvalues['trm_Domain'] = this._currentDomain;
            fieldvalues['trm_ParentTermID'] = this._currentParentID;
        }

        return fieldvalues;
        
    },
    
    //
    //
    //
    _afterSaveEvenHandler: function( recID, fieldvalues ){
        
        var isNewRecord = (this._currentEditID<0);
    
        this._super();
        
        var tree = this._treeview.fancytree("getTree");        
        //refresh treeview
        if(!isNewRecord){//rename
                node = tree.getNodeByKey( fieldvalues['trm_ID'] );
                node.setTitle( fieldvalues['trm_Label'] );
                node.render(true);            
        }else{
            var node, //new node
                parentNode; 
                
            var isVocab = fieldvalues['trm_ParentTermID']==null;
            
            //reload edit page
            
            if(isVocab){
                //add new vocabulary - add to root
                parentNode = tree.rootNode;
            }else { //add new child term
                parentNode = tree.getNodeByKey( fieldvalues['trm_ParentTermID'] );
            }
            parentNode.folder = true;
            node = parentNode.addChildren( { title:fieldvalues['trm_Label'], key: recID}); //addNode({}, "child" );
            
            this.refreshRecordList();
            
            if(isVocab){
                node.setActive();
            }else{
                //select parent node
                parentNode.setExpanded();
                parentNode.setActive();
                //reload parent 
                this._addEditRecord( fieldvalues['trm_ParentTermID'] );
            }
        }
        
        
    },
    
    _afterDeleteEvenHandler: function( recID ){
        this._super();
        this.refreshRecordList();
    },
    
    //-----
    //  perform some after load modifications (show/hide fields,tabs )
    //
    _afterInitEditForm: function(){
        this._super();
        
        var domain = this.searchForm.searchDefTerms('currentDomain');
        var ele = this._editing.getFieldByName('trm_InverseTermId');
        if(domain == 'relation'){
            ele.show();
        }else{
            // hide inversion 
            ele.hide();
        }
        
    },
    
//----------------------------------------------------------------------------------    
    
    _initTreeView: function( treeData ){
        
        var that = this;
        
        var fancytree_options =
        {
            checkbox: false,
            //titlesTabbable: false,     // Add all node titles to TAB chain
            focusOnSelect:true,
            source: treeData,
            quicksearch: true,

            activate: function(event, data) {
                
                that.selectedRecords([data.node.key]);
                
                if (!that.options.edit_dialog){
                        that._onActionListener(event, {action:'edit'}); //default action of selection
                }
                
                //console.log('click on '+data.node.key+' '+data.node.title);
            }
        };

        fancytree_options['extensions'] = ["dnd", "filter"]; //"edit", "dnd", "filter"];
        fancytree_options['dnd'] = {
                preventVoidMoves: true,
                preventRecursiveMoves: true,
                autoExpandMS: 400,
                dragStart: function(node, data) {
                    return true;
                },
                dragEnter: function(node, data) {
                    //return true;
                    // return ["before", "after"];
                    if(node.tree._id == data.otherNode.tree._id){
                        return node.folder ?true :["before", "after"];
                    }else{
                        return false;
                    }


                },
                dragDrop: function(node, data) {
                    /*that._avoidConflictForGroup(groupID, function(){
                        data.otherNode.moveTo(node, data.hitMode);
                        that._saveTreeData( groupID );
                    });*/
                }
            };
            /* fancytree_options['edit'] = {
                save:function(event, data){
                    if(''!=data.input.val()){
                        that._avoidConflictForGroup(groupID, function(){
                            that._saveTreeData( groupID );
                        });
                    }
                }
            };     */
            fancytree_options['filter'] = { highlight:false, mode: "hide" };  

            this._treeview = this.recordList.fancytree(fancytree_options);
        
    },
                 
    _filterTreeView: function( recordset ){
        
        if(this._treeview){
            var wtree = this._treeview.fancytree("getTree");
            
            wtree.filterNodes(function(node){
                return !top.HEURIST4.util.isnull(recordset.getById(node.key));
            }, true);
        }

    }

});

//
// Show as dialog - to remove
//
function showManageDefTerms( options ){

    var manage_dlg = $('#heurist-records-dialog');  //@todo - unique ID

    if(manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog">')
                .appendTo( $('body') )
                .manageDefTerms( options );
    }

    manage_dlg.manageDefTerms( 'popupDialog' );
}
