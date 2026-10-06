<?php
/**
* SmartyTemplateRunner.php - Runs a report template on records (srv engine)
*
* Replaces ReportExecute::execute / executeTemplate for the new API:
* - preview: a test run in the template editor (template file or unsaved body);
* - file: a generated report (the caller writes the file);
* - render: one record as part of a page (record cards, popups).
*
* The template gets $heurist (TemplateApi), $results (record ids) and
* $template_file. Only records the user may view are used. Errors of the engine
* are returned as output (red text in html) and as `error`. JobInterruptedException
* (Stop, time limit) is passed on.
*
* @project     Heurist academic knowledge management system
* @package     Reports\Smarty
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

use Heurist\Database\DatabaseInterface;
use Heurist\Jobs\JobInterruptedException;
use Heurist\Records\Data\RecordDataService;
use Throwable;

/** Executes templates with the srv data services. */
final class SmartyTemplateRunner
{
    /** Output modes. */
    public const MODES = array('html', 'js', 'txt', 'csv', 'xml', 'json');

    private ReportEnvironment $environment;
    private DatabaseInterface $database;
    private RecordDataService $data;
    /** @var callable fn(array $ids): array */
    private $visibleIds;
    /** @var callable fn(string $query): array */
    private $search;
    /** @var callable|null fn(string $name): mixed */
    private $constants;
    private int $userId;
    /** @var array<string,int> */
    private array $codes;

    /**
     * @param ReportEnvironment $environment Installation values.
     * @param DatabaseInterface $database Database of the report.
     * @param RecordDataService $data Batched record loading.
     * @param callable $visibleIds fn(array $ids): array - ids the user may view.
     * @param callable $search fn(string $query): array - visible ids of a query.
     * @param int $userId Current user.
     * @param array<string,int> $codes RT_/DT_ ids (relationships, CMS menu).
     * @param callable|null $constants fn(string $name): mixed - |constant and $heurist->constant().
     */
    public function __construct(
        ReportEnvironment $environment,
        DatabaseInterface $database,
        RecordDataService $data,
        callable $visibleIds,
        callable $search,
        int $userId,
        array $codes = array(),
        ?callable $constants = null
    )
    {
        $this->environment = $environment;
        $this->database = $database;
        $this->data = $data;
        $this->visibleIds = $visibleIds;
        $this->search = $search;
        $this->userId = $userId;
        $this->codes = $codes;
        $this->constants = $constants;
    }

    /**
     * Run a template.
     *
     * @param array $source ['file' => template file name] or ['body' => template text].
     * @param array $ids Record ids (in report order).
     * @param array $options mode (html), purpose (preview|file|render), replevel (0-3).
     * @param callable|null $tick fn(int $done, int $total): void - progress; may throw to stop.
     * @return array{output:string,error:?string,records:int}
     */
    public function run(array $source, array $ids, array $options = array(), ?callable $tick = null): array
    {
        $mode = (string)($options['mode'] ?? 'html');
        if(!in_array($mode, self::MODES, true)){ $mode = 'html'; }
        $purpose = (string)($options['purpose'] ?? 'preview');
        $replevel = max(0, min(3, intval($options['replevel'] ?? 0)));
        $sanitizer = new OutputSanitizer($this->environment);

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        $visible = array_flip(array_map('intval', call_user_func($this->visibleIds, $ids)));
        $ids = array_values(array_filter($ids, static function($id) use ($visible){ return isset($visible[$id]); }));
        if(empty($ids)){
            $message = $purpose === 'preview'
                ? 'Search records to see template output'
                : 'Note: There are no records in this view. The URL will only show records to which the viewer has access. '
                    .'Unless you are logged in to the database, you can only see records which are marked as Public visibility';
            return $this->failure($message, $mode, $purpose, $sanitizer, 0);
        }

        $languages = new LanguageCodes($this->environment->languageCodesFile);
        $api = $this->templateApi($languages);
        $formatter = new ValueFormatter($this->environment);

        $displayErrors = ini_get('display_errors');
        try{
            $smarty = (new SmartyEngineFactory($this->environment, $this->database))
                ->create($api, $formatter, new TemplateModifiers($api, $languages));
            $this->errorReporting($smarty, $replevel);
            if(isset($source['body'])){
                $template = 'string:'.(string)$source['body'];
                $templateFile = '';
            }else{
                $templateFile = (string)($source['file'] ?? '');
                if($templateFile === '' || basename($templateFile) !== $templateFile
                    || !is_file($this->environment->templateDir.$templateFile)){
                    return $this->failure('Template file does not exist', $mode, $purpose, $sanitizer, count($ids));
                }
                $template = $templateFile;
            }
            $api->startRun($ids, $tick);
            $smarty->assign('heurist', $api);
            $smarty->assign('results', $ids);
            $smarty->assign('template_file', $templateFile);
            $output = $smarty->fetch($template);
        }catch(JobInterruptedException $exception){
            throw $exception;
        }catch(Throwable $exception){
            $previous = $exception->getPrevious();
            if($previous instanceof JobInterruptedException){
                throw $previous;
            }
            return $this->failure('Exception on execution: '.$exception->getMessage(), $mode, $purpose, $sanitizer, count($ids));
        }finally{
            ini_set('display_errors', $displayErrors === false ? '0' : (string)$displayErrors);
        }

        return array(
            'output' => $sanitizer->process((string)$output, $mode, $purpose === 'render'),
            'error' => null,
            'records' => count($ids)
        );
    }

    /**
     * A new $heurist object (also used by the engine comparison test).
     *
     * @param LanguageCodes|null $languages Language codes (read from the environment when null).
     * @return TemplateApi
     */
    public function templateApi(?LanguageCodes $languages = null): TemplateApi
    {
        $languages = $languages ?? new LanguageCodes($this->environment->languageCodesFile);
        $definitions = new ReportDefinitions($this->database, $this->environment->registeredDbId);
        $assembler = new ReportRecordAssembler($this->data, $this->database, $definitions, $this->environment,
            $languages, $this->visibleIds, $this->userId, $this->codes);
        return new TemplateApi(array(
            'assembler' => $assembler,
            'definitions' => $definitions,
            'environment' => $this->environment,
            'languages' => $languages,
            'database' => $this->database,
            'data' => $this->data,
            'search' => $this->search,
            'visibleIds' => $this->visibleIds,
            'constants' => $this->constants,
            'codes' => $this->codes
        ));
    }

    /** Error levels of a test run (ReportExecute::setupErrorReporting). */
    private function errorReporting($smarty, int $replevel): void
    {
        $smarty->debugging = false;
        $smarty->setErrorReporting(0);
        if($replevel === 1 || $replevel === 2){
            ini_set('display_errors', '1');
            $smarty->setErrorReporting($replevel === 2 ? (E_ALL & ~E_NOTICE) : E_NOTICE);
        }else{
            ini_set('display_errors', '0');
            $smarty->debugging = $replevel === 3;
        }
        if($replevel > 0){
            $smarty->setDebugTemplate(__DIR__.'/debug_html.tpl');
        }
    }

    /** Result of a run that could not produce output. */
    private function failure(string $message, string $mode, string $purpose, OutputSanitizer $sanitizer, int $records): array
    {
        $text = $mode === 'html' ? '<span style="color:#ff0000;font-weight:bold">'.htmlspecialchars($message).'</span>' : $message;
        return array(
            'output' => $sanitizer->process($text, $mode, $purpose === 'render'),
            'error' => $message,
            'records' => $records
        );
    }
}
