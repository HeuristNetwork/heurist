<?php
/**
* ExportRecordsRDF.php - Class ExportRecordsRDF
* 
* Class to export records as RDF with different serialization (XML, tripple, json)
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\records\export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

namespace hserv\records\export;
use hserv\records\export\ExportRecords;

/**
 * Defines the Dublin Core title predicate.
 * @var string DC_TITLE Predicate for title (dc:title).
 */
define('DC_TITLE', 'dc:title');

/**
 * Class ExportRecordsRDF
 *
 * Extends ExportRecords to provide functionality for exporting records in RDF format
 * using the EasyRdf library. It supports various RDF serializations like RDF/XML,
 * N-Triples, Turtle, and JSON-LD (if dependencies are met).
 * This class is typically controlled by the 'records_output' controller.
 * It maps Heurist record structures and semantic URLs to RDF resources and predicates.
 */
class ExportRecordsRDF extends ExportRecords {

    /**
     * @var \EasyRdf\Graph The EasyRdf graph object where RDF triples are accumulated.
     */
    private $graph;

    // Property names are self-descriptive for their use as indexes into Heurist definition arrays.
    /**
     * @var int|null Index for record type name in definition arrays.
     */
    private $idx_rty_name;
    /**
     * @var int|null Index for record type concept ID in definition arrays.
     */
    private $idx_rty_ccode;
    /**
     * @var int|null Index for record type semantic reference URL in definition arrays.
     */
    private $idx_rty_surl;
    /**
     * @var int|null Index for record type originating DB ID in definition arrays.
     */
    private $idx_rty_dbid;

    /**
     * @var int|null Index for record structure (field) display name in definition arrays.
     */
    private $idx_rst_name;
    /**
     * @var int|null Index for record structure (field) semantic reference URL in definition arrays.
     */
    private $idx_rst_surl;
    /**
     * @var int|null Index for record structure (field) originating DB ID in definition arrays.
     */
    private $idx_rst_dbid;

    /**
     * @var int|null Index for detail type concept ID in definition arrays.
     */
    private $idx_dty_ccode;
    /**
     * @var int|null Index for detail type semantic reference URL in definition arrays.
     */
    private $idx_dty_surl;

    /**
     * @var int|null Index for detail type's data type (e.g., 'enum', 'date') in definition arrays.
     */
    private $idx_dtype;

    /**
     * @var string|null The requested RDF serialization format (e.g., 'rdfxml', 'turtle').
     */
    private $serial_format = null;
    /**
     * @var string|null The registered ID of the current Heurist database.
     */
    private $dbid;

    /**
     * @var bool Flag to include rdfs:label with the record type name for the main resource.
     */
    private $include_definition_label = true;
    /**
     * @var bool Flag to include dc:title with the record title for linked resources.
     */
    private $include_resource_rec_title = true;
    /**
     * @var bool Flag to include dc:title with the term label for linked term resources.
     */
    private $include_resource_term_label = true;
    /**
     * @var bool Flag to include dc:title (filename) and dc:description for linked file resources.
     */
    private $include_resource_file_info = true;

