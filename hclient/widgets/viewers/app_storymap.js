/**
* app_storymap.js - story map controller widget - it loads storyline and manage
* story viewer (recordList with smarty report output), map and timeline (app_timemap.js)
* 
* It may init timemap internally or use some existing map widget via search_realm
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

$.widget( "heurist.app_storymap", {

    // default options
    options: {
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        tabpanel: false,  //if true located on tabcontrol need top:30

        
        storyFields: [],   // field ids that will be considered as story elements (superseed storyRectypes)
        storyRectypes: [], // ids of rectypes that will be considered as story elements (1414-100 or 9-24 or 3-1006 or similar)

        
        // general options
        storyRecordID: null, // record with story elements (it has links to)
        reportOverview: null, // smarty report for overview. It uses storyRecordID
        reportElement: null,  // to draw items in resultList
        //story/result list parameters
        reportOverviewMode: 'inline', // tab | header, no
        reportElementMode: 'vertical', //vertical list, carousel/slide, map popup
        reportElementCss: null,

        // timemap parameters
        use_internal_timemap: false,
        mapDocumentID: null //map document to be loaded (3-1019)
        
        //by default story element loads linked or internal places, or linked map layers
        //if story element has start (1414-1092 or 2-134), transition (1414-1090) and end places (1414-1088 or 2-864) they are preferable
        
        //show/animation parameters per story element
        , mapLayerID: null  // record ids to load on map (3-1020)
        , mapKeep: false    // keep elements on map permanently otherwise it will be unload for next story element
        , markerStyleID: null // record id (2-99) to define style (unless it is defined in map layers)
        , markerStyle: null   // 
        , storyActions: null  // zoom_in, zoom_out, follow_path, ant_path, fade_out, bounce, highlight, show_report
        
    },

    _resultset: null, // current story list
    _resultList: null,
    _tabs: null, //tabs control
    _mapping: null,    // mapping widget
    _currentElementID: 0,
    _L: null, //refrence to leaflet

    // the constructor
    _create: function() {

        var that = this;
        
        var cssOverview = {};
        
        if(this.options.reportOverviewMode=='no' || this.options.reportOverviewMode=='inline'){
            cssOverview = {display:'none'};
        }else if(this.options.reportOverviewMode=='header'){
            cssOverview = {height: '200px'};
        }
        
        var layout = [{"name":"StoryMap","type":"group","css":{}, //"position":"relative","height":"100%"
            "children":
            [{"name":"TabControl","type": (this.options.reportOverviewMode=='tab'?"tabs":"group"),
                "css":{},"folder":true,"dom_id":"tabCtrl","children":
                [{"name":top.HR('Overview'),"type":"group","css":cssOverview,"folder":true,
                    "children":[{"name":"Overview content","type":"text","css":{},"content":"Overview content","dom_id":"pnlOverview"}]},
                 {"name":top.HR('Story'),"type":"group","css":{},"folder":true,
                        "children":[{"appid":"heurist_resultList","name":"Story list","css":{"position":"relative","minWidth":150,"minHeight":400},
                            "options":{
                                "select_mode": "none",
                                "support_collection":false,
                                "support_reorder":false,
                                "blog_result_list":false,
                                "recordview_onselect":"no",
                                "rendererExpandInFrame":false,
                                "recordDivClass":"outline_suppress",
                                "show_url_as_link":true,
                                "view_mode":"record_content",
                                "rendererExpandDetails": this.options.reportElement,
                                "empty_remark": top.HR('There is no story for selected record'),
                                "onScroll": function(event){ that._onScroll(event, that) },
                                "show_toolbar":false,
                                /*"show_inner_header":false,
                                "show_counter":false,
                                "show_viewmode":false,*/
                                "show_action_buttons":false,
                                "init_at_once":true,
                                "eventbased": false},
                            "dom_id":"storyList"}]
                    }
                ]
            }]
        }];
        
        if(!layoutMgr) hLayoutMgr();
        layoutMgr.layoutInit(layout, this.element);
        
        //add overview panel
        this.pnlOverview = this.element.find('#pnlOverview');
        
        //add story panel and result list
        this._resultList = this.element.find('#storyList');

