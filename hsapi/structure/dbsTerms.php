<?php
   /**
    * Manipulation with defTerms 
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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
class DbsTerms
{
    protected $system;  
    
    /*  
      loaded terms
    */    
    protected $data;  
    
    //
    // constructor - load configuration from json file
    //    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }
    //
    // config getter
    //
    public function init(){
        
    }

    //
    // assign terms
    //
    public function setTerms($data){
        $this->data = $data; 
    }
    
    
    //
    //
    //
    public function findTermByConceptCode($ccode, $domain=null){

        if($domain==null){
            $term_id = $this->findTermByConceptCode($ccode, 'enum');
            if($term_id==null){
                $term_id = $this->findTermByConceptCode($ccode, 'relation');
            }
            return $term_id;
        }
        
        $idx_ccode = intval($this->data['fieldNamesToIndex']["trm_ConceptID"]);

        foreach ($this->data['termsByDomainLookup'][$domain] as $term_id => $def) {
            if(is_numeric($term_id) && $def[$idx_ccode]==$ccode){
                return $term_id;
            }
        }
        return null;
    }
   
    //
    // parse term string (as json or comma separated) and validate it 
    // if domain not defined - no validation
    //
    public function getTermsFromFormat($formattedStringOfTermIDs, $domain) {


        $validTermIDs = array();
        if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
            return $validTermIDs;
        }

        if (strpos($formattedStringOfTermIDs,"{")!== false) {
            $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
            if (strrpos($temp,":") == strlen($temp)-1) {
                $temp = substr($temp,0, strlen($temp)-1);
            }
            $termIDs = explode(":",$temp);
        } else {
            $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
            $termIDs = explode(",",$temp);
        }

        // Validate termIDs
        if($domain!=null){
            $TL = $this->data['termsByDomainLookup'][$domain];

            foreach ($termIDs as $trmID) {
                // check that the term valid
                if ( $trmID && array_key_exists($trmID,$TL) && !in_array($trmID, $validTermIDs)){ // valid trm ID
                    array_push($validTermIDs,$trmID);
                }
            }
        }else{
            $validTermIDs = $termIDs; //no validation - take all
        }
        return $validTermIDs;
    }    

    //
    //
    //
    public function getTermLabel($term_id, $with_hierarchy=false) {
    
        $term = $this->getTerm($term_id);
        if($term){
            
            $idx_term_label = $this->data['fieldNamesToIndex']['trm_Label'];
            
            if($with_hierarchy){
                $labels = '';
                $idx_term_parent = $this->data['fieldNamesToIndex']['trm_ParentTermID'];
                $idx_term_doamin = $this->data['fieldNamesToIndex']['trm_Domain'];
                
                
                $labels = array();
                array_push($labels, $term[$idx_term_label]);
                
                while ( $term[$idx_term_parent]>0 ) {
                    $term = $this->getTerm($term[$idx_term_parent]);
                    //if(!$term) break;
                    if($term[$idx_term_parent]>0){
                        array_unshift($labels, $term[$idx_term_label]);
                    }else{
                        break; //ignore vocabulary
                    }
                }
                return implode('.',$labels);
            }else{
                return @$term[$idx_term_label]?$term[$idx_term_label]:'';    
            }
        }else{
            return '';
        }
        
    }

    //
    //
    //
    public function getTermCode($term_id) {
        
        $term = $this->getTerm($term_id);
        if($term){
            $idx_term_code = $this->data['fieldNamesToIndex']['trm_Code'];
            return @$term[$idx_term_code]?$term[$idx_term_code]:'';
        }else{
            return '';
        }
    }
    
    //
    //
    public function getTerm($term_id, $domain='enum') {
        $term = null;
        
        if(@$this->data['termsByDomainLookup'][$domain][$term_id]!=null){
            $term = $this->data['termsByDomainLookup'][$domain][$term_id];
        }else{
            $term = $this->data['termsByDomainLookup'][$domain=='enum'?'relation':'enum'][$term_id];            
        }
        return $term;
    }
    
    //
    //  Find vocabulary ID
    //
    public function getTopMostTermParent($term_id, $domain, $topmost=null) {

        if(is_array($domain)){
            $lvl = $domain;
        }else{
            $lvl = $this->data['treesByDomain'][$domain];
        }
        foreach($lvl as $sub_term_id=>$childs){

            if($sub_term_id == $term_id){
                return $topmost?$topmost:$term_id;
            }else if( count($childs)>0 ) {

                $res = $this->getTopMostTermParent($term_id, $childs, $topmost?$topmost:$sub_term_id );
                if($res) return $res;
            }
        }
        return null; //not found
    }    
    
    
    
    // Disambiguate elements (including terms at the same level of a vocabulary) which have the same label but
    // different concept IDs, by adding 1, 2, 3 etc. to the end of the label.
    //
    // $lvl_src - level to search
    // $idx - field index to check
    // return new term name with index
    //
    public function doDisambiguateTerms($term_import, $lvl_src, $domain, $idx){

        if(!$term_import || $term_import=="") return $term_import;

        if(is_array($lvl_src)){

            $found = 0;
            $name = removeLastNum($term_import);

            foreach($lvl_src as $trmId=>$childs){
                $name1 = removeLastNum($this->data['termsByDomainLookup'][$domain][$trmId][$idx]);
                if($name == $name1){
                    $found++;
                }
            }
            if($found>0){
                $term_import = $name." ".($found+1);
            }

        }

        return $term_import;
    }
    
    
}  
?>