    /**
     * Prepares for RDF export.
     *
     * Initializes RDF-specific settings like serialization format, the database's registered ID,
     * defines a base URI (`HEURIST_REF`) if not already set, and parses parameters
     * to control the inclusion of extended information (labels, titles) in the RDF output.
     *
     * @param array $data The data to be exported.
     * @param array $params Parameters for the export. May include 'serial_format' and 'extinfo'.
     * @return bool True if preparation was successful, false otherwise.
     */
protected function _outputPrepare($data, $params)
{
    $res = parent::_outputPrepare($data, $params);
    if($res){
        $this->serial_format = @$params['serial_format'];
        $this->dbid = $this->system->settings->get('sys_dbRegisteredID');

        if(!defined('HEURIST_REF')){
            define('HEURIST_REF','https://heuristref.net/');
        }


        $ext_info = @$params['extinfo'];
        if($ext_info==null) {$ext_info = '0';}
        elseif($ext_info==='1') {$ext_info = '1111';}
        if(strlen($ext_info)<4){
            $ext_info = str_pad($ext_info,4,'0');
        }
        $this->include_definition_label = ($ext_info[0]==1);
        $this->include_resource_term_label = ($ext_info[1]==1);
        $this->include_resource_rec_title = ($ext_info[2]==1);
        $this->include_resource_file_info = ($ext_info[3]==1);

    }

    return $res;
}

//
//
//
    /**
     * Initializes the RDF graph and prepares necessary definitions for export.
     *
     * Creates a new EasyRdf\Graph instance, sets standard namespaces (xsd, base, db, dc).
     * It preloads Heurist record type and detail type definitions and initializes
     * internal index properties (e.g., `$this->idx_rty_name`) for efficient access
     * to these definitions during record processing.
     */
protected function _outputHeader(){
     $this->graph = new \EasyRdf\Graph();

     \EasyRdf\RdfNamespace::set('xsd', 'http://www.w3.org/2001/XMLSchema#');
     \EasyRdf\RdfNamespace::set('base', HEURIST_REF);
     \EasyRdf\RdfNamespace::set('db', HEURIST_REF.'db/');
     \EasyRdf\RdfNamespace::set('dc', 'http://purl.org/dc/elements/1.1/');

    if(self::$defRecTypes==null) {
        self::$defRecTypes = dbs_GetRectypeStructures($this->system, null, 2);
    }
    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);
    }

    $this->idx_rty_name = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_Name'];
    $this->idx_rty_surl = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_ReferenceURL'];
    $this->idx_rty_ccode = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_ConceptID'];
    $this->idx_rty_dbid = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_OriginatingDBID'];

    $this->idx_rst_name = self::$defRecTypes['typedefs']['commonNamesToIndex']['rst_DiaplayName'];
    $this->idx_rst_surl = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_SemanticReferenceURL'];
    $this->idx_rst_dbid = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_OriginatingDBID'];

    $this->idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $this->idx_dty_surl = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_SemanticReferenceURL'];
    $this->idx_dty_ccode = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

}

//
//
//
    /**
     * Initializes term definitions if they haven't been loaded yet.
     *
     * This is a helper method to ensure that `self::$defTerms` is populated
     * with term data (from `dbs_GetTerms`) before it's accessed.
     */
private function initializeTerms(){
    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms($this->system);
        self::$defTerms = new \DbsTerms($this->system, self::$defTerms);
    }
}

//
// 1. returns pair: namespace and type
// 2. fill array of namespaces
// for example
// 1. https://www.ica.org/standards/RiC/ontology#Person  => rico:Person
// 2. rico => https://www.ica.org/standards/RiC/ontology#
//
    /**
     * Converts a semantic URL (SURL) or Heurist internal identifier into a prefixed URI
     * and registers its namespace with EasyRdf.
     *
     * Parses the input SURL. If it matches known URI patterns (e.g., RiC, FOAF, Heurist schema),
     * it assigns a short prefix (e.g., 'rico', 'foaf', 'heurist') and registers the full URI
     * with EasyRdf\RdfNamespace. If the SURL is an internal Heurist identifier (like 'dty-2-1'
     * or includes an originating DB ID), it constructs a URI within the HEURIST_REF namespace.
     *
     * @param string|null $surl The semantic URL or Heurist identifier (e.g., 'http://xmlns.com/foaf/0.1/Person', 'dty-ConceptID', 'rty-OriginatingDBID-ConceptID').
     * @param string|int|null $original_dbid The originating database ID, used to construct URIs for definitions from other Heurist DBs.
     * @return string|null A prefixed URI (e.g., "foaf:Person"), a full URI if no prefix is mapped, or null if the input is empty.
     */
