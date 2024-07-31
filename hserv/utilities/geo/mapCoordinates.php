<?php

    /**
    * Geo library - working with wkt and geojson coordinates
    *               it uses Simplify and GpointConverter
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    
    /*
    * Simplify and converts from UTM to WGS
    * 
    * $utm_zone = number of zone + hemisphere  40N or 20S
    */
    function geo_SimplifyAndConvert_JSON($json, $need_simplify, &$gPoint=null){
        
        if($json['type']=='GeometryCollection'){
            
            for($idx=0; $idx<count($json['geometries']); $idx++){
                $json['geometries'][$idx] = geo_SimplifyAndConvert_JSON($json['geometries'][$idx], $need_simplify, $gPoint);
            }
            
            return $json;
            
            
        }else if(count($json['coordinates'])>0){

            if($gPoint && ($json['type']=='Point')){
                
                $pnt = array($json['coordinates']);
                geo_SimplifyAndConvert($pnt, false, $gPoint);
                $json['coordinates'] = $pnt[0];
                
            }else if($gPoint && ($json['type']=='MultiPoint')){
                
                geo_SimplifyAndConvert($json['coordinates'], false, $gPoint);
                
            }else if($json['type']=='LineString'){

                geo_SimplifyAndConvert($json['coordinates'], $need_simplify, $gPoint);

            } else if($json['type']=='Polygon'){
                for($idx=0; $idx<count($json['coordinates']); $idx++){
                    geo_SimplifyAndConvert($json['coordinates'][$idx], $need_simplify, $gPoint);
                }
            } else if ( $json['type']=='MultiPolygon' || $json['type']=='MultiLineString')
            {
                for($idx=0; $idx<count($json['coordinates']); $idx++) //shapes
                    for($idx2=0; $idx2<count($json['coordinates'][$idx]); $idx2++) //points
                        geo_SimplifyAndConvert($json['coordinates'][$idx][$idx2], $need_simplify, $gPoint);
            }

            return $json;
        }else{
            return array();
        }
        
    }
    
    //
    // simplify and/or convert
    //
    function geo_SimplifyAndConvert(&$orig_points, $need_simplify, &$gPoint=null){
        
        if($need_simplify && count($orig_points)>1000){
            
            //invert
            $points = array();
            foreach ($orig_points as $point) {
                if($gPoint!=null){
                    $gPoint->setUTM($point[0], $point[1]);
                    $gPoint->convertTMtoLL();
                    array_push($points, array('y'=>$gPoint->Lat(), 'x'=>$gPoint->Long()));
                }else{
                    array_push($points, array('y'=>$point[1], 'x'=>$point[0]));
                }
            }
            
            $tolerance = 0.01;// 0.002;
            $crn = 0; //count of run
            $points2 = $points;
            while(count($points2)>1000 && $crn<4){
                $points2 = Simplify::run($points, $tolerance);
                $tolerance = $tolerance + 0.002;
                $crn++;
            }//while simplify

            if(count($points2)<=1000){ //result
                $orig_points = array();
                foreach ($points2 as $point) {
                    array_push($orig_points, array($point['x'], $point['y']) );
                }
            }
            
        }else if($gPoint!=null){
            
            foreach ($orig_points as $idx=>$point) {
                $gPoint->setUTM($point[0], $point[1]);
                $gPoint->convertTMtoLL();
                $orig_points[$idx] = array($gPoint->Long(), $gPoint->Lat());
            }
        }
    }
    
    
    /*
    * Correct wrong longitude values: abs(lng)>180
    */
    function geo_CorrectLng_JSON($json){
        
        if($json['type']=='GeometryCollection'){
            
            for($idx=0; $idx<count($json['geometries']); $idx++){
                $json['geometries'][$idx] = geo_CorrectLng_JSON($json['geometries'][$idx]);
            }
            
            return $json;
            
            
        }else if(count($json['coordinates'])>0){

            if($json['type']=='Point'){
                
                $pnt = array($json['coordinates']);
                geo_CorrectLng($pnt);
                $json['coordinates'] = $pnt[0];
                
            }else if($json['type']=='MultiPoint'){
                
                geo_CorrectLng($json['coordinates']);
                
            }else if($json['type']=='LineString'){

                geo_CorrectLng($json['coordinates']);

            } else if($json['type']=='Polygon'){
                for($idx=0; $idx<count($json['coordinates']); $idx++){
                    geo_CorrectLng($json['coordinates'][$idx]);
                }
            } else if ( $json['type']=='MultiPolygon' || $json['type']=='MultiLineString')
            {
                for($idx=0; $idx<count($json['coordinates']); $idx++) //shapes
                    for($idx2=0; $idx2<count($json['coordinates'][$idx]); $idx2++) //points
                        geo_CorrectLng($json['coordinates'][$idx][$idx2]);
            }

            return $json;
        }else{
            return array();
        }
        
    }
    
    function geo_CorrectLng(&$orig_points){

        //invert
        $points = array();
        foreach ($orig_points as $idx => $point) {

            $lng = $point[0];
            $lng2 = $point[0];
            
            $k = intdiv($lng, 360);
            
            $lng = ($lng - $k*360);
            
            if(abs($lng)>180){
                if($k==0) $k = ($lng<0)?-1:1;
                $lng = $lng - $k*360;
            }
            
            //-181 => 179
            //182 => -178
            //  -478.4214470 => -118.4215610,
            //  491.8502830 => 131.8501210
            //  204.6147740 => -155.4933140
            // -574 => 145

            $orig_points[$idx] = array($lng, $point['1']);
        }
    }    
    
?>
