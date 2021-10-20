/**
* Integration with DataTable widget
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


$.widget( "heurist.resultListDataTable", {

    // default options
    options: {
        recordset: null,
        selection: null,  //list of selected record ids

        eventbased:true, //if false it does not listen global events
        
        search_realm: null,
        serverSide: false,
        
        show_rt_filter:false,
        show_column_config:true,
        show_search:false,
        show_counter:true,
        show_export_buttons:false,
        
        search_initial:null,
        
        dataTableParams: null
    },

    _current_query: null,
    _current_url: null,
    _events: null,
    _dataTable: null,    
    
    selConfigs: null,

    // the constructor
    _create: function() {

        var that = this;

        //this.element.css({'overflow':'hidden'});
        this.div_content = $('<div>').css({width:'100%', height:'100%'}).appendTo( this.element );
        
        this.options.dataTableParams = window.hWin.HEURIST4.util.isJSON(this.options.dataTableParams);
        
        if(!this.options.dataTableParams) this.options.dataTableParams = {};
        
        //table table-striped table-bordered - for bootstrap.css
        var classes = window.hWin.HEURIST4.util.isempty(this.options.dataTableParams['classes'])
                            ?'display compact nowrap cell-border'
                            :this.options.dataTableParams['classes'];

        //this.div_content.css({'padding-top':'5px'}); //,'overflow-y': 'auto'
        this.div_datatable = $('<table>').css({'width':'98%'})
            .addClass(classes).appendTo(this.div_content);
        
        this.options.is_single_selection = false;
        this.options.reload_for_recordset =false;
        this.options.is_frame_based = false;
        
        if(this.options.eventbased){

            //-----------------------     listener of global events
            this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

            $(this.document).on(this._events, function(e, data) {

                
                if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
                {
                    if(!window.hWin.HAPI4.has_access()){ //logout
    that._dout('credentials');                    
                        that.options.recordset = null;
                        that._refresh();
                    }
                    return;
                }                
                
                if (!(that._isSameRealm(data) && data.source!=that.element.attr('id'))) return;
                
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ 

    that._dout('search finished');                
    
                    that._current_query = data.query;
                    that.options.recordset = data.recordset; //hRecordSet

                    that._refresh();
                    that.loadanimation(false);

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                    that.loadanimation(true);
                    that.options.recordset = null;
                    that._refresh();
                    /*if(data && !data.reset){
                        that.updateDataset( jQuery.extend(true, {}, data) ); //keep current query request (clone)
                    }*/

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

    that._dout('selected');                    
                        var sel = window.hWin.HAPI4.getSelection(data.selection, true)
                        that.options.selection = sel;
                        that._refresh();
                }
                //that._refresh();
            });
        
        }
        

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
that._dout('myOnShowEvent');                
                that._refresh();
            }
        });


        if(this.options.search_initial)
        {
            var request = { q:this.options.search_initial, w: 'a', detail: 'ids', 
                        source:'init', search_realm: this.options.search_realm };
            window.hWin.HAPI4.SearchMgr.doSearch(this.document, request);
        }
        
        
    }, //end _create

    //
    //
    //
    _isSameRealm: function(data){
        return !this.options.search_realm || (data && this.options.search_realm==data.search_realm);
    },


    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        //this._refresh();
    },
    
    _dout: function(msg){
        return;
        if(this.options.url  && this.options.url.indexOf('renderRecordData')>0){
            console.log(msg);
        }
    },
    

    /* private function */
    _refresh: function(){

        this._dout('refresh vis='+this.element.is(':visible'));            

        if(this.options.recordset && this.element.is(':visible')){

            this.loadanimation(false);

            var recIds_list = this.options.recordset.getIds();

            if(this._current_query!=this._current_url){                    

                var that = this;
        
                this._current_url = this._current_query;

                if(this._dataTable!=null){
                    this._dataTable.destroy();
                    this._dataTable = null;
                    this.div_datatable.empty();
                }

                if(recIds_list.length>0){

                    var queryURL = window.hWin.HAPI4.baseURL
                    +'hsapi/controller/record_output.php?format=json'
                    +'&db=' + window.hWin.HAPI4.database;
                    var queryStr = '';
                    var rec_total_count = recIds_list.length;
                    
                    this.options.serverSide = true; //(rec_total_count>0); 
                    if(rec_total_count>0){ //5000
                        queryStr = this._current_query;
                    }else{
                        queryStr = '{"ids":"'+this.options.recordset.getIds().join(',')+'"}';
                    }
                    
                    this.options.dataTableParams['scrollCollapse'] = true;
                    this.options.dataTableParams['scrollY'] = this.div_content.height()-120;
                    this.options.dataTableParams['scrollX'] = true;
                    this.options.dataTableParams['autoWidth'] = false;
                    
                    this.options.dataTableParams['initComplete'] = function(){that._onDataTableInitComplete()};
                    
                    if(window.hWin.HEURIST4.util.isempty(this.options.dataTableParams['dom'])){
                        var dom = '';
                        if(this.options.show_rt_filter || this.options.show_column_config){
                            dom = dom + '<"selectors">';
                        }
                        if(this.options.show_search){
                              dom = dom + 'f';
                        }
                        dom = dom + 'rt';
                        if(this.options.show_counter){
                            dom = dom + 'i';
                        }                   
                        dom = dom + 'p'; //pagination
                        
                        if(this.options.show_export_buttons){
                            dom = dom + 'B'; 
                            this.options.dataTableParams['buttons'] = ['copy', 'excel', 'pdf'];    
                        }

                        this.options.dataTableParams['dom'] = dom;//'<"selectors">frtip'; //l - for page length
                    }
                    if(window.hWin.HEURIST4.util.isempty(this.options.dataTableParams['pageLength'])){
                        this.options.dataTableParams['pageLength'] = window.hWin.HAPI4.get_prefs('search_result_pagesize');
                    }
                    
                    this.options.dataTableParams['ordering'] = false;
                    
                    if(window.hWin.HEURIST4.util.isempty(this.options.dataTableParams['columns'])){
                        
                        var settings = window.hWin.HAPI4.get_prefs('columns_datatable');

                        if(settings){
                            this.options.initial_cfg = settings;
                            this.options.dataTableParams['columns'] = settings.columns;
                        }else{
                            this.options.dataTableParams['columns'] = [
                                { data: 'rec_ID', title:'ID' },
                                { data: 'rec_Title', title:'Title' },
                                { data: 'rec_RecTypeID', title:'Type', visible:false }
                            ];
                        }
                        
                    }
                    
                    var cols = this.options.dataTableParams['columns'];
                    for(var i=0;i<cols.length;i++){
                        if(typeof cols[i]['render']==='string'){
                            var fooName = cols[i]['render']
                            if(typeof(eval(fooName))=='function'){ 
                                cols[i]['render'] = eval(fooName);//function(data,type){ [fooName](data,type); }
                            }else{
                                cols[i]['render'] = null;
                            }
                        }else{
                            var ids = cols[i]['data'].split('.');
                            if(ids && ids.length>0){
                                var dt = ids[ids.length-1];
                                var type = $Db.dty(dt,'dty_Type');
                                if(type=='enum' || type=='relationtype'){
                                    cols[i]['render'] = function(data,type){
                                        if (type === 'display') {
                                            return (data>0)?$Db.trm(data,'trm_Label'):data;
                                        }else{
                                            return data;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    

this._dout('reload datatable '+this.options.serverSide);                  
                    
                    if(this.options.serverSide){
                        //pass query to server side
                        this.options.dataTableParams['processing'] = true;
                        this.options.dataTableParams['serverSide'] = true;
                        
                        
                        
                        var datatable_id = window.hWin.HEURIST4.util.random();
                        
                        //to avoid passs thousands of recids for each page request 
                        //pass and save query on server side 
                        window.hWin.HEURIST4.util.sendRequest(queryURL,
                            {q:queryStr, datatable:datatable_id}, null, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    
                                    that.options.dataTableParams['ajax'] = queryURL 
                                        + '&recordsTotal='+rec_total_count
                                        + '&datatable='+datatable_id;

                                    that._dataTable = that.div_datatable.DataTable( that.options.dataTableParams );
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response, true);    
                                }
                            }
                        );

                    }else{

                        this.options.dataTableParams['processing'] = false;
                        this.options.dataTableParams['serverSide'] = false;                    
                        this.options.dataTableParams['ajax'] = queryURL + '&datatable=1&q=' + queryStr;
                        this._dataTable = this.div_datatable.DataTable( this.options.dataTableParams );        
                    }

                }
            }

        }

    },
    
    //
    //
    //
    _onDataTableInitComplete:function(){
        
        //adjust position for datatable controls    
        this.div_content.find('.dataTables_length').css('padding','5 0 0 10');
        var lele = this.div_content.find('.dataTables_filter').css('padding','5 10 0 0');
        this.div_content.find('.dataTables_info').css({'padding-left':'10px','padding-right':'10px'});
        //this.div_content.find('.dataTables_scroll').css({'padding-bottom':'10px'});
        this.div_content.find('.dataTables_scrollBody').css({'width':'100%'});
        this.div_content.find('.dataTables_wrapper').css('padding','0 8px');
        this.div_content.find('.dataTable').css({'font-size':'inherit','width':'100%'});
        
        this.div_content.find('.dataTables_info').css('padding-top','11px');
        this.div_content.find('.dataTables_paginate').css('padding-top','7px');
        this.div_content.find('.paginate_button').css('padding','2px');
        this.div_content.find('.dt-buttons').css('padding-top','7px');
        this.div_content.find('.dt-button').css('padding','2px');
        this.selConfigs = null;
                    
        var that = this;
        
        // Add title to elements that will truncate
        var cells = this.div_content.find('div.dataTables_scroll td.truncate, div.dataTables_scroll th.truncate');
        if(cells.length > 0){
            $.each(cells, function(idx, cell){

                var $ele = $(cell);
                $ele.attr('title', $ele.text());
            });
        }

        if(this.options.show_rt_filter || this.options.show_column_config){

            var sel_container = this.div_content.find('div.selectors').css({float:'left',padding:'15px 0px','min-width':'200px'});

            if(this.options.show_rt_filter){
                
                //add record type selector - filter by record types
                var rectype_Ids = this.options.recordset.getRectypes();

                if(rectype_Ids.length>1){
                    $('<label>Filter by:&nbsp;</label>').appendTo(sel_container)
                    var selScope = $('<select>').appendTo(sel_container).css({'min-width':'12em'});
                    
                    var opt = window.hWin.HEURIST4.ui.addoption(selScope[0],'','select record type …');
                    $(opt).attr('disabled','disabled').attr('visiblity','hidden').css({display:'none'});
                
                    for (var rty in rectype_Ids){
                        rty = rectype_Ids[rty];
                        if(rty>0 && $Db.rty(rty,'rty_Plural') ){
                            window.hWin.HEURIST4.ui.addoption(selScope[0],rty,
                                    $Db.rty(rty,'rty_Plural') ); //'only: '+
                        }
                    }
                    window.hWin.HEURIST4.ui.addoption(selScope[0],'', 'Any record type');
                    
                    this._on( selScope, {
                        change: this._onRecordTypeFilter} );        

                    
                    window.hWin.HEURIST4.ui.initHSelect(selScope);
                }
            }
            
            if(this.options.show_column_config){

                //$('<label>:&nbsp;&nbsp;Choose fields:&nbsp;</label>').appendTo(sel_container)
                //var selConfigs = $('<select>').appendTo(sel_container).css({'min-width':'15em'});
                
                if($.isFunction($('body')['configEntity'])){ //OK! widget script js has been loaded
                    this.selConfigs = $('<fieldset>').css({display:'inline-block'}).appendTo(sel_container);
                    
                    var that = this;
                    
                    this.selConfigs.configEntity({
                        entityName: 'defRecTypes',
                        configName: 'datatable',
                        loadSettingLabel: 'Choose fields:',

                        getSettings: null,
                        setSettings: function( settings ){ //callback function to apply configuration
                                that._onApplyColumnDefinition( settings ); 
                        }, 

                        divSaveSettings: null,
                        showButtons: true,
                        buttons: {rename:'save as', openedit:'edit', remove:'delete'},
                        openEditAction: function(is_new){ //overwrite default behaviour - open configuration popup
                                that._openColumnDefinition( is_new );
                        }
                    });
                    
                    this.selConfigs.find('div.header').css({padding: '7px 16px 3px 0', float: 'left'});
                    this.selConfigs.find('span.btn-action-div').css({display: 'inline-block','padding-top':'10px'});
                    this.selConfigs.configEntity('updateList', 'all', 
                            that.options.initial_cfg?that.options.initial_cfg.cfg_name:null);
                }                    
                
                //add button to configure columns
                /*
                var btn_cfg = $('<button>').button({icon: "ui-icon-pencil", label:'Configure columns', text:false})
                        .css({height:'20px'}).appendTo(sel_container);
                
                this._on( btn_cfg, {
                        click: this._openColumnDefinition} );        
                */
            }                            
        }

    },
    
    //
    // Set filter for recType_ID column
    //
    _onRecordTypeFilter: function(e){
        
        var rty_ID = $(e.target).val();
        var that = this;
        
        $.each(this.options.dataTableParams['columns'],function(idx,item){
            if(item.data=='rec_RecTypeID'){
                that._dataTable.column(idx).search((rty_ID>0?rty_ID:'')).draw();        
            }
        });
        
        if(this.selConfigs)
            this.selConfigs.configEntity('updateList', rty_ID>0?rty_ID:'all');
        
        
    },
    
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events);

        var that = this;

        // remove generated elements
        this.div_datatable.remove();
        this.div_content.remove();
    },

    loadanimation: function(show){

        if(show){
            this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
        }
    },
    
    _onApplyColumnDefinition: function(config){
        
       window.hWin.HAPI4.save_pref('columns_datatable', config);        
        
       this.options.initial_cfg = config;
       this.options.dataTableParams['columns'] = config.columns;
       this._current_url = null; //to force reset datatable
       this._refresh();
    },
    
    //
    // open column configuration dialog
    //
    _openColumnDefinition: function( is_new ){
        
        var that = this;
    
        var opts = {
            currentRecordset: this.options.recordset,
            initial_cfg: (is_new===true)?null:that.options.initial_cfg,
            onClose: function(context){
                if(context){
                    that._onApplyColumnDefinition(context);
                }
            }
        };
    
        window.hWin.HEURIST4.ui.showRecordActionDialog('recordDataTable', opts);        
        
    }

});
