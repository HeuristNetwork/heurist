<?php
/**
* GeoJsonStreamWriter.php - Transactional GeoJSON FeatureCollection writer
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

namespace Heurist\Records\Map;

/** Stages bounded feature generation before committing a complete response. */
final class GeoJsonStreamWriter
{
    private const MEMORY_LIMIT = 5242880;

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

    /**
     * Write one complete FeatureCollection and its metadata.
     *
     * Generation is staged in php://temp so an exception cannot leave a
     * partial JSON document in the HTTP response. Payloads larger than the
     * memory threshold spill to a temporary file automatically.
     */
    public function write(MapFeatureStream $stream): void
    {
        $buffer = fopen('php://temp/maxmemory:'.self::MEMORY_LIMIT, 'w+b');
        if(!is_resource($buffer)){
            throw new \RuntimeException('Unable to open temporary GeoJSON stream');
        }

        try{
            $this->writeCollection($buffer, $stream);
            rewind($buffer);
            if(stream_copy_to_stream($buffer, $this->output) === false){
                throw new \RuntimeException('Unable to write GeoJSON response');
            }
        }finally{
            fclose($buffer);
        }
    }

    /** Generate a complete FeatureCollection into the staging stream. */
    private function writeCollection($buffer, MapFeatureStream $stream): void
    {
        fwrite($buffer, '{"type":"FeatureCollection","features":[');
        $first = true;
        foreach($stream->features() as $feature){
            $json = json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if($json === false){ throw new \RuntimeException('Unable to encode map feature'); }
            if(!$first){ fwrite($buffer, ','); }
            fwrite($buffer, $json);
            $first = false;
        }
        $meta = json_encode($stream->meta(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if($meta === false){ throw new \RuntimeException('Unable to encode map metadata'); }
        fwrite($buffer, '],"meta":'.$meta.'}');
    }
}
