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


class HeuristInputFile {
    var $_filename;

    var $_fp;
    var $_line;
    var $_line_number;

    var $_raw_lines;	// first 1000 lines of the input file

    var $_encoding;

    function open($filename) {
        $this->_filename = $filename;

        // Open the named file; return an ERROR, or NULL on success
        $this->_fp = @fopen($filename, 'rb');
        if (! $this->_fp) {
            if (! file_exists($filename)) return 'file does not exist';
            else if (! is_readable($filename)) return 'file is not readable';
                else return 'file could not be read';
        }

        $this->_encoding = '';	// ISO-8859-1 ..?
        $this->_line = NULL;
        $this->_line_number = 0;

        $this->_raw_lines = array();

        $this->nextLine();

        return NULL;
    }

    function getLine() {
        // Return contents of current line -- FIXME: this is somewhat naive, maybe the file isn't in ISO-8859-1 or UTF-8
        return $this->_line;
        if ($this->_encoding == 'utf8-squared')
            return utf8_decode($this->_line);
        else if ($this->_encoding == 'utf8') {
            if (preg_match('/[\\xc2-\\xf4][\\x80-\\xbf]/', utf8_decode($this->_line))) {	/* moreover, it looks like utf8-encoded utf8! */
                $this->_encoding = 'utf8-squared';
                return utf8_decode($this->_line);
            }
            return $this->_line;
        } else if (preg_match('/[\\xc2-\\xf4][\\x80-\\xbf]/', $this->_line)) {	/* looks like unlabelled UTF-8 */
            if (preg_match('/[\\xc2-\\xf4][\\x80-\\xbf]/', utf8_decode($this->_line))) {	/* moreover, it looks like utf8-encoded utf8! */
                $this->_encoding = 'utf8-squared';
                return utf8_decode($this->_line);
            } else {
                $this->_encoding = 'utf8';
                return $this->_line;
            }
        } else
            return utf8_encode($this->_line);
    }

    function nextLine() {
        // Go to next line of file; return TRUE if it is available
        $line = @fgets($this->_fp);
        if (! $line) return FALSE;

        if (count($this->_raw_lines) < 1000) {
            array_push($this->_raw_lines, $line);
        } else if (count($this->_raw_lines) == 1000) {
            array_push($this->_raw_lines, "(further lines elided)");
        }

        if ($this->_line_number == 0) {
            // skip UTF-8 BOM
            if (substr($line, 0, 3) == "\357\273\277") {
                $line = substr($line, 3);
                $this->_encoding = 'utf8';
            }
        }

        if (substr($line, -2) == "\r\n") $line = substr($line, 0, -2);
        else if (substr($line, -1) == "\n") $line = substr($line, 0, -1);

            ++$this->_line_number;
        $this->_line = $line;

        return TRUE;
    }

    function getLineNumber() { return $this->_line_number; }
    // Return current line number

    function rewind() {
        // Go to first line of file
        rewind($this->_fp);
        $this->_line = NULL;
        $this->_line_number = 0;
        $this->nextLine();
    }

    function getRawFile() { return $this->_fp; }
    // Return the file resource for this object;
    // mucking with this may destroy the operation of the other class methods

    function getFileSize() { return filesize($this->_filename); }
}


class HeuristOutputFile {
    var $_fp;
    var $_line_number;

    function open($filename) {
        // Open the named file; return an ERROR, or NULL on success
        $this->_fp = @fopen($filename, 'wb');
        if (! $this->_fp) {
            if (! is_writable($filename)) return 'file is not writable';
            else return 'file could not be written';
        }

        $this->_line_number = 0;
        return NULL;
    }

    function writeLine($line) {
        // Write line to the file
        fwrite($this->_fp, $line . "\n");
        ++$this->_line_number;
    }

    function getLineCount() { return $this->_line_number; }
    // Return number of invocations of writeLine

    function getRawFile() { return $this->_fp; }
    // Return the file resource for this object;
    // mucking with this may destroy the operation of the other class methods
}


class HeuristForeignParser {
    // As new file formats are introduced to HEURIST,
    // this class is sub-classed to recognise each new format.
    // e.g.  a HeuristReferParser is a HeuristForeignParser which recognises the standard REFER format,
    //       and produces HeuristReferEntry objects, each of which is a HeuristForeignEntry,
    //       which contains HeuristReferField objects, each of which is a HeuristForeignField.
    // The Heurist prefix is kind of naff, but we want to remain PHP4 friendly.
    // THIS IS IMPORTANT.  THE CODE SHOULD WORK WITH BOTH PHP4 AND PHP5.

    function HeuristForeignParser() { /* registerHeuristFiletypeParser($this); */ }
    // Remember to register the new parser when it is ready

    function recogniseFile(&$file) { return FALSE; }
    // parameter: $file is a HeuristInputFile
    //
    // Return TRUE if this file appears to be in the format understood by this parser,
    // presumably by looking at the beginning of the $file.
    // $file may have been looked at by other HeuristForeignParser, so don't assume it is at the beginning.

    function parserDescription() { return 'unimplemented Heurist parser type'; }
    // Return a short human-readable description of the file-type recognised by this parser

