/**
* Integration with existing H3 applications - mapping and smarty reports
* Working with current result set and selection
* External application are loaded in iframe
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
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
                        position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
                        'background':'url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center'})
                   .appendTo( this.element );
                   
        this.dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'}).appendTo( this.div_content );


        //-----------------------     listener of global events
        this._events = top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT + ' ' 
            + top.HAPI4.Event.ON_REC_SEARCH_FINISH + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART 
            + ' ' + top.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {

            if(e.type == top.HAPI4.Event.LOGIN){

                that._refresh();

            }else  if(e.type == top.HAPI4.Event.LOGOUT)
            {
                that.option("recordset", null);

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCH_FINISH){     //@todo???? with incremental    ON_REC_SEARCHRESULT

                that.option("recordset", data); //hRecordSet
                that.loadanimation(false);

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){

                if(data){
                    that._query_request = jQuery.extend(true, {}, data);  //keep current query request (clone)
                    that.option("recordset", null);
                    if(data.q!='')
                        that.loadanimation(true);
                }
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                if(data && data.source!=that.element.attr('id') && that.options.is_single_selection) { 
                   
                   data = data.selection;
                   that.option("selection", top.HAPI4.getSelection(data, true) );    
                   
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
            value = top.HAPI4.basePathOld + value;
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
        
        //refesh if element is visible only - otherwise it costs much resources        
        if(!this.element.is(':visible') || top.HEURIST4.util.isempty(this.options.url)) return;
        
        if(this.options.is_single_selection){
            
            var newurl = "common/html/msgNoRecordsSelected.html";
            
            if (top.HEURIST4.util.isArrayNotEmpty(this.options.selection)) {
                
                var recIDs_list = this.options.selection;
                
                if(recIDs_list.length>0){
                     var recID = recIDs_list[recIDs_list.length-1];
                     newurl = this.options.url.replace("[recID]", recID).replace("[dbname]",  top.HAPI4.database);         
                }
            }
            
            newurl = top.HAPI4.basePathOld +  newurl;
                
            if(this.dosframe.attr('src')!==newurl){
                this.dosframe.attr('src', newurl);
            }
            
        }else if(this.dosframe.attr('src')!==this.options.url){
            
            this.options.url = top.HAPI4.basePathOld +  this.options.url.replace("[dbname]",  top.HAPI4.database);
             
            this.dosframe.attr('src', this.options.url);
             
        }else{ //content has been loaded already

            var query_string = 'db=' + top.HAPI4.database,
                query_string_all = null,
                query_string_sel = null,
                query_string_main = null;

            if(!top.HEURIST4.util.isnull(this._query_request)){
                query_string = query_string + '&w='+this._query_request.w;
                
                query_string_main = query_string;
                
                if(!top.HEURIST4.util.isempty(this._query_request.q)){
                    query_string_main = query_string_main + '&q=' + encodeURIComponent(this._query_request.q);
                }
                if(!top.HEURIST4.util.isempty(this._query_request.rules)){
                    //@todo simplify rules array - rempove redundant info
                    query_string_main = query_string_main + '&rules=' + encodeURIComponent(this._query_request.rules);
                }
            }else{
                query_string = query_string + '&w=all';
                query_string_main = query_string;
            }
        
            
            if (top.HEURIST4.util.isArrayNotEmpty(this.options.selection)) {
                  var recIDs_list = this.options.selection;
                  if(!top.HEURIST4.util.isempty(recIDs_list.length)){
                        query_string_sel = query_string + '&q=ids:'+recIDs_list.join(',');
                  }
            }
            if (this.options.recordset!=null) {
                /* art2304
                  var recIDs_list = this.options.recordset.getIds();
                  if(!top.HEURIST4.util.isempty(recIDs_list.length)){
                        query_string_all = query_string + '&q=ids:'+recIDs_list.join(',');
                  }
                 */ 
                  top.HEURIST.totalQueryResultRecordCount = this.options.recordset.length();
            }else{                         
                  top.HEURIST.totalQueryResultRecordCount = 0;
            }
            
            /* art2304
            if(query_string_main.toLowerCase().indexOf('sortby')>=0){  //keep order for smarty output
                top.HEURIST.currentQuery_all_ = query_string_main;
            }else{
                top.HEURIST.currentQuery_all_ = top.HEURIST.currentQuery_all
            }*/
            
            
            top.HEURIST.currentQuery_all  = query_string_main; //query_string_all;
            top.HEURIST.currentQuery_sel  = query_string_sel;
            top.HEURIST.currentQuery_main = query_string_main;
            
            top.HEURIST.currentQuery_sel_waslimited = false;
            top.HEURIST.currentQuery_all_waslimited = false;
            
            var showReps = this.dosframe[0].contentWindow.showReps;
            if(showReps){
                showReps.processTemplate();
            }else{
                var showMap = this.dosframe[0].contentWindow.showMap;
                if(showMap){
                    showMap.processMap();
                }else if(this.dosframe[0].contentWindow.updateRuleBuilder && this.options.recordset) {
                    
                    //todo - swtich to event trigger????
                    this.dosframe[0].contentWindow.updateRuleBuilder(this.options.recordset.getRectypes(), this._query_request);
                }
            }
            
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
            //this.dosframe.hide();
            this.div_content.css('background','url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
            //this.dosframe.show();
        }
    },

});
