<?php

require_once (dirname(__FILE__).'/../../vendor/autoload.php'); //for geoPHP
require_once (dirname(__FILE__).'/../utilities/mapSimplify.php');
require_once (dirname(__FILE__).'/../utilities/mapCoordConverter.php');
require_once (dirname(__FILE__).'/../../common/php/Temporal.php');

/**
* recordsExport.php - produces output in json, geojson, xml, gephi formats
* 
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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

/**
* 
*  setSession - work with different database
*  output - main method
* 
*/
class RecordsExport {
    private function __construct() {}    
    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;
    
    private static $defRecTypes = null;
    private static $defDetailtypes = null;
    private static $defTerms = null;
    
//
//
//    
private static function initialize()
{
    if (self::$initialized)
        return;

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->get_mysqli();
    self::$initialized = true;
}

//
// set session different that current global one (to work with different database)
//
public static function setSession($system){
    self::$system  = $system;
    self::$mysqli = $system->get_mysqli();
    self::$initialized = true;
}

//
// output records as json or xml 
//
// $data - recordSearch response
//
// $params: 
//    format - json|geojson|xml|gephi
//    linkmode = direct, direct_links, none, all
//    defs  0|1  include database definitions
//    file  0|1
//    filename - export into file with given name
//    zip   0|1
//    depth 0|1|2|...all  
//
//    tlcmap 0|1  convert tlcmap records to layer
//    restapi 0|1  - json output in format {records:[]}
//
// prefs for geojson, json
//    extended 0 as is (in heurist internal format), 1 - interpretable, 2 include concept code and labels
//
//    datatable -   datatable session id  - returns json suitable for datatable ui component
//              >1 and "q" is defined - save query request in session to result set returned, 
//              >1 and "q" not defined and "draw" is defined - takes query from session
//              1 - use "q" parameter
//
//    leaflet - 0|1 returns strict geojson and timeline data as two separate arrays, without details, only header fields rec_ID, RecTypeID and rec_Title
//    simplify  0|1 simplify  paths with more than 1000 vertices 
//
//    limit for leaflet and gephi only
//
// @todo if output if file and zip - output datatabase,defintions and records as separate files
//      split records by 1000 entries chunks
//
public static function output($data, $params){

    self::initialize();

    if (!($data && @$data['status']==HEURIST_OK)){
        return false;
    }

    $data = $data['data'];
    
    if(@$data['memory_warning']){ //memory overflow in recordSearch
        $records = array(); //@todo
    }else if(!(@$data['reccount']>0)){   //empty response
        $records = array();
    }else{
        $records = $data['records'];
    }
    
    $records_original_count = count($records); //mainset of ids (result of search without linked/related)
    $records_out = array(); //ids already out
    $rt_counts = array(); //counts of records by record type
    
    $error_log = array();
    $error_log[] = 'Total rec count '.count($records);
    
    $tmp_destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "exp");    
    //$fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
    $fd = fopen($tmp_destination, 'w');  //less than 1MB in memory otherwise as temp file 
    if (false === $fd) {
        self::$system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
        return false;
    }   
    //to store gephi links
    $gephi_links_dest = null;
    $fd_links = null;
    $links_cnt = 0;
    
    //convert TLCMAP dataset to MAP_LAYER 
    $is_tlc_export = (@$params['tlcmap']!=null && defined('RT_TLCMAP_DATASET'));
    $maplayer_fields = null;
    $maplayer_records = array();
    $maplayer_extents = array();
    $ds_file_fields = null; //keep ids of all file fields
    if($is_tlc_export){
        //get list of detail types for MAP_LAYER
        $maplayer_fields = mysql__select_list2(self::$mysqli,
            'select rst_DetailTypeID from defRecStructure where rst_RecTypeID='.RT_MAP_LAYER);        
        //get list of field types with type "file"
        $ds_file_fields = mysql__select_list2(self::$mysqli,
            'select dty_ID from defDetailTypes where dty_Type="file"');        
        //get default values for mapspace
        $mapdoc_defaults = mysql__select_assoc2(self::$mysqli,
            'select rst_DetailTypeID, rst_DefaultValue from defRecStructure where rst_RecTypeID='.RT_MAP_DOCUMENT
            .' AND rst_DetailTypeID in ('.DT_MAP_BOOKMARK.','.DT_ZOOM_KM_POINT.')' );        
    }
    
    $find_places_for_geo = false;
    
