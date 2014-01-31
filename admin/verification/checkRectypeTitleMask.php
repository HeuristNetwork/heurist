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
* editRectypeTitle.php
* Generates the title from mask, recid and rectype
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


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
//require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');

// 1 - check only, 2 - check and correct, 3 - output as json list of rectypes with wrong rectitles
$mode = @$_REQUEST['check'] ? intval($_REQUEST['check']):0;
if ($mode == 0) {
	mysql_connection_select(DATABASE);
}else{
	mysql_connection_overwrite(DATABASE);
}

$rectypeID = @$_REQUEST['rty_id'] ? $_REQUEST['rty_id'] : null;
$mask = @$_REQUEST['mask'] ? $_REQUEST['mask'] : null;
$coMask = @$_REQUEST['coMask'] ? $_REQUEST['coMask'] : null; //deprecated - not used
$recID = @$_REQUEST['rec_id']? $_REQUEST['rec_id'] : null;;

if($mode!=3){
?>

<html>

	<head>
		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
		<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
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
			<h2><?=(($mode==2)?'Synch Canonical Title Masks':'Check Title Masks') ?></h2> <!-- <?=$mode==2?" AND CANONICAL SYNCHRONIZATION":"" ?> for <i>"<?=HEURIST_DBNAME?>"</i> -->
		</div>
		<div id="page-inner">

			Title masks are used to construct a composite title for a record based on data fields in the record.<br/>
            For many record types, they will jsut render the title field, or title with some additional contextual information.<br/>
            For bibliographic records they provide a shortened bibliographic style entry.<br/><br/>
            This check looks for ill formed title masks for record types defined in the <b><?=HEURIST_DBNAME?></b> Heurist database.<br/><br/>
			If the title mask is invalid please edit the record type (see under Essentials in the menu on the left) and correct the title mask for the record type.<br/>
		<?php
			
			echo "<br/><hr>\n";
}//$mode!=3
$rtIDs = mysql__select_assoc("defRecTypes","rty_ID","rty_Name","1 order by rty_ID");

if($mode==3){
        $rt_invalid_masks = array();
        foreach ($rtIDs as $rtID => $rtName) {
            $mask= mysql__select_array("defRecTypes","rty_TitleMask","rty_ID=$rtID");
            $mask=$mask[0];
            $res = titlemask_make($mask, $rtID, 2, null, _ERR_REP_MSG); //get human readable
            if(is_array($res)){ //invalid mask
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
    global $mode;
    
	if (!@$mask && @$rtID) {
		$mask= mysql__select_array("defRecTypes","rty_TitleMask","rty_ID=$rtID");
		$mask=$mask[0];
	}
    /* deprecated
	if (!@$coMask && @$rtID) {
		$coMask= mysql__select_array("defRecTypes","rty_CanonicalTitleMask","rty_ID=$rtID");
		$coMask=$coMask[0];
	}*/

	//echo print_r($_REQUEST,true);
	if($mode > 0 || !$recID)
	{
		echo "<h3><b> $rtID : <i>$rtName</i></b> <br/> </h3>";

        $res = titlemask_make($mask, $rtID, 2, null, _ERR_REP_MSG); //get human readable
        echo "<div class='resultsRow'><div class='statusCell ".(is_array($res)? "invalid'>in":"valid'>")."valid</div>";
        echo "<div class='maskCell'>Mask: <i>$mask</i></div>";
        if(is_array($res)){
            echo "<div class='errorCell'>".$res[0]."</div>";
        }else if(strcasecmp($res,$mask)!=0){
            echo "<div><br/>&nbsp;Decoded Mask: $res</div>";
        }
        echo "</div>";

    /* deprecated
		$retMaskCheck = check_title_mask2($mask, $rtID, true);
		echo "<div class='resultsRow'><div class='statusCell ".($retMaskCheck == "" ? "valid'>":"invalid'>in")."valid</div>";
		echo "<div class='maskCell'>mask = <i>$mask</i></div>";
		if ($retMaskCheck != ""){
			echo "<div class='errorCell'>".$retMaskCheck."</div>";
		}
		echo "</div>";
		$retCoMaskCheck = check_title_mask2($coMask, $rtID, true);
		echo "<div class='resultsRow'><div class='statusCell ".($retCoMaskCheck == "" ? "valid'>":"invalid'>in")."valid</div>";
		echo "<div class='maskCell'>canonical mask = <i>$coMask</i></div>";
		if ($retCoMaskCheck != ""){
			echo "<div class='errorCell'>".$retCoMaskCheck."</div>";
		}
		echo "</div>";

		if ($retCoMaskCheck !== "" && $retMaskCheck == "") {
			$coMask = titlemask_make($mask, $rtID, 1); //make canonical
			if ($mode != 2) {
				echo "<div class='resultsRow'><div class='statusCell'></div><div class='maskCell'>Correct canonical mask = <span class='valid'>$coMask</span></div></div>";
			}else{ // repair canonical
				mysql_query("update defRecTypes set rty_CanonicalTitleMask='$coMask' where rty_ID=$rtID");
				$error = mysql_error();
				echo "<div class='resultsRow'><div class='statusCell ".($error == "" ? "valid'>Update successful":"invalid'>Failed to update")."</div>";
				echo "<div class='maskCell'>Correct canonical mask = <span class='valid'>$coMask</span></div>";
				echo ( $error ? "<div class='errorCell invalid'> Error : ".$error."</div>":"")."</div>";
			}
		}
    */
		echo "\n";
	}else{
		echo "checking type mask $mask for recType $rtID and rec $recID <br/>";
		echo fill_title_mask($mask, $recID, $rtID);
	}
}
?>