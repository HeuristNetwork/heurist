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



	/**
	* showMapUrl.php
	*
	* return static google map image with feature either by record Id or by geovalue
	*
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
	require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
	require_once(dirname(__FILE__)."/../../viewers/map/showMapRequest.php");

	$mapobjects = null;

	if(@$_REQUEST['value'] && $_REQUEST['value']!='undefined'){

		$val = $_REQUEST['value'];
		$type = substr($val,0,strpos($val,' '));
		$geoval = substr($val,strpos($val,' ')+1);

		$geoobj = parseValueFromDb(0, $type, $geoval, null);

		if($geoobj){
			$mapobjects = array("cntWithGeo"=>1, "geoObjects"=>array($geoobj) );
		}

	}else{
		$mapobjects = getMapObjects($_REQUEST);
	}

	if($mapobjects && $mapobjects['cntWithGeo']>0){

		if(@$_REQUEST['width'] && @$_REQUEST['height']){
			$size = $_REQUEST['width']."x".$_REQUEST['height'];
		}else{
			$size = "200x200";
		}

		$url = "http://maps.google.com/maps/api/staticmap";
		$url = $url."?size=".$size."&sensor=false";

		if(@$_REQUEST['zoom'] && is_numeric($_REQUEST['zoom'])){
			$url = $url."&zoom=".$_REQUEST['zoom'];
		}
		if(@$_REQUEST['maptype']){
			$url = $url."&maptype=".$_REQUEST['maptype'];
		}else{
			$url = $url."&maptype=terrain";
		}
		if(@$_REQUEST['m_style']){
			$style_marker = $_REQUEST['m_style'];
		}else{
			$style_marker = "color:red";
		}
		if(@$_REQUEST['pl_style']){
			$style_path = $_REQUEST['pl_style'];
		}else{
			$style_path = "weight:3|color:red";
		}
		if(@$_REQUEST['pg_style']){
			$style_poly = $_REQUEST['pg_style'];
		}else{
			$style_poly = "weight:3|color:red|fillcolor:0x0000ff40";
		}

		//$url = $url."&key=ABQIAAAAGZugEZOePOFa_Kc5QZ0UQRQUeYPJPN0iHdI_mpOIQDTyJGt-ARSOyMjfz0UjulQTRjpuNpjk72vQ3w";

		$markers = "";
		$path_all = "";
		$poly_all = "";

		foreach ($mapobjects['geoObjects'] as $geoObject) {

			if($geoObject['type']=="point"){
				$markers = $markers.$geoObject['geo']['y'].",".$geoObject['geo']['x']."|";
			}else if($geoObject['type']=="polyline"){

				$path = "";
				foreach ($geoObject['geo']['points'] as $point) {
					if($path!="") $path = $path."|";
					$path = $path.$point['y'].",".$point['x'];
				}
				if($path!=""){
					$path_all = $path_all."&path=".$style_path."|".$path;
				}

			}else if($geoObject['type']=="polygon"){

				$poly = "";
				$firstpoint = "";
				foreach ($geoObject['geo']['points'] as $point) {
					if($poly=="") {
						$firstpoint = $point['y'].",".$point['x'];
					}
					$poly = $poly.$point['y'].",".$point['x']."|";
				}
				if($poly!=""){
					$poly_all = $poly_all."&path=".$style_poly."|".$poly.$firstpoint;
				}

			}else if($geoObject['type']=="rect"){

				$crd = $geoObject['geo'];
				$poly_all = $poly_all."&path=".$style_poly."|";
				$poly_all = $poly_all.$crd['y0'].",".$crd['x0']."|";
				$poly_all = $poly_all.$crd['y0'].",".$crd['x1']."|";
				$poly_all = $poly_all.$crd['y1'].",".$crd['x1']."|";
				$poly_all = $poly_all.$crd['y1'].",".$crd['x0']."|";
				$poly_all = $poly_all.$crd['y0'].",".$crd['x0'];

			}else if($geoObject['type']=="circle"){
//"geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2]), "radius" => floatval($matches[3] - $matches[1])
				$markers = $markers.$geoObject['geo']['y'].",".$geoObject['geo']['x']."|";
			}
		}

		if($markers!=""){
			$markers = "&markers=".$style_marker."|".$markers;
			$url = $url.$markers;
		}
		if($path_all!=""){         //0xff00007f
			$url = $url.$path_all;
		}
		if($poly_all!=""){
			$url = $url.$poly_all;
		}

/*****DEBUG****///error_log(">>>>>".$url);
		header('Location: '.$url);
		//return $url;
	}else{
		header('Location: '.HEURIST_SITE_PATH.'common/images/notfound.png');
		//print "noting found";
	}
?>