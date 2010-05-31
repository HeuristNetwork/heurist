<?php

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

foreach (get_all_instances() as $prefix => $instance) {

	mysql_connection_db_overwrite($instance["db"]);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);	// timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
	curl_setopt($ch, CURLOPT_PROXY, 'www-cache.usyd.edu.au:8080');
	curl_setopt($ch, CURLOPT_RANGE, '0-1000');

	// another connection with some atypical headers
	$ch_ffx = curl_init();
	curl_setopt($ch_ffx, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch_ffx, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch_ffx, CURLOPT_NOBODY, 0);
	curl_setopt($ch_ffx, CURLOPT_HEADER, 1);
	curl_setopt($ch_ffx, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch_ffx, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch_ffx, CURLOPT_ENCODING, 'identity');
	curl_setopt($ch_ffx, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.4) Gecko/20070515 Firefox/2.0.0.4');
	curl_setopt($ch_ffx, CURLOPT_HTTPHEADER, array('Cache-Control: no-cache'));
	curl_setopt($ch_ffx, CURLOPT_TIMEOUT, 10);	// timeout after ten seconds
	curl_setopt($ch_ffx, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
	curl_setopt($ch_ffx, CURLOPT_PROXY, 'www-cache.usyd.edu.au:8080');
	curl_setopt($ch_ffx, CURLOPT_RANGE, '0-1000');


	mysql_query("set @suppress_update_trigger := 1");
	$res = mysql_query('select rec_id, rec_url from records where rec_url is not null and rec_url != "" and not rec_temporary order by rec_url_last_verified is not null, rec_url_last_verified asc');

	$good_bibs = array();
	$bad_bibs = array();

	while ($row = mysql_fetch_assoc($res)) {
		$row['rec_url'] = str_replace(" ", "+", trim($row['rec_url']));

		if (preg_match('/^file/', $row['rec_url'])) continue;

		curl_setopt($ch, CURLOPT_URL, $row['rec_url']);
		$data = curl_exec($ch);

		$error = curl_error($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

		if (curl_error($ch)  ||  $code >= 400) {
			curl_setopt($ch_ffx, CURLOPT_URL, $row['rec_url']);
			$data = curl_exec($ch_ffx);
			$code = intval(curl_getinfo($ch_ffx, CURLINFO_HTTP_CODE));
			$error = curl_error($ch_ffx);
		}

		// 401 is UNAUTHORISED (don't bother with user authentication -- we can't do it anyway)
		if ($code != 401  &&  $code >= 400) {
			$error = 'URL could not be retrieved ('.$code.')';
		}

		if ($error) {
			$bad_bibs[$row['rec_id']] = array($row['rec_url'], $error);

			if ( ($err = error_for_code($code)) )
				mysql_query('update records set rec_url_error="'.$code.': '.addslashes($err).'" where rec_id = ' . $row['rec_id']);
		} else {
			array_push($good_bibs, $row['rec_id']);

			mysql_query('update records set rec_url_last_verified=now(), rec_url_error=null where rec_id = ' . $row['rec_id']);
		}
	}

	$message = 'HEURIST nightly URL check

	There were problems with the URLs for the following bibliographic records:
	';
	foreach ($bad_bibs as $rec_id => $details) {
		$message .= '  ' . $rec_id . ': ' . $details[0] . ' (' . $details[1] . ')' . "\n";
	}
	$message .= "\n\nFind these at\nhttp://" . ($prefix? $prefix.".": "") . HOST_BASE .HEURIST_SITE_PATH."search/heurist-search.html?q=ids:" . join(',', array_keys($bad_bibs)) . "\n";
	//mail('kjackson@acl.arts.usyd.edu.au', 'HEURIST - bad URLs', $message);
	error_log($message);
}


function error_for_code($code) {
	switch (intval($code)) {
	  case 400: return 'bad request';
	  case 401: return 'requires authorisation';
	  case 402: return 'payment required';
	  case 403: return 'forbidden (requires authorisation?)';
	  case 404: return 'file not found';

	  case 500: return 'internal server error';
	  case 501: return 'server error';
	  case 502: return 'bad gateway';
	  case 503: return 'site unavailable';
	  case 504: return 'gateway timeout';
	}

	return NULL;
}

?>
