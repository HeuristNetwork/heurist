<?php

/*
 * urlJSProxy.php
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

http://heuristscholar.org/h3-ao/applications/dictofsyd/proxyfilter/urlJSProxy.php?db=artem_todelete3&url=http://dictionaryofsydney.org/entry/cockatoo_island
http://heuristscholar.org/h3-ao/applications/dictofsyd/proxyfilter/urlJSProxy.php?db=artem_todelete3&url=organisation/historic_houses_trust_of_new_south_wales&js=sincity.html


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
5) Inject  tag “base” with reference to our server
6) Inject script with declaration of 2 variable baseURL and dbname
7) Saves modified file with prefix “proxyremote_” in HEURIST_FILESTORE_DIR
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
//require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../../common/config/initialise.php");
require_once(dirname(__FILE__).'/../../../records/files/fileUtils.php');

$baseURL = "http://dictionaryofsydney.org/";
$js_file = "maritime.html";

if(array_key_exists('htm', $_REQUEST)){

        $addr = $_REQUEST['htm'];
        $url = $baseURL.$addr;
        $filename = HEURIST_FILESTORE_DIR."proxyremote_".str_replace("/","_",$addr);
        getRemoteFile($filename, null, $url);
        header('Content-Type: text/html');

}else if(array_key_exists('kml', $_REQUEST)){

        $kml = $_REQUEST['kml'];
        $url = $baseURL."kml/full/".$kml;
        $filename = HEURIST_FILESTORE_DIR."proxyremote_".$kml;
        getRemoteFile($filename, null, $url);

        header('Content-Type: application/vnd.google-earth.kml+xml');

}else if(array_key_exists('url', $_REQUEST)){

        $url = $_REQUEST['url'];
        $k = strpos($url, $baseURL);
        if(!($k === 0)){
            $name = preg_replace('/[^a-zA-Z0-9-_\.]/','', $url);
            if(!(strpos($name,".html")==strlen($name)-5)){
                $name = $name.".html";
            }
            $url = $baseURL.$url;
        }else{
            $name = "1.html";
        }
        $filename = HEURIST_FILESTORE_DIR."proxyremote_".$name;

        if(array_key_exists('js', $_REQUEST)){
            $js_file = $_REQUEST['js'];
        }else{
            $js_file = "maritime.html";
        }

        $js_content = file_get_contents($js_file)."</head>";

        getRemoteFile($filename, $js_content, $url);

        header('Content-Type: text/html');
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

    global $js_file;

    if($js_content!=null || !file_exists($filename)){ // || filemtime($filename)<time()-(86400*30))

    $raw = loadRemoteURLContent($url);

    if ($raw) {

            if($js_content!=null){

                //repalce KEY for google map
                $raw = str_replace("ABQIAAAA5wNKmbSIriGRr4NY0snaURTtHC9RsOn6g1vDRMmqV_X8ivHa_xSNBstkFn6GHErY6WRDLHcEp1TxkQ",
                                    "ABQIAAAAGZugEZOePOFa_Kc5QZ0UQRQUeYPJPN0iHdI_mpOIQDTyJGt-ARSOyMjfz0UjulQTRjpuNpjk72vQ3w", $raw);

                //to avoid cross domain issue - load kml via proxy
                $raw = str_replace("../kml/full/", "http://heuristscholar.org/h3-ao/applications/dictofsyd/proxyfilter/urlJSProxy.php?kml=", $raw);

                //to avoid issue with relative path - use global variable baseURL
                $raw = str_replace("../js/popups.js", "http://heuristscholar.org/h3-ao/applications/dictofsyd/proxyfilter/popups.js", $raw);
                $raw = str_replace("../js/tooltip.js", "http://heuristscholar.org/h3-ao/applications/dictofsyd/proxyfilter/tooltip.js", $raw);
                $raw = str_replace("../js/browse.js", "http://heuristscholar.org/h3-ao/applications/dictofsyd/proxyfilter/browse.js", $raw);

                //add base path  $('base').attr('href')
                $raw = str_replace("<head>", "<head><base href=\"http://dictionaryofsydney.org/\">".
                                        "<script>var baseURL = 'http://heuristscholar.org/h3-ao/applications/dictofsyd/proxyfilter/urlJSProxy.php?db=".HEURIST_DBNAME."&js=".$js_file."'; var dbname='".HEURIST_DBNAME."';</script>", $raw);

                //inject main style and javascript
                $raw = str_replace("</head>", $js_content, $raw);

                //remove
                $raw = str_replace("<div id=\"browser\"></div>","",$raw);
            }

            if(file_exists($filename)){
                unlink($filename);
            }
            $fp = fopen($filename, "w");
            //$fp = fopen($filename, "x");
            fwrite($fp, $raw);
            //fflush($fp);    // need to insert this line for proper output when tile is first requestet
            fclose($fp);

    }

    /*if(file_exists($filename)){
        getRemoteFile($mimeType, $filename);
    }*/
    }
}
?>