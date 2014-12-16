/**
* Time Map Widget - load map into frame
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


$.widget( "heurist.app_timemap", {

    // default options
    options: {
        recordset: null,
        selection: null //list of record ids
    },
    
    _events: null,

    recordset_changed: true,

    // the constructor
    _create: function() {

        var that = this;

        this.element.hide();
        
        this.framecontent = $('<div>').addClass('frame_container')
                            //.css({position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
                            //     'background':'url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center'})
                            .appendTo( this.element );
                   
        this.mapframe = $( "<iframe>" )
                        .attr('id', 'map-frame')
                        //.attr('src', 'php/mapping.php?db='+top.HAPI4.database)
                        .appendTo( this.framecontent );

        this._events = top.HAPI4.Event.LOGOUT 
                            + ' ' + top.HAPI4.Event.ON_REC_SEARCH_FINISH 
                            + ' ' + top.HAPI4.Event.ON_REC_SEARCHSTART 
                            + ' ' + top.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {

            if(e.type == top.HAPI4.Event.LOGOUT)
            {
                if(that.options.recordset != null){
                    that.recordset_changed = true;
                    that.option("recordset", null);
                    that._refresh();
                }
                
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCH_FINISH){

                that.loadanimation(false);
                that.recordset_changed = true;
                that.option("recordset", data); //hRecordSet
                that._refresh();

            // Search start
            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHSTART){

                that.option("recordset", null);
                that.option("selection", null);
                that.loadanimation(true);
                //???? that._refresh();
              
            // Record selection  
            }else if(e.type == top.HAPI4.Event.ON_REC_SELECT){
                
                if(data && data.source!=that.element.attr('id')) { //selection happened somewhere else
                  
console.log("_doVisualizeSelection");                  
                    that._doVisualizeSelection( top.HAPI4.getSelection(data.selection, true) );
                }            
            }
                

        });

        // (this.mapframe).load(that._initmap);
        // init map on frame load
        this._on( this.mapframe, {
            load: this._refresh   // this._initmap
            }
        );

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });

        //this._refresh();

    }, //end _create

    /* private function */
    _refresh: function(){

        if ( this.element.is(':visible') && this.recordset_changed) {  //to avoid reload if recordset is not changed

            if( this.mapframe.attr('src') ){
                this._initmap()
            }else {
                (this.mapframe).attr('src', top.HAPI4.basePath + '/page/mapping.php?db='+top.HAPI4.database);
            }
        }

    },

    _initmap: function(){

        if( !top.HEURIST4.util.isnull(this.mapframe) && this.mapframe.length > 0 ){
            
            
            var mapping = this.mapframe[0].contentWindow.mapping;
            
            var that = this;
            
            if(!mapping){
                setTimeout(function(){ that._initmap()}, 1000); //bad idea
                return;
            }
            
                /* DEBUG
                console.log(this.options.recordset);
                console.log(this.options.recordset.getRecords());
                console.log(this.options.recordset.toTimemap());
                */
            
            
            mapping.load(this.options.recordset == null? null: this.options.recordset.toTimemap() ,
                            this.options.selection,  //array of record ids
                                function(selected){
                                        $(that.document).trigger(top.HAPI4.Event.ON_REC_SELECT, 
                                        { selection:selected, source:that.element.attr('id') } );
                                });            
            
            this.recordset_changed = false;
        }

    }

    , _doVisualizeSelection: function (selection) {

            if(top.HEURIST4.util.isnull(this.options.recordset)) return;

            this.option("selection", selection);
            
            if(!this.element.is(':visible')
                || top.HEURIST4.util.isnull(this.mapframe) || this.mapframe.length < 1){
                    return;
            }
            
            this.mapframe[0].contentWindow.mapping.showSelection(this.options.selection);
    }    
    


    // events bound via _on are removed automatically
    // revert other modifications here
    , _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events);

        // remove generated elements
        this.mapframe.remove();
        this.framecontent.remove();

    }
    
    , loadanimation: function(show){
        if(show){
            //this.dosframe.hide();
            this.framecontent.css('background','url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.framecontent.css('background','none');
            //this.dosframe.show();
        }
    },
    

});
