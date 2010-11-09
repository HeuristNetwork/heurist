<?php
	/*<!-- xmlexport.php

	* Main Heurist search page: this page is the effective home page for the Heurist application *

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

	-->*/


/**
 * ExportPage.php - to export Heurist records in any format and/or filetype
 *
 * @package ExportPageFinal.php
 * @version 2007-03-28
 * @author Erik Baaij, Marco Springer, Kim Jackson, Maria Shvedova, Tom Murtagh, Steve White
 * (c) 2007 Archaeological Computing Laboratory, University of Sydney
 */


/**
 * Constants
 */

define('MAX_ROWS', 1000);
define('SEARCH_VERSION', 1);

// give a xml header
header('Content-type: text/xml; charset=utf-8');

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');

require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../search/advanced/adv-search.php');
require_once(dirname(__FILE__).'/../../records/relationships/relationships.php');
require_once(dirname(__FILE__).'/../../common/php/requirements-overrides.php');
require_once(dirname(__FILE__).'/../../records/woot/woot.php');



// The list of all global variables that will be used
$DB_CON;					// the connection with the database
$DB;							// the database name
$XML = '';				// the xml output
$ERROR = '';			// the errors
$RQS = array();   // the required detailtypes per referencetype
$DTN = array();	  // the default names of detailtypes
$LOOP = array();	// holds visited record ids for loop detection while going through containers
$RFT = array();		// reftype labels
$RDL = array();	//record detail lookup
$ONT = array();	//ontology lookup


mysql_connection_db_select(DATABASE);

// have to do this before publish_cred.php unsets our request parameters!
$MAX_DEPTH = @$_REQUEST['depth'] ? intval($_REQUEST['depth']) : 0;
$REVERSE = @$_REQUEST['rev'] =='yes' ? true : false;
$RELATED_DETAILS = NULL;
$details = explode(',', @$_REQUEST['related_details']);        //FIXME this ensures details is an object next statement seems to assume it can be null
if ($details) {
	$RELATED_DETAILS = array();
	foreach ($details as $detail) {
		array_push($RELATED_DETAILS, intval($detail));
	}
}

if (@$argv) {
	// handle command-line queries

	$ARGV = array();
	for ($i=1; $i < count($argv); ++$i) {
		if ($argv[$i][0] == '-') {
			$ARGV[$argv[$i]] = $argv[$i+1];
			++$i;
		} else {
			array_push($ARGV, $argv[$i]);
		}
	}

	$_REQUEST['q'] = @$ARGV['-q'];
	$_REQUEST['w'] = @$ARGV['-w']? $ARGV['-w'] : 'b';	// default to ALL RESOURCES
	$_REQUEST['stype'] = @$ARGV['-stype'];
	$_REQUEST['style'] = '';

	function get_user_id() { return 0; }
	function get_user_name() { return ''; }
	function get_user_username() { return ''; }
	$pub_id = 0;

} else if (@$_REQUEST['pub_id']) {
	$pub_id = intval($_REQUEST['pub_id']);
	$rec_id = intval(@$_REQUEST['bib_id']);
	require_once(dirname(__FILE__).'/../../common/connect/publish_cred.php');

	if ($rec_id) $_REQUEST['q'] .= ' && ids:' . $rec_id;

} else if (@$_SERVER['SERVER_ADDR'] == '127.0.0.1') {	// internal request ... apparently we don't want to authenticate ..?
	function get_user_id() { return 0; }
	function get_user_name() { return ''; }
	function get_user_username() { return ''; }
	function get_group_ids() { return array(2); }
	function is_admin() { return false; }
	function is_logged_in() { return true; }
	$pub_id = 0;

} else {
	$pub_id = 0;
	require_once(dirname(__FILE__).'/../../common/connect/cred.php');

	if (!is_logged_in()) { // check if the record being retrieved is a singe non-protected record
		if (!single_record_retrieval($_REQUEST['q'])) {
			header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
			return;
		}
	}
}

