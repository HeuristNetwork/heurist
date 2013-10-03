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
* listFieldTypeDefinitionErrorsCompact.php - list for editRecord
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

    require_once('getFieldTypeDefinitionErrors.php');
    
    if(@$_REQUEST['data']){
        $lists = json_decode($_REQUEST['data'], true);
    }else{
        $lists = getInvalidFieldTypes(@$_REQUEST['rt']);
        if(!@$_REQUEST['show']){
            if(count($lists["terms"])==0 && count($lists["terms_nonselectable"])==0 && count($lists["rt_contraints"])==0){
                $lists = array();   
            }
            print json_encode($lists);
            exit();
        }
    }
    
    $dtysWithInvalidTerms = $lists["terms"];
    $dtysWithInvalidNonSelectableTerms = $lists["terms_nonselectable"];
    $dtysWithInvalidRectypeConstraint = $lists["rt_contraints"];
?>    
<html>

	<head>
        <title>Invalid Field Type Definition check</title>
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

	<body class="popup" style="width:400;height:400;">
    
		<div id="page-inner">
			Record type has invalid references within the Heurist database structure for Field Type Definitions. 
            <br><br>
            Please edit the definitions for these fields by clicking "Edit record type" link at top right of edit record form
            <br><br>
			<hr/>
<?php
if (count($dtysWithInvalidTerms)>0){
?>
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
						<td><?= $row['dty_ID'] ?></td>
						<td><?= $row['dty_Name'] ?></td>
						<td> a(n) "<?= $row['dty_Type'] ?>" field type definition contains <?= count($row['invalidTermIDs'])?> invalid term ID(s) <?= join(",",$row['invalidTermIDs'])?></td>
					</tr>
						<?php
						}//for
					}
					?>
			</table>
            <hr/>
<?php
}
if (count($dtysWithInvalidNonSelectableTerms)>0){
?>
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
                        <td><?= $row['dty_ID'] ?></td>
						<td><?= $row['dty_Name'] ?></td>
						<td> a(n) "<?= $row['dty_Type'] ?>" field type definition contains <?= count($row['invalidNonSelectableTermIDs'])?> invalid non selectable term ID(s) <?= join(",",$row['invalidNonSelectableTermIDs'])?></td>
					</tr>
						<?php
						}
					}
				?>
			</table>
            <hr/>
<?php
}
if (count($dtysWithInvalidRectypeConstraint)>0){
?>

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
                    <td><?= $row['dty_ID'] ?></td>
					<td><?= $row['dty_Name'] ?></td>
					<td> a(n) "<?= $row['dty_Type'] ?>" field type definition contains <?= count($row['invalidRectypeConstraint'])?> invalid rectype ID(s) <?= join(",",$row['invalidRectypeConstraint'])?></td>
				</tr>
						<?php
						}
					}
				?>
			</table>
			<hr/>
<?php
}
?>

		</div>
	</body>
</html>

