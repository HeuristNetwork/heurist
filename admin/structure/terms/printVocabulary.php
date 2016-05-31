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
            if(!in_array($format, array('txt','html'))){
                $format = 'html';    
            }
            
            $term = @$terms['termsByDomainLookup'][$domain][$termID];
            
            if($term){
                
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
    global $format;
    
    $label = getTermById($termID);
    
    if($format=='html'){
        print '<div style="padding-left:'.($lvl*20).'px">'.$label.'</div>';
    }else{
        print str_repeat(' ',$lvl*5).$label."\n";
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
