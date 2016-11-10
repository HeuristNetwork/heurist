<?php
/*
* Copyright (C) 2005-2016 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


/* Save a bibliographic record (create a new one, or update an existing one) */

define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

if (! is_logged_in()) return;


$rt_ID = @$_REQUEST["rtyID"];
$dt_ID = @$_REQUEST["dtyID"];

if($rt_ID>0 && $dt_ID>0){

    mysql_connection_overwrite(DATABASE);
    
    //1. get detail type
    $res = mysql__select_array('defDetailTypes','dty_Type','dty_ID='.$dt_ID);
    if(is_array($res) && count($res)>0){
        $isNumeric = ($res[0]!='freetext');

    //2. get max value for numeric and last value for non numeric    
        if($isNumeric){
            $res = mysql__select_array('recDetails, Records','max(CAST(dtl_Value as SIGNED))',
                    'dtl_RecID=rec_ID and rec_RecTypeID='.$rt_ID.' and dtl_DetailTypeID='.$dt_ID);    
        }else{
            $res = mysql__select_array('recDetails, Records','dtl_Value',
                    'dtl_RecID=rec_ID and rec_RecTypeID='.$rt_ID.' and dtl_DetailTypeID='.$dt_ID.' ORDER BY dtl_ID desc LIMIT 1');    
        }
        
        $value = 1;
        
        if(is_array($res) && count($res)>0){
            
            if($isNumeric){
                $value = 1 + intval($res[0]);    
            }else{
                //find digits at the end of string
                $value = $res[0];
                $matches = array();
                if (preg_match('/(\d+)$/', $value, $matches)){
                    $digits = $matches[1];
                    $value = substr($value,0,-strlen($digits)).(intval($digits)+1);    
                }else{
                    $value = $value.'1';
                }
            }
        }
        
        $res = array('result'=>$value);    
        
    }else{
        $res = array('error'=>'Get incremented value. Detail type '.$dt_ID.' not found');    
    }
}else{
    $res = array('error'=>'Get incremented value. Parameters are wrong or undefined');
}

header('Content-type: text/javascript; charset=utf-8');
print json_format($res);
?>
