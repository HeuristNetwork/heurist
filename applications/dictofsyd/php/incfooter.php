<?php

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
* Footer part for all pages
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
?>
		<div class="clearfix"></div>
		<div id="container-bottom"></div>
</div> <!-- end of container -->
</div> <!-- end of middle -->
<div id="footer">
<div id="footer-content">
<ul id="footer-left-col">
<li class="no-bullet">
<a href="<?=($urlbase==''?'./':$urlbase)?>">Home</a>
</li>
<li>
<a href="http://trust.dictionaryofsydney.org/about-the-trust/">About</a>
</li>
<li>
<a href="http://trust.dictionaryofsydney.org/participate/copyright/">Copyright</a>
</li>
<li>
<a href="http://trust.dictionaryofsydney.org/participate/contact-form/">Contact</a>
</li>
<li>
<a title="Increase font size" class="increasefont" href="#">Font +</a> <a title="Decrease font size" class="decreasefont" href="#">-</a>
</li>
</ul>
<ul id="footer-right-col">
<li class="no-bullet">
<a href="http://www.everydayhero.com.au/dictionaryofsydney">Donate</a>
</li>
<li>
<a href="http://trust.dictionaryofsydney.org/participate/contact-form/">Contribute</a>
</li>
<li>
<a class="addthis_button" href="http://www.addthis.com/bookmark.php?v=250&amp;pub=dictionaryofsydney">Share</a>
</li>
</ul>
</div>
</div>
<div id="previews"></div>
<div style="background-color: #fff;">
<?php
if( (!$is_generation) && isset($starttime)){
	$mtime = explode(' ', microtime());
	$totaltime = $mtime[0] + $mtime[1] - $starttime;
	printf($query_times.' Page generated in %.3f seconds.', $totaltime);
}
?>
</div>
<!--
<script src="http://www.google-analytics.com/ga.js" type="text/javascript"></script><script type="text/javascript">
				try {
					var pageTracker = _gat._getTracker("UA-11403264-1");
					pageTracker._trackPageview();
				} catch(err) {}
			</script><script src="http://s7.addthis.com/js/250/addthis_widget.js#pub=dictionaryofsydney" type="text/javascript"></script><script type="text/javascript">
				var addthis_config = {
					services_exclude: 'myaol'
				}
			</script>
-->
</body>
</html>
