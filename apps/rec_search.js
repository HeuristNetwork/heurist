/**
* Combination of search and rec_list. This widget is used in editing_input.
* But may be used as application if you add global event trigger and listener for document
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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


$.widget( "heurist.rec_search", {

    // default options
    options: {
        isdialog: false,
        search_domain: 'a',
        rectype_set: null,
        onselect: null
    },

    // the constructor
    _create: function() {

        var that = this;

        this.wcontainer = $("<div>");

        if(this.options.isdialog){

            this.wcontainer
            .css({overflow: 'none !important', width:'100% !important'})
            //.attr('id','mydialog')
            .appendTo($("body"))
            .dialog({
                autoOpen: false,
                height: 640,
                width: 640,
                modal: true,
                title: top.HR("Select record"),
                // position: { my: "center", at: "center", of: window },
                resizeStop: function( event, ui ) {
                    that.wcontainer.css('width','100%');
                }
            });
        }else{
            this.wcontainer.appendTo( this.element );
        }

        this.wsearch = $( "<div>")
        .css({width:'99%', height:'2.5em'})
        .appendTo( this.wcontainer );

        this.wsearch.search({
            search_domain: this.options.search_domain, //current search domain
            search_domain_set: 'a,b', //,r,s',
            isrectype: true,  // show rectype selector
            rectype_set: this.options.rectype_set, // comma separated list of rectypes, null - all
            isapplication: false, // send and recieve the global events
            searchdetails: null, //get only record headers

            onsearch: function(event){
                that.wresult.rec_list("option", "recordset", null);
                that.wresult.rec_list("loadanimation", true);
                //that.wresult.option("recordset", null);
                //that.wresult.loadanimation(true);
            },
            onresult: function(event, data){
                that.wresult.rec_list("option", "recordset", data);
                that.wresult.rec_list("loadanimation", false);
                //that.wresult.option("recordset", data);
                //that.wresult.loadanimation(false);
            }
        });


        this.wresult = $( "<div>")
        .css({width:'99%'}) //, position:'absolute', top:'2.7em', bottom:0})
        .appendTo( this.wcontainer );

        this.wresult.rec_list({
            actionbuttons: "add,sort,view", //list of visible buttons
            multiselect: false,
            isapplication: false,

            onselect: function(event, data){

                that._trigger( "onselect", event, data );
                if(that.options.isdialog){
                    that.wcontainer.dialog("close");
                }
            }
        });

        this._refresh();

    }, //end _create

    /* private function */
    _refresh: function(){
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.wsearch.remove();
        this.wresult.remove();
        this.wcontainer.remove();
    },

    show: function(){
        if(this.options.isdialog){
            this.wsearch.search("option", { rectype_set: this.options.rectype_set });
            this.wcontainer.dialog("open");
        }else{
            //fill selected value this.element
        }
    }

});
