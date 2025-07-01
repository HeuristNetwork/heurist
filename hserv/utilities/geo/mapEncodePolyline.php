<?php
/**
* mapEncodePolyline.php
* 
* Provides functions for encoding polylines, suitable for mapping services (e.g., Google Maps Polyline Algorithm).
* This includes:
* - A Douglas-Peucker like simplification algorithm (`dpEncode`).
* - Encoding of coordinates and zoom levels into compact string representations.
*
* The encoding process is configured by several global variables:
* - `$numLevels`: The number of zoom levels to consider for encoding.
* - `$zoomFactor`: The change in magnification between zoom levels.
* - `$verySmall`: The length of a barely visible object at the highest zoom level, used as a threshold for simplification.
* - `$forceEndpoints`: Boolean indicating whether endpoints should always be visible at all zoom levels.
* - `$zoomLevelBreaks`: An array calculated from the above, defining distance thresholds for each zoom level.
* 
* @project     Heurist academic knowledge management system
* @package Utilities\geo
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Kim Jackson
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1.0
 */

// where numLevels and zoomFactor indicate how many
// different levels of magnification the polyline has
// and the change in magnification between those levels,
// verySmall indicates the length of a barely visible
// object at the highest zoom level, forceEndpoints
// indicates whether or not the endpoints should be
// visible at all zoom levels. forceEndpoints is
// optional with a default value of true. Probably
// should stay true regardless.


$numLevels = 21;
$zoomFactor = 3;
$verySmall = 0.00001;
$forceEndpoints = true;

for($i = 0; $i < $numLevels; $i++)
{
    $zoomLevelBreaks[$i] = $verySmall * pow($zoomFactor, $numLevels-$i-1);
}

/**
 * Computes the appropriate zoom level for a given distance (detail delta).
 *
 * @global float $verySmall The smallest distance considered significant.
 * @global array $zoomLevelBreaks Array of distance thresholds for each zoom level.
 * @param float $dd The distance (detail delta) to compute the level for.
 * @return int The computed zoom level (0-indexed).
 */
function computeLevel($dd)
{
    global $verySmall, $zoomLevelBreaks;

    if($dd > $verySmall)
    {
        $lev = 0;
        while($dd < $zoomLevelBreaks[$lev])
        {
            $lev++;
        }
    }
    return $lev;
}

/**
 * Encodes a polyline using a Douglas-Peucker like simplification algorithm.
 *
 * @global float $verySmall The tolerance for simplification (smallest significant distance).
 * @param array $points An array of points, where each point is an array [latitude, longitude].
 * @return array An array containing the encoded points string, encoded levels string,
 *               and a version of the encoded points string with backslashes escaped.
 */
function dpEncode($points)
{
    global $verySmall;

    $maxDist = 0;
    $absMaxDist = 0;
    $dists = array();

    if(count($points) < 3)
    {
        return encodedPoints($points, $dists, $absMaxDist);
    }

    $stack[] = array(0, count($points)-1);
    while(!empty($stack))
    {
        $current = array_pop($stack);
        $maxDist = 0;
        for($i = $current[0]+1; $i < $current[1]; $i++)
        {
            $temp = distance($points[$i], $points[$current[0]], $points[$current[1]]);
            if($temp > $maxDist)
            {
                $maxDist = $temp;
                $maxLoc = $i;
                if($maxDist > $absMaxDist)
                {
                    $absMaxDist = $maxDist;
                }
            }
        }
        if($maxDist > $verySmall)
        {
            $dists[$maxLoc] = $maxDist;
            array_push($stack, array($current[0], $maxLoc));
            array_push($stack, array($maxLoc, $current[1]));
        }
    }

    return encodedPoints($points, $dists, $absMaxDist);
}

/**
 * Packages the results of polyline encoding.
 *
 * @param array $points Original array of points.
 * @param array $dists Array of distances for simplified points.
 * @param float $absMaxDist Absolute maximum distance found during simplification.
 * @return array An array containing:
 *               0: (string) Encoded points string.
 *               1: (string) Encoded levels string.
 *               2: (string) Encoded points string with backslashes escaped (literal).
 */
function encodedPoints($points, $dists, $absMaxDist){
    $encodedPoints = createEncodings($points, $dists);
    $encodedLevels = encodeLevels($points, $dists, $absMaxDist);
    $encodedPointsLiteral = str_replace('\\',"\\\\",$encodedPoints);

    return array($encodedPoints, $encodedLevels, $encodedPointsLiteral);
}

/**
 * Calculates the perpendicular distance from a point ($p0) to a line segment defined by $p1 and $p2.
 * If the perpendicular projection falls outside the segment, it returns the distance to the closest endpoint.
 *
 * @param array $p0 The point, as an array [latitude, longitude].
 * @param array $p1 The first point of the line segment, as an array [latitude, longitude].
 * @param array $p2 The second point of the line segment, as an array [latitude, longitude].
 * @return float The calculated distance.
 */
