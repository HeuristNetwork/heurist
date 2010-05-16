<?php


if (0) {

function levenshtein_delta($str1, $str2) {
        /* format $str1 with <span class=delta>..</span> tags to indicate inserted / replaced letters
         * to do a minimal transform (as computed by the Levenshtein algorithm) of $str1 with respect to $str2
         */
        $m = strlen($str1);  $n = strlen($str2);
        $d = array(($m+1)*($n+1));

        for ($i=0; $i <= $m; ++$i) $d[$i] = $i;
        for ($j=1; $j <= $n; ++$j) $d[($m+1)*$j] = $j;

        for ($i=1; $i <= $m; ++$i) {
                for ($j=1; $j <= $n; ++$j) {
                        $cost = ($str1[$i-1] == $str2[$j-1])? 0 : 1;

                        $d[$i + ($m+1)*$j] = min($d[($m+1)*$j + ($i-1)] + 1,
                                                 $d[($m+1)*($j-1) + $i] + 1,
                                                 $d[($m+1)*($j-1) + ($i-1)] + $cost);
                }
        }


for ($j=0; $j <= $n; ++$j) {
	for ($i=0; $i <= $m; ++$i) {
		printf("%2d ", $d[$i + ($m+1)*$j]);
	}
	print "\n";
}
print "\n";

/*

        $outstr = '';
        $i = $m; $j = $n;
        while ($i > 0  ||  $j > 0) {
error_log('[' . $d[($m+1)*$j + $i] . '] ' .  $d[($m+1)*($j) + ($i-1)] . ' ' .  $d[($m+1)*($j-1) + ($i-1)]  . ' ' . $d[($m+1)*($j-1) + $i] );

                if ($d[($m+1)*$j + ($i-1)] == $d[($m+1)*$j + $i]-1) {
error_log("          ins $i $j");
                        // letter has been inserted
                        $outstr = '<span class=delta>'.$str1[$i-1].'</span>' . $outstr;
                        --$i;
                } else if ($d[($m+1)*($j-1) + ($i-1)] == $d[($m+1)*$j + $i]) {
                        // letter is the same
error_log("          same $i $j");
                        $outstr = $str1[$i-1] . $outstr;
                        --$i; --$j;
                } else if ($d[($m+1)*($j-1) + ($i-1)] == $d[($m+1)*$j + $i]-1) {
error_log("          subst $i $j");
                        // letter has been substituted
                        if ($i > 0) $outstr = '<span class=delta>'.$str1[$i-1].'</span>' . $outstr;
                        --$i; --$j;
                } else { // if ($d[($m+1)*($j-1) + $i] == $d[($m+1)*$j + $i]-1) {
error_log("          del $i $j");
                        // letter has been deleted
                        --$j;
                }
        }

        return $outstr;


*/


	$outstr = '';
	$i = $m; $j = $n;
	$preferred_mode = "";
	while ($i > 0  ||  $j > 0) {
		$mode = 'unknown';

		$o = $d[($m+1)*$j + $i];
		$nw = $d[($m+1)*($j-1) + ($i-1)];
		$n = $d[($m+1)*($j-1) + $i];
		$w = $d[($m+1)*$j + ($i-1)];

		if ($preferred_mode == '' && $n == $nw) $preferred_mode = 'i';
		else if ($preferred_mode == '' && $w == $nw) $preferred_mode = 'd';

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

		if ($mode == 'i') {
			// letter has been inserted
			$outstr = '<span class=delta>'.$str1[$i-1].'</span>' . $outstr;
			$first_change = $i;
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
			$first_change = $i;
			++$change_count;
			if ($i > 0) $outstr = '<span class=delta>'.$str1[$i-1].'</span>' . $outstr;
			--$i; --$j;
		} else { // $mode == 'd'
			// letter has been deleted
			$first_change = $i;
			++$change_count;
			--$j;
		}

		$preferred_mode = $mode;	// hysteresis!
	}
	return $outstr;
}

} else {
	require_once('lev-delta.php');
}



print levenshtein_delta("Higham,C, Bayard, D. 1993 (Journal of Archaeological Science 20)", "Higham,C, Christie, J. W. 1990 (Bulletin of the School of Oriental and African Studies- University of London 53)");
print "\n";
print "---\n";

// print levenshtein_delta("The Archaeology of Mainland Southeast-Asia - from 10,000 Bc to the Fall of Angkor - Higham,C, Decasparis, J. G. 1990 (Bijdragen Tot De Taal- Land- En Volkenkunde 146)", "The Archaeology of Mainland Southeast-Asia - from 10,000 Bc to the Fall of Angkor - Higham,C, Bayard, D. 1993 (Journal of Archaeological Science 20)");

?>
