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
* Utilities to render sepcific HTML elements for record
* IMG, Gallery, Timemap, Title
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

require_once("Record.php");
require_once("utilsFile.php");

/**
* Render title
*
* @param Record $record
*/
function makeTitleDiv(Record $record){
    global $urlbase;

    $type = $record->type_classname();
    ?>
    <div id="heading" class="title-<?=$type ?>">
        <h1>
            <?=$record->getDet(DT_NAME); ?>
        </h1>

        <span id="sub-title">
            <?php
            switch ( $record->type() ) {
                case RT_ENTITY:
                    print getEntityTypeList($record);
                    break;
                case RT_ENTRY:
                    print makeAuthorList($record);
                    //TODO print makeEntryByline($record);
                    break;
                case RT_ROLE:
                    //TOD print getRoleType($record);   //1084-95
                    break;
                case RT_TERM:
                    print "Subject";
                    break;
                case RT_CONTRIBUTOR:
                    print "Contributor";
                    break;
            }
            ?>
        </span>
        <?php
        if(RT_ENTRY == $record->type()) {
            ?>
            <span id="extra">
                <?=makeLicenseIcon($record); ?>
            </span>
            <span id="citation">
                <a class="citation-link" href="<?=$urlbase ?>citation/<?=$record->id() ?>">Cite this</a>
            </span>
            <?php
        }
        ?>
    </div>
    <?php
}

/**
* list of enity types taken from factoids type "Type"
*
* @param mixed $record
*/
function getEntityTypeList($record){

    $res = "";

    //find factoid with type 'Type'
    $factoids = $record->getRelationRecordByType(RT_FACTOID, 'Type');

    $cnt = 0;
    foreach ($factoids as $factoid) {
        if($cnt>0){
            $res = $res.",";
        }
        $res = $res.$record->getRoleName($factoid);
        $cnt++;
    }
    //todo - sort by date

    return $res;
}

/**
* Returns media url for record: thumbnail of full size
*
* @param mixed $record
* @param mixed $type
*/
function getFileURL(Record $record, $type=null){

    $val="";
    if($type=='thumbnail2' || $type=='thumbnail'){
        $val = $record->getDet(DT_FILE_THUMBNAIL, 'dtfile');
    }
    if($val==""){
        $val = $record->getDet(DT_FILE, 'dtfile');
    }

    $res = "";
    if($val){
        $file = get_uploaded_file_info_internal($val);

        if($type=='thumbnail' || $type=='thumbnail2'){
            $res = $file['thumbURL'];
        }else{
            $res = $file['URL'];
        }
    }
    return $res;
}

/**
* Render IMG tag
*
* @param Record $record
* @param mixed $classname
* @param mixed $type
* @return mixed
*/
function getImageTag(Record $record, $classname=null, $type=null){

    global $urlbase, $is_generation;

    $url = getFileURL($record, $type);

    if($url){
        //$dt0 = reset($record);
        //$id = $dt0['rec_id'];

        $id = $record->id();

        $size = '';
        $islink = false;

        if($type=='large'){
            $size = 'style="{max-width:700px;max-height:640px;}"';
        }else if($type=='wide'){
            $size = 'style="{max-width:800px;max-height:400px;}"';
        }else if($type=='medium'){
            $size = 'height="180"';
            $islink = true;
        }else if($type=='thumbnail2'){
            $size = 'style="{max-width:140px;max-height:140px;}"';
            $islink = true;
        }
        if($classname){
            $classname = 'class="'.$classname.'"';
        }

        if($url){
            $out = '<img '.$classname.' '.$size.' alt="'.$record->getDet(DT_NAME).'"src="'.$url.'"></img>';
        }else{
            $out = $record->getDet(DT_NAME);
        }

        if($islink){
            $out = getLinkTag3(RT_MEDIA, null, $out, $id);
        }

        return $out;
    }
    else{
        return "";
    }
}

/**
* Render HTML element for Audio
*
* @param Record $record
*/
function getAudioTag(Record $record){
    global $urlbase;

    $id = $record->id();

    return getLinkTag3(RT_MEDIA, null, '<img src="'.$urlbase.'images/img-entity-audio.jpg" alt="'.$record->getDet(DT_NAME).'"/>', $id);

    //old way return '<a href="'.$id.'" class="popup preview-'.$id.'">'.
    //		'<img src="'.$urlbase.'images/img-entity-audio.jpg" alt="'.$record->getDet(DT_NAME).'"/></a>';
}

