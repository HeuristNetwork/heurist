<?php
/**
* ReportRendererInterface.php - Smarty report engine boundary
*
* srv/ runs Smarty reports only through this interface. The new API uses the srv
* engine (Reports\Smarty\SrvSmartyRenderer, plan 12 Phase 6). The legacy engine
* (hserv\report\SmartyReportRenderer) still converts templates on import/export
* and is run by the engine comparison test.
*
* @project     Heurist academic knowledge management system
* @package     Reports
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports;

use Heurist\Jobs\JobContext;

/** Executes and converts Smarty report templates. */
interface ReportRendererInterface
{
    /**
     * Render a template for one record as an HTML snippet.
     *
     * @param string $templateFile Template file name in the smarty-templates folder.
     * @param int $recordId Record to render; access rules of the current user apply.
     * @return string Rendered HTML (or the engine's error message as HTML).
     */
    public function renderRecord(string $templateFile, int $recordId): string;

    /**
     * Run a template on records: a test run of the editor or a generated file.
     * Only records the current user may view are used.
     *
     * @param array $source ['file' => template file name] or ['body' => template text].
     * @param array $ids Record ids in report order.
     * @param array $options `purpose` preview|file, `mode` html|js|txt|csv|xml|json, `replevel` 0-3.
     * @param JobContext|null $context Running job: progress, Stop and the time limit.
     * @return array{output:string,error:?string} Output (or the error as output) and the error text.
     */
    public function renderIds(array $source, array $ids, array $options = array(), ?JobContext $context = null): array;

    /**
     * Import an uploaded .gpl/.tpl file: convert concept codes to local ids and
     * save it as a .tpl file.
     *
     * @param array $upload Uploaded file entry (`name`, `tmp_name`, `size`).
     * @return string The saved template file name.
     */
    public function importTemplate(array $upload): string;

    /**
     * Export a template with local ids converted to concept codes.
     *
     * @param string $templateFile Template file name.
     * @return string The exported (.gpl) template body.
     */
    public function exportTemplate(string $templateFile): string;
}
