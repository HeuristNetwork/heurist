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

/* Load HAPI with the correct instance key.  Since this file has the privelege
 * of being on the server with the hapi key database it can look at that directly
 * and save us hard-coding a map of instance => key (yuck).
 */

header("Content-type: text/javascript");

if (@$_REQUEST["instance"]) {
	define("HEURIST_INSTANCE", $_REQUEST["instance"]);
	define("HOST", $_SERVER["HTTP_HOST"]);
}
require_once(dirname(__FILE__)."/../config/manageInstancesDeprecated.php");
require_once("dbMySqlWrappers.php");

mysql_connection_select("hapi");
$query = "SELECT hl_key
            FROM hapi_locations
           WHERE hl_instance = '" . HEURIST_INSTANCE . "'
             AND hl_location = 'http://" . HOST . "/'";
$res = mysql_query($query);
$row = mysql_fetch_assoc($res);
if (! $row) {
	print "Failed to load hapi. No key found for instance = '".HEURIST_INSTANCE."  and uri = http:/".HOST."/";
	exit;
}
$key = $row["hl_key"];
?>

document.write("<" + "scr" +"ipt src=\"<?=HEURIST_SITE_PATH?>hapi/hapiLoader.php?instance=<?= HEURIST_INSTANCE ?>&key=<?= $key?> <?=(@$_REQUEST["inclGeo"]? "&inclGeo=1":"")?>\"><" + "/script>\n");

