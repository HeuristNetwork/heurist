/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

javascript:(function(){h='http://heuristscholar.org/h3/';d=document;c=d.contentType;if(c=='text/html'||!c){if(d.getElementById('__heurist_bookmarklet_div'))return Heurist.init();s=d.createElement('script');s.type='text/javascript';s.src=(h+'import/bookmarklet/bookmarkletPopup.php?'+new Date().getTime()).slice(0,-8);d.getElementsByTagName('head')[0].appendChild(s);}else{e=encodeURIComponent;w=open(h+'records/add/addRecordPopup.php?t='+e(d.title)+'&u='+e(location.href));window.setTimeout('w.focus()',200);}})();