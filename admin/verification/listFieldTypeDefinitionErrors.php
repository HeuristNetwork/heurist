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
* brief description of file
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

?>
<html>

	<head>
        <script src="../../common/js/utilsUI.js"></script>
		<script type=text/javascript>
        
            var Hul = top.HEURIST.util;

			function open_selected() {
				var cbs = document.getElementsByName('bib_cb');
				if (!cbs  ||  ! cbs instanceof Array)
				return false;
				var ids = '';
				for (var i = 0; i < cbs.length; i++) {
					if (cbs[i].checked)
					ids = ids + cbs[i].value + ',';
				}
				var link = document.getElementById('selected_link');
				if (!link)
				return false;
				link.href = '../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:' + ids;
				return true;
			}
            
            function onEditFieldType(dty_ID){

                var url = top.HEURIST.basePath + "admin/structure/editDetailType.html?db=<?= HEURIST_DBNAME?>";
                if(dty_ID>0){
                    url = url + "&detailTypeID="+dty_ID; //existing
                }else{
                    return;
                }

                top.HEURIST.util.popupURL(top, url,
                {   "close-on-blur": false,
                    "no-resize": false,
                    height: 680,
                    width: 700,
                    callback: function(context) {
                    }
                });
            }
		</script>

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
		</style>
	</head>

	<body class="popup">
    
    
        <script src="../../common/js/utilsLoad.js"></script>
        <script src="../../common/php/loadCommonInfo.php"></script>
    
		<?php

			mysql_connection_select(DATABASE);
			// lookup detail type enum values
			$TL = array();
			$query = 'SELECT trm_ID, trm_Label, trm_ParentTermID, trm_OntID, trm_Code FROM defTerms';
			$res = mysql_query($query);
			while ($row = mysql_fetch_assoc($res)) {
				$TL[$row['trm_ID']] = $row;
			}

			$DTT = array();
			$query = "SELECT dty_ID,".
			"dty_Name,".
			"dty_Type,".
			"dty_JsonTermIDTree,".
			"dty_TermIDTreeNonSelectableIDs,".
			"dty_PtrTargetRectypeIDs".
			" FROM defDetailTypes".
			" WHERE (dty_Type in ('enum','relationtype','relmarker','resource')".
			" and (dty_JsonTermIDTree is not null or dty_TermIDTreeNonSelectableIDs is not null)) ".
			"or (dty_Type in ('relmarker','resource') and dty_PtrTargetRectypeIDs is not null); ";
			$res = mysql_query($query);
			while ($row = mysql_fetch_assoc($res)) {
				$DTT[$row['dty_ID']] = $row;
			}

			$RTN = array();	//record type name
			$query = 'SELECT rty_ID, rty_Name FROM defRecTypes';
			$res = mysql_query($query);
			while ($row = mysql_fetch_assoc($res)) {
				$RTN[$row['rty_ID']] = $row['rty_Name'];
			}

			// function that translates all term ids in the passed string to there local/imported value
			function getInvalidTerms($formattedStringOfTermIDs) {
				global $TL;
				$invalidTermIDs = array();
				if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
					return $invalidTermIDs;
				}

				if (strpos($formattedStringOfTermIDs,"{")!== false) {
					/*****DEBUG****///error_log( "term tree string = ". $formattedStringOfTermIDs);
					$temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
					if (strrpos($temp,":") == strlen($temp)-1) {
						$temp = substr($temp,0, strlen($temp)-1);
					}
					$termIDs = explode(":",$temp);
				} else {
					/*****DEBUG****///error_log( "term array string = ". $formattedStringOfTermIDs);
					$temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
					$termIDs = explode(",",$temp);
				}
				// Validate termIDs
                
				foreach ($termIDs as $trmID) {
					// check that the term valid
					if (!$trmID ){ // invalid trm ID null or 0 is not allowed
                        if(count($termIDs)>1){
                            array_push($invalidTermIDs,"blank");    
                        }
					}else if ( !$TL[$trmID]){ // invalid trm ID
						array_push($invalidTermIDs,$trmID);
					}
				}
				return $invalidTermIDs;
			}

			// function that check the existaance of all rectype ids in the passed string
			function getInvalidRectypes($formattedStringOfRectypeIDs) {
				global $RTN;
				$invalidRectypeIDs = array();
				if (!$formattedStringOfRectypeIDs || $formattedStringOfRectypeIDs == "") {
					return $invalidRectypeIDs;
				}

				$rtyIDs = explode(",",$formattedStringOfRectypeIDs);
				// Validate rectypeIDs
				foreach ($rtyIDs as $rtID) {
					// check that the rectype is valid
					if (!$RTN[$rtID]){ // invalid rty ID
						array_push($invalidRectypeIDs,$rtID);
					}
				}
				return $invalidRectypeIDs;
			}

			$dtysWithInvalidTerms = array();
			$dtysWithInvalidNonSelectableTerms = array();
			$dtysWithInvalidRectypeConstraint = array();
			foreach ( $DTT as $dtyID => $dty) {
				if ($dty['dty_JsonTermIDTree']){
					$invalidTerms = getInvalidTerms($dty['dty_JsonTermIDTree']);
					if (count($invalidTerms)){
						$dtysWithInvalidTerms[$dtyID] = $dty;
						$dtysWithInvalidTerms[$dtyID]['invalidTermIDs'] = $invalidTerms;
					}
				}
				if ($dty['dty_TermIDTreeNonSelectableIDs']){
error_log(">>>>".$dty['dty_TermIDTreeNonSelectableIDs']);
					$invalidNonSelectableTerms = getInvalidTerms($dty['dty_TermIDTreeNonSelectableIDs']);
					if (count($invalidNonSelectableTerms)){
						$dtysWithInvalidNonSelectableTerms[$dtyID] = $dty;
						$dtysWithInvalidNonSelectableTerms[$dtyID]['invalidNonSelectableTermIDs'] = $invalidNonSelectableTerms;
					}
				}
				/*****DEBUG****///error_log("selectable - ".print_r($dtysWithInvalidNonSelectableTerms,true));
				if ($dty['dty_PtrTargetRectypeIDs']){
					$invalidRectypes = getInvalidRectypes($dty['dty_PtrTargetRectypeIDs']);
					if (count($invalidRectypes)){
						$dtysWithInvalidRectypeConstraint[$dtyID] = $dty;
						$dtysWithInvalidRectypeConstraint[$dtyID]['invalidRectypeConstraint'] = $invalidRectypes;
					}
				}
			}

		?>
		<div class="banner">
			<h2>Invalid Field Type Definition check</h2>
		</div>
		<div id="page-inner">

			These checks look for invalid references within the Heurist database structure for Field Type Definitions. These should arise rarely.
			Click the hyperlinked number at the start of each row to open an edit form on that Field Type definition. Look edit and save the Terms or Pointer definitions.

			<hr/>

			<div>
				<h3>Enumeration, Relationtype or Relmarker Field Types with invalid terms definitions</h3>
			</div>
			<table>
				<?php
					if (!count($dtysWithInvalidTerms)){
					?>
					<tr>
						<td> All field type definition contain valid term IDs.</td>
					</tr>
					<?php
					}else{
						foreach ($dtysWithInvalidTerms as $row) {
						?>
					<tr>
						<td><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_ID'] ?></a></td>
						<td><?= $row['dty_Name'] ?></td>
						<td> a(n) "<?= $row['dty_Type'] ?>" field type definition contains <?= count($row['invalidTermIDs'])?> invalid term ID(s) <?= join(",",$row['invalidTermIDs'])?></td>
					</tr>
						<?php
						}//for
					}
					?>
			</table>
			<hr/>
			<div>
				<h3>Enumeration, Relationtype or Relmarker Field Types with invalid non-selectable terms definitions</h3>
			</div>
			<table>
					<?php
					if (!count($dtysWithInvalidNonSelectableTerms)){
					?>
					<tr>
						<td> All field type definition contain valid non-selectable term IDs.</td>
					</tr>
					<?php
					}else{
						foreach ($dtysWithInvalidNonSelectableTerms as $row) {
						?>
					<tr>
                        <td><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_ID'] ?></a></td>
						<td><?= $row['dty_Name'] ?></td>
						<td> a(n) "<?= $row['dty_Type'] ?>" field type definition contains <?= count($row['invalidNonSelectableTermIDs'])?> invalid non selectable term ID(s) <?= join(",",$row['invalidNonSelectableTermIDs'])?></td>
					</tr>
						<?php
						}
					}
				?>
			</table>
			[end of list]

			<hr/>

			<div>
				<h3>Reference/Resource Pointer or Relmarker Field Types with invalid rectype(s) in constraint definitions</h3>
			</div>
			<table>
				<?php
					if (!count($dtysWithInvalidRectypeConstraint)){
					?>
				<tr>
					<td> All field type definition contain valid Rectype IDs.</td>
				</tr>
					<?php
					}else{
						foreach ($dtysWithInvalidRectypeConstraint as $row) {
						?>
				<tr>
                    <td><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_ID'] ?></a></td>
					<td><?= $row['dty_Name'] ?></td>
					<td> a(n) "<?= $row['dty_Type'] ?>" field type definition contains <?= count($row['invalidRectypeConstraint'])?> invalid rectype ID(s) <?= join(",",$row['invalidRectypeConstraint'])?></td>
				</tr>
						<?php
						}
					}
				?>
			</table>
			[end of list]
			<hr/>

		</div>
	</body>
</html>

