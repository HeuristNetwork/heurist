
Directory:    hserver/dbaccess

Overview:       Libraries of functions to work with particular tables in heurist database

Notes:          These are mostly CRUD operation
                important are:
                db_structure - library of function that provides database structure information: rectypes, fieldtypes and terms defined in database
                db_recsearch - main search library

Updated:     18 Oct 2015

----------------------------------------------------------------------------------------------------------------

/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


db_files.php  - working with recUploadedFiles download by id, print player tag, get file info  -> static class RecFile

db_users.php - access sysUGrps              -> static class   DbUsers::


db_structure.php - loads db structure info       -> static class DbStructure

                   verify structure info values  -> static class DbValidate

                   whole db actions: clone, create, dump -> static class DbUtils

utils_db.php  - mysqli wrapper                   -> static class MySQL:: or extend  $mysqli ?
utils_db_load_script.php  - run dump script

---

file_download.php - interface to get file -> move to controller
rt_icon.php - rectype and term icons  -> move to controller, merge with fileGet.php
