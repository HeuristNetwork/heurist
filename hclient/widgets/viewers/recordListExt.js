/**
* Integration with existing vsn 3 applications - mapping and smarty reports
* Working with current result set and selection
* External application are loaded in iframe
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


$.widget( "heurist.recordListExt", {

    // default options
    options: {
        widget_id: null, //outdated: user identificator to find this widget custom js script on web/CMS page
        title: '',
        is_single_selection: false, //work with the only record - reloads content on every selection event
        recordset: null,
        selection: null,  //list of selected record ids
        url:null,               //
        is_frame_based: true,
        
        reload_for_recordset: false, //refresh every time recordset is changed - for smarty report from CMS

        search_page: null, //target page (for CMS)
        search_realm: null,
        search_initial: null,  //Query or svs_ID for initial search
        
        custom_css_for_frame: null,
        record_with_custom_styles: 0, //record id with custom css and style links DT_CMS_CSS and DT_CMS_EXTFILES
        
        onLoadComplete: null,  //callback

        empty_remark: null, //html content for empty message  (search returns empty result)
        placeholder_text: null //text to display while no record/recordset is loaded  (search is not prefromed)
    },

    _current_url: null, //keeps current url - see loadURL 
    _query_request: null, //keeps current query request
    _events: null,
    _dataTable: null,
    
    _is_publication:false, //this is CMS publication - take css from parent

    placeholder_ele: null, //element holding the placeholder text

    // the constructor
    _create: function() {

        if(this.options.widget_id){ //outdated
            this.element.attr('data-widgetid', this.options.widget_id);
        }
        
        if(this.element.parent().attr('data-heurist-app-id') || this.element.hasClass('cms-element')){
            this._is_publication = true;
        }
        
        var that = this;

        this.div_content = this.element;
        if(this.div_content.parent('.tab_ctrl').length==0 && !this.element.attr('data-widgetid')){
            this.div_content.css({width:'100%', height:'100%'}); 
        }
        
        //this.div_content = $('<div>').css({width:'100%', height:'100%'}).appendTo( this.element );
        
        if(this.options.css){
            this.div_content.css( this.options.css );
        }
        
        
        if(this.options.is_frame_based){
            this.dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'})
            //.attr('src',window.hWin.HAPI4.baseURL+"common/html/msgNoRecordsSelected.html")
            .appendTo( this.div_content );
        }

        //-----------------------     listener of global events
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
            {
                if(!window.hWin.HAPI4.has_access()){ //logout
                    that.options.recordset = null;
that._dout('credentials');                    
                    that._refresh();
                }
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ 

                if(!that._isSameRealm(data)) return;
                
                that.options.recordset = data.recordset; //hRecordSet
                
that._dout('search finished');                
                that._refresh();
                that.loadanimation(false);

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(!that._isSameRealm(data)) return;
                
                if(data && !data.reset){
                    that.updateDataset( jQuery.extend(true, {}, data) ); //keep current query request (clone)
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                //selection happened somewhere else
                if(that.options.is_single_selection && that._isSameRealm(data) && data.source!=that.element.attr('id')){
                    if(data.reset){
                        //that.option("selection",  null);
                        that.options.selection = null;
                    }else{
                        var sel = window.hWin.HAPI4.getSelection(data.selection, true); //get ids
                        that.options.selection = sel;
                        //that.option("selection", sel);
                    }
that._dout('selected');                    

                    var smarty_template = window.hWin.HAPI4.get_prefs_def('main_recview', 'default'); // default = standard record viewer
                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(that.options.selection) && that.options['url'] 
                        && that.options['url'].indexOf('renderRecordData') != -1 && smarty_template != 'default'){

                        var recIDs_list = that.options.selection;

                        if(recIDs_list.length>0){
                            var recID = recIDs_list[recIDs_list.length-1];

                            // check if the custom report exists
                            var req_url = window.hWin.HAPI4.baseURL + "viewers/smarty/templateOperations.php";
                            var request = {mode: 'check', template: smarty_template, db: window.hWin.HAPI4.database}; 

                            window.hWin.HEURIST4.util.sendRequest(req_url, request, null, function(response){

                                if(response && response.ok){

                                    newurl = 'viewers/smarty/showReps.php?publish=1&debug=0'
                                        + '&q=ids:' + recID
                                        + '&db=' + window.hWin.HAPI4.database
                                        + '&template=' + encodeURIComponent(smarty_template);

                                    if(that._current_url != newurl){    
                                        that.loadURL(newurl);
                                    }
                                }else{

                                    var $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                        "You have specified a custom report format '"+ smarty_template.slice(0, -4) +"' to use in this view,<br>"
                                        + "however this format no longer exists.<br><br>Please go to Design > My preferences to choose a new format.", 
                                        null,
                                        {ok: 'Close', title: 'Custom format unavailable'},
                                        {default_palette_class: 'ui-heurist-explore'}
                                    );

                                    that._refresh(); // display normal record view - custom report missing
                                }
                            });
                        }
                    }else{
                        that._refresh();
                    }
                }
            }
            //that._refresh();
        });

        //this._refresh();

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
that._dout('myOnShowEvent');                
                that._refresh();
            }
        });
            
        if(this.options.is_frame_based){
            this.dosframe.on('load', function(){
                that.onLoadComplete();
            });
        }
        
        if(this.options.search_initial){
that._dout('>>'+this.options.search_initial);            
            this.doSearch( this.options.search_initial );
            this.options.search_initial = null;
        }        

        if(!window.hWin.HEURIST4.util.isempty(this.options.placeholder_text)
        || !window.hWin.HEURIST4.util.isempty(this.options.empty_remark)){
            this.placeholder_ele = $('<div>')
                .css('white-space', 'pre-wrap')
                .prependTo(this.element)
                .html(window.hWin.HEURIST4.util.isempty(this.options.empty_remark)
                    ?this.options.placeholder_text:this.options.empty_remark);
        }
        
    }, //end _create

    //
    // Used when it reloads contents for every selection or for every update of recordset
    // see reload_for_recordset and is_single_selection
    //
    loadURL: function( newurl ){
        
        var that = this;

        this._current_url = newurl;
        this.loadanimation(true);
        
this._dout('load '+this.options.is_frame_based+'  '+newurl);
        
        if(this.options.is_frame_based){
            this.dosframe.attr('src', newurl);
        }else{
            this.div_content.load(newurl, function(){ that.onLoadComplete(); })
        }
        
    },
    
    //
    // Callback event listener on load of content into div_content or iframe
    //
    onLoadComplete: function(){
        this.loadanimation(false);
        if(!this.options.reload_for_recordset && this.options.is_frame_based && !this.options.is_single_selection){
this._dout('onLoadComplete refresh again');                
              this._refresh();
        }
        
        if($.isFunction(this.options.onLoadComplete)){
            this.options.onLoadComplete.call(this);
        }
        
        //add custom css to iframe  besides see cssid parameter
        if(this.options.is_frame_based && this.options.custom_css_for_frame){
            
            var fdoc = this.dosframe[0].contentWindow.document;
            
            var style = document.createElement('style');
            style.type = 'text/css'; 
            style.innerHTML = this.options.custom_css_for_frame;
            fdoc.getElementsByTagName('head')[0].appendChild(style);
            
        }
//2020-03-08        
        //this._trigger( 'loadcomplete2', null, null );
        
        if(this.options.is_frame_based && this._is_publication){                
            //init "a" 
            if($.isFunction(initLinksAndImages)){
                
                var fdoc = this.dosframe[0].contentWindow.document;
                
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = window.hWin.HAPI4.baseURL + 'hclient/core/detectHeurist.js';
                fdoc.getElementsByTagName('head')[0].appendChild(script);
                
                initLinksAndImages($(fdoc), {search_page:this.options.search_page, search_realm:this.options.search_realm});
            }
            
            
        }
        
    },
    
    // 
    // Execute search and update dataset independently
    //
    doSearch: function(query){
        var request = {q:query, w: 'a', detail: 'ids', 
                        source: 'init', search_realm: this.options.search_realm };
        window.hWin.HAPI4.RecordSearch.doSearch(this.document, request);
    },
    
    //
    // refresh 
    //
    updateDataset: function(request){
        this._query_request = request;
        this.options.selection = null;
        this.options.recordset = null;
        if(request.q!=''){
            this.loadanimation(true);
        }
this._dout('update dataset '+request.q);        
        this._refresh();
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
        this._superApply( arguments );
        //this._refresh();
    },
    
    _setOption:function(key, value){
        if(key == 'selection'){
            this.options.selection = value;
            this._refresh();
        }else{
            this._superApply( arguments );
        }
    },

    //debug console output    
    _dout: function(msg){
        /*
        if(this.options.url  && this.options.url.indexOf('visual')>0){ //renderRecordData smarty
            console.log(msg);
        }
        */
    },
    
    /*
    _setOption: function( key, value ) {
    if(key=='url'){
    value = window.hWin.HAPI4.baseURL + value;
    }else if (key=='title'){
    var id = this.element.attr('id');
    $(".header"+id).html(value);
    $('a[href="#'+id+'"]').html(value);
    }

    this._super( key, value );
    this._refresh();
    },*/

    /* private function */
    _refresh: function(){

        if(this.options.title!=''){
            var id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }

        if(this.placeholder_ele != null){
            this.placeholder_ele.hide();
            //this.div_content.css('visibility','visibile');
        }

this._dout('refresh vis='+this.element.is(':visible'));            

        //refesh if element is visible only - otherwise it costs much resources
        if( (!this.element.is(':visible') && !this._is_publication) || window.hWin.HEURIST4.util.isempty(this.options.url)){
            return;  
        } 

        if(this.options.is_single_selection){ //reload content on every selection event

            var newurl = null;

            if (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.selection)) {

                var recIDs_list = this.options.selection;

                if(recIDs_list.length>0){
                    var recID = recIDs_list[recIDs_list.length-1];
                    
                    newurl = this.options.url;
                    
                    if(newurl.indexOf('[recID]')>0){
                        newurl = newurl.replace("[recID]", recID);
                    }else{
                        newurl = newurl.replace("[query]", ('q=ids:'+recID));
                    }
                    if(newurl.indexOf('[dbname]')>0){
                        newurl = newurl.replace('[dbname]', window.hWin.HAPI4.database);
                    }else if(newurl.indexOf('db=')<0){
                        newurl = newurl + '&db=' + window.hWin.HAPI4.database;    
                    }
                    
                    if(this.options.record_with_custom_styles){
                        newurl = newurl + '&cssid=' + this.options.record_with_custom_styles;
                    }
                    
                    
                }
            }
            if(newurl==null){

                this._current_url = null;
                
                if(!window.hWin.HEURIST4.util.isempty(this.options.placeholder_text)){
                    this.placeholder_ele.html(this.options.placeholder_text).show();
                    if(this.options.is_frame_based){
                        this.dosframe.attr('src', null);
                    }else{
                        this.div_content.empty();
                    }
                }else{
                    
                    if(this.options.is_frame_based){
                        this.dosframe.attr('src', window.hWin.HRes('recordSelectMsg'));
                    }else{
                        this.div_content.load(window.hWin.HRes('recordSelectMsg'));
                    }
                }
            }else{
                newurl = window.hWin.HAPI4.baseURL +  newurl;

                if(this._current_url!==newurl){
                    this.loadURL(newurl);
                }
            }

        }else 
        if(!this.options.reload_for_recordset && this._current_url!==this.options.url){

            this.options.url = window.hWin.HAPI4.baseURL +  this.options.url.replace("[dbname]",  window.hWin.HAPI4.database);

            this.loadURL( this.options.url );

        }else{ //content has been loaded already ===============================

            var query_string_all = null,
            query_string_sel = null,
            query_string_main = window.hWin.HEURIST4.util.composeHeuristQueryFromRequest( this._query_request, true );

/*

            window.hWin.HEURIST4.currentQuery_all  = query_string_main+'&h4=1'; //query_string_all;
            window.hWin.HEURIST4.currentQuery_sel  = query_string_sel;
            window.hWin.HEURIST4.currentQuery_main = query_string_main;

            window.hWin.HEURIST4.currentQuery_sel_waslimited = false;
            window.hWin.HEURIST4.currentQuery_all_waslimited = false;
*/
            if(this.options.reload_for_recordset) //reloads content entirely
            {
                var newurl = window.hWin.HAPI4.baseURL +  this.options.url.replace("[query]", query_string_main);
                
                if(this.options.record_with_custom_styles){ //to load custom css and style links
                    newurl = newurl + '&cssid=' + this.options.record_with_custom_styles;
                }
                
                this.loadURL( newurl );
                return;    
            }
            

            if (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.selection)) {
                var recIDs_list = this.options.selection;
                if(!window.hWin.HEURIST4.util.isempty(recIDs_list.length)){
                    //NOT USED
                    query_string_sel = 'db=' + window.hWin.HAPI4.database
                    + '&w=' + window.hWin.HEURIST4.util.isnull(this._query_request)?this._query_request.w:'all'
                    + '&q=ids:'+recIDs_list.join(',');
                }
            }

            if(this.options.is_frame_based){
                //there is heurist apps in iframe - smarty report, crosstabs analysis or mapping
                
                if ((this.options.recordset==null || this.options.recordset.length()==0) &&
                    !window.hWin.HEURIST4.util.isempty(this.options.empty_remark)) 
                {
                    this.placeholder_ele.html(this.options.empty_remark).show();
                }else{

                    var showReps = this.dosframe[0].contentWindow.showReps;
                    if(showReps){
                        //@todo - reimplement - send on server JSON with list of record IDs
                        //{"resultCount":23,"recordCount":23,"recIDs":"8005,11272,8599,8604,8716,8852,8853,18580,18581,18582,18583,18584,8603,8589,11347,8601,8602,8600,8592,10312,11670,11672,8605"}
                        if (this.options.recordset!=null){
                            this._checkRecordsetLengthAndRunSmartyReport(-1);
                        }
                    }else if (this.dosframe[0].contentWindow.crosstabsAnalysis) {
                        
                        if (this.options.recordset!=null){
                            this._checkRecordsetLengthAndRunCrosstabsAnalysis(6000, query_string_main);
                        }
                        
                    }else{
                        var showMap = this.dosframe[0].contentWindow.showMap;
                        if(showMap){ //not used anymore
                            showMap.processMap();
                        }else if(this.dosframe[0].contentWindow.updateRuleBuilder && this.options.recordset) {

                            //todo - swtich to event trigger????
                            this.dosframe[0].contentWindow.updateRuleBuilder(this.options.recordset.getRectypes(), this._query_request);
                        }
                    }
                }
            }
            
            this.loadanimation(false);
        }

    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events);

        var that = this;

        // remove generated elements
        if(this.dosframe) this.dosframe.remove();
        this.div_content.remove();
    },

    loadanimation: function(show){

        if(show){
            this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
        }
    },

    //
    // limit: -1 means no limits
    //
    _checkRecordsetLengthAndRunSmartyReport: function(limit){
        
        if(!this.options.is_frame_based) return;

        var showReps = this.dosframe[0].contentWindow.showReps;
        if(!showReps) return;

        var recordset, recIDs_list = [];

        if (this.options.recordset!=null) {
            /* art2304
            var recIDs_list = this.options.recordset.getIds();
            if(!window.hWin.HEURIST4.util.isempty(recIDs_list.length)){
            query_string_all = query_string + '&q=ids:'+recIDs_list.join(',');
            }
            */

            var tot_cnt = this.options.recordset.length();

            recIDs_list = this.options.recordset.getIds(limit);
            recordset = {"resultCount":tot_cnt, "recordCount":recIDs_list.length, "recIDs":recIDs_list};

        }else{
            recordset = {"resultCount":0,"recordCount":0,"recIDs":[]};
        }

        showReps.assignRecordsetAndQuery(recordset, this._query_request);
        showReps.processTemplate();
    },
    
    //
    //
    //
    _checkRecordsetLengthAndRunCrosstabsAnalysis: function(limit, query_string_main){
/* @todo */
        if(!this.options.is_frame_based) return;
        
        var crosstabs = this.dosframe[0].contentWindow.crosstabsAnalysis;
        if(!crosstabs) return;

        var recordset, recIDs_list = [];

        if (this.options.recordset!=null) {

            var tot_cnt = this.options.recordset.length();
            window.hWin.HEURIST4.totalQueryResultRecordCount = tot_cnt;

            recIDs_list = this.options.recordset.getIds();//limit

            var rectype_first = 0;
            if(recIDs_list.length>0){
                var rec = this.options.recordset.getFirstRecord();
                rectype_first = this.options.recordset.fld(rec, 'rec_RecTypeID');
            }

            recordset = {"resultCount":tot_cnt, "recordCount":recIDs_list.length,
                          query_main:  query_string_main,
                          "recIDs":recIDs_list.join(','), 'first_rt':rectype_first};

        }else{
            window.hWin.HEURIST4.totalQueryResultRecordCount = 0;
            
            recordset = {"resultCount":0,"recordCount":0,"recIDs":""};
        }

        crosstabs.assignRecordset(recordset);

    }
    

});
