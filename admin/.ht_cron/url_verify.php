<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	mysql_connection_db_overwrite(DATABASE);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);	// timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
	if (defined("HEURIST_HTTP_PROXY")) {
		curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
	}
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
	if (defined("HEURIST_HTTP_PROXY")) {
		curl_setopt($ch_ffx, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
	}
	curl_setopt($ch_ffx, CURLOPT_RANGE, '0-1000');


	mysql_query("set @suppress_update_trigger := 1");
	$res = mysql_query('select rec_ID, rec_URL from Records where rec_URL is not null'.
							' and rec_URL != "" and not rec_FlagTemporary'.
							' and rec_URLLastVerified < now() - INTERVAL 1 DAY '.
							(@$_REQUEST['recID']?' and rec_ID = '.$_REQUEST['recID']:'').
							' order by rec_URLLastVerified is not null, rec_URLLastVerified asc');

	$good_bibs = array();
	$bad_bibs = array();

	while ($row = mysql_fetch_assoc($res)) {
		$row['rec_URL'] = str_replace(" ", "+", trim($row['rec_URL']));
		echo $row['rec_id'] ." checking ". $row['rec_url'] . "\n";
		if (preg_match('/^file/', $row['rec_URL'])) continue;

		curl_setopt($ch, CURLOPT_URL, $row['rec_URL']);
		$data = curl_exec($ch);

		preg_match('/^[^\n\r]*/',$data,$matches);
		echo "data = ". $matches[0]. "\n";

		$error = curl_error($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo $error . " ---- code = " . $code . "\n";

		if (curl_error($ch)  ||  $code >= 400) {
			curl_setopt($ch_ffx, CURLOPT_URL, $row['rec_URL']);
			$data = curl_exec($ch_ffx);
			$code = intval(curl_getinfo($ch_ffx, CURLINFO_HTTP_CODE));
			$error = curl_error($ch_ffx);
		preg_match('/^[^\n\r]*/',$data,$matches);
		echo "data2 = ". $matches[0]. "\n";
		echo $error . " ---- code2 = " . $code . "\n";
		}

		// 401 is UNAUTHORISED (don't bother with user authentication -- we can't do it anyway)
		if ($code != 401  &&  $code >= 400) {
			$error = 'URL could not be retrieved ('.$code.')';
		}

		if ($error) {
			$bad_bibs[$row['rec_ID']] = array($row['rec_URL'], $error);

			if ( ($err = error_for_code($code)) )
				mysql_query('update Records set rec_URLErrorMessage="'.$code.': '.addslashes($err).'" where rec_ID = ' . $row['rec_ID']);
		} else {
			array_push($good_bibs, $row['rec_ID']);

			mysql_query('update Records set rec_URLLastVerified=now(), rec_URLErrorMessage=null where rec_ID = ' . $row['rec_ID']);
		}
	}

	$message = 'HEURIST nightly URL check

	There were problems with the URLs for the following bibliographic records:
	';
	foreach ($bad_bibs as $rec_id => $details) {
		$message .= '  ' . $rec_id . ': ' . $details[0] . ' (' . $details[1] . ')' . "\n";
	}
	$message .= "\n\nFind these at\nhttp://" . ($prefix? $prefix.".": "") . HOST_BASE .HEURIST_SITE_PATH."search/search.html?q=ids:" . join(',', array_keys($bad_bibs)) . "\n";
	//TODO   send? and where?  mail('????@??????', 'HEURIST - bad URLs', $message);
//	error_log($message);



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
