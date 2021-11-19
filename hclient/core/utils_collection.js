/**
*  Utility for collection (stored in session)
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

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.collection) 
{

    window.hWin.HEURIST4.collection = {

        _collection: null,
        _collectionURL: 'hsapi/utilities/manageCollection.php',

        //
        //
        //
        getSelectionIds: function(_selection, msg, limit){

            var recIDs_list = [];
            if (_selection!=null) {
                recIDs_list = _selection.getIds();
            }

            if (recIDs_list.length == 0 && !Hul.isempty(msg)) {
                window.hWin.HEURIST4.msg.showMsg(msg, {default_palette_class:'ui-heurist-explore'});
                return null;
            }else if (limit>0 && recIDs_list.length > limit) {
                window.hWin.HEURIST4.msg.showMsg( window.hWin.HR('collection_limit') + limit
                                    , {default_palette_class:'ui-heurist-explore'});
            }else{
                return recIDs_list;
            }

        },

        collectionAdd: function(recIDs, _selection){

            if(!recIDs){
                var recIDs_list = window.hWin.HEURIST4.collection.getSelectionIds(_selection, 
                    window.hWin.HR('collection_select_hint'));
                if(window.hWin.HEURIST4.util.isempty(recIDs_list)) return;
                recIDs = recIDs_list.join(',')
            }

            var params = {db:window.hWin.HAPI4.database, fetch:1, add:recIDs};

            window.hWin.HEURIST4.collection.collectionUpdate(params);
        },

        collectionDel: function(recIDs, _selection){

            if(!recIDs){
                var recIDs_list = this.getSelectionIds(_selection,
                    window.hWin.HR('collection_select_hint2'));
                if(window.hWin.HEURIST4.util.isempty(recIDs_list)) return;
                recIDs = recIDs_list.join(',')
            }

            var params = {db:window.hWin.HAPI4.database, fetch:1, remove:recIDs};

            window.hWin.HEURIST4.collection.collectionUpdate(params);
        },

        collectionClear: function(){

            var params = {db:window.hWin.HAPI4.database, clear:1};
            window.hWin.HEURIST4.collection.collectionUpdate(params);
        },

        collectionShow: function(){

            if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.collection._collection)){

                if(true){
                    var url = window.hWin.HAPI4.baseURL + "?db=" + window.hWin.HAPI4.database + "&q=ids:"
                    +window.hWin.HEURIST4.collection._collection.join(',')+'&nometadatadisplay=true';

                    if(url.length>2083){
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            window.hWin.HR('collection_url_hint')
                            , null, window.hWin.HR('Warning')
                            , {default_palette_class:'ui-heurist-explore'});

                    }else{
                        window.open(url, '_blank');    
                    }    

                }else{
                    //this._query_request.w = 'all';
                    //that.reloadSearch('ids:'+this._collection.join(","));
                }
            }

        },

        //
        // save as filter
        //
        collectionSave: function(){

            if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.collection._collection)){

                var  widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');

                if(widget){
                    //call method editSavedSearch - save collection as search

                    // mode, groupID, svsID, squery
                    widget.svs_list('editSavedSearch', 'saved', null, null, 'ids:'
                        +window.hWin.HEURIST4.collection._collection.join(","));
                }
            }

        },

        //
        //
        //
        collectionUpdate: function(params){

            function __collectionOnUpdate(that, results) {
                if(!Hul.isnull(results)){
                    if(results.status == window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        window.hWin.HEURIST4.msg.showMsgErr(results);
                    }else{
                        window.hWin.HEURIST4.collection._collection = Hul.isempty(results.ids)?[]:results.ids;

                        $(window.hWin.document).trigger( window.hWin.HAPI4.Event.ON_REC_COLLECT, 
                            {collection:window.hWin.HEURIST4.collection._collection} );
                    }
                }
            }

            if(!params){
                params = {db:window.hWin.HAPI4.database, fetch:1};
            }

            window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL + window.hWin.HEURIST4.collection._collectionURL, 
                params, this, 
                __collectionOnUpdate);
        }
        /*
        collectionGet: function(){
        return window.hWin.HEURIST4.collection._collection;  
        }
        */

    }
}
