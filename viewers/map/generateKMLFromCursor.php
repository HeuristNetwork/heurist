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
/**
 *
 * Filename: generateKMLFromCursor.php
 * Author:   Tobias Peirick
 * Description:
 * Generates a KML output
 *
 *
 * change history:
 * 2007-04-13 Tobias Peirick initial version
 *
 *
 */

require_once('class.record.php');
require_once('class.geometry.php');
require_once('class.searchCursor.php');


class KMLBuilder {
	var $search;
	var $kml;

	/**
	 *  Constructor
	 *
	 *  @param $search [class.searchCursor.php]
	 */
	function KMLBuilder($search) {
		$this->search = $search;
	}

	function build($multilevel) {
		$this->multilevel = $multilevel;

		$this->kml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"/>');

		$this->kml->Document->name = 'Heurist records';

		while($record = $this->search->fetch()) {

			if (! $record->hasGeometry()  &&  ! $record->start) {
				// only include records that have spatial or temporal data
				continue;
			}

			$elemPlacemark = $this->kml->Document->addChild('Placemark');
			$elemPlacemark->name = $record->rec_title;
			if ($record->description) {
				$elemPlacemark->description = $record->description;
			}

			$elemPlacemark->ExtendedData->Data['name'] = 'HeuristID';
			$elemPlacemark->ExtendedData->Data->value = $record->rec_id;

			if ($record->start) {
				$start = str_replace(" ", "T", $record->start);
				$start = preg_replace("/T([0-9]):/", "T0$1:", $start);
				$start = preg_replace("/(T[0-9]{2}:[0-9]{2})$/", "$1:00", $start);

				if ($record->end) {
					if ($record->end == $record->start) {
						$elemPlacemark->TimeStamp->when = $start;
					} else {
						$end = str_replace(" ", "T", $record->end);
						$end = preg_replace("/T([0-9]):/", "T0$1:", $end);
						$end = preg_replace("/(T[0-9]{2}:[0-9]{2})$/", "$1:00", $end);
						$elemPlacemark->TimeSpan->begin = $start;
						$elemPlacemark->TimeSpan->end = $end;
					}
				} else {
					$elemPlacemark->TimeSpan->begin = $start;
				}
			}

			if ($record->hasGeometry()) {
				$elemPlacemark->Style->LineStyle['color'] = 'ffff0000';
				$elemPlacemark->Style->LineStyle['width'] = '4';

				$elemMultiGeometry = $elemPlacemark->addChild('MultiGeometry');
				foreach($record->getGeometry() as $geo) {
					switch ($geo->getType()) {
					case 'l':
					case 'pl':
					case 'r':
						$matrix = $geo->getMatrix();
						$innerMatrices = $geo->getInnerMatrices();
						$lodLevel = 5;
						$prevCoordCount = 0;
						$closed = ($geo->getType() != 'l');
						if ($this->multilevel) {
							do {
								$rval =& $this->_addPolygon(
									$elemMultiGeometry,
									$record,
									$matrix,
									$closed,
									$lodLevel--,
									$innerMatrices
								);
								$newElt =& $rval[0];
								$coordCount = $rval[1];
								if ($prevCoordCount  &&  $coordCount / $prevCoordCount > 0.8) {
									// not many points eliminated -- don't add this level of placemark
									$prevElt->Region->Lod->minLodPixels = $newElt->Region->Lod->minLodPixels;
									// remove the element from the XML
									unset($rval[0]);
								} else {
									unset($prevElt);
									$prevElt =& $newElt;
									$prevCoordCount = $coordCount;
								}
							} while ($coordCount > 3  &&  $lodLevel > 0);
						} else {
							$this->_addPolygon(
								$elemMultiGeometry,
								$record,
								$matrix,
								$closed,
								0,
								$innerMatrices
							);
						}
						break;
					case 'p':
						$matrix = $geo->getMatrix();
						$this->_addPoint($elemMultiGeometry, $record, $matrix[1], $matrix[2]);
						break;
					case 'c':
						$matrix = $geo->getCircle();
						$this->_addPolygon($elemMultiGeometry, $record, $matrix, true);
						break;
					} // end switch
				} // end foreach
			} // end if ($record->hasGeometry())
		} // end while
	}


