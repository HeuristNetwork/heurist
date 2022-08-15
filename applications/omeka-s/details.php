<?php
/**
* import OMKEA details and rt structure
* 
* configuration is included into this script
*/
ini_set('max_execution_time', 0);

exit();

//enum flag properties
/* 227,228,232,285,357,370
update value set uri=5442 where value='Non' and property_id in (227,228,232,285,357,370)
update value set uri=5443 where value='N/A' and property_id in (227,228,232,285,357,370)
update value set uri=5444 where value='Oui' and property_id in (227,228,232,285,357,370)
*/

//enum properties
//202,224,318,377,223,245,325,278,280,281,342,283,291,329,344,346,290,383

//2,creator,380,,NULL,
//24,modified,6239,,NULL,

//rty,id,local_name,dty_Type,dty_Code,ptr|vocab
$config = <<<'EOD'
,7,date,date,9
,35,isReferencedBy,blocktext
,48,bibliographicCitation,blocktext
,125,gender,enum,20,
,131,nick,freetext       
,138,name,freetext,132
,139,firstName,freetext,18
,141,givenName,freetext
"95,110,111",143,surname,freetext,1
"150",143,surname,resource,16
,187,biography,blocktext,3
,189,place,freetext,231
,202,agent,enum,,6255
,206,principal,resource,16
,207,partner,resource,16
,209,witness,resource,,95
,211,organization,resource,,157
,218,birthplace,freetext
,220,birthparish,freetext
,223,birthcountry,enum,26,6570
,224,birthdepartment,enum,234,6417
,226,comment,blocktext,4
,227,nationality,enum,,5441
,228,naturalized,enum,,5441
,229,ordfratrie,integer
,230,parrain,resource,,95
,231,marraine,resource,,95
,232,contratdemariage,enum,,5441
,234,witnessplace,freetext
,239,deathplace,freetext
,242,deathparish,freetext
,244,deathdepartment,freetext
,245,deathcountry,enum,26,6570
,247,notarialwitness,resource,,95
,248,notarialarchivaldate,date
,250,passif,blocktext
,251,remarques,blocktext
,252,birthdate,date
,253,deathdate,date,9
,262,sourceimp,blocktext
,267,refinitiale,blocktext
,268,refdef19,freetext
,271,refAN,freetext
,272,datedebutprofession,date,10
,273,datefinprofession,date,11
,274,titulature,blocktext
,275,predecessor,resource,,95
,276,successor,resource,,95
,277,numeroenregistrement,freetext,,
,278,creationrenovation,enum,,6603
,279,datedebutbrevet,date,10,
,280,qualitebrevet,enum,,6623
"149",281,specialites,enum,,6635
"150",281,specialites2,blocktext
,282,datefinbrevet,date,11
,283,brevetcausefin,enum,,7129
,284,occupation,blocktext
,285,exercedansetablissement,enum,,5441
,286,etablissement,resource,,"95,150"
,287,datedebutrelation,date,10,
,288,datefinrelation,date,11,
,289,dateobservation,date,
,290,typederelation,enum,,7363
,291,genre,enum,,7184
,292,devise,freetext
,294,contentieux,blocktext
,298,idoeuvre,freetext
,299,P30134,freetext,,
,300,P30128,freetext,,
,301,P30088,freetext,,
,305,P30176,resource,,150
,306,P30011,freetext
,308,award,blocktex
,309,proprietes,blocktext
,311,datedebutmandat,date,10,
,312,datefinmandat,date,11,
,313,uri,freetext
,315,sourcebnf,freetext,,
,316,pere,resource,,95
,317,mere,resource,,95
,318,departementbrevet,enum,234,6417
,319,remarquesdebutbrevet,blocktext,,
,320,remarquesfinbrevet,blocktext,,
,322,adresse,freetext,,
,324,ville,freetext
,325,pays,enum,26,6570
,326,datedebutadresse,date,10,
,327,datefinadresse,date,11,
,329,typeadresse,enum,,7242
,330,arrondissement,freetext,231,
,331,numeroetudenotariale,freetext,,
,336,datedemariagenotarie,date
,341,temoinsnotaries,resource,,95
,342,diplomedetenu,enum,,7100
,343,etablissementsfrequentes,freetext
,344,statut,enum,,7333
,345,observations,blocktext,,
,346,typedeproces,enum,,7354
,347,datedebuttitulature,date
,348,datefintitulature,date
,349,dateobservationtitulature,date
,351,dateobservationmandat,date
,352,statutpolitique,freetext,,
,353,autresraisonssociales,blocktext,,
,354,statutjuridique,freetext,,
,355,capitalsocial,blocktext,,
,356,datecapital,date,,
,357,editor,enum,,5441
,358,patronymemariage,freetext,, 
,359,consulteenvain,blocktext,,
,360,datebapteme,date,,
,361,datedeclaration,date,,
,362,sitography,blocktext,,
,363,datefinformation,date,,
,364,datedebutformation,date,,
,366,ordre,integer,,
,368,remarquesalter,blocktext,,
,369,ordrepassation,integer,,
,370,brevete,enum,,5441
,371,causerefus,blocktext,,
,373,temoinmoralite,resource,,95
,374,temoincapacite,resource,,95
,375,remarquespassationbrevet,blocktext,,
,376,complementadresse,blocktext,,
,377,departement,enum,234,6417
,378,paroisse,freetext
,379,commune,freetext
,382,autresbiens,blocktext,,
,383,roleassociation,enum,,7363
,384,datedebutentreprise,date,,
,385,datefinentreprise,date,,
,386,remarqueactivite,blocktext,,
,388,relationpredecesseur,freetext
,389,relationsuccesseur,freetext
EOD;

