<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../importerBaseClass.php");

$titleDT = (defined('DT_NAME')?DT_NAME:0);
$geoDT = (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:0);
$locationDT = (defined('DT_LOCATION')?DT_LOCATION:0);
$contactDT = (defined('DT_CONTACT_INFO')?DT_CONTACT_INFO:0);
$startdateDT = (defined('DT_START_DATE')?DT_START_DATE:0);
$enddateDT = (defined('DT_END_DATE')?DT_END_DATE:0);


$kml_to_heurist_map = array(
	"name" => $titleDT,
	"address" => $locationDT,
	"AddressDetails" => $locationDT,
	"phoneNumber" => $contactDT,
	"Snippet" => 0,
	"description" => 0,
	"TimeSpan" => array("begin" => $startdateDT, "end" => $enddateDT),
	"TimeStamp" => array("when" => array($startdateDT, $enddateDT)),
	"Metadata" => 0,

	"Region" => "geo",
	"Point" => "geo",
	"LineString" => "geo",
	"LinearRing" => "geo",
	"Polygon" => "geo",
	"MultiGeometry" => "geo",

	"ExtendedData" => "extended"
);


class HeuristKMLParser extends HeuristForeignParser {

	function recogniseFile(&$file) {
		$file->rewind();
		$beginning = "";
		for ($i = 6; $i > 0; --$i) {
			$beginning .= $file->getLine();
			$file->nextLine();
		}

		// File should start with an xml declaration and a kml header, or at least a kml header ...
		if (! preg_match('/^\s*(<[?]xml[^>]*>)?\s*<kml[^>]*>/is', $beginning)) return FALSE;
		return TRUE;
	}


	function parserDescription() { return 'KML parser'; }


	function parseFile(&$file) {
        
		$fp = $file->getRawFile();
		rewind($fp);

		// easiest to grab the file as a single string and use the (natively compiled) preg stuff
		$contents = fread($fp, $file->getFileSize());
		$errors = array();
		$entries = array();

/* preg_match_all chokes on especially large inputs ... we have to do it the hard way
		if (! preg_match_all('!<Placemark[^>]*>.*?</Placemark>!s', $contents, $matches))
			return array( array('No <Placemark> tags found'), NULL );
*/
		$startOffset = 0;
		$matches = array(array());
		while (($startOffset = stripos($contents, "<placemark", $startOffset)) !== FALSE) {
			$endOffset = stripos($contents, "</placemark>", $startOffset);
			if ($endOffset != FALSE) {
				$placemarkText = substr($contents, $startOffset, $endOffset-$startOffset+12);
				array_push($matches[0], $placemarkText);
				$startOffset = $endOffset + 12;
			}
		}

		foreach ($matches[0] as $placemarkText) {
			try {
				$entries[] = $this->_makeNewEntry($placemarkText);
			} catch (Exception $e) {
				array_push($errors, $e->getMessage());
			}
		}

		if ($errors) return array( $errors, NULL );
		return array( NULL, &$entries );
	}


	function outputEntries(&$entries) {
		$output = "";

		foreach (array_keys($entries) as $i) {
			unset($entry);
			$entry = &$entries[$i];
			$some_entry_output = FALSE;

			if ($entry->getErrors()) {
				foreach ($entry->getErrors() as $error) {
					$output .= "<!-- ERROR: " . htmlspecialchars($error) . " -->\n";
					$some_entry_output = TRUE;
				}
			}
			if ($entry->getWarnings()) {
				foreach ($entry->getWarnings() as $warning) {
					$output .= "<!-- WARNING: " . htmlspecialchars($warning) . " -->\n";
					$some_entry_output = TRUE;
				}
			}
			if ($entry->getValidationErrors()) {
				foreach ($entry->getValidationErrors() as $error) {
					$output .= "<!-- DATA ERROR: " . htmlspecialchars($error) . " -->\n";
					$some_entry_output = TRUE;
				}
			}
		}

		return $output;
	}


