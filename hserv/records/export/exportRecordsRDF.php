<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecordsRDF.php - class to export records as RDF with different serialization (XML, tripple, json)
* 
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

require_once 'exportRecords.php';

/**
* 
*  setSession - switch current datbase
*  output - main method
* 
*/
class ExportRecordsRDF extends ExportRecords {
    
    private $graph;

    //indexes in defintions  
    private $idx_rty_surl;
    private $idx_rst_surl;
    private $idx_dty_surl;
    private $idx_dtype;
  
    private $serial_format = null;
    
protected function _outputPrepare($data, $params)
{
    $res = parent::_outputPrepare($data, $params);
    if($res){
        $this->serial_format = @$params['serial_format'];
    }

    return $res;
}
    
//
//
//  
protected function _outputHeader(){
     $this->graph = new \EasyRdf\Graph();
     
     \EasyRdf\RdfNamespace::set('xsd', 'http://www.w3.org/2001/XMLSchema#');
     
    if(self::$defRecTypes==null) {
        self::$defRecTypes = dbs_GetRectypeStructures($this->system, null, 2);
    }    
    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);   
    }
    
    $this->idx_rty_surl = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_ReferenceURL'];
    $this->idx_rst_surl = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_SemanticReferenceURL'];
    
    $this->idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $this->idx_dty_surl = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_SemanticReferenceURL'];
     
}

//
//
//
private function initializeTerms(){
    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms($this->system);
        self::$defTerms = new DbsTerms($this->system, self::$defTerms);
    }
}

