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


require_once(dirname(__FILE__)."/../importerBaseClass.php");


$zotero_to_heurist_detail_map = array(
	'book' => array(
/*		'author' => '158',
		'editor' => '158',
		'title' => '160',
		'shortTitle' => '173',
		'date' => '166',
		'year' => '159',
		'pages' => '163',
		'edition' => '176',
		'volume' => '184',
		'ISBN' => '187',
		'callNumber' => '190',

		'series' => ':160',
		'numberOfVolumes' => ':185',
		'publisher' => '::160',
		'place' => '::172'
*/
		'author' => '158',
		'editor' => '158',
		'edited' => '194',		//SAW processed from editor if not suppplied
		'contributor' => '158',  //SAW added
		'translator' => '348',  //SAW added
		'date' => '166',
		'year' => '159',		//SAW processed from date if not available
		'title' => '160',
		'shortTitle' => '173',
		'seriesNumber' => '184',  //SAW  ?? how is this different from volume
		'volume' => '184',
		'ISBN' => '187',
		'abstractNote' => '560', // SAW changed mapping '191',
		'pages' => '163',
		'edition' => '176',
		'language' => '193',
		'accessDate' => '349',
		'rights' => '290',
		'repository' => '350',
        'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'series' => ':160',
		'numberOfVolumes' => ':185',

		'publisher' => '::160',
		'place' => '::172'
	),

	'bookSection' => array(
/*		'author' => '158',
		'editor' => ':158',
		'title' => '160',
		'shortTitle' => '173',
	//	'pages' => '163',
		'start-page' => '164',
		'end-page' => '165',

		'bookTitle' => ':160',
		'date' => ':166',
		'year' => ':159',
		'edition' => ':176',
		'volume' => ':184',
		'ISBN' => ':187',
		'callNumber' => ':190',

		'series' => '::160',
		'numberOfVolumes' => '::185',
		'publisher' => ':::160',
		'place' => ':::172'
*/
		'author' => '158',
		'title' => '160',
		'shortTitle' => '173',
		'abstractNote' => '191',
		'language' => '193',
		'accessDate' => '349',
		'rights' => '290',
		'repository' => '350',
		'start-page' => '164',
		'end-page' => '165',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'bookTitle' => ':160',
		'editor' => ':158',
		'date' => ':166',
		'year' => ':159',
		'edition' => ':176',
		'seriesNumber' => ':184',	//SAW this should be it's own value
		'volume' => ':184',
		'ISBN' => ':187',

		'series' => '::160',
		'seriesEditor' => '::158',
		'numberOfVolumes' => '::185',

		'publisher' => ':::160',
		'place' => ':::172'

	),

	'journalArticle' => array(
/*		'author' => '158',
		'editor' => ':158',
		'title' => '160',
		'shortTitle' => '173',
		'start-page' => '164',
		'end-page' => '165',

		'abstractNote' => '191',
		'DOI' => '198',
		'issue' => ':169',
		'volume' => ':184',
		'date' => ':166',
		'year' => ':159',

		'ISSN' => '::188',
		'publicationTitle' => '::160',
		'journalAbbreviation' => '::173'
*/
		'author' => '158',
		'title' => '160',
		'shortTitle' => '173',
		'DOI' => '198',
		'abstractNote' => '560',
		'language' => '193',
		'accessDate' => '349',
		'rights' => '290',
		'repository' => '350',
		'callNumber' => '190',
		'start-page' => '164',	//SAW preprocessed from pages if not present
		'end-page' => '165',		//SAW preprocessed from pages if not present
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'publicationTitle' => ':160',  // SAW  changed
		'editor' => ':158',
		'volume' => ':184',
		'issue' => ':169',
		'date' => ':166',
		'year' => ':159',

		'series' => '::160',		// SAW added
		'journalAbbreviation' => '::173',
		'seriesTitle' => '::174',
		'seriesText' => '::303',  //SAW changed to summary
		'ISSN' => '::188',

		'publisher' => ':::160',	//SAW added
		'place' => ':::172'			//SAW added

	),

	'thesis' => array(
/*		'author' => '158',
		'editor' => ':158',
		'title' => '160',
		'shortTitle' => '173',
		'abstractNote' => '191',
		'pages' => '163',
		'date' => '166',
		'year' => '159',

		'university' => ':160',
		'thesisType' => '243'
*/
		'author' => '158',
		'date' => '166',
		'year' => '159',
		'title' => '160',
		'shortTitle' => '173',
		'abstractNote' => '560', //SAW changed from 191
//		'numberOfVolumes' => '185',
//		'volume' => '184',
		'pages' => '163',
		'language' => '193',
		'accessDate' => '349',
//		'edition' => '176',
		'rights' => '290',
		'thesisType' => '243',
		'repository' => '350',
		'callNumber' => '190',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'place' => ':172',
		'university' => ':160'
	),

	'report' => array(
/*		'author' => '158',
		'editor' => ':158',
		'title' => '160',
		'shortTitle' => '173',
		'abstractNote' => '191',
		'pages' => '163',
		'date' => '166',
		'year' => '159',

		'institution' => ':160',
		'reportType' => '243'
*/
		'author' => '158',
		'date' => '166',
		'year' => '159',
		'title' => '160',
		'volume' => '184',
		'shortTitle' => '173',
		'reportType' => '175',
		'abstractNote' => '560',
		'numberOfVolumes' => '185',
		'pages' => '163',
		'language' => '193',
		'accessDate' => '349',
		'reportNumber' => '176',
		'rights' => '290',
		'repository' => '350',
		'callNumber' => '190',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'seriesTitle' => ':160',
		'editor' => ':158',

		'place' => '::172',
		'institution' => '::160'
	),

	'conferencePaper' => array(
/*		'author' => '158',
		'editor' => ':158',
		'title' => '160',
		'shortTitle' => '173',
		'abstractNote' => '191',
		'start-page' => '164',
		'end-page' => '165',
		'conferenceName' => '217:160',

		'date' => ':166',
		'year' => ':159',

		'proceedingsTitle' => ':160',
		'DOI' => ':198',
		'ISBN' => ':187',
		'volume' => ':184',

		'series' => '::160',

		'publisher' => ':::160',
		'place' => ':::172'
*/
		'author' => '158',
		'title' => '160',
		'shortTitle' => '173',
		'abstractNote' => '560',
		'language' => '193',
		'accessDate' => '349',
		'rights' => '290',
		'repository' => '350',
		'callNumber' => '190',
		'start-page' => '164',
		'end-page' => '165',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'bookTitle' => ':160',
		'date' => ':159',
		'seriesNumber' => ':184',
		'volume' => ':184',
		'ISBN' => ':187',
		'edition' => ':176',

		'series' => '::160',
		'numberOfVolumes' => '::185',

		'place' => ':::172',
		'publisher' => ':::160'

	),

	'conferenceProceedings' => array(
		'author' => '158',
		'date' => '159',
		'title' => '160',
		'seriesNumber' => '184',
		'shortTitle' => '173',
		'ISBN' => '187',
		'abstractNote' => '191',
		'numberOfVolumes' => '185',
		'pages' => '163',
		'language' => '193',
		'accessDate' => '349',
		'edition' => '176',
		'rights' => '290',
		'repository' => '350',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'series' => ':160',
		'numberOfVolumes' => ':185',

		'place' => '::172',
		'publisher' => '::160',
	),

	'magazineArticle' => array(
		'author' => '158',
		'title' => '160',
		'shortTitle' => '173',
		'DOI' => '198',
		'abstractNote' => '191',
		'language' => '193',
		'accessDate' => '349',
		'accessDate' => '349',
		'rights' => '290',
		'repository' => '350',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'volume' => ':184',
		'issue' => ':169',
		'date' => ':166',
		'year' => ':159',

		'publicationTitle' => '::160',
		'magazineAbbreviation' => '::173',
		'ISSN' => '::188',

		'start-page' => '164',
		'end-page' => '165'
	),

	'newspaperArticle' => array(
/*		'author' => '158',
		'title' => '160',
		'shortTitle' => '173',
		'date' => '166',
		'year' => '159',
		'abstractNote' => '191',
		'pages' => '163',
		'start-page' => '164',
		'end-page' => '165',

		'section' => ':169',	// part / issue
		'publicationTitle' => '::160',

		'ISSN' => '188'
*/
		'author' => '158',
		'title' => '160',
		'shortTitle' => '173',
		'DOI' => '198',
		'abstractNote' => '191',
		'language' => '193',
		'accessDate' => '349',
		'accessDate' => '349',
		'rights' => '290',
		'repository' => '350',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added

		'volume' => ':184',
		'issue' => ':169',
		'section' => ':169',	// part / issue
		'date' => ':166',
		'year' => ':159',

		'publicationTitle' => '::160',
		'newspaperAbbreviation' => '::173',
		'ISSN' => '::188',

		'start-page' => '164'
	),

	'webpage' => array(
		'author' => '158',
		'date' => '166',
		'year' => '159',
		'title' => '160',
		'shortTitle' => '173',
		'abstractNote' => '191',
		'numberOfVolumes' => '185',
		'volume' => '184',
		'pages' => '163',
		'language' => '193',
		'accessDate' => '349',
		'edition' => '176',
		'rights' => '290',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added
		'repository' => '350'
	),

	'' => array(	// generic mappings
		'author' => '158',
		'editor' => '158',
		'title' => '160',
		'shortTitle' => '173',
		'date' => '166',
		'year' => '159',
		'abstractNote' => '191',
		'pages' => '163',
		'edition' => '176',
		'volume' => '184',
		'repository' => '350',
		'ISBN' => '187',
		'callNumber' => '190',
		'DOI' => '198',
		'tag' => 'tag',		// SAW added
		'url' => 'url',			// SAW added
		'ISSN' => '188'
	)
);

