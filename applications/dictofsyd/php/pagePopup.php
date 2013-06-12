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
* Render popup page (for media items)
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

    if(!isset($record)){

	    $rec_id = @$_REQUEST['name'];

	    $record = null;

	    if(is_numeric($rec_id)){
            if(!$db_selected){
                $db_selected = mysql_connection_select();
            }
		    $record = getRecordFull($rec_id);
	    }

	    if(!$record){
		    echo "not found";
		    exit();
	    }
    }

	if($record->type()==RT_MEDIA || $record->type()==RT_TILEDIMAGE){
		$media_type = $record->getFeatureTypeName();
	}
?>
<div class="picbox-container">

			<div class="picbox-close">
				<a href="#" onclick="Boxy.get(this).hide(); return false;">[close]</a>
			</div>
			<div class="clearfix"></div>

<?php
		if($media_type=="image"){
			print '<div class="picbox-image">'.getImageTag($record, null, 'wide').'</div>';
		}
?>

			<div class="picbox-heading balloon-<?=$record->type_classname() ?>">
				<h2><?=$record->getDet(DT_NAME) ?></h2>
			</div>

			<div class="picbox-content">
<?php
	if($media_type=="audio" || $media_type=="video"){
		$url = getFileURL($record, "direct");
		if($url){
?>
					<div class="picbox-flash">
						<div id="<?=$media_type ?>">
							<a href="<?=$url ?>"></a>
						</div>
					</div>
<?php
		}
	}
	if($record->getDet(DT_DESCRIPTION)){
?>

				<p>
					<?=$record->getDet(DT_DESCRIPTION) ?>
				</p>
<?php
	}

    //    <a href="< =$record->id() >">full record &#187;</a>
?>

				<p class="attribution">
					<?=makeMediaAttributionStatement($record) ?>
				</p>

				<p>
					<?=($record->type()==RT_TILEDIMAGE)?'This is a high-resolution image - to view in more detail, go to the ':''?>
                    <?=getLinkTag3(RT_MEDIA, "noclass", "full record &#187;", $record->id() ) ?>
				</p>
				<div class="clearfix"></div>
			</div>
</div>
