<?php
namespace hserv\records\batch\file;

use hserv\records\batch\RecordsBatchAction;

/**
 * Extracts text content from PDF files associated with records in a batch
 * and stores the extracted text into a specified detail field (defaulting to `DT_EXTRACTED_TEXT`).
 *
 * Key operations:
 * - Validates parameters and record accessibility. Defaults `dtyID` to `DT_EXTRACTED_TEXT` if not provided.
 * - For each record:
 *   - Checks if the target text field already has a value; if so, skips (adds to `skippedRecIDs`).
 *   - Searches for PDF files linked to the record via any file-type field using `recordSearchByID`.
 *   - For each PDF found:
 *     - Parses the PDF using `\Smalot\PdfParser\Parser`.
 *     - Extracts text from pages (up to a limit of 60000 chars or 10 pages).
 *     - Handles UTF-8 encoding issues.
 *     - If text is extracted, it's split into chunks of max 20000 chars and new details are created
 *       for the target `dtyID` with this text.
 *     - Updates `rec_Modified` for the record.
 * - Handles progress tracking if `session_id` is set.
 * - Assigns system tags if enabled and reports various outcomes (processed, skipped, errors, parse exceptions).
 *
 * Note: Requires the `smalot/pdfparser` library.
 * If `DEBUG_RUN` constant is true, actual PDF parsing is skipped.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID' (optional), 'tag': Common batch parameters.
 * - 'dtyID': (int, optional) The detail type ID where extracted text should be stored.
 *            Defaults to `DT_EXTRACTED_TEXT` (constant `2-652`).
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records where extracted text was stored.
 * - undefined: records with no associated PDF.
 * - limited: records where extracted text was already defined.
 * - parseexception: records where PDF parsing raised an exception.
 * - parseempty: records where parsing produced no text.
 * - errors: records with file, parser or SQL errors.
 * - Each outcome may include a corresponding *_list entry; processed may include tag information.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation.
 *                     Returns `false` on critical validation failure or if `DT_EXTRACTED_TEXT` is not defined and no `dtyID` is given.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchExtractPdf extends RecordsBatchAction
{
    
    public function execute(){

        //default value to pass validation
        if(!@$this->data['dtyID']){
            if(!defined('DT_EXTRACTED_TEXT')){
                $this->system->addError(HEURIST_NOT_FOUND, 'Field "Extracted text" (2-652) not found');
                return false;
            }
            $this->data['dtyID'] = DT_EXTRACTED_TEXT;
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();

        $tot_count = count($this->recIDs);

        $execution_counter = 0;

        $processedRecIDs = array();//success
        $sqlErrors = array();
        $skippedRecIDs = array();//values already defined

        $skippedNoPDF   = array();//no assosiated records
        $skippedEmpty   = array();//empty
        $skippedParseEx = array();//parse exception



        $now = date(DATE_8601);
        $dtl = array('dtl_DetailTypeID'  => $this->data['dtyID'],
                     'dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~extract pdf $now";

        $parser = new \Smalot\PdfParser\Parser();

        foreach ($this->recIDs as $recID) {

            $sql = 'select count(dtl_ID) from recDetails where dtl_RecID='.$recID.' AND dtl_DetailTypeID = '.$this->data['dtyID'];
            $isExistsAlready = mysql__select_value($mysqli, $sql)>0;

            if($isExistsAlready){
               $skippedRecIDs[] = $recID;
               continue;
            }

            $details = array();
            $hasPDFs = false;

            $record = recordSearchByID($this->system, $recID, array('file'));
            foreach ($record['details'] as $dtl_ID => $detailValue){
    // 2. find assosiated pdf files
                if(is_array($detailValue)){
                    foreach ($detailValue as $id => $fileValue){
                    if($fileValue['file']['fxm_MimeType']=='application/pdf'){

                        $hasPDFs = true;

                        $file = $fileValue['file']['fullPath'];
                        $file = resolveFilePath($file);
                        if(file_exists($file)){
        // 3. Parse pdf file
                            try{

                                if(!DEBUG_RUN){
                                    $pdf    = $parser->parseFile($file);

                                        // Retrieve all pages from the pdf file.
                                        $pages  = $pdf->getPages();
                                        $page_cnt = 0;
                                        $text = '';
                                        // Loop over each page to extract text.
                                        foreach ($pages as $page) {

                                            $pagetext = $page->getText();

                                            if(mb_detect_encoding($pagetext, 'UTF-8', true)===false){

                                                $pagetext = iconv("UTF-8","UTF-8//IGNORE", $pagetext);// to remove

                                                //$pagetext = Encoding::fixUTF8($pagetext);
                                                if(mb_detect_encoding($pagetext, 'UTF-8', true)===false){
                                                    $pagetext = 'Page '.$page_cnt.' cannot be converted to UTF-8';
                                                }
                                            }

                                            $text = $text . $pagetext;
                                            if(strlen($text)>60000 || $page_cnt>10){
                                                break;
                                            }
                                            $page_cnt++;


                                        }//foreach

                                }else{
                                    //debug without real parsing
                                    sleep(1);
                                    $text = 'test';
                                    $skippedParseEx[$recID] = $file.' Debug parse exception';
                                }

                                if($text==null || mb_strlen(trim($text))==0){
                                    $skippedEmpty[$recID] = $file;
                                }else{
                                    $orig_len = mb_strlen($text);
                                    $maxlen = 20000;
                                    if($orig_len>$maxlen){ //split by 20k

                                            $k=0;
                                            while (strlen($text)>$maxlen && $k<3){
                                                $details[] = mb_substr($text,0,$maxlen);
                                                $text = mb_substr($text,$maxlen);
                                                $k++;
                                            }
                                            if($k>2){
                                                $len = count($details)-1;
                                                $details[$len] =
                                                    $details[$len]
                                                    .' <more text is available. Remaining text has not been extracted from file>';
                                            }
                                   }else{
                                        $details[] = $text;
                                   }
                                }

                            } catch (\Exception $ex) {
                                //throw new ParseException($ex);
                                $skippedParseEx[$recID] = $file.' '.print_r($ex, true);
                            }
                        }else{
                            $skippedNoPDF[$recID] = 'PDF file not found';
                        }
                    }
                }
                }
            }//details

            if(!$hasPDFs){
                $skippedNoPDF[] = $recID;
            }elseif(!empty($details)){

                /*
                // 4. remove old 2-652 "Extracted text"
                $sql = 'delete from recDetails where dtl_RecID='.$recID.' AND dtl_ID = '.$this->data['dtyID'];
                if ($mysqli->query($sql) !== true) {
                    $sqlErrors[$recID] = 'Cannot remove dt#'.$this->data['dtyID'].' for record # '.$recID.'  '.$mysqli->error;
                }else{}
                */
    // 5. Add new values to 2-652 - one entry per file
                if(!DEBUG_RUN){
                    $dtl['dtl_RecID'] = $recID;
                    foreach($details as $text){
                        $dtl['dtl_Value'] = $text;
                        if(mb_detect_encoding($dtl['dtl_Value'], 'UTF-8', true)===false){
                            $sqlErrors[$recID] = 'Extracted text has not valid utf8 encoding';
                            break;
                            /*
                            $query = 'INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES ('
                            .$dtl['dtl_RecID'].', '.$dtl['dtl_DetailTypeID'].', '
                            .'CONVERT( CAST(? AS BINARY) USING utf8mb4))';

                            $ret = mysql__exec_param_query($mysqli, $query, array($dtl['dtl_Value']));
                            */
                        }else{
                            $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                            if (!is_numeric($ret)) {
                                    $sqlErrors[$recID] = $ret;
                                    break;
                            }
                        }
                    }//foreach
                    if(@$sqlErrors[$recID]) {continue;}

                    //update record edit date
                    $rec_update['rec_ID'] = $recID;
                    $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                    if (!is_numeric($ret)) {
                        $sqlErrors[$recID] = 'Cannot update record "Modify date". '.$ret;
                    }
                }
                $processedRecIDs[] = $recID;
            }


            if($this->session_id!=null){
                //check for termination and set new value
                $execution_counter++;
                $session_val = $execution_counter.','.$tot_count;
                $current_val = mysql__update_progress($mysqli, $this->session_id, false, $session_val);
                if($current_val=='terminate'){ //session was terminated from client side
                    break;
                }
            }

        }//for records

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skippedNoPDF, null); //no pdf assigned
        $this->_assignTagsAndReport('limited',   $skippedRecIDs, null); //value already defined
        $this->_assignTagsAndReport('parseexception', $skippedParseEx, null);
        $this->_assignTagsAndReport('parseempty', $skippedEmpty, null);
        $this->_assignTagsAndReport('errors',  $sqlErrors, null);

        return $this->result_data;
    }

}
