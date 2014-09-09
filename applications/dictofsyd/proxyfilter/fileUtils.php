<?php

function loadRemoteURLContent($url, $timeout=10) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return the output as a string from curl_exec
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);	// timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
	if (defined("HEURIST_HTTP_PROXY")) {
		curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
	}

	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);

	$error = curl_error($ch);
	if ($error) {
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		//echo "$error ($code)" . " url = ". $url;
		error_log("$error ($code)" . " url = ". $url);
		curl_close($ch);
		return false;
	} else {
		curl_close($ch);
		return $data;
	}
}

/**
* save remote url as file and returns the size of saved file
*
* @param mixed $url
* @param mixed $filename
*/
function saveURLasFile($url, $filename)
{ //Download file from remote server

	$rawdata = loadRemoteURLContent($url);

    if($rawdata){
		if(file_exists($filename)){
			unlink($filename);
		}
		$fp = fopen($filename,'x');
		fwrite($fp, $rawdata);
		fclose($fp);

		return filesize($filename);
	}else{
		return 0;
	}
}


/**
*
*
* @param mixed $filedata
*/
function downloadViaProxy($filename, $mimeType, $url){

	if(!file_exists($filename)){ // || filemtime($filename)<time()-(86400*30))

		$raw = loadRemoteURLContent($url);

		if ($raw) {

			if(file_exists($filename)){
				unlink($filename);
			}
			$fp = fopen($filename, "w");
			//$fp = fopen($filename, "x");
			fwrite($fp, $raw);
			//fflush($fp);    // need to insert this line for proper output when tile is first requestet
			fclose($fp);

		}
	}

	if(file_exists($filename)){
		downloadFile($mimeType, $filename);
	}
}

/**
* direct file download
*
* @param mixed $mimeType
* @param mixed $filename
*/
function downloadFile($mimeType, $filename){
/*****DEBUG****///error_log(">>>>>".$mimeType."   ".$filename);

		if ($mimeType) {
			header('Content-type: ' .$mimeType);
		}else{
			header('Content-type: binary/download');
		}

		if($mimeType!="video/mp4"){
			header('access-control-allow-origin: *');
			header('access-control-allow-credentials: true');
		}
		readfile($filename);


}

/**
*
*
* @param mixed $dir
* @param mixed $zipfile
*/
function zipDirectory($dir, $zipfile){

		// Adding files to a .zip file, no zip file exists it creates a new ZIP file

		// increase script timeout value
		ini_set('max_execution_time', 5000);

		// create object
		$zip = new ZipArchive();

		// open archive
		if ($zip->open($zipfile, ZIPARCHIVE::CREATE) !== TRUE) {
			return false;
		}

		// initialize an iterator
		// pass it the directory to be processed
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

		// iterate over the directory
		// add each file found to the archive
		foreach ($iterator as $key=>$value) {
			if(! $zip->addFile(realpath($key), $key)){
				return false;
				//or die ("ERROR: Could not add file: $key");
			}
		}

		// close and save archive
		$zip->close();
		return true;

}

?>