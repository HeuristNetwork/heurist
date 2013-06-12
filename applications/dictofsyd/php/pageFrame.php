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
* Main script for particular record page. This is central place to include all pageXYZ.php
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/


require_once("pageMap.php");
require_once("pageRole.php");
require_once("pageEntry.php");

$starttime = explode(' ', microtime());
$starttime = $starttime[1] + $starttime[0];

	$rec_id = @$_REQUEST['name'];
	$record = null;

	if(is_numeric($rec_id)){
		$record = getInfo(@$_REQUEST['name']);
		if($record){
			$g_title = $record->getDet(DT_NAME);
		}
	}

require('incheader.php');

?>
<div id="left-col">
	<div id="content">
<?php

	if($record){
			//echo print_r($record, true),"<br>";
			makeTitleDiv($record);

			if($record->type()==RT_ENTITY){
				require('pageEntity.php');
			}else if($record->type()==RT_ENTRY){
				makeEntryPage($record);
			}else if($record->type()==RT_MEDIA){
				require('pageMedia.php');
			}else if($record->type()==RT_CONTRIBUTOR){
				require('pageContributor.php');
			}else if($record->type()==RT_TERM){
				require('pageTerm.php');
			}else if($record->type()==RT_ROLE){
				makeRolePage($record);
			}else if($record->type()==RT_MAP){
				makeFullTimeMap($record);
			}


	}else{
			echo "record not found"; //move to main browser page?????
	}

?>
	</div>
</div>
<div id="right-col">
	<a title="Dictionary of Sydney" href="<?=($urlbase==''?'./':$urlbase)?>"><img class="logo" height="125" width="198" alt="Dictionary of Sydney" src="<?=$urlbase?>images/img-logo.jpg"></a>
	<div id="search-bar">
        <form action="<?=$urlbase.($is_generation?'search/search.cgi':'search.php') ?>" method="get">
			<input size="20" id="search" name="zoom_query" type="text">
			<div id="search-submit"></div>
		</form>
	</div>

	<div id="browse-link">
		<a title="Dictionary of Sydney Browse" href="<?=$urlbase.($is_generation?'browse.html':'browse.php') ?>">Browse</a>
	</div>

<?php
	if($record->type()==RT_ENTRY){
?>
		<div id="chapters">
			<div id="chapters-top"></div>
			<div id="chapters-middle">
				<h3>Chapters</h3>
				<!-- document index generated here -->
			</div>
			<div id="chapters-bottom"></div>
		</div>
<?php
	}

	if($record->type()==RT_TERM){
		print '<div id="connections"><h3>Connections</h3>';
			makeTermsMenuItem($record, 1);
			makeTermsMenuItem($record, 2);
		print '</div>';

	}else if($record->type()!=RT_CONTRIBUTOR && $record->type()!=RT_ROLE){
		makeConnectionMenu($record);
	}
?>

	<!-- TODO sidebar -->
</div>
<?php
require('incfooter.php');
?>