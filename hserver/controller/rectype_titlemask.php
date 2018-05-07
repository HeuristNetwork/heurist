<?php

    /**
    * Determines rectype relations for a certain database.
    * 
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Jan Jaap de Groot  <jjedegroot@gmail.com>
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

    require_once (dirname(__FILE__).'/../System.php');        
    require_once (dirname(__FILE__).'/../utilities/titleMask.php');


    if(isset($_REQUEST['db'])) {
        $dbName = $_REQUEST['db'];
        
        // Initialize a System object that uses the requested database
        $system = new System();
        if( $system->init($dbName) ){
            
            $rectypeID = @$_REQUEST['rty_id'];
            $mask = @$_REQUEST['mask'];
            $check_mode = $_REQUEST["check"];

            if($check_mode==2){ //get coded mask
                
                $res = TitleMask::execute($mask, $rectypeID, 1, null, _ERR_REP_MSG);
                //$res = titlemask_make($mask, $rectypeID, 1, null, _ERR_REP_MSG);
                print is_array($res)?$res[0]:$res;
                
            }else 
            if($check_mode==3){ //human readable

                $res = TitleMask::execute($mask, $rectypeID, 2, null, _ERR_REP_MSG);
                //$res = titlemask_make($mask, $rectypeID, 2, null, _ERR_REP_MSG);
                print is_array($res)?$res[0]:$res;
                
            }else
            if($check_mode==1){ //verify text title mask
            
                $check = TitleMask::check($mask, $rectypeID, true);
                //$check = check_title_mask2($mask, $rectypeID, true);

                if(!empty($check)){
                    print $check;
                }else{
                    print "";
                }
                
            }else{
                
                $recID = @$_REQUEST['rec_id'];
                $new_title = TitleMask::execute($mask, $rectypeID, 0, $recID, _ERR_REP_WARN);
                //$new_title = TitleMask::fill($recID);
                //$new_title = titlemask_value($mask, $recID);//."<br><br>".fill_title_mask_old($mask, $recID, $rectypeID);
                print $new_title;
                
            }
                
            // Returning result as JSON
            //header('Content-type: application/json');
            //print json_encode($result);
        }else {
            // Show construction error
            echo $system->getError();   
        }
    }else{
        echo "\"db\" parameter is required";
    }
?>