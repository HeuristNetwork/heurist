<?php

/*
* Copyright (C) 2005-2020 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


// hand-rolled punctuation class ... doesn't go sticking its finger into any characters past chr(0x7F)
define('PUNCT', '[\041-\057\072-\100\133-\140\173-\176]');
define('notPUNCT', '[^\041-\057\072-\100\133-\140\173-\176]');

function parseName($allnames) {
	// return an array of arrays of the parts of the name
	// (one for each name encountered in the supplied $allnames value)

	$allnames = preg_replace('/corporatename=(.*)/i', '"$1"', $allnames);	// hells yeah, handle zotero's esoteric tagging of corporate names

	/* handle unpaddable spaces (\ ) in names as if they were ties (\0) */
	$allnames = str_replace('\\ ', '~', $allnames);
	$allnames = preg_replace('/\\s+/s', ' ', $allnames);
	$allnames = preg_replace('/^and(?: |$)/', '', $allnames);

	// Handle a stupid (but maybe common) special case:
	// user may have specified multiple authors separated by commas instead of "and".
	// In this case it's very much a case of caveat emptor: we're relying on them not to do something
	// entirely crazy like
	// Murtagh, T.F., Jackson, K.M., and Moore, R.J.B.
	//   or even
	// Murtagh, Tom, Jackson, Kim, and Moore, Richard
	// which we will *not* be able to pick up.  Something like that would even be hard to parse with the naked eye.
	// So we just look for Capitalised Words, More Capitalised Words, Et Cetera ...
	// and replace the comma following each (pair, triplet, tuplet) of Capitalised Words with an "and".

	// Note that this chokes on ... oh, just a random example, "De Cunzo, Lu Ann", which is one person's implausible name.
	// There are some more "first-word of a multiword surname only" names encoded in the negative lookahead here.
	$allnames = preg_replace('/(\\G(?!(?:De|La|Le)\\b)(?:[A-Z][^,]+ )(?:[a-zA-Z][^,]+ )*?(?:[A-Z][^,]+)) ?, ?/', '\\1 and ', $allnames);
	$names = explode(' and ', $allnames);

	$parsed_names = array();
	while ($names) {
		$oname = $name = array_shift($names);

		if (preg_match('/^"(.*)"$/', $name, $matches))	// quoted name
			$name = $matches[1];

		if (strtolower($name) == 'anonymous'  ||  preg_match('/^anon[.]?\b/i', $name)) {
			array_push($parsed_names, array('anonymous' => 1));
			continue;
		}

		$firstn = $vonn = $lastn = '';
		$title = '';
		$parsed_name = array();

		// special case: if user forgot the comma between SURNAME and obvious initials ...
		if (preg_match('/^([A-Z][a-z]+) ([A-Z](?:\\.|$))/', $name, $matches))
			$oname = $name = $matches[1] . ',' . $matches[2];

		if (preg_match('/('.notPUNCT.'+(?:[-\' ]'.notPUNCT.'+)*?)'	/* SURNAME */
		              .'( (?:[js]r\\.?|\\(?edi?t?o?r?s?\\.?\\)?|I+))?'	/* possibly JR or SR or EDITORS or I or II (etc.) */
		              .', ?('.notPUNCT.'.*?)'				/* NAMES or INITIALS */
		              .',?((?: ?(?:[js]r\\.?|\\(?edi?t?o?r?s?\\.?\\)?|\\bI+))?)$'
		              .'$/i', $name, $matches)) {
			$firstn = $matches[3];
			$lastn = $matches[1];
			$jrn = ($matches[2]  &&  $matches[4])? ($matches[2] . ' ' . $matches[4]) : ($matches[2] . $matches[4]);

			if (preg_match('/^(Mr|Mrs|Miss|Ms|Dr|Pr|Prof|[A-Z][a-z]+\\.)\\b\\s(.*)/', $firstn, $matches)) {
				// a title has been included
				$title = $matches[1];
				$firstn = $matches[2];
			}

		} else if (preg_match('/^(.*) ([a-z][.*]|\\S+)$/', $name, $matches)) {
			// Senthil has thoughtfully put all her authors' names in the form FIRSTNAMES SURNAME
			// THANKYOU VERY MUCH!!!

			$firstn = $matches[1];
			$lastn = $matches[2];

			if (preg_match('/^(Mr|Mrs|Miss|Ms|Dr|Pr|Prof|[A-Z][a-z]+\\.)\\b\\s(.*)/', $firstn, $matches)) {
				// a title has been included
				$title = $matches[1];
				$firstn = $matches[2];
			}

		} else if (preg_match('/^(?:et\\.? al\\.?|al\\.?, et\\.?|others)$/i', $name)) {
			$jrn = 'others';

		} else {
			array_push($parsed_names, array('full name' => $name, 'questionable' => $name));
			continue;
		}

		if ($jrn == 'others') {
			array_push($parsed_names, array('others' => 1));
		} else if ($jrn) {
			array_push($parsed_names, array('full name' => $oname, 'surname' => $lastn, 'title' => $title, 'first names' => $firstn, 'postfix' => $jrn));
		} else if ($lastn  ||  $firstn) {
			array_push($parsed_names, array('full name' => $oname, 'surname' => $lastn, 'title' => $title, 'first names' => $firstn));
		}
	}

	return $parsed_names;
}


