<?php
/**
* ServiceFactory.php - Modern workflow composition
*
* Builds controllers and their shared PDO-backed services at the temporary
* boundary with an initialized legacy System object.
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Runtime;

use Heurist\Controller\DefinitionController;
use Heurist\Controller\GraphController;
use Heurist\Controller\MapDataController;
use Heurist\Controller\PublicationController;
use Heurist\Controller\RecordPresentationController;
use Heurist\Controller\RecordQueryController;
use Heurist\Controller\SystemQueryController;
use Heurist\Controller\TimeDataController;
use Heurist\Database\DatabaseFactory;
use Heurist\Database\DatabaseInterface;
use Heurist\Publication\PublicationService;
use Heurist\Records\Map\MapFeatureService;
use Heurist\Records\Presentation\DatasetPresentationService;
use Heurist\Records\Presentation\MapPresentationService;
use Heurist\Records\Presentation\PresentationRecordRepository;
use Heurist\Definitions\DefinitionSnapshotService;
use Heurist\Records\Time\TimeDataService;
use Heurist\System\Query\SystemQueryService;

/** Creates one consistent service graph for an initialized request. */
final class ServiceFactory
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private PresentationRecordRepository $presentations;
    private SystemCode $codes;
    private string $publicationDirectory;
    private string $entityDirectory;

    /** Build the PDO and runtime boundary from the current legacy initialization. */
    public static function fromLegacySystem($system): self
    {
        $runtime = RuntimeContext::fromLegacySystem($system);
        $codeNames = array(
            'RT_DATASET', 'RT_QUERY_SOURCE', 'RT_MAP_DOCUMENT', 'RT_MAP_LAYER',
            'RT_FILE_SOURCE', 'RT_GEOTIFF_SOURCE', 'RT_IMAGE_SOURCE',
            'RT_KML_SOURCE', 'RT_SHP_SOURCE', 'RT_TILED_IMAGE_SOURCE',
            'RT_TLCMAP_DATASET',
            'DT_DATA_SOURCE', 'DT_QUERY_STRING', 'DT_DATA_FIELDS', 'DT_EXPANSION_RULES',
            'DT_SHORT_SUMMARY', 'DT_CRS', 'DT_FILE_RESOURCE', 'DT_GEO_OBJECT',
            'DT_GEO_OUTPUTMODE', 'DT_IS_LOADED_BY_EXTENT', 'DT_IS_VISIBLE',
            'DT_MAP_BOOKMARK', 'DT_MAP_IMAGE_LAYER_SCHEMA',
            'DT_MAP_IMAGE_WORLDFILE', 'DT_MAP_LAYER', 'DT_MAXIMUM_ZOOM',
            'DT_MAXIMUM_ZOOM_LEVEL', 'DT_MIME_TYPE', 'DT_MINIMUM_ZOOM',
            'DT_MINIMUM_ZOOM_LEVEL', 'DT_SERVICE_URL', 'DT_SMARTY_TEMPLATE',
            'DT_SYMBOLOGY', 'DT_TIMELINE_FIELDS', 'DT_WORLD_BASEMAP',
            'DT_ZOOM_KM_POINT', 'DT_START_DATE', 'DT_END_DATE'
        );
        $codeIds = array();
        foreach($codeNames as $codeName){
            if($system->defineConstant($codeName)){
                $codeIds[$codeName] = intval(constant($codeName));
            }
        }
        return new self(
            DatabaseFactory::fromHeuristConfiguration($runtime->databaseNameFull),
            $runtime,
            new SystemCode($codeIds),
            (string)$system->getSysDir('generated-pubs'),
            (string)$system->getSysDir('entity')
        );
    }

    /** Initialise shared services from explicit modern dependencies. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        SystemCode $codes,
        string $publicationDirectory = '',
        string $entityDirectory = ''
    )
    {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->codes = $codes;
        $this->presentations = new PresentationRecordRepository($database, $runtime, $codes);
        $this->publicationDirectory = $publicationDirectory;
        $this->entityDirectory = $entityDirectory;
    }

    /** Create the records query/retrieval controller. */
    public function recordQueryController(): RecordQueryController
    {
        return new RecordQueryController($this->database, $this->runtime);
    }

    /** Create the graph-document controller for the heurist-graph client. */
    public function graphController(): GraphController
    {
        return new GraphController($this->database, $this->runtime);
    }

    /** Create the database-definitions snapshot controller (/api/{db}/def). */
    public function definitionController(): DefinitionController
    {
        return new DefinitionController(
            new DefinitionSnapshotService($this->database, $this->runtime, $this->entityDirectory),
            $this->runtime
        );
    }

    /** Create the mapped filter/user query and retrieval controller. */
    public function systemQueryController(): SystemQueryController
    {
        return new SystemQueryController(
            new SystemQueryService($this->database, $this->runtime),
            $this->runtime
        );
    }

    /** Create the Dataset/Map definition controller. */
    public function recordPresentationController(): RecordPresentationController
    {
        $maps = new MapPresentationService(
            $this->presentations, $this->runtime, new ConceptCode($this->database)
        );
        return new RecordPresentationController(
            new DatasetPresentationService($this->presentations), $maps
        );
    }

    /** Create the query-to-GeoJSON controller. */
    public function mapDataController(): MapDataController
    {
        return new MapDataController(
            new MapFeatureService($this->database, $this->runtime), $this->runtime
        );
    }


    /** Create the record-to-temporal projection controller. */
    public function timeDataController(): TimeDataController
    {
        return new TimeDataController(
            new RecordQueryController($this->database, $this->runtime),
            new TimeDataService($this->database, $this->runtime, $this->codes),
            $this->runtime
        );
    }

    /** Create the shared module publication controller. */
    public function publicationController(): PublicationController
    {
        return new PublicationController(
            new PublicationService($this->runtime, $this->publicationDirectory),
            $this->runtime
        );
    }
}
