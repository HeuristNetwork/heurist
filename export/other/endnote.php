<?php

define('SEARCH_VERSION', 1);

header('Content-type: text/plain');
require_once(dirname(__FILE__).'/../../common/config/manageInstancesDeprecated.php');

if ($_REQUEST['pub_id']) {
	require_once(dirname(__FILE__).'/../../common/connect/bypassCredentialsForPublished.php');
} else {
	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	if (!is_logged_in()) {
	        header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE);
	        return;
	}
}

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once('.ht_stdefs');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');


$heurist_to_refer_map = array(
	158 => 'A',	// Creator
	159 => 'D', // Year
	160 => 'T', // Title
	163 => '', // Number of pages
	164 => 'P', // Start page
	165 => 'P', // End page
	166 => 'D', // Date
	169 => 'N', // Part / Issue
	171 => 'I', // Publisher
	172 => 'C', // Place published_searches
	173 => '!', // Title - short
	174 => 'O', // Title - alternate
	175 => 'R', // Type of Work
	176 => '7', // Edition / Version
	177 => '', // Start Date
	178 => '', // End Date
	179 => '', // Name
	180 => '', // Organisation
	181 => 'C', // Location
	182 => '', // Recipient
	183 => '9', // Degree
	184 => 'V', // Volume
	185 => 'N', // Number of Volumes
	187 => '@', // ISBN
	188 => '@', // ISSN
	189 => '', // Accession Number
	190 => 'O', // Call Number
	191 => 'X', // Abstract
	192 => '', // Availability
	193 => 'G', // Language
	194 => '', // Edited
	196 => '', // AuthorEditor
	197 => '', // Sample ID
	198 => '', // DOI
	199 => '', // Resource
	200 => '', // RelationType
	201 => '', // Notes
	202 => '', // Primary resource
	203 => '', // Organisation type
	204 => '', // Method Type
	205 => '', // Discipline
	206 => '', // Funding Type
	207 => '', // Funding Amount
	210 => '', // X-Longitude
	211 => '', // Y-latitude
	212 => '', // EventDomain
	213 => '', // Attested Date
	214 => '', // Terminus Post Quem
	215 => '', // Terminus Ante Quem
	216 => '', // Place Name
	217 => '', // ConferenceRef
	218 => '', // xxxx
	219 => '', // xxxxx
	220 => '', // xxxxxx
	221 => '', // AssociatedFile
	222 => '', // Logo image
	223 => '', // Thumbnail
	224 => '', // Images
	225 => '', // Journal Volume Reference
	226 => '', // Journal Reference
	227 => '', // Book Reference
	228 => '', // Book Series Reference
	229 => '', // Publisher Reference
	230 => '', // Geographic object
	231 => '', // Associated File
	232 => '', // Event type
	233 => '', // Start time
	234 => '', // End time
	235 => '', // Project scope
	236 => '', // Magazine Volume Reference
	237 => '', // Newspaper Volume Reference
	238 => '', // Conference Proceedings Reference
	241 => '', // Magazine Reference
	242 => '', // Newspaper Reference
	243 => '' // Thesis type
);

$parent_detail_types = array(
	217, 225, 226, 227, 228, 229, 236, 237, 238, 241, 242
);

$reftype_parent_map = array(
	// Book chapter
	4 => array(
		// Book
		5 => array(
			160 => 'B',			// Title
			173 => 'SUPPRESS',	// Title - short		*** SUPPRESS OUTPUT ***
			174 => 'SUPPRESS',	// Title - alternate
			191 => 'SUPPRESS'	// Abstract
		),
		// Book Publisher & Series
		44 => array(
			158 => 'Y',			// Series Editor
			160 => 'S',			// Title
			191 => 'SUPPRESS'	// Abstract
		),
		// Publisher
		30 => array(
			160 => 'I'			// Publisher Name
		)
	),

	// Book
	5 => array(
		// Book Publisher & Series
		44 => array(
			158 => 'E',			// Series Editor
			160 => 'B',			// Title
			191 => 'SUPPRESS'	// Abstract
		),
		// Publisher
		30 => array(
			160 => 'I'			// Publisher Name
		)
	),

	// Book Publisher & Series
	44 => array(
		// Publisher
		30 => array(
			160 => 'I'			// Publisher Name
		)
	),

	// Journal Article
	3 => array(
		// Journal Volume
		28 => array(
			160 => 'SUPPRESS' // Title
		),
		// Journal
		29 => array(
			160 => 'J' // Title
		)
	),

	// Journal Volume
	28 => array(
		// Journal
		29 => array(
			160 => 'J' // Title
		)
	),

	// Conference Paper
	31 => array(
		// Conference Proceedings
		7 => array(
			158 => 'E',	// Editor
			160 => 'SUPPRESS',	// Full Proceedings Title
			173 => 'SUPPRESS',	// Title - short
			174 => 'SUPPRESS'	// Title - alternate
		),
		// Conference
		49 => array(
			160 => 'B'	// Title
		)
	),

	// Conference Proceedings
	7 => array(
		// Conference Proceedings
		7 => array(
			158 => 'E'	// Editor
		),
		// Conference
        49 => array(
            160 => 'B'  // Title
        )
	)
);


