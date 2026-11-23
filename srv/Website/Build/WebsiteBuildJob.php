<?php
/**
* WebsiteBuildJob.php - Background job "website-build" (plan 27b §7, plan 27m §2)
*
* prepare() checks that the current user may edit the v4 website or standalone
* page (WebsiteEditService::buildable) and the options of Generate (plan 27m §2.1:
* languages, target folder | zip | both, public address of a ZIP, record pages by query).
* run() writes the pages with guest rights (WebsiteBuilder):
* - folder: into a new folder beside `generated-sites/{id}` with `manifest.json`, which then
*   replaces the site folder (the live URLs serve these files, WebsiteController); a failed
*   build leaves the former folder (or none) as it was;
* - zip: zipped into the job's result folder, downloaded with GET /api/{db}/jobs/{id}/result
*   by the user who started the job and removed with the job after 24 hours.
* One build per user at a time.
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
    private GeneratedSites $generated;
    private int $userId;

    /**
     * @param WebsiteEditService $sites Rights of the current user (prepare).
     * @param WebsiteBuilder $builder Builder reading with guest rights (run).
     * @param GeneratedSites $generated The generated-sites folder of the database.
     * @param int $userId The user who starts the build (manifest).
     */
    public function __construct(WebsiteEditService $sites, WebsiteBuilder $builder, GeneratedSites $generated, int $userId = 0)
    {
        $this->sites = $sites;
        $this->builder = $builder;
        $this->generated = $generated;
        $this->userId = $userId;
    }

    /** @inheritDoc */
    public function type(): string
    {
        return self::TYPE;
    }

    /** @inheritDoc */
    public function limitSeconds(): int
    {
        return 1800;
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
        $options = WebsiteBuilder::options(is_array($params['options'] ?? null) ? $params['options'] : array());
        return array('title' => 'Generate website: '.$summary['title'], 'params' => array('id' => $id, 'options' => $options));
    }

    /** @inheritDoc */
    public function run(array $params, JobContext $context): array
    {
        $id = intval($params['id']);
        $options = WebsiteBuilder::options(is_array($params['options'] ?? null) ? $params['options'] : array());
        $check = static function() use ($context): void { $context->check(); };
        $progress = static function(int $done, int $total, string $message) use ($context): void {
            $context->progress($done, $total, $message);
        };
        $result = array('id' => $id, 'target' => $options['target']);

        if($options['target'] !== 'zip'){
            // the generated site served at the live URLs (plan 27m §2): complete, then in place of the former one
            $folder = $this->generated->buildFolder($id, (string)getmypid().'-'.time());
            try{
                $built = $this->builder->build($id, $folder, 'folder', $options, $check, $progress);
                $measure = GeneratedSites::measure($folder);
                $manifest = array(
                    'site' => $id,
                    'title' => $built['title'],
                    'generatedAt' => time(),
                    'user' => $this->userId,
                    'languages' => $built['languages'],
                    'defaultLanguage' => $built['defaultLanguage'],
                    'pages' => $built['pages'],
                    'records' => $built['records'],
                    'recordPages' => $built['recordPages'],
                    'files' => $measure['files'] + 1,
                    'size' => $measure['size'],
                    'modified' => $built['modified'],
                    // the Heurist address of the build: its URLs in the files (an own domain rewrites them, plan 27m §5)
                    'baseUrl' => $built['baseUrl'],
                    'options' => $options
                );
                $json = (string)json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                if(file_put_contents($folder.'/'.GeneratedSites::MANIFEST, $json) === false){
                    throw new \RuntimeException('Cannot write the manifest of the generated website');
                }
                $this->generated->install($id, $folder);
            }catch(\Throwable $exception){
                GeneratedSites::removeFolder($folder);
                throw $exception;
            }
            $result += array('generated' => $this->generated->info($id), 'pages' => $built['pages'],
                'records' => $built['records'], 'languages' => $built['languages']);
        }

        if($options['target'] !== 'folder'){
            // a copy for another host (plan 27m §4)
            $folder = rtrim($context->resultDirectory(), '/\\').'/site';
            $built = $this->builder->build($id, $folder, 'zip', $options, $check, $progress);
            $file = 'website-'.$id.'.zip';
            $size = WebsiteBuilder::zip($folder, $built['files'], rtrim($context->resultDirectory(), '/\\').'/'.$file);
            WebsiteBuilder::remove($folder);
            $result += array('file' => $file, 'size' => $size, 'pages' => $built['pages'], 'records' => $built['records'],
                'languages' => $built['languages'], 'mime' => 'application/zip', 'download' => true);
        }
        return $result;
    }
}
