/**
* Accordeon/treeview in navigation panel: saved, faceted and tag searches
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

//constants 
//const _NAME = 0, _QUERY = 1, _GRPID = 2, _ISFACETED=3;

$.widget( "heurist.dh_search", {

    // default options
    options: {
        UGrpID: 1007
    },

    _currenttype:null,  //absolete
    usr_SavedSearch: null,   //list of saved searches for given group
    
    _currentRequest:null,
    _currentRecordset:null,

    
    // the constructor
    _create: function() {
        
        // important - it overwrite default search manager - we use special one 
        // it adds some parameters to search request and postprocess the result
        top.HAPI4.SearchMgr = new hSearchMinimalDigitalHarlem();
        

        var that = this;

        this.search_list = $( "<div>" ).css({'height':'100%', 'padding':'40px'}).appendTo( this.element );
        
        this.search_pane  = $( "<div>" ).css({'height':'100%' }).appendTo( this.element ).hide();
        
        this.res_div = $('<div>').css({'margin-botton':'1em', 'font-size':'0.9em','padding':'10px','text-align':'center'}).appendTo(this.search_pane).hide();
        this.res_lbl = $('<label>').css('padding','0.4em').appendTo(this.res_div);
        //this.res_name = $('<input>').appendTo(this.res_div);
        this.res_btn_add = $('<button>', {text:top.HR('Add To Map')})
            .button({icons:{primary:'ui-icon-circle-plus'}})
            .on("click", function(event){ that._onAddLayer(); } )
            .appendTo(this.res_div);
    
        //this.res_div_input =  $('<div>', {id:'res_div_input'}).appendTo(this.res_div).hide();
        //this.res_name = $('<input>').appendTo(this.res_div_input);
        
        this.search_faceted = $( "<div>", {id:this.element.attr('id')+'_search_faceted'} )
            //.css({'height':'100%'})
            .css({'position':'absolute',top:'3em',bottom:'0','width':'100%'})
            .appendTo( this.search_pane )
        
        this._refresh();
        
        
            // "Featured Individuals" 
            var request = { q: {"t":"19","f:144":"532"},    //t:19 f:144:532
                            w: 'a',
                            detail: 'header',
                            source:this.element.attr('id') };
            //perform search
            top.HAPI4.RecordMgr.search(request, function(response){
                        if(response.status == top.HAPI4.ResponseStatus.OK){
                            var resdata = new hRecordSet(response.data);
                            //add SELECT and fill it with values
                            var smenu = '', idx;
                            var records = resdata.getRecords();
                            for(idx in records){
                                if(idx)
                                {
                                    var record = records[idx];
                                    var recID       = resdata.fld(record, 'rec_ID');
                                        recName     = resdata.fld(record, 'rec_Title');
                                    smenu = smenu + '<li id="fimap'+recID+'"><a href="#">'+recName+'</a></li>';
                                }
                            }
                            if(smenu=='') return;
                            
                            that.btn_fi_menu = $( "<button>", {
                                text: top.HR('select')+'...'
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
                                        
                                        var app = top.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');  //top.HAPI4.LayoutMgr.appGetWidgetById('ha51'); 
                                        if(app && app.widget){
                                            //switch to Map Tab
                                            top.HAPI4.LayoutMgr.putAppOnTop('app_timemap');
            
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
                            top.HEURIST4.util.showMsgErr(response);
                        }
                        });
    

    }, //end _create

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        
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

            top.HAPI4.SystemMgr.ssearch_get( {UGrpID: this.options.UGrpID},
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){
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

    _doSearch2: function(svsID){
        
        var qsearch = this.usr_SavedSearch[svsID][_QUERY];

        //switch to Map Tab
        top.HAPI4.LayoutMgr.putAppOnTop('resultList');
        
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
                    Hul.showMsgDlg(top.HR('Can not init this search. Corrupted parameters. Please contact support team'), null, "Error");
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
            top.HAPI4.SearchMgr.doSearch( this, request );
            
            
        }

    },
    
    //
    // this is public methid, it is called on search complete
    //
    updateResultSet: function( recordset, request ){
                
            //show result  - number of records and add to map button
            var count_total = (recordset!=null)?recordset.length():0; //count_total
                
            this.res_div.show();
                
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
            var app = top.HAPI4.LayoutMgr.appGetWidgetByName('resultList');  //top.HAPI4.LayoutMgr.appGetWidgetById('ha51'); 
            if(app && app.widget){
                $(app.widget).resultList('updateResultSet', recordset);
            }
                     
    },
    
    dlgcont:null,
    
    // add layer to current map document
    _onAddLayer: function(){
        
        if($("#dh_layer_name").length>0){
            $("#dh_layer_name").val('');
        }
        
        if(this._currentRequest==null) return;
        //if result > 1000 warning message and exit
        if( this._currentRequest.total_count>2000){
            top.HEURIST4.util.showMsgErr(this._currentRequest.total_count
            + ' records have been retrieved. This is more than can be comfortably plotted on a Google map. '
            + 'Please narrow down your selection in order to create a useful map');
            return;
        }
            
           var that = this;
           
           if(this.dlgcont==null){
               
               this.dlgcont = $('<label>What would you like to call<br>the new map layer:</label>'
               + '<br><br><input id="dh_layer_name" type="text">'
               + '<br><br><label>Color:</label>'
               + '<br><br><input id="dh_layer_color" value="#FF0000" style="background-color:#f00">');           
               
               (this.dlgcont.find('#dh_layer_color')).colorPicker({
                   color:'#f00',
                   opacity:false,
                   valueRanges: {rgb: {r: [0, 255], g: [0, 255], b: [0, 255]} },
                   cssAddon:'.cp-color-picker{z-index:999999999999 !important;background-color:#fff;border-radius: 0px;}'
               });
           }
            
           var $dlg = top.HEURIST4.util.showMsgDlg(this.dlgcont, 
                   [
                    {text:'Add Layer', click:function(){
                        var layer_name = $dlg.find("#dh_layer_name").val();
                        if(Hul.isempty(layer_name)){
                            //top.HEURIST4.util.showMsgErr
                        }else{
                            
                            var app = top.HAPI4.LayoutMgr.appGetWidgetByName('app_timemap');
                            if(app && app.widget){

                                
                                // 2. search rules (applyRules) with relationship records
                                // 3. search details for timemap (9,10,11,28) event type (74) and relationships (5,6,7)
                                var request = { q: 'ids:'+that._currentRecordset.getMainSet().join(','),
                                        rules: that._currentRequest.rules,
                                        w: 'all'};
                
                                //add new layer with given name
                                var params = {id:"dhs"+Math.floor((Math.random() * 10000) + 1),
                                     title: layer_name,
                                     query: request,
                                     color: $dlg.find("#dh_layer_color").css('background-color') 
                                };                 
                                
                                $(app.widget).app_timemap('addQueryLayer', params);
                                //remove current search layer
                                //@todo
                            }
                                        
                            
                            $('.cp-color-picker').hide();
                            $dlg.dialog( "close" );
                            that.res_div.hide();
                        }
                        
                    }},
                    {text:'Cancel', click:function(){ 
                        $('.cp-color-picker').hide();
                        $dlg.dialog( "close" ); }}
                   ]
           , 'New map layer');
            
           //top.HEURIST4.util.showMsgErr('Define name of layer');
        
    }
    

});


