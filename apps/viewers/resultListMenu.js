/**
* Menu for result list
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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

$.widget( "heurist.resultListMenu", {

    // default options
    options: {
        // callbacks
        show_searchmenu:false
    },
    
    _query_request: {}, //keep current query request
    _selection: null,     //current set of selected records (not just ids)
    _collection:null,
    _collectionURL:null,

    // the widget's constructor
    _create: function() {

        var that = this;

        this._collectionURL = top.HAPI4.basePathOld + "search/saved/manageCollection.php";
        
        this.element
        .css('font-size', '0.9em')
        // prevent double click to select text
        .disableSelection();

        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu').appendTo(this.element);
        
        if(this.options.show_searchmenu) this._initMenu('Search');
        this._initMenu('Selected');
        this._initMenu('Collected');
        this._initMenu('Shared');
        //this._initMenu('Layout');
        this.divMainMenuItems.menu();

        this.divMainMenuItems.find('li').css('padding-right','3px'); //reduce gap
        
        
        //-----------------------     listener of global events
        var sevents = top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT+' '+top.HAPI4.Event.ON_REC_SEARCHSTART+' '+top.HAPI4.Event.ON_REC_SELECT; 
        /*top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT;
        if(this.options.isapplication){
            sevents = sevents + ' ' + top.HAPI4.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART + ' ' + top.HAPI4.Event.ON_REC_SELECT;
        }*/
        
        $(this.document).on(sevents, function(e, data) {

            if(e.type == top.HAPI4.Event.LOGIN || e.type == top.HAPI4.Event.LOGOUT){

                that._refresh();
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){
                
                    if(data) {
                        that._query_request = jQuery.extend({}, data); //keep current query request 
                    }
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                if(data && data.source!=that.element.attr('id')) { 
                    if(data) data = data.selection;
                    that._selection = top.HAPI4.getSelection(data, false);    
                }
            }
            //that._refresh();
        });        
        
        this._refresh();
        
        this.collectionRender();
        //setTimeout(function(){that.collectionRender();}, 2500);
        

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
    },
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if(top.HAPI4.is_logged()){
            
            this.menu_Selected.find('.logged-in-only').show();
            this.menu_Collected.find('.logged-in-only').show();
            this.btn_Shared.show();
            
            //$(this.element).find('.logged-in-only').show();//.css('visibility','visible');
        }else{
            //$(this.element).find('.logged-in-only').hide();//.css('visibility','hidden');
            this.menu_Selected.find('.logged-in-only').hide();
            this.menu_Collected.find('.logged-in-only').hide();
            this.btn_Shared.hide();
        }
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        
        $(this.document).off(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT+' '+top.HAPI4.Event.ON_REC_SEARCHSTART+' '+top.HAPI4.Event.ON_REC_SELECT);
        
        // remove generated elements
        if(this.btn_Search){
            this.btn_Search.remove();
            this.menu_Search.remove();
        }
        this.btn_Selected.remove();
        this.menu_Selected.remove();
        this.btn_Collected.remove();
        this.menu_Collected.remove();
        this.btn_Shared.reSmove();
        this.menu_Shared.remove();
        this.divMainMenuItems.remove();
    },
    
    _initMenu: function(name){
        
        var that = this;
        var myTimeoutId = -1;

        //show hide function
        var _hide = function(ele) {
                myTimeoutId = setTimeout(function() {
                    $( ele ).hide();
                }, 800);
                //$( ele ).delay(800).hide();
            },
            _show = function(ele, parent) {
                clearTimeout(myTimeoutId);
                
                $('.menu-or-popup').hide(); //hide other
                var menu = $( ele )
                //.css('width', this.btn_user.width())
                .show()
                .position({my: "left top", at: "left bottom", of: parent });
                //$( document ).one( "click", function() { menu.hide(); });
                return false;
            };
            
        var link = $('<a>',{
            text: top.HR(name), href:'#'
        });//IJ 2015-06-26 .css('font-weight','bold');
        
        if(name=='Collected'){
            this.menu_Collected_link = link;
        }
        
        this['btn_'+name] = $('<li>').append(link)
        .appendTo( this.divMainMenuItems );
            
        
        this['menu_'+name] = $('<ul>')
        .load('apps/viewers/resultListMenu'+name+'.html?t='+(new Date().getTime()), function(){  //'?t='+(new Date().getTime())
            that['menu_'+name].addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            //.addClass('ui-menu-divider-heurist')
            .menu({select: function(event, ui){ event.preventDefault(); that._menuActionHandler(ui.item.attr('id')); return false; }});
            
            if(top.HAPI4.is_logged()){
                that['menu_'+name].find('.logged-in-only').show();
            }else{
                that['menu_'+name].find('.logged-in-only').hide();
            }
            
        })
        //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
        .hide();
        
        //{select: that._menuActionHandler}
        
        this._on( this['btn_'+name], {
            mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
            mouseleave : function(){_hide(this['menu_'+name])}
        });
        this._on( this['menu_'+name], {
            mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
            mouseleave : function(){_hide(this['menu_'+name])}
        });        
        
        
    },
    

    _menuActionHandler: function(action){

          var that = this;
        
          //var action = ui.item.attr('id');     
          if(action == "menu-search-quick"){  //H4
              
                //hack $('#btn_search_assistant').click();
                var app = appGetWidgetByName('search');  //appGetWidgetById('ha10'); 
                if(app && app.widget){
                    $(app.widget).search('showSearchAssistant');
                }
              
          }else if(action == "menu-search-advanced"){ //H3
              
                //call H3 search builder
                var q = "", 
                    that = this;
                if(!Hul.isnull(this._query_request) && !Hul.isempty(this._query_request.q)){
                    q ="&q=" + encodeURIComponent(this._query_request.q);
                }
                var url = top.HAPI4.basePathOld+ "search/queryBuilderPopup.php?db=" + top.HAPI4.database + q;
                
                Hul.showDialog(url, { callback: 
                    function(res){
                        if(!Hul.isempty(res)) {
                                that.reloadSearch(res);
                        }
                    }});
              
          }else if(action == "menu-search-save"){  //H4
              
                var  app = appGetWidgetByName('svs_list');  //appGetWidgetById('ha13');
                if(app && app.widget){
                    $(app.widget).svs_list('editSavedSearch', 'saved'); //call public method editRules
                    //$(app.widget).search_links('editSavedSearch', null, null, 'saved'); //call method editSavedSearch - save current search
                }

          }else if(action == "menu-search-rulebuilder"){  

                var  app = appGetWidgetByName('svs_list');  //appGetWidgetById('ha13');
                if(app && app.widget){
                    $(app.widget).svs_list('editSavedSearch', 'rules'); //call public method editRules
                    // $(app.widget).search_links('editRules', null); //call public method editRules
                }
                
                
          }else if(action == "menu-selected-select-all"){  
          
                this.selectAll();                
              
          }else if(action == "menu-selected-select-none"){                  

                this.selectNone();                
              
          }else if(action == "menu-selected-select-show"){  //show selection as separate search

                this.selectShow();   
                
          }else if(action == "menu-selected-relateto"){  //show add relation dialog

                this.addRelationshipsPopup();   

          }else if(action == "menu-selected-merge"){  //show add relation dialog

                this.fixDuplicatesPopup();   
              
          }else if(action == "menu-selected-tag"){                  
              
                this.addRemoveTagsPopup(true);
                
          }else if(action == "menu-selected-wgtags"){                  
              
                this.addRemoveKeywordsPopup();
                
          }else if(action == "menu-selected-bookmark"){                  
                
                this.addBookmarks();

          }else if(action == "menu-selected-unbookmark"){                  
    
                this.deleteBookmarks();
                
          }else if(action == "menu-selected-rate"){                  
              
                this.setRatingsPopup();
                
          }else if(action == "menu-selected-email") {
              
              this.openEmailForm();
                
          }else if(action == "menu-selected-ownership"){                  
              
                this.setWorkgroupPopup();

          }else if(action == "menu-selected-delete"){                  
              
                this.deleteRecords();
                
          }else if(action == "menu-selected-notify"){                  
              
                this.notificationPopup();
                
          }else if(action == "menu-selected-value-add"){                  
              
                this.addDetailPopup();

          }else if(action == "menu-selected-value-replace"){                  
              
                this.replaceDetailPopup();

          }else if(action == "menu-selected-value-delete"){                  
              
                this.deleteDetailPopup();

          }else if(action == "menu-collected-add"){                  
              
              this.collectionAdd();
                
          }else if(action == "menu-collected-del"){                  

              this.collectionDel();

              
          }else if(action == "menu-collected-clear"){                  

              this.collectionClear();
              
          }else if(action == "menu-collected-show"){                  

              this.collectionShow();
              
          }else if(action == "menu-collected-save"){                  
              
              this.collectionSave();
              
          }
          
          
        
    },
    
     /**    H3
     * action - name of action
     * _data - array of parameters
     * cbAction - callback
     */
    executeAction: function(action, _data, cbAction){

            var that = this;
        
            function _requestCallBack(context) {

                if(!Hul.isnull(context)){

                    if(context.problem){
                        Hul.showMsgErr(context.problem);
                    }else if(context.none){
                        Hul.showMsgFlash(context.none);
                    }else if(context.execute){
                        var fname = context.execute.shift();
                        var args = context.execute;
                        if(fname=="addRemoveTagsPopup"){
                             that.addRemoveTagsPopup.apply(that, args);
                        }
                        
                        //$(that).resultListMenu(fname, args);
                        /*$.each($(that).data(), function(key, val) {
                            if ($.isFunction(val[fname])) {
                              $this[key].apply($this, args);
                              // break out of the loop
                              return false;
                            }
                        });*/                       
                        
                        //top.HEURIST.util.executeFunctionByName("that."+fname, window, context.execute);
                    }else if(context.ok){
                        
                        Hul.showMsgFlash(context.ok);
                        that.reloadSearch();
                        
                        /*Hul.showMsgDlg(context.ok+
                        "<br><br>Information changes will be visible on re-run the current search."+
                        "<br>Reloading will reset filters and selection."+
                        "<br>'Yes' to re-run, 'No' to leave display as-is", 
                            function(){
                                 that._query_request.source = this.element.attr('id');
                                 top.HAPI4.RecordMgr.search(that._query_request, $(that.document));
                            });*/
                    }
                }
            }
                                                             //encodeURIComponent(JSON.stringify(_data))
            var str = JSON.stringify(_data);
            var baseurl = top.HAPI4.basePathOld + "search/actions/actionHandler.php";   //h3 action handler
            
            /* it can not prase reponse properly
            var request = {db:top.HAPI4.database, data: str, action: action }
            $.ajax({
                url: baseurl,
                type: "POST",
                data: request,
                dataType: "json",
                cache: false,
                error: function(jqXHR, textStatus, errorThrown ) {
                    var context = {problem:jqXHR.responseText};
                    _requestCallBack();
                },
                success: (typeof cbAction == "function" ? cbAction : _requestCallBack)
            });*/
            
            
            var callback = (typeof cbAction == "function" ? cbAction : _requestCallBack);
            var params = "db="+top.HAPI4.database+"&action="+action+"&data=" + encodeURIComponent(str);
            top.HEURIST.util.getJsonData(baseurl, callback, params);
            
            
    },
    
    reloadSearch: function(query){

            if(!Hul.isempty(query)){
                this._query_request.q = query;
            }
                                
            this._query_request.id = null;
            this._query_request.source = this.element.attr('id');
            top.HAPI4.SearchMgr.doSearch( this, this._query_request );
    },

    getSelectionIds: function(msg, limit){
        
        var recIDs_list = [];
        if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }

        if (recIDs_list.length == 0 && !Hul.isempty(msg)) {
            Hul.showMsgDlg(msg);
            return null;
        }else if (limit>0 && recIDs_list.length > limit) {
            Hul.showMsgDlg("The number of selected records is above the limit in "+limit);   
        }else{            
            return recIDs_list;
        }
        
    },
    