private function _prepareURI($surl, $original_dbid=null){

    $ns = null;
    $heurist_schema = HEURIST_REF.'schema/';

    if($surl){

        //take first only
        if(strpos($surl,';')>0){
            $surl = explode(';',$surl);
            $surl = $surl[0];
        }

        //
        if(strpos($surl,'#')>0){
            list($uri, $type) = explode('#',$surl);
        }elseif(strpos($surl,'http://')===0 || strpos($surl,'https://')===0){
            $parts = explode('/',$surl);
            $type = array_pop($parts);
            if($type=='') {$type = array_pop($parts);}
            $uri = implode('/',$parts).'/';
        }else{
            //dty-2-1
            //familyName
            $type = $surl;
            if(strpos($type,'rty-')===0 || strpos($type,'dty-')===0 || strpos($type,'trm-')===0){
                $uri = $heurist_schema;
            }elseif($original_dbid!=null){

                if(is_string($original_dbid) && strpos($original_dbid,'-')>0){
                    list($dbid, $id) = explode('-', $original_dbid);
                }else{
                    $dbid = $original_dbid;
                }

                if(intval($dbid)>0){
                    $uri = $heurist_schema;
                    $uri .= $original_dbid.'/';
                }

            }
        }

        if($type){

            $ns = @$this->namespaces[$uri];
            if($ns==null){
                //https://www.ica.org/standards/RiC/
                if(strpos($uri, $heurist_schema)===0){

                    $ns = 'heurist';

                    $parts = explode('/',$uri);
                    $dbid = array_pop($parts);
                    if($dbid=='') {$dbid = array_pop($parts);}
                    if(intval($dbid)>0){
                        $ns .= $dbid;
                    }

                }elseif($uri=='https://www.ica.org/standards/RiC/ontology'){

                    $ns = 'rico';
                }elseif($uri=='http://www.w3.org/2000/01/rdf-schema'){
                    $ns = 'rdfs';
                }elseif($uri=='https://www.omg.org/spec/LCC/Languages/ISO639-2-LanguageCodes'){

                    $ns = 'lcc-639-2';

                }elseif($uri=='http://xmlns.com/foaf/0.1/'){

                    $ns = 'foaf';

                }else {


                    /*  http://xmlns.com/foaf/0.1/familyName

                    }elseif($uri=='http://dbpedia.org/resource/Category:'){
                    $ns = 'dbc';
                    }elseif($uri=='http://dbpedia.org/resource/'){
                    $ns = 'dbpedia';

                    }elseif($uri=='http://dbpedia.org/resource/'){
                    $ns = 'dbo';

                    }elseif($uri=='http://dbpedia.org/property/'){
                    $ns = 'dbp';*/
                }

                if($ns!=null){
                    $this->namespaces[$uri] = $ns;
                    if(substr($uri,-1)!='/'){
                        $uri = $uri.'#';
                    }
                    \EasyRdf\RdfNamespace::set($ns, $uri);
                }else{
                    if($uri && substr($uri,-1)=='/'){
                        return $uri.$type;
                    }
                }
            }
        }
    }
    if($ns!=null){
        return $ns.':'.$type;
    }else{
        return null;
    }

}

//
//
//
    /**
     * Processes a single Heurist record and adds its RDF representation to the graph.
     *
     * Determines the RDF type for the record using its semantic URL or concept ID via `_prepareURI`.
     * If a type is determined, it creates an EasyRdf\Resource for the record (using a
     * HEURIST_REF based URI like `HEURIST_REF.'db/record/'.$this->dbid.'-'.$recID`).
     * Then, it calls `_setResourceProps` to populate the resource with properties and relationships.
     *
     * @param array $record The Heurist record array to process.
     * @return bool Always true to continue processing.
     */
protected function _outputRecord($record){

    $recID = intval($record['rec_ID']);
    $rty_ID = intval($record['rec_RecTypeID']);

    $type = $this->_prepareURI(self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_surl],
                               self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_dbid] );

    if($type==null && self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_ccode]){
        $type = $this->_prepareURI('rty-'.self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_ccode]);
    }

    if($type==null){
        $type = 'rdf:Description';
    }

    if($type){



        //https://www.ica.org/standards/RiC/ontology#Person

        //$uri = HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/view/'.$recID;
        $uri = HEURIST_REF.'db/record/'.$this->dbid.'-'.$recID; //new


        $me = $this->graph->resource($uri, $type);

        $this->_setResourceProps($record, $me);

    }

    return true;

}

//
//
//
    /**
     * Finalizes the RDF export by serializing and outputting the graph.
     *
     * If the graph is not empty, it serializes the accumulated RDF triples
     * into the format specified by `$this->serial_format` (defaulting to 'rdfxml'
     * if the requested format is invalid or not set). Supported formats include
     * 'rdfxml', 'json', 'ntriples', 'turtle'.
     * The serialized data is written to the output stream.
     */
