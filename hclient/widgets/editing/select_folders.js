/**
* Widget to manage/select folders in database upload folder
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

$.widget( "heurist.select_folders", {

    // default options
    options: {
        isdialog: true, //show in dialog or embedded

        multiselect: true, 
        
        onselect: null
        
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)

    // the constructor
    _init: function() {

        var that = this;
        
        this.element.addClass('ui-heurist-bg-light');

        $('<div class="ent_wrapper">'
                +'<div class="ent_content_full recordList" style="top:0"/>'
                +'</div>').appendTo( this.element );


        if(this.options.isdialog){
            
            var buttons = {};
            buttons[window.hWin.HR('New folder')]  = function() {
                    
                    var node = that._treeview.fancytree("getActiveNode");
                    if(node.data.issystem) return;
                    if( !node ) {
                        node = this._treeview.getRootNode();
                    }
                    node.editCreateNode("child", "new folder");                    
            }; 
            buttons[window.hWin.HR('Delete')]  = function() {
                    
                    var node = that._treeview.fancytree("getActiveNode");
console.log(node);                                       
                    if(node && !node.data.issystem){
                        if(node.countChildren()>0 || node.data.files_count>0){
                            window.hWin.HEURIST4.msg.showMsgFlash('Cannot delete non-empty folder');
                        }else{
                            
                            var path = node.getParent().getKeyPath();
                            path = (path=='/')?'':(path+'/');
                            var currname = path+node.title;

                            window.hWin.HAPI4.SystemMgr.get_sysfolders({operation:'delete', name:currname}, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    node.remove();
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            });
                        }
                    }
            }; 

            var $dlg = this.element.dialog({
                autoOpen: true,
                height: 640,
                width: 840,
                modal: true,
                title: "Select Folders",
                resizeStop: function( event, ui ) {
                    var pele = that.element.parents('div[role="dialog"]');
                    that.element.css({overflow: 'none !important', width:pele.width()-24 });
                },
                close:function(){
                    that._as_dialog.remove();    
                },
                buttons: buttons
            });
            
            this._as_dialog = $dlg; 
        }
        
        this.recordList = this.element.find('.recordList');

        /*        
            this._on( this.recordList, {        
                        "resultlistonselect": function(event, selected_recs){
                            
                                    //var recordset = that.recordList.resultList('getRecordSet');
                                    //recordset = recordset.getSubSetByIds(selected_recs);
                                    var recordset = selected_recs;
                                    var record = recordset.getFirstRecord();
                                    var filename = recordset.fld(record, 'file_name')
                                    var res = {url:recordset.fld(record, 'file_url')+filename,
                                               path:recordset.fld(record, 'file_dir')+filename};

                                    that.options.onselect.call(that, res);           
                                    if(that._as_dialog){
                                        that._as_dialog.dialog('close');
                                    }
                                }
                        });        
         */

            //search for images in given array of folder
            var that = this;                                                
       
            window.hWin.HAPI4.SystemMgr.get_sysfolders({}, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that._initTreeView( response.data );
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
     
         
         
    }, //end _create

    /* private function */
    _refresh: function(){
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.recordList.remove();
    },
    
    //
    //
    //
    _initTreeView: function( treeData ){
      
        if(!$.ui.fancytree._extensions["edit"]){
console.log('edit not enabled');
        }
        
        var that = this;
        
        var fancytree_options =
        {
            checkbox: true,
            //titlesTabbable: false,     // Add all node titles to TAB chain
            focusOnSelect:true,
            source: treeData,
            //quicksearch: true,
            //icon: true,
            selectMode: 3, // 1:single, 2:multi, 3:multi-hier
            renderNode: function(event, data) {
                    // Optionally tweak data.node.span
                    var node = data.node;
                    var $span = $(node.span).find("> span.fancytree-title");
                    if(node.data.files_count>0)
                        $span.html(node.title+' <span style="font-weight:normal">('+node.data.files_count+')</span>');
                    if(node.data.issystem){
                        $span.addClass('graytext');//css({color:'gray !important'});
                    }
            },            
            extensions: ["edit"],
            edit: {
            triggerStart: ["clickActive", "dblclick", "f2", "mac+enter", "shift+click"],
            beforeEdit: function(event, data){
                // Return false to prevent edit mode
                return !data.node.data.issystem;
            }, 
            /*edit: function(event, data){
                // Editor was opened (available as data.input)
                data.input.val = data.node.key;
            },*/
            save:function(event, data){
                
                var path = data.node.getParent().getKeyPath();
                path = (path=='/')?'':(path+'/');
                var newname = data.input.val();
                var newpath =  path+newname;
                
                var request;
                if(window.hWin.HEURIST4.util.isempty(data.node.origTitle)){
                    //new folder
                    request = {operation:'create', name:newpath};
                }else{
                    var currname = path+data.node.origTitle;
                    request = {operation:'rename', name:currname, newname:newpath};
                }

//console.log(data.node.getLevel()+',  '+path+', '+data.node.orgTitle+', '+data.input.val());
                
                window.hWin.HAPI4.SystemMgr.get_sysfolders(request, 
                    function(response){
                        $(data.node.span).removeClass("pending");
                        if(response.status == window.hWin.ResponseStatus.OK){
                            data.node.setTitle(newname);       
                            data.node.origTitle = newname;
                            data.node.folder = true;
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                
                return true;
            },
            close: function(event, data){
                // Editor was removed
                if( data.save ) {
                  // Since we started an async request, mark the node as preliminary
                  $(data.node.span).addClass("pending");
                }
            }        
            },
            select: function(event, data) {
            
                //var snodes = data.tree.getSelectedNodes();
                
                // Get a list of all selected TOP nodes
                var snodes = data.tree.getSelectedNodes(true);
                // ... and convert to a key array:
                var selRootKeys = $.map(snodes, function(node){
                    console.log(node.getKeyPath());
                    return node.key;
                });
                /*
                for(var i=0; i<snodes.length; i++){
                    if(!snodes[i].partsel()){
                        if(snodes[i].getLevel()<2 || snodes[i].getParent().isPartsel()){
                            console.log(snodes[i].getKeyPath());
                        }
                    }
                }*/
            },
            activate: function(event, data) {
                
                //that.selectedRecords([data.node.key]);
                //console.log('click on '+data.node.key+' '+data.node.title);
            }
        };

        this._treeview = this.recordList.fancytree(fancytree_options);
    },


});
