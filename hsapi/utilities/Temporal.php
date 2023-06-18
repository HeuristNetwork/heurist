<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Brief description of file
* 
* Usage:
* for export: recordsExport.php (json)  export/xml/kml.php
*                   @todo flathml.php  outputTemporalDetail (xml),  recordsExportCSV.php (csv)
* 
* for import:     importParser.php  prepareDateField   @todo validate real dates only
*                 syncZotero.php
* for validation: listDatabaseErrors.php               @todo validate real dates only     
*                 compose_sql.php
* 
*  - converts temporal string to human readable
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/*
* 1. Public methods
*       setValue
*       getValue 
*       isValid
*       isValidSimple 
*       getMinMax - pairs of min max values in decimal format to store in recDetailsDateIndex
*       calcMinMax - calculates and returns min max dates as iso strings
* 2. Static parse and formatting functions
*       _getLimitDate  - find earliest and latest dates for timespan
*       _parseTemporal - parses json or plain string to array of values
*       _datePrepare     - Validates and sanitizes string date value - returns date array 
*       _dateDecimal    - Converts string date yyyy-mm-dd  to decimal yyyy.mmdd 

*       dateToISO       - Converts date array to ISO8601 string
*       dateToString    - Converts to human readable string
*       correctDMYorder - Replaces slashes or dots "/." to dashes "-", Reorders month and day
*       mergeTemporals  - merge two temporals (it lost fields comment,determination,calendar) 
*       toHumanReadable
*
* 3. Export function
*       toKML           - xml snippet for kml
*       toJSON          - geojson-h (to store in database recDetails)
*       toPlain         - old temporal plain string 
*       toReadable      - human readble
*       toReadableExt   - human readble extended
*/
class Temporal {

    protected $tDate = null;

    private $dictDetermination = array(
        0=>"Unknown",
        1=>"Attested",
        2=>"Conjecture",
        3=>"Measurement"
        );
        
    private $dictProfile = array(
        0=>"Flat",
        1=>"Central",
        2=>"Slow Start",
        3=>"Slow Finish"
        );

    
    function __construct( $date ) {
        $this->setValue($date);
    }    
    
    public function setValue( $date ){
        $this->tDate = Temporal::_parseTemporal( $date );
        
        // Calculate and assign estMinDate and estMaxDate (decimal values)
        if($this->tDate){
            $minmax = $this->calcMinMax();
            
            $this->tDate['estMinDate'] = Temporal::_dateDecimal($minmax[0]);
            $this->tDate['estMaxDate'] = Temporal::_dateDecimal($minmax[1]);
        }
    }
    
    public function getValue($is_simple=false){
        if($is_simple && @$this->tDate['timestamp']['in']){
            return $this->tDate['timestamp']['in'];
        }else{
            return $this->tDate;    
        }
    }

    //
    // Simple type, 0<=year<10000, has both day and month
    //
    public function isValidSimple(){
        
        if($this->isValid() 
             && @$this->tDate['timestamp']   //not range
             && count($this->tDate)==3 //does not have aux fields: comment, calendar etc
             && $this->tDate['estMinDate']==$this->tDate['estMaxDate'])
        {
            $after_digit = substr(strrchr(strval($this->tDate['estMinDate']), '.'), 1);
            //has both month and day and CE   
            if (($this->tDate['estMinDate']>=0 && $this->tDate['estMinDate']<10000 
                   && strlen($after_digit)>2)                    
                || (floor( $this->tDate['estMinDate'] ) == $this->tDate['estMinDate'])) { 
                    return true;        
            }
        }
        return false;
    }
    
    //
    //
    //
    public function getOldTimespan(){
        if($this->isValid()){
                //[start, latest-start, earliest-end, end, label]
                $minmax = $this->calcMinMax();
                return array($minmax[0],'','',$minmax[1],'');
        }    
        return [];
    }
    
    
    public function isValid(){
        return ($this->tDate!=null);   
    }
    