// initialise rec_types labels
$query = 'SELECT rt_id, rt_name FROM rec_types';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {

	$RFT[$row['rt_id']] = $row['rt_name'];
	foreach (getRecordRequirements($row['rt_id']) as $rdr_rdt_id => $rdr) {
	// initialise requirements and names for detailtypes ($RQS)
		$RQS[$rdr['rdr_rec_type']][$rdr['rdr_rdt_id']]['rdr_required'] = $rdr['rdr_required'];
		$RQS[$rdr['rdr_rec_type']][$rdr['rdr_rdt_id']]['rdr_name'] = $rdr['rdr_name'];
	}
}

// initialise default names for detailtypes ($DTN)
$query = 'SELECT rdt_id, rdt_name FROM rec_detail_types';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {

	$DTN[$row['rdt_id']] = $row['rdt_name'];
}


// lookup for detail type enum values
$query = 'SELECT rdl_id, rdl_value, rdl_ont_id FROM rec_detail_lookups';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$RDL[$row['rdl_id']] = $row;
}

// lookup for ontologies
$query = 'SELECT ont_id, ont_name, ont_refurl FROM ontologies';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$ONT[$row['ont_id']] = $row;
}



// get the resultset from the searchpage (fill $BIBLIO_IDS)
$BIBLIO_IDS = getRecords();

if (@$_REQUEST['style']  &&  !eregi ('.xsl', @$_REQUEST['style'])  &&  @$_REQUEST['style']!='genericxml'){
    $_REQUEST['style'] = $_REQUEST['style'].".xsl";
  }
/*
 * The main loop. It loops through the array with records ids and makes a '<reference>' in
 * the XML document for all valid records. It gets general data from table records and all
 * available data in rec_details. If it has a container, it will be added within a <container>
 * tag, recursively going through all containers till none are found.
 */

// the start of the XML document
$XML .= '<?xml version="1.0" encoding="UTF-8"?>';
$XML .= "\n";

/*
 * Here, the selection of a stylesheet takes place. This is a temporary solution to create
 * functional links in the Heurist page. Later on, a selection can be made on the Heurist
 * page and the selected xsl will be added in the header of this xml document. If no style
 * is selected, the generic xml will be made.
 */

// harvard or anything but genericxml
if ( @$_REQUEST['style'] && @$_REQUEST['style']!='genericxml' ) {
	$XML .= '<?xml-stylesheet type="text/xsl" href="'.HEURIST_URL_BASE.'/viewers/publish/xsl/' . $_REQUEST['style'] . '"?>';
	$XML .= "\n";
}
// no style, give default generic xml
else {
}

if ($pub_id)
	$XML .= "<export pub_id=\"$pub_id\">\n";
else
	$XML .= "<export>\n";

$time = time();
$XML .= "<date_generated>";
$XML .= "<year>".date('Y', $time)."</year>";
$XML .= "<month>".date('n', $time)."</month>";
$XML .= "<day>".date('j', $time)."</day>";
$XML .= "</date_generated>\n";

//output the query for this XML result
$XML .= "<query ";
if (@$_REQUEST['q'])
    $XML .= "q='" . str_replace("'","&#39;",$_REQUEST['q']) . "' ";
if (@$_REQUEST['w'])
    $XML .= "w='" . $_REQUEST['w'] . "' ";
$XML .= "/>\n";

$XML .= "<ontologies>\n";
foreach($ONT as $ontology){
	$XML .="<ontology id=\"" .$ontology['ont_id']. "\"";
	if ($ontology['ont_refurl']) {
		$XML .= " namespace=\"". htmlspecialchars($ontology['ont_refurl']). "\"";
	}
	$XML .= ">" .$ontology['ont_name']. "</ontology>\n";
}
$XML .= "</ontologies>\n";


$XML .= "<references>\n";

// start main loop
for ($i = 0; $i < count($BIBLIO_IDS); $i++) {

	// clear loop array
	$LOOP = array();

	writeReference($BIBLIO_IDS[$i]);
}

