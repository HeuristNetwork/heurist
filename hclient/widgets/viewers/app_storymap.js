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
        elementOrder: '', // field ID (dty_ID) to order story elements

        
        // general options
        storyRecordID: null, // record with story elements (it has links to)
        reportOverview: null, // smarty report for overview. It uses storyRecordID
        reportOverviewMapFilter: null, //filter for events/story elements in initial map
        
        reportElement: null,  // smarty report to draw items in resultList
        //story/result list parameters
        reportOverviewMode: 'inline', // tab | header (separate panel on top), no
        reportElementMode: 'vertical', //vertical | slide | tabs
        reportElementDistinct: 'unveil', //none, heighlight, unveil (veil others)
        reportElementSlideEffect: '', 
        reportElementMapMode:'linked', //filtered, all
        reportElementMapFilter:'',
        reportElementCss: null,

        // timemap parameters
        keepCurrentTime: true, //keep current time on story change and load appropriate element
        //NOT USED use_internal_timemap: false, 
        //NOT USED mapDocumentID: null, //map document to be loaded (3-1019)
        
        zoomAnimationTime: 5000 //default value is 5000ms, it can be overwritten by animation parameters per story element
        
        //by default story element loads linked or internal places, or linked map layers
        //if story element has start (1414-1092 or 2-134), transition (1414-1090) and end places (1414-1088 or 2-864) they are preferable
        
        //show/animation parameters per story element
        , mapLayerID: null  // record ids to load on map (3-1020)
        , mapKeep: false    // keep elements on map permanently otherwise it will be unload for next story element
        , markerStyleID: null // record id (2-99) to define style (unless it is defined in map layers)
        , markerStyle: null   // 
        , storyActions: null  // zoom_in, zoom_out, follow_path, ant_path, fade_out, bounce, highlight, show_report
        
        , init_completed: false   //flag to be set to true on full widget initializtion
        
        , onClearStory: null

        , storyPlaceholder: 'Please select a story in the list' // placeholder text
    },

    _resultset_main: null, // current all stories
    _resultset: null, // current story element list - story elements
    _resultList: null,
    _tabs: null, //tabs control for overview/stories
    _mapping: null,    // mapping widget
    _mapping_onselect: null, //mapping event listener
    _L: null, //refrence to leaflet
    
    _all_stories_id: 0, //leaflet layer id with all stories

    _storylayer_id: 0,
    _cache_story_geo: {},
    _cache_story_time: {},
    _cache_story_places: null,
    
    _currentElementID: 0,
    _initialElementID: 0,
    _currentTime: null,
    _nativelayer_id: 0, //current layer for Story Elements
    
    _terminateAnimation: false,
    _animationResolve: null, 
    _animationReject: null,
    
    _initial_div_message:null,
    
    _expected_onScroll_timeout: 0,
    _expected_onScroll: 0,
    
    _timeout_count:0,
    
    _btn_clear_story: null,

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
                    "children":[{"name":"Overview content","type":"text","css":{},"content":"","dom_id":"pnlOverview"}]},
                 {"name":top.HR('Story'),"type":"group","css":{},"folder":true,
                        "children":[{"appid":"heurist_resultList","name":"Story list","css":{"position":"relative","minWidth":150}, //"minHeight":400
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
                                "onSelect": function(selected_ids){
                                    //if(recID==that.options.storyRecordID)
                                    if(selected_ids && selected_ids.length>0 
                                    &&that.options.reportOverviewMode=='inline' && that.options.reportElementMode=='tabs'){
                                        that._startNewStoryElement( selected_ids[0] );
                                    }
                                },
                                "rendererExpandDetails": function(recset, recID){
                                    var rep = (recID==that.options.storyRecordID)
                                        ?that.options.reportOverview
                                        :that.options.reportElement;
                                    if(window.hWin.HEURIST4.util.isempty(rep)){
                                        rep = 'default';
                                    }
                                    return rep;
                                                
                                },
                                "empty_remark": 
                        '<h3 class="not-found" style="color:teal;">'
                        + top.HR('There are no visible story points for the selected record (they may exist but not made public)')
                        + '</h3>',
                                "onScroll": function(event){ that._onScroll(event, that) },
                                "expandDetailsWithoutWarning": true,
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
        
        this._initial_div_message = 
        $('<div class="ent_wrapper" style="padding: 1em;background: white;"><br>'
        +'<h3 class="not-found" style="color:teal;display:inline-block">No records match the filter criteria</h3></div>')
        .appendTo(this.element);
        
        
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
        if(this._tabs.length>0 && this._tabs.tabs('instance')){  //TAB VIEW
            
            var h = this.element.find('.ui-tabs-nav:first').height(); //find('#tabCtrl').
            this.pnlOverview.height(this.element.height() - h);
            this._resultList.height(this.element.height() - h); //465
            this.pnlStory.height(this.element.height() - h); //465
            
            this._tabs.tabs('option','activate',function(event, ui){
                if(that._resultset && that._resultset.length()>0){
                    
                    if(!(that._currentElementID>0) && that._tabs.tabs('option','active')==1)
                    {
                        if(that.options.reportElementMode=='vertical'){
                            that._addStubSpaceForStoryList();
                        }
                        if(that._initialElementID==0){
                            that._onNavigateStatus(0);
                            that._startNewStoryElement( that._resultset.getOrder()[0] );
                        }
                    }
                    if(that.options.reportElementMode=='tabs'){
                        that._resizeStoryTabPages();
                    }
                }
            });
            
        }else{  //INLINE
            this.pnlStory.css({'position':'absolute',top:0, bottom:'0px', left:0, right:0});
            
            if(this.options.reportOverviewMode=='header'){
                this.pnlOverview.height(cssOverview.height);
                this.pnlStory.css({top:(this.pnlOverview.height()+'px')});
            }else 
            if(this.options.reportOverviewMode=='inline'){
                //this.pnlStory.height(this.element.height());
                //this._resultList.height('100%');    
            }
        }
        
        if(this.options.reportElementMode=='slide')
        {
            this.pnlStoryReport = $('<div>').css({overflow:'auto'})
                .appendTo(this.pnlStory);
                
            var css = ' style="height:28px;line-height:28px;display:inline-block;'
                +'text-decoration:none;text-align: center;font-weight: bold;color:black;'
                
            var navbar = $('<div style="top:2px;right:46px;position:absolute;z-index: 800;border: 2px solid #ccc; background:white;'
                +'background-clip: padding-box;border-radius: 4px;">' //;width:64px;
            +'<a id="btn-prev" '+css+'width:30px;border-right: 1px solid #ccc" href="#" '
                +'title="Previous" role="button" aria-label="Previous">&lt;</a>'
            +'<span id="nav-status" '+css+';width:auto;padding:0px 5px;border-right: 1px solid #ccc" href="#" '
                +'>1 of X</span>'
            +'<a id="btn-next" '+css+'width:30px;" href="#" '
                +'title="Next" role="button" aria-label="Next">&gt;</a></div>')        
                .appendTo(this.pnlStory);
                
            if(this.options.reportOverviewMode=='header'){
                //navbar.css({top: this.pnlOverview.height()+10+'px'});
                //this.pnlStoryReport.css({'position':'absolute',top:(this.pnlOverview.height()+'px'), bottom:0, left:0, right:0})
            }else{
                
            }
            this.pnlStoryReport.css({width:'100%',height:'100%'});   
                
            this._on(this.pnlStory.find('#btn-prev'),{click:function(){ this._onNavigate(false); }});    
            this._on(this.pnlStory.find('#btn-next'),{click:function(){ this._onNavigate(true); }});    
                
            this._resultList.hide();
        }else 
        {
            //vertical
            
            if(this.options.reportOverviewMode=='header'){
                this._resultList.css({'position':'absolute',top:0, bottom:0, left:0, right:0});
                //this.pnlOverview.height()+'px')
            }else{
                this._resultList.height('100%');    
            }
            
            
            if(this.options.reportElementMode=='tabs'){
                this._resultList.resultList('applyViewMode', 'tabs', true);
            }else{
                //add pointer to show activate zone for story element switcher (see in onScroll event)
                $('<span>')
                    .addClass('ui-icon ui-icon-triangle-1-e')
                    .css({position:'absolute', top: (this._resultList.position().top+100) + 'px', left:'-4px'})
                    .appendTo(this.pnlStory)
            }
        }
        
        
        /*        
        if(this.options.tabpanel){
            this.framecontent.css('top', 30);
        }else if ($(".header"+that.element.attr('id')).length===0){
            this.framecontent.css('top', 0);
        }*/
        
        //find linked mapping
        if(this.options.map_widget_id){
            this._mapping = $('#'+this.options.map_widget_id);
        }
        
        this._btn_clear_story = $('<button style="position:absolute;top:2px;right:12px;z-index:999;'
        +'border: 2px solid #ccc;background: white;background-clip: padding-box; border-radius: 4px;height: 31px;"'        
        +'">Close</button>')
        .button()
        .hide()
        .insertBefore((this.options.reportOverviewMode=='tab')?this._tabs:this.element.find('#tabCtrl'));
        this._on(this._btn_clear_story, {click:this.clearStory});
        
        if(window.hWin.HEURIST4.util.isempty(this.options.storyPlaceholder)){
            this.options.storyPlaceholder = 'Please select a story in the list';
        }

        this._initCompleted();
        
    }, //end _create
    
    
    
    //
    // It is called after associated map init
    // it init global listeners
    //
    _initCompleted: function(){
        
        var that = this;
        
        if(this._mapping){
            
            if(this._mapping.length==0){
                this._mapping = $('#'+this.options.map_widget_id);
            }
            
            if($.isFunction(this._mapping.app_timemap) && this._mapping.app_timemap('instance')){
                //widget inited
                if(!this._mapping.app_timemap('isMapInited')){
                    this._mapping.app_timemap('option','onMapInit', function(){
                        that._initCompleted();
                    });
                    return;
                }
        
            }else{
                this._timeout_count++;
                if(this._timeout_count<100){
                    setTimeout(function(){ that._initCompleted(); },200);
                    return;
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr('Mapping widget for story map is not inited properly');
                }
            }
        }
        
        if(this.options.storyRecordID){ //story is fixed and set as an option
            this._checkForStory( this.options.storyRecordID, true );
        }else{
            //take from selected result list
            
            this._events = window.hWin.HAPI4.Event.ON_REC_SELECT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH;                       
            
            $(this.document).on(this._events, function(e, data) {
                
                if(!that._isSameRealm(data)) return;
                
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){
                    
                    if(data && data.source!=that.element.attr('id')) {

                        if(data.selection && data.selection.length==1){
                            
                            var recID = data.selection[0];
                        
                            that._checkForStory(recID); //load certain story
                        
                        }
                    }
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){
                
                    var recset = data.recordset; //record in main result set (for example Persons)
                    
//console.log('main result set loaded');
                    
                    that._initial_div_message.find('h3')
                        .text(recset.length()>0
                            ?that.options.storyPlaceholder
                            :'No records match the filter criteria');
                    that._initial_div_message.show();
                    
                    that._resultset_main = recset;
                    
                    //find filtered Story Elements
                    that.updateInitialMap( recset );
                }
                
                
            });
        }
        
        this.options.init_completed = true;
        
    },

    //
    //
    //
    _destroy: function() {

        if(this._events){
            $(this.document).off(this._events);
        }
    },
    
    _setOption: function( key, value ) {
        if(key=='storyRecordID'){
            this.options.storyRecordID = value;
            if(value>0){
                this._checkForStory( this.options.storyRecordID, true );
            }
        }else{
            this._super( key, value );
        }
    },
    
    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        if(arguments && ((arguments[0] && arguments[0]['storyRecordID']>0) || arguments['storyRecordID']>0) ){
            this._checkForStory((arguments['storyRecordID']>0)?arguments['storyRecordID']:arguments[0].storyRecordID, true); 
        }else{
            this._superApply( arguments );    
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
    
    //
    // Change current story element - resultList listener
    //     
    _onScroll: function(event, that) {
        //if(this._disable_onScroll) return;
        
        var ele = $(event.target); //this.div_content;
        $.each(ele.find('.recordDiv'), function(i,item){
            var tt = $(item).position().top;
            var h = -($(item).height()-50);
            if(tt>h && tt<50){
                if(that._expected_onScroll>0 && that._expected_onScroll!=$(item).attr('recid')){

                }else{
                    that._startNewStoryElement( $(item).attr('recid') );
                }
                return false;
            }
        });
    },
    
    //
    // scroll to story element after selection on map
    //
    _scrollToStoryElement: function(recID){
        
        if(this.options.reportOverviewMode=='tab' && this._tabs){
            //switch to Story
            this._tabs.tabs('option', 'active', 1);   
        }
        
        if(this.options.reportElementMode=='vertical'){
            if(this._expected_onScroll_timeout>0){
                clearTimeout(this._expected_onScroll_timeout);
                this._expected_onScroll_timeout = 0;
            }
            
            //scroll result list
            this._expected_onScroll = recID;
            this._resultList.resultList('scrollToRecordDiv', recID, true);
            //sometimes it is called before addition of stub element at the end of list
            if(this._currentElementID!=recID){
                var that = this;
                this._expected_onScroll_timeout =  setTimeout(function(){that._scrollToStoryElement(that._expected_onScroll)},300);
                return;
            }
            this._expected_onScroll = 0;
            
        }else if(this.options.reportElementMode=='tabs'){
            
            this._resultList.resultList('scrollToRecordDiv', recID, true);
            this._startNewStoryElement( recID );
            
        }else
        {
            var order = this._resultset.getOrder();
            var idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
        
            this._onNavigateStatus( idx );    
            this._startNewStoryElement( recID );
        }
        
    },
    
    // for slide mode
    // navigate between story elements
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
            //show overview for current story in inline mode
            this.updateOverviewPanel(this.options.storyRecordID);
        }
        this._onNavigateStatus(idx);
    },

    // for slide mode
    // enable/disable next/prev buttons
    //
    _onNavigateStatus: function(idx){

        var order = this._resultset.getOrder();
        var dis_next = false, dis_prev = false;
        var len = order.length;
        var is_inline = (this.options.reportOverviewMode=='inline');
        
        if(idx >= len-1){
          idx = len-1; 
          dis_next = true;      
        } 
        if(idx < (is_inline?0:1)){
            idx = is_inline?-1:0;
            dis_prev = true;      
        }
        
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btn-prev'), dis_prev );
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btn-next'), dis_next );
        
        
        this.element.find('#nav-status').text((idx+1)+' of '+len);
    },

        
    //
    // Loads story elements for given record from server side
    //
    _checkForStory: function(recID, is_forced){
        
        if(this.options.storyRecordID != recID || is_forced){

            var that = this;
            
            this._initial_div_message.hide();
            
            this._currentTime = null;
            
            if(this.options.keepCurrentTime && this.options.storyRecordID>0 && this._currentElementID>0){
                
                if(this._cache_story_time && this._cache_story_time[this.options.storyRecordID]){

                    var story_time = this._cache_story_time[this.options.storyRecordID];
                    $.each(story_time, function(i,item){
                        if(item.rec_ID == that._currentElementID){
                            that._currentTime = item.when[0];
                        } 
                    });
                }
            }
            
            
            this.options.storyRecordID = recID;
        
            if(!$.isArray(this.options.storyFields) && typeof this.options.storyFields === 'string'){
                this.options.storyFields = this.options.storyFields.split(',');
            }
            if(!$.isArray(this.options.storyRectypes) && typeof this.options.storyRectypes === 'string'){ //NOT USED
                this.options.storyRectypes = this.options.storyRectypes.split(',');
            }
            
            var request;
            
            let DT_STORY_ANIMATION = $Db.getLocalID('dty', '2-1090'); //configuration field for animation and style
            let DT_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATE'];     //9
            let DT_START_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_START_DATE']; //10
            let DT_END_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_END_DATE']; //11

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
                                var request = {q:[{ids:recIDs},{sort:('set:'+recIDs)}]};
                                
                                var detail_fields = [];
                                if(that.options.elementOrder=='def'){
                                    detail_fields = [DT_DATE,DT_START_DATE,DT_END_DATE];
                                }else if(that.options.elementOrder){
                                    detail_fields = [that.options.elementOrder];
                                }
                                detail_fields.push(DT_STORY_ANIMATION);
                                
                                request['detail'] = detail_fields;
                                
                                if(that.options.storyRectypes.length>0){
                                    request['q'].push({t:that.options.storyRectypes.join(',')});
                                }
                                
                                window.hWin.HAPI4.RecordMgr.search(request,
                                    function(response) {
//console.log( response.data );                                        
                                        that._resultset = new hRecordSet(response.data);
                                        
                                        //sort
                                        if(that.options.elementOrder){
                                            var sortFields = {};
                                            if(that.options.elementOrder=='def'){
                                                
                                                that._resultset.each(function(recID, record){
                                                    var dt_st = that._resultset.fld(record, DT_START_DATE);
                                                    var dt_end = that._resultset.fld(record, DT_END_DATE);
                                                    if(!dt_st){
                                                        dt_st = that._resultset.fld(record, DT_DATE);
                                                    }
                                                    var dres = window.hWin.HEURIST4.util.parseDates(dt_st, dt_end);
                                                    
                                                    that._resultset.setFld(record, DT_START_DATE, dres[0]);
                                                    that._resultset.setFld(record, DT_END_DATE, dres[1]);
                                                });        
                                                
                                                //sortFields = {"9":1,"10":1,"11":1};
                                                //sortFields[DT_DATE] = 1;
                                                sortFields[DT_START_DATE] = 1;
                                                sortFields[DT_END_DATE] = 1;
                                            }else{
                                                sortFields[that.options.elementOrder] = 1;
                                            }
                                            
                                            that._resultset.sort(sortFields);
                                        }
                                        
                                        
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

        if(this._mapping){
            //stop animation        
            if(this._animationResolve!=null){ //animation active
                this._terminateAnimation = this._currentElementID>0?this._currentElementID:true;
            }
            
    //console.log('REMOVE '+this._nativelayer_id);
            //clear map
            if(this._nativelayer_id>0 && this._mapping.app_timemap('instance')){
                var mapwidget = this._mapping.app_timemap('getMapping');
                
                mapwidget.removeLayer( this._nativelayer_id );
                this._nativelayer_id = -1;
            }
        }
        
        this._currentElementID = 0;
    },
    
    //
    //
    //
    clearStory: function( trigger_event ){
        
        //remove previous story layer
        if(this._mapping){
            var mapwidget = this._mapping.app_timemap('getMapping');
            if(this._all_stories_id>0){
                mapwidget.removeLayer( this._all_stories_id );
                this._all_stories_id = 0;
            }
            if(this._storylayer_id>0){
                mapwidget.removeLayer( this._storylayer_id );
                this._storylayer_id = 0;
            }
        }
        this._stopAnimeAndClearMap();

        //switch to Overview
        if(this._tabs){
            this._tabs.tabs('option', 'active', 0);   
        }
        if(this.options.reportElementMode!='slide'){   
            this._resultList.resultList('clearAllRecordDivs');
        }
        
        this.pnlOverview.html('');
        
        
        this.options.storyRecordID = null;
        this._btn_clear_story.hide();
        if(this.options.reportOverviewMode=='tab') this._tabs.hide(); else this.element.find('#tabCtrl').hide();
        
        if(trigger_event !== false && $.isFunction(this.options.onClearStory)){
            this.options.onClearStory.call(this);
        }
    },
    
    //
    //
    //
    _resizeStoryTabPages: function(){
        if(this.options.reportElementMode=='tabs'){  
                var div_content = this._resultList.find('.div-result-list-content');
                if(div_content.tabs('instance')){
                    try{
                        div_content.tabs('pagingResize');
                    }catch(ex){
                    }
                }
        }
    },
    
    
    // 1. Loads all story elements time data
    // 2. loads list of story elements (this._resultset) into reulst list
    // 3. Render overview as smarty report or renderRecordData
    //
    _startNewStory: function(recID){
    
        if(this.options.storyRecordID != recID) return; //story already changed

        this.clearStory( false );
        
        this.options.storyRecordID = recID;
        
        if(this.options.reportOverviewMode=='tab') this._tabs.show(); else this.element.find('#tabCtrl').show();
        
        //loads list of story elements into reulst list
        if(this.options.reportElementMode=='vertical' || this.options.reportElementMode=='tabs'){   
            /*
            if(this.options.reportElementMode=='vertical'){   
                var ele = this._resultList.find('.div-result-list-content');
                ele.empty(); //to reset scrollbar
                ele[0].scrollTop = 0;
            }*/
            if(this.options.reportOverviewMode=='inline' && this.options.reportElementMode=='tabs'){
                //show overview for current story in inline mode
                this._resultset.addRecord(recID, {rec_ID:recID, rec_Title:'Overview'}, true);
            }
            
            this._resultList.resultList('updateResultSet', this._resultset);
            //add last stub element to allow proper onScroll event for last story element
            if(this.options.reportElementMode=='vertical'){
                this._addStubSpaceForStoryList( 2000 );    
            }else{
                this._resizeStoryTabPages();
            }
            /* var k = rdivs.length-1;
            var h = 0;
            while (k>0){
                h = h + rdivs[k].height();
                if(h)
                k--;
            } */
            
        }
        
        if(this._resultset && this._resultset.length()>0){
            
            //1. Render overview panel
            this.updateOverviewPanel(recID);
            
            //2. Loads time data for all story elements - into special layer "Whole Store Timeline"
            this.updateTimeLine(recID);
            
        }else{
            //clear 
            //this.options.storyRecordID = null;
            this.pnlOverview.html(
            '<h3 class="not-found" style="color:teal;">'
            +top.HR('There are no visible story points for the selected record (they may exist but not made public)')
            +'</h3>');
        }
        
        if(this._btn_clear_story){
            if(this.options.reportOverviewMode=='inline' && this.options.reportElementMode!='vertical'){
                this._btn_clear_story.button({icon:'ui-icon-circle-b-close', showLabel:false});
            }else{
                this._btn_clear_story.button({label:top.HR('Close'),showLabel:true,icon:null});
            }
            this._btn_clear_story.show();  
        } 
        
    },
    
    //
    // add last stub element to allow proper onScroll event for last story element
    //
    _addStubSpaceForStoryList: function( delay ){
        
        if(this._resultList.is(':visible')){
            
            if(!(delay>0)) delay = 10; 
            
            var rh = this._resultList.height();
            var rdiv = this._resultList.find('.recordDiv');
            rdiv.css('padding','20px 0px');
            if(rdiv.length>1){
                var rdiv0 = $(rdiv[0]);
                rdiv = $(rdiv[rdiv.length-1]);
                var that = this;
                setTimeout(function(){ 
                    if(rdiv0.height() < 101){
                        rdiv0.css({'min-height':'100px'});
                    }
                    var stub_height = (rdiv.height() < rh-100)? rh-100 :0;
                    
                    that._resultList.find('.div-result-list-content').find('.stub_space').remove();
                    if(stub_height>0){
                        $('<div>').addClass('stub_space').css({'min-height':stub_height+'px'})
                                .appendTo(that._resultList.find('.div-result-list-content'));
                    }
                    
                }, delay);
            }
        }
    },

    //
    // Loads Overview info (called after loading of story)
    //
    updateOverviewPanel: function(recID){

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
                infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?recID='  //mapPopup=1&
                        +recID
                        +'&db='+window.hWin.HAPI4.database;
            }
            
            //reportOverviewMode: inline | tab | header | no
            //reportElementMode: vertical | slide | tab
            
            if (!((this.options.reportOverviewMode=='no') ||
              (this.options.reportOverviewMode=='inline' && this.options.reportElementMode=='tabs')))
            { //inline, tab, header
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
                            
                            if(that.options.reportElementMode=='slide'){ //as first slide     
                                that._onNavigateStatus( -1 );    
                                that.pnlStoryReport.html(that.pnlOverview.html())
                            }else
                            if(that.options.reportElementMode=='tabs'){
                                //var tab_header = that._resultList.find( '.div-result-list-content > ul[role="tablist"]' );
                                //tab_header.css('height','38px');
                                //tab_header.find('.ui-tabs-nav li a').css('padding','5px 12px 9px 12px !important');
                                
                                /* dynamic addition does not work properly
                                var tabs = that._resultList.find('.div-result-list-content');
                                tabs.find('div.recordDiv:first').html(that.pnlOverview.html());                                

                                //add overview as a first tab
                                var tab_header = tabs.find( 'ul[role="tablist"]' );

                                $(('<li><a href="#rec_0">Overview</a></li>')).prependTo(tab_header); 

                                $('<div class="recordDiv ui-tabs-panel ui-corner-bottom ui-widget-content" recid="0" id="rec_0"'
                                    +'>').html(that.pnlOverview.html()).insertAfter(tab_header);
                                
                                tabs.find('div.recordDiv').each(function(idx, item){
                                    $(item).prop('tabindex',idx);
                                });
                                
                                
                                
                                tabs.tabs({active:0});
                                tabs.tabs( "refresh" );                                
                                */
                            }else{
                                var ele = that._resultList.find('.div-result-list-content');    
                                $('<div class="recordDiv outline_suppress expanded" recid="0" tabindex="0">')
                                    .html(that.pnlOverview.html()).prependTo(ele);
                            }
                            
                        }else if(that.options.reportOverviewMode=='header'){
                            
                            if(that._initialElementID==0){
                                that._onNavigateStatus( 0 );    
                                that._startNewStoryElement( that._resultset.getOrder()[0] );
                            }
                                
                        }else{
                            //tab
                            var h = ele2[0].scrollHeight+10;
                            if(ele2.find('div[data-recid]').length>0){
                                ele2.find('div[data-recid]').css('max-height','100%');
                            }
                        }

                        if(that._initialElementID>0){
                            that._scrollToStoryElement( that._initialElementID );
                            that._initialElementID = 0;   
                        }

                        
                        /*
                        if(that.options.reportElementMode=='vertical'){
                            var ele = that._resultList.find('.div-result-list-content');
console.log('sctop '+ele.scrollTop()+'  '+ele.height());                            
                            ele[0].scrollTop = 0;
console.log('>sctop '+ele.scrollTop());                            
                        }
                        */
                                
                    });   
                    
            }else 
            {
                if(this._initialElementID>0){
                    this._scrollToStoryElement( this._initialElementID );
                    this._initialElementID = 0;   
                }else{
                    this._onNavigateStatus( 0 );    
                    this._startNewStoryElement( this._resultset.getOrder()[0] );
                }
            }
                        
            
    },

    //
    // show all elements on map for initial state
    //        
    updateInitialMap: function( recset ){
        
        if(!this._mapping) return; //there is not associated map widget

        var that = this;
        
        var mapwidget = this._mapping.app_timemap('getMapping');
        
        if(!this._mapping_onselect){ //assign event listener
            
            this._mapping_onselect = function( rec_ids ){
                /*                            
                if(that._all_stories_id>0){
                    //initial map is loaded
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        {selection:rec_ids, source:that.element.attr('id'), 
                            search_realm:that.options.search_realm} ); //highlight in main resultset
                    that._checkForStory(rec_ids[0]); //load first story
                }else*/

                if(rec_ids.length>0){
                    //find selected record id among stories
                    var rec = that._resultset_main.getById(rec_ids[0]);
                    if(rec){
                        $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                            {selection:[rec_ids[0]], source:that.element.attr('id'), 
                                search_realm:that.options.search_realm} ); //highlight in main resultset
                        that._checkForStory(rec_ids[0]); //load first story
                    }else{
                        //find selected record id among story elements    
                        rec = that._resultset.getById(rec_ids[0]);
                        if(rec){
                            that._scrollToStoryElement(rec_ids[0]); //scroll to selected story element
                        }    
                    }
                }
                
            }
            mapwidget.options.onselect = this._mapping_onselect;
        }
        
        //clear map
        if(this._all_stories_id>0){
            mapwidget.removeLayer( this._all_stories_id );
            this._all_stories_id = 0;
        }else if(this._storylayer_id>0){
            mapwidget.removeLayer( this._storylayer_id );
            this._storylayer_id = 0;
        }else{
            this._stopAnimeAndClearMap();
        }
        
        if(recset.length()==0) return;
        
        if(recset.length()==1){
            //select the only story at once
            $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                {selection:recset.getIds(), source:that.element.attr('id'), 
                    search_realm:that.options.search_realm} );
            that._checkForStory(recset.getIds()[0]); //load certain story
            return;            
        }
        
        //Find story elements ids
        if(!$.isArray(this.options.storyFields) && typeof this.options.storyFields === 'string'){
            this.options.storyFields = this.options.storyFields.split(',');
        }
        
        //---------
        // find all story elements
        var request = {q:{ids:recset.getIds()}, detail:this.options.storyFields.join(',')};

        window.hWin.HAPI4.RecordMgr.search(request,
            function(response) {
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    var storyIDs = [];
                    var storiesByRecord = {};
                    for (var recID in response.data.records){
                        if(recID>0){
                            var details = response.data.records[recID]['d'];
                            storiesByRecord[recID] = [];
                            for(var dty_ID in details){
                                if(dty_ID>0){
                                    storyIDs = storyIDs.concat(details[dty_ID]);
                                    storiesByRecord[recID] = storiesByRecord[recID].concat(details[dty_ID]);
                                }
                            }
                        }
                    }//for
                    
                    //find filtered stories
                    var query = [{ids:storyIDs}];

                    if( !window.hWin.HEURIST4.util.isempty(that.options.reportOverviewMapFilter)){
                        query = window.hWin.HEURIST4.util.mergeTwoHeuristQueries( query, that.options.reportOverviewMapFilter );    
                    }
                    
        
                    var server_request = {
                                    q: query, 
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
//console.log(response['timeline']);
                            //REPLACE rec id and title to main result set
                            function __findMainId(storyID){
                                var rec = null;
                                recset.each2(function(recID, record){
                                    if(recID>0 && storiesByRecord[recID]){
                                        var idx = window.hWin.HEURIST4.util.findArrayIndex(storyID, storiesByRecord[recID]);
                                        if(idx>=0){
                                            rec = record;
                                            return false;
                                        }
                                    }
                                });
                                return rec;
                            }
                            
                            if( !window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) ){
                                geojson_data = null;
                            }else{
                                
                                for(var i=0; i<geojson_data.length; i++){
                                    var storyID = geojson_data[i].id
                                    var record  = __findMainId(storyID);
                                    if(record){
                                        geojson_data[i].id = record['rec_ID'];
                                        geojson_data[i]['properties'].rec_ID = record['rec_ID']; 
                                        geojson_data[i]['properties'].rec_RecTypeID = record['rec_RecTypeID']; 
                                        geojson_data[i]['properties'].rec_Title = record['rec_Title']; 
                                    }
                                }
                            }

                            if(response['timeline']){
                                var aused = [];
                                for(var i=0; i<response['timeline'].length; i++){
                                    var storyID = response['timeline'][i]['rec_ID'];
                                    //find original resulet set it
                                    var record  = __findMainId(storyID);
                                    if(record && aused.indexOf(record['rec_ID'])<0){
                                        aused.push(record['rec_ID']);
                                        response['timeline'][i]['rec_ID'] = record['rec_ID'];
                                        response['timeline'][i]['rec_RecTypeID'] = record['rec_RecTypeID'];
                                        response['timeline'][i]['rec_Title'] = record['rec_Title'];
                                    }
                                }
                            }


                            
                            that._all_stories_id = mapwidget.addGeoJson(
                                {geojson_data: geojson_data,
                                 timeline_data: response['timeline'],
                                    //layer_style: layer_style,
                                    //popup_template: layer_popup_template,
                                 dataset_name: 'All Stories',
                                 preserveViewport: false });
                        });                    
                    
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
            });
        //----------
        
        /*
        var field_ids = 'linkedfrom:'+this.options.storyFields.join(',');
        var query = {};
        query[field_ids] = {ids:recset.getIds()};
        query = [query];
        

        if( !window.hWin.HEURIST4.util.isempty(that.options.reportOverviewMapFilter)){
            query = window.hWin.HEURIST4.util.mergeTwoHeuristQueries( query, that.options.reportOverviewMapFilter );    
        }

//console.log(query);
//console.log(recset);

            */
    },


    //
    // update Whole store timeline
    //
    updateTimeLine: function(recID){
        
        var that = this;
        
        if(this.options.storyRecordID != recID) return; //story already changed to different one
        
        if(!this._mapping) return; //there is not associated map widget
        
        if(that._cache_story_geo[that.options.storyRecordID]=='no data'){
            //no time data for this story
            
        }else if(that._cache_story_geo[that.options.storyRecordID] 
                || that._cache_story_time[that.options.storyRecordID]) 
        {
            // loads from cache
            
            if(!this._mapping.app_timemap('isMapInited')){
                   
                /*    
                this._mapping.app_timemap('option','onMapInit', function(){
                    that.updateTimeLine(recID);
                });
                */
                return;
            }
            
            //update timeline
            var mapwidget = this._mapping.app_timemap('getMapping');
            mapwidget.isMarkerClusterEnabled = false;
            
            this._storylayer_id = mapwidget.addGeoJson(
                {geojson_data: that._currentElementID>0?null:that._cache_story_geo[that.options.storyRecordID], //story element is loaded already
                 timeline_data: that._cache_story_time[that.options.storyRecordID],
                    //popup_template: layer_popup_template,
                    layer_style:{"stroke":"1","color":"#00009b","fill":"1","fillColor":"#0000fa", "fillOpacity":"0.8"},
                    
                 selectable: false,
                 dataset_name: 'Story Timeline',
                 preserveViewport: false });
                
            //
            //     
            if(this._currentTime!=null){

                var start0 = that._currentTime[0];
                var end0 = that._currentTime[3] ?that._currentTime[3] :start0;
                
                $.each(that._cache_story_time[that.options.storyRecordID],function(i,item){
               
                    if(item.when && item.when[0]){
                    
                        var start = item.when[0][0];
                        var end = item.when[0][3] ?item.when[0][3] :start;
                    
                        //intersection
                        var res = false;
                        if(start == end){
                            res = (start>=start0 && start<=end0);
                        }else{
                            res = (start==start0) || 
                                (start > start0 ? start <= end0 : start0 <= end);
                        }                    
                        
                        if(res){
                            that._initialElementID = item.rec_ID;
                            //open story element
                            //setTimeout(function(){that._scrollToStoryElement( item.rec_ID )}, 1000);
                            return false;                        
                        }
                    }
                });
                
            }     
                 
            
        }else{
            
            var server_request = {
                            q: {ids: this._resultset.getIds()}, //list of story elements/events
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
                        that._cache_story_geo[recID] = geojson_data;
                    }else{
                        that._cache_story_geo[recID] = null;
                    }
                    
                    if(response['timeline']){
                        that._cache_story_time[recID] = response['timeline']; //timeline data
                    }else {
                        that._cache_story_time[recID] = null;
                    }   
                    
                    if(!(that._cache_story_geo[recID] 
                        || that._cache_story_time[recID])){
                        that._cache_story_geo[recID] = 'no data';     
                    }
                    
                    that.updateTimeLine(recID);
                });
        }
    },

    //
    // 1. Loads story for slide (if reportElementMode=='slide')
    // 2. Executes animation on map
    // recID - story element
    //
    _startNewStoryElement: function(recID){

        if(this._currentElementID != recID){
          
            if(this._storylayer_id>0){
                var mapwidget = this._mapping.app_timemap('getMapping');
                mapwidget.setLayerVisibility(this._storylayer_id, false);
                //mapwidget.removeLayer(this._storylayer_id, true);
                /*
                var lyr = mapwidget.all_layers[this._storylayer_id];        
                if(lyr._map!=null){
                    lyr.remove(); //remove from map only
                }
                */
            }
            
            
            this._stopAnimeAndClearMap();

            this._currentElementID = recID;
            
            if(this.options.reportElementMode=='slide'){   //one by one   

                
                var infoURL;
                var isSmarty = false;
                
                if( typeof this.options.reportElement === 'string' 
                                && this.options.reportElement.substr(-4)=='.tpl' ){
                
                    infoURL = window.hWin.HAPI4.baseURL + 'viewers/smarty/showReps.php?snippet=1&publish=1&debug=0&q=ids:'
                            + recID 
                            + '&db='+window.hWin.HAPI4.database+'&template='
                            + encodeURIComponent(this.options.reportElement);
                    isSmarty = true;
                }else{
                    infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?recID='  //mapPopup=1&
                            +recID
                            +'&db='+window.hWin.HAPI4.database;
                }

                
                var that = this;

                function __load_content(){
                    that.pnlStoryReport.addClass('loading').css({'overflow-y':'auto'})
                        .load(infoURL, function(){ 
                            
                            var ele2 = $(this);
                            ele2.removeClass('loading').css('min-height','200px');//.height('auto');    

                            if(ele2.find('div[data-recid]').length>0){ //for standard view
                                ele2.find('div[data-recid]')[0].style = null;
                            }
                        });
                }
                
                if(this.options.reportElementSlideEffect && 
                    this.options.reportElementSlideEffect!='none' &&
                    !this.pnlStoryReport.is(':empty'))
                {
                    this.pnlStoryReport.effect( this.options.reportElementSlideEffect, {}, 1000, function(){
                            that.pnlStoryReport.empty().show();
                            __load_content();
                    } );
                }else{
                    __load_content();
                }
                
                
            }
            //else
            //if(this.options.reportElementMode=='tab'){
            else { //if(this.options.reportElementMode=='vertical'){    
            
                if(this.options.reportElementDistinct=='highlight'){
                    this._resultList.find('.recordDiv').removeClass('selected');
                    this._resultList.find('.recordDiv[recid='+recID+']').addClass('selected');
                }else if(this.options.reportElementDistinct=='unveil'){
                    
                    $.each(this._resultList.find('.recordDiv'),function(i,item){
                        if($(item).attr('recid')==recID){
                            $(item).find('.veiled').remove();   
                        }else if($(item).find('.veiled').length==0){
                            $('<div>').addClass('veiled').appendTo($(item));
                        }
                    });
                }
                
            }
            
            if(this._mapping && this._mapping.length>0){
                //this._animateStoryElement_A(recID);
                if(recID==0 || recID==this.options.storyRecordID){
                    //zoom for entire story
                    //console.log('zoom for entire story');
                    
                    if(this._mapping){
                        var mapwidget = this._mapping.app_timemap('getMapping');
                        mapwidget.setLayerVisibility(this._storylayer_id, true);
                        mapwidget.zoomToLayer(this._storylayer_id );
                    }
                    
                }else{
                    this._animateStoryElement_B(recID);    
                }
            

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
                //no geodata, zoom to story element on timeline
                var mapwidget = that._mapping.app_timemap('getMapping');
                mapwidget.vistimeline.timeline('zoomToSelection', [recID]); //select and zoom 
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
                            //no geodata, zoom to story element on timeline
                            var mapwidget = that._mapping.app_timemap('getMapping');
                            mapwidget.vistimeline.timeline('zoomToSelection', [recID]); //select and zoom 
                            return;
                        }

//console.log(that._cache_story_places[recID]);

                        var qq = {ids:that._cache_story_places[recID]['places']};
                        
                        if(that.options.reportElementMapMode=='filtered'){ //additional filter for places 
                            qq = window.hWin.HEURIST4.util.mergeTwoHeuristQueries( qq, that.options.reportElementMapFilter );
                        }
                        


                        // 2. retrieve all places from server side as geojson
                        var server_request = {
                            q: qq,
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
                                    if(that.options.reportElementMapMode!='all'){
                                        that._createPointLinks(recID);
                                    }
                                    
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
        
        if(this._currentElementID != recID) return; //user can switch to different story
        
        var that = this;
        
//console.log(that._cache_story_places[recID]['geojson']);        
        
        var mapwidget = that._mapping.app_timemap('getMapping');
        
        if(window.hWin.HEURIST4.util.isnull(this._L)) this._L = mapwidget.getLeaflet();
        
        let DT_STORY_ANIMATION = $Db.getLocalID('dty', '2-1090');
        var record = this._resultset.getRecord(recID);
        var anime = this._resultset.fld(record, DT_STORY_ANIMATION);

        let default_story_element_style = 
        window.hWin.HEURIST4.util.isempty(anime)
                ?{"stroke":"1","color":"#00009b","fill":"1","fillColor":"#0000fa", "fillOpacity":"0.8"} //blue
                :null;
        
        mapwidget.isMarkerClusterEnabled = false;
        this._nativelayer_id = mapwidget.addGeoJson(
            {geojson_data: that._cache_story_places[this._currentElementID]['geojson'],
                timeline_data: null, //that._cache_story_places[this._currentElementID]['timeline'],
                layer_style: default_story_element_style,
                //popup_template: layer_popup_template,
                dataset_name: 'Story Map',
                selectable: false,
                preserveViewport: true });
//console.log('ADDED '+this._nativelayer_id);                
        //possible sequences
        // gain: begin-visible, trans fade in, end-visible
        // loses: trans-visible, trans fade out
        // path grow: fade by 1 or group
        // path move: fade in and out by 1 or group
        
        // json to describe animation
        // [{scope:begin|trans|end|all, range:0~n, actions:[{ action: duration: , steps:},..]},....] 

//console.log('Animation '+anime);        

        /*  examples:     
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
        
        
        //zoom to story element on timeline
        // @todo  It would be brilliant if 
        // the current time were calculated as a proportion of the change in time between start and end of the Story Element, perhaps in increments of say 1/20t
        mapwidget.vistimeline.timeline('zoomToSelection', [recID]); //select and zoom 
        
        //by default first action is fly to extent of story element
        this.actionBounds(recID, [mapwidget.all_layers[this._nativelayer_id]], 'fly' );
        
        if(!window.hWin.HEURIST4.util.isempty(anime)){
    
            anime = window.hWin.HEURIST4.util.isJSON(anime);
            if(anime){        

                function __startNewAnimation(){

                    if(that._terminateAnimation!==false){ 
                        //do not start new animation - waiting for termination of previous animation
console.log('wait for stopping of previous animation');
                        if(that._currentElementID==recID){
                            setTimeout(__startNewAnimation, 500);                        
                        }else{
                            that._terminateAnimation = false;
                        }
                            
                    }else if(that._currentElementID==recID){
                        //start animation/actions
                        that._animateStoryElement_B_step3(recID, anime);
                    }
                }
                
                setTimeout(__startNewAnimation, 1700); //wait for flyto stop
                
            }
        }
    },
    
    //
    // performs animation for current range
    //
    _animateStoryElement_B_step3: function(recID, aSteps, step_idx, aRanges, range_idx, aActions, action_idx ){        

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
                this._animateStoryElement_B_step3(recID, aSteps, step_idx );   
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
                this._animateStoryElement_B_step3(recID, aSteps, step_idx );   
                return;
            }

        }//search for ranges for current step
        
        //3 execute action(s)    
        if(action_idx>=aActions.length){ 
            //all actions are executed
            action_idx = 0;
            this._animateStoryElement_B_step3(recID, aSteps, step_idx, aRanges, range_idx+1, aActions, action_idx );
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
                    that.actionFadeIn(recID, mapwidget.nativemap, layers, 0, 1, 0.05, action['duration'], action['delay']);
               break;
               case 'fade_out':
                    that.actionFadeIn(recID, mapwidget.nativemap, layers, 1, 0, -0.05, action['duration'], action['delay']);
               break;
               case 'fade_in_out':
                    that.actionFadeIn(recID, mapwidget.nativemap, layers, 0, 1, 0.05, action['duration'], action['delay'], true);
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
                    that.actionBounds(recID, layers, action['action'], action['duration']);       
               break;
               case 'blink':

                    that.actionBlink(recID, layers, action['steps'], action['duration']);
               
               break;
               case 'gradient':
                    //change color from one color to another
                    if(!action['from']) action['from'] = '#ff0000';
                    if(!action['to']) action['to'] = '#00ff00';
                    that.actionGradient(recID, layers, action['from'], action['to'], action['steps'], action['duration']);
               break;
               case 'style':

                    that.actionSetStyle(recID, layers, action['style'], action['duration']);
               
               break;
            }
        });
        
        promise
        .then(function(res){
//console.log('after anim range_idx='+range_idx+'  action_idx='+action_idx);
            that._animationResolve = null;
            if(that._terminateAnimation===true || that._terminateAnimation==recID){
                that._terminateAnimation = false;
            }else{
                //next step
                that._animateStoryElement_B_step3(recID, aSteps, step_idx, aRanges, range_idx, aActions, action_idx+1 );    
            }
        },
        function(res){ //termination
            that._terminateAnimation = false;
            that._animationResolve = null;
            
        });     
        
    },
    
    //
    // returns layers for given record ids
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
    actionBounds: function(recID, layers, mode, duration){
        
        var mapwidget = this._mapping.app_timemap('getMapping');
        var useRuler = (layers.length==1), bounds = [];
        
        $.each(layers, function(i, layer){
            var bnd = mapwidget.getLayerBounds(layer, useRuler);
            bounds.push( bnd );    
        });

        //.nativemap
        var bounds = mapwidget._mergeBounds(bounds);
        
        //
        if(mode=='center'){
            mapwidget.nativemap.panTo(bounds.getCenter());    //setView
        }else{
            if(!(duration>0)){
                duration = this.options.zoomAnimationTime>=0
                            ?this.options.zoomAnimationTime
                            :5000;
                if(duration==0 && mode=='fly') mode = 'zoom'; //if duration is zero mode is "zoom"
            } 
            if(mode == 'fly'){
                mode = {animate:true, duration:duration/1000};
            }else{
                mode = false;
            }
            
            mapwidget.zoomToBounds(bounds, mode);
        }        
        
        
        if($.isFunction(this._animationResolve)){
            if(duration==0){
                this._animationResolve();
            }else{
                var that = this;
                setTimeout(function(){
                        if(that._terminateAnimation===true || that._terminateAnimation==recID){
    console.log('animation terminated actionBounds');                        
                            if($.isFunction(that._animationReject)) that._animationReject();
                        }else{
                            if ($.isFunction(that._animationResolve)) that._animationResolve();
                        }                
                    }, duration);                        
            }
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
    actionFadeIn: function(recID, nativemap, layers, startOpacity, finalOpacity, opacityStep, duration, show_delay, need_reverce) 
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
                    
                    if(that._terminateAnimation===true || that._terminateAnimation==recID){
console.log('animation terminated actionFadeIn');                        
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
    actionGradient: function(recID, layers, startColour, endColour, steps, duration){

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
                    
                    if(that._terminateAnimation===true || that._terminateAnimation==recID){
console.log('animation terminated actionGradient');                        
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
    
    //
    //
    //
    actionBlink: function(recID, layers, steps, duration){
        
        if(!duration) duration = 1000;
        if(!steps) steps = 10;
        
        var that = this;
        var delay = duration/steps;
        var count = 0;
        var mapwidget = this._mapping.app_timemap('getMapping');
        var is_visible = [];
        var is_terminated = false;

        var interval = window.setInterval(function() {
            
            $.each(layers, function(i, lyr){
                
                if(count==0){
                    //keep initial visibility
                    is_visible.push((lyr._map!=null));
                }
            
                if(that._terminateAnimation===true || that._terminateAnimation==recID){
console.log('animation terminated actionBlink');                        
                    clearInterval(interval);
                    interval = 0;
                    if($.isFunction(that._animationReject)) that._animationReject();
                    is_terminated = true;
                    return false;
                }else 
                if(lyr._map==null){
                    lyr.addTo( mapwidget.nativemap );                      
                }else{
                    lyr.remove();
                }
                
            });
            
            count++;
            if(count>steps){
                clearInterval(interval);
                interval = 0;
                if($.isFunction(that._animationResolve)) that._animationResolve();    
            }
        },delay);
        
        //restore initial visibility
        if(!is_terminated){
console.log('restore initial visibility');                        
            $.each(layers, function(i, lyr){
                if(is_visible[i]){
                    if(lyr._map==null) lyr.addTo( mapwidget.nativemap );                    
                }else{
                    lyr.remove()
                }
                
            });
        }
        
    },
    
    //
    //
    //
    actionSetStyle: function(recID, layers, newStyle, delay){

        if(newStyle){
        
        if(!delay) delay = 500;

            var that = this;
            var mapwidget = this._mapping.app_timemap('getMapping');
            var top_layer = mapwidget.all_layers[this._nativelayer_id];
            
            setTimeout(function(){

                $.each(layers, function(i, lyr){
                    mapwidget.applyStyleForLayer(top_layer, lyr, newStyle);
                });
                
                if(that._terminateAnimation===true || that._terminateAnimation==recID){
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
    // recID - 
    //
    _createPointLinks: function( recID ){

        // Begin '2-134', Transition '1414-1090', End '2-864'
        let DT_BEGIN_PLACES = $Db.getLocalID('dty', '2-134');
        let DT_BEGIN_PLACES2 = $Db.getLocalID('dty', '1414-1092');
        let DT_END_PLACES = $Db.getLocalID('dty', '2-864');
        let DT_TRAN_PLACES = $Db.getLocalID('dty', '1414-1090');
        
        //var recID = this._currentElementID;
        
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