    //
    // HEADER ------------------------------------------------------------
    //
    if($params['format']=='geojson'){
        
        $find_places_for_geo = (self::$system->user_GetPreference('deriveMapLocation', 1)==1);

        if(@$params['leaflet']){
            fwrite($fd, '{"geojson":');         
        }else{
            fwrite($fd, '{"type":"FeatureCollection","features":');
        }
        
        fwrite($fd, '[');         
        
    }else if(@$params['restapi']==1){
        
        if(count($records)==1 && @$params['recID']>0){
            //fwrite($fd, '');             
        }else{
            //@todo xml for api
            fwrite($fd, '{"records":[');             
        }
        
    }else if($params['format']=='json'){
        
        if(@$params['datatable']>1){
            
            //"recordsTotal": 57,"recordsFiltered":'.count($records).',
            
            fwrite($fd, '{"draw": '.$params['draw'].',"recordsTotal":'
                    .$params['recordsTotal'].',"recordsFiltered":'
                    .(@$params['recordsFiltered']!=null?$params['recordsFiltered']:$params['recordsTotal']).',"data":[');     
            
        }else if(@$params['datatable']==1){
            
            fwrite($fd, '{"data": [');     
        }else{
            fwrite($fd, '{"heurist":{"records":[');         
        }
            
    }else if($params['format']=='gephi'){

        $gephi_links_dest = tempnam(HEURIST_SCRATCHSPACE_DIR, "links");    
        //$fd = fopen('php://temp/maxmemory:1048576', 'w');  //less than 1MB in memory otherwise as temp file 
        $fd_links = fopen($gephi_links_dest, 'w');  //less than 1MB in memory otherwise as temp file 
        if (false === $fd_links) {
            self::$system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
            return false;
        }   

        $t2 = new DateTime();
        $dt = $t2->format('Y-m-d');
/*
        $gephi_header = 
'<gexf xmlns="http://www.gexf.net/1.2draft" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance"'
    .' xsi:schemaLocation="http://www.gexf.net/1.2draft http://www.gexf.net/1.2draft/gexf.xsd" version="1.2">'
    ."<meta lastmodifieddate=\"{$dt}\">"
        .'<creator>HeuristNetwork.org</creator>'
        .'<description>Visualisation export</description>'
    .'</meta>'
    .'<graph mode="static" defaultedgetype="directed">'
        .'<attributes class="node">'
            .'<attribute id="0" title="name" type="string"/>'
            .'<attribute id="1" title="image" type="string"/>'
            .'<attribute id="2" title="rectype" type="string"/>'
            .'<attribute id="3" title="count" type="float"/>'
        .'</attributes>'
        .'<attributes class="edge">'
            .'<attribute id="0" title="relation-id" type="float"/>'
            .'<attribute id="1" title="relation-name" type="string"/>'
            .'<attribute id="2" title="relation-image" type="string"/>'
            .'<attribute id="3" title="relation-count" type="float"/>'
        .'</attributes>'
        .'<nodes>';
*/
//although anyURI is defined it is not recognized by gephi v0.92

    $heurist_url = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME;

        $gephi_header = <<<XML
<gexf xmlns="http://www.gexf.net/1.2draft" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance"
     xsi:schemaLocation="http://www.gexf.net/1.2draft http://www.gexf.net/1.2draft/gexf.xsd" version="1.2">
    <meta lastmodifieddate="{$dt}">
        <creator>HeuristNetwork.org</creator>
        <description>Visualisation export $heurist_url </description>
    </meta>
    <graph mode="static" defaultedgetype="directed">
        <attributes class="node">
            <attribute id="0" title="name" type="string"/>
            <attribute id="1" title="image" type="string"/>
            <attribute id="2" title="rectype" type="string"/>
            <attribute id="3" title="count" type="float"/>
            <attribute id="4" title="url" type="string"/>
        </attributes>
        <attributes class="edge">
            <attribute id="0" title="relation-id" type="float"/>
            <attribute id="1" title="relation-name" type="string"/>
            <attribute id="2" title="relation-image" type="string"/>
            <attribute id="3" title="relation-count" type="float"/>
        </attributes>
        <nodes>
XML;

        $gephi_header = '<?xml version="1.0" encoding="UTF-8"?>'.$gephi_header;

        fwrite($fd, $gephi_header);     
    }else if($params['format']=='hml'){
        
        //@TODO
        
        fwrite($fd, '<?xml version="1.0" encoding="UTF-8" xmlns="http://heuristnetwork.org" '
        .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
        .'xsi:schemaLocation="http://heuristnetwork.org reference/scheme_record.xsd"?><hml><records>');     

        $dbID = self::$system->get_system('sys_dbRegisteredID');
        fwrite($fd, '<dbID>'.($dbID>0?$dbID:0).'</dbID>'."\n");
        fwrite($fd, "<records>\n");
        
    }else{
        fwrite($fd, '<?xml version="1.0" encoding="UTF-8"?><heurist><records>');     
    }

    //CONTENT
    $timeline_data = [];
    $layers_record_ids = [];
    
    $comma = '';
    
    //
    // in case depth>0 gather all linked and related record ids with given depth
    //    
    $max_depth = 0;
    if(@$params['depth']){
        $max_depth = (@$params['depth']=='all') ?9999:intval(@$params['depth']);
    }
    
    $direction = 0;// both direct and reverse links
    $no_relationships = false;
        
    if(@$params['linkmode']){//direct, direct_links, none, all

        if($params['linkmode']=='none'){
            $max_depth = 0;
        }else if($params['linkmode']=='direct'){
            $direction = 1; //direct only
        }else if($params['linkmode']=='direct_links'){
            $direction = 1; //direct only
            $no_relationships = true;
        }
    }
    
    if($max_depth>0){
        if($params['format']=='gephi' && @$params['limit']>0){
           $limit = $params['limit'];  
        }else{
           $limit = 0; 
        }
        
        //search direct and reverse links 
        //it adds ids to $records
        recordSearchRelatedIds(self::$system, $records, $direction, $no_relationships, 0, $max_depth, $limit);
    }


    //for gephi we don't need details
    $need_record_details = ($params['format']!='gephi');
    $columns = array('0'=>array()); //for datatable
    $row_placeholder = array();
    
