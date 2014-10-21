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

$.widget( "heurist.search_links", {

    // default options
    options: {
        isapplication:true,  // send and recieve the global events

        searchdetails: "map", //level of search results  map - with details, structure - with detail and structure

        // callbacks
        onsearch: null,  //on start search
        onresult: null   //on search result
    },

    currentSearch: null,
    currentMode:'saved', //faceted|tags

    // the constructor
    _create: function() {

        var that = this;

        this.search_list = $( "<div>" ).appendTo( this.element );
        this.search_faceted = $( "<div>" ).appendTo( this.element ).hide();


        /*
        this.btn_1 = $( '<input type="radio" id="ssradio1" name="ssradio" checked="checked" value="saved"/>' )
        .appendTo( this.mode_selector );
        $( '<label for="ssradio1">'+top.HR('Saved')+'</label>').appendTo( this.mode_selector );
        this.btn_2 = $( '<input type="radio" id="ssradio2" name="ssradio" checked="checked" value="faceted"/>' )
        .appendTo( this.mode_selector );
        $( '<label for="ssradio1">'+top.HR('Faceted')+'</label>').appendTo( this.mode_selector );
        this.btn_3 = $( '<input type="radio" id="ssradio3" name="ssradio" checked="checked" value="tags"/>' )
        .appendTo( this.mode_selector );
        $( '<label for="ssradio1">'+top.HR('Tags')+'</label>').appendTo( this.mode_selector );
        */

        this.mode_selector = $( "<div>" )
        .css({"font-size":"0.7em","height":"2.4em","text-align":"center" })
        .html('<input type="radio" id="ssradio1" name="ssradio" checked="checked" value="saved"/>'
            +'<label for="ssradio1">'+top.HR('Saved')+'</label>'
            +'<input type="radio" id="ssradio2" name="ssradio" value="faceted"/>'
            +'<label for="ssradio2">'+top.HR('Faceted')+'</label>'
            +'<input type="radio" id="ssradio3" name="ssradio" value="tags"/>'
            +'<label for="ssradio3">'+top.HR('Tags')+'</label>')
        .buttonset()
        .click(function( event ) {
            that.currentMode = $("input[name='ssradio']:checked").val(); //event.target.value;
            that._refresh();
        })
        .appendTo( this.search_list );

        this.user_groups_container = $( "<div>" )
        .css({"top":"2.4em","bottom":0,"left":0,"right":4,"position":"absolute"})
        .appendTo( this.search_list );

        this.user_groups = $( "<div>" )
        .css({"font-size":"0.9em","overflow-y":"auto","height":"100%"})
        .appendTo( this.user_groups_container )
        .hide();


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
        if(this.currentMode=='tags' && !top.HAPI4.currentUser.usr_Tags){

            top.HAPI4.RecordMgr.tag_get({UGrpID:'all', info:'full'},
                function(response) {
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        top.HAPI4.currentUser.usr_Tags = response.data;
                        that._prepareTags();
                        that._updateAccordeon();
                    }else{
                        Hul.showMsgErr(response);
                    }
            });

        }else if(!top.HAPI4.currentUser.usr_SavedSearch){

            top.HAPI4.SystemMgr.ssearch_get(
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        top.HAPI4.currentUser.usr_SavedSearch = response.data;
                        that._updateAccordeon();
                    }
            });
        }else{
            this._updateAccordeon();
        }

    },

    _prepareTags: function(){

        var gtags = top.HAPI4.currentUser.usr_Tags;
        var name, usage;

        this.tagsMinMax = {};

        for (var usrID in gtags){
            var tags = gtags[usrID];
            var min=0,max=0,cnt=0;

            for (var tagID in tags)
            {
                usage = parseInt(tags[tagID][3]);
                if(usage>0){
                    //name = tags[tagID][0];
                    min = Math.min(min, usage);
                    max = Math.max(max, usage);
                    cnt++;
                }
            }
            if(cnt>0){
                this.tagsMinMax[usrID] = [min,max,cnt];
            }
        }
    },

    //
    // redraw accordeon with the list of saved searches, tags, faceted searches
    //
    _updateAccordeon: function()
    {
        var islogged = (top.HAPI4.currentUser.ugr_ID>0);

        this.user_groups.hide().empty();

        if(islogged){

            this.mode_selector.show();
            this.user_groups_container.css('top',"2.4em");


            if(this.currentMode=='tags'){
                this.user_groups.append(
                    $('<div>')
                    .append(this._defineHeader(top.HR('Personal Tags')))
                    .append( this._define_GroupContent(top.HAPI4.currentUser.ugr_ID, "bookmark") ));
            }else{
                this.user_groups.append(
                    $('<div>')
                    .append(this._defineHeader(top.HR('My Bookmarks'), "bookmark"))
                    .append( this._define_GroupContent(top.HAPI4.currentUser.ugr_ID, "bookmark") ));
                this.user_groups.append(
                    $('<div>')
                    .append( this._defineHeader(top.HR('All Records'), "all"))
                    .append( this._define_GroupContent(top.HAPI4.currentUser.ugr_ID) ));
            }

            if(!top.HAPI4.currentUser.usr_GroupsList){

                var that = this;

                //get details about user groups (names etc)
                top.HAPI4.SystemMgr.mygroups(
                    function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            top.HAPI4.currentUser.usr_GroupsList = response.data;
                            that._updateGroups();
                        }
                });
            }else{
                this._updateGroups();
            }

        }else{

            this.mode_selector.hide();
            this.user_groups_container.css('top',0);
            this.user_groups
            .append(
                $('<div>')
                .append(this._defineHeader(top.HR('Predefined searches'), null))
                .append( this._define_GroupContent( top.HAPI4.currentUser.ugr_ID) ))
            .show();

        }

    },

    /**
    * define header for each accordeon
    * 
    * domain - bookmark or usergroup ID
    */
    _defineHeader: function(name, domain){

        var $header = $('<h3>'+name+'</h3>');
        var that = this;

        if(this.currentMode!='tags' && domain!=null){
            $('<div>',{
                id: 'rec_add_link',
                title: top.HR(this.currentMode=='faceted'?'Define new faceted search':'Click to save current search')
            })
            .button({icons: {primary: "ui-icon-circle-plus"}, text:false})
            .click(function( event ) {
                event.preventDefault();
                that.editSavedSearch(null, null, domain);
                return false;
            })
            .appendTo($header);
        }
        return $header
    },

    /**
    * fill content of group
    * domain - all or bookmark
    */
    _define_GroupContent: function(ugr_ID, domain){
        if(this.currentMode=='tags'){
            return this._define_TagCloud(ugr_ID, domain);
        }else{
            return this._define_SVSlist(ugr_ID, domain);
        }
    },

    /**
    * create cloud of tags
    * domain - all or bookmark
    */
    _define_TagCloud: function(ugr_ID, domain){

        var $div = $('<div>',{class:'tagsCloud'});
        var that = this;

        if(this.tagsMinMax[ugr_ID] && this.tagsMinMax[ugr_ID][2]>0){

            var $ul = $('<ul>').appendTo($div);

            var tags = top.HAPI4.currentUser.usr_Tags[ugr_ID];
            var min = this.tagsMinMax[ugr_ID][0];
            var max = this.tagsMinMax[ugr_ID][1];
            var cnt = 0;

            for (var tagID in tags)
            {
                var usage = parseInt(tags[tagID][3]);
                if(usage>0){
                    var name = tags[tagID][0];

                    var group = Math.floor( (usage - min) * 5 / (max - min) );
                    group++;if(group>5) group=5;

                    $ul.append( $('<li>',{class:'tag'+group})
                        .append($("<A>",{href:"#"}).text(name).on("click", function(event){
                            var name = $(event.target).text();
                            that._doSearch2(name, 'tag:"'+name+'"');
                    } )) );

                    cnt++;
                    if(cnt>100) break;
                }
            }


        }
        return $div;
    },

    //
    /**
    * create list of saved searches for given user/group
    * domain - all or bookmark
    */
    _define_SVSlist: function(ugr_ID, domain){

        var ssearches = top.HAPI4.currentUser.usr_SavedSearch;
        var cnt = 0;
        var that = this;

        var $ul = $('<div>'); //.css({'padding': '0em 0em !important', 'background-color':'red'});

        //add predefined searches
        if(ugr_ID == top.HAPI4.currentUser.ugr_ID){  //if current user domain may be all or bookmark

            domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';

            $ul.attr('id', 'svsu-'+ugr_ID+'-'+domain);

            if(this.currentMode=='saved'){

                var s_all = "?w="+domain+"&q=sortby:-m after:\"1 week ago\"&label=Recent changes";
                var s_recent = "?w="+domain+"&q=sortby:-m&label=All records";

                $ul.append( this._add_SVSitem(top.HR('Recent changes'), null, s_all));
                $ul.append( this._add_SVSitem(top.HR('All (date order)'), null, s_recent));

                cnt = 2;
            }
        }else{
            $ul.attr('id', 'svsu-'+ugr_ID+'-all');
        }

        //_NAME = 0, _QUERY = 1, _GRPID = 2
        
        var facet_params, domain2;

        for (var svsID in ssearches)
        {
            if(svsID && ssearches[svsID][_GRPID]==ugr_ID){


                try {
                    facet_params = $.parseJSON(ssearches[svsID][1]);
                    if(this.currentMode=='saved') continue;
                }
                catch (err) {
                    // Do something about the exception here
                    if(this.currentMode=='faceted') continue;
                }


                if(ugr_ID==top.HAPI4.currentUser.ugr_ID){  //detect either boomark or all

                    if(this.currentMode=='saved'){
                        var prms = Hul.parseHeuristQuery(ssearches[svsID][_QUERY]);
                        //var qsearch = prms[0];
                        domain2  = prms.w;
                        if(Hul.isempty(prms.q)) continue; //do not show saved searches without Q (rules) in this list
                    }else{
                        domain2  = facet_params.domain;
                    }

                    if(domain!=domain2){
                        continue;
                    }

                }

                var name = ssearches[svsID][_NAME];
                $ul.append( this._add_SVSitem(name, svsID) );
                cnt++;
            }
        }

        return $ul;

    },

    /**
    * create item for given saved search
    * return div
    */
    _add_SVSitem: function(name, qid, squery){

        //+(qid?' svsid="'+qid+'"':'')+'>'+(squery?'<div style="display:none;">'+squery+'</div>':'')
        // .css({'cursor':'pointer', 'text-decoration':'underline'})
        var that = this;

        var $resdiv = $('<div>')
        .addClass('saved-search')
        .append($('<div>')
            .addClass('name')
            .html(name)
            .on("click", function(){
                if (qid && top.HAPI4.currentUser.usr_SavedSearch){
                    squery = top.HAPI4.currentUser.usr_SavedSearch[qid][_QUERY];
                }
                that._doSearch2(name, squery);
            } )
        );
        if(qid && top.HAPI4.currentUser.usr_SavedSearch){

            $resdiv.find('.name').css('width','75%').attr('id','svs-'+qid);

            $resdiv
            .append( $('<div>')
                .addClass('edit-delete-buttons')
                .append( $('<div>', { title: top.HR('Edit '+this.currentMode+' search') })
                    .button({icons: {primary: "ui-icon-pencil"}, text:false})
                    .click(function( event ) {
                        that.editSavedSearch(qid);
                }) )
                .append($('<div>',{title: top.HR('Delete '+this.currentMode+' search') })
                    .button({icons: {primary: "ui-icon-close"}, text:false})
                    .click(function( event ) {
                        that._deleteSavedSearch(qid);
                }) )
            );
        }

        return $resdiv;
    },


    _updateGroups: function(){

        var groups = top.HAPI4.currentUser.usr_GroupsList;

        for (var groupID in groups)
        {
            if(groupID){
                var name = groups[groupID][1];
                this.user_groups.append(
                    $('<div>')
                    .append( this._defineHeader(name, groupID))
                    .append( this._define_GroupContent(groupID) ));

            }
        }

        this.user_groups.children()
        .accordion({
            header: "> h3",
            heightStyle: "content",
            collapsible: true
        });
        this.user_groups.show();


        /* common accordeon
        this.user_groups
        .accordion({
        header: "> div > h3",
        heightStyle: "content"
        })
        .sortable({
        axis: "y",
        handle: "h3",
        stop: function( event, ui ) {
        // IE doesn't register the blur when sorting
        // so trigger focusout handlers to remove .ui-state-focus
        ui.item.children( "h3" ).triggerHandler( "focusout" );
        }
        })
        .show();
        */

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

    _doSearch2: function(qname, qsearch){

        if ( qsearch ) {

            if(this.currentMode=='faceted'){

                var facet_params;
                try {
                    facet_params = $.parseJSON(qsearch);
                }
                catch (err) {
                    // Do something about the exception here
                    Hul.showMsgDlg(top.HR('Can not init faceted search. Corrupted parameters'), null, "Error");
                }

                var that = this;
                //use special widget
                if($.isFunction($('body').search_faceted)){ //already loaded
                    //init faceted search
                    this.search_faceted.show();
                    this.search_list.hide();

                    var noptions= { query_name:qname, params:facet_params,
                        onclose:function(event){
                            that.search_faceted.hide();
                            that.search_list.show();
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

    },

    /**
    * Assign values to UI input controls
    * 
    * squery - need for new - otherwise it takes currentSearch
    * domain need for new 
    */
    _fromDataToUI: function(svsID, squery, domain){

        var $dlg = this.edit_dialog;
        if($dlg){
            $dlg.find('.messages').empty();

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_query = $dlg.find('#svs_Query');
            var svs_ugrid = $dlg.find('#svs_UGrpID');
            var svs_rules = $dlg.find('#svs_Rules');
            var svs_notes = $dlg.find('#svs_Notes');

            var isEdit = (parseInt(svsID)>0);

            if(isEdit){
                var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];

                var request = Hul.parseHeuristQuery(svs[_QUERY]);
                domain  = request.w;

                svs_ugrid.val(svs[2]==top.HAPI4.currentUser.ugr_ID ?domain:svs[_GRPID]);
                svs_ugrid.parent().hide();
                
                svs_id.val(svsID);
                svs_name.val(svs[_NAME]);
                svs_query.val( request.q );
                svs_rules.val( request.rules );
                svs_notes.val( request.notes );
                

            }else{ //add new saved search

                svs_id.val('');
                svs_name.val('');
                svs_rules.val('');
                svs_notes.val('');
                //var domain = 'all';
                
                if(!Hul.isempty(squery)) {
                    svs_query.val( squery );
                }else if(Hul.isnull(this.currentSearch)){
                    svs_query.val( '' );
                }else{
                    //domain = this.currentSearch.w;
                    //domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';
                    svs_query.val( this.currentSearch.q );
                    svs_rules.val( top.HEURIST4.util.isArray(this.currentSearch.rules)?JSON.stringify(this.currentSearch.rules):this.currentSearch.rules );   //@todo - stringigy????
                }

                //fill with list of user groups in case non bookmark search
                var selObj = svs_ugrid.get(0);
                if(domain=="bookmark"){
                    svs_ugrid.empty();
                    Hul.addoption(selObj, 'bookmark', top.HR('My Bookmarks'));
                }else{
                    Hul.createUserGroupsSelect(selObj, top.HAPI4.currentUser.usr_GroupsList,
                        [{key:'all', title:top.HR('All Records')}],
                        function(){
                            svs_ugrid.val(top.HAPI4.currentUser.ugr_ID);
                    });
                    svs_ugrid.val(domain);
                }
                svs_ugrid.parent().show();
            }
        }
    }

    , _updateAfterSave: function(request){

        if( parseInt(request.svs_ID) > 0 ){
            $('#svs-'+request.svs_ID).html(request.svs_Name);
        }else{
            $('#svsu-'+request.svs_UGrpID+'-'+request.domain).append( this._add_SVSitem(request.svs_Name, request.new_svs_ID) );
        }
    }
    
    , _editRules:function(ele_rules){
        
                var url = top.HAPI4.basePath+ "page/ruleBuilderDialog.php?db=" + top.HAPI4.database+'&rules' + ele_rules.val();
                
                Hul.showDialog(url, { width:1200, callback: 
                    function(res){
                        if(!Hul.isempty(res)) {
                            if(res.mode == 'apply'){
                                if(Hul.isempty(res.rules)){
                                    ele_rules.val( '' );
                                }else{
                                    ele_rules.val( JSON.stringify(res.rules) );    
                                }
                                
                            }
                        }
                    }});
        
    }

    /**
    * Public method to add/edit saved search - it opens dialog and allows to edit/create saved search
    * 
    * @param svsID
    * @param domain - bookmark or usergroupID
    */
    , editSavedSearch: function(svsID, squery, domain){

        var that = this;
        if( this.currentMode == "faceted") {

            this._showSearchFacetedWizard( {svsID:svsID, domain:domain, onsave: function(event, request){
                that._updateAfterSave(request);
            } });

        }else if(  this.edit_dialog==null )
        {
            var $dlg = this.edit_dialog = $( "<div>" ).appendTo( this.element );

            //load edit dialogue
            $dlg.load("apps/svs_edit.html?t="+(new Date().time), function(){

                //find all labels and apply localization
                $dlg.find('label').each(function(){
                    $(this).html(top.HR($(this).html()));
                })
                
                $dlg.find("#svs_Rules_edit")                  
                    .button({icons: {primary: "ui-icon-pencil"}, text:false})
                    .css({'height':'16px', 'width':'16px'})
                    .click(function( event ) {
                        that._editRules( $dlg.find('#svs_Rules') );
                    });
                

                var allFields = $dlg.find('input');

                that._fromDataToUI(svsID, squery, domain);

                function __doSave(){

                    var message = $dlg.find('.messages');
                    var svs_id = $dlg.find('#svs_ID');
                    var svs_name = $dlg.find('#svs_Name');
                    var svs_query = $dlg.find('#svs_Query');
                    var svs_ugrid = $dlg.find('#svs_UGrpID');
                    var svs_rules = $dlg.find('#svs_Rules');
                    var svs_notes = $dlg.find('#svs_Notes');

                    allFields.removeClass( "ui-state-error" );

                    var bValid = Hul.checkLength( svs_name, "Name", message, 3, 25 )
                    && Hul.checkLength( svs_query, "Query", message, 1 );


                    if(bValid){

                        var svs_ugrid = svs_ugrid.val();
                        var query_to_save = []; 
                        
                        var domain = 'all';    
                        if(svs_ugrid=="all" || svs_ugrid=="bookmark"){
                            domain = svs_ugrid;    
                            svs_ugrid = top.HAPI4.currentUser.ugr_ID;
                            if(domain!="all"){
                                query_to_save.push('w='+domain);
                            }
                        }
                        if(!Hul.isempty(svs_query.val())){
                           query_to_save.push('q='+svs_query.val());
                        }
                        if(!Hul.isempty(svs_rules.val())){
                           query_to_save.push('rules='+svs_rules.val());
                        }
                        if(!Hul.isempty(svs_notes.val())){
                           query_to_save.push('notes='+svs_notes.val());
                        }

                        var request = {  //svs_ID: svsID, //?svs_ID:null,
                            svs_Name: svs_name.val(),
                            svs_Query: '?'+query_to_save.join('&'),
                            svs_UGrpID: svs_ugrid,
                            domain:domain};

                        var isEdit = ( parseInt(svs_id.val()) > 0 );
                        if(isEdit){
                            request.svs_ID = svs_id.val();
                        }

                        //
                        top.HAPI4.SystemMgr.ssearch_save(request,
                            function(response){
                                if(response.status == top.HAPI4.ResponseStatus.OK){

                                    var svsID = response.data;

                                    if(!top.HAPI4.currentUser.usr_SavedSearch){
                                        top.HAPI4.currentUser.usr_SavedSearch = {};
                                    }

                                    top.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

                                    $dlg.dialog( "close" );

                                    request.new_svs_ID = svsID;

                                    that._updateAfterSave(request);


                                }else{
                                    message.addClass( "ui-state-highlight" );
                                    message.text(response.message);
                                }
                            }

                        );

                    }
                }

                allFields.on("keypress",function(event){
                    var code = (event.keyCode ? event.keyCode : event.which);
                    if (code == 13) {
                        __doSave();
                    }
                });


                $dlg.dialog({
                    autoOpen: false,
                    height: 260,
                    width: 350,
                    modal: true,
                    resizable: false,
                    title: top.HR('Edit saved search'),
                    buttons: [
                        {text:top.HR('Save'), click: __doSave},
                        {text:top.HR('Cancel'), click: function() {
                            $( this ).dialog( "close" );
                        }}
                    ],
                    close: function() {
                        allFields.val( "" ).removeClass( "ui-state-error" );
                    }
                });

                $dlg.dialog("open");

            });
        }else{
            //show dialogue
            this._fromDataToUI(svsID, squery, domain);
            this.edit_dialog.dialog("open");
        }

    },

    _deleteSavedSearch: function(svsID){


        var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];
        if(!svs) return;

        Hul.showMsgDlg(top.HR("Delete? Please confirm"),  function(){

            top.HAPI4.SystemMgr.ssearch_delete({ids:svsID, UGrpID: svs[2]},
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){

                        //remove from UI
                        $('#svs-'+svsID).parent().remove();
                        //remove from
                        delete top.HAPI4.currentUser.usr_SavedSearch[svsID];

                    }else{
                        Hul.showMsgErr(response);
                    }
                }

            );
            }, "Confirmation");                 

    },

    //show faceted search wizard
    _showSearchFacetedWizard: function( params ){

        if($.isFunction($('body').search_faceted_wiz)){ //already loaded
            showSearchFacetedWizard(params);
        }else{
            $.getScript(top.HAPI4.basePath+'apps/search_faceted_wiz.js', function(){ showSearchFacetedWizard(params); } );
        }

    },  


    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        if(this.edit_dialog) {
            this.edit_dialog.remove();
        }

        this.user_groups.remove();
        this.mode_selector.remove();
        this.user_groups_container.remove();

        this.search_list.remove(); 
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