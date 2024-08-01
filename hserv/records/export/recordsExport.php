<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* recordsExport.php - produces output in json, geojson, xml, gephi formats
* 
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/


require_once dirname(__FILE__).'/../../../vendor/autoload.php';//for geoPHP
require_once dirname(__FILE__).'/../../utilities/geo/mapSimplify.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapCoordConverter.php';
require_once dirname(__FILE__).'/../../utilities/Temporal.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';

/**
* 
*  setSession - switch current datbase
*  output - main method
* 
*/
class RecordsExport {

    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;
    private static $version = 3;
    
    private static $defRecTypes = null;
    private static $defDetailtypes = null;
    private static $defTerms = null;
    private static $datetime_field_types = null;
    private static $mapdoc_defaults = null;

    private static $relmarker_fields = [];
    
//
//
//    
private static function initialize()  
{
    if (self::$initialized) {return;}

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->get_mysqli();
    self::$initialized = true;
    self::$version = 3;
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
//    format - json|geojson|xml|gephi|iiif
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
// prefs for iiif
//     version 2 or 3(default) 

// prefs for geojson, json
//    extended 0 as is (in heurist internal format), 1 - interpretable, 2 include concept code and labels
//
//    datatable -   datatable session id  - returns json suitable for datatable ui component
//              >1 and "q" is defined - save query request in session to result set returned, 
//              >1 and "q" not defined and "draw" is defined - takes query from session
//              1 - use "q" parameter
//    columns - array of header and detail fields to be returned
//    detail_mode  0|1|2  - 0- no details, 1 - inline, 2 - fill in "details" subarray
//
//    leaflet - 0|1 returns strict geojson and timeline data as two separate arrays, without details, only header fields rec_ID, RecTypeID and rec_Title
//        geofields  - additional filter - get geodata from specified fields only (in facetsearch format rt:dt:rt:dt )
//        timefields - additional filter - get datetime from specified fields only
//        suppress_linked_places - do not retriev geodata from linked places 
//        separate - do not create GeometryCollection for heurist record
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
        $records = array();//@todo
    }else if(!(@$data['reccount']>0)){   //empty response
        $records = array();
    }else{
        $records = $data['records'];
    }
    
    $records_original_count = is_array($records)?count($records):0; //mainset of ids (result of search without linked/related)
    $records_out = array();//ids already out
    $rt_counts = array();//counts of records by record type
    
    $error_log = array();
    $error_log[] = 'Total rec count '.count($records);
    
