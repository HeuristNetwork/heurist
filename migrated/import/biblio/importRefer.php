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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__)."/../importerBaseClass.php");


$refer_to_heurist_map = array(
	/* Hopefully this is fairly easy to interpret:
	   Each array entry corresponds to a REFER entry reference type;
	   the rvalues are associative arrays looking up the REFER tag type to the Heurist dty_ID.
	   Some rvalue-rvalues are prefixed with one or more colons; these indicate that the REFER
	   value doesn't go in the Heurist records entry, but rather in the records's container (:),
	   container container (::) et cetera.

	   Single-character rvalue-lvalues are straight outta REFER,
	   the more prosaic values with more than one character are derived,
	   e.g. an ISBN spotted in the O field.

	   There's a heap more of these in importer.php, line 1180 onwards;
	   this is non-exhaustive, and remember that EndNote REFER may have subtle differences.
	 */

	'book section' => array(
/*		'A' => '158',
		'B' => ':160',
		'C' => ':::172',
		'E' => ':158',
		'I' => ':::160',
		'S' => '::160',
		'T' => '160',
		'V' => ':184',
		'X' => '191',
*/
		'A' => '158',    // Author
		'T' => '160',    // Title
		'!' => '173',    // Short Title
		'O' => '174',    // Alternate title
		'R' => '198',    // Electronic Resource Number
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',   // Access Date
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'E' => ':158',    // Editor
		'B' => ':160',    // Book Title
		'V' => ':184',    // Volume
		'@' => ':187',    // ISBN
		')' => ':176',    // Reprint Edition

		'S' => '::160',    // Series Title
		'6' => '::185',    // Number of Volumes
		'Y' => '::158',    // Series Editor

		'C' => ':::172',    // City
		'I' => ':::160',    // Publisher

		'Edited' => ':194',
		'Dew' => ':190',
		'Ed' => ':176',
		'ISBN' => ':187',
		'startP' => '164',
		'endP' => '165',
		'Yr' => ':159'
	),

	'book' => array(
/*		'A' => '158',
		'C' => '::172',
		'E' => '158',
		'I' => '::160',
		'P' => '163',
		'S' => ':160',
		'T' => '160',
		'B' => '160',
		'V' => '184',
		'X' => '191',
*/
		'A' => '158',    // Author
		'T' => '160',    // Title
		'V' => '184',    // Volume
		'7' => '176',    // Edition
		'?' => '348',    // Translator
		'!' => '173',    // Short Title/secondary title
		'O' => '174',    // Alternate Title
		'@' => '187',    // ISBN
		'R' => '198',    // Electronic Resource Number
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'6' => '185',    // Number of Volumes
		'P' => '163',    // Number of Pages
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',   // Access Date
		')' => '176',    // Reprint Edition
		'9' => '175',    // Type of Work
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'B' => ':160',    // Series Title
		'E' => ':158',    // Series Editor

		'C' => '::172',    // City
		'I' => '::160',    // Publisher

		'Edited' => '194',
		'Dew' => '190',
		'Ed' => '176',
		'ISBN' => '187',
		'Yr' => '159'
	),

/*
	'conference paper' => array(
		'A' => '158',
		'B' => ':160',
		'C' => ':181',
		'E' => ':158',
		'I' => '::171',
		'J' => ':160',
		'N' => ':185',
		'T' => '160',
		'V' => ':184',
	),
*/

	'conference proceedings' => array(
/*		'B' => ':160',
		'T' => '160',
		'C' => ':181',
		'R' => ':232',
		'E' => '158',
		'A' => '158',
		'I' => ':171',
		'J' => '160',
		'N' => '185',
		'V' => '184',
*/
		'A' => '158',    // Author
		'E' => '158',    // Editor
		'T' => '160',    // Title
		'V' => '184',    // Volume
		'7' => '176',    // Edition
		'?' => '348',    // Translator
		'!' => '173',    // Short Title/secondary title
		'O' => '174',    // Alternate Title
		'@' => '187',    // ISBN
		'R' => '198',    // Electronic Resource Number
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'6' => '185',    // Number of Volumes
		'P' => '163',    // Number of Pages
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',    // Access Date
		')' => '176',    // Reprint Edition
		'9' => '175',    // Type of Work
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'S' => ':160',    // Series Title
		'6' => ':185',    // Number of Volumes
		'Y' => ':158',    // Series Editor

		'C' => '::172',    // City
		'I' => '::160',    // Publisher

		'Dew' => '190',
		'ISBN' => '187',
		'Yr' => array('159', ':159'),
		'YrKey' => '159',
	),

	'magazine article' => array(
/*		'A' => '158',
		'B' => ':160',
		'C' => ':::172',
		'E' => ':158',
		'I' => ':::160',
		'T' => '160',
		'J' => '::160',
		'V' => ':184',
		'X' => '191',
*/
		'A' => '158',    // Author
		'8' => '166',    // Date
		'9' => '175',    // Type of Article
		'T' => '160',    // Title
		'!' => '173',    // Short Title
		'R' => '198',    // Electronic Resource Number
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',   // Access Date
		')' => '176',    // Reprint Edition
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'V' => ':184',    // Volume
		'N' => ':169',    // Issue

		'J' => '::160',    // Magazine
		'O' => '::173',    // Alternate magazine
		'@' => '::188',    // ISSN

		'Ed' => ':176',
		'ISSN' => '::188',
		'Yr' => ':159',
		'YrKey' => ':159'
	),

	'newspaper article' => array(
/*		'A' => '158',
		'B' => ':160',
		'C' => ':::172',
		'E' => ':158',
		'I' => ':::160',
		'T' => '160',
		'J' => '::160',
		'V' => ':184',
		'X' => '191',
*/
		'A' => '158',    // Author
		'8' => '166',    // Date
		'9' => '175',    // Type of Article
		'T' => '160',    // Title
		'!' => '173',    // Short Title
		'R' => '198',    // Electronic Resource Number
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',   // Access Date
		')' => '176',    // Reprint Edition
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'V' => ':184',    // Volume
		'N' => ':169',    // Issue

		'J' => '::160',    // Newspaper
		'O' => '::173',    //
		'@' => '::188',    // ISSN

		'Ed' => ':176',
		'ISSN' => '::188',
		'Yr' => ':159',
		'YrKey' => ':159'
	),

	'thesis' => array(
/*		'A' => '158',
		'C' => ':172',
		'P' => '163',
		'9' => '243',
		'R' => '243',
		'T' => '160',
		'X' => '191',
		'Q' => ':160',
		'I' => ':160',
		'S' => ':160',
*/
		'A' => '158',    // Author
		'T' => '160',    // Title
		'8' => '166',    // Date
		'?' => '348',    // Translator
		'!' => '173',    // Short Title
		'R' => '198',    // Electronic Resource Number
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'6' => '185',    // Number of Volumes

		'P' => '163',    // Number of Pages
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',   // Access Date
		')' => '176',    // Reprint Edition

		'9' => '175',    // Report Type
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'C' => ':172',    // City
		'B' => ':352',    // academic department
		'I' => ':160',    // Institution

		'Yr' => '159',
		'YrKey' => '159'
	),

	'journal article' => array(
/*		'A' => '158',
		'J' => '::160',
		'N' => ':185',
		'T' => '160',
		'V' => ':184',
		'X' => '191',
		'C' => ':::172',
		'Q' => ':::160',
		'I' => ':::160',
*/
		'A' => '158',    // Author
		'8' => '166',    // Date
		'9' => '175',    // Type of Article
		'T' => '160',    // Title
		'!' => '173',    // Short Title
		'R' => '198',    // Electronic Resource Number
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'&' => '164',    // Start page (end page doesn't exist)
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',   // Access Date
		')' => '176',    // Reprint Edition
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'V' => ':184',    // Volume
		'N' => ':169',    // Issue

		'J' => '::160',    // Journal
		'O' => '::173',    // Alternate Journal
		'@' => '::188',    // ISSN

		'Dew' => '::190',
		'startP' => '164',
		'endP' => '165',
		'Yr' => ':159',
		'YrKey' => ':159'
	),

	'report' => array(
/*		'A' => '158',
		'E' => ':158',
		'T' => '160',
		'B' => ':160',
		'P' => '163',
		'I' => ':160',
		'C' => ':172',
		'V' => ':184',
		'X' => '191',
*/
		'A' => '158',    // Author
		'D' => '166',    // Year
		'T' => '160',    // Title
		'V' => '184',    // Volume
		'?' => '348',    // Translator
		'!' => '173',    // Short Title
		'O' => '174',    // Alternate Title
		'R' => '198',    // Electronic Resource Number
		'9' => '175',    // Type
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'6' => '185',    // Number of Volumes
		'P' => '163',    // Number of Pages
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',   // Access Date
		')' => '176',    // Reprint Edition
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'B' => ':160',    // Series Title
		'E' => ':158',    // Series Editor

		'C' => '::172',    // City
		'I' => '::160',    // Institution

		'Dew' => '190',
		'Yr' => '159',
		'YrKey' => '159'
	),

	'personal communication' => array(
		'A' => '158',
		'T' => '160',
		'X' => '191',

		'Yr' => '159',
		'YrKey' => '159'
	),

	'web page' => array(
		'A' => '158',    // Author
		'T' => '160',    // Title
		'8' => '166',    // Date
		'?' => '348',    // Translator
		'!' => '173',    // Short Title
		'O' => '198',    // Alternate Title/secondary title
		'R' => '198',    // DOI
		'@' => '187',    // ISBN
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes
		'6' => '185',    // Number of Volumes
		'V' => '184',    // Volume
		'P' => '163',    // Number of Pages
		'G' => '193',    // Language
		'[' => '349',    // Access Date
		'\\' => '349',    // Access Date
		')' => '176',    // Reprint Edition
		'9' => '175',    // Type of medium
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider
		'Z' => '201',    // Notes

		'Yr' => '159',
		'YrKey' => '159'
	),
		'other document' => array(
		'A' => '158',    // Author
		'T' => '160',    // Title
		'8' => '177',    // Date
		'?' => '348',    // Translator
		'!' => '173',    // Short Title
		'R' => '198',    // DOI
		'X' => '191',    // Abstract
		'Z' => '201',    // Notes

		'P' => '163',    // Number of Pages
		'G' => '193',    // Language

		'9' => '175',    // Document Type
		'Q' => '174',    // Translated Title
		'~' => '350',    // Name of Database
		'W' => '351',    // Database Provider

		'Yr' => '159',
		'YrKey' => '159'
	)
);
$refer_to_heurist_map['generic'] = $refer_to_heurist_map['other document'];
$refer_to_heurist_map['unpublished work'] = $refer_to_heurist_map['report'];
$refer_to_heurist_map['edited book'] = $refer_to_heurist_map['book'];
$refer_to_heurist_map['electronic book'] = $refer_to_heurist_map['book'];
$refer_to_heurist_map['edited book section'] = $refer_to_heurist_map['book section'];
$refer_to_heurist_map['conference paper'] = $refer_to_heurist_map['book section'];
$refer_to_heurist_map['electronic article'] = $refer_to_heurist_map['journal article'];

