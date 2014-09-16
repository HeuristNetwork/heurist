/**
* Menu for result list
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


$.widget( "heurist.resultListMenu", {

    // default options
    options: {
        // callbacks
    },
    
    _query_request: {}, //keep current query request
    _selection: null,     //current set of selected records

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        .css('font-size', '0.9em')
        // prevent double click to select text
        .disableSelection();

        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu').appendTo(this.element);
        
        this._initMenu('Search');
        this._initMenu('Selected');
        this._initMenu('Collected');
        this._initMenu('Layout');
        this.divMainMenuItems.menu();

        
        //-----------------------     listener of global events
        var sevents = top.HAPI4.Event.ON_REC_SEARCHSTART+' '+top.HAPI4.Event.ON_REC_SELECT; 
        /*top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT;
        if(this.options.isapplication){
            sevents = sevents + ' ' + top.HAPI4.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART + ' ' + top.HAPI4.Event.ON_REC_SELECT;
        }*/

        $(this.document).on(sevents, function(e, data) {

            if(e.type == top.HAPI4.Event.LOGIN || e.type == top.HAPI4.Event.LOGOUT){

                that._refresh();
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){
                
                that._query_request = data;  //keep current query request 
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                    if(data) data = data.selection;
                
                   if( (typeof data.isA == "function") && data.isA("hRecordSet") ){
                       that._selection = data;
                   }else{
                       that._selection = null
                   }
                
            }
            //that._refresh();
        });        
        
        this._refresh();

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

        if(top.HAPI4.currentUser.ugr_ID>0){
            $(this.element).find('.logged-in-only').css('visibility','visible');
        }else{
            $(this.element).find('.logged-in-only').css('visibility','hidden');
        }
                
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        this.btn_Search.remove();
        this.menu_Search.remove();
        this.btn_Selected.remove();
        this.menu_Selected.remove();
        this.btn_Collected.remove();
        this.menu_Collected.remove();
        this.btn_Layout.remove();
        this.menu_Layout.remove();
        this.divMainMenuItems.remove();
    },
    
    _initMenu: function(name){
        
        var that = this;

        //show hide function
        var _hide = function(ele) {
                $( ele ).delay(700).hide();
            },
            _show = function(ele, parent) {
                $('.menu-or-popup').hide(); //hide other
                var menu = $( ele )
                //.css('width', this.btn_user.width())
                .show()
                .position({my: "left top", at: "left bottom", of: parent });
                //$( document ).one( "click", function() { menu.hide(); });
                return false;
            };
            
        var link = $('<a>',{
            text: name, href:'#'
        });
        
        this['btn_'+name] = $('<li>').append(link)
        .appendTo( this.divMainMenuItems );
            
        
        this['menu_'+name] = $('<ul>')
        .load('apps/search/resultListMenu'+name+'.html?t='+(new Date().getTime()), function(){
            that['menu_'+name].addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( that.document.find('body') )
            .menu({select: function(event, ui){ that._menuActionHandler(ui.item.attr('id')); }})
        })
        //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
        .hide();
        
        {select: that._menuActionHandler}
        
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
                if(!top.HEURIST4.util.isnull(this._query_request) && !top.HEURIST4.util.isempty(this._query_request.q)){
                    q ="&q=" + encodeURIComponent(this._query_request.q);
                }
                var url = top.HEURIST.basePath+ "search/queryBuilderPopup.php?db=" + top.HAPI4.database + q;
                
                top.HEURIST4.util.showDialog(url, { callback: 
                    function(q){
                        if(!top.HEURIST4.util.isempty(q)) {
                            that._query_request.q = q;
                            //that._query_request.w = 'a';
                            that._query_request.orig = 'rec_list';
                            top.HAPI4.RecordMgr.search(that._query_request, $(that.document));
                        }
                    }});
              
          }else if(action == "menu-search-save"){  //H4
              
                var  app = appGetWidgetByName('search_links');  //appGetWidgetById('ha13');
                if(app && app.widget){
                    $(app.widget).search_links('editSavedSearch', null, 'all');
                }

          }else if(action == "menu-selected-select-all"){  
          
                this.selectAll();                
              
          }else if(action == "menu-selected-select-none"){                  

                this.selectNone();                
              
          }else if(action == "menu-selected-select-show"){  //show selection as separate search

                this.selectShow();                
              
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
                
          }else if(action == "menu-selected-ownership"){                  
              
                this.setWorkgroupPopup();

          }else if(action == "menu-selected-delete"){                  
              
                this.deleteRecords();
                
          }else if(action == "menu-selected-notify"){                  
              
                this.notificationPopup();
                
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

                if(!top.HEURIST4.util.isnull(context)){

                    if(context.problem){
                        top.HEURIST4.util.showMsgErr(context.problem);
                    }else if(context.none){
                        top.HEURIST4.util.showMsgErr(context.none);
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
                        
                        top.HEURIST4.util.showMsgDlg(context.ok+
                        "<br><br>Information changes will be visible on re-run the current search."+
                        "<br>Reloading will reset filters and selection."+
                        "<br>'Yes' to re-run, 'No' to leave display as-is", 
                            function(){
                                 that._query_request.orig = 'rec_list';
                                 top.HAPI4.RecordMgr.search(that._query_request, $(that.document));
                            });
                    }
                }
            }
                                                             //encodeURIComponent(JSON.stringify(_data))
            var str = JSON.stringify(_data);
            var url = top.HAPI4.basePathOld + "search/actions/actionHandler.php";
            var request = {db:top.HAPI4.database, data: str, action: action }
            
            $.ajax({
                url: url,
                type: "POST",
                data: request,
                dataType: "json",
                cache: false,
                error: function(jqXHR, textStatus, errorThrown ) {
                    var context = {problem:jqXHR.responseText};
                    _requestCallBack();
                },
                success: (typeof cbAction == "function" ? cbAction : _requestCallBack)
            });
            
    },
    
    /**
    * Personal tags
    */
    addRemoveTagsPopup: function(reload, recID, bkmkID) {

        var that = this;
        var recIDs_list = [];
        var bkmkIDs_list = [];

        if(recID || bkmkID){
            if(recID) recIDs_list = top.HEURIST4.util.isArray(recID)?recID:[recID];
            if(bkmkID) bkmkIDs_list = top.HEURIST4.util.isArray(bkmkID)?bkmkID:[bkmkID];
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
            top.HEURIST4.util.showMsgDlg("Select at least one record to add tags");
            return;
        }else if (recIDs_list.length > bkmkIDs_list.length) {
            // at least one unbookmarked record selected
            hasRecordsNotBkmkd = true;
        }
        
        var url = top.HAPI4.basePathOld+ "records/tags/updateTagsSearchPopup.php?show-remove?db=" + top.HAPI4.database + (recID?"&recid="+recID:"");
        
        top.HEURIST4.util.showDialog(url, { callback:
         
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
        
        var recIDs_list = [],
            that = this;
        if (that._selection!=null) {
            recIDs_list = that._selection.getIds();
        }
        if (recIDs_list.length == 0) {
            top.HEURIST4.util.showMsgDlg("Select at least one record to add / remove workgroup tags");
            return;
        }
        
        this.convertGroupsForH3();
        
        var url = top.HAPI4.basePathOld+ "records/tags/editUsergroupTagsPopup.html?db=" + top.HAPI4.database;
        
        top.HEURIST4.util.showDialog(url, { 
                        width: 800,
                        callback: function(add, wgTag_ids) {//options
                            if (! wgTag_ids) return;

                            var saction = (add ? "add" : "remove") + "_wgTags_by_id";
                            var _data = {rec_ids:recIDs_list, wgTag_ids:wgTag_ids};

                            that.executeAction(saction, _data);
                        }
            });
    },

    selectBookmarkMessage: function(operation) {
        top.HEURIST4.util.showMsgDlg("Select at least one bookmark " + operation
            + (this._query_request.w=="all"
                ? "<br>(operation can only be carried out on bookmarked records, shown by a red star )"
                : ""));
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
                    
                    if(!top.HEURIST4.util.isnull(name))
                    {
                        workgroups.push(groupID);
                        workgroups2[groupID] = {name: name};
                    }
                }
            }
            top.HEURIST.user.workgroups = workgroups;
            top.HEURIST.workgroups = workgroups2;
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
            this.selectBookmarkMessage("to set ratings");
            return;
        }

        var url = top.HAPI4.basePathOld+ "search/actions/setRatingsPopup.php?db=" + top.HAPI4.database;

        top.HEURIST4.util.showDialog(url, { 
                        width:250, height:220,
                        callback: function(value) {//options
                            if(Number(value)>=0){
                               var _data = {bkmk_ids:bkmkIDs_list, ratings: value};
                               that.executeAction('set_ratings', _data);
                            }
                        }
            });
        
    },
    
    addBookmarks: function(recID) {
        
        var recIDs_list = [];
            
        if(recID){
            recIDs_list = [recID];
        }else if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }
        if (recIDs_list.length == 0) {
            top.HEURIST4.util.showMsgDlg("Select at least one record to bookmark");
            return;
        }

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
            top.HEURIST4.util.showMsgDlg("Select at least one bookmark to delete");
            return;
        }else if (bkmkIDs_list.length == 1) {
            sMsg = "Do you want to delete one bookmark?<br>(this ONLY removes the bookmark from your resources,<br>it does not delete the record entry)";
        } else {
            sMsg = "Do you want to delete " + bkmkIDs_list.length + " bookmarks?<br>(this ONLY removes the bookmarks from your resources,<br>it does not delete the record entries)";
        }
        
        top.HEURIST4.util.showMsgDlg(sMsg, function(){
            that.executeAction( "delete_bookmark", {bkmk_ids: bkmkIDs_list} );    
        })
    },

    deleteRecords: function(recID) {
        
        var recIDs_list = [],
            that = this;
            
        if(recID){
            recIDs_list = [recID];
        }else if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }
        if (recIDs_list.length == 0) {
            top.HEURIST4.util.showMsgDlg("Select at least one record to delete");
            return;
        }
        
        var url = top.HAPI4.basePathOld+ "search/actions/deleteRecordsPopup.php?db=" + top.HAPI4.database;

        top.HEURIST4.util.showDialog(url, { 
                    onpopupload: function(frame){ //assign list of records to be deleted to POST form, to avoid GET length limitation
                            var ele = frame.contentDocument.getElementById("ids");
                            ele.value = recIDs_list.join(",");
                            frame.contentDocument.forms[0].submit();
                        },
                    callback: function(context) {
                            if (context==="reload") { //something was deleted
                                 that._query_request.orig = 'rec_list';
                                 top.HAPI4.RecordMgr.search(that._query_request, $(that.document));
                            }
                        }
            });
    },

    notificationPopup: function() {
        
        var recIDs_list = [];
            
        if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }
        if (recIDs_list.length == 0) {
            this.selectBookmarkMessage("for notification");
            return;
        }
        recIDs_list = recIDs_list.join(",");
        url = top.HAPI4.basePathOld+ "search/actions/sendNotificationsPopup.php?db=" + top.HAPI4.database + "&bib_ids=\""+recIDs_list+"\"";
        
        top.HEURIST4.util.showDialog(url, {height:230});
    },
        
    setWorkgroupPopup: function() {
        
        var recIDs_list = [],
            that = this;
            
        if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }
        if (recIDs_list.length == 0) {
            top.HEURIST4.util.showMsgDlg("Select at least one record to set workgroup ownership and visibility");
            return;
        }
        
        this.convertGroupsForH3();
        
        var url = top.HAPI4.basePathOld+ "records/permissions/setRecordOwnership.html?db=" + top.HAPI4.database;
        
        top.HEURIST4.util.showDialog(url, { height:300, width:650, 
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
    
    selectAll: function(){
         $(this.document).trigger(top.HAPI4.Event.ON_REC_SELECT, {selection:"all", source:this.element.attr('id')} );
    },

    selectNone: function(){
         //this._selection = null;
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
    }
    
    
});
