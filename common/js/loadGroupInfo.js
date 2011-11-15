/*
 * loadGroupInfo.js, Functions to load JSON data for workgroups
 * functions are installed as  top.HEURIST.json.*
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
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
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+"common/php/loadGroupSavedSearches.php?"+
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
