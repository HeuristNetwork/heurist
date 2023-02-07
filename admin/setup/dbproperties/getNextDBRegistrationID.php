<?php

/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* getNextRegistrationID.php - request an ID from Heurist master index/db=HeuristMasterIndex, allocates the ID,
* sets metadata in record and details.
* This file is called by registerDB.php
* ONLY ALLOW IN HEURIST Master Index database
* 
* returns new id  or 0,error message
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
// TO DO: WE NEED SOME MECHANISM TO AVOID DENIAL OF SERVICE ATTACK WHICH REPEATEDLY REQUESTS REGISTRATIONS

// TODO: We may need to hobble/delete some of the functionality on Heurist Reference Index db (HEURIST_INDEX_DATABASE) to avoid people
// creating unwanted records or importing random crap into it
require_once (dirname(__FILE__).'/../../../hsapi/System.php');
require_once (dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');

if(@$_REQUEST["db"]!=HEURIST_INDEX_DATABASE){
    echo '0,This script allowed for Master Index database only';
    return;
}

$system = new System();
$connect_failure = (!$system->init(@$_REQUEST['db'], false, false));

if($connect_failure){
    echo '0, Failed to connect to  Master Index database';
    return;
}

$out = DbUtils::databaseNextRegisterID($_REQUEST);

echo $out;
?>
