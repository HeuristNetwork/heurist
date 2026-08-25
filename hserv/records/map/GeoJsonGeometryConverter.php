<?php
/**
* GeoJsonGeometryConverter.php - WKT to GeoJSON geometry conversion
*
* @project     Heurist academic knowledge management system
* @package     Records\Map
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/

namespace hserv\records\map;

/** Stateless converter extracted from the legacy GeoJSON exporter. */
final class GeoJsonGeometryConverter
{
    public function convert(string $wkt, bool $simplify = false): ?array
    {
        if(trim($wkt) === ''){ return null; }
        try{
            $geometry = \geoPHP::load($wkt, 'wkt');
            if(!$geometry || $geometry->isEmpty()){ return null; }
            $adapter = new \GeoJSON();
            $json = $adapter->write($geometry, true);
            if(!is_array($json) || empty($json['type'])){ return null; }
            if($simplify && !empty($json['coordinates'])){
                $this->simplify($json);
            }
            return $json;
        }catch(\Throwable $e){
            return null;
        }
    }

    private function simplify(array &$geometry): void
    {
        if(!function_exists('simplifyCoordinates')){ return; }
        if($geometry['type']==='LineString'){
            simplifyCoordinates($geometry['coordinates']);
        }elseif($geometry['type']==='Polygon'){
            foreach($geometry['coordinates'] as &$ring){ simplifyCoordinates($ring); }
            unset($ring);
        }elseif($geometry['type']==='MultiPolygon' || $geometry['type']==='MultiLineString'){
            foreach($geometry['coordinates'] as &$shape){
                foreach($shape as &$points){ simplifyCoordinates($points); }
                unset($points);
            }
            unset($shape);
        }
    }
}