    //
    // parses json or plain string to array of values
    // dates are not validated
    //
    private static function _parseTemporal( $value ){
        
        $timespan = null;
        
        if(is_array($value) && (@$value['timestamp'] || @$value['start'])){
            //already defined
            $timespan = $value;
            
        }else if ($value) {
            
            //at first - detect time interval in format start/end start/duration duration/end
            //$values = explode('/',$value);
            $seps = array('à','.','/','to','-',',');
            
            preg_match_all('/\w+|[à|.|\/|to|\-|,]+/i', $value, $matches);
            
            if(is_array(@$matches[0]) && count($matches[0])==3 && in_array($matches[0][1],$seps) ){
                
                $values = array($matches[0][0],$matches[0][2]);
                
                if(strlen($values[0])>2 && strlen($values[1])>2) {
                    $tStart = null;
                    $tEnd = null;
                    
                    if(strcasecmp(substr($values[0], 0, 1),'P')==0){
                        // duration/end
                        $timespan = Temporal::_getInterval($values[1], $values[0], -1);
                       
                    }else if(strcasecmp(substr($values[1], 0, 1),'P')==0){
                        // start/duration
                        $timespan = Temporal::_getInterval($values[0], $values[1], 1);
                        
                    }else{
                        // start/end
                        $tStart = Temporal::dateToISO($values[0], 2, false, 'now');
                        $tEnd = Temporal::dateToISO($values[1], 2, false, 'now');
                    
                        if($tStart && $tEnd){    
                            $timespan = array('start'=>array('earliest'=>$tStart ),
                                            'end'=>array('latest'=>$tEnd ));
                        }
                    }
                }
                
            }else if(strpos($value,'±')!==false){

                $values = explode('±', $value);
                $period = $values[1];
                $period = str_replace('years','Y',$period);
                $period = str_replace('months','M',$period);
                $period = str_replace('days','D',$period);
                $period = preg_replace('/\s+/', '', $period); //remove spaces
                $period = 'P'.$period;
                if(!preg_match('/Y|M|D$/i',$period)){
                    $period = $period.'Y'; //year by default
                }
                $timespan = Temporal::_getInterval(trim($values[0]), $period, 0);
             
            }

            
            //if(!is_numeric($value)){
            if($timespan==null && !preg_match('/^-?\d+$/', $value) ){
                $timespan = json_decode($value, true);
            }    
            if($timespan!=null){
                //json object
                if(@$timespan[0]){
                    $timespan = $timespan[0]; //in case [{}]
                }    

            }else if (strpos($value,"|")!==false) {// temporal encoded date - converts to array

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
                    
                    
                    switch ($tDate["TYP"]){
                        case 's'://simple
                            $timespan = array('timestamp'=>array('in'=>@$tDate['DAT'], 'type'=>'s'));
                            
                            if(@$tDate['CIR']){  //circa or aproximate
                                $timespan['timestamp']['circa'] = true;
                            }
                            
                            break;
                        case 'f'://fuzzy
                            $timespan = array('timestamp'=>array('in'=>@$tDate['DAT'],'deviation'=>$tDate['RNG'], 'type'=>'f'));    
                            
                            break;
                        case 'c'://carbon

                            //BPD - before present date
                            if(@$tDate['BPD']){
                                $date = 1950 - $tDate['BPD']; //date('Y')
                            }else{
                                $date = -intval($tDate['BCE']);
                            }

                            $timespan = array('timestamp'=>array('in'=>$date, 'type'=>'c', 'bp'=>(@$tDate['BPD']!=null)), 
                                'native'=>(@$tDate['BPD']
                                    ? ('' . $tDate['BPD'] . ' BP')
                                    :(@$tDate['BCE']? '' . $tDate['BCE'] . ' BCE': '')));


                            if(@$tDate['DEV']){
                                $timespan['timestamp']['deviation'] = $tDate['DEV'];
                            }else{
                                if (@$tDate['DVN']){
                                    $timespan['timestamp']['deviation_negative'] = $tDate['DVN'];
                                }
                                if (@$tDate['DVP']){
                                    $timespan['timestamp']['deviation_positive'] = $tDate['DVP'];
                                }
                            }
                            break;
                            
                        case 'p'://probability range
                        
                            $timespan = array('start'=>array('earliest'=>@$tDate['TPQ'] ),
                                              'end'=>array('latest'=>@$tDate['TAQ'] ));
                        
                            if (@$tDate['PDB']){
                                $timespan['start']['latest'] = $tDate['PDB'];
                            } 
                            if(@$tDate['PDE']){
                                $timespan['end']['earliest'] = $tDate['PDE'];
                            }
                            
                            if(@$tDate['SPF']) $timespan['start']['profile'] = $tDate['SPF'];
                            if(@$tDate['EPF']) $timespan['end']['profile'] = $tDate['EPF'];
                            if(@$tDate['PRF']) $timespan['profile'] = $tDate['PRF'];
                                                       
                            break;
                    }//end case
            
            
                    if(@$tDate['DET']) $timespan['determination'] = $tDate['DET'];
                    if(@$tDate['CLD']) $timespan['calendar'] = $tDate['CLD'];
                    if(@$tDate['COM']) $timespan['comment'] = $tDate['COM'];
                    //labaratory code for C14
                    if(@$tDate['COD']) $timespan['labcode'] = $tDate['COD'];
                    //human readable in native calendar
                    if(@$tDate['CL2']) $timespan['native'] = $tDate['CL2'];
                    
            }  else {
                $value = Temporal::dateToISO($value, 2, false, 'now');  //standard order, days not need
                if($value){
                    $timespan = array('timestamp'=>array('in'=>$value, 'type'=>'s'));
                }
            } 
        }

        return $timespan; 
    }
    
