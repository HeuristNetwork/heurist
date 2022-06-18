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

/*
parse tempoaral string and returns array of values
CLD - calendar
CL2 

TYP - type: s (simple), f (fuzzy), c (carbon), p (probability range) 

DAT  - date for s,f,c
RNG  - range for f

for carbon
BPD, BCE - 
DEV, DVP, DVN

for probability rnage
PDB - probably begin
PDE - end
TPQ -
TAQ - 

*/
function temporalToArray( $value ){

    if (strpos($value,"|")!==false) {// temporal encoded date

        $tDate = array();
        $props = explode("|",substr_replace($value,"",0,1)); // remove first verticle bar and create array
        foreach ($props as $prop) {//create an assoc array
            list($tag, $val) = explode("=",$prop);
            $tDate[$tag] = $val;
        }

        if (@$tDate["CLD"] && @$tDate["CL2"] && strtolower($tDate["CLD"])!='gregorian') {
            $cld = $tDate["CL2"]." ".$tDate["CLD"];
            if(strpos($cld,'null')!==false) $tDate["CLD"] = substr($cld,4); //some dates were saved in wrong format - fix it
        }        
    
    }  else {
          $tDate = array('TYP'=>'s', 'DAT'=>$value);
    } 
    
    return $tDate; 
}

//
//
//
function temporalToHumanReadableString($value, $showoriginal_temporal=false){

        $value2 = $value;
    
        $tDate = temporalToArray($value);

        $is_greg_or_julian = ((!@$tDate["CLD"]) || 
                            strtolower($tDate["CLD"])=='gregorian' || strtolower(@$tDate["CLD"])=='julian');

		switch ($tDate["TYP"]){
			case 's'://simple
				if (@$tDate['DAT']){
					$value = removeLeadingYearZeroes($tDate['DAT'], $is_greg_or_julian);
				}else{
					$value = "unknown temporal format";
				}
				break;
			case 'f'://fuzzy
				if (@$tDate['DAT']){
					$value = removeLeadingYearZeroes($tDate['DAT'], $is_greg_or_julian) .
								($tDate['RNG']? ' ' . convertDurationToDelta($tDate['RNG'],'±'):"");
				}else{
					$value = "unknown fuzzy temporal format";
				}
				break;
			case 'c'://carbon
				$value = (@$tDate['BPD']? ('' . $tDate['BPD'] . ' BP'):
								(@$tDate['BCE']? '' . $tDate['BCE'] . ' BCE': "") );
				if ($value) {
                    
                    if(@$tDate['DEV']){
                        $value = $value . ' ' . convertDurationToDelta($tDate['DEV'],'±');
                        
                    }else if (@$tDate['DVP']){
						$value = $value. 
                                ' ' . convertDurationToDelta($tDate['DVP'],'+').
								(@$tDate['DVN']?("/ ".convertDurationToDelta($tDate['DVN'],'-')):"");
                    }else if (@$tDate['DVN']){
                            $value = $value.' '.convertDurationToDelta($tDate['DVN'],'-');
                    }
                    
                    
				}else{
					$value = "unknown carbon temporal format";
				}
				break;
			case 'p'://probability range
				if (@$tDate['PDB'] && @$tDate['PDE']){
					$value = "" . removeLeadingYearZeroes($tDate['PDB'], $is_greg_or_julian).
                            " to ". removeLeadingYearZeroes($tDate['PDE'], $is_greg_or_julian);
				}else if (@$tDate['TPQ'] && @$tDate['TAQ']){
					$value = "" . removeLeadingYearZeroes($tDate['TPQ'],$is_greg_or_julian).
                                " to ". removeLeadingYearZeroes($tDate['TAQ'],$is_greg_or_julian);
				}else{
					$value = "unknown probability range temporal format";
				}
				break;
		}
        if(@$tDate["CLD"] && !$is_greg_or_julian){
            $value = $tDate["CLD"].' (Gregorian '.$value.')';
        }            
		if($showoriginal_temporal && strpos($value2,"|")!==false){
			$value .= " [ $value2 ]";
		}

		return $value;
}


