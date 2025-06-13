<?php
/**
* ImportAnnotations.php  - Class ImportAnnotations
* 
* Handles the import of IIIF annotations.
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\records\import
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\records\import;
use hserv\utilities\USanitize;
use hserv\entity\DbAnnotations;
use hserv\entity\DbRecUploadedFiles;

require_once dirname(__FILE__).'/../edit/recordModify.php';

set_time_limit(0);

/**
 * Class ImportAnnotations
 *
 * Handles the import of IIIF (International Image Interoperability Framework) annotations
 * into the Heurist system. It processes IIIF manifests (v2 or v3), extracts annotation
 * data, and creates or updates corresponding "Annotation" records in Heurist.
 *
 * The class can find manifests that are registered in the system (as `ULF_IIIF` files)
 * or process a specific list of manifest files. It links imported annotations to the
 * Heurist record associated with the manifest file.
 *
 * This class is typically invoked by the `importController.php`.
 *
 */
class ImportAnnotations{

    /**
     * @var \hserv\System The Heurist system object.
     */
    private $system;

    /**
     * @var array|null Array of obfuscated file IDs (ulf_ObfuscatedFileID) for specific IIIF manifest files to process.
     *                 If null or empty, all registered ULF_IIIF files might be processed (depending on context).
     */
    private $ulfIDs; // files ids to check manifest

    /**
     * @var int|string|null Identifier for the progress tracking session.
     */
    private $progressSessionId = 0;

    /**
     * @var bool Whether to attempt creation of thumbnails for imported annotations.
     */
    private $createThumbnail = false;

    /**
     * @var bool Whether to directly link the created Heurist Annotation record
     *           to the Heurist file record representing the IIIF manifest.
     */
    private $linkAnnotationWithManifest = false;

    /**
     * @var DbAnnotations|null Instance of DbAnnotations for database interactions related to annotations.
     */
    private $dbAnno;

    /**
     * Constructor for ImportAnnotations.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array|null $params An array of parameters for the import operation:
     *                           - 'ids': (array|string) Specific ulf_ObfuscatedFileIDs of manifests to process.
     *                           - 'session': (int|string) Progress session ID.
     *                           - 'create_thumb': (int|bool) 1 or true to create thumbnails.
     *                           - 'direct_link': (int|bool) 1 or true to link annotations to the manifest file record.
     */
    public function __construct( $system, $params = null ) {
        $this->system = $system;

        $this->ulfIDs = @$params['ids'];

        $this->progressSessionId = @$params['session'];

        $this->createThumbnail = @$params['create_thumb']==1;
        $this->linkAnnotationWithManifest = @$params['direct_link']==1;

    }

    /**
     * Finds IIIF manifest files registered in the `recUploadedFiles` table.
     *
     * Queries for files where `ulf_OrigFileName` is `ULF_IIIF`.
     * If `$this->ulfIDs` is set, the search is filtered to include only those manifest files.
     *
     * @return array An associative array mapping `ulf_ID` to `ulf_ExternalFileReference` (manifest URL)
     *               for each found manifest. Returns an empty array if none are found.
     */
    private function findRegisteredManifests(){

        $mysqli = $this->system->getMysqli();
        $query = 'SELECT ulf_ID, ulf_ExternalFileReference FROM recUploadedFiles WHERE ulf_OrigFileName="'.ULF_IIIF.'"';

        if(!empty($this->ulfIDs)){
            $ids = prepareStrIds($this->ulfIDs);
            if(!empty($ids)){
                if(count($ids)==1){
                    $query = $query . ' AND ulf_ObfuscatedFileID='.$ids[0];
                }else{
                    $query = $query . ' AND ('.implode(' OR ulf_ObfuscatedFileID =',$ids).')';
                }
            }
        }

        return mysql__select_assoc2($mysqli, $query);

    }


    /**
     * Downloads and parses an IIIF manifest or annotation list from a given URL.
     *
     * It attempts to load the content from the URL, decode it as JSON, and then
     * checks if it's a valid IIIF Manifest (v2 or v3) or an AnnotationList (v2 or v3).
     * If it's a manifest, it calls `getIiifAnnotationList` for v2 manifests to extract annotations.
     * For annotation lists, it directly accesses the 'resources' (v2) or assumes a similar structure for v3.
     *
     * @param string $url The URL of the IIIF manifest or annotation list.
     * @return array|false An array of annotation objects if successfully processed,
     *                     or `false` if there's an error (e.g., URL not accessible, invalid JSON,
     *                     not a recognized IIIF type). Errors are added to `$this->system`.
     */
    private function processManifest( $url ){

        $annotations = null;

        $iiif_manifest = loadRemoteURLContent($url);//check that json is iiif manifest

        if(!$iiif_manifest){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Manifest file '.$url.' is not accessible');
            return false;
        }

        $iiif_manifest = json_decode($iiif_manifest, true);
        // Check if the JSON was decoded successfully
        if($iiif_manifest!==false && is_array($iiif_manifest))
        {
            // Check if the content is valid
            if(@$iiif_manifest['@type']=='sc:Manifest' ||   //v2
                @$iiif_manifest['type']=='Manifest')        //v3
            {
                $annotations = $this->getIiifAnnotationList($iiif_manifest);

            }elseif( $this->isAnnotationList($iiif_manifest) ||   //v2
                    @$iiif_manifest['type']=='AnnotationList')        //v3
            {
                $annotations = $iiif_manifest['resources'] ?? [];
            }

        }else{
            $msg = '';
            if (json_last_error() !== JSON_ERROR_NONE) {
                    $msg = json_last_error_msg();
            }
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Manifest file is not valid. '.$msg);
            return false;
        }


        return $annotations;
    }

