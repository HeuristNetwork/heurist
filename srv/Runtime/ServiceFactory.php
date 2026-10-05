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
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);
namespace Heurist\Runtime;

use Heurist\Controller\DefinitionController;
use Heurist\Controller\GraphController;
use Heurist\Controller\JobController;
use Heurist\Controller\MapDataController;
use Heurist\Controller\PublicationController;
use Heurist\Controller\QueryCancelController;
use Heurist\Controller\RecordPresentationController;
use Heurist\Controller\RecordQueryController;
use Heurist\Controller\ReportController;
use Heurist\Controller\SystemQueryController;
use Heurist\Controller\TimeDataController;
use Heurist\Database\DatabaseFactory;
use Heurist\Database\DatabaseInterface;
use Heurist\Database\MysqlDatabase;
use Heurist\Database\QueryTrace;
use Heurist\Jobs\JobHandlerInterface;
use Heurist\Jobs\JobRunner;
use Heurist\Jobs\JobStore;
use Heurist\Publication\PublicationService;
use Heurist\Records\Map\MapFeatureService;
use Heurist\Records\Presentation\QuerySourcePresentationService;
use Heurist\Records\Presentation\MapPresentationService;
use Heurist\Records\Presentation\PresentationRecordRepository;
use Heurist\Definitions\DefinitionSnapshotService;
use Heurist\Records\Data\RecordDataService;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Records\Query\SearchRequest;
use Heurist\Reports\Jobs\ReportGenerateJob;
use Heurist\Reports\Jobs\ReportPreviewJob;
use Heurist\Reports\ReportJobPlanner;
use Heurist\Reports\Smarty\ReportEnvironment;
use Heurist\Reports\Smarty\SmartyTemplateRunner;
use Heurist\Reports\Smarty\SrvSmartyRenderer;
use Heurist\Reports\ReportPolicy;
use Heurist\Reports\ReportRecordWriterInterface;
use Heurist\Reports\ReportRendererInterface;
use Heurist\Reports\ReportRepository;
use Heurist\Reports\ReportService;
use Heurist\Reports\ReportTemplateStore;
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
    /** @var array{templates?:string,generated?:string,generatedUrl?:string,jobs?:string,settings?:array} */
    private array $reportEnvironment;

    /** Build the PDO and runtime boundary from the current legacy initialization. */
    public static function fromLegacySystem($system): self
    {
        QueryTrace::markBoot();
        $runtime = RuntimeContext::fromLegacySystem($system);
        $codeNames = array(
            'RT_QUERY_SOURCE', 'RT_MAP_DOCUMENT', 'RT_MAP_LAYER',
            'RT_FILE_SOURCE', 'RT_GEOTIFF_SOURCE', 'RT_IMAGE_SOURCE',
            'RT_KML_SOURCE', 'RT_SHP_SOURCE', 'RT_TILED_IMAGE_SOURCE',
            'RT_TLCMAP_DATASET',
            'DT_DATA_SOURCE', 'DT_QUERY_STRING', 
            'DT_GEO_FIELDS', 'DT_TABLE_FIELDS', 'DT_TIMELINE_FIELDS', 
            'DT_EXPANSION_RULES', 'DT_FILTER_FORM',
            'DT_SHORT_SUMMARY', 'DT_CRS', 'DT_FILE_RESOURCE', 'DT_GEO_OBJECT',
            'DT_GEO_OUTPUTMODE', 'DT_IS_LOADED_BY_EXTENT', 'DT_IS_VISIBLE',
            'DT_MAP_BOOKMARK', 'DT_MAP_IMAGE_LAYER_SCHEMA',
            'DT_MAP_IMAGE_WORLDFILE', 'DT_MAP_LAYER', 'DT_MAXIMUM_ZOOM',
            'DT_MAXIMUM_ZOOM_LEVEL', 'DT_MIME_TYPE', 'DT_MINIMUM_ZOOM',
            'DT_MINIMUM_ZOOM_LEVEL', 'DT_SERVICE_URL', 'DT_SMARTY_TEMPLATE',
            'DT_SYMBOLOGY', 'DT_TIMELINE_FIELDS', 'DT_WORLD_BASEMAP',
            'DT_ZOOM_KM_POINT', 'DT_START_DATE', 'DT_END_DATE', 'DT_NAME',
            'DT_EXTENDED_DESCRIPTION',
            // Smarty reports (plan 12)
            'RT_CUSTOM_REPORT', 'RT_REPORT_SCHEDULE', 'DT_FILE_NAME',
            'DT_IS_CARD_VIEW', 'DT_REPORT', 'DT_INTERVAL_MINUTES',
            // srv Smarty engine (plan 12, Phase 6): relationships, CMS menu descriptions
            'RT_RELATION', 'RT_CMS_MENU', 'DT_RELATION_TYPE', 'DT_PRIMARY_RESOURCE', 'DT_TARGET_RESOURCE'
        );
        $codeIds = array();
        foreach($codeNames as $codeName){
            if($system->defineConstant($codeName)){
                $codeIds[$codeName] = intval(constant($codeName));
            }
        }
        $database = DatabaseFactory::fromHeuristConfiguration($runtime->databaseNameFull);
        // srv/ services never write the PHP session. Releasing its lock lets parallel
        // module requests and a cancel request run while a long query is executing.
        if(session_status() === PHP_SESSION_ACTIVE){ session_write_close(); }
        RequestRegistry::register($database, $runtime);
        if(QueryTrace::enabled()){
            // SQL text and EXPLAIN only for logged-in users
            QueryTrace::allowSql($runtime->userId > 0);
            QueryTrace::setExplainer(static function(string $sql, array $values) use ($database): array {
                return $database->fetchAll($sql, $values);
            });
        }
        $reportSettings = $system->settings->getDatabaseSetting('Reports');
        return new self(
            $database,
            $runtime,
            new SystemCode($codeIds),
            (string)$system->getSysDir('generated-pubs'),
            (string)$system->getSysDir('entity'),
            array(
                'templates' => (string)$system->getSysDir('smarty-templates'),
                'generated' => (string)$system->getSysDir('generated-reports'),
                'generatedUrl' => (string)$system->getSysUrl('generated-reports'),
                'jobs' => (string)$system->getSysDir('scratch').'jobs/',
                'settings' => is_array($reportSettings) ? $reportSettings : array(),
                // values of the srv Smarty engine, read only when a report runs
                'engine' => static function() use ($system): array {
                    return self::legacyEngineEnvironment($system);
                }
            )
        );
    }

    /**
     * Installation values of the srv Smarty engine from the legacy System
     * (see Reports\Smarty\ReportEnvironment).
     *
     * @param object $system Initialized legacy System instance.
     * @return array
     */
    private static function legacyEngineEnvironment($system): array
    {
        $settings = $system->settings;
        $user = $system->getCurrentUser();
        if(is_array($user)){ unset($user['ugr_Preferences'], $user['ugr_Password']); }
        // TinyMCE formats of the database as CSS rules (ReportExecute::getFontStyles)
        $tinyMce = '';
        $formats = $settings->getDatabaseSetting('TinyMCE formats');
        if(is_array($formats) && is_array($formats['formats'] ?? null)){
            foreach($formats['formats'] as $format){
                $styles = $format['styles'] ?? null;
                $classes = $format['classes'] ?? null;
                if(empty($styles) || empty($classes) || !is_array($styles)){ continue; }
                $tinyMce .= '.'.implode(', .', explode(' ', (string)$classes)).' { ';
                foreach($styles as $property => $value){
                    $tinyMce .= $property.': '.$value.'; ';
                }
                $tinyMce .= '} ';
            }
        }
        $fonts = $settings->getWebFontsLinks('ui-sans-serif');
        return array(
            'databaseName' => (string)$system->dbname(),
            'baseUrl' => defined('HEURIST_BASE_URL') ? HEURIST_BASE_URL : '',
            'baseUrlPro' => defined('HEURIST_BASE_URL_PRO') ? HEURIST_BASE_URL_PRO : '',
            'templateDir' => (string)$system->getSysDir('smarty-templates'),
            'scratchDir' => (string)$system->getSysDir('scratch'),
            'javaScriptAllowed' => (bool)$settings->isJavaScriptAllowed(),
            'fontStyles' => is_string($fonts) ? $fonts : '',
            'tinyMceStyles' => $tinyMce,
            'registeredDbId' => (string)($settings->get('sys_dbRegisteredID') ?: ''),
            'language' => (string)$system->userGetPreference('layout_language', ''),
            'currentUser' => is_array($user) ? $user : array(),
            // from this folder: HEURIST_DIR is not the Heurist folder in CLI scripts (cron)
            'languageCodesFile' => dirname(__DIR__, 2).'/hclient/assets/language-codes-active-list.json',
            'recordLink' => static function(string $reference) use ($system): string {
                return (string)$system->recordLink($reference);
            },
            'constant' => static function(string $name) use ($system) {
                return $system->getConstant($name);
            }
        );
    }

    /** Initialise shared services from explicit modern dependencies. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        SystemCode $codes,
        string $publicationDirectory = '',
        string $entityDirectory = '',
        array $reportEnvironment = array()
    )
    {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->codes = $codes;
        $this->presentations = new PresentationRecordRepository($database, $runtime, $codes);
        $this->publicationDirectory = $publicationDirectory;
        $this->entityDirectory = $entityDirectory;
        $this->reportEnvironment = $reportEnvironment;
    }

    /** Create the records query/retrieval controller. */
    public function recordQueryController(): RecordQueryController
    {
        return new RecordQueryController($this->database, $this->runtime);
    }

    /** Create the controller that cancels a running query (POST /records/cancel). */
    public function queryCancelController(): QueryCancelController
    {
        return new QueryCancelController($this->database, $this->runtime);
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

    /** Create the QuerySource/Map definition controller. */
    public function recordPresentationController(): RecordPresentationController
    {
        $maps = new MapPresentationService(
            $this->presentations, $this->runtime, new ConceptCode($this->database)
        );
        return new RecordPresentationController(
            new QuerySourcePresentationService($this->presentations), $maps
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

    /**
     * Create the reports manager controller. The Smarty engine and record writes
     * are supplied by the caller (legacy hserv implementations for now).
     */
    public function reportController(
        ReportRendererInterface $renderer,
        ReportRecordWriterInterface $writer
    ): ReportController
    {
        return new ReportController($this->reportService($renderer, $writer), $this->runtime);
    }

    /** Create the planner used by the report background jobs. */
    public function reportJobPlanner(
        ReportRendererInterface $renderer,
        ReportRecordWriterInterface $writer
    ): ReportJobPlanner
    {
        return new ReportJobPlanner(
            $this->reportService($renderer, $writer),
            $this->reportRepository(),
            $this->reportPolicy(),
            new QuerySourcePresentationService($this->presentations),
            $this->database,
            $this->runtime,
            $this->codes,
            (string)($this->reportEnvironment['generated'] ?? ''),
            (string)($this->reportEnvironment['generatedUrl'] ?? '')
        );
    }

    /**
     * Create the background job runner with the given job types.
     *
     * @param array<int,JobHandlerInterface> $handlers
     */
    public function jobRunner(array $handlers): JobRunner
    {
        $database = $this->database;
        return new JobRunner(
            new JobStore((string)($this->reportEnvironment['jobs'] ?? '')),
            $this->runtime,
            $handlers,
            $this->reportPolicy()->maxJobsPerDatabase(),
            static function(int $connectionId) use ($database): bool {
                return $database instanceof MysqlDatabase && $database->killQuery($connectionId);
            }
        );
    }

    /**
     * Create the background jobs controller (/api/{db}/jobs).
     *
     * @param array<int,JobHandlerInterface> $handlers
     */
    public function jobController(array $handlers): JobController
    {
        return new JobController($this->jobRunner($handlers), $this->runtime);
    }

    /**
     * Renderer of the new API: the srv Smarty engine. The legacy renderer given
     * by the caller (hserv) only converts templates on import/export
     * (ReportTemplateMgr is not ported).
     *
     * @param ReportRendererInterface $legacy Legacy renderer.
     * @param string|null $engine "legacy" runs the legacy engine instead (comparison tests only).
     */
    public function reportRenderer(ReportRendererInterface $legacy, ?string $engine = null): ReportRendererInterface
    {
        return $engine === 'legacy'
            ? $legacy
            : new SrvSmartyRenderer($this->smartyRunner(), $legacy);
    }

    /**
     * Background job types of reports: report-preview and report-generate.
     *
     * @return array<int,JobHandlerInterface>
     */
    public function reportJobHandlers(ReportRendererInterface $renderer, ReportRecordWriterInterface $writer): array
    {
        $planner = $this->reportJobPlanner($renderer, $writer);
        return array(
            new ReportPreviewJob($planner, $renderer),
            new ReportGenerateJob($planner, $renderer, $this->runtime->userId)
        );
    }

    /** Template runner of the srv Smarty engine. */
    public function smartyRunner(): SmartyTemplateRunner
    {
        $values = $this->reportEnvironment['engine'] ?? array();
        if(is_callable($values)){
            $values = call_user_func($values);
            $this->reportEnvironment['engine'] = $values;
        }
        $values = is_array($values) ? $values : array();
        if(empty($values['templateDir'])){
            $values['templateDir'] = (string)($this->reportEnvironment['templates'] ?? '');
        }
        $database = $this->database;
        $presentations = $this->presentations;
        $runtime = $this->runtime;
        $visibleIds = static function(array $ids) use ($database, $presentations): array {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
            $visible = array();
            foreach(array_chunk($ids, 1000) as $chunk){
                $parameters = $chunk;
                $sql = 'SELECT rec_ID FROM Records WHERE rec_ID IN ('.implode(',', array_fill(0, count($chunk), '?')).') AND ';
                $sql .= $presentations->accessCondition($parameters);
                foreach($database->fetchColumn($sql, $parameters) as $id){
                    $visible[intval($id)] = true;
                }
            }
            return array_values(array_filter($ids, static function($id) use ($visible){ return isset($visible[$id]); }));
        };
        $search = static function(string $query) use ($database, $runtime): array {
            $builder = new QueryBuilder($database);
            $decoded = json_decode($query, true);
            $request = is_array($decoded) ? $decoded : $builder->normalize($query);
            if(!is_array($request)){ return array(); }
            $service = new RecordSearchService($database, $runtime, $builder);
            return array_map('intval', $service->search(new SearchRequest($request, array('limit' => 100000)))->ids);
        };
        $codes = array();
        foreach(array('RT_RELATION', 'RT_CMS_MENU', 'DT_RELATION_TYPE', 'DT_PRIMARY_RESOURCE', 'DT_TARGET_RESOURCE',
            'DT_SHORT_SUMMARY', 'DT_START_DATE', 'DT_END_DATE', 'DT_EXTENDED_DESCRIPTION') as $name){
            $codes[$name] = $this->codes->id($name);
        }
        return new SmartyTemplateRunner(
            new ReportEnvironment($values),
            $database,
            new RecordDataService($database, $runtime),
            $visibleIds,
            $search,
            $runtime->userId,
            $codes,
            is_callable($values['constant'] ?? null) ? $values['constant'] : null
        );
    }

    /** Database connection id of the modern services (KILL QUERY on Stop). */
    public function connectionId(): int
    {
        return $this->database instanceof MysqlDatabase ? $this->database->connectionId() : 0;
    }

    private function reportService(ReportRendererInterface $renderer, ReportRecordWriterInterface $writer): ReportService
    {
        return new ReportService(
            $this->reportRepository(),
            new ReportTemplateStore((string)($this->reportEnvironment['templates'] ?? '')),
            $this->reportPolicy(),
            $renderer,
            $writer,
            (string)($this->reportEnvironment['generated'] ?? ''),
            (string)($this->reportEnvironment['generatedUrl'] ?? '')
        );
    }

    private function reportRepository(): ReportRepository
    {
        return new ReportRepository($this->database, $this->runtime, $this->codes, $this->presentations);
    }

    private function reportPolicy(): ReportPolicy
    {
        return new ReportPolicy($this->runtime, (array)($this->reportEnvironment['settings'] ?? array()));
    }
}
