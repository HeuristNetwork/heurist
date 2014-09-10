<?php

/*
 * urlproxy.php
 * read html and inserts JS on the fly to disable web links
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo

to test
http://dictionaryofsydney.org/natural_feature/cockatoo_island
http://dictionaryofsydney.org/entry/cockatoo_island

http://heuristscholar.org/h3-ao/applications/dictofsyd/urlJSProxy.php?db=artem_todelete3&url=http://dictionaryofsydney.org/entry/cockatoo_island
http://heuristscholar.org/h3-ao/applications/dictofsyd/urlJSProxy.php?db=artem_todelete3&url=organisation/historic_houses_trust_of_new_south_wales&js=sincity.html


Main script is urlJSProxy.php. It obtains the following parameters

db - h3 database name. Actually not required. It need to avoid the exception with missed db parameter

js - name of css/js file that will be injected into redirected page (by default maritime.html)

url - url of DOS page to be proxied

special parameters to avoid cross-domain issues:

htm - used to proxy calls of content for popup.js and tooltip.js

kml -  all ../kml/full/kmlfilename.kml  are replaced with urlJSProxy.php?kml=kmlfilename.kml

How it works.
1) The script downloads the page specified in url parameter.
2) Injects the content of file specified in js parameter into head section
3) Replace Google map key to key for requried domain (TO BE CHANGED to maritime museum!)
4) Replace reference for popup.js, tooltip.js, browse.js to local ones
5) Inject  tag "base" with reference to our server
6) Inject script with declaration of 2 variable baseURL and dbname
7) Saves modified file with prefix "proxyremote_" in HEURIST_FILESTORE_DIR
8) Returns the content of this file to client

In case  proxyremote_ file exists its content is returned to client at once.
In case instead of url parameter the script gets kml or htm parameter steps 2-6 (injections) are omitted.
Configuration for injection css/script file


maritime.html contains css and javascript to be injected into head section of each page.

Need pay attention on 2 hardcoded reference

Line#131. Reference to maritime museum home page. All empty  links will be replaced with this url.
Line#183  Reference to list of allowed pages. This is text file with list of pages links to them will be allowed

In css section it is possible to define another color styles for allowed and disabled links.

Besides the script in this file hides  footer-content and search-bar in any DOS page.

For browse page it calls new function excludeNotAllowed.

 */
require_once('fileUtils.php');

$baseURL = "http://dictionaryofsydney.org/";
$localURL = "http://heuristscholar.org/maritime/";
// THIS NEEDS SETTING TO AN APPROPRIATE PATH
$localFolder = "/var/www/htML/maritime/cache/";

if(array_key_exists('htm', $_REQUEST)){

        $addr = $_REQUEST['htm'];
        $url = $baseURL.$addr;
        $filename = $localFolder.str_replace("/","_",$addr);

//error_log(">>>".$filename."  ".$url);

        getRemoteFile($filename, null, $url);
        header('Content-Type: text/html');

}else if(array_key_exists('kml', $_REQUEST)){

        $kml = $_REQUEST['kml'];
        $url = $baseURL."kml/full/".$kml;
        $filename = $localFolder.$kml;
        getRemoteFile($filename, null, $url);

        header('Content-Type: application/vnd.google-earth.kml+xml');
}else{
    exit;
}

if(file_exists($filename)){
    readfile($filename);
}
exit;
/**
* filename - locale cache file
* js_contet - to inject
* url - to obtain from
*
* @param mixed $filedata
*/
function getRemoteFile($filename, $js_content, $url){

    if(!file_exists($filename)){ // || filemtime($filename)<time()-(86400*30))

        $raw = loadRemoteURLContent($url);

//error_log(">>>".strlen($raw));

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
}
?>