/**
* Render HTML element for Video
*
* @param Record $record
*/
function getVideoTag(Record $record){
    $id = $record->id();

    $out = getImageTag($record, 'thumbnail', 'thumbnail2');
    if($out==""){
        return getLinkTag($record);
        //old way return '<a href="'.$id.'" class="popup preview-'.$id.'">'.$record->getDet(DT_NAME).'</a>';
    }else{
        return $out;
    }
}


/**
* Render image gallery for given record
*
* @param mixed $record
*/
function makeImageGallery(Record $record){

    $relations = $record->getRelationRecordByType(RT_MEDIA, 'image');

    if($relations){

        $main_img_id = $record->getDet(DT_MEDIA_REF);

        $rec_id = $record->id();

        $out = "";
        $cnt = 1;

        foreach ($relations as $rel_id => $related) {
            if($rel_id==$main_img_id) continue;

            if($cnt>3){
                if($cnt==4){
                    $divclass = 'no-right-margin';
                }else if($cnt % 4 == 0){
                    $divclass = 'top-margin no-right-margin';
                }else{
                    $divclass = 'top-margin';
                }
                $divclass = 'class="'.$divclass.'"';
            }else{
                $divclass = '';
            }

            $out = $out."<div ".$divclass.">".getImageTag($related,'thumbnail','thumbnail2')."</div>";
            if($cnt>1 && $cnt % 4 == 0){
                $out = $out.'<div class="clearfix"></div>';
            }

            $related = null;
            $cnt++;
        }

        if($cnt>1){
            print '<div class="list-left-col list-image" title="Pictures"></div>';
            print '<div class="list-right-col">';
            print '<div class="list-right-col-content entity-thumbnail">';
            print $out;
            print '</div></div><div class="clearfix"></div>';

        }
    }
}

/**
* Render list of audio/video elements for given record
*
* @param Record $record
* @param mixed $type
*/
function makeAudioVideoGallery(Record $record, $type){

    $relations = $record->getRelationRecordByType(RT_MEDIA, $type);

    if(count($relations)>0){

        $rec_id = $record->id();

        ?>
        <div class="list-left-col list-<?=$type ?>" title="<?=($type=='audio'?'Sound':'Video') ?>"></div>
        <div class="list-right-col">
            <div class="list-right-col-<?=($type=='audio'?'audio':'content entity-thumbnail') ?>">
                <?php
                foreach ($relations as $rel_id => $related) {
                    if($type=='audio'){
                        print getAudioTag($related);
                    }else{
                        print getVideoTag($related);;
                    }
                }
                ?>
            </div>
        </div>
        <div class="clearfix"></div>
        <?php
    }
}

