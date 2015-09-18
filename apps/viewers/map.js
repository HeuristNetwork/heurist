/**
* H4 mapping widget
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
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


$.widget( "heurist.map", {

    // default options
    options: {
        title: '',
        is_single_selection: false, //work with the only record
        recordset: null,
        selection: null,
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
            + top.HAPI4.Event.ON_REC_SEARCHRESULT + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART + ' ' + top.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {
            // Login
            if(e.type == top.HAPI4.Event.LOGIN){

                that._refresh();

            // Logout
            }else  if(e.type == top.HAPI4.Event.LOGOUT) { 
                
                that.option("recordset", null);

            // Search results
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT){

                that.option("recordset", data); //hRecordSet
                that.loadanimation(false);

            // Search start
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){

                if(data){
                    that._query_request = data;  //keep current query request 
                    if(data.q!='') that.loadanimation(true);
                }
                that.option("recordset", null);
                
              
            // Record selection  
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                if(data && data.source!=that.element.attr('id')) { 
                   
                    
                    //!!!!! @todo top.HAPI4.getSelection(selection, true);
                    
                   data = data.selection;
                
                   if(data && (typeof data.isA == "function") && data.isA("hRecordSet") ){
                       that.option("selection", data);
                   }else{
                       that.option("selection", null);
                   }
                }
            }
        });

        
        // Refreshing
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

    /* private function */
    _refresh: function(){

        if(this.options.title!=''){
            var id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }
        
        //refesh if element is visible only - otherwise it costs much resources        
        if(!this.element.is(':visible') || top.HEURIST4.util.isempty(this.options.url)) return;
        
        // Single selection
        if(this.options.is_single_selection){
            
            var newurl = "common/html/msgNoRecordsSelected.html";
            
            if (this.options.selection!=null) {
                
                var recIDs_list = this.options.selection.getIds();
                
                if(recIDs_list.length>0){
                     var recID = recIDs_list[recIDs_list.length-1];
                     newurl = this.options.url.replace("[recID]", recID).replace("[dbname]",  top.HAPI4.database);         
                }
            }
            
            newurl = top.HAPI4.basePathOld +  newurl;
                
            if(this.dosframe.attr('src')!==newurl){
                this.dosframe.attr('src', newurl);
            }
       
        // Set new source     
        }else if(this.dosframe.attr('src')!==this.options.url){
            
            this.options.url = top.HAPI4.basePathOld +  this.options.url.replace("[dbname]",  top.HAPI4.database);
            this.dosframe.attr('src', this.options.url);
          
        // Content loaded already    
        }else{
            // MAPPING CODE
            var q = this._query_request;
            console.log("q: " + q);
            
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
