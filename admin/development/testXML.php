<?php

require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");


	function _parseTags($text) {
		$text = preg_replace('/<!-.*?->/s', '', $text);

		// Very simple context-free XML parser: doesn't support <X> which contains <X>.
		// Each tag is stored as tagName => array(tagName, openingTag, contents);
		// if a type of tag is encountered multiple times then tagName => array(array(tagName, openingTag1, contents1), array(tagName, openingTag2, contents2) ...)
		// Order is slightly preserved inasmuch as tags are returned in the order in which the first tag of each type was encountered.
		// CDATA is stored with the special (albeit slightly misleading) tagname "-text"





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
					if (@$contents["-text"]) {
						if (! is_array($contents["-text"])) $contents["-text"] = array($contents["-text"]);
						array_push($contents, $match[1]);
					}
					else $contents["-text"] = $match[1];
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
					if (@$contents["-text"]) {
						if (! is_array($contents["-text"]))
							$contents["-text"] = array($contents["-text"]);
						array_push($contents["-text"], $preTag);
					}
					else $contents["-text"] = $preTag;
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
					$parsedTags = _parseTags($innerContents);

					// bump the -text up to the front of the innerTags array
					if (@$parsedTags["-text"]) {
						if (count($parsedTags) > 1  &&  ! is_array($parsedTags["-text"]))
							$innerTags["-text"] = array($parsedTags["-text"]);
						else
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


$tags = _parseTags('
<recipe name="sandwich" prep_time="1 mins" cook_time="0 hours">
   <title>Basic sandwich</title>
   <ingredient amount="2" unit="slices">Bread</ingredient>
   <ingredient amount="500" unit="kilogram">Filling</ingredient>
   <instructions>
     <step>Mix all ingredients together.</step>
     <step>Knead thoroughly.</step>
   </instructions>
 </recipe>
');

$tags = _parseTags('
<a>
 <b>value1</b>
 <c>
  <d>value2</d>
 </c>
</a>
');


print_r( $tags );

?>