    //
    //
    //
    /**
     * Checks if the given decoded JSON element represents an IIIF v2 AnnotationList.
     *
     * @param array $ele The decoded JSON element.
     * @return bool True if it's an `sc:AnnotationList`, false otherwise.
     */
    private function isAnnotationList($ele){
        return @$ele['@type']=='sc:AnnotationList';
    }

    //
    //
    //
    /**
     * Extracts annotations from a parsed IIIF v2 manifest.
     *
     * It traverses the manifest structure: `sequences -> canvases -> otherContent`.
     * If an `sc:AnnotationList` is found within `otherContent`, it recursively calls
     * `processManifest` with the URL of that annotation list to fetch and parse it.
     *
     * @param array $iiif_manifest The parsed IIIF v2 manifest array.
     * @return array An array of annotation objects. Can be empty if no annotation lists are found or processed.
     */
    private function getIiifAnnotationList($iiif_manifest){

        //find annoatations in sequences->[canvases->[otherContent->["@type": "sc:AnnotationList"]
        $annotations = array();

        foreach($iiif_manifest['sequences'] as $seq){
            foreach($seq['canvases'] as $canvas){
                foreach($canvas['otherContent'] as $annoList){
                    if($this->isAnnotationList($annoList)){
                        $annotations = $this->processManifest(@$annoList['@id']);
                    }
                }
            }
        }

        return $annotations;
    }

    /**
     * Prepares for the main annotation import execution.
     *
     * This method performs preliminary checks:
     * - Ensures the current user has administrator privileges.
     * - Calls `findRegisteredManifests()` to get the list of manifest URLs to process.
     *
     * @return array|false An array of manifest URLs (ulf_ID => manifest_url) if successful and manifests are found.
     *                     Returns `['total'=>0]` if no manifests are found.
     *                     Returns `false` if the user is not an administrator (error added to `$this->system`).
     */
    private function prepareExecution(){

        //must be database manager
        if(!$this->system->isAdmin()){
            $this->system->addError(HEURIST_REQUEST_DENIED, 'To perform this action you must be logged in as Administrator of group \'Database Managers\'');
            return false;
        }

        //finds manifests
        $urls = $this->findRegisteredManifests();

        if(empty($urls)){
            $urls = array('total'=>0);
        }
        return $urls;
    }

    /**
     * Executes the main annotation import process.
     *
     * This method orchestrates the import:
     * 1. Calls `prepareExecution()` to perform initial checks and get the list of manifest URLs.
     * 2. Initializes a progress session if a `progressSessionId` was provided.
     * 3. Iterates through each manifest URL:
     *    a. Calls `processManifest()` to download and parse the manifest, extracting annotations.
     *    b. Retrieves the Heurist record ID(s) linked to the current manifest file (`ulf_ID`).
     *       The first linked record ID is taken as the `source_rec_id` for the annotations.
     *    c. If annotations are found, calls `processAnnotations()` to save them.
     *    d. Updates the progress session and checks for termination requests via `progressSession()`.
     * 4. Finalizes and removes the progress session.
     *
     * @return array|false An array summarizing the import results:
     *                     - 'total': Total number of manifests processed.
     *                     - 'processed': Number of manifests successfully processed (annotations extracted or confirmed none).
     *                     - 'missed': Number of manifests that could not be processed (e.g., URL error, invalid manifest).
     *                     - 'added': Array of record IDs for newly created Heurist Annotation records.
     *                     - 'updated': Array of record IDs for updated Heurist Annotation records.
     *                     - 'retained': Array of record IDs for Annotation records that were processed but resulted in no change.
     *                     - 'without_annotations': Array mapping ulf_ID to source_rec_id for manifests found to have no annotations.
     *                     - 'issues': Array mapping source_rec_id to error messages for specific processing issues.
     *                     Returns `false` if the operation is terminated by the user via the progress mechanism.
     *                     Returns the result of `prepareExecution` if it indicates an initial failure (e.g. no admin rights, no manifests).
     */
    public function execute(){

        $urls = $this->prepareExecution();

        if(!$urls || array_key_exists('total',$urls) ){
            return $urls;
        }

        $tot_count = count($urls);

        if($this->progressSessionId){
            //init progress session
            mysql__update_progress(null, $this->progressSessionId, true, '0,'.$tot_count);
        }

        $this->dbAnno = new DbAnnotations($this->system);
        $dbUlf  = new DbRecUploadedFiles($this->system);

        $result = array('total'=>$tot_count,
                         'processed'=>0,
                         'missed'=>0,
                         'added'=>array(),
                         'updated'=>array(),
                         'retained'=>array(),
                         'without_annotations'=>array(),
                         'issues'=>array()
                         );

        //loop manifests
        foreach($urls as $ulf_ID=>$manifest_url){

            $annotations = $this->processManifest($manifest_url);
            $result['processed']++;

            //find linked records
            $rec_ids = $dbUlf->getMediaRecords($ulf_ID, 'file_fields', 'rec_ids');

            $source_rec_id = $rec_ids?$rec_ids[0]:0;
            if($source_rec_id==0){
                continue;
            }

            if($annotations===false){
                $result['missed']++;
                $err_msg = $this->system->getError();
                $issues[$source_rec_id] = $err_msg['message'];
                $this->system->clearError();
                continue;
            }

            if(empty($annotations)){
                $result['without_annotations'][$ulf_ID] = $source_rec_id;
                continue;
            }

            $this->processAnnotations($annotations, $source_rec_id, $manifest_url, $ulf_ID, $result);
          
            if($this->progressSession($result)){
                return false;
            }
          

        }//for

        if($this->progressSessionId){
            //remove session file
            mysql__update_progress(null, $this->progressSessionId, false, 'REMOVE');
        }

        return $result;
    }
    
