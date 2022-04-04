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
    _L: null, //refrence to leaflet
    

    _currentStoryID: 0,
    _storylayer_id: 0,
    _cache_story_geo: {},
    _cache_story_time: {},
    _cache_story_places: null,
    
    _currentElementID: 0,
    _nativelayer_id: 0, //current layer for Story Elements
    
    _terminateAnimation: false,
    _animationResolve: null, 
    _animationReject: null,

    // the constructor
    _create: function() {

        var that = this;
        
        this._cache_story_places = {};
        
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
        this.pnlStory = this._resultList.parent();

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
            this.pnlStory.height(this.element.height() - h); //465
            
            this._tabs.tabs('option','activate',function(event, ui){
                if(that._resultset && that._resultset.length()>0 
                    && !(that._currentElementID>0) && that._tabs.tabs('option','active')==1){
                    that._onNavigateStatus(0);
                    that._startNewStoryElement( that._resultset.getOrder()[0] );
                }
            });
            
        }else if(this.options.reportOverviewMode=='header'){
            this.pnlOverview.height(cssOverview.height);
        }else 
        if(this.options.reportOverviewMode=='inline'){
            this._resultList.parent().height(this.element.height());
            this._resultList.height('100%');    
        }
        
        if(this.options.reportElementMode=='slide'){
            this.pnlStoryReport = $('<div>').css({width:'100%',height:'100%',overflow:'auto'})
                .appendTo(this.pnlStory);
                
            var css = ' style="height:30px;line-height:30px;display:inline-block;'
                +'text-decoration:none;text-align: center;font-weight: bold;color:black;'
                
            $('<div style="top:10px;right:10px;position:absolute;z-index: 800;border: 2px solid #ccc; background:white;'
                +'background-clip: padding-box;border-radius: 4px;">' //;width:64px;
            +'<a id="btn-prev" '+css+'width:30px;border-right: 1px solid #ccc" href="#" '
                +'title="Previous" role="button" aria-label="Previous">&lt;</a>'
            +'<span id="nav-status" '+css+';width:auto;padding:0px 5px;border-right: 1px solid #ccc" href="#" '
                +'>1 of X</span>'
            +'<a id="btn-next" '+css+'width:30px;" href="#" '
                +'title="Next" role="button" aria-label="Next">&gt;</a></div>')        
                .appendTo(this.pnlStory);
                
            this._on(this.pnlStory.find('#btn-prev'),{click:function(){ this._onNavigate(false); }});    
            this._on(this.pnlStory.find('#btn-next'),{click:function(){ this._onNavigate(true); }});    
                
            this._resultList.hide();
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
    // for slide - navigate between story elements
    //
    _onNavigate: function(is_forward){
        
        var order = this._resultset.getOrder();
        var recID = 0, idx=-1;
        
        if(this._currentElementID>0){
            var idx = window.hWin.HEURIST4.util.findArrayIndex(this._currentElementID, order);
            if(is_forward){
                idx++;
            }else{
                idx--;
            }
        }else{
            idx = 0;
        }
        if(idx>=0 && idx<order.length){
            recID = order[idx];
        }
        
        if(recID>0){
            this._startNewStoryElement( recID );    
        }else
        if(this.options.reportOverviewMode=='inline'){
            //show overview for inline mode
            this._startNewStory(this.options.storyRecordID);
        }
        this._onNavigateStatus(idx);
    },

    //
    //
    //
    _onNavigateStatus: function(idx){

        var order = this._resultset.getOrder();
        var dis_next = false, dis_prev = false;;    
        
        if(idx>=order.length-1){
          idx = order.length-1; 
          dis_next = true;      
        } 
        if(idx < (this.options.reportOverviewMode=='inline'?0:1)){
            idx = (this.options.reportOverviewMode=='inline')?-1:0;
            dis_prev = true;      
        }
        
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btn-prev'), dis_prev  );
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btn-next'), dis_next);
        
        
        this.element.find('#nav-status').text((idx+1)+' of '+order.length);
    },

        
    //
    // Loads story elements for given record from server side
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
            
            let DT_STORY_ANIMATION = $Db.getLocalID('dty', '2-1090');


            if(this.options.storyFields.length>0){
                //search for story fields for given record
                request = {q:{ids:this.options.storyRecordID}, detail:this.options.storyFields.join(',')};

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
                                
                                //returns story elements in exact order
                                var request = {q:[{ids:recIDs},{sort:('set:'+recIDs)}], detail:DT_STORY_ANIMATION};
                                
                                if(that.options.storyRectypes.length>0){
                                    request['q'].push({t:that.options.storyRectypes.join(',')});
                                }
                                
                                window.hWin.HAPI4.RecordMgr.search(request,
                                    function(response) {
//console.log( response.data );                                        
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
    _stopAnimeAndClearMap: function(){

        //stop animation        
        if(this._animationResolve!=null){
            this._terminateAnimation = true;
        }
    
//console.log('REMOVE '+this._nativelayer_id);
        //clear map
        if(this._nativelayer_id>0 && this._mapping.app_timemap('instance')){
            var mapwidget = this._mapping.app_timemap('getMapping');
            mapwidget.removeLayer( this._nativelayer_id );
            this._nativelayer_id = -1;
        }
    },
    
    // 1. Loads all story elements time data
    // 2. loads list of story elements (this._resultset) into reulst list
    // 3. Render overview as smarty report or renderRecordData
    //
    _startNewStory: function(recID){
      
        this._stopAnimeAndClearMap();
        
        //find linked mapping
        if(this.options.map_widget_id){
            this._mapping = $('#'+this.options.map_widget_id)    
        }

        //remove previous story layer
        if(this._storylayer_id>0){
            var mapwidget = this._mapping.app_timemap('getMapping');
            mapwidget.removeLayer( this._storylayer_id );
            this._storylayer_id = 0;
        }
        
        // mode A: load all places at once 
        // mode B: loads places for every story element separately - use this
        if(false && this._mapping && this._mapping.length>0){  
            
            var res = this._mapping.app_timemap('updateDataset', this._resultset, 'Whole Story Map');
            if(!res) {
                var that = this;
                this._mapping.app_timemap('option','onMapInit', function(){
                    var map = that._mapping.app_timemap('getMapping');
                    map.isMarkerClusterEnabled = false;
                    that._mapping.app_timemap('updateDataset', that._resultset, 'Whole Story Map');
                });
            }else{
                var map = this._mapping.app_timemap('getMapping');
                map.isMarkerClusterEnabled = false;
            }
        }
        
        //switch to Overview
        if(this._tabs){
            this._tabs.tabs('option', 'active', 0);   
        }
        
        //loads list of story elements into reulst list
        if(this.options.reportElementMode!='slide'){      
            this._resultList.resultList('updateResultSet', this._resultset);
        }
        
        this._currentElementID = null;
                        
        if(this._resultset && this._resultset.length()>0){
            this.options.storyRecordID = recID;
            //1. Render overview panel
            var infoURL;
            var isSmarty = false;
            
            if( typeof this.options.reportOverview === 'string' 
                            && this.options.reportOverview.substr(-4)=='.tpl' ){
            
                infoURL = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?snippet=1&publish=1&debug=0&q=ids:'
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

                        if(ele2.find('div[data-recid]').length>0){ //for standard view
                            ele2.find('div[data-recid]')[0].style = null;
                        }
                            
                        if(that.options.reportOverviewMode=='inline'){
                            //loads overview as first element in story list
                            
                            if(that.options.reportElementMode=='slide'){      
                                that.pnlStoryReport.html(that.pnlOverview.html())
                            }else{
                                var ele = that._resultList.find('.div-result-list-content');    
                                $('<div class="recordDiv outline_suppress expanded" recid="0" tabindex="0">')
                                    .html(that.pnlOverview.html()).prependTo(ele);
                            }
                                
                        }else{
                            var h = ele2[0].scrollHeight+10;
                            if(ele2.find('div[data-recid]').length>0){
                                ele2.find('div[data-recid]').css('max-height','100%');
                            }
                        }
                                
                    });   
                    
            }
            
            
            //Loads time data all story elements - into special layer "Whole Store Timeline"
            this.updateTimeLine();
            
            
        }else{
            //clear 
            //this.options.storyRecordID = null;
            this.pnlOverview.html(top.HR('There is no story for selected record'));
        }
        
    },
    
    //
    // update Whole store timeline
    //
    updateTimeLine: function(){
        
        var that = this;
        
        if(that._cache_story_geo[that.options.storyRecordID]=='no data'){
            //no time data for this story
            
            
        }else if(that._cache_story_geo[that.options.storyRecordID] 
                || that._cache_story_time[that.options.storyRecordID])
        {
            
            if(!this._mapping.app_timemap('isMapInited')){
                    
                this._mapping.app_timemap('option','onMapInit', function(){
                    that.updateTimeLine();
                });
                return;
            }
            
            //update timeline
            var mapwidget = this._mapping.app_timemap('getMapping');
            mapwidget.isMarkerClusterEnabled = false;
            
            this._storylayer_id = mapwidget.addGeoJson(
                {geojson_data: that._cache_story_geo[that.options.storyRecordID],
                 timeline_data: that._cache_story_time[that.options.storyRecordID],
                    //layer_style: layer_style,
                    //popup_template: layer_popup_template,
                 dataset_name: 'Story Timeline',
                 preserveViewport: false });
            
        }else{
            
            var server_request = {
                            q: {ids: this._resultset.getIds()},
                            leaflet: true, 
                            simplify: true, //simplify paths with more than 1000 vertices
                            //suppress_linked_places: 1, //do not load assosiated places
                            zip: 1,
                            format:'geojson'};
            window.hWin.HAPI4.RecordMgr.search_new(server_request,
                function(response){
                    var geojson_data = null
                    if(response['geojson']){
                        geojson_data = response['geojson'];
                    }else{
                        geojson_data = response;
                    }
//console.log(geojson_data);
                    if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) ){
                        that._cache_story_geo[that.options.storyRecordID] = geojson_data;
                    }else{
                        that._cache_story_geo[that.options.storyRecordID] = null;
                    }
                    
                    if(response['timeline']){
                        that._cache_story_time[that.options.storyRecordID] = response['timeline'];
                    }else {
                        that._cache_story_time[that.options.storyRecordID] = null;
                    }   
                    
                    if(!(that._cache_story_geo[that.options.storyRecordID] 
                        || that._cache_story_time[that.options.storyRecordID])){
                        that._cache_story_geo[that.options.storyRecordID] = 'no data';     
                    }
                    
                    that.updateTimeLine();
                });
        }
    },

    //
    // 1. Loads story for slide (if reportElementMode=='slide')
    // 2. Executes animation on map
    //
    _startNewStoryElement: function(recID){

        if(this._currentElementID != recID){
          
            if(this._storylayer_id>0){
                var mapwidget = this._mapping.app_timemap('getMapping');
                var lyr = mapwidget.all_layers[this._storylayer_id];        
                if(lyr._map!=null){
                    //lyr.addTo( mapwidget.nativemap );                      
                    lyr.remove();
                }
            }
            
            
            this._stopAnimeAndClearMap();

            this._currentElementID = recID;
            
            if(this.options.reportElementMode=='slide'){   //one by one   

                
                var infoURL;
                var isSmarty = false;
                
                if( typeof this.options.reportOverview === 'string' 
                                && this.options.reportOverview.substr(-4)=='.tpl' ){
                
                    infoURL = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?snippet=1&publish=1&debug=0&q=ids:'
                            + recID 
                            + '&db='+window.hWin.HAPI4.database+'&template='
                            + encodeURIComponent(this.options.reportElement);
                    isSmarty = true;
                }else{
                    infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?mapPopup=1&recID='
                            +recID
                            +'&db='+window.hWin.HAPI4.database;
                }
                
                this.pnlStoryReport.addClass('loading').css({'overflow-y':'auto'})
                    .load(infoURL, function(){ 
                        
                        var ele2 = $(this);
                        ele2.removeClass('loading').css('min-height','200px');//.height('auto');    

                        if(ele2.find('div[data-recid]').length>0){ //for standard view
                            ele2.find('div[data-recid]')[0].style = null;
                        }
                    });
                
            }                    
            
            if(this._mapping && this._mapping.length>0){
                //this._animateStoryElement_A(recID);
                this._animateStoryElement_B(recID);
            
            

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
    
    //
    // story element is geometry collection  - animate entire set - OLD MODE
    //
    _animateStoryElement_A: function(recID){
        
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
        
    },
    
    //
    // Every place is separate object on map - animate sequence - begin, transition, end places
    // 1. find all resource fields that points to places
    // 2. retrieve all places from server side as geojson
    // 3. create links between points
    // 4. update map
    // 5. execute animation
    _animateStoryElement_B: function(recID){

        var that = this;

        if ( that._cache_story_places[recID] ){ //cache is loaded already
            
            var pl = that._cache_story_places[recID]['places'];
            if( pl.length==0){
            }else{
                //map is already cleared in _startNewStoryElement
                that._animateStoryElement_B_step2(recID);
            }
            return;    
        }

        var request = {q: 'ids:'+recID, detail:'detail'};

        window.hWin.HAPI4.RecordMgr.search(request,
            function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    // 1. find all resource fields that points to places               
                    that._cache_story_places[recID] = {};
                    that._cache_story_places[recID]['places'] = [];
                    var RT_PLACE  = window.hWin.HAPI4.sysinfo['dbconst']['RT_PLACE'];
                    if(response.data.count==1){
                        var details = response.data.records[recID]['d'];
                        var dty_ID;   
                        for(dty_ID in details){
                            var field = $Db.dty(dty_ID);
                            if(field['dty_Type']=='resource'){
                                var ptr = field['dty_PtrTargetRectypeIDs'];
                                if(ptr && window.hWin.HEURIST4.util.findArrayIndex(RT_PLACE, ptr.split(','))>=0){
                                    that._cache_story_places[recID][dty_ID] = details[dty_ID];   
                                } 
                            } 
                        }
                        //concatenate all places in proper order
                        // Begin '2-134', Transition '1414-1090', End '2-864'
                        let DT_BEGIN_PLACES = $Db.getLocalID('dty', '2-134');
                        let DT_BEGIN_PLACES2 = $Db.getLocalID('dty', '1414-1092');
                        let DT_END_PLACES = $Db.getLocalID('dty', '2-864');
                        if(DT_BEGIN_PLACES>0 && that._cache_story_places[recID][DT_BEGIN_PLACES]){
                            that._cache_story_places[recID]['places'] = that._cache_story_places[recID][DT_BEGIN_PLACES];
                        }
                        if(DT_BEGIN_PLACES2>0 && that._cache_story_places[recID][DT_BEGIN_PLACES2]){
                            that._cache_story_places[recID]['places'] = that._cache_story_places[recID][DT_BEGIN_PLACES2];
                        }
                        for(dty_ID in that._cache_story_places[recID]){
                            if(dty_ID!=DT_BEGIN_PLACES && dty_ID!=DT_BEGIN_PLACES2 && dty_ID!=DT_END_PLACES && dty_ID!='places'){
                                    that._cache_story_places[recID]['places'] = that._cache_story_places[recID]['places']
                                    .concat(that._cache_story_places[recID][dty_ID]);
                            }
                        }
                        if(DT_END_PLACES>0 && that._cache_story_places[recID][DT_END_PLACES]){
                            that._cache_story_places[recID]['places'] = that._cache_story_places[recID]['places']
                                    .concat(that._cache_story_places[recID][DT_END_PLACES]);
                        }
                        
                        
                        if (that._cache_story_places[recID]['places'].length==0){
                            return;
                        }

//console.log(that._cache_story_places[recID]);

                        // 2. retrieve all places from server side as geojson
                        var server_request = {
                            q: {ids:that._cache_story_places[recID]['places']},
                            leaflet: true, 
                            simplify: true, //simplify paths with more than 1000 vertices
                            zip: 1,
                            format:'geojson'};
                        window.hWin.HAPI4.RecordMgr.search_new(server_request,
                            function(response){

                                var geojson_data = null;
                                //var timeline_data = [];
                                var layers_ids = [];
                                if(response['geojson']){
                                    geojson_data = response['geojson'];
                                    //not used timeline_data = response['timeline']; 
                                }else{
                                    geojson_data = response;
                                }
//console.log(geojson_data);
                                if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) )
                                {
                                     
                                    that._cache_story_places[recID]['geojson'] = geojson_data;
                                    //that._cache_story_places[recID]['timeline'] = timeline_data;

                                    // 3. create links between points
                                    that._createPointLinks();
                                    
                                    // 4. update map
                                    that._animateStoryElement_B_step2(recID);
                                    

                                }else {
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }


                        });
                    }

                }else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );  


    },
    
    //
    // 4. update map
    //
    _animateStoryElement_B_step2: function(recID){
        var that = this;
        
//console.log(that._cache_story_places[recID]['geojson']);        
        
        var mapwidget = that._mapping.app_timemap('getMapping');
        
        if(window.hWin.HEURIST4.util.isnull(this._L)) this._L = mapwidget.getLeaflet();
        
        //this._currentStoryID = recID;
        
        mapwidget.isMarkerClusterEnabled = false;
        this._nativelayer_id = mapwidget.addGeoJson(
            {geojson_data: that._cache_story_places[this._currentElementID]['geojson'],
                timeline_data: null, //that._cache_story_places[this._currentElementID]['timeline'],
                //layer_style: layer_style,
                //popup_template: layer_popup_template,
                dataset_name: 'Story Map',
                preserveViewport: true });
//console.log('ADDED '+this._nativelayer_id);                
        //possible sequences
        // gain: begin-visible, trans fade in, end-visible
        // loses: trans-visible, trans fade out
        // path grow: fade by 1 or group
        // path move: fade in and out by 1 or group
        
        // json to describe animation
        // [{scope:begin|trans|end|all, range:0~n, actions:[{ action: duration: , steps:},..]},....] 

        let DT_STORY_ANIMATION = $Db.getLocalID('dty', '2-1090');
        var record = this._resultset.getRecord(recID);
        var anime = this._resultset.fld(record, DT_STORY_ANIMATION);

//console.log('Animation '+anime);        

        /*        
        var anime = [{scope:'all',action:'hide'},{scope:'all',range:1,action:'fade_in',duration:1000}]; //show in sequence

        anime = [{scope:'all',range:1,action:'fade_out',duration:1000}]; //hide in sequence
        anime = [{scope:'all',action:'hide'},{scope:'all',range:1,action:'fade_in_out',duration:1000}];

        anime = [{scope:'all',range:1,actions:[{action:'fly'}]}];
        anime = [{scope:'all',action:'hide'},{scope:'all',range:1,actions:[{action:'center'},{action:'fade_in'}]}];
        anime = [{"scope":"all","actions":[{"action":"blink","duration":2000}]}];
        
        anime = [{"scope":"begin","actions":[{"action":"style","style":  }]}];
        
[{"scope":"all","actions":[{"action":"zoom"},{"action":"blink","steps":10,"duration":2000}] }]        
        */

        //or several actions per scope
        //var anime = [{scope:'all',range:1,actions:[{action:'fly'},{action:'fade_in',duration:500}]}];
        
        //var anime = [{scope:'all',range:1,action:'fade_in_out',duration:500}]; //show one by one
        
        this.actionBounds( [mapwidget.all_layers[this._nativelayer_id]], 'fly' );
        
        if(!window.hWin.HEURIST4.util.isempty(anime)){
            
            anime = window.hWin.HEURIST4.util.isJSON(anime);
            if(anime){        
                //start animation/actions
                this._animateStoryElement_B_step3(anime);
            }
        }
    },
    
    //
    // performs animation for current range
    //
    _animateStoryElement_B_step3: function(aSteps, step_idx, aRanges, range_idx, aActions, action_idx ){        

//console.log(this._currentElementID+'  step_idx='+step_idx+' range_idx='+range_idx+'  action_idx='+action_idx);
        
        //find ranges of places for animation
        if(window.hWin.HEURIST4.util.isempty(aRanges) || range_idx>=aRanges.length){ 
            //ranges not defined or all ranges are executed - go to new step
        
            //1.loop for steps - fill aRanges for current step
            step_idx = (step_idx>=0) ?step_idx+1:0;
            
            if(step_idx>=aSteps.length) return; //animation is completed
            
            var step = aSteps[step_idx];
            
            aActions = step['actions'];
            if(!aActions && step['action']){
                aActions = [{action:step['action']}];  
                if(step['duration']>0){
                    aActions[0]['duration'] = step['duration'];
                }
            } 
            if(window.hWin.HEURIST4.util.isempty(aActions)){ //actions are not defined for this step
                //actions are not defined - go to next step
                this._animateStoryElement_B_step3(aSteps, step_idx );   
                return;
            }
            
            range_idx = 0;
            action_idx = 0;
            
            //2.find places for current step
            
            var places, scope = null;
            
            //2a get scope - @todo array/combination of scopes
            if(step['scope']=='begin'){
                scope = '2-134';
            }else if(step['scope']=='trans'){
                scope = '1414-1090';
            }else if(step['scope']=='end'){
                scope = '2-864';
            }else if( typeof step['scope'] === 'string' && step['scope'].indexOf('-')>0 ){ //dty concept code
                scope = step['scope'];
            }else if( $.isArray(step['scope']) ){
                //array of record ids
                places = step['scope'];
            }else if( parseInt(step['scope'])>0 ){
                //particular record id
                places = [step['scope']]; 
                
            }else{ //if(step['scope']=='all'){ //default
                scope = 'places'; //all places ids in proper order
            }
            
            if(scope){
                var scope2 = scope;
                if(scope.indexOf('-')>0){
                    scope = $Db.getLocalID('dty', scope);
                }
                places = this._cache_story_places[this._currentElementID][scope];    
                
                //special case - we have to fields for begin places
                if(window.hWin.HEURIST4.util.isempty(places) && scope2=='2-134'){
                    scope = $Db.getLocalID('dty', '1414-1092');
                    places = this._cache_story_places[this._currentElementID][scope];    
                }
            }
            
            //2b get ranges within scope
            aRanges = []; //reset
            var range = places.length;
            if(step['range'] && step['range']>0 && step['range']<places.length){
                range = step['range'];
            
                var start = 0, end = 0;
                while (end<places.length){
                    
                    end = start+range;
                    //if(end>=places.length) end = places.length-1;
                    
                    aRanges.push(places.slice(start, end));
                    start = end;
                    
                    //if(end>=places.length-1) break;
                }
            }else{
                aRanges.push(places);
            }
            
            if(window.hWin.HEURIST4.util.isempty(aRanges)){ //ranges are not defined for this step
                //rangess are not defined - go to next step
                this._animateStoryElement_B_step3(aSteps, step_idx );   
                return;
            }

        }//search for ranges for current step
        
        //3 execute action(s)    
        if(action_idx>=aActions.length){ 
            //all actions are executed
            action_idx = 0;
            this._animateStoryElement_B_step3(aSteps, step_idx, aRanges, range_idx+1, aActions, action_idx );
            return;
        }
        
        
        var range_of_places = aRanges[range_idx];
        
        var mapwidget = this._mapping.app_timemap('getMapping');
        var L = this._L;
        var top_layer = mapwidget.all_layers[this._nativelayer_id];
        
        var layers = this._getPlacesForRange(top_layer, range_of_places);
       
        var action = aActions[action_idx];
               
//console.log(range_of_places);               
//console.log('before anime '+layers.length);

        // 
        //take list of required actions on story element change
        // ??zoom out - show entire bounds (all places within story element?)
        // zoom - zoom to scope
        // *fly - fly to scope
        // center
        // *show
        // *hide
        // *fade_in - show marker, path or poly
        // *fade_in_out - show marker, path or poly
        // *blink
        // *gradient
        // *style - assign new style
        // show popup
        
        // follow_path - move marker along path
        // ant_path - css style
        // show_report - popup on map


        var that = this;

        var promise = new Promise(function(_resolve, _reject){
            
            that._animationResolve = _resolve;
            that._animationReject = _reject;
               
            switch (action['action']) {
               case 'fade_in':
                    that.actionFadeIn(mapwidget.nativemap, layers, 0, 1, 0.05, action['duration'], action['delay']);
               break;
               case 'fade_out':
                    that.actionFadeIn(mapwidget.nativemap, layers, 1, 0, -0.05, action['duration'], action['delay']);
               break;
               case 'fade_in_out':
                    that.actionFadeIn(mapwidget.nativemap, layers, 0, 1, 0.05, action['duration'], action['delay'], true);
               break;
               case 'hide':
                    that.actionHide(layers); //, action['duration']
               break;
               case 'show':
                    that.actionShow(mapwidget.nativemap, layers); //, action['duration']
               break;
               case 'fly':
               case 'zoom':
               case 'center':
                    that.actionBounds(layers, action['action'], action['duration']);       
               break;
               case 'blink':

                    that.actionBlink(layers, action['steps'], action['duration']);
               
               break;
               case 'gradient':
                    //change color from one color to another
                    if(!action['from']) action['from'] = '#ff0000';
                    if(!action['to']) action['to'] = '#00ff00';
                    that.actionGradient(layers, action['from'], action['to'], action['steps'], action['duration']);
               break;
               case 'style':

                    that.actionSetStyle(layers, action['style'], action['duration']);
               
               break;
            }
        });
        
        promise
        .then(function(res){
//console.log('after anim range_idx='+range_idx+'  action_idx='+action_idx);
            that._animationResolve = null;
            if(that._terminateAnimation){
                that._terminateAnimation = false;
            }else{
                that._animateStoryElement_B_step3(aSteps, step_idx, aRanges, range_idx, aActions, action_idx+1 );    
            }
        },
        function(res){ //termination
            that._terminateAnimation = false;
            that._animationResolve = null;
            
        });     
        
    },
    
    //
    //
    //
    _getPlacesForRange: function(top_layer, range_of_places){

        var layers = [];
        var L = this._L;


        top_layer.eachLayer(function(layer){
              if (layer instanceof L.Layer && layer.feature)  //(!(layer.cluster_layer_id>0)) &&
              {
                    if(layer.feature.properties.rec_ID>0){
                        var idx = window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, range_of_places);
                        if(idx>=0) layers.push(layer);
                    }

                    /*                  
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
                    */
              }
        });        
        
        return layers;
    },


    //
    // fly, zoom, center
    //
    actionBounds: function(layers, mode, duration){
        
        var mapwidget = this._mapping.app_timemap('getMapping');
        var useRuler = (layers.length==1), bounds = [];
        
        $.each(layers, function(i, layer){
            var bnd = mapwidget.getLayerBounds(layer, useRuler);
            bounds.push( bnd );    
        });
        
        //.nativemap
        var bounds = mapwidget._mergeBounds(bounds);
        
        if(mode=='center'){
            mapwidget.nativemap.panTo(bounds.getCenter());    //setView
        }else{
            mapwidget.zoomToBounds(bounds, (mode=='fly')); //default 1.5 seconds   
        }
        
        if(!(duration>0)) duration = 2000;
        
        if($.isFunction(this._animationResolve)){
            var that = this;
            setTimeout(function(){
                    if(that._terminateAnimation){
console.log('animation terminated !!!');                        
                        if($.isFunction(that._animationReject)) that._animationReject();
                    }else{
                        if ($.isFunction(that._animationResolve)) that._animationResolve();
                    }                
                }, duration);                        
        }
    },
    
    //
    //
    //    
    actionHide: function( layers ){        
            //hide all layer elements
            $.each(layers, function(i, layer){
                      layer.remove();
            });
            if($.isFunction(this._animationResolve)) this._animationResolve();
    },

    //
    //
    //    
    actionShow: function( nativemap, layers ){        
            //hide all layer elements
            $.each(layers, function(i, layer){
                  if (layer._map==null){
                      layer.addTo( nativemap )                    
                  }
            });
            if($.isFunction(this._animationResolve)) this._animationResolve();
    },

    //
    // Fade-in function for Leaflet
    // if opacityStep<0 - fade out
    // need_reverce - true - fade in and then out
    // show_delay - delay before hide or after show
    actionFadeIn: function(nativemap, layers, startOpacity, finalOpacity, opacityStep, duration, show_delay, need_reverce) 
    {
        
        var steps = Math.abs(finalOpacity-startOpacity)/Math.abs(opacityStep);
        if(need_reverce) steps = steps * 2;
        
        if(!(duration>0)) duration = 1000;        
        var delay = duration/steps;
        
        if(!show_delay) show_delay = 0;

        
        var that = this;
        var L = this._L;
        var opacity = startOpacity;
        function __changeOpacity() {
            
            var iOK = (opacityStep>0)
                    ?(opacity < finalOpacity)
                    :(finalOpacity < opacity);

            if ( iOK ) {
                $.each(layers, function(i, lyr){
                    
                    if(that._terminateAnimation){
console.log('animation terminated');                        
                        if($.isFunction(that._animationReject)) that._animationReject();
                        return false;
                    }
               
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
                opacity = opacity + opacityStep;
                timer = setTimeout(__changeOpacity, delay);
            }else{
                if(need_reverce===true){
                    need_reverce = false
                    opacityStep = -opacityStep;
                    opacity = finalOpacity;
                    finalOpacity = startOpacity;
                    startOpacity = opacity; 
                    //delay before hide
                   
                    timer = setTimeout(__changeOpacity, show_delay>0?show_delay:delay);
                }else{
                    if(opacityStep>0 && show_delay>0){
                        //delay after show
                        setTimeout(function(){
                             if($.isFunction(that._animationResolve)) that._animationResolve();   
                        }, show_delay);
                    }else{
                        if($.isFunction(that._animationResolve)) that._animationResolve();        
                    }
                    
                    
                }
            }
        }//__changeOpacity
        
        if(opacityStep<0 && show_delay>0){
            //delay before hide
            setTimeout(__changeOpacity, show_delay);
        }else{
            __changeOpacity();    
        }
        
        
    },
    
    //
    //
    //
    actionGradient: function(layers, startColour, endColour, steps, duration){

        if(!duration) duration = 2000;
        if(!steps) steps = 20;
        
        var that = this;
        var delay = duration/steps;
        
        var colors = window.hWin.HEURIST4.ui.getColourGradient(startColour, endColour, steps);
        var color_step = 0;
        
        var mapwidget = this._mapping.app_timemap('getMapping');
        var top_layer = mapwidget.all_layers[this._nativelayer_id];
        
        function __changeColor() {
            if ( color_step<colors.length ) {
                $.each(layers, function(i, lyr){
                    
                    if(that._terminateAnimation){
console.log('animation terminated 2');                        
                        if($.isFunction(that._animationReject)) that._animationReject();
                        return false;
                    }
                    
                    var clr = colors[color_step];
                    
                    var style = {color:clr, fillColor:clr};
                    
                    mapwidget.applyStyleForLayer(top_layer, lyr, style);
                    
                    //if(lyr._map==null) lyr.addTo( nativemap )                    
                });
                color_step++;
                timer = setTimeout(__changeColor, delay);
            }else{
                if($.isFunction(that._animationResolve)) that._animationResolve();    
            }                
        }
        
        __changeColor();
        
        
    },
    
    actionBlink: function(layers, steps, duration){
        
        if(!duration) duration = 1000;
        if(!steps) steps = 10;
        
        var that = this;
        var delay = duration/steps;
        var count = 0;
        var mapwidget = this._mapping.app_timemap('getMapping');
        var is_visible = [];

        var interval = window.setInterval(function() {
            
            $.each(layers, function(i, lyr){
                
                if(count==0){
                    //keep initial visibility
                    is_visible.push((lyr._map!=null));
                }
            
                if(that._terminateAnimation){
                    clearInterval(interval);
                    if($.isFunction(that._animationReject)) that._animationReject();
                    return false;
                }
                
                if(lyr._map==null){
                    lyr.addTo( mapwidget.nativemap );                      
                }else{
                    lyr.remove();
                }
                
            });
            
            count++;
            if(count>steps){
                clearInterval(interval);
                if($.isFunction(that._animationResolve)) that._animationResolve();    
            }
        },delay);
        
        //restore initial visibility
        $.each(layers, function(i, lyr){
            if(is_visible[i]){
                if(lyr._map==null) lyr.addTo( mapwidget.nativemap );                    
            }else{
                lyr.remove()
            }
            
        });
        
    },
    
    //
    //
    //
    actionSetStyle: function(layers, newStyle, delay){

        if(newStyle){
        
        if(!delay) delay = 500;

            var that = this;
            var mapwidget = this._mapping.app_timemap('getMapping');
            var top_layer = mapwidget.all_layers[this._nativelayer_id];
            
            setTimeout(function(){

                $.each(layers, function(i, lyr){
                    mapwidget.applyStyleForLayer(top_layer, lyr, newStyle);
                });
                
                if(that._terminateAnimation){
                    console.log('animation terminated actionSetStyle');                        
                    if($.isFunction(that._animationReject)) that._animationReject();
                    return false;
                }else
                    if($.isFunction(that._animationResolve)) that._animationResolve();            
                }, delay);
            
        }else{
            if($.isFunction(that._animationResolve)) that._animationResolve();            
        }
        
    },
    
    //
    //
    //
    _createPointLinks: function(){

        // Begin '2-134', Transition '1414-1090', End '2-864'
        let DT_BEGIN_PLACES = $Db.getLocalID('dty', '2-134');
        let DT_BEGIN_PLACES2 = $Db.getLocalID('dty', '1414-1092');
        let DT_END_PLACES = $Db.getLocalID('dty', '2-864');
        let DT_TRAN_PLACES = $Db.getLocalID('dty', '1414-1090');
        
        var recID = this._currentElementID;
        
        var gd = this._cache_story_places[recID]['geojson'];
        //gather all verties
        var begin_pnt = [], end_pnt = [], tran_pnt = [];
        
        function _fillPnts(ids, pnt){

            if(!window.hWin.HEURIST4.util.isempty(ids))
            for (var k=0; k<=ids.length; k++){
                for (var i=0; i<gd.length; i++){
                    if(gd[i]['id']==ids[k] && gd[i]['geometry']['type']=='Point'){
                        pnt.push(gd[i]['geometry']['coordinates']);
                        break;
                    }
                }
            }
            
        }
        
        _fillPnts(this._cache_story_places[recID][DT_BEGIN_PLACES], begin_pnt);
        _fillPnts(this._cache_story_places[recID][DT_BEGIN_PLACES2], begin_pnt);
        _fillPnts(this._cache_story_places[recID][DT_END_PLACES], end_pnt);
        _fillPnts(this._cache_story_places[recID][DT_TRAN_PLACES], tran_pnt);
        
        var path = null;
        //create link path from begin to end place
        if (begin_pnt.length>0 || end_pnt.length>0 || tran_pnt.length>0){
            //$geovalues = array();
            
            //PAIRS: many start points and transition points - star from start points to first transition
            if(begin_pnt.length>1 || end_pnt.length>1){
                path = {geometry:{coordinates:[], type:'MultiLineString'}, id:'xxx', type:'Feature', properties:{rec_ID:0}};
                
                if(tran_pnt.length>0){

                    //adds lines from start to first transition    
                    if(begin_pnt.length>0){
                        for(var i=0; i<begin_pnt.length; i++){
                            path.geometry.coordinates.push([begin_pnt[i], tran_pnt[0]]);
                        }                
                    }
                    //transition
                    path.geometry.coordinates.push(tran_pnt);
                    
                    //lines from last transition to end points
                    if(end_pnt.length>0){
                        var last = tran_pnt.length-1;
                        for(var i=0; i<end_pnt.length; i++){
                            path.geometry.coordinates.push([tran_pnt[last], end_pnt[i]]);
                        }                
                    }
                    
                }else if(end_pnt.length==begin_pnt.length){ //PAIRS
                    //adds lines from start to end
                    for(var i=0; i<begin_pnt.length; i++){
                        path.geometry.coordinates.push([begin_pnt[i], end_pnt[i]]);
                    }                
                }
                
                
            }else{
                path = {geometry:{coordinates:[], type:'LineString'}, id:'xxx', type:'Feature', properties:{rec_ID:0}};
                
                if(begin_pnt.length>0) path.geometry.coordinates.push(begin_pnt[0]);

                if(tran_pnt.length>0)
                    for(var i=0; i<tran_pnt.length; i++){
                        path.geometry.coordinates.push(tran_pnt[i]);
                    }                

                if(end_pnt.length>0) path.geometry.coordinates.push(end_pnt[0]);
            }
            
            
            if(path.geometry.coordinates.length>0){
                this._cache_story_places[recID]['geojson'].push(path);
            }
        }    
    
    }

});
