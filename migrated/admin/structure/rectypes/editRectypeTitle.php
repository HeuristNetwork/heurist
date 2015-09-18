<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
*  Generates the title from mask, recid and rectype
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


    require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../../common/php/utilsTitleMask.php');
    //require_once(dirname(__FILE__).'/../../../common/php/utilsTitleMaskOld.php');

    mysql_connection_select(DATABASE);

    $rectypeID = @$_REQUEST['rty_id'];
    $mask = @$_REQUEST['mask'];

    if(array_key_exists("check", @$_REQUEST))
    {
        if($_REQUEST["check"]==2){ //get coded mask

            $res = titlemask_make($mask, $rectypeID, 1, null, _ERR_REP_MSG);
            print is_array($res)?$res[0]:$res;

        }else if($_REQUEST["check"]==3){ //get human readable mask

            $res = titlemask_make($mask, $rectypeID, 2, null, _ERR_REP_MSG);
            print is_array($res)?$res[0]:$res;

        }else{ ///verify text title mask

            $check = check_title_mask2($mask, $rectypeID, true);

            if(!empty($check)){
                print $check;
            }else{
                print "";
            }
        }
    }else{
        $recID = @$_REQUEST['rec_id'];
        
        $res = titlemask_value($mask, $recID);//."<br><br>".fill_title_mask_old($mask, $recID, $rectypeID);
        print $res;
        
        //echo fill_title_mask_old($mask, $recID, $rectypeID);
        /* it works - but beforehand verification is already done on client side
        $check = check_title_mask2($mask, $rectypeID, true);
        if(empty($check)){
            echo titlemask_value($mask, $recID); // fill_title_mask($mask, $recID, $rectypeID);
        }else{
           echo array("error"=>$check);
        }*/
    }
    exit();
?>