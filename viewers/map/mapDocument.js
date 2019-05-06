/**
* mapDocument.js - working with map document and dependent record types
* 
* loads list of map documents and theirs content (layers and datasources), 
* opens map document - creates mapLayers
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

function hMapDocument( _options )
{    
    var _className = "MapDocument",
    _version   = "0.4";

    // default options
    options = {
        container:null,  //@todo all ui via mapcontrol
        mapwidget:null, 
        onRefreshList:null //callback  
    },
    
    RT_MAP_DOCUMENT = 0,
    RT_MAP_LAYER = 0,
    DT_DATA_SOURCE = 0,
    
    map_documents = null, //recordset - all loaded documents
    map_documents_content = {}; //mapdoc_id=>recordset with all layers and datasources of document
    //map_documents_layers = {};  //native map layers
    
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    function _init(_options) {
        
        options = $.extend(options, _options);
        
        options.container = $(options.container);
        
        RT_MAP_DOCUMENT = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'];
        RT_MAP_LAYER = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'];
        DT_DATA_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATA_SOURCE'];
        
        _loadMapDocuments();
    }
    
    //
    // loads all map documents from server, init treeview and stores recordset in map_documents 
    //
    function _loadMapDocuments(){
        
            if(!(RT_MAP_DOCUMENT>0)) return;
            
            var that = this;
        
            var request = {
                        q: 't:'+RT_MAP_DOCUMENT,w: 'a',
                        detail: 'header',
                        source: 'map_document'};
            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        var resdata = new hRecordSet(response.data);
                        map_documents = resdata;
                        
                        if($.isFunction(that.options.onRefreshList)) that.options.onRefreshList.call(that, resdata);
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                }
            );           
        
    }

    //
    // load all linked layers and dataset records for given map document
    // invoked from _openMapDocument and call the same method when data are recieved from server side
    // deferred object is required for treeview, it returns treeview data
    //    
    function _loadMapDocumentContent(mapdoc_id, deferred){

//{"any":[{"ids":11},{"all":{"t":"25","linkedfrom":11}}]}        
        
            var request = {
                        q: {"t":RT_MAP_LAYER,"linkedfrom":mapdoc_id},  //layers linked to given mapdoc
                        rules:[{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE}], //data sources linked to layers
                        w: 'a',
                        detail: 'detail',
                        source: 'map_document'};
            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        var resdata = new hRecordSet(response.data);
                        
                        map_documents_content[mapdoc_id] = resdata;
                        
                        if(deferred){
                            var treedata = [];
                            
                            var idx, records = resdata.getRecords();
                            for(idx in records){
                                if(idx)
                                {
                                    var record = records[idx];
                                    
                                    if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER){
                                        var recID  = resdata.fld(record, 'rec_ID'),
                                        recName = resdata.fld(record, 'rec_Title');
                                        
                                        var $res = {};  
                                        $res['key'] = recID;
                                        $res['title'] = recName;
                                        $res['type'] = 'layer';

                                        treedata.push($res);
                                    }
                                }
                            }//for
                            
                            deferred.resolve( treedata );
                        }
                        
                        _openMapDocument( mapdoc_id );
                        
                    }else {
                        map_documents_content[mapdoc_id] = 'error';
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        if(deferred) deferred.reject( ); //deferred.resolve( [] );
                    }

                }
            );           
        
    }

    //
    // 
    //
    function _openMapDocument(mapdoc_id, deferred){
        
        //get list of layers and datasets
        if(!map_documents_content[mapdoc_id]){
            //map doc is bot loaded yet
            _loadMapDocumentContent(mapdoc_id, deferred);
            return;
        }else if(map_documents_content[mapdoc_id]=='error'){
            //prevent loop
            return;
        }
        
        var resdata = map_documents_content[mapdoc_id];
        
        var idx, records = resdata.getRecords();
        for(idx in records){
            if(idx)
            {
                var record = records[idx];
                
                if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER){
                    var recID  = resdata.fld(record, 'rec_ID'),
                        datasource_recID = resdata.fld(record, DT_DATA_SOURCE);    

                    var datasource_record = resdata.getById( datasource_recID );
                    
                    //creates and add layer to nativemap
                    //returns mapLayer object
                    record['layer'] = new hMapLayer2({rec_layer: record, 
                                                      rec_datasource: datasource_record, 
                                                      map_recordset: resdata, //need to get fields
                                                      mapwidget: options.mapwidget});
                }
            }
        }//for
    }
    
    //
    //
    //
    function _layerAction( e ){
        
        var layerid = $(e.target).attr('data-layerid');
        var action  = $(e.target).attr('data-action'); 
        var value = null;
        if(action=='visibility'){
            value = $(e.target).is(':checked');
        }
     
        that.layerAction( layerid, action, value );
    }
        
    //
    // returns index
    //
    function _findInSearchResults(byId, byName){
        var idx = -1;
        for (var k=0; k<map_searchresults.length; k++){
            if( map_searchresults[k]['layer_id'] == byId || 
                map_searchresults[k]['layer_name'] == byName){
                idx = k;
                break;
            }
        }
        return idx;
    }
    
    
    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        loadMapDocuments: function(){
            _loadMapDocuments();    
        },
        
        openMapDocument: function(mapdoc_id, deferred){
            _openMapDocument(mapdoc_id, deferred);
        },
        
        isLoaded: function(mapdoc_id){
           return !window.hWin.HEURIST4.util.isnull( map_documents_content[mapdoc_id] );
        },

        //
        // remove map document data and remove it from map
        //
        closeMapDocument: function(){
            
        },

        //
        // data - layer name and reference
        // 
        defineContent: function(){

        },

        removeEntry: function(groupID, data){

        },

    }//end public methods

    _init( _options );
    return that;  //returns object
};

        
        