//-------------------------------------- ADD, REMOVE TAGS, BOOKMARKS. RATING -------------------------------    
    
    
    /**
    * Personal tags
    */
    addRemoveTagsPopup: function(reload, recID, bkmkID) {

        var that = this;
        var recIDs_list = [];
        var bkmkIDs_list = [];

        if(recID || bkmkID){
            if(recID) recIDs_list = Hul.isArray(recID)?recID:[recID];
            if(bkmkID) bkmkIDs_list = Hul.isArray(bkmkID)?bkmkID:[bkmkID];
        }else if (that._selection!=null) {
            recIDs_list = that._selection.getIds();
            bkmkIDs_list = that._selection.getBookmarkIds();
        }
        if(recIDs_list.length == 1){
            recID = recIDs_list[0];
        }


        var hasRecordsNotBkmkd = false;
        if (recIDs_list.length == 0  &&  bkmkIDs_list.length == 0) {
            //nothing selected
            Hul.showMsgDlg("Select at least one record to add tags");
            return;
        }else if (recIDs_list.length > bkmkIDs_list.length) {
            // at least one unbookmarked record selected
            hasRecordsNotBkmkd = true;
        }
        
        var url = top.HAPI4.basePathOld+ "records/tags/updateTagsSearchPopup.php?show-remove?db=" + top.HAPI4.database + (recID?"&recid="+recID:"");
        
        Hul.showDialog(url, { callback:
         
                        function(add, tags) {//options
                            if (! tags) { //no tags added
                                if (reload) {
                                    //@todo top.HEURIST.search.executeQuery(top.HEURIST.currentQuery_main);
                                }
                                return;
                            }

                            var saction = (add ? (hasRecordsNotBkmkd? "bookmark_and":"add") : "remove") + "_tags";

                            var _data = {bkmk_ids:bkmkIDs_list, rec_ids: recIDs_list, tagString:tags, reload:(reload ? "1" : "")};

                            that.executeAction(saction, _data);

                        }
            });
            
    },
    
    /**
    * workgroup tags
    */
    addRemoveKeywordsPopup: function() {
        
        var recIDs_list = this.getSelectionIds("Select at least one record to add / remove workgroup tags");
        if(Hul.isempty(recIDs_list)) return;

        var that = this;
        
        this.convertGroupsForH3();
        
        var url = top.HAPI4.basePathOld+ "records/tags/editUsergroupTagsPopup.html?db=" + top.HAPI4.database;
        
        Hul.showDialog(url, { 
                        width: 800,
                        callback: function(add, wgTag_ids) {//options
                            if (! wgTag_ids) return;

                            var saction = (add ? "add" : "remove") + "_wgTags_by_id";
                            var _data = {rec_ids:recIDs_list, wgTag_ids:wgTag_ids};

                            that.executeAction(saction, _data);
                        }
            });
    },

    getBookmarkMessage: function(operation) {
            return "Select at least one bookmark " + operation
            + (this._query_request.w=="all"
                ? "<br>(operation can only be carried out on bookmarked records, shown by a red star )"
                : "");
    },

    convertGroupsForH3: function() {

            var groups = top.HAPI4.currentUser.usr_GroupsList;
            var workgroups = []; 
            var workgroups2 = {}; 
            for (var idx in groups)
            {
                if(idx){
                    var groupID = idx;
                    var name = groups[idx][1];
                    
                    if(!Hul.isnull(name))
                    {
                        workgroups.push(groupID);
                        workgroups2[groupID] = {name: name};
                    }
                }
            }
            top.HEURIST.user.workgroups = workgroups;
            top.HEURIST.workgroups = workgroups2;
    },
    
    openEmailForm: function() {
        // Selection check
        var ids = this.getSelectionIds("Select at least one record to e-mail");
        if(Hul.isempty(ids)) {
            return;  
        }
        
        // Open URL
        var url = top.HAPI4.basePath+ "page/send_email.php?db=" + top.HAPI4.database;
        top.HEURIST.user.selectedRecordIds = ids;
        Hul.showDialog(url, { width:500, height:600 });
        
    },

    setRatingsPopup: function(bkmkID) {

        var bkmkIDs_list = [], 
            that = this;
            
        if(bkmkID){
            bkmkIDs_list = [bkmkID];
        }else if (this._selection!=null) {
            bkmkIDs_list = this._selection.getBookmarkIds();
        }

        if (bkmkIDs_list.length == 0) {
            Hul.showMsgDlg(this.getBookmarkMessage("to set ratings"));
            return;
        }

        var url = top.HAPI4.basePathOld+ "search/actions/setRatingsPopup.php?db=" + top.HAPI4.database;

        Hul.showDialog(url, { 
                        width:250, height:220,
                        callback: function(value) {//options
                            if(Number(value)>=0){
                               var _data = {bkmk_ids:bkmkIDs_list, ratings: value};
                               that.executeAction('set_ratings', _data);
                            }
                        }
            });
        
    },
    
    addBookmarks: function() {
        
        var recIDs_list = this.getSelectionIds("Select at least one record to bookmark");
        if(Hul.isempty(recIDs_list)) return;

        this.executeAction( "bookmark_reference", {rec_ids: recIDs_list} );
    },

    deleteBookmarks: function(bkmkID) {
        
        var bkmkIDs_list = [],
            that = this; 
        if(bkmkID){
            bkmkIDs_list = [bkmkID];
        }else if (this._selection!=null) {
            bkmkIDs_list = this._selection.getBookmarkIds();
        }
        
        var sMsg = "";

        if (bkmkIDs_list.length == 0) {
            Hul.showMsgDlg("Select at least one bookmark to delete");
            return;
        }else if (bkmkIDs_list.length == 1) {
            sMsg = "Do you want to delete one bookmark?<br>(this ONLY removes the bookmark from your resources,<br>it does not delete the record entry)";
        } else {
            sMsg = "Do you want to delete " + bkmkIDs_list.length + " bookmarks?<br>(this ONLY removes the bookmarks from your resources,<br>it does not delete the record entries)";
        }
        
        Hul.showMsgDlg(sMsg, function(){
            that.executeAction( "delete_bookmark", {bkmk_ids: bkmkIDs_list} );    
        })
    },

    setWorkgroupPopup: function() {

        var recIDs_list = this.getSelectionIds("Select at least one record to set workgroup ownership and visibility");
        if(Hul.isempty(recIDs_list)) return;
        
        var that = this;
            
        this.convertGroupsForH3();
        
        var url = top.HAPI4.basePathOld+ "records/permissions/setRecordOwnership.html?db=" + top.HAPI4.database;
        
        Hul.showDialog(url, { height:300, width:650, 
                    callback:function(wg, viewable, hidden, pending) {
                        if (wg === undefined) return;

                        var _data = {rec_ids: recIDs_list,
                            wg_id  : wg,
                            vis : (hidden ? "hidden" :
                                viewable ? "viewable" :
                                pending ? "pending" : "public") };
                        that.executeAction( "set_wg_and_vis", _data );
                    }
            });
    },