// the end of the references
$XML .= "</references>";
$XML .= "<rowcount>".count($BIBLIO_IDS)."</rowcount>";
// the errors
$XML .= "<errors>\n";
$XML .= $ERROR;
$XML .= "</errors>\n";
// the end of the document
$XML .= "</export>";

// print the xml
echo "$XML";


// total_num_rows is set when we do the initial query
if ($total_num_rows > MAX_ROWS) {
?>
<!--

Note: you requested a very large amount of data in preview mode.
First <?= MAX_ROWS ?> records only have been output. Please use publish wizard if you
wish to export all of this data

-->
<?php
}


/**
 * This function writes the begin- and endtag to the XML document per reference. This
 * includes the containers found in records. Between it, it puts the general data
 * and all details found in rec_details.
 *
 * @global $XML the xml document where it writes to
 * @global $LOOP the array with visited records ids for loop detection on containers
 * @global $ERROR the log to which the errors will be added
 * @param $rec_id the rec_id for the record
 * @param $depth current depth (0 == root level)
 * @param $rd_type rd_type of the pointer to this reference (only applicable when depth > 0)
 * @param $rec_types rec_types of this reference (only applicable when depth > 0)
 */
function writeReference($rec_id, $depth = 0, $rd_type = 0, $rec_types = 0, $rev = false) {
	global $XML;
	global $LOOP;
	global $ERROR;
	global $DTN;
	global $RQS;
	global $MAX_DEPTH;

	// check if rec_id is already visited
	if (!in_array($rec_id, $LOOP)) {

		// add bib id to loop array
		// DISABLE LOOP DETECTION - not needed since we now specify a max depth  --kj 28/03/07
		// array_push($LOOP, $rec_id);

		// open reference or container tag

		if ($depth == 0) {
			$XML .= "<reference>\n";
		} else {
			if ($rev){
			   $XML .= "<reverse-pointer ";
			}else{
			   $XML .= "<pointer ";
			}
			$XML .= "name=\"" . htmlspecialchars(@$RQS[$rec_types][$rd_type]['rdr_name']) . "\" type=\"" . htmlspecialchars(@$DTN[$rd_type]) . "\" id=\"" . htmlspecialchars($rd_type) . "\">\n";
		}

		// print all general data
		writeGeneralData($rec_id, $depth);

		// print all details and look for required fields and containers
		if ($depth <= $MAX_DEPTH) {
			writeDetails($rec_id, $depth);

		} else {
			fetchExtraDetails($rec_id, $rec_types);
		}

		if ($depth == 0) {
			writeWootContent($rec_id);
		}

		// close reference or container tag
		if ($depth == 0) {
			$XML .= "</reference>\n";
		}
		else {
			if ($rev){
			   $XML .= "</reverse-pointer>\n";
			}else{
			   $XML .= "</pointer>\n";
			}

		}

	}
	// loop detected
	else {
		// create path of loop
		$path = '';
		foreach($LOOP as $l) {
			$path .= " -> " . $l;
		}
		// add error
		$ERROR .= "<error>loop detected for rec_id: " . $rec_id . ", path: " . $path . " -> " . $rec_id . "</error>\n";
	}
}

/**
 * This function writes general data about the record to the XML. For the tag of
 * the user who added it, the method constructs a name from the persons table. For
 * the workgroup, it gets the value from the users database.
 *
 * @global $XML the xml document where it writes to
 * @global $DB the current database
 * @param int $bib the records id for the record
 */
