<?php
/**
* mapSimplify.php - Class Simplify
* 
* Provides polyline simplification capabilities using a port of the simplify.js algorithm.
* This algorithm is based on the Douglas-Peucker method for reducing the number of points in a polyline
* while retaining its essential shape, given a tolerance.
*
* This file contains:
* - The `Simplify` class with a static `run` method to perform the simplification.
* - A global utility function `simplifyCoordinates` that uses the `Simplify` class, typically for GeoJSON coordinate arrays.
*
* Original simplify.js library by Vladimir Agafonkin.
* @link http://mourner.github.io/simplify-js
* Ported to PHP by Andreychumak.
* @link http://github.com/andreychumak/simplify-php
*
* @package     Heurist academic knowledge management system
* @subpackage  Utilities/Geo
*/

/**
* Class Simplify
* 
* A PHP port of the simplify.js library for high-performance polyline simplification.
* Uses a combination of radial distance and Douglas-Peucker algorithms to reduce
* the number of points in a series of coordinates.
*/
class Simplify {

    /**
     * Simplifies an array of points.
     *
     * @param array $points An array of points, where each point is an associative array with 'x' and 'y' keys.
     * @param float|int $tolerance Optional. The tolerance for simplification (the square of this value is used).
     *                             Higher values result in more simplification. Defaults to 1.
     * @param bool $highestQuality Optional. If true, performs a slower but higher-quality simplification by retaining
     *                             original points. If false (default), uses a faster radial distance pre-processing step.
     * @return array The simplified array of points.
     */
    public static function run(array $points, $tolerance = 1, $highestQuality = false) {
        if (count($points) <= 1) {return $points;}

        $sqTolerance = $tolerance*$tolerance;

        $points = $highestQuality ? $points : self::simplifyRadialDist($points, $sqTolerance);
        $points = self::simplifyDouglasPeucker($points, $sqTolerance);

        return $points;
    }

    // basic distance-based simplification
    private static function simplifyRadialDist($points, $sqTolerance) {

        $prevPoint = $points[0];
        $newPoints = array($prevPoint);
        $point = null;

        for ($i = 1, $len = count($points); $i < $len; $i++) {
            $point = $points[$i];

            if (self::getSqDist($point, $prevPoint) > $sqTolerance) {
                $newPoints[] = $point;
                $prevPoint = $point;
            }
        }

        if ($prevPoint !== $point) {$newPoints[] = $point;}

        return $newPoints;
    }

    // square distance between 2 points
    private static function getSqDist($p1, $p2) {

        $dx = $p1['x'] - $p2['x'];
        $dy = $p1['y'] - $p2['y'];

        return $dx * $dx + $dy * $dy;
    }

    // simplification using optimized Douglas-Peucker algorithm with recursion elimination
    private static function simplifyDouglasPeucker($points, $sqTolerance) {

        $len = count($points);
        $markers = array_fill(0, $len-1, null);
        $first = 0;
        $last = $len - 1;
        $stack = array();
        $newPoints = array();
        $index = null;

        $markers[$first] = $markers[$last] = 1;

        while ($last) {

            $maxSqDist = 0;

            for ($i = $first + 1; $i < $last; $i++) {
                $sqDist = self::getSqSegDist($points[$i], $points[$first], $points[$last]);

                if ($sqDist > $maxSqDist) {
                    $index = $i;
                    $maxSqDist = $sqDist;
                }
            }

            if ($maxSqDist > $sqTolerance) {
                $markers[$index] = 1;
                array_push($stack, $first, $index, $index, $last);
            }

            $last = array_pop($stack);
            $first = array_pop($stack);
        }

        //var_dump($markers, $points, $i);
        for ($i = 0; $i < $len; $i++) {
            if ($markers[$i]) {$newPoints[] = $points[$i];}
        }

        return $newPoints;
    }

    // square distance from a point to a segment
    private static function getSqSegDist($p, $p1, $p2) {
        $x = $p1['x'];
        $y = $p1['y'];
        $dx = $p2['x'] - $x;
        $dy = $p2['y'] - $y;

        if (intval($dx) !== 0 || intval($dy) !== 0) {

            $t = (($p['x'] - $x) * $dx + ($p['y'] - $y) * $dy) / ($dx * $dx + $dy * $dy);

            if ($t > 1) {
                $x = $p2['x'];
                $y = $p2['y'];

            } elseif($t > 0) {
                $x += $dx * $t;
                $y += $dy * $t;
            }
        }

        $dx = $p['x'] - $x;
        $dy = $p['y'] - $y;

        return $dx * $dx + $dy * $dy;
    }

}

/**
 * Simplifies an array of coordinates (typically [longitude, latitude] or [easting, northing] pairs) in place.
 * This function is a utility wrapper around the `Simplify::run` method. It converts the input coordinate
 * pairs to the {x, y} format required by `Simplify::run`, performs simplification if the point count
 * exceeds 1000, and then converts the result back to the original [coord1, coord2] format.
 * The simplification tolerance is incrementally increased if the point count remains too high after initial attempts.
 *
 * @param array &$orig_points An array of coordinate pairs (e.g., `[[lon1, lat1], [lon2, lat2], ...]`).
 *                            This array is passed by reference and will be modified directly.
 * @return void The `$orig_points` array is modified in place.
 */
function simplifyCoordinates(&$orig_points){

    if(count($orig_points)>1000){

        //invert
        $points = array();
        foreach ($orig_points as $point) {
            array_push($points, array('y'=>$point[1], 'x'=>$point[0]));
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

    }
}
?>
