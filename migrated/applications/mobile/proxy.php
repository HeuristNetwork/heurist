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

require_once (dirname(__FILE__) . '/../../../configIni.php'); // read in the configuration file
if (@$httpProxy != '') {
    define('HEURIST_HTTP_PROXY', $httpProxy); //http address:port for proxy request
    if (@$httpProxyAuth != '') {
        define('HEURIST_HTTP_PROXY_AUTH', $httpProxyAuth); // "username:password" for proxy authorization
    }
}

$ttl = 86400; //cache timeout in seconds

$x = intval($_GET['x']);
$y = intval($_GET['y']);
$z = intval($_GET['z']);
if(array_key_exists('r', $_GET)){
    $r = strip_tags($_GET['r']);
}else{
    $r = 'mapnik';
}

if($r=='mapquest'){
    $mimetype = 'image/jpg';
    $ext = "jpg";
}else{
    $mimetype = 'image/png';
    $ext = "png";
}

/*switch ($r)
{
case 'mapnik':
$r = 'mapnik';
break;
case 'osma':
default:
$r = 'osma';
break;
}*/

// refer http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames

//This requires settign to appropraite file path for the server\
// TODO: set this using relative paths
$filename = "/var/www/html/HEURIST/HEURIST_FILESTORE/OpenLayersMapCache/${r}_${z}_${x}_${y}.".$ext;

if(!file_exists($filename) || filemtime($filename)<time()-(86400*30))
{
    //if (!is_file($filename) || filemtime($filename)<time()-(86400*30))

    $server = array();
    switch ($r)
    {
        case 'mapnik':
            $server[] = 'a.tile.openstreetmap.org';
            $server[] = 'b.tile.openstreetmap.org';
            $server[] = 'c.tile.openstreetmap.org';

            $url = 'http://'.$server[array_rand($server)];
            $url .= "/".$z."/".$x."/".$y.".png";
            break;

        case 'ocm':

            $server[] = 'a.tile.opencyclemap.org';
            $server[] = 'b.tile.opencyclemap.org';
            $server[] = 'c.tile.opencyclemap.org';

            $url = 'http://'.$server[array_rand($server)];
            $url .= "/cycle/".$z."/".$x."/".$y.".png";

            break;
        case 'mapquest':

            $server[] = 'otile1.mqcdn.com';
            $server[] = 'otile2.mqcdn.com';
            $server[] = 'otile3.mqcdn.com';
            $server[] = 'otile4.mqcdn.com';

            $url = 'http://'.$server[array_rand($server)];
            $url .= "/tiles/1.0.0/osm/".$z."/".$x."/".$y.".jpg";

            break;
        case 'osma':
        default:
            $server[] = 'a.tah.openstreetmap.org';
            $server[] = 'b.tah.openstreetmap.org';
            $server[] = 'c.tah.openstreetmap.org';

            $url = 'http://'.$server[array_rand($server)].'/Tiles/tile.php';
            $url .= "/".$z."/".$x."/".$y.".png";
            break;
    }


    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    //return the output as a string from curl_exec
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);    //don't include header in output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);    // follow server header redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // don't verify peer cert
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);    // timeout after ten seconds
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);    // no more than 5 redirections

    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );

    if ( defined("HEURIST_HTTP_PROXY") ) {
        curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if(  defined('HEURIST_HTTP_PROXY_AUTH') ) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    $raw = curl_exec($ch);

    $error = curl_error($ch);
    if ($error) {
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
/*****DEBUG****///error_log("$error ($code)" . " url = ". $url);
        curl_close($ch);
        return;
    }else{
        curl_close($ch);

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
/*
$exp_gmt = gmdate("D, d M Y H:i:s", time() + $ttl * 60) ." GMT";
$mod_gmt = gmdate("D, d M Y H:i:s", filemtime($filename)) ." GMT";
header("Expires: " . $exp_gmt);
header("Last-Modified: " . $mod_gmt);
header("Cache-Control: public, max-age=" . $ttl * 60);
// for MSIE 5
header("Cache-Control: pre-check=" . $ttl * 60, FALSE);
*/
if(file_exists($filename)){
    header('Content-Type: '.$mimetype);
    header('access-control-allow-origin: *');
    header('access-control-allow-credentials: true');
    readfile($filename);
}
?>