function writeGeneralData($bib, $depth) {
	global $XML;
	global $DB;
	global $MAX_DEPTH;

	$query = 'SELECT * FROM records
						LEFT JOIN rec_types on rec_type = rt_id
						LEFT JOIN '.USERS_DATABASE.'.Users on rec_added_by_usr_id = Id
						WHERE rec_id=' . $bib;
	$res = mysql_query($query);
	// do we have results ?
	if(!empty($res)) {

		$row = mysql_fetch_assoc($res);

		// the htmlspecialchars method replaces specific chars for a 'well-formed' XML document

		$XML .= "<reftype id=\"" . htmlspecialchars($row['rt_id']). "\">" . htmlspecialchars($row['rt_name']) . "</reftype>\n";
		$XML .= "<id>" . htmlspecialchars($bib) . "</id>\n";
		//$XML .= "<query>" . htmlspecialchars($query) . "</query>\n";
		$XML .= "<url>" . htmlspecialchars($row['rec_url']) . "</url>\n";
		$XML .= "<added>" . htmlspecialchars($row['rec_added']) . "</added>\n";
		$XML .= "<modified>" . htmlspecialchars($row['rec_modified']) . "</modified>\n";
		$XML .= "<notes>" . htmlspecialchars($row['rec_scratchpad']) . "</notes>\n";
		$XML .= "<title>" . htmlspecialchars($row['rec_title']) . "</title>\n";

		// construct username who added record
		$person = $row['firstname'] . ' ' . $row['lastname'];
		// remove multiple spaces
		$person = trim(preg_replace('/[\s]+/', ' ', $person));
		$XML .= "<added_by>" . htmlspecialchars($person) . "</added_by>\n";

		// check if it's a public record, if not, get workgroup from users database
		$workgroup = $row['rec_wg_id'];
		if ($workgroup == 0 || empty($workgroup)) {
			$XML .= "<workgroup>public</workgroup>\n";
		}
		// get workgroup from users database
		else {
			mysql_connection_db_select(USERS_DATABASE) or die(mysql_error());
			$query = 'SELECT grp_name FROM Groups WHERE grp_id=' . $workgroup;
			$res = mysql_query($query);
			$row = mysql_fetch_assoc($res);
			$XML .= "<workgroup>" . htmlspecialchars($row['grp_name']) . "</workgroup>\n";
			// set database back
			mysql_connection_db_select(DATABASE) or die(mysql_error());
		}
	}

	if ($depth <= $MAX_DEPTH) {
		writeRelatedData($bib, $depth + 1);
	}


}

/**
 * This function loops through all the occurences in rec_details for the record.
 * If one is an author or editor that tag gets three subtags with the firstname,
 * othernames and surname of that person from the persons table.
 * If it is a container detail it fires the writing of the container.
 *
 * @param int $bib the records id of the record
 * @param int $depth current recursion depth (0 == root level)
 */
function writeDetails($bib, $depth) {
	global $RQS;
	global $REVERSE;

	writeKeywords($bib);

    $assoc_resources = array();
	$query = 'SELECT rec_type, rd_val, rdt_id, rdt_type, rd_type, rd_file_id FROM rec_details
						LEFT JOIN rec_detail_types on rd_type = rdt_id
						LEFT JOIN records on rec_id = rd_rec_id
						LEFT JOIN rec_detail_requirements on rdr_rdt_id = rdt_id and rdr_rec_type = rec_type
						WHERE rd_rec_id=' . $bib .'
						ORDER BY rdr_order is null, rdr_order, rdt_id, rd_id';

	$res = mysql_query($query);

if (mysql_error()) error_log(mysql_error());

	while ($row = mysql_fetch_assoc($res)) {

		// if it's a record pointer, write the record pointed to
		if ($row['rdt_type'] == 'resource') {
			writeReference($row['rd_val'], $depth + 1, $row['rd_type'], $row['rec_type'], false);

		}
		else {
			writeTag($row['rec_type'], $row['rd_type'], $row['rd_val'], $row['rd_file_id']);
		}
	}

	if ($REVERSE) {
		writeReversePointers($bib, $depth + 1);
	}
}


/**
 * This function retrieves "reverse pointers" - resource pointers that point to a specified record
 *
 * @global $XML the xml document where it writes to
 * @param string $bib record id
 *
 */
function writeReversePointers($bib, $depth) {
	global $XML;

	$q_reverse= 'SELECT * FROM rec_details LEFT JOIN rec_detail_types ON rdt_id = rd_type
	             LEFT JOIN records ON rec_id = rd_rec_id
	             WHERE rd_val ='.$bib .' AND rdt_type = "resource" AND rec_temporary != 1 ORDER BY rec_type';

	$results = mysql_query($q_reverse);

	while ($row = mysql_fetch_array($results)) {
		writeReference($row['rd_rec_id'], $depth, $row['rd_type'], $row['rec_type'], true);
	}
}