$personal_notes_tags = array(
	/* REFER tags that will be put into the 'personal notes' field in the bookmark */
	'<' => 'Research Notes',
	'>' => 'Link to PDF',
	'M' => 'Accession Number',
	'L' => 'Call Number'
);

$refer_to_heurist_type_map = array(
	/* This might be a little harder to interpet:
	   Each array entry corresponds to a REFER entry reference type;
	   the rvalues are the Heurist reference types (rty_ID) for the records entry, its container, its container's container etc.

	   This is a data-driven version of the big switch statement in importer.php lines 1276 onwards.
	 */
	'book section' => array(4, 5, 44, 30),
	'edited book section' => array(4, 5, 44, 30),
	'conference paper' => array(4, 5, 44, 30),
	'book' => array(5, 44, 30),
	'edited book' => array(5, 44, 30),
	'electronic book' => array(5, 44, 30),
	'conference proceedings' => array(5, 44, 30),
	'magazine article' => array(10, 67, 68, 30),
	'newspaper article' => array(9, 66, 69, 30),
	'thesis' => array(13, 30),
	'journal article' => array(3, 28, 29, 30),
	'electronic article' => array(3, 28, 29, 30),
	'report' => array(12, 44, 30),
	'unpublished work' => array(12, 44, 30),
	'personal communication' => array(11),
	'web page' => array(1),
	'generic' => array(46),
	'other document' => array(46)
);

