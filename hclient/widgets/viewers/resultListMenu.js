/**
* Menu for result list
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

var Hul = window.hWin.HEURIST4.util;

$.widget( "heurist.resultListMenu", {

    // default options
    options: {
        is_h6style: false,
        // callbacks
        show_searchmenu:false,
        menu_class:null,
        resultList: null,  //reference to parent
        search_realm: null
    },

    _query_request: {},   //keep current query request
    _selection: null,     //current set of selected records (not just ids)

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        .css('font-size', this.options.is_h6style?'1.2em':'1.3em')
        // prevent double click to select text
        .disableSelection();
        
        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu')
            //.css({'dispaly':'table-row'})
            .appendTo(this.element);

        if(this.options.show_searchmenu) this._initMenu('Search');
        this._initMenu('Selected');
        this._initMenu('Collected');
        this._initMenu('Recode');
        this._initMenu('Shared');
        if(this.options.resultList){
            this._initMenu('Reorder');  
            this.options.search_realm = this.options.resultList.resultList('option', 'search_realm');
        } 
        //this._initMenu('Experimental',0);
        //this._initMenu('Layout');
        this.divMainMenuItems.menu();

        this.divMainMenuItems.find('li').css({'padding':'0 3px 3px', 'width':'80px', 'text-align':'center'}); // center, place gap and setting width
        
        if(this.options.menu_class!=null){
             this.element.addClass( this.options.menu_class );   
        }else{
            this.divMainMenuItems.find('.ui-menu-item > a').addClass('ui-widget-content');    
        }

        //this.divMainMenuItems.children('li').children('a').css({'padding-right': '22px !important'});
        this.divMainMenuItems.children('li').children('a').children('.ui-icon').css({right: '2px', left:'unset'});
        //this.divMainMenuItems.children('li>a>.ui-icon').css({right: '2px', left:'unset'});
        
        //-----------------------     listener of global events
        var sevents = window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
                 +window.hWin.HAPI4.Event.ON_REC_SEARCHSTART+' '
                 +window.hWin.HAPI4.Event.ON_REC_COLLECT+' '
                 +window.hWin.HAPI4.Event.ON_REC_SELECT;

        $(window.hWin.document).on(sevents, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS){

                that._refresh();

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(data && !data.reset && that._isSameRealm(data)) {
                    that._query_request = jQuery.extend({}, data); //keep current query request
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_COLLECT){
                
                that.collectionRender( data.collection );
                
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {

                    if(data.reset){
                        that._selection = null;
                    }else{
                        that._selection = window.hWin.HAPI4.getSelection(data.selection, false);
                    }
                    window.hWin.HAPI4.currentRecordsetSelection = that.getSelectionIds();
                }
            }
            //that._refresh();
        });

        this._refresh();

        //get collection
        window.hWin.HEURIST4.collection.collectionUpdate();

    }, //end _create

    //
    //
    //
    _isSameRealm: function(data){
        return !this.options.search_realm || (data && this.options.search_realm==data.search_realm);
    },

    
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

        if(window.hWin.HAPI4.has_access()){

            this.menu_Selected.find('.logged-in-only').show();
            this.menu_Collected.find('.logged-in-only').show();
            this.btn_Recode.show();
            this.btn_Shared.show();
            this.btn_Reorder.show();

            //$(this.element).find('.logged-in-only').show();//.css('visibility','visible');
        }else{
            //$(this.element).find('.logged-in-only').hide();//.css('visibility','hidden');
            this.menu_Selected.find('.logged-in-only').hide();
            this.menu_Collected.find('.logged-in-only').hide();
            this.btn_Recode.hide();
            this.btn_Shared.hide();
            this.btn_Reorder.hide();
        }
    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
            +window.hWin.HAPI4.Event.ON_REC_SEARCHSTART+' '
            +window.hWin.HAPI4.Event.ON_REC_COLLECT+' '
            +window.hWin.HAPI4.Event.ON_REC_SELECT); 

        // remove generated elements
        if(this.btn_Search){
            this.btn_Search.remove();
            this.menu_Search.remove();
        }
        this.btn_Selected.remove();
        this.menu_Selected.remove();
        this.btn_Collected.remove();
        this.menu_Collected.remove();
        this.btn_Recode.remove();
        this.menu_Recode.remove();
        this.btn_Shared.remove();
        this.menu_Shared.remove();
        this.btn_Reorder.remove();
        this.divMainMenuItems.remove();
    },

    _initMenu: function(name, competency_level){

        var that = this;
        var myTimeoutId = -1;

        //show hide function
        var _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                $( ele ).hide();
                }, 1);
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
/*
console.log('padleft');            
console.log(menu.find('.ui-menu-item').css('padding-left'));
            menu.find('.ui-menu-item').css({'padding':'0 !important'});
console.log('pad');            
console.log(menu.find('.ui-menu-item').css('padding'));
*/            
            return false;
        };

        var link = $('<a href="#"'
                +(this.options.is_h6style?' style="padding-right:22px !important"':'')
                +'>'+window.hWin.HR(name)+'</a>')
        //,{});
        
        if(this.options.is_h6style){
            //link.css({'padding-right': '22px !important'});
            if(name=='Reorder'){
                $('<span class="ui-icon ui-icon-signal">').css({'transform':'rotate(90deg)'}).appendTo(link);  //caret-1-s
            }else{
                $('<span class="ui-icon ui-icon-carat-d">').appendTo(link);  //caret-1-s
            }
        }
        

        if(name=='Collected'){
            this.menu_Collected_link = link;
        }

        this['btn_'+name] = $('<li>').append(link)
        .appendTo( this.divMainMenuItems );
        
        
        var usr_exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
        
        if(competency_level>=0){
            this['btn_'+name].addClass('heurist-competency'+competency_level);    
            if(usr_exp_level>competency_level){
                this['btn_'+name].hide();    
            }
        }

        if(name=='Reorder'){
            
            this['btn_'+name].attr('title',
            'Allows manual reordering of the current results and saving as a fixed list of ordered records');
            
            this._on( this['btn_'+name], {
                click : function(){
                       $(this.options.resultList).resultList('setOrderAndSaveAsFilter');
                }
            });
            
        }else{
        
            this['menu_'+name] = $('<ul>')                               //add to avoid cache in devtime '?t='+(new Date().getTime())
            .load(window.hWin.HAPI4.baseURL+'hclient/widgets/viewers/resultListMenu'+name+'.html', function(){  
                that['menu_'+name].addClass('menu-or-popup')
                .css('position','absolute')
                .appendTo( that.document.find('body') )
                //.addClass('ui-menu-divider-heurist')
                .menu({
                    icons: { submenu: "ui-icon-circle-triangle-e" },
                    select: function(event, ui){ 
                    event.preventDefault(); 
                    that.menuActionHandler(ui.item.attr('id')); 
                    return false; }});

                if(window.hWin.HAPI4.has_access()){
                    that['menu_'+name].find('.logged-in-only').show();
                }else{
                    that['menu_'+name].find('.logged-in-only').hide();
                }
                
                that['menu_'+name].find('li[data-user-experience-level]').each(function(){
                    if(usr_exp_level > $(this).data('exp-level')){
                        $(this).hide();    
                    }else{
                        $(this).show();    
                    }
                });
                
                
                that['menu_'+name].find('li').css('padding-left',0);
                
            })
            //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
            .hide();

            //{select: that.menuActionHandler}
            


            this._on( this['btn_'+name], {
                mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                mouseleave : function(){_hide(this['menu_'+name])}
            });
            this._on( this['menu_'+name], {
                mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                mouseleave : function(){_hide(this['menu_'+name])}
            });

        }
        
    },


    menuActionHandler: function(action){

        var that = this;

        //var action = ui.item.attr('id');
        if(action == "menu-search-quick"){  //H4

            //hack $('#btn_search_assistant').click();
            var widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('search');  //window.hWin.HAPI4.LayoutMgr.appGetWidgetById('ha10');
            if(widget){
                widget.search('showSearchAssistant');
            }

        }else if(action == "menu-search-advanced"){ //H3

            //call vsn 3 search builder
            var q = "",
            that = this;
            if(!Hul.isnull(this._query_request) && !Hul.isempty(this._query_request.q)){
                q ="&q=" + encodeURIComponent(this._query_request.q);
            }
            var url = window.hWin.HAPI4.baseURL+ "search/queryBuilderPopup.php?db=" + window.hWin.HAPI4.database + q;

            window.hWin.HEURIST4.msg.showDialog(url, {
                title: window.hWin.HR('Advanced search builder'),
                callback: function(res){
                    if(!Hul.isempty(res)) {
                        that.reloadSearch(res);
                    }
            }});

        }else if(action == "menu-search-save"){  //H4

            var  widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');  //window.hWin.HAPI4.LayoutMgr.appGetWidgetById('ha13');
            if(widget){
                widget.svs_list('editSavedSearch', 'saved'); //call public method editRules
                //$(app.widget).search_links('editSavedSearch', null, null, 'saved'); //call method editSavedSearch - save current search
            }

        }else if(action == "menu-search-rulebuilder"){

            var  widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');  //window.hWin.HAPI4.LayoutMgr.appGetWidgetById('ha13');
            if(widget){
                widget.svs_list('editSavedSearch', 'rules'); //call public method editRules
                // $(app.widget).search_links('editRules', null); //call public method editRules
            }


        }else if(action == "menu-selected-select-all"){

            this.selectAll();

        }else if(action == "menu-selected-select-none"){

            this.selectNone();

        }else if(action == "menu-selected-select-show"){  //show selection as separate search

            this.selectShow();

        }else if(action == "menu-selected-merge"){  //show add relation dialog

            this.fixDuplicatesPopup();

        }else if(action == "menu-selected-tag" || action == "menu-selected-bookmark" || action == "menu-selected-wgtags"){
           
            if(this.isResultSetEmpty()) return;
            
            var opts = {
                width:700,
                groups: (action == "menu-selected-bookmark")?'personal':'all',
                //(action == "menu-selected-wgtags")?'grouponly':'personal',
                onClose:
                   function( context ){
                       if(context){
                           //refresh search page
                           that.reloadSearch(); //@todo reloadPage                   
                       }
                   }
            };
            if(action == "menu-selected-bookmark"){
                opts['title'] = 'Add bookmarks and tag records';
                opts['modes'] = ['assign'];
            }else if (action == "menu-selected-wgtags"){
                opts['title'] = 'Add or Remove Workgroup Tag for Records';    
            }
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordTag', opts);

        }else if(action == "menu-selected-unbookmark"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordBookmark', {onClose:
                   function( context ){
                       if(context){
                           //refresh search page
                           that.reloadSearch(); //@todo reloadPage                   
                       }
                   }
            });

        }else if(action == "menu-selected-rate"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordRate');

        }else if(action == "menu-selected-delete"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HAPI4.currentRecordsetSelection = this.getSelectionIds("Please select at least one record to delete");
            if(Hul.isempty(window.hWin.HAPI4.currentRecordsetSelection)) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordDelete', {onClose:
               function( context ){
                   if(context){
                       // refresh search
                       that.reloadSearch();                    
                   }
               }
            });

        }else if(action == "menu-selected-email") {

            if(this.isResultSetEmpty()) return;
            
            this.openEmailForm();

        }else if(action == "menu-selected-ownership"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAccess', {height:450, onClose:
               function( context ){
                   if(context){
                       //@todo refresh page
                       that.reloadSearch();                    
                   }
               }
            });

        }else if(action == "menu-selected-notify"){
            
            if(this.isResultSetEmpty()) return;

            window.hWin.HEURIST4.ui.showRecordActionDialog('recordNotify');

        }else if(action == "menu-selected-value-add"){

            this.detailBatchEditPopup('add_detail');

        }else if(action == "menu-selected-value-replace"){

            this.detailBatchEditPopup('replace_detail');

        }else if(action == "menu-selected-value-delete"){

            this.detailBatchEditPopup('delete_detail');

        }else if(action == "menu-selected-add-link"){

            if(this.isResultSetEmpty()) return;
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink');
            
        }else if(action == "menu-selected-extract-pdf"){

            this.detailBatchEditPopup('extract_pdf');
            
        }else if(action == "menu-selected-rectype-change"){

            this.detailBatchEditPopup('rectype_change');

        }else if(action == "menu-collected-add"){

            window.hWin.HEURIST4.collection.collectionAdd(null, this._selection);
            this.selectNone();

        }else if(action == "menu-collected-del"){

            window.hWin.HEURIST4.collection.collectionDel(null, this._selection);
            this.selectNone();

        }else if(action == "menu-collected-clear"){

            window.hWin.HEURIST4.collection.collectionClear();

        }else if(action == "menu-collected-show"){

            window.hWin.HEURIST4.collection.collectionShow();

        }else if(action == "menu-collected-save"){

            window.hWin.HEURIST4.collection.collectionSave();
        
        }else if(action == "menu-subset-set"){
            
            if(!window.hWin.HAPI4.currentRecordset ||
                    window.hWin.HAPI4.currentRecordset.length()==0)
            {
                        
                window.hWin.HEURIST4.msg.showMsgDlg(
                '<p>The working subset is created from your current query results. You have no query results, so no subset was created.</p>' 
                +'<p>Please run a query which returns the set of records you wish to treat as the working subset and select this function again.</p>');
                    
            }else if(window.hWin.HAPI4.currentRecordset.length()>window.hWin.HAPI4.sysinfo.db_total_records*0.8){
                
                window.hWin.HEURIST4.msg.showMsgDlg(
                '<p>You are trying to make a subset of everything or nearly everything (>=99%) of records  in the database. This does not make much sense.</p>'  
+'<p>Please apply a filter which returns the subset you wish to work with and select this function again.</p>');
                
            }else {
            
                var scope = window.hWin.HAPI4.currentRecordset.getIds();
                
                window.hWin.HAPI4.SystemMgr.user_wss({ids:scope.join(',')},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            window.hWin.HAPI4.sysinfo.db_workset_count = response.data;
                            that.options.resultList.resultList('refreshSubsetSign');
                            window.hWin.HEURIST4.msg.showMsgFlash(response.data+' records has been added to work subset');
                            
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
                                {closeFacetedSearch:true, userWorkSetUpdated:true, 
                                    source:that.element.attr('id'), search_realm:that.options.search_realm} );
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response, true);
                        }
                    });
                    
            }
            

        }else if(action == "menu-subset-clear"){

            window.hWin.HAPI4.SystemMgr.user_wss({clear:1},
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.sysinfo.db_workset_count = 0;
                        that.options.resultList.resultList('refreshSubsetSign');
                        
                        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
                                {userWorkSetUpdated:true, 
                                    source:that.element.attr('id'), search_realm:that.options.search_realm} );
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                    }
                });
                
        }



    },

    //
    //
    // 
    reloadSearch: function(query){

        if(!Hul.isempty(query)){
            this._query_request.q = query;
        }

        this._query_request.id = null;
        this._query_request.source = this.element.attr('id');
        window.hWin.HAPI4.SearchMgr.doSearch( this, this._query_request );
    },
    
    //
    // clean details for current recordset and force refresh current page
    // it will be faster than perform search again
    //
    reloadPage: function(){
    
    },
    
    //
    //
    //
    getSelectionIds: function(msg, limit){

        var recIDs_list = [];
        if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }

        if (recIDs_list.length == 0 && !Hul.isempty(msg)) {
            window.hWin.HEURIST4.msg.showMsgDlg(msg);
            return null;
        }else if (limit>0 && recIDs_list.length > limit) {
            window.hWin.HEURIST4.msg.showMsgDlg("The number of selected records is above the limit in "+limit);
        }else{
            return recIDs_list;
        }

    },

    //-------------------------------------- EMAIL FORM -------------------------------
    openEmailForm: function() {
        // Selection check
        var ids = this.getSelectionIds("Please select at least one record to e-mail");
        if(Hul.isempty(ids)) {
            return;
        }

        // Open URL
        var url = window.hWin.HAPI4.baseURL+ "hclient/framecontent/send_email.php?db=" + window.hWin.HAPI4.database;
        window.hWin.HAPI4.selectedRecordIds = ids;  //the only place it is assigned
        window.hWin.HEURIST4.msg.showDialog(url, { width:500, height:600, title: window.hWin.HR('Email information') });

    },

    //-------------------------------------- SELCT ALL, NONE, SHOW -------------------------------

    selectAll: function(){
        this._selection = window.hWin.HAPI4.getSelection('all', false);
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
            {selection:"all", source:this.element.attr('id'), search_realm:this.options.search_realm} );
    },

    selectNone: function(){
        this._selection = null;
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
            {selection:null, source:this.element.attr('id'), search_realm:this.options.search_realm} );
    },

    selectShow: function(){
        if(this._selection!=null){
            var recIDs_list = this._selection.getIds();
            if (recIDs_list.length > 0) {
                var url = window.hWin.HAPI4.baseURL + "?db=" + window.hWin.HAPI4.database + "&q=ids:"+recIDs_list.join(',');
                window.open(url, "_blank");
            }
        }
    },

    //-------------------------------------- COLLECTIONS -------------------------------
    //
    // render counter
    //
    collectionRender: function(_collection) {
        
        this.menu_Collected_link.html( window.hWin.HR('Collected')
                + (_collection && _collection.length>0?':'+_collection.length:'') 
                + '<span class="ui-icon ui-icon-carat-d" style="right: 2px; left: unset;">');
    },

    //-------------------------------------- RELATION, MERGE -------------------------------
    fixDuplicatesPopup: function(){


        var recIDs_list = this.getSelectionIds(null);
        if(Hul.isempty(recIDs_list) || recIDs_list.length<2){
            window.hWin.HEURIST4.msg.showMsgDlg("Please select at least two records to identify/merge duplicate records");
            return;
        }

        var that = this;
        var url = window.hWin.HAPI4.baseURL + "admin/verification/combineDuplicateRecords.php?bib_ids="+recIDs_list.join(",")+"&db=" + window.hWin.HAPI4.database;

        window.hWin.HEURIST4.msg.showDialog(url, {
            width:700, height:550,
            default_palette_class:'ui-heurist-explore',
            title: window.hWin.HR('Combine duplicate records'),
            callback: function(context) {
                that.reloadSearch();
            }
        });

    },

    
    isResultSetEmpty: function(){
        var recIDs_all = window.hWin.HAPI4.getSelection("all", true);
        if (Hul.isempty(recIDs_all)) {
            window.hWin.HEURIST4.msg.showMsgDlg('No results found. '
            +'Please modify search/filter to return at least one result record.');
            return true;
        }else{
            return false;
        }
    },
    

    //-------------------------------------- ADD, REPLACE, DELETE FIELD VALUES -------------------------------
    //
    //  MAIN  in use
    //  
    detailBatchEditPopup: function(action_type) {
        
        if(this.isResultSetEmpty()) return;

        var script_name = 'recordAction';
        var callback = null;
        
        if(action_type=='add_link'){
            script_name = 'recordAddLink';
            callback = function(context) {
                        if(context!="" && context!=undefined) {
                            var sMsg = (context==true)?'Link created...':context;
                            window.hWin.HEURIST4.msg.showMsgFlash(sMsg, 2000);
                        }
            };            
        /*}else if(action_type=='ownership'){

            var that = this;
            callback = function(context) {
                        if(context!="" && context!=undefined) {
                                that.executeAction( "set_wg_and_vis", context );
                        }
            };*/            
        }else{
            callback = function(context){
                window.hWin.HAPI4.NEED_TAG_REFRESH = true; //flag to reload tags in next manageUsrTags invocation
            }
        }
        
        var url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/'+script_name+'.php?db='+window.hWin.HAPI4.database+'&action='+action_type;

        window.hWin.HEURIST4.msg.showDialog(url, {height:450, width:750,
            padding: '0px',
            title: window.hWin.HR(action_type),
            callback: callback,
            default_palette_class:'ui-heurist-explore'} );
    },


});