    function parseFile(&$file) { return array(array('Parser is not implemented'), NULL); }
    // Parse the given file to determine the entries in it;
    // this function basically just breaks the file into sections which correspond to one entry each,
    // and returns these sections as HeuristForeignEntry objects.
    //
    // Return two values:
    //   - an array of "fatal" errors encountered while parsing the file;
    //     this should be as complete as possible (i.e. don't necessarily stop at the first fault),
    //     This is NULL if there are no errors.
    //   - an array of HeuristForeignEntry objects of the appropriate subclass for this parser,
    //     as extracted from the last file specified by parseFile.
    //     These entries should NOT have been parsed or processed yet, so that we can
    //     control the load-balancing over the course of the import.
    //     If there were fatal errors while parsing, then this is NULL.

    function outputEntries(&$entries) { return ''; }
    // parameter: $entries is an array of HeuristForeignEntry objects of the appropriate subclass for this parser
    //
    // Return a string in the format accepted by this parser,
    // containing all the data in the given entries, including any embedded warnings/errors in each entry.

    function getReferenceTypes() { return array(); }
    // Return an array of all the reference types supported by this parser (i.e. all those supported by its file format)

    function supportsReferenceTypeGuessing() { return false; }

    function guessReferenceType(&$entry, $allowed_types=NULL) { return NULL; }
    // parameter: $entry is a HeuristForeignEntry object of the appropriate subclass for this parser
    // parameter: $allowed_types is an optional subset of the types specified by getReferenceTypes()
    //
    // Returns a "best guess" at the type for the entry, preferring the types specified in $allowed_types.
    // If no type can be guessed, return NULL.
}


$_parsers = array();

function registerHeuristFiletypeParser(&$parser) {
    // parameter: $parser is a HeuristForeignParser object
    // This lets the system know that the parser for a new file type is available.
    //  i Viva la plug-e-play !
    global $_parsers;
    $_parsers[] = &$parser;
}

function getHeuristFiletypeParsers() { global $_parsers; return $_parsers; }
// Return the current list of file-type parsers (HeuristForeignParser objects) known to Heurist


class HeuristForeignEntry {
    // Subclass this for each new file format (see the notes at the top of class HeuristForeignParser)

    var $_errors;
    var $_warnings;
    var $_unknown_tags;
    var $_validation_errors;

    function HeuristForeignEntry() {
        $this->_errors = NULL;
        $this->_warnings = NULL;
        $this->_unknown_tags = NULL;
        $this->_validation_errors = NULL;
    }

    function parseEntry() { return array(); }
    // Parse the given entry to determine the individual data (e.g. tags) in it;
    // this function basically just breaks the entry into sections which correspond to one tag each,
    // and stores these sections in the HeuristForeignField objects returned by getFields().
    //
    // Return an array of "fatal" errors in the entry.

    function getFields() { return NULL; }
    // Return an array of HeuristForeignField objects of the appropriate subclass for this parser,
    // as extracted from this entry.
    // If parseEntry has not been invoked yet, or there were fatal errors while parsing, then return NULL.


    function getErrors() { return $this->_errors; }
    function getWarnings() { return $this->_warnings; }
    function getUnknownTags() { return $this->_unknown_tags; }

    function addValidationErrors($errors) {
        if (! $this->_validation_errors) $this->_validation_errors = array();
        foreach ($errors as $error) array_push($this->_validation_errors, $error);
    }
    function getValidationErrors() { return $this->_validation_errors; }

    /*

    function getErrors() { return array(); }
    // Return the array of errors returned by parseEntry()

    function getWarnings() { return array(); }
    // Return an array of warnings (non-fatal format errors, etc) encountered while parsing this entry.

    function getUnknownTags() { return array(); }
    // Return an array of unrecognised tags encountered while parsing the entry.

    function addValidationErrors($errors) { }
    function getValidationErrors() { return array(); }
    // Need to store validation errors with the foreign entry so that the errors can be emitted by outputEntries().
    // Errors are stored as an array of human-readable strings.

    */

    function setReferenceType($type) { }
    function getReferenceType() { return NULL; }
    // Set/return the definitive reference type for this object

    function setPotentialReferenceType($type) { }
    function getPotentialReferenceType() { return NULL; }
    // Set/return the "current best suggestion" for the reference type for this object

    function crosswalk() { return NULL; }
    // Return a HeuristNativeEntry object corresponding to this entry.
    // precondition: parseEntry has been invoked, and the definitive reference type for this entry is known.
}


class HeuristForeignField {
    // Subclass this for each new file format (see the notes at the top of class HeuristForeignParser)

    function getTagName() { return NULL; }
    // Return the tag name for this field.

    function getRawValue() { return NULL; }
    // Return the field value as it appears in the file.

    function getValue() { return NULL; }
    // Return the interpreted field value (may be the same as getRawValue)

    function getError() { return NULL; }
    // Return a description of any data error that has occurred in this field (e.g. unparseable value)
}


class HeuristNativeEntry {
    // A native HEURIST object -- equivalent to one Records row and its associated recDetails row(s)

    var $_fields;
    var $_fields_by_bdt_id;
    var $_rectype;
    var $_container;
    var $_references;
    var $_ancestor;
    var $_authors;
    var $_author_bib_ids;
    var $_author_hashes;