/**
 * This function retrieves workgroup usrTags for the record
 *
 * @global $XML the xml document where it writes to
 * @param string $bib record id
 *
 */
function writeKeywords($bib) {
	global $XML;

	$query = 'SELECT distinct grp_name, tag_Text
				FROM usrRecTagLinks
		   LEFT JOIN usrTags ON tag_ID = kwl_kwd_id
		   LEFT JOIN '.USERS_DATABASE.'.Groups ON grp_id = tag_UGrpID
			   WHERE kwl_rec_id = '.$bib.'
				 AND ???kwd_wg_id > 0
			ORDER BY grp_name, tag_Text';
	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res)) {
		$XML .= "<keyword workgroup=\"".$row['grp_name']."\">".$row['tag_Text']."</keyword>\n";
	}
}

/**
 * This function writes a tag to the xml doc. It replaces the detailtype number with
 * the right name for that reference type and checks if the value is empty. If so, it
 * only writes it if it's a required detail (empty tag).
 *
 * @global $XML the xml document where it writes to
 * @global $RQS the requirements for detailtypes
 * @global $DTN names of detailtypes
 * @global $ERROR the log to which errors will be added
 * @param int $rec_types the referencetype
 * @param int $detail the detailtype
 * @param string $value the value of the detail
 * @param int $file_id file_id if the detail is of type file
 */
function writeTag($reftype, $detail, $value, $file_id) {
	global $XML;
	global $RQS;
	global $DTN;
	global $ERROR;

	if (!empty($value))
		$value = htmlspecialchars($value);

	if ($file_id) {
		$res = mysql_query('select * from files where file_id='.intval($file_id));
		$file = mysql_fetch_assoc($res);
		if ($file) {
			$value = "\n<file_id>" . htmlspecialchars($file['file_nonce']) . "</file_id>\n"
				   . "<file_orig_name>" . htmlspecialchars($file['file_orig_name']) . "</file_orig_name>\n"
				   . "<file_date>" . htmlspecialchars($file['file_date']) . "</file_date>\n"
				   . "<file_size>" . htmlspecialchars($file['file_size']) . "</file_size>\n"
				   . "<file_fetch_url>" . htmlspecialchars(HEURIST_URL_BASE.'/records/files/fetch_file.php/'.urlencode($file['file_orig_name']).'?file_id='.$file['file_nonce']) . "</file_fetch_url>\n"
				   . "<file_thumb_url>" . htmlspecialchars(HEURIST_URL_BASE.'/common/php/resize_image.php?file_id='.$file['file_nonce']) . "</file_thumb_url>\n";
		}
	}

	// dates
	if ($detail == 166  ||  $detail == 177  ||  $detail == 178) {
		if (preg_match('/^\\s*-?\\d+\\s*$/', $value)) { // year only
			$value = $value . "<year>" . $value . "</year>\n";
		} else {
			$date = strtotime($value);
			if ($date != false) {
				$time = null;
				if (preg_match("![ T]!", $value)) {	// looks like there's a time
					$time = date('H:i:s', $date);
				}
				$value = date('jS F Y', $date) . "\n"
					   . "<year>" . date('Y', $date) . "</year>\n"
					   . "<month>" . date('n', $date) . "</month>\n"
					   . "<day>" . date('j', $date) . "</day>\n";
				if ($time) {
					$value .= "<time>" . $time . "</time>\n";
				}
			} else {
				@list($date, $time) = preg_split("![ T]!", $value);
				@list($y,$m,$d) = array_map("intval", preg_split("![-\/]!", $date));
				if (! (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31)) {
					@list($d,$m,$y) = array_map("intval", preg_split("![-\/]!", $date));
				}
				if (! (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31)) {
					@list($m,$d,$y) = array_map("intval", preg_split("![-\/]!", $date));
				}
				if (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31) {
					$months = array("", "January", "February", "March", "April", "May", "June", "July",
								"August", "September", "October", "November", "December");
					switch($d % 10) {
						case 1: $suffix = "st"; break;
						case 2: $suffix = "nd"; break;
						case 3: $suffix = "rd"; break;
						default: $suffix = "th"; break;
					}
					$value = $d . $suffix . " " . $months[$m] . " " . $y . "\n"
					   . "<year>" . $y . "</year>\n"
					   . "<month>" . $m . "</month>\n"
					   . "<day>" . $d . "</day>\n";
					if ($time) {
						$value .= "<time>" . $time . "</time>\n";
					}
				}
			}

		}
	}

	// if value is required but empty, make a notice of missing detail
	if (empty($value) && $RQS[$reftype][$detail]['rdr_required'] == 'Y') {
		$ERROR .= "<error>missing required detail</error>\n";
	}

	// if the value is not empty OR empty but required, write tag with escaping weird chars
	if (!empty($value) || (empty($value) && $RQS[$reftype][$detail]['rdr_required'] == 'Y')) {
		$XML.= "<detail name='". htmlspecialchars(@$RQS[$reftype][$detail]['rdr_name']) ."' type='" . htmlspecialchars(@$DTN[$detail]) . "' id='" . $detail . "'>" . $value . "</detail>\n";
	}
}