/**
* Render timemap (map and timeline) elements for given records (for entities)
*
* @param mixed $record
*/
function makeTimeMap(Record $record){

    $factoids = $record->getRelationRecordByType(RT_FACTOID);

    if($factoids){

        $rec_id = $record->id();

        $items = "";
        $cnt = 0;
        $cnt_geo = 0;
        $cnt_time = 0;

        foreach ($factoids as $factoid) {

            $geo = $factoid->getDet(DT_GEO,'geo');
            $date_start = $factoid->getDet(DT_DATE_START);

            if($geo || $date_start){

                if($cnt>0){
                    $items = $items.',';
                }


                $title = $record->getRoleName($factoid);
                if($title){

                    $entity_source = $factoid->getDet(DT_FACTOID_SOURCE);
                    $entity_target = $factoid->getDet(DT_FACTOID_TARGET);

                    if($factoid->getDet(DT_FACTOID_SOURCE,'ref')){
                        $title = $title." - ".$factoid->getDet(DT_FACTOID_SOURCE,'ref');
                    }
                }else{
                    $title = $record->getDet(DT_NAME);
                }

                $items = $items."getTimeMapItem(".$rec_id.",'".
                $record->type_classname()."',\"".     //getRecordTypeClassName($record).
                $title."\",'".
                $date_start."','".
                ($factoid->getDet(DT_DATE_END)?$factoid->getDet(DT_DATE_END):date("Y"))."','".
                $factoid->getDet(DT_GEO)."','".  //shape type
                $geo."')";



                //??? $factoid = null;
                if($geo){
                    $cnt_geo++;
                }
                if($date_start){
                    $cnt_time++;
                }
                $cnt++;
            }
        }

        if($cnt>0){
            /*,{
            type: "kml",
            options: {
            //url: "http://dictionaryofsydney.org/kml/full/rename/<xsl:value-of select="id"/>"
            url: "http://dictionaryofsydney.org/kml/full/<xsl:value-of select="id"/>.kml"
            }
            }*/
            ?>
            <div id="map" class="entity-map<?=($cnt_geo>0)?'':' hide' ?>">
            </div>
            <div class="clearfix"></div>
            <div id="timeline-zoom" <?=($cnt_time>0)?'':'class="hide"' ?>>
            </div>
            <div id="timeline" class="entity-timeline<?=($cnt_time>0)?'':' hide' ?>"> <!--  style="height: 52px;" -->
            </div>
            <script type="text/javascript">
                var mapdata = {
                    //mini: true,
                    timemap: [
                        {
                            type: "basic",
                            options: { items: [<?=$items ?>] }
                        }
                    ],
                    layers: [],
                    count_mapobjects: <?=$cnt_geo ?>
                };
                setTimeout(function(){ initMapping("map", mapdata, true); }, 500);

            </script>
            <div class="clearfix"></div>
            <?php

        }
    }
}

/**
* put your comment there...
*
* @param Record $record
*/
function makeFactoids(Record $record){

    $factoids = $record->getRelationRecordByType(RT_FACTOID, 'Type');

    if($factoids && count($factoids)>1){
        makeFactoidGroup($record, $factoids, 'Types');
    }

    $factoids = $record->getRelationRecordByType(RT_FACTOID, 'Name');
    makeFactoidGroup($record, $factoids, 'Names');

    $factoids = $record->getRelationRecordByType(RT_FACTOID, 'Milestone');
    makeFactoidGroup($record, $factoids, 'Milestones');

    $factoids = $record->getRelationRecordByType(RT_FACTOID, 'Relationship');
    makeFactoidGroup($record, $factoids, 'Relationships');

    $factoids = $record->getRelationRecordByType(RT_FACTOID, 'Occupation');
    makeFactoidGroup($record, $factoids, 'Occupations');

    $factoids = $record->getRelationRecordByType(RT_FACTOID, 'Position');
    makeFactoidGroup($record, $factoids, 'Positions');

    $factoids = $record->getRelationRecordByType(RT_FACTOID, 'Property');
    makeFactoidGroup($record, $factoids, 'Property');
}

function sort_factoids(Record $a,  Record $b){
    if($a && $b){

        $a1 = $a->getDet(DT_DATE_START);
        $b1 = $b->getDet(DT_DATE_START);

        if($a1 < $b1){
            return -1;
        }else if ($a1==$b1) {

            $a1 = $a->getDet(DT_DATE_END);
            $b1 = $b->getDet(DT_DATE_END);
            if($a1 < $b1){
                return -1;
            }else if ($a1==$b1) {

                $a1 = $a->getDet(DT_FACTOID_ROLE,'ref');
                $b1 = $b->getDet(DT_FACTOID_ROLE,'ref');
                if($a1 < $b1){
                    return -1;
                }else if ($a1==$b1) {
                    return 0;

                }else{
                    return 1;
                }
            }else{
                return 1;
            }

        }else{
            return 1;
        }

    }else{
        return 0;
    }
}
function sort_byname(Record $a,  Record $b){
    if($a && $b){
        return (//$a->getFeatureTypeName() < $b->getFeatureTypeName() &&
            $a->getDet(DT_NAME) < $b->getDet(DT_NAME)) ?-1: 1;
    }else{
        return 0;
    }
}
//for annotation list
function sort_byrefname(Record $a,  Record $b){
    if($a && $b){
        return ($a->getDet(DT_ANNOTATION_ENTRY,'ref') < $b->getDet(DT_ANNOTATION_ENTRY,'ref') ) ?-1: 1;
    }else{
        return 0;
    }
}


