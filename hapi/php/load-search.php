<?php

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

session_cache_limiter('no-cache');
define('SAVE_URI', 'disabled');
define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__)."/../../search/saved/loading.php");

$args = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

$result = loadSearch($args);

if (array_key_exists("records", $result)) {
	// turn the associative array into the non-associative one HAPI expects
	foreach ($result["records"] as $i => $record) {
		$result["records"][$i] = array(
			@$record["rec_id"],                   //  0
			null,                                //  1 ("version")
			@$record["rec_type"],                 //  2
			@$record["rec_title"],                //  3
			@$record["details"],                  //  4
			@$record["rec_url"],                  //  5
			@$record["rec_scratchpad"],           //  6
			@$record["rec_wg_id"],                //  7
			@$record["rec_visibility"],           //  8
			@$record["rec_url_last_verified"],    //  9
			@$record["rec_url_error"],            // 10
			@$record["rec_added"],                // 11
			@$record["rec_modified"],             // 12
			@$record["rec_added_by_usr_id"],      // 13
			@$record["rec_hhash"],                // 14
			@$record["bkm_ID"],                  // 15
			@$record["pers_notes"],               // 16
			@$record["bkm_Rating"],      // 17
			null, //@$record["pers_interest_rating"],     // 18  // pass null to not affect ordering HAPI expects
			null, //@$record["pers_quality_rating"],      // 19  //saw FIXME: should just change the internal interface removing old args.
			@$record["tags"],                     // 20
			@$record["wgTags"],                   // 21
			@$record["notifies"],                 // 22
			@$record["comments"]                  // 23
		);
	}
}

print json_format($result);

?>