    //
    // $direction -1 (sub) 0 (both) +1 (add)
    //
    private static function _getInterval($timestamp, $deviation, $direction=0){
        
        $is_year_only = ($deviation==null || preg_match('/^P\d+Y$/',$deviation)) && preg_match('/^-?\d+$/',$timestamp);
        
        $dt = Temporal::dateToISO($timestamp, 2, !$is_year_only);    
        
        if($is_year_only){
            
            $tStart = $dt;
            $tEnd = $dt;
            if($deviation!=null){
                $years = intval(substr($deviation,1,-1)); //remove P and Y

                if($direction>=0){
                    $tEnd = strval(intval($dt)+$years);    
                }
                if($direction<=0){
                    $tStart = strval(intval($dt)-$years);    
                }
            }
            
        }else{
            
            $tStart = null;
            $tEnd = null;
            
            if($deviation!=null){
                $dt = Temporal::dateToISO($timestamp);    
                try{
                    $tStart = new DateTime($dt);
                    $tEnd = new DateTime($dt);
                } catch (Exception  $e){
                }
                $deviation = strtoupper($deviation);
                $i = null;
                try{
                    $i = new DateInterval($deviation);    
                } catch (Exception  $e){
                }
                
                if($tStart!=null && $i!=null){
                    if($direction>=0){
                        $tEnd->add($i);    
                    }
                    if($direction<=0){
                        $tStart->sub($i);    
                    }
                    
                    $format = 'Y-m-d H:i:s';
                    $tEnd = Temporal::dateToISO($tEnd->format($format), 2, false);
                    $tStart = Temporal::dateToISO($tStart->format($format), 2, false);
                }
            }else{
                $tEnd = $dt;
                $tStart = $dt;
            }
        }
    
        if($tStart && $tEnd){
            return array('start'=>array('earliest'=>$tStart ),
                                'end'=>array('latest'=>$tEnd ));
        }else{
            return null;
        }
        
    
    }
    
    //
    // Calculates and returns min max dates as iso strings
    //
    public function calcMinMax(){
      
        if($this->tDate){  
            
            if(@$this->tDate['timestamp']){ //only one date
               // in 
               $min = Temporal::_getLimitDate($this->tDate['timestamp'], -1);
               $max = Temporal::_getLimitDate($this->tDate['timestamp'], 1);
                
            }else { //start and end
               $min = Temporal::_getLimitDate($this->tDate['start'], -1);
               $max = Temporal::_getLimitDate($this->tDate['end'], 1);
            }
            
            return array($min, $max);
        }
    }
    
    //
    // Returns pair of min max values in decimal format to store in recDetailsDateIndex
    //
    public function getMinMax()
    {
        if($this->tDate){
            return array($this->tDate['estMinDate'], $this->tDate['estMaxDate']);
        }else{
            return null;
        }
    }    
    
    
    //
    // Calculate and assign estMinDate and estMaxDate (decimal values)
    // $direction - earliest or latest
    //
    private static function _getLimitDate($date, $direction){
        
        $res = null;
        if(@$date['in']){
            
                $deviation = @$date['deviation']?$date['deviation']
                    :@$date[$direction>1?'deviation_positive':'deviation_negative'];
            
                // c14 date - only years - consider BCE dates before 5K?
                if(@$date['type']=='c'){ 
                    
                    $res = intval($date['in']);
                    if($deviation!=null){
                        try{                        
                            $i = new DateInterval($deviation);
                            $res = strval($res + $direction * $i->y);
                        } catch (Exception  $e){
                        }
                    }
                    
                }else{
                    
                    $timestamp = Temporal::_getInterval($date['in'], $deviation, $direction);
                    if($timestamp!=null){
                        $res = $direction<0?$timestamp['start']['earliest']:$timestamp['end']['latest'];
                    }
/*                    
                    $dt = Temporal::dateToISO($date['in'],2,false);    
                    
                    if($deviation!=null){
                        try{
                            $dt = new DateTime($dt);
                        } catch (Exception  $e){
                            $dt = null;
                        }
                        if($dt!=null){
                            try{
                                $i = new DateInterval($deviation);
                                $dt = ($direction>0) ?$dt->add($i) :$dt->sub($i);
                            } catch (Exception  $e){
                            }
                            $res = Temporal::dateToISO($dt->format('Y-m-d H:i:s'));
                        }
                    }else{
                        $res = $dt;            
                    }
*/                    
                }
            
        }else{
            $res = Temporal::dateToISO($date[$direction<0?'earliest':'latest'],2,$direction<0?false:'-12-31');
        }
        
        return $res;
    }
    
// 4. Formatting functions