//-------------------------------------- SELCT ALL, NONE, SHOW -------------------------------    
    
    selectAll: function(){
         this._selection = top.HAPI4.getSelection('all', false);    
         $(this.document).trigger(top.HAPI4.Event.ON_REC_SELECT, {selection:"all", source:this.element.attr('id')} );
    },

    selectNone: function(){
         this._selection = null;
         $(this.document).trigger(top.HAPI4.Event.ON_REC_SELECT, {selection:null, source:this.element.attr('id')} );
    },

    selectShow: function(){
        if(this._selection!=null){
            var recIDs_list = this._selection.getIds();    
            if (recIDs_list.length > 0) {
                var url = top.HAPI4.basePath + "?db=" + top.HAPI4.database + "&q=ids:"+recIDs_list.join(',');        
                window.open(url, "_blank");
            }
        }
    },
    
//-------------------------------------- COLLECTIONS -------------------------------    
    
    collectionAdd: function(){

        var recIDs_list = this.getSelectionIds("Select at least one record to add to collection basket");
        if(Hul.isempty(recIDs_list)) return;
        
        var params = {db:top.HAPI4.database, fetch:1, add:recIDs_list.join(",")};
        
        Hul.sendRequest(this._collectionURL, params, this, this.collectionOnUpdate);

        this.selectNone();
    },
    
    collectionDel: function(){
        
        var recIDs_list = this.getSelectionIds("Select at least one record to remove from collection basket");
        if(Hul.isempty(recIDs_list)) return;
        
        var params = {db:top.HAPI4.database, fetch:1, remove:recIDs_list.join(",")};
        
        Hul.sendRequest(this._collectionURL, params, this, this.collectionOnUpdate);

        this.selectNone();
    },
    
    collectionClear: function(){

        var params = {db:top.HAPI4.database, clear:1};
        
        Hul.sendRequest(this._collectionURL, params, this, this.collectionOnUpdate);
    },
    
    collectionShow: function(){

        if(!Hul.isempty(this._collection)){
      
            if(true){      
                var url = top.HAPI4.basePath + "?db=" + top.HAPI4.database + "&q=ids:"+this._collection.join(',');        
                window.open(url, "_blank");
            }else{
                this._query_request.w = 'all';
                that.reloadSearch('ids:'+this._collection.join(","));                                
            }
        }
        
    },
    
    collectionSave: function(){

        if(!Hul.isempty(this._collection)){
            
            var  app = appGetWidgetByName('svs_list');  //appGetWidgetById('ha13');
            if(app && app.widget){
                //call method editSavedSearch - save collection as search

                // mode, groupID, svsID, squery
                $(app.widget).svs_list('editSavedSearch', 'saved', null, null, 'ids:'+this._collection.join(","));
            }
        }
        
    },
    collectionOnUpdate: function(that, results) {
        if(!Hul.isnull(results)){
            if(results.status == top.HAPI4.ResponseStatus.UNKNOWN_ERROR){
                Hul.showMsgErr(results.message);        
            }else{
                that._collection = Hul.isempty(results.ids)?[]:results.ids;
                that.collectionRender();
                //top.HEURIST.search.collectCount = results.count;
                //top.HEURIST.search.collection = results.ids;
            }
        }
    },

    collectionRender: function() {
        if(Hul.isnull(this._collection))
        {
            var params = {db:top.HAPI4.database, fetch:1};
            Hul.sendRequest(this._collectionURL, params, this, this.collectionOnUpdate);
        }else{
            //top.HR('Collected')
            this.menu_Collected_link.html( 'Collected' + (this._collection.length>0?':'+this._collection.length:''));
        }
    },

