/**
* Button - logo which opens heurist-about dialogue
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


$.widget( "heurist.about", {

    // default options
    options: {
    },

    // the constructor
    _create: function() {

        var that = this;

        this.element
        // prevent double click to select text
        .disableSelection();

        this.div_logo = $( "<div>")
        .addClass('logo')
        .css('width','150px')
        .css('float','left')
        .appendTo( this.element )
        .button();

        // bind click events
        this._on( this.div_logo, {
            click: function(){$( "#heurist-about" ).dialog("open");}
        });

        //'padding-left':'15px', 'display':'inline-block',  'vertical-align': 'middle'
        this.div_dbname = $( "<div>").css({'float':'left', 'padding-left':'2em', 'text-align':'center' }).appendTo(this.element);

        $("<div>").css({'font-size':'1.6em', 'font-style':'italic'}).text(top.HAPI.database).appendTo( this.div_dbname );
        $("<div>").css({'font-size':'0.8em'}).text("v"+top.HAPI.sysinfo.version).appendTo( this.div_dbname );

        this._refresh();

    }, //end _create

    /* private function */
    _refresh: function(){
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.div_logo.remove();
    }

});
