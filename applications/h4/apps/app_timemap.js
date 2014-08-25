/**
* Time Map Widget
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
        recordset: null
    },

    recordset_changed: true,

    // the constructor
    _create: function() {

        var that = this;

        this.element.hide();

        this.framecontent = $(document.createElement('div'))
        .addClass('frame_container')
        .appendTo( this.element );

        this.mapframe = $( "<iframe>" )
        .attr('id', 'map-frame')
        //.attr('src', 'php/mapping.php?db='+top.HAPI4.database)
        .appendTo( this.framecontent );


        $(this.document).on(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT+' '+top.HAPI4.Event.ON_REC_SEARCHRESULT, function(e, data) {

            if(e.type == top.HAPI4.Event.LOGOUT)
            {
                if(that.options.recordset != null){
                    that.recordset_changed = true;
                    that.option("recordset", null);
                }

            }else if(e.type == top.HAPI4.Event.ON_REC_SEARCHRESULT)
            {
                that.recordset_changed = true;
                that.option("recordset", data); //hRecordSet
                that._refresh();
            }
        });

        // (this.mapframe).load(that._initmap);
        this._on( this.mapframe, {
            load: this._initmap
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

        if ( this.element.is(':visible') && this.recordset_changed) {

            if( this.mapframe.attr('src') ){
                this._initmap()
            }else {
                (this.mapframe).attr('src', 'php/mapping.php?db='+top.HAPI4.database);
            }
        }

    },

    _initmap: function(){
        var mapping = document.getElementById('map-frame').contentWindow.mapping;
        //var mapping = $(this.mapframe).contents().mapping;

        if(mapping){

            if(this.options.recordset == null){
                mapping.load();
            }else{
                mapping.load(this.options.recordset.toTimemap());
            }
            this.recordset_changed = false;

        }
    },



    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(top.HAPI4.Event.LOGIN+' '+top.HAPI4.Event.LOGOUT+' '+top.HAPI4.Event.ON_REC_SEARCHRESULT);

        // remove generated elements
        this.mapframe.remove();
        this.framecontent.remove();

    }

});
