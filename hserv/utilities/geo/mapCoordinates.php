<?php
/**
* mapCoordinates.php - GeoJSON coordinate manipulation utilities for Heurist
*
* This file provides a collection of global functions designed to process GeoJSON data.
* Key functionalities include:
* - Simplification of geometries to reduce point density, often using an external `Simplify` class (not defined in this file).
* - Conversion of coordinates between UTM (Universal Transverse Mercator) and WGS84 (Latitude/Longitude) systems,
*   utilizing an external `GpointConverter` class (expected to be `hserv\utilities\GpointConverter`).
* - Correction of longitude values to ensure they fall within the -180 to 180 degree range.
* - Recursive processing for complex GeoJSON types like GeometryCollection, MultiPolygon, and MultiLineString.
*
* These functions are typically used when handling geographic data for display or storage within Heurist.
*
* @project     Heurist academic knowledge management system
* @package Utilities\geo
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    /**
     * Simplifies and/or converts coordinates within a GeoJSON object.
     * If $gPoint is provided, coordinates are assumed to be UTM and are converted to WGS84 (Lat/Lon).
     * If $need_simplify is true, geometries are simplified.
     * Handles various GeoJSON types including Point, MultiPoint, LineString, Polygon, MultiPolygon, MultiLineString, and GeometryCollection.
     *
     * @param array $json The input GeoJSON object as an associative array.
     * @param bool $need_simplify Flag indicating whether to simplify the geometry.
     * @param \hserv\utilities\GpointConverter|null &$gPoint Optional. A GpointConverter object for UTM to Lat/Lon conversion.
     *                                                       Passed by reference. If null, no coordinate conversion is performed.
     * @return array The processed GeoJSON object with simplified and/or converted coordinates, or an empty array if input is invalid.
     */
    function geoSimplifyAndConvertJSON($json, $need_simplify, &$gPoint = null)
    {

        // Validate input type
        if (!is_array($json) || !isset($json['type']) || !isset($json['coordinates'])) {
            return array(); // Return an empty array if the input is not valid GeoJSON
        }

        // Handle GeometryCollection recursively
        if ($json['type'] === 'GeometryCollection') {
            return processGeometryCollection($json, $need_simplify, $gPoint);
        }

        // Process individual geometry types
        if ($json['type'] == 'Point') { //$gPoint &&
            // Convert a single point
            $point = array($json['coordinates']);
            geoSimplifyAndConvert($point, false, $gPoint);
            $json['coordinates'] = $point[0];

        } elseif ($json['type'] == 'MultiPoint') { //$gPoint &&
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
             $json['coordinates'] = processMultiShape($json['coordinates'], $need_simplify, $gPoint);
        }

        return $json;
    }

    /**
     * Processes a GeoJSON GeometryCollection by recursively calling `geoSimplifyAndConvertJSON` on each geometry within it.
     *
     * @param array $json The GeoJSON GeometryCollection object.
     * @param bool $need_simplify Flag indicating whether simplification is needed.
     * @param \hserv\utilities\GpointConverter|null &$gPoint Optional. A GpointConverter object for coordinate conversion. Passed by reference.
     * @return array The processed GeometryCollection.
     */
    function processGeometryCollection($json, $need_simplify, &$gPoint) {
        foreach ($json['geometries'] as $idx => $geometry) {
            $json['geometries'][$idx] = geoSimplifyAndConvertJSON($geometry, $need_simplify, $gPoint);
        }
        return $json;
    }

    /**
     * Processes the coordinates of a multi-part geometry (MultiPolygon or MultiLineString).
     * For MultiPolygon, each "shape" is a polygon (an array of rings), and each "ring" is an array of points.
     * For MultiLineString, each "shape" is a line (an array of points).
     * This function iterates through these structures and applies `geoSimplifyAndConvert` to the point arrays.
     *
     * @param array $shapes An array of shapes. For MultiPolygon, this is an array of polygons (array of rings of points).
     *                      For MultiLineString, this is an array of lines (array of points).
     * @param bool $need_simplify Flag indicating whether simplification is needed.
     * @param \hserv\utilities\GpointConverter|null &$gPoint Optional. A GpointConverter object for coordinate conversion. Passed by reference.
     * @return array The processed shapes array with simplified and/or converted coordinates.
     */
    function processMultiShape($shapes, $need_simplify, &$gPoint) {
        foreach ($shapes as $idx => $shape) {
            foreach ($shape as $idx2 => $points) {
                geoSimplifyAndConvert($points, $need_simplify, $gPoint); // $points is passed by reference here
                $shapes[$idx][$idx2] = $points;
            }
        }
        return $shapes;
    }

    /**
     * Simplifies a set of coordinates and/or converts them from UTM to WGS84 (Lat/Lon).
     * Simplification is applied if `$need_simplify` is true and the number of points exceeds a threshold (1000).
     * Coordinate conversion from UTM to Lat/Lon is performed if `$gPoint` is provided.
     * The input array `$orig_points` is modified in place.
     *
     * @param array &$orig_points An array of points, where each point is an array [easting, northing] or [longitude, latitude].
     *                            This array is passed by reference and will be modified.
     * @param bool $need_simplify Flag indicating whether to simplify the geometry.
     * @param \hserv\utilities\GpointConverter|null &$gPoint Optional. A GpointConverter object, configured for the correct UTM zone,
     *                                                       for UTM to Lat/Lon conversion. Passed by reference. If null, no conversion is done.
     * @return void The `$orig_points` array is modified directly.
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
     * @param array $json The GeoJSON object (as an associative array) to correct.
     * @return array The GeoJSON object with corrected longitude values, or an empty array if input coordinates are empty.
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


    /**
     * Corrects longitude values in an array of points to fall within the -180 to 180 degree range.
     * Modifies the input array in place.
     *
     * @param array &$orig_points An array of points, where each point is an array [longitude, latitude].
     *                            This array is passed by reference and will be modified.
     * @return void The `$orig_points` array is modified directly.
     */
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