$zotero_to_heurist_type_map = array(
	'book' => array(5, 44, 30),
	'bookSection' => array(4, 5, 44, 30),
	'journalArticle' => array(3, 28, 29, 30),
	'thesis' => array(13, 30),
	'conferencePaper' => array(31, 7, 44, 30),
	'report' => array(12, 30),
	'webpage' => array(1),

	'magazineArticle' => array(10, 67, 68),
	'newspaperArticle' => array(9, 66, 69, 30),
//	'manuscript' => array(46),
//	'interview' => array(46),
	'film' => array(84),
	'artwork' => array(74),
//	'attachment' => array(46),
//	'bill' => array(46),
//	'case' => array(46),
//	'hearing' => array(46),
//	'patent' => array(46),
//	'statute' => array(46),
	'email' => array(11),
//	'map' => array(46),
//	'blogpost' => array(46),
//	'instantmessage' => array(46),
//	'forumpost' => array(46),
	'audiorecording' => array(83),
	'presentation' => array(82),
	'videorecording' => array(84),
	'tvbroadcast' => array(84),
	'radiobroadcast' => array(83),
	'podcast' => array(83),
//	'computerprogram' => array(46),
	'document' => array(46),
	'encyclopediaarticle' => array(78),
	'dictionaryentry' => array(78)
);


