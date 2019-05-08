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
    },
    
    RT_MAP_DOCUMENT = 0,
    RT_MAP_LAYER = 0,
    DT_DATA_SOURCE = 0,
    RT_QUERY_SOURCE = 0,
    DT_QUERY_STRING = 0,
    
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
        DT_QUERY_STRING = window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING'];
        RT_QUERY_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE'];
        
        //_loadMapDocuments();
    }
    
    //
    // loads all map documents from server, init treeview and stores recordset in map_documents 
    //
    function _loadMapDocuments( onRefreshList ){
        
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
                        
                        if($.isFunction(onRefreshList)) onRefreshList.call(that, resdata);
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                }
            );           
        
    }
    
    //
    // returns content of mapdocument in fancytree data format
    //
    function _getTreeData( mapdoc_id ){
        
        var treedata = [];
        
        var resdata = map_documents_content[mapdoc_id];
        if(resdata){
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
                        $res['mapdoc_id'] = mapdoc_id; //reference to parent mapdoc

                        treedata.push($res);
                    }
                }
            }//for
        }
        return treedata;
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
                            var treedata = _getTreeData(mapdoc_id);
                            deferred.resolve( treedata ); //returns data t fancytree to render child layers for given mapdocument
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
    // opens map document - loads content (resolve deferred - to updated treeview) and add layers on map
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
                                                      mapdoc_recordset: resdata, //need to get fields
                                                      mapwidget: options.mapwidget});
                }
            }
        }//for
    }
    
    
    //public members
    var that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        loadMapDocuments: function( onRefreshList ){
            _loadMapDocuments( onRefreshList );    
        },
        
        openMapDocument: function(mapdoc_id, deferred){
            _openMapDocument(mapdoc_id, deferred);
        },
        
        isLoaded: function(mapdoc_id){
           return !window.hWin.HEURIST4.util.isnull( map_documents_content[mapdoc_id] );
        },
        
        //return layer or mapdocument layer
        getLayer: function(mapdoc_id, rec_id){
            
            if(map_documents_content[mapdoc_id]){
                var _record = null;
                if(rec_id>0){
                    _record = (map_documents_content[mapdoc_id]).getById( rec_id );
                }else{
                    _record = map_documents.getById( mapdoc_id );
                }
                if(_record) return _record; 
            }
            return null;
        },
        
        
        //
        // adds new layer to mapdoc
        // data - recordset, heurist query or json
        //
        addLayer: function(mapdoc_id, data, dataset_name){

            var curr_request;
            
            if( (typeof data.isA == "function") && data.isA("hRecordSet") ){
                    
                    var recset = data;
                    
                    if(recset.length()<2001){ //limit query by id otherwise use current query
                        curr_request = { w:'all', q:'ids:'+recset.getIds().join(',') };
                    }else{
                        curr_request = recset.getRequest();
                    }            
                
            }else if( window.hWin.HEURIST4.util.isObject(data)&& data['q']) {
                
                 curr_request = data;
            }
                
            if(!map_documents_content[mapdoc_id]){
                map_documents_content[mapdoc_id] = new hRecordSet(); //create new recordset
            }
            
            var recset = map_documents_content[mapdoc_id];
            
            var _record = recset.getById(dataset_name);
            
            if(_record){
                _record['d'][DT_QUERY_STRING] = curr_request;
                //remove previous result set from map
                _record['layer'].removeLayer();    
            }else{
                _record = {rec_Title: dataset_name, rec_RecTypeID:RT_MAP_LAYER,  d:{}};
                _record['d'][DT_QUERY_STRING] = curr_request;
                map_documents_content[mapdoc_id].addRecord(dataset_name, _record);
            }
            
            _record['layer'] = new hMapLayer2({rec_datasource: _record, 
                                              mapdoc_recordset: map_documents_content[mapdoc_id], //need to get fields
                                              mapwidget: options.mapwidget});
            
        },
         
        getTreeData: function( mapdoc_id )
        {
            return _getTreeData( mapdoc_id );
        },
        
        //@todo
        zoomToMapDocument: function(mapdoc_id){
           console.log('todo zoom to maodoc '+mapdoc_id); 
            //options.mapwidget.mapping('zoomToExtent', map_document_extent);  
        },

        //
        // remove map document data and remove it from map
        //
        closeMapDocument: function(){
            
        },

        removeEntry: function(groupID, data){

        },

    }//end public methods

    _init( _options );
    return that;  //returns object
};

        
        