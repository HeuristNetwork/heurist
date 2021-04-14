<?php
    /*
    * Copyright (C) 2005-2020 University of Sydney
    *
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
    * in compliance with the License. You may obtain a copy of the License at
    *
    * http://www.gnu.org/licenses/gpl-3.0.txt
    *
    * Unless required by applicable law or agreed to in writing, software distributed under the License
    * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
    * or implied. See the License for the specific language governing permissions and limitations under
    * the License.
    */

    /**
    * Restore recDetails from sysArchive as CSV output
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2020 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
define('MANAGER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

/*
if( $system->verifyActionPassword($_REQUEST['pwd'], $passwordForServerFunctions) ){
    print $response = $system->getError()['message'];
    exit();
}
*/

$mysqli = $system->get_mysqli();
    
    if(!$mysqli) exit('db not defined');
    
    $filename = $_REQUEST['db'].'_sysArchive.csv';

//1. get date range
    $date_min = @$_REQUEST['dmin'];
    $date_max = @$_REQUEST['dmax'];
    
//2. get data type
    $dty_ids = null;
    $data_type = @$_REQUEST['type'];    
    if($data_type!=null){
        
        $query = 'select dty_ID from defDetailTypes where (dty_Type="'.$data_type.'")';
        $dty_ids = mysql__select_list2($mysqli, $query);
    }
    if(is_array($dty_ids) && count($dty_ids)==0) $dty_ids = null;
    $rty_ID = @$_REQUEST['rty_ID'];    
    
    
//3. select from sysArchive

    $query = 'SELECT arc_DataBeforeChange FROM sysArchive';
    
    if($rty_ID>0){
        $query = $query.', Records WHERE arc_RecID=rec_ID AND rec_RecTypeID='.$rty_ID.' AND ';        
    }else{
        $query = $query.' WHERE ';
    }
    
    $query = $query.' arc_Table="dtl" AND arc_ContentType="raw"';
    
    if($date_min!=null){
        $query = $query.' AND arc_TimeOfChange>"'.$date_min.'" ';    
    }
    if($date_max!=null){
        $query = $query.' AND arc_TimeOfChange<"'.$date_max.'" ';    
    }
    
    $res = $mysqli->query($query);

    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
   
    //open output stream
    $stream = fopen('php://temp/maxmemory:1048576', 'w');
    
    fwrite($stream, '"dtl_ID","dtl_RecID","dtl_DetailTypeID","dtl_Value","dtl_AddedByImport",'
    .'"dtl_UploadedFileID","dtl_Geo","dtl_ValShortened",'
    .'"dtl_Modified","dtl_Certainty","dtl_Annotation"'."\n");
    
    $is_filtered = ($dty_ids!=null);
    
    while ($row = $res->fetch_row()) {
        
        $str = $row[0];
        
        if($is_filtered){
            $vals = str_getcsv($str);
            
            //if($rty_ID>0 && $vals[]!=$rty_ID) continue;
            if($dty_ids!=null && !in_array($vals[2],$dty_ids)) continue;
        }
        
        fwrite ($stream, $str."\n");    
    }
    
    rewind($stream);
    $out = stream_get_contents($stream);
    fclose($stream);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename='.$filename);
    header('Content-Length: ' . strlen($out)+3);
    echo "\xEF\xBB\xBF"; // Byte Order Mark        
    exit($out);
?>