//
// $month_day_order     1 - dd/mm,  2 - mm/dd
//
function validateAndConvertToISO($value, $today_date=null, $month_day_order=2, $need_day=true){
          if (strpos($value,"|")!==false) {// temporal encoded date
                return 'Temporal';
          }else{
                if($today_date!=null){
                    $t2 = new DateTime($today_date);
                    
                    $sdate = strtolower(trim($value));
                    if($sdate=='today'){
                        $value = $t2->format('Y-m-d');
                    }else if($sdate=='now'){
                        $value = $t2->format('Y-m-d H:i:s');
                    }else if($sdate=='yesterday'){
                        $t2->modify('-1 day');
                        $value = $t2->format('Y-m-d');//date('Y-m-d',strtotime("-1 days"));
                    }else if($sdate=='tomorrow'){
                        $t2->modify('+1 day');
                        $value = $t2->format('Y-m-d');//date('Y-m-d',strtotime("+1 days"));
                    }
                }
                //$date = parseDateTime($value);
                //return @$date['year'].'-'.@$date['month'].'-'.@$date['day'];
                $res = removeLeadingYearZeroes($value, false, true, $month_day_order, $need_day);
                
                return $res;
          }
}
//
// $is_greg_or_julian true - returns full month names
// $month_day_order   1  dd/mm   2 mm/dd
// $is_strict_iso - adds zeroes, missed days
//   
function removeLeadingYearZeroes($value, $is_greg_or_julian=true, $is_strict_iso=false, $month_day_order=2, $need_day=false){

	//$date = parseDateTime($value);
    // preg_match('/^\d+$/', $value)  && is_int(intval($value))
    if(!$is_strict_iso){
        $need_day = false;
    }
    $origWithoutDays = false;
    
    //trim ? 
    $value = str_replace('?','',$value);

    if(substr_count($value, '-') == 3 && $value[0] == '-'){ // remove padding 0's
        $parts = explode('-', $value);
        if(count($parts) == 4 && empty($parts[0]) && strlen($parts[1]) == 6){
            $new_year_val = str_pad(intval($parts[1]), 4, 0, STR_PAD_LEFT);
            $value = str_replace($parts[1], $new_year_val, $value);
        }
    }

    if( preg_match('/^-?\d+$/', $value) ){ //YEAR only digits with possible minus
        $date = array('year'=>$value);
    }else{
        
        if(strpos($value,'XX')>0){
            $date = null;
        }else{
            
            //replace slashes or dots "/." to dashes "-"
            //reorder month and day
            $value = correctDMYorder($value, $month_day_order);

            try{   
                $origHasSeconds = (substr_count($value,':')>1);
                $origWithoutDays = substr_count($value,'-')==1 || substr_count($value,' ')==1 || substr_count($value,'/')==1;
                
                
                $t2 = new DateTime($value);
                $datestamp = $t2->format('Y-m-d H:i:s');
                $date = date_parse($datestamp);
            } catch (Exception  $e){
                $date = null;
                //print $value.' => NOT SUPPORTED<br>';                            
            }                            
        }
        
    }

	if($date){

		$res = "";
		$isbce = false;

		if(@$date['year'] || $date['year']==0){

			$isbce= ($date['year']<0);

			/*$res = intval($res);
			if($res==0 || $isbce){
				$isbce = true;
				$res = abs($res + 1);
			}*/
            $res = ''.abs($date['year']);
            
            //year must be four digit
            if($is_strict_iso) {
                if($isbce){
                    $res = str_pad($res,6,'0',STR_PAD_LEFT);
                }else if(abs($date['year'])<10000){
                    $res = str_pad($res,4,'0',STR_PAD_LEFT);
                }
            }
		}else if($is_strict_iso){
            return null;
        }
        
        $has_time = (@$date['hour']>0 || @$date['minute']>0 || @$date['second']>0);
        
        if($is_greg_or_julian && !$is_strict_iso){

            $res2 = '';
            if(!$need_day && $origWithoutDays){
                
            }else if(@$date['day']){
                $res2 = $date['day']; 
            }
            if(@$date['month']){
                $res2 = $res2.' '.date('M', mktime(0, 0, 0, $date['month'], 1)); //strtotime($date['month'].'01')); 
            }
            
            $res = trim($res2."  ".$res);

        }else{
        
		    if(@$date['month'] || $has_time){
			    $res = $res.'-'.str_pad($date['month'],2,'0',STR_PAD_LEFT);
                
                if(!$need_day && $origWithoutDays && !$has_time){
                
		        }else if(@$date['day']){ //&& ($need_day || $has_time)
			        $res = $res.'-'.str_pad($date['day'],2,'0',STR_PAD_LEFT);
		        }
            }
        }

        if(true){
		    if($has_time){
			    if(!@$date['hour']) {
					    $date['hour'] = 0;
			    }

			    if($date['hour']>0 || @$date['minute']>0 || @$date['second']>0){
				    $res = $res.' '.str_pad(''.$date['hour'],2,'0',STR_PAD_LEFT);

				    if(!@$date['minute']) { $date['minute'] = 0; }
				    $res = $res.':'.str_pad(''.$date['minute'],2,'0',STR_PAD_LEFT);
			    }
			    if(@$date['second']>0 || $origHasSeconds){
				    $res = $res.':'.str_pad(''.$date['second'],2,'0',STR_PAD_LEFT);
			    }
		    }
        }else{   //debug
            $res = $res.' '.@$date['hour'].':'.@$date['minute'].':'.@$date['second'];
        }


		if($isbce){
            if($is_strict_iso){
                $res = '-'.$res;
            }else{
			    $res = $res.' BCE';
            }
		}
        
		return $res;
	}else{
		return ($is_strict_iso)?null:$value;
	}
}