    //
    // Converts string date yyyy-mm-dd  to decimal yyyy.mmdd 
    //
    private static function _dateDecimal($date){
        
        $date = Temporal::_datePrepare($date);
        
        $res = 0;
        
        if(is_array($date)){
             if(@$date['year']!=null){
                 
                 //(($date['year']<0)?'-':'').
                 $res = strval($date['year']);
                 
                 if($date['month']>0){
                    $res = $res.'.'.str_pad(strval($date['month']),2,'0',STR_PAD_LEFT);
                    if($date['day']>0 && $date['has_days']){
                        $res = $res.str_pad(strval($date['day']),2,'0',STR_PAD_LEFT);
                    }
                 }
                 
                 $res = floatval($res);
                 
             }
        }
        
        return $res;
    }

    //
    // Validates and sanitizes string date value 
    // Returns date array (year, month, day...)
    //
    private static function _datePrepare($value, $month_day_order=2){
        
        if($value==null) return null;
    
        //1. Preparation of sting value - trim, remove "?", remove padding zeroes for year, 
        $origHasDays = false;
        $origHasSeconds = false;
        $date = null;
        
        //trim ? 
        $value = str_replace('?','',$value);
        $is_bce = false;
        
        if(strpos(strtolower($value),'bce')!==false){
            $value = trim('-'.str_replace('bce','',strtolower($value)));
        }

        if(!preg_match('/^-?\d+$/', $value) && $value[0] == '-'){ //this is BCE with month and day
            
            $parts = explode('-', str_replace(' ','-',$value));
            
            if(count($parts) > 2 && empty($parts[0])){
                $is_bce = true;
                
                if(intval($parts[1]) < 10000){ //less than 10K - month/day allowed
                    //pad to 4 digits to avoid  <70 to 1969 and >69 to 2070
                    $new_year_val = str_pad(strval(intval($parts[1])), 4, '0', STR_PAD_LEFT);
                    $value = str_replace('-'.$parts[1], $new_year_val, $value);
                }else{
                    $value = '-'.$parts[1]; //drop months for years <10kya
                }
            }
        }

        if( preg_match('/^-?\d+$/', $value) ){ //this is YEAR - only digits with possible minus and spaces for milles
            $value = preg_replace('/\s+/', '', $value);
            $date = array('year'=>$value);
        }else{
            
            if(strpos($value,'XX')>0){
                $date = null;
            }else{
                
                //replace slashes or dots "/." to dashes "-"
                //reorder month and day
                $value = Temporal::correctDMYorder($value, $month_day_order);

                //2. Create php datetime and parse it to array
                try{   
                    $origHasSeconds = (substr_count($value,':')>1);
                    $origHasDays = substr_count($value,'-')>1 || substr_count($value,' ')>1 || substr_count($value,'/')>1;
                    
                    
                    $t2 = new DateTime($value);
                    $datestamp = $t2->format('Y-m-d H:i:s');
                    $date = date_parse($datestamp);

                    if(is_array($date)){
                        $date['has_days'] = $origHasDays;
                        $date['has_seconds'] = $origHasSeconds;
                    }
                    
                    if($is_bce){
                        $date['year'] = -$date['year'];
                    }
                    
                } catch (Exception  $e){
                    $date = null;
                    //print $value.' => NOT SUPPORTED<br>';                            
                }                            
            }
            
        }
        
        return $date;
        
    }
    
    
    //
    // Converts date array to ISO8601 string
    // $value - string or date array (date_parse)
    // $month_day_order for $value   1 - dd/mm,  2 - mm/dd
    // $today_date - for conversion textual values (today, tomorrow) to date
    // returns ISO8601 string or "Temporal"
    // returns null if fails
    //
    public static function dateToISO($date, $month_day_order=2, $need_day=true, $today_date=null){

        $res = null;

        if(!is_array($date)){

            //check for textual values
            if (strpos($date,'|')!==false || strpos($date,'{"')!==false) {// temporal encoded date - this is for check in import and validation only
                return 'Temporal';
            }else{
                if($today_date!=null){
                    $t2 = new DateTime($today_date);

                    $sdate = strtolower(trim($date));
                    if($sdate=='today'){
                        $date = $t2->format('Y-m-d');
                    }else if($sdate=='now'){
                        $date = $t2->format('Y-m-d H:i:s');
                    }else if($sdate=='yesterday'){
                        $t2->modify('-1 day');
                        $date = $t2->format('Y-m-d');//date('Y-m-d',strtotime("-1 days"));
                    }else if($sdate=='tomorrow'){
                        $t2->modify('+1 day');
                        $date = $t2->format('Y-m-d');//date('Y-m-d',strtotime("+1 days"));
                    }
                }
            }

            $date = Temporal::_datePrepare($date, $month_day_order);
        }

        if(is_array($date)){ //this is array

            $res = "";
            $isbce = false;

            if(is_numeric(@$date['year'])){

                $date['year'] = intval($date['year']);

                $isbce= ($date['year']<0);

                $res = strval(abs($date['year']));

                //year must be four digit for CE and 6 for BCE
                if($isbce){
                    $res = str_pad($res,4,'0',STR_PAD_LEFT); //WAS 6
                }else if(abs($date['year'])<10000){
                    $res = str_pad($res,4,'0',STR_PAD_LEFT);

                    if($need_day && count($date) == 1){ // only year, add -01-01 for ISO format
                        if($need_day===true) $need_day = '-01-01';
                        $res = $res . $need_day;
                    }
                }
            }else{
                return null; //wrong value for year
            }

            $has_time = (@$date['hour']>0 || @$date['minute']>0 || @$date['second']>0);

            //for strict ISO - make sure month and days are 2 digits
            if(@$date['month'] || $has_time){
                $res = $res.'-'.str_pad(strval($date['month']),2,'0',STR_PAD_LEFT);

                if(!$need_day && @$date['has_days']!=true && !$has_time){

                }else if(@$date['day']){ //&& ($need_day || $has_time)
                    $res = $res.'-'.str_pad(strval($date['day']),2,'0',STR_PAD_LEFT);
                }
            }

            if(true){
                if($has_time){
                    if(!@$date['hour']) {
                        $date['hour'] = 0;
                    }

                    if($date['hour']>0 || @$date['minute']>0 || @$date['second']>0){
                        $res = $res.' '.str_pad(strval($date['hour']),2,'0',STR_PAD_LEFT);

                        if(!@$date['minute']) { $date['minute'] = 0; }
                        $res = $res.':'.str_pad(strval($date['minute']),2,'0',STR_PAD_LEFT);
                    }
                    if(@$date['second']>0 || @$date['has_seconds']){
                        $res = $res.':'.str_pad(strval($date['second']),2,'0',STR_PAD_LEFT);
                    }
                }
            }else{   //debug
                $res = $res.' '.@$date['hour'].':'.@$date['minute'].':'.@$date['second'];
            }


            if($isbce){
                $res = '-'.$res;
            }
        }

        return $res;        
    }