class HeuristReferParser extends HeuristForeignParser {

	function recogniseFile(&$file) {
		// Look at beginning of file to see if we can understand a full reference
		// (the EndNote refer parser can understand a somewhat different format)
		$file->rewind();

		// Ignore blank / whitespace lines at the start of the file
		do {
			$line = $file->getLine();
		} while (preg_match('/^\\s*$|^%%/', $line)  &&  $file->nextLine());

		// File should start with a tag
		if (! preg_match('/^%\\S\\s.+/', $line)) return FALSE;	// not tagged

		// This file appears to be in some sort of a refer format,
		// but it could be in EndNote refer format.
		// First line of each entry in EndNote refer has the %0 tag;
		// but there might be other info or comments, which we skip over first.

		// Here we go: embrace and extend 'em: %% means a comments now, ignore it.
		// Also ignore "other" info (percent capital oh) at the start of the entry.
		while (preg_match('/^%[%O]\\s|^\\s*$/i', $line)  &&  $file->nextLine())
			$line = $file->getLine();

		// Check for a valid tag other than EndNote refer's "reference type" (percent zero)
		if (preg_match('/^%[^0]\\s/', $line)) return TRUE;

		return FALSE;
	}


	function parserDescription() { return 'REFER parser'; }


	function parseFile(&$file) {
		$file->rewind();

		$errors = array();
		$entries = array();

		$current_entry_lines = array();
		do {
			$line = $file->getLine();

			if (substr($line, 0, 2) != '%%') {	// ignore comments
				if ($line == '') {
					// blank line marks end of current entry

					if ($current_entry_lines) {
						// throw it on the pile
						$entries[] = &$this->_makeNewEntry($current_entry_lines);
						$current_entry_lines = array();
					}

				} else if ($line[0] != '%'  &&  ! $current_entry_lines) {
					// First line of new entry doesn't contain a tag -- this is a format error!
					// Ignore the case where it's a line of whitespace, that's not too harmful

					if (! preg_match('/^\\s*$/', $line))
						array_push($errors, 'line ' . $file->getLineNumber() . ': '
						                  . 'expected tag, found <span title="'.htmlspecialchars($line).'">"'.htmlspecialchars(substr($line, 0, 20)).' ..."</span>');
				} else {
					// store the line
					array_push($current_entry_lines, $line);
				}
			}

		} while ($file->nextLine());

		if ($current_entry_lines) {
			$entries[] = &$this->_makeNewEntry($current_entry_lines);
			$current_entry_lines = array();
		}

		if ($errors) return array( $errors, NULL );
		else return array( NULL, &$entries );
	}