    var $_title;
    var $_title_field, $_author_fields;
    var $_title_metaphone;

    var $_tags;
    var $_workgroupTag;

    var $_missing_fields;
    var $_other_errors;

    var $_matches;
    var $_non_matches;
    var $_bib_id;
    var $_bkmk_id;
    var $_permanent;

    var $_foreign;
    var $_is_valid;

    var $_hhash, $_craphash;

    var $_has_name_problems;

    var $_bkmk_personal_notes;

    function HeuristNativeEntry($rt) {
        // Create a native entry of the given type (a defRecTypes.rty_ID, not an rty_Name)
        $this->_rectype = $rt;
        $this->_fields = array();
        $this->_fields_by_bdt_id = array();
        $this->_container = NULL;
        $this->_references = array();
        $this->_ancestor = NULL;
        $this->_authors = array();
        $this->_author_bib_ids = array();
        $this->_author_hashes = array();

        $this->_title = NULL;
        $this->_title_field = NULL;  $this->_author_fields = array();
        $this->_title_metaphone = NULL;

        $this->_tags = array();
        $this->_workgroupTag = 0;

        $this->_missing_fields = array();
        $this->_other_errors = array();

        $this->_matches = array();
        $this->_non_matches = array();
        $this->_bib_id = NULL;
        $this->_bkmk_id = NULL;
        $this->_permanent = false;	// is this represented by a PERMANENT (i.e. non-temporary) Records entry in the database?

        $this->_foreign = NULL;	// object whence this sprung
        $this->_is_valid = NULL;

        $this->_has_name_problems = false;

        $this->_bkmk_personal_notes = "";
    }

    function getForeignPrototype() { return $this->_foreign; }

    function getReferenceType() { return $this->_rectype; }

    function addField(&$field) {
        // $field is a HeuristNativeField
        $this->_fields[] = &$field;

        $titleDT = (defined('DT_NAME')?DT_NAME:0);

        // these are important fields
        if ($field->getType() == $titleDT) $this->_title_field = &$field;//MAGIC NUMBER
        else if ($field->getType() == 158) $this->_author_fields[] = &$field;//MAGIC NUMBER

            if (! @$this->_fields_by_bdt_id[$field->getType()]) $this->_fields_by_bdt_id[$field->_type] = array();
        $this->_fields_by_bdt_id[$field->getType()][] = $field->_value;
    }
    function getFields() { return $this->_fields; }
    // Return an array of HeuristNativeField objects

    function addTag($tag, $userSupplied=false) {
        global $allTags;

        // Do a bit of cooking here: tags are typically comma or newline separated
        if (preg_match('/\\n/s', $tag))
            $tags = preg_split('/\\s*\\n\\s*/s', $tag);
        else
            $tags = preg_split('/\\s*,\\s*/', $tag);
        foreach ($tags as $kwd) {
            if (substr($kwd, -1) == '.') $kwd = substr($kwd, 0, -1);
            array_push($this->_tags, $kwd);
            if (! $userSupplied) $allTags[$kwd] = $kwd;
        }
    }
    function getTags() { return $this->_tags; }

    function setWorkgroupTag($kwd_id) {
        $this->_workgroupTag = $kwd_id;
    }
    function getWorkgroupTag() { return $this->_workgroupTag; }

    function setContainerEntry(&$entry) { $this->_container = &$entry; }
    // $entry is a HeuristNativeEntry, used to set the container for this entry
    function getContainerEntry() { return $this->_container; }
    // Return the HeuristNativeEntry that contains this one,
    // e.g. book section's book; conference proceeding's conference publisher etc.

    function setReferenceEntry($rdt_id, &$entry) { $this->_references[$rdt_id] = $entry; }
    function getReferenceEntry($rdt_id) { return $this->_references[$rdt_id]; }

    function setAncestor(&$entry) { $this->_ancestor = $entry; }
    function getAncestor() { return $this->_ancestor; }
    // This is not set unless there are no possible or definite Records matches for this entry
    // but there are possible or definite Records matches for its container, or container-container etc.
    // We call such an entry the ancestor, and I acknowledge that this is a brief, rather than precise, term.

    function addAuthor($author_bib_id) {
        // $author_bib_id is either the Records ID of a record containing the author's details,
        // or a special string ("anonymous" or "et al.")

        if (is_numeric($author_bib_id)) {
            $author_details = mysql__select_assoc('recDetails', 'dtl_DetailTypeID', 'dtl_Value', 'dtl_RecID='.intval($author_bib_id));
            $res = mysql_query("select rec_Hash from Records where rec_ID = " . $author_bib_id);
            $hash = mysql_fetch_row($res);  $hash = $hash[0];

            $this->_authors[] = &$author_details;
            $this->_author_bib_ids[] = $author_bib_id;
            $this->_author_hashes[] = $hash;
        } else {
            $this->_authors[] = $author_bib_id;
        }
    }

