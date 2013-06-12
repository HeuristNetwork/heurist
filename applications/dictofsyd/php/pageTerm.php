<div id="subject-list">
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
* Render list of related records
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

	$val = $record->getDet(DT_DESCRIPTION);
	if($val){
		print '<p>'.$val.'</p>';
	}

	makeSubjectItem( $record->getRelationRecordByType(RT_ENTRY) );

	makeSubjectItem( $record->getRelationRecordByType(RT_ENTITY,'place') );
	makeSubjectItem( $record->getRelationRecordByType(RT_ENTITY,'person') );
	makeSubjectItem( $record->getRelationRecordByType(RT_ENTITY,'artefact') );
	makeSubjectItem( $record->getRelationRecordByType(RT_ENTITY,'building') );
	makeSubjectItem( $record->getRelationRecordByType(RT_ENTITY,'event') );
	makeSubjectItem( $record->getRelationRecordByType(RT_ENTITY,'natural') );
	makeSubjectItem( $record->getRelationRecordByType(RT_ENTITY,'organisation') );
	makeSubjectItem( $record->getRelationRecordByType(RT_ENTITY,'structure') );

	makeSubjectItem( $record->getRelationRecordByType(RT_MEDIA, 'image') );
	makeSubjectItem( $record->getRelationRecordByType(RT_TILEDIMAGE, 'image') );
	makeSubjectItem( $record->getRelationRecordByType(RT_MEDIA, 'audio') );
	makeSubjectItem( $record->getRelationRecordByType(RT_MEDIA, 'video') );

	makeSubjectItem( $record->getRelationRecordByType(RT_MAP) );

	//makeSubjectItem($record->getRelationRecordByType(RT_TERM));
?>
</div>
