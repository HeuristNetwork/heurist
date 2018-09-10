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
            $term_id = findTermByConceptCode($ccode, 'enum');
            if($term_id==null){
                $term_id = findTermByConceptCode($ccode, 'relation');
            }
            return $term_id;
        }
        
        $terms = $this->data['termsByDomainLookup'][$domain];
        $idx_ccode = intval($this->data['fieldNamesToIndex']["trm_ConceptID"]);

        foreach ($this->data as $term_id => $def) {
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

        $terms = $this->data;
        
        // Validate termIDs
        if($domain!=null){
            $TL = $terms['termsByDomainLookup'][$domain];

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
    //  Find vocabulary ID
    //
    public function getTopMostTermParent($term_id, $domain, $topmost=null) {

        $terms = $this->data;

        if(is_array($domain)){
            $lvl = $domain;
        }else{
            $lvl = $terms['treesByDomain'][$domain];
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
    
}  
?>
