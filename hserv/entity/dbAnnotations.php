<?php

    /**
    * dbAnnotations 
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

require_once dirname(__FILE__).'/../System.php';
require_once dirname(__FILE__).'/dbEntityBase.php';
require_once dirname(__FILE__).'/../structure/import/dbsImport.php';

class DbAnnotations extends DbEntityBase 
{
    private $dty_Annotation_Info;
    
    
    public function __construct( $system, $data=null ) {
        $this->system = $system;
        $this->data = $data;
        $this->system->defineConstant('RT_MAP_ANNOTATION');
        $this->system->defineConstant('DT_NAME');
        $this->system->defineConstant('DT_URL');
        $this->system->defineConstant('DT_ORIGINAL_RECORD_ID');
        $this->system->defineConstant('DT_ANNOTATION_INFO');
        $this->system->defineConstant('DT_EXTENDED_DESCRIPTION');
        $this->system->defineConstant('DT_MEDIA_RESOURCE');
                
                        
        $this->dty_Annotation_Info = (defined('DT_ANNOTATION_INFO'))
                ? DT_ANNOTATION_INFO
                : 0; 

    }

    public function isvalid(){
        return true;
    }
    
    /**
    *  Search all annotaions for given uri (IIIF manifest)
    *  or particular annotaion id
    * 
    *  Mirador requests our Annotation server (via api/annotations) for annotations per page(canvas).
    * 
    *  @todo overwrite
    */
    public function search(){

        $sjson = array('id'=>"https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", 
                        'type' => 'AnnotationPage');
                       

        //find all annotation for given uri 
                    
        if($this->data['recID']=='pages'){
            $sjson['items'] = array();
            
            if($this->data['uri']){
                $this->data['uri'] = substr($_SERVER['QUERY_STRING'],4);
            }
            $uri = $this->data['uri']; //.(@$this->data['file']?'&file='.$this->data['file']:'');
            $items = $this->findItems_by_Canvas($uri);
            if(is_array($items) && count($items)>0){
                
                foreach($items as $item){
                    $sjson['items'][] = json_decode($item, true);
                }
                
            }else{
                $sjson['items'] = array(); 
            }
        }
        else if($this->data['recID']=='edit'){
            
            $recordId = $this->findRecID_by_UUID($this->data['uuid']);
           
            $redirect = HEURIST_BASE_URL.'/hclient/framecontent/recordEdit.php?db='.HEURIST_DBNAME.'&fmt=edit&recID='.$recordId;
           
            header('Location: '.$redirect);  
            exit;
            
        }else{
            $item = $this->findItem_by_UUID($this->data['recID']);
            if($item!=null){
                $sjson['items'] = array(json_decode($item, true));     
            }
        }
            
        return $sjson;
    }

    //
    // returns Annotation description by Canvas URI
    //    
    private function findItems_by_Canvas($canvasUri){
        if($this->dty_Annotation_Info>0 && defined('DT_URL')){
            $query = 'SELECT d2.dtl_Value FROM recDetails d1, recDetails d2 WHERE '
            .'d1.dtl_DetailTypeID='.DT_URL .' AND d1.dtl_Value="'.$canvasUri.'"'
            .' AND d1.dtl_RecID=d2.dtl_RecID'
            .' AND d2.dtl_DetailTypeID='.$this->dty_Annotation_Info;
            $items = mysql__select_list2($this->system->get_mysqli(), $query);
            return $items;
        }else{
            return array();
        }
    }

    //
    //
    //    
    private function findItem_by_UUID($uuid){
        if($this->dty_Annotation_Info>0 && defined('DT_ORIGINAL_RECORD_ID')){
            $query = 'SELECT d2.dtl_Value FROM recDetails d1, recDetails d2 WHERE '
            .'d1.dtl_DetailTypeID='.DT_ORIGINAL_RECORD_ID .' AND d1.dtl_Value="'.$uuid.'"'
            .' AND d1.dtl_RecID=d2.dtl_RecID'
            .' AND d2.dtl_DetailTypeID='.$this->dty_Annotation_Info;
            $item = mysql__select_value($this->system->get_mysqli(), $query);
            return $item;
        }else{
            return array();
        }
    }
    
    //
    //
    //    
    private function findRecID_by_UUID($uuid){
        if(defined('DT_ORIGINAL_RECORD_ID')){
            $query = 'SELECT dtl_RecID FROM recDetails WHERE dtl_DetailTypeID='.DT_ORIGINAL_RECORD_ID.' AND dtl_Value="'.$uuid.'"';
            $recordId = mysql__select_value($this->system->get_mysqli(), $query);
            return $recordId;
        }else{
            return 0;
        }
    }
    
    //
    //
    //
    public function delete($disable_foreign_checks = false){
        
        if($this->data['recID']){  //annotation UUID
            
            //validate permission for current user and set of records see $this->recordIDs
            if(!$this->_validatePermission()){
                return false;
            }
            
            //remove annotation with given ID
            $recordId = $this->findRecID_by_UUID($this->data['recID']);
            if($recordId>0){
                return recordDelete($this->system, $recordId);
            }else{
                $this->system->addError(HEURIST_NOT_FOUND, 'Annotation record to be deleted not found');
                return false;
            }
            
        }else{
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid annotation identificator');
            return false;
        }        
    }
    
    //
    //
    // 
    private function _assignField(&$details, $id, $value){
        if(intval($id)>0){
            $id = intval($id);
        }else if(defined($id)){
            $id = constant($id);
        }
            //$id = "t:".$id;
        if(intval($id)>0){            
            if(@$details[$id]){
                $details[$id][0] = $value;
            }else{
                $details[$id][] = $value;        
            }
        } 
    }
    
    //
    //
    //    
    public function save(){
         //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){
            return false;
        }
