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
* Render page for map record type.
*
* for entities map see utilsMake.php
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

function makeFullTimeMap(Record $record){

	$cnt_geo = 0;
	$cnt_time = 0;
	$datasets = "var datasets = [];";

	$rec_ids = $record->getDetails(DT_MAP_KML_REF);

	//kml files
	foreach ($rec_ids as $rec_id){
		$kml = $record->getRelationRecord($rec_id);
		if(!$kml){
			$kml = getRecordFull($rec_id);
			if(!$kml){
				continue;
			}
		}

		$datasets .= '
			datasets.push({
							title: "'.$kml->getDet(DT_NAME).'",
							type: "kml",
							options: {
								url: "'.getFileURL($kml).'"
							}
						});';
		$cnt_geo++;
	}



	$factoids = $record->getRelationRecordByType(RT_FACTOID, 'TimePlace');

	if($factoids){

		$rec_id = $record->id();

		$items = "";
		$cnt = 0;

		foreach ($factoids as $factoid) {

			$geo = $factoid->getDet(DT_GEO,'geo');
			$date_start = $factoid->getDet(DT_DATE_START);

			$entity_id = $factoid->getDet(DT_FACTOID_SOURCE);
			$entity = $record->getRelationRecord($entity_id);

			$entity_name = $entity->getDet(DT_NAME);
			$entity_type = $entity->type_classname();


			if($geo || $date_start){

				if($cnt>0){
					$items = $items.', ';
				}


				$item = "getTimeMapItem(".$entity_id.",'".
											$entity_type."',\"".
											$entity_name."\",'".
											$date_start."','".
											($factoid->getDet(DT_DATE_END)?$factoid->getDet(DT_DATE_END):date("Y"))."','".
											$factoid->getDet(DT_GEO)."','".  //shape type
											$geo."')";
											//"','".getPreviewDiv($record, true)."')";

//print $item."<br/>"; //" ".$entity_name.
				$items = $items.$item;

				if($geo){
					$cnt_geo++;
				}
				if($date_start){
					$cnt_time++;
				}
				$cnt++;
			}
		}

		if($cnt>0){
			$datasets = $datasets.'datasets.push({
							type: "basic",
							options: { items: ['.$items.'] }});';
		}
	}//factoids


	$rec_ids = $record->getDetails(DT_MAP_TILEDIMAGE_REF);
	$layers = "var layers = [];";

	//image layers files
	foreach ($rec_ids as $rec_id){
		$layer = $record->getRelationRecord($rec_id);
		if(!$layer){
			$layer = getRecordFull($rec_id);
			if(!$layer){
				continue;
			}
		}

		$layers .= 'layers.push({'.
							'title: "'.$layer->getDet(DT_NAME2).'",'.
							'type: "'.getTypeValue($layer->getDet(DT_TILEDIMAGE_SCHEME)).'",'.
							'url: "'.$layer->getDet(DT_TILEDIMAGE_URL).'",'.
							'mime_type: "'.getMimeTypeValue($layer->getDet(DT_TYPE_MIME)).'",'.
							'min_zoom: "'.$layer->getDet(DT_TILEDIMAGE_ZOOM_MIN).'",'.
							'max_zoom: "'.$layer->getDet(DT_TILEDIMAGE_ZOOM_MAX).'",'.
							'copyright: "'.$layer->getDet(DT_COPYRIGHT_STATEMENT).'"});';
		$cnt_geo++;
	}



	$description = $record->getDescription();
	if($description){
		$description = '<p>'.$description.'</p>';
	}else{
		$description = '';
	}
?>
<div>

				<?=$description  ?>

				<div id="map" class="full"></div>

				<div id="timeline-zoom" <?=($cnt_time>0)?'':'class="hide"' ?>>
				</div>
				<div id="timeline" class="entity-timeline<?=($cnt_time>0)?'':' hide' ?>">
				</div>

				<script type="text/javascript">
					<?php echo $layers; ?>
					<?php echo $datasets; ?>

				var mapdata = {
					<?php echo ($record->getDet(DT_GEO,'geo')?'focus: "'.$record->getDet(DT_GEO,'geo').'",':''); ?>
					timemap: datasets,
					layers: layers,
					count_mapobjects: <?=$cnt_geo ?>
				};

				setTimeout(function(){ initMapping("map", mapdata, false); }, 500);

				</script>
</div>
<?php
}
?>