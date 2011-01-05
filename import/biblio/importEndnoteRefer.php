<?php
/* Subclass of the HeuristReferImport stuff, specially suited to the quirks of the REFER stuff pumped out by EndNote.
   Note that these quirks are not implemented here, except for type determination.
   The rest are left as an exercise for the reader, or for the author when he gets back from Greece.
 */

require_once('HeuristReferImport.php');


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
