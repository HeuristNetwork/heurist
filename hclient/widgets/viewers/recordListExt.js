/**
* Integration with existing vsn 3 applications - mapping and smarty reports
* Working with current result set and selection
* External application are loaded in iframe
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
        title: '',
        is_single_selection: false, //work with the only record
        recordset: null,
        selection: null,  //list of selected record ids
        url:null
    },

    _query_request: null, //keep current query request
    _events: null,

    // the constructor
    _create: function() {

        var that = this;

        this.div_content = $('<div>')
        .css({
            position:'absolute', top:'2.5em', bottom:0, left:0, right:0})
        //'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center'})
        .appendTo( this.element );

        this.dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'})
        //.attr('src',window.hWin.HAPI4.baseURL+"common/html/msgNoRecordsSelected.html")
        .appendTo( this.div_content );

        //-----------------------     listener of global events
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS + ' '
        + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
            {
                if(!window.hWin.HAPI4.has_access()){ //logout
                  that.option("recordset", null);  
                }
                that._refresh();
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){     //@todo???? with incremental    ON_REC_SEARCHRESULT

                that.option("recordset", data); //hRecordSet
                that.loadanimation(false);

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(data){
                    that._query_request = jQuery.extend(true, {}, data);  //keep current query request (clone)
                    that.option("recordset", null);
                    that.option("selection", null);
                    if(data.q!='')
                        that.loadanimation(true);
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                if(that.options.is_single_selection){
                    if(data){
                        if(data.source!=that.element.attr('id')) { //selection happened somewhere else
                            data = data.selection;
                            that.option("selection", window.hWin.HAPI4.getSelection(data, true) );
                        }
                    }else{
                        that.option("selection",  null);
                    }
                }
            }
            //that._refresh();
        });

        //this._refresh();

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
        if(!this.options.is_single_selection){
            this.dosframe.on('load', function(){
                that.loadanimation(false);
                that._refresh();
            });
        }


    }, //end _create


    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
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

//DEBUG console.log('REFRESH');        
        
        if(this.options.title!=''){
            var id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }

        //refesh if element is visible only - otherwise it costs much resources
        if(!this.element.is(':visible') || window.hWin.HEURIST4.util.isempty(this.options.url)) return;

        if(this.options.is_single_selection){

            var newurl = "common/html/msgNoRecordsSelected.html";

            if (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.selection)) {

                var recIDs_list = this.options.selection;

                if(recIDs_list.length>0){
                    var recID = recIDs_list[recIDs_list.length-1];
                    newurl = this.options.url.replace("[recID]", recID).replace("[dbname]",  window.hWin.HAPI4.database);
                }
            }

            newurl = window.hWin.HAPI4.baseURL +  newurl;

            if(this.dosframe.attr('src')!==newurl){
                this.loadanimation(true);
                this.dosframe.attr('src', newurl);
            }

        }else if(this.dosframe.attr('src')!==this.options.url){

            this.options.url = window.hWin.HAPI4.baseURL +  this.options.url.replace("[dbname]",  window.hWin.HAPI4.database);

            this.loadanimation(true);
            this.dosframe.attr('src', this.options.url);

        }else{ //content has been loaded already

            var query_string_all = null,
            query_string_sel = null,
            query_string_main = window.hWin.HEURIST4.util.composeHeuristQueryFromRequest( this._query_request, true );

            if (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.selection)) {
                var recIDs_list = this.options.selection;
                if(!window.hWin.HEURIST4.util.isempty(recIDs_list.length)){

                    query_string_sel = 'db=' + window.hWin.HAPI4.database
                    + '&w=' + window.hWin.HEURIST4.util.isnull(this._query_request)?this._query_request.w:'all'
                    + '&q=ids:'+recIDs_list.join(',');
                }
            }


            /* art2304
            if(query_string_main.toLowerCase().indexOf('sortby')>=0){  //keep order for smarty output
            top.HEURIST.currentQuery_all_ = query_string_main;
            }else{
            top.HEURIST.currentQuery_all_ = top.HEURIST.currentQuery_all
            }*/

            if(top.HEURIST){
                top.HEURIST.currentQuery_all  = query_string_main+'&h4=1'; //query_string_all;
                top.HEURIST.currentQuery_sel  = query_string_sel;
                top.HEURIST.currentQuery_main = query_string_main;

                top.HEURIST.currentQuery_sel_waslimited = false;
                top.HEURIST.currentQuery_all_waslimited = false;
            }

            var showReps = this.dosframe[0].contentWindow.showReps;
            if(showReps){
                //@todo - reimplement - send on server JSON with list of record IDs
                //{"resultCount":23,"recordCount":23,"recIDs":"8005,11272,8599,8604,8716,8852,8853,18580,18581,18582,18583,18584,8603,8589,11347,8601,8602,8600,8592,10312,11670,11672,8605"}
                if (this.options.recordset!=null){
                    this._checkRecordsetLengthAndRunSmartyReport(-1);
                }
            }else if (this.dosframe[0].contentWindow.crosstabsAnalysis) {
                
                if (this.options.recordset!=null){
                    this._checkRecordsetLengthAndRunCrosstabsAnalysis(6000);
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
        this.dosframe.remove();
        this.div_content.remove();
    },

    loadanimation: function(show){

        if(show){
            this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
        }
    },

    _checkRecordsetLengthAndRunSmartyReport: function(limit){

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
            if(top.HEURIST){
                top.HEURIST.totalQueryResultRecordCount = tot_cnt;
            }
                

            recIDs_list = this.options.recordset.getIds(limit);
            recordset = {"resultCount":tot_cnt, "recordCount":recIDs_list.length, "recIDs":recIDs_list.join(',')};

        }else{
            if(top.HEURIST){
                top.HEURIST.totalQueryResultRecordCount = 0;
            }
            
            recordset = {"resultCount":0,"recordCount":0,"recIDs":""};
        }

        showReps.assignRecordset(recordset);
        showReps.processTemplate();
    },
    
    _checkRecordsetLengthAndRunCrosstabsAnalysis: function(limit){
/* @todo */
        var crosstabs = this.dosframe[0].contentWindow.crosstabsAnalysis;
        if(!crosstabs) return;

        var recordset, recIDs_list = [];

        if (this.options.recordset!=null) {

            var tot_cnt = this.options.recordset.length();
            if(top.HEURIST){
                top.HEURIST.totalQueryResultRecordCount = tot_cnt;
            }

            recIDs_list = this.options.recordset.getIds();//limit

            var rectype_first = 0;
            if(recIDs_list.length>0){
                var rec = this.options.recordset.getFirstRecord();
                rectype_first = this.options.recordset.fld(rec, 'rec_RecTypeID');
            }

            recordset = {"resultCount":tot_cnt, "recordCount":recIDs_list.length, 
                                                "recIDs":recIDs_list.join(','), 'first_rt':rectype_first};

        }else{
            if(top.HEURIST){
                top.HEURIST.totalQueryResultRecordCount = 0;
            }
            recordset = {"resultCount":0,"recordCount":0,"recIDs":""};
        }

        crosstabs.assignRecordset(recordset);

    }
    

});
