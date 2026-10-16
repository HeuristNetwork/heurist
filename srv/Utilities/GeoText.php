<?php
/**
* GeoText.php - Human-readable text of a geographic value
*
* Feature type, number of vertices and the coordinate ranges, e.g.
*   Polygon n=5 (X -153.2389637,190.6447474 Y -38.3421436,70.3620549)
* A single point is "Point (X x Y y)". The client builds the same text in
* heurist-explorer shared/src/utils/geoText.js (geoToHuman); keep both in step.
*
* @project     Heurist academic knowledge management system
* @package     Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Utilities;

/** Human-readable text of WKT geometries. */
final class GeoText
{
    /** WKT keyword => feature type name. */
    private const FEATURE_TYPES = array(
        'POINT' => 'Point',
        'MULTIPOINT' => 'MultiPoint',
        'LINESTRING' => 'LineString',
        'MULTILINESTRING' => 'MultiLineString',
        'POLYGON' => 'Polygon',
        'MULTIPOLYGON' => 'MultiPolygon',
        'GEOMETRYCOLLECTION' => 'GeometryCollection'
    );

    /**
     * Readable text of a WKT geometry. A legacy Heurist type code before it
     * ("pl POLYGON(...)") is ignored; a value that is not WKT is returned as it is.
     *
     * @param string|null $wkt WKT text.
     * @return string The text; '' for an empty value.
     */
    public static function summary(?string $wkt): string
    {
        $text = trim((string)$wkt);
        $text = (string)preg_replace('/^(?:p|pl|c|r|l|m)\s+(?=[a-z]+\s*\(|[a-z]+\s+empty)/i', '', $text);
        if($text === ''){
            return '';
        }
        if(!preg_match('/^([a-z]+)(?:\s+(?:zm|z|m))?\s*(?:\(|empty\b)/i', $text, $match)){
            return $text;
        }
        $keyword = strtoupper($match[1]);
        $type = self::FEATURE_TYPES[$keyword] ?? $keyword;
        $vertices = self::vertices($text);
        if(empty($vertices)){
            return $type;
        }
        if(count($vertices) === 1){
            return $type.' (X '.self::coordinate($vertices[0][0]).' Y '.self::coordinate($vertices[0][1]).')';
        }
        $xs = array_column($vertices, 0);
        $ys = array_column($vertices, 1);
        return $type.' n='.count($vertices)
            .' (X '.self::coordinate(min($xs)).','.self::coordinate(max($xs))
            .' Y '.self::coordinate(min($ys)).','.self::coordinate(max($ys)).')';
    }

    /**
     * The vertices of a WKT geometry: x and y of every coordinate (z/m are ignored).
     *
     * @param string $wkt WKT text.
     * @return array<int,array{0:float,1:float}>
     */
    public static function vertices(string $wkt): array
    {
        $vertices = array();
        // innermost parentheses hold the coordinate lists
        if(preg_match_all('/\(([^()]*)\)/', $wkt, $groups)){
            foreach($groups[1] as $list){
                foreach(explode(',', $list) as $item){
                    $parts = preg_split('/\s+/', trim($item));
                    if(count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])){
                        $vertices[] = array((float)$parts[0], (float)$parts[1]);
                    }
                }
            }
        }
        return $vertices;
    }

    /** A coordinate rounded to 7 decimals, without insignificant trailing zeros. */
    private static function coordinate(float $value): string
    {
        $text = rtrim(rtrim(number_format(round($value, 7), 7, '.', ''), '0'), '.');
        return $text === '-0' || $text === '' ? '0' : $text;
    }
}