	private static $_referenceTypes;
	function getReferenceTypes() {
		if (HeuristKMLParser::$_referenceTypes) return array_keys($this->_referenceTypes);

		mysql_connection_select(DATABASE);	// saw TODO check that this is correct seems that it is trying order on non bib types.
		HeuristKMLParser::$_referenceTypes = mysql__select_assoc("defRecTypes ", "rty_Name", "rty_ID", "rty_ShowInLists = 1 order by rty_RecTypeGroupID = 2, rty_Name");

		return array_keys(HeuristKMLParser::$_referenceTypes);
	}

	public static function getHeuristReferenceTypeByID($kmlType) {
		return HeuristKMLParser::$_referenceTypes[$kmlType];
	}


	// we don't override guessReferenceType ... leave it up to the user


	function _makeNewEntry($text) { return new HeuristKMLEntry($text); }
}


class TrivialXMLParser {

	public static function getValue(&$parsedTags /* , tag1 , tag2 , ... */) {
		/* Get a single (text) value of a node from the given tree structure (as parsed by _parseTags)
		   e.g. if $T is the parse structure for "<a><b>value1</b><c param='value2'><d>value3</d></c></a>",
		   then   getValue($T, "a", "b") => "value1"
		   and    getValue($T, "a", "b", "c", "param") => "value2"
		   and    getValue($T, "a", "b", "c", "d") => "value3"
		 */
		$pathComponents = func_get_args();
		array_shift($pathComponents);

		unset($currentNode);
		$currentNode = &$parsedTags;
		while (count($pathComponents) > 0) {
			$nextComponent = strtolower(array_shift($pathComponents));
			if (! @$currentNode[$nextComponent]  ||  ! @$currentNode[$nextComponent][0]) return NULL;

			unset($tmpNode);     $tmpNode = &$currentNode[$nextComponent][0];
			unset($currentNode); $currentNode = &$tmpNode;
		}

		if (is_string($currentNode)) {
			return $currentNode;
		} else if (@$currentNode["-text"]) {
			return $currentNode["-text"][0];
		} else if (is_array($currentNode)) {
			return $currentNode;
		}

		return NULL;
	}