    //
    //
    /**
     * Updates the progress session and checks for user-initiated termination.
     *
     * This method is called periodically during the import process (e.g., every 5 manifests).
     * It updates the progress tracker on the server side using `mysql__update_progress`.
     * It also checks if the progress tracker indicates a 'terminate' signal from the user.
     *
     * @param array $result The current result array, containing 'cnt_processed' (actual count of items processed for progress update)
     *                      and 'total' (total items for progress calculation). Note: the code uses $result['cnt_processed']
     *                      but the execute method populates $result['processed']. This might be a discrepancy.
     * @return bool True if the import process should be terminated, false otherwise.
     */
    private function progressSession($result){
        
            if($this->progressSessionId && @$result['processed'] % 5 == 0){ // Use @ to safely access 'processed'
                $current_val = mysql__update_progress(null, $this->progressSessionId, true, $result['processed'].','.$result['total']);
                if($current_val && $current_val=='terminate'){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Operation is terminated by user');
                    return true;
                }
            }
        
            return false;
    }

    //
    //
    //
    /**
     * Processes a list of annotations for a given source record and manifest.
     *
     * Iterates through each annotation object in the `$annotations` array.
     * For each annotation, it prepares data for `DbAnnotations->save()` and calls it.
     * The `DbAnnotations->save()` method handles the logic of creating a new Heurist Annotation
     * record or updating an existing one based on the annotation data.
     *
     * Updates the `$result` array (passed by reference) with counts of added, updated,
     * or retained annotation records, and logs any issues.
     *
     * @param array $annotations An array of annotation objects extracted from a manifest.
     * @param int $source_rec_id The Heurist record ID to which these annotations are primarily related
     *                           (usually the record linked to the manifest file).
     * @param string $manifest_url The URL of the manifest from which annotations were extracted.
     * @param int $ulf_ID The `ulf_ID` of the Heurist file record representing the manifest.
     *                    Used if `$this->linkAnnotationWithManifest` is true.
     * @param array &$result The main result summary array, passed by reference to update statistics.
     */
    private function processAnnotations($annotations, $source_rec_id, $manifest_url, $ulf_ID, &$result)
    {
        foreach ($annotations as $anno){

            $this->dbAnno->setData(array('fields'=>array('annotation'=>$anno, 'sourceRecordId'=>$source_rec_id, 'manifestUrl'=>$manifest_url)));
            $res = $this->dbAnno->save($this->createThumbnail, $this->linkAnnotationWithManifest?$ulf_ID:0);

            if($res===false){

                $err_msg = $this->system->getError();
                $result['issues'][$source_rec_id] = $err_msg['message'];
                $this->system->clearError();
                continue;
                
            }elseif(is_array($res) && $res['status']!=HEURIST_OK){

                $result['issues'][$source_rec_id] = $res['message'];
                continue;
            }
            

            $rec_id = $res['data'];
            if(@$res['is_new']){
                $result['added'][] = $rec_id;
            }elseif(@$res['is_retained']){
                if(!in_array($rec_id, $result['added'])){
                    $result['retained'][] = $rec_id;
                }
            }else{
                $result['updated'][] = $rec_id;
            }
            
        }
    }

}