//
// $month_day_order   true or 0 - returns true or false whether date/month are ambiguate, or month=13 or day=32
//                    2 - mm/dd (default)
//                    1 - dd/mm
// replace slashes or dots "/." to dashes "-"
// reorder month and day
//
//
function correctDMYorder($value, $month_day_order=2){
    
        $check_ambguation = ($month_day_order===0 ||  $month_day_order===true);
    
        $is_dots_slash = false;
        $is_ambguation = false;
        
        //chnage / and . separators to -
        $cnt_dash = substr_count($value,'-');  
        if($cnt_dash==0){
            $cnt_dots = substr_count($value,'.');  //try to convert from format with . fullstops
            $cnt_slash = substr_count($value,'/');  //try to convert from format with / separator
            if( $cnt_slash>0){  // 6/2006  =  1-6-2006
                $value = str_replace('/','-',$value);
            }else if( $cnt_dots>0){  // 4.3.2006  =  4-3-2006
                $value = str_replace('.','-',$value);
            }
            $is_dots_slash = ($cnt_dots>0 || $cnt_slash>0);
        }
        
        if(substr_count($value,'-')==1) { //year and month only
            
            list($m, $y) = explode('-', $value);
            
            //Oct-12
            if((strlen($m)>2 && !is_numeric($m)) || $y>12){
                $value = $y.'-'.$m;
                
                    if($y>22 && $y<100){
                        $value = '19'.$y.'-'.$m;
                    }else  if($y>=0 && $y<22){
                        $value = '20'.$y.'-'.$m;
                    }
                
            }else if( (strlen($y)>2 && !is_numeric($y)) || $y<13){ //09-Nov 09-11
                
                    if($m>22 && $m<100){
                        $value = '19'.$m.'-'.$y;
                    }else  if($m>=0 && $m<22){
                        $value = '20'.$m.'-'.$y;
                    }
            }
            $is_ambguation = ($y<13 && $m<13); //ambiguation
        }
        
        if(substr_count($value,'-')==2 && strpos($value,':')===false) {
            //change d-m-y to y-m-d
            list($m, $d, $y) = explode('-', $value);

            // Mar.2.20  2/Jan/17  for / and . separators is is assumed that year is the last
            // or rare case: year is last  as 10-11-1970
            if( $y>31 || ($is_dots_slash && (!is_numeric($m) || $m<32)) ){
            
                if($y>22 && $y<100) $y = '19'.$y; 
                
                if(strlen($m)>2 || $d>12){ // month is word
                    //$value = $y.'-'.$m.'-'.$d;
                }else if(strlen($d)>2 || $m>12){ //$d is word month 
                    $d2 = $d; $d = $m;  $m = $d2;
                
                    //$value = $y.'-'.$d.'-'.$m;
                }else if($d<13 && $m>12){
                    $d2 = $d; $d = $m;  $m = $d2;
                    
                    //$value = $y.'-'.$d.'-'.$m;
                }else{
                    //$value = $y.'-'.$m.'-'.$d;
                    
                    if($month_day_order==1){  //dd/mm
                        $d2 = $d; $d = $m;  $m = $d2;
                        //$value = $y.'-'.$d.'-'.$m;
                    }else{
                        //$value = $y.'-'.$m.'-'.$d; // mm/dd
                    }
                    
                    $is_ambguation = ($m<13 && $d<13); //day-month ambiguation
                }
                
                $value = $y.'-'.$m.'-'.$d; // mm/dd
            }else{
                list($y, $m, $d) = explode('-', $value);
            }
            
            if($check_ambguation){
                if($m==13){
                    $is_ambguation = true;    
                }else{
                    $days_req = cal_days_in_month(CAL_GREGORIAN, intval($m), intval($y));        
                    if($days_req+1==$d || $days_req+2==$d){
                         $is_ambguation = true;
                    }
                } 
            }
        }

        return ($check_ambguation)?$is_ambguation :$value;    
}