	function outputEntries(&$entries) {
		// Not too much to do here --
		// go through the entries and output them together with any attached warnings / errors,
		// putting a blank line between entries.

		$output = '';

		foreach (array_keys($entries) as $i) {
			unset($entry);
			$entry = &$entries[$i];
			$some_entry_output = FALSE;

			if ($entry->getErrors()) {
				foreach ($entry->getErrors() as $error) {
					$error = preg_replace("/\n/s", "\n%%        ", $error);
					$output .= "%% ERROR: $error\n";
					$some_entry_output = TRUE;
				}
			}

			if ($entry->getWarnings()) {
				foreach ($entry->getWarnings() as $warning) {
					$warning = preg_replace("/\n/s", "\n%%          ", $warning);
					$output .= "%% WARNING: $warning\n";
					$some_entry_output = TRUE;
				}
			}

			if ($entry->getValidationErrors()) {
				foreach ($entry->getValidationErrors() as $error) {
					$error = preg_replace("/\n/s", "\n%%             ", $error);
					$output .= "%% DATA ERROR: $error\n";
					$some_entry_output = TRUE;
				}
			}

			$unknown_tags = array_unique($entry->getUnknownTags());
			if (count($unknown_tags) == 1) {
				$output .= "%% UNKNOWN TAG: %" . $unknown_tags[0] . "\n";
				$some_entry_output = TRUE;
			} else if (count($unknown_tags) > 1) {
				$output .= "%% UNKNOWN TAGS: %" . join(' %', $unknown_tags) . "\n";
				$some_entry_output = TRUE;
			}

			if ($some_entry_output) $output .= "%% \n";	// for readability -- a blank line after errors and warnings

			unset($fields);
			$fields = &$entry->getFields();
			foreach (array_keys($fields)  as  $i) {
				unset($field);
				$field = & $fields[$i];
				if ($field->_fake) continue;
				$output .= '%' . $field->getTagName() . ' ' . $field->getRawValue() . "\n";
			}

			if (@$this->_has_name_problems) {
				$output .= '%% Non-person names may be included by surrounding them in "double quotes"' . "\n";
			}

			$output .= "\n";	// blank line at end of entry
		}

		return $output;
	}


	function getReferenceTypes() {
		// refer doesn't have any explicit types; add to these as necessary
		static $known_types = array('Conference Paper', 'Conference Proceedings',
		                            'Book', 'Book Section', 'Edited Book', 'Edited Book Section',
		                            'Generic','Unpublished Work', 'Audiovisual Material',
		                            'Journal Article', 'Magazine Article', 'Newspaper Article',
		                            'Personal Communication',
		                            'Technical Report', 'Thesis', 'Web Page');
		return $known_types;
	}


	function supportsReferenceTypeGuessing() { return true; }

	function guessReferenceType(&$entry, $allowed_types=NULL) {
		// FIXME: needs to be, uh, finessed.
		// We ignore the $allowed_types here.

		if (@$entry->_tag_names['0']) {	// endnote-style hint?
			$endnote_type = trim(ucwords(strtolower($entry->_tag_names['0'])));
			if (in_array($endnote_type, $this->getReferenceType())) return $endnote_type;
		}

		if (@$entry->_tag_names['J']  &&  !@$entry->_tag_names['B']) {
/*
			if (preg_match('/^proc\\w*\\.\\s|proceeding|proc[.]?\\s+of\\s|conference|symposium|workshop/is', $entry->_tag_names['J']))
				return 'Conference Paper';
*/
			return 'Journal Article';
		}

		if (@$entry->_tag_names['ISSN'])
			return (@$entry->_tag_names['V'])? 'Journal Volume' : 'Journal';	/* by definition! */

		if (@$entry->_tag_names['B']) {
/*
			if (preg_match('/^proc\\w*\\.\\s|proceeding|conference|workshop/is', $entry->_tag_names['B']))
				return (@$entry->_tag_names['T'])? 'Conference Paper' : 'Conference Proceedings';
			else
*/
				return (@$entry->_tag_names['T']  ||  @$entry->_tag_names['startP'])? 'Book Section' : 'Book';
		}

		if (@$entry->_tag_names['R']) {
			if (preg_match('/ph\\.?\\s*d\\.?|diploma|master|^m[as]thes|hons|honours/i', $entry->_tag_names['R'])
			 or preg_match('/^M\\.?[A-Z][a-z]*\\.?/', $entry->_tag_names['R']))
				return 'Thesis';
			else
				return 'Report';
		}

		if (@$entry->_tag_names['I']  ||  @$entry->_tag_names['Dew']  ||  @$entry->_tag_names['ISBN']) {
			// some redundancy (defensive coding) in the next line: if _tag_names['B'] existed we wouldn't be here
			if ((@$entry->_tag_names['B'] && @$entry->_tag_names['T']) || @$entry->_tag_names['startP'])
				return 'Book Section';
			else
				return 'Book';
		}

		return NULL;
	}

