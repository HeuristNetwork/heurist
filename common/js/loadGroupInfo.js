/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

/* heurist-json.js
 * Copyright 2006, 2007 Tom Murtagh and Kim Jackson
 * http://heuristscholar.org/
 *
 * Functions to load JSON data into various places in top.HEURIST
 * functions are installed as  top.HEURIST.json.*
 */

/*
This file is part of Heurist.

Heurist is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

Heurist is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if (! top.HEURIST.json) top.HEURIST.json = {

	loadWorkgroupDetails: function(wg_id, callback) {
		if (top.HEURIST.workgroups[wg_id].members  &&
			top.HEURIST.workgroups[wg_id].savedSearches &&
			top.HEURIST.workgroups[wg_id].publishedSearches) {
			if (callback) callback(wg_id);
			return;
		}
		top.HEURIST.util.getJsonData(top.HEURIST.basePath+"common/php/loadGroupSavedSearches.php?db="+ (top.HEURIST.database.name ? top.HEURIST.database.name: "") + "&wg_id="+wg_id, function(obj) {
			if (! obj  || obj.error) return;
			top.HEURIST.workgroups[wg_id].members = obj.members;
			top.HEURIST.workgroups[wg_id].savedSearches = obj.savedSearches;
			top.HEURIST.workgroups[wg_id].publishedSearches = obj.publishedSearches;
			if (callback) callback(wg_id);
		});
	}
};
