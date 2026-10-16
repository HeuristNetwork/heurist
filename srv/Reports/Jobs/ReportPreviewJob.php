<?php
/**
* ReportPreviewJob.php - Background job "report-preview" (test run of a template)
*
* Runs a saved template or the unsaved text of the editor on up to
* ReportPolicy::TEST_RECORD_LIMIT records; the HTML output is the job result
* (GET /jobs/{id}/result). Works with either engine (ReportRendererInterface).
* A new test of the user replaces the older one.
*
* Moved from hserv/report/ReportPreviewJob.php (plan 12, Phase 6).
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

/** Test run of a report template. */
final class ReportPreviewJob implements JobHandlerInterface
{
    private ReportJobPlanner $planner;
    private ReportRendererInterface $renderer;

    /**
     * @param ReportJobPlanner $planner Validation and limits.
     * @param ReportRendererInterface $renderer Smarty engine.
     */
    public function __construct(ReportJobPlanner $planner, ReportRendererInterface $renderer)
    {
        $this->planner = $planner;
        $this->renderer = $renderer;
    }

    /** @inheritDoc */
    public function type(): string
    {
        return 'report-preview';
    }

    /** @inheritDoc */
    public function limitSeconds(): int
    {
        return $this->planner->policy()->previewLimitSeconds();
    }

    /** A new test run of the user stops the older one. */
    public function replacesPrevious(): bool
    {
        return true;
    }

    /**
     * @param array $params {report?, body?, ids, replevel?}
     * @return array
     */
    public function prepare(array $params): array
    {
        return $this->planner->preparePreview($params);
    }

    /**
     * @param array $params Normalized parameters (templateFile|body, ids, replevel).
     * @param JobContext $context Running job.
     * @return array Record count and engine error (records, error).
     */
    public function run(array $params, JobContext $context): array
    {
        $ids = array_map('intval', (array)$params['ids']);
        $context->progress(0, count($ids), 'running report');
        $context->addConnectionId($this->planner->connectionId());
        $source = !empty($params['body'])
            ? array('body' => (string)$params['body'])
            : array('file' => (string)$params['templateFile']);

        $result = $this->renderer->renderIds($source, $ids, array(
            'purpose' => 'preview',
            'mode' => 'html',
            'replevel' => intval($params['replevel'] ?? 0)
        ), $context);

        $context->writeResult($result['output']);
        $context->progress(count($ids), count($ids), 'done');
        return array('records' => count($ids), 'error' => $result['error']);
    }
}
