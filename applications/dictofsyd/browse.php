<?php
/*
* Copyright (C) 2005-2015 University of Sydney
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
* Browse page.
*  parameter r - type of browsing
*   content (javascript arrays) is generated in gen_browse.php
*   render is in browse.js
*
* if r is omitted it show flash browser
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

  require_once(dirname(__FILE__).'/php/incvars.php');
  require_once(dirname(__FILE__)."/php/Record.php");

  $starttime = explode(' ', microtime());
  $starttime = $starttime[1] + $starttime[0];

	  $type = null;
	  if(@$_REQUEST['r']){
  		$type = $_REQUEST['r'];

	    $type =  getCodeByName($type);

  		$type_name = getNameByCode($type, true);
  		if($type_name){
  				$g_title = 'Browse - '.$type_name;
	              $class_name = getNameByCode($type);
	              if($class_name=="media") {
	                  $class_name = "image";
	              }

  				//$extraScripts = '<script src="'.$urlbase.'js/browse.js" type="text/javascript"></script><script src="'.$urlbase.'cache/'.$type.'.js" type="text/javascript"></script>';
	            $thisis_browsepage = true;

		}else{
			$type = null; //not supported - show root
		}
	  }

  require(dirname(__FILE__).'/php/incheader.php');
?>
<div id="left-col">
	<div id="content">
<?php
	if($type){
?>

		<div id="heading" class="title-<?=$class_name?>">
			<h1>Browse <?=$type_name?></h1>
			<span id="sub-title"/>
		</div>


		<div id="loading">
			<p>Loading data</p>
			<img src="<?=$urlbase?>images/loadingAnimation.gif"/>
		</div>

		<ul id="browse-alpha-index"></ul>
		<ul id="browse-type-index"></ul>
		<ul id="browse-licence-index"></ul>

		<div class="list-right-col-browse" id="entities-alpha"></div>
		<div class="list-right-col-browse" id="entities-type"></div>
		<div class="list-right-col-browse" id="entities-licence"></div>

		<div class="list-right-col-browse" id="entities-content">
			<h2><?=$type_name?> with Entries</h2>
			<ul id="entities-with-entries"></ul>
			<h2>Other <?=$type_name?> mentioned in the Dictionary</h2>
			<ul id="entities-without-entries"></ul>
		</div>

<?php
	}else{
?>
		<div id="home-heading">
			<div id="browser"></div>
			<script type="text/javascript">
							$(function () { DOS.Media.embedBrowser("browser"); });
			</script>
		</div>
		<div class="clearfix"></div>
		<div>
			<h2>Click one of the images above or the links on the side</h2>
			<h2>or Search to enter the Dictionary</h2>
		</div>
		<div class="clearfix"></div>
<?php
	}
?>
	</div>
</div>
<div id="right-col">
	<a title="Dictionary of Sydney" href="<?=($startupurl==''?'./':$startupurl)?>"><img class="logo" height="125" width="198" alt="Dictionary of Sydney" src="<?=$urlbase?>images/img-logo.jpg"></a>
	<div id="search-bar">
        <form action="<?=$urlbase.($is_generation?'search/search.cgi':'search.php') ?>" method="get">
			<input size="20" id="search" name="zoom_query" type="text">
			<div id="search-submit"></div>
		</form>
	</div>
	<div id="browse-connections">
		<h3>Browse</h3>
		<ul id="menu">
		<li class="browse-artefact">
		<a href="<?=$urlbase?>browse/artefact">Artefacts</a>
		</li>
		<li class="browse-building">
		<a href="<?=$urlbase?>browse/building">Buildings</a>
		</li>
		<li class="browse-event">
		<a href="<?=$urlbase?>browse/event">Events</a>
		</li>
		<li class="browse-natural">
		<a href="<?=$urlbase?>browse/natural">Natural features</a>
		</li>
		<li class="browse-organisation">
		<a href="<?=$urlbase?>browse/organisation">Organisations</a>
		</li>
		<li class="browse-person">
		<a href="<?=$urlbase?>browse/person">People</a>
		</li>
		<li class="browse-place">
		<a href="<?=$urlbase?>browse/place">Places</a>
		</li>
		<li class="browse-structure">
		<a href="<?=$urlbase?>browse/structure">Structures</a>
		</li>
		<li class="browse-entry">
		<a href="<?=$urlbase?>browse/entry">Entries</a>
		</li>
		<li class="browse-map">
		<a href="<?=$urlbase?>browse/map">Maps</a>
		</li>
		<li class="browse-image">
		<a href="<?=$urlbase?>browse/media">Multimedia</a>
		</li>
		<li class="browse-term">
		<a href="<?=$urlbase?>browse/term">Subjects</a>
		</li>
		<li class="browse-role">
		<a href="<?=$urlbase?>browse/role">Roles</a>
		</li>
		<li class="browse-contributor">
		<a href="<?=$urlbase?>browse/contributor">Contributors</a>
		</li>
		</ul>
	</div>
</div>

<?php
  require(dirname(__FILE__).'/php/incfooter.php');
?>
