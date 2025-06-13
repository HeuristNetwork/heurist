<?php
  
<?php
/**
 * DbExportTSV.php: export entire database to TSV (Tab Separated Values).
 *
 * This file defines the DbExportTSV class, which is responsible for
 * exporting database content into TSV format.
 *
 * @package     HeuristWebService
 * @subpackage  Export
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network Ltd.
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     5
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * Class DbExportTSV
 *
 * Handles the export of database content to TSV format.
 * It takes a system object during construction to interact with the database.
 */
class DbExportTSV {

    /**
     * @var mysqli|null The mysqli database connection object.
     */
    private $mysqli = null;

    /**
     * @var HeuristSystem|null The Heurist system instance.
     * REMARK: Changed type hint from mixed to HeuristSystem for clarity, assuming $system is an instance of HeuristSystem or a compatible interface.
     */
    private $system = null;

    /**
     * Constructor for DbExportTSV.
     *
     * Initializes the exporter with the necessary system context.
     *
     * @param HeuristSystem $system The Heurist system instance.
     * REMARK: Changed type hint from mixed to HeuristSystem for clarity.
     */
    public function __construct($system) {
        $this->setSession($system);
    }

    /**
     * Sets the system instance and initializes the database connection.
     *
     * @param HeuristSystem $system The Heurist system instance.
     * REMARK: Changed type hint from mixed to HeuristSystem for clarity.
     * @return void
     */
    public function setSession($system) {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
        //$this->initialized = true;
    }

}
?>
