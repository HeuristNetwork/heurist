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
* Render Entry page
*
* It loads wml file (specified in detail) and applies 2 xsl
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/


function makeEntryPage($record){

 $doc_content = "";
 $fileid = $record->getDet(DT_ENTRY_WML, 'dtfile');
 if(!$fileid){
 	$fileid = $record->getDet(DT_FILE, 'dtfile');
 }
 if($fileid){

 	$file = get_uploaded_file_info_internal($fileid);

 	$filename = $file['fullpath'];

 	if(file_exists($filename)){

		$xml = new DOMDocument();
		$xml->load($filename);

		if($xml){

			$xsl = new DOMDocument;
			$xsl->load(dirname(__FILE__)."/../xsl/wordml2TEI.xsl");

			$proc = new XSLTProcessor();
			$proc->importStyleSheet($xsl);

			$xml->loadXML($proc->transformToXML($xml));
			$xsl->load(dirname(__FILE__)."/../xsl/tei_to_html_basic2.xsl");
			$proc->importStyleSheet($xsl);

			$doc_content = $proc->transformToXML($xml);
		}
	}

    if(!$doc_content){
        error_log(">>>>> CONTENT EMPTY. entry ".$record->id());
    }

 }
?>
<div id="content-left-col">

	<?=$doc_content ?>

	<div id="pagination">
		<a id="previous" href="#">&#171; previous</a>
		<a id="next" href="#">next &#187;</a>
	</div>
</div>
<div id="content-right-col">
<?php
		makeAnnotations($record);
?>
</div>

<div class="clearfix"></div>
<?php
} //end makeEntryPage


function compare_annotations($a1,  $b1, $ind){

                if(intval(@$a1[$ind]) < intval(@$b1[$ind])){
                    return -1;
                }else if (intval(@$a1[$ind]) == intval(@$b1[$ind])) {
                    if(count($a1)==$ind+1){
                        return 0;
                    }else{
                        return compare_annotations($a1,  $b1, $ind+1);
                    }
                }else{
                    return 1;
                }

}
function sort_annotations(Record $a,  Record $b){

            if($a && $b){

                $a1 = explode(',', $a->getDet(DT_ANNOTATION_START_ELEMENT));
                array_push($a1, $a->getDet(DT_ANNOTATION_START_WORD));
                $b1 = explode(',', $b->getDet(DT_ANNOTATION_START_ELEMENT));
                array_push($b1, $b->getDet(DT_ANNOTATION_START_WORD));

                return compare_annotations($a1, $b1, 0);

                //return (intval(@$a1[0]) < intval(@$b1[0])) ?-1:1;
                        ///&& (intval(@$a1[1]) <= intval(@$b1[1])) ?-1:1;
                        //&& (intval($a->getDet(DT_ANNOTATION_START_WORD)) < intval($b->getDet(DT_ANNOTATION_START_WORD)))?-1:1;
                        //(intval(@$a1[2]) < intval(@$b1[2])) &&
                        //(intval(@$a1[3]) < intval(@$b1[3])) ?-1:1;
                        //

/*
                return (intval(@$a1[0]) < intval(@$b1[0]))?-1:
                        (intval(@$a1[1]) < intval(@$b1[1]))?-1:
                        (intval(@$a1[2]) < intval(@$b1[2]))?-1:
                        (intval(@$a1[3]) < intval(@$b1[3]))?-1:
                        (intval($a->getDet(DT_ANNOTATION_START_WORD)) < intval($b->getDet(DT_ANNOTATION_START_WORD)))?-1:1;
*/
            }else{
                return 0;
            }
}

/**
* create array of references and add media divs
*
* @param mixed $record
*/
function makeAnnotations($record){

    global $urlbase, $is_generation;

    $annotations = $record->getRelationRecordByType(RT_ANNOTATION);

    uasort($annotations, 'sort_annotations');

    $refs = "";
    $images = "";

    foreach($annotations as $ann_id => $annotation){

        $hide = false;
        $annotation_type = $annotation->getFeatureTypeName();
        $annotated_rec_id = $annotation->getDet(DT_ANNOTATION_ENTITY);

        if(!$annotated_rec_id){

            // ignore such records
            //error_log("ERROR >>>> entry ".$record->id()." annotated target is not defined for annotation ".$annotation->id());
            continue;

        }else if($annotation_type == "Multimedia"){

                //find and add miltimedia
                $mediarec = getRecordFull($annotated_rec_id);

//print "<!-- rec ".print_r($mediarec, true)." -->";

                if($mediarec && ($mediarec->type()==RT_MEDIA || $mediarec->type()==RT_TILEDIMAGE)){

                    $media_type = $mediarec->getFeatureTypeName();
                    $out = null;

                    if($media_type=="image"){
                        $out = getImageTag($mediarec, 'thumbnail', 'thumbnail2');
                    }else if($media_type=="audio"){
                        $out = getAudioTag($mediarec);
                    }else if($media_type=="video"){
                        $out = getVideoTag($mediarec);
                    }

                    if($out){
                        $images = $images.'<div class="annotation-img annotation-id-'.$ann_id.'">'.$out.'</div>';
                        $hide = true;
                    }

                }else{
                    //  print "<!-- NOT FOUND ".$annotated_rec_id." ".print_r($annotation, true)."-->";

                }

                if(!$mediarec){
                    error_log("ERROR >>>> entry ".$record->id().". annotation ".$annotation->id().". Entity not found ".$annotated_rec_id);
                    continue;
                }


            if ($is_generation){
                $annotated_url = $urlbase.getStaticFileName(RT_MEDIA, null, $mediarec->getDet(DT_NAME), $annotated_rec_id);
            }else {
                $annotated_url = $annotated_rec_id;
            }

        }else{

            if ($is_generation){

                $subtype = $annotation->getDet(DT_ANNOTATION_ENTITY, 'ref2');
                if(!$subtype){
                    $record = getRecordFull($annotated_rec_id);
                    if(!$record){
                        error_log("ERROR >>>> entry ".$record->id().". annotation ".$annotation->id().". Entity not found ".$annotated_rec_id);
                        continue;
                    }
                    $rectype = $record->type();
                }else{
                    $rectype = RT_ENTITY;
                }

                $annotated_url = $urlbase.getStaticFileName($rectype, $annotation->getDet(DT_ANNOTATION_ENTITY, 'ref2'), $annotation->getDet(DT_ANNOTATION_ENTITY, 'ref'), $annotated_rec_id);

            }else {
                $annotated_url = $annotated_rec_id;
            }

        }

        $start_word = $annotation->getDet(DT_ANNOTATION_START_WORD);
        if(!is_numeric($start_word)) $start_word = 'null';
        $end_word = $annotation->getDet(DT_ANNOTATION_END_WORD);
        if(!is_numeric($end_word)) $end_word = 'null';




//error_log(print_r($annotation, true));

                $refs .= "window.refs.push( {
                    startElems : [ ".$annotation->getDet(DT_ANNOTATION_START_ELEMENT)." ],
                    endElems : [ ".$annotation->getDet(DT_ANNOTATION_END_ELEMENT)." ],
                    startWord : ".$start_word.",
                    endWord : ".$end_word.",
                    ".($hide?'hide : true,':'')."
                    targetID : \"".$annotated_rec_id."\",
                    href : \"".$annotated_url."\",
                    recordID : \"".$ann_id."\" } );\n";

    }//foreach
?>

    <script type="text/javascript">
        if (! window["refs"]) {
                window["refs"] = [];
  <?=$refs ?>
        }
    </script>

<?php
    print $images;
}
?>