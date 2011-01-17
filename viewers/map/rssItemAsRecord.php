<?php

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
error_log(" in pseudo with id=$id");
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
