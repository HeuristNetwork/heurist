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
* Search page.
*
* WORKS VERY SLOW! NEED TO BE REIMPLEMENTED
*
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
  require_once(dirname(__FILE__).'/php/Record.php');
  require_once(dirname(__FILE__).'/php/getRecordInfo.php');
  require_once(dirname(__FILE__).'/php/utilsMakes.php');

  $starttime = explode(' ', microtime());
  $starttime = $starttime[1] + $starttime[0];

  $squery = null;
  if(@$_REQUEST['zoom_query']){
  	$squery = $_REQUEST['zoom_query'];


    mysql_connection_select();

    $dttypes = array(DT_NAME, DT_DESCRIPTION, DT_TYPE_MIME, DT_ENTITY_TYPE);
    $squery = mysql_real_escape_string($squery);

    //, d.dtl_DetailTypeID as dttype, d.dtl_Value  as dtvalue

$query = "select distinct d.dtl_RecID as rec_id, r.rec_RecTypeID as rectype from Records r, recDetails d where ".
"(r.rec_Title like '%".$squery."%' or (d.dtl_DetailTypeID = 4 and d.dtl_Value  like '%".$squery."%')) and ".
" r.rec_ID=d.dtl_RecID ".
' and  r.rec_RecTypeID in ('.RT_ENTRY.','.RT_MEDIA.','.RT_TILEDIMAGE.','.RT_CONTRIBUTOR.','.RT_ENTITY.','.RT_ROLE.','.RT_MAP.','.RT_TERM.')';

//"(r.rec_Title REGEXP '[^ ]".$squery."[ $]' or (d.dtl_DetailTypeID = 4 and d.dtl_Value REGEXP '[^ ]".$squery."[ $]')) and ".

             $records = array();
             $res = mysql_query($query);
             while ($row = mysql_fetch_assoc($res)){
                $record = new Record();
                $record->init($row);
                $record->setDetails(getRecordDetails($record->id(), $dttypes));
                array_push($records, $record);
             }

  }
  $squery = "";

  $extraScripts = '<link type="text/css" href="'.$urlbase.'search.css" rel="stylesheet">';

  require(dirname(__FILE__).'/php/incheader.php');
?>
<div id="left-col">
	<div id="content">

        <div id="heading" class="title-search">
                <h1>Search</h1>
        </div>


        <div id="searchbox">
            <form method="get" action="<?=$urlbase ?>search.php">
                    <input type="text" id="bigsearch" name="zoom_query" size="40" value="<?=$squery?>"><input type="submit" value="search">
            </form>
        </div>

        <div class="summary">
            <?=count($records)>0?count($records).'results found':'No results found' ?>.<br>
        </div>


        <div class="searchheading">
                Search results for: <?=$squery ?><br><br>
        </div>

        <div class="results">
<?php
            foreach ($records as $record){
?>
                <div class="result_block list-<?=$record->type_classname() ?>">
                    <div class="result_title">
                        <?=getLinkTag($record) ?>
                    </div>
                    <div class="context">
                        <?=$record->getDescription() ?>
                    </div>
                    <div class="infoline">&nbsp;</div>
                </div>
<?php
            }
?>
        </div>

	</div>
</div>
<div id="right-col">
	<a title="Dictionary of Sydney" href="<?=($startupurl==''?'./':$startupurl) ?>"><img class="logo" height="125" width="198" alt="Dictionary of Sydney" src="<?=$urlbase?>images/img-logo.jpg"></a>
	<div id="search-bar">
        <form action="<?=$urlbase ?>search.php" method="get">    <!-- was search/search.cgi -->
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