    if(@$params['leaflet']){
        //for leaflet get only limited set of fields 
        $retrieve_fields = 'rec_ID,rec_RecTypeID,rec_Title';
    }else if(@$params['datatable']>0){ 

        //for datatable convert  $params['columns'] to array
        
        /*
0: ["rec_ID","rec_Title"],
3: ["rec_ID", "rec_RecTypeID", "1", "949", "9", "61"]
5: ["1", "38"]
        */
        $need_tags = false;
        $need_record_details = array();
        $retrieve_fields = array(); //header fields
        
        if(is_array($params['columns'])){
            foreach($params['columns'] as $idx=>$column){
                $col_name = $column['data'];

                if(strpos($col_name,'.')>0){
                    list($rt_id,$col_name) = explode('.',$col_name);
                    
                    if(!@$row_placeholder[$rt_id]) $row_placeholder[$rt_id] = array();
                    $row_placeholder[$rt_id][$col_name] = '';
                }else{
                    $rt_id = 0;
                    $row_placeholder[$col_name] = '';
                }

                if($col_name=='rec_Tags'){
                    $need_tags = true;           
                }else if($rt_id==0){
                     if(strpos($col_name,'rec_')===0){
                         array_push($retrieve_fields, $col_name);    
                     }else{
                         array_push($need_record_details, $col_name);
                     }
                }
                
                
                if(!array_key_exists($rt_id, $columns)) {
                      $columns[$rt_id] = array();
                }
                array_push($columns[$rt_id], $col_name);    
            }
        }
        
        if(count($need_record_details)==0){
            $need_record_details = false;
        }
        
        //always include rec_ID and rec_RecTypeID fields 
        if(!in_array('rec_ID',$retrieve_fields)){
            array_push($retrieve_fields,'rec_ID');
            array_push($columns['0'],'rec_ID');
        }
        if(!in_array('rec_RecTypeID',$retrieve_fields)){
            array_push($retrieve_fields,'rec_RecTypeID');
            array_push($columns['0'],'rec_RecTypeID');
        }

        $retrieve_fields = implode(',', $retrieve_fields);
        
    }else{
        $retrieve_fields = null;
    }
    
    //MAIN LOOP  ----------------------------------------
    $records_count = (@$params['datatable']>0)?$records_original_count:count($records);
    
    $idx = 0;
    while ($idx<$records_count){   //loop by record ids
    
        $recID = $records[$idx];
        if(is_array($recID)){
            //record data is already loaded
            $record = $records[$idx];
            $recID = $record['rec_ID'];
        }else{
            $record = recordSearchByID(self::$system, $recID, $need_record_details, $retrieve_fields );
        }
        $idx++;
        
        $rty_ID = $record['rec_RecTypeID'];
        
        //change record type to layer, remove redundant fields
        if($is_tlc_export){
            if($rty_ID==RT_TLCMAP_DATASET){
                $record['rec_RecTypeID'] = RT_MAP_LAYER;
                $rty_ID = RT_MAP_LAYER;
                $new_details = array();
                //remove redundant fields from RT_TLCMAP_DATASET
                foreach($record["details"] as $dty_ID => $values){
                    if(in_array($dty_ID, $maplayer_fields)){
                        $new_details[$dty_ID] = $values;                    
                        
                    }
                    if($dty_ID==DT_GEO_OBJECT){
                        //keep geo to calculate extent for mapdocument
                        array_push($maplayer_extents, $values);
                    }
                }
                $record["details"] = $new_details;
                
                //{"id":"287","type":"29","title":"Region cities","hhash":null}            
                $midx = (count($maplayer_records)+5);
                $maplayer_records[$midx] = array('id'=>$record['rec_ID']);
            }
        }
        
        if(!@$rt_counts[$rty_ID]){
            $rt_counts[$rty_ID] = 1;
        }else{
            $rt_counts[$rty_ID]++;
        }
        
        if($params['format']=='geojson'){
            
            $feature = self::_getGeoJsonFeature($record, (@$params['extended']==2), 
                        @$params['simplify'], @$params['leaflet'], $find_places_for_geo);
            if(@$params['leaflet']){ //include only geoenabled features, timeline data goes in separate timeline array
                   if(@$feature['when']){
                        $timeline_data[] = array('rec_ID'=>$recID, 'when'=>$feature['when']['timespans'], 
                                        'rec_RecTypeID'=>$rty_ID, "rec_Title"=>$record['rec_Title']);
                        $feature['when'] = null;
                        unset($feature['when']);
                   }
                   
                   if( (defined('RT_TLCMAP_DATASET') && $rty_ID==RT_TLCMAP_DATASET) || 
                       (defined('RT_MAP_LAYER') && $rty_ID==RT_MAP_LAYER) ){
                        array_push($layers_record_ids, $recID);    
                   }
                   
                   if(!@$feature['geometry']) continue;
            }

            fwrite($fd, $comma.json_encode($feature));
            $comma = ',';
        
        }else if($params['format']=='json'){ 
            
            if(@$params['datatable']>0){
                
                $feature = self::_getJsonFlat( $record, $columns, $row_placeholder );
                fwrite($fd, $comma.json_encode($feature));
                
            }else if(@$params['extended']>0 && @$params['extended']<3){ //with concept codes and labels
                $feature = self::_getJsonFeature($record, $params['extended']);
                fwrite($fd, $comma.json_encode($feature));
            }else{
                fwrite($fd, $comma.json_encode($record)); //as is
            }
            $comma = ',';
            
        }else if($params['format']=='gephi'){ 
            
            $name   = htmlspecialchars($record['rec_Title']);                               
            $image  = htmlspecialchars(HEURIST_ICON_SCRIPT.$rty_ID);
            $recURL = htmlspecialchars(HEURIST_BASE_URL.'recID='.$recID.'&fmt=html&db='.HEURIST_DBNAME);                               
                               
            $gephi_node = <<<XML
<node id="{$recID}" label="{$name}">                               
    <attvalues>
    <attvalue for="0" value="{$name}"/>
    <attvalue for="1" value="{$image}"/>
    <attvalue for="2" value="{$rty_ID}"/>
    <attvalue for="3" value="0"/>
    <attvalue for="4" value="{$recURL}"/>
    </attvalues>
</node>
XML;
            fwrite($fd, $gephi_node);
            
            $links = recordSearchRelated(self::$system, $recID, 0, false);
            if($links['status']==HEURIST_OK){
                if(@$links['data']['direct'])
                    fwrite($fd_links, self::_composeGephiLinks($records, $links['data']['direct'], $links_cnt, 'direct'));
                if(@$links['data']['reverse'])
                    fwrite($fd_links, self::_composeGephiLinks($records, $links['data']['reverse'], $links_cnt, 'reverse'));
            }else{
                return false;
            }
            
             
        }else if($params['format']=='hml'){
            
            //@TODO
            
            
        }else{
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><record/>');
            self::_array_to_xml($record, $xml);
            //array_walk_recursive($record, array ($xml , 'addChild'));
            fwrite($fd, substr($xml->asXML(),38)); //remove header
        }
        
        
        if($is_tlc_export && $idx==count($records)){
            //calculate extent of mapdocument - last record
            $records[$idx] = self::_calculateSummaryExtent($maplayer_extents, true);
            $is_tlc_export = false; //avoid infinite loop
        }
    }//while records
    
    
    //CLOSE brackets ----------------------------------------
    
