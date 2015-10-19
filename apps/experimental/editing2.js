
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/*

TODO: OLD - TO REMOVE

*/
function hEditing(_recdata) {
     var _className = "Editing",
         _version   = "0.4";

     var $container = null,
         recdata = null;     //hRecordSet

    /**
    * Initialization
    */
    function _init(container, _recdata) {
        if (typeof container==="string") {
            $container = $("#"+container);
        }else{
            $container = container;
        }
        if($container.length==0){
            $container = null;
            alert('Container element for editing not found');
        }
        _load(_recdata);
    }

    function _load(_recdata) {
        if(!$container) return;

        $container.empty(); //clear previous edit elements

        recdata = _recdata;

        if(!recdata) return;

        //create form, fieldset and input elements according to record structure

        var record = recdata.getFirstRecord();
        if(record){
            var recID = recdata.fld(record, 'rec_ID');
            var rectypeID = recdata.fld(record, 'rec_RecTypeID');
            var rectypes = recdata.getStructures();

            var rfrs = rectypes.typedefs[rectypeID].dtFields;
            var fi = rectypes.typedefs.dtFieldNamesToIndex;

            var order = rectypes.dtDisplayOrder[rectypeID];
            if(order){
                var i, l = order.length;

                //???? move outside????
                var $header = $('<div>')
                        .css('padding','0.4em')
                        .addClass('ui-widget-header ui-corner-all')
                        .appendTo($container);
                $('<div style="display:inline-block"><span>'
                        + rectypes.names[rectypeID]
                        +'</span><h3 id="recTitle">'
                        +recdata.fld(record, 'rec_Title')
                        +'</h3></div>')
                        .appendTo($header);

                var $toolbar = $('<div>')
                    .css('float','right')
                    .appendTo( $header );

                _create_toolbar($toolbar);

                $('<button>', {text:'Save'}).button().on("click", function(event){ _save(); } ).appendTo($toolbar);
                $('<button>', {text:'Cancel'}).button().appendTo($toolbar);
                //???? END move outside????

                var $formedit = $("<div>").appendTo($container);
                var $fieldset = $("<fieldset>").appendTo($formedit);

                for (i = 0; i < l; ++i) {
                    var dtID = order[i];
                    if (rfrs[dtID][fi['rst_RequirementType']] == 'forbidden') continue;

                    var values = recdata.fld(record, dtID);

                    $("<div>").editing_input(
                              {
                                recID: recID,
                                rectypeID: rectypeID,
                                dtID: dtID,
                                rectypes: rectypes,
                                values: values
                              })
                              .appendTo($fieldset);

                              /*
                    $("<div>"+recID+" "+rectypeID+"  "+
                            dtID+"  "+
                            rfrs[dtID][fi['rst_DisplayName']]+":  "+
                            val+"</div>").appendTo($container);
                            */
                }
            }


        }
    }

    function _verify(){
        return true;
    }

    function _save(){
        alert('save');
        return true;
    }


    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        load: function(_recdata){
            _load(_recdata);
        },

        verify: function(){
            _verify();
        },

        save: function(){
            _save();
        }

    }

    _init(_recdata);
    return that;  //returns object
}