/*        
        annotation: {
          canvas: this.canvasId,
          data: JSON.stringify(annotation),
          uuid: annotation.id,
        },
*/      
        if(!defined('RT_MAP_ANNOTATION')){

            $isOK = false;
            $importDef = new DbsImport( $this->system );
            if($importDef->doPrepare(  array(
            'defType'=>'rectype', 
            'databaseID'=>2, 
            'conceptCode'=>array('2-101'))))
            {
                $isOK = $importDef->doImport();
            }
            if(!$isOK){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                    'Can not add annotation. This database does not have "Map/Image Annotation" record type. '
                    .'Import required record type');
                return false;
            }
            
            //redefine constants            
            $this->system->defineConstant('RT_MAP_ANNOTATION', true);
            $this->system->defineConstant('DT_ORIGINAL_RECORD_ID', true);
            $this->system->defineConstant('DT_ANNOTATION_INFO', true);
        }
                 
        if( !defined('DT_ANNOTATION_INFO') || !defined('DT_ORIGINAL_RECORD_ID')){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 
                    'Can not add annotation. This database does not have "Annotation" (2-1098) or "Original ID" fields (2-36). '
                    .'Import record type "Map/Image Annotation" to get this field');
            return false;
        }
        
        
        $this->system->defineConstant('DT_SHORT_SUMMARY');
        $this->system->defineConstant('DT_THUMBNAIL');

        $anno = $this->data['fields']['annotation'];
        
        $details = array();
        
        if($this->is_addition){
             $recordId = 0;
        }else{     
            //find record id by annotation UID
            $recordId = $this->findRecID_by_UUID($anno['uuid']);

            if($recordId>0){
                //@todo make common function findOriginalRecord (see importAction)
                $query = "SELECT dtl_Id, dtl_DetailTypeID, dtl_Value, ST_asWKT(dtl_Geo), dtl_UploadedFileID FROM recDetails WHERE dtl_RecID=$recordId ORDER BY dtl_DetailTypeID";
                $dets = mysql__select_all($this->system->get_mysqli(), $query);
                if($dets){
                    foreach ($dets as $row){
                        $bd_id = $row[0];
                        $field_type = $row[1];
                        if($row[4]){ //dtl_UploadedFileID
                            $value = $row[4];
                        }else if($row[3]){
                            $value = $row[2].' '.$row[3];
                        }else{
                            $value = $row[2];
                        }
                        //if(!@$details[$field_type]) $details[$field_type] = array(); //
                        $details[$field_type][] = $value; //"t:"
                    }
                }            
            }else{
               $recordId = 0; //add new annotation 
            }
        }
        //"body":{"type":"TextualBody","value":"<p>RR Station</p>"},
        $anno_dec = json_decode($anno['data'], true);
        if(is_array($anno_dec)){
        
            if(@$anno_dec['body']['type']=='TextualBody'){
                $this->_assignField($details, 'DT_NAME', substr(strip_tags($anno_dec['body']['value']),0,50));
                $this->_assignField($details, 'DT_SHORT_SUMMARY', $anno_dec['body']['value']); 
            }

            //thumbnail
            // "selector":[{"type":"FragmentSelector","value":"xywh=524,358,396,445"}
            if(@$anno['canvas']){ //canvas defined on addition only
                
                //at the moment it creates thumbnail on addition only
                //@todo - check  FragmentSelector and compare with $details[$this->dty_Annotation_Info]
                // recreate thumbnail if annotated area is changed
                if(is_array(@$anno_dec['target']) && @$anno_dec['target']['selector'] && defined('DT_THUMBNAIL')){
                    
                    foreach ($anno_dec['target']['selector'] as $selector){
                        if(@$selector['type']=='FragmentSelector'){
                            $region = @$selector['value'];
                            if($region){
                                $region = substr($region, 5);
                                
                                // https://fragmentarium.ms/metadata/iiif/F-hsd6/canvas/F-hsd6/fol_2r.jp2.json
                                // https://gallica.bnf.fr/iiif/ark:/12148/bpt6k9604118j/canvas/f11/ 
                                $url2 = $anno['canvas'];
                                $url = $anno['canvas'];
                                
                                if(@$anno['manifestUrl']){ //sourceRecordId
                                    //find image service uri by canvas in manifest
                                    $iiif_manifest_url = filter_var($anno['manifestUrl'], FILTER_SANITIZE_URL);
                                    $iiif_manifest = loadRemoteURLContent($anno['manifestUrl']); //retrieve iiif manifest into manifest
                                    $iiif_manifest = json_decode($iiif_manifest, true);
                                    if($iiif_manifest!==false && is_array($iiif_manifest)){

                                    //"@context": "http://iiif.io/api/presentation/2/context.json"    
                                    //sequences->canvases->images->resource->service->@id
                                    if(@$iiif_manifest['@context']=='http://iiif.io/api/presentation/2/context.json'){
                                        if(is_array(@$iiif_manifest['sequences']))
                                        foreach($iiif_manifest['sequences'] as $seq){
                                            if(is_array(@$seq['canvases']))
                                            foreach($seq['canvases'] as $canvas){
                                                if($canvas['@id']==$url && is_array(@$canvas['images'])){
                                                    foreach($canvas['images'] as $image){
                                                        $url2 = @$image['resource']['service']['@id'];
                                                        if($url2!=null) {
                                                            $url = $url2;
                                                            break 3;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        
                                    }else{ //version 3
                                    //"@context": "http://iiif.io/api/presentation/3/context.json" 
                                    //items(type:Canvas)->items[AnnotationPage]->items[Annotation]->body->service[0]->id
                                        
                                        if(is_array(@$iiif_manifest['items']))
                                        foreach($iiif_manifest['items'] as $canvas){
                                            if(@$canvas['type']=='Canvas' && $canvas['id']==$url && is_array(@$canvas['items'])){
                                                foreach($canvas['items'] as $annot_page){
                                                    if(@$annot_page['type']=='AnnotationPage' && is_array(@$annot_page['items']))
                                                    foreach($annot_page['items'] as $annot){
                                                        if(@$annot['type']=='Annotation')
                                                        if(@$annot['body']['type']=='Image'){
                                                            $url2 = @$annot['body']['service']['id'];
                                                            if($url2!=null) {
                                                                $url = $url2;
                                                                break 3;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        
                                        
                                    }
                                        
                                    }
                                }
                                
                                if(strpos($url, '/canvas/')>0){
                                    //remove /canvas to get image url
                                    $url = str_replace('/canvas/','/',$url);
                                }
                                // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
                                $url = $url.'/'.$region.'/!200,200/0/default.jpg';

                                $tmp_file = HEURIST_SCRATCH_DIR.'/'.filter_var($anno['uuid']).'.jpg';
                                //tempnam(HEURIST_SCRATCH_DIR,'iiif_thumb');
                                //tempnam()
                                $res = saveURLasFile($url, $tmp_file);

                                if($res>0){
                                    $entity = new DbRecUploadedFiles($this->system);

                                    $dtl_UploadedFileID = $entity->registerFile($tmp_file, null); //it returns ulf_ID

                                    if($dtl_UploadedFileID===false){
                                        $err_msg = $this->system->getError();
                                        $err_msg = $err_msg['message'];
                                        $this->system->clearError();  
                                    }else{
                                        $this->_assignField($details, 'DT_THUMBNAIL', $dtl_UploadedFileID[0]); 
                                    }
                                }
                                
                                
                                break;   
                            }
                        }
                    }
                }
                //url to page/canvas
                $details[DT_URL][] = $anno['canvas'];
            }else {
                if($this->is_addition && @$anno_dec['target']['source']){ //page is not changed on edit
                    $this->_assignField($details, 'DT_URL', $anno_dec['target']['source']); 
                }
            }
        
        }
        
        $this->_assignField($details, $this->dty_Annotation_Info, $anno['data']); 
        $this->_assignField($details, 'DT_ORIGINAL_RECORD_ID', $anno['uuid']); 
        
        if($anno['sourceRecordId']>0){
            //link referenced image record with annotation record
            $this->_assignField($details, 'DT_MEDIA_RESOURCE', $anno['sourceRecordId']); 
        }
        
        //record header
        $record = array();
        $record['ID'] = $recordId;
        $record['RecTypeID'] = RT_MAP_ANNOTATION;
        $record['no_validation'] = true;
        $record['details'] = $details;
        
        $out = recordSave($this->system, $record, false, true);
        return $out;
    }
}
?>
