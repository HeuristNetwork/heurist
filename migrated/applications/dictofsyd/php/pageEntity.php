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
* Render Entity page
*
* It loads wml file (specified in detail) and applies 2 xsl
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
?>
<div class="list-left-col"></div>
<div class="list-right-col">
<?php
	$val = $record->getDescription();
	if($val){
?>

	<div class="list-right-col-content">
		<div class="entity-content">
			<p>
				<?=$val ?>
			</p>
		</div>
	</div>

<?php
	}
	//main media reference
	$main_img_id = $record->getDet(DT_MEDIA_REF);
	if($main_img_id){
		$rec = $record->getRelationRecord($main_img_id);

		if(!$rec){
			$rec = getRecordFull($main_img_id);
			if($rec){
				$record->addRelation($rec);
			}
		}

		if($rec){
			print getImageTag($rec, 'entity-picture', 'medium');
		}
	}

	makeTimeMap($record);

?>
<div class="clearfix"></div>
<?php
	//factoid groups
	makeFactoids($record);
?>
</div>
<div class="clearfix"></div>
<?php
	makeEntriesInfo($record);

	makeLinksInfo($record);

	makeImageGallery($record);

	makeAudioVideoGallery($record, 'audio');

	makeAudioVideoGallery($record, 'video');
?>