//-------------------------------------- VARIOUS: DELETE RECORD, SEND NOTIFICATION -------------------------------    

    deleteRecords: function() {
        
        var recIDs_list = this.getSelectionIds("Select at least one record to delete");
        if(Hul.isempty(recIDs_list)) return;
        
        var that = this;        
        var url = top.HAPI4.basePathOld+ "search/actions/deleteRecordsPopup.php?db=" + top.HAPI4.database;
        
        Hul.showDialog(url, { 
                    onpopupload: function(frame){ //assign list of records to be deleted to POST form, to avoid GET length limitation
                            var ele = frame.contentDocument.getElementById("ids");
                            if(Hul.isempty(recIDs_list) || Hul.isnull(ele)) return;
                            ele.value = recIDs_list.join(",");
                            frame.contentDocument.forms[0].submit();
                        },
                    callback: function(context) {
                            if (context==="reload") { //something was deleted - refresh
                                that.reloadSearch();                                
                            }
                        }
            });
    },

    notificationPopup: function() {
        
        var recIDs_list = this.getSelectionIds(this.getBookmarkMessage("for notification"), 1000);
        if(Hul.isempty(recIDs_list)) return;

        recIDs_list = recIDs_list.join(",");
        var url = top.HAPI4.basePathOld+ "search/actions/sendNotificationsPopup.php?h4=1&db=" + top.HAPI4.database + "&bib_ids=\""+recIDs_list+"\"";
        
        Hul.showDialog(url, {height:230, title: top.HR('Notification')} );
    },
        
    
