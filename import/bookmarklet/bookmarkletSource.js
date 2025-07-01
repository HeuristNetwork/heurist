/**
* bookmarkletSource.js - bookmarklet source
* 
* The source for the bookmarklet: this file is here to provide a reference
* for the source, but is not referenced by any other file. The code is
* duplicated in: hclient/widgets/profile/profilePreferences.html
*
* @project     Heurist academic knowledge management system
* @package  import\bookmarklet
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1
*/
(function(){h='https://replace to current server url';d=document;c=d.contentType;if(c=='text/html'||!c){if(d.getElementById('__heurist_bookmarklet_div'))return Heurist.init();s=d.createElement('script');s.type='text/javascript';s.src=(h+'import/bookmarklet/bookmarkletPopup.php?'+new Date().getTime()).slice(0,-8);d.getElementsByTagName('head')[0].appendChild(s);}else{e=encodeURIComponent;w=open(h+'hclient/framecontent/recordEdit.php?t='+e(d.title)+'&u='+e(location.href));window.setTimeout('w.focus()',200);}})();