	function getResult() {
		return utf8_encode($this->kml->asXML());
	}



	function _addPolygon($parentElem, $record, $matrix, $closed = true, $lodLevel = 0, $innerMatrices = null) {
		list($coordinates, $minLon, $maxLon, $minLat, $maxLat, $coordCount, $prevLon, $prevLat) = $this->_processMatrix($matrix, $lodLevel);
		$innerCoords = array();
		if ($innerMatrices) {
			foreach ($innerMatrices as $innerMatrix) {
				list($coords, $_, $_, $_, $_, $_, $_) = $this->_processMatrix($innerMatrix, $lodLevel);
				array_push($innerCoords, $coords);
			}
		}

		if ($coordCount > 3) {	// multiple coordinates -- draw a polygon
			if ($closed) {
			error_log(print_r($parentElem, 1));
				$elem = $parentElem->addChild('Polygon');
				$elem->outerBoundaryIs->LinearRing->coordinates = $coordinates;
				foreach ($innerCoords as $coords) {
					$elem->innerBoundaryIs->LinearRing->coordinates = $coords;
				}
			}
			else {
				$elem = $parentElem->addChild('LineString');
				$elem->coordinates = $coordinates;
			}
		}
		else {	// very few vertices -- draw a single point
			$elem = $parentElem->addChild('Point');
			$elem->coordinates = round($prevLon, 5) . ',' . round($prevLat , 5) . ',0';
		}

		if ($lodLevel) {
			$elem->Region->Lod->minLodPixels = pow(4, $lodLevel + 1);
			if ($lodLevel < 5) {
				$elem->Region->Lod->maxLodPixels = pow(4, $lodLevel + 2);
			}
			$elem->Region->LatLonAltBox->north = $maxLat;
			$elem->Region->LatLonAltBox->south = $minLat;
			$elem->Region->LatLonAltBox->east = $maxLon;
			$elem->Region->LatLonAltBox->west = $minLon;
		}

		if ($closed) {
			$colors = array('44ff0000', '4400ff00', '44ff0000', '440000ff', '44ffff00', '44ff00ff');
			$elem->Style->PolyStyle->color = $colors[$lodLevel];
			$elem->Style->PolyStyle->fill = '1';
			$elem->Style->PolyStyle->outline = '1';
		}

		if (! $lodLevel) {
			return $elem;
		} else {
			return array($elem, $coordCount);
		}
	}


	function _processMatrix($matrix, $lodLevel) {
		$coordinates = '';
		$coordCount = 0;
		$prevLon = 99999;
		$prevLat = 99999;
		$minLon = $minLat = 99999;
		$maxLon = $maxLat = -99999;
		$lodDiff = pow(0.1, $lodLevel);
		for ($i = 0; $i < count($matrix[1]); $i++) {
			if (! $lodLevel) {
				$coordinates .= round($matrix[1][$i],5).','.round($matrix[2][$i],5).',0 ';
				++$coordCount;
				$prevLon = $matrix[1][$i];
				$prevLat = $matrix[2][$i];
			}
			else {
				if (abs($prevLon - $matrix[1][$i]) >= $lodDiff  ||  abs($prevLat - $matrix[2][$i]) >= $lodDiff) {
					$coordinates .= round($matrix[1][$i],5).','.round($matrix[2][$i],5).',0 ';
					++$coordCount;
					$prevLon = $matrix[1][$i];
					$prevLat = $matrix[2][$i];
				}
				$minLon = min($matrix[1][$i], $minLon);
				$maxLon = max($matrix[1][$i], $maxLon);
				$minLat = min($matrix[2][$i], $minLat);
				$maxLat = max($matrix[2][$i], $maxLat);
			}
		}
		return array($coordinates, $minLon, $maxLon, $minLat, $maxLat, $coordCount, $prevLon, $prevLat);
	}


	function _addPoint($elem, $record, $x, $y) {
		$point = $elem->addChild("Point");
		$point->Style->IconStyle->Icon['href'] = '../../common/images/rectype-icons/'.$record->rec_RecTypeID.'.png';
		$point->Style->IconStyle->Icon['w'] = '26';
		$point->Style->IconStyle->Icon['h'] = '26';
		$point->coordinates = round($x, 5) . ',' . round($y, 5) . ',0';
	}
}
?>
