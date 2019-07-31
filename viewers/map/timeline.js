/**
* methods for timeline 
* timelineRefresh - reloads timeline component
* setSelection
* 
* _timelineZoomToAll
* _timelineZoomToRange
* _timelineApplyRangeOnMap - filter items on map based on timeline range
* _timelineInitToolbar - button initialization (called once on first init)
* _timelineApplyLabelSettings
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


$.widget( "heurist.timeline", {

    // default options
    options: {
        
        element_timeline: '#timeline',
        
        // callbacks
        onselect: null,
        onfilter:null
        
    }, 
    
    timeline_ele: null, //vis timeline element
    vis_timeline: null,
    
    vis_timeline_range: null,
    vis_timeline_label_mode: 0, //default full label
    isApplyTimelineFilter: false,
    stack_setting: true,
        
    _keepLastTimeLineRange: null,
    selected_rec_ids:[],

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        // prevent double click to select text
        .disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
        
            this.element.addClass('ui-widget');
        
            this.timeline_ele = document.getElementById( this.options.element_timeline );
            // Configuration for the Timeline
            var options = {dataAttributes: ['id'],
                           orientation:'both', //scale on top and bottom
                           selectable:true, multiselect:true,
                           zoomMax:31536000000*500000,
                           stack:this.stack_setting,  //how to display items: staked or in line 
                           margin:1,
                           minHeight: $(this.timeline_ele).height(),
                           order: function(a, b){
                               return a.start<b.start?-1:1;
                           }
                           };
                        //31536000000 - year
            // Create a Timeline
            this.vis_timeline = new vis.Timeline(this.timeline_ele, null, options);
        
        
            var that = this;
        
            this.vis_timeline.on('rangechanged', function(params){
                    that._timelineApplyRangeOnMap(params);  
            });
            //on select listener
            this.vis_timeline.on('select', function(params){
                
                var selection = params.items;
                that.selected_rec_ids = [];
                if(selection && selection.length>0){

                    var e = params.event;
                                                //div.vis-item.vis-dot.vis-selected.vis-readonly
                    e.cancelBubble = true;
                    if (e.stopPropagation) e.stopPropagation();
                    e.preventDefault();
                    if($(e.target).hasClass('vis-item vis-dot vis-selected')) return;

                    //remove dataset prefixes - extract record ID
                    $.each(selection, function(idx, itemid){
                        var k = itemid.indexOf('-');
                        if(k>0){
                            itemid = itemid.substring(k+1);
                            k = itemid.indexOf('-');
                            if(k>0){
                                itemid = itemid.substring(0,k);
                            }
                        }
                        that.selected_rec_ids.push( itemid ); //record id
                    });
                    
                    if($.isFunction(that.options.onselect)){ //trigger global event
                        that.options.onselect.call(that, that.selected_rec_ids);    
                    }
    
                    //$( document ).bubble( "option", "content", "" );
                    //_showSelection(true, true, null); //show selection on map
                    //if(_onSelectEventListener)_onSelectEventListener.call(that, selection); //trigger global selection event
                }
            });
        
        
            this._timelineInitToolbar();
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        //this.select_rectype.remove();
    },
    
    //
    //
    //
    setSelection: function(selected){
        this.selected_rec_ids = selected;
        var selection_vis = [];
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.selected_rec_ids) && this.vis_timeline.itemsData){
            
            this.vis_timeline.itemsData.forEach(function (itemData) {
                if(window.hWin.HEURIST4.util.findArrayIndex(itemData.recID, selected)>=0){
                    selection_vis.push( itemData.id );
                }
            });
        
        }    
        this.vis_timeline.setSelection(selection_vis);
    },

    //
    //
    //    
    timelineUpdateGroupLabel: function(timeline_group){
      
        var grp = this.vis_timeline.itemSet.groups[timeline_group.id];
        grp.setData(timeline_group);
        //grp.dom.inner
        
    },
    
    //
    // timelineRefresh (former loadVisTimeline) - reloads timeline component with new data
    //
    timelineRefresh: function(timeline_data, timeline_groups){

        /*
        var timeline_data = [],
            timeline_groups = [];

        $.each(all_mapdata, function(dataset_id, mapdata){

            var cnt = mapdata.timeline.items.length;
            if(mapdata.visible && cnt>0){

                timeline_data = timeline_data.concat( mapdata.timeline.items );
                timeline_groups.push({ id:dataset_id, content: mapdata.title});

            }
        });
        */

        if(timeline_data){
            
            var tdata = [];
            if($.isPlainObject(timeline_data)){

                for(var idx in timeline_data) {
                    if(timeline_data.hasOwnProperty(idx)){
                        tdata = tdata.concat(timeline_data[idx]);
                    }
                }
                
            }else{
                tdata = timeline_data;
            }

            var groups = new vis.DataSet( timeline_groups );
            var items = new vis.DataSet( tdata ); //options.items );
            
            
            this.vis_timeline_range = null; //reset
            
            var timeline_content = $(this.timeline_ele).find('.vis-itemset');
            timeline_content.hide();
            
            this.vis_timeline.itemSet.setOptions({groupOrder:function (a, b) {
                    var av = a['content'];
                    var bv = b['content'];
                    if(av=='Current query') return -1;
                    if(bv=='Current query') return 1;
                    return av > bv ? 1 : av < bv ? -1 : 0;
                }});
            this.vis_timeline.setGroups(groups);
            this.vis_timeline.setItems(items);
            
            
            //apply label settings
            this._timelineApplyLabelSettings(this.vis_timeline_label_mode, 0);
            
            if(this.isApplyTimelineFilter) this._timelineApplyRangeOnMap(null);
            window.hWin.HAPI4.save_pref('mapTimelineFilter', this.isApplyTimelineFilter?1:0);
            $('#lbl_timeline_filter').html('Time filter '+(this.isApplyTimelineFilter?'on':'off'));
            
            
            this._timelineZoomToAll();
            timeline_content.show();
        }
        
    },
    
    //
    //
    //
    _timelineZoomToAll: function(){
        
            if(this.vis_timeline_range==null){
                this.vis_timeline_range = this.vis_timeline.getDataRangeHeurist();
            }
            //var range = vis_timeline.getItemRange(); //@todo calculate once
            this._timelineZoomToRange( this.vis_timeline_range );
        
    },
    
    //
    //
    //
    _timelineZoomToRange: function( range ){
        
            if(!(range && range.min  && range.max && this.vis_timeline)) return;
        
            var min = this.vis_timeline.getDate(range.min), // new Date(range.min).getTime(),
                max = this.vis_timeline.getDate(range.max); //new Date(range.max).getTime();
            var delta = 0;

            if(isNaN(min) || isNaN(max) ) return;
            
            
            if(range['nofit']==undefined){
            var interval = max-min;
            var YEAR = 31536000000;
            var DAY = 86400000;

            var yearmax = (new Date(range.max)).getFullYear();
            var dt = range['omax'];
            var dta = [];
            if(dt!=undefined){
                dta = dt.split('-');
                if(dta.length>0)
                    yearmax = Number(dta[0]);
            }


            if(interval < 1000){ //single date
                    //detect detalization
                    if(dta.length>2){
                        interval = DAY;
                    }else if(dta.length>1){
                        interval = DAY*90;
                    }else{
                        interval = YEAR;
                    }
            }

            if(interval < DAY){  // day  - zoom to 4 days
                delta = DAY*2;
            }else if(interval < DAY*30) {
                delta = DAY*10;
            }else if(interval < DAY*90) { // month - zoom to year
                delta = YEAR/2;
            }else if(interval < YEAR) { // year - zoom to 5
                delta = YEAR*2;
            }else if(interval < YEAR*2){
                delta = YEAR*5;
            }else if(interval < YEAR*50){

                //zoom depend on epoch
                //var yearmax = (new Date(range.max)).getFullYear();
                if(yearmax<0){
                    delta = interval*1.5;
                }else if(yearmax<1500){
                    delta = interval;
                }else{
                    delta = interval*0.5;
                }

            }else  if(interval < YEAR*2000){
                delta = interval*0.1;
            }else{
                delta = interval*0.05;
            }

            }
            
            //this.range.setRange(range.min, range.max, animation);
            this.vis_timeline.setWindow({
                start: min-delta,
                end: max+delta
            });
        
    },
    
    
    
    // get unix timestamp from vis
    // item - record id or timelime item, field - start|end
    _getUnixTs: function(item, field, ds){

        var item_id = 0;
        if(item && item['id']){
            item_id = item['id'];
        }else{
            item_id = item;
        }
        if(item_id){

            if(!ds) ds = this.vis_timeline.itemsData;

            var type = {};
            type[field] = 'Moment';
            var val = ds.get(item_id,{fields: [field], type: type });

            if(val!=null && val[field] && val[field]._isAMomentObject){
                //return val[field].toDate().getTime(); //unix
                return val[field].unix()*1000;
            }
        }
        return NaN;
    },

    /*
    _getItemField(item, field){
      
        var val = null;
        var item_id = 0;
        if(item && item['id']){
            item_id = item['id'];
        }else{
            item_id = item;
        }
        if(item_id){
            var ds = this.vis_timeline.itemsData;
            val = ds.get(item_id,{fields: [field], type: type });
        }  
        return val;     
    },*/

    // filter items on map based on timeline range 
    _timelineApplyRangeOnMap: function(params)
    {
        var that = this;
        
        if(params==null){
            params = this._keepLastTimeLineRange;
        }else{
            this._keepLastTimeLineRange = params;    
        }
        
        
        if($.isFunction(this.options.onfilter)){ //trigger global event
        
            if (!this.isApplyTimelineFilter || !this.vis_timeline.itemsData || !params) return;
            
            //console.log(params);
            //console.log(new Date(params.start_stamp) + '  ' + new Date(params.end_stamp))
            //loop by timeline datasets
            
            //if fit range
            var items_visible = [], items_hidden=[], that = this;
            
            this.vis_timeline.itemsData.forEach(function (itemData) {
                        
                var start = that._getUnixTs(itemData, 'start');
                var end = 'end' in itemData ? that._getUnixTs(itemData, 'end') :start;
                //var start = itemData.start.valueOf();
                //var end = 'end' in itemData ? itemData.end.valueOf() : itemData.start.valueOf();            
                //start = (new Date(start)).getTime();
                //end = (new Date(end)).getTime();
                
                //intersection
                var res = false;
                if(start == end){
                    res = (start>=params.start_stamp && start<=params.end_stamp);
                }else{
                    res = (start==params.start_stamp) || 
                        (start > params.start_stamp ? start <= params.end_stamp : params.start_stamp <= end);
                }
                
                //find record among map items
                if (res) {
                    items_visible.push(itemData.recID); // do not use id but item.id, id itself is stringified
                } else {
                    items_hidden.push(itemData.recID);
                }

            });
            
            if(items_visible.length>0 || items_hidden.length>0){
                this.options.onfilter.call(this, items_visible, items_hidden);    
            }
            
        }
    },
    
    // button initialization (called once on first init)
    _timelineInitToolbar: function(){
        
        var that = this;
        /**
         * Zoom the timeline a given percentage in or out
         * @param {Number} percentage   For example 0.1 (zoom out) or -0.1 (zoom in)
         */
        function __timelineZoom (percentage) {
            var range = that.vis_timeline.getWindow();
            var interval = range.end - range.start;

            that.vis_timeline.setWindow({
                start: range.start.valueOf() - interval * percentage,
                end:   range.end.valueOf()   + interval * percentage
            });

    //console.log('set2: '+(new Date(range.start.valueOf() - interval * percentage))+' '+(new Date(range.end.valueOf()   + interval * percentage)));
        }

        function __timelineZoomToSelection(){

                var sels = that.vis_timeline.getSelection();
                if(sels && sels['length']>0){
                       var range = that.vis_timeline.getDataRangeHeurist(new vis.DataSet(that.vis_timeline.itemsData.get(sels)));
                       that._timelineZoomToRange(range);
                }
        }

        function __timelineMoveToLeft(){

            var range2 = that.vis_timeline.getWindow();
            var interval = range2.end - range2.start;

            if(that.vis_timeline_range==null){
                    that.vis_timeline_range = that.vis_timeline.getDataRangeHeurist();
            }

            that._timelineZoomToRange({min:that.vis_timeline_range.min.getTime(), 
                                        max:that.vis_timeline_range.min.getTime()+interval, nofit:true});
        }

        function __timelineMoveToRight(){

            var range2 = that.vis_timeline.getWindow();
            var interval = range2.end - range2.start;
            var delta = interval*0.1;
            if(that.vis_timeline_range==null){
                    that.vis_timeline_range = that.vis_timeline.getDataRangeHeurist();
            }

            that._timelineZoomToRange({min:that.vis_timeline_range.max.getTime()-interval+delta,
                                         max:that.vis_timeline_range.max.getTime()+delta, nofit:true});
        }

        //not used
        function __timelineShowLabels(){
            if($("#btn_timeline_labels").is(':checked')){
                $(that.timeline_ele).find(".vis-item-content").find("span").show();
            }else{
                $(that.timeline_ele).find(".vis-item-content").find("span").hide();
            }
        }
        
        function __timelineEditProperties(){
            
            var $dlg_edit_layer = $('#timeline-edit-dialog').dialog({
                width: 450,
                modal: true,
                resizable: false,
                title: window.hWin.HR('Timeline options'),
                buttons: [
                    {text:window.hWin.HR('Apply'), click: function(){
                        
                        var mode = $( this ).find('input[type="radio"][name="time-label"]:checked').val();
                        
                        that.vis_timeline_label_mode = mode;
                        var spinner = $(that.element).find("#timeline_spinner");
                        if(mode==2){
                            spinner.show();
                        }else{
                            spinner.hide();
                        }
                        
                        var labelpos = $( this ).find('input[type="radio"][name="time-label-pos"]:checked').val();

                        that.stack_setting = ($( this ).find('input[type="radio"][name="time-label-stack"]:checked').val()==1);

                        that._timelineApplyLabelSettings(mode, labelpos);                
                        that.vis_timeline.redraw();    
                        
                        /*
                        var stack_mode = $( this ).find('input[type="radio"][name="time-label-stack"]:checked').val()==1;
                        if(that.stack_setting != (stack_mode==1)){
                            that.stack_setting = (stack_mode==1);
                            that.vis_timeline.setOptions({'stack':that.stack_setting});
                        }else{
                            that.vis_timeline.redraw();    
                        }*/
                        
                        var newval = $( this ).find('input[type="checkbox"][name="time-filter-map"]').is(':checked');
                        
                        if(that.isApplyTimelineFilter !== newval){
                            that.isApplyTimelineFilter = newval;
                            if(newval){
                                that._timelineApplyRangeOnMap( null );    
                            }else if($.isFunction(that.options.onfilter)){
                                //set map elements visible
                                that.options.onfilter.call(this, true);
                            }
                        }
                        window.hWin.HAPI4.save_pref('mapTimelineFilter', that.isApplyTimelineFilter?1:0);
                        $(that.element).find('#lbl_timeline_filter')
                                .html('Time filter '+(that.isApplyTimelineFilter?'on':'off'));
                        
                        $( this ).dialog( "close" );
                    }},
                    {text:window.hWin.HR('Cancel'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]                
            });
            
        }
        
        var toolbar = $(this.element).find("#timeline_toolbar").css({'font-size':'0.8em', zIndex:3});

        $("<button>").button({icons: {
            primary: "ui-icon-circle-plus"
            },text:false, label:window.hWin.HR("Zoom In")})
            .click(function(){ __timelineZoom(-0.25); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-circle-minus"
            },text:false, label:window.hWin.HR("Zoom Out")})
            .click(function(){ __timelineZoom(0.5); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-arrowthick-2-e-w"
            },text:false, label:window.hWin.HR("Zoom to All")})
            .click(function(){ that._timelineZoomToAll(); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-arrowthickstop-1-s"
            },text:false, label:window.hWin.HR("Zoom to selection")})
            .click(function(){ __timelineZoomToSelection(); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-arrowthickstop-1-w"
            },text:false, label:window.hWin.HR("Move to Start")})
            .click(function(){ __timelineMoveToLeft(); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-arrowthickstop-1-e"
            },text:false, label:window.hWin.HR("Move to End")})
            .click(function(){ __timelineMoveToRight(); })
            .appendTo(toolbar);
        $("<button>").button({icons: {
            primary: "ui-icon-gear"
            },text:false, label:window.hWin.HR("Timeline options")})
            .click(function(){ __timelineEditProperties(); })
            .appendTo(toolbar);
        $("<label>").attr('id','lbl_timeline_filter')
            .text('').css('font-style','italic').appendTo(toolbar);
            
        var spinner = $( "<input>", {id:"timeline_spinner", value:10} ).appendTo(toolbar);
        spinner.spinner({
              value: 10,
              spin: function( event, ui ) {
                if ( ui.value > 100 ) {
                  $( this ).spinner( "value", 100 );
                  return false;
                } else if ( ui.value < 5 ) {
                  $( this ).spinner( "value", 5 );
                  return false;
                } else {
                    $(that.timeline_ele).find(".vis-item-content").css({'width': ui.value+'em'});

                }
              }
            }).css('width','2em').hide();//rre.hide();

            
            
        this.isApplyTimelineFilter = (window.hWin.HAPI4.get_prefs_def('mapTimelineFilter', 1)==1);   
            
        if(this.isApplyTimelineFilter){    
            $('#timeline-edit-dialog').find('input[type="checkbox"][name="time-filter-map"]').prop('checked',true);
        }                    
    },

    
    //
    // 0 - full, 1 - truncate, 2- fixed width, 3 - hide
    //
    _timelineApplyLabelSettings: function(mode, labelpos){
        
                var contents = $(this.timeline_ele).find(".vis-item-content");
                var spinner = $(this.element).find("#timeline_spinner");

                if(mode==1){ //truncate
                    $(this.timeline_ele).find('div .vis-item-overflow').css('overflow','hidden');
                    contents.css({'width': '100%'});
                }else{
                    $(this.timeline_ele).find('div .vis-item-overflow').css('overflow','visible');
                    if(mode==0){  //full length
                        //$.each(contents, function(i,item){item.style.width = 'auto';});//.css({'width':''});
                        contents.css({'width': 'auto'});
                    }else if(mode==2){  //specific length 
                        contents.css({'width': spinner.spinner('value')+'em'});
                    }else{
                        contents.css({'width': '16px'});
                    }                    
                }
                
                if(labelpos==2){ //above
                    $(this.timeline_ele).find(".vis-item-bbox").css('top','10px');
                }else{
                    $(this.timeline_ele).find(".vis-item-bbox").css('top','25%');
                }
                /*
                if(labelpos==1){ //on right
                }else{ //on left
                }*/
                    
                
                

                //'label_in_bar':(mode==1),
                this.vis_timeline.setOptions({'margin':1,'stack':this.stack_setting}); //(mode!=4)
        
    }
    
    
});
