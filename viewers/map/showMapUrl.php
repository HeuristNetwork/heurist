<?php

	/**
	* showMap.php
	*
	* search records and fills the structure to display on map
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

	$mapobjects = getMapObjects($_REQUEST);

	if($mapobjects['cntWithGeo']>0){

		if(@$_REQUEST['width'] && @$_REQUEST['height']){
			$size = $_REQUEST['width']."x".$_REQUEST['height'];
		}else{
			$size = "170x170";
		}

		$url = "http://maps.google.com/maps/api/staticmap";
		$url = $url."?size=".$size."&sensor=false&maptype=terrain";
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
					$path_all = $path_all."&path=weight:3|color:red|".$path;
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
					$poly_all = $poly_all."&path=weight:3|color:red|fillcolor:0x0000ff40|".$poly.$firstpoint;
				}

			}else if($geoObject['type']=="rect"){

				$crd = $geoObject['geo'];
				$poly_all = $poly_all."&path=weight:3|color:red|fillcolor:0x0000ff40|";
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
			$markers = "&markers=".$markers;
			$url = $url.$markers;
		}
		if($path_all!=""){         //0xff00007f
			$url = $url.$path_all;
		}
		if($poly_all!=""){
			$url = $url.$poly_all;
		}

error_log(">>>>>".$url);
		header('Location: '.$url);
		//return $url;
	}else{
		header('Location: '.$url);
		//return ""; //@todo reference to empty image with warning message
	}
?>