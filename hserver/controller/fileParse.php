<?php
    /**
    *  CSV parser for content from client side
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
require_once(dirname(__FILE__)."/../System.php");

$system = new System();
$response = null;
if($system->init(@$_REQUEST['db'])){

   if(!$system->is_admin()){
        $response = $system->addError(HEURIST_REQUEST_DENIED);
   }else{
        $content = @$_REQUEST['content'];
        if(!$content){
            $response = $system->addError(HEURIST_INVALID_REQUEST, "'content' parameter is not defined");            
        }
   }
}else{
    $response = $system->getError();
}

if($response!=null){
    header('Content-type: application/json'); //'text/javascript');
    print json_encode($response);
    exit();
}

//parse
$csv_delimiter = @$_REQUEST['csv_delimiter'];
$csv_enclosure = @$_REQUEST['csv_enclosure'];
$csv_linebreak = @$_REQUEST['csv_linebreak'];

if(!$csv_delimiter) $csv_delimiter = ',';
else if($csv_delimiter=='tab') $csv_delimiter="\t";

$csv_enclosure = ($csv_enclosure==1)?"'":'"';

if(!$csv_linebreak) $csv_linebreak = "\n";

$response = array();

if(intval($csv_linebreak)>0){
    $group_by = $csv_linebreak;
    $response = str_getcsv($content, $csv_delimiter); 
    
        $temp = array();
        $i = 0;
        while($i<count($response)) {
            $temp[] = array_slice($response,$i,$csv_linebreak);
            $i = $i + $csv_linebreak;
        }
        $response = $temp;
    
}else{
    
    if($csv_linebreak=='win') $csv_linebreak = "\r\n";
    else if ($csv_linebreak=='mac') $csv_linebreak = "\r";
    else $csv_linebreak = "\n";
    
    $group_by = 0;
    $content = str_getcsv($content, "\n"); //$csv_linebreak); //parse the rows 

//error_log(print_r($content,true));
    
    foreach($content as &$Row) {
         $row = str_getcsv($Row, $csv_delimiter , $csv_enclosure); //parse the items in rows    
         array_push($response, $row);
    }
}

//error_log(print_r($response,true));

header('Content-type: application/json');
$response = array("status"=>HEURIST_OK, "data"=> $response);
print json_encode($response);
exit();
?>