//
// 1. returns pair: namespace and type
// 2. fill array of namespaces
// for example
// 1. https://www.ica.org/standards/RiC/ontology#Person  => rico:Person
// 2. rico => https://www.ica.org/standards/RiC/ontology#
//
private function _prepareURI($surl){
    
    $ns = null;
    
    if($surl){
        
        //take first only
        if(strpos($surl,';')>0){
            $surl = explode(';',$surl);
            $surl = $surl[0];
        }
    
        //
        if(strpos($surl,'#')>0){
            list($uri, $type) = explode('#',$surl);
        }else{
            $parts = explode('/',$surl);
            $type = array_pop($parts);
            $uri = implode('/',$parts).'/';
        }
        
        if($type){
        
            $ns = @$this->namespaces[$uri];
            if($ns==null){
                //https://www.ica.org/standards/RiC/
                if($uri=='https://www.ica.org/standards/RiC/ontology'){
                    $ns = 'rico';
                }else if($uri=='http://www.w3.org/2000/01/rdf-schema'){
                    $ns = 'rdfs';
                }else if($uri=='https://www.omg.org/spec/LCC/Languages/ISO639-2-LanguageCodes'){
                    
                     $ns = 'lcc-639-2';
                     
                }else if($uri=='http://xmlns.com/foaf/0.1/'){
                    
                     $ns = 'foaf';
                    
                /*  http://xmlns.com/foaf/0.1/familyName
                
                }else if($uri=='http://dbpedia.org/resource/Category:'){
                    $ns = 'dbc';
                }else if($uri=='http://dbpedia.org/resource/'){
                    $ns = 'dbpedia';

                }else if($uri=='http://dbpedia.org/resource/'){
                    $ns = 'dbo';

                }else if($uri=='http://dbpedia.org/property/'){
                    $ns = 'dbp';*/
                }
                    
                if($ns!=null){
                    $this->namespaces[$uri] = $ns;
                    if(substr($uri,-1)=='/'){
                        $uri = $uri.'#';
                    }
                    \EasyRdf\RdfNamespace::set($ns, $uri);
                }else{
                    if(substr($uri,-1)=='/'){
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
protected function _outputRecord($record){

    $recID = intval($record['rec_ID']);
    $rty_ID = intval($record['rec_RecTypeID']);
    
    $type = $this->_prepareURI(self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_surl]);

    if($type){
        
        
        //https://www.ica.org/standards/RiC/ontology#Person
        
        $uri = HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/view/'.$recID;
        //$type = 'foaf:Person';
        
        $me = $this->graph->resource($uri, $type); 
        
        $this->_setResourceProps($record, $me);

    }
    
    return true;
    
}

//
//
//
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
// convert heurist record to more interpretable format
// 
// $extended_mode = 0 - as is, 1 - details in format {dty_ID: val: }, 2 - with concept codes and names/labels
//
private function _setResourceProps($record, &$resource){

    $rec_ID = $record['rec_ID'];
    $rty_ID = $record['rec_RecTypeID'];
    $rec_Title = $record['rec_Title'];

    // label or name attribute    
    $field_surl = $this->_prepareURI('http://www.w3.org/2000/01/rdf-schema#name'); //or label ?
    $resource->set($field_surl, $rec_Title);

    //convert details to attributes
    foreach ($record['details'] as $dty_ID=>$field_details) {
        
        
        $field_surl = $this->_prepareURI(self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$this->idx_rst_surl]);    
        if($field_surl==null){
            $field_surl = $this->_prepareURI(self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_surl]);
        }
        
        if($field_surl==null) continue; //sematic url is not defined
        
        $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dtype];

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues

            if(is_array($value)){ //geo,file,resource
                /*
                if(@$value['file']){
                    //remove some fields
                    $val = $value['file'];
                    unset($val['ulf_ID']);
                    unset($val['fullPath']);
                    unset($val['ulf_Parameters']);

                }else if(@$value['id']){ //resource
                    $val = $value['id'];
                }else if(@$value['geo']){
                    
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
                if(@$value['id']){ //resource
                
                    $val = $value['id'];
                    $uri = HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/view/'.$val;
                    //$resource->add($field_surl, $this->graph->resource($uri));
                }
                
                continue;
            }
            else{
                $lang = null;
                $dtype = null;

                if($field_type=='enum'){
                    $this->initializeTerms();
                    
                    $label = self::$defTerms->getTermLabel($value);
                    $term_iri = $this->_prepareURI(self::$defTerms->getTermReferenceURL($value));
                    if($term_iri == null){
                        //$term_iri = HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/terms/'.$value;
                    }
                    if($term_iri!=null){
                        $value = $this->graph->resource($term_iri);
                        $value->add($field_surl, $value);
                    }else{
                        $value = $label;
                    }
                    
                    //$value->add($this->_prepareURI('http://www.w3.org/2000/01/rdf-schema#label'), $label);
                    
                    
                }else
                if($field_type=='date' || $field_type=='year'){
                    //http://www.w3.org/2001/XMLSchema#date
                    $dtype = 'xsd:dateTime';
                    //$dtype = 'xsd:date'; 'xsd:gYear'
                }else 
                if($field_type=='float'){
                    $dtype = 'xsd:decimal';
                }else 
                if($field_type=='integer'){
                    $dtype = 'xsd:integer';
                }else 
                if($field_type=='boolean'){
                    $dtype = 'xsd:boolean';
                }else 
                if($field_type=='freetext' || $field_type=='blocktext'){
                    //detect language
                    // it does not work in EasyRdf
                    /*
                    list($lang, $value) = extractLangPrefix($value);
                    $dtype = 'xsd:string';
                    if($lang!=null) {
                        $lang = 'fr';//strtolower($lang);   
                    }
                    */
                }
                
                if($dtype!=null){
                    $value = new \EasyRdf\Literal($value, $lang, $dtype);  
                }
                
                $val = $value;
            }
            
            $resource->add($field_surl, $value);
  
/*            
            if(!isset($val)) $val = '';

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
*/            
        } //for detail multivalues
    } //for all details of record

    if(true){
        //all records related to original set of records are already found in outputPrepare (with recordSearchRelatedIds)
        //now we need to detect only relation for current record 
        $links = recordSearchRelated($this->system, $rec_ID, 0, true);        
        
        if($links['status']==HEURIST_OK){
            if(@$links['data']['direct']){
                $this->_composeLinks($resource, $links['data']['direct'], 'direct', $rty_ID);
            }
            if(@$links['data']['reverse']){
                $this->_composeLinks($resource, $links['data']['reverse'], 'reverse', $rty_ID);
            }
        }
    }

}

//
//
//
private function _composeLinks(&$resource, $relations, $direction, $rty_ID){
    
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
            $field_surl = $this->_prepareURI(self::$defTerms->getTermReferenceURL($trm_ID));
            
        }else{
            //link
            $dty_ID = $relation->dtID;
            
            $field_surl = $this->_prepareURI(self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$this->idx_rst_surl]);    
            if($field_surl==null){
                $field_surl = $this->_prepareURI(self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_surl]);
            }

        }
        
        if($field_surl==null){
            //$field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#Relation');
            if($direction=='direct'){
                $field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#isSourceOf');    
            }else{
                $field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#hasSource');
            }
        }
        
        if($direction=='direct'){
            $related_rec_ID = $relation->targetID;
        }else{
            $related_rec_ID = $relation->sourceID;
        }
        
        $uri = HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/view/'.$related_rec_ID;
        //$resource->add($field_surl, $this->graph->resource($uri));
        $resource->add($field_surl, $this->graph->resource($uri));
        
    }
    
    
    
}


} //end class
?>