	public static function parseTags($text) {
		$text = preg_replace('/<!-.*?->/s', '', $text);

		if (strpos($text, "<") === FALSE) return array("-text" => array($text));

		// Very simple context-free XML parser: doesn't support <X> which contains <X>.
		// Each tag is stored as tagName => array(tagName, openingTag, contents);
		// if a type of tag is encountered multiple times then tagName => array(array(tagName, openingTag1, contents1), array(tagName, openingTag2, contents2) ...)
/*

<recipe name="sandwich" prep_time="1 mins" cook_time="0 hours">
   <title>Basic sandwich</title>
   Blah1
   <ingredient amount="2" unit="slices">Bread</ingredient>
   <ingredient amount="500" unit="kilogram">Filling</ingredient>
   Blah2
   <instructions>
     <step>Mix all ingredients together.</step>
     <step>Knead thoroughly.</step>
   </instructions>
 </recipe>

produces (using JSO notation)

{
  recipe: [
  {
    name: "sandwich",
    prep_time: "1 mins",
    cook_time: "0 hours",

    title: [ { -text: "Basic sandwich" } ],
    ingredient: [
      { amount: "2", unit: "slices", -text: "Bread" },
      { amount: "500", unit: "kilogram", -text: "Filling" },
    ],
    instructions: [ {
      step: [
        { -text: "Mix all ingredients together." },
        { -text: "Knead thoroughly." }
      ]
    } ]
  } ]
}

*/
		// Order is slightly preserved inasmuch as tags are returned in the order in which the first tag of each type was encountered.
		// CDATA is stored with the special (albeit slightly misleading) tagname "-text"

		// Below:
		//  a = cdata before empty tag (e.g. <BR/>)
		//  b = tag name for empty tag
		//  c = tag attributes for empty tag
		//  d = cdata before full tag (e.g. <P>..</P>)
		//  e = tag name for full tag
		//  f = tag attributes for full tag
		//  g = data inside full tag
		//  h = closing tag
		//  i = cdata after all tags have been harvested
		//
		//                              \1         \2     \3\4                \5     \6\7              \8         \9
		//                                          aaaaa   bbbbbbbb ccccc     ddddd   eeeeeeee fffff   ggg  hhh   iiiii
		if (preg_match_all('@<!\[CDATA\[(.*?)\]\]>|([^<]*)(<([^>\s]+)[^>]*/>)|([^<]*)(<([^>\s]+)[^>]*>)(.*?)</\7>|([^<]+)@si', $text, $matches, PREG_SET_ORDER)) {
			$contents = array();
			foreach ($matches as $match) {
				$preTag = "";
				$openingTag = "";
				$tagName = "";
				$innerContents = "";

				if ($match[1]) {
					// CDATA -- not to be processed any further (leave everything intact)
					if (! @$contents["-text"]) $contents["-text"] = array();
					array_push($contents["-text"], $match[1]);
					continue;
				}
				else if ($match[4]) {
					// empty-element tag
					$preTag = $match[2];
					$openingTag = $match[3];
					$tagName = $match[4];
				}
				else if ($match[7]) {
					// nonempty-element tag
					$preTag = $match[5];
					$openingTag = $match[6];
					$tagName = $match[7];
					$innerContents = trim($match[8]);

					if ($innerContents) {
						$nestedTagPos = stripos($innerContents, "<".$tagName);
						if ($nestedTagPos !== FALSE  &&  preg_match('/^[\s>]/i', $innerContents, $dummy, NULL, $nestedTagPos+strlen($tagName)+1))
							throw new Exception("nested <" . $tagName . "> tag not supported");
					}
				}
				else if ($match[9]) {
					// text
					$preTag = $match[9];
				}

				$tagName = strtolower($tagName);
				$preTag = trim($preTag);
				if ($preTag) {
					$preTag = str_replace(array("&lt;", "&gt;", "&amp;", "&apos;", "&quot;"), array("<", ">", "&", "'", "\""), $preTag);
					if (! @$contents["-text"]) $contents["-text"] = array();
					array_push($contents["-text"], $preTag);
				}
				if (! $openingTag) continue;

				$innerTags = array();

				// Parse attributes
				if (preg_match_all("/\s([^\s=>]+)=?(?:\"([^\"]*)\"|'([^']*)'|([^\s>]*))/", $openingTag, $attribMatches, PREG_SET_ORDER)) {
					foreach ($attribMatches as $attribMatch) {
						$attribName = str_replace(array("&lt;","&gt;","&amp;","&apos;","&quot;"), array("<",">","&","'","\""), $attribMatch[1]);

						if (@$attribMatch[2]) $attribValue = $attribMatch[2];
						else if (@$attribMatch[3]) $attribValue = $attribMatch[3];
						else if (@$attribMatch[4]) $attribValue = $attribMatch[4];
						else $attribValue = "";
						$attribValue = str_replace(array("&lt;","&gt;","&amp;","&apos;","&quot;"), array("<",">","&","'","\""), $attribValue);

						$innerTags[$attribName] = $attribValue;
					}
				}

				// Parse innards
				if ($innerContents) {
					$parsedTags = TrivialXMLParser::parseTags($innerContents);

					// bump the -text up to the front of the innerTags array
					if (@$parsedTags["-text"]) {
						$innerTags["-text"] = $parsedTags["-text"];
						unset($parsedTags["-text"]);
					}
					foreach ($parsedTags as $_tagName => $_parsedTag) {
						$innerTags[$_tagName] = $_parsedTag;
					}
				}

				if (! @$contents[$tagName]) $contents[$tagName] = array();
				array_push($contents[$tagName], $innerTags);
			}

			return $contents;
		}

		return NULL;
	}
}


