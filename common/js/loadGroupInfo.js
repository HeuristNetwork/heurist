/*
* Copyright (C) 2005-2016 University of Sydney
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
* loadGroupInfo.js
* Functions to load JSON data for workgroups
* functions are installed as  top.HEURIST.json.*
* 
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

if (! top.HEURIST.json) top.HEURIST.json = {

	loadWorkgroupDetails: function(wg_id, callback) {
		if (top.HEURIST.workgroups[wg_id].members && //if data already loaded  then just call callback function
			top.HEURIST.workgroups[wg_id].savedSearches &&
			top.HEURIST.workgroups[wg_id].publishedSearches) {
			if (callback) callback(wg_id);
			return;
		}
		// workgroup data not loaded so make asynch call to load it.
		top.HEURIST.util.getJsonData(top.HEURIST.baseURL_V3+"common/php/loadGroupSavedSearches.php?"+
										"db="+(top.HEURIST.database.name ? top.HEURIST.database.name: "") +
										"&wg_id="+wg_id,
										function(obj) {//callback
											if (! obj  || obj.error) return;
											top.HEURIST.workgroups[wg_id].members = obj.members;
											top.HEURIST.workgroups[wg_id].savedSearches = obj.savedSearches;
											top.HEURIST.workgroups[wg_id].publishedSearches = obj.publishedSearches;
											if (callback) callback(wg_id);
										}
									);
	}
};