//-------------------------------------- RELATION, MERGE -------------------------------    
    
    addRelationshipsPopup: function(){
        
        var recIDs_list = this.getSelectionIds("Select at least one record to add relationships");
        if(Hul.isempty(recIDs_list)) return;

        var that = this;        
        
        if(!top.HEURIST.rectypes){
            $.getScript(top.HAPI4.basePathOld + 'common/php/loadCommonInfo.php?db='+top.HAPI4.database, function(){ that.addRelationshipsPopup(); } );
            return
        }
        
        var url = top.HAPI4.basePathOld + "search/actions/setRelationshipsPopup.html?db=" + top.HAPI4.database;
        
        Hul.showDialog(url, { 
                    onpopupload: function(frame){
                            var ele = frame.contentDocument.getElementById("record-count");
                            if(Hul.isempty(recIDs_list) || Hul.isnull(ele)) return;
                            
                            ele.innerHTML = recIDs_list.length;
                            ele = frame.contentDocument.getElementById("record-selected");
                            ele.innerHTML = recIDs_list.join(",");
                        },
                    callback: function(context) {
                              that.reloadSearch();                                
                        }
            });
        
    },
    
    fixDuplicatesPopup: function(){
        
        
        var recIDs_list = this.getSelectionIds(null);
        if(Hul.isempty(recIDs_list) || recIDs_list.length<2){
            Hul.showMsgDlg("Select at least two records to identify/merge duplicate records");
            return;
        }

        var that = this;        
        var url = top.HAPI4.basePathOld + "admin/verification/combineDuplicateRecords.php?bib_ids="+recIDs_list.join(",")+"&db=" + top.HAPI4.database;
        
        Hul.showDialog(url, { 
                    callback: function(context) {
                              that.reloadSearch();                                
                        }
            });
        
    },

    