/**
 * This function writes a person tag to the XML doc. It builds the tag with
 * the firstname, the othernames and the surname.
 *
 * @global $XML the xml document where it writes to
 * @global $DTN names of detailtypes
 * @param int $detail the detailtype
 * @param string $firstname the firstname of the person
 * @param string $othernames the othernames of the person
 * @param string $surname the surname of the person
 */
function writeRelatedData($bib, $depth) {
	global $XML;
	global $DTN;
	global $MAX_DEPTH;
	global $RFT;

	$from_res = mysql_query('select a.rd_rec_id
							   from rec_details a
						  left join rec_details b on b.rd_rec_id = a.rd_rec_id
						  left join records on rec_id = b.rd_val
							  where a.rd_type = 202
								and a.rd_val = ' . $bib . '
								and b.rd_type = 199
								and rec_temporary != 1');        // 202 = primary resource

	$to_res =   mysql_query('select a.rd_rec_id
							   from rec_details a
						  left join rec_details b on b.rd_rec_id = a.rd_rec_id
						  left join records on rec_id = b.rd_val
							  where a.rd_type = 199
								and a.rd_val = ' . $bib . '
								and b.rd_type = 202
								and rec_temporary != 1');        // 199 = linked resource

	if (mysql_num_rows($from_res) <= 0  &&  mysql_num_rows($to_res) <= 0) return;

	while ($reln = mysql_fetch_assoc($from_res)) {
		writeRelatedRecord(fetch_relation_details($reln['rd_rec_id'], true), $depth);
	}
	while ($reln = mysql_fetch_assoc($to_res)) {
		writeRelatedRecord(fetch_relation_details($reln['rd_rec_id'], false), $depth);
	}
}

function writeRelatedRecord ($rel, $depth) {
	global $XML;
	global $MAX_DEPTH;
	global $RFT,$ONT;

	$XML .= '<related';
	if (@$rel['ID']) $XML .= ' id="' . htmlspecialchars($rel['ID']) . '"';
	if (@$rel['RelationValue']) {
		$XML .= ' type="' . htmlspecialchars($rel['RelationValue']) . '"';	//saw Enum change
	}else if (@$rel['RelationType']) {
		$XML .= ' type="' . htmlspecialchars($rel['RelationType']) . '"';	//saw Enum change
	}
	if (@$rel['OntologyID'] && array_key_exists($rel['OntologyID'],$ONT))
		 $XML .= ' ontology="' . htmlspecialchars($ONT[$rel['OntologyID']]['ont_name']) . '"';
	if (@$rel['Title']) $XML .= ' title="' . htmlspecialchars($rel['Title']) . '"';
	if (@$rel['Notes']) $XML .= ' notes="' . htmlspecialchars($rel['Notes']) . '"';
	if (@$rel['StartDate']) $XML .= ' start="' . htmlspecialchars($rel['StartDate']) . '"';
	if (@$rel['EndDate']) $XML .= ' end="' . htmlspecialchars($rel['EndDate']) . '"';
	$XML .= '>';

	if (@$rel['OtherResource']) {
		$rec_id = $rel['OtherResource']['rec_id'];
		writeGeneralData($rec_id, $depth);
		if ($depth <= $MAX_DEPTH) {
			writeDetails($rec_id, $depth);
		} else {
			fetchExtraDetails($rec_id, $rel['OtherResource']['rec_type']);
		}
	} else {
		$XML .=  '<title>'. htmlspecialchars($rel['Title']). '</title>';
	}

	$XML .= '</related>';
}

function fetchExtraDetails($rec_id, $reftype) {
	global $RELATED_DETAILS;
	global $RQS;
	global $DTN;

	if (! $RELATED_DETAILS) return '';

	$res = mysql_query('select rd_type, rd_val, rd_file_id from rec_details where rd_rec_id = ' . $rec_id . ' and rd_type in (' . join(',', $RELATED_DETAILS) . ') order by rd_type, rd_val');
	while ($row = mysql_fetch_assoc($res)) {
		writeTag($reftype, $row['rd_type'], $row['rd_val'], $row['rd_file_id']);
	}
}


function writeWootContent($rec_id) {
	global $XML;

	$result = loadWoot(array("title" => "record:$rec_id"));
	if (! $result["success"]) {
		return;
	}

	$XML .= "<woot title=\"record:$rec_id\">";
	foreach ($result["woot"]["chunks"] as $chunk) {
		$XML .= $chunk["text"];
	}
	$XML .= "</woot>";
}


/**
 * This function gets all the records that are the resultset at that moment. It puts all
 * the records ids in an array.
 *
 * return array[] an array with the records ids of the resultset
 */
function getRecords() {
	global $total_num_rows;

	$Bibs = array();

	if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

	if ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')
		$search_type = BOOKMARK;	// my bookmarks
	else
		$search_type = BOTH;	// all records

	// NOTE THAT WE LIMIT THE OUTPUT to MAX_ROWS entries
	$res = mysql_query(REQUEST_to_query('select SQL_CALC_FOUND_ROWS rec_id ', $search_type).' limit ' . MAX_ROWS);
	$fres = mysql_query('select found_rows()');
	$num_rows = mysql_fetch_row($fres); $total_num_rows = $num_rows[0];

	// this fills the array with the records ids
	while ($row = mysql_fetch_assoc($res)) {
		$Bibs[] = $row['rec_id'];
	}

	return $Bibs;
}
/**
 * This function checks if the bibiographic entry is workgroup-protected
 * @param string $rec_id  records id of a record
 * return boolean
 */
function have_bib_permissions_forall($rec_id) {

	$rec_id = intval($rec_id);
	$query = 'select * from records where rec_id='.$rec_id;
	$res = mysql_query($query);

	if (mysql_num_rows($res) < 1) return false;

	$bib = mysql_fetch_assoc($res);

	if ($bib['rec_wg_id']  &&  $bib['rec_visibility'] == 'Hidden') {
		return false;
	}

	return true;
}
/**
 * This function checks if the record is single and uses have_bib_permissions_forall function
 * to check if it is workgroup-protected
 * @param string $q  search query from url
 * return boolean
 */
function single_record_retrieval($q) {
//check if it is a single search for a specific id
//                  match on word boundary for "id" with optional "s" followed by : followed by a number on the word boundary ignoring case
   if (preg_match ('/\bids?:([0-9]+)(?!,)\b/i', $q, $matches)) {
		$rec_id = $matches[1];
		if (have_bib_permissions_forall($rec_id)) return true;
	}
	return false;
}

// EOF
?>
