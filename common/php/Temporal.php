<?php

/*
 * Temporal.php
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

function temporalToHumanReadableString($value, $showoriginal_temporal=false){

		if (strpos($value,"|")!==false) {// temporal encoded date
			$value2 = $value;
			$tDate = array();
			$props = explode("|",substr_replace($value2,"",0,1)); // remove first verticle bar and create array
			foreach ($props as $prop) {//create an assoc array
				list($tag, $val) = explode("=",$prop);
				$tDate[$tag] = $val;
			}
			switch ($tDate["TYP"]){
				case 's'://simple
					if (@$tDate['DAT']){
						$value = $tDate['DAT'];
					}else{
						$value = "unknown temporal format";
					}
					break;
				case 'f'://fuzzy
					if (@$tDate['DAT']){
						$value = $tDate['DAT'] .
									($tDate['RNG']? ' ' . convertDurationToDelta($tDate['RNG'],'±'):"");
					}else{
						$value = "unknown fuzzy temporal format";
					}
					break;
				case 'c'://carbon
					$value = (@$tDate['BPD']? '' . $tDate['BPD'] . ' BPD':
									@$tDate['BCE']? '' . $tDate['BCE'] . ' BCE': "");
					if ($value) {
						$value = $value.(@$tDate['DEV']? ' ' . convertDurationToDelta($tDate['DEV'],'±'):
											@$tDate['DVP']? ' ' . convertDurationToDelta($tDate['DVP'],'+').
													(@$tDate['DVN']?"/ ".convertDurationToDelta($tDate['DVN'],'-'):""):
											@$tDate['DVN']?" ".convertDurationToDelta($tDate['DVN'],'-'):"");
					}else{
						$value = "unknown carbon temporal format";
					}
					break;
				case 'p'://probability range
					if (@$tDate['PDB'] && @$tDate['PDE']){
						$value = "" . $tDate['PDB']." - ". $tDate['PDE'];
					}else if (@$tDate['TPQ'] && @$tDate['TAQ']){
						$value = "" . $tDate['TPQ']." - ". $tDate['TAQ'];
					}else{
						$value = "unknown probability range temporal format";
					}
					break;
			}
			if($showoriginal_temporal){
				$value .= " [ $value2 ]";
			}
		}

		return $value;
}

//
//
//
function convertDurationToDelta($value,$prefix = "") {
	if (preg_match('/^P([^T]*)T?(.*)$/', $value, $matches)) { // valid ISO Duration split into date and time
		$date = @$matches[1];
		$time = @$matches[2];
		if ($date) {
			if (preg_match('/[YMD]/',$date)){ //char separated version 6Y5M8D
				preg_match('/(?:(\d+)Y)?(?:(\d|0\d|1[012])M)?(?:(0?[1-9]|[12]\d|3[01])D)?/',$date,$matches);
			}else{ //delimited version  0004-12-06
				preg_match('/^(?:(\d\d\d\d)[-\/]?)?(?:(1[012]|0[23]|[23](?!\d)|0?1(?!\d)|0?[4-9](?!\d))[-\/]?)?(?:([12]\d|3[01]|0?[1-9]))?\s*$/',$date,$matches);
			}
			if (@$matches[1]) $year = intval($matches[1]);
			if (@$matches[2]) $month = intval($matches[2]);
			if (@$matches[3]) $day = intval($matches[3]);
		}
		if ($time) {
			if (preg_match('/[HMS]/',$time)){ //char separated version 6H5M8S
				preg_match('/(?:(0?[1-9]|1\d|2[0-3])H)?(?:(0?[1-9]|[0-5]\d)M)?(?:(0?[1-9]|[0-5]\d)S)?/',$time,$matches);
			}else{ //delimited version  23:59:59
				preg_match('/(?:(0?[1-9]|1\d|2[0-3])[:\.])?(?:(0?[1-9]|[0-5]\d)[:\.])?(?:(0?[1-9]|[0-5]\d))?/',$time,$matches);
			}
			if (@$matches[1]) $hour = intval($matches[1]);
			if (@$matches[2]) $minute = intval($matches[2]);
			if (@$matches[3]) $second = intval($matches[3]);
		}
		return (@$year ? "$prefix" + $year + "year(s)" :
				@$month ? "$prefix" + $month + "month(s)" :
				@$day ? "$prefix" + $day + "day(s)" :
				@$hour ? "$prefix" + $hour + "hour(s)" :
				@$minute ? "$prefix" + $minute + "minute(s)" :
				@$second ? "$prefix" + $second + "second(s)" :
				"");

	}
}

?>
