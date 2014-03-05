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
    * Dictionary of Sydney applications - Record class and some constants arrays
    *
    * Array of terms ARE HARDCODED
    * @todo load terms array from database
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2013 University of Sydney
    * @link        http://sydney.edu.au/heurist
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  applications
    */

    $featureNames = array(
        RT_WEBLINK	=> array("link",	"Links"),
        RT_MEDIA	=> array("media",	"Media"),
        RT_ENTRY	=> array("entry",	"Entries"),
        RT_ROLE 	=> array("role",	"Roles"),
        RT_MAP 		=> array("map",		"Maps"),
        RT_TERM 	=> array("term",	"Subjects"),
        RT_CONTRIBUTOR	=> array("contributor",	"Contributors"),
        RT_ANNOTATION	=> array("annotation",	"Mentioned in")
    );

    /*$featureTypeCodes = array(
    RT_ANNOTATION	=> array("annotation", DT_ANNOTATION_TYPE),
    RT_ENTITY	=> array("entity", DT_ENTITY_TYPE),
    RT_FACTOID	=> array("factoid", DT_FACTOID_TYPE),
    RT_CONTRIBUTOR	=> array("contributor",	DT_CONTRIBUTOR_TYPE),
    RT_MEDIA	=> array("image",	DT_TYPE_MIME),
    RT_TILEDIMAGE => array("",	DT_TILEDIMAGE_TYPE),
    RT_ROLE 	=> array("role", DT_ROLE_TYPE),
    );*/
    $featureTypeCodes = array(
        RT_ANNOTATION	=> DT_ANNOTATION_TYPE,
        RT_ENTITY	=> DT_ENTITY_TYPE,
        RT_FACTOID	=> DT_FACTOID_TYPE,
        RT_CONTRIBUTOR	=> DT_CONTRIBUTOR_TYPE,
        RT_MEDIA	=> DT_TYPE_MIME,
        RT_TILEDIMAGE => DT_TILEDIMAGE_TYPE,
        RT_ROLE 	=> DT_ROLE_TYPE
    );

    //@todo - load from defTerms
    $typeValues = array(

        //entities
        3291 => array("artefact"	,	"Artefacts"),
        3294 => array("building"	,	"Buildings"),
        3296 => array("event"   	,	"Events"),
        3298 => array("natural" 	,	"Natural features"),
        3300 => array("organisation",	"Organisations"),
        3301 => array("person"  	,	"People"),
        3302 => array("place"		,	"Places"),
        3305 => array("structure"	,	"Structures"),

        //roles
        3322 => "Milestone",
        3323 => "Name",
        3324 => "Occupation",
        3325 => "Property",
        3326 => "Relationship",
        3327 => "Type",

        //factoids
        3307 => "Milestone",
        3308 => "Name",
        3309 => "Occupation",
        3310 => "Position",
        3311 => "Property",
        3312 => "Relationship",
        3313 => "TimePlace",
        3314 => "Type",

        // media
        534 => array("image", "Pictures"),
        536 => array("image", "Pictures"),
        537 => array("image", "Pictures"),
        540 => array("image", "Pictures"),
        3330 => array("audio", "Sounds"),
        3331 => array("audio", "Sounds"),
        3329 => array("video", "Videos"),


        //contributor
        3286 => "author",
        3287 => "institution",
        3288 => "public",
        3289 => "supporter",

        //annotation types
        3335 => "Entity",
        3337 => "Gloss",
        3334 => "Multimedia",
        3333 => "Multimedia",
        3336 => "Text",

        544 => "image",
        545 => "map",

        547 => "gmapimage",
        548 => "maptiler",
        549 => "virtual earth",
        550 => "zoomify",

    );

    function getMimeTypeValue($code){
        if($code==537) {return "image/jpeg";}
        else if($code==540) {return "image/png";}
            return "";
    }
    function getTypeValue($code){
        global $typeValues;

        if($code){
            $res = @$typeValues[$code];
            return is_array($res)?$res[0]:$res;
        }
        return null;
    }
    //name -> code
    function getCodeByName($typename){

        global $featureNames, $typeValues;

        if(is_numeric($typename)){
            return $typename;
        }

        foreach ($featureNames as $code=>$name){
            if(is_array($name) && $name[0]==$typename){
                return $code;
            }
        }

        foreach ($typeValues as $code=>$name){
            if(is_array($name) && $name[0]==$typename){
                return $code;
            }
        }
        return null;
    }
    //code -> name
    function getNameByCode($_code, $plural=false){

        global $featureNames, $typeValues;

        if(!is_numeric($_code)){
            return null;
        }

        foreach ($featureNames as $code=>$name){
            if($code==$_code && is_array($name)){
                return $name[$plural?1:0];
            }
        }

        foreach ($typeValues as $code=>$name){
            if($code==$_code && is_array($name)){
                return $name[$plural?1:0];
            }
        }
        return null;
    }

    /**
    *
    *
    */
    class Record {

        private $header = array();
        private $details = array();
        private $relations = array();

        public function init($arr){
            $this->header = $arr;
        }
        public function init2($arr){
            $this->header = array('rec_id'=>@$arr['rec_id'], 'rectype'=>@$arr['rectype'], 'url'=>@$arr['url']);
        }

        public function id(){
            return @$this->header['rec_id'];
        }
        public function type(){
            return intval(@$this->header['rectype']);
        }
        public function url(){
            return @$this->header['url'];
        }

        public function toString(){
            return "header: ".print_r($this->header, true)."<br>".
            "details: ".print_r($this->details, true)."<br>".
            "relations: ".print_r($this->relations, true)."<br>";
        }


        /* various util function
        function getEntityCodeName($type){
        global $entityTypes;
        return @$entityTypes[$type][0];
        }
        function getEntityTypeName($type){
        global $entityTypes;
        return @$entityTypes[$type][2];
        }
        function getEntityPluralName($type){
        global $entityTypes;
        return @$entityTypes[$type][3];
        }
        */

        /**
        * put your comment there...
        *
        */
        function type_classname(){

            global $featureNames;

            if (RT_ENTITY == $this->type() || RT_MEDIA == $this->type()){
                return $this->getFeatureTypeName(); //@$typeValues[ intval($this->getDet(DT_ENTITY_TYPE))][0];
            }else{
                return @$featureNames[$this->type()][0];
            }
        }
        //TODO use references arrays
        public function type_name(){
            return @$this->header['rectype'];
        }


        //details ---------------------------------

        public function setDetails($_details){
            $this->details = $_details;

            //error_log("DT=".print_r($this->details,true));
        }
        public function addDetail($arr){
            array_push($this->details, $arr);
            /*
            $dt_id = @$arr['dttype'];
            if($dt_id){
            $this->details[$dt_id] = array('dtvalue'=>@$arr['dtvalue'], 'dtfile'=>@$arr['dtfile'], 'geo'=>@$arr['geo'], 'ref'=>@$arr['ref'], 'ref2'=>@$arr['ref2']);
            }
            */
        }

        public function getDet($typeid, $kind='dtvalue'){

            foreach ($this->details as $det) {
                if($det['dttype']==$typeid){
                    if(@$det[$kind]){
                        return $det[$kind];
                    }
                }
            }
            
            //error_log("DT=".$typeid."  ".print_r($this->details, true));
            
            return "";
            /*
            $dt = @$this->details[$typeid];
            if($dt){
            if(is_array($dt)){
            return @$dt[$kind];
            }else{
            return $dt;
            }
            }else{
            return "";
            }*/
        }
        public function getDetails($typeid, $kind='dtvalue'){

            $res = array();
            foreach ($this->details as $det) {
                if($det['dttype']==$typeid){
                    if(@$det[$kind]){
                        array_push($res, $det[$kind]);
                    }
                }
            }
            return $res;
        }

        //
        public function getDescription(){
            
            $val = $this->getDet(DT_SHORTSUMMARY);           
            if(!$val){
                    $val = $this->getDet(DT_DESCRIPTION);           
            }
            return $val;
        }
        
        /*
        public function getDetails($typeid, $kind='dtvalue'){
        $dt = @$this->details[$typeid];
        $res = array();
        if($dt){
        if(is_array($dt){
        if(@$dt[$kind]){
        array_push($res, $dt[$kind]);
        }
        }else{
        array_push($res, $dt)
        }
        }
        return $res;
        }
        */


        public function getFeatureTitle(){
            global $featureNames, $featureTypeCodes, $typeValues;

            if(($this->type()==RT_MEDIA || $this->type()==RT_ENTITY) && @$featureTypeCodes[$this->type()]){

                $dt_code = $this->getDet($featureTypeCodes[$this->type()]);
                $res = @$typeValues[$dt_code];

            }else{
                $res = @$featureNames[$this->type()];
            }
            if($res){
                return array_pop($res);
            }
            return null;
        }

        /**
        * returns type detail id by record type id
        * for example entitytype if record type is entity
        */
        public function getFeatureTypeName(){

            global $featureTypeCodes;

            $dt_id = @$featureTypeCodes[$this->type()]; //get detail id by rectype id
            if($dt_id){
                $dt_code = $this->getDet($dt_id);
                return getTypeValue($dt_code);
            }else{
                return null;
            }
        }

        // related records-------------------------------------


        public function addRelation(Record $frec){
            $this->relations[$frec->id()] = $frec;
        }

        public function getRelationRecords(){
            return $this->relations;
        }


        public function getRelationRecord($search_id){
            if($search_id){
                return @$this->relations[$search_id];
            }
            return null;
        }

        function getRelationRecordByType($type, $subtype=null){

            $res = array();

            if($type){
                foreach ($this->relations as $id => $record) {
                    if($type == $record->type() &&
                        ($subtype==null || $subtype==$record->getFeatureTypeName()) )
                    {
                        $res[$id] = $record;
                    }
                }
            }
            return $res;
        }

        //for terms
        function getRelationRecordByRef($type, $refvalue){

            $res = array();

            if($type){
                foreach ($this->relations as $id => $record) {
                    if($type == $record->type() &&	$refvalue==$record->getDet(DT_NAME,'ref'))
                    {
                        $res[$id] = $record;
                    }
                }
            }
            return $res;
        }

        /**
        * return role name for given factoid
        *
        * @param Record $factoid
        */
        function getRoleName(Record $factoid){

            $res = $factoid->getDet(DT_FACTOID_ROLE, 'ref');

            if($res == 'Generic'){
                $res = $factoid->getDet(DT_NAME);
            }else if( $factoid->getDet(DT_FACTOID_TARGET)== $this->id() && $factoid->getDet(DT_FACTOID_ROLE, 'ref2') ){
                $res = $factoid->getDet(DT_FACTOID_ROLE, 'ref2');
            }

            return $res;
        }

        /*
        function getLink(){
        return getLinkTag($this->type(), $this->getDet(DT_ENTITY_TYPE), $this->getDet(DT_NAME), $this->id());
        }
        */
    }
?>
