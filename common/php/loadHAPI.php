<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php
/*<!-- loadHapi.php

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	-->
*/

/* Load HAPI with the correct database key.  Since this file has the privelege
 * of being on the server with the hapi key database it can look at that directly
 * and save us hard-coding a map of database => key (yuck).
 */
define('ISSERVICE',1);

header("Content-type: text/javascript");
/* hapi key's removed saw 17/1/11
if (@$_REQUEST["db"]) {
	define("HEURIST_DBNAME", $_REQUEST["db"]);
	define("HOST", $_SERVER["HTTP_HOST"]);
}
*/
require_once(dirname(__FILE__)."/../config/initialise.php");
require_once("dbMySqlWrappers.php");

?>

document.write("<" + "scr" +"ipt src=\"<?=HEURIST_BASE_URL?>hapi/hapiLoader.php?db=<?= HEURIST_DBNAME ?><?=(@$_REQUEST["inclGeo"]? "&inclGeo=1":"")?>\"><" + "/script>\n");