protected function _outputFooter(){

    if(!$this->graph->isEmpty()){
/*
        foreach (\EasyRdf\Format::getFormats() as $f) {
            if ($f->getSerialiserClass()) {
    error_log($f.', '.$f->getName().', '.$f->getLabel());
            }
        }
*/
//ntriples
//turtle
//rdfxml
//json, jsonld - Please install "ml/json-ld" dependency to use JSON-LD serialisation
//php
//svg -
//png -

        if($this->serial_format == null || !in_array($this->serial_format,array('rdfxml','json','ntriples','turtle'))){
            $this->serial_format = 'rdfxml';
        }

        $data = $this->graph->serialise( $this->serial_format );
        fwrite($this->fd, $data);
    }

}


//
//
    /**
     * Sets properties for a given RDF resource based on a Heurist record's data.
     *
     * Adds rdfs:label (record type name) and dc:title (record title) to the resource.
     * Iterates through the record's details:
     * - Determines the predicate URI for each field using `_getFieldURI`.
     * - For file details: creates a new RDF resource for the file, adding dc:title (filename)
     *   and dc:description if enabled by `$this->include_resource_file_info`.
     * - For enum/term details: creates/retrieves an RDF resource for the term using its SURL or
     *   concept ID via `_prepareURI`. Adds dc:title (term label) if enabled by
     *   `$this->include_resource_term_label`.
     * - For literal types (date, year, float, integer, boolean, text): creates an
     *   EasyRdf\Literal with the appropriate XSD datatype.
     * - Adds the triple: `$resource $predicateURI $valueOrResource`.
     * Finally, calls `_composeLinks` to add relationships to other resources.
     *
     * @param array $record The Heurist record array.
     * @param \EasyRdf\Resource $resource The EasyRdf resource to add properties to.
     */
private function _setResourceProps($record, &$resource){

    $rec_ID = $record['rec_ID'];
    $rty_ID = $record['rec_RecTypeID'];
    $rec_Title = $record['rec_Title'];

    if($this->include_definition_label){
        // record type label
        $resource->set('rdfs:label', self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_name]);
    }


    // label or name attribute
    //$field_surl = $this->_prepareURI('http://www.w3.org/2000/01/rdf-schema#name');//or label ?
    $resource->set(DC_TITLE, $rec_Title);

    //convert details to attributes
    foreach ($record['details'] as $dty_ID=>$field_details) {

        $field_URI = $this->_getFieldURI($rty_ID, $dty_ID);

        if($field_URI==null) {continue;} //sematic url is not defined

        $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dtype];

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues

            if(is_array($value)){ //geo,file,resource

                if(@$value['file']){
                    //remove some fields
                    $fileinfo = $value['file'];

                    $file_resource_uri = HEURIST_REF.'db/file/'.$this->dbid.'-'.$fileinfo['ulf_ObfuscatedFileID'];

                    $value = $this->graph->resource($file_resource_uri);//create new or find resource
                    if($this->include_resource_file_info){
                        if(@$fileinfo['ulf_OrigFileName']){
                            $skip_file = strpos(@$fileinfo['ulf_OrigFileName'], ULF_REMOTE) === 0 || // skip if not local file
                                         strpos(@$fileinfo['ulf_OrigFileName'], ULF_IIIF) === 0 ||
                                         strpos(@$fileinfo['ulf_OrigFileName'], ULF_TILED_IMAGE) === 0;
                            if(!$skip_file){
                                $value->set(DC_TITLE, $fileinfo['ulf_OrigFileName']);
                            }
                        }
                        if(@$fileinfo['ulf_Description']){
                            $value->set('dc:description', $fileinfo['ulf_Description']);
                        }
                    }

                }else{

                    continue;
                }
                /*
                elseif(@$value['id']){ //resource
                    $val = $value['id'];
                }elseif(@$value['geo']){

                    if($this->find_by_geofields==null || in_array($dty_ID, $this->find_by_geofields)){

                        $wkt = $value['geo']['wkt'];
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
                */
            }
            else{
                $lang = null;
                $dtype = null;

                if($field_type=='enum'){
                    $this->initializeTerms();

                    $trm_ID = $value;
                    $trm_Label = self::$defTerms->getTermLabel($trm_ID);
                    $trm_ConceptCode = self::$defTerms->getTermConceptID($trm_ID);
                    $term_resource_uri = $this->_prepareURI(self::$defTerms->getTermReferenceURL($trm_ID));

                    if($term_resource_uri == null){
                        $term_resource_uri = HEURIST_REF.'db/term/'.$trm_ConceptCode;
                    }
                    if($term_resource_uri!=null){
                        $value = $this->graph->resource($term_resource_uri);//create new or find resource
                        if($this->include_resource_term_label){
                            $value->set(DC_TITLE, $trm_Label);
                        }

                        //works: $value->addLiteral('rdfs:name', $label);

                        //as separate resource to graph root
                        //$value->add($field_surl, $value);//add new resource
                    }else{
                        $value = $trm_Label;
                    }


                }elseif($field_type=='date' || $field_type=='year'){
                    //http://www.w3.org/2001/XMLSchema#date
                    $dtype = 'xsd:dateTime';
                    //$dtype = 'xsd:date';'xsd:gYear'
                }elseif($field_type=='float'){
                    $dtype = 'xsd:decimal';
                }elseif($field_type=='integer'){
                    $dtype = 'xsd:integer';
                }elseif($field_type=='boolean'){
                    $dtype = 'xsd:boolean';
                }elseif($field_type=='freetext' || $field_type=='blocktext'){
                    //detect language
                    // it does not work in EasyRdf
                    /*
                    list($lang, $value) = extractLangPrefix($value);
                    $dtype = 'xsd:string';
                    if($lang!=null) {
                        $lang = 'fr';
                    }
                    */
                }

                if($dtype!=null){
                    $value = new \EasyRdf\Literal($value, $lang, $dtype);
                }

                $val = $value;
            }

            $resource->add($field_URI, $value);

