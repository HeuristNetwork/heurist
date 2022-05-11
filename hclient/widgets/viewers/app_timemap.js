/**
* app_timemap.js - load map + timeline into an iframe in the interface.
* This widget acts as a wrapper for viewers/gmap/map.php or viewers/map/map_leaflet.php
* 
* app_timemap -> map_leaflet.php -> mapping.js
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


$.widget( "heurist.app_timemap", {

    // default options
    options: {
        recordset: null,
        selection: null, //list of record ids
        
        layout:null, // ['header','map','timeline'] - old parameters @todo change in layout_default to layout_params
        
        eventbased:true,
        tabpanel:false,  //if true located on tabcontrol need top:30
        
        leaflet: false,
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        search_initial: null,  //query string or svs_ID for initial search

        init_at_once: false,  //load basemap at once (useful for publish to avoid empty space) 
        
        layout_params:null, //params to be passed to mapping.js
        mapdocument:null,   // map document loaded on map init
        
        //this value reset to false on every search_finish, need to set it to true explicitely before each search
        preserveViewport: false,   //zoom to current search
        //only the first request call the server - all others requests will show/hide items only
        use_cache: false,
        
        onMapInit: null,   //event triggered when map is fully loaded/inited
        
        custom_links: null,  //links to custom css and scripts to be injected into mao iframe
        current_search_filter: null  //additional filter for current search result
    },

    _events: null,

    recordset_changed: true,
    
    //whether mapping widget is inited (frame is loaded)
    map_inited: false,
    
    //it is  set to true for addSearchResult to avoid multiple mapqueries @todo REVISE
    map_curr_search_inited: false,  
    map_cache_got: false, 

    map_resize_timer: 0,
    
    // the constructor
    _create: function() {

        var that = this;

        //???? this.element.hide();

        this.framecontent = $('<div>').addClass('frame_container')
        //.css({position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
        //     'background':'url('+window.hWin.HAPI4.baseURL+'assets/loading-animation-white.gif) no-repeat center center'})
        .appendTo( this.element );

        if(this.options.tabpanel){
            this.framecontent.css('top', 30);
        }else if ($(".header"+that.element.attr('id')).length===0){
            this.framecontent.css('top', 0);
        }


        this.mapframe = $( "<iframe>" )
        .attr('id', 'map-frame')
        .css('padding','0px')
        .appendTo( this.framecontent );
          
        this.loadanimation(true);
          
        if(this.options.eventbased){

            this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS
            + ' ' + window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
            + ' ' + window.hWin.HAPI4.Event.ON_SYSTEM_INITED
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART;

            $(this.document).on(this._events, function(e, data) {

                if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
                {
                    if(that.options.recordset != null && !window.hWin.HAPI4.has_access()){ //logout
                        that.recordset_changed = true;
                        that.option("recordset", null);
                        that._refresh();
                    }

                }else if(e.type == window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE){
                    
                    if(that.options.leaflet && that.mapframe[0].contentWindow){
                        var mapping = that.mapframe[0].contentWindow.mapping;
                        if(mapping) {
                            if(that.map_resize_timer>0) clearTimeout(that.map_resize_timer);
                            that.map_resize_timer = setTimeout(function(){
                                that.map_resize_timer = 0;
                                mapping.mapping('invalidateSize');         
                            },400);
                            
                        }
                    }
            
                    
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){
                    //accept events from the same realm only
                    if(!((data && data.search_realm=='mapping_recordset') || 
                        that._isSameRealm(data))) return;
                 
/* DEBUG HIE
if( data.search_realm=='mapping_recordset'){
//console.log('listner '+data.search_realm);                    
if(data.recordset=='show_all'){
console.log('show all');        
}else{
var re = $.isArray(data.recordset)?data.recordset:data.recordset.getIds();
console.log('got '+re.length);    
console.log(re);    
}
}                        
*/        
                        
                 
                    that.recordset_changed = true;
                    that.map_curr_search_inited = false;
                    
                    if(that.options.current_search_filter){
                        //data.recordset
                        var sub_query = window.hWin.HEURIST4.util.mergeHeuristQuery(
                                    data.recordset.getIds(2000), that.options.current_search_filter);
                                    
                        var sub_request = {q: sub_query, w: 'all', detail:'ids', id:window.hWin.HEURIST4.util.random()};
                        that.option("recordset", sub_request); 
                    }else{
                        that.option("recordset", data.recordset); //hRecordSet
                    }
                        
                    that._refresh();
                    that.loadanimation(false);
                        
                    // Search start
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                    //accept events from the same realm only
                    if(!that._isSameRealm(data)) return;
                    
                    that.option("recordset", null);
                    that.option("selection", null);
                    if(data && !data.reset && data.q!='')  {
                        that.loadanimation(true);
                    }else{
                        that.recordset_changed = true;
                        that._refresh();
                    }
                    //???? that._refresh();

                    // Record selection
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                    //accept events from the same realm only
                    if(that._isSameRealm(data) && data.source!=that.element.attr('id')) { //selection happened somewhere else
                        if(data.reset){
                            //clear selection
                            that.option("selection",  null);
                            
                        }else if(data.map_layer_action == 'trigger_visibility'){
                            
                            var sel =  window.hWin.HAPI4.getSelection(data.selection, true);
                        
                            // data.selection is dataset id - show/hide visibility of dataset
                            // new_visiblity - true, false to show/hide entire layer or array of ids to filter out on map
                            that._setLayersVisibility( sel, data.mapdoc_id, data.new_visiblity );
                            
                        }else if(data.map_layer_action == 'download'){
                            //download layer data
                            var sel =  window.hWin.HAPI4.getSelection(data.selection, true);
                            that._downloadLayerData( sel );
                            
                        }else if(data.map_layer_action == 'zoom'){

                            var sel =  window.hWin.HAPI4.getSelection(data.selection, true);
                            that._zoomToLayer(sel);
                            
                        }else{
                            //highlight and zoom
                            that._doVisualizeSelection( window.hWin.HAPI4.getSelection(data.selection, true) );
                        }
                    }
                    
                }else if (e.type == window.hWin.HAPI4.Event.ON_SYSTEM_INITED){
                    that._refresh();

                }

            
            });
        }
        // (this.mapframe).load(that._initmap);
        // init map on frame load
        this._on( this.mapframe, {
                load: function(){
                    that.loadanimation(false);
                    this.recordset_changed = true;
                    this._refresh();
                }
            }
        );

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
        
        if(this.options.init_at_once){
            this._refresh();  
        }

    }, //end _create

    
    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },
    
    /* private function */
    _refresh: function(){

        if ( this.element.is(':visible') && this.recordset_changed) {  //to avoid reload if recordset is not changed

            if( this.mapframe.attr('src') ){  //frame already loaded
                this._initmap();
            }else {
                //need to load map.php into frame

                this.loadanimation(true);
                
                //adding url parameters to map_leaflet.php from widget options
              
                var mapdoc = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', window.hWin.location.search);
                if(mapdoc>0){
                    this.options.mapdocument = mapdoc;    
                }
                
                var url;
                if(this.options.leaflet){
                    url = window.hWin.HAPI4.baseURL + 'viewers/map/map_leaflet.php?';
                }else{
                    url = window.hWin.HAPI4.baseURL + 'viewers/gmap/map.php?';
                }
                url = url + 'db=' + window.hWin.HAPI4.database;

                if(this.options.layout_params){
            
                    for(var key in this.options.layout_params){
                        if(key=='style' && window.hWin.HEURIST4.util.isJSON(this.options.layout_params[key])){
                            url = url + '&'+key + '=' +  encodeURIComponent(JSON.stringify( this.options.layout_params[key] ));
                        }else{
                            url = url + '&'+key + '=' + this.options.layout_params[key];    
                        }
                    }

                }else{
                    //init from default_layout
                    if(this.options.layout){
                        //old version
                        if( this.options.layout.indexOf('timeline')<0 )
                            url = url + '&notimeline=1';
                                                    
                        if( this.options.layout.indexOf('header')<0 )
                            url = url + '&noheader=1';
                    }
                    url = url + '&noinit=1'; // map will be inited here (for google only)
                    
                    this.options.published = 0;
                }
                
                //besides layout_params (controls and panels visibility) it passes
                // mapdocument - id of startup mapdocument
                // search_initial - initial query
                // published  - 0|1
                
                if(!window.hWin.HEURIST4.util.isempty(this.options.published)){
                    url = url + '&published='+this.options.published; 
                }
                if(this.options.mapdocument>0){
                    url = url + '&mapdocument='+this.options.mapdocument; 
                }
                if(this.options.search_initial){
                    url = url + '&q='+encodeURIComponent(this.options.search_initial); 
                }
                
                (this.mapframe).attr('src', url);
                
            }
        }

    },
    
    _reload_frame: function(){
        if(this.element.is(':visible')){
            
            window.hWin.HEURIST4.msg.showMsgFlash('Reloading map to apply new settings', 2000);
            
            this.recordset_changed = true;
            this.mapframe.attr('src', null);
        }
    },
    
    //
    // called as soon as map.php is loaded into iframe and on _refresh (after search finished)
    //
    _initmap: function( cnt_call ){

        if( !window.hWin.HEURIST4.util.isnull(this.mapframe) && this.mapframe.length > 0 ){

            //access mapping object in mapframe to referesh content 
            var mapping = null;
            if(this.mapframe[0].contentWindow){
                mapping = this.mapframe[0].contentWindow.mapping;
            }

            var that = this;

            if(!mapping){
                this.map_inited = false; 
                cnt_call = (cnt_call>0) ?cnt_call+1 :1;
                setTimeout(function(){ that._initmap(cnt_call); }, 1000); //bad idea
                return;
            }
            

            if(this.map_inited && cnt_call>0) return;
            
            if(this.options.leaflet){ //LEAFLET
            
                if(!that.map_curr_search_inited && that.options.recordset){

                    that.map_curr_search_inited = true;
                    
                    if(that.map_cache_got && that.options.use_cache){
                        //do not reload current search since first request load full dataset - just hide items that are not in current search
                        var _selection = null;
                        if(that.options.recordset=='show_all' || that.options.recordset=='hide_all'){
                            _selection = that.options.recordset;
                            that.options.recordset = null;
                        }else {
                            _selection = $.isArray(that.options.recordset)
                                    ?that.options.recordset :that.options.recordset.getIds();
                            if(_selection.length==0) _selection = 'hide_all';
                        }
                        mapping.mapping('setVisibilityAndZoom', {mapdoc_id:0, dataset_name:'Current query'}, _selection, true);                            

                        
                    }else{
                        
                        //add layer to virtual mapdocument
                        mapping.mapping('addSearchResult', that.options.recordset, 'Current query', that.options.preserveViewport);
                        that.options.preserveViewport = false; //restore it before each call if require
                        that.map_cache_got = true; //@todo need to check that search is really performed
                    }
                //}else if(this.options.selection){
                }
                if(!this.map_inited){
                    //assign listener
               
                    mapping.mapping('option','onselect',function(selected ) {
                            $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT,
                                    { selection:selected, source:that.element.attr('id'), search_realm:that.options.search_realm } );
                        });
                        
                    mapping.mapping('option','onlayerstatus',function( layer_id, status ) {

                            if(layer_id>0)
                            $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT,
                                    { selection:[layer_id], map_layer_status:status,
                                      source:that.element.attr('id'), search_realm:that.options.search_realm } );

                    });
                
                    this.map_inited = true;
                    
                    if($.isFunction(this.options.onMapInit)){
                        this.options.onMapInit.call();
                    }
                    
                    //call special method to inject custom links (css and javascript) to iframe map document
                    if(that.options.custom_links){
                        //custom_links - urls to be injected as css and js
                        mapping.mapping('injectLinks', that.options.custom_links);
                    }
                    
                }
                
                
            
            }else{
                //google to remove
                this.map_inited = true;
                
                mapping.load( null, //mapdataset,
                    this.options.selection,  //array of record ids
                    this.options.mapdocument,    //map document on load
                    function(selected){  //callback if something selected on map
                        $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT,
                            { selection:selected, source:that.element.attr('id'), search_realm:that.options.search_realm } );
                    },
                    function(){ //callback function on native map init completion
                        var params = {id:'main', recordset:that.options.recordset, title:'Current query'};
                        that.addRecordsetLayer(params, -1);
                    }
                );
            
            }

            this.recordset_changed = false;
        }

    }
    
    , updateDataset: function(data, dataset_name){
        var mapping = null;
        if(this.mapframe[0].contentWindow){
            mapping = this.mapframe[0].contentWindow.mapping;
            if(mapping){
                mapping.mapping('addSearchResult', data, dataset_name);    
                
                return true;
            }else if(data['q']){
                //mapping not defined yet - perfrom initial search
                this.options.search_initial = data['q'];
                this.recordset_changed = true;
                this._refresh();
            }
            
        }
        return false;
    }

    //
    // highlight and zoom
    //
    , _doVisualizeSelection: function (selection) {

        if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

        this.option("selection", selection);

        if(!this.element.is(':visible')
            || window.hWin.HEURIST4.util.isnull(this.mapframe) || this.mapframe.length < 1){
            return;
        }
        
        if (this.mapframe[0].contentWindow.mapping) {
            var  mapping = this.mapframe[0].contentWindow.mapping;  
            
            if(this.options.leaflet){ //leaflet

                mapping.mapping('setFeatureSelection', this.options.selection, true);
                
            }else{
                mapping.showSelection(this.options.selection);  //see viewers/gmap/map.js
            }
            
        }
    }
    
    //
    //  sends request for map data (json, kml or shp) and text file with links (to record view and hml) 
    //
    , _downloadLayerData: function (selection) {

        if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

        if(!this.element.is(':visible')
            || window.hWin.HEURIST4.util.isnull(this.mapframe) || this.mapframe.length < 1){
            return;
        }

        if (this.mapframe[0].contentWindow.mapping) {
            var  mapping = this.mapframe[0].contentWindow.mapping;  

            if(this.options.leaflet){ //leaflet
                //if layer is visible - select and zoom to record in search results
                var recID = selection[0];
                var layer_rec = mapping.mapping('getMapManager').getLayer( 0, recID );
                (layer_rec['layer']).getMapData();
                
            }
            
        }        
    }

    //
    // zoom to layer extent - selection - layer id 
    //
    , _zoomToLayer: function (selection) {
        
        if (this.mapframe[0].contentWindow.mapping && selection && selection.length>0) {
            var  mapping = this.mapframe[0].contentWindow.mapping;  

            if(this.options.leaflet){ //leaflet
                //if layer is visible - select and zoom to record in search results
                var recID = selection[0];
                var layer_rec = mapping.mapping('getMapManager').getLayer( 0, recID );
                (layer_rec['layer']).zoomToLayer();
                
            }
        }        
    }

    //
    // show (expand) layer/dataset or hide it on map
    //
    , _setLayersVisibility: function (selection, mapdoc_ID, new_visiblity) {

        //if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

        if(!this.element.is(':visible')
            || window.hWin.HEURIST4.util.isnull(this.mapframe) || this.mapframe.length < 1){
            return;
        }

        if (this.mapframe[0].contentWindow.mapping) {
            var  mapping = this.mapframe[0].contentWindow.mapping;  

            if(this.options.leaflet){ //leaflet
            
                if(!(mapdoc_ID>=0)) mapdoc_ID = 0;
                var mapManager = mapping.mapping( 'getMapManager' );
                mapManager.setLayersVisibility(mapdoc_ID, selection, new_visiblity);
                /*
                //if layer is visible - select and zoom to record in search results
                var recID = selection[0];
                var layer_rec = mapManager.getLayer( mapdoc_ID, recID );
                if(layer_rec && (layer_rec['layer']).isVisible()){
                    this._doVisualizeSelection( selection );
                }
                */ 

                //zoom to visible elements only
                this.zoomToSelection( new_visiblity );
            }
            
        }        
    }

    // events bound via _on are removed automatically
    // revert other modifications here
    , _destroy: function() {

        this.element.off("myOnShowEvent");
        if(this._events)  $(this.document).off(this._events);

        // remove generated elements
        this.mapframe.remove();
        this.framecontent.remove();

    }

    , loadanimation: function(show){
       
        if(show){
            this.mapframe.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
            //this.mapframe.css('cursor', 'progress');
        }else{
            //this.framecontent.css('cursor', 'auto');
            this.mapframe.css('background','none');
        }
    }

    /**
    * public method
    */

    , reloadMapFrame: function(){
        this._reload_frame();    
    }
    
    //google to remove
    , getMapDocumentDataById: function(mapdocument_id){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            return mapping.map_control.getMapDocumentDataById(mapdocument_id);
        }else{
            return null;
        }
    }
    
    //google to remove
    , loadMapDocumentById: function(recId){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            mapping.map_control.loadMapDocumentById(recId);  //see viewers/gmap/map.js
        }
    }

    /**
    * Add dataset on map
    * params = {id:$.uniqueId(), title:'Title for Legend', query: '{q:"", rules:""}'}
    */
    //google to remove
    , addQueryLayer: function(params){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            mapping.map_control.addQueryLayer(params);
        }
    }
    
    //google to remove
    , addRecordsetLayer: function(params){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            mapping.map_control.addRecordsetLayer(params);
        }
    }
    
    //google to remove
    , editLayerProperties: function( dataset_id, legendid, callback ){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            mapping.map_control.editLayerProperties(dataset_id, legendid, callback);
        }
    },
    
    //leaflet
    zoomToSelection:function(selection, fly_params){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping){
            mapping.mapping('zoomToSelection', selection, fly_params );
        }
    }

    //    
    //
    //
    , getMapping: function(){
        if(this.mapframe[0].contentWindow){
            var map = this.mapframe[0].contentWindow.mapping;
            return map.mapping('instance');
        }else{
            return null;
        }
    }
    
    , isMapInited: function(){
        return this.map_inited;
    }
    /*
    , getNativeMap: function(){
        if(this.mapframe[0].contentWindow){
            var map = this.mapframe[0].contentWindow.mapping;
            map = map.mapping('instance');
            return (map)?map.nativemap:null;
        }else{
            return null;
        }
    }*/

});
