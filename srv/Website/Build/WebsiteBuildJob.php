<?php
/**
* WebsiteBuildJob.php - Background job "website-build" (plan 27b §7)
*
* prepare() checks that the current user may edit the v4 website or standalone
* page (WebsiteEditService::buildable); run() writes the pages with guest rights
* (WebsiteBuilder) and zips them into the job's result folder. The zip is
* downloaded with GET /api/{db}/jobs/{id}/result by the user who started the
* job and removed with the job after 24 hours. One build per user at a time.
*
* @project     Heurist academic knowledge management system
* @package     Website\Build
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website\Build;

use Heurist\Jobs\JobContext;
use Heurist\Jobs\JobHandlerInterface;
use Heurist\Website\WebsiteEditService;
use Heurist\Website\WebsiteException;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;

/** Website build job. */
final class WebsiteBuildJob implements JobHandlerInterface
{
    public const TYPE = 'website-build';

    private WebsiteEditService $sites;
    private WebsiteBuilder $builder;

    /**
     * @param WebsiteEditService $sites Rights of the current user (prepare).
     * @param WebsiteBuilder $builder Builder reading with guest rights (run).
     */
    public function __construct(WebsiteEditService $sites, WebsiteBuilder $builder)
    {
        $this->sites = $sites;
        $this->builder = $builder;
    }

    /** @inheritDoc */
    public function type(): string
    {
        return self::TYPE;
    }

    /** @inheritDoc */
    public function limitSeconds(): int
    {
        return 600;
    }

    /** Builds are kept 24 hours (JobRunner removes older ones). */
    public function keepSeconds(): int
    {
        return 86400;
    }

    /** One build per user; a second one is refused. */
    public function replacesPrevious(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function prepare(array $params): array
    {
        $id = intval($params['id'] ?? 0);
        if($id < 1){ throw new InvalidArgumentException('Website id is required'); }
        try{
            $summary = $this->sites->buildable($id);
        }catch(WebsiteException $exception){
            if($exception->status() === 404){ throw new OutOfBoundsException($exception->getMessage()); }
            if($exception->status() === 403){ throw new DomainException($exception->getMessage()); }
            throw new InvalidArgumentException($exception->getMessage());
        }
        return array('title' => 'Generate website: '.$summary['title'], 'params' => array('id' => $id));
    }

    /** @inheritDoc */
    public function run(array $params, JobContext $context): array
    {
        $id = intval($params['id']);
        $folder = rtrim($context->resultDirectory(), '/\\').'/site';
        $built = $this->builder->build($id, $folder,
            static function() use ($context): void { $context->check(); },
            static function(int $done, int $total, string $message) use ($context): void {
                $context->progress($done, $total, $message);
            });
        $file = 'website-'.$id.'.zip';
        $size = WebsiteBuilder::zip($folder, $built['files'], rtrim($context->resultDirectory(), '/\\').'/'.$file);
        WebsiteBuilder::remove($folder, $built['files']);
        return array(
            'file' => $file,
            'size' => $size,
            'id' => $id,
            'pages' => $built['pages'],
            'languages' => $built['languages'],
            'mime' => 'application/zip',
            'download' => true
        );
    }
}
