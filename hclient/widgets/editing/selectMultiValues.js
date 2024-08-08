/**
* Widget to select values from multiselection list of tree
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

$.widget( "heurist.selectMultiValues", {

    // default options
    options: {
        isdialog: true, //show in dialog or embedded
        
        title: 'Select Values',
        
        emptyMessage: 'No values found',

        multiselect: true, 
        
        //allowEdit: true,
        
        onselect: null,
        
        //root_dir: null,
        allValues: [],
        
        selectedValues: [] //array or semicolon separated list
        
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    
    //_show_system_folders:false,

    // the constructor
    _init: function() {

        let that = this;
        
        this.element.empty();
        
        this.element.addClass('ui-heurist-bg-light');

        $('<div class="ent_wrapper">'
                +'<div class="ent_header"></div>'
                +'<div class="ent_content_full recordList"></div>'
                +'</div>').appendTo( this.element );
                
        //hide toolbar by default                
        let ent_header = this.element.find('.ent_header').hide();        
        
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

        $( "<div>" )
        .css({ 'width': '50%', 'height': '50%', 'top': '25%', 'margin': '0 auto', 'position': 'relative',
            'z-index':'99999999', 'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center' })
        .appendTo( this.recordList );
        
        
        this._initList();

/*         
            //search for folders
            var that = this;                            
            var opts = {};
            if(this.options.root_dir){
                opts.root_dir = this.options.root_dir;
            }
       
            window.hWin.HAPI4.SystemMgr.get_sysfolders(opts, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.options.allValues = response.data;
                        that._initList();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
*/     
         
         
    }, //end _create
    
    //
    //
    //
    _initList: function(){
        if(Array.isArray(this.options.allValues) && this.options.allValues.length>0){
            this._showAsDialog();
            this._initTreeView( this.options.allValues );    
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR(this.options.emptyMessage));
        }
    },

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
    _showAsDialog: function(){
        
        if(this.options.isdialog){
            
            let that = this;
            
            let buttons = {};
            buttons[window.hWin.HR('Select')]  = function() {
                
                let wtrr = that._treeview.fancytree("getTree");
                // Get a list of all selected TOP nodes
                let snodes = wtrr.getSelectedNodes(true);
                // ... and convert to a key array:
                let res = [];
                let selRootKeys = $.map(snodes, function(node){
                    let currname = node.getKeyPath()
                    if(currname[0]=='/') currname = currname.substring(1);
                    
                    res.push(currname);
                });
                
                if(window.hWin.HUL.isFunction(that.options.onselect)){
                    that.options.onselect.call(that, res);           
                }
                that._as_dialog.dialog('close');
            }; 
            buttons[window.hWin.HR('Cancel')]  = function() {
                that._as_dialog.dialog('close');
            }; 

            let $dlg = this.element.dialog({
                autoOpen: true,
                height: 640,
                width: 480,
                modal: true,
                title: window.hWin.HR(this.options.title),
                resizeStop: function( event, ui ) {
                    let pele = that.element.parents('div[role="dialog"]');
                    that.element.css({overflow: 'none !important', width:pele.width()-24 });
                },
                close:function(){
                    that._as_dialog.remove();    
                },
                buttons: buttons
            });
            
            this._as_dialog = $dlg; 
        }        
        
    },
    
    //
    //
    //
    _initTreeView: function( treeData ){
      
        let that = this;
        
        let fancytree_options =
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
                    let node = data.node;
                    let $span = $(node.span).find("> span.fancytree-title").css({'font-weight':'normal'});
                    if(node.data.files_count>0)
                        $span.html(node.title+' <span style="font-weight:normal">('+node.data.files_count+')</span>');
                    if(node.data.issystem){
                        $span.addClass('graytext');//.css({color:'red !important'});
                    }
            },            
            extensions: ['edit'], //'filter'],
            //filter: { highlight:false, mode: "hide" },
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
                
                let path = data.node.getParent().getKeyPath();
                path = (path=='/')?'':(path+'/');
                let newname = data.input.val();
                let newpath =  path+newname;
                
                let request;
                if(window.hWin.HEURIST4.util.isempty(data.node.origTitle)){
                    //new folder
                    request = {operation:'create', name:newpath};
                }else{
                    let currname = path+data.node.origTitle;
                    request = {operation:'rename', name:currname, newname:newpath};
                }
                
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
            },
            activate: function(event, data) {
            }
        };

        this.recordList.empty();
        
        this._treeview = this.recordList.fancytree(fancytree_options);
        
        if(window.hWin.HEURIST4.util.isempty(this.options.selectedValues)){
            this.options.selectedValues = [];
        }else if(!Array.isArray(this.options.selectedValues)){
            this.options.selectedValues = this.options.selectedValues.split(';');
        }
        
        if(this.options.selectedValues.length>0){
            let wtrr = that._treeview.fancytree("getTree");
            
            wtrr.visit(function(node){
                    if(!node.data.issystem){
                        
                        /*var path = node.getParent().getKeyPath();
                        path = (path=='/')?'':(path+'/');
                        var currname = path+node.title;*/
                        let currname = node.getKeyPath();
                        //remove leading slash
                        if(currname[0]=='/') currname = currname.substring(1);
                        
                        if(that.options.selectedValues.indexOf(currname)>=0){
                            node.setSelected(true);    
                        }
                        
                        
                    }
            });
        }
        
        
    },


});
