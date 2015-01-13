/**
* Accordeon view in navigation panel: saved, faceted and tag searches
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

$.widget( "heurist.search_links_tree", {

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

        if(this.options.btn_visible_dbstructure){
            this.btn_db_dtructure = $( "<button>", {
                text: top.HR("Database Summary")
            })
            .addClass('logged-in-only')
            .appendTo( $( "<div>" ).css({'height':'4em', 'width':'100%', 'text-align':'center'}).appendTo( this.search_tree ) )
            .button({icons: {
                primary: "ui-icon-circle-plus"
            }})
            .click( function(){ that._showDbSummary(); });
        }
        
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
        
        var toppos = 0;
        if(this.options.btn_visible_dbstructure) toppos = toppos + 4;
        if(this.options.btn_visible_filter) toppos = toppos + 2;
        if(hasHeader) toppos = toppos + 2;

        this.accordeon = $( "<div>" ).css({'top':toppos+'em', 'bottom':'0', 'position': 'absolute', 'overflow':'auto'}).appendTo( this.search_tree );
        
        
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
            that._refresh();
        });
        $(this.document).on(top.HAPI4.Event.ON_REC_SEARCHSTART, function(e, data){
            if(data) that.currentSearch = data;
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
        
        var islogged = (top.HAPI4.currentUser.ugr_ID>0);
        if(!islogged){
            //@todo clear accordeon and show login button
            return;
        }
        
        this.treeviews = {};
        
        var that = this;
        
        //verify that all required libraries have been loaded
        if(!$.isFunction($('body').fancytree)){        //jquery.fancytree-all.min.js                           
            $.getScript(top.HAPI4.basePath+'ext/fancytree/jquery.fancytree-all.min.js', function(){ that._updateAccordeon(); } );
            return;
        }else if(!$.ui.fancytree._extensions["dnd"]){
        //    $.getScript(top.HAPI4.basePath+'ext/fancytree/src/jquery.fancytree.dnd.js', function(){ that._updateAccordeon(); } );
            alert('drag-n-drop extension for tree not loaded')
            return;
        }else if(!top.HAPI4.currentUser.ugr_SvsTreeData){ //not loaded - load from file [user_id]_svstree.json
            
            top.HAPI4.SystemMgr.ssearch_gettree( function(response){
                
                if(response.status = top.HAPI4.ResponseStatus.OK){
                    try {
                        top.HAPI4.currentUser.ugr_SvsTreeData = $.parseJSON(response.data);
                        
                        //remove nodes that refers to missed search
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
                        
                        top.HAPI4.currentUser.ugr_SvsTreeData = __cleandata(top.HAPI4.currentUser.ugr_SvsTreeData);
                    }
                    catch (err) {
                    }  
                }
                
                if(!top.HAPI4.currentUser.ugr_SvsTreeData) //treeview was not saved - define tree data by default
                        top.HAPI4.currentUser.ugr_SvsTreeData = that._define_DefaultTreeData();
                        
                that._updateAccordeon();        
            } );
            
            return;
        }        
        
        this.accordeon.append(
                $('<div>')
                .append( this._defineHeader(top.HR('Rule Sets'), 'rules'))
                .append( this._defineContent('rules') ));
        
        this.accordeon.append(
            $('<div>')
            .append( this._defineHeader(top.HR('My Bookmarks'), 'bookmark'))
            .append( this._defineContent('bookmark') ) );
            
        this.accordeon.append(
            $('<div>')
            .append( this._defineHeader(top.HR('All Records'), 'all'))
            .append( this._defineContent('all') ));
            
        var groups = top.HAPI4.currentUser.usr_GroupsList;

        for (var groupID in groups)
        {
            if(groupID){
                var name = groups[groupID][1];
                this.accordeon.append(
                    $('<div>')
                    .append( this._defineHeader(name, groupID))
                    .append( this._defineContent(groupID) ));
            }
        }

        this.accordeon.children()
        .accordion({
            header: "> h3",
            heightStyle: "content",
            collapsible: true
        });
        this.accordeon.show();        
        
    },
    
    _defineHeader: function(name, domain){

        var $header = $('<h3>'+name+'</h3>');
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
        return $header
    },    

    //
    // redraw treeview with the list of saved searches for given groupID and domain
    // add tree as a accordeon content
    //
    _defineContent: function(groupID){
        //
        var that = this;
        var CLIPBOARD = null;
        
        var treeData = top.HAPI4.currentUser.ugr_SvsTreeData[groupID] && top.HAPI4.currentUser.ugr_SvsTreeData[groupID].children
                             ?top.HAPI4.currentUser.ugr_SvsTreeData[groupID].children :[];
        
        var tree = $("<div>");
        
        tree.fancytree({
    checkbox: false,
    //titlesTabbable: false,     // Add all node titles to TAB chain
    source: treeData,
    quicksearch: true,

    extensions: ["edit", "dnd", "filter"],

    dnd: {
      preventVoidMoves: true,
      preventRecursiveMoves: true,
      autoExpandMS: 400,
      dragStart: function(node, data) {
        return true;
      },
      dragEnter: function(node, data) {
        // return ["before", "after"];
        return node.folder ?true :["before", "after"];
      },
      dragDrop: function(node, data) {
        data.otherNode.moveTo(node, data.hitMode);
      }
    },
    edit: {
    },
    filter: {
        mode: "hide"
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
            that._doSearch2( qname, qsearch, isfaceted );
        }
        
    }
  })
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
      that._deleteSavedSearch(node.key, node.title, function(){
            node.remove(); 
            that._saveTreeData(); 
            
            if(that.treeviews[groupID].count()<1){
                $("#addlink"+groupID).css('display', 'block');
            }
      });
      break;
    case "addFolder":  //always create sibling folder
      /*if(!node.folder){
        node = tree.rootNode;    
      }*/
      node = node.parent;    
      node.editCreateNode("child", {title:"New folder", folder:true});
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
    delegate: "span.fancytree-node",
    menu: [
      {title: "New <kbd>[Ctrl+N]</kbd>", cmd: "addSearch", uiIcon: "ui-icon-plus" },
      {title: "New faceted", cmd: "addSearch2", uiIcon: "ui-icon-plus" },
      {title: "Edit <kbd>[F2]</kbd>", cmd: "rename", uiIcon: "ui-icon-pencil" },
      {title: "----"},
      {title: "New folder <kbd>[Ctrl+Shift+N]</kbd>", cmd: "addFolder", uiIcon: "ui-icon-folder-open" },
      {title: "Delete <kbd>[Del]</kbd>", cmd: "remove", uiIcon: "ui-icon-trash" },
      {title: "----"},
      {title: "Cut <kbd>Ctrl+X</kbd>", cmd: "cut", uiIcon: "ui-icon-scissors"},
      {title: "Copy <kbd>Ctrl-C</kbd>", cmd: "copy", uiIcon: "ui-icon-copy"},
      {title: "Paste as child<kbd>Ctrl+V</kbd>", cmd: "paste", uiIcon: "ui-icon-clipboard", disabled: true }
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

      //treedata is empty - add div - to show add links
       var tree_links = $("<div>", {id:"addlink"+groupID})
            .css({'display': treeData && treeData.length>0?'none':'block', 'padding-left':'1em'} )
            .append( $("<a>",{href:'#', text:'Add new'}).click(function(){
                    var isfaceted = false;
                    that.editSavedSearch(isfaceted?'faceted':'saved', groupID);
                    }) );
  
       this.treeviews[groupID] = tree.fancytree("getTree");

       var res = $('<div>').append(tree_links).append(tree);
  
       return res;

    },
    
    
    _define_DefaultTreeData: function(){

        var treeData = {
            rules:{title: top.HR('Rule Sets'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'rules') },
            bookmark:{title: top.HR('My Bookmarks'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'bookmark') },
            all:{title: top.HR('All Records'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'all') }
        };
        
        var groups = top.HAPI4.currentUser.usr_GroupsList;

        for (var groupID in groups)
        {
            if(groupID){
                var name = groups[groupID][1];
                treeData[groupID] = {title: name, folder: true, expanded: false, children: this._define_SVSlist(groupID) };
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
            var facet_params = null, domain2, isfaceted = false, saddition = '';
            
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
                            saddition = ' [rules]';
                        }else{
                            if(!Hul.isempty(prms.rules)){
                                saddition = ' [+rules]';
                            }
                            domain2 = prms.w;    
                            if(Hul.isempty(prms.q)) continue; //do not show saved searches without Q (rules) in this list
                        }
                }
                
                if(!domain || domain==domain2){
                    var sname = ssearches[svsID][_NAME] + saddition;                     //, iconclass:'ui-icon icon-share-alt'
                    res.push( { title:sname, folder:false, key:svsID, isfaceted:isfaceted } );    //, url:ssearches[svsID][_QUERY]
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

    _doSearch2: function(qname, qsearch, isfaceted){
        
        if ( qsearch ) {

            if(isfaceted){

                var facet_params = null;
                try {
                    facet_params = $.parseJSON(qsearch);
                }
                catch (err) {
                    facet_params = null;
                }
                if(!facet_params || !Hul.isArray(facet_params.rectypes)){
                    // Do something about the exception here
                    Hul.showMsgDlg(top.HR('Can not init faceted search. Corrupted parameters. Remove this search'), null, "Error");
                    return;
                }

                var that = this;
                //use special widget
                if($.isFunction($('body').search_faceted)){ //already loaded
                    //init faceted search
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
                    $.getScript(top.HAPI4.basePath+'apps/search_faceted.js', that._doSearch2(qname, qsearch) );
                }

            }else{

                var request = Hul.parseHeuristQuery(qsearch);
                
                if(Hul.isempty(request.q)&&!Hul.isempty(request.rules)){
                    $(this.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_APPLYRULES, [ request.rules ]); //global app event   - see resultList.js for listener
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

    // mode: saved, rules, faceted
    , editSavedSearch: function(mode, groupID, svsID, squery, node){

        var that = this;
            
        var callback = function(request) {    
                if(Hul.isempty(svsID)){     //new 
                
                    //update tree after addition - add new search to root
                    if(Hul.isnull(node)){
                        groupID = request.svs_UGrpID
                        var tree = that.treeviews[groupID];
                        node = tree.rootNode;  
                        node.folder = true;  
                    }
                    var isfaceted = (mode=='faceted');
                    node.addNode( { title:request.svs_Name, key: request.new_svs_ID, isfaceted:isfaceted }, node.folder?"child":"after" );    
                    
                    that._saveTreeData();
                    
                    $("#addlink"+groupID).css('display', 'none');
                    
                }else if(node){ //edit is called from this widget only - otherwise we have to implement search node by svsID
                
                    //edit - changed only title in treeview
                    var saddition = '';
                    if(node.data.isfaceted){
                         saddition = ' [faceted]';
                    }else{
                        var prms = Hul.parseHeuristQuery(request.svs_Query);
                        if(!Hul.isempty(prms.rules)){
                            saddition = Hul.isempty(prms.q)?' [rules]' :' [+rules]';
                        }
                    }
                    node.setTitle(request.svs_Name + saddition);
                }
           };
        
        
        if( true ) { //}!Hul.isnull(this.hSvsEdit) && $.isFunction(this.hSvsEdit)){ //already loaded     @todo - load dynamically
            
            if(Hul.isnull(svsID) && Hul.isempty(squery)){
                squery = this.currentSearch;
            }
        
            if(null == this.edit_dialog){
                this.edit_dialog = new hSvsEdit();
            }
            //this.edit_dialog.callback_method  = callback;
            this.edit_dialog.show( mode, groupID, svsID, squery, callback );
                
        }else{
            $.getScript(top.HAPI4.basePath+'apps/search/svs_edit.js', function(){ that.hSvsEdit = hSvsEdit; that.editSavedSearch(mode, groupID, callback, svsID, squery); } );
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