    if($params['format']=='geojson'){
        
        fwrite($fd, ']');
        
        if(@$params['leaflet']){ //return 2 array - pure geojson and timeline items
           fwrite($fd, ',"timeline":'.json_encode($timeline_data));
           fwrite($fd, ',"layers_ids":'.json_encode($layers_record_ids).'}');
        }else{
           fwrite($fd, '}'); //close for FeatureCollection
        }
    }else if(@$params['restapi']==1){
        if(count($records)==1 && @$params['recID']>0){
            //fwrite($fd, '');             
        }else{ 
            //@todo xml for api
            fwrite($fd, ']}');             
        }
    }else if($params['format']=='gephi'){
    
        fwrite($fd, '</nodes>');
        
        fwrite($fd, '<edges>'.file_get_contents($gephi_links_dest).'</edges>');
        
        fwrite($fd, '</graph></gexf>');
        
        fclose($fd_links);
    
    }else if($params['format']=='json' && @$params['datatable']>0){
        
        fwrite($fd, ']}');     
        
    }else{  //json or xml 
    
            if($params['format']=='json'){
                fwrite($fd, ']');     
            }else{ //xml
                fwrite($fd, '</records>');     
            }
        
            $rectypes = dbs_GetRectypeStructures(self::$system, null, 2);
            // include defintions
            if(@$params['defs']==1){
                
                $detailtypes = dbs_GetDetailTypes(self::$system, null, 2);
                $terms = dbs_GetTerms(self::$system);
                
                unset($rectypes['names']);
                unset($rectypes['pluralNames']);
                unset($rectypes['groups']);
                unset($rectypes['dtDisplayOrder']);
                
                unset($detailtypes['names']);
                unset($detailtypes['groups']);
                unset($detailtypes['rectypeUsage']);

                $rectypes = array('rectypes'=>$rectypes);
                $detailtypes = array('detailtypes'=>$detailtypes);
                $terms = array('terms'=>$terms);
                
                if($params['format']=='json'){
                    fwrite($fd, ',{"definitions":['); 
                    fwrite($fd, json_encode($rectypes).',');
                    fwrite($fd, json_encode($detailtypes).',');
                    fwrite($fd, json_encode($terms));
                    fwrite($fd, ']}'); 
                }else{
                    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><definitions/>');
                    self::_array_to_xml($rectypes, $xml);
                    self::_array_to_xml($detailtypes, $xml);
                    self::_array_to_xml($terms, $xml);
                    fwrite($fd, substr($xml->asXML(),38));
                }
            }
            
            //add database information to be able to load definitions later
            $dbID = self::$system->get_system('sys_dbRegisteredID');
            $database_info = array('id'=>$dbID, 
                                                'url'=>HEURIST_BASE_URL, 
                                                'db'=>self::$system->dbname());
                
            $query = 'select rty_ID,rty_Name,'
            ."if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID"
            .' from defRecTypes where rty_ID in ('.implode(',',array_keys($rt_counts)).')';    
            $rectypes = mysql__select_all(self::$system->get_mysqli(),$query,1);    
                
            foreach($rt_counts as $rtid => $cnt){
                //include record types that are in output - name, ccode and count
                $rt_counts[$rtid] = array('name'=>$rectypes[$rtid][0],'code'=>$rectypes[$rtid][1],'count'=>$cnt);
            }
            $database_info['rectypes'] = $rt_counts;
            
            if($params['format']=='json'){
                    fwrite($fd, ',"database":'.json_encode($database_info));
                    fwrite($fd, '}}');     
            }else{
                $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><database/>');
                self::_array_to_xml($database_info, $xml);
                fwrite($fd, substr($xml->asXML(),38));
                fwrite($fd, '</heurist>');     
            }
        
    }
 
