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
* Simple import for RSS
*
* @author      Kim Jackson
* @author      Stephen White   
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Viewers/Map
*/


require_once(dirname(__FILE__).'/class.geometry.php');

class PseudoRssBiblio {
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

	function PseudoRssBiblio ($item,$id) {
		$this->geometry  = array();
		$this->minX = null; $this->minY = null; $this->maxX = null; $this->maxY = null;

		if ($item && $item instanceof SimpleXMLElement) {
			$this->rec_id = $id;
			$this->rec_Title = (@$item->title ? $item->title : "unknown title");
			$this->rec_URL = (@$item->url ? htmlentities($item->url) : (@$item->guid ? htmlentities($item->guid) :null)); //FIXME add code to check for http:
			$this->rec_RecTypeID = "pseudo";
//			$this->description = (@$item->description ? $item->description : null);

			$geo = @$item->xpath('georss:point');
			if ($geo && is_array($geo) && $geo[0]) {
				$geo = $geo[0];
				list($lat,$lng) = split(" ",$geo,2);
				$geometry = new Geometry('POINT('.$lng.' '.$lat.')', 'p', null);
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
