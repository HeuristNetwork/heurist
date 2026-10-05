<?php
/**
* ReportGenerateJob.php - Background job "report-generate" (report file)
*
* Searches the records of a Query Source or query, runs the template on them
* and writes generated-reports/<output>.<format>: first to a temporary file,
* then renamed, so a failed run never leaves a half-written report. Used by the
* reports manager (Generate) and by cron (runReportSchedules.php). Works with
* either engine (ReportRendererInterface).
*
* Moved from hserv/report/ReportGenerateJob.php (plan 12, Phase 6).
*
* @project     Heurist academic knowledge management system
* @package     Reports\Jobs
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Jobs;

use Heurist\Jobs\JobContext;
use Heurist\Jobs\JobHandlerInterface;
use Heurist\Reports\ReportJobPlanner;
use Heurist\Reports\ReportRendererInterface;
use RuntimeException;

/** Generation of a report file. */
final class ReportGenerateJob implements JobHandlerInterface
{
    private ReportJobPlanner $planner;
    private ReportRendererInterface $renderer;
    private int $userId;

    /**
     * @param ReportJobPlanner $planner Validation, record ids and output files.
     * @param ReportRendererInterface $renderer Smarty engine.
     * @param int $userId Current user (0: cron, public records only).
     */
    public function __construct(ReportJobPlanner $planner, ReportRendererInterface $renderer, int $userId = 0)
    {
        $this->planner = $planner;
        $this->renderer = $renderer;
        $this->userId = $userId;
    }

    /** @inheritDoc */
    public function type(): string
    {
        return 'report-generate';
    }

    /** @inheritDoc */
    public function limitSeconds(): int
    {
        return $this->planner->policy()->generateLimitSeconds();
    }

    /** One generation per user; a second one is refused. */
    public function replacesPrevious(): bool
    {
        return false;
    }

    /**
     * @param array $params {schedule} or {report, querySource|query, format?, suffix?}
     * @return array
     */
    public function prepare(array $params): array
    {
        return $this->planner->prepareGenerate($params);
    }

    /**
     * @param array $params Normalized parameters (templateFile, query, output, format, scheduleId).
     * @param JobContext $context Running job.
     * @return array Generated file: file, url, size, records, scheduleId.
     */
    public function run(array $params, JobContext $context): array
    {
        $context->progress(0, 0, 'searching records');
        $context->addConnectionId($this->planner->connectionId());
        $ids = $this->planner->searchIds($params['query']);
        $context->check();
        if(empty($ids)){
            throw new RuntimeException('The query finds no records'
                .($this->userId > 0 ? '' : ' (only public records are used)'));
        }
        $context->progress(0, count($ids), 'running report');

        $result = $this->renderer->renderIds(array('file' => (string)$params['templateFile']), $ids, array(
            'purpose' => 'file',
            'mode' => (string)$params['format']
        ), $context);
        if($result['error'] !== null && $result['error'] !== ''){
            throw new RuntimeException($result['error']);
        }

        $folder = $this->planner->generatedDirectory();
        if($folder === '' || (!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder))){
            throw new RuntimeException('Failed to create folder for generated reports');
        }
        $final = $this->planner->generatedFile($params['output'], $params['format']);
        $temp = $this->planner->generatedFile('job-tmp-'.substr($context->id(), 0, 12), $params['format']);
        try{
            if(@file_put_contents($temp['path'], $result['output']) === false){
                throw new RuntimeException('The report file was not written. Check permissions for the generated-reports folder');
            }
            if(is_file($final['path']) && !@unlink($final['path'])){
                throw new RuntimeException('Cannot replace '.$final['file'].'. Check permissions for the generated-reports folder');
            }
            if(!@rename($temp['path'], $final['path'])){
                throw new RuntimeException('Cannot write '.$final['file'].'. Check permissions for the generated-reports folder');
            }
        }finally{
            if(is_file($temp['path'])){
                @unlink($temp['path']);
            }
        }

        $context->progress(count($ids), count($ids), 'done');
        return array(
            'file' => $final['file'],
            'url' => $final['url'],
            'size' => intval(filesize($final['path'])),
            'records' => count($ids),
            'scheduleId' => $params['scheduleId'] ?? null
        );
    }
}