if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if (! @$_REQUEST['w']  ||  $_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')	// my bookmark entries
	$search_type = BOOKMARK;
else if ($_REQUEST['w'] == 'b'  ||  $_REQUEST['w'] == 'biblio')				// records entries I haven't bookmarked yet
	$search_type = BIBLIO;
else if ($_REQUEST['w'] == 'a'  ||  $_REQUEST['w'] == 'all')				// all records entries
	$search_type = BOTH;
else
	return;	// wwgd


mysql_connection_db_select(DATABASE);

$REFTYPE = mysql__select_assoc('defRecTypes', 'rty_ID', 'rty_Name', '1');

$res = mysql_query(REQUEST_to_query('select distinct rec_ID, rec_URL, rec_ScratchPad, rec_RecTypeID ', $search_type));

while ($row = mysql_fetch_assoc($res))
	print_biblio($row);


function print_biblio($bib) {
	global $REFTYPE;
	$output = '';

	$output .= print_bib_details($bib['rec_ID'], $bib['rec_RecTypeID'], array());

	if ($bib['rec_URL']) $output .= '%U ' . $bib['rec_URL'] . "\n";

	$kwds = mysql__select_array('usrBookmarks left join usrRecTagLinks on rtl_RecID = bkm_RecID
	                                       left join usrTags on tag_ID = rtl_TagID',
	                            'tag_Text',
	                            'bkm_recID = ' . $bib['rec_ID'] . ' and bkm_UGrpID = ' . get_user_id() . ' and tag_Text != "" and tag_Text is not null');
	if (count($kwds)) $output .= '%K ' . join(', ', $kwds) . "\n";

/*
	if ($bib['rec_ScratchPad'])
		$output .= '%Z ' . preg_replace("/\n\n+/s", "\n", str_replace('%', '', $bib['rec_ScratchPad'])) . "\n";
*/

	if (strlen($output))
		print '%0 ' . $REFTYPE[$bib['rec_RecTypeID']] . "\n" . $output . "\n";
}


function print_bib_details ($rec_id, $base_reftype, $visited) {
	global $heurist_to_refer_map;
	global $parent_detail_types;
	global $reftype_parent_map;
	$output = '';

	array_push($visited, $rec_id);

	$res = mysql_query('select rec_RecTypeID from Records where rec_ID = ' . $rec_id);
	$row = mysql_fetch_assoc($res);
	$rt = $row['rec_RecTypeID'];

	$details = array();
	$res = mysql_query('select dtl_DetailTypeID, dtl_Value
	                      from recDetails
	                     where dtl_RecID = ' . $rec_id);
	while ($row = mysql_fetch_assoc($res)) {

		if (! @$details[$row['dtl_DetailTypeID']]) {
			$details[$row['dtl_DetailTypeID']] = $row['dtl_Value'];
		} else if (@$details[$row['dtl_DetailTypeID']]  &&  ! is_array($details[$row['dtl_DetailTypeID']])) {
			$details[$row['dtl_DetailTypeID']] = array($details[$row['dtl_DetailTypeID']] , $row['dtl_Value']);
		} else if (@$details[$row['dtl_DetailTypeID']]  &&  is_array($details[$row['dtl_DetailTypeID']])) {
			array_push($details[$row['dtl_DetailTypeID']], $row['dtl_Value']);
		}
	}

	// authors
	if (@$details['158']) {
		$res = mysql_query("select rec_Title from Records where rec_ID in (" .
							(is_array($details['158']) ? join(",", $details['158']) : $details['158']) . ")");
		$details['158'] = array();
		while ($row = mysql_fetch_assoc($res)) {
			array_push($details['158'], $row['rec_Title']);
		}
	}

	// page numbers
	if (@$details['164']  &&  @$details['165']) {
		$details['164'] .= '-' . $details['165'];
		unset($details['165']);
	}

	foreach ($details as $rd_type => $detail) {
		// suppress output of some fields
		if (@$reftype_parent_map[$base_reftype][$rt][$rd_type] === 'SUPPRESS'  ||
			@$heurist_to_refer_map[$rd_type] === 'SUPPRESS') {
			continue;
		// type specific overrides e.g. B instead of T for Book Title
		} else if (@$reftype_parent_map[$base_reftype][$rt][$rd_type]) {
			if (is_array($detail)) {
				foreach ($detail as $val) {
					$output .= '%' . $reftype_parent_map[$base_reftype][$rt][$rd_type] . ' ' . $val . "\n";
				}
			} else {
				$output .= '%' . $reftype_parent_map[$base_reftype][$rt][$rd_type] . ' ' . $detail . "\n";
			}
		// standard output types
		} else if (@$heurist_to_refer_map[$rd_type]) {
			if (is_array($detail)) {
				foreach ($detail as $val) {
					$output .= '%' . $heurist_to_refer_map[$rd_type] . ' ' . $val . "\n";
				}
			} else {
				$output .= '%' . $heurist_to_refer_map[$rd_type] . ' ' . $detail . "\n";
			}
		// parent reference - recurse
		} else if (in_array($rd_type, $parent_detail_types)) {
			if (is_array($detail)) {
				// error - should only have one parent
			} else {
				if (! in_array(intval($detail), $visited))	// avoid infinite recursion
					$output .= print_bib_details(intval($detail), $base_reftype, $visited);
			}
		// undefined - dump out under %O
		/* no - don't output it
		} else {
			if (is_array($detail)) {
				foreach ($detail as $val) {
					$output .= '%O ' . $val . "\n";
				}
			} else {
				$output .= '%O ' . $detail . "\n";
			}
		*/
		}
	}

	return $output;
}

?>