function checkNames($allnames, $force_authors=0) {
	// check that the given string consists of a name, or a list of names, in a format known to parseName(..)
	// Returns an array of descriptive errors as appropriate.

	/* handle unpaddable spaces (\ ) in names as if they were ties (\0) */
	$allnames = str_replace('\\ ', '~', $allnames);
	$allnames = preg_replace('/\\s+/s', ' ', $allnames);
	$allnames = preg_replace('/^and(?: |$)/', '', $allnames);

	// Handle a stupid (but maybe common) special case:
	// user may have specified multiple authors separated by commas instead of "and".
	// In this case it's very much a case of caveat emptor: we're relying on them not to do something
	// entirely crazy like
	// Murtagh, T.F., Jackson, K.M., and Moore, R.J.B.
	//   or even
	// Murtagh, Tom, Jackson, Kim, and Moore, Richard
	// which we will *not* be able to pick up.  Something like that would even be hard to parse with the naked eye.
	// So we just look for Capitalised Words, More Capitalised Words, Et Cetera ...
	// and replace the comma following each (pair, triplet, tuplet) of Capitalised Words with an "and".

	// Note that this chokes on ... oh, just a random example, "De Cunzo, Lu Ann", which is one person's implausible name.
	// There are some more "first-word of a multiword surname only" names encoded in the negative lookahead here.
	$allnames = preg_replace('/(\\G(?!(?:De|La|Le)\\b)(?:[A-Z][^,]+ )(?:[a-zA-Z][^,]+ )*?(?:[A-Z][^,]+)) ?, ?(?=.* )/', '\\1 and ', $allnames);
	$names = explode(' and ', $allnames);

	$errors = array();
	while ($names) {
		$oname = $name = array_shift($names);
		if (preg_match('/^".*"$/', $name)) continue;	// a quoted name

		if (strtolower($name) == 'anonymous'  ||  preg_match('/^anon[.]?\b/i', $name)) {
			continue;
		}

		if (preg_match('/\\b(?:university|dept|department|national|of|for)\\b|[()]/i', $name)) {
			array_push($errors, '"'.$name.'" doesn\'t look like a person\'s name.  Put the name in "double quotes" to force import.');
			continue;
		}

		$firstn = $vonn = $lastn = '';
		$title = '';

		if (strpos($name, ' ') === FALSE  &&  ! $force_authors) {
			// single word name? realllllly?
			array_push($errors, '"'.$name.'" doesn\'t look like a person\'s name.  Put the name in "double quotes" to force import.');
			continue;
		}

		// special case: if user forgot the comma between SURNAME and obvious initials ...
		if (preg_match('/^([A-Z][a-z]+) ([A-Z](?:\\.|$))/', $name, $matches))
			$oname = $name = $matches[1] . ',' . $matches[2];

		if (preg_match('/('.notPUNCT.'+(?:[-\' ]'.notPUNCT.'+)*?)'	/* SURNAME */
		              .'( (?:[js]r\\.?|\\(?edi?t?o?r?s?\\.?\\)?|I+))?'	/* possibly JR or SR or EDITORS or I or II (etc.) */
		              .', ?('.notPUNCT.'.*?)'				/* NAMES or INITIALS */
		              .',?((?: ?(?:[js]r\\.?|\\(?edi?t?o?r?s?\\.?\\)?|I+))?)$'
		              .'$/i', $name, $matches)) {
			continue;

		} else if (preg_match('/^(.*) ([a-z][.*]|\\S+)$/', $name, $matches)) {
			// Senthil has thoughtfully put all her authors' names in the form FIRSTNAMES SURNAME
			// THANKYOU VERY MUCH!!!
			continue;

		} else if (preg_match('/^(?:et\\.? al\\.?|al\\.?, et\\.?|others)$/i', $name)) {
			continue;

		} else {
			array_push($errors, '"'.$name.'" doesn\'t look like a person\'s name.  Put the name in "double quotes" to force import.');
		}
	}

	return $errors;
}




?>
