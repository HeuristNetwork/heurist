/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
-- insert any SQL below you wish to run across all Heurist databases on the server

-- update sysIdentification set sys_dbSubVersion=1 where (select count(*) from defDetailTypes where (defDetailTypes.dty_id=50) and (defDetailTypes.dty_Name="Transform Pointer"));

-- use this to print the database name
-- select database() as name from sysIdentification where not (select count(*) from defDetailTypes where (defDetailTypes.dty_id=50) and (defDetailTypes.dty_Name="Transform Pointer"));
select database() as name from sysIdentification where not (select count(*) from defDetailTypes where (defDetailTypes.dty_id=50) and (defDetailTypes.dty_Name="Transform Pointer"));

-- repeat the query to do the actual work of update
-- update ??? set ??? = ??? where not (select count(*) from defDetailTypes where (defDetailTypes.dty_id=50) and (defDetailTypes.dty_Name="Transform Pointer"));
