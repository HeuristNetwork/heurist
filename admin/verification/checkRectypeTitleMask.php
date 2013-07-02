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

$check = @$_REQUEST['check'] ? intval($_REQUEST['check']):0;
if ($check == 0) {
	mysql_connection_select(DATABASE);
}else{
	mysql_connection_overwrite(DATABASE);
}

$rectypeID = @$_REQUEST['rty_id'] ? $_REQUEST['rty_id'] : null;
$mask = @$_REQUEST['mask'] ? $_REQUEST['mask'] : null;
$coMask = @$_REQUEST['coMask'] ? $_REQUEST['coMask'] : null;
$recID = @$_REQUEST['rec_id']? $_REQUEST['rec_id'] : null;;

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
			<h2><?=(($check==2)?'Synch Canonical Title Masks':'Check Title Masks') ?></h2> <!-- <?=$check==2?" AND CANONICAL SYNCHRONIZATION":"" ?> for <i>"<?=HEURIST_DBNAME?>"</i> -->
		</div>
		<div id="page-inner">

			These checks look for ill formed Title Masks for Record Types defined in the <b><?=HEURIST_DBNAME?></b> Heurist database.<br/><br/>
			If the Title mask is invalid please edit the Record Type directly.<br/>
		<?php
			if ($check != 2) {
				echo "If the Canonical Title mask is invalid please run 'Synchronise Canocnial Title Mask' command below\n";
			}else{
				echo "Canonical Title mask will synchronise to Title Mask and carries the same validity as the Title Mask\n";
			}

			echo "<br/><hr>\n";

$rtIDs = mysql__select_assoc("defRecTypes","rty_ID","rty_Name","1 order by rty_ID");


if (!$rectypeID){
	foreach ($rtIDs as $rtID => $rtName) {
		checkRectypeMask($rtID, $rtName, null, null, null, $check);
	}
}else{
	checkRectypeMask($rectypeID, $rtIDs[$rectypeID], $mask, $coMask, $recID, $check);
}

function checkRectypeMask($rtID, $rtName, $mask, $coMask, $recID, $check) {
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
	if($check > 0 || !$recID)
	{
?>
			<div>
				<h3>Checking rectype "<b><i><?=$rtName?></i></b>"[<?=$rtID?>]</h3>
			</div>
<?php

        $res = titlemask_make($mask, $rtID, 2, null, _ERR_REP_MSG); //get human readable
        echo "<div class='resultsRow'><div class='statusCell ".(is_array($res)? "invalid'>in":"valid'>")."valid</div>";
        echo "<div class='maskCell'>Mask: <i>$mask</i></div>";
        if(is_array($res)){
            echo "<div class='errorCell'>".$res[0]."</div>";
        }else if(strcasecmp($res,$mask)!=0){
            echo "<div>Decoded mask: $res</div>";
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
			if ($check != 2) {
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
		echo "<hr>\n";
	}else{
		echo "checking type mask $mask for recType $rtID and rec $recID <br/>";
		echo fill_title_mask($mask, $recID, $rtID);
	}
}
?>
	</body>
</html>
<?php
exit();
?>