function makeFactoidGroup(Record $record, $factoids, $title){
    if($factoids && count($factoids)>0){

        uasort($factoids, 'sort_factoids');

        ?>
        <div class="entity-information">
            <div class="entity-information-heading">
                <?=$title ?>
            </div>

            <?php
            foreach ($factoids as $factoid) {
                //echo $factoid->id()."  ".$factoid->getDet(DT_NAME)."<br>";
                makeFacoid($record, $factoid, $title);
            }
            ?>
            <div class="clearfix"></div>
        </div>
        <?php
    }
}

function makeFacoid(Record $record, Record $factoid, $title){

    $factoid_type = $factoid->getFeatureTypeName();
    $role_rec_id = $factoid->getDet(DT_FACTOID_ROLE);
    $role_n = $factoid->getDet(DT_FACTOID_ROLE, 'ref');
    $role_name	= $record->getRoleName($factoid);
    //$role_name2 = $factoid->getDet(DT_FACTOID_ROLE, 'ref2');

    if($role_n =='Generic'){
        print '<div class="entity-information-col01-02">'.$factoid->getDet(DT_NAME).'</div>';
    }else if($factoid_type=='Type'){

        print '<div class="entity-information-col01-02">'.getLinkTag3(RT_ROLE, null, $role_name, $role_rec_id).'</div>';

    }else{

        $out = '<div class="entity-information-col01">';
        if($factoid_type == 'Occupation' || $factoid_type == 'Position'){
            //$factoid->toString().
            $out = $out.getLinkTag3(RT_ROLE, null, $role_name, $role_rec_id);
        }else{
            $out = $out.$role_name;
        }
        $out = $out.'</div><div class="entity-information-col02">';

        $entity_source = $factoid->getDet(DT_FACTOID_SOURCE);
        $entity_target = $factoid->getDet(DT_FACTOID_TARGET);

        if($entity_target== $record->id() && $entity_source){

            $out = $out.getLinkTag($factoid, DT_FACTOID_SOURCE);

        }else if($entity_source == $record->id() && $entity_target){

            $out = $out.getLinkTag($factoid, DT_FACTOID_TARGET);

        }else if($factoid->getDet(DT_FACTOID_DESCR)){

            $out = $out.$factoid->getDet(DT_FACTOID_DESCR);
        }

        print $out.'</div>';
    }

    print '<div class="entity-information-col03">'.formatDate($factoid->getDet(DT_DATE_START)).'</div>';

    if($factoid->getDet(DT_DATE_START)!=$factoid->getDet(DT_DATE_END)){
        print '<div class="entity-information-col04"> - </div>';
        print '<div class="entity-information-col05">'.formatDate($factoid->getDet(DT_DATE_END)).'</div>';
    }

    print '<div class="clearfix"></div>';
}

function formatDate($val){
    if(strpos($val,'-')>0){
        $date = new DateTime($val);
        return $date->format("j M Y"); //for >1970 only date("j M Y", strtotime($val))." ".$val;
    }else{
        return $val;
    }
}

//
//