    //
    // Converts date array to human readable string:  day Month year + suffix (BCE)
    // $value - string or date array (date_parse)
    //
    public static function dateToString($value, $calendar=null){

        $res = 'unknown temporal format';

        if($value && !is_array($value)){
            $res = $res . ' ' . $value;
            $date = Temporal::_datePrepare($value);
        }else{
            $date = $value;
        }
    
        if($date){

            $res = '';
            $isbce = false;

            if(is_numeric(@$date['year'])){

                $date['year'] = intval($date['year']);

                $isbce= ($date['year']<0);

                $res = strval(abs($date['year']));
            }

            $has_time = (@$date['hour']>0 || @$date['minute']>0 || @$date['second']>0);
            
            $is_greg_or_julian = (!$calendar || 
                            strtolower($calendar)=='gregorian' || strtolower($calendar)=='julian');

            if($is_greg_or_julian){

                $res2 = '';
                if(@$date['has_days']!=true){

                }else if(@$date['day']){
                    $res2 = $date['day']; 
                }
                if(@$date['month']){
                    $res2 = $res2.' '.date('M', mktime(0, 0, 0, $date['month'], 1)); //strtotime($date['month'].'01')); 
                }

                $res = trim($res2."  ".$res); // day month year

            }else{
                if(@$date['month'] || $has_time){
                    $res = $res.'-'.str_pad(strval($date['month']),2,'0',STR_PAD_LEFT);

                    if(@$date['has_days']!=true && !$has_time){

                    }else if(@$date['day']){
                        $res = $res.'-'.str_pad(strval($date['day']),2,'0',STR_PAD_LEFT);
                    }
                }
            }

            if($has_time){
                if(!@$date['hour']) {
                    $date['hour'] = 0;
                }

                if($date['hour']>0 || @$date['minute']>0 || @$date['second']>0){
                    $res = $res.' '.str_pad(strval($date['hour']),2,'0',STR_PAD_LEFT);

                    if(!@$date['minute']) { $date['minute'] = 0; }
                    $res = $res.':'.str_pad(strval($date['minute']),2,'0',STR_PAD_LEFT);
                }
                if(@$date['second']>0 || @$date['has_seconds']){
                    $res = $res.':'.str_pad(strval($date['second']),2,'0',STR_PAD_LEFT);
                }
            }

            if($isbce){
                if(@$date['has_days']!=true && $date['year']<-999999){
                    if($date['year']<-999999999){
                        $res = (intval($res)/1e9).' bya';
                    }else if($date['year']<-999999){
                        $res = (intval($res)/1e6).' Mya';
                    }else{
                        //$res = (intval($res)/1000).' kya';
                    }
                }else{
                    $res = $res.' BCE';    
                }
                
            }

        }            


        return $res;
    }
    