	function _makeNewEntry($lines) { return new HeuristReferEntry($lines); }
}


class HeuristReferEntry extends HeuristForeignEntry {
	var $_lines;

	var $_type, $_potential_type;
	var $_fields;
	var $_tag_names;

	var $_have_supplementary_fields;

	function HeuristReferEntry($lines) {
		$this->HeuristForeignEntry();

		$this->_lines = $lines;
		$this->_type = NULL;
		$this->_potential_type = NULL;
		$this->_fields = NULL;
		$this->_tag_names = array();
	}

	function getFields() { return $this->_fields; }

	function setReferenceType($type) { $this->_type = $type; }
	function getReferenceType() { return $this->_type; }

	function setPotentialReferenceType($type) { $this->_potential_type = $type; }
	function getPotentialReferenceType() { return $this->_potential_type; }


	function parseEntry() {
		static $known_tags = array(
			'H' => 1,	// header commentary
			'A' => 1,	// author's name
			'Q' => 1,	// corporate author / foreign author (unreversed author name)
			'T' => 1,	// title of article or book
			'S' => 1,	// title of series  +book/journ./conf. sect. title
			'J' => 1,	// journal containing article
			'B' => 1,	// book containing article
			'R' => 1,	// report, paper or thesis type ... something to do with %T of tech report, thesis / electronic resource number
			'V' => 1,	// volume number (used with %N)
			'N' => 1,	// number of issue within volume = +chapt/sect/rept #, # entries
			'E' => 1,	// editor of book containing article
			'P' => 1,	// page number(s) or page count
			'I' => 1,	// issuer (publisher) => imply %C ??
			'C' => 1,	// city where published_searches (publisher's address)
			'D' => 1,	// date of publication
			'O' => 1,	// other information
			'K' => 1,	// keywords
			'L' => 1,	// label
			'X' => 1,	// abstract

				// BibIX stuff
			'F' => 1,	// caption / footnote number or label
			'G' => 1,	// US Government ordering number
			'W' => 1,	// physical location of item (alt.: location of conference, database provider)

				// other fields
			'$' => 1,	// price
			'*' => 1,	// copyright information
			'M' => 1,	// Mathematical Reviews number / Bell labs memorandum / mnemonic id / mod. info / month
			'Y' => 1,	// table of contents / series editor (EndNote) / tertiary author
			'Z' => 1,	// pages in the entire document / references / notes
			'l' => 1,	// (little ell) language used for document
			'U' => 1,	// annotation or WWW URL / user email address
			'^' => 1,	// contained parts or containing doc
			'6' => 1,	// number of volumes
			'7' => 1,	// edition
			'8' => 1,	// date associated with entry. For a conference proceedings, this is the conference date.
			'9' => 1,	// how the entry was published_searches (e.g. the thesis type)
			'0' => 1,	// Endnote - type
			'@' => 1,	// ISBN/ISSN
			'!' => 1,	// short title

		// these ones are from http://www.cardiff.ac.uk/schoolsanddivisions/divisions/insrv/help/guides/endnote/endnote_codes.html
			'?' => 1,	// subsidiary author
			'&' => 1,	// section
			'(' => 1,	// original publication
			')' => 1,	// reprint edition
			'+' => 1,	// author address
			'>' => 1,	// link to PDF
			'<' => 1,	// research notes
			'[' => 1,	// access date
			'=' => 1,	// last modified date
			'~' => 1,	// name of database
		);

		$errors = array();
		$warnings = array();
		$unknown_tags = array();
		$fields = array();

		$field_lines = array();
		foreach ($this->_lines as $line) {
			if (preg_match('/^%\\S\\s/', $line)) {
				if ($line[1] == '0') $this->_type = trim(substr($line, 3));

				// We have a new line ...
				if ($field_lines) {
					unset($new_field);
					$new_field = &new HeuristReferField($this, $field_lines);
					$fields[] = &$new_field;
					@$this->_tag_names[$new_field->_tag] .= $new_field->getValue() . "\n";
				}
				$field_lines = array($line);
				if (! @$known_tags[$line[1]]) array_push($unknown_tags, $line[1]);
			} else if ($field_lines) {
				// ... otherwise if there's a line in progress, append to it ...
				array_push($field_lines, $line);
			} else {
				// ... otherwise there's a problem!
				array_push($errors, 'expected tag, found "' . substr($line, 0, 20) . ' ..."');
			}
		}

		if ($field_lines) $fields[] = &new HeuristReferField($this, $field_lines);

		$this->_errors = &$errors;
		$this->_warnings = &$warnings;
		$this->_unknown_tags = &$unknown_tags;
		$this->_fields = &$fields;

		return $errors;
	}


