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
* Render preview page
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

	$rec_id = @$_REQUEST['name'];
    $rec_id_annotation = null;

//debug error_log("GEN PREVIEW=".$rec_id);
    
    if(strpos($rec_id,"A")>0){
        $recs = explode("A",$rec_id);
        $rec_id = $recs[0];
        $rec_id_annotation = $recs[1];
    }
    
	$record = null;
    $record_annotation = null;

	if(is_numeric($rec_id)){
        if(!$db_selected){
            $db_selected = mysql_connection_select();
        }
		$record = getRecordFull($rec_id);


        if(is_numeric($rec_id_annotation)){
            $record_annotation = getRecordFull($rec_id_annotation);
        }
    }

	if(!$record){
		//echo "not found"; //TODO
        add_error_log("ERROR >>>> Can not create PREVIEW. Record not found: ".$rec_id);
		exit();
	}

//error_log(">>>>>".$rec_id);

	echo makePreviewDiv($record, $record_annotation);
