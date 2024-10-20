/**
* methods for timeline 
* timelineRefresh - reloads timeline component
* setSelection
* zoomToSelection
* 
* _timelineZoomToAll
* _timelineZoomToRange
* _timelineApplyRangeOnMap - filter items on map based on timeline range
* _timelineInitToolbar - button initialization (called once on first init)
* _timelineApplyLabelSettings
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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

/* global vis */

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
    
    visible_fields: null,

    // the widget's constructor
    _create: function() {

        let that = this;

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
            let options = {dataAttributes: ['id'],
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
        
        
            let that = this;
        
            this.vis_timeline.on('rangechanged', function(params){
                    that._timelineApplyRangeOnMap(params);  
            });
            //on select listener
            this.vis_timeline.on('select', function(params){
                
                let selection = params.items;
                that.selected_rec_ids = [];
                if(selection && selection.length>0){

                    let e = params.event;
                                                //div.vis-item.vis-dot.vis-selected.vis-readonly
                    e.cancelBubble = true;
                    if (e.stopPropagation) e.stopPropagation();
                    e.preventDefault();
                    if($(e.target).hasClass('vis-item vis-dot vis-selected')) return;

                    //remove dataset prefixes - extract record ID
                    $.each(selection, function(idx, itemid){
                        let k = itemid.indexOf('-');
                        if(k>0){
                            itemid = itemid.substring(k+1);
                            k = itemid.indexOf('-');
                            if(k>0){
                                itemid = itemid.substring(0,k);
                            }
                        }
                        that.selected_rec_ids.push( itemid ); //record id
                    });
                    
                    if(window.hWin.HEURIST4.util.isFunction(that.options.onselect)){ //trigger global event
                        that.options.onselect.call(that, that.selected_rec_ids);    
                    }
    
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
        let selection_vis = [];
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.selected_rec_ids) && this.vis_timeline.itemsData){
            
            this.vis_timeline.itemsData.forEach(function (itemData) {
                if(window.hWin.HEURIST4.util.findArrayIndex(itemData.recID, selected)>=0){
                    selection_vis.push( itemData.id );
                }
            });
        
        }    
        this.vis_timeline.setSelection(selection_vis);
       
        //scroll to first selected
        if(selection_vis.length>0){
            let tele = $(this.timeline_ele);
            let rdiv = tele.find('.vis-item.vis-selected:first'); 
            if(rdiv.length>0){
                let spos2 = rdiv.position().top; //relative position of record div
                tele.scrollTop( spos2 );
            }
        }
    },
    
    //
    //
    //
    zoomToSelection: function(selected){
        
        if(selected && selected.length>0){
            this.setSelection(selected);
        }
        
        let sels = this.vis_timeline.getSelection();
        if(sels && sels['length']>0){
               let range = this.vis_timeline.getDataRangeHeurist(new vis.DataSet(this.vis_timeline.itemsData.get(sels)));
               this._timelineZoomToRange(range);
        }
    },

    //
    //
    //    
    timelineUpdateGroupLabel: function(timeline_group){
      
        let grp = this.vis_timeline.itemSet.groups[timeline_group.id];
        grp.setData(timeline_group);
        //grp.dom.inner
        
    },
    
    //
    // timelineRefresh (former loadVisTimeline) - reloads timeline component with new data
    //
    timelineRefresh: function(timeline_data, timeline_groups){

        /*
        let timeline_data = [],
            timeline_groups = [];

        $.each(all_mapdata, function(dataset_id, mapdata){

            let cnt = mapdata.timeline.items.length;
            if(mapdata.visible && cnt>0){

                timeline_data = timeline_data.concat( mapdata.timeline.items );
                timeline_groups.push({ id:dataset_id, content: mapdata.title});

            }
        });
        */

        if(timeline_data){
            
            this.visible_fields = null;

            
            this._off($(this.timeline_ele).find('input[type="checkbox"][data-dty_id]'),'click');
            
            let tdata = [];
            if($.isPlainObject(timeline_data)){

                for(let idx in timeline_data) {
                    if(Object.hasOwn(timeline_data, idx)){
                        tdata = tdata.concat(timeline_data[idx]);
                    }
                }
                
            }else{
                tdata = timeline_data;
            }

            let groups = new vis.DataSet( timeline_groups );
            let items = new vis.DataSet( tdata ); //options.items );
            
            this.vis_timeline_range = null; //reset
            
            let timeline_content = $(this.timeline_ele).find('.vis-itemset');
            timeline_content.hide();
            window.hWin.HEURIST4.msg.bringCoverallToFront($(this.timeline_ele), {'background-color': "#000", opacity: '0.6', color: 'white', 'font-size': '16px'}, 'loading dates ...');
            
            let that = this;
            
            this.vis_timeline.itemSet.setOptions({
                    onDisplay: function(item, callback){
                        //returns true if visible
                        let res = true;
                        if(that.visible_fields 
                            && that.visible_fields[item.data.group] 
                            && that.visible_fields[item.data.group].length>0)
                        {
                            res = (that.visible_fields[item.data.group].indexOf(item.data.dtyID)>=0);
                        }
                        return res;
                    },
                    groupOrder:function (a, b) {
                    let av = a['content'];
                    let bv = b['content'];
                    if(av.indexOf('Current query')==0) return -1;
                    if(bv.indexOf('Current query')==0) return 1;
                    return av > bv ? 1 : av < bv ? -1 : 0;
                }});
            this.vis_timeline.setGroups(groups);
            try{
                this.vis_timeline.setItems(items);    
            }catch(err){
                console.error(err);
            }

            //apply label settings
            this._timelineApplyLabelSettings(this.vis_timeline_label_mode, 0);
            
            if(this.isApplyTimelineFilter) this._timelineApplyRangeOnMap(null);
            window.hWin.HAPI4.save_pref('mapTimelineFilter', this.isApplyTimelineFilter?1:0);
            $('#lbl_timeline_filter').html('Time filter '+(this.isApplyTimelineFilter?'on':'off'));
            
            
            this._timelineZoomToAll();
            window.hWin.HEURIST4.msg.sendCoverallToBack($(this.timeline_ele));
            timeline_content.show();
            
            //add listener
            this._on($(this.timeline_ele).find('input[type="checkbox"][data-dty_id]'),{click:function(event){
                let group_id = $(event.target).attr('data-layer_id');
                if(!this.visible_fields) this.visible_fields = {};
                if(!this.visible_fields[group_id]) this.visible_fields[group_id] = [];
                this.visible_fields[group_id] = [];
                let that = this;
                $(this.timeline_ele).find('input[type="checkbox"][data-layer_id="'+group_id+'"]').each(function(i,item){
                    if($(item).prop('checked')){
                        that.visible_fields[group_id].push(parseInt($(item).attr('data-dty_id')));
                    }
                });
                
                this.vis_timeline.redraw();
            }});
            
        }
        
    },
    
    //
    //
    //
    _timelineZoomToAll: function(){
        
            if(this.vis_timeline_range==null){
                this.vis_timeline_range = this.vis_timeline.getDataRangeHeurist();
            }
            //let range = vis_timeline.getItemRange(); //@todo calculate once
            this._timelineZoomToRange( this.vis_timeline_range );
        
    },
    
    //
    //
    //
    _timelineZoomToRange: function( range ){
        
            if(!(range && range.min  && range.max && this.vis_timeline)) return;
        
            let min = this.vis_timeline.getDate(range.min), // new Date(range.min).getTime(),
                max = this.vis_timeline.getDate(range.max); //new Date(range.max).getTime();
            let delta = 0;

            if(isNaN(min) || isNaN(max) ) return;
            
            
            if(range['nofit']==undefined){
            let interval = max-min;
            let YEAR = 31536000000;
            let DAY = 86400000;

            let yearmax = (new Date(range.max)).getFullYear();
            let dt = range['omax'];
            let dta = [];
            if(typeof dt==='string'){
                dta = dt.split('-');
                if(dta.length>0)
                    yearmax = Number(dta[0]);
            }else if(!isNaN(parseInt(dt))){
                yearmax = Number(dt);
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
                //let yearmax = (new Date(range.max)).getFullYear();
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

        let item_id = 0;
        if(item && item['id']){
            item_id = item['id'];
        }else{
            item_id = item;
        }
        if(item_id){

            if(!ds) ds = this.vis_timeline.itemsData;

            let type = {};
            type[field] = 'Moment';
            let val = ds.get(item_id,{fields: [field], type: type });

            if(val!=null && val[field] && val[field]._isAMomentObject){
                //return val[field].toDate().getTime(); //unix
                return val[field].unix()*1000;
            }
        }
        return NaN;
    },

    /*
    _getItemField(item, field){
      
        let val = null;
        let item_id = 0;
        if(item && item['id']){
            item_id = item['id'];
        }else{
            item_id = item;
        }
        if(item_id){
            let ds = this.vis_timeline.itemsData;
            val = ds.get(item_id,{fields: [field], type: type });
        }  
        return val;     
    },*/
    
    //
    // filter items on map based on timeline range 
    //
    _timelineApplyRangeOnMap: function(params)
    {
        let that = this;
        
        if(params==null){
            params = this._keepLastTimeLineRange;
        }else{
            this._keepLastTimeLineRange = params;    
        }
        
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onfilter)){ //trigger global event
        
            if (!this.isApplyTimelineFilter || !this.vis_timeline.itemsData || !params) return;
            
            //loop by timeline datasets
            
            //if fit range
            let items_visible = [], items_hidden=[];
            
            this.vis_timeline.itemsData.forEach(function (itemData) {
                        
                let start = that._getUnixTs(itemData, 'start');
                let end = 'end' in itemData ? that._getUnixTs(itemData, 'end') :start;
                //let start = itemData.start.valueOf();
                //let end = 'end' in itemData ? itemData.end.valueOf() : itemData.start.valueOf();            
                //start = (new Date(start)).getTime();
                //end = (new Date(end)).getTime();
                
                //intersection
                let res = false;
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
    
    //
    // button initialization (called once on first init)
    //
    _timelineInitToolbar: function(){
        
        let that = this;
        /**
         * Zoom the timeline a given percentage in or out
         * @param {Number} percentage   For example 0.1 (zoom out) or -0.1 (zoom in)
         */
        function __timelineZoom (percentage) {
            let range = that.vis_timeline.getWindow();
            let interval = range.end - range.start;

            that.vis_timeline.setWindow({
                start: range.start.valueOf() - interval * percentage,
                end:   range.end.valueOf()   + interval * percentage
            });
        }

        function __timelineZoomToSelection(){

                let sels = that.vis_timeline.getSelection();
                if(sels && sels['length']>0){
                       let range = that.vis_timeline.getDataRangeHeurist(new vis.DataSet(that.vis_timeline.itemsData.get(sels)));
                       that._timelineZoomToRange(range);
                }
        }

        function __timelineMoveToLeft(){

            let range2 = that.vis_timeline.getWindow();
            let interval = range2.end - range2.start;

            if(that.vis_timeline_range==null){
                    that.vis_timeline_range = that.vis_timeline.getDataRangeHeurist();
            }

            that._timelineZoomToRange({min:that.vis_timeline_range.min.getTime(), 
                                        max:that.vis_timeline_range.min.getTime()+interval, nofit:true});
        }

        function __timelineMoveToRight(){

            let range2 = that.vis_timeline.getWindow();
            let interval = range2.end - range2.start;
            let delta = interval*0.1;
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
            
            let $dlg_edit_layer = $('#timeline-edit-dialog').dialog({
                width: 450,
                modal: true,
                resizable: false,
                title: window.hWin.HR('Timeline options'),
                buttons: [
                    {text:window.hWin.HR('Apply'), click: function(){
                        
                        let mode = $( this ).find('input[type="radio"][name="time-label"]:checked').val();
                        
                        that.vis_timeline_label_mode = mode;
                        let spinner = $(that.element).find("#timeline_spinner");
                        if(mode==2){
                            spinner.show();
                        }else{
                            spinner.hide();
                        }
                        
                        let labelpos = $( this ).find('input[type="radio"][name="time-label-pos"]:checked').val();

                        that.stack_setting = ($( this ).find('input[type="radio"][name="time-label-stack"]:checked').val()==1);

                        that._timelineApplyLabelSettings(mode, labelpos);                
                        that.vis_timeline.redraw();    
                        
                        /*
                        let stack_mode = $( this ).find('input[type="radio"][name="time-label-stack"]:checked').val()==1;
                        if(that.stack_setting != (stack_mode==1)){
                            that.stack_setting = (stack_mode==1);
                            that.vis_timeline.setOptions({'stack':that.stack_setting});
                        }else{
                            that.vis_timeline.redraw();    
                        }*/
                        
                        let newval = $( this ).find('input[type="checkbox"][name="time-filter-map"]').is(':checked');
                        
                        if(that.isApplyTimelineFilter !== newval){
                            that.isApplyTimelineFilter = newval;
                            if(newval){
                                that._timelineApplyRangeOnMap( null );    
                            }else if(window.hWin.HEURIST4.util.isFunction(that.options.onfilter)){
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
        
        let toolbar = $(this.element).find("#timeline_toolbar").css({'font-size':'0.8em', zIndex:3});

        $("<button>").button({icon:"ui-icon-circle-plus",showLabel:false, label:window.hWin.HR("Zoom In")})
            .on('click', function(){ __timelineZoom(-0.25); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-circle-minus",showLabel:false, label:window.hWin.HR("Zoom Out")})
            .on('click', function(){ __timelineZoom(0.5); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-arrowthick-2-e-w",showLabel:false, label:window.hWin.HR("Zoom to All")})
            .on('click', function(){ that._timelineZoomToAll(); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-arrowthickstop-1-s",showLabel:false, label:window.hWin.HR("Zoom to selection")})
            .on('click', function(){ __timelineZoomToSelection(); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-arrowthickstop-1-w",showLabel:false, label:window.hWin.HR("Move to Start")})
            .on('click', function(){ __timelineMoveToLeft(); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-arrowthickstop-1-e",showLabel:false, label:window.hWin.HR("Move to End")})
            .on('click', function(){ __timelineMoveToRight(); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-gear",showLabel:false, label:window.hWin.HR("Timeline options")})
            .on('click', function(){ __timelineEditProperties(); })
            .appendTo(toolbar);
        $("<span>").attr('id','lbl_timeline_filter')
            .text('').css('font-style','italic').appendTo(toolbar);
            
        let spinner = $( "<input>", {id:"timeline_spinner", value:10} ).appendTo(toolbar);
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
    // mode: 0 - full, 1 - truncate, 2- fixed width, 3 - hide
    // labelpos 2 - above, 1 - within the bar
    //
    _timelineApplyLabelSettings: function(mode, labelpos){
        
                let contents = $(this.timeline_ele).find(".vis-item-content");
                let spinner = $(this.element).find("#timeline_spinner");

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
