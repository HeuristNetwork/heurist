/**
* Accordeon/treeview in navigation panel: saved, faceted and tag searches
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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

var Hul = top.HEURIST4.util;

//constants 
const _NAME = 0, _QUERY = 1, _GRPID = 2;

$.widget( "heurist.svs_list", {

    // default options
    options: {
        isapplication:true,  // send and recieve the global events
        searchdetails: "map", //level of search results  map - with details, structure - with detail and structure
        btn_visible_dbstructure: true,
        btn_visible_filter: false
    },

    currentSearch: null,
    hSvsEdit: null,
    treeviews:{},
    
    // the constructor
    // create filter+button and div for tree
    _create: function() {

        var that = this;

        this.search_tree = $( "<div>" ).css({'height':'100%'}).appendTo( this.element );
        this.search_faceted = $( "<div>" ).css({'height':'100%'}).appendTo( this.element ).hide(); 
        
        
        this.div_header = $( "<div>" ).css({'width':'100%', 'padding-left':'2.5em', 'font-size':'0.9em'}) //, 'height':'2em', 'padding':'0.5em 0 0 2.2em'})
                    //.removeClass('ui-widget-content')
                    .appendTo( this.search_tree );
        
        this.helper_top = $( '<div>'+top.HR('right-click for actions')+'</div>' )
            .addClass('logged-in-only heurist-helper1')
            .appendTo( $( "<div>" ).css('height','1.3em').appendTo(this.div_header) )
            //.appendTo( this.accordeon );
            if(top.HAPI4.get_prefs('help_on')=='0') this.helper_top.hide();
        
        
        if(this.options.btn_visible_filter){
        
            this.filter = $( "<div>" ).css({'height':'2em', 'width':'100%'}).appendTo( this.search_tree );
            
            this.filter_input = $('<input name="search" placeholder="Filter...">')
                            .css('width','100px').appendTo(this.filter);
            this.btn_reset = $( "<button>" )
                .appendTo( this.filter )
                .button({icons: {
                        primary: "ui-icon-close"
                    },
                    title: top.HR("Reset"),
                    text:false})
                .css({'font-size': '0.8em','height':'18px','margin-left':'2px'})
                .attr("disabled", true);
            this.btn_save = $( "<button>" )
            .appendTo( this.filter )
            .button({icons: {
                    primary: "ui-icon-disk"
                },
                title: top.HR("Save"),
                text:false})
            .css({'font-size': '0.8em','height':'18px','margin-left':'2px'})
            
        }

        var hasHeader = ($(".header"+that.element.attr('id')).length>0);
        
        var toppos = 1;
        //if(this.options.btn_visible_dbstructure) toppos = toppos + 3;
        if(this.options.btn_visible_filter) toppos = toppos + 2;
        if(hasHeader) toppos = toppos + 2;

        //main container
        this.accordeon = $( "<div>" ).css({'top':toppos+'em', 'bottom':0, 'left':'1em', 'right':'0.5em', 'position': 'absolute', 'overflow':'auto'}).appendTo( this.search_tree );

        
        this.edit_dialog = null;
            
        if(this.options.btn_visible_filter){
            // listeners            
            this.filter_input.keyup(function(e){
                  var leavesOnly = true; //$("#leavesOnly").is(":checked"),
                      match = $(this).val();

                  if(e && e.which === $.ui.keyCode.ESCAPE || $.trim(match) === ""){
                    that.btn_reset.click();
                    return;
                  }
                  // Pass a string to perform case insensitive matching
                  for (var groupID in that.treeviews)
                    if(groupID){
                        var n = that.treeviews[groupID].filterNodes(match, leavesOnly); //n - found
                    }
                 
                  that.btn_reset.attr("disabled", false);
            });        
            
            this._on( this.btn_reset, { click: function(){
                  this.filter_input.val("");
                  for (var groupID in this.treeviews)
                    if(groupID){
                        this.treeviews[groupID].clearFilter();
                    }
            } });
            this._on( this.btn_save, { click: "_saveTreeData"} );
        }
        

        //global listener
        $(this.document).on(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT, function(e, data) {
            that.accordeon.empty();
            that.helper_top = null;
            that._refresh();
        });
        $(this.document).on(top.HAPI4.Event.ON_REC_SEARCHSTART, function(e, data){
            if(data && !data.increment){
                that.currentSearch = Hul.cloneJSON(data);  
            } 
        });

        this._refresh();
    }, //end _create

    _setOption: function( key, value ) {
        this._super( key, value );
        /*
        if(key=='rectype_set'){
        this._refresh();
        }*/
    },

    /* private function */
    _refresh: function(){
        
        if(!top.HAPI4.is_logged()){
             top.HAPI4.currentUser.usr_GroupsList = [];
        }else if (!this.helper_btm) {

            //old
            /* var t1 = '<p><img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" style="background-image: url(&quot;'+top.HAPI4.basePath+'assets/fa-cubes.png&quot;);">'
            +'This is a faceted search. It allows the user to drill-down into the database on a set of pre-selected database fields.</p>'
            +'<p><img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" style="background-image: url(&quot;'+top.HAPI4.basePath+'assets/fa-share-alt.png&quot;);">'
            +'This is a search with addition of a Rule Set. The initial search is expanded to a larger set of records by following a set of rules specifying which pointers and relationships to follow.'
            +'</p>'; */

            //new
            var t1 = '<div style="padding-top:2.5em;font-style:italic;" title="Faceted searches allow the user to drill-down into the database on a set of pre-selected database fields">'
            +'<img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" style="background-image: url(&quot;'+top.HAPI4.basePath+'assets/fa-cubes.png&quot;);vertical-align:middle">'
            +'&nbsp;Faceted search</div>'
            +'<div style="font-style:italic;" title="Searches with addition of a Rule Set automatically expand the initial search to a larger set of records by following a set of rules specifying which pointers and relationships to follow (including relationship type and target record types)">'
            +'<img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" style="background-image: url(&quot;'+top.HAPI4.basePath+'assets/fa-share-alt.png&quot;);vertical-align:middle">'
            +'&nbsp;Search with rules</div>';        

            this.helper_btm = $( '<div>'+t1+'</div>' )
            //IAN request 2015-06-23 .addClass('heurist-helper1')
            .appendTo( this.accordeon );
            //IAN request 2015-06-23 if(top.HAPI4.get_prefs('help_on')=='0') this.helper_btm.hide(); // this.helper_btm.css('visibility','hidden');

        }

        var that = this;
        if(!top.HAPI4.currentUser.usr_SavedSearch){  //find all saved searches for current user

            top.HAPI4.SystemMgr.ssearch_get(
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        top.HAPI4.currentUser.usr_SavedSearch = response.data;
                        that._refresh();
                    }
            });
        }else if(!top.HAPI4.currentUser.usr_GroupsList){
            
                //get details about user groups (names etc)
                top.HAPI4.SystemMgr.mygroups(
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            top.HAPI4.currentUser.usr_GroupsList = response.data;
                            that._refresh();
                        }
                });
                
        }else{
            this._updateAccordeon();
        }

    },
    
    //
    // save current treeview layout
    //
    _saveTreeData: function(){

            var treeData = {};        
              for (var groupID in this.treeviews)
                if(groupID){
                    var d = this.treeviews[groupID].toDict(true);
                    
                    treeData[groupID] = d;
                }
          
            var request = { data:JSON.stringify(treeData) };
            
            top.HAPI4.SystemMgr.ssearch_savetree( request, function(response){} );
    },

    //
    // redraw accordeon - list of workgroups + rules, all, bookmarked
    //
    _updateAccordeon: function(){
        
        this.accordeon.hide();

        
        var islogged = (top.HAPI4.is_logged());
        //if not logged in show only rules and "my searches/all records"
        
        this.treeviews = {};
        
        var that = this;
        
        //verify that all required libraries have been loaded
        if(!$.isFunction($('body').fancytree)){        //jquery.fancytree-all.min.js                           
            $.getScript(top.HAPI4.basePath+'ext/fancytree/jquery.fancytree-all.min.js', function(){ that._updateAccordeon(); } );
            return;
        }else if(!islogged){   
        
            top.HAPI4.currentUser.ugr_SvsTreeData = that._define_DefaultTreeData();         
            
        }else if(!$.ui.fancytree._extensions["dnd"]){
        //    $.getScript(top.HAPI4.basePath+'ext/fancytree/src/jquery.fancytree.dnd.js', function(){ that._updateAccordeon(); } );
            alert('drag-n-drop extension for tree not loaded')
            return;
        }else if(!top.HAPI4.currentUser.ugr_SvsTreeData){ //not loaded - load from file [user_id]_svstree.json
            
            top.HAPI4.SystemMgr.ssearch_gettree( function(response){
                
                if(response.status = top.HAPI4.ResponseStatus.OK){
                    try {
                        //1. remove nodes that refers to missed search
                        function __cleandata(data){
                            
                            if(data.children){
                                var newchildren = [];
                                for (var idx in data.children){
                                  if(idx>=0){
                                       var node = __cleandata(data.children[idx]);
                                       if(node!=null)
                                            newchildren.push(node)
                                  }
                                }
                                data.children = newchildren;
                            }else if(data.key>0){
                                return top.HAPI4.currentUser.usr_SavedSearch[data.key]?data:null;
                            }else{
                                return data;
                            }
                        }                        
                        
                        top.HAPI4.currentUser.ugr_SvsTreeData = $.parseJSON(response.data);
                        
                        top.HAPI4.currentUser.ugr_SvsTreeData = __cleandata(top.HAPI4.currentUser.ugr_SvsTreeData);
                        
                    }
                    catch (err) {
                    }  
                }
                
                if(!top.HAPI4.currentUser.ugr_SvsTreeData) //treeview was not saved - define tree data by default
                        top.HAPI4.currentUser.ugr_SvsTreeData = that._define_DefaultTreeData();
                else{
                    that._validate_TreeData();
                }        
                        
                //add missed entries        
                        
                        
                that._updateAccordeon();        
            } );
            
            return;
        }        
        
        
        //add db summary as a first entry
        if(this.options.btn_visible_dbstructure && this.helper_btm){
                                           //8ea9b9 
            this.helper_btm.before( 
              $('<div>')
                .attr('grpid',  'dbs').addClass('svs-acordeon')
                .css('border','none')
                .append( this._defineHeader(top.HR('Database Summary'), 'dbs').click( function(){ that._showDbSummary(); })
                ) );
        
            //
        }        
                
        if(islogged){   
            this.helper_btm.before(
                $('<div>')
                .attr('grpid',  'rules').addClass('svs-acordeon')
                .css('border','none')
                .append( this._defineHeader(top.HR('Rule Sets'), 'rules'))
                .append( this._defineContent('rules') ));

                
            this.helper_btm.before(
                $('<div>')
                    .addClass('svs-acordeon-group')
                    .html(top.HR('PERSONAL')));
                
            this.helper_btm.before(
                $('<div>')
                .attr('grpid',  'bookmark').addClass('svs-acordeon')
                .addClass('heurist-bookmark-search')
                .css('display', (top.HAPI4.get_prefs('bookmarks_on')=='1')?'block':'none')
                .append( this._defineHeader(top.HR('My Bookmarks'), 'bookmark'))
                .append( this._defineContent('bookmark') ) );

            this.helper_btm.before(
                $('<div>')
                .attr('grpid',  'all').addClass('svs-acordeon')
                .css('border','none')
                .append( this._defineHeader(top.HR('My Searches'), 'all'))
                .append( this._defineContent('all') ));

            var groups = top.HAPI4.currentUser.usr_GroupsList;

            if(groups){
                
                this.helper_btm.before(
                $('<div>')
                    .addClass('svs-acordeon-group')
                    .html(top.HR('WORKGROUPS')));
            
                for (var groupID in groups)
                {
                    if(groupID){
                        var name = groups[groupID][1];
                        this.helper_btm.before(
                            $('<div>')
                            .attr('grpid',  groupID).addClass('svs-acordeon')
                            .append( this._defineHeader(name, groupID))
                            .append( this._defineContent(groupID) ));
                    }
                }
            }
        }else{
                ($('<div>')
                .attr('grpid',  'all').addClass('svs-acordeon')
                .append( this._defineHeader(top.HR('Predefined Searches'), 'all'))
                .append( this._defineContent('all') )).appendTo( this.accordeon );
                        
               
        }
        
        var keep_status = top.HAPI4.get_prefs('svs_list_status');
        if(!keep_status) keep_status = {};
        else keep_status = $.parseJSON(keep_status);

        var cdivs = this.accordeon.find('.svs-acordeon');
        $.each(cdivs, function(i, cdiv){
            
            cdiv = $(cdiv);
            var groupid = cdiv.attr('grpid');
            cdiv.accordion({
            active: ( ( keep_status && keep_status[ groupid ] )?0:false),
            header: "> h3",
            heightStyle: "content",
            collapsible: true,
            activate: function(event, ui) {
                //save status of accordions - expandad/collapsed
                if(ui.newHeader.length>0 && ui.oldHeader.length<1){ //activated
                    keep_status[ ui.newHeader.attr('grpid') ] = true;    
                }else{ //collapsed
                    keep_status[ ui.oldHeader.attr('grpid') ] = false;    
                }
                //save
                top.HAPI4.SystemMgr.save_prefs({'svs_list_status': JSON.stringify(keep_status)});
                }
            });
        });
        
        
        /*this.accordeon.children()
        .accordion({
            active: ( ( keep_status && keep_status[ this.attr('grpid') ] )?0:false),
            header: "> h3",
            heightStyle: "content",
            collapsible: true,
            activate: function(event, ui) {
                //save status of accordions - expandad/collapsed
                if(ui.newHeader.length>0 && ui.oldHeader.length<1){ //activated
                    keep_status[ ui.newHeader.attr('grpid') ] = true;    
                }else{ //collapsed
                    keep_status[ ui.oldHeader.attr('grpid') ] = false;    
                }
                //save
                top.HAPI4.save_prefs({'svs_list_status': keep_status});
            }
        });*/
        this.accordeon.show();        
        
    },
    
    _getAddContextMenu: function(groupID){  
               var arr_menu = [{title: "New", cmd: "addSearch", uiIcon: "ui-icon-plus" }];
               if(groupID!='rules'){
                    arr_menu.push({title: "New faceted", cmd: "addSearch2", uiIcon: "ui-icon-plus" });  
               }
               arr_menu.push({title: "New folder", cmd: "addFolder", uiIcon: "ui-icon-folder-open" });
               
               var that = this;

               var context_opts = {
                    menu: arr_menu,
                    select: function(event, ui) {
                         if(ui.cmd=="addFolder"){
                             
                            setTimeout(function(){ 
                                var tree = that.treeviews[groupID];
                                var node = tree.rootNode;  
                                node.folder = true;  
                                
                                node.editCreateNode( "child", {title:"", folder:true}); //New folder
                                that._saveTreeData();
                                $("#addlink"+groupID).css('display', 'none');
                            }, 300);
                            //tree.trigger("nodeCommand", {cmd: ui.cmd}); 
                         }else{
                            var isfaceted = (ui.cmd=="addSearch2");
                            that.editSavedSearch(isfaceted?'faceted':'saved', groupID);
                         }
                    }
                    };
               return context_opts;
    }, 
      
    _defineHeader: function(name, domain){

        var $header = $('<h3 grpid="'+domain+'">'+name+'</h3>').addClass('tree-accordeon-header');
        /*
        var that = this;

        if(this.currentMode!='tags' && domain!=null){
            $('<div>',{
                id: 'rec_add_link',
                title: top.HR(this.currentMode=='faceted'?'Define new faceted search':'Click to save current search')
            })
            .button({icons: {primary: "ui-icon-circle-plus"}, text:false})
            .click(function( event ) {
                event.preventDefault();
                if(domain=='rules'){
                    that.editRules(null);
                }else{
                    that.editSavedSearch(null, null, domain);
                }
                return false;
            })
            .appendTo($header);
        }
        */
        
        if('dbs'!=domain){
            var context_opts = this._getAddContextMenu(domain);
            $header.contextmenu(context_opts);
        }
        
        return $header
    },    

    //
    // redraw treeview with the list of saved searches for given groupID and domain
    // add tree as a accordeon content
    //
    _defineContent: function(groupID){
        //
        var res;
        var that = this;
        var CLIPBOARD = null;
        
        var treeData = top.HAPI4.currentUser.ugr_SvsTreeData[groupID] && top.HAPI4.currentUser.ugr_SvsTreeData[groupID].children
                             ?top.HAPI4.currentUser.ugr_SvsTreeData[groupID].children :[];
        
        var tree = $("<div>").css('padding-bottom','0em');
            
        var fancytree_options =     
            {
        checkbox: false,
        //titlesTabbable: false,     // Add all node titles to TAB chain
        source: treeData,
        quicksearch: true,
        
        renderNode: function(event, data) {
            // Optionally tweak data.node.span
            var node = data.node;
            if(true || node.data.cstrender){
              var $span = $(node.span);
              var s = '', s1='';
              if(node.folder){
                  //
                    s1 = '<span class="ui-icon-folder-open ui-icon" style="display:inline-block;"></span>';
                    $span.find("> span.fancytree-title").html(s1 + 
                    '<div style="display:inline-block;vertical-align:top;padding:2 0 0 3;font-weight:normal !important">'+node.title+'</div>');
                    //vertical-align:top;
                    
                    //$span.find("> span.fancytree-icon").addClass("ui-icon-folder-open ui-icon").css('display','inline-block');
                    //s = '<img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" title="faceted" style="background-image: url(&quot;'+top.HAPI4.basePath+'assets/fa-cubes.png&quot;);">';
              }else{ 
                  if(node.data.isrules){
                        s = '<img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" title="+rule set" style="background-image: url(&quot;'+top.HAPI4.basePath+'assets/fa-share-alt.png&quot;);">';
                  }else if(node.data.isfaceted){
                        s = '<img src="'+top.HAPI4.basePath+'assets/16x16.gif'+'" title="faceted" style="background-image: url(&quot;'+top.HAPI4.basePath+'assets/fa-cubes.png&quot;);">';
                  }
                  if(s==''){
                        $span.find("> span.fancytree-title").text(node.title);
                  }else{
                        $span.find("> span.fancytree-title").html(node.title + s)
                            .attr('title', node.data.isrules
                            ?'Searches with addition of a Rule Set automatically expand the initial search to a larger set of records by following a set of rules specifying which pointers and relationships to follow (including relationship type and target record types)'
                            :'Faceted searches allow the user to drill-down into the database on a set of pre-selected database fields'  );
                  }
              }
              
              //<span class="fa-ui-accordion-header-icon ui-icon ui-icon-triangle-1-s"></span>
              
              //.css({fontStyle: "italic"});
              /*
              $span.find("> span.fancytree-icon").css({
    //                      border: "1px solid green",
                backgroundImage: "url(skin-custom/customDoc2.gif)",
                backgroundPosition: "0 0"
              });*/
            }
          },    


            click: function(event, data) {
                if(!data.node.folder){
                    var qname, qsearch, isfaceted;
                    if(data.node.data && data.node.data.url){
                            isfaceted= data.node.data.isfaceted;
                            qsearch = data.node.data.url;
                            qname   = data.node.title;
                    }else{
                        if (data.node.key && top.HAPI4.currentUser.usr_SavedSearch && top.HAPI4.currentUser.usr_SavedSearch[data.node.key]){
                            qsearch = top.HAPI4.currentUser.usr_SavedSearch[data.node.key][_QUERY];
                            qname   = top.HAPI4.currentUser.usr_SavedSearch[data.node.key][_NAME];
                            isfaceted = data.node.data.isfaceted;
                        }
                    }
                    that._doSearch2( qname, qsearch, isfaceted, event.target );
                }
                
            }
          };
  
  if(top.HAPI4.is_logged()){

      fancytree_options['extensions'] = ["edit", "dnd", "filter"];
      fancytree_options['dnd'] = {
          preventVoidMoves: true,
          preventRecursiveMoves: true,
          autoExpandMS: 400,
          dragStart: function(node, data) {
              return true;
          },
          dragEnter: function(node, data) {
              // return ["before", "after"];
              if(node.tree._id == data.otherNode.tree._id){
                  return node.folder ?true :["before", "after"];    
              }else{
                  return false;
              }


          },
          dragDrop: function(node, data) {
              data.otherNode.moveTo(node, data.hitMode);
              that._saveTreeData();
          }
      };
      fancytree_options['edit'] = {
          save:function(event, data){
              if(''!=data.input.val())
                    that._saveTreeData(); 
          }
      };
      fancytree_options['filter'] = { mode: "hide" };

      tree.fancytree(fancytree_options)
      //.css({'height':'100%','width':'100%'})
      .on("nodeCommand", function(event, data){
        // Custom event handler that is triggered by keydown-handler and
        // context menu:
        var refNode, moveMode,
          tree = $(this).fancytree("getTree"),
          node = tree.getActiveNode();

        switch( data.cmd ) {
        case "moveUp":
          node.moveTo(node.getPrevSibling(), "before");
          node.setActive();
          break;
        case "moveDown":
          node.moveTo(node.getNextSibling(), "after");
          node.setActive();
          break;
        case "indent":
          refNode = node.getPrevSibling();
          node.moveTo(refNode, "child");
          refNode.setExpanded();
          node.setActive();
          break;
        case "outdent":
          node.moveTo(node.getParent(), "after");
          node.setActive();
          break;
        case "rename":   //EDIT
        
            if(!node.folder && node.key>0){
                that.editSavedSearch(node.data.isfaceted?'faceted':'saved', groupID, node.key, null, node);
            }else{
                node.editStart();   
            }
          
          break;
        case "remove":
                  
              function __removeNode(){
                    node.remove(); 
                    that._saveTreeData(); 
                    if(that.treeviews[groupID].count()<1){
                        $("#addlink"+groupID).css('display', 'block');
                    }
              }
        
            if(node.folder){
                if(node.countChildren()>0){
                    Hul.showMsgDlg('Can not delete non-empty folder. Delete dependent entries first.',null,top.HR('Warning'));
                }else{
                    __removeNode();
                }
            }else{
                  //saved search may have several entries - try to find
                  
                  //loop all nodes
                  var cnt = 0;
                  that.treeviews[groupID].visit(function(node2){
                      if(node2.key==node.key){
                          cnt++;
                          if(cnt>1) return false;
                      }
                  });
                
                  if(cnt==1){
                    that._deleteSavedSearch(node.key, node.title, __removeNode);
                  }else{
                    __removeNode();    
                  }
            }
          break;
        case "addFolder":  //always create sibling folder
          /*if(!node.folder){
            node = tree.rootNode;    
          }*/
          //node = node.parent;    
          node.editCreateNode( node.folder?"child":"after", {title:"", folder:true}); //New folder
          
          break;

        case "addSearch":  //add new saved search
        case "addSearch2": //add new faceted search
        
           var isfaceted = (data.cmd == "addSearch2");

           that.editSavedSearch(isfaceted?'faceted':'saved', groupID, null, null, node);
          
          break;
          
        case "addChild":
          node.editCreateNode("child", "New folder");
          // refNode = node.addChildren({
          //   title: "New node",
          //   isNew: true
          // });
          // node.setExpanded();
          // refNode.editStart();
          break;
        case "addSibling":
          node.editCreateNode("after", "New node");
          // refNode = node.getParent().addChildren({
          //   title: "New node",
          //   isNew: true
          // }, node.getNextSibling());
          // refNode.editStart();
          break;
        case "cut":
          CLIPBOARD = {mode: data.cmd, data: node};
          break;
        case "copy":
          CLIPBOARD = {
            mode: data.cmd,
            data: node.toDict(function(n){
              delete n.key;
            })
          };
          break;
        case "clear":
          CLIPBOARD = null;
          break;
        case "paste":
          if( CLIPBOARD.mode === "cut" ) {
            // refNode = node.getPrevSibling();
            CLIPBOARD.data.moveTo(node, "child");
            CLIPBOARD.data.setActive();
          } else if( CLIPBOARD.mode === "copy" ) {
            node.addChildren(CLIPBOARD.data).setActive();
          }
          that._saveTreeData();
          break;
        default:
          alert("Unhandled command: " + data.cmd);
          return;
        }

      }).on("keydown", function(e){
        var c = String.fromCharCode(e.which),
          cmd = null;

        if( c === "N" && e.ctrlKey && e.shiftKey) {     //add new folder
          cmd = "addFolder";
        } else if( c === "C" && e.ctrlKey ) {
          cmd = "copy";
        } else if( c === "V" && e.ctrlKey ) {
          cmd = "paste";
        } else if( c === "X" && e.ctrlKey ) {
          cmd = "cut";
        } else if( c === "N" && e.ctrlKey ) {
          cmd = "addSearch";
        } else if( e.which === $.ui.keyCode.DELETE ) {
          cmd = "remove";
        } else if( e.which === $.ui.keyCode.F2 ) {
          cmd = "rename";
        } else if( e.which === $.ui.keyCode.UP && e.ctrlKey ) {
          cmd = "moveUp";
        } else if( e.which === $.ui.keyCode.DOWN && e.ctrlKey ) {
          cmd = "moveDown";
        } else if( e.which === $.ui.keyCode.RIGHT && e.ctrlKey ) {
          cmd = "indent";
        } else if( e.which === $.ui.keyCode.LEFT && e.ctrlKey ) {
          cmd = "outdent";
        }
        if( cmd ){
          $(this).trigger("nodeCommand", {cmd: cmd});
          return false;
        }
      });    
      
      /*
       * Context menu (https://github.com/mar10/jquery-ui-contextmenu)
       */
      var that = this;
      tree.contextmenu({
        delegate: "li", //span.fancytree-node
        menu: [
          {title: "New", cmd: "addSearch", uiIcon: "ui-icon-plus" }, //<kbd>[Ctrl+N]</kbd>
          {title: "New faceted", cmd: "addSearch2", uiIcon: "ui-icon-plus" },
          {title: "Edit", cmd: "rename", uiIcon: "ui-icon-pencil" }, // <kbd>[F2]</kbd>
          {title: "----"},
          {title: "New folder", cmd: "addFolder", uiIcon: "ui-icon-folder-open" }, // <kbd>[Ctrl+Shift+N]</kbd>
          {title: "Delete", cmd: "remove", uiIcon: "ui-icon-trash" },  // <kbd>[Del]</kbd>
          {title: "----"},
          {title: "Cut", cmd: "cut", uiIcon: "ui-icon-scissors"}, // <kbd>Ctrl+X</kbd>
          {title: "Copy", cmd: "copy", uiIcon: "ui-icon-copy"},  // <kbd>Ctrl-C</kbd>
          {title: "Paste as child", cmd: "paste", uiIcon: "ui-icon-clipboard", disabled: true } //<kbd>Ctrl+V</kbd>
          ],
        beforeOpen: function(event, ui) {
          var node = $.ui.fancytree.getNode(ui.target);
          tree.contextmenu("enableEntry", "paste", !!CLIPBOARD);
          node.setActive();
        },
        select: function(event, ui) {
          var that = this;
          // delay the event, so the menu can close and the click event does
          // not interfere with the edit control
          setTimeout(function(){
            $(that).trigger("nodeCommand", {cmd: ui.cmd});
          }, 100);
        }
      });

      $.each( tree.find('li'), function( idx, item ){
          $('<div class="svs-contextmenu ui-icon ui-icon-pencil"></div>')
          .click(function(event){ tree.contextmenu("open", $(event.target) ); top.HEURIST4.util.stopEvent(event); return false;})
          .appendTo(item);
      })
      
      
          var context_opts = this._getAddContextMenu(groupID);
  
  
          //treedata is empty - add div - to show add links
          var tree_links = $("<div>", {id:"addlink"+groupID})
          .css({'display': treeData && treeData.length>0?'none':'block', 'padding-left':'1em'} )
          .append( $("<a>",{href:'#'})
              .html('<span class="ui-icon ui-icon-plus" style="display:inline-block; vertical-align: bottom"></span>add')
              .click(function(event){
                  $(this).contextmenu('open', $(this));
              }) 
              .contextmenu(context_opts)
          );
              

          res = $('<div>').append(tree_links).append(tree);
    }else{
          //not logged in
          tree.fancytree(fancytree_options);
          res = $('<div>').append(tree);
    }
      
       this.treeviews[groupID] = tree.fancytree("getTree");

       return res;

    },

    //add missed groups and saved searches to treeview
    _validate_TreeData: function(){
        
        var treeData = this._define_DefaultTreeData();
        
        //form files
        var treeDataF = top.HAPI4.currentUser.ugr_SvsTreeData;
        if(!treeDataF) treeDataF = {};
        
                
        for (var groupID in treeData){
            if(!treeDataF[groupID]){
                //direct copy
                treeDataF[groupID] = treeData[groupID];
            }else{
                if(!treeDataF[groupID]['children']){
                    treeDataF[groupID]['children'] = [];
                }
                
                
                function __findInTeeDataF(nodes, key){
                    
                    var res = false;
                    
                                for (var idx in nodes){
                                  if(idx>=0){
                                       var node = nodes[idx];
                                       if(node['key'] == key){
                                           return true;
                                       }else if(node['children']){
                                           res = __findInTeeDataF( node.children, key );
                                           if(res) return res;
                                       }                                       
                                  }
                                }
                                
                    return res; 
                    
                }
                
                //add missed saved searches
                var svs = treeData[groupID].children;
                var i;
                for(i=0; i<svs.length; i++){
                    //find in treeview from file
                    if(svs[i]['key']){
                        if(!__findInTeeDataF( treeDataF[groupID]['children'], svs[i]['key'])){ //if not found - add
                            treeDataF[groupID]['children'].push(svs[i]);
                        }
                    }
                }
            }
        }
        
        top.HAPI4.currentUser.ugr_SvsTreeData = treeDataF;
    },
    
    _define_DefaultTreeData: function(){

        var treeData;
        if(top.HAPI4.is_logged()){
            treeData = {
                rules:{ title: top.HR('Rule Sets'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'rules') },
                all: { title: top.HR('My Searches'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'all') },
                bookmark:{ title: top.HR('My Bookmarks'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'bookmark') }
            };
            if(top.HAPI4.is_admin()){
                treeData['guests'] = { title: top.HR('Searches for guests'), folder: true, expanded: false, children: this._define_SVSlist(0) };
            }
        }else{
            treeData = {
                //rules:{ title: top.HR('Rule Sets'), folder: true, expanded: true, children: this._define_SVSlist(0, 'rules') },
                all: { title: top.HR('Searches'), folder: true, expanded: true, children: this._define_SVSlist(0, 'all') }
            };
        }
        
        if(top.HAPI4.is_logged()){
        
            var groups = top.HAPI4.currentUser.usr_GroupsList;

            for (var groupID in groups)
            {
                if(groupID){
                    var name = groups[groupID][1];
                    treeData[groupID] = {title: name, folder: true, expanded: false, children: this._define_SVSlist(groupID) };
                }
            }
        
        }
        
        return treeData;
        
    },

    //
    /**
    * create list of saved searches for given user/group
    * domain - all or bookmark or rules
    */
    _define_SVSlist: function(ugr_ID, domain){

        var ssearches = top.HAPI4.currentUser.usr_SavedSearch;

        var res = [];

        //add predefined searches
        if(ugr_ID == top.HAPI4.currentUser.ugr_ID && domain!='rules'){  //if current user domain may be all or bookmark

            domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';

            var s_all = "?w="+domain+"&q=sortby:-m after:\"1 week ago\"&label=Recent changes";
            var s_recent = "?w="+domain+"&q=sortby:-m&label=All records";

            res.push( { title: top.HR('Recent changes'), folder:false, url: s_all}  );
            res.push( { title: top.HR('All (date order)'), folder:false, url: s_recent}  );
        }

        //_NAME = 0, _QUERY = 1, _GRPID = 2
        
        

        for (var svsID in ssearches)
        {
            var facet_params = null, domain2, isfaceted = false, isrules = false, saddition = '';
            
            if(svsID && ssearches[svsID][_GRPID]==ugr_ID){


                try {
                    facet_params = $.parseJSON(ssearches[svsID][_QUERY]);
                    
                    if(facet_params && Hul.isArray(facet_params.rectypes)){
                        //this is faceted search
                        domain2 = facet_params.domain;
                        isfaceted = true;
                        saddition = ' [faceted]';
                    }
                }
                catch (err) {
                }
                if(!isfaceted){
                        // this is saved search
                        var prms = Hul.parseHeuristQuery(ssearches[svsID][_QUERY]);
                        //var qsearch = prms[0];
                        if(Hul.isempty(prms.q)&&!Hul.isempty(prms.rules)){
                            domain2 = 'rules';
                            saddition = '';//' [rules]';
                        }else{
                            if(!Hul.isempty(prms.rules)){
                                saddition = ' [+rules]';
                                isrules = true;
                            }
                            domain2 = prms.w;    
                            if(Hul.isempty(prms.q)) continue; //do not show saved searches without Q (rules) in this list
                        }
                }
                
                if(!domain || domain==domain2){
                    var sname = ssearches[svsID][_NAME]; // + saddition;                     //, iconclass:'ui-icon icon-share-alt'
                    res.push( { title:sname, folder:false, key:svsID, isfaceted:isfaceted, isrules:isrules } );    //, url:ssearches[svsID][_QUERY]
                }
            }
        }

        return res;

    },


    /*
    _doSearch: function(event){

    var qsearch = null;
    var qid = $(event.target).attr('svsid');

    if (qid && top.HAPI4.currentUser.usr_SavedSearch){
    qsearch = top.HAPI4.currentUser.usr_SavedSearch[qid][1];
    } else {
    qsearch = $(event.target).find('div').html();
    qsearch = qsearch.replace("&amp;","&");
    }
    this._doSearch2(qsearch);
    },*/

    _doSearch2: function(qname, qsearch, isfaceted, ele){
        
        if ( qsearch ) {

            if(isfaceted){

                var facet_params = null;
                try {
                    facet_params = $.parseJSON(qsearch);
                }
                catch (err) {
                    facet_params = null;
                }
                if(!facet_params || !Hul.isArray(facet_params.facets)){
                    // Do something about the exception here
                    Hul.showMsgDlg(top.HR('Can not init faceted search. Corrupted parameters. Remove this search'), null, "Error");
                    return;
                }

                var that = this;
                
                
                if(facet_params['version']==2){
                    
                        this.search_faceted.show();
                        this.search_tree.hide();

                        var noptions= { query_name:qname, params:facet_params,
                            onclose:function(event){
                                that.search_faceted.hide();
                                that.search_tree.show();
                        }};

                        if(this.search_faceted.html()==''){ //not created yet
                            this.search_faceted.search_faceted( noptions );                    
                        }else{
                            this.search_faceted.search_faceted('option', noptions ); //assign new parameters
                        }
                    
                }else{
                
                    Hul.showMsgErr("This faceted search is old version. Please edit and save to upgrade it")    
                
                }

            }else{

                var request = Hul.parseHeuristQuery(qsearch);
                
                if(Hul.isempty(request.q)&&!Hul.isempty(request.rules)){
                    
                    if(this.currentSearch){
                        this.currentSearch.rules = Hul.cloneJSON(request.rules);
                    }
                    
                    $(this.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_APPLYRULES, [ {rules:request.rules, target:ele} ]); //global app event   - see resultList.js for listener
                }else{
                    //additional params
                    request.f = this.options.searchdetails;
                    request.source = this.element.attr('id');
                    request.qname = qname;
                    request.notes = null; //unset to reduce traffic

                    //that._trigger( "onsearch"); //this widget event
                    //that._trigger( "onresult", null, resdata ); //this widget event

                    //get hapi and perform search
                    top.HAPI4.RecordMgr.search(request, $(this.document));
                }
            }
        }

    },
    
    _deleteSavedSearch: function(svsID, svsTitle, callback){


        var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];
        if(!svs) return;

        Hul.showMsgDlg(top.HR("Delete '"+ svsTitle  +"'? Please confirm"),  function(){

            top.HAPI4.SystemMgr.ssearch_delete({ids:svsID, UGrpID: svs[2]},
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){

                        //remove from UI
                        callback.apply(this);
                        //remove from
                        delete top.HAPI4.currentUser.usr_SavedSearch[svsID];

                    }else{
                        Hul.showMsgErr(response);
                    }
                }

            );
            }, "Confirmation");                 

    }
    
    , _hasRules: function(query){
          var prms = Hul.parseHeuristQuery(query);
          return (!Hul.isempty(prms.rules));
    }

    // mode: saved, rules, faceted
    // groupID - current user or workgroup
    // svsID - saved search id
    // squery
    , editSavedSearch: function(mode, groupID, svsID, squery, node){

        var that = this;
            
        var callback = function(event, request) {    
                if(Hul.isempty(svsID)){     //new saved search
                
                    //update tree after addition - add new search to root
                    if(Hul.isnull(node)){
                        groupID = request.svs_UGrpID
                        if(groupID == top.HAPI4.currentUser.ugr_ID){
                               groupID = request.domain; //all or bookmarks
                        }
                        var tree = that.treeviews[groupID];
                        node = tree.rootNode;  
                        node.folder = true;  
                    }
                    var isfaceted = (mode=='faceted');
                    node.addNode( { title:request.svs_Name, key: request.new_svs_ID, isfaceted:isfaceted, isrules:that._hasRules(request.svs_Query) }, node.folder?"child":"after" );    
                    
                    that._saveTreeData();
                    $("#addlink"+groupID).css('display', 'none');
                    
                }else if(node){ //edit is called from this widget only - otherwise we have to implement search node by svsID
                
                    //edit - changed only title in treeview
                    var saddition = '';
                    node.data.isrules = false;
                    if(node.data.isfaceted){
                         saddition = ' [faceted]';
                    }else{
                        node.data.isrules = that._hasRules(request.svs_Query);
                    }
                    node.setTitle(request.svs_Name); // + saddition);
                    node.render(true);
                    that._saveTreeData();
                }
           };
        
        
        if( true ) { //}!Hul.isnull(this.hSvsEdit) && $.isFunction(this.hSvsEdit)){ //already loaded     @todo - load dynamically
            
            if(groupID=="rules" && Hul.isempty(svsID)) mode="rules";
            
            if(Hul.isnull(svsID) && Hul.isempty(squery)){
                squery = this.currentSearch;
            }
        
            if(null == this.edit_dialog){
                this.edit_dialog = new hSvsEdit();
            }
            //this.edit_dialog.callback_method  = callback;
            this.edit_dialog.show( mode, groupID, svsID, squery, callback );
                
        }else{
            $.getScript(top.HAPI4.basePath+'apps/search/svs_edit.js', function(){ that.hSvsEdit = hSvsEdit; that.editSavedSearch(mode, groupID, svsID, squery); } );
        }
    
    },
    

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        if(this.edit_dialog) {
            this.edit_dialog.remove();
        }

        if(this.filter){
            this.btn_save.remove(); 
            this.btn_reset.remove(); 
            this.filter.remove(); 
        }
        this.accordeon.remove(); 
        //this.tree.remove(); 
        
        this.search_tree.remove(); 
        this.search_faceted.remove(); 
    }
    
    , _showDbSummary: function(){
        
        var url = top.HAPI4.basePath+ "page/databaseSummary.php?popup=1&db=" + top.HAPI4.database;
        
        var body = this.document.find('body');
        var dim = {h:body.innerHeight(), w:body.innerWidth()};
        
        Hul.showDialog(url, { height:dim.h*0.8, width:dim.w*0.8} );
            
    }

});

/*

jQuery(document).ready(function(){
$('.accordion .head').click(function() {
$(this).next().toggle();
return false;
}).next().hide();
});

*/
