<?php

$q = @$_REQUEST['q'];

$res = _parseQuery($q, 0);

$res = json_encode($res);


function _parseQuery($q, $lvl){    

    $res = array();
    $subres = array(); //parsed subqueries
    
    if($q!=null && $q!=''){
    //   t   :  554 f68: "petero  metero"     

    //1) get subqueries
        $cnt = preg_match_all('/\(([^[\)]|(?R))*\)/', $q, $subqueries);

        
        if($cnt>0){
            
            $subqueries = $subqueries[0];
            foreach($subqueries as $subq){

                 $subq = preg_replace_callback('/^\(|\)$/', function($m) {return '';}, $subq);
                 
                 $r = _parseQuery($subq, $lvl+1);
                 $subres[] = ($r)?$r:array();
            }
            
            //for square brackets      '/\[([^[\]]|(?R))*\]/'
            
            $q = preg_replace_callback('/\(([^[\)]|(?R))*\)/', 
                function($m) {
                    return ' [subquery] ';
                }, $q);            
            
            
        }
    }else{
        return false;
    }    
    
//2) split by words ignoring quotes    
    $cnt = preg_match_all('/[^\s":]+|"([^"]*)"|:{1}/',  $q, $matches);
    if($cnt>0){
        
        $matches = $matches[0];
        
        $idx = 0;

        $keyword = null;
        
//3 detect predicates  - they are in pairs: keyword:value
        foreach ($matches as $word) 
        {            
            if($word==':'){
              continue;  
            } 
            
            $word = trim($word,'"');


            if($keyword==null)
            {
                $dty_id = null;
                
                if(preg_match('/^(typename|typeid|type|t)$/', $word, $match)>0){ //rectype

                    $keyword = 't';

                }else if(preg_match('/^(ids|id|title|added|modified|url|notes|addedby|access|before)$/', $word, $match)>0){ //header fields

                    $keyword = $match[0];
                    
                }else if(preg_match('/^(workgroup|wg|owner)$/', $word, $match)>0){ //ownership (header field)
                
                    $keyword = 'owner';

                }else if(preg_match('/^(after|since)$/', $word, $match)>0){ //special case for modified
                    
                    $keyword = 'after';

                }else if(preg_match('/^(sortby|sort|s)$/', $word, $match)>0){ //sort keyword
                    
                    $keyword = 'sortby';
                    
                }else if(preg_match('/^(field|f)(\d*)$/', $word, $match)>0){ //base field

                    $keyword = 'f';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(count|cnt|geo)(\d*)$/', $word, $match)>0){ //special base field

                    $keyword = $match[1];
                    $dty_id = @$match[2];

                }else if(preg_match('/^(tag|keyword|kwd)$/', $word, $match)>0){ //tags

                    $keyword = 'tag';

                }else if(preg_match('/^(linked_to|linkedto|linkto|link_to|lt)(\d*)$/', $word, $match)>0){ //resource - linked to

                    $keyword = 'lt';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(linked_from|linkedfrom|linkfrom|link_from|lf)(\d*)$/', $word, $match)>0){ //backward link

                    $keyword = 'lf';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(related_to|relatedto|rt)(\d*)$/', $word, $match)>0){ //relationship to

                    $keyword = 'rt';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(related_from|relatedfrom|rf)(\d*)$/', $word, $match)>0){ //relationship from

                    $keyword = 'rf';
                    $dty_id = @$match[2];

                }else if(preg_match('/^(related|links)$/', $word, $match)>0){ //link or relation in any direction

                    $keyword = $match[0];
                    
                }else if(preg_match('/^(relf|r)(\d*)$/', $word, $match)>0){ //field from relation record 

                    $keyword = $match[1];
                    $dty_id = @$match[2];
                    
                }else if(preg_match('/^(any|all|not)$/', $word, $match)>0){ //logical operation

                    $keyword = $match[0];
                
                }else{
                    $warn = 'Keywords '.$word.' not recognized';
                    //by default this is title (or add to previous value or skip pair?)
                    if($word=='[subquery]'){ 
                        $res = array_merge($res, array_shift($subres) );
                    }else{
                        
                        //if($previous_key && )
                        
                        if(false && @$res[count($res)-1]['title']){
                            $res[count($res)-1]['title'] .= (' '.$word);
                        }else{
                            array_push($res, array( 'title'=>$word ));        
                        }
                    }
                    continue;
                }

                if($keyword){
                    if($dty_id>0){
                        $keyword = $keyword.':'.$dty_id;
                    }
                }
                
            }//keyword turn
            else{
                
                if($word=='[subquery]'){ 
                    $word = array_shift($subres);
                }
                
                array_push($res, array( $keyword=>$word ));    
                
                $previous_key = $keyword;
                
                $keyword = null;
            }
        }//for
        
    
        //$res = json_encode($res);
        
        //$res = implode('.',$matches[0]);
    }else{
        $res =  false; //no entries
    }
    
    return $res;
} //END _parseQuery
?>
<html>
<head>
<style>
body {
  background-color: #AAA;
}
</style>
<link rel="stylesheet" type="text/css" href="hclient/assets/css/marching_ants.css" />

</head>
<body>
<div class="headline marching-ants marching">One element marching ants border in pure CSS</div>
<form>
<input name="q" size="100" type="text" value="<?php echo $q;?>"/>
<button type="submit">DO</button>
<br><br>
<?php echo $res;?>
</form>
</body>
</html>
