/**
*
* dh_search.js (Digital Harlem) : Special navigation panel: saved, faceted and tag searches
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

var Hul = window.hWin.HEURIST4.util;

//constants
//const _NAME = 0, _QUERY = 1, _GRPID = 2, _ISFACETED=3;

$.widget( "heurist.dh_search", {

    // default options
    options: {
        UGrpID: 1007, // 1915-1930 user group for 'original' Digital Harlem map. 1010 is Year of the Riot (1935) only
        search_at_init: false,  //open first saved search (if true) or with given Id (if numeric)
        uni_ID: 0
    },

    _currenttype:null,  //obsolete
    usr_SavedSearch: null,   //list of saved searches for given group

    _currentRequest:null,
    _currentRecordset:null,
    add_filter:null,
    add_filter_original:null,

   _isDigitalHarlem:true,
   
   _first_search_in_list:0,

    // the constructor
    _create: function() {

        // important - it overwrite default search manager - we use special one
        // it adds some parameters to search request and postprocess the result
        this._isDigitalHarlem = (window.hWin.HAPI4.sysinfo['layout'].indexOf('DigitalHarlem')==0);
        if(this._isDigitalHarlem){
                window.hWin.HAPI4.RecordSearch = new hSearchMinimalDigitalHarlem();
        }
                


        var that = this;

        this.search_list = $( "<div>" ).css({'height':'100%', 'padding':'40px'}).appendTo( this.element );

        this.search_pane  = $( "<div>" ).css({'height'
            :'100%' }).appendTo( this.element ).hide();

        // BUTTON - KEEP CURRENT RESULT SET AS LAYER
        this.res_div = $('<div>')
        .css({'margin-botton':'1em', 'font-size':'0.9em','padding':'10px','text-align':'center'})
        .appendTo(this.search_pane).hide();
        this.res_lbl = $('<label>').css('padding','0.4em').appendTo(this.res_div);
        //this.res_name = $('<input>').appendTo(this.res_div);
        
        if(this._isDigitalHarlem){
            this.res_btn_add = $('<button>', {text:window.hWin.HR('Keep On Map')})
            .button({icons:{primary:'ui-icon-circle-plus'}})
            .on("click", function(event){ that._onAddLayer(); } )
            .appendTo(this.res_div);
        }

        this.res_div_progress = $('<div>')
        .css({'height':'60px',
            'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center' })
        .appendTo(this.search_pane).hide();


        //this.res_div_input =  $('<div>', {id:'res_div_input'}).appendTo(this.res_div).hide();
        //this.res_name = $('<input>').appendTo(this.res_div_input);

        this.search_faceted = $( "<div>", {id:this.element.attr('id')+'_search_faceted'} )
        //.css({'height':'100%'})
        .css({'position':'absolute',top:'3em',bottom:'0','width':'100%'})
        .appendTo( this.search_pane )

        if(this.element.is(':visible')){
            this._refresh();
        }

        if(this._isDigitalHarlem){
            this.element.on("myOnShowEvent", function(event){
                if( event.target.id == that.element.attr('id')){
                    that._refresh();
                }
            });
        }
        
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH+' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART+'  '+
                        window.hWin.HAPI4.Event.ON_SYSTEM_INITED,
            function(e, data) {
                // show progress div
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){
                    if(data && !window.hWin.HEURIST4.util.isempty(data.q)){
                        that.res_div.hide();
                        that.res_div_progress.show();
                    }
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){
                    that.res_div.show();
                    that.res_div_progress.hide();
                    
                }else if(e.type == window.hWin.HAPI4.Event.ON_SYSTEM_INITED){
//alas it does not work - it is triggered before this widget is created                    
                    
                    if(that._isDigitalHarlem){
                     
                        var init_search = window.hWin.HAPI4.get_prefs('defaultSearch');
                        if(false && !window.hWin.HEURIST4.util.isempty(init_search)){
                            var request = {q: init_search, w: 'a', f: 'map', source:'init' };
                            setTimeout(function(){
                                window.hWin.HAPI4.RecordSearch.doSearch(document, request);
                            }, 3000);
                        }else{
                            //trigger search finish to init some widgets
                            $(document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, {recordset:null} );   
                        }
                        
                    }
                    
                }
                
        });

        var lt = window.hWin.HAPI4.sysinfo['layout'];
        if(lt=='Beyond1914' || lt=='UAdelaide'){
            //find on page external search_value and search_button elements  - for USyd Book of Remembrance  external search form
            var ele_search = $('#search_query'); //$(window.hWin.document).find('#search_query');
            if(ele_search.length>0){
                
                this._on( ele_search, {
                    keypress: function(e){
                        var code = (e.keyCode ? e.keyCode : e.which);
                        if (code == 13) {
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                            
                            that.doSearch3( ele_search.val() );
                        }
                    }
                });
                
                var btn_search = $('#search_button');
                this._on( btn_search, {
                    click:  function(){
                            that.doSearch3( ele_search.val() );}
                });
            }
            
            // for expertnation layouts the container page is hidden initially
            // need to refresh-init when it becomes visible
            $('.bor-page-search').on("myOnShowEvent", function(event){
                if( event.target.id == 'bor-page-search'){
                    that._refresh();
                }
            });
            
            
        }
        
        
        
    }, //end _create

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        //
        $(this.document).off( window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH+' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART );

        this.search_list.remove();
        this.search_faceted.remove();

        if(this.btn_fi_menu) {
            this._off( this.btn_fi_menu, 'click');
            this.btn_fi_menu.remove();
            this.menu_fi.remove();
        }
    },



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
        if(!this.usr_SavedSearch){  //find all saved searches for current user

            window.hWin.HAPI4.SystemMgr.ssearch_get( {UGrpID: this.options.UGrpID},
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.usr_SavedSearch = response.data;
                        that._refresh();
                    }
            });
        }else{
            this._updateSavedSearchList();
        }

    },

    isInited: function(){
        return (this.usr_SavedSearch!=null);  
    },

    //
    // redraw list of saved searches
    //
    _updateSavedSearchList: function(){
        this.search_list.empty();
        
        
        //find Map Documents (19) for featured individuals ---------------------------------------
        if(this._isDigitalHarlem){
            
            var query = null;
            // "Featured Individuals"
            if(window.hWin.HAPI4.sysinfo['layout']=='DigitalHarlem1935'){
                query = {"t":"19","f:144":"4749,4819","sortby":"f:94"};
            }else if(window.hWin.HAPI4.sysinfo['layout']=='DigitalHarlem') {
                query = {"t":"19","f:144":"4801,4819","sortby":"f:94"};
            }
            
            var request = { q: query,
                w: 'a',
                detail: 'header',
                source:this.element.attr('id') };
            //perform search
            window.hWin.HAPI4.RecordMgr.search(request, function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    var resdata = new hRecordSet(response.data);
                    //add SELECT and fill it with values
                    var smenu = '', idx;
                    var records = resdata.getRecords();
                    for(idx in records){
                        if(idx)
                        {
                            var record = records[idx];
                            var recID  = resdata.fld(record, 'rec_ID'),
                                recName = resdata.fld(record, 'rec_Title');
                            smenu = smenu + '<li id="fimap'+recID+'"><a href="#">'+recName+'</a></li>';
                        }
                    }
                    if(smenu=='') return;

                    that.btn_fi_menu = $( "<button>", {
                        text: window.hWin.HR('select a person ')+'...'
                    })
                    .css({'width':'100%','margin-top':'0.4em'})
                    .appendTo(  that.search_list )
                    .button({icons: {
                        secondary: "ui-icon-triangle-1-s"
                    }});

                    that.menu_fi = $('<ul>'+smenu+'</ul>')   //<a href="#">
                    .css({'position':'absolute', 'width':that.btn_fi_menu.width(), zIndex:99999 })
                    .appendTo( that.document.find('body') )
                    .menu({
                        select: function( event, ui ) {
                            var map_rec_id =  Number(ui.item.attr('id').substr(5));
                            
                            var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('dh_maps');
                            if(app && app.widget){
                                $(app.widget).dh_maps('loadMapDocument', map_rec_id);
                            }

    /*    
                            var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');  //window.hWin.HAPI4.LayoutMgr.appGetWidgetById('heurist_Map');
                            if(app && app.widget){
                                //switch to Map Tab
                                window.hWin.HAPI4.LayoutMgr.putAppOnTop('app_timemap');

                                //load Map Document
                                $(app.widget).app_timemap('loadMapDocumentById', map_rec_id);
                            }
    */

                    }})
                    .hide();

                    that._on( that.btn_fi_menu, {
                        click: function() {
                            $('.ui-menu').not('.horizontalmenu').not('.heurist-selectmenu').hide(); //hide other
                            var menu = $( that.menu_fi )
                            //.css('min-width', '80px')
                            .show()
                            .position({my: "right top", at: "right bottom", of: that.btn_fi_menu });
                            menu.width( that.btn_fi_menu.width() );
                            $( document ).one( "click", function() { menu.hide(); });
                            return false;
                    }});


                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });

            
            
        }//_isDigitalHarlem
        
        
        

        var that = this, facet_params = null, isfaceted = false, cnt = 0;

        this._first_search_in_list = 0;

        for (var svsID in this.usr_SavedSearch)
        {

            isfaceted = false;
            try {
                facet_params = $.parseJSON(this.usr_SavedSearch[svsID][_QUERY]);

                isfaceted = (facet_params && Hul.isArray(facet_params.rectypes));
            }
            catch (err) {
            }

            this.usr_SavedSearch[svsID].push(isfaceted);
            
            if(this._first_search_in_list<1){
                this._first_search_in_list = svsID; //keep first
            }

            if(true || isfaceted){

                $('<button>', {text: this.usr_SavedSearch[svsID][_NAME], 'data-svs-id':svsID })
                .css({'width':'100%','margin-top':'0.4em'})
                .button().on("click", function(event){
                    that.doSearch2( $(this).attr('data-svs-id') );
                })
                .appendTo(this.search_list);
                cnt++;
            }
        }
        
        if(this._isDigitalHarlem && cnt==1){
            this.doSearch2( this._first_search_in_list );
        }else if(this.options.search_at_init==true || this.options.search_at_init>0){
            this.doSearch2( this.options.search_at_init==true?this._first_search_in_list:this.options.search_at_init);    
        }
                    
                    
        
        //add featured maps
    },

    // ExpertNation
    // apply search as preliminary filter for current faceted search
    //
    doSearch3: function(search_value){
        
       $('#main_pane > .clearfix').hide(); //hide all
       $('.bor-page-search').show();
       
       var search_value_original = null;
       
       if(search_value!=''){
           
           if(typeof search_value === 'string'){
               
                search_value_original = search_value;
           
                var DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], //1
                    DT_GIVEN_NAMES = window.hWin.HAPI4.sysinfo['dbconst']['DT_GIVEN_NAMES'],
                    DT_EXTENDED_DESCRIPTION = 134;//window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']; //4      
                
                var criteria = [];
                var criteria_or = {any:[]};
                
                const regex = /"(?:\\.|[^\\"])*"|\S+/g;
                var matches = search_value.match(regex);
                var hasOR = false;
                if(matches!=null){
//DEBUG console.log(matches);
                    //const regex = /"[^"]+"|(&&|\ OR \b)/gi;
                    for(var i=0;i<matches.length;i++){
                        if(matches[i].toUpperCase() === 'OR'){
                            /*if(criteria.length>0){
                                hasOR = true;
                                criteria_or.any.push(criteria.length==1?criteria[0]:criteria);
                                criteria = [];
                            }*/
                        }else{
                            //strip quotes
                            var val = matches[i].replace(/(^")|("$)/g, '');
                            if(val){
                                
                                //search for given and family name
                                //var pred = {any:[{"f:1":val},{"f:18":val}]};
                                
                                //search  for rec title and OCR
                                //var pred = {any:[{"title":val},{"f:134":val}]};

                                //search  for rec any field and tite of linked records
                                var pred = {"title":val};
                                
                                criteria.push(val); //was pred
                            }
                        }
                    }//for
                    /*
                    if(hasOR && criteria.length>0){
                        criteria_or.any.push(criteria.length==1?criteria[0]:criteria);
                    }
                    search_value = (hasOR)?criteria_or:criteria;
                    */
//DEBUG console.log(search_value);  
                }
                
                
                //perform search here and set 'add_filter' as array of persons IDs
                //search person by any field (name,family,description)
                //search place and institution by title
                //
                //search persons linked to school, tertiary that are linked to inst
                var request = {
                    db:window.hWin.HAPI4.database,
                    search: criteria.length>0 ?criteria:search_value, //search_value_original,
                    uni_ID: this.options.uni_ID};
                    
                $('#main_pane').find('.clearfix').hide();     
                $('.bor-page-loading').show();
                    
            
                window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL 
                        + 'hclient/widgets/expertnation/searchPersons.php', 
                        request, null, 
                            function(response){
                                
                                $('.bor-page-loading').hide();
                                $('#main_pane').find('.bor-page-search').show();
                                
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    
                                    var ids = 'ids:'+response.data.records.join(',')
                                    __applyAddFilter(ids, search_value_original);
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response, true);    
                                }
                            }
                        );                
               
           }else{
               //search is not a string - see search by gender in expernation_nav
               __applyAddFilter(search_value, search_value_original);
           }
            
           //this.add_filter = search_value;
           //this.add_filter_original = search_value_original;
       } else {
           __applyAddFilter(null, null);
       }

       var that = this;       
       
       function __applyAddFilter(search_value, search_value_original){
           
           that.add_filter = search_value;
           that.add_filter_original = search_value_original;
           if(that.search_faceted.html()==''){ //not inited yet

           }else{
               //add filter to existing faceted search
               if(search_value_original!=null){
                   that.search_faceted.search_faceted('option', 'add_filter_original', search_value_original); 
                   that.add_filter_original = search_value_original;
               }
               that.search_faceted.search_faceted('option', 'add_filter', that.add_filter);
               that.search_faceted.search_faceted('doReset');
           }
       }
       
    },

    //
    //
    //
    getFacetedSearch: function(){
        if(this.search_faceted.html()==''){
            return null;
        }else{
            return this.search_faceted;    
        }
        
    },
    
     //
     // START SEARCH
     //
    doSearch2: function(svsID){

        if(!this.usr_SavedSearch[svsID]) return;
        
        this.options.search_at_init = false; //only once!!!!
        
        var qsearch = this.usr_SavedSearch[svsID][_QUERY];

        //switch to result List Tab
        //window.hWin.HAPI4.LayoutMgr.putAppOnTop('resultList');

        if(this.usr_SavedSearch[svsID][3]){  //_ISFACETED
            var facet_params = null;
            try {
                facet_params = $.parseJSON(qsearch);
            }
            catch (err) {
                facet_params = null;
            }
            if(!facet_params || !Hul.isArray(facet_params.facets) || facet_params['version']!=2){
                // Do something about the exception here
                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise this search due to corrupted parameters. Please contact Heurist support'), null, "Error");
                return;
            }

            var that = this;

            this.search_pane.show();
            this.search_list.hide();
            
            if(that.add_filter!=null){
                facet_params['add_filter'] = that.add_filter;
                facet_params['add_filter_original'] = that.add_filter_original;
            }            

            var noptions= { query_name: this.usr_SavedSearch[svsID][_NAME], 
                params:facet_params,
                showclosebutton:this._isDigitalHarlem,
                onclose:function(event){
                    that.search_pane.hide();
                    that.search_list.show();
            }};

            if(this.search_faceted.html()==''){ //not created yet
                this.search_faceted.search_faceted( noptions );
            }else{
                this.search_faceted.search_faceted('option', noptions ); //assign new parameters
            }

            this._currenttype = facet_params.rectypes[0];

        } else {
            var request = window.hWin.HEURIST4.query.parseHeuristQuery(qsearch);


            request.detail = 'detail';
            request.source = this.element.attr('id');
            request.qname = this.usr_SavedSearch[svsID][_NAME];

            //get hapi and perform search
            window.hWin.HAPI4.RecordSearch.doSearch( this, request );


        }

    },

    //
    // this is public method, it is called on search complete - see dh_search_minimal._doSearch
    //
    updateResultSet: function( recordset, request ){

        //show result  - number of records and add to map button
        var count_total = (recordset!=null)?recordset.length():0; //count_total

        if(count_total>0){
            this.res_lbl.html('Found '+count_total+' matches....'); //<br>Provide a layer name<br>
            if(this.res_btn_add) this.res_btn_add.show();

            this._currentRequest = request;
            this._currentRecordset = recordset;
        }else{
            this.res_lbl.html('Found no matches....');
            if(this.res_btn_add) this.res_btn_add.hide();

            this._currentRequest = null;
        }

        //update result set
        var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('resultList');
        if(app && app.widget){
            $(app.widget).resultList('updateResultSet', recordset);
        }

    },


    //
    // add current result set as layer to current map document
    //
    _onAddLayer: function(){

        var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');
        if(app && app.widget){
            var that = this;
            //switch to Map Tab
            window.hWin.HAPI4.LayoutMgr.putAppOnTop('app_timemap');
            $(app.widget).app_timemap('editLayerProperties', 'main', null, function(res){
                if(res){
                    that.res_div.hide();
                }
            });
        }

    }


});