    function isValid() {
        // Return TRUE only if the entry contains all required fields for this reference type,
        // there are no internal field-parsing problems,
        // and its container (if any) is also valid

        if ($this->_is_valid !== NULL) return $this->_is_valid;

        global $rec_detail_requirements;
        if (! $rec_detail_requirements) load_bib_detail_requirements();    //FIXME doesn't load defRecStructure equal empty array

        $field_lookup = array();
        foreach (array_keys($this->_fields) as $i) {
            $field_lookup[$this->_fields[$i]->getType()] = true;

            /*			if ($this->_fields[$i]->getType() == 158) {	// author
            $name_errors = checkNames($this->_fields[$i]->getRawValue());
            if ($name_errors) {
            $this->addOtherErrors($name_errors);
            $this->_has_name_problems = true;
            }
            }*/
        }

        if (@$rec_detail_requirements[$this->getReferenceType()]) {
            foreach ($rec_detail_requirements[$this->getReferenceType()] as $req_type) {
                if (! @$field_lookup[$req_type])	// missing field!
                    array_push($this->_missing_fields, $req_type);
            }
        }

        if ($this->_container) {
            $container_is_valid = $this->_container->isValid();
            /*
            if (! $container_is_valid  &&  $this->_container->getReferenceType() == 30) {
            // special case: publisher is "optional" (but recommended of course)

            if ($this->getReferenceType() != 44) {	// anything but a publication series
            // "ignore" the missing publisher, but only if it was completely unspecified.
            // If the user has specified *any* publisher fields, then they need to specify them all.
            return ($this->_is_valid = ((! $this->_missing_fields  &&  ! $this->_other_errors)  &&  count($this->_container->_fields) == 0));
            }

            } else if (! $container_is_valid  &&  $this->_container->getReferenceType() == 44) {
            // special case of the special case: if publisher can be omitted and there is nothing in the
            // publication series (type 44) then omit the publisher AND the publication series

            return ($this->_is_valid = ((! $this->_missing_fields  &&  ! $this->_other_errors) && count($this->_container->_container->_fields) == 0));
            }
            */
            if (! $container_is_valid  &&  $this->containerIsOptional()) return ($this->_is_valid = (! $this->_missing_fields && ! $this->_other_errors));

            return ($this->_is_valid = ($container_is_valid  &&  ! $this->_missing_fields  &&  ! $this->_other_errors));
        } else
            return ($this->_is_valid = (! $this->_missing_fields  &&  ! $this->_other_errors));
    }

    function getMissingFields() { return $this->_missing_fields; }
    // Return the array of missing fields identified during verification

    function getOtherErrors() { return $this->_other_errors; }
    // Any errors encountered during parsing / verification
    function addOtherErrors($errors) {
        if (! $this->_other_errors) $this->_other_errors = array();
        foreach ($errors as $error) array_push($this->_other_errors, $error);
    }

    function addPotentialMatch($rec_id) {
        if (! array_key_exists($rec_id, $this->_non_matches))
            array_push($this->_matches, $rec_id);
    }
    function getPotentialMatches() { return $this->_matches; }
    // Manage a list of Records IDs that might correspond to this entry

    function addNonMatches($bib_ids) { foreach ($bib_ids as $rec_id) $this->_non_matches[$rec_id] = $rec_id; }
    function getNonMatches() { return array_keys($this->_non_matches); }
    // Manage a list of Records IDs that the user has decided DO NOT correspond to this entry

    function eliminatePotentialMatches() { $this->addNonMatches($this->_matches); $this->_matches = array(); }
    // Move all the potential matches to the non-match list (on the user's request)

    function setBiblioID($rec_id) { $this->_bib_id = $rec_id; }
    // Store the ID for the Records table row corresponding to this entry
    function getBiblioID() { return $this->_bib_id; }
    // Get the ID for the Records table row corresponding to this entry

    function setBookmarkID($bkm_ID) { $this->_bkmk_id = $bkm_ID; }
    function getBookmarkID() { return $this->_bkmk_id; }
    // Store/get the ID for the bookmark table row corresponding to this entry