class HeuristKMLEntry extends HeuristForeignEntry {
	var $_text;
	var $_innerTags;

	var $_type;
	var $_fields;

	function HeuristKMLEntry($text) {
		$this->_text = $text;
		$this->_innerTags = NULL;

		$this->_type = NULL;
		$this->_fields = array();
	}

	function getFields() { return $this->_fields; }

	function setReferenceType($type) { $this->_type = $type; }
	function getReferenceType() { return $this->_type; }

	function parseEntry() {
		// Each PLACEMARK in KML corresponds to a new RECORD in HEURIST
		$this->_innerTags = TrivialXMLParser::parseTags($this->_text);
		$placemark = &$this->_innerTags["placemark"][0];

		global $kml_to_heurist_map;
		foreach ($kml_to_heurist_map as $kmlTag => $type) {
			if ($type === "geo") {
				$value = @$placemark[strtolower($kmlTag)];
				if (! $value) continue;
			}
			else {
				$value = TrivialXMLParser::getValue($placemark, $kmlTag);
				if ($value === NULL) continue;
			}

			if (is_array($value)) { $this->_fields[$kmlTag] = $value; }
			else { $this->_fields[$kmlTag] = array($value); }
		}
		if (count($this->_fields) == 0) return ($this->_errors = array("No known fields found in <Placemark>"));
		return NULL;
	}

	function crosswalk() {
		global $kml_to_heurist_map, $geoDT;

		$heuristType = HeuristKMLParser::getHeuristReferenceTypeByID($this->_type);
		if (! $heuristType) return NULL;

		$entry = new HeuristNativeEntry($heuristType);

		foreach ($this->_fields as $kmlTag => $value) {
			$heuristFieldID = $kml_to_heurist_map[$kmlTag];
			if (strtolower($kmlTag) == "metadata"  &&  $value[0]["detail"]) {
				$value = $value[0];
				foreach ($value["detail"] as $detail) {
					$entry->addField(new HeuristNativeField(intval($detail["id"]), $detail["-text"][0]));
				}
				continue;
			}

			if (is_array($heuristFieldID)  &&  @$heuristFieldID[0]) {
				// just an array of detail IDs
				foreach ($heuristFieldID as $hField) {
					foreach($value as $val) {
						$entry->addField(new HeuristNativeField($hField, $val["-text"][0]));
					}
				}
			} else if (is_array($heuristFieldID)) {
				// some sub-tags
				foreach ($heuristFieldID as $subTag => $hField) {
					if (! is_array($hField)) {
						$hField = array($hField);
					}
					if (@$value[$subTag]) {
						foreach($value[$subTag] as $val) {
							foreach($hField as $f) {
								$entry->addField(new HeuristNativeField($f, $val["-text"][0]));
							}
						}
					}
				}
			} else if ($heuristFieldID === "geo") {
				foreach ($value as $val) {
					$geometries = $this->_parseGeometry($kmlTag, $val);
					foreach ($geometries as $geometry) {
						list($geoType, $geoValue) = $geometry;

						unset($newField);
						$newField = new HeuristNativeField($geoDT, $geoType);
						$newField->setGeographicValue($geoValue);
						$entry->addField($newField);
					}
				}
			} else if ($heuristFieldID === "extended") {
				$this->_parseExtendedData($entry, $value);
			} else {
				foreach ($value as $val) {
					$entry->addField(new HeuristNativeField($heuristFieldID, $val));
				}
			}
		}

		return $entry;
	}

