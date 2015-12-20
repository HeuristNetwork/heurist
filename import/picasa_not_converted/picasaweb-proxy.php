<?php

/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


require_once(dirname(__FILE__)."/xml2json.php");

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
