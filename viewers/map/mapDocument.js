/**
* mapDocument.js - working with map document and dependent record types
* 
* loads list of map documents and theirs content (layers and datasources), 
* opens map document - creates mapLayers
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

/**
* put your comment there...
* 
* 
* @param _options

  private 
    _loadMapDocuments - loads all map document records
    _getTreeData - converts mapdocument recordset (list of layers) to treeview data
    _loadMapDocumentContent - loads all linked layer and datasources for mapdocument - store it in map_documents_content 
    _openMapDocument - call _loadMapDocumentContent add layer to map
    _addLayerRecord - add new layer to map_documents_content and on map
    _getSymbology - returns symbology for layer (or if not defined it returns general mapdocument symbology)
    _editSymbology - opens symbology editor for layer and then call layer.applyStyle
    
* 
* @returns {Object}
*/
function hMapDocument( _options )
{    
    var _className = "MapDocument",
    _version   = "0.4";

    // default options
    options = {
        container:null,  //@todo all ui via mapcontrol
        mapwidget:null,  //reference back to mapping.js
    },
    
    RT_MAP_DOCUMENT = 0,
    RT_TLCMAP_DATASET = 0,
    RT_MAP_LAYER = 0,
    DT_MAP_LAYER = 0,
    DT_DATA_SOURCE = 0,
    RT_QUERY_SOURCE = 0,
    DT_QUERY_STRING = 0,
    DT_SYMBOLOGY = 0, 
    DT_GEO_OBJECT = 0,
    DT_MAP_BOOKMARK = 0,
    DT_NAME = 0,
    
    DT_ZOOM_KM_POINT = 0,
    DT_MINIMUM_ZOOM = 0, //bounds for mapdoc and visibility for layers in km
    DT_MAXIMUM_ZOOM = 0,
    
    map_documents = null, //recordset - all loaded documents
    map_documents_content = {}, //mapdoc_id=>recordset with all layers and datasources of document
    //mapdoc_id - 0 current search, temp temporal mapspace, or record id
    
    _uniqueid = 9000000;   
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    function _init(_options) {
        
        options = $.extend(options, _options);
        
        options.container = $(options.container);
        
        RT_MAP_DOCUMENT = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'];
        RT_TLCMAP_DATASET = window.hWin.HAPI4.sysinfo['dbconst']['RT_TLCMAP_DATASET'];
        RT_MAP_LAYER = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'];
        DT_MAP_LAYER = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_LAYER'];
        DT_DATA_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATA_SOURCE'];
        DT_QUERY_STRING = window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING'];
        RT_QUERY_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE'];
        DT_SYMBOLOGY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY'];
        DT_NAME      = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
        DT_MAP_BOOKMARK = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_BOOKMARK'];
        DT_MINIMUM_ZOOM = window.hWin.HAPI4.sysinfo['dbconst']['DT_MINIMUM_ZOOM'];
        DT_MAXIMUM_ZOOM = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAXIMUM_ZOOM'];
        DT_ZOOM_KM_POINT = window.hWin.HAPI4.sysinfo['dbconst']['DT_ZOOM_KM_POINT'];
        
        //_loadMapDocuments();
    }
    
    //
    // loads all map documents from server
    //
    function _loadMapDocuments( onRefreshList ){
        
            if(!(RT_MAP_DOCUMENT>0)) return;
            
            var that = this;
            
            var request = {
                        q: 't:'+RT_MAP_DOCUMENT,w: 'a',
                        detail: [DT_GEO_OBJECT,DT_MAP_BOOKMARK,DT_SYMBOLOGY,DT_MINIMUM_ZOOM,DT_MAXIMUM_ZOOM,DT_ZOOM_KM_POINT], //fields_to_be_downloaded
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
    // returns content of mapdocument (map_documents_content) in fancytree data format
    // converts recordset to treeview data
    //
    function _getTreeData( mapdoc_id ){
        
        var treedata = [];
        
        var limit_for_search = 11;
        
        var resdata = map_documents_content[mapdoc_id];
        if(resdata){ //we may use resdata.each
            var idx, records = resdata.getRecords();
            for(idx in records){
                if(idx)
                {
                    var record = records[idx];
                    
                    if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER
                        || resdata.fld(record, 'rec_RecTypeID')==RT_TLCMAP_DATASET)
                    { //ignore sources
                        var recID  = resdata.fld(record, 'rec_ID'), recName = '';
                        if(record['d'] && record['d'][1]){
                            recName = record['d'][1][0];
                        }else{
                            recName = resdata.fld(record, 'rec_Title');
                            //console.log(record);
                            //continue;
                        }
                        
                        var $res = {};  
                        $res['key'] = recID;
                        $res['title'] = "<span style='font-style:italic;'>" + recName + "</span>";
                        $res['type'] = 'layer';
                        $res['mapdoc_id'] = mapdoc_id; //reference to parent mapdoc
                        
                        var layer_rec = that.getLayer(mapdoc_id, recID);
                        if(layer_rec && mapdoc_id==0){
                            $res['selected'] = (layer_rec['layer']).isVisible();  
                        }else{
                            $res['selected'] = true;
                        } 
                        
                        treedata.push($res);
                    }
                }
            }//for
            
            if(mapdoc_id==0 && treedata.length > limit_for_search){
                //remove invisible 
                var i = 0;
                while(i<treedata.length && treedata.length > limit_for_search){
                    if(!treedata[i]['selected'] && treedata[i]['key']<9000000){
                        treedata.splice(i,1);
                    }else{
                        i++;
                    }
                }
            }
            
        }
        return treedata;
    }
    

    //
    // load all linked layers, tlcmapdataset and datasources records for given map document
    // invoked from _openMapDocument and call the same method when data are recieved from server side
    // deferred object is required for treeview, it returns treeview data
    //    
    function _loadMapDocumentContent(mapdoc_id, deferred){

//{"any":[{"ids":mapdoc_id},{"all":{"t":RT_MAP_LAYER,"linkedfrom":mapdoc_id}}]},    //mapdoc and layer linked to given mapdoc     
//{"t":RT_MAP_LAYER,"linkedfrom":mapdoc_id},  //layers linked to given mapdoc

            var request = {
                        w: 'a',
                        detail: 'detail',
                        source: 'map_document',
                        rules: [{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE}]
            };

            if($.isArray(mapdoc_id)){ //this is load of temp mapspace with given set of layers
                //"t":RT_MAP_LAYER+','+RT_TLCMAP_DATASET,
                request['q'] = {"ids":mapdoc_id.join(',')};
                mapdoc_id = 'temp';
            }else{
                if(RT_TLCMAP_DATASET>0){
                    request['q'] = {"t":RT_MAP_LAYER+','+RT_TLCMAP_DATASET,"linkedfrom":mapdoc_id};
                }else{
                    request['q'] = {"t":RT_MAP_LAYER,"linkedfrom":mapdoc_id};  //layers linked to given mapdoc
                }
            }
            if(RT_TLCMAP_DATASET>0){
                //additional rule for tlc datasets
                request['rules'].push({"query":"linkedfrom:"+RT_TLCMAP_DATASET+"-"+DT_DATA_SOURCE});
            }

            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        var resdata = new hRecordSet(response.data);
                        
                        map_documents_content[mapdoc_id] = resdata;

                        _openMapDocument( mapdoc_id, deferred);
                        
                    }else {
                        map_documents_content[mapdoc_id] = 'error';
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        if(deferred) deferred.reject(); //deferred.resolve( [] );
                    }

                }
            );           
        
    }

    //
    // opens map document - loads content (resolve deferred - to updated treeview) and adds layers on map
    //
    function _openMapDocument(mapdoc_id, deferred){
        
        //get list of layers and datasets
        if($.isArray(mapdoc_id) || !map_documents_content[mapdoc_id]){ //if array this is set of layers for temp mapspace
            //map doc is not loaded yet
            _loadMapDocumentContent(mapdoc_id, deferred);    
            return;
        }else if(map_documents_content[mapdoc_id]=='error'){
            //prevent loop
            return;
        }
        
        _defineZooms( mapdoc_id );
        
        var resdata = map_documents_content[mapdoc_id];
        
        var idx, records = resdata.getRecords();
        for(idx in records){
            if(idx)
            {
                var record = records[idx];
                var recID  = resdata.fld(record, 'rec_ID');
                
                if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER
                    ||resdata.fld(record, 'rec_RecTypeID')==RT_TLCMAP_DATASET){
                    
                    var datasource_recID = resdata.fld(record, DT_DATA_SOURCE);    

                    var datasource_record = resdata.getById( datasource_recID );
                    
                    //creates and add layer to nativemap
                    //returns mapLayer object
                    record['source_rectype'] = resdata.fld(datasource_record, 'rec_RecTypeID');  //for icon in legend
                    record['layer'] = new hMapLayer2({rec_layer: record, 
                                                      rec_datasource: datasource_record, 
                                                      mapdoc_recordset: resdata, //need to get fields
                                                      mapwidget: options.mapwidget});
                }
            }
        }//for
        
        
        if(deferred){
            var treedata = _getTreeData(mapdoc_id);
            deferred.resolve( treedata ); //returns data to fancytree to render child layers for given mapdocument
        }
                                
    }

    //
    // Converts min/max zoom in km to nativemap zoom levels and assign to map
    //
    function _defineZooms( mapdoc_id ){
        if(mapdoc_id!='temp'){
            var record2 = map_documents.getById( mapdoc_id );
            if(DT_ZOOM_KM_POINT>0){
                var val = map_documents.fld(record2, DT_ZOOM_KM_POINT);
                if(parseFloat(val)>0){
                    options.mapwidget.mapping('option','zoomToPointInKM', parseFloat(val));    
                }
            }
            
            if(DT_MAXIMUM_ZOOM >0){
                var val = parseFloat(map_documents.fld(record2,DT_MAXIMUM_ZOOM  ));
                if(val>0.01){
                    var zoomNative = options.mapwidget.mapping('convertZoomToNative', val);
                    if(zoomNative>0){
                        options.mapwidget.mapping('defineMaxZoom', 'doc'+mapdoc_id, zoomNative);
                    }
                }
            }
            if(DT_MINIMUM_ZOOM >0){
                var val = parseFloat(map_documents.fld(record2,DT_MINIMUM_ZOOM ));
                if(val>0 && val!=90){ //90 old def value when this field was in degrees
                    var zoomNative = options.mapwidget.mapping('convertZoomToNative', val);
                    if(zoomNative>=0){
                        options.mapwidget.mapping('defineMinZoom', 'doc'+mapdoc_id, zoomNative);
                    }
                }
            }
        }
    }
    
    //
    // adds layer and datasource records to mapdocument recordset, adds map on map, refresh tree
    //
    // mapdoc_id - target map document
    // rec_ids - array of layer record ids, or single layer record id
    //
    function _addLayerRecord(mapdoc_id, rec_ids, callback){
        
            if(!(map_documents_content[mapdoc_id] && map_documents_content[mapdoc_id].isA("hRecordSet") )) return;
            if(!rec_ids) return;
            if(!$.isArray(rec_ids)) rec_ids = [rec_ids];
        
            var request = {
                        q: {"ids":rec_ids.join(',')},  
                        rules:[{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE}], //data sources linked to layers
                        w: 'a',
                        detail: 'detail',
                        source: 'map_document'};
                        
            if(RT_TLCMAP_DATASET>0){
                //additional rule for tlc datasets
                request['rules'].push({"query":"linkedfrom:"+RT_TLCMAP_DATASET+"-"+DT_DATA_SOURCE});
            }
                        
            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        var resdata = new hRecordSet(response.data);
                        
                        //add to map_document recordset
                        //var idx, records = resdata.getRecords();
                        resdata.each(function(recID, record){
                            
                                if(!map_documents_content[mapdoc_id].getById(recID)){
                                    //not exists in this mapdocument
                                
                                    var record2 = {rec_ID:recID,  
                                              rec_Title: resdata.fld(record, 'rec_Title'), 
                                              rec_RecTypeID: resdata.fld(record, 'rec_RecTypeID'),  
                                              d: record['d']};
                                    
                                    
                                    map_documents_content[mapdoc_id].addRecord2(recID, record2);
                                    
                                    if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER 
                                        || resdata.fld(record, 'rec_RecTypeID')==RT_TLCMAP_DATASET)
                                    {
                                        var datasource_recID = resdata.fld(record, DT_DATA_SOURCE);    
                                        var datasource_record = resdata.getById( datasource_recID );
                                        
                                        //creates and add layer to nativemap
                                        //returns mapLayer object
                                        record2['source_rectype'] = resdata.fld(datasource_record, 'rec_RecTypeID'); //for icon in legend
                                        record2['layer'] = new hMapLayer2({rec_layer: record, 
                                                                          rec_datasource: datasource_record, 
                                                                          mapdoc_recordset: resdata, //need to get fields
                                                                          mapwidget: options.mapwidget,
                                                                          not_init_atonce:true});
                                    }
                                }
                            
                        });
                                
                        
                        if($.isFunction(callback)){
                            callback.call(that, _getTreeData(mapdoc_id));
                        }
                        
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        if($.isFunction(callback)) callback.call(that);
                    }

                }
            );           
        
    }
    
    //
    //  returns sybology for given layer of map document, if layer not defined 
    //  if returns default symbology defined in map document
    //
    function _getSymbology( mapdoc_id, rec_id ){
        
        
        if(!rec_id){
            
            if(map_documents){
                var record2 = (mapdoc_id>0) ? map_documents.getById( mapdoc_id ) 
                                            : map_documents.getFirstRecord();
                var def_style = map_documents.fld(record2, DT_SYMBOLOGY);
                return window.hWin.HEURIST4.util.isJSON(def_style);
            }else{
                return false;
            }
        }
        
        var _recset = map_documents_content[mapdoc_id]; //layers
        var _record = _recset.getById( rec_id );
        
        if(!_record['source_rectype'] || _record['source_rectype'] == RT_QUERY_SOURCE){
            //for query of recordset symbology is stored in details
            
            var layer_style = _recset.fld(_record, DT_SYMBOLOGY);
            var layer_style = window.hWin.HEURIST4.util.isJSON(layer_style);
            if(!layer_style){
                if(_record['layer']){
                    layer_style = (_record['layer']).getStyle();    
                }else{
                    layer_style = {};
                }
            } 
        }else{
            layer_style = {rectypeIconUrl:window.hWin.HAPI4.iconBaseURL + _record['source_rectype'] };
        }
        
        return layer_style;
    }

    // get layer record, take symbology field and title 
    // open symbology editor
    // on exit 1) call mapLayer.applyStyles
    //         2) change title in tree and timeline
    function _editSymbology( mapdoc_id, rec_id, callback ){


        var _recset = map_documents_content[mapdoc_id];
        var _record = _recset.getById( rec_id );
        
        var layer_title = _recset.fld(_record, 'rec_Title');
        var layer_style = _getSymbology(  mapdoc_id, rec_id );

        var current_value = layer_style;//affected_layer.options.default_style;
        ///console.log(affected_layer);                   
        current_value.sym_Name = layer_title; //affected_layer.options.layer_name;
        //open edit dialog to specify symbology
        window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_value, true, function(new_value){

            var new_title = null, new_style = null;
            
            //rename in list
            if(!window.hWin.HEURIST4.util.isempty(new_value.sym_Name)
                && current_value.sym_Name!=new_value.sym_Name)
            {
                new_title = new_value.sym_Name;
                _recset.setFld(_record, 'rec_Title', new_value.sym_Name);

                //update label in timeline                                                
                options.mapwidget.mapping('updateTimelineLayerName', (_record['layer']).getNativeId(), new_value.sym_Name);
                
                delete new_value.sym_Name;
            }
            
            //update style
            _recset.setFld(_record, DT_SYMBOLOGY, new_value);
            (_record['layer']).applyStyle(new_value);
            
           //callback to update ui in mapManager
            if($.isFunction(callback)){
                callback( new_title, new_style );
            }
        });        
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
        
        //
        // create new virtual map document for given layer_ids
        //
        createVirtualMapDocument: function(layer_ids, deferred){
            that.closeMapDocument('temp'); //clear old one
            if(!$.isArray(layer_ids)){
                if(window.hWin.HEURIST4.util.isNumber(layer_ids)){
                    layer_ids = [parseInt(layer_ids)];
                }else{
                    layer_ids = layer_ids.split(',');
                }
            }
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(layer_ids)){
                _openMapDocument(layer_ids, deferred);    
                return;
            }else{
                return;
            }
            
            //if(map_documents==null) _loadMapDocuments(); //init list of mapdocs
        },
        
        //
        // adds search results or query as a new layer to mapdoc (usuallly "Search Results")
        // data - recordset, heurist query or json
        //
        // mapdoc_id - target map document
        // data - record set or heurist query
        //
        // returns record to use in mapping widget
        //
        addSearchResult: function(mapdoc_id, data, dataset_name, preserveViewport){

            var curr_request, original_heurist_query;
            
            if( (typeof data.isA == "function") && data.isA("hRecordSet") ){
                    
                    var recset = data;
                    
                    original_heurist_query = recset.getRequest(); //keep for possible 'save as layer'
                    
                    if(recset.length()<2001){ //limit query by id otherwise use current query
                        var MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
                        curr_request = { w:'all', q:'ids:'+recset.getIds(MAXITEMS).join(',') };
                    }else{
                        curr_request = original_heurist_query;
                    }            
                
            }else if( window.hWin.HEURIST4.util.isObject(data)&& data['q']) {
                
                 original_heurist_query = data;
                 curr_request = data;
            }
                
            if(!map_documents_content[mapdoc_id]){
                map_documents_content[mapdoc_id] = new hRecordSet(); //create new recordset - list of layers for mapdocument
            }
            
            var recset = map_documents_content[mapdoc_id];
            
            //dataset_name is unique within mapdoc
            var search_res = recset.getSubSetByRequest({'rec_Title':('='+dataset_name)}); 
            //var _record = recset.getById(dataset_name);
            var _record;
            if(search_res && search_res.length()>0){  //layer with such name already exists - replace
                
                var recID = search_res.getOrder();
                recID = recID[0];
                _record = recset.getById(recID);
                recset.setFld(_record, DT_QUERY_STRING, curr_request);
                //remove previous result set from map
                if(_record['layer']){ //ref to hMapLayer2
                    _record['layer'].removeLayer();    
                    delete _record['layer']; //clear
                }
            }else{
                //add new layer to mapdocument
                _record = {rec_ID:_uniqueid,  rec_Title:dataset_name, rec_RecTypeID:RT_MAP_LAYER,  d:{}};
                recset.setFld(_record, DT_QUERY_STRING, curr_request);
                _record = recset.addRecord(_uniqueid, _record);
                _uniqueid++;
            }
            _record['original_heurist_query'] = original_heurist_query;
            
            _record['source_rectype'] = RT_QUERY_SOURCE;
            
            //always preserve for mapdoc layers 
            preserveViewport = (mapdoc_id!=0) || (preserveViewport===true);
            
            recset.setFld(_record, 'layer',
                        new hMapLayer2({rec_datasource: _record, 
                                        mapdoc_recordset: recset, //need to get fields
                                        mapwidget: options.mapwidget,  //need to call back addGeoJson when data ara obtained from server
                                        mapdocument_id: mapdoc_id,
                                        preserveViewport:preserveViewport })); //zoom to current search 
                                              
                                              
            return _record;
        },
        
        //
        // returns reference to hMapLayer by mapdoc id and name
        //
        getLayerByName: function( mapdoc_id, dataset_name, dataset_id ){
            
            var recset = map_documents_content[mapdoc_id];
            //dataset_name is unique within mapdoc
            if(recset){
                
                var recID;
                if(dataset_id>0){
                    recID = dataset_id;
                }else{
                    var search_res = recset.getSubSetByRequest({'rec_Title':('='+dataset_name)}); 
                    if(search_res && search_res.length()>0){
                        recID = search_res.getOrder();
                        recID = recID[0];
                    }    
                }
                _record = recset.getById(recID);
                return _record?_record['layer']:null;
                
            }
            
            return null;
        },
        
        //
        // adds arbitrary recordset
        // it is used in Digital Harlem and Expert Nation when recordset is generated and prepared in custom way on client side
        // it converts given recordset to geojson and pass it to to mapping.addGeoJson
        //
        // mapdoc_id - target map document
        // recordset - record set
        //
        // returns record to use in mapping widget
        //
        addRecordSet: function(mapdoc_id, recordset, dataset_name){

            if( (typeof recordset.isA == "function") && recordset.isA("hRecordSet") ){
                    
            }
                
            if(!map_documents_content[mapdoc_id]){
                map_documents_content[mapdoc_id] = new hRecordSet(); //create new recordset
            }
            
            var recset = map_documents_content[mapdoc_id];
            
            //dataset_name is unique within mapdoc
            var search_res = recset.getSubSetByRequest({'rec_Title':('='+dataset_name)}); 
            
            //var _record = recset.getById(dataset_name);
            var _record;
            if(search_res && search_res.length()>0){  //layer with such name already exists - replace
                
                var recID = search_res.getOrder();
                recID = recID[0];
                _record = recset.getById(recID);
                recset.setFld(_record, DT_QUERY_STRING, 'N/A');
                //remove previous result set from map
                if(_record['layer']){
                    _record['layer'].removeLayer();    
                    delete _record['layer']; //clear
                }
            }else{
                _record = {rec_ID:_uniqueid,  rec_Title:dataset_name, rec_RecTypeID:RT_MAP_LAYER,  d:{}};
                recset.setFld(_record, DT_QUERY_STRING, 'N/A');
                _record = recset.addRecord(_uniqueid, _record);
                _uniqueid++;
            }
            
            _record['source_rectype'] = RT_QUERY_SOURCE;  //for icon in legend
            recset.setFld(_record, 'layer',
                        new hMapLayer2({recordset: recordset,
                                        rec_datasource: _record, 
                                        mapdoc_recordset: recset, //need to get fields
                                        mapwidget: options.mapwidget,
                                        preserveViewport:(mapdoc_id!=0) })); //zoom to current search
                                              
                                              
            return _record;
        },        
         
         
        //
        //
        // 
        getTreeData: function( mapdoc_id )
        {
            return _getTreeData( mapdoc_id );
        },
        
        //
        // show/hide entire map document
        //
        setMapDocumentVisibility: function( mapdoc_id, is_visibile ){
            //loop trough all 
            var resdata = map_documents_content[mapdoc_id];
            var idx, records = resdata.getRecords();
            for(idx in records){
                if(idx)
                {
                    var record = records[idx];
                    var rtype = resdata.fld(record, 'rec_RecTypeID');
                    if( (rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET) && record['layer']){
                        (record['layer']).setVisibility( is_visibile ); //set all layers visible
                    }
                }
            }
            
            if(is_visibile){
                _defineZooms( mapdoc_id );
            }else{
                options.mapwidget.mapping('defineMaxZoom', 'doc'+mapdoc_id, -1); //remove
                options.mapwidget.mapping('defineMinZoom', 'doc'+mapdoc_id, -1); //remove
            }
            
        },


        //
        // update layer visibility for all active map documents according to current zoom 
        // this method is called from zoomend event of native map
        //
        updateLayerVisibility: function( current_zoom, mapdoc_id ){
            
            if(!(DT_MAXIMUM_ZOOM>0 && DT_MINIMUM_ZOOM>0)) return;

            function __updateLayers(resdata){
                if(resdata){
                    var idx, records = resdata.getRecords();
                    for(idx in records){
                        if(idx)
                        {
                            var record = records[idx];
                            var rtype = resdata.fld(record, 'rec_RecTypeID');
                            if( (rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET) && record['layer'])
                            {
                                (record['layer']).setVisibilityForZoomRange( current_zoom );
                            }
                        }
                    }
                }
            }
            
            if(mapdoc_id>0){
                var resdata = map_documents_content[mapdoc_id];
                __updateLayers(resdata);
            }else{ 
                //loop trough all 
                var m_ids = Object.keys(map_documents_content);
                for(var k in m_ids){
                    if(m_ids[k]!='temp'){
                        var resdata = map_documents_content[ m_ids[k] ];
                        __updateLayers(resdata);
                    }
                }
            }

        },

        
        //
        // remove map document data and remove it from map
        //
        closeMapDocument: function( mapdoc_id ){
            //loop trough all 
            var resdata = map_documents_content[mapdoc_id];
            if(resdata){
                var idx, records = resdata.getRecords();
                for(idx in records){
                    if(idx)
                    {
                        var record = records[idx];
                        var rtype = resdata.fld(record, 'rec_RecTypeID');
                        if( (rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET) && record['layer']){
                            (record['layer']).removeLayer();
                            delete record['layer'];
                        }
                    }
                }
                map_documents_content[mapdoc_id] = null;
            }
         
            if(mapdoc_id!='temp'){
                options.mapwidget.mapping('defineMaxZoom', 'doc'+mapdoc_id, -1); //remove
                options.mapwidget.mapping('defineMinZoom', 'doc'+mapdoc_id, -1); //remove
            }
            
        },

        //
        //
        //
        removeLayer: function(mapdoc_id, rec_id){
            
            if(rec_id>0){
                var layer_rec = that.getLayer(mapdoc_id, rec_id);
                if(layer_rec){
                    (layer_rec['layer']).removeLayer();
                    delete layer_rec['layer']; 
                } 
                (map_documents_content[mapdoc_id]).removeRecord( rec_id );
            }
        },
        
        // 
        // returns default style for mapdoc or layer
        //
        getSymbology: function( mapdoc_id, rec_id ){
            return _getSymbology( mapdoc_id, rec_id );    
        },
        
        // get layer record, take symbology field and title 
        // open symbology editor
        // on exit 1) call mapLayer.applyStyles
        //         2) change title in tree and timeline
        editSymbology: function( mapdoc_id, rec_id, callback ){
            _editSymbology( mapdoc_id, rec_id, callback );    
        },
        
        
        //
        //
        //
        zoomToMapDocument: function(mapdoc_id){

            var mapdoc_extent = null;
            
            if(map_documents!=null && mapdoc_id!='temp'){ //for temp always zoom to real extent
            
                var record2 = map_documents.getById( mapdoc_id );

                mapdoc_extent = window.hWin.HEURIST4.geo.getWktBoundingBox(
                            map_documents.getFieldGeoValue(record2, DT_GEO_OBJECT));
                if(mapdoc_extent==null){
                    mapdoc_extent = window.hWin.HEURIST4.geo.getHeuristBookmarkBoundingBox(
                            map_documents.fld(record2, DT_MAP_BOOKMARK));
                }
            }    
                
            if(mapdoc_extent!=null){ 

                    //extent is taken from mapdocument
                    options.mapwidget.mapping('zoomToBounds', mapdoc_extent);
                
            }else{
                //neither bbox nor bookmark are defined
                //find all layer ids and zoom to summary extent
                var ids = that.getNativeIdsForDocument( mapdoc_id );
                
                if(ids.length>0)
                    options.mapwidget.mapping('zoomToLayer', ids);
            }
        },
        
             
        //
        // returns layer record OR mapdocument record (if rec_id is not defined)
        //
        getLayer: function(mapdoc_id, rec_id){
            
            if(map_documents_content[mapdoc_id]){
                var _record = null;
                if(rec_id>0){
                    _record = (map_documents_content[mapdoc_id]).getById( rec_id );
                }else if(map_documents) {
                    _record = map_documents.getById( mapdoc_id );
                }
                if(_record) return _record; 
            }
            return null;
        },

        //
        // returns all records for given mapspace (layers and datasources)
        //
        getMapDocumentRecordset: function(mapdoc_id){
            return map_documents_content[mapdoc_id];
        },
        
        
        //
        //
        //
        getNativeIdsForDocument: function(mapdoc_id){
                var resdata = map_documents_content[mapdoc_id];
            
                var ids = [];
                if(resdata){
                    var idx, records = resdata.getRecords();
                    for(idx in records){
                        if(idx)
                        {
                            var record = records[idx];
                            var rtype = resdata.fld(record, 'rec_RecTypeID');
                            if( (rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET) && record['layer']){
                                ids.push((record['layer']).getNativeId());
                            }
                        }
                    }
                }
                return ids;            
        },
        
        //
        // Add new layer record to map document
        //
        // Opens record selector popup and adds selected layer to given mapdoc
        //
        selectLayerRecord: function(mapdoc_id, callback){
            
            var popup_options = {
                select_mode: 'select_single', //select_multi
                select_return_mode: 'recordset',
                edit_mode: 'popup',
                selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                title: window.hWin.HR('Select or create a layer record'),
                rectype_set: [RT_MAP_LAYER, RT_TLCMAP_DATASET],
                parententity: 0,
                
                onselect:function(event, data){
                         if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            var recordset = data.selection;
                            var record = recordset.getFirstRecord();
                            var targetID = recordset.fld(record,'rec_ID');
                            
                            var request = {a: 'add',
                                        recIDs: mapdoc_id,
                                        dtyID:  DT_MAP_LAYER,
                                        val:    targetID};
                            
                            window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                    if(response.status == hWin.ResponseStatus.OK){
                                        //refresh treeview - add layer to mapdocument
                                        _addLayerRecord(mapdoc_id, targetID, null);
                                    }else{
                                        hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                            });                             
                         }
                },

                onClose:function(event){

                    if($.isFunction(callback)) callback.call(that);
                }
            };//popup_options

                    
            var usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_records', 
                {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

            popup_options.width = Math.max(usrPreferences.width,710);
            popup_options.height = usrPreferences.height;
            
            window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
        },
        
        //
        // adds new layers (by record ids) to mapdcoument
        //
        addLayerRecords: function(mapdoc_id, layers_ids, callback ){

            var to_remove = [];
            //remove from map_document
            map_documents_content[mapdoc_id].each(function(recID, record){
                 if(recID<9000000 && window.hWin.HEURIST4.util.findArrayIndex(recID, layers_ids)<0){
                     var rtype = record['rec_RecTypeID'];
                     if(rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET){
                         to_remove.push(recID);
                     }
                 }
            });
            for (var idx=0; idx<to_remove.length; idx++){
                that.removeLayer(mapdoc_id, to_remove[idx]);    
            }
            
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(layers_ids)){
                _addLayerRecord(mapdoc_id, layers_ids, callback);
            }else{
                callback.call();
            }
        },
        
        //
        //  saves current result set as layer+dataset records
        //
        saveResultSetAsLayerRecord: function(rec_id, callback){
            
            var recset = map_documents_content[0];
            if(!recset) return;
            
            var _record = recset.getById(rec_id);

            var r_name =  recset.fld(_record, 'rec_Title'),
                r_query = _record['original_heurist_query']; //recset.fld(_record, DT_QUERY_STRING),
                r_style = recset.fld(_record, DT_SYMBOLOGY);
                
                
            r_query = window.hWin.HEURIST4.util.hQueryStringify(r_query);
            if(window.hWin.HEURIST4.util.isJSON(r_style)) r_style = JSON.stringify(r_style);
            
            if(window.hWin.HEURIST4.util.isempty( r_query )) return;
            var bnd = _record['layer'].getBounds('wkt');
            if(!bnd){
                return;
            }
            
            
            var details = {};
            details['t:'+DT_NAME] = [ r_name ];
            details['t:'+DT_QUERY_STRING] = [ r_query ];
            details['t:'+DT_GEO_OBJECT] = ['r '+bnd ];
            
            var request = {a: 'save',    //add new relationship record
                        ID:0, //new record
                        RecTypeID: RT_QUERY_SOURCE,
                        //RecTitle: recset.fld(_record,'rec_Title'),
                        details: details};
            
            
            //save datasource record
            window.hWin.HAPI4.RecordMgr.saveRecord(request, function(response){
                    if(response.status == hWin.ResponseStatus.OK){
                        var datasource_recID = response.data; //add rec id
                        
                        var details = {};
                        details['t:'+DT_NAME] = [ r_name ];
                        details['t:'+DT_SYMBOLOGY] = [ r_style ];
                        details['t:'+DT_DATA_SOURCE] = [ datasource_recID ];
                            
                        var request = {a: 'save',    //add new relationship record
                                    no_validation: true,
                                    ID:0, //new record
                                    RecTypeID: RT_MAP_LAYER,
                                    details: details};
                        
                        window.hWin.HAPI4.RecordMgr.saveRecord(request, function(response){
                            if(response.status == hWin.ResponseStatus.OK){
                                //open new record editor
                                window.hWin.HEURIST4.ui.openRecordEdit(response.data);
                                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Records saved'));
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                            if($.isFunction(callback)) callback.call(that);
                        });
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        if($.isFunction(callback)) callback.call(that);
                    }
            });
        }
          
    }//end public methods

    _init( _options );
    return that;  //returns object
};

               