	function crosswalk() {
		global $refer_to_heurist_type_map, $refer_to_heurist_map;

		if (! $this->_have_supplementary_fields)
			heurist_refer_add_supplementary_fields($this);

		$refer_type = strtolower($this->_type);
		if (! @$refer_to_heurist_type_map[$refer_type]) {
/*****DEBUG****///error_log("Invalid REFER type: " . $this->_type);
			return NULL;	// FIXME: probably store an error message somewhere
		}
		$heurist_rectypes = $refer_to_heurist_type_map[$refer_type];

		$base_entry = $last_entry = NULL;
		foreach ($heurist_rectypes as $rt) {
			// construct an empty entry [and its containers]
			unset($new_entry);
			$new_entry = &new HeuristNativeEntry($rt);
			if (! $base_entry) $base_entry = &$new_entry;

			if ($last_entry) $last_entry->setContainerEntry($new_entry);
			$last_entry = &$new_entry;
		}

		$refer_to_heurist_bdts = $refer_to_heurist_map[$refer_type];


		// Finally: actually add values to the native Heurist entry -- convert the refer values one by one
		foreach (array_keys($this->_fields) as $i) {
			unset($refer_field);
			$refer_field = &$this->_fields[$i];

			$heurist_tags = @$refer_to_heurist_bdts[$refer_field->getTagName()];
			if ($heurist_tags) {	// There is a Heurist type for this refer tag
				if (! is_array($heurist_tags)) $heurist_tags = array($heurist_tags);
				foreach ($heurist_tags as $heurist_tag) {
					unset($entry);
					$entry = &$base_entry;
					while ($heurist_tag[0] == ':') {
						// &*(&*(#$&*(%   RIDDLE ME THIS .. why does it not work if I use ->getContainerEntry() ?
						unset($tmp_entry);
						$tmp_entry = &$entry->_container;
						unset($entry);
						$entry = &$tmp_entry;
						$heurist_tag = substr($heurist_tag, 1);
					}

					if (! $entry) { 
/*****DEBUG****///error_log('refer-to-heurist mapping inconsistency'); 
                        continue; 
                    }

					// FIXME: need to put in a fuzzy matching lovey-dovey thing here to recognise ANY enum type, not just THESIS TYPE
					if ($heurist_tag == '243')//MAGIC NUMBER
						$entry->addField(new HeuristNativeField($heurist_tag, $refer_field->getRawValue(), decode_thesis_type($refer_field)));
					else if (is_enum_field($heurist_tag))
						$entry->addField(new HeuristNativeField($heurist_tag, $refer_field->getRawValue(), decode_enum($heurist_tag, $refer_field)));
					else if ($heurist_tag == '160')	//MAGIC NUMBER// title field -- cook it a little
						$entry->addField(new HeuristNativeField($heurist_tag, preg_replace('/\\s*[&]\\s*/', ' and ', $refer_field->getValue())));
					else
						$entry->addField(new HeuristNativeField($heurist_tag, $refer_field->getValue()));
				}

			} else if ($refer_field->getTagName() == 'K') {	// tags are handled specially, of course
				$base_entry->addTag($refer_field->getRawValue());

			} else if (@$personal_notes_tags[$refer_field->getTagName()]) {
				$base_entry->addBkmkNotes($personal_notes_tags[$refer_field->getTagName()].': '.$refer_field->getValue());

			} else {	// No Heurist type for this refer tag, so add it with type 0 to the base-level entry
				if ($refer_field->_superseded) continue;	// ... but skip it if it's in another field
				$base_entry->addField(new HeuristNativeField(0, '%'.$refer_field->getTagName().' '.$refer_field->getValue()));
			}
		}

		$base_entry->_foreign = &$this;

		return $base_entry;
	}
}


class HeuristReferField extends HeuristForeignField {
	var $_entry;	// a reference to the entry that contains this field

	var $_tag;
	var $_raw_value;
	var $_value;

	var $_fake;		// internal boolean: a field not found in the original file
	var $_superseded;	// internal boolean: this field has been supplanted by other [_fake] field(s)