    function getTitle() {
        // Construct/retrieve the formatted title for this entry,
        // based on the reference type's title mask.
        global $heurist_rectypes;
        if (! $heurist_rectypes) load_heurist_rectypes();
        if (! $this->getBiblioID()) { return ""; }

        if ($this->_title) return $this->_title;

        $mask = $heurist_rectypes[$this->_rectype]['rty_TitleMask'];

        $this->_title = fill_title_mask($mask, $this->getBiblioID(), $this->getReferenceType());

        return $this->_title;

        // fin     FIXME  the code below never executes, looks old and refactored into TitleMask.php ?remove?


        global $heurist_rectypes, $bib_type_names;
        if (! $heurist_rectypes) load_heurist_rectypes();
        if (! $bib_type_names) load_bib_type_names();

        $mask = $heurist_rectypes[$this->_rectype]['rty_TitleMask'];
        if (! $mask) return '';


        if (! preg_match_all('/\\[\\[|\\]\\]|(\\s*(\\[\\s*([^]]+)\\s*\\]))/s', $mask, $matches))
            return ($this->_title = $mask);	// nothing to do -- no substitutions

        $replacements = array();
        for ($i=0; $i < count($matches[1]); ++$i) {
            /*
            * $matches[3][$i] contains the field name as supplied (the string that we look up),
            * $matches[2][$i] contains the field plus surrounding whitespace and containing brackets
            *        (this is what we replace if there is a substitution)
            * $matches[1][$i] contains the field plus surrounding whitespace and containing brackets and LEADING WHITESPACE
            *        (this is what we replace with an empty string if there is no substitution value available)
            */
            $value = $this->get_field_value($matches[3][$i]);

            if ($value)
                $replacements[$matches[2][$i]] = $value;
            else
                $replacements[$matches[1][$i]] = '';
        }
        $replacements['magic-open-bracket'] = '[';
        $replacements['magic-close-bracket'] = ']';

        $title = array_str_replace(array_keys($replacements), array_values($replacements), $mask);
        $title = preg_replace('!^[-:;,./\\s]*(.*?)[-:;,/\\s]*$!s', '\\1', $title);
        $title = preg_replace('!\\([-:;,./\\s]+\\)!s', '', $title);
        $title = preg_replace('!\\([-:;,./\\s]*(.*?)[-:;,./\\s]*\\)!s', '(\\1)', $title);
        $title = preg_replace('!\\([-:;,./\\s]*\\)|\\[[-:;,./\\s]*\\]!s', '', $title);
        $title = preg_replace('!,,+!s', ',', $title);
        $title = preg_replace('!\\s+,!s', ',', $title);
        $title = preg_replace('!  +!s', ' ', $title);

        /* Clean up miscellaneous stray punctuation &c. */
        return ($this->_title = trim($title));
    }


    function get_field_value($field_name) {
        /* Return the value for the given field in this record */

        global $bib_type_name_to_id, $bib_requirement_names, $rectype_name_to_id;
        if (! $bib_type_name_to_id) load_bib_type_name_to_id();
        if (! $bib_requirement_names) load_bib_requirement_names();
        if (! $rectype_name_to_id) load_rectype_name_to_id();

        if (count($this->_fields_by_bdt_id) == 0) {
            // dupe code ... FIXME ... safe to remove now that this is handled in addField ..?
            for ($i=0; $i < count($this->_fields); ++$i) {
                $field = &$this->_fields[$i];

                if (! $this->_fields_by_bdt_id[$field->_type]) $this->_fields_by_bdt_id[$field->_type] = array();
                $this->_fields_by_bdt_id[$field->_type][] = $field->_value;
            }
        }

        if (strpos($field_name, '.') === FALSE) {	/* direct field-name lookup */
            if (preg_match('/^(\\d+)/', $field_name, $matches)) {
                $rdt_id = $matches[1];
            } else {
                $rdt_id = $bib_type_name_to_id[strtolower($field_name)];
            }
            return join(', ', $this->_fields_by_bdt_id[$rdt_id]);
        }

        // have to decode a RESOURCE REFERENCE, and pull out a field from within that

        if (preg_match('/^(\\d+)\\s*(?:-[^.]*?)?\\.\\s*(.+)$/', $field_name, $matches)) {
            $rt_id = $matches[1];
            $inner_field_name = $matches[2];
        } else if (preg_match('/^([^.]+?)\\s*\\.\\s*(.+)$/', $field_name, $matches)) {
            /*
            $bdr = _title_mask__get_bib_detail_requirements();

            $rt_id = $rectype_name_to_id$bib_requirement_names[$rt][strtolower($matches[1])];
            */
            // FIXME : currently assuming that the resource type linked to is the (former) container type
            $rt_id = $this->_container->getReferenceType();
            $inner_field_name = $matches[2];
        } else {
            return '';
        }

        if ($rt_id  &&  $inner_field_name) {
            // FIXME: we only understand two types of resource reference at the moment --
            // a person reference (author), and whatever used to correspond to the container type for this rectype

            if ($rt_id == 75) {	// author reference//MAGIC NUMBER
                if (preg_match('/^(\\d+)/', $inner_field_name, $matches))
                    $inner_bdt_id = $matches[1];
                else
                    $inner_bdt_id = $bib_requirement_names[55][strtolower($inner_field_name)];//MAGIC NUMBER


                if ($this->_authors[0] == 'anonymous') {
                    if ($inner_bdt_id == 160) return 'Anonymous';//MAGIC NUMBER
                    else if ($inner_bdt_id == 291  &&  count($this->_authors) > 1) return 'et al.';//MAGIC NUMBER
                        else return '';
                }

                if (count($this->_authors) == 1) {	// a single author -- return the relevant field
                    return $this->_authors[0][$inner_bdt_id];
                } else if ($inner_bdt_id == 291) {	//MAGIC NUMBER// authors' first names are included after all else, so whack an "et al." on the end
                    return $this->_authors[0][$inner_bdt_id] . ' et al.';
                } else {
                    return $this->_authors[0][$inner_bdt_id];	// multiple authors, but we're not being asked for the "first names" field
                    // -- just return the value for the first author
                }

            } else if ($rt_id == $this->_container->getReferenceType()) {
                return $this->_container->get_field_value($inner_field_name);
            }
        }

        return '';
    }