//-------------------------------------- ADD, REPALCE, DELETE FIELD VALUES -------------------------------    

    addDetailPopup: function() {
        
        var recIDs_all = top.HAPI4.getSelection("all", true);
        if (Hul.isempty(recIDs_all)) {
            Hul.showMsgDlg("No results found. Please run a query with at least one result record. You can use selction to direct your change.");
            return;
        }
        var recIDs_sel = this.getSelectionIds();
        
        var that = this;   
        
        if(!top.HEURIST.rectypes){
            $.getScript(top.HAPI4.basePathOld + 'common/php/loadCommonInfo.php?db='+top.HAPI4.database, function(){ that.addDetailPopup(); } );
            return
        }
             
        var url = top.HAPI4.basePathOld+ "search/actions/addDetailPopup.html?db=" + top.HAPI4.database ;

        //substitutes
        top.HEURIST.search4 = {};
        top.HEURIST.search4.recids_all = recIDs_all;
        top.HEURIST.search4.recids_sel = recIDs_sel;
        top.HEURIST.search4.rectypes =  top.HAPI4.currentRecordset.getRectypes();
        top.HEURIST.search4.executeAction = this.executeAction;
        
        Hul.showDialog(url);
    },

    replaceDetailPopup: function() {
        
        var recIDs_all = top.HAPI4.getSelection("all", true);
        if (Hul.isempty(recIDs_all)) {
            Hul.showMsgDlg("No results found. Please run a query with at least one result record. You can use selction to direct your change.");
            return;
        }
        var recIDs_sel = this.getSelectionIds();
        
        var that = this;   
        
        if(!top.HEURIST.rectypes){
            $.getScript(top.HAPI4.basePathOld + 'common/php/loadCommonInfo.php?db='+top.HAPI4.database, function(){ that.replaceDetailPopup(); } );
            return
        }
             
        var url = top.HAPI4.basePathOld+ "search/actions/replaceDetailPopup.html?db=" + top.HAPI4.database ;

        //substitutes
        top.HEURIST.search4 = {};
        top.HEURIST.search4.recids_all = recIDs_all;
        top.HEURIST.search4.recids_sel = recIDs_sel;
        top.HEURIST.search4.rectypes =  top.HAPI4.currentRecordset.getRectypes();
        top.HEURIST.search4.executeAction = this.executeAction;
        
        Hul.showDialog(url);
    },

    deleteDetailPopup: function() {
        
        var recIDs_all = top.HAPI4.getSelection("all", true);
        if (Hul.isempty(recIDs_all)) {
            Hul.showMsgDlg("No results found. Please run a query with at least one result record. You can use selction to direct your change.");
            return;
        }
        var recIDs_sel = this.getSelectionIds();
        
        var that = this;   
        
        if(!top.HEURIST.rectypes){
            $.getScript(top.HAPI4.basePathOld + 'common/php/loadCommonInfo.php?db='+top.HAPI4.database, function(){ that.deleteDetailPopup(); } );
            return
        }
             
        var url = top.HAPI4.basePathOld+ "search/actions/deleteDetailPopup.html?db=" + top.HAPI4.database ;

        //substitutes
        top.HEURIST.search4 = {};
        top.HEURIST.search4.recids_all = recIDs_all;
        top.HEURIST.search4.recids_sel = recIDs_sel;
        top.HEURIST.search4.rectypes =  top.HAPI4.currentRecordset.getRectypes();
        top.HEURIST.search4.executeAction = this.executeAction;
        
        Hul.showDialog(url);
    },
        
    
});
