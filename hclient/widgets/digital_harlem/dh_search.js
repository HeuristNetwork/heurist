/**
*
* dh_search.js (Digital Harlem) : Special navigation panel: saved, faceted and tag searches
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

//constants
//const _NAME = 0, _QUERY = 1, _GRPID = 2, _ISFACETED=3;

$.widget( "heurist.dh_search", {

    // default options
    options: {
        UGrpID: 1007 // 1915-1930 user group for 'original' Digital Harlem map. 1008 is Year of the Riot (1935) only
    },

    _currenttype:null,  //obsolete
    usr_SavedSearch: null,   //list of saved searches for given group

    _currentRequest:null,
    _currentRecordset:null,



    // the constructor
    _create: function() {

        // important - it overwrite default search manager - we use special one
        // it adds some parameters to search request and postprocess the result
        window.hWin.HAPI4.SearchMgr = new hSearchMinimalDigitalHarlem();


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
        this.res_btn_add = $('<button>', {text:window.hWin.HR('Keep On Map')})
        .button({icons:{primary:'ui-icon-circle-plus'}})
        .on("click", function(event){ that._onAddLayer(); } )
        .appendTo(this.res_div);

        this.res_div_progress = $('<div>')
        .css({'height':'60px',
            'background':'url('+window.hWin.HAPI4.basePathV4+'hclient/assets/loading-animation-white.gif) no-repeat center center' })
        .appendTo(this.search_pane).hide();


        //this.res_div_input =  $('<div>', {id:'res_div_input'}).appendTo(this.res_div).hide();
        //this.res_name = $('<input>').appendTo(this.res_div_input);

        this.search_faceted = $( "<div>", {id:this.element.attr('id')+'_search_faceted'} )
        //.css({'height':'100%'})
        .css({'position':'absolute',top:'3em',bottom:'0','width':'100%'})
        .appendTo( this.search_pane )

        this._refresh();

        //find Map Documents (19) for featured individuals ---------------------------------------
        
        var query;
        // "Featured Individuals"
        if(window.hWin.HAPI4.sysinfo['layout']=='DigitalHarlem1935'){
            query = {"t":"19","f:144":"4749,4819"};
        }else{
            query = {"t":"19","f:144":"4801,4819"};
        }
        
        var request = { q: query,
            w: 'a',
            detail: 'header',
            source:this.element.attr('id') };
        //perform search
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
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
                .zIndex(9999)
                .css({'position':'absolute', 'width':that.btn_fi_menu.width() })
                .appendTo( that.document.find('body') )
                .menu({
                    select: function( event, ui ) {
                        var map_rec_id =  Number(ui.item.attr('id').substr(5));

                        var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');  //window.hWin.HAPI4.LayoutMgr.appGetWidgetById('ha51');
                        if(app && app.widget){
                            //switch to Map Tab
                            window.hWin.HAPI4.LayoutMgr.putAppOnTop('app_timemap');

                            //load Map Document
                            $(app.widget).app_timemap('loadMapDocumentById', map_rec_id);
                        }


                }})
                .hide();

                that._on( that.btn_fi_menu, {
                    click: function() {
                        $('.ui-menu').not('.horizontalmenu').hide(); //hide other
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

        
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH+' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART,
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
                }
        });


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
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        that.usr_SavedSearch = response.data;
                        that._refresh();
                    }
            });
        }else{
            this._updateSavedSearchList();
        }

    },



    //
    // redraw list of saved searches
    //
    _updateSavedSearchList: function(){

        this.search_list.empty();

        var that = this, facet_params = null, isfaceted = false;


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

            if(true || isfaceted){

                $('<button>', {text: this.usr_SavedSearch[svsID][_NAME], 'data-svs-id':svsID })
                .css({'width':'100%','margin-top':'0.4em'})
                .button().on("click", function(event){
                    that._doSearch2( $(this).attr('data-svs-id') );
                })
                .appendTo(this.search_list);
            }
        }

        //add featured maps


    },


     //
     // START SEARH
     //
    _doSearch2: function(svsID){

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

            var noptions= { query_name: this.usr_SavedSearch[svsID][_NAME], params:facet_params,
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
            var request = Hul.parseHeuristQuery(qsearch);


            request.detail = 'detail';
            request.source = this.element.attr('id');
            request.qname = this.usr_SavedSearch[svsID][_NAME];

            //get hapi and perform search
            window.hWin.HAPI4.SearchMgr.doSearch( this, request );


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
            this.res_btn_add.show();

            this._currentRequest = request;
            this._currentRecordset = recordset;
        }else{
            this.res_lbl.html('Found no matches....');
            this.res_btn_add.hide();

            this._currentRequest = null;
        }

        //update result set
        var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('resultList');
        if(app && app.widget){
            $(app.widget).resultList('updateResultSet', recordset);
        }

    },


    //
    // add current reuslt set as layer to current map document
    //
    _onAddLayer: function(){

        var app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');
        if(app && app.widget){
            var that = this;
            //switch to Map Tab
            window.hWin.HAPI4.LayoutMgr.putAppOnTop('app_timemap');
            $(app.widget).app_timemap('editLayerProperties', 'main', function(res){
                if(res){
                    that.res_div.hide();
                }
            });
        }

    }


});