    //
    // $month_day_order   true or 0 - returns true or false whether date/month are ambiguate, or month=13 or day=32
    //                    2 - mm/dd (default)
    //                    1 - dd/mm
    // Replaces slashes or dots "/." to dashes "-"
    // Reorders month and day
    //                          
    public static function correctDMYorder($value, $month_day_order=2){

        $check_ambiguation = ($month_day_order===0 ||  $month_day_order===true);

        $is_dots_slash = false;
        $is_ambiguation = false;

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

            
            if(strlen($m)>2 && is_numeric($m)){
                 list($y, $m) = explode('-', $value);
            
            }else if((strlen($m)>2 && !is_numeric($m)) || $y>12){ //Oct-12
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
            $is_ambiguation = ($y<13 && $m<13); //ambiguation
        }

        if(substr_count($value,'-')==2 && strpos($value,':')===false) {
            //change d-m-y to y-m-d   only if original value has slashes or dots
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

                    $is_ambiguation = ($m<13 && $d<13); //day-month ambiguation
                }

                $value = $y.'-'.$m.'-'.$d; // mm/dd
            }else{
                list($y, $m, $d) = explode('-', $value);
            }

            if($check_ambiguation){
                if($m==13){
                    $is_ambiguation = true;    
                }else{
                    $days_req = cal_days_in_month(CAL_GREGORIAN, intval($m), intval($y));        
                    if($days_req+1==$d || $days_req+2==$d){
                        $is_ambiguation = true;
                    }
                } 
            }
        }

        return ($check_ambiguation)?$is_ambiguation :$value;    
    }
    
    //
    //
    //
    public static function mergeTemporals($dt1, $dt2){
        
            $dt1 = new Temporal($dt1);
            $dt2 = new Temporal($dt2);
            
            if($dt1->isValid() && $dt2->isValid()) {
        
                $range1 = $dt1->getMinMax();
                $range2 = $dt2->getMinMax();
                $dt1 = $dt1->getValue();
                $dt2 = $dt2->getValue();
                
                $newdate = array();

                if($range1[0]<$range2[0]){
                    $newdate['start'] = @$dt1['start']?$dt1['start']:$dt1['timestamp'];
                }else{
                    $newdate['start'] = @$dt2['start']?$dt2['start']:$dt2['timestamp'];
                }
                if($range1[1]>$range2[1]){
                    $newdate['end'] = @$dt1['end']?$dt1['end']:$dt1['timestamp'];
                }else{
                    $newdate['end'] = @$dt2['end']?$dt2['end']:$dt2['timestamp'];
                }
                $newdate= new Temporal($newdate);
                
                return $newdate;
            }else{
                return null;
            }
    }
    
    //
    // $dt - string
    //
    public static function toHumanReadable($dt){
        
        if($dt){
            $dt = new Temporal($dt);
            if($dt && $dt->isValid()) {
               return $dt->toReadable();   
            }else{
               return 'invalid temporal object'; 
            }
        }else{
            return '';
        }
    }
    

