/**
* Accordeon/treeview in navigation panel: saved, faceted and tag searches
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
//const _NAME = 0, _QUERY = 1, _GRPID = 2, _ISFACETED=3;

$.widget( "heurist.dh_search", {

    // default options
    options: {
        searchdetails: "map", //level of search results  map - with details, structure - with detail and structure
        UGrpID: 1007
    },

    _currenttype:null,  //absolete
    currentSearch: null,
    usr_SavedSearch: null,   //list of saved searches for given group

    
    // the constructor
    _create: function() {

        var that = this;

        this.search_list = $( "<div>" ).css({'height':'100%', 'padding':'40px'}).appendTo( this.element );
        
        this.search_pane  = $( "<div>" ).css({'height':'100%' }).appendTo( this.element ).hide();
        
        this.res_div = $('<div>').css({'margin-botton':'1em', 'font-size':'0.9em','padding':'10px','text-align':'center'}).appendTo(this.search_pane).hide();
        this.res_lbl = $('<label>').css('padding','0.4em').appendTo(this.res_div);
        //this.res_name = $('<input>').appendTo(this.res_div);
        this.res_btn = $('<button>', {text:top.HR('Add To Map')})
            .button({icons:{primary:'ui-icon-circle-plus'}})
            .on("click", function(event){ that._onAddLayer(); } )
            .appendTo(this.res_div);
    
        //this.res_div_input =  $('<div>', {id:'res_div_input'}).appendTo(this.res_div).hide();
        //this.res_name = $('<input>').appendTo(this.res_div_input);
        
        this.search_faceted = $( "<div>", {id:this.element.attr('id')+'_search_faceted'} )
            //.css({'height':'100%'})
            .css({'position':'absolute',top:'3em',bottom:'0','width':'100%'})
            .appendTo( this.search_pane )
        
        this._events = top.HAPI4.Event.ON_REC_SEARCHSTART+ ' ' +  top.HAPI4.Event.ON_REC_SEARCH_FINISH;
        
        $(this.document).on(this._events, function(e, data) {
            if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART)
            {
                if(data && !data.increment){
                    that.res_div.hide();
                    that.currentSearch = Hul.cloneJSON(data);  
                } 
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCH_FINISH){
                
                //show result  - number of records and add to map button
                var count_total = (top.HAPI4.currentRecordset!=null)?top.HAPI4.currentRecordset.length():0; //count_total
                
                                        that.res_div.show();
                                            
                                        if(count_total>0){
                                            that.res_lbl.html('Found '+count_total+' matches....'); //<br>Provide a layer name<br>
                                            //that.res_name.val('');
                                            //that.res_name.show();
                                            that.res_btn.show();
                                        }else{
                                            that.res_lbl.html('Found no matches....');
                                            //that.res_name.hide();
                                            that.res_btn.hide();
                                        }
                
                
            }
        });

        this._refresh();
    }, //end _create

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        
        $(this.document).off(this._events);
        this.search_list.remove(); 
        this.search_faceted.remove(); 
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
    // redraw accordeon - list of workgroups + rules, all, bookmarked
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

    },

    _doSearch2: function(svsID){
        
        var qsearch = this.usr_SavedSearch[svsID][_QUERY];

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
    
    
            request.f = this.options.searchdetails;
            request.source = this.element.attr('id');
            request.qname = this.usr_SavedSearch[svsID][_NAME];
            request.notes = null; //unset to reduce traffic

            //get hapi and perform search
            top.HAPI4.RecordMgr.search(request, $(this.document));
            
            
        }

    },

    // add kayer to current map document
    _onAddLayer: function(){
        
        if($("#dh_layer_name").length>0){
            $("#dh_layer_name").val('');
        }

            
           var that = this;
            
           var $dlg = top.HEURIST4.util.showMsgDlg('<label>What would you like to call<br>the new map layer:</label><br><br><input id="dh_layer_name" type="text">', 
                   [
                    {text:'Add Layer', click:function(){
                        var layer_name = $dlg.find("#dh_layer_name").val();
                        if(Hul.isempty(layer_name)){
                            //top.HEURIST4.util.showMsgErr
                        }else{
                            
                            var app = appGetWidgetByName('app_timemap');  //appGetWidgetById('ha51'); 
                            if(app && app.widget){
                                //add new layer witg given name
                                var params = {id:"dhs"+Math.floor((Math.random() * 10000) + 1), title:layer_name}; //, query: {q:that.currentSearch.q, rules:that.currentSearch.rules} };
                            
                                params.recordset = top.HAPI4.currentRecordset;
                                
                                $(app.widget).app_timemap('addRecordsetLayer', params);
                                //remove current search layer
                                //@todo
                            }
                                        
                            
                            $dlg.dialog( "close" );
                        }
                        
                    }},
                    {text:'Cancel', click:function(){ $dlg.dialog( "close" ); }}
                   ]
           , 'New map layer');
            
           //top.HEURIST4.util.showMsgErr('Define name of layer');
        

           
            /*
           function(){
                var params = {id:"dhs"+Math.floor((Math.random() * 10000) + 1), title:layer_name, query: {q:this.currentSearch.q, rules:this.currentSearch.rules} };
                
                var app = appGetWidgetByName('app_timemap');  //appGetWidgetById('ha51'); 
                if(app && app.widget){
                    $(app.widget).app_timemap('addQueryLayer', params);
                }
           }
           */
        
        
        /*
        var rules = ''; 
        
        if(this._currenttype==10){
            //find person->events->addresses and person->addresses
            rules = [{"query":"t:14 relatedfrom:10","levels":[{"query":"t:12 relatedfrom:14"}]},{"query":"t:12 relatedfrom:10"}];
        }else if(this._currenttype==14){ //events->addresses
            rules = [{"query":"t:12 relatedfrom:14"}];
        }else if(this._currenttype==12){ //address
        
        }else{
            return;
        }
        
        var layer_name = $("#dh_layer_name").val();
        
        var params = {id:"dhs"+Math.floor((Math.random() * 10000) + 1), title:layer_name, query: {q:this.currentSearch, rules:rules} };
        */

      
    }
    

});


