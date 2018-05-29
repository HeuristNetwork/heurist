<?php

    /**
    * checkRectypeTitleMask.php: Verifies the validity of the record type title masks and lists their contents
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.1.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

define('MANAGER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hserver/utilities/titleMask.php');

$mysqli = $system->get_mysqli();

    // 0 execute for given rec_id, 1 - check only, 2 - check and correct, 3 - output as json list of rectypes with wrong rectitles
    // 2,3 not used at the moment
    $mode = @$_REQUEST['check'] ? intval($_REQUEST['check']):0;

    $rectypeID = @$_REQUEST['rty_id'] ? $_REQUEST['rty_id'] : null;
    $mask = @$_REQUEST['mask'] ? $_REQUEST['mask'] : null;
    $coMask = @$_REQUEST['coMask'] ? $_REQUEST['coMask'] : null; //deprecated - not used
    $recID = @$_REQUEST['rec_id']? $_REQUEST['rec_id'] : null;;

if($mode!=3){
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            Table tr td {
                line-height:2em;
            }
            .statusCell{
                width:50px;
                display: table-cell;
            }
            .maskCell{
                width:550px;
                display: table-cell;
            }
            .errorCell{
                display: table-cell;
                color: red;
            }
            .valid{
                color:green;
            }
            .invalid{
                color:red;
            }

        </style>
    </head>

<body class="popup">

        <div class="banner">
            <h2><?=(($mode==2)?'Synch Canonical Title Masks':'Check Title Masks') ?></h2>
        </div>

        <div id="page-inner">

            Title masks are used to construct a composite title for a record based on data fields in the record.<br/>
            For many record types, they will just render the title field, or the title with some additional contextual information.<br/>
            For bibliographic records they provide a shortened bibliographic style entry.<br/><br/>
            This check looks for ill formed title masks for record types defined in the <b><?=HEURIST_DBNAME?></b> Heurist database.<br/><br/>
            If the title mask is invalid please edit the record type (see under Structure in the menu on the left)
            and correct the title mask for the record type.<br/><br/><hr>
<?php
}//$mode!=3
                
                
                $rtIDs = mysql__select_assoc2($mysqli, 'select rty_ID, rty_Name from defRecTypes order by rty_ID');

                if($mode==3){
                    $rt_invalid_masks = array();
                    foreach ($rtIDs as $rtID => $rtName) {
                        $mask = mysql__select_value($mysqli, 'select rty_TitleMask from defRecTypes where rty_ID='.$rtID);
                        
                        //get human readable
                        $res = TitleMask::execute($mask, $rtID, 2, null, _ERR_REP_MSG);
                        
                        if(is_array($res)){ //invalid mask
                            //$res[0]; // mask is invalid - this is error message
                            array_push($rt_invalid_masks, $rtName);
                        }
                    }
                    header('Content-type: text/javascript');
                    print json_encode($rt_invalid_masks);

                }else{

                    if (!$rectypeID){
                        //check all rectypes
                        foreach ($rtIDs as $rtID => $rtName) {
                            checkRectypeMask($rtID, $rtName, null, null, null, $mode);
                        }
                    }else{
                        checkRectypeMask($rectypeID, $rtIDs[$rectypeID], $mask, $coMask, $recID, $mode);
                    }

                    echo "</body></html>";
                }

                function checkRectypeMask($rtID, $rtName, $mask, $coMask, $recID, $mode) {
                    global $mode, $mysqli;

                    if (!@$mask && @$rtID) {
                        
                        $mask = mysql__select_value($mysqli, 'select rty_TitleMask from defRecTypes where rty_ID='.$rtID);
                        
                    }

                    if($mode > 0 || !$recID)
                    {
                        echo "<h3 style=\"padding:15px 0px 10px 4px;\"><b> $rtID : <i>$rtName</i></b> <br/> </h3>";

                        if($mask==null || trim($mask)==""){
                            $res = array();
                            $res[0] = "Title mask is not defined";
                        }else{
                            //get human readable
                            $res = TitleMask::execute($mask, $rtID, 2, null, _ERR_REP_MSG);
                        }
                        echo "<div class='resultsRow'><div class='statusCell ".
                                    (is_array($res)? "invalid'>in":"valid'>")."valid</div>";
                                    
                        echo "<div class='maskCell'>Mask: <i>$mask</i></div>";
                        if(is_array($res)){
                            echo "<div class='errorCell'><b>< < < < < ".$res[0]."</b></div>";
                        }else if ($mask == "") {
                            echo "<div class='errorCell'><b>< < < < < EMPTY TITLE MASK</b></div>";
                        }else if(strcasecmp($res,$mask)!=0){
                            echo "<div><br/>&nbsp;Decoded Mask: $res</div>";
                        }
                        echo "</div>";

                        echo "\n";
                    }else{
                        echo "Checking title mask $mask for record type $rtID and record $recID <br/>";
                        echo TitleMask::fill($recID, $mask);
                    }
                }
            ?>
        </div>
    </body>
</html>