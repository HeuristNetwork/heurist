<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* return static google map image with feature either by record Id or by geovalue
* 
* 2018-04-24 rewritten for H4. It uses geoPHP and Simplify to prepare coordinates for encoding
* @todo - move to other mapping code into separate folder
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Viewers/Map
* @deprecated
*/
    require_once (dirname(__FILE__).'/../../hserver/System.php');
    require_once (dirname(__FILE__).'/../../hserver/dbaccess/db_recsearch.php');
    
    require_once(dirname(__FILE__).'/../../external/geoPHP/geoPHP.inc');
    require_once(dirname(__FILE__)."/encodePolyline.php");
    require_once(dirname(__FILE__)."/Simplify.php");

	$mapobjects = array();
    
    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        //$response = $system->getError();
        header('Location: '.HEURIST_BASE_URL.'common/images/notfound.png');
        exit();
    }
    
	if(false && @$_REQUEST['value'] && $_REQUEST['value']!='undefined'){

		$val = $_REQUEST['value'];
		$type = substr($val,0,strpos($val,' '));
		$geoval = substr($val,strpos($val,' ')+1);

		$geoobj = parseValueFromDb(0, $type, $geoval, null);
        
		if($geoobj){
			$mapobjects = array("cntWithGeo"=>1, "geoObjects"=>array($geoobj) );
		}

	}else{
		//$mapobjects = getMapObjects($_REQUEST);
        
        
        if(@$_REQUEST['id'] && !$_REQUEST['q']){
            $_REQUEST['q'] = 'ids:'.$_REQUEST['id'];    
        }
        $_REQUEST['w'] = 'e';
        $_REQUEST['detail'] = 'timemap';
        //retrieve records
        $response = recordSearch($system, $_REQUEST, true, true);
        
        if($response && $response['status'] == HEURIST_OK && $response['data'] && $response['data']['count']>0){
             
            $records = $response['data']['records'];
            foreach($records as $recID => $record){
                if($record['d'] && $record['d'][28])
                foreach($record['d'][28] as $as_wkt){
                    //extract type
                    $k = strpos($as_wkt, ' ');
                    $geo_type = substr($as_wkt, 0, $k);
                    $as_wkt = substr($as_wkt, $k+1);
                    
                    switch ($geo_type) {
                    
                        case "r":
                            if (preg_match("/POLYGON\s?\(\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*\\S+\\s+\\S+\\)\\)/i", $as_wkt, $matches)) {
                                
                                array_push($mapobjects, array("type" => "rect",
                                    "geo" => array("x0" => floatval($matches[1]), "y0" => floatval($matches[2]), 
                                            "x1" => floatval($matches[5]), "y1" => floatval($matches[6]))));
                                    
                            }
                            break;

                        case "c":
                            if (preg_match("/LINESTRING\s?\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+\\S+,\\s*\\S+\\s+\\S+,\\s*\\S+\\s+\\S+\\)/i", $as_wkt, $matches)) {
                                
                                array_push($mapobjects, array("type" => "circle",
                                "geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2]), 
                                            "radius" => floatval($matches[3] - $matches[1]))));
                            }
                            break;

                        default:                        
                        
                            //'geoObjects' => array() $geoObject['geo']['points']
                            //$geoObject['type']
                        
                            //geometryType()
                            //getGeos()
                        
                            $geom = geoPHP::load($as_wkt, 'wkt');
                            if(!$geom->isEmpty()){
                               
                               $geojson_adapter = new GeoJSON(); 
                               $json = $geojson_adapter->write($geom, true); 
                               
                               ///$json = $geom->out('json'); 
                               // $geom->numGeometries()
                               if(count($json['coordinates'])>0){
                                   if($json['type']=='Polygon'){
                                       foreach($json['coordinates'] as $points){
                                           array_push($mapobjects, array('type'=>$geo_type, 'geo'=>$points));
                                       }
                                   }else if ($json['type']=='MultiPolygon' 
                                                || $json['type']=='MultiLineString' 
                                                || $json['type']=='MultiPoint'){
                                       foreach($json['coordinates'] as $shape){
                                           foreach($shape as $points){
                                                array_push($mapobjects, array('type'=>$geo_type, 'geo'=>$points));
                                           }
                                       }
                                   }else{
                                       array_push($mapobjects, array('type'=>$geo_type, 'geo'=>$json['coordinates']));
                                   }
                               }
                               
                               // array_push($points, array(floatval($point['y']), floatval($point['x'])) );
                            }
                    }//switch
                    
                }//foreach multi values
            }//foreach
        }
	}

	if(count($mapobjects)>0){
        
		if(@$_REQUEST['width'] && @$_REQUEST['height']){
			$size = $_REQUEST['width']."x".$_REQUEST['height'];
		}else{
			$size = "300x300";
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

		foreach ($mapobjects as $geoObject) {

			if($geoObject['type']=="point" || $geoObject['type']=="p"){
				$markers = $markers.$geoObject['geo'][1].",".$geoObject['geo'][0]."|";
            }
            else if($geoObject['type']=="rect"){

                $crd = $geoObject['geo'];
                $poly_all = $poly_all."&path=".$style_poly."|";
                $poly_all = $poly_all.$crd['y0'].",".$crd['x0']."|";
                $poly_all = $poly_all.$crd['y0'].",".$crd['x1']."|";
                $poly_all = $poly_all.$crd['y1'].",".$crd['x1']."|";
                $poly_all = $poly_all.$crd['y1'].",".$crd['x0']."|";
                $poly_all = $poly_all.$crd['y0'].",".$crd['x0'];

            }else 
            if($geoObject['type']=="circle"){
                $markers = $markers.$geoObject['geo']['y'].",".$geoObject['geo']['x']."|";
            }else
			//if($geoObject['type']=="polyline" || $geoObject['type']=="l")
            //if($geoObject['type']=="polygon" || $geoObject['type']=="pl")
            {

                    $points = array();
                    $points2 = $geoObject['geo'];
                    $points_to_encode = array();

                    if(count($points2)>1000){
                    
                        $points = array();    
                        foreach ($geoObject['geo'] as $point) {
                            array_push($points, array('y'=>$point[1], 'x'=>$point[0]));
                        }
                        
                        $tolerance = 0.01;// 0.002;
                        $crn = 0;
                        $points2 = $points;
                        while(count($points2)>1000 && $crn<4){
                            $points2 = Simplify::run($points, $tolerance);
                            $tolerance = $tolerance + 0.002;
                            $crn++;
                        }//while simplify

                        if(count($points2)<=1000)
                        foreach ($points2 as $point) {
                            array_push($points_to_encode, array($point['y'], $point['x']) );
                        }
                        
                    }else{
                        
                        foreach ($points2 as $point) {
                            array_push($points_to_encode, array($point[1], $point[0]) );
                        }
                    }
                    
                    if(count($points_to_encode)>1){
                        
                        if($geoObject['type']=="polygon" || $geoObject['type']=="pl"){
                            array_push($points_to_encode, $points_to_encode[0]); //add last as first
                        }

                        $encodedPoints = dpEncode($points_to_encode);

                        if($geoObject['type']=="polygon" || $geoObject['type']=="pl"){
                            $poly_all = $poly_all."&path=".$style_poly."|enc:".$encodedPoints[0];
                            if(strlen($poly_all)>1900){
                                break; //total length of url is too long
                            }
                        }else{
                            $path_all = $path_all."&path=".$style_path."|enc:".$encodedPoints[0];        
                            if(strlen($path_all)>1900){
                                break; //total length of url is too long
                            }
                        }
                        
                    }

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

        if(@$_REQUEST['debug']){
            print '<div>'.strlen($url)."</div>";
            print $url;
        }else{
		    header('Location: '.$url);
        }
		//return $url;
	}else{
		header('Location: '.HEURIST_BASE_URL.'common/images/notfound.png');
		//print "noting found";
	}
?>