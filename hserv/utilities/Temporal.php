<?php
/**
* Temporal.php - Class Temporal
* 
* Represents and manipulates temporal (date/time) data within Heurist
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1.0 
*/
namespace hserv\utilities;

/**
* Class Temporal
* 
* Represents and manipulates temporal (date/time) data within Heurist.
* Handles parsing various date formats, converting between formats (ISO, human-readable, JSON, KML, plain string),
* calculating date ranges, and validating temporal values.
* 
* 1. Public methods
*       setValue
*       getValue
*       isValid
*       isValidSimple
*       getMinMax - pairs of min max values in decimal format to store in recDetailsDateIndex
*       calcMinMax - calculates and returns min max dates as iso strings
*       getTimespan - returns temporal as array
*                     [start, latest-start, earliest-end, end, label, profile-start, profile-end, determination]
* 2. Static parse and formatting functions
*       _getLimitDate  - find earliest and latest dates for timespan
*       _parseTemporal - parses json or plain string to array of values
*       _datePrepare     - Validates and sanitizes string date value - returns date array
*       _dateDecimal    - Converts string date yyyy-mm-dd  to decimal yyyy.mmdd
*       decimalToYMD   - Converts string yyyy.mmdd to yyyy-mm-dd
*
*       dateToISO       - Converts date array to ISO8601 string
*       dateToString    - Converts to human readable string
*       correctDMYorder - Replaces slashes or dots "/." to dashes "-", Reorders month and day
*       mergeTemporals  - merge two temporals (it lost fields comment,determination,calendar)
*       toHumanReadable
*       getPeriod       - finds difference between two temporals in years, month, days
*
* 3. Export function
*       toKML           - xml snippet for kml
*       toJSON          - geojson-h (to store in database recDetails)
*       toPlain         - old temporal plain string
*       toReadable      - human readble
*       toReadableExt   - human readble extended
* 
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

    /**
     * Constructor for the Temporal class.
     * Initializes the temporal object by parsing the input date string or array.
     *
     * @param string|array $date The date value to parse (can be a string or a pre-parsed array).
     * @param bool $is_for_search Optional. Indicates if the parsing is for a search context, which might affect interpretation. Defaults to false.
     */
    public function __construct( $date, $is_for_search=false ) {
        $this->setValue($date, $is_for_search);
    }

    /**
     * Sets or updates the value of the temporal object.
     * Parses the input date and calculates min/max decimal representations.
     *
     * @param string|array $date The date value to parse (can be a string or a pre-parsed array).
     * @param bool $is_for_search Optional. Indicates if the parsing is for a search context. Defaults to false.
     * @return void
     */
    public function setValue( $date, $is_for_search=false ){
        $this->tDate = Temporal::_parseTemporal( $date, $is_for_search );

        // Calculate and assign estMinDate and estMaxDate (decimal values)
        if($this->tDate){
            $minmax = $this->calcMinMax();

            $this->tDate['estMinDate'] = Temporal::_dateDecimal($minmax[0]);
            $this->tDate['estMaxDate'] = Temporal::_dateDecimal($minmax[1]);
        }
    }

    /**
     * Gets the internal representation of the temporal object.
     *
     * @param bool $is_simple Optional. If true and the date is a simple timestamp, returns only the 'in' value. Defaults to false.
     * @return array|string|null The parsed temporal data array, or a specific string value if $is_simple is true, or null if not valid.
     */
    public function getValue($is_simple=false){
        if($is_simple && @$this->tDate['timestamp']['in']){
            return $this->tDate['timestamp']['in'];
        }else{
            return $this->tDate;
        }
    }

    /**
     * Checks if the current temporal object represents a "simple" valid date.
     * A simple date has a year between 0-9999, includes month and day, and is not a range or fuzzy date.
     *
     * @return bool True if the date is simple and valid, false otherwise.
     */
    public function isValidSimple(){

        if($this->isValid()
        && @$this->tDate['timestamp']   //not range
        && count($this->tDate)==3 && count($this->tDate['timestamp'])==2 //does not have aux fields: comment, calendar etc
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

    /**
     * Gets a timespan representation suitable for GeoJSON or similar outputs.
     * Returns an array: [start, latest-start, earliest-end, end, label, profile-start, profile-end, determination].
     *
     * @param bool $plain_array Optional. This parameter is currently not used in the method logic. Defaults to false.
     * @return array|null An array representing the timespan, or null if the date is invalid or out of representable range.
     */
    public function getTimespan($plain_array=false){
        $res = null;
        if($this->isValid()){
            //[start, latest-start, earliest-end, end, label, profile-start, profile-end, determination]

            $minmax = $this->calcMinMax();

            if(intval($minmax[0])<-250000){
                return null;
            }

            $res = array($minmax[0],'','',$minmax[1],$this->toReadableExt('',true), 0, 0, 0);

            $date = $this->tDate;

            if(@$date['timestamp']){

                $profile = 0;

                if($date['timestamp']['type']=='c'){ //radiometric/carbon

                    if(@$date['timestamp']['deviation_negative'] && !@$date['timestamp']['deviation_positive']){
                        $profile = 2; //slow start
                    }elseif(!@$date['timestamp']['deviation_negative'] && @$date['timestamp']['deviation_positive']){
                        $profile = 3; //slow finish
                    }else{
                        $profile = 1; //central
                    }

                }elseif(@$date['timestamp']['circa']){
                    $profile = 1; //central
                }elseif(@$date['timestamp']['before']){
                    $profile = 2; //slow start
                }elseif(@$date['timestamp']['after']){
                    $profile = 3; //slow finish
                }

                $res[5] = $profile;

            }else{
                $res[1] = Temporal::dateToISO($date['start']['latest'],2,false);
                $res[2] = Temporal::dateToISO($date['end']['earliest'],2,false);

                if(@$date['profile']){
                    //simple range
                    $res[5] = $date['profile'];
                }else{
                    //fuzzy range
                    if(@$date['start']['profile']>0) {$res[5] = $date['start']['profile'];}
                    if(@$date['end']['profile']>0) {$res[6] = $date['end']['profile'];}
                }
            }

            //profile: Flat(0), Central(1) (circa), Slow Start(2) (before), Slow Finish(3) (after) - responsible for gradient
            //determination: Unknown(0), Conjecture(2), Measurment(3), Attested(1)  - color depth
            if(@$date['determination']){
                $res[7] = $date['determination'];
            }
        }
        return $res;
    }

    /**
     * Checks if the current temporal object holds a valid parsed date.
     *
     * @return bool True if the date is valid, false otherwise.
     */
    public function isValid(){
        return $this->tDate!=null;
    }

    //
    // parses json or plain string to array of values
    // dates are not validated
    //
    private static function _parseTemporal( $value, $is_for_search=false ){

        $regex_after_year = '/^\d{4}-$/i'; //find "year-" that means "after year"

        $timespan = null;

        if(is_array($value) && (@$value['timestamp'] || @$value['start'])){
            //already defined
            $timespan = $value;

        }elseif($value) {

            if(strpos($value,'><')===0 || strpos($value,'<>')===0){
                $is_for_search = true;
                $value = substr($value,2);
            }

            //at first - detect time interval in format start/end start/duration duration/end

            //separator with - will work only for years - otherwise it is very difficult ot detect
            //$values = explode('-',$value);
            //$value2 = preg_replace('/\s+/', '', $value);

            if(!preg_match('/\||timestamp|start/',$value)){

                if(preg_match('/fl|abt\.|abt|about|around|vers|^c\s|ca|circa|~/i',$value)){

                    preg_match_all('/fl|abt\.|abt|about|around|vers|ca\.|ca|circa|^c|~\s*|[-|\w+|\s]+$/i', $value, $matches);

                    if(@$matches[0][1]){

                        $timespan = Temporal::_getIntervalForMonth($matches[0][1]);

                        if($timespan){
                            if(@$timespan['timestamp']){
                                $timespan['timestamp']['circa'] = true;
                            }elseif(@$timespan['start']){
                                $timespan = array('timestamp' => array('in'=>$timespan['start']['earliest'],'type'=>'s', 'circa'=>true));
                            }
                        }
                    }
                }elseif(preg_match('/(before|bef\.|bef|avant|after|post|aft\.|aft|après)/i',$value)
                      || preg_match($regex_after_year, $value))
                {
                    if(preg_match($regex_after_year, $value)){
                        preg_match_all($regex_after_year, $value, $matches);
                        $matches[0][1] = substr($matches[0][0],0,4);
                        $matches[0][0] = 'after';
                    }else{
                        preg_match_all('/(before|bef\.|bef|avant|after|post|aft\.|aft|après)\s+|(?:[-|\w+|\s]+$)/i', $value, $matches);
                    }


                    if(@$matches[0][1]){

                        $timespan = Temporal::_getIntervalForMonth($matches[0][1]);

                        if($timespan){

                            $is_before = (strtolower($matches[0][0])=='before'
                                || strtolower($matches[0][0])=='bef' || strtolower($matches[0][0])=='bef.'
                                || strtolower($matches[0][0])=='avant');

                            if(@$timespan['start']){
                                $timespan = array('timestamp' => array('in'=>$is_before?$timespan['start']['earliest']:$timespan['end']['latest']
                                    ,'type'=>'s'));
                            }

                            if(@$timespan['timestamp']){
                                if($is_before){
                                    $timespan['timestamp']['before'] = true;
                                }else{
                                    $timespan['timestamp']['after'] = true;
                                }
                            }
                        }
                    }
                }



                if(!$timespan){

                    $seps = array('à','.','/','to',',');

                    if(preg_match('/à|\.|\/|to|,/i', $value)){

                        preg_match_all('/[-|\w+]+|[à|\.|\/|to|,]+/i', $value, $matches);

                        if($matches && is_array(@$matches[0])){
                            $values = array();
                            $values[0] = '';
                            $k = 0;
                            foreach($matches[0] as $val){
                                if(in_array($val,$seps)){
                                    $values[$k+1] = $val;
                                    $k = $k + 2;
                                    $values[$k] = '';
                                }else{
                                    $values[$k] = trim($values[$k].' '.$val);
                                }
                            }
                            if(count($values)==3){
                                $matches[0] = $values;
                            }
                        }

                    }else{

                        preg_match_all('/-?\d+|-/', $value, $matches);

                        if(is_array(@$matches[0]) && (count($matches[0])==2 || (count($matches[0])==3 && $matches[0][1]=='-')))
                        {
                            if(count($matches[0])==2){
                                if(substr($matches[0][1],0,1)=='-'){
                                    $matches[0][2] = substr($matches[0][1],1);
                                    $matches[0][1] = '-';
                                }else{
                                    $matches = null;
                                }
                            }
                        }else{
                            $matches = null;
                        }
                    }

                    $seps = array('à','.','/','to','-',',');

                    if($matches && is_array(@$matches[0]) && count($matches[0])==3 && in_array($matches[0][1],$seps) ){

                        $values = array($matches[0][0],$matches[0][2]);

                        if( (strlen($values[0])>2 && strlen($values[1])>2)
                        || $is_for_search ) {
                            $tStart = null;
                            $tEnd = null;

                            if(strcasecmp(substr($values[0], 0, 1),'P')==0){
                                // duration/end
                                $timespan = Temporal::_getInterval($values[1], $values[0], -1);

                            }elseif(strcasecmp(substr($values[1], 0, 1),'P')==0){
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

                    }elseif(strpos($value,'±')!==false){

                        $values = explode('±', $value);
                        $period = $values[1];
                        if(preg_match('/(years|months|days)/',$period)){
                            $period = str_replace('years','Y',$period);
                            $period = str_replace('months','M',$period);
                            $period = str_replace('days','D',$period);
                            $period = preg_replace('/\s+/', '', $period);//remove spaces
                            $period = 'P'.$period;
                        }elseif(strpos($period,'year')!==false){
                            $period = 'P1Y';
                        }elseif(strpos($period,'month')!==false){
                            $period = 'P1M';
                        }elseif(strpos($period,'day')!==false){
                            $period = 'P1D';
                        }
                        if(!preg_match('/[Y|M|D]$/i',$period)){
                            $period = $period.'Y';//year by default
                        }
                        $timespan = Temporal::_getInterval(trim($values[0]), $period, 0);

                    }
                }
            }


            if($timespan==null && !preg_match(REGEX_YEARONLY, $value) ){
                $timespan = json_decode($value, true);

                if($timespan){
                    if(is_double($timespan)){ //200.15
                        $value = strval(intval($timespan));
                        $timespan = null;
                    }elseif(!isEmptyArray($timespan) && is_numeric(@$timespan[0])){
                        if(count($timespan)==1){
                            $value = strval(intval($timespan[0]));
                            $timespan = null;
                        }
                    }
                }

            }
            if($timespan!=null && is_array($timespan)){
                //json object
                if(@$timespan[0]){
                    $timespan = $timespan[0];//in case [{}]
                }

            }elseif (strpos($value,"|")!==false) {// temporal encoded date - converts to array

                $tDate = array();
                $props = explode("|",substr_replace($value,"",0,1));// remove first verticle bar and create array
                foreach ($props as $prop) {//create an assoc array
                    list($tag, $val) = explode("=",$prop);
                    $tDate[$tag] = $val;
                }

                if (@$tDate["CLD"] && @$tDate["CL2"] && strtolower($tDate["CLD"])!='gregorian') {
                    $cld = $tDate["CL2"]." ".$tDate["CLD"];
                    if(strpos($cld,'null')!==false) {$tDate["CLD"] = substr($cld,4);}//some dates were saved in wrong format - fix it
                }


                switch ($tDate["TYP"]){
                    case 's'://simple

                        $timespan = Temporal::_getIntervalForMonth(@$tDate['DAT']);

                        if(is_array($timespan) && $timespan['timestamp'] && @$tDate['CIR']) //circa or aproximate
                        {
                            if(@$tDate['CIR']==1){
                                $timespan['timestamp']['circa'] = true;
                            }elseif(@$tDate['CIR']==2){
                                $timespan['timestamp']['before'] = true;
                            }elseif(@$tDate['CIR']==3){
                                $timespan['timestamp']['after'] = true;
                            }

                        }

                        break;
                    case 'f'://fuzzy
                        $timespan = array('timestamp'=>array('in'=>@$tDate['DAT'],'deviation'=>$tDate['RNG'], 'type'=>'f'));

                        break;
                    case 'c'://carbon

                        //BPD - before present date
                        if(@$tDate['BPD']){
                            $date = 1950 - $tDate['BPD'];//date('Y')
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

                        if(@$tDate['SPF']) {$timespan['start']['profile'] = $tDate['SPF'];}
                        if(@$tDate['EPF']) {$timespan['end']['profile'] = $tDate['EPF'];}
                        if(@$tDate['PRF']) {$timespan['profile'] = $tDate['PRF'];}

                        break;
                    default;
                }//end case


                if(@$tDate['DET']) {$timespan['determination'] = $tDate['DET'];}
                if(@$tDate['CLD'] && $tDate['CLD']!='Gregorian') {$timespan['calendar'] = $tDate['CLD'];}
                if(@$tDate['COM'] && $tDate['COM']!='') {$timespan['comment'] = $tDate['COM'];}
                //labaratory code for C14
                if(@$tDate['COD']) {$timespan['labcode'] = $tDate['COD'];}
                if(@$tDate['CAL']) {$timespan['calibrated'] = 1;}
                //human readable in native calendar
                if(@$tDate['CL2']) {$timespan['native'] = $tDate['CL2'];}

            }  else {
                $timespan = Temporal::_getIntervalForMonth($value);
            }
        }

        return $timespan;
    }

    //
    // Converts dates like 2005-05 to interval 2005-05-01/2005-05-31
    //
    private static function _getIntervalForMonth($value){

        $value = Temporal::dateToISO($value, 2, false, 'now');//standard order, days not need
        $timespan = null;

        if($value){

            if(strpos($value,'-')>0 && substr_count($value,'-')==1){
                //year and month only
                $timespan = array('start'=>array('earliest'=>$value.'-01' ),
                    'end'=>array('latest'=>date("Y-m-t", strtotime($value.'-01')) ));
            }else{
                $timespan = array('timestamp'=>array('in'=>$value, 'type'=>'s'));
            }
        }
        return $timespan;
    }

    //
    // get timespan from timestamp and deviation
    // $direction -1 (sub) 0 (both) +1 (add)
    //
    private static function _getInterval($timestamp, $deviation, $direction=0){

        $is_year_only = ($deviation==null || preg_match('/^P\d+Y$/',$deviation)) && preg_match(REGEX_YEARONLY,$timestamp);

        $dt = Temporal::dateToISO($timestamp, 2, !$is_year_only);

        if($is_year_only){

            $tStart = $dt;
            $tEnd = $dt;
            if($deviation!=null){
                $years = intval(substr($deviation,1,-1));//remove P and Y

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
                    $tStart = new \DateTime($dt);
                    $tEnd = new \DateTime($dt);
                } catch (\Exception  $e){
                }
                $deviation = strtoupper($deviation);
                $i = null;
                try{
                    $i = new \DateInterval($deviation);
                } catch (\Exception  $e){
                }

                if($tStart!=null && $i!=null){
                    if($direction>=0){
                        $tEnd->add($i);
                    }
                    if($direction<=0){
                        $tStart->sub($i);
                    }

                    $format = DATE_8601;
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

    /**
     * Calculates and returns the minimum and maximum ISO date strings for the temporal object.
     *
     * @return array|null An array containing two elements: [min_iso_date, max_iso_date], or null if the date is not valid.
     */
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

    /**
     * Returns a pair of min/max values in decimal format, suitable for storing in recDetailsDateIndex.
     * These values are pre-calculated and stored in the tDate property.
     *
     * @return array|null An array containing [estMinDate, estMaxDate] as decimal values, or null if the date is not valid.
     */
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
        if(@$date['in']){ //timestamp

            $deviation = @$date['deviation']?$date['deviation']
            :@$date[$direction>1?'deviation_positive':'deviation_negative'];

            // c14 date - only years - consider BCE dates before 5K?
            if(@$date['type']=='c'){

                $res = intval($date['in']);
                if($deviation!=null){
                    try{
                        $i = new \DateInterval($deviation);
                        $res = $res + $direction * $i->y;
                        if($res<0){
                            $res = '-'.str_pad(substr(strval($res),1),6,'0',STR_PAD_LEFT);
                        }else{
                            $res = str_pad($res,4,'0',STR_PAD_LEFT);
                        }
                    } catch (\Exception  $e){
                    }
                }

            }else{

                $timestamp = Temporal::_getInterval($date['in'], $deviation, $direction);
                if($timestamp!=null){
                    $res = $direction<0?$timestamp['start']['earliest']:$timestamp['end']['latest'];
                }
                if($res<0){
                    $res = '-'.str_pad(substr(strval($res),1),6,'0',STR_PAD_LEFT);
                }else{
                    $res = str_pad($res,4,'0',STR_PAD_LEFT);
                }
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

    /**
     * Converts a decimal date representation (YYYY.MMDD) to a YYYY-MM-DD string.
     *
     * @param string|float $date The decimal date value.
     * @param bool $lpad_years Optional. If true, pads years with leading zeros (currently not used in logic). Defaults to false.
     * @return string The date in YYYY-MM-DD format, or the original year if no decimal part.
     */
    public static function decimalToYMD($date, $lpad_years=false){

        $date = strval($date);
        $k = strpos($date,'.');
        if($k>0){

            $res = substr($date,0,$k);//year
            $mmdd = substr($date,$k+1);
            if(strlen($mmdd)<3){
                $month = str_pad($mmdd,2,'0',STR_PAD_RIGHT);
            }else{
                $month = substr($mmdd,0,2);
                if(substr($mmdd,2)==0){
                    $day = '01';
                }else{
                    $day = str_pad(substr($mmdd,2), 2,'0',STR_PAD_RIGHT);
                }
            }

            if(intval($res)<0 && strlen($res)<5){
                $res = '-'.str_pad(substr($res,1), 4,'0',STR_PAD_LEFT);
            }

            $res = $res.'-'.$month.'-'.$day;

        }elseif($date=='0'){
                $res = '0000-01-01';
        }else{
                $res = $date;
        }
        return $res;
    }

    //
    // Validates and sanitizes string date value
    // Returns date array (year, month, day...)
    //
    private static function _datePrepare($value, $month_day_order=2){

        if($value==null) {return null;}

        //1. Preparation of sting value - trim, remove "?", remove padding zeroes for year,
        $origValue = $value;
        $origHasDays = false;
        $origHasSeconds = false;
        $date = null;

        $value = str_replace('??-','',$value);
        $value = str_replace('?','',$value);
        $is_bce = false;

        if(strpos(strtolower($value),'bce')!==false){
            $value = trim('-'.str_replace('bce','',strtolower($value)));
        }

        if(!preg_match(REGEX_YEARONLY, $value) && $value[0] == '-'){ //this is BCE with month and day

            $parts = explode('-', str_replace(' ','-',$value));

            if(count($parts) > 2 && empty($parts[0])){
                $is_bce = true;

                if(intval($parts[1]) < 10000){ //less than 10K - month/day allowed
                    //pad to 4 digits to avoid  <70 to 1969 and >69 to 2070
                    $new_year_val = str_pad(strval(intval($parts[1])), 4, '0', STR_PAD_LEFT);
                    $value = str_replace('-'.$parts[1], $new_year_val, $value);
                }else{
                    $value = '-'.$parts[1];//drop months for years <10kya
                }
            }
        }

        if( preg_match(REGEX_YEARONLY, $value) ){ //this is YEAR - only digits with possible minus and spaces for milles

            if(strlen($value)==14){ //20090410000000
                $value = substr($value,0,4).'-'.substr($value,4,2).'-'.substr($value,6,2)
                .' '.substr($value,8,2).':'.substr($value,10,2).':'.substr($value,12,2);
            }elseif(intval($value)>9999){ //20090410
                $nval = substr($value,0,4);
                if(strlen($value)>4) {$nval = $nval.'-'.substr($value,4,2);}
                if(strlen($value)>6) {$nval = $nval.'-'.substr($value,6,2);}
                $value = $nval;
            }else{
                $value = preg_replace('/\s+/', '', $value);//remove spaces
                $date = array('year'=>$value);
            }
        }
        if($date==null){

            if(strpos($value,'XX')>0){
                $date = null;
            }else{

                //replace slashes or dots "/." to dashes "-"
                //reorder month and day
                $value = Temporal::correctDMYorder($value, $month_day_order);
                
                $value = rtrim($value,'-');

                //2. Create php datetime and parse it to array
                try{
                    $origHasSeconds = (substr_count($value,':')>1);
                    $origHasDays = substr_count($value,'-')>1 || substr_count($value,' ')>1 || substr_count($value,'/')>1;


                    $t2 = new \DateTime($value);
                    $datestamp = $t2->format(DATE_8601);
                    $date = date_parse($datestamp);

                    if(is_array($date)){
                        $date['has_days'] = $origHasDays;
                        $date['has_seconds'] = $origHasSeconds;
                    }

                    if($is_bce){
                        $date['year'] = -$date['year'];
                    }

                } catch (\Exception  $e){
                    $date = null;
                    //print $value.' => NOT SUPPORTED<br>';
                }
            }

        }

        return $date;

    }


    /**
     * Converts a date string or a parsed date array to an ISO 8601 formatted string (YYYY-MM-DD HH:MM:SS).
     * Handles various input formats, textual values like "today", "now", and BCE dates.
     *
     * @param string|array $date The date value to convert. Can be a string or a pre-parsed date array (from date_parse).
     * @param int $month_day_order Optional. Specifies the expected order of month and day if ambiguous and separated by '/' or '.'.
     *                             1 for DD/MM, 2 for MM/DD. Defaults to 2.
     * @param bool|string $need_day Optional. If true (default), ensures day is part of the output (e.g., YYYY-MM-01).
     *                              If a string (e.g., '-01-01'), it's appended if only year is present.
     *                              If false, day might be omitted if not present in input and no time.
     * @param string|null $today_date Optional. A reference date string (e.g., 'now') for converting textual values like "today".
     *                                Defaults to null.
     * @return string|null The date as an ISO 8601 string, "Temporal" if the input is a complex Heurist temporal string,
     *                     or null if conversion fails.
     */
    public static function dateToISO($date, $month_day_order=2, $need_day=true, $today_date=null){

        $res = null;

        if(!is_array($date) && $date!=null){

            //check for textual values
            if (strpos($date,'|')!==false || strpos($date,'{"')!==false) {// temporal encoded date - this is for check in import and validation only
                return 'Temporal';
            }else{
                $date = trim($date);

                if($today_date!=null){ // && preg_match('/^today|now|yesterday|tomorrow|-1 day|+1 day$/i',$date)){
                    $t2 = new \DateTime($today_date);

                    $sdate = strtolower($date);
                    if($sdate=='today'){
                        $date = $t2->format('Y-m-d');
                    }elseif($sdate=='now'){
                        $date = $t2->format(DATE_8601);
                    }elseif($sdate=='yesterday'){
                        $t2->modify('-1 day');
                        $date = $t2->format('Y-m-d');//date('Y-m-d',strtotime("-1 days"));
                    }elseif($sdate=='tomorrow'){
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
                    $res = str_pad($res,6,'0',STR_PAD_LEFT);// timeline requires 6 digits for BCE years
                }elseif(abs($date['year'])<10000){
                    $res = str_pad($res,4,'0',STR_PAD_LEFT);

                    if($need_day && count($date) == 1){ // only year, add -01-01 for ISO format
                        if($need_day===true) {$need_day = '-01-01';}
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

                }elseif(@$date['day']){ //&& ($need_day || $has_time)
                    $res = $res.'-'.str_pad(strval($date['day']),2,'0',STR_PAD_LEFT);
                }
            }

            //left pad hours, minutes and seconds
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
                $res = '-'.$res;
            }
        }

        return $res;
    }

    /**
     * Converts a date string or parsed date array into a human-readable string (e.g., "DD Mon YYYY BCE").
     * Handles different calendars and formats years, months, days, and BCE suffix appropriately.
     *
     * @param string|array $value The date value to convert. Can be a string or a pre-parsed date array.
     * @param string|null $calendar Optional. The name of the calendar to consider for formatting (e.g., "Gregorian", "Julian").
     *                              Defaults to null, implying Gregorian/Julian conventions for month names.
     * @return string A human-readable representation of the date, or "unknown temporal format" if conversion fails.
     */
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

                }elseif(@$date['day']){
                    $res2 = $date['day'];
                }
                if(@$date['month']){
                    $res2 = $res2.' '.date('M', mktime(0, 0, 0, $date['month'], 1));//strtotime($date['month'].'01'));
                }

                $res = trim($res2."  ".$res);// day month year

            }else{
                if(@$date['month'] || $has_time){
                    $res = $res.'-'.str_pad(strval($date['month']),2,'0',STR_PAD_LEFT);

                    if(@$date['has_days']!=true && !$has_time){

                    }elseif(@$date['day']){
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
                    }elseif($date['year']<-999999){
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


    /**
     * Corrects date strings by replacing '/' or '.' separators with '-' and reordering day/month parts
     * based on the specified or detected order. Can also check for date ambiguity.
     *
     * @param string $value The date string to correct.
     * @param int|bool $month_day_order Optional. Defines how to interpret D/M or M/D:
     *                                  - 2 (default): Assumes MM-DD or MM/DD.
     *                                  - 1: Assumes DD-MM or DD/MM.
     *                                  - true or 0: Checks for ambiguity (e.g., 01/02/2023) and returns true if ambiguous,
     *                                    false otherwise. Does not reformat in this mode.
     * @return string|bool If $month_day_order is 1 or 2, returns the reformatted date string.
     *                     If $month_day_order is true or 0, returns true if ambiguous, false otherwise.
     */
    public static function correctDMYorder($value, $month_day_order=2){

        $check_ambiguation = ($month_day_order===0 ||  $month_day_order===true);

        $is_dots_slash = false;
        $is_ambiguation = false;

        //chnage / and . separators to -
        $cnt_dash = substr_count($value,'-');
        if($cnt_dash==0){
            $cnt_dots = substr_count($value,'.');//try to convert from format with . fullstops
            $cnt_slash = substr_count($value,'/');//try to convert from format with / separator
            if( $cnt_slash>0){  // 6/2006  =  1-6-2006
                $value = str_replace('/','-',$value);
            }elseif($cnt_dots>0 && preg_match('/\d{1,4}\.\d{1,4}/', $value)){  // 4.3.2006  =  4-3-2006   exclude Mar.2, 2021

                $value = str_replace('.','-',$value);
            }
            $is_dots_slash = ($cnt_dots>0 || $cnt_slash>0);
        }

        if(substr_count($value,'-')==1) { //year and month only

            list($m, $y) = explode('-', $value);


            if(strlen($m)>2 && is_numeric($m)){
                list($y, $m) = explode('-', $value);

            }elseif((strlen($m)>2 && !is_numeric($m)) || $y>12){ //Oct-12
                $value = $y.'-'.$m;

                if($y>22 && $y<100){
                    $value = '19'.$y.'-'.$m;
                }elseif($y>=0 && $y<22){
                    $value = '20'.$y.'-'.$m;
                }

            }elseif( (strlen($y)>2 && !is_numeric($y)) || $y<13){ //09-Nov 09-11

                if($m>22 && $m<100){
                    $value = '19'.$m.'-'.$y;
                }elseif($m>=0 && $m<22){
                    $value = '20'.$m.'-'.$y;
                }
            }
            $is_ambiguation = ($y<13 && $m<13);//ambiguation
        }

        if(substr_count($value,'-')==2 && strpos($value,':')===false) {
            //change d-m-y to y-m-d   only if original value has slashes or dots
            list($m, $d, $y) = explode('-', $value);

            // Mar.2.20  2/Jan/17  for / and . separators is is assumed that year is the last
            // or rare case: year is last  as 10-11-1970
            if( $y>31 || ($is_dots_slash && (!is_numeric($m) || $m<32)) ){

                if($y>22 && $y<100) {$y = '19'.$y; }

                if(strlen($m)>2 || $d>12){ // month is word
                    //$value = $y.'-'.$m.'-'.$d;
                }elseif(strlen($d)>2 || $m>12){ //$d is word month
                    $d2 = $d; $d = $m;  $m = $d2;

                    //$value = $y.'-'.$d.'-'.$m;
                }elseif($d<13 && $m>12){
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

                    $is_ambiguation = ($m<13 && $d<13);//day-month ambiguation
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

        $ret = ($check_ambiguation)?$is_ambiguation :$value;
        return $ret;
    }

    /**
     * Calculates the period (difference) between two dates.
     * Returns an array with years, months, days, full days difference, and the middle date.
     *
     * @param string|array $date1 The first date (string or parsed array).
     * @param string|array $date2 The second date (string or parsed array).
     * @return array|false An array with 'years', 'months', 'days', 'fulldays', 'middle' keys on success,
     *                     or false if date parsing or calculation fails.
     */
    public static function getPeriod($date1, $date2){

        $dt1 = Temporal::_datePrepare($date1);
        $dt2 = Temporal::_datePrepare($date2);

        if(intval($dt1['year'])<-10000 || intval($dt2['year'])<-10000){
            //years only
            return array('years'=>intval($dt2['year']) - intval($dt1['year']));
        }


            if(count($dt1) == 1){ // only year, add -01-01 for ISO format
                $dt1['month'] = 1;
                $dt1['day'] = 1;
            }
            if(count($dt2) == 1){ // only year, add -01-01 for ISO format
                $dt2['month'] = 1;
                $dt2['day'] = 1;
            }


            $early = null;
            $latest = null;
            $err_msg = array();
            $res = true;

            try{
                $dt1 = Temporal::dateToISO($dt1);
                $early = new \DateTime($dt1);
                $early->setTime(0, 0);
            }catch(\Exception $e){
                //$system->addError(HEURIST_INVALID_REQUEST, "An invalid starting date has been provided, " . $e->errorMessage());
            }
            try{
                $dt2 = Temporal::dateToISO($dt2);
                $latest = new \DateTime($dt2);
                $latest->setTime(0, 0);
            }catch(\Exception $e){
                //$system->addError(HEURIST_INVALID_REQUEST, "An invalid latest date has been provided, " . $e->errorMessage());
            }

            if(!$early || !$latest){
                $res = false;
            }elseif($res !== false){

                $diff = $early->diff($latest, true);

                $middle_day = date('Y-m-d', (strtotime($dt2) + strtotime($dt1)) / 2);

                $res = array("days" => $diff->format('%d'), "months" => $diff->format('%M'), "years" => $diff->format('%y'), "middle" => $middle_day, 'fulldays' => $diff->days);
            }else{
                $res = false;
            }

        return $res;

    }

    /**
     * Merges two temporal objects to create a new temporal object that encompasses the range of both.
     * The new temporal object will have the earliest start and latest end of the two inputs.
     * Note: Loses fields like comment, determination, and calendar from the original objects.
     *
     * @param string|array|Temporal $dt1 The first temporal object or its representation.
     * @param string|array|Temporal $dt2 The second temporal object or its representation.
     * @return Temporal|null A new Temporal object representing the merged range, or null if either input is invalid.
     */
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

    /**
     * Converts a temporal value to a human-readable string with various formatting options.
     *
     * @param string|array|Temporal $dt The temporal value (string, array, or Temporal object).
     * @param bool $print_invalid_str Optional. If true and $dt is an invalid string, includes the original string in the error message. Defaults to false.
     * @param int $mode Optional. Output mode:
     *                  0: Simple human-readable (native calendar first if available, then Gregorian).
     *                  1: Compact human-readable (Gregorian first, then native if different and requested by $calendar).
     *                  2: Extended human-readable (all fields listed, pipe-separated by default).
     *                  Defaults to 0.
     * @param string $sep Optional. Separator for extended mode (mode 2). Defaults to '|'.
     * @param string $calendar Optional. Calendar preference for output: "both", "native", "gregorian". Defaults to "both".
     * @return string The human-readable date string, or an error message if invalid.
     */
    public static function toHumanReadable($dt, $print_invalid_str=false, $mode=0, $sep='|', $calendar="both"){

        if($dt){
            $dt2 = new Temporal($dt);
            if($dt2 && $dt2->isValid()) {
                if($mode>0){
                    return $dt2->toReadableExt($sep, ($mode==1), $calendar);
                }else{
                    return $dt2->toReadable($calendar);
                }
            }else{
                $dt = $print_invalid_str && is_string($dt) && !empty($dt) ? '('. $dt .')' : '';
                return 'invalid temporal object'. $dt;
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


    /**
     * Encodes the current temporal object into a JSON string.
     *
     * @return string|null JSON string representation of the temporal object, or null if the date is not valid.
     */
    public function toJSON(){
        if($this->tDate){
            return json_encode($this->tDate);
        }else{
            return null;
        }
    }

    /**
     * Returns an XML string snippet suitable for KML (Keyhole Markup Language) export.
     * Outputs either a `<TimeStamp>` or `<TimeSpan>` element.
     *
     * @return string KML XML string for the temporal object, or an empty string if the date is not valid.
     */
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

        if(!$value){
            return '';
        }

        $ret = '';

        try{
            $i = new \DateInterval($value);
            if($i){
                $ret = ($i->y ? ("$prefix{$i->y} years") :
                    ($i->m ? ("$prefix{$i->m} months") :
                        ($i->d ? ("$prefix{$i->d} days") :'' )));
            }
        } catch (\Exception  $e){
            $ret = '';
        }

        return $ret;
    }

    /**
     * Outputs a human-readable representation of the temporal object.
     * Prioritizes native calendar representation if available and $out_calendar allows.
     *
     * @param string $out_calendar Optional. Specifies calendar output preference:
     *                             "both": Show native then Gregorian in parentheses if different (default).
     *                             "native": Show native calendar representation only.
     *                             "gregorian": Show Gregorian representation only.
     * @return string Human-readable date string, or "undefined temporal" if the date is not valid.
     */
    public function toReadable($out_calendar='both'){
        if($this->tDate){

            $date = $this->tDate;

            $calendar = @$date['calendar'];

            $native = null;
            $is_greg_or_julian = (!$calendar || strtolower($calendar)=='gregorian');// || strtolower($calendar)=='julian'
            if(@$date['native'] && !$is_greg_or_julian){
                $native = @$date['native'];
            }

            if(@$date['timestamp']){

                //one date value with possible deviation
                $res = Temporal::dateToString(@$date['timestamp']['in'], $calendar);
                if($res){
                    $res = $res.Temporal::_deviationSuffix( $date['timestamp'] );
                }

                $prefix = null;
                if(@$date['timestamp']['circa']){
                    $prefix = 'circa ';
                }elseif(@$date['timestamp']['before']){
                    $prefix = 'before ';
                }elseif(@$date['timestamp']['after']){
                    $prefix = 'after ';
                }

                if($prefix){
                    $res = $prefix.$res;
                    if($native){
                        $native = $prefix.$native;
                    }
                }

            }else{

                $from = '';
                $to = '';

                if(@$date['start'] && @$date['start']['in']){
                    $from = Temporal::dateToString($date['start']['in'], $calendar);
                    if($from && strpos($from,'unknown')===false){
                        $from = $from.Temporal::_deviationSuffix( $date['start'] );
                    }
                }elseif(@$date['start']['earliest']){
                    $from = Temporal::dateToString($date['start']['earliest'], $calendar);
                }

                if(@$date['end'] && @$date['end']['in']){
                    $to = Temporal::dateToString($date['end']['in'], $calendar);
                    if($to && strpos($to,'unknown')===false){
                        $to = $to.Temporal::_deviationSuffix( $date['end'] );
                    }
                }elseif(@$date['end']['latest']){
                    $to = Temporal::dateToString($date['end']['latest'], $calendar);
                }
                $res = $from.' to '.$to;
            }

            //add native decription as prefix
            if($native){
                if($out_calendar=='native'){
                    $res = $native; //native only
                }elseif($out_calendar!='gregorian'){
                    //both gregorian and native
                    $res = $native.'  '.$calendar.' (Gregorian '.$res.')';
                }
            }

            return $res;

        }else{
            return 'undefined temporal';
        }
    }


    /**
     * Outputs an extended human-readable representation of the temporal object.
     * Can be compact or a list of all fields.
     *
     * @param string $separator Separator string for non-compact mode (when listing all fields).
     * @param bool $is_compact Optional. If true, provides a compact single-line representation.
     *                         If false (default), lists all fields separated by $separator.
     * @param string|null $out_calendar Optional. Calendar preference for output: "both", "native", "gregorian".
     *                                  Defaults to null (behaves like "both").
     * @return string Extended human-readable date string, or "undefined temporal" if the date is not valid.
     */
    public function toReadableExt($separator, $is_compact=false, $out_calendar=null){

        $tSimpleRange = 'Simple Range';
        $tEarliestEstimate = 'Earliest estimate';
        $tLatestEstimate = 'Latest estimate';

        if($this->tDate){

            $date = $this->tDate;

            $calendar = @$date['calendar'];
            $res = array();
            $is_simple = false;

            $res['Type'] = '';

            $native = null;
            $is_greg_or_julian = (!$calendar || strtolower($calendar)=='gregorian');// || strtolower($calendar)=='julian'
            if(@$date['native'] && !$is_greg_or_julian){
                $native = @$date['native'];
            }

            if(@$date['timestamp']){

                $res['Type'] = ($date['timestamp']['type'] == 'c')?'Radiometric'
                :($date['timestamp']['type'] == 'f'?'Fuzzy date'
                    :'Simple');
                //one date value with possible deviation
                $timestamp = Temporal::dateToString(@$date['timestamp']['in'], $calendar);
                if($timestamp){
                    $timestamp = $timestamp.Temporal::_deviationSuffix( $date['timestamp'] );
                }

                $res['Date']  = $timestamp;

                $prefix = null;
                if(@$date['timestamp']['circa']){
                    $prefix = 'circa ';
                }elseif(@$date['timestamp']['before']){
                    $prefix = 'before ';
                }elseif(@$date['timestamp']['after']){
                    $prefix = 'after ';
                }

                if($prefix){
                    $res['Date'] = $prefix.$res['Date'];
                    if($native){
                        $native = $prefix.$native;
                    }
                }

            }elseif(@$date['start'] && $date['type']=='r'){  //simple range

                $res['Type'] = $tSimpleRange;

                $res[$tEarliestEstimate] = Temporal::dateToString($date['start']['earliest'], $calendar);
                $res[$tLatestEstimate] = Temporal::dateToString($date['end']['latest'], $calendar);

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
                }elseif(@$date['start']['earliest']){
                    $from = Temporal::dateToString($date['start']['earliest'], $calendar);

                    $dt = null;
                    if(@$date['start']['latest']){
                        $dt = Temporal::dateToString($date['start']['latest'], $calendar);
                        if($dt && strpos($dt,'unknown')===false){
                            $is_simple = false;
                        }
                    }

                    $res[$is_simple?$tEarliestEstimate:'Terminus Post Quem'] = $from;
                    if(!$is_simple) {$res['Probable Begin'] = $dt;}
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
                }elseif(@$date['end']['latest']){

                    if(@$date['end']['earliest']){
                        $dt = Temporal::dateToString($date['end']['earliest'], $calendar);
                        if($dt && strpos($dt,'unknown')===false){
                            $res['Probable End'] = $dt;
                            $is_simple = false;
                        }
                    }

                    $to = Temporal::dateToString($date['end']['latest'], $calendar);
                    $res[$is_simple?$tLatestEstimate:'Terminus Ante Quem'] = $to;

                    if(@$date['start']['profile']){
                        $res['End Profile'] = $this->dictProfile[intval($date['end']['profile'])];
                    }
                }

                if($is_simple){
                    Temporal::checkMonthSpan($res, $date);
                }

                $res['Type'] = ($is_simple)?$tSimpleRange:'Fuzzy Range';
            }

            //add native decription as prefix
            //$is_greg_or_julian = (!$calendar ||
            //    strtolower($calendar)=='gregorian');// || strtolower($calendar)=='julian'

            if(@$date['comment']) {$res['Comment'] = $date['comment'];}
            if(@$date['determination']) {$res['Determination'] = $this->dictDetermination[intval($date['determination'])];}
            //labaratory code for C14
            if(@$date['labcode']) {$res['Labaratory Code'] = $date['labcode'];}
            if(@$date['calibrated']) {$res['Calibarated'] = 'yes';}

            $res2 = '';
            if($is_compact){

                if($res['Type']!='Simple' && $res['Type']!=$tSimpleRange){
                    $res2 = $res['Type'].' ';
                    if($native && $out_calendar=='native'){
                        $native = $res['Type'].' '.$native;
                    }
                }

                if($res['Date']){
                    $res2 = $res2 . $res['Date'];
                }elseif($is_simple){

                    $res2 = $res2 . $res[$tEarliestEstimate] . ' .. ' . $res[$tLatestEstimate];
                }else {

                    $res2 = $res2 . '>' .$res['Terminus Post Quem'] . ':' .$res['Probable Begin']
                    .' .. '.
                    $res['Probable End'].':<'.$res['Terminus Ante Quem'];
                }

                $supinfo = array();
                if($res['Determination']) {$supinfo[] = $res['Determination'];}
                if($date['calibrated']) {$supinfo[] = 'Calibarated';}

                //add native decription as prefix
                if($native){
                    if($out_calendar=='native'){
                        $res2 = $native; //native only
                    }elseif($out_calendar!='gregorian'){
                        //both gregorian and native
                        $supinfo[] =  $calendar.' '.$native;
                    }
                }

                if(!empty($supinfo)){
                    $res2 = $res2 . ' (' . implode(', ', $supinfo) . ')';
                }

            }else{
                if($native!=null){
                    $res['Calendar'] = $date['calendar'].' '.$native; //($date['native']?$date['native']:'');
                }
                foreach($res as $key=>$val){
                    $res2 = $res2.$key.': '.$val.$separator;
                }
            }

            return $res2;

        }else{
            return 'undefined temporal';
        }
    }

    /**
     * Converts the current temporal object to the old Heurist plain string format (pipe-separated key-value pairs).
     * Example: |VER=1|TYP=s|DAT=YYYY-MM-DD
     *
     * @return string The temporal object as a plain string, or an empty string if the date is not valid.
     */
    public function toPlain(){


        if($this->isValid()){

            $res = array();
            $date = $this->tDate;

            if(@$date['timestamp']){

                $res['TYP'] = @$date['timestamp']['type'];

                if($res['TYP']=='c'){
                    if($date['timestamp']['bp']){
                        $res['BPD'] = ''.(abs(intval(@$date['timestamp']['in'])-1950));
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

                        //convert floating to range
                        $res['TPQ'] = Temporal::decimalToYMD($date['estMinDate']);
                        $res['TAQ'] = Temporal::decimalToYMD($date['estMaxDate']);

                        $res['RNG'] = $date['timestamp']['deviation'];
                        if(@$date['timestamp']['profile']) {$res['PRF'] = $date['timestamp']['profile'];}

                    }else{
                        $res['TYP'] = 's';
                        if(@$date['timestamp']['circa']){
                            $res['CIR'] = '1';
                        }elseif(@$date['timestamp']['before']){
                            $res['CIR'] = '2';
                        }elseif(@$date['timestamp']['after']){
                            $res['CIR'] = '3';
                        }
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
                    if(@$date['start']['latest']) {$res['PDB'] = $date['start']['latest'];}
                }

                if(@$date['end'] && @$date['end']['in']){
                    $res['TAQ'] = $date['end']['in'];
                }else{
                    if(@$date['end']['earliest']) {$res['PDE'] = $date['end']['earliest'];}
                    $res['TAQ'] = $date['end']['latest'];
                }

                if(@$date['start']['profile']) {$res['SPF'] = $date['start']['profile'];}
                if(@$date['end']['profile']) {$res['EPF'] = $date['end']['profile'];}
                if(@$date['profile']) {$res['PRF'] = $date['profile'];}
            }


            if(@$date['determination']) {$res['DET'] = $date['determination'];}
            if(@$date['calendar']) {$res['CLD'] = $date['calendar'];}
            if(@$date['comment']) {$res['COM'] = $date['comment'];}
            //labaratory code for C14
            if(@$date['labcode']) {$res['COD'] = $date['labcode'];}
            if(@$date['calibrated']) {$res['CAL'] = '1';}
            //human readable in native calendar
            if(@$date['native']) {$res['CL2'] = $date['native'];}

            $res2 = '|VER=1';
            foreach($res as $key=>$val){
                $res2 = $res2.'|'.$key.'='.$val;
            }

            return $res2;
        }else{
            return '';
        }

    } //toPlain

    /**
     * Converts a given date value into the appropriate format for storage in recDetails.dtl_Value.
     * If the date is simple and valid (YYYY-MM-DD, year 0-9999), it's stored as a plain string.
     * Otherwise, it's stored as either a JSON string or an old-style plain temporal string,
     * depending on the $useNewTemporalFormatInRecDetails flag.
     *
     * @param string|array $dtl_Value The input date value.
     * @param bool $useNewTemporalFormatInRecDetails If true, complex dates are stored as JSON.
     *                                               If false, they are stored as old-style plain strings.
     * @return string The processed date string ready for database storage.
     */
    public static function getValueForRecDetails( $dtl_Value, $useNewTemporalFormatInRecDetails ){

        $preparedDate = new Temporal( $dtl_Value );
        if($preparedDate && $preparedDate->isValid()){

            // saves as usual date
            // if date is Simple, 0<year>9999 (CE) and has both month and day
            if($preparedDate->isValidSimple()){
                $dtl_Value = $preparedDate->getValue(true);//returns simple yyyy-mm-dd
            }else{
                if($useNewTemporalFormatInRecDetails){
                    $dtl_Value = $preparedDate->toJSON();//json encoded string
                }else{
                    $dtl_Value = $preparedDate->toPlain();//Plain string (|VER=1|DAT=....)
                }
            }
        }

        return $dtl_Value;
    }

    private static function checkMonthSpan(&$resDate, $date){

        $start = strval(@$date['estMinDate']);
        $end = strval(@$date['estMaxDate']);

        if(empty($start) || empty($end) || substr($start, 0, -2) !== substr($end, 0, -2)){
            return;
        }

        if(preg_match('/01$/', $start) && preg_match('/(?:02(?:28|29)|(?:01|03|05|07|08|10|12)31|(?:04|06|09|11)30)$/', $end)){

            $start = self::decimalToYMD($start);
            $start = new \DateTime($start);

            $resDate['Date'] = $start->format('F Y');
        }
    }


} // end Temporal class
?>