function distance($p0, $p1, $p2)
{
    if($p1[0] == $p2[0] && $p1[1] == $p2[1])
    {
        $out = sqrt(pow($p2[0]-$p0[0],2) + pow($p2[1]-$p0[1],2));
    }
    else
    {
        $u = (($p0[0]-$p1[0])*($p2[0]-$p1[0]) + ($p0[1]-$p1[1]) * ($p2[1]-$p1[1])) / (pow($p2[0]-$p1[0],2) + pow($p2[1]-$p1[1],2));
        if($u <= 0)
        {
            $out = sqrt(pow($p0[0] - $p1[0],2) + pow($p0[1] - $p1[1],2));
        }
        if($u >= 1)
        {
            $out = sqrt(pow($p0[0] - $p2[0],2) + pow($p0[1] - $p2[1],2));
        }
        if(0 < $u && $u < 1)
        {
            $out = sqrt(pow($p0[0]-$p1[0]-$u*($p2[0]-$p1[0]),2) + pow($p0[1]-$p1[1]-$u*($p2[1]-$p1[1]),2));
        }
    }
    return $out;
}

/**
 * Encodes a signed number into the polyline format.
 * This involves shifting the number left (multiplying by 2) and inverting bits if negative,
 * then passing to `encodeNumber`.
 *
 * @param int|float $num The signed number to encode.
 * @return string The encoded string representation of the number.
 */
function encodeSignedNumber($num)
{
    $sgn_num = $num << 1;
    if ($num < 0)
    {
        $sgn_num = ~($sgn_num);
    }
    return encodeNumber($sgn_num);
}

/**
 * Creates the encoded points string from an array of points and their simplification distances.
 * Only points that are part of the simplified polyline (i.e., in $dists or endpoints) are included.
 * Coordinates are encoded relative to the previous point.
 *
 * @param array $points An array of points, where each point is [latitude, longitude].
 * @param array $dists An associative array where keys are indices of points in the simplified polyline
 *                     and values are their calculated distances (used to determine if a point is kept).
 * @return string The encoded points string.
 */
function createEncodings($points, $dists)
{
    $encoded_points = "";
    $plat = 0;
    $plng = 0;
    for($i=0; $i<count($points); $i++)
    {
        if(isset($dists[$i]) || $i == 0 || $i == count($points)-1)
        {
            $point = $points[$i];
            $lat = $point[0];
            $lng = $point[1];
            $late5 = floor($lat * 1e5);
            $lnge5 = floor($lng * 1e5);
            $dlat = $late5 - $plat;
            $dlng = $lnge5 - $plng;
            $plat = $late5;
            $plng = $lnge5;
            $encoded_points .= encodeSignedNumber($dlat) . encodeSignedNumber($dlng);
        }
    }
    return $encoded_points;
}

/**
 * Encodes the zoom levels for the simplified polyline points.
 *
 * @global int $numLevels The total number of zoom levels.
 * @global bool $forceEndpoints Whether to force endpoints to be visible at the highest detail.
 * @param array $points The original array of points.
 * @param array $dists An associative array of distances for simplified points.
 * @param float $absMaxDist The absolute maximum distance found during simplification,
 *                          used for endpoints if not forcing them.
 * @return string The encoded levels string.
 */
function encodeLevels($points, $dists, $absMaxDist)
{
    global $numLevels, $forceEndpoints;
    $encoded_levels = "";

    if($forceEndpoints)
    {
        $encoded_levels .= encodeNumber($numLevels-1);
    }
    else
    {
        $encoded_levels .= encodeNumber($numLevels-computeLevel($absMaxDist)-1);
    }
    for($i=1; $i<count($points)-1; $i++)
    {
        if(isset($dists[$i]))
        {
            $encoded_levels .= encodeNumber($numLevels-computeLevel($dists[$i])-1);
        }
    }
    if($forceEndpoints)
    {
        $encoded_levels .= encodeNumber($numLevels -1);
    }
    else
    {
        $encoded_levels .= encodeNumber($numLevels-computeLevel($absMaxDist)-1);
    }
    return $encoded_levels;
}

/**
 * Encodes a non-negative number into the polyline character format.
 * Each character represents 5 bits of the number, with a continuation bit.
 * Characters are offset by 63.
 *
 * @param int|float $num The non-negative number to encode.
 * @return string The encoded string.
 */
function encodeNumber($num)
{
    $encodeString = "";
    while($num >= 0x20)
    {
        $nextValue = (0x20 | ($num & 0x1f)) + 63;
        $encodeString .= chr($nextValue);
        $num >>= 5;
    }
    $finalValue = $num + 63;
    $encodeString .= chr($finalValue);
    return $encodeString;
}
