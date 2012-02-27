/**
 * initViewer.js
 *
 * Popup dialogue to define URLinclude field type in editRecord
 * URL is either to external resource or link file uploaded to heurist
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 *
 * this file requires
 * ../../common/js/utilsUI.js
 * ../../external/js/simple_js_viewer/script/core/Simple_Viewer_beta_1.1.js
 */
var viewerObject,
	Hul = top.HEURIST.util;

/*
// Parse a URL and return its components
//
// version: 901.2514
// discuss at: http://phpjs.org/functions/parse_url
// +      original by: Steven Levithan (http://blog.stevenlevithan.com)
// + reimplemented by: Brett Zamir
// %          note: Based on http://stevenlevithan.com/demo/parseuri/js/assets/parseuri.js
// %          note: blog post at http://blog.stevenlevithan.com/archives/parseuri
// %          note: demo at http://stevenlevithan.com/demo/parseuri/js/assets/parseuri.js
// %          note: Does not replace invaild characters with '_' as in PHP, nor does it return false with
// %          note: a seriously malformed URL.
// %          note: Besides function name, is the same as parseUri besides the commented out portion
// %          note: and the additional section following, as well as our allowing an extra slash after
// %          note: the scheme/protocol (to allow file:/// as in PHP)
// *     example 1: parse_url('http://username:password@hostname/path?arg=value#anchor');
// *     returns 1: scheme: 'http', host: 'hostname', user: 'username', pass: 'password', path: '/path', query: 'arg=value',fragment: 'anchor'
*/
function parse_url(str, component)
{
		var  o   = {
strictMode: false,
key: ["source","protocol","authority","userInfo","user","password","host","port","relative","path","directory","file","query","anchor"],
q:   {
	name:   "queryKey",
	parser: /(?:^|&)([^&=]*)=?([^&]*)/g
	},
			parser: {
strict: /^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/,
			loose:  /^(?:(?![^:@]+:[^:@\/]*@)([^:\/?#.]+):)?(?:\/\/\/?)?((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?)(((\/(?:[^?#](?![^?#\/]*\.[^?#\/.]+(?:[?#]|$)))*\/?)?([^?#\/]*))(?:\?([^#]*))?(?:#(.*))?)/ // Added one optional slash to post-protocol to catch file:/// (should restrict this)
		    }
		};

		var m   = o.parser[o.strictMode ? "strict" : "loose"].exec(str),
			uri = {},
			i   = 14;
		while (i--) uri[o.key[i]] = m[i] || "";
    // Uncomment the following to use the original more detailed (non-PHP) script
    /*
        uri[o.q.name] = {};
        uri[o.key[12]].replace(o.q.parser, function ($0, $1, $2) {
        if ($1) uri[o.q.name][$1] = $2;
        });
        return uri;
    */

			switch (component) {
				case 'PHP_URL_SCHEME':
					return uri.protocol;
				case 'PHP_URL_HOST':
					return uri.host;
				case 'PHP_URL_PORT':
					return uri.port;
				case 'PHP_URL_USER':
					return uri.user;
				case 'PHP_URL_PASS':
					return uri.password;
				case 'PHP_URL_PATH':
					return uri.path;
				case 'PHP_URL_QUERY':
					return uri.query;
				case 'PHP_URL_FRAGMENT':
					return uri.anchor;
				default:
					var retArr = {};
					if (uri.protocol !== '') retArr.scheme=uri.protocol;
					if (uri.host !== '') retArr.host=uri.host;
					if (uri.port !== '') retArr.port=uri.port;
					if (uri.user !== '') retArr.user=uri.user;
					if (uri.password !== '') retArr.pass=uri.password;
					if (uri.path !== '') retArr.path=uri.path;
					if (uri.query !== '') retArr.query=uri.query;
					if (uri.anchor !== '') retArr.fragment=uri.anchor;
					return retArr;
			}
}


/**
* try to detect the source (service) and type of file/media content
*
* returns object with 2 properties
*/
function detectSourceAndType(link, extension){

	var source = 'arbitrary';
	var type = 'unknown';

	if(link){

	//1. detect source
	if(link.indexOf('http://heuristscholar.org')==0){
		source = 'heurist';
	}else if(link.indexOf('http://www.flickr.com')==0){
		source = 'flickr';
		type = 'image';
	}else if(link.indexOf('http://www.panoramio.com/')==0){
		source = 'panoramio';
		type = 'image';
	}else if(link.match('http://(www.)?locr\.de|locr\.com')){
		source = 'locr';
		type = 'image';
	//}else if(link.indexOf('http://www.youtube.com/')==0 || link.indexOf('http://youtu.be/')==0){
	}else if(link.match('http://(www.)?youtube|youtu\.be')){
		source = 'youtube';
		type = 'video';
	}

	//try to detect type by extension and protocol
	if(type==='unknown'){

		if ( Hul.isnull(extension ) ){ //
			//get extension from url - unreliable
			var trueFileName = parse_url(link).path;

			extension = (/[.]/.exec(trueFileName)) ? /[^.]+$/.exec(trueFileName) : undefined;
			if(extension){
				extension = extension[0];
			}
			//alternative extension = filename.split('.').pop();
			//@TODO if extension is still undefined - try to load via server and detectg mimeType
		}
		if ( !Hul.isnull(extension ) ){ //
			extension = extension.toLowerCase();
		}


		if(extension==="jpg" || extension==="jpeg" || extension==="png" || extension==="gif"){
			type = 'image';
		}else if(extension==="mp4" || extension==="mov" || extension==="avi"){
			type = 'video';
		}else if(extension==="mp3" || extension==="wav"){
			type = 'audio';
		}else if(extension==="html" || extension==="htm" || extension==="txt"){
			type = 'text/htnl';
		}else if(extension==="pdf" || extension==="doc" || extension==="xls"){
			type = 'document';
		}else if(extension==="swf"){
			type = 'flash';
		}
	}
	}
	return {source:source, type:type, extension:extension};
}

function linkifyYouTubeURLs(text) {
    /* Commented regex (in PHP syntax)
    $text = preg_replace('%
        # Match any youtube URL in the wild.
        (?:https?://)?   # Optional scheme. Either http or https
        (?:www\.)?       # Optional www subdomain
        (?:              # Group host alternatives
          youtu\.be/     # Either youtu.be,
        | youtube\.com   # or youtube.com
          (?:            # Group path alternatives
            /embed/      # Either /embed/
          | /v/          # or /v/
          | /watch\?v=   # or /watch\?v=
          )              # End path alternatives.
        )                # End host alternatives.
        ([\w\-]{10,12})  # $1: Allow 10-12 for 11 char youtube id.
        \b               # Anchor end to word boundary.
        [?=&\w]*         # Consume any URL (query) remainder.
        (?!              # But don\'t match URLs already linked.
          [\'"][^<>]*>   # Not inside a start tag,
        | </a>           # or <a> element text contents.
        )                # End negative lookahead assertion.
        %ix',
        '<a href="http://www.youtube.com/watch?v=$1">YouTube link: $1</a>',
        $text);
    */
    // Here is the same regex in JavaScript literal regexp syntax:
    return text.replace(
        /(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com(?:\/embed\/|\/v\/|\/watch\?v=))([\w\-]{10,12})\b[?=&\w]*(?!['"][^<>]*>|<\/a>)/ig,
        '<iframe width="420" height="345" src="http://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>');
        //'<a href="http://www.youtube.com/watch?v=$1">YouTube link: $1</a>');
}


//
function clearViewer(container){
	if(container){
 		var cell = container;
		if ( cell.hasChildNodes() )
		{
    		while ( cell.childNodes.length >= 1 )
    		{
     					cell.removeChild( cell.firstChild );
    		}
		}
		cell.innerHTML = "";
	}
}

//
function showViewer(container, url_and_cfg){

	   var acfg = url_and_cfg.split('|');
	   if(Hul.isnull(acfg) || acfg.length<1){
	   	   return;
	   }

	   var sUrl = acfg[0];
	   var sType;
	   var sSource;
	   if(acfg.length<3){
	   		var oType = detectSourceAndType(sUrl, null);
	   		sType = oType.type;
	   		sSource = oType.source;
	   }else{
	   		sSource = acfg[1];
	   		sType = acfg[2];
	   }

	   clearViewer(container);

 		if(sType === "image"){

 			viewerObject = null;
			viewer.toolbarImages = top.HEURIST.baseURL+"images/toolbar";
			viewer.onload =  viewer.toolbar;

			viewerObject = new viewer({
					parent: container,
					imageSource: sUrl,
					frame: ['100%','100%']
			});

		}else if (sType === "video"){

			if(sSource === "youtube"){
				//var id = /^.*((youtu.be\/)|(v\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/.exec(curr_link);
				container.innerHTML = linkifyYouTubeURLs(sUrl);
			}
		}
}