    //
    // OUTPUT
    //
    if(@$params['zip']==1 || @$params['zip']===true){
        
        $output = gzencode(file_get_contents($tmp_destination), 6); 
        fclose($fd);
        
        header('Content-Encoding: gzip');
        if($params['format']=='json' || $params['format']=='geojson'){
            header( 'Content-Type: application/json');    
        }else{
            header( 'Content-Type: text/xml');
        }
        unlink($tmp_destination);
        echo $output; 
        unset($output);   
        
        return true;
    }else{
        
        //$content = stream_get_contents($fd);
        fclose($fd);
        
        if(@$params['filename'] || @$params['metadata']){
            
            $record = null;
            $originalFileName = null;
            if(@$params['metadata']){
                list($db_meta,$rec_ID) = explode('-',$params['metadata']);
                if(!$db_meta && $rec_ID) $db_meta = self::$system->dbname(); 
                
                $record = array("rec_ID"=>$rec_ID);
                if($db_meta!=self::$system->dbname()){
                    self::$system->init($db_meta, true, false);
                    //mysql__usedatabase(self::$mysqli, $db_meta);
                }
                
                if(self::$system->defineConstant('DT_NAME', true)){
                    
                    //$val = mysql__select_value(self::$mysqli,'select dtl_Value from recDetails where rec_ID='
                    //    .$params['metadata'].' and dtl_DetailTypeID='.DT_NAME);
                    //if($val){
                        //$originalFileName = fileNameSanitize($val);
                    //}
                    
                    recordSearchDetails(self::$system, $record, array(DT_NAME));
                    if(is_array($record['details'][DT_NAME])){
                        $originalFileName = fileNameSanitize(array_values($record['details'][DT_NAME])[0]);
                    }
                }
                if(!$originalFileName) $originalFileName = 'Dataset_'.$record['rec_ID'];
                
            }else{
                $originalFileName = $params['filename'];
            }
            
            
            //save into specified file in scratch folder
            $file_records  = $originalFileName.'.'.$params['format'];

            //archive into zip    
            $file_zip = $originalFileName.'.zip';
            $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");
            $zip = new ZipArchive();
            if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
                self::$system->addError(HEURIST_SYSTEM_CONFIG, "Cannot create zip $file_zip_full");
                return false;
            }else{
                $zip->addFile($tmp_destination, $file_records);
            }
            
            // SAVE hml inot file DOES NOT WORK - need to rewrite flathml
            if(@$params['metadata']){//save hml into scratch folder
                    $zip->addFromString($originalFileName.'.txt', 
                                    recordLinksFileContent(self::$system, $record));    

            }
            $zip->close();
            //donwload
            $contentDispositionField = 'Content-Disposition: attachment; '
                . sprintf('filename="%s"; ', rawurlencode($file_zip))
                . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));            
            
            header('Content-Type: application/zip');
            header($contentDispositionField);
            header('Content-Length: ' . filesize($file_zip_full));
            readfile($file_zip_full);

            // remove the zip archive and temp files
            //unlink($file_zip_full); 
            //unlink($file_metadata_full);
            unlink($tmp_destination);   
            return true;
        }else{
            $content = file_get_contents($tmp_destination);

            if($params['format']=='json' || $params['format']=='geojson'){
                header( 'Content-Type: application/json');    
            }else{
                header( 'Content-Type: text/xml');
            }
        
            if(@$params['file']==1 || @$params['file']===true){
                $filename = 'Export_'.$params['db'].'_'.date("YmdHis").'.'.$params['format'];
                header('Content-Disposition: attachment; filename='.$filename);
                header('Content-Length: ' . strlen($content));
            }
            
            if(@$params['restapi']==1 && count($rt_counts)==0){
                http_response_code(404);
            }
            unlink($tmp_destination);
            
            exit($content);
        }
    }
    
}
//------------------------

/**
* returns xml string with gephi links
* 
* @param mixed $records - array of record ids to limit output only for links in this array
* @param mixed $links - array of relations produced by recordSearchRelated
*/
private static function _composeGephiLinks(&$records, &$links, &$links_cnt, $direction){

    if(self::$defDetailtypes==null) self::$defDetailtypes = dbs_GetDetailTypes(self::$system, null, 2);
    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms(self::$system);
        self::$defTerms = new DbsTerms(self::$system, self::$defTerms);
    }

    $idx_dname = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];


    $edges = ''; 

    if($links){

        foreach ($links as $link){
            if($direction=='direct'){
                $source =  $link->recID;
                $target =  $link->targetID;
            }else{
                $source = $link->sourceID;
                $target = $link->recID;
            }

            $dtID = $link->dtID;
            $trmID = $link->trmID;
            $relationName = "Floating relationship";
            $relationID = 0;

            if(in_array($source, $records) && in_array($target, $records)){

                if($dtID > 0) {
                    $relationName = self::$defDetailtypes['typedefs'][$dtID]['commonFields'][$idx_dname];
                    $relationID = $dtID;
                }else if($trmID > 0) {
                    $relationName = self::$defTerms->getTermLabel($trmID, true);
                    $relationID = $trmID;
                }

                $relationName  = htmlspecialchars($relationName);                               
                //$image = htmlspecialchars(HEURIST_TERM_URL.$trmID.'.png');
                //<attvalue for="2" value="{$image}"/>
                $links_cnt++; 

                $edges = $edges.<<<XML
<edge id="{$links_cnt}" source="{$source}" target="{$target}" weight="1">                               
    <attvalues>
    <attvalue for="0" value="{$relationID}"/>
    <attvalue for="1" value="{$relationName}"/>
    <attvalue for="3" value="1"/>
    </attvalues>
</edge>
XML;


            }   
        }//for
    }
    return $edges;         
}

