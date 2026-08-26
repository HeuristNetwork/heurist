<?php
/**
* GeoJsonStreamWriter.php - Incremental strict GeoJSON FeatureCollection writer
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

/** Writes each feature as soon as its bounded retrieval batch is available. */
final class GeoJsonStreamWriter
{
    /** @var resource */
    private $output;

    /** @param resource|null $output Defaults to php://output. */
    public function __construct($output = null)
    {
        $this->output = $output ?? fopen('php://output', 'wb');
        if(!is_resource($this->output)){
            throw new \RuntimeException('Unable to open GeoJSON output stream');
        }
    }

    public function write(MapFeatureStream $stream): void
    {
        fwrite($this->output, '{"type":"FeatureCollection","features":[');
        $first = true;
        foreach($stream->features() as $feature){
            $json = json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if($json === false){ throw new \RuntimeException('Unable to encode map feature'); }
            if(!$first){ fwrite($this->output, ','); }
            fwrite($this->output, $json);
            $first = false;
        }
        $meta = json_encode($stream->meta(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if($meta === false){ throw new \RuntimeException('Unable to encode map metadata'); }
        fwrite($this->output, '],"meta":'.$meta.'}');
    }
}