/*
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
                    if($term_code) {$val['termCode'] = $term_code; }
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
*/
        } //for detail multivalues
    } //for all details of record


    //all records related to original set of records are already found in outputPrepare (with recordSearchRelatedIds)
    //now we need to detect only relation for current record
    $links = recordSearchRelated($this->system, $rec_ID, 0, true);

    if($links['status']==HEURIST_OK){
        if(@$links['data']['direct']){
            $this->_composeLinks($resource, $links['data']['direct'], 'direct', $rty_ID, $links['data']['headers']);
        }
        if(@$links['data']['reverse']){
            $this->_composeLinks($resource, $links['data']['reverse'], 'reverse', $rty_ID, $links['data']['headers']);
        }
    }

}

//
//
//
    /**
     * Adds relationship triples (links) from a source RDF resource to target RDF resources.
     *
     * Iterates through a list of relations. For each relation:
     * - Determines the predicate URI using `_getRelationURI` for typed relations (terms)
     *   or `_getFieldURI` for simple links (detail fields).
     * - If the direction is 'reverse', it tries to find an inverse term for the predicate.
     * - Creates/retrieves an RDF resource for the target record.
     * - If `$this->include_resource_rec_title` is true, adds dc:title (target record's title)
     *   to the target resource.
     * - Adds the triple: `$resource $predicateURI $targetResource`.
     *
     * @param \EasyRdf\Resource $resource The source EasyRdf resource.
     * @param array $relations An array of relationship objects (typically from `recordSearchRelated`).
     * @param string $direction 'direct' or 'reverse', indicating the direction of the relationship.
     * @param int $rty_ID The record type ID of the source record (used by `_getFieldURI` if relation is via detail field).
     * @param array $headers An array mapping record IDs to their titles (used for `dc:title` on target resources).
     */
