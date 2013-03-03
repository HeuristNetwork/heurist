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

?>

<?php

// ARTEM: TO REMOVE

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

require_once('class.geometry.php');

class Biblio {
	var $rec_id;
	var $rec_title;
	var $rec_url;
	var $rec_type;
	var $depth;
	var $start;
	var $end;
	var $geometry;
	var $description;

	var $minX, $minY, $maxX, $maxY;

	function Biblio ($rec_id) {
		$this->geometry  = array();

		$this->minX = null; $this->minY = null; $this->maxX = null; $this->maxY = null;

		if ($rec_id) {
			$res = mysql_query('select rec_ID, rec_Title, rec_URL, rec_RecTypeID from Records where rec_ID='.$rec_id);
			$row = mysql_fetch_assoc($res);
			if (!$row) return false;
			$this->rec_id = $row['rec_ID'];
			$this->rec_title = htmlentities($row['rec_Title']);
			$this->rec_URL = htmlentities($row['rec_URL']);
			$this->rec_RecTypeID = $row['rec_RecTypeID'];

			$details = mysql__select_assoc('recDetails', 'dtl_DetailTypeID', 'dtl_Value', 'dtl_RecID='.$rec_id);

			if (DT_START_DATE && array_key_exists(DT_START_DATE, $details)) {
				$this->start = $details[DT_START_DATE];
			}
			if (DT_END_DATE && array_key_exists(DT_END_DATE, $details)) {
				$this->end   = $details[DT_END_DATE];
			}

			$thumb_url = null;
			// 223  Thumbnail
			// 222  Logo image
			// 224  Images
			$res = mysql_query("select recUploadedFiles.*
								  from recDetails
							 left join recUploadedFiles on ulf_ID = dtl_UploadedFileID
								 where dtl_RecID = $rec_id
								   and dtl_DetailTypeID in(".(defined('DT_THUMBNAIL')?DT_THUMBNAIL:"0").",".
															(defined('DT_LOGO_IMAGE')?DT_LOGO_IMAGE:"0").",".
															(defined('DT_IMAGES')?DT_IMAGES:"0").",".
															(defined('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:"0").",".
															(defined('DT_OTHER_FILE')?DT_OTHER_FILE:"0").")".
								  " and file_mimetype like 'image%'
							  order by ".(defined('DT_THUMBNAIL')?"dtl_DetailTypeID = ".DT_THUMBNAIL." desc, ":"").
										(defined('DT_LOGO_IMAGE')?"dtl_DetailTypeID = ".DT_LOGO_IMAGE." desc, ":"").
										(defined('DT_IMAGES')?"dtl_DetailTypeID = ".DT_IMAGES." desc, ":"").
										"dtl_DetailTypeID limit 1");
			if (mysql_num_rows($res) !== 1) {
				$res = mysql_query("select recUploadedFiles.*
				                      from recDetails a, defDetailTypes, Records, recDetails b, recUploadedFiles
				                     where a.dtl_RecID = $rec_id
				                       and a.dtl_DetailTypeID = dty_ID
				                       and dty_Type = 'resource'
				                       and rec_ID = a.dtl_Value
				                       and b.dtl_RecID = rec_ID
				                       and ulf_ID = b.dtl_UploadedFileID
				                       and file_mimetype like 'image%'
				                     limit 1;");
			}
			if (mysql_num_rows($res) == 1) {
				$file = mysql_fetch_assoc($res);
				$thumb_url = "../../common/php/resizeImage.php?ulf_ID=".$file['ulf_ObfuscatedFileID'];
			}

			$text = DT_EXTENDED_DESCRIPTION && @$details[DT_EXTENDED_DESCRIPTION] ? ''.DT_EXTENDED_DESCRIPTION : (DT_SHORT_SUMMARY && @$details[DT_SHORT_SUMMARY] ? ''.DT_SHORT_SUMMARY : null);
			$this->description = "";
			if ($thumb_url) {
				$this->description .= "<img" . ($text ? " style='float: right;'" : "") . " src='" . $thumb_url . "'/>";
			}
			if ($text) {
				$this->description .= $details[$text];
			}

			$res = mysql_query('SELECT AsText(dtl_Geo) geo, dtl_Value, astext(envelope(dtl_Geo)) as rect
			                      FROM recDetails
			                     WHERE NOT IsNULL(dtl_Geo)
			                       AND dtl_RecID = ' . $rec_id);

			if (mysql_num_rows($res) < 1  &&  (!defined('RT_RELATION') || $this->rec_RecTypeID != RT_RELATION)  &&  DT_START_DATE && $details[DT_START_DATE]) {
				// Special case behaviour!
				// If a record has time data but not spatial data,
				// and it points to record(s) with spatial data, use that.
				// Although this is written in a general fashion it was
				// created for Event records, which may point to Site records
				$res = mysql_query('select astext(g.dtl_Geo) geo, g.dtl_Value, astext(envelope(g.dtl_Geo)) as rect
				                      from recDetails p
				                 left join defDetailTypes on dty_ID = p.dtl_DetailTypeID
				                 left join Records on rec_ID = p.dtl_Value
				                 left join recDetails g on g.dtl_RecID = rec_ID
				                     where p.dtl_RecID = ' . $rec_id . '
				                       and dty_Type = "resource"
				                       and g.dtl_Geo is not null');
			}

			while ($row = mysql_fetch_assoc($res)) {
				$geometry = new Geometry($row['geo'], $row['dtl_Value'], $row['rect']);
				if ($this->minX === null  ||  $geometry->minX < $this->minX) $this->minX = $geometry->minX;
				if ($this->maxX === null  ||  $geometry->maxX > $this->maxX) $this->maxX = $geometry->maxX;
				if ($this->minY === null  ||  $geometry->minY < $this->minY) $this->minY = $geometry->minY;
				if ($this->maxY === null  ||  $geometry->maxY > $this->maxY) $this->maxY = $geometry->maxY;

				$this->geometry[] = $geometry;
			}
		}
	}

	function hasGeometry() {
		return count($this->geometry) > 0;
	}

	function getGeometry() {
		return $this->geometry;
	}

}

?>
