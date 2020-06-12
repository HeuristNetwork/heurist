/**
* manageDefTerms.js - main widget to manage defTerms
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


$.widget( "heurist.manageDefTerms", $.heurist.manageEntity, {
   
    _entityName:'defTerms',
    
    _treeview:null,
    _thumbs:null,
    _currentDomain:null,
    _currentParentID:null,
    
    _init: function() {
        this.options.layout_mode = 'tabbed';
        this.options.use_cache = true;

        //for selection mode set some options
        if (this.options.select_mode=='manager' || this.options.select_mode=='images') {
            this.options.width = 940;                   
            this.options.height = Math.min(1200, $(this.document).find('body').innerHeight()-10);                 
        }else{
            this.options.width = 390;                    
            this.options.edit_mode = 'none'
        }
    
        this._super();
                                         
    },
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        this.options.list_mode = 'treeview';
        
        if(!this._super()){
            return false;
        }
        
        this.recordList.css('top','10px');
        this.recordList2 = this.element.find('.recordList2');
        

        if(this.options.select_mode=='manager'){
            //this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }else{
            //hide form 
            this.editForm.parent().hide();
            //
            if(this.options.select_mode=='images'){
                this.recordList.hide();
                this.recordList2.show().css({'width':'100%',left:0});
            }
        }
        
        //add two elements - treeview on recordList (left side) and thumb viewer on ent_wrapper (right side)
        /*this.treeview_Container = $('<div>')
            .css({position:'absolute', top:0,bottom:0, left:0, right:0, 'overflow-y':'auto'})
            .appendTo(this.recordList);*/
        this.treeview_Container = this.recordList.css('top','10px');
        
        /*this.thumb_Container = $('<div>')
            .css({position:'absolute', top:0,bottom:0, left:0, right:0, 'overflow-y':'auto'})
            .appendTo(this.recordList2);*/
        this.thumb_Container = this.recordList2.css('top','10px');
        
        if(this.options.select_mode=='images'){
            
            this.options.select_mode = 'select_single';
            
            this.searchForm.css({'height':0});
            this.recordList.parent().css({'top':0});

            
            this._initTreeView(null);
            //search for given list of ids
            
            if(this.options.recordset){
                this._filterTreeView(this.options.recordset);
            }else if(this.options.initialTermsIds){
                this.searchTermsWithImages(this.options.initialTermsIds);    
            }
            
        
        }else{
            var iheight = 2;
            //if(this.searchForm.width()<200){  - width does not work here  
            if(this.options.select_mode=='manager'){            
                //iheight = iheight + 2;
            }
            if(window.hWin.HEURIST4.util.isempty(this.options.filter_groups)){
                iheight = iheight + 2;    
            }
            
            this.searchForm.css({'height':iheight+'em'});
            this.recordList.parent().css({'top':iheight+'em'});
            //this.recordList.css({'top':iheight+0.4+'em'});
            //this.recordList2.css({'top':iheight+0.4+'em'});
            if(this.options.edit_mode=='inline'){
                this.editForm.parent().css({'top':'10px'});
            }
            // init search header
            this.searchForm.searchDefTerms(this.options);
            
            this._on( this.searchForm, {
                    "searchdeftermsonresult": this.updateRecordList,          //called once
                    "searchdeftermsonfilter": this.filterRecordList,
                    "searchdeftermsonviewmode": function(){
                        
                        var mode = this.searchForm.find('#sel_viewmode').tabs('option','active');
                        if(mode==0){
                            this.recordList2.hide();
                        }else{
                            this.recordList2.show();
                        }
                    }
                    });
                
        }
                
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
    
    //
    // non standard way - search and show only specific terms
    //
    searchTermsWithImages: function(term_IDs){
        
        var request = {};
        request['a']          = 'search'; //action
        request['entity']     = 'DefTerms';//this.options.entity.entityName;
        request['details']    = 'list'; //'id';
        request['request_id'] = window.hWin.HEURIST4.util.random();
        request['trm_ID'] = term_IDs;
        request['withimages'] = 1;
        
        //request['DBGSESSID'] = '423997564615200001;d=1,p=0,c=0';

        var that = this;                                                
        
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    var recset = new hRecordSet(response.data);
                    if(recset.length()>0){                                                                      
                        that._filterTreeView(recset);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgFlash('No terms images defined');
                        that.closeDialog();
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });

    },
    
    //
    // listener of onresult event generated by searchEtity
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
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(fld(fldname))+'</div>';
        }
        
        var recID   = fld('trm_ID');
        var recTitle = fld('trm_Code')+' '+fld('trm_Label');
        var recTitleHint = fld('trm_Description');
        
        
        //var curtimestamp = (new Date()).getMilliseconds();
        var thumb_url = window.hWin.HAPI4.iconBaseURL + recID + '&ent=term&editmode=0';//"&t=" + curtimestamp

        var html_thumb = '<div class="recTypeThumb realThumb" style="background-image: url(&quot;'
            + thumb_url + '&quot;);opacity:1"></div>';


        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        /*+ '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+recIcon+'&quot;);">'
        + '</div>'*/
        + '<div class="recordTitle" title="'+recTitleHint+'">'
        +     recTitle
        + '</div>';
        
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
/*            
            html = html 
                + '<div title="Click to edit term" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>&nbsp;&nbsp;';
           html = html      
                + '<div title="Click to delete term" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div>';
*/           
           html = html + '<div class="rec_actions">' 
            + this._defineActionButton({key:'add-child',
                label:'Add a child term (a term hierarchichally below the current vocabulary or term)', title:'', icon:'ui-icon-plus'},
                 null, 'icon_text')
            + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil'}, null, 'icon_text')
            + this._defineActionButton({key:'remove',label:'Delete this term (if unused in database)', title:'', icon:'ui-icon-close'}, null, 'icon_text')
            + this._defineActionButton({key:'add-import',label:'IMPORT a comma-delimited list of terms (and optional codes and labels) as children of this term', title:'', icon:'ui-icon-arrowthick-1-w'}, null, 'icon_text')
            + this._defineActionButton({key:'export',label:'EXPORT this vocabulary to a text file', title:'', icon:'ui-icon-arrowthick-1-e'}, null, 'icon_text')
            + '</div>';
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
                
        if(this._currentEditID>0){
            
            this._currentParentID = this._currentEditID;
            
            if(action=='add-child'){
                this.addEditRecord(-1);
            }else if(action=='add-import'){
                
                var that = this;
                
                //open import dialog
                window.hWin.HEURIST4.msg.showDialog(
                    window.hWin.HAPI4.baseURL + 'hclient/framecontent/importDefTerms.php?db='
                            +window.hWin.HAPI4.database
                            +'&trm_ID='+this._currentParentID,
                  {
                     title: 'Import children from structured data (CSV)',
                     callback: function(context){that._onImportComplete(context);},
                     width: '800px',
                     height: '460px'
                  }          
                );
                
            }else if(action=='merge'){ //@todo
                /*
                var url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/recordAction.php?db='
                        +window.hWin.HAPI4.database+'&action=merge_term&value='+encodeURIComponent();

                window.hWin.HEURIST4.msg.showDialog(url, {height:450, width:700,
                    padding: '0px',
                    title: window.hWin.HR(action_type),
                    class:'ui-heurist-bg-light'} );
               */ 
            }
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('Save current term before adding a child term');
        }
        
    },
    
    /**
    * Add the list of imported terms
    * context - {result:recIDs, parent:trm_ParentTermID, terms:response.data.terms }
    */
    _onImportComplete: function (context){
        
        if(!window.hWin.HEURIST4.util.isnull(context) && !window.hWin.HEURIST4.util.isnull(context.terms))
        {
            var tree = this._treeview.fancytree("getTree");        
            
            window.hWin.HEURIST4.terms = context.terms;
            var res = context.result,
            ind,
            parentNode, node,
            fi = window.hWin.HEURIST4.terms.fieldNamesToIndex;

            var isVocab = !(context.parent>0);
            if(isVocab){ //add to root
                parentNode = tree.rootNode;
            }else { //add new children
                parentNode = tree.getNodeByKey( context.parent );
            }
            
            if(res.length>0 && parentNode){

                for (ind in res)
                {
                    if(!Hul.isnull(ind)){
                        var termid = Number(res[ind]);
                        if(!isNaN(termid))
                        {
                            var arTerm = window.hWin.HEURIST4.terms.termsByDomainLookup[this._currentDomain][termid];
                            if(!Hul.isnull(arTerm)){
                                node = parentNode.addChildren( { title:arTerm[fi.trm_Label], key:termid});    
                            }
                        }
                    }
                }//for
                
                this.refreshRecordList();
                
                if(isVocab){ //activate last node
                    if(node) node.setActive();
                }else{//select parent node
                    parentNode.setExpanded();
                    parentNode.setActive();
                }
                
            }//if length>0
        }
    },
    
    //-----------------------------------------------------
    //
    //  change parent
    //
    _changeParentTerm: function( termID, newParentTermID, callback ){

            var fields = {'trm_ID':termID, 'trm_ParentTermID':newParentTermID};
        
            var request = {
                'a'          : 'save',
                'entity'     : this.options.entity.entityName,
                'fields'     : fields                     
                };
                
                var that = this;                                                
                //that.loadanimation(true);
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            if(callback && $.isFunction(callback)){
                                callback();                                
                            }
                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Parent has been changed'));
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
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
    _afterSaveEventHandler: function( recID, fieldvalues ){
        
        var isNewRecord = (this._currentEditID<0);
    
        this._super();
        
        var tree = this._treeview.fancytree("getTree");        
        //refresh treeview
        if(!isNewRecord){//rename
                node = tree.getNodeByKey( fieldvalues['trm_ID'] );
                node.setTitle( fieldvalues['trm_Label'] );
                //node.render(true);            
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
                //reload parent in edit form 
                this.addEditRecord( fieldvalues['trm_ParentTermID'] );
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
            
            expand: function(event, data){
                //show thumbs for expanded node - otherwise for root
                //data.node.children
                if(data.node.children && data.node.children.length>0){
                    var rec_ids = [];
                    for (var idx in data.node.children){
                        if(idx>=0){
                            var node = data.node.children[idx];
                            rec_ids.push(node.key);
                        }
                    }

                   setTimeout(function(){
                    $.each( $('.fancytree-node'), function( idx, item ){
                        that.__defineActionIcons(item);
                    });
                    }, 500);  
                    
                    var subset = that._cachedRecordset.getSubSetByIds(rec_ids);
                    that._thumbs.resultList('applyViewMode', 'thumbs3'); 
                    that._thumbs.resultList('updateResultSet', subset);  
                }
            },

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
                    if(data && data.otherNode && node.tree._id == data.otherNode.tree._id)
                    {
                        
                        //recordset.getById(node.key)
                        //recordset.getById(node.otherNode)
                        //node.key 
                        
                        return ['over'];//true; //node.folder ?true :["before", "after"];
                    }else{
                        return false;
                    }


                },
                dragDrop: function(node, data) {
                    
                    that._changeParentTerm(node.key, data.otherNode.key, function(){
                        data.otherNode.moveTo(node, data.hitMode);    
                    });
                    //that._saveTreeData( groupID );
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

            this._treeview = this.treeview_Container.fancytree(fancytree_options); //was recordList

            
       setTimeout(function(){
            //var tree = $treediv.fancytree("getTree");
            //tree.getRootNode().sortChildren(null, true); //true - deep sort
            $.each( $('.fancytree-node'), function( idx, item ){
                that.__defineActionIcons(item);
            });
        }, 500);  

            
            
            this._thumbs = this.thumb_Container.resultList(
                      {
                       eventbased: false, //do not listent global events
                       multiselect: (this.options.select_mode!='select_single'), //@todo replace to select_mode

                       select_mode: this.options.select_mode,
                       pagesize:999,
                       show_toolbar: false,
                       /*
                       selectbutton_label: this.options.selectbutton_label,
                       
                       //view_mode: this.options.view_mode?this.options.view_mode:null,
                       empty_remark: 
                            (this.options.select_mode!='manager')
                            ?'<div style="padding:1em 0 1em 0">'+this.options.entity.empty_remark+'</div>'
                            :'',
                       searchfull: function(arr_ids, pageno, callback){
                           that._recordListGetFullData(arr_ids, pageno, callback);
                       },//this._recordListGetFullData,    //search function 
                       */
                       renderer: function(recordset, record){ 
                                return that._recordListItemRenderer(recordset, record);  //custom render for particular entity type
                            },
                       rendererHeader: this.options.list_header ?function(){
                                return that._recordListHeaderRenderer();  //custom header for list mode (table header)
                                }:null
                               
                       });     
                   
            this._on( this._thumbs, {
                    "resultlistonselect": function(event, selected_recs){
                                this.selectedRecords(selected_recs);
                            },
                    "resultlistonaction": this._onActionListener        
                    });
                    
            
        
    },
                 
    _filterTreeView: function( recordset ){
        
        if(this._treeview){
            var wtree = this._treeview.fancytree("getTree");
            
            wtree.filterNodes(function(node){
                return !window.hWin.HEURIST4.util.isnull(recordset.getById(node.key));
            }, true);
        }
        if(this._thumbs){
            this._thumbs.resultList('applyViewMode', 'thumbs3');
            this._thumbs.resultList('updateResultSet', recordset);               
        }

    },
    
    //
    // for treeview on mouse over toolbar
    //
    __defineActionIcons: function(item){ 
           if($(item).find('.svs-contextmenu3').length==0){
               
               if(!$(item).hasClass('fancytree-hide')){
                    $(item).css('display','block');   
               }

               var actionspan = $('<div class="svs-contextmenu3" style="position:absolute;right:4px;display:none;padding-top:2px">'
                   +'<span class="ui-icon ui-icon-plus" title="Add a child term (a term hierarchichally below the current vocabulary or term)"></span>'
                   +((this._currentDomain=="relation")
                      ?'<span class="ui-icon ui-icon-reload" title="Set the inverse term for this term"></span>' //for relations only
                      :'')
                   +'<span class="ui-icon ui-icon-close" title="Delete this term (if unused in database)"></span>'
                   //+'<span class="ui-icon ui-icon-image" title="Add an image which illustrates this term"></span>'
                   +'<span class="ui-icon ui-icon-arrowthick-1-w" title="IMPORT a comma-delimited list of terms (and optional codes and labels) as children of this term"></span>'
                   +'<span class="ui-icon ui-icon-arrowthick-1-e" title="EXPORT this vocabulary to a text file"></span>'
                   +'</div>').appendTo(item);
                   
                   
               actionspan.find('.ui-icon').click(function(event){
                    var ele = $(event.target);
                    
                    //timeour need to activate current node    
                    setTimeout(function(){                         
                        if(ele.hasClass('ui-icon-plus')){
                            //td _doAddChild(false);
                        }else if(ele.hasClass('ui-icon-reload')){
                             $("#btnInverseSetClear").click();
                             //_setOrclearInverseTermId();
                        }else if(ele.hasClass('ui-icon-close')){
                            $("#btnDelete").click();
                            //_doDelete();
                        }else if(ele.hasClass('ui-icon-arrowthick-1-w')){
                            //td_import(false)
                        }else if(ele.hasClass('ui-icon-arrowthick-1-e')){
                            //td_export(false);
                        }else if(ele.hasClass('ui-icon-image')){
                            //td that.showFileUploader();
                        }
                    },500);
                    //window.hWin.HEURIST4.util.stopEvent(event); 
                    //return false;
               });
               /*
               $('<span class="ui-icon ui-icon-pencil"></span>')                                                                
               .click(function(event){ 
               //tree.contextmenu("open", $(event.target) ); 
               
               ).appendTo(actionspan);
               */

               //hide icons on mouse exit
               function _onmouseexit(event){
                       var node;
                       if($(event.target).is('li')){
                          node = $(event.target).find('.fancytree-node');
                       }else if($(event.target).hasClass('fancytree-node')){
                          node =  $(event.target);
                       }else{
                          //hide icon for parent 
                          node = $(event.target).parents('.fancytree-node');
                          if(node) node = $(node[0]);
                       }
                       var ele = node.find('.svs-contextmenu3'); //$(event.target).children('.svs-contextmenu3');
                       ele.hide();//css('visibility','hidden');
               }               
               
               $(item).hover(
                   function(event){
                       var node;
                       if($(event.target).hasClass('fancytree-node')){
                          node =  $(event.target);
                       }else{
                          node = $(event.target).parents('.fancytree-node');
                       }
                       var ele = $(node).find('.svs-contextmenu3');
                       ele.css('display','inline-block');//.css('visibility','visible');
                   }
               );               
               $(item).mouseleave(
                   _onmouseexit
               );
           }
    },
    
    

});
