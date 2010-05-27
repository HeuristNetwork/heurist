<?php

require_once("xml2json.php");

$feeds = array(
	"user" => "http://picasaweb.google.com/data/feed/api/user/[user]?kind=album",
	"album" => "http://picasaweb.google.com/data/feed/api/user/[user]/albumid/[album]?kind=photo",
	"photo" => "http://picasaweb.google.com/data/feed/api/user/[user]/albumid/[album]/photoid/[photo]?kind=tag"
);

$feed = @$_REQUEST["feed"];
$user = @$_REQUEST["user"];
$album = @$_REQUEST["album"];

$url = @$feeds[$feed];
$url = str_replace("[user]", $user, $url);
$url = str_replace("[album]", $album, $url);

if (! $url) {
	print "{ error: \"invalid feed type specified\" }";
	exit;
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_PROXY, 'www-cache.usyd.edu.au:8080');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

$data = curl_exec($ch);
$error = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);


if ($error) {
	print "{ error: \"$error\" }";
	exit;
}

if ($data[0] != "<") {
	print "{ error: \"$data\" }";
} else {
	print xml2json::transformXmlStringToJson($data);
}

?>
