<?php
/**
* MapFeatureStream.php - Iterable map feature result
*
* Couples a lazy feature iterator with metadata finalized after iteration for
* incremental GeoJSON output.
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
declare(strict_types=1);
namespace Heurist\Records\Map;

/** Iterable feature stream plus metadata finalized after iteration. */
final class MapFeatureStream
{
    private \Traversable $features;
    private $metaProvider;

    /** Initialise a feature iterator and deferred metadata provider. */
    public function __construct(\Traversable $features, callable $metaProvider)
    {
        $this->features=$features; $this->metaProvider=$metaProvider;
    }

    /** Return the lazy feature iterator. */
    public function features(): \Traversable {return $this->features;}

    /** Return metadata after feature iteration has completed. */
    public function meta(): array {return call_user_func($this->metaProvider);}
}
