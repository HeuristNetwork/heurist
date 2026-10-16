<?php
/**
* SrvSmartyRenderer.php - ReportRendererInterface on the srv Smarty engine
*
* Runs templates with SmartyTemplateRunner (srv data services). Import and
* export of templates (concept-code conversion) are not ported yet: they are
* passed to another renderer (the legacy one).
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

use Heurist\Jobs\JobContext;
use Heurist\Reports\ReportRendererInterface;

/** Report renderer of the srv engine. */
final class SrvSmartyRenderer implements ReportRendererInterface
{
    private SmartyTemplateRunner $runner;
    private ?ReportRendererInterface $converter;

    /**
     * @param SmartyTemplateRunner $runner Template runner.
     * @param ReportRendererInterface|null $converter Renderer used for import/export.
     */
    public function __construct(SmartyTemplateRunner $runner, ?ReportRendererInterface $converter = null)
    {
        $this->runner = $runner;
        $this->converter = $converter;
    }

    /** @inheritDoc */
    public function renderRecord(string $templateFile, int $recordId): string
    {
        $result = $this->runner->run(array('file' => $templateFile), array($recordId),
            array('purpose' => 'render', 'mode' => 'html'));
        return $result['output'];
    }

    /** @inheritDoc */
    public function renderIds(array $source, array $ids, array $options = array(), ?JobContext $context = null): array
    {
        $tick = $context === null ? null : static function(int $done, int $total) use ($context): void {
            $context->progress($done, $total, 'running report');
            $context->check();
        };
        $result = $this->runner->run($source, $ids, $options, $tick);
        if($context !== null){
            $context->check();
        }
        return array('output' => $result['output'], 'error' => $result['error']);
    }

    /** @inheritDoc */
    public function importTemplate(array $upload): string
    {
        if($this->converter === null){
            throw new \DomainException('Template import is not available');
        }
        return $this->converter->importTemplate($upload);
    }

    /** @inheritDoc */
    public function exportTemplate(string $templateFile): string
    {
        if($this->converter === null){
            throw new \DomainException('Template export is not available');
        }
        return $this->converter->exportTemplate($templateFile);
    }
}