	function HeuristReferField(&$entry, $lines) {
		// $lines is an array of strings comprising the entry; obviously $lines[0] should start with a tag.
		// Does a bit of parsing; lines may be concatenated for the raw value.

		// $this->_entry = $entry;

		if (preg_match('/^%\\S\\s/', $lines[0])) {
			$this->_tag = $lines[0][1];
			$this->_raw_value = ltrim(substr($lines[0], 2));
			if (count($lines) > 1) {
				for ($i=1; $i < count($lines); ++$i) {
					if ($lines[$i] != '')
						$this->_raw_value .= "\n" . $lines[$i];
					else	// not sure how this sneaked past here, but we can't have blank lines mid-field
						$this->_raw_value .= " \n";
				}
			}

			// cooked value is just a straight join of the component lines
			$this->_value = preg_replace('/\\s+/', ' ', ltrim(substr(join(' ', $lines), 2)));

		} else {
			// Uh oh!  The provided $lines do not start with a tag;
			// should throw an exception here but we want to stay PHP4 compatible so we do the best we can.
			$this->_tag = '%';
			$this->_value = $this->_raw_value = 'INTERNAL ERROR: ' . join(' / ', $lines);
		}

		$this->_fake = false;
		$this->_superseded = false;
	}


	function getTagName() { return $this->_tag; }

	function getRawValue() { return $this->_raw_value; }

	function getValue() { return $this->_value; }
}


class HeuristReferSuppField extends HeuristReferField {
	// This is what we do because PHP doesn't allow constructor overloading

	function HeuristReferSuppField($tag, $raw_value) {
		$this->_tag = $tag;
		$this->_value = $this->_raw_value = $raw_value;
		$this->_fake = true;
		$this->_superseded = false;
	}
}


