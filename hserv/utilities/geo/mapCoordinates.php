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

    /**
     * Simplifies and converts GeoJSON from UTM to WGS84 coordinates.
     * 
     * @param array   $json          The GeoJSON to process
     * @param boolean $need_simplify Flag indicating whether to simplify the geometry
     * @param object  $gPoint        Reference to the geographic point object for conversion
     * 
     * @return array                 The processed GeoJSON
     */
    function geoSimplifyAndConvertJSON($json, $need_simplify, &$gPoint = null)
    {
        // Process GeometryCollection recursively
        if ($json['type'] == 'GeometryCollection') {
            foreach ($json['geometries'] as $idx => $geometry) {
                $json['geometries'][$idx] = geoSimplifyAndConvertJSON($geometry, $need_simplify, $gPoint);
            }
            return $json;
        }

        // Return empty array if no coordinates are present
        if (empty($json['coordinates'])) {
            return array();
        }

        // Process individual geometry types
        if ($gPoint && $json['type'] == 'Point') {
            // Convert a single point
            $point = array($json['coordinates']);
            geoSimplifyAndConvert($point, false, $gPoint);
            $json['coordinates'] = $point[0];

        } elseif ($gPoint && $json['type'] == 'MultiPoint') {
            // Convert multiple points
            geoSimplifyAndConvert($json['coordinates'], false, $gPoint);

        } elseif ($json['type'] == 'LineString') {
            // Convert a line string
            geoSimplifyAndConvert($json['coordinates'], $need_simplify, $gPoint);

        } elseif ($json['type'] == 'Polygon') {
            // Convert polygons (outer and inner rings)
            foreach ($json['coordinates'] as $idx => $ring) {
                geoSimplifyAndConvert($ring, $need_simplify, $gPoint);
                $json['coordinates'][$idx] = $ring;
            }

        } elseif ($json['type'] == 'MultiPolygon' || $json['type'] == 'MultiLineString') {
            // Convert multi-polygons or multi-line strings
            foreach ($json['coordinates'] as $idx => $shape) {
                foreach ($shape as $idx2 => $points) {
                    geoSimplifyAndConvert($points, $need_simplify, $gPoint);
                    $json['coordinates'][$idx][$idx2] = $points;
                }
            }
        }

        return $json;
    }

    /**
    * Simplifies a large set of UTM coordinates and convert them to latitude and longitude (WGS84)
    * using a geographic point object ($gPoint). It simplifies geometries only when needed 
    * (if the points exceed 1000) and converts them to lat/lon when $gPoint is provided.
    * 
    * @param mixed $orig_points
    * @param boolean $need_simplify Flag indicating whether to simplify the geometry
    * @param object  $gPoint        Reference to the geographic point object for conversion
    * @return mixed
    */
    function geoSimplifyAndConvert(&$orig_points, $need_simplify, &$gPoint = null)
    {
        // Define constants for simplification thresholds and tolerance values
        $MAX_POINTS = 1000;
        $INITIAL_TOLERANCE = 0.01;
        $TOLERANCE_INCREMENT = 0.002;
        $MAX_SIMPLIFY_RUNS = 4;

        // Simplify points if necessary and if there are more than the allowed threshold
        if ($need_simplify && count($orig_points) > $MAX_POINTS) {

            // Invert the points and optionally convert UTM to Lat/Lon
            $points = array_map(function ($point) use ($gPoint) {
                if ($gPoint !== null) {
                    $gPoint->setUTM($point[0], $point[1]);
                    $gPoint->convertTMtoLL();
                    return array('y' => $gPoint->Lat(), 'x' => $gPoint->Long());
                } else {
                    return array('y' => $point[1], 'x' => $point[0]);
                }
            }, $orig_points);

            // Initialize tolerance and run simplification
            $tolerance = $INITIAL_TOLERANCE;
            $crn = 0;
            $simplified_points = $points;

            // Run simplification multiple times to reduce point count
            while (count($simplified_points) > $MAX_POINTS && $crn < $MAX_SIMPLIFY_RUNS) {
                $simplified_points = Simplify::run($points, $tolerance);
                $tolerance += $TOLERANCE_INCREMENT;
                $crn++;
            }

            // If after simplification points are still above the threshold, do nothing
            if (count($simplified_points) > $MAX_POINTS) {
                return;
            }

            // Update the original points array with simplified results
            $orig_points = array_map(function ($point) {
                return array($point['x'], $point['y']);
            }, $simplified_points);

        } elseif ($gPoint !== null) {
            // If simplification is not needed but conversion is required (UTM to Lat/Lon)
            foreach ($orig_points as $idx => $point) {
                $gPoint->setUTM($point[0], $point[1]);
                $gPoint->convertTMtoLL();
                $orig_points[$idx] = array($gPoint->Long(), $gPoint->Lat());
            }
        }
    }


    /**
     * Corrects longitude values in GeoJSON if abs(lng) > 180.
     * 
     * @param array $json The GeoJSON object to correct
     * 
     * @return array The corrected GeoJSON
     */
    function geoCorrectLngJSON($json)
    {
        // Handle GeometryCollection by recursively correcting geometries
        if ($json['type'] == 'GeometryCollection') {
            foreach ($json['geometries'] as $idx => $geometry) {
                $json['geometries'][$idx] = geoCorrectLngJSON($geometry);
            }
            return $json;
        }

        // Skip if no coordinates are present
        if (empty($json['coordinates'])) {
            return array();
        }

        // Correct longitudes based on geometry type
        switch ($json['type']) {
            case 'Point':
                // Correct single point
                $pnt = array($json['coordinates']);
                geoCorrectLng($pnt);
                $json['coordinates'] = $pnt[0];
                break;

            case 'MultiPoint':
            case 'LineString':
                // Correct multiple points
                geoCorrectLng($json['coordinates']);
                break;

            case 'Polygon':
                // Correct polygon rings (outer and inner)
                foreach ($json['coordinates'] as $idx => $ring) {
                    geoCorrectLng($json['coordinates'][$idx]);
                }
                break;

            case 'MultiPolygon':
            case 'MultiLineString':
                // Correct each shape and its points
                foreach ($json['coordinates'] as $idx => $shape) {
                    foreach ($shape as $idx2 => $points) {
                        geoCorrectLng($json['coordinates'][$idx][$idx2]);
                    }
                }
                break;

            default:
                // Unsupported geometry types, return unchanged
                return $json;
        }

        return $json;
    }


    function geoCorrectLng(&$orig_points){

        //invert
        $points = array();
        foreach ($orig_points as $idx => $point) {

            $lng = $point[0];
            $lng2 = $point[0];

            $k = intdiv($lng, 360);

            $lng = ($lng - $k*360);

            if(abs($lng)>180){
                if($k==0) {$k = ($lng<0)?-1:1;}
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
