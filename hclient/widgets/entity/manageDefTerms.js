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

    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        this._entityIDfield = 'trm_ID'; //@todo - take it from config

        // init search header
        this.searchRecord.searchDefTerms(this.options);
            
        this._on( this.searchRecord, {
                "searchdeftermsonresult": this.updateRecordList
                });
        this._on( this.searchRecord, {
                "searchdeftermsonfilter": this.filterRecordList
                });
        this.recordList.css('top','5.5em');
                
        this.recordList.resultList('option','hide_view_mode',true);
        
        //move editor panel from element to div_content        
        if(this.ent_editor && !this.options.edit_dialog){
            
            var list_container = this.recordList.find('.div-result-list-content');
            
            list_container.removeProp('right').css('width','250px');
            
            this.ent_editor
                .detach()
                .appendTo(list_container.parent())
                .css({'min-width':'500px','left':'250px', top:list_container.position().top})
                .show();
        }
        
        return true;
    },
    
    //
    //
    //
    updateRecordList: function( event, data ){
        
        this._super(event, data);
        
        if (this._cachedRecordset && this.options.use_cache){
            //prepare treeview data
            var treeData = this._cachedRecordset.getTreeViewData('trm_Label','trm_ParentTermID');
            this._initTreeView( treeData );
        }
    },
    
    //
    //
    //
    filterRecordList: function(event, request){
        //this._super();
        
        if(this._cachedRecordset && this.options.use_cache){
            var subset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
            this._filterTreeView( subset );
            //show/hide items in list according to subset
            //this.recordList.resultList('updateResultSet', subset, request);   
        }
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
        
        var showActionInList = (top.HEURIST4.util.isArrayNotEmpty(this.options.action_select)); 
        //&& (this.options.select_mode=='manager')
        
        var recID   = fld('trm_ID');
        
        var recTitle = fld2('trm_ID','4em')
                + fld2('trm_Label','14em');

        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="height:1.3em;">'  
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordTitle" style="left:24px">'
        +     recTitle ;
        
        //actions
        if(true || showActionInList){
              html = html
                + this._rendererActionButton('edit')
                + this._rendererActionButton('delete');
        }
        html = html + '</div>'
                + '</div>'; //close recordDiv

        return html;
        
    },
    
    //  -----------------------------------------------------
    //
    // perform special action for virtual fields 
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
        return fieldvalues;
        
    },
    
    //-----
    //  perform some after load modifications (show/hide fields,tabs )
    //
    _afterInitEditForm: function(){

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

            this._treeview = this.recordList.find('.div-result-list-content').fancytree(fancytree_options);
        
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