class HeuristZoteroParser extends HeuristForeignParser {

	function recogniseFile(&$file) {
		return false;
	}

	function parserDescription() { return 'Zotero parser'; }

	function parseFile(&$file) { }

	function parseRequest($request) {
		if (! array_key_exists('itemID', $request))
			return array( array('Internal error - the HeuristZotero plugin is not sending any data'), NULL );

		$number_of_entries = count($request['itemID']);
		$entries = array();

		for ($i=0; $i < $number_of_entries; ++$i) {
			$entry_details = array();

			foreach (array_keys($request) as $key) {
				if (@$request[$key][$i])
					$entry_details[$key] = $request[$key][$i];
			}

			$entries[] = new HeuristZoteroEntry($entry_details);
		}

		return array( NULL, &$entries );
	}

	function outputEntries(&$entries) {
		$output = '';

		foreach ($entries as $i => $entry) {
			$output .= "Zotero item #" . $entries[$i]->_raw['itemID'] . ":\n";
			if ($entries[$i]->getErrors()) {
				foreach ($entries[$i]->getErrors() as $error)
					$output .= ' error: ' . $error . "\n";
			}
			if ($entries[$i]->getValidationErrors()) {
				foreach ($entries[$i]->getValidationErrors() as $error)
					$output .= ' error: ' . $error . "\n";
			}
			if ($entries[$i]->getWarnings()) {
				foreach ($entries[$i]->getWarnings() as $error)
					$output .= ' warning: ' . $error . "\n";
			}

			// output the itemType and title first
			$output .= '  [title] = ' . $entries[$i]->_raw['title'] . "\n";
			$output .= '  [itemType] = ' . $entries[$i]->_raw['itemType'] . "\n";
			foreach ($entries[$i]->_raw as $key => $val) {
				if ($key == 'itemID'  ||  $key == 'itemType'  ||  $key == 'title') continue;
				if (! is_array($val))
					$output .= '  [' . $key . '] = ' . $val . "\n";
				else {
					foreach ($val as $v)
						$output .= '  [' . $key . '] = ' . $v . "\n";
				}
			}

			$output .= "\n\n";
		}

		return $output;
	}

