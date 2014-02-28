<?php
header("Content-type: text/xml");

//error_reporting(E_ALL);
//display_errors(1);

require_once("utilsFile.php");

$xml = new DOMDocument();

if(@$_REQUEST['id']){
	$rec_id = $_REQUEST['id'];

	$filename = "../cache/wml_".$rec_id.".xml";

	if(file_exists($filename)){

		$xml->load($filename);

	}else{
		$url = "http://heuristscholar.org/h3-ij/records/files/downloadFile.php?db=dos_1&ulf_ID=".$rec_id;
		$data = loadRemoteURLContentWithRange($url, null);
		if($data){
			$xml->loadXML($data);
			saveAsFile($data, $filename);
		}
	}

	if($xml){

		$xsl = new DOMDocument;
		$xsl->load(dirname(__FILE__)."/../xsl/wordml2TEI.xsl");

		$proc = new XSLTProcessor();
		$proc->importStyleSheet($xsl);

		$xml->loadXML($proc->transformToXML($xml));
		$xsl->load(dirname(__FILE__)."/../xsl/tei_to_html_basic2.xsl");
		$proc->importStyleSheet($xsl);

		echo $proc->transformToXML($xml);
	}
}
?>