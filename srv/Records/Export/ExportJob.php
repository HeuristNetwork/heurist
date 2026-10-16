<?php
/**
* ExportJob.php - Background job "export" (record export into a file)
*
* prepare() validates the parameters (ExportRequest); run() exports into the
* job's result folder (JobContext::resultDirectory). The file is downloaded with
* GET /api/{db}/jobs/{id}/result by the user who started the job, and removed
* with the job after 24 hours (keepSeconds). One export per user at a time; a
* new export is refused while the user's export results take more than 100 MB.
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export;

use Heurist\Jobs\JobContext;
use Heurist\Jobs\JobHandlerInterface;

/** Record export job. */
final class ExportJob implements JobHandlerInterface
{
    private const MIME = array(
        'csv' => 'text/csv; charset=utf-8', 'tsv' => 'text/tab-separated-values; charset=utf-8',
        'json' => 'application/json', 'geojson' => 'application/geo+json',
        'kml' => 'application/vnd.google-earth.kml+xml', 'xml' => 'text/xml; charset=utf-8',
        'gexf' => 'application/xml', 'zip' => 'application/zip'
    );

    private ExportService $service;
    /** @var callable fn(): int database connection id (KILL QUERY on Stop) */
    private $connectionId;

    /**
     * @param ExportService $service Export of records into a file.
     * @param callable $connectionId Returns the connection id of the export queries.
     */
    public function __construct(ExportService $service, callable $connectionId)
    {
        $this->service = $service;
        $this->connectionId = $connectionId;
    }

    /** @inheritDoc */
    public function type(): string
    {
        return 'export';
    }

    /** @inheritDoc */
    public function limitSeconds(): int
    {
        return $this->service->planner()->settings()->timeLimitSeconds();
    }

    /** Export results are kept 24 hours (JobRunner removes older ones). */
    public function keepSeconds(): int
    {
        return 86400;
    }

    /** A user's export results may take 100 MB; a new export is refused above that. */
    public function maxResultBytes(): int
    {
        return 100 * 1024 * 1024;
    }

    /** One export per user; a second one is refused. */
    public function replacesPrevious(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function prepare(array $params): array
    {
        $request = new ExportRequest($params);
        $title = 'Export '.strtoupper($request->format === 'xml' ? 'hml' : $request->format)
            .($request->title !== '' ? ': '.$request->title : '');
        return array('title' => $title, 'params' => $request->toArray());
    }

    /** @inheritDoc */
    public function run(array $params, JobContext $context): array
    {
        $context->addConnectionId(intval(call_user_func($this->connectionId)));
        $request = new ExportRequest($params);
        $result = $this->service->run(
            $request,
            $context->resultDirectory(),
            static function() use ($context): void { $context->check(); },
            static function(int $done, int $total, string $message) use ($context): void {
                $context->progress($done, $total, $message);
            }
        );
        $extension = strtolower(pathinfo($result['file'], PATHINFO_EXTENSION));
        return array(
            'file' => $result['file'],
            'size' => $result['size'],
            'records' => $result['records'],
            'format' => $result['format'],
            'rectypes' => (object)$result['rectypes'],
            'mime' => self::MIME[$extension] ?? 'application/octet-stream',
            'download' => true
        );
    }
}