/**
* put your comment there...
*
* @param Record $record
*/
function makeEntriesInfo(Record $record){

    $entries = $record->getRelationRecordByType(RT_ENTRY);
    $cnt = 0;

    foreach ($entries as $ent_id => $entry) {

        $content_class = ($cnt+1 == count($entries))?"list-right-col-content":"list-right-col-content margin";

        $morelink = getLinkTag($entry, "more &#187;");
        ?>
        <div class="list-left-col list-entry" title="Entries"></div>
        <div class="list-right-col">
            <div class="entity-entry">
                <div class="list-right-col-heading">
                    <h2>
                        <?=$entry->getDet(DT_NAME) ?>
                    </h2>
                    <?=makeAuthorList($entry) ?>

                    <span class="copyright">
                        <?=makeLicenseIcon($entry) ?>
                    </span>
                </div>
                <div class="<?=$content_class ?>">
                    <p>
                        <?=$entry->getDescription() ?>
                        <br/><?=$morelink ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    print '<div class="clearfix"></div>';

}

/**
* put your comment there...
*
* @param Record $record
*/

function getAuthors(Record $record){
    //find all contributors

    $rec_ids = $record->getDetails(DT_CONTRIBUTOR_REF);
    $authors = array();
    $supportes = array();

    foreach ($rec_ids as $rec_id){
        $author = $record->getRelationRecord($rec_id);
        if(!$author){
            $author = getRecordFull($rec_id);
            if($author){
                $record->addRelation($author);
            }else{
                continue;
            }
        }

        if($author->getFeatureTypeName()=='supporter'){
            array_push($supportes, $author); //->getDet(DT_NAME));
        }else{
            array_push($authors, $author);
        }
    }

    return array($authors, $supportes);

}

function makeAuthorList(Record $record){

    $a_s = getAuthors($record);
    $authors = $a_s[0];
    $supportes = $a_s[1];

    if(count($authors)>0 || count($supportes)>0){

        uasort($authors, 'sort_byname');
        asort($supportes);

        $cnt = 0;
        $len = count($authors);

        $out = "";
        foreach ($authors as $author){
            $cnt++;
            if($cnt==$len && $len>1){
                $out = $out." and ";
            }else if ($cnt>1){
                $out = $out.", ";
            }
            $out = $out.getLinkTag($author);
        }
        $out = "by ".$out.", ".date('Y', strtotime($record->getDet(DT_DATE)));
        if(count($supportes)){
            $out = $out."<br/>supported by "; //.implode(",", $supportes);
            $cnt = 0;
            $len = count($supportes);
            foreach ($supportes as $author){
                $cnt++;
                if($cnt==$len && $len>1){
                    $out = $out." and ";
                }else if ($cnt>1){
                    $out = $out.", ";
                }
                $out = $out.getLinkTag($author);
            }
        }

        print $out;
    }
}


/**
* put your comment there...
*
* @param mixed $type
* @param mixed $subtype
* @param mixed $name
* @param mixed $id
*/
function getLinkTag($record, $factoid_type=null){

    global $urlbase, $is_generation;

    if($record->type()==RT_FACTOID){

        if($factoid_type==null){
            $factoid_type = DT_FACTOID_TARGET;
        }

        $id = $record->getDet($factoid_type);
        if($id)
        {
            $type = RT_ENTITY;
            $subtype = $record->getDet($factoid_type, 'ref2');
            $title = $record->getDet($factoid_type, 'ref');
            $url = "";

            if(!$title && $factoid_type == DT_FACTOID_SOURCE) {
                return $record->getDet(DT_FACTOID_DESCR);
            }
        }else{
            return "";
        }

    }else if($record->type()==RT_ANNOTATION)
    {
        $id = $record->getDet(DT_ANNOTATION_ENTRY);
        $type = RT_ENTRY;
        $subtype = null;
        $title = $record->getDet(DT_ANNOTATION_ENTRY,'ref');
        $url = '#ref='.$record->id();
    }
    else
    {
        $id = $record->id();
        $type = $record->type();
        $subtype = $record->getDet(DT_ENTITY_TYPE);
        $title = $record->getDet(DT_NAME);
        $url = ($record->type()==RT_WEBLINK) ?$record->url() :"";

        if($factoid_type){
            $subtype = $factoid_type;
        }
    }

    return getLinkTag3($type, $subtype, $title, $id, $url);
}

function getLinkTag3($type, $subtype, $title, $id, $url=""){

    global $urlbase, $is_generation, $path_preview;

    $linktext = $title;

    if($subtype=="noclass"){
        $classname = "";
    }else if( $subtype && !is_numeric($subtype) ){

        $linktext = $subtype;
        $classname = "";
        $subtype = null;

    }else{
        $classname = 'preview-'.$id;
        if(strpos($url,"#ref=")===0){
            //add annotation id
            $classname = $classname."A".substr($url,5);

            if ($is_generation){
                $preview_id = $id."A".substr($url,5);
                $keep = @$_REQUEST['name'];
                $_REQUEST['name'] = $preview_id;
                ob_start();
                require(dirname(__FILE__)."/pagePreview.php");
                $out = ob_get_clean();
                if($out){
                    saveAsFile($out, $path_preview."/".$preview_id);
                }else{
                    add_error_log("ERROR >>>> Cannot create PREVIEW. ".$preview_id);
                }
                $_REQUEST['name'] = $keep;
            }
        }

        if($type==RT_MEDIA){
            $classname = 'popup '.$classname;
        }
    }

    if($type!=RT_WEBLINK){
        if ($is_generation){
            $url = $urlbase.getStaticFileName($type, $subtype, $title, $id).$url;
        }else {
            $url = $id.$url;
        }
    }
    return '<a href="'.$url.'" class="'.$classname.'">'.$linktext.'</a>';
}

/**
* put your comment there...
*
* @param Record $record
*/
function makeLicenseIcon(Record $record){

    $dt = $record->getDet(DT_COPYRIGHTS);
    if($dt==3319){
        print '<a rel="license" target="_blank" href="http://creativecommons.org/licenses/by/2.5/au/">
        <img alt="Creative Commons License" src="http://i.creativecommons.org/l/by/2.5/au/80x15.png"/>
        </a>';
    }else if($dt==3320){
        print '<a rel="license" target="_blank" href="http://creativecommons.org/licenses/by-sa/2.5/au/">
        <img alt="Creative Commons License" src="http://i.creativecommons.org/l/by-sa/2.5/au/80x15.png"/>
        </a>';
    }

}

/**
* output list of external links
*
* @param Record $record
*/
function makeLinksInfo(Record $record){

    $entries = $record->getRelationRecordByType(RT_WEBLINK);
    $cnt = 0;

    foreach ($entries as $ent_id => $entry) {

        $name = $entry->getDet(DT_NAME);
        $check = "demographics";
        if(substr($name, -strlen($check)) == $check){
            $content_class = ($cnt+1 == count($entries))
            ?"list-right-col-content"
            :"list-right-col-content margin";
            ?>
            <div class="list-left-col list-link" title="Demographics"></div>
            <div class="list-right-col">
                <div class="entity-entry">
                    <div class="list-right-col-heading">
                        <h2>
                            <?=$name ?>
                        </h2>
                        <div class="<?=$content_class ?>">
                            <p><a href="<?=$entry->url() ?>" target="_blank">more &#187;</a></p>
                        </div>
                    </div>
                </div>

            </div>
            <?php
        }
    }
    print '<div class="clearfix"></div>';

}

/**
* put your comment there...
*
* @param Record $record
*/
function makeConnectionMenu(Record $record){

    if($record){

        print '<div id="connections"><h3>Connections</h3>';

        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTITY,'place') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTITY,'person') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTITY,'artefact') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTITY,'building') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTITY,'event') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTITY,'natural') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTITY,'organisation') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTITY,'structure') );

        makeConnectionMenuItem( $record->getRelationRecordByType(RT_ENTRY) );

        makeConnectionMenuItem( $record->getRelationRecordByType(RT_MEDIA, 'image') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_MEDIA, 'audio') );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_MEDIA, 'video') );

        makeConnectionMenuItem( $record->getRelationRecordByType(RT_MAP) );
        makeConnectionMenuItem( $record->getRelationRecordByType(RT_TERM) );

        if($record->type()!=RT_ENTRY){
            makeConnectionMenuItem( $record->getRelationRecordByType(RT_ANNOTATION) );
        }

        makeConnectionMenuItem( $record->getRelationRecordByType(RT_WEBLINK) );

        print '</div>';

    }
}


