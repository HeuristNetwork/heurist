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
/**
 *
 * Filename: class.geometry.php
 * Author:   Tobias Peirick
 * Description:
 * This class has been written to be a wrapper from the OpenGIS format,
 * that is the geometry format of MySQL.
 *
 * Information about the OpenGIS standard of MySQL can be found at
 * http://dev.mysql.com/doc/refman/5.0/en/spatial-extensions.html
 *
 * change history:
 * 2007-04-02 Tobias Peirick initial version
 *
 *
 */
class Geometry {
  var $type;
	var $geo;
	var $matrix;
	var $innerMatrices;
	var $circle;

	var $envelope;
	var $minX, $minY, $maxX, $maxY;

  /**
   *  Constructor
   *
   *  @param $geo the WKF (Well Known Format) OpenGIS string
   *  @param $type the type of the geometry object. can be
   *          pl = Polygon
   *          p  = Point
   *          r  = Rectangle
   *          c  = Circle
   */
	function Geometry ($geo, $type, $rect) {
		$this->geo = $geo;
		$this->type = $type;
		$this->envelope = $rect;
		$this->innerMatrices = null;
		$this->_process();
	}

	/**
	 * Break up the WKF (Well Known Format) geo string.
	 *
	 * @return void
	 */
	function _process(){
	  if (preg_match('/^POLYGON[(][(]([^ ]+) ([^,]+),[^ ]+ [^,]+,([^ ]+) ([^,]+),[^ ]+ [^)]+[)][)]/i', $this->envelope, $matches))
	  	list($_, $this->minX, $this->minY, $this->maxX, $this->maxY) = array_map('floatval', $matches);

	  switch($this->type) {
		 case 'pl':
		 		$this->innerMatrices = array();
      			if (preg_match('/POLYGON\\((.*)\\)/i', $this->geo, $matches)) {
					preg_match_all('/\\(([^()]+)\\)(?:,|$)/', $matches[1], $submatches, PREG_PATTERN_ORDER);
					for ($i = 0; $i < count($submatches[1]); ++$i) {
						preg_match_all('/(\\S+) (\\S+)(?:,|$)/', $submatches[1][$i], $matrix, PREG_PATTERN_ORDER);
						if ($i === 0) {
      						$this->matrix = $matrix;
						} else {
							array_push($this->innerMatrices, $matrix);
						}
					}
      			}
      			break;

		 case 'l':
      			if (preg_match('/LINESTRING\\((.*)\\)/i', $this->geo, $matches)) {
      				preg_match_all('/(\\S+) (\\S+)(?:,|$)/', $matches[1], $matches, PREG_PATTERN_ORDER);
      				$this->matrix = $matches;
      			}
      			break;

		case 'p':
      			if (preg_match('/POINT\\((.*) (.*)\\)/i', $this->geo, $matches)) {
      		    $this->matrix = $matches;
      			}
      			break;

		case 'r':
      			if (preg_match('/POLYGON\\(\\((.*)\\)\\)/i', $this->geo, $matches)) {
      				preg_match_all('/(\\S+) (\\S+)(?:,|$)/', $matches[1], $matches, PREG_PATTERN_ORDER);
      			 $this->matrix = $matches;
      			}
      			break;

		case 'c':
      			if (preg_match('/LINESTRING\\((.*)\\)/i', $this->geo, $matches)) {
      				preg_match_all('/(\\S+) (\\S+)(?:,|$)/', $matches[1], $matches, PREG_PATTERN_ORDER);
      			  $this->matrix = $matches;
      			}
      			break;
		}
  }

  /**
   *
   * @return the type of the geometry object. can be
   *          pl = Polygon
   *          p  = Point
   *          r  = Rectangle
   *          c  = Circle
   */
  function getType() {
    return $this->type;
  }


  /**
   * Returns the coordinates of a circle
   *
   * @return array with coordinates of the circle
   */
  function getCircle(){
    if($this->type == 'c'){

      $centerX = $this->matrix[1][0];
      $centerY = $this->matrix[2][0];

      $endX = $this->matrix[1][1];
      $endY = $this->matrix[2][1];

      $radius = $endX - $centerX;

      $this->circle = array();
      $this->circle[] = array();
      $this->circle[] = array();
      $this->circle[] = array();

      for($i=0; $i <= 40; $i += 10){
        $aRad = $i/40 * (2*pi());
        $y = $centerY + $radius * sin($aRad);
        $x = $centerX + $radius * cos($aRad);
        $this->circle[0][] = $x.' '.$y.',';
        $this->circle[1][] = $x;
        $this->circle[2][] = $y;
      }
      return $this->circle;
    }
  }


  /**
   *
   *  @return array with coordinates depending of the type
   */
  function getMatrix() {
    return $this->matrix;
  }

  function getInnerMatrices() {
    return $this->innerMatrices;
  }


}

?>