    function getHHash() {
        if ($this->_hhash) return $this->_hhash;

        global $hash_info;
        if (! $hash_info) {
            // hash_info contains all the good stuff we need for determining the hash, indexed by rectype, and then by dty_ID
            $res = mysql_query("select rst_RecTypeID, dty_ID, dty_Type = 'resource' as isResource from defRecStructure, defDetailTypes
                where rst_DetailTypeID=dty_ID and ((dty_Type != 'resource' and rst_RecordMatchOrder) or (dty_Type = 'resource' and rst_RequirementType = 'required'))
            order by rst_RecTypeID, dty_Type = 'resource', dty_ID");
            $hash_info = array();
            while ($row = mysql_fetch_assoc($res)) {
                if (! @$hash_info[$row["rst_RecTypeID"]]) $hash_info[$row["rst_RecTypeID"]] = array();
                $hash_info[$row["rst_RecTypeID"]][$row["dty_ID"]] = $row["isResource"];
            }
        }
        global $bdt_to_rectype;
        if (! @$bdt_to_rectype) {
            $temp = mysql__select_assoc('defDetailTypes', 'dty_ID', 'dty_PtrTargetRectypeIDs', 'dty_PtrTargetRectypeIDs is not null');
            foreach ($temp as $dtyID => $ptrTargetString) {
                $bdt_to_rectype[$dtyID] = explode(",",$ptrTargetString);
            }
        }
        $infos = $hash_info[$this->_rectype];

        $hhash = $this->_rectype . ":";
        foreach ($infos as $rdt_id => $isResource) {
            if (! $isResource) {
                $values = @$this->_fields_by_bdt_id[$rdt_id];
                if (! $values) continue;

                $_vals = array();
                foreach ($values as $val) {
                    // $val = preg_replace('/[[:punct:][:space:]]+/s', '', $val);
                    // use a hand-rolled space/punctuation check here so we don't accidentally strip out high-bytes -- can't wait for that UTF-8 compatibility!  Should be in PHP 2070
                    $val = preg_replace('/[\011-\015\040-\057\072-\100\133-\140\173-\176]+/s', '', $val);
                    if ($val) array_push($_vals, $val);
                }
                sort($_vals);

                if (count($_vals)) $hhash .= join(";", $_vals) . ";";
            } else { //resource pointer
                if ($this->_container  &&  in_array($this->_container->getReferenceType(), $bdt_to_rectype[$rdt_id])) {
                    $hhash .= '^' . $this->_container->getHHash() . '$';
                }
            }
        }

        $this->_hhash = $hhash;
        return $hhash;
    }


    function getCrapHash() {
        if ($this->_craphash) return $this->_craphash;

        global $hash_info;
        if (! $hash_info) {
            // hash_info contains all the good stuff we need for determining the hash, indexed by rectype, and then by dty_ID
            $res = mysql_query("select rst_RecTypeID, dty_ID, dty_Type = 'resource' as isResource from defRecStructure, defDetailTypes
                where rst_DetailTypeID=dty_ID and ((dty_Type != 'resource' and rst_RecordMatchOrder) or (dty_Type = 'resource' and rst_RequirementType = 'required'))
            order by rst_RecTypeID, dty_Type = 'resource', dty_ID");
            $hash_info = array();
            while ($row = mysql_fetch_assoc($res)) {
                if (! @$hash_info[$row["rst_RecTypeID"]]) $hash_info[$row["rst_RecTypeID"]] = array();
                $hash_info[$row["rst_RecTypeID"]][$row["dty_ID"]] = $row["isResource"];
            }
        }
        global $bdt_to_rectype;
        if (! @$bdt_to_rectype) {
            $temp = mysql__select_assoc('defDetailTypes', 'dty_ID', 'dty_PtrTargetRectypeIDs', 'dty_PtrTargetRectypeIDs is not null');
            foreach ($temp as $dtyID => $ptrTargetString) {
                $bdt_to_rectype[$dtyID] = explode(",",$ptrTargetString);
            }
        }

        $infos = $hash_info[$this->_rectype];

        $hhash = $this->_rectype . ":";
        foreach ($infos as $rdt_id => $isResource) {
            if (! $isResource) {
                $values = @$this->_fields_by_bdt_id[$rdt_id];
                if (! $values) continue;

                $_vals = array();
                foreach ($values as $val) {
                    // $val = preg_replace('/[[:punct:][:space:]]+/s', '', $val);
                    // use a hand-rolled space/punctuation check here so we don't accidentally strip out high-bytes -- can't wait for that UTF-8 compatibility!  Should be in PHP 2070
                    $val = preg_replace('/[\011-\015\040-\057\072-\100\133-\140\173-\176]+/s', '', $val);
                    if ($val) array_push($_vals, $val);
                }
                sort($_vals);

                if (count($_vals)) $hhash .= join(";", $_vals) . ";";
            } else {
                if ($this->_container  &&  in_array($this->_container->getReferenceType(), $bdt_to_rectype[$rdt_id])) {
                    $hhash .= '^' . $this->_container->getHHash() . '$';
                }
            }
        }

        $this->_craphash = $hhash;
        return $hhash;
    }


    function getTitleMetaphone() {
        if ($this->_title_metaphone) return $this->_title_metaphone;
        if ($this->_title_field)
            $this->_title_metaphone = metaphone(preg_replace('/^(?:a|an|the|la|il|le|die|i|les|un|der|gli|das|zur|una|ein|eine|lo|une)\\s+|^l\'\\b/i', '', $this->_title_field->getValue()));
        return $this->_title_metaphone;
    }


    function containerIsOptional() {
        /* returns true if the container for this type is not a required field */
        global $rectype_to_bdt_id_map;

        if (! $this->_container) return true;

        $containerBDType = intval($rectype_to_bdt_id_map[ $this->_container->getReferenceType() ]);
        $res = mysql_query("select * from defRecStructure where rst_DetailTypeID = $containerBDType and rst_RecTypeID = " . $this->_rectype . " and rst_RequirementType = 'required'");
        if (mysql_num_rows($res) == 0) return true;
        return false;
    }

    function getBkmkNotes() { return $this->_bkmk_personal_notes; }
    function setBkmkNotes($notes) { $this->_bkmk_personal_notes = $notes; }
    function addBkmkNotes($notes) { $this->_bkmk_personal_notes .= ($this->_bkmk_personal_notes ? "\n" : "") . $notes; }
}


class HeuristNativeField {
    var $_type;
    var $_raw_value, $_value;
    var $_geo_value;

    function HeuristNativeField($type, $raw_value, $value=NULL) {
        $this->_type = $type;
        $this->_raw_value = $raw_value;
        $this->_value = ($value === NULL)? $raw_value : $value;
        $this->_geo_value = NULL;
    }

    function getType() { return $this->_type; }
    // Return bib_detail_type.dty_ID for this field, or 0 if this has no recognised type in Heurist
    // (0-typed fields end up in the 'Other bibliographic data' section)

    function getRawValue() { return $this->_raw_value; }
    // Return the field value as it appears in the import file

    function getValue() { return $this->_value; }
    // Return the interpreted field value as it appears in the recDetails table


    function setGeographicValue($val) { $this->_geo_value = $val; }
    function getGeographicValue() { return $this->_geo_value; }
}


function decode_thesis_type(&$foreign_field) {	//SAW bug fix - the value passed in is not a Native Field structure it is just the raw value.
    /* see also the general DECODE ENUM TYPE */
    global $rec_detail_lookups;
    if (! $rec_detail_lookups) load_rec_detail_lookups();

    $value = trim($foreign_field);
    if (preg_match('/\\b(M)[.]?\\s*(A|Phil|Sc)\\b/', $value, $matches))	// masters
        $value = $matches[1].$matches[2];
    else if (preg_match('/Doctorat\\s+d\'..?tat/', $value))
        $value = 'Doctorat d\'Etat';
        else if (preg_match('/Doctorat\\s+3/', $value))
            $value = 'Doctorat 3eme cycle';
            else if (preg_match('/\\bP[.]?[hH][.]?[dD]\\b/', $value))
                $value = 'PhD';
                else if (preg_match('/\\bB[.]?A[.]?\\s+[Hh]on/', $value))
                    $value = 'BA Hons.';
                    else if (preg_match('/\\bD[.]?E[.]?S\\b/', $value))
                        $value = 'DES';
                        else
                            $value = 'Other';

    return $rec_detail_lookups[243][$value];
}


function is_enum_field($heurist_type) {
    static $bdt_enums = NULL;
    if (! $bdt_enums) $bdt_enums = mysql__select_assoc('defDetailTypes', 'dty_ID', '1', 'dty_Type="enum"');

    if (@$bdt_enums[$heurist_type]) return true;
    else return false;
}


function decode_enum($heurist_type, &$foreign_field) {// saw TODO Enum change   need to ensure this code and calling code use ids
    // find the best match for the given value, in the list of existing enum lookups
    global $rec_detail_lookups_lc;
    if (! $rec_detail_lookups_lc) load_rec_detail_lookups();

    $in_value = strtolower(trim($foreign_field));
    $possible_values = $rec_detail_lookups_lc[$heurist_type];

    // check for an exact match
    if (@$possible_values[$in_value]) return $possible_values[$in_value];

    // rip out punctuation and spaces, try again
    $in_value = preg_replace('/(?:[[:punct:]]|\\s)+/', '', $in_value);
    foreach ($possible_values as $key => $value)
        if ($in_value == preg_replace('/(?:[[:punct:]]|\\s)+/', '', $key)) return $value;

        // return a nothing value -- let the user figure out what's going on
        return NULL;
}


function load_bib_detail_requirements() {
    // $rec_detail_requirements is just an array of arrays; outer array is indexed by rectype,
    // inner array is a list of the bdt_ids required by that reference type.
    // Should probably detail with eXcluded elements at some stage, but not now.
    global $rec_detail_requirements;

    // mysql_connection_select('SHSSERI_bookmarks');
    //mysql_connection_select(DATABASE);
    $res = mysql_query('select rst_RecTypeID, rst_DetailTypeID from defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID where rst_RequirementType = "Y" and dty_Type != "resource"');
    $rec_detail_requirements = array();
    while ($row = mysql_fetch_row($res)) {
        if (array_key_exists($row[0], $rec_detail_requirements))
            array_push($rec_detail_requirements[$row[0]], $row[1]);
        else
            $rec_detail_requirements[$row[0]] = array($row[1]);
    }
}


function load_bib_requirement_names() {
    // $bib_requirement_names is an array of arrays; outer array is indexed by rectype,
    // inner array is a mapping of dty_ID to rst_DisplayName, union a mapping of rst_DisplayName to dty_ID
    global $bib_requirement_names;

    // mysql_connection_select('SHSSERI_bookmarks');
    $res = mysql_query('select rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, dty_Name from defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID');
    $bib_requirement_names = array();
    while ($row = mysql_fetch_row($res)) {
        if (! array_key_exists($row[0], $bib_requirement_names))
            $bib_requirement_names[$row[0]] = array();
        $bib_requirement_names[$row[0]][$row[1]] = $row[2];
        $bib_requirement_names[$row[0]][strtolower($row[3])] = $row[1];
    }
}


function load_bib_type_names() {
    // $bib_type_names is the mapping of dty_ID to dty_Name
    global $bib_type_names;

    // mysql_connection_select('SHSSERI_bookmarks');
    $res = mysql_query('select dty_ID, dty_Name from defDetailTypes');
    $bib_type_names = array();
    while ($row = mysql_fetch_row($res))
        $bib_type_names[$row[0]] = $row[1];
}


function load_bib_type_name_to_id() {
    // $bib_type_names is the mapping of dty_Name to dty_ID
    global $bib_type_name_to_id;

    // mysql_connection_select('SHSSERI_bookmarks');
    $res = mysql_query('select dty_ID, dty_Name from defDetailTypes');
    $bib_type_name_to_id = array();
    while ($row = mysql_fetch_row($res))
        $bib_type_name_to_id[strtolower($row[1])] = $row[0];
}


function load_heurist_rectypes() {
    // $heurist_rectypes is just the obvious mapping of rty_ID to the rest of the fields
    global $heurist_rectypes;

    $heurist_rectypes = array();

    // mysql_connection_select('SHSSERI_bookmarks');
    $res = mysql_query('select * from defRecTypes');
    while ($row = mysql_fetch_assoc($res)) {
        // fix up the title masks

        // magical strings unlikely to appear in a title mask: magic-open-bracket becomes [, magic-close-bracket becomes ]
        $row['rty_TitleMask'] = str_replace(array('[[', ']]'), array('magic-open-bracket', 'magic-close-bracket'), $row['rty_TitleMask']);
        $row['rty_TitleMask'] = preg_replace('/\\[([^\\]]+)\\]/e', "'['.strtolower('\\1').']'", $row['rty_TitleMask']);

        $heurist_rectypes[$row['rty_ID']] = $row;
    }
}


function load_rectype_name_to_id() {
    // $bib_type_names is the mapping of rty_Name to rty_ID
    global $rectype_name_to_id;

    // mysql_connection_select('SHSSERI_bookmarks');
    $res = mysql_query('select rty_ID, rty_Name from defRecTypes where rty_ID');
    $rectype_name_to_id = array();
    while ($row = mysql_fetch_row($res))
        $rectype_name_to_id[strtolower($row[1])] = $row[0];
}


function load_rec_detail_lookups() {	//saw TODO enumTerms change
    // $rec_detail_lookups is an array of arrays:
    // the outer keys are the dty_ID, the inner arrays are mapping from the lookup value to the lookup ID

    global $rec_detail_lookups, $rec_detail_lookups_lc;	// staid and lowercase versions of this data

    $res = mysql_query('select * from defTerms');
    $rec_detail_lookups = array();
    while ($row = mysql_fetch_assoc($res)) {
        if (! @$rec_detail_lookups[$row['trm_Label']])
            $rec_detail_lookups[$row['trm_Label']] = array();

        $rec_detail_lookups[$row['trm_VocabID']][$row['trm_Label']] = $row['trm_ID'];
        $rec_detail_lookups_lc[$row['trm_VocabID']][strtolower($row['trm_Label'])] = $row['trm_ID'];
    }
}


if (! function_exists('array_str_replace')) {

    function array_str_replace($search, $replace, $subject) {
        /*
        * PHP's built-in str_replace is broken when $search is an array:
        * it goes through the whole string replacing $search[0],
        * then starts again at the beginning replacing $search[1], &c.
        * array_str_replace instead looks for non-overlapping instances of each $search string,
        * favouring lower-indexed $search terms.
        *
        * Whereas str_replace(array("a","b"), array("b", "x"), "abcd") returns "xxcd",
        * array_str_replace returns "bxcd" so that the user values aren't interfered with.
        */

        $val = '';

        while ($subject) {
            $match_idx = -1;
            $match_offset = -1;
            for ($i=0; $i < count($search); ++$i) {
                $offset = strpos($subject, $search[$i]);
                if ($offset === FALSE) continue;
                if ($match_offset == -1  ||  $offset < $match_offset) {
                    $match_idx = $i;
                    $match_offset = $offset;
                }
            }

            if ($match_idx != -1) {
                $val .= substr($subject, 0, $match_offset) . $replace[$match_idx];
                $subject = substr($subject, $match_offset + strlen($search[$match_idx]));
            } else {	// no matches for any of the strings
                $val .= $subject;
                $subject = '';
                break;
            }
        }

        return $val;
    }

}

?>
