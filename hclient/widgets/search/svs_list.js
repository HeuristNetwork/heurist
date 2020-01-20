/**
* Accordeon/treeview in navigation panel: saved, faceted and tag searches
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

var Hul = window.hWin.HEURIST4.util;

$.widget( "heurist.svs_list", {

    // default options
    options: {
        isapplication:true,  // send and recieve the global events
        btn_visible_dbstructure: true,  //button to open database structure graph
        btn_visible_filter: false, //filter for treeview
        btn_visible_save: false,

        buttons_mode: false,
        searchTreeMode: -1, //0-buttons, 1- tree, 2-full tree
        allowed_UGrpID: [], // allowed groups
        allowed_svsIDs: [], // allowed searches - for buttons only
        init_svsID:null,    // launch search on init
        
        onclose_search:null,  //function to be called on close faceted search
        sup_filter:null       //suplementary filter for faceted search
    },

    loaded_saved_searches: null,   //loaded searches for button mode - based on options.allowed_XXX
    search_faceted: null,

    currentSearch: null,
    hSvsEdit: null,
    treeviews:{},

    _HINT_RULESET:'It does not perform the search. However it applies rules to current result set and  expand the initial search to a larger set of records by following a set of rules specifying which pointers and relationships to follow (including relationship type and target record types)',
    _HINT_WITHRULES:'Searches with addition of a RuleSet automatically expand the initial search to a larger set of records by following a set of rules specifying which pointers and relationships to follow (including relationship type and target record types)',
    _HINT_FACETED:'Faceted searches allow the user to drill-down into the database on a set of pre-selected database fields',

    // the constructor
    // create filter+button and div for tree
    _create: function() {

        var tab_td = this.element.parents('td');
        if(tab_td.length>0){
            $(tab_td[0]).css('height','1px');
        }
        
        if(this.options.allowed_svsIDs && !$.isArray(this.options.allowed_svsIDs)){
            this.options.allowed_svsIDs = this.options.allowed_svsIDs.split(',');
        }
        if(this.options.allowed_UGrpID && !$.isArray(this.options.allowed_UGrpID)){
            this.options.allowed_UGrpID = this.options.allowed_UGrpID.split(',');
        }
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_UGrpID))
            this._setOptionFromUrlParam('allowed_UGrpID', 'groupID');
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_svsIDs))
            this._setOptionFromUrlParam('allowed_svsIDs', 'searchIDs');

        var isPublished = window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_UGrpID)
            || window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_svsIDs)
            || (this.options.searchTreeMode>=0);
            
        if( isPublished ){
            if(!(this.options.searchTreeMode>=0)) this.options.searchTreeMode = 0;
            this.options.buttons_mode = (this.options.searchTreeMode==0);
        }
            
        if(window.hWin.HAPI4.has_access() && this.options.buttons_mode){
            this._setOptionFromUrlParam('treeview_mode','treeViewLoggedIn', 'bool');
            if(this.options.treeview_mode){
                this.options.buttons_mode= false;
            }
        }

        var that = this;
        
        if(this.element.parent().attr('data-heurist-app-id')){
            //this is publication
            this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }
        
        this.element.css({'overflow-y':'auto','font-size':'0.8em'});
        this.element.parent().css({'overflow':'hidden'});
        
        //panel to show list of saved filters
        this.search_tree = $( "<div>" ).css({'height':'100%'}).appendTo( this.element );

        //panel to show faceted search when it is activated
        this.search_faceted = $( "<div>", {id:this.element.attr('id')+'_search_faceted'} )
        .css({'height':'100%'}).appendTo( this.element ).hide();


        this.div_header = $( "<div>" ).css({'width':'100%', 'padding-top':'1em', 'font-size':'0.9em'}) //, 'height':'2em', 'padding':'0.5em 0 0 2.2em'})
        //.removeClass('ui-widget-content')
        .appendTo( this.search_tree );

        var toppos = 1;

        if(window.hWin.HAPI4.sysinfo['layout']!='original' && !this.options.buttons_mode){
            toppos = toppos + 4;
            $('<div>'+window.hWin.HR('Saved Filters')+'</div>')
            .css({'padding': '0.5em 1em', 'font-size': '1.4em', 'font-weight': 'bold'})
            .addClass('svs-header')
            .appendTo(this.div_header);

            if(this.options.btn_visible_save){

                this.btn_search_save = $( "<button>", {
                    text: window.hWin.HR('Save Filter'),
                    title: window.hWin.HR('Save the current filter and rules as a link in the navigation tree')
                })
                .css({'min-width': '110px','vertical-align':'top','margin-left': '32px','font-size':'1.2em', 'font-weight': 'bold'})
                .addClass('ui-state-focus')
                .appendTo(this.div_header)
                .button({icons: {
                    primary: 'ui-icon-circle-arrow-s'  //"ui-icon-disk"
                }})
                .hide();

                this._on( this.btn_search_save, {  click: function(){
                    window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                        that.editSavedSearch('saved');
                    });
                } });

                //toppos = toppos + 2.5
            }

            if(!isPublished){
                this.helper_top = $( '<div>'+window.hWin.HR('right-click in list for menu')+'</div>' )
                //.addClass('logged-in-only heurist-helper1')
                .appendTo( $( "<div>" )
                    .css({'padding':'0.2em 0 0 1.2em','font-size':'1em','font-style':'italic'})
                    .addClass('svs-header')
                    .appendTo(this.div_header) );
            }else{
                toppos = 2;
            }
        }

        if(this.options.btn_visible_filter && !isPublished){

            this.filter_div = $( "<div>" ).css({'height':'2em', 'width':'100%'}).appendTo( this.search_tree );

            this.filter_input = $('<input name="search" placeholder="Filter...">')
            .css('width','100px').appendTo(this.filter_div);
            this.btn_reset = $( "<button>" )
            .appendTo( this.filter_div )
            .button({icons: {
                primary: "ui-icon-close"
                },
                title: window.hWin.HR("Reset"),
                text:false})
            .css({'font-size': '0.8em','height':'18px','margin-left':'2px'})
            .attr("disabled", true);
            
            this.btn_save = $( "<button>" )
            .appendTo( this.filter_div )
            .button({icons: {
                primary: "ui-icon-disk"
                },
                title: window.hWin.HR("Save"),
                text:false})
            .css({'font-size': '0.8em','height':'18px','margin-left':'2px'})

        }

        var hasHeader = ($(".header"+that.element.attr('id')).length>0);

        //if(this.options.btn_visible_dbstructure) toppos = toppos + 3;
        if(this.options.btn_visible_filter && !isPublished) toppos = toppos + 2;
        if(hasHeader) toppos = toppos + 2;

        if(this.options.buttons_mode){
            //this.helper_top.hide();
            toppos = 1;
            if(this.filter_div) this.filter_div.hide();
            if(this.btn_search_save) this.btn_search_save.hide();
            this.options.btn_visible_filter = false;
        }

        //main container
        this.accordeon = $( "<div>" ).css({'top':toppos+'em', 'bottom':0, 'left':'1em', 'right':'0.5em', 'position': 'absolute', 'overflow':'auto'}).appendTo( this.search_tree );


        this.edit_dialog = null;

        if(this.options.btn_visible_filter && !isPublished){
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
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS, function(e, data) {
            that.accordeon.empty();
            //that.helper_top = null;
            that.helper_btm = null;
            that._refresh();
        });
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, function(e, data){
            if(data && !data.increment && !data.reset){
                that.currentSearch = Hul.cloneJSON(data);
            }
        });

        if(this.btn_search_save){
            $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, function(e, data){
                //show if there is resulst
                that._adjustAccordionTop();
            });
        }


        this._refresh();
    }, //end _create

    _adjustAccordionTop: function(){
        if(this.btn_search_save){

            if(window.hWin.HAPI4.currentRecordset && window.hWin.HAPI4.currentRecordset.length()>0)
            {
                this.btn_search_save.show();
            }else{
                this.btn_search_save.hide();

            }
            var is_vis = (this.btn_search_save.is(':visible'))?0:-1;
            var px = 0;//window.hWin.HEURIST4.util.em(2.5);
            this.accordeon.css('top', this.div_header.height() + is_vis*px + 5);
        }
    },

    _setOption: function( key, value ) {
        this._super( key, value );
        /*
        if(key=='rectype_set'){
        this._refresh();
        }*/
        if(key=='onclose_search' && this.search_faceted && 
            $.isFunction(this.search_faceted.search_faceted) && this.search_faceted.search_faceted('instance')){
            this.search_faceted.search_faceted('option', 'onclose', value);
        }
    },

    _setOptionFromUrlParam: function( key, param_name, dtype ){

        var param_value = window.hWin.HEURIST4.util.getUrlParameter(param_name);
        //overwrite with param values
        if(!window.hWin.HEURIST4.util.isempty(param_value)){

            if(dtype=='bool'){
                this.options[key] = (param_value==1 || param_value=='true');
            }else{
                param_value = param_value.split(',');
                if(window.hWin.HEURIST4.util.isArrayNotEmpty(param_value)){
                    this.options[key] = param_value;
                }
            }

        }

    },

    /* private function */
    _refresh: function(){

        
        var that = this;
        if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){  //find all saved searches for current user

            window.hWin.HAPI4.SystemMgr.ssearch_get( null,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                        that._refresh();
                    }
            });
            return;
        }
        
        
        // show saved searches as a list of buttons
        if(this.options.buttons_mode){

            this._updateAccordeon();
            return;

        }else if(!window.hWin.HAPI4.has_access()){
            window.hWin.HAPI4.currentUser.ugr_Groups = {};
        }else if (!this.helper_btm) {

            //new
            var t1 = '<div style="padding-top:2.5em;font-style:italic;" title="'+this._HINT_FACETED+'">'
            //+'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'+'" style="background-image: url(&quot;'+window.hWin.HAPI4.baseURL+'hclient/assets/fa-cubes.png&quot;);vertical-align:middle">'
            +'<span class="ui-icon ui-icon-box" style="display:inline-block; vertical-align: bottom; font-size:1em"></span>'
            +'&nbsp;Faceted search</div>'


            +'<div style="font-style:italic;" title="'+this._HINT_WITHRULES+'">'
            +'<span class="ui-icon ui-icon-plus" style="display:inline-block; vertical-align: bottom; font-size:0.8em;width:0.7em;"></span>'
            +'<span class="ui-icon ui-icon-shuffle" style="display:inline-block; vertical-align: bottom; font-size:1em;width:0.9em;"></span>'
            +'&nbsp;Search with rules</div>'

            +'<div style="font-style:italic;" title="'+this._HINT_RULESET+'">'
            +'<span class="ui-icon ui-icon-shuffle" style="display:inline-block; vertical-align: bottom; font-size:1em"></span>'
            +'&nbsp;RuleSet</div>';

            this.helper_btm = $( '<div>'+t1+'</div>' )
            //IAN request 2015-06-23 .addClass('heurist-helper1')
            .appendTo( this.accordeon );
            //IAN request 2015-06-23 if(window.hWin.HAPI4.get_prefs('help_on')=='0') this.helper_btm.hide(); // this.helper_btm.css('visibility','hidden');
        }

        this._updateAccordeon();

    },

    //
    // save current treeview layout
    //
    _saveTreeData: function( groupToSave, treeData, callback ){

        var isPersonal = (groupToSave=="all" || groupToSave=="bookmark" || groupToSave=="entity");

        if(!treeData){
            treeData = {};
            for (var groupID in this.treeviews)
                if(groupID){
                    if ( (isPersonal && isNaN(groupID)) || groupToSave == groupID){

                        var d = this.treeviews[groupID].toDict(true);
                        if(groupID=='all'){
                            //console.log(d);                            
                        }

                        treeData[groupID] = d;
                    }
            }
        }

        var request = { data:JSON.stringify(treeData) };
        window.hWin.HAPI4.SystemMgr.ssearch_savetree( request, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupToSave].modified = response.data;
                
                if($.isFunction(callback)) callback.call(this);
                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response, true);
            }

        } );
    },

    //
    // redraw accordeon - list of workgroups, all, bookmarked, entity
    //
    _updateAccordeon: function(){


        // show saved searches as a list of buttons
        if(this.options.buttons_mode){
            this._updateAccordeonAsListOfButtons();
            return;
        }


        this._adjustAccordionTop();

        this.accordeon.hide();


        var islogged = (window.hWin.HAPI4.has_access());
        //if not logged in show only "my searches/all records"

        this.treeviews = {};

        var that = this;

        //verify that all required libraries have been loaded
        if(!$.isFunction($('body').fancytree)){        //jquery.fancytree-all.min.js
            $.getScript(window.hWin.HAPI4.baseURL+'external/jquery.fancytree/jquery.fancytree-all.min.js', function(){ that._updateAccordeon(); } );
            return;
        }else if(!islogged){

            window.hWin.HAPI4.currentUser.ugr_SvsTreeData = that._define_DefaultTreeData();

        }else if(!$.ui.fancytree._extensions["dnd"]){
            //    $.getScript(window.hWin.HAPI4.baseURL+'external/jquery.fancytree/src/jquery.fancytree.dnd.js', function(){ that._updateAccordeon(); } );
            alert('drag-n-drop extension for tree not loaded')
            return;
        }else if(!window.hWin.HAPI4.currentUser.ugr_SvsTreeData){ //not loaded - load from sysUgrGrps.ugr_NavigationTree

            window.hWin.HAPI4.SystemMgr.ssearch_gettree( {}, function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    try {
                        //1. remove nodes that refers to missed search
                        //it returns null if leaf is not found
                        function __cleandata(data, level){

                            if(level==0){ //this is top level  data['all'] && data['bookmark'] && data['entity']
                                for(groupID in data){
                                    data[groupID] = __cleandata(data[groupID], level+1);
                                    if(data[groupID].was_cleaned==true){
                                        data[groupID].was_cleaned = null;
                                        var treeData = {};
                                        treeData[groupID] = data[groupID];
                                        that._saveTreeData( groupID, treeData );
                                    }
                                }
                                return data;
                            }

                            if(data.children){
                                var newchildren = [];
                                for (var idx in data.children){
                                    if(idx>=0){
                                        var node = __cleandata(data.children[idx], level+1);
                                        if(node!=null){
                                            newchildren.push(node);
                                            data.was_cleaned = data.was_cleaned || node.was_cleaned; 
                                            node.was_cleaned = null;
                                        }else{
                                            data.was_cleaned = true;
                                        }
                                    }
                                }
                                data.children = newchildren;
                                return data;
                            }else if(data.key>0){
                                return window.hWin.HAPI4.currentUser.usr_SavedSearch[data.key]?data:null;
                            }else{
                                return data;
                            }
                        }

                        window.hWin.HAPI4.currentUser.ugr_SvsTreeData = $.parseJSON(response.data);

                        window.hWin.HAPI4.currentUser.ugr_SvsTreeData = __cleandata(window.hWin.HAPI4.currentUser.ugr_SvsTreeData, 0);

                    }
                    catch (err) {
                        window.hWin.HEURIST4.msg.showMsgErrJson(response.data);
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

                if(!window.hWin.HAPI4.currentUser.ugr_SvsTreeData) //treeview was not saved - define tree data by default
                    window.hWin.HAPI4.currentUser.ugr_SvsTreeData = that._define_DefaultTreeData();
                else{
                    that._validate_TreeData();
                }
                that._updateAccordeon();
            } );

            return;
        }

        //all required scripts and data have been loaded - start update
        var ispublished = (this.options.searchTreeMode>=0);

        //add db summary as a first entry
        if(this.helper_btm && this.options.btn_visible_dbstructure && !ispublished){
            
            this.helper_btm.before(
                $('<div>')
                .attr('grpid',  'dbs').addClass('svs-acordeon')
                .css('border','none')
                .append( this._defineHeader(window.hWin.HR('Database Summary'), 'dbs').click( function(){ that._showDbSummary(); })
            ) );

        }
        
        if(islogged || ispublished){

            this.helper_btm.before(
                $('<div>')
                .addClass('svs-acordeon-group')
                .html('&nbsp;')); //window.hWin.HR('PERSONAL')

            if(islogged && this.options.searchTreeMode!=1){    
                
                this.helper_btm.before(
                    $('<div>')
                    .attr('grpid',  'bookmark').addClass('svs-acordeon')
                    .addClass('heurist-bookmark-search')  //need tp find from preferences
                    .css('display', (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1')?'block':'none')
                    .append( this._defineHeader(window.hWin.HR('My Bookmarks'), 'bookmark'))
                    .append( this._defineContent('bookmark') ) );

                this.helper_btm.before(
                    $('<div>')
                    .attr('grpid',  'all').addClass('svs-acordeon')
                    //.css('border','none')
                    .append( this._defineHeader(window.hWin.HR('My Searches'), 'all'))
                    .append( this._defineContent('all') ));
                
            }
                
            var groups = window.hWin.HAPI4.currentUser.ugr_Groups;
            for (var groupID in groups)
            if(groupID>0){
                    if(this.options.searchTreeMode==1 && this.options.allowed_UGrpID.length>0 ) //show only allowed groups
                    {
                        if(window.hWin.HEURIST4.util.findArrayIndex(groupID, this.options.allowed_UGrpID)<0){
                            continue;
                        }
                    }
                
                    var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                    if(!window.hWin.HEURIST4.util.isnull(name))
                    {   
                        this.helper_btm.before(
                            $('<div>')
                            .attr('grpid',  groupID).addClass('svs-acordeon')
                            .append( this._defineHeader(name, groupID))
                            .append( this._defineContent(groupID) ));
                        
                        //get descriptiopn for user    
                        window.hWin.HAPI4.SystemMgr.user_get( { UGrpID: groupID},
                            function(response){
                                var  success = (response.status == window.hWin.ResponseStatus.OK);
                                if(success){
                                    that.element.find('div[grpid='+response.data['ugr_ID']+']').attr('title',
                                        that.options.edit_data = response.data['ugr_Description']);
                                }else{
                                    //window.hWin.HEURIST4.msg.showMsgErr(response, true);
                                }
                            }
                        );                            
                    }
            }
            
        }else{
            ($('<div>')
                .attr('grpid',  'all').addClass('svs-acordeon')
                .append( this._defineHeader(window.hWin.HR('Predefined Searches'), 'all'))
                .append( this._defineContent('all') )).appendTo( this.accordeon );


        }

        //init list of accordions
        var keep_status = window.hWin.HAPI4.get_prefs('svs_list_status');
        if(!keep_status) {
            keep_status = { 1:true, 'all':true };
        }
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
                    window.hWin.HAPI4.save_pref('svs_list_status', JSON.stringify(keep_status));
                    //replace all ui-icon-triangle-1-s to se
                    cdivs.find('.ui-icon-triangle-1-e').removeClass('ui-icon-triangle-1-se');
                    cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');

                }
            });
            
            //cintext menu for group header
            if(!ispublished){
                var context_opts = that._getAddContextMenu(groupid);
                cdiv.contextmenu(context_opts);
            }

            
            //replace all ui-icon-triangle-1-s to se
            cdivs.find('.ui-accordion-header-icon').css('font-size','inherit');
            cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');

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
        window.hWin.HAPI4.save_pref('svs_list_status', keep_status);
        }
        });*/
        this.accordeon.show();
        
        //
        if(ispublished && this.options.init_svsID){
            this.doSearchByID( this.options.init_svsID );
        }        

    },


    //
    //
    //
    _updateAccordeonAsListOfButtons: function(){
        
        var that = this;
        if(!this.loaded_saved_searches){  //find all saved searches for current user
        
            if(this.options.allowed_svsIDs.length>0 || this.options.allowed_UGrpID.length>0){

                window.hWin.HAPI4.SystemMgr.ssearch_get( {svsIDs: this.options.allowed_svsIDs,
                    UGrpID: this.options.allowed_UGrpID},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that.loaded_saved_searches = response.data; //svs_id=>array()
                            
                            /*
                            if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                                window.hWin.HAPI4.currentUser.usr_SavedSearch = that.loaded_saved_searches
                            }
                            */
                            
                            var svsID = Object.keys(that.loaded_saved_searches)
                            var missed = [];
                            //verify
                            for(var i=0; i<that.options.allowed_svsIDs.length; i++){
                                if(window.hWin.HEURIST4.util.findArrayIndex(that.options.allowed_svsIDs[i],svsID)<0){
                                    missed.push(that.options.allowed_svsIDs[i]);
                                }
                            }
                            if(missed.length>0){
                                window.hWin.HEURIST4.msg.showMsgErr(
                                'Saved filter'+(missed.length>1?'s':'')+' (ID '
                                + missed.join(', ')
                                + ') specified in parameters '
                                + (missed.length>1?'does':'do')+' not exist in the database.<br><br>Please advise the database owner ('
                                + window.hWin.HAPI4.sysinfo['dbowner_email'] +')');
                            }
                            
                            that._updateAccordeonAsListOfButtons();
                        }
                });
                
                return;
            }else{
                this.loaded_saved_searches = [];
            }
        }

        this.accordeon.hide();
        this.accordeon.empty();
        this.search_tree.css('overflow','hidden');
        
        
        var i, svsIDs = Object.keys(this.loaded_saved_searches),
            visible_cnt = 0, visible_svsID;

            
        if(svsIDs.length>0){ 
            
            //$('<h4 style="padding:20px 0px;margin:0">Focussed searches</h4>').appendTo(this.accordeon);

            for (i=0; i<svsIDs.length; i++)
            {
                var svsID = svsIDs[i];
                
                var params = Hul.parseHeuristQuery(this.loaded_saved_searches[svsID][_QUERY]);

                var iconBtn = 'ui-icon-search';
                if(params.type==3){
                    iconBtn = 'ui-icon-box';
                }else {
                    if(params.type==1){ //withrules
                        iconBtn = 'ui-icon-plus ui-icon-shuffle';
                    }else if(params.type==2){ //rules only
                        iconBtn = 'ui-icon-shuffle';
                    }else  if(params.type<0){ //broken empty
                        iconBtn = 'ui-icon-alert';
                    }
                }

                
                var sname = this.loaded_saved_searches[svsID][_NAME];
                
                if(sname.toLowerCase().indexOf('placeholder')===0) continue;

                $('<button>', {text: sname, 'data-svs-id':svsID})
                .css({'width':'100%','margin-top':'0.4em','max-width':'300px','text-align':'left'})
                .button({icons:{primary: iconBtn}}).on("click", function(event){

                    var svs_ID = $(this).attr('data-svs-id');
                    if (svs_ID){
                        var qsearch = that.loaded_saved_searches[svs_ID][_QUERY];
                        var qname   = that.loaded_saved_searches[svs_ID][_NAME];

                        that.doSearch( svs_ID, svs_ID, qsearch, event.target ); //qname replaced with svs_ID
                        that.accordeon.find('#search_query').val('');
                    }
                })
                .appendTo(this.accordeon);
                $('<br>').appendTo(this.accordeon);

                visible_svsID = svsID;
                visible_cnt++;
            }//for

            //$(this.accordeon).css({'overflow-x':'hidden',bottom:'3em'});
            $(this.accordeon).css({'overflow':'hidden',position:'unset','padding':'4px'});

            //position:absolute;bottom:0px;
            var search_div = $('<div style="height:2.5em;padding:4px;width:100%">'
                +'<h4 style="padding:20px 0px;margin:0">Simple search</h4><label>Search everything:</label>'
                +'&nbsp;<input id="search_query" style="display:inline-block;width:40%" type="search" value="">'
                +'&nbsp;<button id="search_button"/></div>')
            .insertAfter(this.accordeon);
            var ele_search = search_div.find('#search_query'); //$(window.hWin.document).find('#search_query');
            if(ele_search.length>0){

                this._on( ele_search, {
                    keypress: function(e){
                        var code = (e.keyCode ? e.keyCode : e.which);
                        if (code == 13) {
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                            that.doSearch(0, '', ele_search.val(), ele_search);
                        }
                    }
                });

                var btn_search = search_div.find('#search_button')
                .button({icons:{primary:'ui-icon-search'},text:false})
                .css({width:'18px', height:'18px', 'margin-bottom': '5px'});
                this._on( btn_search, {
                    click:  function(){
                        that.doSearch(0, '', ele_search.val(), ele_search);
                    }
                });
            }

        }
        else{
            
            $('<span style="padding:10px">No searches defined</span>').appendTo(this.accordeon);
        }
        this.accordeon.show();
        
        //if the only search - start search at once
        if(visible_cnt==1){//this.loaded_saved_searches &&
            $(this.accordeon).find('button[data-svs-id="'+visible_svsID+'"]').click();
        }else if(this.options.init_svsID){
            $(this.accordeon).find('button[data-svs-id="'+this.options.init_svsID+'"]').click();
        }

    },

    //it adds context menu for evey group header 
    _getAddContextMenu: function(groupID){
        var arr_menu = [{title: "New", cmd: "addSearch", uiIcon: "ui-icon-plus" },
            {title: "New faceted", cmd: "addSearch2", uiIcon: "ui-icon-box" },
            {title: "New RuleSet", cmd: "addSearch3", uiIcon: "ui-icon-shuffle" },
            {title: "New folder", cmd: "addFolder", uiIcon: "ui-icon-folder-open" }];

        var that = this;

        var context_opts = {
            delegate: ".hasmenu2",
            menu: arr_menu,
            select: function(event, ui) {

                that._avoidConflictForGroup(groupID, function(){

                    if(ui.cmd=="addFolder"){

                        setTimeout(function(){
                            var tree = that.treeviews[groupID];
                            var node = tree.rootNode;
                            node.folder = true;

                            node.editCreateNode( "child", {title:"", folder:true}); //New folder
                            that._saveTreeData( groupID );
                            $("#addlink"+groupID).css('display', 'none');
                            }, 300);
                        //tree.trigger("nodeCommand", {cmd: ui.cmd});
                    }else{
                        that.editSavedSearch((ui.cmd=="addSearch2")?'faceted':((ui.cmd=="addSearch3")?'rules':'saved'), groupID);
                    }
                });
            }
        };
        return context_opts;
    },

    //
    //
    //
    _defineHeader: function(name, domain){

        if(domain=='all' || domain=='bookmark' || domain=='entity'){
            sIcon = 'user';
        }else if(domain=='dbs'){
            sIcon = 'database';
        }else {
            sIcon = 'group';
        }

        var $header = $('<h3 class="hasmenu2" grpid="'+domain+'" style="margin:0"><span class="ui-icon ui-icon-'+sIcon+'" '
            + 'style="display:inline-block;padding:0 4px"></span><span style="vertical-align:top;">'
            + name+'</span><span style="font-size:0.8em;font-weight:normal;vertical-align:top;line-height: 1.8em;"> ('
            + ((sIcon=='user')?'private':'workgroup')
            + ')</span></h3>').addClass('tree-accordeon-header svs-header');

        if('dbs'!=domain){
//console.log('adddddd');            
        }

        return $header
    },

    //
    // it invokes before each attempt of group tree modification (delete, drag, add, rename)
    // it verifies date of last modification on server and compare with date stored in treedata
    //
    _avoidConflictForGroup: function(groupID, continueFunc){


        if(isNaN(groupID)){
            continueFunc();
            return;
        }

        var that = this;

        window.hWin.HAPI4.SystemMgr.ssearch_gettree( {UGrpID:groupID}, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                var newdata = {};
                try {
                    var newdata = $.parseJSON(response.data);
                }
                catch (err) {
                    window.hWin.HEURIST4.msg.showMsgErrJson(response.data);
                    return;
                }

                if( !newdata[groupID] ||
                    !window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID] ||
                    (window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID].modified &&
                        newdata[groupID].modified <= window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID].modified))
                {

                    continueFunc();

                }else{
                    window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID] = newdata[groupID];

                    that._redefineContent( groupID );

                    window.hWin.HEURIST4.msg.showMsgDlg('The tree structure for the "'+
                        window.hWin.HAPI4.sysinfo.db_usergroups[groupID]
                        +'" group has been modified by another user. The tree will be reloaded. '
                        +'Please repeat your operation with the new tree');
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });

    },

    _redefineContent: function(groupID){
        //find group div
        var grp_div = this.accordeon.find('.svs-acordeon[grpid="'+groupID+'"]');
        //define new
        this._defineContent(groupID, grp_div.find('.ui-accordion-content'));
    },


    //
    // main method to draw content for particular group
    // redraw treeview with the list of saved searches for given groupID and domain
    // add tree as a accordeon content
    //
    _defineContent: function(groupID, container){
        //
        var res;
        var that = this;
        var CLIPBOARD = null;

        var treeData = window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID] && window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID].children
        ?window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID].children :[];

        var tree = $("<div>").addClass('hasmenu').css('padding-bottom','0em');
        
        var ispublished = (this.options.searchTreeMode>=0);

        var fancytree_options =
        {
            groupID: groupID,    
            checkbox: false,
            //titlesTabbable: false,     // Add all node titles to TAB chain
            source: treeData,
            quicksearch: true,
            selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)

            renderNode: function(event, data) {
                // Optionally tweak data.node.span
                var node = data.node;
                if(true || node.data.cstrender){
                    var $span = $(node.span);
                    var s = '', s1='';
                    if(node.folder){
                        //
                        s1 = '<span class="ui-icon-folder-open ui-icon" style="font-size:0.9em;"></span>';
                        $span.find("> span.fancytree-title").html(s1 +
                            '<div style="display:inline-block;vertical-align:top;">'+node.title+'</div>');  //padding:2 0 0 3;
                        //vertical-align:top;  font-weight:normal !important

                        //$span.find("> span.fancytree-icon").addClass("ui-icon-folder-open ui-icon").css('display','inline-block');
                        //s = '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'+'" title="faceted" style="background-image: url(&quot;'+window.hWin.HAPI4.baseURL+'hclient/assets/fa-cubes.png&quot;);">';
                    }else{

                        var s_hint = '';  //NOT USED this hint shows explanatory text about mode of search:faceted,with rules,rules only
                        var s_hint2 = ''; //this hint shows notes and RAW text of query,rules

                        
                        var squery = '';
                        if(node.data.url){
                            s_hint2 = node.title;
                            squery = node.data.url;
                        }else{
                            s_hint2 = node.key+':'+node.title;
                            if(window.hWin.HAPI4.currentUser.usr_SavedSearch[node.key]){
                                var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[node.key];
                                squery = svs[_QUERY];
                                /*if(!node.data.isfaceted){
                                    var qsearch = svs[_QUERY];
                                    prms = Hul.parseHeuristQuery(qsearch);
                                }*/
                            }
                        }
                        var prms = Hul.parseHeuristQuery(squery);
                        
                        if(!Hul.isempty(prms.notes)){
                            s_hint2 = s_hint2 + '\nNotes: '+prms.notes;
                        }
                        if(!Hul.isempty(prms.q)){
                            s_hint2 = s_hint2 + '\nFilter: '+prms.q;
                        }
                        if(!Hul.isempty(prms.rules)){
                            s_hint2 = s_hint2 + '\nRules: '+prms.rules;
                        }

                        if(prms.type==3){ //node.data.isfaceted
                            s = '<span class="ui-icon ui-icon-box svs-type-icon" title="faceted" ></span>';
                            s_hint = this._HINT_FACETED;
                        }else if(prms.type==1){ //withrules
                                s = '<span class="ui-icon ui-icon-plus svs-type-icon"></span>'
                                +'<span class="ui-icon ui-icon-shuffle svs-type-icon"></span>';
                                s_hint = this._HINT_WITHRULES;
                        }else if(prms.type==2){ //rules only
                                s = '<span class="ui-icon ui-icon-shuffle svs-type-icon" title="rules" ></span>';
                                s_hint = this._HINT_RULESET;
                        }else if(prms.type<0){ //broken
                                s = '<span class="ui-icon ui-icon-alert svs-type-icon" title="rules" ></span>';
                                s_hint = 'Broken filter. Remove and re-create it';
                        }

                        if(s==''){
                            $span.find("> span.fancytree-title").text(node.title);
                        }else{
                            //'<span style="display:inline-block;">'+node.title+ '</span>'
                            //'<div style="display:inline-block;">'+node.title+'</div>'
                            //
                            $span.find("> span.fancytree-title").html(s+' '+node.title);
                        }
                        $span.attr('title', s_hint2)
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
                    var qname, qsearch, svs_ID = 0;
                    if(data.node.data && data.node.data.url){
                        qsearch = data.node.data.url;
                        qname   = (data.node.key>0)?data.node.key:data.node.title; //qname replaced with svs_ID
                    }else{
                        if (data.node.key && 
                            window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                            window.hWin.HAPI4.currentUser.usr_SavedSearch[data.node.key]){
                                
                            svs_ID = data.node.key; 
                            qsearch = window.hWin.HAPI4.currentUser.usr_SavedSearch[data.node.key][_QUERY];
                            qname   = data.node.key; //window.hWin.HAPI4.currentUser.usr_SavedSearch[data.node.key][_NAME];
                        }
                    }

                    //data.node.setSelected(true);
                    //remove highlight from others
                    that.search_tree.find('li.ui-state-active').removeClass('ui-state-active');
                    that.doSearch( svs_ID, qname, qsearch, event.target );
                    setTimeout(function(){
                        that.search_tree.find('div.svs-contextmenu2').parent().addClass('leaves');
                        $(data.node.li).css('border','none').addClass('ui-state-active leaves');
                        },500);
                }

            }
        };

        if(window.hWin.HAPI4.has_access() && !ispublished){

            fancytree_options['extensions'] = ["edit", "dnd", "filter"];
            
            fancytree_options['dnd'] = {
                preventVoidMoves: true,
                preventRecursiveMoves: true,
                autoExpandMS: 400,
                dragStart: function(node, data) {
                    //return (node.key && node.key[0]=='_')?false:true; //disable folder dnd
                    return true;
                },
                dragEnter: function(node, data) {
                    //data.otherNode - dragging node
                    //node - target node
                    if(data.otherNode.folder && node.tree._id != data.otherNode.tree._id){
                        //do not allow drag folders between trees
                        return false;
                    }else{
                        return node.folder ?true :["before", "after"];
                    }

                },
                dragDrop: function(node, data) {
                    
                    //check that tree was not modified by other user
                    that._avoidConflictForGroup(groupID, function(){
                        //data.otherNode - dragging node
                        //node - target node
                        if(node.tree._id != data.otherNode.tree._id){
                        
                            var mod_node = data.otherNode;
                            
                            var newGroupID = node.tree.options.groupID;
                            var oldGroupID = mod_node.tree.options.groupID;
                            var newGroupID_for_db = (newGroupID=='all' || newGroupID=='bookmark'|| newGroupID=='entity')
                                        ? window.hWin.HAPI4.currentUser.ugr_ID :newGroupID; 

//console.log('move '+mod_node.key+'  '+mod_node.title+' from '+oldGroupID
//+' to '+newGroupID_for_db+' ('+newGroupID+') '+node.key+' '+node.title);

                            var request = { svs_ID: mod_node.key, 
                                            svs_UGrpID: newGroupID_for_db };
                            
                            window.hWin.HAPI4.SystemMgr.ssearch_save(request,
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){

                                        window.hWin.HAPI4.currentUser.usr_SavedSearch[mod_node.key][_GRPID] = newGroupID;
                                        data.otherNode.tree._id = node.tree._id;
                                        data.otherNode.moveTo(node, data.hitMode);
                            
                                        that._saveTreeData( oldGroupID, null, function(){
                                            that._saveTreeData( groupID, null, function(){
                                                
                                            } );
                                        } );
                            
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                                    }
                                });
                            
                        }else{
                            data.otherNode.moveTo(node, data.hitMode);
                            that._saveTreeData( groupID );
                        }
                    });
                }
            };
            fancytree_options['edit'] = {
                save:function(event, data){
                    if(''!=data.input.val()){
                        var new_name = data.input.val();
                        that._avoidConflictForGroup(groupID, function(){
                            data.node.setTitle( new_name );
                            that._saveTreeData( groupID );
                        });
                    }
                }
            };
            fancytree_options['filter'] = { mode: "hide" };

            tree.fancytree(fancytree_options)
            //.css({'height':'100%','width':'100%'})
            .on("nodeCommand", function (event, data){
                that._avoidConflictForGroup(groupID, function(){

                    // Custom event handler that is triggered by keydown-handler and
                    // context menu:
                    var refNode, moveMode,
                    wtree = $(tree).fancytree("getTree"),
                    node = wtree.getActiveNode();

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
                        case "copycb":
                            break;    
                        case "embed":   //EMBED
                            //show popup with link
                            if(!node.folder && node.key>0){
                                
                                that._showEmbedDialog(node.key);
                                /*
                                window.hWin.HEURIST4.msg.showMsgDlg(
                                    'One or more saved facet (or ordinary) searches can be embedded into an iframe in a website using the following code:<br><br>'
                                    +'<xmp style="font-size:1.2em">'
                                    +'<iframe src="'+window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&ll=WebSearch&searchIDs='
                                    +node.key+'"\nwidth="100%" height="700" frameborder="0"></iframe>'
                                    +'</xmp><br>'
                                    +'For more than one search, separate the IDs by commas (the searches will appear as buttons). Enclose within &lt;code&gt; &lt;/code&gt; '
                                    +'for Wordpress sites (the use of &lt;code&gt; may need to be enabled for your site). '
                                    +'The URL can also be opened on its own in a separate tab. Note that ordinary searches can also be embedded simply through '
                                    +'specifying the URL obtained when the search is run in the Heurist interface.',
                                    null, 'Embedding searchs');
                                 */   
                            }
                            break;
                        case "rename":   //EDIT

                            if(!node.folder && node.key>0){

                                that.editSavedSearch(null, groupID, node.key, null, node);
                            }else{
                                //rename folder
                                node.editStart();
                            }

                            break;
                        case "remove":

                            function __removeNode(){
                                node.remove();
                                that._saveTreeData( groupID );
                                if(that.treeviews[groupID].count()<1){
                                    $("#addlink"+groupID).css('display', 'block');
                                }
                            }

                            if(node.folder){
                                if(node.countChildren()>0){
                                    window.hWin.HEURIST4.msg.showMsgDlg('Cannot delete non-empty folder. Please delete dependent entries first.',null,window.hWin.HR('Warning'));
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
                            node = wtree.rootNode;
                            }*/
                            //node = node.parent;
                            node.editCreateNode( node.folder?"child":"after", {title:"", folder:true}); //New folder

                            break;

                        case "addSearch":  //add new saved search
                        case "addSearch2": //add new faceted search
                        case "addSearch3": //add new RuleSet

                            that.editSavedSearch( (data.cmd=="addSearch2")?'faceted':((data.cmd=="addSearch3")?'rules':'saved')
                                , groupID, null, null, node);

                            break;

                        case "addChild":
                            node.editCreateNode("child", "New folder");
                            break;
                        case "addSibling":
                            node.editCreateNode("after", "New node");
                            break;
                        /*  copy/paste actions disabled since 2017-07-29                        
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
                        if(CLIPBOARD){

                        if( CLIPBOARD.mode === "cut" ) {
                        // refNode = node.getPrevSibling();
                        CLIPBOARD.data.moveTo((!node.folder)?node.parent:node, "child");
                        CLIPBOARD.data.setActive();
                        that._saveTreeData( groupID );

                        } else if( CLIPBOARD.mode === "copy" ) {
                        //get svsID and and save as new 

                        var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[CLIPBOARD.data.key];
                        var request = {svs_ID: -1,
                        svs_Name: svs[_NAME]+' (copy)',
                        svs_Query: svs[_QUERY],
                        svs_UGrpID: groupID,
                        isfaceted: CLIPBOARD.data.data.isfaceted};

                        //ssearch_save
                        that._saveSearch(request, node);

                        //node.addChildren(CLIPBOARD.data).setActive();
                        }
                        }
                        break;
                        */                            
                        default:
                            alert("This command not handled: " + data.cmd);
                            return;
                    }

                });
                }
            )
            .on("keydown", function(e){
                var code = e.charCode || e.keyCode; //e.which
                var c = String.fromCharCode(code),
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
            tree.contextmenu({
                delegate: "li", //span.fancytree-node
                menu: [
                    {title: "New", cmd: "addSearch", uiIcon: "ui-icon-plus" }, //<kbd>[Ctrl+N]</kbd>
                    {title: "New Faceted", cmd: "addSearch2", uiIcon: "ui-icon-box" },
                    {title: "New RuleSet", cmd: "addSearch3", uiIcon: "ui-icon-shuffle" },
                    {title: "Edit", cmd: "rename", uiIcon: "ui-icon-pencil" }, // <kbd>[F2]</kbd>
                    {title: "----"},
                    {title: "Copy to clipboard", cmd: "copycb", uiIcon: "ui-icon-copy" }, 
                    {title: "Embed", cmd: "embed", uiIcon: "ui-icon-globe" }, 
                    {title: "----"},
                    {title: "New folder", cmd: "addFolder", uiIcon: "ui-icon-folder-open" }, // <kbd>[Ctrl+Shift+N]</kbd>
                    {title: "Delete", cmd: "remove", uiIcon: "ui-icon-trash" }  // <kbd>[Del]</kbd>
                    /*
                    {title: "----"},
                    {title: "Cut", cmd: "cut", uiIcon: "ui-icon-scissors"}, // <kbd>Ctrl+X</kbd>
                    {title: "Copy", cmd: "copy", uiIcon: "ui-icon-copy"},  // <kbd>Ctrl-C</kbd>
                    {title: "Paste as child", cmd: "paste", uiIcon: "ui-icon-clipboard", disabled: true } //<kbd>Ctrl+V</kbd>
                    */
                ],
                beforeOpen: function(event, ui) {
                    var node = $.ui.fancytree.getNode(ui.target);
                    tree.contextmenu("enableEntry", "paste", node.folder && !!CLIPBOARD);
                    node.setActive();
                },
                select: function(event, ui) {
                    
                    if(ui.cmd=='copycb'){
                            var wtree = $(tree).fancytree("getTree"),
                                node = wtree.getActiveNode();
                            that._getFilterString(node.key);
                    }else{
                        var that2 = this;
                        // delay the event, so the menu can close and the click event does
                        // not interfere with the edit control
                        setTimeout(function(){
                            $(that2).trigger("nodeCommand", {cmd: ui.cmd});
                            }, 100);
                            
                    }
                }
            });

            $.each( tree.find('span.fancytree-node'), function( idx, item ){

                var ele = $(item); //.find('span.fancytree-node');
                ele.css({display: 'block', width:'99%'});         

                ele.find('.fancytree-title').css({display: 'inline-block', width:'80%'}).addClass('truncate');

                $('<div class="svs-contextmenu2 ui-icon ui-icon-menu"></div>')
                .click(function(event){ tree.contextmenu("open", $(event.target) ); window.hWin.HEURIST4.util.stopEvent(event); return false;})
                .appendTo(ele);

                if($(item).find('span.fancytree-folder').length==0)
                {
                    $(item).addClass('leaves');

                    $(item).mouseenter(
                        function(event){
                            var ele = $(item).find('.svs-contextmenu2');
                            ele.css('display','inline-block');
                            //ele.show();
                    }).mouseleave(
                        function(event){
                            var ele = $(item).find('.svs-contextmenu2');
                            ele.hide();
                    });
                    /*
                    $(item).hover(
                    function(event){
                    $(event.target).find('.svs-contextmenu2').css('display','inline-block');
                    },
                    function(event){
                    $(event.target).find('.svs-contextmenu2').hide();
                    });*/
                }
            });


            var context_opts = this._getAddContextMenu(groupID);

            var append_link = $("<a>",{href:'#'})
                .html('<span class="ui-icon ui-icon-plus hasmenu2" '
                    +' style="display:inline-block; vertical-align: bottom"></span>'
                    +'<span class="hasmenu2">add</span>')
                .click(function(event){
                    append_link.contextmenu('open', append_link.find('span.ui-icon') );
                    //$(this).parent('a').contextmenu('open', $(event.target) );//$(this).parent('a'));
             });
             append_link.contextmenu(context_opts);

            //treedata is empty - add div - to show add links
            var tree_links = $("<div>", {id:"addlink"+groupID})
            .css({'display': treeData && treeData.length>0?'none':'block', 'padding-left':'1em'} )
            .append( append_link );


            if(window.hWin.HEURIST4.util.isnull(container)){
                res = $('<div>').append(tree_links).append(tree);
            }else{
                container.empty();
                container.append(tree_links).append(tree);
                res = container;
            }


        }else{
            //not logged in
            tree.fancytree(fancytree_options);

            if(window.hWin.HEURIST4.util.isnull(container)){
                res = $('<div>').append(tree);
            }else{
                container.empty();
                container.append(tree);
                res = container;
            }
            
            $.each( tree.find('span.fancytree-node'), function( idx, item ){

                var ele = $(item); //.find('span.fancytree-node');
                ele.css({display: 'block', width:'99%'});         

                ele.find('.fancytree-title').css({display: 'inline-block', width:'90%'}).addClass('truncate');

                if($(item).find('span.fancytree-folder').length==0)
                {
                    $(item).addClass('leaves');
                }
            });            
            
            if(ispublished){
                res.css({background:'none', border:'none'});
            }
        }

        this.treeviews[groupID] = tree.fancytree("getTree");

        return res;

    },


    _saveSearch: function(request, node){

        var that = this;

        window.hWin.HAPI4.SystemMgr.ssearch_save(request,
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    var svsID = response.data;

                    if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = {};
                    }

                    window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

                    window.hWin.HAPI4.save_pref('last_savedsearch_groupid', request.svs_UGrpID);

                    request.new_svs_ID = svsID;

                    node.addNode( { title:request.svs_Name, key: request.new_svs_ID }
                        , node.folder?"child":"after" );

                    that._saveTreeData( request.svs_UGrpID );
                    $("#addlink"+request.svs_UGrpID).css('display', 'none');

                    //callback_method.call(that, null, request);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response, true);
                }
            }

        );


    },

    //add missed groups and saved searches to treeview
    _validate_TreeData: function(){

        var treeData = this._define_DefaultTreeData();

        //form files
        var treeDataF = window.hWin.HAPI4.currentUser.ugr_SvsTreeData;
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

        window.hWin.HAPI4.currentUser.ugr_SvsTreeData = treeDataF;
    },

    _define_DefaultTreeData: function(){

        var treeData;
        if(window.hWin.HAPI4.has_access()){
            treeData = {
                all: { title: window.hWin.HR('My Searches'), folder: true, expanded: true, children: this._define_SVSlist(window.hWin.HAPI4.currentUser.ugr_ID, 'all') },
                bookmark:{ title: window.hWin.HR('My Bookmarks'), folder: true, expanded: true, children: this._define_SVSlist(window.hWin.HAPI4.currentUser.ugr_ID, 'bookmark') }
            };
            if(window.hWin.HAPI4.is_admin()){
                treeData['guests'] = { title: window.hWin.HR('Searches for guests'), folder: true, expanded: false, children: this._define_SVSlist(0) };
            }
        }else{
            treeData = {
                all: { title: window.hWin.HR('Searches'), folder: true, expanded: true, children: this._define_SVSlist(0, 'all') }
            };
        }

        if(window.hWin.HAPI4.has_access()){

            var groups = window.hWin.HAPI4.currentUser.ugr_Groups;
            for (var groupID in groups)
            {
                if(groupID>0){
                    var name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                    treeData[groupID] = {title: name, folder: true, expanded: false, children: this._define_SVSlist(groupID) };
                }
            }

        }

        return treeData;

    },

    //
    /**
    * create list of saved searches for given user/group
    * domain - all or bookmark or entity
    */
    _define_SVSlist: function(ugr_ID, domain){

        var ssearches = window.hWin.HAPI4.currentUser.usr_SavedSearch;

        var res = [];

        //add predefined searches
        if(ugr_ID == window.hWin.HAPI4.currentUser.ugr_ID){  //if current user domain may be all or bookmark or entity

            if(domain=='entity'){
                //push rectypes with top most record count
                //@todo
                res.push( { title:'Places', folder:false, url:'?q=t:12'} );
                
            }else{
        
                domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';

                var s_all = "?w="+domain+"&q=sortby:-m after:\"1 week ago\"&label=Recent changes";
                var s_recent = "?w="+domain+"&q=sortby:-m&label=All records";

                res.push( { title: window.hWin.HR('Recent changes'), folder:false, url: s_all}  );
                res.push( { title: window.hWin.HR('All (date order)'), folder:false, url: s_recent}  );
            }
        }

        //_NAME = 0, _QUERY = 1, _GRPID = 2



        for (var svsID in ssearches)
        {
            if(svsID && ssearches[svsID][_GRPID]==ugr_ID){

                var prms = Hul.parseHeuristQuery(ssearches[svsID][_QUERY]);

                if(!domain || domain==prms.domain){
                    var sname = ssearches[svsID][_NAME];
                    res.push( { title:sname, folder:false, key:svsID } );
                }
            }
        }

        return res;

    },


    /*
    _doSearch: function(event){

    var qsearch = null;
    var qid = $(event.target).attr('svsid');

    if (qid && window.hWin.HAPI4.currentUser.usr_SavedSearch){
    qsearch = window.hWin.HAPI4.currentUser.usr_SavedSearch[qid][1];
    } else {
    qsearch = $(event.target).find('div').html();
    qsearch = qsearch.replace("&amp;","&");
    }
    this.doSearch(qsearch);
    },*/

    //
    //
    //
    doSearchByID: function(svs_ID, query_name){
    
        if(window.hWin.HAPI4.currentUser.usr_SavedSearch && 
            window.hWin.HAPI4.currentUser.usr_SavedSearch[svs_ID]){
                                
            var qsearch = window.hWin.HAPI4.currentUser.usr_SavedSearch[svs_ID][_QUERY];
            var qname   = query_name || svs_ID; //window.hWin.HAPI4.currentUser.usr_SavedSearch[svs_ID][_NAME];
            
            this.doSearch( svs_ID, qname, qsearch, null );
        }else{
            //not found - try to find
            var that = this;
            window.hWin.HAPI4.SystemMgr.ssearch_get( { svsIDs:svs_ID },
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        var qsearch = response.data[svs_ID][_QUERY];
                        that.doSearch( svs_ID, query_name || svs_ID, qsearch, null );
                    }
            });
            
        }
        
    },
    
    doSearch: function(svs_ID, qname, qsearch, ele){

        if ( qsearch ) {

            var params = Hul.parseHeuristQuery( qsearch );
            
            if(params.type==3){ //isfaceted

                /*if(facet_params==null){
                    // Do something about the exception here
                    window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise this faceted search due to corrupted parameters. Please remove and re-create this search.'), null, window.hWin.HR('Warning'));
                    return;
                }*/

                var that = this;


                if(params['version']==2){

                    this.search_faceted.show();
                    this.search_tree.hide();
                    
                    //suplementary filter for faceted search
                    if(that.options.sup_filter){
                        params.sup_filter = that.options.sup_filter;
                    }

                    var noptions = { 
                        svs_ID: svs_ID,
                        query_name:qname, 
                        params:params, 
                        showresetbutton:(this.options.showresetbutton!==false),
                        search_realm:this.options.search_realm};
                    
                    
                    //function to be called on close faceted search
                    if($.isFunction(that.options.onclose_search)){
                        noptions.onclose = that.options.onclose_search;
                    }else{
                        noptions.onclose = function(event){

                            if(that.search_faceted.is(':visible')){
                               
                                $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ 
                                    {reset:true, search_realm:that.options.search_realm} ]);  //global app event to clear views

                                that.search_faceted.hide();
                                that.search_tree.show();
                                that._adjustAccordionTop();
                            }
                        };
                    }
                    
                    if(!$.isFunction($('body')['search_faceted'])){
                        $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/search/search_faceted.js', function() {
                            that.doSearch( qname, qsearch, ele );
                        });
                        return;
                    }else
                    if(this.search_faceted.html()==''){ //not created yet
                        this.search_faceted.search_faceted( noptions );
                    }else{
                        this.search_faceted.search_faceted('option', noptions ); //assign new parameters
                    }
                    
                    window.hWin.HAPI4.SystemMgr.user_log('search_Record_faceted');

                }else{

                    window.hWin.HEURIST4.msg.showMsgErr("This faceted search is in an old format. "
                        + "Please delete it and add a new one (right click in the saved search list). "
                        + "We apologise for this inconvenience, but we have added many new features to the facet search "
                        + "function and it was not cost-effective to provide backward compatibility (given the relative "
                        + "ease of rebuilding searches and the new features now available).")
                }
                
            }else if(params.type<0){

                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise saearch due to corrupted parameters. Please remove and re-create this search.'), null, window.hWin.HR('Warning'));
                return;
            }else{

                var request = params;

                request.rules = window.hWin.HEURIST4.util.cleanRules(request.rules);
                
                //query is not defenied, but rules are - this is pure RuleSet - apply it to current result set
                if(Hul.isempty(request.q)&&!Hul.isempty(request.rules)){

                    if(this.currentSearch){
                        this.currentSearch.rules = Hul.cloneJSON(request.rules);
                    }
                    
                    //target is required
                    if(! window.hWin.HAPI4.SearchMgr.doApplyRules( this, request.rules, 
                                        (request.rulesonly==1 || request.rulesonly)?1:0, this.options.search_realm ) ){
                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('RuleSets require an initial search result as a starting point.'),
                            3000, window.hWin.HR('Warning'), ele);
                    }else{
                        window.hWin.HAPI4.SystemMgr.user_log('search_Record_applyrules');
                    }
                    
                }else if(Hul.isempty(request.q)){

                    window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise this search due to corrupted parameters. Please redefine filter parameters.'), null, window.hWin.HR('Warning'));                    
                    
                }else{
                    //additional params
                    request.detail = 'detail';
                    request.source = this.element.attr('id');
                    request.qname = qname;
                    request.search_realm = this.options.search_realm;
                    
                    window.hWin.HAPI4.SystemMgr.user_log('search_Record_savedfilter');

                    //get hapi and perform search
                    window.hWin.HAPI4.SearchMgr.doSearch( this, request );
                }
            }
        }

    },
    
    //
    //
    //
    _deleteSavedSearch: function(svsID, svsTitle, callback){


        var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
        if(!svs) return;

        window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Delete '"+ svsTitle  +"'? Please confirm"),  function(){

            window.hWin.HAPI4.SystemMgr.ssearch_delete({ids:svsID, UGrpID: svs[2]},
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        //remove from UI
                        callback.apply(this);
                        //remove from
                        delete window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                    }
                }

            );
            }, "Confirmation");

    }

    
    // open edit dialog
    // mode: saved, rules, faceted
    // groupID - current user or workgroup
    // svsID - saved search id
    // squery
    , editSavedSearch: function(mode, groupID, svsID, squery, node, is_short){

        var that = this;

        var callback = function(event, response) {
            if(response.isNewSavedFilter){     //new saved search

                //update tree after addition - add new search to root
                if(Hul.isnull(node)){
                    groupID = response.svs_UGrpID
                    if(groupID == window.hWin.HAPI4.currentUser.ugr_ID){
                        groupID = response.domain; //all or bookmarks
                    }
                    var tree = that.treeviews[groupID];
                    node = tree.rootNode;
                    node.folder = true;
                }

                node.addNode( { title:response.svs_Name, key: response.new_svs_ID}
                    , node.folder?"child":"after" );

                that._saveTreeData( groupID );
                $("#addlink"+groupID).css('display', 'none');

            }else if(node){ //edit is called from this widget only - otherwise we have to implement search node by svsID
                //edit - changed only title in treeview
                node.setTitle(response.svs_Name);
                node.render(true);
                that._saveTreeData( groupID );
            }
        };


        if( true ) { //}!Hul.isnull(this.hSvsEdit) && $.isFunction(this.hSvsEdit)){ //already loaded     @todo - load dynamically

            if(Hul.isnull(svsID) && Hul.isempty(squery)){
                squery = window.hWin.HEURIST4.util.cloneJSON(this.currentSearch);
            }

            if(null == this.edit_dialog){
                this.edit_dialog = new hSvsEdit();
            }
            //this.edit_dialog.callback_method  = callback;
            this.edit_dialog.showSavedFilterEditDialog( mode, groupID, svsID, squery, is_short, callback );

        }else{
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/search/svs_edit.js',
                function(){ that.hSvsEdit = hSvsEdit; that.editSavedSearch(mode, groupID, svsID, squery); } );
        }

    },


    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        if(this.edit_dialog) {
            this.edit_dialog.remove();
        }

        if(this.filter_div){
            this.btn_save.remove();
            this.btn_reset.remove();
            this.filter_div.remove();
        }
        if(this.btn_search_save) this.btn_search_save.remove();
        this.accordeon.remove();
        //this.tree.remove();

        this.search_tree.remove();
        this.search_faceted.remove();
    }

    , _showDbSummary: function(){

        var url = window.hWin.HAPI4.baseURL+ "hclient/framecontent/visualize/databaseSummary.php?popup=1&db=" + window.hWin.HAPI4.database;

        var body = this.document.find('body');
        var dim = {h:body.innerHeight(), w:body.innerWidth()};

        window.hWin.HEURIST4.msg.showDialog(url, { height:dim.h*0.8, width:dim.w*0.8, title:'Database Summary',} );

    },
    
    //
    //
    //
    _getFilterString: function( svs_ID ){
        
        var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svs_ID];
        if(svs ){
            var qsearch = svs[_QUERY];
            var prms = Hul.parseHeuristQuery(qsearch); //url to json
            if(prms.type!=3){
                var res = Hul.hQueryStringify(prms); //json to string

                if(!Hul.isempty(res)){
                            var dummy = document.createElement("input");
                            dummy.value = res;
                            document.body.appendChild(dummy);
                            dummy.select();
                            try {

                                if(document.execCommand("copy"));  // Security exception may be thrown by some browsers.
                                {
                                    window.hWin.HEURIST4.msg.showMsgFlash('Query is in clipboard');
                                }
                                
                            } catch (ex) {
                                console.warn("Copy to clipboard failed.", ex);
                            } finally {
                                document.body.removeChild(dummy);
                            }        
                }    
            }
        }
        
    },
    
    //
    //
    //
    _showEmbedDialog: function(svs_ID){
        
        var query = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&ll=WebSearch&views=list,map&searchIDs='+svs_ID;
        
        window.hWin.HEURIST4.ui.showPublishDialog({mode:'websearch', url: query, url_encoded: query});
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
