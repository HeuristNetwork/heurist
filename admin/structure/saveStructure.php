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
* saveStructure.php. This file accepts request to update the system structural definitions -
* rectypes, detailtypes, terms and constraints. It returns the entire structure for the affected area
* in order to update client side definitions
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

require_once(dirname(__FILE__).'/../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_structure.php');
require_once('saveStructureLib.php');

$system = new System();

$rv = array();

$legalMethods = array(
    "saveRectype",
    "saveRT",
    "saveRTS",
    "deleteRTS",
    "saveRTC",
    "deleteRTC",
    "saveRTG",
    "saveDetailType",
    "saveDT",
    'checkDtPtr',
    "saveDTG",
    "checkTerm",
    "saveTerms",
    "mergeTerms",
    "deleteTerms",
    'checkPtr',
    "deleteDT",
    "deleteRT",
    "deleteRTG",
    "deleteDTG",
    "checkDTusage"
);

$method = @$_REQUEST['method'];
if ($method && !in_array($_REQUEST['method'],$legalMethods)) {
    $method = null;
}

if(!$method){
    $system->error_exit('Invalid call to saveStructure, there is no valid "method" parameter');
}
else
{

        if( !$system->init(@$_REQUEST['db']) ){
            $system->error_exit();
        }

        if(!$system->is_admin()){
            $system->addError(HEURIST_REQUEST_DENIED, 
                'To perform this action you must be logged in as Administrator of group \'Database Managers\'');
            $system->error_exit();
        }
        
        define('HEURIST_DBID', $system->get_system('sys_dbRegisteredID'));
        $system->defineConstant('DT_NAME');
        $system->defineConstant('DT_SHORT_SUMMARY');
    
    
        $mysqli = $system->get_mysqli();
    
    
        purifyHTML($_REQUEST);
        //stripScriptTagInRequest($_REQUEST);
    
        $data = @$_REQUEST['data'];
        //decode and unpack data
        if(is_string($data)){
            $data = json_decode(urldecode(@$_REQUEST['data']), true);
        }
        
        switch ($method) {


            //{ rectype:
            //            {colNames:{ common:[rty_name,rty_OrderInGroup,.......],
            //                        dtFields:[rst_DisplayName, ....]},
            //            defs : {-1:[[common:['newRecType name',56,34],dtFields:{dty_ID:[overide name,76,43], 160:[overide name2,136,22]}],
            //                        [common:[...],dtFields:{nnn:[....],...,mmm:[....]}]],
            //                    23:{common:[....], dtFields:{nnn:[....],...,mmm:[....]}}}}}

            case 'saveRectype':

            case 'saveRT': // Record type
                if (!array_key_exists('rectype',$data) ||
                    !array_key_exists('colNames',$data['rectype']) ||
                    !array_key_exists('defs',$data['rectype'])) {
                    $system->error_exit("Invalid data structure sent with saveRectype method call to saveStructure.php");
                }
                $commonNames = $data['rectype']['colNames']['common'];
                //$dtFieldNames = $rtData['rectype']['colNames']['dtFields'];

                $rv['result'] = array(); //result


                foreach ($data['rectype']['defs'] as $rtyID => $rt) {
                    if ($rtyID == -1) {    // new rectypes
                        $definit = @$_REQUEST['definit'];  //create set of default fields for new rectype

                        $ret = createRectypes($commonNames, $rt, ($definit=="1"), true, @$_REQUEST['icon'], @$_REQUEST['newfields']);
                        array_push($rv['result'], $ret);

                    }else{
                        array_push($rv['result'], updateRectype($commonNames, $rtyID, $rt));
                    }
                }

                $rv['rectypes'] = dbs_GetRectypeStructures($system, null, 2);

                break;

            case 'saveRTS': // Record type structure

                if (!array_key_exists('rectype',$data) ||
                    !array_key_exists('colNames',$data['rectype']) ||
                    !array_key_exists('defs',$data['rectype']))
                {
                    $system->error_exit("Invalid data structure sent with updateRecStructure method call to saveStructure.php");
                }

                //$commonNames = $rtData['rectype']['colNames']['common'];
                $dtFieldNames = $data['rectype']['colNames']['dtFields'];

                $rv['result'] = array(); //result

                //actually client sends the definition only for one record type
                foreach ($data['rectype']['defs'] as $rtyID => $rt) {
                    $res = updateRecStructure($dtFieldNames, $rtyID, $rt);
                    if( $res!==false ){
                        array_push($rv['result'], $res);
                    }else{
                        $rv = false;
                        break;
                    }
                }
                if($rv!==false){
                    $rv['rectypes'] = dbs_GetRectypeStructures($system, null, 2);
                    $rv['detailtypes'] = dbs_GetDetailTypes($system);
                    $rv['terms'] = dbs_GetTerms($system);
                }
                break;

            case 'deleteRTS':
 
                $rtyID = @$_REQUEST['rtyID'];
                $dtyID = @$_REQUEST['dtyID'];

                if (!$rtyID || !$dtyID) {
                    $system->error_exit( "Error: No IDs or invalid IDs sent with deleteRecStructure method call to saveStructure.php" );
                }else{
                    $rv = deleteRecStructure($rtyID, $dtyID);
                    if ($rv!==false) {
                        $rv['rectypes'] = dbs_GetRectypeStructures($system, null, 2);
                        $rv['detailtypes'] = dbs_GetDetailTypes($system);
                    }
                }
                break;

            case 'saveRTC': //Constraints

                $srcID = @$_REQUEST['srcID'];
                $trgID = @$_REQUEST['trgID'];
                $terms_todel = @$_REQUEST['del'];

                if (!$srcID && !$trgID) {

                    $system->error_exit('Error: No record type IDs or invalid IDs sent with deleteRelConstraint method call to saveStructure.php');

                }else{
                    //$colNames = $data['colNames'];  //['defs']
                    $rv['result'] = array(); //result

                    for ($ind=0; $ind<count($data); $ind++) {
                        array_push($rv['result'], updateRelConstraint($srcID, $trgID, $data[$ind]  ));
                    }
                    if($terms_todel){
                        array_push($rv['result'], deleteRelConstraint($srcID, $trgID, $terms_todel));
                    }

                    $rv['constraints'] = dbs_GetRectypeConstraint($system);//dbs_GetRectypeStructures($system, null, 2);

                }
                break;


            case 'deleteRTC': //Constraints

                $srcID = @$_REQUEST['srcID'];
                $trgID = @$_REQUEST['trgID'];
                $trmID = @$_REQUEST['trmID'];

                if (!$srcID && !$trgID) {
                    $system->error_exit('Error: No record type IDs or invalid IDs sent with deleteRelConstraint method call to saveStructure.php');
                }else{
                    $rv = deleteRelConstraint($srcID, $trgID, $trmID);
                    if (!array_key_exists('error', $rv)) {
                        $rv['constraints'] = dbs_GetRectypeConstraint($system);//dbs_GetRectypeStructures($system, null, 2);
                    }
                }
                break;

            case 'deleteRectype':
            case 'deleteRT':

                $rtyID = @$_REQUEST['rtyID'];

                if (!($rtyID>0)) {
                    $system->error_exit('Error: No IDs or invalid IDs sent with deleteRectype method call to saveStructure.php');
                }else{
                    $rv = deleteRecType($rtyID);
                    if ($rv!==false) {
                        $rv['rectypes'] = dbs_GetRectypeStructures($system, null, 2);
                    }
                }
                break;

                //------------------------------------------------------------

            case 'saveRTG':    // Record type group

                if (!array_key_exists('rectypegroups',$data) ||
                    !array_key_exists('colNames',$data['rectypegroups']) ||
                    !array_key_exists('defs',$data['rectypegroups'])) {
                    $system->error_exit("Invalid data structure sent with saveRectypeGroup method call to saveStructure.php");
                }
                $colNames = $data['rectypegroups']['colNames'];
                $rv['groups'] = array();
                foreach ($data['rectypegroups']['defs'] as $rtgID => $rt) {
                    if ($rtgID == -1) {    // new rectype group
                        $resp = createRectypeGroups($colNames, $rt);
                    }else{
                        $resp = updateRectypeGroup($colNames, $rtgID, $rt);
                    }
                    if($resp!==false){
                        array_push($rv['groups'], $resp);    
                    }else{
                        $rv = false;
                        break;
                    }
                }
                if ($rv!==false) {
                    $rv['rectypes'] = dbs_GetRectypeStructures($system, null, 2);
                }
                break;

            case 'deleteRTG':

                $rtgID = @$_REQUEST['rtgID'];
                if (!$rtgID) {
                    $system->error_exit("Invalid or no record type group ID sent with deleteRectypeGroup method call to saveStructure.php");
                }
                $rv = deleteRectypeGroup($rtgID);
                if ($rv!==false) {
                    $rv['rectypes'] = dbs_GetRectypeStructures($system, null, 2);
                }
                break;

            case 'saveDTG':    // Field (detail) type group

                if (!array_key_exists('dettypegroups',$data) ||
                    !array_key_exists('colNames',$data['dettypegroups']) ||
                    !array_key_exists('defs',$data['dettypegroups'])) {
                    $system->error_exit("Invalid data structure sent with saveDetailTypeGroup method call to saveStructure.php");
                }
                $colNames = $data['dettypegroups']['colNames'];
                $rv['groups'] = array();
                
                foreach ($data['dettypegroups']['defs'] as $dtgID => $rt) {
                    if ($dtgID == -1) {    // new dettype group
                        $resp = createDettypeGroups($colNames, $rt);
                    }else{
                        $resp = updateDettypeGroup($colNames, $dtgID, $rt);
                    }
                    if($resp!==false){
                        array_push($rv['groups'], $resp);    
                    }else{
                        $rv = false;
                        break;
                    }
                }
                if ($rv!==false) {
                    $rv['detailtypes'] = dbs_GetDetailTypes($system);
                }
                break;

            case 'deleteDTG':

                $dtgID = @$_REQUEST['dtgID'];
                if (!$dtgID) {
                    $system->error_exit("Invalid or no detail type group ID sent with deleteDetailType method call to saveStructure.php");
                }
                $rv = deleteDettypeGroup($dtgID);
                if ($rv!==false) {
                    $rv['detailtypes'] = dbs_GetDetailTypes($system);
                }
                break;

                //------------------------------------------------------------

            case 'saveDetailType': // Field (detail) types
            case 'saveDT':
                /*
                 detailtype:{
                        colNames:common:[],
                        defs:[dtyID:{},.... ]
                */
                if (!array_key_exists('detailtype',$data) ||
                    !array_key_exists('colNames',$data['detailtype']) ||
                    !array_key_exists('defs',$data['detailtype'])) {
                    $system->error_exit("Invalid data structure sent with saveDetailType method call to saveStructure.php");
                }
                $commonNames = $data['detailtype']['colNames']['common'];
                $rv['result'] = array(); //result

                foreach ($data['detailtype']['defs'] as $dtyID => $dt) {
                    if ($dtyID == -1) {    // new detailtypes
                        array_push($rv['result'], createDetailTypes($commonNames,$dt));
                    }else{
                        array_push($rv['result'], updateDetailType($commonNames, $dtyID, $dt)); //array($dtyID =>
                    }
                }

                $rv['rectypes'] = dbs_GetRectypeStructures($system, null, 2);
                $rv['detailtypes'] = dbs_GetDetailTypes($system);
                break;

            case 'checkDTusage': //used in editRecStructure to prevent detail type delete


                $rtyID = @$_REQUEST['rtyID'];
                $dtyID = @$_REQUEST['dtyID'];

                $rv = findTitleMaskEntries($rtyID, $dtyID);

                break;

            case 'deleteDetailType':
            case 'deleteDT':
                $dtyID = @$_REQUEST['dtyID'];

                if (!$dtyID) {
                    $system->error_exit('Error: No IDs or invalid IDs sent with deleteDetailType method call to saveStructure.php');
                }else{
                    $rv = deleteDetailType($dtyID);
                    if ($rv!==false) {
                        $rv['detailtypes'] = dbs_GetDetailTypes($system);
                    }
                }
                break;

            case 'checkDtPtr':  //verify usage of recordtype in pointer field

                $rty_ID = @$_REQUEST['rty_ID'];
                $dty_ID = @$_REQUEST['dty_ID'];
                $rv = checkDtPtr($rty_ID, $dty_ID);

                break;
                
                
                //------------------------------------------------------------

            case 'saveTerms': // Terms

                if (!array_key_exists('terms',$data) ||
                    !array_key_exists('colNames',$data['terms']) ||
                    !array_key_exists('defs',$data['terms'])) {
                    $system->error_exit("Invalid data structure sent with saveTerms method call to saveStructure.php");
                }
                
                $colNames = $data['terms']['colNames'];
                $rv['result'] = array(); //result
                $parent_is_valid = true;
                /*  TO DO
                $idx = array_search('trm_ParentTermID', $colNames);
                //verify that there is not recursion in parent-child
                foreach ($data['terms']['defs'] as $trmID => $dt) {
                    $new_parent_ID = $dt[$idx];
                    $all_children = getAllChildren($trmID);
                    
error_log($trmID.'  '.$new_parent_ID.'  '.print_r($all_children, true));                    
                    
                    if(true || array_search($new_parent_ID, $all_children, true)!==false){
                        array_push($rv['result'], "Proposed new parent term ID $new_parent_ID is among children of term to be updated (#$trmID  ). Can't proceed");    
                        $parent_is_valid = false;
                        break;
                    }
                }
                */
                
                if($parent_is_valid){

                    foreach ($data['terms']['defs'] as $trmID => $dt) {
                        $res = updateTerms($colNames, $trmID, $dt, null);
                        array_push($rv['result'], $res);
                    }
                    // slows down the performance, but we need the updated terms because Ian wishes to update terms
                    // while selecting terms while editing the field type
                    $rv['terms'] = dbs_GetTerms($system);
                }
                
                break;

            case 'mergeTerms':

                $retain_id = @$_REQUEST['retain'];
                $merge_id = @$_REQUEST['merge'];
                if($retain_id==null || $merge_id==null || $retain_id == $merge_id){
                    $system->error_exit("Invalid data structure sent with mergeTerms method call to saveStructure.php");
                }

                $colNames = $data['terms']['colNames'];
                $dt = @$data['terms']['defs'][$retain_id];

                $ret = mergeTerms($retain_id, $merge_id, $colNames, $dt);

                if($ret!==false){
                    $rv['result'] = $ret;
                    $rv['terms'] = dbs_GetTerms($system);
                }else{
                    $rv = false;
                }
                

                break;

            case 'checkTerm':  //show the usage if term is in vocab

                $termID = @$_REQUEST['termID'];
                $rv = checkTerms($termID);

                break;
                
            case 'deleteTerms':
                $trmID = @$_REQUEST['trmID'];

                if (!$trmID) {
                    $system->error_exit('Error: No IDs or invalid IDs sent with deleteTerms method call to saveStructure.php');
                }else{
                    $ret = deleteTerms($trmID);
                    if($ret!==false){
                        $rv['result'] = $ret;
                        $rv['terms'] = dbs_GetTerms($system);
                    }else{
                        $rv = false;
                    }
                }
                break;
        }
        $mysqli->close();

        /*
        if (@$rv) {
        print json_format($rv);
        }*/

}//$method!=null


if($rv===false){
    $response = $system->getError();
}else if(@$rv['error']){  //sql error
    $response = $system->addError(HEURIST_ERROR, $rv['error']);
}else{
    $response = array("status"=>HEURIST_OK, "data"=>$rv);
}

ob_start(); 
echo json_encode($response);
$output = gzencode(ob_get_contents(),6); 
ob_end_clean(); 

header('Content-Encoding: gzip');
header('Content-type: text/javascript; charset=utf-8');
echo $output; 
unset($output);
?>