// 3. Export functions
/*    
*       toJSON          - geojson-h (to store in database recDetails)
*       toKML           - xml snippet for kml
*       toPlain         - old temporal plain string 
*       toHumanReadble  - human readble
*/


    //
    // Encodes temporal object into JSON string
    //
    public function toJSON(){
        if($this->tDate){
            return json_encode($this->tDate);
        }else{
            return null;
        }
    }
    
    //
    // Returns xml string snippet for kml export  temporalToSimple
    //
    public function toKML(){
        if($this->tDate){
                 $minmax = $this->calcMinMax();//get min max as iso string
                 //substr_replace($value,"T",10,1);
                 if($minmax[0] == $minmax[1]){
                     return '<TimeStamp><when>'.$minmax[0].'</when></TimeStamp>';
                 }else{
                     return "<TimeSpan><begin>{$minmax[0]}</begin><end>{$minmax[1]}</end></TimeSpan>";
                 }
        }else{
            return '';
        }
    }
    
    //
    //
    //
    private static function _deviationSuffix($timestamp){

            $res = '';
            if(@$timestamp['deviation']){
                $res = Temporal::_deviationToText($timestamp['deviation'], ' ±');
            }else{
                if(@$timestamp['deviation_negative']){
                    $res = Temporal::_deviationToText($timestamp['deviation_negative'], ' -');
                }
                if(@$timestamp['deviation_positive']){
                    $res = trim($res.' '.Temporal::_deviationToText($timestamp['deviation_positive'], ' +'));
                }
            }
            return $res;        
    }
    
    //
    //get textual version of deviation
    //
    private static function _deviationToText($value, $prefix){
        
        if($value){
            try{
                $i = new DateInterval($value);
                if($i){
                    return ($i->y ? ("$prefix{$i->y} years") :
                            ($i->m ? ("$prefix{$i->m} months") :
                            ($i->d ? ("$prefix{$i->d} days") :'' )));
                }
            } catch (Exception  $e){
            }
        }
        return '';
    }
    
    //
    // Outputs humand readable representation of temporal object
    //    
    public function toReadable(){
        if($this->tDate){
            
            $date = $this->tDate;
            
            $calendar = @$date['calendar'];

            if(@$date['timestamp']){
                
                //one date value with possible deviation
                $res = Temporal::dateToString(@$date['timestamp']['in'], $calendar);
                if($res){
                    $res = $res.Temporal::_deviationSuffix( $date['timestamp'] );
                }
                
                if(@$date['timestamp']['circa']){
                    $res = 'circa '.$res;
                }
                
            }else{

                $from = '';                
                $to = '';
                
                if(@$date['start'] && @$date['start']['in']){
                    $from = Temporal::dateToString($date['start']['in'], $calendar);
                    if($from && strpos($from,'unknown')===false){
                        $from = $from.Temporal::_deviationSuffix( $date['start'] );
                    }
                }else if(@$date['start']['earliest']){
                    $from = Temporal::dateToString($date['start']['earliest'], $calendar);
                }
                
                if(@$date['end'] && @$date['end']['in']){
                    $to = Temporal::dateToString($date['end']['in'], $calendar);
                    if($to && strpos($to,'unknown')===false){
                        $to = $to.Temporal::_deviationSuffix( $date['end'] );
                    }
                }else if(@$date['end']['latest']){
                    $to = Temporal::dateToString($date['end']['latest'], $calendar);
                }
                $res = $from.' to '.$to;
            }
            
            //add native decription as prefix
            $is_greg_or_julian = (!$calendar || 
                            strtolower($calendar)=='gregorian' || strtolower($calendar)=='julian');
            
            if($calendar && @$date['native'] && !$is_greg_or_julian){
                $res = $date['native'].'  '.$calendar.' (Gregorian '.$res.')';
            }            
            
            return $res;
            
        }else{
            return 'undefined temporal';
        }
    }


    //
    // Outputs humand readable representation of temporal object
    //    
    public function toReadableExt($separator){
        if($this->tDate){
            
            $date = $this->tDate;
            
            $calendar = @$date['calendar'];
            $res = array();
            
            $res['Type'] = '';

            if(@$date['timestamp']){
            
                $res['Type'] = ($date['timestamp']['type'] == 'c')?'Radiometric'
                                :($date['timestamp']['type'] == 'f'?'Fuzzy date'
                                :'Simple');
                //one date value with possible deviation
                $timestamp = Temporal::dateToString(@$date['timestamp']['in'], $calendar);
                if($timestamp){
                    $timestamp = $timestamp.Temporal::_deviationSuffix( $date['timestamp'] );
                }
                
                if(@$date['timestamp']['circa']){
                    $res['Date'] = 'circa '.$timestamp;
                }
                
                $res['Date']  = $timestamp;
                
            }else if(@$date['start'] && $date['type']=='r'){  //simple range - NOT USED

                $res['Type'] = 'Simple Range';
            
                $res['Earliest estimate'] = Temporal::dateToString($date['start']['earliest'], $calendar);
                $res['Latest estimate'] = Temporal::dateToString($date['end']['latest'], $calendar);
            
                if(@$date['profile']){
                    $res['Probability curve'] = $this->dictProfile[intval($date['profile'])];
                }
            
            }else{ //timespan - range

            
                $from = '';                
                $to = '';
                $is_simple = true;
                
                if(@$date['start'] && @$date['start']['in']){ //not used
                    $from = Temporal::dateToString($date['start']['in'], $calendar);
                    if($from && strpos($from,'unknown')===false){
                        $from = $from.Temporal::_deviationSuffix( $date['start'] );
                    }
                    if(@$date['start']['profile']){
                        $res['Start probability curve'] = $this->dictProfile[intval($date['start']['profile'])];
                    }
                }else if(@$date['start']['earliest']){
                    $from = Temporal::dateToString($date['start']['earliest'], $calendar);
                    
                    $dt = null;
                    if(@$date['start']['latest']){
                        $dt = Temporal::dateToString($date['start']['latest'], $calendar);
                        if($dt && strpos($dt,'unknown')===false){
                            $is_simple = false;
                        }
                    }
                    
                    $res[$is_simple?'Earliest estimate':'Terminus Post Quem'] = $from;
                    if(!$is_simple) $res['Probable Begin'] = $dt;
                    if(@$date['start']['profile']){
                        $res['Start Profile'] = $this->dictProfile[intval($date['start']['profile'])];
                    }
                }
                
                if(@$date['end'] && @$date['end']['in']){  //not used
                    $to = Temporal::dateToString($date['end']['in'], $calendar);
                    if($to && strpos($to,'unknown')===false){
                        $to = $to.Temporal::_deviationSuffix( $date['end'] );
                    }
                    
                    if(@$date['end']['profile']){
                        $res['End probability curve'] = $this->dictProfile[intval($date['end']['profile'])];
                    }
                }else if(@$date['end']['latest']){
                    
                    if(@$date['end']['earliest']){
                        $dt = Temporal::dateToString($date['end']['earliest'], $calendar);
                        if($dt && strpos($dt,'unknown')===false){
                            $res['Probable End'] = $dt;
                            $is_simple = false;
                        }
                    }
                    
                    $to = Temporal::dateToString($date['end']['latest'], $calendar);
                    $res[$is_simple?'Latest estimate':'Terminus Ante Quem'] = $to;
                    
                    if(@$date['start']['profile']){
                        $res['End Profile'] = $this->dictProfile[intval($date['end']['profile'])];
                    }
                }
                
                $res['Type'] = ($is_simple)?'Simple Range':'Approximate Range';
            }
            
            //add native decription as prefix
            $is_greg_or_julian = (!$calendar || 
                            strtolower($calendar)=='gregorian' || strtolower($calendar)=='julian');
            
            if($calendar && @$date['native'] && !$is_greg_or_julian){
                $res[''] = $date['native'];
            }            
            
            
            if(@$date['calendar']) $res['Calendar'] = $date['calendar'];
            if(@$date['comment']) $res['Comment'] = $date['comment'];
            if(@$date['determination']) $res['Determination'] = $this->dictDetermination[intval($date['determination'])];
            //labaratory code for C14
            if(@$date['labcode']) $res['Labaratory Code'] = $date['labcode'];
            
            $res2 = '';
            foreach($res as $key=>$val){
                $res2 = $res2.$key.': '.$val.$separator;
            }
            
            return $res2;
            
        }else{
            return 'undefined temporal';
        }
    }
    
    //
    // To old plain string format
    //
    public function toPlain(){
        
        
        if($this->isValid()){

            $res = array();
            $date = $this->tDate;

            if(@$date['timestamp']){
                
                $res['TYP'] = @$date['timestamp']['type'];
                
                if($res['TYP']=='c'){
                    if($date['timestamp']['bp']){
                        $res['BPD'] = ''.(abs(intval(@$date['timestamp']['in'])+1950));
                    }else{
                        $res['BCE'] = ''.abs(intval(@$date['timestamp']['in']));
                    }
                    if(@$date['timestamp']['deviation']){
                        $res['DEV'] = $date['timestamp']['deviation'];    
                    }else{
                        if(@$date['timestamp']['deviation_negative']){
                            $res['DVN'] = $date['timestamp']['deviation_negative'];    
                        }
                        if(@$date['timestamp']['deviation_positive']){
                            $res['DVP'] = $date['timestamp']['deviation_positive'];    
                        }
                    }
                    
                }else{
                    $res['DAT'] = @$date['timestamp']['in'];
                
                    if(@$date['timestamp']['deviation']){
                        $res['TYP'] = 'f';
                        $res['RNG'] = $date['timestamp']['deviation'];    
                        if(@$date['timestamp']['profile']) $res['PRF'] = $date['timestamp']['profile'];
                    }else{
                        $res['TYP'] = 's';
                        if(@$date['timestamp']['circa']) $res['CIR'] = '1';
                    }
                }
                
            }else{
                /*                
                TPQ = terminus post Quem
                PDB = probable begin date
                SPF = start profile

                PDE = probable date end
                TAQ = Terminus Ante Quem
                EPF = end profile
                */
                
                $res['TYP'] = 'p';
                    
                if(@$date['start'] && @$date['start']['in']){
                    $res['TPQ'] = $date['start']['in'];
                }else{
                    $res['TPQ'] = $date['start']['earliest'];
                    if(@$date['start']['latest']) $res['PDB'] = $date['start']['latest'];
                }

                if(@$date['end'] && @$date['end']['in']){
                    $res['TAQ'] = $date['end']['in'];
                }else{
                    if(@$date['end']['earliest']) $res['PDE'] = $date['end']['earliest'];
                    $res['TAQ'] = $date['end']['latest'];
                }

                if(@$date['start']['profile']) $res['SPF'] = $date['start']['profile'];    
                if(@$date['end']['profile']) $res['EPF'] = $date['end']['profile'];
                if(@$date['profile']) $res['PRF'] = $date['profile'];
            }
        
            
            if(@$date['determination']) $res['DET'] = $date['determination'];
            if(@$date['calendar']) $res['CLD'] = $date['calendar'];
            if(@$date['comment']) $res['COM'] = $date['comment'];
            //labaratory code for C14
            if(@$date['labcode']) $res['COD'] = $date['labcode'];
            //human readable in native calendar
            if(@$date['native']) $res['CL2'] = $date['native'];
            
            $res2 = '|VER=1';
            foreach($res as $key=>$val){
                $res2 = $res2.'|'.$key.'='.$val;    
            }
            
            return $res2;    
        }else{
            return '';
        }
        
    } //toPlain
    
    
} // end Temporal class
?>
