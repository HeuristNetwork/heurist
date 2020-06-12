<?php

/*
* Copyright (C) 2005-2020 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


function levenshtein_delta($str1, $str2) {
        /* format $str1 with <span class=delta>..</span> tags to indicate inserted / replaced letters
         * to do a minimal transform (as computed by the Levenshtein algorithm) of $str1 with respect to $str2
         */
        $m = strlen($str1);  $n = strlen($str2);
        $d = array(($m+1)*($n+1));

        for ($i=0; $i <= $m; ++$i) $d[$i] = $i;
        for ($j=1; $j <= $n; ++$j) $d[($m+1)*$j] = $j;

	$lstr1 = strtolower($str1);
	$lstr2 = strtolower($str2);
        for ($i=1; $i <= $m; ++$i) {
                for ($j=1; $j <= $n; ++$j) {
                        $cost = ($lstr1[$i-1] == $lstr2[$j-1])? 0 : 1;

                        $d[$i + ($m+1)*$j] = min($d[($m+1)*$j + ($i-1)] + 1,
                                                 $d[($m+1)*($j-1) + $i] + 1,
                                                 $d[($m+1)*($j-1) + ($i-1)] + $cost);
                }
        }


	$outstr = '';
	$i = $m; $j = $n;
	$preferred_mode = "";
	$change_count = 0;
	$last_change = 0;
	while ($i > 0  ||  $j > 0) {
		$mode = 'unknown';

		$o = @$d[($m+1)*$j + $i];
		$nw = @$d[($m+1)*($j-1) + ($i-1)];
		$n = @$d[($m+1)*($j-1) + $i];
		$w = @$d[($m+1)*$j + ($i-1)];

		if ($preferred_mode == '' && $n == $nw) $preferred_mode = 'i';
		else if ($preferred_mode == '' && $w == $nw) $preferred_mode = 'd';

/*
		if ($preferred_mode == '' && $j >= 1 && $i >= 1 &&  $nw <= $n  &&  $nw <= $w) $mode = '';
		else if ($preferred_mode == 'd'  && $i >= 1 && $i >= 1 && $n < $o) $mode = 'd';
		else if ($preferred_mode == 'i'  &&   $i >= 1  &&  $w < $o) $mode = 'i';
		else if ($preferred_mode == 's'  && $j >= 1 && $i >= 1 &&  $nw < $o) $mode = 's';

		if ($mode == 'unknown') {
			if ($i >= 1 && $i >= 1 && $n < $o) $mode = 'd';
			else if ($i >= 1  &&  $w < $o) $mode = 'i';
			else if ($j >= 1 && $i >= 1 &&  $nw <= $n  &&  $nw <= $w) $mode = '';
			else $mode = 's';
		}
*/
		if ($preferred_mode == '' && $j >= 1 && $i >= 1 &&  $nw <= $n  &&  $nw <= $w) $mode = '';
		else if ($preferred_mode == 'i'  &&   $i >= 1  &&  $w < $o) $mode = 'i';
		else if ($preferred_mode == 'd'  && $i >= 1 && $i >= 1 && $n < $o) $mode = 'd';
		else if ($preferred_mode == 's'  && $j >= 1 && $i >= 1 &&  $nw < $o) $mode = 's';

		if ($mode == 'unknown') {
			if ($i >= 1  &&  $w < $o) $mode = 'i';
			else if ($i >= 1 && $i >= 1 && $n < $o) $mode = 'd';
			else if ($j >= 1 && $i >= 1 &&  $nw <= $n  &&  $nw <= $w) $mode = '';
			else $mode = 's';
		}


		if ($mode != ''  &&  ! $last_change) $last_change = $i;

		if ($mode == 'i') {
			// letter has been inserted
			$outstr = '<span class=delta>'.$str1[$i-1].'</span>' . $outstr;
			$first_change = $i-1;
			++$change_count;
			--$i;
			$last_changed = true;
		} else if ($mode == '') {
			// letter is the same
			$outstr = $str1[$i-1] . $outstr;
			--$i; --$j;
			$last_changed = false;
		} else if ($mode == 's') {
			// letter has been substituted
			$first_change = $i-1;
			++$change_count;
			if ($i > 0) $outstr = '<span class=delta>'.$str1[$i-1].'</span>' . $outstr;
			--$i; --$j;
		} else { // $mode == 'd'
			// letter has been deleted
			$first_change = $i-1;
			++$change_count;
			--$j;
		}

		$preferred_mode = 's';	// hysteresis!
	}

	$outstr = str_replace('</span><span class=delta>', '', $outstr);
	if (substr_count($outstr, '<span class=delta>') > 5) $outstr = substr($str1, 0, $first_change) . '<span class=delta>'.substr($str1, $first_change, $last_change-$first_change).'</span>' . substr($str1, $last_change);

	if (0  &&  $change_count > 10) $outstr = substr($str1, 0, $first_change) . '<span class=delta>'.substr($str1, $first_change).'</span>';

        return $outstr;
}

?>