//
//
//
private static function _calculateSummaryExtent($maplayer_extents, $is_return_rec){


        $zoomKm = 0;
        $mbookmark = null;
        $mbox = array();
        foreach($maplayer_extents as $dtl_ID=>$values){
            foreach($values as $value){
                if(is_array($value) && @$value['geo']){
                    $wkt = $value['geo']['wkt'];
                    $bbox = self::_getExtentFromWkt($wkt);  
                    if($bbox!=null){
                        if( !@$mbox['maxy'] || $mbox['maxy']<$bbox['maxy'] ){
                            $mbox['maxy'] = $bbox['maxy'];
                        }        
                        if( !@$mbox['maxx'] || $mbox['maxx']<$bbox['maxx'] ){
                            $mbox['maxx'] = $bbox['maxx'];
                        }        
                        if( !@$mbox['miny'] || $mbox['miny']>$bbox['miny'] ){
                            $mbox['miny'] = $bbox['miny'];
                        }        
                        if( !@$mbox['minx'] || $mbox['minx']>$bbox['minx'] ){
                            $mbox['minx'] = $bbox['minx'];
                        }        
                    }
                }
            }
        }
        if(count($mbox)==4){
            
            $gPoint = new GpointConverter();
            $gPoint->setLongLat($mbox['minx'], $mbox['miny']);
            $zoomKm = round($gPoint->distanceFrom($mbox['maxx'], $mbox['minx'])/100000,0);
            
            
            $mbookmark = 'Extent,'.$mbox['miny'].','.$mbox['minx']
                         .','.$mbox['maxy'].','.$mbox['maxx'].',1800,2050';
            
            $mbox = array($mbox['minx'].' '.$mbox['miny'], $mbox['minx'].' '.$mbox['maxy'], 
                            $mbox['maxx'].' '.$mbox['maxy'], $mbox['maxx'].' '.$mbox['miny'], 
                              $mbox['minx'].' '.$mbox['miny']);
            $mbox = 'POLYGON(('.implode(',',$mbox).'))';
            
        }
        
        if($is_return_rec){
        
            //add constructed mapspace record
            $record['rec_ID'] = 999999999;
            $record['rec_RecTypeID'] = RT_MAP_DOCUMENT;
            $record['rec_Title'] = $params['tlcmap'];
            $record['rec_URL'] = ''; 
            $record['rec_ScratchPad'] = '';
            $record["details"] = array(
                DT_NAME=>array('1'=>$params['tlcmap']),
                DT_MAP_BOOKMARK=>array('2'=>($mbookmark!=null?$mbookmark:$mapdoc_defaults[DT_MAP_BOOKMARK])),
                DT_ZOOM_KM_POINT=>array('3'=>($zoomKm>0?$zoomKm:$mapdoc_defaults[DT_ZOOM_KM_POINT])),
                DT_GEO_OBJECT=>array('4'=>($mbox!=null?array('geo'=>array("type"=>"pl", "wkt"=>$mbox)):null)),
                DT_MAP_LAYER=>$maplayer_records
            );
            
            return $record;    
        }else{
            return $mbox;
        }

}

//
//
//
private static function _getExtentFromWkt($wkt)
{
        $bbox = null;
        $geom = geoPHP::load($wkt, 'wkt');
        if(!$geom->isEmpty()){
            $bbox = $geom->getBBox();
        }
        return $bbox;
}