private function _composeLinks(&$resource, $relations, $direction, $rty_ID, $headers){

    /*
                $relation->recID
                $relation->targetID
                $relation->trmID  rl_RelationTypeID
                $relation->dtID  rl_DetailTypeID
                $relation->relationID  rl_RelationID

                if($relation->relationID>0) {

                    $vals = mysql__select_row($mysqli, $query_rel.$relation->relationID);
                    if($vals!=null){
                        $relation->dtl_StartDate = $vals[1];
                        $relation->dtl_EndDate = $vals[2];
                    }
                }
    */
    foreach($relations as $relation){

        //relationship
        if($relation->relationID>0) {

            $this->initializeTerms();

            //link
            $trm_ID = $relation->trmID;

            if($direction=='reverse'){
                  $rev_trm_ID = self::$defTerms->getTermField($term_id, 'trm_InverseTermID');
                  if($rev_trm_ID>0){
                      $trm_ID = $rev_trm_ID;
                  }
            }
            $relation_uri = $this->_getRelationURI($trm_ID);

        }else{
            //link
            $dty_ID = $relation->dtID;
            $relation_uri = $this->_getFieldURI($rty_ID, $dty_ID);
        }

        /*
        if($field_surl==null){
            //$field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#Relation');
            if($direction=='direct'){
                $field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#isSourceOf');
            }else{
                $field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#hasSource');
            }
        }
        */

        if($relation_uri!=null){

            if($direction=='direct'){
                $related_rec_ID = $relation->targetID;
            }else{
                $related_rec_ID = $relation->sourceID;
            }

            //old $uri = HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/view/'.$related_rec_ID;
            $uri = HEURIST_REF.'db/record/'.$this->dbid.'-'.$related_rec_ID; //new

            $rec_resource = $this->graph->resource($uri);
            if($this->include_resource_rec_title && @$headers[$related_rec_ID][0]){
                $rec_resource->set(DC_TITLE, $headers[$related_rec_ID][0]);
            }

            //$resource->add($field_surl, $this->graph->resource($uri));
            $resource->add($relation_uri, $rec_resource);

        }
    }

}

//
//
//
    /**
     * Determines the predicate URI for a Heurist detail type (field).
     *
     * It prioritizes the semantic URL defined in the record type's structure for that field (`rst_SemanticReferenceURL`).
     * If not found, it falls back to the semantic URL defined directly in the detail type (`dty_SemanticReferenceURL`).
     * If still not found, it attempts to construct a URI using the detail type's concept ID
     * (e.g., 'heurist:dty-ConceptID').
     * Uses `_prepareURI` to potentially get a prefixed URI.
     *
     * @param int $rty_ID The record type ID (to look up field settings within the record type structure).
     * @param int $dty_ID The detail type ID.
     * @return string|null The predicate URI for the field, or null if no suitable SURL or concept ID is found.
     */
private function _getFieldURI($rty_ID, $dty_ID){

    $field_URI = $this->_prepareURI(self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$this->idx_rst_surl],
                                    self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$this->idx_rst_dbid]);
    if($field_URI==null){
        $field_URI = $this->_prepareURI(self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_surl],
                                         self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_ccode]);
    }
    if($field_URI==null && self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_ccode]){
        $field_URI = $this->_prepareURI('dty-'.self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_ccode]);
        //$field_surl .= '-'.preg_replace('/\s+/', '', self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_name]);
    }

    return $field_URI;
}

//
//
//
    /**
     * Determines the predicate URI for a Heurist term (used for typed relationships).
     *
     * It prioritizes the semantic reference URL defined for the term (`trm_ReferenceURL`).
     * If not found, it attempts to construct a URI using the term's concept ID
     * (e.g., 'heurist:trm-ConceptID').
     * Uses `_prepareURI` to potentially get a prefixed URI.
     *
     * @param int $trm_ID The term ID.
     * @return string|null The predicate URI for the term, or null if no suitable SURL or concept ID is found.
     */
private function _getRelationURI($trm_ID){

    //$term = self::$defTerms->getTerm($trm_ID);

    $term_URI = $this->_prepareURI(self::$defTerms->getTermReferenceURL($trm_ID),
                                   self::$defTerms->getTermField($trm_ID, 'trm_OriginatingDBID'));

    if($term_URI == null){
        $trm_ConceptCode = self::$defTerms->getTermConceptID($trm_ID);
        $trm_Label = self::$defTerms->getTermLabel($trm_ID);
        if($term_URI==null && $trm_ConceptCode){
            $field_URI = $this->_prepareURI('trm-'.$trm_ConceptCode);
            //$term_URI = 'heurist:trm-'.$trm_ConceptCode.'-'.preg_replace('/\s+/', '', $trm_Label);
        }
    }

    return $term_URI;

}


} //end class
?>