function makeConnectionMenuItem($entries){

    if(count($entries)>0){

        $entry0 = reset($entries); //get first


        $type = $entry0->type();
        $title = $entry0->getFeatureTitle();
        $classname = $entry0->type_classname();

        //sort by name
        if($type==RT_ANNOTATION){
            uasort($entries, 'sort_byrefname');
        }else{
            uasort($entries, 'sort_byname');
        }

        ?>
        <div class="menu">
            <h4 class="menu-<?=$classname ?>"><?=$title ?></h4>
        </div>
        <div class="submenu">
            <ul>
                <?php
                foreach ($entries as $entry) {
                    print '<li>'.getLinkTag($entry).'</li>';
                }
                ?>
            </ul>
        </div>
        <?php

    }
}

function makeTermsMenuItem($record, $type){

    $entries = $record->getRelationRecordByRef(RT_TERM, $type);
    if(count($entries)>0){

        $entry0 = reset($entries);

        $title = ($type==1)?"Broader subjects":"Narrower subjects";
        $classname = $record->type_classname();

        uasort($entries, 'sort_byname');
        ?>
        <div class="menu">
            <h4 class="menu-<?=$classname ?>"><?=$title ?></h4>
        </div>
        <div class="submenu">
            <ul>
                <?php
                foreach ($entries as $entry) {
                    print '<li>'.getLinkTag($entry).'</li>';
                }
                ?>
            </ul>
        </div>
        <?php

    }
}

