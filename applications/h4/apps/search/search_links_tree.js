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

    },

    currentSearch: null,
    hSvsEdit: null,

    // the constructor
    // create filter+button and div for tree
    _create: function() {

        var that = this;

        this.search_tree = $( "<div>" ).appendTo( this.element );
        this.search_faceted = $( "<div>" ).appendTo( this.element ).hide();
        
        this.filter = $( "<div>" ).appendTo( this.search_tree );
        
        $('<input name="search" placeholder="Filter...">').css('width','100px').appendTo(this.filter);
        this.btn_reset = $( "<div>" )
        .appendTo( this.filter )
        .button({icons: {
                primary: "ui-icon-close"
            },
            title: top.HR("Reset"),
            text:false})
        .css({'font-size': '0.8em','height':'18px','margin-left':'2px'})
        
        this._on( this.btn_reset, { click: "doFilterReset" });
        
        this.tree = $( "<div>" ).appendTo( this.search_tree );
        
        this.edit_dialog = null;

        //global listener
        $(this.document).on(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT, function(e, data) {
            that._refresh();
        });
        $(this.document).on(top.HAPI4.Event.ON_REC_SEARCHSTART, function(e, data){
            that.currentSearch = data;
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
            this._updateTree();
        }

    },

    //
    // redraw accordeon with the list of saved searches, tags, faceted searches
    //
    _updateTree: function()
    {
        var that = this;
        //verify that all required libraries have been loaded
        if(!$.isFunction($('body').fancytree)){        //jquery.fancytree-all.min.js                           
            $.getScript(top.HAPI4.basePath+'ext/fancytree/jquery.fancytree-all.min.js', function(){ that._updateTree(); } );
            return;
        }else if(!$.ui.fancytree._extensions["dnd"]){
        //    $.getScript(top.HAPI4.basePath+'ext/fancytree/src/jquery.fancytree.dnd.js', function(){ that._updateTree(); } );
            alert('dnd ext for tree not loaded')
            return;
        }

  var SOURCE = this._define_DefaultTreeData(),
  CLIPBOARD = null;
        
        
        var islogged = (top.HAPI4.currentUser.ugr_ID>0);

        //this.user_groups.hide().empty();

        if(islogged){

this.tree.fancytree({
    checkbox: false,
    titlesTabbable: false,     // Add all node titles to TAB chain
    source: SOURCE,

    extensions: ["edit", "dnd"],

    dnd: {
      preventVoidMoves: true,
      preventRecursiveMoves: true,
      autoExpandMS: 400,
      dragStart: function(node, data) {
        return true;
      },
      dragEnter: function(node, data) {
        // return ["before", "after"];
        return true;
      },
      dragDrop: function(node, data) {
        data.otherNode.moveTo(node, data.hitMode);
      }
    },
    edit: {
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
  }).on("nodeCommand", function(event, data){
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
    case "rename":
    
        if(!node.folder && node.key>0){
            that.editSavedSearch(node.data.isfaceted?'faceted':'saved', function(request) {    
                   //node.title = request.svs_Name;
                   node.setTitle(request.svs_Name);
                   //node.applyPatch({titel: request.svs_Name});
            }, node.key);
        }else{
            node.editStart();      
        }
      
      break;
    case "remove":
      that._deleteSavedSearch(node.key, function(){node.remove();});
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

      that.editSavedSearch(isfaceted?'faceted':'saved', function(request) {    
          //update tree after addition
          node.editCreateNode( node.folder?"child":"after", { title:request.svs_Name, key: request.new_svs_ID, isfaceted:isfaceted } );    
      });
      
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
  this.tree.contextmenu({
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
      that.tree.contextmenu("enableEntry", "paste", !!CLIPBOARD);
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
            

        }else{

            /* @todo for public/nonlogged mode
            this.mode_selector.hide();
            this.user_groups_container.css('top',0);
            this.user_groups
            .append(
                $('<div>')
                .append(this._defineHeader(top.HR('Predefined searches'), null))
                .append( this._define_GroupContent( top.HAPI4.currentUser.ugr_ID) ))
            .show();
             */
        }

    },
    
    
    _define_DefaultTreeData: function(){

        var treeData = [
            {title: top.HR('My Bookmarks'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'bookmark') },
            {title: top.HR('All Records'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'all') },
            {title: top.HR('Rules'), folder: true, expanded: true, children: this._define_SVSlist(top.HAPI4.currentUser.ugr_ID, 'rules') }
        ];
        
        var groups = top.HAPI4.currentUser.usr_GroupsList;

        for (var groupID in groups)
        {
            if(groupID){
                var name = groups[groupID][1];
                treeData.push( {title: name, folder: true, expanded: false, children: this._define_SVSlist(groupID) } );
            }
        }
        return treeData;
        
    },

    //
    /**
    * create list of saved searches for given user/group
    * domain - all or bookmark
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
        
        var facet_params, domain2, isfaceted = false;

        for (var svsID in ssearches)
        {
            if(svsID && ssearches[svsID][_GRPID]==ugr_ID){


                try {
                    facet_params = $.parseJSON(ssearches[svsID][_QUERY]);
                    //this is faceted search
                    domain2 = facet_params.domain;
                    isfaceted = true;
                }
                catch (err) {
                        // this is saved search
                        var prms = Hul.parseHeuristQuery(ssearches[svsID][_QUERY]);
                        //var qsearch = prms[0];
                        if(Hul.isempty(prms.q)&&!Hul.isempty(prms.rules)){
                            domain2 = 'rules';
                        }else{
                            domain2 = prms.w;    
                            if(Hul.isempty(prms.q)) continue; //do not show saved searches without Q (rules) in this list
                        }
                }
                
                if(!domain || domain==domain2){
                    res.push( { title: ssearches[svsID][_NAME], folder:false, key:svsID, isfaceted:isfaceted } );    //, url:ssearches[svsID][_QUERY]
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

                var facet_params;
                try {
                    facet_params = $.parseJSON(qsearch);
                }
                catch (err) {
                    // Do something about the exception here
                    Hul.showMsgDlg(top.HR('Can not init faceted search. Corrupted parameters. Remove this search'), null, "Error");
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
    
    _deleteSavedSearch: function(svsID, callback){


        var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];
        if(!svs) return;

        Hul.showMsgDlg(top.HR("Delete? Please confirm"),  function(){

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

    , _updateAfterSave: function(request, mode){

        if(this.currentMode == mode)
        {

            if( parseInt(request.svs_ID) > 0 ){
                $('#svs-'+request.svs_ID).html(request.svs_Name);  //change name on edit
            }else{

                var prms = Hul.parseHeuristQuery(request.svs_Query);
                var domain = (Hul.isempty(prms.q)&&!Hul.isempty(prms.rules))?'rules':request.domain;

                $('#svsu-'+request.svs_UGrpID+'-'+domain).append( this._add_SVSitem(request.svs_Name, request.new_svs_ID) );
            }

        }
    }
    
    , editSavedSearch: function(mode, callback, svsID, squery){

        if( true ) { //}!Hul.isnull(this.hSvsEdit) && $.isFunction(this.hSvsEdit)){ //already loaded
            
            if(Hul.isnull(svsID) && Hul.isempty(squery)){
                squery = this.currentSearch;
            }
        
            if(null == this.edit_dialog){
                this.edit_dialog = new hSvsEdit();
            }
            
            this.edit_dialog.showDialog( mode, callback, svsID, squery  );
                
        }else{
            var that = this;
            $.getScript(top.HAPI4.basePath+'apps/search/svs_edit.js', function(){ that.hSvsEdit = hSvsEdit; that.editSavedSearch(mode, callback, svsID, squery); } );
        }
    
    },
    

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        if(this.edit_dialog) {
            this.edit_dialog.remove();
        }

        this.btn_reset.remove(); 
        this.filter.remove(); 
        this.tree.remove(); 
        
        this.search_tree.remove(); 
        this.search_faceted.remove(); 
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