//
// convert heurist record to GeoJSON Feasture
// 
// $extended - include concept codes, term code and labels
// $simplify - simplify paths with more than 1000 vertices
// $leaflet_minimum_fields  - only header fields rec_ID, RecTypeID, rec_Title and description if details are defined
// $find_places_for_geo - if true it searches linked places for geo
//
private static function _getGeoJsonFeature($record, $extended=false, $simplify=false, $leaflet_minimum_fields=false, $find_places_for_geo=false){

    if($extended){
        if(self::$defRecTypes==null) self::$defRecTypes = dbs_GetRectypeStructures(self::$system, null, 2);
        $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];

        if(self::$defTerms==null) {
            self::$defTerms = dbs_GetTerms(self::$system);
            self::$defTerms = new DbsTerms(self::$system, self::$defTerms);
        }
    }else{
        $idx_name = -1;
    }    
    
    if(self::$defDetailtypes==null) self::$defDetailtypes = dbs_GetDetailTypes(self::$system, null, 2);
    $idx_dname = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
    $idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $idx_ccode = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

    $res = array('type'=>'Feature',
        'id'=>$record['rec_ID'], 
        'properties'=>array(),
        'geometry'=>array());

    $rty_ID = $record['rec_RecTypeID'];

    $res['properties'] = $record;
    $res['properties']['details'] = array();

    $geovalues = array();
    $timevalues = array();
    $date_start = null;
    $date_end = null;
    $symbology = null;
    $ext_description = null;

    //convert details to proper JSON format, extract geo fields and convert WKT to geojson geometry
    foreach ($record['details'] as $dty_ID=>$field_details) {

        $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues

            if(is_array($value)){ //geo,file,resource
                if(@$value['file']){
                    //remove some fields
                    $val = $value['file'];
                    unset($val['ulf_ID']);
                    unset($val['fullPath']);
                    unset($val['ulf_Parameters']);

                }else if(@$value['id']){ //resource
                    $val = $value['id'];
                }else if(@$value['geo']){

                    $wkt = $value['geo']['wkt'];
                    /*if($value['geo']['type']=='r'){
                    //@todo convert rect to polygone  
                    }else if($value['geo']['type']='c'){
                    //@todo convert circle to polygone
                    }*/
                    try{
                        $json = self::_getJsonFromWkt($wkt, $simplify);
                        if($json){
                           $geovalues[] = $json; 
                        }
                    }catch(Exception $e){
                    }

                    $val = $wkt;
                    continue;  //it will be included into separate geometry property  
                }
            }else{
                if($field_type=='date' || $field_type=='year'){
                    if($dty_ID==DT_START_DATE){
                        $date_start = temporalToSimple($value);
                        if(strpos($date_start,'unknown')!==false) $date_start = null;
                    }else if($dty_ID==DT_END_DATE){
                        $date_end = temporalToSimple($value);
                        if(strpos($date_end,'unknown')!==false) $date_end = null;
                    }else if($value!=null){
                        //parse temporal
                        $ta = temporalToSimpleRange($value);
                        if($ta!=null) $timevalues[] = $ta;
                    }
                }else if(defined('DT_SYMBOLOGY') && $dty_ID==DT_SYMBOLOGY){
                    $symbology = json_decode($value,true);                    
                //}else if(defined('DT_EXTENDED_DESCRIPTION') && $dty_ID==DT_EXTENDED_DESCRIPTION){
                //    $ext_description = $value;
                }
                $val = $value;
            }
            
            if(!isset($val)) $val = '';

            $val = array('ID'=>$dty_ID,'value'=>$val);

            if(@$value['id']>0){ //resource
                $val['resourceTitle']     = @$value['title'];
                $val['resourceRecTypeID'] = @$value['type'];
            }

            if($extended){
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = self::$defTerms->getTermLabel($val, true);
                    $term_code  = self::$defTerms->getTermCode($val);
                    if($term_code) $val['termCode'] = $term_code;    
                }

                //take name for rt structure    
                if(@self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID] && $idx_name>=0){
                    $val['fieldName'] = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$idx_name];    
                }else{
                    //non standard field
                    $val['fieldName'] = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dname];    
                }

                $val['fieldType'] = $field_type;
                $val['conceptID'] = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_ccode];
            }

            $res['properties']['details'][] = $val;
        } //for detail multivalues
    } //for all details of record

    
    if($leaflet_minimum_fields){
        if($ext_description) $res['properties']['description'] = $ext_description;
        $res['properties']['details'] = null;
        unset($res['properties']['details']);
    }
    
    if(count($geovalues)==0 && $find_places_for_geo){
        //this record does not have geo value - find it in related/linked places
        $geodetails = recordSearchGeoDetails(self::$system, $record['rec_ID']);    
        foreach ($geodetails as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
                    $wkt = $value['geo']['wkt'];
                    $json = self::_getJsonFromWkt($wkt, $simplify);
                    if($json){
                       $geovalues[] = $json; 
                    }
            }
        }
    }

    if(count($geovalues)>1){
        $res['geometry'] = array('type'=>'GeometryCollection','geometries'=>$geovalues);
    }else if(count($geovalues)==1){
        $res['geometry'] = $geovalues[0];
    }else{
        //$res['geometry'] = [];
    }

    if($date_start!=null || $date_end!=null){
        if($date_start==null){
            $date_start = $date_end;
        }
        if($date_end==null) $date_end = '';
        $timevalues[] = array($date_start, '', '', $date_end, '');
    }

    if(count($timevalues)>0){
        //https://github.com/kgeographer/topotime/wiki/GeoJSON%E2%80%90T
        // "timespans": [["-323-01-01 ","","","-101-12-31","Hellenistic period"]],  [start, latest-start, earliest-end, end, label]
        $res['when'] = array('timespans'=>$timevalues);
    }
    if($symbology){
        $res['style'] = $symbology;
    }

    return $res;

}

//
// Convert WKT to geojson and simplifies coordinates  
// @TODO use utils_geo
//
private static function _getJsonFromWkt($wkt, $simplify=true)
{
        $geom = geoPHP::load($wkt, 'wkt');
        if(!$geom->isEmpty()){

            /*The GEOS-php extension needs to be installed for these functions to be available 
            if($simplify)$geom->simplify(0.0001, TRUE);
            */

            $geojson_adapter = new GeoJSON(); 
            $json = $geojson_adapter->write($geom, true); 

            if(count($json['coordinates'])>0){

                if($simplify){
                    if($json['type']=='LineString'){

                        simplifyCoordinates($json['coordinates']);

                    } else if($json['type']=='Polygon'){
                        for($idx=0; $idx<count($json['coordinates']); $idx++){
                            simplifyCoordinates($json['coordinates'][$idx]);
                        }
                    } else if ( $json['type']=='MultiPolygon' || $json['type']=='MultiLineString')
                    {
                        for($idx=0; $idx<count($json['coordinates']); $idx++) //shapes
                            for($idx2=0; $idx2<count($json['coordinates'][$idx]); $idx2++) //points
                                simplifyCoordinates($json['coordinates'][$idx][$idx2]);
                    }
                }

                return $json;
            }
        }//isEmpty
        return null;
}