    $tmp_destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "exp");
    //$fd = fopen('php://temp/maxmemory:1048576', 'w');//less than 1MB in memory otherwise as temp file 
    $fd = fopen($tmp_destination, 'w');//less than 1MB in memory otherwise as temp file 
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
        self::$mapdoc_defaults = mysql__select_assoc2(self::$mysqli,
            'select rst_DetailTypeID, rst_DefaultValue from defRecStructure where rst_RecTypeID='.RT_MAP_DOCUMENT
            .' AND rst_DetailTypeID in ('.DT_MAP_BOOKMARK.','.DT_ZOOM_KM_POINT.')' );
    }
    
    $find_timefields = prepareIds(@$params['timefields']);
    if(count($find_timefields)==0) {$find_timefields = null;}
    
    $find_geo_by_pointer_rty = false;
    $geojson_ids = array();//simplify array('all'=>array());
    $geojson_dty_ids = array();//unique list of all geofields 
    $geojson_rty_ids = array();
    $timeline_dty_ids = array();//unique list of all date fields 
    
    //
    // HEADER ------------------------------------------------------------
    //
    if($params['format']=='geojson'){
        
        $find_geo_by_pointer_rty =  @$params['geofields'] ||
               ((@$params['suppress_linked_places']!=1) 
               && (self::$system->user_GetPreference('deriveMapLocation', 1)==1));
               
        if($find_geo_by_pointer_rty){ //true
            
            //list of rectypes that are sources for geo location
            $rectypes_as_place = self::$system->get_system('sys_TreatAsPlaceRefForMapping');
            if($rectypes_as_place){
                $rectypes_as_place = prepareIds($rectypes_as_place);
            }else {
                $rectypes_as_place = array();
            }
            //Place always in this array
            if(self::$system->defineConstant('RT_PLACE')){
                if(!in_array(RT_PLACE, $rectypes_as_place)){
                    array_push($rectypes_as_place, RT_PLACE);
                }
            }
            //list of record types that are considered as Places with geo field
            $find_geo_by_pointer_rty = $rectypes_as_place; 
            
            $search_all_geofields = true;
            
            if(@$params['leaflet']){
                $search_all_geofields = false;
                $_geofields = @$params['geofields'];
                $find_geo_by_pointer_dty = array();
                $find_by_geofields = array();
                
                if($_geofields){
                    if(is_String($_geofields)){
                        $_geofields = explode(',', $_geofields);
                    }
                    if(is_Array($_geofields) && count($_geofields)>0){
                        
                        foreach($_geofields as $idx=>$code){
                            if($code=='all'){
                                //search all geofields
                                $search_all_geofields = true;
                            }else if(is_array($code)){
                                if(!@$code['q']){
                                    array_push($find_by_geofields,$code['id']);
                                }else{
                                    array_push($find_by_geofields,$code);//with query to linked record                    
                                }
                                
                            }else{
                                $dty_ID = ConceptCode::getDetailTypeLocalID($code);
                                if($dty_ID>0){
                                    array_push($find_geo_by_pointer_dty, $dty_ID);
                                }
                            }
                        }
                    }
                }
                
                
                if(is_array($find_geo_by_pointer_dty) && count($find_geo_by_pointer_dty)==0){
                    $find_geo_by_pointer_dty = null;
                }
                if(is_array($find_by_geofields) && count($find_by_geofields)==0){
                    $find_by_geofields = null;
                }
                
            }
        }
        
        //define constant for start and end places
        define('DT_PLACE_START', ConceptCode::getDetailTypeLocalID('2-134'));
        define('DT_PLACE_END', ConceptCode::getDetailTypeLocalID('2-864'));
        
        define('DT_PLACE_START2', ConceptCode::getDetailTypeLocalID('1414-1092'));
        define('DT_PLACE_END2', ConceptCode::getDetailTypeLocalID('1414-1088'));
        define('DT_PLACE_TRAN', ConceptCode::getDetailTypeLocalID('1414-1090'));
        
        self::$system->defineConstant('DT_MINIMUM_ZOOM_LEVEL', true);
        self::$system->defineConstant('DT_MAXIMUM_ZOOM_LEVEL', true);
        

        if(@$params['leaflet']){
            fwrite($fd, '{"geojson":');
        }else{
            fwrite($fd, '{"type":"FeatureCollection","features":');
        }
        
        fwrite($fd, '[');
        
    }
    else if(@$params['restapi']==1){
        
        if(count($records)==1 && @$params['recID']>0){
            //fwrite($fd, '');
        }else{
            //@todo xml for api
            fwrite($fd, '{"records":[');
        }

    }else if($params['format']=='iiif'){ //it creates iiif manifest see getIiifResource
        
        self::$version = (@$params['version']==2 || @$params['v']==2)?2:3;
        
        $manifest_uri = self::gen_uuid();
            
        if(self::$version==2){

        $sequence_uri = self::gen_uuid();
            
    $iiif_header = <<<IIIF
{
    "@context": "http://iiif.io/api/presentation/2/context.json",
    "@id": "http://$manifest_uri",
    "@type": "sc:Manifest",
    "label": "Heurist IIIF manifest",
    "metadata": [],
    "description": [
        {
            "@value": "[Click to edit description]",
            "@language": "en"
        }
    ],
    "license": "https://creativecommons.org/licenses/by/3.0/",
    "attribution": "[Click to edit attribution]",
    "sequences": [
        {
            "@id": "http://$sequence_uri",
            "@type": "sc:Sequence",
            "label": [
                {
                    "@value": "Normal Sequence",
                    "@language": "en"
                }
            ],
            "canvases": [   
IIIF;
        }else{
            //VERSION 3
            
$pageURL = 'http';

        /*if ($_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $pageURL .= "://"; $_SERVER["SERVER_NAME"] */     
        $manifest_uri = HEURIST_SERVER_URL.$_SERVER["REQUEST_URI"];
    
    $iiif_header = <<<IIIF
{
  "@context": "http://iiif.io/api/presentation/3/context.json",
  "id": "$manifest_uri",
  "type": "Manifest",
  "label": {
    "en": [
      "Heurist IIIF manifest"
    ]
  },
  "items": [
IIIF;
            
        }
        
        fwrite($fd, $iiif_header);
        
        $params['depth'] = 0;
        
    }
    else if($params['format']=='json'){
        
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
            
    }else if($params['format']=='gephi'){ //xml

        $gephi_links_dest = tempnam(HEURIST_SCRATCHSPACE_DIR, "links");
        //$fd = fopen('php://temp/maxmemory:1048576', 'w');//less than 1MB in memory otherwise as temp file 
        $fd_links = fopen($gephi_links_dest, 'w');//less than 1MB in memory otherwise as temp file 
        if (false === $fd_links) {
            self::$system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
            return false;
        }   

        $t2 = new DateTime();
        $dt = $t2->format('Y-m-d');

        //although anyURI is defined it is not recognized by gephi v0.92

        $heurist_url = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME;

        $rec_fields = '';
        if(!empty(@$params['columns'])){
            
            $id_idx = 5;

            $params['columns'] = prepareIds($params['columns']);

            foreach ($params['columns'] as $dty_ID) {

                $dty_Name = mysql__select_value(self::$mysqli, "SELECT dty_Name FROM defDetailTypes WHERE dty_ID = {$dty_ID}");
                $rec_fields .= "\n\t\t\t\t<attribute id=\"{$id_idx}\" title=\"{$dty_Name}\" type=\"string\"/>";

                $id_idx ++;
            }
        }

        // Relationship record values
        $rel_RecTypeID = self::$system->defineConstant('RT_RELATION') ? RT_RELATION : null;
        $rel_Source = self::$system->defineConstant('DT_PRIMARY_RESOURCE') ? DT_PRIMARY_RESOURCE : null;
        $rel_Target = self::$system->defineConstant('DT_TARGET_RESOURCE') ? DT_TARGET_RESOURCE : null;
        $rel_Type = self::$system->defineConstant('DT_RELATION_TYPE') ? DT_RELATION_TYPE : null;
        $rel_Start = self::$system->defineConstant('DT_START_DATE') ? DT_START_DATE : null;
        $rel_End = self::$system->defineConstant('DT_END_DATE') ? DT_END_DATE : null;

        $rel_fields = '';
        if($rel_RecTypeID && $rel_Source && $rel_Target && $rel_Type && $rel_Start && $rel_End){

            $query = "SELECT rst_DisplayName, rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID = ? AND rst_DetailTypeID NOT IN (?,?,?,?,?)";
            $query_params = ['iiiiii', $rel_RecTypeID, $rel_Source, $rel_Target, $rel_Type, $rel_Start, $rel_End];
            $res = mysql__select_param_query(self::$mysqli, $query, $query_params);

            $id_idx = 6;

            if($res){

                while($row = $res->fetch_row()){

                    $rel_fields .= "\n\t\t\t\t<attribute id=\"{$id_idx}\" title=\"{$row[0]}\" type=\"string\"/>";
                    self::$relmarker_fields[] = $row[1];

                    $id_idx ++;
                }
            }
        }

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
                        <attribute id="4" title="url" type="string"/>{$rec_fields}
                    </attributes>
                    <attributes class="edge">
                        <attribute id="0" title="relation-id" type="float"/>
                        <attribute id="1" title="relation-name" type="string"/>
                        <attribute id="2" title="relation-image" type="string"/>
                        <attribute id="3" title="relation-count" type="float"/>
                        <attribute id="4" title="relation-start" type="string"/>
                        <attribute id="5" title="relation-end" type="string"/>{$rel_fields}
                    </attributes>
                    <nodes>
        XML;

        $gephi_header = '<?xml version="1.0" encoding="UTF-8"?>'.$gephi_header;

        fwrite($fd, $gephi_header);
    }
    else if($params['format']=='hml'){
        
        //@TODO
        
        fwrite($fd, '<?xml version="1.0" encoding="UTF-8" xmlns="https://heuristnetwork.org" '
        .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
        .'xsi:schemaLocation="https://heuristnetwork.org/documentation_and_templates/scheme_record.xsd"?><hml><records>');

        $dbID = self::$system->get_system('sys_dbRegisteredID');
        fwrite($fd, '<dbID>'.($dbID>0?$dbID:0).'</dbID>'."\n");
        fwrite($fd, "<records>\n");
        
    }else{
        fwrite($fd, '<?xml version="1.0" encoding="UTF-8"?><heurist><records>');
    }

    //CONTENT
    $timeline_data = [];
    $layers_record_ids = [];//list of ids RT_MAP_LAYER if this is search for layers in clearinghouse
    
    
    $comma = '';
    $cnt = 0;
    
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
    $retrieve_header_fields = null;
    $retrieve_detail_fields = ($params['format']!='gephi');
    $columns = array('0'=>array());//for datatable
    $row_placeholder = array();
    $need_rec_type = false;
    
    if($params['format']=='json' && @$params['detail']){
    
                //same code as in dt_recsearch.php
                $fieldtypes_ids = is_array($params['detail'])?$params['detail']:explode(',',$params['detail']);
                $f_res = array();
                $retrieve_header_fields = array();
                foreach ($fieldtypes_ids as $dt_id){

                    if(is_numeric($dt_id) && $dt_id>0){
                        array_push($f_res, $dt_id);
                    }else if(strpos($dt_id,'rec_')===0){
                        array_push($retrieve_header_fields, $dt_id);
                    }
                }
                
                if(is_array($f_res) && count($f_res)>0){
                    $retrieve_detail_fields = $f_res;
                }else{
                    $retrieve_detail_fields = false;
                }
                if(count($retrieve_header_fields)==0){
                    $retrieve_header_fields = null;
                }else{
                    //always include rec_ID and rec_RecTypeID
                    if(!in_array('rec_RecTypeID',$retrieve_header_fields)) {array_unshift($retrieve_header_fields, 'rec_RecTypeID');}
                    if(!in_array('rec_ID',$retrieve_header_fields)) {array_unshift($retrieve_header_fields, 'rec_ID');}
                    $retrieve_header_fields = implode(',', $retrieve_header_fields);
                }
        
        
    }else
    if($params['format']=='iiif' || ($params['format']=='json' && @$params['extended']==3)){
        $retrieve_detail_fields = array('file');
        $retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';
    }else
    if(@$params['leaflet']){
        //for leaflet get only limited set of fields 
        $retrieve_detail_fields = null;
        $retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';
    }else if(@$params['datatable']>0){ 

        //for datatable convert  $params['columns'] to array
        
        /*
0: ["rec_ID","rec_Title"],
3: ["rec_ID", "rec_RecTypeID", "1", "949", "9", "61"]
5: ["1", "38"]
        */
        $need_tags = false;
        $retrieve_detail_fields = array();
        $retrieve_header_fields = array();//header fields
        $retrieve_relmarker_fields = array();
        
        if(is_array($params['columns'])){
            foreach($params['columns'] as $idx=>$column){
                $col_name = $column['data'];

                if(strpos($col_name,'.')>0){
                    list($rt_id, $col_name) = explode('.',$col_name);
                    
                    if(!@$row_placeholder[$rt_id]) {$row_placeholder[$rt_id] = array();}
                    $row_placeholder[$rt_id][$col_name] = '';
                }else{
                    $rt_id = 0;
                    $row_placeholder[$col_name] = '';
                }

                if($col_name=='rec_Tags'){
                    $need_tags = true;
                }else if($rt_id==0){
                    if(strpos($col_name,'rec_')===0){
                        array_push($retrieve_header_fields, $col_name);
                    }else if(strpos($col_name, 'lt')===0 || strpos($col_name, 'rt')===0){
                        array_push($retrieve_detail_fields, substr($col_name, 2));
                    }else{
                        if($col_name === 'typename'){
                            $need_rec_type = true;
                        }else{
                            array_push($retrieve_detail_fields, $col_name);
                        }
                    }
                }
                
                if(!array_key_exists($rt_id, $columns)) {
                      $columns[$rt_id] = array();
                }
                array_push($columns[$rt_id], $col_name);
            }
        }
        
        if(!is_array($retrieve_detail_fields) || count($retrieve_detail_fields)==0){
            $retrieve_detail_fields = false;
        }
        
        //always include rec_ID and rec_RecTypeID fields 
        if(!in_array('rec_ID',$retrieve_header_fields)){
            array_push($retrieve_header_fields,'rec_ID');
            array_push($columns['0'],'rec_ID');
        }
        if(!in_array('rec_RecTypeID',$retrieve_header_fields)){
            array_push($retrieve_header_fields,'rec_RecTypeID');
            array_push($columns['0'],'rec_RecTypeID');
        }

        $retrieve_header_fields = implode(',', $retrieve_header_fields);
        
    }
    else{
        
        $retrieve_header_fields = array();
        $retrieve_detail_fields = array();
        
        if(@$params['columns'] && is_array(@$params['columns'])){
            foreach($params['columns'] as $idx=>$col_name){
                if(strpos($col_name,'rec_')===0){
                    array_push($retrieve_header_fields, $col_name);
                }else if($col_name>0){
                    array_push($retrieve_detail_fields, $col_name);
                }
        
            }
        }
        
        //header fields
        $retrieve_header_fields = (count($retrieve_header_fields)>0)?implode(',', $retrieve_header_fields):null;
        
        //detail fields
        $retrieve_detail_fields = (count($retrieve_detail_fields)>0)?$retrieve_detail_fields:true;
        
    }
    
    //MAIN LOOP  ----------------------------------------
    $records_count = (@$params['datatable']>0)?$records_original_count:count($records);
    
    $idx = 0;
    //while ($idx<$records_count){   //loop by record ids
    foreach($records as $idx=>$record){
    
        //$recID = $records[$idx];
        if(is_array($record)){
            //record data is already loaded
            //$record = $records[$idx];
            $recID = $record['rec_ID'];
        }else{
            $recID = $record;
            $record = recordSearchByID(self::$system, $recID, $retrieve_detail_fields, $retrieve_header_fields );
        }
        $idx++;
        
        $rty_ID = @$record['rec_RecTypeID'];
        
        //$record['origin'] = @$_SERVER['HTTP_ORIGIN'];
        
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
        
        if($rty_ID>0){
            if(!@$rt_counts[$rty_ID]){
                $rt_counts[$rty_ID] = 1;
            }else{
                $rt_counts[$rty_ID]++;
            }
        }
        
        if($params['format']=='geojson'){
            
            $feature = self::_getGeoJsonFeature($record, (@$params['extended']==2), 
                @$params['simplify'],  //simplify
                //mode for details if leaflet - description only
                @$params['leaflet']?0:@$params['detail_mode'], 
                $find_by_geofields, 
                $find_geo_by_pointer_rty,
                $search_all_geofields?null:$find_geo_by_pointer_dty,
                $find_timefields,
                @$params['leaflet'] && @$params['separate']);//separate multi geo values per record as separate entries
                
            if(@$params['leaflet']){ //include only geoenabled features, timeline data goes in the separate timeline array
                if(@$feature['when']){
                    $timeline_data[] = array('rec_ID'=>$recID, 'when'=>$feature['when']['timespans'], 
                        'rec_RecTypeID'=>$rty_ID, "rec_Title"=>$record['rec_Title']);
                    
                    foreach($feature['timevalues_dty'] as $dty_ID){
                        if(!in_array($dty_ID, $timeline_dty_ids)){
                            $timeline_dty_ids[] = $dty_ID;  //unique list of all date fields 
                        } 
                    }
                    
                    $feature['when'] = null;
                    unset($feature['when']);
                    $feature['timevalues_dty'] = null;
                    unset($feature['timevalues_dty']);
                }

                if( (defined('RT_TLCMAP_DATASET') && $rty_ID==RT_TLCMAP_DATASET) || 
                (defined('RT_MAP_LAYER') && $rty_ID==RT_MAP_LAYER) ){
                    array_push($layers_record_ids, $recID);
                }

                if(!@$feature['geometry']) {continue;}

                $geojson_ids[] = $recID;
                /* simplify
                array_push($geojson_ids['all'], $recID);

                if(@$feature['geofield']>0){
                if(@$geojson_ids[$feature['geofield']]){ 
                //record ids grouped by geo pointer fields
                array_push($geojson_ids[$feature['geofield']], $recID);
                }
                $feature['geofield'] = null;
                unset($feature['geofield']);
                }*/

            }

            if(@$feature['geometries']){

                $geoms = $feature['geometries'];
                $geoms_dty = $feature['geometries_dty'];
                
                $feature['geometries'] = null;
                unset($feature['geometries']);
                $feature['geometries_dty']=null;
                unset($feature['geometries_dty']);
                
                foreach ($geoms as $idx=>$geom){
                        $feature['geometry'] = $geom;
                        $feature['properties']['rec_GeoField'] = $geoms_dty[$idx];//dty_ID
                        fwrite($fd, $comma.json_encode($feature));
                        $comma = ',';
                        
                        if(!in_array($geoms_dty[$idx], $geojson_dty_ids)){
                            $geojson_dty_ids[] = $geoms_dty[$idx];//unique list of all geofields 
                        } 
                }
                $geojson_rty_ids = array_keys($rt_counts);
                
            }else{
                fwrite($fd, $comma.json_encode($feature));
            }
            
            $comma = ',';
        
        }else if($params['format']=='json'){ 
            
            if(@$params['datatable']>0){
                
                recordSearchDetailsRelations(self::$system, $record, $retrieve_detail_fields);
/*
                if($need_rec_type && $rty_ID>0){ // Add record type to details
                   $query = 'select rty_Name from defRecTypes where rty_ID = ' . $rty_ID . ' LIMIT 1';
                   $type = mysql__select_value(self::$system->get_mysqli(), $query);
                   $record['typename'] = $type;
                }
*/
                $feature = self::_getJsonFlat( $record, $columns, $row_placeholder, 0, true );
                
                fwrite($fd, $comma.json_encode($feature));
                
            }else if(@$params['extended']>0 && @$params['extended']<3){ //with concept codes and labels
                $feature = self::_getJsonFeature($record, $params['extended']);
                fwrite($fd, $comma.json_encode($feature));
            }else if(@$params['extended']==3){

                $feature = self::_getMediaViewerData($record);
                if($feature){
                    fwrite($fd, $comma.$feature);
                }else{
                    continue;
                }
                
            }else{
                fwrite($fd, $comma.json_encode($record));//as is
            }
            $comma = ',';


        }else if($params['format']=='iiif'){ 
            
            $canvas = self::getIiifResource($record, @$params['iiif_image']);
            if($canvas && $canvas!=''){
                fwrite($fd, $comma.$canvas);
                $comma = ",\n";
                $cnt++;
            }
            //not more than 1000 records per manifest
            //or the only image if it is specified
            if($cnt>1000 || $params['iiif_image']) {break;}
            
        }else if($params['format']=='gephi'){ 

            $name   = htmlspecialchars($record['rec_Title']);
            $image  = htmlspecialchars(HEURIST_RTY_ICON.$rty_ID);
            $recURL = htmlspecialchars(HEURIST_BASE_URL.'recID='.$recID.'&fmt=html&db='.HEURIST_DBNAME);

            $rec_values = '';
            if(is_array($retrieve_detail_fields)){

                $att_id = 4;
                foreach($retrieve_detail_fields as $dty_ID){

                    $att_id ++;
                    $values = array_key_exists($dty_ID, $record['details']) && is_array($record['details'][$dty_ID]) ? 
                                $record['details'][$dty_ID] : null;

                    if(empty($values)){
                        continue;
                    }

                    self::_processFieldData($dty_ID, $values);

                    if(empty($values)){
                        continue;
                    }

                    $rec_values .= "\n\t\t\t<attvalue for=\"{$att_id}\" value=\"{$values}\"/>";
                }
            }

            $gephi_node = <<<XML
<node id="{$recID}" label="{$name}">                               
    <attvalues>
        <attvalue for="0" value="{$name}"/>
        <attvalue for="1" value="{$image}"/>
        <attvalue for="2" value="{$rty_ID}"/>
        <attvalue for="3" value="0"/>
        <attvalue for="4" value="{$recURL}"/>{$rec_values}
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
            fwrite($fd, substr($xml->asXML(),38));//remove header
        }
        
        
    }//while records
    
    if($is_tlc_export){ // && $idx==count($records)
        //calculate extent of mapdocument - last record
        $records[$idx] = self::_calculateSummaryExtent($maplayer_extents, true, $params['tlcmap'], $maplayer_records);
        $is_tlc_export = false; //avoid infinite loop
    }
    
    
    //CLOSE brackets ----------------------------------------
    
    if($params['format']=='geojson'){
        
        fwrite($fd, ']');
        
        if(@$params['leaflet']){ //return 2 array - pure geojson and timeline items
        
           fwrite($fd, ',"timeline":'.json_encode($timeline_data));
           fwrite($fd, ',"timeline_dty_ids":'.json_encode($timeline_dty_ids));//unique list of all date fields 
           fwrite($fd, ',"geojson_ids":'.json_encode($geojson_ids));
           fwrite($fd, ',"geojson_dty_ids":'.json_encode($geojson_dty_ids));//unique list of all geofields 
           fwrite($fd, ',"geojson_rty_ids":'.json_encode($geojson_rty_ids));
           fwrite($fd, ',"layers_ids":'.json_encode($layers_record_ids).'}');
        }else{
           fwrite($fd, '}');//close for FeatureCollection
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
        
    }else if($params['format']=='iiif'){

        if(self::$version==2){        
            fwrite($fd, ']}],"structures": []}');
        }else{
            fwrite($fd, ']}');
        }
        
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
        fileDelete($tmp_destination);
        echo $output; 
        unset($output);
        
        return true;
    }else{
        
        //$content = stream_get_contents($fd);
        fclose($fd);
        
        //
        // download output as a file
        //
        if(@$params['filename'] || @$params['metadata']){
            
            $record = null;
            $originalFileName = null;
            if(@$params['metadata']){
                list($db_meta,$rec_ID) = explode('-',$params['metadata']);
                if(!$db_meta && $rec_ID) {$db_meta = self::$system->dbname();}
                
                $record = array("rec_ID"=>$rec_ID);
                if($db_meta!=self::$system->dbname()){
                    self::$system->init($db_meta, true, false);
                    //mysql__usedatabase(self::$mysqli, $db_meta);
                }
                
                if(self::$system->defineConstant('DT_NAME', true)){
                    
                    //$val = mysql__select_value(self::$mysqli,'select dtl_Value from recDetails where rec_ID='
                    //    .$params['metadata'].' and dtl_DetailTypeID='.DT_NAME);
                    //if($val){
                        //$originalFileName = USanitize::sanitizeFileName($val);
                    //}
                    
                    recordSearchDetails(self::$system, $record, array(DT_NAME));
                    if(is_array($record['details'][DT_NAME])){
                        $originalFileName = USanitize::sanitizeFileName(array_values($record['details'][DT_NAME])[0]);
                    }
                }
                if(!$originalFileName) {$originalFileName = 'Dataset_'.$record['rec_ID'];}
                
            }else{
                $originalFileName = $params['filename'];
            }
            
            
            //save into specified file in scratch folder
            $file_records  = $originalFileName.'.'.($params['format']=='gephi'?'gexf':$params['format']);

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
                . sprintf('filename="%s";', rawurlencode($file_zip))
                . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));
            
            header('Content-Type: application/zip');
            header($contentDispositionField);
            header('Content-Length: ' . self::get_file_size($file_zip_full));
            self::readfile($file_zip_full);
                                     
            // remove the zip archive and temp files
            //unlink($file_zip_full);
            //unlink($file_metadata_full);
            fileDelete($tmp_destination);
            return true;
        }else{
            //$content = file_get_contents($tmp_destination);

            if(@$params['restapi']==1){
                //header("Access-Control-Allow-Origin: *");
                //header("Access-Control-Allow-Methods: POST, GET");
                
                // Allow from any origin
                if (isset($_SERVER['HTTP_ORIGIN'])) {
                    // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
                    // you want to allow, and if so:
                    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
                    header('Access-Control-Allow-Credentials: true');
                    header('Access-Control-Max-Age: 5');// default value 5 sec
                    //header('Access-Control-Max-Age: 86400');// cache for 1 day
                /*}else if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        
                    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                        // may also be using PUT, PATCH, HEAD etc
                        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
                    
                    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
                    exit(0);*/
                }else{
                    //2024-02-23 header("Access-Control-Allow-Origin: *");
                }                
            }
            
            if($params['format']=='json' || $params['format']=='geojson' || $params['format']=='iiif'){
                header( 'Content-Type: application/json');
            }else{
                header( 'Content-Type: text/xml');
            }
        
            if(@$params['file']==1 || @$params['file']===true){
                
                if($params['format']=='iiif'){
                    $filename = 'manifest_'.$params['db'].'_'.date("YmdHis").'.json';
                }else{
                    $filename = 'Export_'.$params['db'].'_'.date("YmdHis").'.'.($params['format']=='gephi'?'gexf':$params['format']);
                }
                
                header('Content-Disposition: attachment; filename='.$filename);
                header('Content-Length: ' . self::get_file_size($tmp_destination));
            }
            
            if(@$params['restapi']==1){
                
                if(count($rt_counts)==0){
                    http_response_code(404);
                }else{
                    http_response_code(200);
                }
            }
            self::readfile($tmp_destination);
            fileDelete($tmp_destination);
            
            return true;
//            exit($content);
        }
    }
    
}

//
// read file by 10MB chunks
//
private static function readfile($file_path) {
    $file_size = self::get_file_size($file_path);
    $chunk_size = 10 * 1024 * 1024; // 10 MiB
    if ($chunk_size && $file_size > $chunk_size) {
        $handle = fopen($file_path, 'rb');
        while (!feof($handle)) {
            echo fread($handle, $chunk_size);
            @ob_flush();
            @flush();
        }
        fclose($handle);
        return $file_size;
    }
    return readfile($file_path);
}
  
  
// Fix for overflowing signed 32 bit integers,
// works for sizes up to 2^32-1 bytes (4 GiB - 1):
private static function fix_integer_overflow($size) {
    if ($size < 0) {
        $size += 2.0 * (PHP_INT_MAX + 1);
    }
    return $size;
}

private static function get_file_size($file_path, $clear_stat_cache = false) {
    if ($clear_stat_cache) {
        if (version_compare(phpversion(), '5.3.0') >= 0) { //strnatcmp(phpversion(), '5.3.0') >= 0
            clearstatcache(true, $file_path);
        } else {
            clearstatcache();
        }
    }
    if(file_exists($file_path)){
        return self::fix_integer_overflow(filesize($file_path));
    }else{
        return 0;
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

            $startDate = empty($link->dtl_StartDate) ? '' : $link->dtl_StartDate;
            $endDate = empty($link->dtl_EndDate) ? '' : $link->dtl_EndDate;

            if(in_array($source, $records) && in_array($target, $records)){

                if($dtID > 0) {
                    $relationName = self::$defDetailtypes['typedefs'][$dtID]['commonFields'][$idx_dname];
                    $relationID = $dtID;
                }else if($trmID > 0) {
                    $relationName = self::$defTerms->getTermLabel($trmID, true);
                    $relationID = $trmID;
                }

                $rel_values = '';
                $att_id = 5;
                if(!empty(self::$relmarker_fields) && !empty($link->relationID) && intval($link->relationID) > 0){

                    $record = recordSearchByID(self::$system, intval($link->relationID), self::$relmarker_fields, 'rec_ID');

                    foreach(self::$relmarker_fields as $dty_ID){
                        
                        $att_id ++;

                        if(!array_key_exists($dty_ID, $record['details']) || empty($record['details'][$dty_ID])){
                            continue;
                        }

                        $values = $record['details'][$dty_ID];
                        self::_processFieldData($dty_ID, $values);

                        if(empty($values)){
                            continue;
                        }

                        $rel_values .= "\n\t\t<attvalue for=\"{$att_id}\" value=\"{$values}\"/>";
                    }
                }

                $relationName  = htmlspecialchars($relationName); 
                $links_cnt++;

                $edges = $edges.<<<XML
<edge id="{$links_cnt}" source="{$source}" target="{$target}" weight="1">                               
    <attvalues>
        <attvalue for="0" value="{$relationID}"/>
        <attvalue for="1" value="{$relationName}"/>
        <attvalue for="3" value="1"/>
        <attvalue for="4" value="{$startDate}"/>
        <attvalue for="5" value="{$endDate}"/>{$rel_values}
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
private static function _calculateSummaryExtent($maplayer_extents, $is_return_rec, $tlc_mapdoc_name, $maplayer_records){

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
            $record['rec_Title'] = $tlc_mapdoc_name;
            $record['rec_URL'] = '';
            $record['rec_ScratchPad'] = '';
            $record["details"] = array(
                DT_NAME=>array('1'=>$tlc_mapdoc_name),
                DT_MAP_BOOKMARK=>array('2'=>($mbookmark!=null?$mbookmark:self::$mapdoc_defaults[DT_MAP_BOOKMARK])),
                DT_ZOOM_KM_POINT=>array('3'=>($zoomKm>0?$zoomKm:self::$mapdoc_defaults[DT_ZOOM_KM_POINT])),
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
// $detail_mode - 0  - only header fields rec_ID, RecTypeID, rec_Title and description if details are defined (for leaflet output)
//                1  - details inline
//                2  - all details in "details" subarray          
// $find_by_geofields - search only specified geo fields (in main or linked records)

// if $find_by_geofields is not defined and 
// if there is not geo data in main record it may search geo in linked records 
// $find_geo_by_pointer_rty - if true it searches for linked RT_PLACE 
//                        or it is array of rectypes defined in sys_TreatAsPlaceRefForMapping + RT_PLACE
// $find_geo_by_pointer_dty - list of pointer fields linked to record with geo field (narrow $find_geo_by_pointer_rty)
// $separate_geo_by_dty - if true it separates multi geo values per record as separate entries, otherwise it creates GeometryCollection
//
private static function _getGeoJsonFeature($record, $extended=false, $simplify=false, $detail_mode=2, 
                $find_by_geofields=null, //search only specified geo fields (in main or linked records)
                $find_geo_by_pointer_rty=false, 
                $find_geo_by_pointer_dty=null, 
                $find_timefields=null,
                $separate_geo_by_dty){

    if(!($detail_mode==0 || $detail_mode==1 || $detail_mode==2)){
        $detail_mode=2;
    }
                    
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
    $geovalues_dty = array();
    $timevalues = array();
    $timevalues_dty = array();
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
                    
                    if($find_by_geofields==null || in_array($dty_ID, $find_by_geofields)){

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
                               $geovalues_dty[] = $dty_ID;
                            }
                        }catch(Exception $e){
                        }

                        $val = $wkt;
                    
                    }
                    
                    continue;  //it will be included into separate geometry property  
                }
            }else{
                if($field_type=='date' || $field_type=='year'){
                    
                    if($find_timefields==null || in_array($dty_ID, $find_timefields)){
                    
                        if($dty_ID==DT_START_DATE){
                            $date_start = $value;
                        }else if($dty_ID==DT_END_DATE){
                            $date_end = $value;
                        }else if($value!=null){
                            //parse temporal
                            $ta = new Temporal($value);
                            $ta = $ta->getTimespan(true);
                            if($ta!=null){
                                $ta[] = $dty_ID;
                                $timevalues[] = $ta;  //temporal json array for geojson
                                $timevalues_dty[] = $dty_ID;
                            }
                        }
                    }
                }else if(defined('DT_SYMBOLOGY') && $dty_ID==DT_SYMBOLOGY){
                    $symbology = json_decode($value,true);
                //}else if(defined('DT_EXTENDED_DESCRIPTION') && $dty_ID==DT_EXTENDED_DESCRIPTION){
                //    $ext_description = $value;
                }else if(defined('DT_MINIMUM_ZOOM_LEVEL') && $dty_ID==DT_MINIMUM_ZOOM_LEVEL){
                    $res['properties']['rec_MinZoom'] = $value;                    
                }else if(defined('DT_MAXIMUM_ZOOM_LEVEL') && $dty_ID==DT_MAXIMUM_ZOOM_LEVEL){
                    $res['properties']['rec_MaxZoom'] = $value;                    
                }
                $val = $value;
            }
            
            if(!isset($val)) {$val = '';}

            $val = array('ID'=>$dty_ID,'value'=>$val);

            if(is_array($value) && @$value['id']>0){ //resource
                $val['resourceTitle']     = @$value['title'];
                $val['resourceRecTypeID'] = @$value['type'];
            }

            if($extended){
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = self::$defTerms->getTermLabel($val['value'], true);
                    $term_code  = self::$defTerms->getTermCode($val['value']);
                    if($term_code) {$val['termCode'] = $term_code;}
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

    
    if($detail_mode==0){ //for leaflet - header and description only
        if($ext_description) {$res['properties']['description'] = $ext_description;}
        $res['properties']['details'] = null;
        unset($res['properties']['details']);
    }else if($detail_mode==1){
        //details are inline
        $det = $res['properties']['details'];
        for($k=0; $k<count($det); $k++){
            $res['properties'][$det[$k]['fieldName']] = $det[$k]['value'];
        }
        unset($res['properties']['details']);
    }
    
    if(is_array($find_by_geofields) && count($find_by_geofields)>0){
        //find geo values in linked records 
        foreach ($find_by_geofields as $idx => $code){
            if(is_array($code) && @$code['id'] && @$code['q']){
                //@todo group details by same queries
                $geodetails = recordSearchLinkedDetails(self::$system, $record['rec_ID'], $code['id'], $code['q']);
                foreach ($geodetails as $dty_ID=>$field_details) {
                    foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
                        if(@$value['geo']){
                            $wkt = $value['geo']['wkt'];
                            $json = self::_getJsonFromWkt($wkt, $simplify);
                            if($json){
                               $geovalues[] = $json; 
                               //$geovalues_dty[] = $value['geo']['pointerDtyID'];
                            }
                        }
                    }
                }
            }/*else if (is_numeric($code) && intval($code)>0){
                if(@$record['details'][$code]['geo']){
                            $wkt = $record['details'][$code]['geo']['wkt'];
                            $json = self::_getJsonFromWkt($wkt, $simplify);
                            if($json){
                               $geovalues[] = $json; 
                            }
                }
            }*/
        }
    
    }else
    //record does not contains geo field - search geo in linked records
    if( (!is_array($geovalues) || count($geovalues)==0) && 
        ($find_geo_by_pointer_rty===true || (is_array($find_geo_by_pointer_rty) && count($find_geo_by_pointer_rty)>0)) ){
        
        //this record does not have geo value - find it in related/linked places
        $point0 = array();
        $point1 = array();
        $points = array();
        
        //returns array of wkt
        //"geo" => array("type","wkt","placeID","pointerDtyID")
        $geodetails = recordSearchGeoDetails(self::$system, $record['rec_ID'], 
                                $find_geo_by_pointer_rty, $find_geo_by_pointer_dty);
                                 
        foreach ($geodetails as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
                    $wkt = $value['geo']['wkt'];
                    $json = self::_getJsonFromWkt($wkt, $simplify);
                    if($json){
                       $geovalues[] = $json; 
                       if($value['geo']['pointerDtyID']>0){
                           $geovalues_dty[] = $value['geo']['pointerDtyID'];
                           
                           if($json['type']=='Point' && $find_geo_by_pointer_dty==null){
                               $pointerDtyID =  @$value['geo']['pointerDtyID'];
                               
                               if(defined('DT_PLACE_END') && $pointerDtyID==DT_PLACE_END){
                                    $point1[] = $json;            
                               }else if(defined('DT_PLACE_START') && $pointerDtyID==DT_PLACE_START){
                                    $point0[] = $json;            
                               }else if(defined('DT_PLACE_START') && $pointerDtyID==DT_PLACE_END2){
                                    $point1[] = $json;            
                               }else if(defined('DT_PLACE_START') && $pointerDtyID==DT_PLACE_START2){
                                    $point0[] = $json;            
                               }else if(defined('DT_PLACE_TRAN') && $pointerDtyID==DT_PLACE_TRAN){
                                    $points[] = $json;            
                               }
                           }
                       }else if($value['geo']['relationID']>0){
                           $geovalues_dty[] = 'relation:'.$value['geo']['relationID'];
                       }
                    }
            } //foreach
        } //foreach
        
        //special case
        //create link path from begin to end place
        if (count($point1)>0 && count($point0)>0 || count($points)>0){
            //$geovalues = array();
            
            //many start points and transition points - star from start points to first transition
            if(count($point0)>1 || count($point1)>1){
                $path = array('type'=>'MultiLineString', 'coordinates'=>array());
                
                
                if(count($points)>0){

                    //adds lines from start to first transition    
                    if(count($point0)>0){
                        foreach($point0 as $pnt){
                            $path['coordinates'][] = array($pnt['coordinates'], $points[0]['coordinates']);
                        }                
                    }

                    $path_t = array();
                    foreach($points as $pnt){
                        $path_t[] = $pnt['coordinates'];
                    }                
                    $path['coordinates'][] = $path_t;
                    
                    //lines from last transition to end points
                    if(count($point1)>0){
                        $lidx = count($points)-1;
                        foreach($point1 as $pnt){
                            $path['coordinates'][] = array($points[$lidx]['coordinates'], $pnt['coordinates']);
                        }                
                    }
                    
                }else if(count($point0)==count($point1)){
                    //adds lines from start to end
                    foreach($point0 as $idx=>$pnt){
                        $path['coordinates'][] = array($pnt['coordinates'], $point1[$idx]['coordinates']);
                    }                
                }
                
                
            }else{
                $path = array('type'=>'LineString', 'coordinates'=>array());
                
                if(count($point0)>0) {$path['coordinates'][] = $point0[0]['coordinates'];}

                if(count($points)>0)
                    foreach($points as $pnt){
                        $path['coordinates'][] = $pnt['coordinates'];
                    }                
                
                if(count($point1)>0) {$path['coordinates'][] = $point1[0]['coordinates'];}
            
            }
            
            
            if(count($path['coordinates'])>0){
                $geovalues[] = $path;
                $geovalues_dty[] = 'Path';
            }
        }
    }//if search for linked values

    // $res['geometry'] - merged GeometryCollection
    // $res['geometries'] - separated by fields  $res['geometries_dty']  
    // $res['geometries_dty'] - dty_IDs
    if(is_array($geovalues)){
        if(count($geovalues)>1){
            $res['geometry'] = array('type'=>'GeometryCollection','geometries'=>$geovalues);
        }else if(count($geovalues)==1){
            $res['geometry'] = $geovalues[0];
        }
        if($separate_geo_by_dty){
            $res['geometries'] = $geovalues;
            $res['geometries_dty'] = $geovalues_dty; //dty_ID                 
        }
    }

    //if data_start and date_end are temporal objects - take action    
    if($date_start || $date_end){

        $dty_ID = intval(DT_START_DATE);
        
        if($date_start && $date_end){ //both are defined
            $dt = Temporal::mergeTemporals($date_start, $date_end);
        }else{
            if(!$date_start){
                $date_start = $date_end;
                $dty_ID = intval(DT_END_DATE);
            }
            $dt = new Temporal($date_start);
        }
        
        if($dt && $dt->isValid())
        {
            $ta = $dt->getTimespan(true);
            if($ta!=null){
                //array($date_start, '', '', $date_end, '');
                $ta[] = $dty_ID;
                $timevalues[] = $ta;  //temporal json array for geojson
                $timevalues_dty[] = $dty_ID;
            }
        }
    }

    if(count($timevalues)>0){
        
//profile: Flat(0), Central(1) (circa), Slow Start(2) (before), Slow Finish(3) (after) - responsible for gradient
//determination: Unknown(0), Conjecture(2), Measurment(3), Attested(1)  - color depth
// start.earliest - end.latest => vis-item-bbox
// start.earlist~latest => vis-item-bbox-start 
// end.earlist~latest => vis-item-bbox-end
            
            //https://github.com/kgeographer/topotime/wiki/GeoJSON%E2%80%90T
            // "timespans": [["-323-01-01 ","","","-101-12-31","Hellenistic period"]],  
            // [start, latest-start, earliest-end, end, label, profile-start, profile-end, determination]
            $res['when'] = array('timespans'=>$timevalues);
            $res['timevalues_dty'] = $timevalues_dty;
    }
    if($symbology){
        $res['style'] = $symbology; //individual symbology per feature
    }

    return $res;

} // _getGeoJsonFeature

//
// Convert WKT to geojson and simplifies coordinates  
// @TODO use mapCoordinates.php
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

            if(is_array(@$json['coordinates']) && count($json['coordinates'])>0){

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
private static function _getJsonFlat( $record, $columns, $row_placeholder, $level=0, $is_datatables = false ){

    $res = ($level==0)?$row_placeholder:array();

    if($level==0){
        $rt_id = 0;
    }else{
        $rt_id = 't'.$record['rec_RecTypeID'];
    }

    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms(self::$system);
        self::$defTerms = new DbsTerms(self::$system, self::$defTerms);
    }

    if(!array_key_exists($rt_id, $columns)) {return null;}

    foreach($columns[$rt_id] as $column){

        $col_name = $column; //($rt_id>0 ?$rt_id.'.':'').   

        //HEADER FIELDS
        if ($column=='tags' || $column=='rec_Tags'){
            $value = recordSearchPersonalTags(self::$system, $record['rec_ID']);
            $res[$col_name] = ($value==null)?'':implode(' | ', $value);
        }else if(strpos($column,'rec_')===0){
            $res[$col_name] = $record[$column];
        }else if($column=='ids'){
            $res[$col_name] = $record['rec_ID'];
        }else if($column=='typeid'){
            $res[$col_name] = $record['rec_RecTypeID'];
        }else if($column=='typename'){
            if(self::$defRecTypes==null) self::$defRecTypes = dbs_GetRectypeStructures(self::$system, null, 0);
            $res[$col_name] = self::$defRecTypes['names'][$record['rec_RecTypeID']];
        }else if($column=='added'){
            $res[$col_name] = $record['rec_Added'];
        }else if($column=='modified'){
            $res[$col_name] = $record['rec_Modified'];
        }else if($column=='addedby'){
            $res[$col_name] = $record['rec_AddedBy'];
        }else if($column=='url'){
            $res[$col_name] = $record['rec_URL'];
        }else if($column=='notes'){
            $res[$col_name] = $record['rec_ScratchPad'];
        }else if($column=='owner'){
            $res[$col_name] = $record['rec_OwnerUGrpID'];
        }else if($column=='visibility'){
            $res[$col_name] = $record['rec_NonOwnerVisibility'];
        }else{
            $res[$col_name] = '';//placeholder
        }
    }

    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes(self::$system, null, 2);
    }
    $idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];

    //convert details to proper JSON format
    if(@$record['details'] && is_array($record['details'])){

        foreach ($record['details'] as $dty_ID=>$field_details) {

            $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];

            if($field_type=='resource' && in_array('lt'.$dty_ID, $columns[0]) !== false){ // account for resources, which expects 'lt' before the Id
                $dty_ID = 'lt'.$dty_ID;
            }

            if(!in_array($dty_ID, $columns[$rt_id])) {continue;}

            $col_name = $dty_ID; //($rt_id>0 ?$rt_id.'.':'').$dty_ID;

            $res[$col_name] = array();

            foreach($field_details as $dtl_ID=>$field_value){ //for detail multivalues

                if($field_type=='resource' || $field_type=='relmarker'){
                    //continue;
                    //if multi constraints - search all details
                    $link_rec_Id =  $field_value['id'];
                    $relation_id =  @$field_value['relation_id'];
                    $record = recordSearchByID(self::$system, $link_rec_Id, true, null );
                    $field_value = self::_getJsonFlat( $record, $columns, null, $level+1 );

                    if($field_value!=null){
                        $rt_id_link = 't'.$record['rec_RecTypeID'];
                        if(@$res[$rt_id_link]){ //such record type already exists in flat array - create multiple values
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
                        if($field_type=='relmarker' && $relation_id>0){
                            
                            $record2 = recordSearchByID(self::$system, $relation_id, true, null );
                            $field_value2 = self::_getJsonFlat( $record2, $columns, null, $level+1 );
                            if($field_value2!=null){
                                $rt_id_link = 't'.$record2['rec_RecTypeID'];//t1
                                if(@$res[$rt_id_link]){
                                    foreach($field_value2 as $col=>$field){
                                        //$col = 'r.'.$col;
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
                                    $res[$rt_id_link] = $field_value2;    
                                }
                            }
                        }
                    }
                    $field_value = $record['rec_Title'];//$link_rec_Id Record ID replaced with Record Title

                }else if ($field_type=='file'){

                    $field_value = $field_value['file']['ulf_ObfuscatedFileID'];

                }else if ($field_type=='geo'){

                    $field_value = @$field_value['geo']['wkt'];

                }else if (($field_type=='enum' || $field_type=='relationtype')){

                    $field_value = self::$defTerms->getTermLabel($field_value, true);

                }else if ($is_datatables && $field_type=='date'){

                    $temporal = new Temporal($field_value);
                    $field_value = $temporal && $temporal->isValid() ? $temporal->toReadableExt('', true) : $field_value;

                }

                if($field_value!=null){
                    array_push($res[$col_name], $field_value);
                }
            } //for detail multivalues

            if(is_array(@$res[$col_name]) && count($res[$col_name])==1){
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
    
    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes(self::$system, null, 2);
    }
    $idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    
    if($mode==2){ //extended - with concept codes and names/labels
    
        if(self::$defRecTypes==null) {
            self::$defRecTypes = dbs_GetRectypeStructures(self::$system, null, 2);
        }    
        if(self::$defTerms==null) {
            self::$defTerms = dbs_GetTerms(self::$system);
            self::$defTerms = new DbsTerms(self::$system, self::$defTerms);
        }
        $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
        
        $idx_dname = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
        $idx_ccode = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];
        
        
        $idx_ccode2 = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_ConceptID'];
        
        $res['rec_RecTypeName'] = self::$defRecTypes['names'][$rty_ID];
        $idx_ccode2 = self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$idx_ccode2];
        if($idx_ccode2) {$res['rec_RecTypeConceptID'] = $idx_ccode2;}
        
    }

    //convert details to proper JSON format, extract geo fields and convert WKT to geojson geometry
    foreach ($record['details'] as $dty_ID=>$field_details) {

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
        
            $val = array('dty_ID'=>$dty_ID,'value'=>$value);
            $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];

            if($mode==2){ //extended - with concept codes and names/labels
                
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = self::$defTerms->getTermLabel($value, true);
                    $term_code  = self::$defTerms->getTermCode($value);
                    if($term_code) {$val['termCode'] = $term_code; }
                    $term_code  = self::$defTerms->getTermConceptID($value);
                    if($term_code) {$val['termConceptID'] = $term_code;    }
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
            if($field_type=='date'){ //decode json temporal
                $temporal = json_decode($val['value']);
                if($temporal!=null){
                    $val['value'] = $temporal;
                }
            }

            $res['details'][] = $val;
        } //for detail multivalues
    } //for all details of record

    return $res;
}

//
// convert heurist record to plain object for mediaViewer
// 
// return null if not media content found
//
private static function _getMediaViewerData($record){

    $res = '';
    $comma = '';
    $info = array();
    
    //1. get "file" field values
    foreach ($record['details'] as $dty_ID=>$field_details) {
        foreach($field_details as $dtl_ID=>$file){
            array_push($info, $file['file']);
        }
    }
    
    //2. get file info
    if(count($info)>0){
    
        foreach($info as $fileinfo){
            
            if(strpos($fileinfo['ulf_OrigFileName'],'_tiled')===0) {continue;}
    
            $mimeType = $fileinfo['fxm_MimeType'];
        
            $resource_type = null;

            if(strpos($mimeType,"video/")===0){
                $resource_type = 'Video';
            }else if(strpos($mimeType,"audio/")===0){
                if(strpos($mimeType,"soundcloud")>0) {continue;}
                $resource_type = 'Sound';
            }else if(strpos($mimeType,"image/")===0 || $fileinfo['ulf_OrigFileName']=='_iiif_image'){
                $resource_type = 'Image';
            }
        
            if($resource_type){
            
                $fileid = $fileinfo['ulf_ObfuscatedFileID'];
                $external_url = $fileinfo['ulf_ExternalFileReference'];
                
                /*
                $item = '{rec_ID:'.$record['rec_ID'].', id:"'.$fileid.'",mimeType:"'.$mimeType
                        .'",filename:"'.htmlspecialchars($fileinfo['ulf_OrigFileName'])
                        .'",external:"'.htmlspecialchars($external_url).'"}';
                */

                $res = $res.$comma.json_encode(array('rec_ID'=>$record['rec_ID'],
                               'caption'=>htmlspecialchars($record['rec_Title']),
                               'id'=>$fileid,
                               'mimeType'=>$mimeType,
                               'filename'=>htmlspecialchars($fileinfo['ulf_OrigFileName']),
                               'external'=>htmlspecialchars($external_url)));//important need restore on client side
                $comma =  ",\n";
                               
            }
        }//for
    
    }//count($file_ids)>0
    
    
    return $res;    
}


//
// Converts heurist record to iiif canvas json
// It allows to see any media in mirador viewer
// 
// return null if not media content found
//
public static function getIiifResource($record, $ulf_ObfuscatedFileID, $type_resource='Canvas'){
    
    //validate $resource_type
    
    

    $canvas = '';
    $comma = '';
    $info = array();
    
    if($record==null){
        //find file info by obfuscation id
        $info = fileGetFullInfo(self::$system, $ulf_ObfuscatedFileID);
        
        if(count($info)>0){
            $label = trim(htmlspecialchars(strip_tags($info[0]['ulf_Description'])));
            
            if($label==''){
                //find name from linked record
                $query = 'SELECT rec_RecTypeID, rec_Title FROM Records, recDetails '
                .'WHERE rec_ID=dtl_RecID and dtl_UploadedFileID='.$info[0]['ulf_ID']
                .' LIMIT 1';
                
                $record = mysql__select_row(self::$mysqli, $query);
                $label = htmlspecialchars(strip_tags($record[1]));//rec_Title
                $rectypeID = $record[0];//rec_RecTypeID
            }else{
                $rectypeID = 5;
            }
            
        }else{
            self::$system->addError(HEURIST_NOT_FOUND, 'Resource with given id not found');
            return false;
        }
        
    }else{
    
        $label = htmlspecialchars(strip_tags($record['rec_Title']));
        $rectypeID = $record['rec_RecTypeID'];
        //1. get "file" from field values
        foreach ($record['details'] as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$file){
                
                if($ulf_ObfuscatedFileID){
                    if($file['file']['ulf_ObfuscatedFileID']==$ulf_ObfuscatedFileID){
                        array_push($info, $file['file']);
                        break 2;
                    }
                }else{
                    array_push($info, $file['file']);
                }
            }
        }
        
    }
        
    $label = preg_replace('/\r|\n/','\n',trim($label));
    
    //2. get file info
    if(count($info)>0){
        //$info = fileGetFullInfo(self::$system, $file_ids);
    
        foreach($info as $fileinfo){
    
        $mimeType = $fileinfo['fxm_MimeType'];
    
        $resource_type = null;

        if(strpos($mimeType,"video/")===0){
            if(strpos($mimeType,"youtube")>0 || strpos($mimeType,"vimeo")>0) {continue;}
            
            $resource_type = 'Video';
        }else if(strpos($mimeType,"audio/")===0){
            
            if(strpos($mimeType,"soundcloud")>0) {continue;}

            $resource_type = 'Sound';
        }else if(strpos($mimeType,"image/")===0 || $fileinfo['ulf_OrigFileName']=='_iiif_image'){
            $resource_type = 'Image';
        }
    
        if ((self::$version==2 && $resource_type!='Image') || ($resource_type==null)){
            continue;
        }
        
        $fileid = $fileinfo['ulf_ObfuscatedFileID'];
        $external_url = $fileinfo['ulf_ExternalFileReference'];
        if($external_url && strpos($external_url,'http://')!==0){ //download non secure external resource via heurist
            $resource_url = $external_url;  //external 
        }else{
            //to itself
            $resource_url = HEURIST_BASE_URL_PRO."?db=".HEURIST_DBNAME."&file=".$fileid;
        }
        
        $height = 800;
        $width = 1000;
        if($resource_type=='Image' && $fileinfo['ulf_OrigFileName']!='_iiif_image'){
            $img_size = getimagesize($resource_url);
            if(is_array($img_size)){
                $width = $img_size[0];
                $height = $img_size[1];
            }
        }
        
        
        $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
        if(file_exists($thumbfile)){
            $tumbnail_url = HEURIST_BASE_URL_PRO.'?db='.HEURIST_DBNAME.'&thumb='.$fileid;
        }else{
            //if thumb not exists - rectype thumb (HEURIST_RTY_ICON)
            $tumbnail_url = HEURIST_BASE_URL_PRO.'?db='.HEURIST_DBNAME.'&version=thumb&icon='.$rectypeID;
        }
        
        $service = '';
        $resource_id = '';
        
        //get iiif image parameters
        if($fileinfo['ulf_OrigFileName']=='_iiif_image'){ //this is image info - it gets all required info from json
            
                $iiif_manifest = loadRemoteURLContent($fileinfo['ulf_ExternalFileReference']);//retrieve iiif image.info to be included into manifest
                $iiif_manifest = json_decode($iiif_manifest, true);
                if($iiif_manifest!==false && is_array($iiif_manifest)){
                    
                    $context = @$iiif_manifest['@context'];
                    $service_id = $iiif_manifest['@id'];
                    if(@$iiif_manifest['width']>0) {$width = $iiif_manifest['width'];}
                    if(@$iiif_manifest['height']>0) {$height = $iiif_manifest['height'];}
                    
                    $profile = @$iiif_manifest['profile'];
                    
                    $mimeType = null;
                    if(is_array($profile)){
                        $mimeType = @$profile[1]['formats'][0];
                        if($mimeType) {$mimeType = 'image/'.$mimeType;}
                        $profile = @$profile[0];
                    }else if($profile==null){
                        $profile = 'level1';
                    }
                    if(!$mimeType) {$mimeType= 'image/jpeg';}
                    
                    if(strpos($profile, 'library.stanford.edu/iiif/image-api/1.1')>0){
                        $quality = 'native';
                    }else{
                        $quality = 'default';
                    }
                    $resource_url = $iiif_manifest['@id'].'/full/full/0/'.$quality.'.jpg';
                    $resource_id = $iiif_manifest['@id'];
                    
                    if(self::$version==2){
$service = <<<SERVICE2
                "height": $height,
                "width": $width,
                "service" : {
                            "profile" : "$profile",
                            "@context" : "$context",
                            "@id" : "$service_id"
                          }                    
                ],
SERVICE2;
                    }else{
$service = <<<SERVICE3
                "height": $height,
                "width": $width,
                "service": [
                  {
                    "id": "$service_id",
                    "profile": "$profile"
                  }
                ],
SERVICE3;
//                    "type": "ImageService3"
                    }
                }
        }
        
    
        $canvas_uri = self::gen_uuid();//uniqid('',true);

        $tumbnail_height = 200;
        $tumbnail_width = 200;

        if(self::$version==2){ //not used - outdated for mirador v2
                      
$item = <<<CANVAS2
{
        "@id": "http://$canvas_uri",
        "@type": "sc:Canvas",
        "label": "$label",
        "height": $height,
        "width": $width,
        "thumbnail" : {
                "@id" : "$tumbnail_url",
                "height": $tumbnail_height,
                "width": $tumbnail_width
         }, 
        "images": [
            {
                "@type": "oa:Annotation",
                "motivation": "sc:painting",
                "resource": {
                    $service
                    "@id": "$resource_url",
                    "@type": "dctypes:$resource_type",
                    "format": "$mimeType"
                },
                "on": "http://$canvas_uri"
            }
        ]
  }
CANVAS2;
    
//                    "height": $height,
//                    "width": $width
      }else{
    
//$annotation_uri = self::gen_uuid();
//  "duration": 5,
//        "height": $height,
//        "width": $width

// Returns json
if($resource_id){ //this is iiif image
    
    //last section
    $parts = explode('/',$resource_id);
    $cnt = count($parts)-1;
    array_splice( $parts, $cnt, 0, 'canvas');
    $canvas_uri = implode('/',$parts);
    $parts[$cnt] = 'page';
    $annopage_uri = implode('/',$parts);
    $parts[$cnt] = 'annotation';
    $annotation_uri = implode('/',$parts);
    $image_uri = $resource_id.'/info.json';
                    
    
}else{
    $root_uri = HEURIST_BASE_URL_PRO.'api/'.HEURIST_DBNAME.'/iiif/';
    $canvas_uri = $root_uri.'canvas/'.$fileid;
    $annopage_uri = $root_uri.'page/'.$fileid;
    $annotation_uri = $root_uri.'annotation/'.$fileid;
    $image_uri = $root_uri.'image/'.$fileid.'/info.json';
}



$annotation = <<<ANNOTATION3
            {
              "id": "$annotation_uri",
              "type": "Annotation",
              "motivation": "painting",
              "body": {
                $service
                "id": "$resource_url",
                "type": "$resource_type",
                "format": "$mimeType"
              },
              "target": "$canvas_uri"
            }
ANNOTATION3;

if($type_resource=='annotation'){
    return $annotation;
}

$annotation_page = <<<PAGE3
        {
          "id": "$annopage_uri",
          "type": "AnnotationPage",
          "items": [
                $annotation
          ]
        }
PAGE3;

if($type_resource=='page'){
    return $annotation_page;
}

$item = <<<CANVAS3
{
      "id": "$canvas_uri",
      "type": "Canvas",
      "label": "$label",
                "height": $height,
                "width": $width,
      "items": [
           $annotation_page
      ],
      "thumbnail": [
        {
          "id": "$tumbnail_url",
          "type": "Image",
          "format": "image/png",
          "width": $tumbnail_width,
          "height": $tumbnail_height
        }
      ]    

 }
CANVAS3;

/*
                "height": $height,
                "width": $width,
                "duration": 5,
*/
        }
        
        
        $canvas = $canvas.$comma.$item;
        $comma =  ",\n";
        
        }//for info in fileinfo
    
    }//count($file_ids)>0
    
    
    return $canvas;
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

//
// not used 
//
private static function gen_uuid2() {
    return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4) );
}

