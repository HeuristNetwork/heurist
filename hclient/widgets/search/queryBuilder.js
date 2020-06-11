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
* Heurist Query helper 
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


var HQuery = (function() {

/* private */ function splitValue(val) {
	/* split the given value into word chunks, respecting quoted phrases */
	val = val.replace(/^\s+|\s+$/g, '');
	var val_bits = val.split(/\s+/);

	var bits = [];
	var open_quotes = false;
	for (var i=0; i < val_bits.length; ++i) {
		if (val_bits[i].match(/^"[^"\s]*"$/)) val_bits[i] = val_bits[i].substring(1, val_bits[i].length-1);

		if (open_quotes) {
			bits[bits.length-1] += ' ' + val_bits[i];
			if (val_bits[i].charAt(val_bits[i].length-1) == '"') open_quotes = false;
		} else {
			if (val_bits[i].charAt(0) == '"') open_quotes = true;
			bits.push(val_bits[i]);
		}
	}

	return bits;
}

/* private */ function parseMinibit(minibit) {
	var bit_type = '';
	var bit_val = '';
	var colon_pos = minibit.indexOf(':');

	if (colon_pos == -1) {
		bit_type = 'title';
		bit_val = minibit;
	} else {
		bit_type = minibit.substr(0, colon_pos);
		bit_val = minibit.substr(colon_pos+1);

		switch (bit_type) {
			case 't': bit_type = 'type'; break;
			case 'u': bit_type = 'url'; break;
			case 'n': bit_type = 'notes'; break;
			case 'ids': bit_type = 'id'; break;
			case 'usr': bit_type = 'user'; break;
			case 'wg': bit_type = 'owner'; break;
			case 'kwd': case 'keyword': bit_type = 'tag'; break;
			case 'any': bit_type = 'all'; break;
			case 'f': case 'field':
			bit_type = 'field';
			colon_pos = bit_val.indexOf(':');
			if (colon_pos >= 0) {
				bit_type += ':' + bit_val.substr(0, colon_pos);
				bit_val = bit_val.substr(colon_pos+1);
			}
			break;
		}
	}

	return { type: bit_type, value: bit_val };
}

/* private */ function dcMultiply(dc1, dc2) {
	var result = []
	for (var i=0; i < dc1.length; ++i) {
		for (var j=0; j < dc2.length; ++j) {
			var elt = dc1[i].slice(0);
			for (var k=0; k < dc2[j].length; ++k) elt.push(dc2[j][k]);
			result.push(elt);
		}
	}
	return result;
}


/* private */ function simpleMultiply(dc1, term) {
	var result = []
	for (var i=0; i < dc1.length; ++i) {
		var elt = dc1[i].slice(0);
		elt.push(term);
		result.push(elt);
	}
	return result;
}


return {

	parseQuery: function(q_str) {
		var clauses = {}
			// clauses[xxx] is an array of arrays:
			// the inner array is to be ANDed, the outer array is to be ORed

		var AND_bits = q_str.split(/\s*\bAND\b\s*/);

		for (var i=0; i < AND_bits.length; ++i) {
			var OR_clauses = []
			var OR_bits = AND_bits[i].split(/\s*\bOR\b\s*/);

			if (OR_bits[1]) {
				var _bit_type = '';
				for (var j=0; j < OR_bits.length; ++j) {
					var minibits = OR_bits[j].match(/(?:[^" ]+:|"[^"]*":)*(?:[^" ]+|"[^"]*")/g);
					if (! minibits) continue;

					var parsed_minibits = []

					for (var k=0; k < minibits.length; ++k) {
						var type_and_value = parseMinibit(minibits[k]);

						if (type_and_value.value) {
							parsed_minibits.push(type_and_value.value);

							// we can only have one bit-type per ORed limb
							if (! _bit_type) _bit_type = type_and_value.type;
							else if (_bit_type != type_and_value.type) return null;
						}
					}

					if (_bit_type  &&  parsed_minibits) OR_clauses.push(parsed_minibits);
				}

				if (_bit_type  &&  OR_clauses.length) {
					if (! clauses[_bit_type]) {
						clauses[_bit_type] = OR_clauses;
					} else {
						// this bit-type has already been referenced in a previous AND sub-clause:
						// multiply the disjunctive conjunctions! (hahA!)
						clauses[_bit_type] = dcMultiply(clauses[_bit_type], OR_clauses);
					}
				}

			} else {
				var minibits = OR_bits[0].match(/(?:[^" ]+:|"[^"]*":)*(?:[^" ]+|"[^"]*")/g);
				if (minibits) {
					for (var j=0; minibits[j]; ++j) {
						var type_and_value = parseMinibit(minibits[j]);
						if (! type_and_value.value) continue;

						if (! clauses[type_and_value.type]) {
							clauses[type_and_value.type] = []
							clauses[type_and_value.type][0] = []
							clauses[type_and_value.type][0][0] = type_and_value.value;
						} else {
							clauses[type_and_value.type] = simpleMultiply(clauses[type_and_value.type], type_and_value.value);
						}
					}
				}
			}
		}

		return clauses;
	},

	makeQuerySnippet: function(type, val) {
		if (type === "sortby") { return type + ":" + val; }

		var val_bits = splitValue(val);

		if (type.match(/^"[^"\s]*"$/)) type = type.substring(1, type.length-1);
		else if (type.match(/^field:"[^"\s]*"$/)) type = 'field:' + type.substring(7, type.length-1);

		var snippet = '';
		for (var i=0; i < val_bits.length; ++i) {
			if (snippet) snippet += ' ';

			if (val_bits[i] == 'OR') {
				snippet += 'OR';
				continue;
			} else if (val_bits[i] == 'AND') {
				continue;	// elide ANDs
			}

			if (type) snippet += type + ':' + val_bits[i];
			else snippet += val_bits[i];
		}

		return snippet;
	}

};

})();

