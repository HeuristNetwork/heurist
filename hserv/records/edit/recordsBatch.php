<?php
/**
* recordsBatch.php - Compatibility facade for record batch actions
*
* Controller is record_batch.php.
*
* @project     Heurist academic knowledge management system
* @package Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
use hserv\records\batch\RecordsBatchMultiAction;
use hserv\records\batch\field\RecordsBatchDetailAdd;
use hserv\records\batch\field\RecordsBatchDetailReplace;
use hserv\records\batch\field\RecordsBatchDetailDelete;
use hserv\records\batch\field\RecordsBatchCaseConversion;
use hserv\records\batch\field\RecordsBatchFieldTranslation;
use hserv\records\batch\field\RecordsBatchNl2BrConversion;
use hserv\records\batch\field\RecordsBatchFieldIncrement;
use hserv\records\batch\file\RecordsBatchExtractPdf;
use hserv\records\batch\file\RecordsBatchUrlToFile;
use hserv\records\batch\file\RecordsBatchUploadToRepository;
use hserv\records\batch\file\RecordsBatchResetThumbnails;
use hserv\records\batch\file\RecordsBatchCreateIiifAnnotationThumbnails;
use hserv\records\batch\record\RecordsBatchChangeRecordType;
use hserv\records\batch\record\RecordsBatchCreateSubRecords;
use hserv\records\batch\relationship\RecordsBatchAddReverseChildPointer;
use hserv\records\batch\relationship\RecordsBatchCreateLinksByMatching;

// These existing dependencies define global functions/classes used by batch actions.
require_once dirname(__FILE__).'/../../../vendor/autoload.php';
require_once dirname(__FILE__).'/recordModify.php';
require_once dirname(__FILE__).'/recordTitleMask.php';
require_once dirname(__FILE__).'/../search/recordSearch.php';
require_once dirname(__FILE__).'/../../structure/dbsUsersGroups.php';

define('DEBUG_RUN', false);
define('ERR_REC_MODDATE','Cannot update record modification date. ');
define('ERR_REC_TITLE', 'Cannot update record title');
define('R_ARROW',' &Rightarrow; ');
define('FILE_NO','File #');

/**
 * Preserves the original RecordsBatch public interface while delegating each
 * operation to a focused action class under hserv\records\batch.
 */
class RecordsBatch
{
    private $system;
    private $data;
    private $result_data = array();
    private $session_id = null;

    public function __construct($system, $data)
    {
        $this->system = $system;
        $this->data = $data;

        $this->session_id = @$this->data['session'];
        if($this->session_id!=null){
            $payload = array(
                'status' => 'running',
                'done' => 0,
                'total' => 0,
                'note' => 'Preparing records',
                'action' => @$this->data['a'],
                'updated' => time()
            );
            mysql__update_progress(
                $system->getMysqli(),
                $this->session_id,
                true,
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }

        // Refresh list of current user groups.
        $this->system->getUserGroupIds(null, true);
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getReport()
    {
        return $this->result_data;
    }

    private function executeAction($className, $data = null)
    {
        $action = new $className($this->system, $data===null ? $this->data : $data);
        $res = $action->execute();
        $this->result_data = $action->getReport();

        return $res;
    }

    public function detailsAdd()
    {
        return $this->executeAction(RecordsBatchDetailAdd::class);
    }

    public function multiAction()
    {
        return $this->executeAction(RecordsBatchMultiAction::class);
    }

    public function detailsReplace()
    {
        return $this->executeAction(RecordsBatchDetailReplace::class);
    }

    public function detailsDelete($unconditionally=false)
    {
        $data = $this->data;
        $data['unconditionally'] = $unconditionally;

        return $this->executeAction(RecordsBatchDetailDelete::class, $data);
    }

    /**
     * Existing unused compatibility stub. No implementation is added here.
     */
    public function addRevercePointerForChild()
    {
        return $this->executeAction(RecordsBatchAddReverseChildPointer::class);
    }

    public function createRecordLinksByMatching()
    {
        return $this->executeAction(RecordsBatchCreateLinksByMatching::class);
    }

    public function changeRecordTypeInBatch()
    {
        return $this->executeAction(RecordsBatchChangeRecordType::class);
    }

    public function extractPDF()
    {
        return $this->executeAction(RecordsBatchExtractPdf::class);
    }

    public function changeUrlToFileInBatch()
    {
        return $this->executeAction(RecordsBatchUrlToFile::class);
    }

    public function uploadFileToRepository()
    {
        return $this->executeAction(RecordsBatchUploadToRepository::class);
    }

    public function resetThumbnails()
    {
        return $this->executeAction(RecordsBatchResetThumbnails::class);
    }

    public function createIiifAnnotationThumbnails()
    {
        return $this->executeAction(RecordsBatchCreateIiifAnnotationThumbnails::class);
    }

    public function createSubRecords()
    {
        return $this->executeAction(RecordsBatchCreateSubRecords::class);
    }

    public function caseConversion()
    {
        return $this->executeAction(RecordsBatchCaseConversion::class);
    }

    public function nl2brConversion()
    {
        return $this->executeAction(RecordsBatchNl2BrConversion::class);
    }

    public function fieldTranslation()
    {
        return $this->executeAction(RecordsBatchFieldTranslation::class);
    }

    public function fieldIncrementValue()
    {
        return $this->executeAction(RecordsBatchFieldIncrement::class);
    }

    public function removeSession()
    {
        if($this->session_id==null){
            return;
        }

        $current = mysql__update_progress(
            $this->system->getMysqli(),
            $this->session_id,
            false,
            null
        );

        // Keep an explicit terminate marker; do not overwrite it with a completed result.
        if($current==='terminate'){
            return;
        }

        $payload = array(
            'status' => 'completed',
            'done' => intval(@$this->result_data['processed']),
            'total' => intval(@$this->result_data['passed']),
            'note' => 'Completed',
            'action' => @$this->data['a'],
            'updated' => time(),
            'result' => $this->result_data
        );

        mysql__update_progress(
            $this->system->getMysqli(),
            $this->session_id,
            false,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
?>