//
//
//
private static function gen_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        random_int( 0, 0xffff ), random_int( 0, 0xffff ),

        // 16 bits for "time_mid"
        random_int( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        random_int( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        random_int( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff )
    );
}

private static function _processFieldData($dty_ID, &$values){

    $dty_Type = mysql__select_value(self::$mysqli, "SELECT dty_Type FROM defDetailTypes WHERE dty_ID = ?", ['i', $dty_ID]);

    foreach($values as $dtl_ID => $value){

        switch ($dty_Type) {
            case 'file': // get external URL / Heurist URL

                $f_id = $value['file']['ulf_ObfuscatedFileID'];
                $external_url = $value['file']['ulf_ExternalFileReference'];

                $value = empty($external_url) ? HEURIST_BASE_URL_PRO."?db=".HEURIST_DBNAME."&file={$f_id}" : $external_url;
                break;

            case 'enum': // get term label

                if(is_numeric($value)){
                    $value = intval($value);
                    $value = mysql__select_value(self::$mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $value]);
                }

                break;

            case 'resource': // get record title

                if(is_numeric($value)){
                    $value = intval($value);
                    $value = mysql__select_value(self::$mysqli, "SELECT rec_Title FROM Records WHERE rec_ID = ?", ['i', $value]);
                }else if(is_array($value)){
                    $value = $value["title"];
                }

                break;

            default:
                break;
        }

        $values[$dtl_ID] = htmlspecialchars($value);
    }

    $values = implode('|', $values);
}

} //end class
?>