/*console.log('>>>'+this.options.reportElement);
        if(this.options.reportElement){
            this._resultList.resultList('option', 'rendererExpandDetails', this.options.reportElement);    
        }
        this._resultList.resultList('option', 'empty_remark', top.HR('There is no story for selected record'));
        this._resultList.resultList('option', 'onScroll', function(){ 
            console.log('onScroll');
        });*/
        this._resultList.resultList('applyViewMode', 'record_content', true);
                
        
        this._tabs = this.element.find('.ui-tabs:first');
        if(this._tabs.length>0 && this._tabs.tabs('instance')){
            
            var h = this.element.find('.ui-tabs-nav:first').height(); //find('#tabCtrl').
            this.pnlOverview.height(this.element.height() - h);
            this._resultList.height(this.element.height() - h); //465
            
            this._tabs.tabs('option','activate',function(event, ui){
                if(that._resultset && that._resultset.length()>0 
                    && !(that._currentElementID>0) && that._tabs.tabs('option','active')==1){
                    that._startNewStoryElement( that._resultset.getOrder()[0] );
                }
            });
            
        }else if(this.options.reportOverviewMode=='header'){
            this.pnlOverview.height(cssOverview.height);
        }
        
        /*        
        if(this.options.tabpanel){
            this.framecontent.css('top', 30);
        }else if ($(".header"+that.element.attr('id')).length===0){
            this.framecontent.css('top', 0);
        }*/

        if(this.options.storyRecordID){
            this._checkForStory( this.options.storyRecordID, true );
        }else{
            
            this._events = window.hWin.HAPI4.Event.ON_REC_SELECT;
            
            $(this.document).on(this._events, function(e, data) {
                
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){
                    
                    if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {

                        if(data.selection && data.selection.length==1){
                            
                            var recID = data.selection[0];
                        
                            that._checkForStory(recID);
                        
                        }
                    }
                }
            });
        }
        
        
    }, //end _create

    //
    //
    //
    _destroy: function() {

        if(this._events){
            $(this.document).off(this._events);
        }
    },
    
    
    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },
    
    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        if(key == 'storyRecordID'){
            this._checkForStory(recID); 
        }else{
            this._superApply( arguments );    
        }
    },

    //
    // Change current story element 
    //     
    _onScroll: function(event, that) {
        var ele = $(event.target); //this.div_content;
        $.each(ele.find('.recordDiv'), function(i,item){
            var tt = $(item).position().top;
            var h = -($(item).height()-100);
            if(tt>h && tt<100){
                that._startNewStoryElement( $(item).attr('recid') );
                return false; //$(item).attr('recid');
            }
        });
    },
        
    //
    //
    //
    _checkForStory: function(recID, is_forced){
        
        if(this.options.storyRecordID != recID || is_forced){
            
            this.options.storyRecordID = recID;
        
            if(!$.isArray(this.options.storyFields) && typeof this.options.storyFields === 'string'){
                this.options.storyFields = this.options.storyFields.split(',');
            }
            if(!$.isArray(this.options.storyRectypes) && typeof this.options.storyRectypes === 'string'){
                this.options.storyRectypes = this.options.storyRectypes.split(',');
            }
            
            var request;
            var that = this;

            if(this.options.storyFields.length>0){
                //search for fields
                request = {q:{ids:recID}, detail:this.options.storyFields.join(',')};

                window.hWin.HAPI4.RecordMgr.search(request,
                    function(response) {
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            var details = response.data.records[recID]['d'];
                            var recIDs = [];
                            for(var dty_ID in details){
                                if(dty_ID>0){
                                    recIDs = recIDs.concat(details[dty_ID]);
                                }
                            }
                            
                            if(recIDs.length>0){
                            
                                recIDs = recIDs.join(',');
                                
                                var request = {q:[{ids:recIDs},{sort:('set:'+recIDs)}], detail:'header'};
                                
                                if(that.options.storyRectypes.length>0){
                                    request['q'].push({t:that.options.storyRectypes.join(',')});
                                }
                                
                                window.hWin.HAPI4.RecordMgr.search(request,
                                    function(response) {
                                        that._resultset = new hRecordSet(response.data);
                                        that._startNewStory(recID);        
                                    });
                            }else{
                                that._resultset = new hRecordSet();
                                that._startNewStory(recID);
                            }
                            
                        }else{
                            that._resultset = null;
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
            /*
            }else if (this.options.storyRectypes.length>0){
                //search for linked records
                request = {q:[{t:this.options.storyRectypes.join(',')},{lf:recID}], detail:'header'};

                window.hWin.HAPI4.RecordMgr.search(request,
                    function(response) {
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that._resultset = new hRecordSet(response.data);
                            that._startNewStory(recID);
                            
                        }else{
                            that._resultset = null;
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
                
            */    
            }else{
                //show warning on overview panel
                this.pnlOverview.html(top.HR('No story fields defined'));
            }
                
        }
    },
    
    //
    //
    //
    _startNewStory: function(recID){
        
        //find linked mapping
        if(this.options.map_widget_id){
            this._mapping = $('#'+this.options.map_widget_id)    
        }
        
        if(this._mapping && this._mapping.length>0){
            
            //adds links between start and end place
            //start (1414-1092 or 2-134), transition (1414-1090) and end places (1414-1088 or 2-864)                    
            /*
            if(this._resultset.length()>0){
                
                this._resultset.each(function(rec_id, record){
                    
                    var pntBeg, pntEnd;
                    //start, transition and end places
                    if(this.DT_PLACE_START>0 && this.DT_PLACE_END>0){
                        pntEnd = fld(record, this.DT_PLACE_END);   
                        if(pntEnd){
                            pntBeg = fld(record, this.DT_PLACE_START);
                            if(pntBeg){
                                //add new field - link path
console.log(pntBeg);
console.log(pntEnd);

//LINESTRING (37.639204 55.730027,37.636628 55.729814)
                            }
                        } 
                    }
                    if(this.DT_PLACE_START2>0 && this.DT_PLACE_END2>0){
                        pntEnd = fld(record, this.DT_PLACE_END2);   
                        if(pntEnd){
                            pntBeg = fld(record, this.DT_PLACE_START2);
                            if(pntBeg){
                                //add new field - link path
                                
                            }
                        } 
                    }
                    
                    
                });
            
            }*/
                
            var res = this._mapping.app_timemap('updateDataset', this._resultset, 'Story Map');
            if(!res) {
//console.log('NOT INTED');                 
                var that = this;
                this._mapping.app_timemap('option','onMapInit', function(){
                    var map = that._mapping.app_timemap('getMapping');
                    map.isMarkerClusterEnabled = false;
                    that._mapping.app_timemap('updateDataset', that._resultset, 'Story Map');
                });
            }else{
                var map = this._mapping.app_timemap('getMapping');
                map.isMarkerClusterEnabled = false;
            }
            
        }
        
        if(this._tabs){
            this._tabs.tabs('option', 'active', 0);   
        }
        
        
        this._resultList.resultList('updateResultSet', this._resultset);
        
        this._currentElementID = null;
                        
        if(this._resultset && this._resultset.length()>0){
            this.options.storyRecordID = recID;
            //1. Render overview panel
            var infoURL;
            var isSmarty = false;
            
            if( typeof this.options.reportOverview === 'string' 
                            && this.options.reportOverview.substr(-4)=='.tpl' ){
            
                infoURL = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?publish=1&debug=0&q=ids:'
                        + recID 
                        + '&db='+window.hWin.HAPI4.database+'&template='
                        + encodeURIComponent(this.options.reportOverview);
                isSmarty = true;
            }else{
                infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?mapPopup=1&recID='
                        +recID
                        +'&db='+window.hWin.HAPI4.database;
            }
            
            if(this.options.reportOverviewMode!='no'){
                var that = this;
                this.pnlOverview.addClass('loading').css({'overflow-y':'auto'})
                    .load(infoURL, function(){ 
                                var ele2 = $(this);
                                ele2.removeClass('loading').css('min-height','200px');//.height('auto');    
                                
                        if(that.options.reportOverviewMode=='inline'){
                            var ele = that._resultList.find('.div-result-list-content');

                            if(ele2.find('div[data-recid]').length>0){ //for standard view
                                ele2.find('div[data-recid]')[0].style = null;
                            }
                            
                            $('<div class="recordDiv outline_suppress expanded" recid="0" tabindex="0">')
                                .html(that.pnlOverview.html()).prependTo(ele);
                                
                        }else{
                            var h = ele2[0].scrollHeight+10;
                            ele2.find('div[data-recid]').css('max-height','100%');
                        }
                                
                    });   
                    
            }
            
        }else{
            //clear 
            //this.options.storyRecordID = null;
            this.pnlOverview.html(top.HR('There is no story for selected record'));
        }
        
    },

    //
    //
    //
    _startNewStoryElement: function(recID){

        if(this._currentElementID != recID){
            this._currentElementID = recID;
            
            var actions = ['fade_in','fly_to','highlight'];
            //take list of required actions on story element change
            // zoom out - show entire bounds
            // zoom_in - zoom to current step
            // *fly_to - fly to step 
            // *fade_in - show marker, path or poly
            // bounce ??
            // highlight
            // follow_path - move marker along path
            // ant_path - css style
            // show_report - popup on map

            var all_events = this._resultset.getOrder();
            
            var that = this;
            
            if(this._mapping && this._mapping.length>0){
            
            var map = this._mapping.app_timemap('getMapping');
            var nativemap = map.nativemap;
            if(window.hWin.HEURIST4.util.isnull(this._L)) this._L = map.getLeaflet();
            
            var L = this._L;
            
            //hide all, gather to be animated
            var to_hide = [], layers = [];
            var hide_before_show = (actions.indexOf('fade_in')>=0);
            
            nativemap.eachLayer(function(top_layer){    
                if(top_layer instanceof L.LayerGroup)
                top_layer.eachLayer(function(layer){
                      if (layer instanceof L.Layer && layer.feature)  //(!(layer.cluster_layer_id>0)) &&
                      {
                            if(layer.feature.properties.rec_ID==recID){
                                layers.push(layer);
                                if(hide_before_show){
                                    layer.remove();    
                                }else if(layer._map==null){
                                    layer.addTo( nativemap );           
                                }
                            }else 
                            if (window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, all_events)>=0)
                            {
                                layer.remove();
                            }
                      }
                });
            });
            
            var delay = 0;   
            // ZOOM IN       
            if(actions.indexOf('zoom_in')>=0 || actions.indexOf('fly_to')>=0){
                var useRuler = (layers.length==1), bounds = [];
                $.each(layers, function(i, layer){
                    bounds.push( map.getLayerBounds(layer, useRuler) );    
                });
                bounds = map._mergeBounds(bounds);
                map.zoomToBounds(bounds, (actions.indexOf('fly_to')>=0) ); //default 1.5 seconds   
                delay = (actions.indexOf('fly_to')>=0)?2000:1000;
            }
            
            // FADE IN
            setTimeout(function(){
                that.fadeInLayerLeaflet(nativemap, layers, 0, 1, 0.05, 100);     
            },delay);

            /*
            if(actions.indexOf('fade_in')>=0){
                var map = this._mapping.app_timemap('getMapping');
                map.fadeInLayers( [recID] );
            }
            var map = this._mapping.app_timemap('getMapping');
            var layers = map.findLayerByRecID( [recID] );
            if(layers.length>0){
                    console.log('Found: '+recID);  
            }else{
                console.log('NOT Found: '+recID);
            } 
            if(actions.indexOf('fade_in')>=0){
                  this.fadeInLayerLeaflet(layers, 0, 1, 0.1, 100);     
            }
            */     
            
            }  
        }
    },
    
    // Fade-in function for Leaflet
    fadeInLayerLeaflet: function(nativemap, layers, startOpacity, finalOpacity, opacityStep, delay) 
    {
        var L = this._L;
        var opacity = startOpacity;
        var timer = setTimeout(function changeOpacity() {
            if (opacity < finalOpacity) {
                $.each(layers,function(i, lyr){
                    if(lyr instanceof L.Marker){
                        lyr.setOpacity( opacity );                        
                    }else{
                        lyr.setStyle({
                            opacity: opacity,
                            fillOpacity: opacity
                        });
                    }
                    if(lyr._map==null) lyr.addTo( nativemap )                    
                });
                opacity = opacity + opacityStep
            }

            timer = setTimeout(changeOpacity, delay);
            }, delay)
    }

});
