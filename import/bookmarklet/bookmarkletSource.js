/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


javascript:(function(){h='http://heuristscholar.org/h3/';d=document;c=d.contentType;if(c=='text/html'||!c){if(d.getElementById('__heurist_bookmarklet_div'))return Heurist.init();s=d.createElement('script');s.type='text/javascript';s.src=(h+'import/bookmarklet/bookmarkletPopup.php?'+new Date().getTime()).slice(0,-8);d.getElementsByTagName('head')[0].appendChild(s);}else{e=encodeURIComponent;w=open(h+'records/add/addRecordPopup.php?t='+e(d.title)+'&u='+e(location.href));window.setTimeout('w.focus()',200);}})();