	function _parseGeometry($geoType, $innerTags) {
		$geometries = array();

		switch (strtolower($geoType)) {
		    case "region":
			$box = $innerTags["latlonaltbox"][0]["-text"][0];
			$n = floatval(TrivialXMLParser::getValue($box, "north"));
			$s = floatval(TrivialXMLParser::getValue($box, "south"));
			$w = floatval(TrivialXMLParser::getValue($box, "west"));
			$e = floatval(TrivialXMLParser::getValue($box, "east"));
			if ($n && $s && $w && $e) {
				// store a rectangle
				array_push($geometries, array("r", "POLYGON (($n $w,$n $e,$s $e,$s $w,$n $w))"));
			}
			break;

		    case "point":
			list($w,$n) = array_map("floatval", explode(',', $innerTags["coordinates"][0]["-text"][0]));
			if ($w && $n) {
				// store a point
				array_push($geometries, array("p", "POINT ($w $n)"));
			}
			break;

		    case "linestring":
			if (preg_match_all('/([^\s,]+),([^\s,]+)(?:,[^\s+])?/', $innerTags["coordinates"][0]["-text"][0], $coords, PREG_SET_ORDER)) {
				// store a path (linestring)
				$bdGeoValue = "";
				foreach ($coords as $coord) {
					if ($bdGeoValue) $bdGeoValue .= ",";
					$bdGeoValue .= floatval($coord[1])." ".floatval($coord[2]);
				}
				$bdGeoValue = "LINESTRING (" . $bdGeoValue . ")";
				array_push($geometries, array("l", $bdGeoValue));
			}
			break;

		    case "polygon":
			// a polygon is defined by an OUTER BOUNDARY, and zero or more INNER BOUNDARIES, all of which are just linear rings
			$outerBoundaryTags = @$innerTags["outerboundaryis"][0]["linearring"][0];
			if (! $outerBoundaryTags) break;
			$ring = $this->_parseLinearRing($outerBoundaryTags);
			if (! $ring) break;
			$bdGeoValue = "(" . $ring . ")";
			if (@$innerTags["innerboundaryis"]) {
				foreach(@$innerTags["innerboundaryis"] as $innerBoundaryTags) {
					$innerRing = $this->_parseLinearRing($innerBoundaryTags["linearring"][0]);
					if ($innerRing) {
						$bdGeoValue .= ",(" . $innerRing . ")";
					}
				}
			}
			array_push($geometries, array("pl", "POLYGON (" . $bdGeoValue . ")"));
			break;

		    case "linearring":
			$bdGeoValue = $this->_parseLinearRing($innerTags);
			if ($bdGeoValue) {
				$bdGeoValue = "POLYGON ((" . $bdGeoValue . "))";
				array_push($geometries, array("pl", $bdGeoValue));
			}
			break;

		    case "multigeometry":
			// a list of geometries -- we will add one dtl_Geo field per geometry
			foreach ($innerTags as $tag => $values) {
				foreach ($values as $value) {
					$innerGeometries = $this->_parseGeometry($tag, $value);
					foreach ($innerGeometries as $innerGeometry) array_push($geometries, $innerGeometry);
				}
			}
			break;
		}

		return $geometries;
	}

	function _parseLinearRing($innerTags) {
		if (preg_match_all('/([^\s,]+),([^\s,]+)(?:,[^\s+])?/', $innerTags["coordinates"][0]["-text"][0], $coords, PREG_SET_ORDER)) {
			$coordString = "";
			foreach ($coords as $coord) {
				if ($coordString) $coordString .= ",";
				$coordString .= floatval($coord[1])." ".floatval($coord[2]);
			}
			return $coordString;
		}
		return NULL;
	}

	function _parseExtendedData(&$entry, $innerTags) {
        
        if(is_array(@$innerTags['data'])){
		    foreach ($innerTags["data"] as $data) {
			    if ($data["name"] === "HeuristID") {
				    $id = $data["value"][0]["-text"][0];
				    setPermanentBiblioID($entry, $id);
			    }
		    }
        }
	}
}

registerHeuristFiletypeParser( new HeuristKMLParser() );

?>