/*


Translate gender for 2-20 and Flag 2-530 
*/
define('O_DB','def19'); //OMEKA_DB
define('HEU_DB','hdb_def19_v1');


define('OWNER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once (dirname(__FILE__).'/../../hsapi/import/importParser.php');
//require_once (dirname(__FILE__).'/../../hsapi/entity/dbDefTerms.php');

$mysqli = $system->get_mysqli();

mysql__usedatabase($mysqli, HEU_DB);

$cfg = ImportParser::simpleCsvParser(array('content'=>$config));

$ret = false;
$keep_autocommit = mysql__begin_transaction($mysqli);

$pairs = array();//omeka=>heurist

//adds fields
foreach($cfg as $idx=>$row){
//rty 0, id 1, local_name 2, dty_Type 3,  dty_Code 4,  ptr|vocab 5

if(@$row[4]>0){ //pair already defined
    //$pairs[] = $row;
    //$pairs[$row[1]] = $row[4];
}else{
    //add new field
    $vocab = ($row[3]=='enum')?$row[5]:'NULL';
    $ptr = ($row[3]=='resource')?('"'.$row[5].'"'):'NULL';
    
    //find label and description
    $om_property = mysql__select_row($mysqli, 
        'SELECT label, comment FROM '.O_DB.'.`property` where id='.$row[1]);
    
    if($om_property==null){
        echo 'can find property '.$row[1];
        break;
    }
    if($row[1]==281){
        $om_property[0] = $om_property[0].'(2)';
    }
    
   $query = 'insert into defDetailTypes '
.'(dty_Name, dty_Type, dty_HelpText, dty_EntryMask, dty_DetailTypeGroupID, dty_JsonTermIDTree,dty_PtrTargetRectypeIDs)'
.' values ("'.$om_property[0].'","'.$row[3].'","'.(!$om_property[1]?'help':$om_property[1])
    .'",'.$row[1].',115,'.$vocab.','.$ptr.')'; 
    
    $ret = $mysqli->query($query);
        if(!$ret){
            echo $mysqli->error;
            break; 
        }
    
    $row[4] = $mysqli->insert_id;
    //$pairs[] = $row;
}

if($row[1]==281 || ($row[1]==143 && $row[0]==150)){
    $p_idx = $row[0].'.'.$row[1];
}else{
    $p_idx = $row[1];
}
$pairs[$p_idx] = $row[4];    

print $p_idx.','.$row[4].'<br>';    
    
}//for config

if($ret){

//adds rectype structure

//get properties by classes
$query = 'SELECT distinct r.resource_class_id, p.id '
.'FROM '.O_DB.'.value v, '.O_DB.'.property p, '.O_DB.'.resource r '
.'where v.resource_id = r.id  and v.property_id=p.id '
.'order by  r.resource_class_id, p.id';

$res = $mysqli->query($query);
if ($res){

    $currID = null;
    while ($vals = $res->fetch_row()){

            $rty_ID = $vals[0];
            $property_ID = $vals[1];
            if($currID != $rty_ID){
                $currID = $rty_ID;
                $order = 1;
                echo "add str ".htmlspecialchars($currID)."</br>";
            }else{
                $order = $order+1;
            }
            
            $dty_ID = null;
            //find dty_ID in pairs
            if($property_ID==281 || ($property_ID==143 && $rty_ID==150)){
                $dty_ID = @$pairs[$rty_ID.'.'.$property_ID];
            }else{
                $dty_ID = @$pairs[$property_ID];
            }
            
            if($dty_ID==null){
                continue;
                /*
                echo 'cant find dty_ID by '.$property_ID;
                $ret = false;
                break;
                */
            }
            
            if($property_ID==143 && $rty_ID==150){
                $property_ID=206;
            }
            
            //find label and description
            $om_property = mysql__select_row($mysqli, 
                'SELECT label, comment FROM '.O_DB.'.`property` where id='.$property_ID);
            
    $query = 'insert into defRecStructure '
.'(rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayOrder)'
.' values ('.$rty_ID.','.$dty_ID.',"'.$om_property[0].'","'.(!$om_property[1]?'':$om_property[1]).'",'.$order.')';

            $res2 = $mysqli->query($query);
            if(!$res2){
                $ret = false;
                echo $mysqli->error;
                break; 
            }

    }//while

}else{
    $ret = false;
    echo $mysqli->error;
}
    
}

if($ret===false){
    $mysqli->rollback();
}else{
    $mysqli->commit();    
}

if($keep_autocommit===true) $mysqli->autocommit(TRUE);
/*
7,9
35,937
48,938
125,20
131,939
138,132
139,18
141,940
143,1
150.143,16
187,3
189,231
202,941
206,16
207,16
209,942
211,943
218,944
220,945
223,26
224,234
226,4
227,946
228,947
229,948
230,949
231,950
232,951
234,952
239,953
242,954
244,955
245,26
247,956
248,957
250,958
251,959
252,960
253,9
262,961
267,962
268,963
271,964
272,10
273,11
274,965
275,966
276,967
277,968
278,969
279,10
280,970
149.281,971
150.281,0
282,11
283,973
284,974
285,975
286,976
287,10
288,11
289,977
290,978
291,979
292,980
294,981
298,982
299,983
300,984
301,985
305,986
306,987
308,0
309,988
311,10
312,11
313,989
315,990
316,991
317,992
318,234
319,993
320,994
322,995
324,996
325,26
326,10
327,11
329,997
330,231
331,998
336,999
341,1000
342,1001
343,1002
344,1003
345,1004
346,1005
347,1006
348,1007
349,1008
351,1009
352,1010
353,1011
354,1012
355,1013
356,1014
357,1015
358,1016
359,1017
360,1018
361,1019
362,1020
363,1021
364,1022
366,1023
368,1024
369,1025
370,1026
371,1027
373,1028
374,1029
375,1030
376,1031
377,234
378,1032
379,1033
382,1034
383,1035
384,1036
385,1037
386,1038
388,1039
389,1040
*/
?>