/**
* Render sorted list of related record
*
* @param mixed $entries
*/
function makeSubjectItem($entries){

    if(count($entries)>0){

        $entry0 = reset($entries);


        $type = $entry0->type();
        $title = $entry0->getFeatureTitle();
        $classname = $entry0->type_classname();

        //sort by name
        uasort($entries, 'sort_byname');
        ?>
        <div class="list-left-col list-<?=$classname ?>" title="<?=$title ?>"></div>
        <div class="list-right-col">
            <div class="list-right-col-browse">
                <ul>
                    <?php
                    foreach ($entries as $entry) {
                        print '<li>'.getLinkTag($entry).'</li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
        <div class="clearfix"></div>
        <?php

    }
}

/**
* Render media attribution
*
* @param Record $record
*/
function makeMediaAttributionStatement(Record $record){


    $out = '';
    if($record->getDet(DT_CONTRIBUTOR_FREETEXT)){
        $out = $out.'By '.$record->getDet(DT_CONTRIBUTOR_FREETEXT).'. ';
    }

    $contributorid = $record->getDet(DT_CONTRIBUTOR_REF);
    if($contributorid){

        $author = $record->getRelationRecord($contributorid);
        if(!$author){
            $author = getRecordFull($contributorid);
        }
        if($author){
            if($author->getDet(DT_CONTRIBUTOR_ATTRIBUTION)){
                $out = $out.$author->getDet(DT_CONTRIBUTOR_ATTRIBUTION).' ';
            }else{
                $out = $out.'Contributed by ';
            }
            $out = $out.getLinkTag($author);
        }
    }

    if($record->getDet(DT_CONTRIBUTOR_ID)){

        if($record->getDet(DT_CONTRIBUTOR_ITEM_URL)){
            //.$record->getDet(DT_CONTRIBUTOR_ID)
            $linkid = '<a href="'.$record->getDet(DT_CONTRIBUTOR_ITEM_URL).'" target="_blank">'.$record->getDet(DT_CONTRIBUTOR_ID).'</a>';
        }else{
            $linkid = $record->getDet(DT_CONTRIBUTOR_ID); //@todo apply linkify
        }

        $out = $out.' ['.$linkid.']';
    }

    if($record->getDet(DT_COPYRIGHT_STATEMENT)){
        $out = $out.' ('.$record->getDet(DT_COPYRIGHT_STATEMENT).')'; //@todo apply linkify
    }

    return $out;
}

/**
* Render record preview
*
* @param mixed $record
* @param mixed $intemplate
*/
function makePreviewDiv($record, $record_annotation)
{
    $type = $record->type_classname();

    $image = "";


    if($record->type()==RT_ENTITY){
        $main_img_id = $record->getDet(DT_MEDIA_REF);
        if($main_img_id){
            $rec = $record->getRelationRecord($main_img_id);
            if(!$rec){
                $rec = getRecordFull($main_img_id);
            }

            if($rec){
                $image = getImageTag($rec, 'thumbnail', 'thumbnail2');
            }
        }
    }
    if(!$image){

        if($record->type()==RT_MEDIA && $record->getFeatureTypeName()=="audio"){
        }else{
            $image = getImageTag($record, 'thumbnail', 'thumbnail2');
        }
    }

    $description = null;
    if($record_annotation!=null){
        $description = $record_annotation->getDet(DT_SHORTSUMMARY);
    }
    if(!$description){
        $description = $record->getDet(DT_SHORTSUMMARY);
    }

    //ob_start();
    ?>
    <div class="balloon-container">
        <div class="balloon-top"/>
        <div class="balloon-middle">
            <div class="balloon-heading balloon-<?=$type ?>">
                <h2>
                    <?=$record->getDet(DT_NAME) ?>
                </h2>
            </div>
            <div class="balloon-content">
                <?=$image ?>
                <p>
                    <?=$description ?>
                </p>
                <div class="clearfix">
                </div>
            </div>
        </div>
        <div class="balloon-bottom"/>
    </div>
    <?php
    //return ob_get_clean();
}
?>