//
//
//
function parseDateTime($value) {
	$isbce= (strpos($value,'-')===0);
	if($isbce){
		$value = substr($value,1);
	}
    if(strpos($value,'P')===0){
        $value = substr($value,1);
    }

    
    $res = array();
    
    /*preg_match('/P(\d+)Y/', $value, $matches);
    if(count($matches)>0){
        return array('year'=>$matches[1]);
    }*/
	//if (preg_match('/^P([^T]*)T?(.*)$/', $value, $matches)) { // valid ISO Duration split into date and time

	if($value && strlen($value)>10 && strpos($value,'T')>0){
		$matches = explode('T',$value);
	}else if($value && strlen($value)>10 && strpos($value,' ')>0){
		$matches = explode(' ',$value);
	}else{
		$matches = array();
		$matches[0] = $value;
	}

		$date = @$matches[0];
		$time = @$matches[1];

		if ($date) {
			if (preg_match('/[YMD]/',$date)){ //char separated version 6Y5M8D
				preg_match('/(?:(\d+)Y)?(?:(\d|0\d|1[012])M)?(?:(0?[1-9]|[12]\d|3[01])D)?/',$date,$matches);
			}else{ //delimited version  0004-12-06
				preg_match('/^(?:(\d\d\d\d)[-\/]?)?(?:(1[012]|0[23]|[23](?!\d)|0?1(?!\d)|0?[4-9](?!\d))[-\/]?)?(?:([12]\d|3[01]|0?[1-9]))?\s*$/',$date,$matches);
			}
			if (@$matches[1]) $res['year'] = intval($matches[1])*($isbce?-1:1);
			if (@$matches[2]) $res['month'] = intval($matches[2]);
			if (@$matches[3]) $res['day'] = intval($matches[3]);
		}
		if ($time) {
			if (preg_match('/[HMS]/',$time)){ //char separated version 6H5M8S
				preg_match('/(?:(0?[1-9]|1\d|2[0-3])H)?(?:(0?[1-9]|[0-5]\d)M)?(?:(0?[1-9]|[0-5]\d)S)?/',$time,$matches);
			}else{ //delimited version  23:59:59
				//preg_match('/(?:(0?[1-9]|1\d|2[0-3])[:\.])?(?:(0?[1-9]|[0-5]\d)[:\.])?(?:(0?[1-9]|[0-5]\d))?/',$time,$matches);

				//preg_match('/^(?:(?:([01]?\d|2[0-3]):)?([0-5]?\d):)?([0-5]?\d)$/',$time,$matches);

				$matches = explode(":",$time);
				$matches = array_merge(array($time), $matches);
			}

			$res['hour'] = (@$matches[1])? intval($matches[1]):0;
			$res['minute'] = (@$matches[2])?intval($matches[2]):0;
			$res['second'] = (@$matches[3])?intval($matches[3]):0;
		}

		return count($res)?$res:null;
}