	function getReferenceTypes() {
		static $known_types = array('book', 'bookSection', 'journalArticle', 'thesis', 'conferencePaper',
'magazineArticle', 'newspaperArticle', 'letter', 'manuscript', 'interview', 'film', 'artwork', 'webpage', 'attachment', 'report',
'bill', 'case', 'hearing', 'patent', 'statute', 'email', 'map', 'blogPost', 'instantMessage', 'forumPost', 'audioRecording',
'presentation', 'videoRecording', 'tvBroadcast', 'radioBroadcast', 'podcast', 'computerProgram', 'document', 'encyclopediaArticle',
'dictionaryEntry' );
		return $known_types;
	}

	function guessReferenceType(&$entry, $allowed_types=NULL) {
		if (@$entry->_type) return $entry->_type;
		return NULL;
	}
}


class HeuristZoteroEntry extends HeuristForeignEntry {
	var $_raw;

	var $_type, $_origType;
	var $_fields;
	var $_zoteroID;

	function HeuristZoteroEntry($raw) {
		global $zotero_to_heurist_type_map;

		$this->HeuristForeignEntry();

		$this->_raw = $raw;
		$this->_origType = $raw['itemType'];

		if (array_key_exists($raw['itemType'], $zotero_to_heurist_type_map)) {
			$this->_type = $raw['itemType'];
		} //else {
			// import anything of an unknown type as a "document"
		//	$this->_type = 'document';
		//}
		$this->_zoteroID = $raw['itemID'];
	}

	function getZoteroID() { return $this->_zoteroID; }

	function getReferenceType() {
		global $zotero_to_heurist_type_map;
		if (array_key_exists($this->_origType, $zotero_to_heurist_type_map)) {
			return $this->_type;  // why does it check original type but return type
		}
		else {
			return NULL;
		}
	}

	function getErrors() {
		global $zotero_to_heurist_type_map;
		if (! array_key_exists($this->_origType, $zotero_to_heurist_type_map)) {
			return array("record #" . $this->getZoteroID() . " has unknown type '" . $this->_origType . "'");
		}
		return array();
	}

