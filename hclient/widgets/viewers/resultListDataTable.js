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
        
        dataTableParams: null
    },

    _current_query: null,
    _current_url: null,
    _events: null,
    _dataTable: null,    

    // the constructor
    _create: function() {

        var that = this;

        this.div_content = $('<div>').css({width:'100%', height:'100%'}).appendTo( this.element );
        
        var classes = this.options.dataTableParams['classes']
                            ?this.options.dataTableParams['classes']
                            :'display compact';

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

    that._dout('search finised');                
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
                    
                    this.options.serverSide = (rec_total_count>1); 
                    if(rec_total_count>5000){
                        queryStr = this._current_query;
                    }else{
                        queryStr = '{"ids":"'+this.options.recordset.getIds().join(',')+'"}';
                    }
                    
                    this.options.dataTableParams['scrollCollapse'] = true;
                    this.options.dataTableParams['scrollY'] = this.div_content.height()-100;
                    
                    this.options.dataTableParams['initComplete'] = function(){that._onDataTableInitComplete()};
                    
                    this.options.dataTableParams['dom'] = 'l<"selectors">frtip';
                    
                    // need download code: https://datatables.net/download/
                    //this.options.dataTableParams['dom'] = 'lfrtipB';
                    //this.options.dataTableParams['buttons'] = ['copy', 'excel', 'pdf'];

                    if(!this.options.dataTableParams['columns']){
                        
                        this.options.dataTableParams['columns'] = [
                            { data: 'rec_ID', title:'ID' },
                            { data: 'rec_Title', title:'Title' },
                            { data: 'rec_RecTypeID', title:'Type', visible:false }
                        ];
                        
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
                    this.div_content.find('.dataTables_info').css('padding-left','10px');
                    this.div_content.find('.dataTables_scrollBody').css('width','99%');
                    this.div_content.find('.dataTable').css('font-size','inherit');
                    
                    var sel_container = this.div_content.find('div.selectors').css({float:'left',padding:'5px 10px','min-width':'250px'});
                    
                    //add record type selector - filter by record types
                    var rectype_Ids = this.options.recordset.getRectypes();
//console.log(rectype_Ids);                    
                    if(rectype_Ids.length>1){
                        var selScope = $('<select>').appendTo(sel_container);
                        
                        var opt = window.hWin.HEURIST4.ui.addoption(selScope[0],'','select record type …');
                        $(opt).attr('disabled','disabled').attr('visiblity','hidden').css({display:'none'});
                    
                        for (var rty in rectype_Ids){
                            if(rty>=0 && window.hWin.HEURIST4.rectypes.pluralNames[rectype_Ids[rty]]){
                                rty = rectype_Ids[rty];
                                window.hWin.HEURIST4.ui.addoption(selScope[0],rty,
                                        window.hWin.HEURIST4.rectypes.pluralNames[rty]); //'only: '+
                            }
                        }
                        window.hWin.HEURIST4.ui.addoption(selScope[0],'', 'Any record type');
                        
                        this._on( selScope, {
                            change: this._onRecordTypeFilter} );        
                        
                        
                        window.hWin.HEURIST4.ui.initHSelect(selScope);
                    }
                    
                    //add button to configure columns
                    var btn_cfg = $('<button>').button({icon: "ui-icon-gear"})
                            .css({height:'20px'}).appendTo(sel_container);
                    
                    this._on( btn_cfg, {
                            click: this._openColumnDefinition} );        
        
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
    
    //
    //
    //
    _openColumnDefinition: function(){
        
        var that = this;
    
        var opts = {
            currentRecordset: this.options.recordset,
            onClose: function(context){
                if(context){
console.log('selected');                    
console.log(context);      
                   that.options.dataTableParams['columns'] = context.columns;
                   that._current_url = null; //to force reset datatable
                   that._refresh();
                }
            }
        };
    
        window.hWin.HEURIST4.ui.showRecordActionDialog('recordDataTable', opts);        
        
    }

});
