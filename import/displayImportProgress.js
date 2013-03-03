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
/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

var importer = {
	initProgress: function() {
		if (! importer.progress) {
			importer.progress = {
				bar: document.getElementById("progress_indicator_bar"),
				text: document.getElementById("progress"),
				title: document.getElementById("progress_indicator_title"),
			}
		}
	},

	setProgress: function(percent) {
		importer.initProgress();
		if (percent < 0) {
			importer.progress.bar.parentNode.style.display = "none";
			importer.progress.title.style.display = "none";
			return;
		}
		if (percent > 100) { percent = 100;	/* don't be embarrassing! */ }

		importer.progress.bar.parentNode.style.display = "block";
		importer.progress.bar.style.width = percent + "%";
		importer.progress.title.innerHTML = "&nbsp;" + parseInt(percent) + "%";
	},

	setProgressTitle: function(title) {
		importer.initProgress();
		importer.progress.title.innerHTML = title;
		importer.progress.title.style.display = "block";
	}
};