	function crosswalk() {
		global $zotero_to_heurist_detail_map, $zotero_to_heurist_type_map;

		$fields = $this->_raw;
		if (array_key_exists($fields['itemType'], $zotero_to_heurist_type_map))
			unset($fields['itemType']);

		// ignore these fields  SAW FIXME  we should have a way to include ignoring input fields in the map or just put all unused field in scratch pad
		unset($fields['dateModified']);
		unset($fields['accessDate']);
		unset($fields['firstCreator']);

		if (array_key_exists($this->_type, $zotero_to_heurist_detail_map))
			$type_map = $zotero_to_heurist_detail_map[$this->_type];
		else
			$type_map = $zotero_to_heurist_detail_map[''];

		// cook the pages and page fields   FIXME  assumes format "pages sp - ep" consecutive page numbering preceded by the word pages
		if (@$fields['pages']  &&  ! @$fields['start-page'] && array_key_exists('start-page', $type_map)) {
			if (preg_match('/[pages|pp\.]*\s*(\\d+)\\s*-\\s*(\\d+)/i', $fields['pages'], $matches)) {
				$fields['start-page'] = $matches[1];
				$fields['end-page'] = $matches[2];
				if ($matches[0] == trim($fields['pages']))
					unset($fields['pages']);
			}
		}
		else if (@$fields['page']  &&  ! @$fields['start-page'] && array_key_exists('start-page', $type_map)) {
			if (preg_match('/(\\d+)\\s*-\\s*(\\d+)/', $fields['page'], $matches)) {
				$fields['start-page'] = $matches[1];
				$fields['end-page'] = $matches[2];
				if ($matches[0] == trim($fields['page']))
					unset($fields['page']);
			}
		}

		//cook the date so we have a year value
		if (@$fields['date']) {
			if (preg_match('/^([^-]+?)-00-00\s+(.+)/', $fields['date'], $matches)) {
				// typically a YEAR
				if (! @$fields['year']) $fields['year'] = $matches[1];
				$fields['date'] = $matches[2];
			} // strip off any dupes.  FIXME  this should check the format specifically for date.
			else if (preg_match('/^(\S+)\s.*/', $fields['date'], $matches)) {
				if (! @$fields['year']) $fields['year'] = intval($matches[1]);  //FIXME  matches is a date and intval returns the year which seems to be undocumented behavior
				$fields['date'] = $matches[1];
			}
		}
		// create the Heurist containment heirarchy for this Zotero biblio type
		$last_entry = NULL;
		$new_entry = NULL;
		foreach ($zotero_to_heurist_type_map[$this->_type] as $rt) {
			$entry = new HeuristNativeEntry($rt);

			if ($new_entry == NULL) $new_entry = &$entry;
			else $last_entry->setContainerEntry($entry);

			$last_entry = &$entry;
		}
		// go through the detail types map and if there is a field value then use the map to
		// attach the value to the correct entry in the containment heirarchy
		foreach ($type_map as $zoteroType => $heuristTag) {
			if (! @$fields[$zoteroType]) continue;

			if (strpos($heuristTag, ':') === FALSE) {	// straightforward, set field directly
				$entry = &$new_entry;
			} else {
				if ($heuristTag[0] == ':') {
					unset($entry);
					$entry = &$new_entry;
					while ($heuristTag[0] == ':') {
						unset($tmpEntry);
						$tmpEntry = &$entry->_container;  // why doesn't it use getContainerEntry references?
						unset($entry);
						$entry = &$tmpEntry;
						$heuristTag = substr($heuristTag, 1);
					}
				} else { //this is a case where we have a reference pointer to a record and we want to add a field to teh record.  FIXME never gets set ?depricate?
					preg_match('/(\\d+):(\\d+)/', $heuristTag, $matches);
					$entry = &$new_entry->_references[$matches[1]];
					$heuristTag = $matches[2];
				}
			}

			if (! is_array($fields[$zoteroType])) {
				if ($heuristTag == '243') //MAGIC NUMBER//thesis type
					$entry->addField(new HeuristNativeField($heuristTag, $fields[$zoteroType], decode_thesis_type($fields[$zoteroType])));
				else if (is_enum_field($heuristTag))
					$entry->addField(new HeuristNativeField($heuristTag, $fields[$zoteroType], decode_enum($heuristTag, $fields[$zoteroType])));
				else if ($heuristTag == '160') {//MAGIC NUMBER	// title field, cook it a little
					$val = preg_replace('/\\s*[&]\\s*/', ' and ', $fields[$zoteroType]);
					$val = preg_replace('/(.+),\\s*(The|A|An)$/', "$2 $1", $val);	// what is this, a damn library catalogue?

					$entry->addField(new HeuristNativeField($heuristTag, $val));
				}
				else
					$entry->addField(new HeuristNativeField($heuristTag, $fields[$zoteroType]));
			} else {
				if ($zoteroType == 'tag') {
					foreach ($fields[$zoteroType] as $f)
						$entry->addTag($f);
				} else {
					foreach ($fields[$zoteroType] as $f) {
						$entry->addField(new HeuristNativeField($heuristTag, $f));
					}
				}
			}

			unset($fields[$zoteroType]);
		}

		// we unset the zotero fields as they are crosswalked; anything that's left should get sent to the scratch field of the base-level entry
		foreach ($fields as $zoteroType => $value)
			$new_entry->addField(new HeuristNativeField(0, $zoteroType . ' ' . $value));

		$new_entry->_foreign = &$this;

		return $new_entry;
	}

	function setReferenceType($type) { $this->_type = $type; }
}

?>
