<?php

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



/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/



require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (! is_logged_in()) return;
?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
<title>Set ratings</title>

<script type="text/javascript">

function set_ratings() {

	var elements = document.getElementsByName('r');
	for (var i=0, iLen=elements.length; i<iLen; i++) {
  		if (elements[i].checked) {
    		value = elements[i].value;
    		break;
  		}
	}
	window.close(value);
}
</script>
<style>
.lblr{
background-image: url(../../common/images/star-yellow.png);
display:block;
height:14px;
}
</style>
</head>

<body class="popup" width="200" height="160">

		<table>
				<tr><td><input type="radio" value="0" name="r" id="r0"></td><td><label for="r0">No Rating</label></td></tr>
				<tr><td><input type="radio" value="1" name="r" id="r1"></td><td><label for="r1" class="lblr" style="width:14px;"></label></td></tr>
				<tr><td><input type="radio" value="2" name="r" id="r2"></td><td><label for="r2" class="lblr" style="width:24px;"></label></td></tr>
				<tr><td><input type="radio" value="3" name="r" id="r3"></td><td><label for="r3" class="lblr" style="width:38px;"></label></td></tr>
				<tr><td><input type="radio" value="4" name="r" id="r4"></td><td><label for="r4" class="lblr" style="width:50px;"></label></td></tr>
				<tr><td><input type="radio" value="5" name="r" id="r5"></td><td><label for="r5" class="lblr" style="width:64px;"></label></td></tr>
		</table>
		<div style="text-align: right; padding-top: 10px;">
			<input type="button" value="Set ratings" style="font-weight: bold;" onclick="set_ratings();">
		</div>

</body>
</html>
