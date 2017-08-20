<?php

    require_once (dirname(__FILE__).'/../../../hserver/System.php');
    require_once (dirname(__FILE__).'/../../../hserver/dbaccess/db_structure.php');

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
        //header('Location: '.dirname(__FILE__).'/../../../hclient/framecontent/errorPage.php?msg='.$error);
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
                    $parent_term_label = '"'.getFullTermLabel($terms, $parent_term, $domain, false).'"';
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
//@todo - move to db_structure
function getFullTermLabel($dtTerms, $term, $domain, $withVocab, $parents=null){

    $fi = $dtTerms['fieldNamesToIndex'];
    $parent_id = $term[ $fi['trm_ParentTermID'] ];

    $parent_label = '';

    if($parent_id!=null && $parent_id>0){
        $term_parent = @$dtTerms['termsByDomainLookup'][$domain][$parent_id];
        if($term_parent){
            if(!$withVocab){
                $parent_id = $term_parent[ $fi['trm_ParentTermID'] ];
                if(!($parent_id>0)){
                    return $term[ $fi['trm_Label']];
                }
            }
            
            if($parents==null){
                $parents = array();
            }
            
            if(array_search($parent_id, $parents)===false){
                array_push($parents, $parent_id);
                
                $parent_label = getFullTermLabel($dtTerms, $term_parent, $domain, $withVocab, $parents);    
                if($parent_label) $parent_label = $parent_label.'.';
            }
        }    
    }
    return $parent_label.$term[ $fi['trm_Label']];
}

?>
