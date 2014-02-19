<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* getCurrentVersion.php - requests code and database version from HeuristScholar.org
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

    require_once(dirname(__FILE__)."/../../../common/config/initialise.php");
    require_once(dirname(__FILE__)."/../../../records/files/fileUtils.php");
    require_once(dirname(__FILE__)."/../../../common/php/utilsMail.php");

    $is_check = @$_REQUEST["check"];
    

// REFERENCE SERVER
// Code to run on the reference server to return the current program and database versions

    if($is_check){ // check is set to 1 when this is called to contact the Heurist reference server.
                   // If HEURIST_INDEX_BASE_URL==HEURIST_BASE_URL, this script is running on the reference server
        //return current db and code versions
        echo HEURIST_VERSION."|".HEURIST_DBVERSION;
        exit();
    }

    
// LOCAL COPY CHECK
// Code to run on a copy which is checking itself against the reference server

/**
* return the date of last check if it is less than 7 days, otherwise it returns null
*
* @param mixed $date_and_version
*/
function getLastCheckedVersion($date_and_version){

    if($date_and_version){

            $arr = explode("|", $date_and_version);
            if($arr && count($arr)==2){
                $date_last_check    = $arr[0];
                $version_last_check = $arr[1];

                //debug  $date_last_check = "2013-02-10";
                if(strtotime($date_last_check) && checkVersionValid($version_last_check)){
                    $days =intval((time()-strtotime($date_last_check))/(3600*24));

                    if(intval($days)<7){
                        return $date_and_version;
                    }
                }
            }
    }
    return null; //version check is outdated or has never been done
}

function checkVersionValid($version){

    $current_version = explode("|", $version);

    if (count($current_version)>0)
    {
                $curver = explode(".", $current_version[0]);
                if( count($curver)>=2 && intval($curver[0])>0 && is_numeric($curver[1]) && intval($curver[1])>=0 ){
                    return true;
                }
    }

    return false;

}

/**
* request version on Heurist reference server and compare it with the local version
*
* @param mixed $version_in_session
*/
function checkVersionOnMainServer($version_in_session)
{
        $version_last_check = getLastCheckedVersion($version_in_session);

        //check data of last check and last warning email
        $fname = HEURIST_UPLOAD_ROOT."lastAdviceSent.ini";
        if ($version_last_check==null && file_exists($fname)){
            //last check and version
            $version_in_session = file_get_contents($fname);
            $version_last_check = getLastCheckedVersion($version_in_session);
        }

        if ($version_last_check){
            return $version_in_session;
        }

        //send request to main server at HEURIST_INDEX_BASE_URL
        // H3MasterIndex is the refernece standard for current database version
        // TODO: Maybe this should be changed to H3Sandpit?
        $url = HEURIST_INDEX_BASE_URL . "admin/setup/dbproperties/getCurrentVersion.php?db=H3MasterIndex&check=1";

        $rawdata = loadRemoteURLContent($url);

        if($rawdata){
            //parse result
            if(checkVersionValid($rawdata))
            {
   
                // $rawdata contains program version | database version
                $current_version = explode("|", $rawdata);

                // $curver is the current program version
                $curver = explode(".", $current_version[0]);
                if(count($curver)>=2){
                    $major = intval($curver[0]);
                    $subver = intval($curver[1]);

                    //compare with local program version set in HEURIST_VERSION
                    $locver = explode(".", HEURIST_VERSION);
                    $major_local = intval($locver[0]);
                    $subver_local = intval($locver[1]);

                    // TODO: HEURIST_VERSION is not rendering the local version in the email below, yet HEURIST_SERVER_NAME works
                    // and it seems to have set the $variables and to detect local version OK ???
 
                    error_log("Heurist Version = ".HEURIST_VERSION); // DEBUG                 
                    $email_title = null;
                    if($major_local<$major){
                        $email_title = "Major new version of Heurist Vsn ".$current_version[0]." available for "
                                        .HEURIST_SERVER_NAME." (running Vsn ".HEURIST_VERSION.")";

                    }else if($major_local == $major && $subver_local<$subver){
                        $email_title = "Heurist update Vsn ".$current_version[0]." available for "
                                        .HEURIST_SERVER_NAME." (running Vsn ".HEURIST_VERSION.")";
                    }

                    if($email_title){

                        //send email to administrator about new database registration
                        $email_text =
                        "Your server is running Heurist version ".HEURIST_VERSION." The current stable version of Heurist (version ".
                        $current_version[0].") is available from <a href='https://code.google.com/p/heurist/'>Google Code</a> or ".
                        "<a href='http://HeuristNetwork.org'>HeuristNetwork.org</a>. We recommend updating your copy of the software if the sub-version has changed ".
                        "(or better still with any change of version).\n\n".
                        "Heurist is copyright (C) 20075 - 2014 The University of Sydney and available as Open Source software under the GNU-GPL licence. ".
                        "Beta versions of the software with new features may also be available at the Google Code repository or linked from the HeuristNetwork home page.";

                        sendEmail(HEURIST_MAIL_TO_ADMIN, $email_title, $email_text, null);
                    }

                    //save current date of check
                    $version_in_session = date("Y-m-d")."|".$current_version[0];
                    if(!saveAsFile($version_in_session, $fname)){
                                error_log("Can't write file ".$fname);
                    }
                    return $version_in_session;
                }
            }
        }

        return null; //version check fails
}
?>