<?php
    /**
    * printVocabulary.php: print out vocabulary as a csv
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    require_once (dirname(__FILE__).'/../../../hsapi/System.php');
    require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_structure.php');

    $error = null;
    
    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        $error = 'db parameter is not defined';
    }else{

        if(@$_REQUEST['trm_ID']>0 && @$_REQUEST['domain']){
        
            $terms = dbs_GetTerms($system);
    
            $termID = $_REQUEST['trm_ID'];
            $domain = $_REQUEST['domain'];
            $format = @$_REQUEST['format'];
            
            $format = 'csv';
            if(!in_array($format, array('csv','txt','html'))){
                $format = 'html';    
            }
            
            $term = @$terms['termsByDomainLookup'][$domain][$termID];
            
            if($term){
                
                if($format=='csv'){
                ob_start();    
                header('Content-type: text/csv; charset=utf-8');
                header('Pragma: public');
                header('Content-Disposition: attachment; filename="heurist_vocabulary.csv"');
                //header('Content-Length: ' . filesize($file_read));
                @ob_clean();
                flush();  
                print "Term,Internal code,Parent term,Parent internal code,Standard Code,Description\n";
                }
                
                $children = getTermInTree($termID);
                
                printTerm($termID,0);
                printBranch($children, 1);
   
            }else{
                $error = 'Term '.$termID.' not found';        
            }
            
            
        }else{
            $error = 'You have to define trm_ID and domain parameters';    
        }
    }
     
    if($error){
        print $error;
        //header('Location: '.dirname(__FILE__).'/../../../hclient/framecontent/infoPage.php?message='.$error);
    }

function printTerm($termID, $lvl){
    global $format, $terms;
    
    //$label = getTermById($termID);
    
    $domain = 'enum';
    $term = @$terms['termsByDomainLookup']['enum'][$termID];
    if(null==$term){
        $domain = 'relation';
        $term = @$terms['termsByDomainLookup']['relation'][$termID];
    }
    if($term){
        $fi = $terms['fieldNamesToIndex'];
        
        $label = $term[$fi['trm_Label']];
                    
        if($format=='csv'){
            
            //Term, Internal code, Parent term, Parent internal code, Standard Code, Description
            //parent term = Humanities.Arts.Undergrad
            
            $parent_term_label = ''; 
            $parent_id = $term[$fi['trm_ParentTermID']];
            if($lvl>0 && $parent_id>0){
                $parent_term = @$terms['termsByDomainLookup'][$domain][$parent_id];
                if($parent_term){
                    $parent_term_label = '"'.getTermFullLabel($terms, $parent_term, $domain, false).'"';
                }else{
                    $parent_id = '';
                }
            }else{
                $parent_id = '';
            }
            
            $description = ($term[$fi['trm_Description']])?'"'.$term[$fi['trm_Description']].'"':''; 
            
            $line = array('"'.$label.'"', $termID, $parent_term_label, $parent_id, $term[$fi['trm_Code']],
                            $description);  
    
            print implode(',',$line)."\n";
            
        }else if($format=='html'){
            print '<div style="padding-left:'.($lvl*20).'px">'.$label.'</div>';
        }else{
            print str_repeat(' ',$lvl*5).$label."\n";
        }
    }
}
function printBranch($tree, $lvl){
    if(count($tree)>0)
    foreach($tree as $termID => $children){
        printTerm($termID, $lvl);
        printBranch($children, $lvl+1);
    }
}    
?>