function heurist_refer_add_supplementary_fields(&$entry) {
	// Derive well-known fields from existing Refer fields.
	// Might be invoked during crosswalk, or earlier while trying to guess entry type.

	$fields = &$entry->getFields();
	$field_keys = array_keys($fields);

	$supp_fields = array();

	$edited = false;
	if ($entry->getReferenceType() == 'Edited Book') {
		$entry->setReferenceType('Book');
		$supp_fields[] = &new HeuristReferSuppField('Edited', 'true');
		$entry->_tag_names['Edited'] = 'true';
		$edited = true;
	} else if ($entry->getReferenceType() == 'Edited Book Section') {
		$entry->setReferenceType('Book Section');
		$supp_fields[] = &new HeuristReferSuppField('Edited', 'true');
		$entry->_tag_names['Edited'] = 'true';
		$edited = true;
	}

	if ($entry->getReferenceType() == 'Conference Proceedings') {
		$supp_fields[] = &new HeuristReferSuppField('EventType', 'Conference');
		$entry->_tag_names['EventType'] = 'Conference';
	}

	foreach ($field_keys as $i) {
		unset($field);
		$field = &$fields[$i];
		$tagname = $field->getTagName();
		$value = $field->getValue();

		if ($tagname == 'E'  &&  ! $edited) {
			// presence of an EDITOR generally means we're talking about an EDITED BOOK
			$supp_fields[] = &new HeuristReferSuppField('Edited', 'true');
			$entry->_tag_names['Edited'] = 'true';
			$edited = true;
		}

		// Dewey call number
		if (preg_match('/^[0-9][0-9][0-9](?:(?:\s+|\\.[0-9]+).*)?$/s', $value)) {
			$supp_fields[] = &new HeuristReferSuppField('Dew', $value);
			@$entry->_tag_names['Dew'] .= $value . " ";
			$field->_superseded = true;
			continue;
		}

		// Look for Thesis or Dissertation in O and move to R
		if ($tagname == 'O'  &&  strlen($value) < 1000  &&  preg_match('/\\b(thesis|dissert.*)/is', $value, $matches)) {
			$supp_fields[] = &new HeuristReferSuppField('R', $matches[1]);
			@$entry->_tag_names['R'] .= $matches[1] . " ";
		}

		// look for "tech* rep*" in S and move to R
		if ($tagname == 'S'  &&  preg_match('/tech\\w*\\s+rep/is', $value)) {
			$supp_fields[] = &new HeuristReferSuppField('R', $value);
			@$entry->_tag_names['R'] .= $value . " ";
			$field->_superseded = true;
		}

// FIXME: many, many more.  Look in refer-importerFramework.php, line 163 onwards
// Here I've just done the ones interpreted by "book section".

		/* look for "* edition" in some fields and move to Ed field */
		if ($tagname == 'V'  ||  $tagname == 'S'  ||  $tagname == 'R'  ||  $tagname == 'T'  ||  $tagname == 'O'  ||  $tagname == 'B') {
			if (strlen($value) > 1000) continue;
			if (preg_match('/([\\w\d]+)\\s+edition/is', $value, $matches)) {
				$supp_fields[] = &new HeuristReferSuppField('Ed', $matches[1]);
				@$entry->_tag_names['Ed'] .= $matches[1] . " ";
				$new_value = preg_replace('/\\s*[-,;(]?\\s*[\\w\\d]+\\s+edition\\s*[),;]?\\s*/is', '', $value);
				if (trim($new_value) == '') $field->_superseded = true;
				continue;
			}
		}


		/* find the year */
// for EndNote this should also include 8 field ... are you taking notes, Kim?
		if ($tagname == 'D'  ||  $tagname == '8') {
			if (preg_match('/\\b(1[5-9][0-9][0-9]|2[01][0-9][0-9])\\b|^\\s*((?:c(?:irca)?\\.?\\s*)?(?:b\\.?c\\.?)?\\s*[0-9]{1,4}\\s*(?:a\\.?d\\.?)?)\\.?\\s*$|\\b(n[.]\\s*d[.]|In\\s+press)\\b/i', $value, $matches)) {
				$match = $matches[1]? $matches[1] : ($matches[2]? $matches[2] : $matches[3]);
				// beware the Y2.02K problem
				$supp_fields[] = &new HeuristReferSuppField('Yr', $match);
				@$entry->_tag_names['Yr'] .= $match . " ";
				if (strlen(trim($value)) == strlen(trim($match))) {	// this was the only thing in the field
					$field->_superseded = true;
					continue;
				}
			}
		}


		/* look for ISBN/ISSN# in some fields and move to ISBN/ISSN */
		if ($tagname == 'G'  ||  $tagname == 'O'  ||  $tagname == '@') {
			if (preg_match('/\\bIS[BS]N/i', $value)) {
				// something to do with nonbreaking spaces, I imagine (ripped from somebody else's code)
				$value = str_replace('\\ ', '~', $value);
				if (preg_match('/(.*)\\bISBN\s*:?\\s*(\\d\\S*)\s*[,;]*(.*)/is', $value, $matches)) {
					$new_value = str_replace('~', '-', preg_replace('/[;.,]+$/', '', $matches[2]));
					$supp_fields[] = &new HeuristReferSuppField('ISBN', $new_value);
					@$entry->_tag_names['ISBN'] .= $new_value . " ";
				}
				if (preg_match('/(.*)\\bISSN\s*:?\\s*(\\d\\S*)\s*[,;]*(.*)/is', $value, $matches)) {
					$new_value = str_replace('~', '-', preg_replace('/[;.,]+$/', '', $matches[2]));
					$supp_fields[] = &new HeuristReferSuppField('ISSN', $new_value);
					@$entry->_tag_names['ISSN'] .= $new_value . " ";
				}
			}
			else {
				// Find un-labelled ISBN in ISBN-10 or ISBN-13 representation
				$unhyp_value = preg_replace('/[- ]/', '', $value);
				if (preg_match('/\\b(97[89][0-9]{10}|[0-9]{9}[0-9xX])\\b/', $unhyp_value, $matches)) {
					$new_value = $matches[1];
					$supp_fields[] = &new HeuristReferSuppField('ISBN', $new_value);
					@$entry->_tag_names['ISBN'] .= $new_value . " ";
				}
			}
		}


		if (! @$entry->_tag_names['V']
		    &&  $tagname == 'T'  &&  preg_match('/\\s?volume +([^[:punct:][:space:]]+)/i', $value, $matches)) {
			$field->_value = preg_replace('/\\s?volume +[^[:punct:][:space:]]+/i', '', $value);
			$supp_fields[] = &new HeuristReferSuppField('V', $matches[1]);
			@$entry->_tag_names['V'] .= $new_value . " ";
		}

		if ($tagname == 'P') {
			if (! preg_match('/[0-9]\\s*p|^\\s*[0-9ivxlcdm]+\\s*\\.?\\s*$/', $value)) {
				// looks like a page specification rather than a page count: try to find start and end pages
				$startP = $endP = '';
				if (preg_match('/([0-9]+)\\s*-+\\s*([0-9]+)/', $value, $matches)) {
					$startP = $matches[1];
					$endP = $matches[2];
					if (intval($endP) < intval($startP)  &&  strlen($endP) < strlen($startP)) {
						/* e.g. pp300-23  .. should become pp300-323 */
						$endP = substr($startP, 0, strlen($startP)-strlen($endP)) . $endP;
					}

				} else if (preg_match('/([ivxlcdm]+)\\s*-+\\s*([ivxlcdm]+)/', $value, $matches)) {
					$startP = $matches[1];
					$endP = $matches[2];

                                } else {
					$startP = $value;
				}

				$supp_fields[] = &new HeuristReferSuppField('startP', $startP);
				$supp_fields[] = &new HeuristReferSuppField('endP', $endP);
				@$entry->_tag_names['startP'] .= $startP . " ";
				@$entry->_tag_names['endP'] .= $endP . " ";
				$field->_superseded = true;
			}
		}
	}

	foreach (array_keys($supp_fields) as $i)
		$entry->_fields[] = &$supp_fields[$i];

	$entry->_have_supplementary_fields = true;
}


registerHeuristFiletypeParser( new HeuristReferParser() );

?>
