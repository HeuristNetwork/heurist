<?php
/*
<?php
/**
 * updateReportOutput.php - Handles updating and publishing of Smarty-based reports.
 *
 * @fileOverview This script is primarily for backward compatibility and delegates its functionality
 * to the `ReportController`. It processes requests to generate or update report outputs
 * (HTML/JS files) based on a report ID (from `usrReportSchedule`).
 * It supports different publishing modes and output formats.
 *
 * Parameters:
 * - `id` or `template_id`: The ID of the report schedule (rps_ID). If 0, may trigger refreshing all reports.
 * - `mode`: Output format ('js' or 'html'). Default is 'html'. (Used when publish > 0)
 * - `publish`: Defines the publishing behavior:
 *   - 0: Heurist v3 UI (Smarty tab display).
 *   - 1: Publish the report and display a status page.
 *   - 2: No browser output; save to file only.
 *   - 3: Redirect to existing published report; if not found, acts like publish=1.
 *
 * Error Handling:
 * - If `publish=1`, errors are displayed on a status page.
 * - Otherwise, errors (e.g., file write issues, template not found) are emailed to the database owner.
 *
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\smarty
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Tom Murtagh
 * @author      Kim Jackson
 * @author      Ian Johnson ian.johnson.heurist@gmail.com
 * @author      Stephen White
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @since       Pre-3.1.0
 */

// Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
// with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
// Unless required by applicable law or agreed to in writing, software distributed under the License is
// distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
// See the License for the specific language governing permissions and limitations under the License.
//
// REMARK: Removed redundant license block comment, as the license is already specified in the PHPDoc.
// REMARK: Corrected @subpackage to hclient\widgets\smarty based on directory structure.

use hserv\controller\FrontController;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';
$params = USanitize::sanitizeInputArray();
$params['controller'] = 'ReportController';
if(!isset($params['id'])){
    $params['id'] = @$params['template_id'];
}
$frontController = new FrontController($params);
$frontController->run();