/*
Produces json for DataTable widget

$columns: 
0: ["rec_ID","rec_Title"],
3: ["rec_ID", "rec_RecTypeID", "1", "949", "9", "61"]
5: ["1", "38"]
*/
private static function _getJsonFlat( $record, $columns, $row_placeholder, $level=0 ){
    
    
    $res = ($level==0)?$row_placeholder:array();
    
    if($level==0){
        $rt_id = 0;
    }else{
        $rt_id = $record['rec_RecTypeID'];
    }

    if(!array_key_exists($rt_id, $columns)) return null;
    
    foreach($columns[$rt_id] as $column){

        $col_name = $column; //($rt_id>0 ?$rt_id.'.':'').   
        
        if ($column=='rec_Tags'){
            $value = recordSearchPersonalTags(self::$system, $record['rec_ID']);
            $res[$col_name] = ($value==null)?'':implode(' | ', $value);
        }else if(strpos($column,'rec_')===0){
            $res[$col_name] = $record[$column];       
        }else{
            $res[$col_name] = ''; //placeholder
        }
    }
    
    
    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes(self::$system, null, 2);   
    }
    $idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    
    //convert details to proper JSON format
    if(@$record['details'] && is_array($record['details'])){
        foreach ($record['details'] as $dty_ID=>$field_details) {
        
        if(!in_array($dty_ID, $columns[$rt_id])) continue;
        
        $col_name = $dty_ID; //($rt_id>0 ?$rt_id.'.':'').$dty_ID;
        
        $res[$col_name] = array();
        $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];
        
        foreach($field_details as $dtl_ID=>$field_value){ //for detail multivalues
        
            if($field_type=='resource'){
                
                //if multi constraints - search all details
                $link_rec_ID =  $field_value['id'];
                $record = recordSearchByID(self::$system, $link_rec_ID, true, null );
                $field_value = self::_getJsonFlat( $record, $columns, null, $level+1 );
                
                if($field_value!=null){
                    $rt_id_link = $record['rec_RecTypeID'];
                    if(@$res[$rt_id_link]){
                        foreach($field_value as $col=>$field){
                            if(@$res[$rt_id_link][$col]==null || $res[$rt_id_link][$col]==''){
                                $res[$rt_id_link][$col] = $field;
                            }else{
                                if(!is_array($res[$rt_id_link][$col])){
                                    $res[$rt_id_link][$col] = array($res[$rt_id_link][$col]);
                                }
                                array_push($res[$rt_id_link][$col], $field);
                            }    
                        }
                    }else{
                        $res[$rt_id_link] = $field_value;    
                    }
                }
                $field_value = $link_rec_ID;
                
            }else if ($field_type=='file'){
                
                $field_value = $field_value['file']['ulf_ObfuscatedFileID'];
                
            }else if ($field_type=='geo'){

                $field_value = @$field_value['geo']['wkt'];
                
            //}else if ($field_type=='enum' || $field_type=='relationtype'){
            }
            
            if($field_value!=null){
                array_push($res[$col_name], $field_value);
            }
        } //for detail multivalues
        
        if(count($res[$col_name])==1){
            $res[$col_name] = $res[$col_name][0];  
        } 
    } //for all details of record
    }

    return $res;
    
    
}

//
// convert heurist record to more interpretable format
// 
// $mode = 0 - as is, 1 - details in format {dty_ID: val: }, 2 - with concept codes and names/labels
//
private static function _getJsonFeature($record, $mode){

    if($mode==0){ //leave as is
        return $record;
    }

    $rty_ID = $record['rec_RecTypeID'];

    $res = $record;
    $res['details'] = array();
    
    
    if($mode==2){ //extended - with concept codes and names/labels
    
        if(self::$defRecTypes==null) {
            self::$defRecTypes = dbs_GetRectypeStructures(self::$system, null, 2);
        }    
        if(self::$defTerms==null) {
            self::$defTerms = dbs_GetTerms(self::$system);
            self::$defTerms = new DbsTerms(self::$system, self::$defTerms);
        }
        if(self::$defDetailtypes==null){
            self::$defDetailtypes = dbs_GetDetailTypes(self::$system, null, 2);   
        }

        $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
        
        $idx_dname = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
        $idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
        $idx_ccode = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];
        
        
        $idx_ccode2 = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_ConceptID'];
        
        $res['rec_RecTypeName'] = self::$defRecTypes['names'][$rty_ID];    
        $idx_ccode2 = self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$idx_ccode2];
        if($idx_ccode2) $res['rec_RecTypeConceptID'] = $idx_ccode2;
        
    }


    //convert details to proper JSON format, extract geo fields and convert WKT to geojson geometry
    foreach ($record['details'] as $dty_ID=>$field_details) {

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
        
            $val = array('dty_ID'=>$dty_ID,'value'=>$value);

            if($mode==2){ //extended - with concept codes and names/labels
                $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = self::$defTerms->getTermLabel($value, true);
                    $term_code  = self::$defTerms->getTermCode($value);
                    if($term_code) $val['termCode'] = $term_code; 
                    $term_code  = self::$defTerms->getTermConceptID($value);
                    if($term_code) $val['termConceptID'] = $term_code;    
                }

                if(@self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID]){
                    $val['fieldName'] = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$idx_name];    
                }else{
                    //non standard field
                    $val['fieldName'] = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dname];    
                }

                $val['fieldType'] = $field_type;
                $val['conceptID'] = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_ccode];
            }

            $res['details'][] = $val;
        } //for detail multivalues
    } //for all details of record

    return $res;
}



//
// json to xml
//
private static function _array_to_xml( $data, &$xml_data ) {
    foreach( $data as $key => $value ) {
        if( is_numeric($key) ){
            $key = 'item'.$key; //dealing with <0/>..<n/> issues
        }
        if( is_array($value) ) {
            $subnode = $xml_data->addChild($key);
            self::_array_to_xml($value, $subnode);
        } else {
            $xml_data->addChild("$key",htmlspecialchars("$value"));
        }
     }
}


} //end class
?>
