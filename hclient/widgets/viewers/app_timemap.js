/**
* app_timemap.js - load map + timeline into an iframe in the interface.
* This widget acts as a wrapper for hclient/framecontent/map.php
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


$.widget( "heurist.app_timemap", {

    // default options
    options: {
        recordset: null,
        selection: null, //list of record ids
        layout:null, //['header','map','timeline']
        startup:0,         //map document loaded on map init
        autoupdate:true,   //update content on global search events   ON_REC_SEARCHSTART (clear) and ON_REC_SEARCH_FINISH (add data)
        eventbased:true
    },

    _events: null,

    recordset_changed: true,

    // the constructor
    _create: function() {

        var that = this;

        //???? this.element.hide();

        this.framecontent = $('<div>').addClass('frame_container')
        //.css({position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
        //     'background':'url('+window.hWin.HAPI4.baseURL+'assets/loading-animation-white.gif) no-repeat center center'})
        .appendTo( this.element );

        if($(".header"+that.element.attr('id')).length===0){
            this.framecontent.css('top',0);
        }


        this.mapframe = $( "<iframe>" )
        .attr('id', 'map-frame')
        .appendTo( this.framecontent );
          
        this.loadanimation(true);
          
        if(this.options.eventbased){

            this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
            + ' ' + window.hWin.HAPI4.Event.ON_SYSTEM_INITED;

            if(this.options.autoupdate){
                this._events = this._events
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART;
            }

            $(this.document).on(this._events, function(e, data) {

                if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
                {
                    if(that.options.recordset != null && !window.hWin.HAPI4.has_access()){ //logout
                        that.recordset_changed = true;
                        that.option("recordset", null);
                        that._refresh();
                    }

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                    that.recordset_changed = true;
                    that.option("recordset", data); //hRecordSet
                    that._refresh();
                    that.loadanimation(false);

                    // Search start
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                    that.option("recordset", null);
                    that.option("selection", null);
                    if(data && data.q!='')  {
                        that.loadanimation(true);
                    }else{
                        that.recordset_changed = true;
                        that._refresh();
                    }
                    //???? that._refresh();

                    // Record selection
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                    if(data){
                        if(data.source!=that.element.attr('id')) { //selection happened somewhere else
                            //console.log("_doVisualizeSelection");
                            that._doVisualizeSelection( window.hWin.HAPI4.getSelection(data.selection, true) );
                        }
                    }else{
                        that.option("selection",  null);
                    }
                }else if (e.type == window.hWin.HAPI4.Event.ON_SYSTEM_INITED){
                    that._refresh();

                }

            
            });
        }
        // (this.mapframe).load(that._initmap);
        // init map on frame load
        this._on( this.mapframe, {
                load: function(){
                    //that.loadanimation(false);
                    this.recordset_changed = true;
                    this._refresh();   // this._initmap
                }
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

            if( this.mapframe.attr('src') ){  //frame already loaded
                this._initmap()
            }else {
                var url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/map.php?db='+window.hWin.HAPI4.database;
                if(this.options.layout){
                    if( this.options.layout.indexOf('timeline')<0 )
                        url = url + '&notimeline=1';
                    if( this.options.layout.indexOf('header')<0 )
                        url = url + '&noheader=1';
                }

                (this.mapframe).attr('src', url);
            }
        }

    },
    
    _reload_frame: function(){
        if(this.element.is(':visible')){
            
            window.hWin.HEURIST4.msg.showMsgFlash('Reloading map to apply new settings', 2000);
            
            this.recordset_changed = true;
            this.mapframe.attr('src', null);
        }
    },

    _initmap: function(){

        if( !window.hWin.HEURIST4.util.isnull(this.mapframe) && this.mapframe.length > 0 ){


            var mapping = this.mapframe[0].contentWindow.mapping;

            var that = this;

            if(!mapping){
                setTimeout(function(){ that._initmap()}, 1000); //bad idea
                return;
            }

            mapping.load( null, //mapdataset,
                this.options.selection,  //array of record ids
                this.options.startup,    //map document on load
                function(selected){  //callback if something selected on map
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT,
                        { selection:selected, source:that.element.attr('id') } );
                },
                function(){ //callback function on native map init completion
                    var params = {id:'main', recordset:that.options.recordset, title:'Current query'};
                    that.addRecordsetLayer(params, -1);
                }
            );

            this.recordset_changed = false;
        }

    }

    , _doVisualizeSelection: function (selection) {

        if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

        this.option("selection", selection);

        if(!this.element.is(':visible')
            || window.hWin.HEURIST4.util.isnull(this.mapframe) || this.mapframe.length < 1){
            return;
        }

        this.mapframe[0].contentWindow.mapping.showSelection(this.options.selection);  //see hclient/framecontent/map.js
    }



    // events bound via _on are removed automatically
    // revert other modifications here
    , _destroy: function() {

        this.element.off("myOnShowEvent");
        if(this._events)  $(this.document).off(this._events);

        // remove generated elements
        this.mapframe.remove();
        this.framecontent.remove();

    }

    , loadanimation: function(show){
       
        if(show){
            this.mapframe.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
            //this.mapframe.css('cursor', 'progress');
        }else{
            //this.framecontent.css('cursor', 'auto');
            this.mapframe.css('background','none');
        }
    }

    /**
    * public method
    */

    , reloadMapFrame: function(){
        this._reload_frame();    
    }
    
    , getMapDocumentDataById: function(mapdocument_id){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            return mapping.map_control.getMapDocumentDataById(mapdocument_id);
        }else{
            return null;
        }
    }
    
    , loadMapDocumentById: function(recId){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            mapping.map_control.loadMapDocumentById(recId);  //see hclient/framecontent/map.js
        }
    }

    /**
    * Add dataset on map
    * params = {id:$.uniqueId(), title:'Title for Legend', query: '{q:"", rules:""}'}
    */
    , addQueryLayer: function(params){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            mapping.map_control.addQueryLayer(params);
        }
    }

    , addRecordsetLayer: function(params){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            mapping.map_control.addRecordsetLayer(params);
        }
    }
    
    , editLayerProperties: function( dataset_id, legendid, callback ){
        var mapping = this.mapframe[0].contentWindow.mapping;
        if(mapping && mapping.map_control){
            mapping.map_control.editLayerProperties(dataset_id, legendid, callback);
        }
    }


});
