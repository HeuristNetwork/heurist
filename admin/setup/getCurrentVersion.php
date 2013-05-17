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
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

    require_once(dirname(__FILE__)."/../../common/config/initialise.php");
    require_once(dirname(__FILE__)."/../../records/files/fileUtils.php");

    $is_check = @$_REQUEST["check"];

    if($is_check){ // || HEURIST_INDEX_BASE_URL==HEURIST_BASE_URL){ //this is main server
        //return current db and code versions
        echo HEURIST_VERSION."|".HEURIST_DBVERSION;
        exit();
    }

//
function getLastCheckedVersion($date_and_version){

    if($date_and_version){

            $arr = explode("|", $date_and_version);
            if($arr && count($arr)==2){
                $date_last_check    = $arr[0];
                $version_last_check = $arr[1];

                //debug  $date_last_check = "2013-02-10";

                $days =intval((time()-strtotime($date_last_check))/(3600*24));

                if(intval($days)<7){
                    return $date_and_version;
                }
            }
    }
    return null; //version check is outdated or not performed at all
}

//
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

        //send request to main server HEURIST_INDEX_BASE_URL
        $url = HEURIST_INDEX_BASE_URL . "admin/setup/getCurrentVersion.php?db=H3MasterIndex&check=1";

        $rawdata = loadRemoteURLContent($url);

        if($rawdata){
            //parse result
            $current_version = explode("|", $rawdata);
            if(count($current_version)>0)
            {
                //debug $current_version[0] = "5.9.1 RC2";

                $curver = explode(".", $current_version[0]);
                if(count($curver)>2){
                    $major = intval($curver[0]);
                    $subver = intval($curver[1]);

                    //compare with local versions
                    $locver = explode(".", HEURIST_VERSION);
                    $major_local = intval($locver[0]);
                    $subver_local = intval($locver[1]);

                    $email_title = null;
                    if($major_local<$major){
                        $email_title = "Major new version of Heurist Vsn ".$current_version[0]." available for ".HEURIST_SERVER_NAME." (running Vsn ".HEURIST_VERSION.")";

                    }else if($major_local == $major && $subver_local<$subver){
                        $email_title = "Heurist update Vsn ".$current_version[0]." available for ".HEURIST_SERVER_NAME." (running Vsn ".HEURIST_VERSION.")";
                    }

                    if($email_title){

                        //send email to administrator about new database registration
                        $email_text =
                        "Your server is running Heurist version ".HEURIST_VERSION." The current stable version of Heurist (version ".
                        $current_version[0].") is available from HeuristScholar.org We recommend updating your copy of the software if the sub-version has changed ".
                        "(or better still with any change of version).\n\n".
                        "Heurist is copyright (C) The University of Sydney and available as Open Source software under the GNU-GPL licence. ".
                        "Beta versions of the software with new features may also be available at the Google Code repository, linked from the Heurist home page.";

                        //
                        $rv = mail(HEURIST_MAIL_TO_ADMIN, $email_title, $email_text, "From: root");
                        if (! $rv) {//TODO  SAW this should not fail silently
                            error_log("mail send failed: " . HEURIST_MAIL_TO_ADMIN);
                            //to delete check file ?????
                        }else{
                        }
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