//
//
//
function convertDurationToDelta($value, $prefix = "") {
	$date = parseDateTime($value);
	if ($date) { // valid ISO Duration split into date and time

		return (@$date['year'] ? ("$prefix".$date['year'].' years') :
				(@$date['month'] ? ("$prefix".$date['month'].' months') :
				(@$date['day'] ? ("$prefix".$date['day'].' days') :
				(@$date['hour'] ? ("$prefix".$date['hour']) :
				(@$date['minute'] ? ("$prefix".$date['minute']) :
				(@$date['second'] ? ("$prefix".$date['second']) :
				"") )))));

	} else {
        return '';
    }
}

/**
* simplify temporal to simple date (for kml export)
*
* @param mixed $value
*/
function temporalToSimple($value)
{
        $tDate = temporalToArray($value);

		switch ($tDate["TYP"]){
			case 's'://simple
			case 'f'://fuzzy
				if (@$tDate['DAT']){
					$value = $tDate['DAT'];
				}else{
					$value = null;
				}
				break;
			case 'c'://carbon
				$value = null;
				break;
			case 'p'://probability range
				if (@$tDate['PDB'] && @$tDate['PDE']){
					$value = "".$tDate['PDB'];
				}else if (@$tDate['TPQ'] && @$tDate['TAQ']){
					$value = "".$tDate['TPQ'];
				}else{
					$value = null;
				}
				break;
		}
		
		//ART - @todo rewrite - it is ugly!!!
		if($value && strlen($value)>10 && strpos($value," ")==10){
            
                $is_greg_or_julian = ((!@$tDate["CLD"]) || 
                            strtolower($tDate["CLD"])=='gregorian' || strtolower(@$tDate["CLD"])=='julian');
            
                $value = removeLeadingYearZeroes($value, $is_greg_or_julian, true);
				$value = substr_replace($value,"T",10,1);
		}
		return $value;
}

//
//
//
function temporalToSimpleRange($value){
    
    $tDate = temporalToArray($value);

    $is_greg_or_julian = ((!@$tDate["CLD"]) || 
                            strtolower($tDate["CLD"])=='gregorian' || strtolower(@$tDate["CLD"])=='julian');
    $res = null;
    
    switch ($tDate["TYP"]){
            case 's'://simple
            case 'f'://fuzzy
                if (@$tDate['DAT']){
                    $res = removeLeadingYearZeroes($tDate['DAT'], $is_greg_or_julian, true);
                    if($res!=null) $res = array($res,'','','','');
                }
                break;
            case 'c'://carbon
                $res = null;
                break;
            case 'p'://probability range
                $res = array('','','','','');            
                $is_valid = false;
                if (@$tDate['PDB']){
                    $res[0] = removeLeadingYearZeroes($tDate['PDB'], $is_greg_or_julian, true); 
                    $is_valid = $is_valid || ($res[0]!=null && $res[0]!='');
                }
                if (@$tDate['TPQ']){
                    $res[1] = removeLeadingYearZeroes($tDate['TPQ'], $is_greg_or_julian, true); 
                }
                if (@$tDate['TAQ']){
                    $res[2] = removeLeadingYearZeroes($tDate['TAQ'], $is_greg_or_julian, true); 
                }
                if (@$tDate['PDE']){
                    $res[3] = removeLeadingYearZeroes($tDate['PDE'], $is_greg_or_julian, true); 
                    $is_valid = $is_valid || ($res[3]!=null && $res[3]!='');
                }
                
                if($res[0]=='' && $res[1]!='' && $res[1]!=null) $res[0]=$res[1];    
                if($res[3]=='' && $res[2]!='' && $res[2]!=null) $res[3]=$res[2];    
                
                
                break;
                if(!$is_valid) $res = null;
    }
    
    return $res;  
}

?>
