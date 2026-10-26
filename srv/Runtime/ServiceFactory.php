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
use Heurist\Controller\FileController;
use Heurist\Controller\GraphController;
use Heurist\Controller\JobController;
use Heurist\Controller\MapDataController;
use Heurist\Controller\PublicationController;
use Heurist\Controller\QueryCancelController;
use Heurist\Controller\RecordEditController;
use Heurist\Controller\RecordPresentationController;
use Heurist\Controller\RecordQueryController;
use Heurist\Controller\ReportController;
use Heurist\Controller\SystemQueryController;
use Heurist\Controller\TimeDataController;
use Heurist\Database\DatabaseFactory;
use Heurist\Database\DatabaseInterface;
use Heurist\Database\MysqlDatabase;
use Heurist\Database\QueryTrace;
use Heurist\Database\WritableDatabaseInterface;
use Heurist\Jobs\JobHandlerInterface;
use Heurist\Jobs\JobRunner;
use Heurist\Jobs\JobStore;
use Heurist\Publication\PublicationService;
use Heurist\Records\Map\MapFeatureService;
use Heurist\Records\Export\ExportJob;
use Heurist\Records\Export\ExportPlanner;
use Heurist\Records\Export\ExportService;
use Heurist\Records\Export\ExportSettings;
use Heurist\Records\Presentation\QuerySourcePresentationService;
use Heurist\Records\Presentation\MapPresentationService;
use Heurist\Records\Presentation\PresentationRecordRepository;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Definitions\DefinitionSnapshotService;
use Heurist\Records\Edit\RecordDeleteService;
use Heurist\Records\Edit\RecordDuplicateService;
use Heurist\Records\Edit\RecordPermissionService;
use Heurist\Records\Edit\RecordSaveService;
use Heurist\Records\Store\RecordAccess;
use Heurist\Records\Store\RecordRepository;
use Heurist\Records\Store\RecordSchema;
use Heurist\Records\Store\RecordStore;
use Heurist\Files\FileContent;
use Heurist\Files\FilePaths;
use Heurist\Files\FileRecordMapping;
use Heurist\Files\FileStorage;
use Heurist\Files\FileUrlInspector;
use Heurist\Files\FileWriteService;
use Heurist\Files\LegacyFileRows;
use Heurist\Files\LegacyFileStore;
use Heurist\Files\RecordFileRows;
use Heurist\Files\RecordFileStore;
use Heurist\Files\Upload\ChunkedUpload;
use Heurist\Records\Edit\RecordTitle;
use Heurist\Records\Edit\TitleMask;
use Heurist\Records\Edit\CalculatedFields;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Edit\RecordValidator;
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
use Heurist\Utilities\LanguageCodes;
use Heurist\Controller\SystemEditController;
use Heurist\System\Bookmarks\BookmarkRecordMapping;
use Heurist\System\Bookmarks\BookmarkWriteService;
use Heurist\System\Reminders\ReminderRecordMapping;
use Heurist\System\Reminders\ReminderWriteService;
use Heurist\System\Users\UserRecordMapping;
use Heurist\System\Users\UserWriteService;
use Heurist\System\Groups\GroupRecordMapping;
use Heurist\System\Groups\GroupWriteService;
use Heurist\System\Entity\EntityImageService;
use Heurist\Files\EntityImageStorage;
use Heurist\System\Tags\TagRecordMapping;
use Heurist\System\Tags\TagWriteService;
use Heurist\Controller\WebsiteController;
use Heurist\Website\CmsRecordCheck;
use Heurist\Website\PageRenderer;
use Heurist\Website\SiteUrls;
use Heurist\Website\WebsiteDefinitionsInterface;
use Heurist\Website\WebsiteRepository;
use Heurist\Website\WebsiteService;
use Heurist\Website\WebsiteSetup;

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
    /** @var (\Closure():array)|null Database setting "Languages" (translations), read when needed. */
    private ?\Closure $translationLanguages = null;
    /** Whether the installation has a DeepL key: the legacy translator works (plan 25, meta.translator). */
    private bool $translatorAvailable = false;
    /** @var array{root?:string,quota?:\Closure} File store root and disk quota (plan 20, /files writes). */
    private array $fileEnvironment = array();
    /** @var (\Closure(array):void)|null Sends a reminder now with the legacy mailer (plan 20, §10.9). */
    private ?\Closure $reminderSender = null;

    /** @var array<string,\Closure> Legacy code of the user and group writes (plan 20, 4d): hash, delete, notify. */
    private array $userGroupLegacy = array();

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
            'RT_RELATION', 'RT_CMS_PAGE', 'DT_RELATION_TYPE', 'DT_PRIMARY_RESOURCE', 'DT_TARGET_RESOURCE',
            // record export (plan 13): KML time span
            'DT_DATE',
            // record editing (plan 14)
            'RT_CMS_HOME', 'DT_WORKFLOW_STAGE', 'DT_CMS_EXTFILES',
            'DT_CMS_SCRIPT', 'DT_CMS_CSS', 'DT_CMS_HEADER', 'DT_CMS_FOOTER',
            // virtual parents (plan 26): title masks, Smarty fN / fNs
            'DT_PARENT_ENTITY',
            // website format v4 (plan 27a): page translations, joined on read
            'DT_CMS_PAGE_TRANSLATIONS'
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
        $exportSettings = $system->settings->getDatabaseSetting('Export');
        $factory = new self(
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
                'exportSettings' => is_array($exportSettings) ? $exportSettings : array(),
                'javaScriptAllowed' => static function() use ($system): bool {
                    return (bool)$system->settings->isJavaScriptAllowed();
                },
                // values of the srv Smarty engine, read only when a report runs
                'engine' => static function() use ($system): array {
                    return self::legacyEngineEnvironment($system);
                }
            )
        );
        // languages offered for translations (record editor): the database setting (db_languages.json)
        $factory->translationLanguages = static function() use ($system): array {
            $languages = $system->settings->getDatabaseSetting('Languages');
            return is_array($languages) ? $languages : array();
        };
        // machine translation: the legacy translator (usr_info.php translate_string) has a DeepL key
        // (as System.php sysinfo api_Translator; plan 25)
        $factory->translatorAvailable = !empty($GLOBALS['accessToken_DeepLAPI'] ?? null);
        // file store of the database and its disk quota (legacy fileUpload.php: 10 GB when not set)
        $factory->fileEnvironment = array(
            'root' => (string)$system->getSysDir(),
            'quota' => static function() use ($system): int {
                $quota = intval($system->settings->getDiskQuota());
                return $quota > 0 ? $quota : 10737418240;
            }
        );
        // "Send now" of a reminder: the legacy mailer (DbUsrReminders, as "Send immediately"; not saved)
        $factory->reminderSender = static function(array $columns) use ($system): void {
            $reminders = new \hserv\entity\DbUsrReminders($system, array('fields' => $columns + array('rem_ID' => 0)));
            if($reminders->batch_action() === false){
                $error = $system->getError();
                throw new \RuntimeException(is_array($error) ? (string)($error['message'] ?? '') : '');
            }
        };
        // users and groups (plan 20, 4d): the legacy password hash, delete (with its checks and
        // clean-up) and e-mails of DbSysUsers / DbSysGroups and dbsUsersGroups.php
        $legacyError = static function() use ($system): string {
            $error = $system->getError();
            return is_array($error) ? (string)($error['message'] ?? '') : '';
        };
        $factory->userGroupLegacy = array(
            'hash' => static function(string $password): string {
                class_exists('\hserv\entity\DbSysUsers'); // loads dbsUsersGroups.php (hash_it)
                return (string)\hash_it($password);
            },
            'delete' => static function(string $type, int $id) use ($system, $legacyError): void {
                // the legacy delete removes the entity image (as LookupController, DeepL)
                if(!defined('HEURIST_FILESTORE_ROOT')){ define('HEURIST_FILESTORE_ROOT', $system->getFileStoreRootFolder()); }
                $entity = $type === 'group'
                    ? new \hserv\entity\DbSysGroups($system, array('ugr_ID' => $id))
                    : new \hserv\entity\DbSysUsers($system, array('ugr_ID' => $id));
                if($entity->delete() === false){ throw new \RuntimeException($legacyError()); }
            },
            'notify' => static function(int $userId, ?string $password, bool $approval) use ($system, $legacyError): void {
                class_exists('\hserv\entity\DbSysUsers');
                if(!\user_EmailApproval($system, $userId, (string)$password, $approval)){ throw new \RuntimeException($legacyError()); }
                \user_SyncCommonCredentials($system, $userId, $approval);
            }
        );
        return $factory;
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
            'languageCodesFile' => self::languageCodesFile(),
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

    /**
     * Create the /files controller (plan 20). Files are read from fileRecords/fileRecDetails
     * when the database has these tables (after the switch), else from recUploadedFiles.
     */
    public function fileController(?FilePaths $paths = null, ?callable $fetch = null): FileController
    {
        $fileRecords = new RecordRepository($this->database, new RecordSchema(RecordSchema::FILE_TABLES));
        $recordStore = $fileRecords->tablesExist();
        $store = $recordStore
            ? new RecordFileStore($this->database, $this->runtime)
            : new LegacyFileStore($this->database, $this->runtime);
        $root = $paths !== null ? $paths->root : (string)($this->fileEnvironment['root'] ?? '');
        if($root === '' || !($this->database instanceof WritableDatabaseInterface)){
            return new FileController($store, $this->runtime); // read only
        }
        $paths = $paths ?? new FilePaths($root);
        $definitions = DefinitionLookup::shared($this->database);
        $files = FileRepository::shared($this->database);
        $mapping = new FileRecordMapping($definitions, $files);
        $rows = $recordStore ? new RecordFileRows($this->database, $mapping) : new LegacyFileRows($this->database);
        $storage = new FileStorage($paths);
        $quota = $this->fileEnvironment['quota'] ?? null;
        $uploads = new ChunkedUpload($paths->uploadSessions(), $this->runtime->userId, $files->mimeTypes(),
            $quota === null ? null : static function() use ($quota, $storage): array {
                return array($quota(), $storage->usage());
            }, self::iniBytes((string)ini_get('post_max_size')));
        $writer = new FileWriteService($rows, $mapping, $storage, $uploads, new FileUrlInspector($files->mimeTypes(), $fetch),
            RecordRepository::shared($this->database), $definitions, $this->runtime, $files->mimeTypes());
        return new FileController($store, $this->runtime, $writer, new FileContent($rows, $storage, $this->runtime));
    }

    /** Bytes of a php.ini size ("40M", "2G"); 0 when not set. */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if($value === ''){ return 0; }
        $number = intval($value);
        switch(strtolower(substr($value, -1))){
            case 'g': return $number * 1073741824;
            case 'm': return $number * 1048576;
            case 'k': return $number * 1024;
            default: return $number;
        }
    }

    /**
     * Create the record write controller (plan 14): POST/PUT/PATCH/DELETE /records/{id}.
     */
    public function recordEditController(): RecordEditController
    {
        if(!($this->database instanceof WritableDatabaseInterface)){
            throw new \LogicException('The database adapter does not support writes');
        }
        $definitions = DefinitionLookup::shared($this->database);
        $languages = new LanguageCodes(self::languageCodesFile());
        $store = new RecordStore($this->database);
        $permissions = new RecordPermissionService($this->database, $this->runtime);
        $title = new RecordTitle($languages, $this->codes->id('DT_NAME'));
        $titleMask = new TitleMask($definitions, $store->records(), FileRepository::shared($this->database), $languages,
            $this->codes->id('DT_PARENT_ENTITY'));
        $validator = new RecordValidator($this->database, $definitions, $languages, array(
            'name' => $this->codes->id('DT_NAME'),
            'shortSummary' => $this->codes->id('DT_SHORT_SUMMARY'),
            'extendedDescription' => $this->codes->id('DT_EXTENDED_DESCRIPTION'),
            'cmsPage' => $this->codes->id('RT_CMS_PAGE'),
            'cmsHome' => $this->codes->id('RT_CMS_HOME'),
            'cmsScript' => $this->codes->id('DT_CMS_SCRIPT'),
            'cmsCss' => $this->codes->id('DT_CMS_CSS'),
            'cmsHeader' => $this->codes->id('DT_CMS_HEADER'),
            'cmsFooter' => $this->codes->id('DT_CMS_FOOTER'),
            'cmsTranslations' => $definitions->localId('dty', '2-1202')
        ), null, $this->javaScriptAllowed());
        // website format v4 (plan 27a): configuration and hosts of website and page records
        $validator->addRecordCheck(new CmsRecordCheck(
            (new WebsiteRepository($this->database, $this->runtime))->codes(), $this->runtime->baseUrl));
        $calculated = new CalculatedFields($definitions, $store, function(string $formula, int $recordId): array {
            return $this->smartyRunner()->run(array('body' => $formula), array($recordId),
                array('mode' => 'txt', 'purpose' => 'render', 'record' => true));
        }, $validator);
        return new RecordEditController(
            new RecordSaveService($store, $validator, $permissions, $definitions, $this->runtime, $title, array(
                'cmsPage' => $this->codes->id('RT_CMS_PAGE'),
                'workflowStage' => $this->codes->id('DT_WORKFLOW_STAGE')
            ), $titleMask, $calculated),
            new RecordDeleteService($store, $permissions, $definitions, $this->runtime),
            new RecordDuplicateService($store, $permissions, $definitions, $this->runtime, $title, $languages, array(
                'name' => $this->codes->id('DT_NAME'),
                'cmsPage' => $this->codes->id('RT_CMS_PAGE')
            ), $titleMask, $validator),
            $this->recordQueryController(),
            $this->runtime
        );
    }

    /** Language codes list of value prefixes ("fre:") of this installation. */
    public static function languageCodesFile(): string
    {
        // from this folder: HEURIST_DIR is not the Heurist folder in CLI scripts (cron)
        return dirname(__DIR__, 2).'/hclient/assets/language-codes-active-list.json';
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

    /** Authorization is obtained from the server environment, never the request body. */
    private function javaScriptAllowed(): bool
    {
        $allowed = $this->reportEnvironment['javaScriptAllowed'] ?? false;
        return is_callable($allowed) ? (bool)$allowed() : $allowed === true;
    }

    /** Create the database-definitions snapshot controller (/api/{db}/def). */
    public function definitionController(): DefinitionController
    {
        return new DefinitionController(
            new DefinitionSnapshotService($this->database, $this->runtime, $this->entityDirectory,
                $this->translationLanguages ? ($this->translationLanguages)() : array(), $this->javaScriptAllowed(),
                $this->translatorAvailable),
            $this->runtime
        );
    }

    /** Create the mapped filter/user query and retrieval controller. */
    public function systemQueryController(): SystemQueryController
    {
        return new SystemQueryController(
            new SystemQueryService($this->database, $this->runtime, null, $this->entityImageStorage()),
            $this->runtime
        );
    }

    /**
     * Create the writes of /sys (plan 20, §10.4): tags (4a), bookmarks and reminders (4b). Without
     * a writable database the controller refuses writes (405).
     */
    public function systemEditController(): SystemEditController
    {
        $query = new SystemQueryService($this->database, $this->runtime, null, $this->entityImageStorage());
        if(!($this->database instanceof WritableDatabaseInterface)){
            return new SystemEditController($query, $this->runtime);
        }
        $definitions = DefinitionLookup::shared($this->database);
        $users = new UserWriteService($this->database, $this->runtime, new UserRecordMapping($definitions), $this->userGroupLegacy);
        $groups = new GroupWriteService($this->database, $this->runtime, new GroupRecordMapping($definitions),
            $this->userGroupLegacy['delete'] ?? null);
        return new SystemEditController($query, $this->runtime, array(
            'tag' => new TagWriteService($this->database, $this->runtime, new TagRecordMapping($definitions)),
            'bookmark' => new BookmarkWriteService($this->database, $this->runtime, new BookmarkRecordMapping($definitions)),
            'reminder' => new ReminderWriteService($this->database, $this->runtime, new ReminderRecordMapping($definitions),
                $this->reminderSender),
            'user' => $users,
            'group' => $groups
        ), null, null, $this->entityImages($users, $groups));
    }

    /** The legacy entity image folders of users and groups; null without a file store. */
    private function entityImageStorage(): ?EntityImageStorage
    {
        $root = (string)($this->fileEnvironment['root'] ?? '');
        return $root === '' ? null : new EntityImageStorage($root.'entity/');
    }

    /**
     * Images of users and groups (plan 20, 4d): the legacy entity folders, uploads as /files.
     * Null without a file store.
     */
    public function entityImages(UserWriteService $users, GroupWriteService $groups, ?FilePaths $paths = null): ?EntityImageService
    {
        $root = $paths !== null ? $paths->root : (string)($this->fileEnvironment['root'] ?? '');
        if($root === ''){ return null; }
        $paths = $paths ?? new FilePaths($root);
        $uploads = new ChunkedUpload($paths->uploadSessions(), $this->runtime->userId, FileRepository::shared($this->database)->mimeTypes(),
            null, self::iniBytes((string)ini_get('post_max_size')));
        return new EntityImageService(new EntityImageStorage($paths->root.'entity/'), $uploads, $this->runtime, array(
            'user' => static function(int $id) use ($users): void { $users->assertCanEditImage($id); },
            'group' => static function(int $id) use ($groups): void { $groups->assertCanEditImage($id); }
        ));
    }

    /** Create the QuerySource/Map definition controller. */
    public function recordPresentationController(): RecordPresentationController
    {
        $maps = new MapPresentationService(
            $this->presentations, $this->runtime, DefinitionLookup::shared($this->database)
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

    /**
     * Create the controller of the v4 websites (plan 27a): live pages, instance frames,
     * /api/{db}/website/{id}/check and /website/setup.
     *
     * @param WebsiteDefinitionsInterface|null $definitions Definition writer (hserv) for setup.
     */
    public function websiteController(?WebsiteDefinitionsInterface $definitions = null): WebsiteController
    {
        $repository = new WebsiteRepository($this->database, $this->runtime);
        $languages = new LanguageCodes(self::languageCodesFile());
        $environment = array(
            'database' => $this->runtime->databaseName,
            'baseUrl' => $this->runtime->baseUrl,
            'javaScriptAllowed' => $this->javaScriptAllowed()
        );
        $renderer = static function(bool $pretty) use ($repository, $languages, $environment): PageRenderer {
            return new PageRenderer($repository->codes(), $languages, $environment
                + array('urls' => new SiteUrls($environment['database'], $environment['baseUrl'], $pretty)));
        };
        return new WebsiteController(
            new WebsiteService($repository, $languages, $this->runtime->baseUrl),
            $renderer,
            $this->runtime,
            $definitions !== null ? new WebsiteSetup($definitions, $this->runtime) : null
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

    /**
     * Background job type of record export (plan 13): export.
     *
     * @return array<int,JobHandlerInterface>
     */
    public function exportJobHandlers(): array
    {
        $planner = new ExportPlanner(
            $this->database,
            $this->runtime,
            new ExportSettings((array)($this->reportEnvironment['exportSettings'] ?? array())),
            $this->visibleIdsFilter()
        );
        $service = new ExportService($this->database, $this->runtime, $planner, array(
            'date' => $this->codes->id('DT_DATE'),
            'start' => $this->codes->id('DT_START_DATE'),
            'end' => $this->codes->id('DT_END_DATE'),
            'relationType' => $this->codes->id('DT_RELATION_TYPE')
        ));
        return array(new ExportJob($service, function(): int { return $this->connectionId(); }));
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
        $runtime = $this->runtime;
        $visibleIds = $this->visibleIdsFilter();
        $search = static function(string $query) use ($database, $runtime): array {
            $builder = new QueryBuilder($database);
            $decoded = json_decode($query, true);
            $request = is_array($decoded) ? $decoded : $builder->normalize($query);
            if(!is_array($request)){ return array(); }
            $service = new RecordSearchService($database, $runtime, $builder);
            return array_map('intval', $service->search(new SearchRequest($request, array('limit' => 100000)))->ids);
        };
        $codes = array();
        foreach(array('RT_RELATION', 'RT_CMS_PAGE', 'DT_RELATION_TYPE', 'DT_PRIMARY_RESOURCE', 'DT_TARGET_RESOURCE',
            'DT_SHORT_SUMMARY', 'DT_START_DATE', 'DT_END_DATE', 'DT_EXTENDED_DESCRIPTION', 'DT_PARENT_ENTITY') as $name){
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

    /** Filter of record ids by the access rules of the current user (order kept). */
    private function visibleIdsFilter(): callable
    {
        $records = RecordRepository::shared($this->database);
        $access = RecordAccess::fromRuntime($this->runtime);
        return static function(array $ids) use ($records, $access): array {
            return $records->visibleIds($ids, $access);
        };
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
            (string)($this->reportEnvironment['generatedUrl'] ?? ''),
            $this->reportEnvironment['javaScriptAllowed'] ?? false
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
