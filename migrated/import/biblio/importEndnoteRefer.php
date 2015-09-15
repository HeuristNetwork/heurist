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


/* Subclass of the importRefer stuff, specially suited to the quirks of the REFER stuff pumped out by EndNote.
   Note that these quirks are not implemented here, except for type determination.
   The rest are left as an exercise for the reader, or for the author when he gets back from Greece.
 */

require_once('importRefer.php');


class HeuristEndnoteReferParser extends HeuristReferParser {

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
		// ... it COULD be in EndNote refer format.
		// First line of each entry in EndNote refer has the %0 tag;
		// but there might be other info or comments, which we skip over first.

		// Here we go: embrace and extend 'em: %% means a comments now, ignore it.
		// Also ignore "other" info (percent capital oh) at the start of the entry.
		while (preg_match('/^%[%O]\\s|^\\s*$/i', $line)  &&  $file->nextLine())
			$line = $file->getLine();

		// Check for EndNote refer's "reference type" (percent zero)
		if (preg_match('/^%0\\s/', $line)) return TRUE;

		return FALSE;
	}

	function parserDescription() { return 'EndNote REFER parser'; }


	function parseFile(&$file) {
		$file->rewind();

		$errors = array();
		$entries = array();

		$current_entry_lines = array();
		do {
			$line = $file->getLine();

			if (substr($line, 0, 2) != '%%') {	// ignore comments
				if (substr($line, 0, 2) == '%0') {
					// %0 marks beginning of new entry

					if ($current_entry_lines) {
						// throw it on the pile
						$entries[] = &$this->_makeNewEntry($current_entry_lines);
						$current_entry_lines = array();
					}
					array_push($current_entry_lines, $line);

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


	function getReferenceTypes() {
		static $known_types = array(
'Artwork', 'Audiovisual Material', 'Book', 'Book Section', 'Computer Program',
'Conference Proceedings', 'Edited Book', 'Edited Book Section', 'Generic', 'Journal Article', 'Magazine Article', 'Map',
'Newspaper Article', 'Patent', 'Personal Communication', 'Report', 'Thesis','Unpublished Work'
		);
		return $known_types;
	}

	function _makeNewEntry($lines) { return new HeuristEndnoteReferEntry($lines); }
}


class HeuristEndnoteReferEntry extends HeuristReferEntry {

	function HeuristEndnoteReferEntry($lines) {
		parent::HeuristReferEntry($lines);
		foreach ($lines as $line) {
			if (preg_match('/^%0\\s*(.*)\\s*$/', $line, $matches)) {
				$this->_type = $matches[1];
				break;
			}
		}
	}


	function crosswalk() {
		global $refer_to_heurist_map;

		if (! in_array(strtolower($this->_type), array_keys($refer_to_heurist_map))) {
			// we don't have a translation for this
			array_push($this->_errors, 'no Heurist support for reference type: ' . $this->_type);
			return NULL;
		}
		return parent::crosswalk();
	}
}

registerHeuristFiletypeParser( new HeuristEndnoteReferParser() );

?>
