<?php
/*
* ReportTemplateMgr.php - Class ReportTemplateMgr
*
* Manages Smarty template files.
*
* @project     Heurist academic knowledge management system
* @package Report
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\report;

use hserv\structure\ConceptCode;
use hserv\utilities\USanitize;

/**
 * Class ReportTemplateMgr
 *
 * Manages Smarty template files for Heurist reports. This includes operations such as
 * listing available templates, retrieving template content, saving, deleting,
 * and handling the import/export of templates. A key feature is the ability
 * to convert Heurist-specific field identifiers within templates between local database IDs
 * (e.g., `f123`) and global concept codes (e.g., `f10-23` or `f[[10-23]]`) to facilitate
 * template sharing and interoperability between different Heurist databases.
 * It also handles the conversion of older `.gpl` template formats to the standard `.tpl` format.
 *
 */
class ReportTemplateMgr
{
    /**
     * @var \hserv\System The Heurist system object.
     */
    protected $system;

    /**
     * @var string The directory path where Smarty template files are stored.
     */
    protected $dir;

    /**
     * ReportTemplateMgr constructor.
     *
     * Initializes the template manager with the Heurist system object and the template directory.
     * If no directory is explicitly provided, it defaults to the path defined by the
     * `HEURIST_SMARTY_TEMPLATES_DIR` constant.
     *
     * @param \hserv\System $_system The Heurist system object.
     * @param string|null $_dir (Optional) The directory where templates are stored.
     *                             Defaults to `HEURIST_SMARTY_TEMPLATES_DIR`.
     */
    public function __construct($_system, $_dir = null)
    {
        global $system;

        $this->system = $_system ?: $system;
        $this->dir = $_dir ?? (defined('HEURIST_SMARTY_TEMPLATES_DIR') ? HEURIST_SMARTY_TEMPLATES_DIR : null);
    }

    /**
     * Retrieves a list of available Smarty template files.
     *
     * Scans the template directory (`$this->dir`). It processes `.gpl` files by converting
     * them to `.tpl` format using `processGplFile` (which includes deleting the original `.gpl`).
     * It lists all `.tpl` files, excluding temporary files (those starting with an underscore).
     *
     * @return array An array of associative arrays, where each inner array has:
     *               - 'filename': The `.tpl` filename (e.g., "my_report.tpl").
     *               - 'name': The template name (filename without the .tpl extension).
     */
    public function getList()
    {
        $files = scandir($this->dir);
        $results = [];

        foreach ($files as $filename) {
            $path_parts = pathinfo($filename);
            if (!array_key_exists('extension', $path_parts)) {
                continue;
            }

            $ext = strtolower($path_parts['extension']);
            $ind = strpos($filename, "_");
            $isnot_temp = (!(is_numeric($ind) && $ind == 0));

            if (file_exists($this->dir . $filename) && $ext == "gpl") {

                $processed_template = $this->processGplFile($filename);
                if ($processed_template) {
                    $results[] = $processed_template;
                }

            } elseif (file_exists($this->dir . $filename) && $ext == "tpl" && $isnot_temp) {
                $name = substr($filename, 0, -4);
                $results[] = ['filename' => $filename, 'name' => $name];
            }

        }

        return $results;
    }
    
    /*
    * Returns templates for cms headers or footers
    */
    public function getListForCms($template_type=null){
    
        $dir = HEURIST_DIR.'hserv/web/templates/'.($template_type=='header'?'headers':'footers');
        
        $files = scandir($dir);
        $results = [];

        foreach ($files as $filename) {
            $path_parts = pathinfo($filename);
            if ($path_parts['extension']=='html') {
                $name = substr($filename, 0, -5);
                $results[] = ['filename' => $filename, 'name' => $name];
            }
        }
        
        return $results;
    }

    /**
     * Processes a `.gpl` template file by converting it to the `.tpl` format.
     *
     * The conversion involves translating global concept IDs used in `.gpl` files
     * to local Heurist field IDs using `convertTemplate()` with mode 1.
     * The newly created `.tpl` file is saved, and the original `.gpl` file is deleted.
     *
     * @param string $filename The basename of the `.gpl` file in the template directory.
     * @return array|null An associative array `['filename' => ..., 'name' => ...]` for the
     *                    newly created `.tpl` file if successful, or null on failure.
     */
    private function processGplFile($filename)
    {
        $template_body = file_get_contents($this->dir . $filename);
        $res = $this->convertTemplate($template_body, 1);

        if (is_array($res) && isset($res['template'])) {
            $template_body = $res['template'];
            $filename_tpl = $this->saveTemplate($template_body, $filename);

            if ($filename_tpl) {
                $name = substr($filename_tpl, 0, -4);
                fileDelete($this->dir . $filename); // Remove .gpl
                return ['filename' => $filename_tpl, 'name' => $name];
            }
        }

        return null;
    }


    /**
     * Outputs the content of a specified template file, typically for browser download/display.
     *
     * If `$template_file` is empty or null, it defaults to outputting a standard
     * 'template.tpl' example file located within the same directory as this class.
     * Otherwise, it validates the provided filename using `checkTemplate`.
     * Sets 'Content-type: text/html' header before outputting the file content.
     *
     * @param string|null $template_file The basename of the template file to download.
     *                                   Defaults to a standard example template if null/empty.
     * @return void Outputs directly to the browser or prints an error message on failure.
     */
    public function downloadTemplate($template_file, $cms_type=null)
    {
        
        try {
            if ($template_file == null || $template_file == '') {
                $template_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'template.tpl';
                if (!file_exists($template_file)) {
                    throw new \Exception("Template example file not found");
                }
            } else {
                $template_file = $this->checkTemplate($template_file);
            }

            header(CTYPE_HTML);
            $res = readfile($template_file);

            if (!$res) {
                throw new \Exception("Cannot read template file " . basename($template_file));
            }

        } catch (\Exception $e) {
            print $e->getMessage();
        }
    }

    /**
     * Saves template content to a specified file within the template directory.
     *
     * Ensures the filename ends with '.tpl'. It checks if the template directory exists
     * (and creates it if not, though `folderExists` with `true` usually means it checks/creates).
     * Uses `fileSave` (presumably a global helper or system method) for the actual saving.
     *
     * @param string $template_body The string content of the template.
     * @param string $template_file The desired basename for the template file.
     * @return string The final filename (e.g., "my_template.tpl") if successful.
     * @throws \Exception If the directory is not writable or `fileSave` fails.
     */
    public function saveTemplate($template_body, $template_file)
    {
        $path_parts = pathinfo($template_file);
        $template_file = $path_parts['filename'] . '.tpl';
        $template_file_fullpath = $this->dir . $template_file;

        $res = folderExists($this->dir, true);
        if ($res > 0) {
            $res = fileSave($template_body, $template_file_fullpath);
        }
        if ($res <= 0) {
            throw new \Exception('Cannot write file. Check permissions for the Smarty template directory');
        }
        return $template_file;
    }

    /**
     * Deletes a specified template file from the template directory.
     *
     * Uses `checkTemplate()` to validate the filename and get the full path before attempting to delete.
     *
     * @param string $template_file The basename of the template file to delete.
     * @return string Returns the string "deleted" on successful deletion.
     * @throws \Exception If `checkTemplate` fails (e.g., file not found).
     */
    public function deleteTemplate($template_file)
    {
        $template_file = $this->checkTemplate($template_file);
        unlink($template_file);
        return 'deleted';
    }

    /**
     * Validates a template filename and returns its full, sanitized path.
     *
     * Ensures that the provided `$template_file` (basename) exists within the configured
     * template directory (`$this->dir`). It uses `basename()` to prevent directory traversal issues.
     *
     * @param string $templateFile The basename of the template file.
     * @return string The full, verified path to the template file.
     * @throws \Exception If the template file does not exist in the directory.
     */
    public function checkTemplate($templateFile)
    {
            
        //$safeFileName = basename($templateFile);
        $path_parts = pathinfo($templateFile);
        $safeFileName = $path_parts['filename'] . '.tpl';
        
        if(strpos($templateFile,'def/')===0){
            $templateFile = HEURIST_DIR.'hclient/widgets/HRecordList/'.$safeFileName;
        }else{
            $templateFile = $this->dir . $safeFileName;
        }
        
        if (file_exists($templateFile)) {
            return $templateFile;
        } else {
            throw new \Exception("Template file $safeFileName not found");
        }
    }

    /**
     * Rename an existing template
     *
     * @param string $oldTemplateFile
     * @param string $newTemplateName
     * @return string Returns the string "renamed" on successful renaming.
     * @throws \Exception If `checkTemplate` fails (e.g., file not found).
     */
    public function renameTemplate($oldTemplateFile, $newTemplateName){

        $oldTemplateFile = $this->checkTemplate($oldTemplateFile);
        $newTemplateName = !empty($newTemplateName) ? USanitize::sanitizeFileName(basename(urldecode($newTemplateName)), false) : '';

        if(empty($newTemplateName)){
            throw new \Exception('New template name is invalid');
        }

        $pathInfo = pathinfo($oldTemplateFile);
        $oldFileName = "{$pathInfo['filename']}.tpl";
        $newFileName = "{$newTemplateName}.tpl";
        $newTemplateFile = str_replace($oldFileName, $newFileName, $oldTemplateFile);

        // Ensure the new name isn't already taken
        if(file_exists($newTemplateFile)){
            throw new \Exception("Template \"$newTemplateName\" already exists");
        }

        // Rename template file
        if(!rename($oldTemplateFile, $newTemplateFile)){
            throw new \Exception('Failed to rename template');
        }

        // Update usages of the original template
        $mysqli = $this->system->getMysqli();
        $oldNameEncoded = rawurlencode($oldFileName);
        $newNameEncoded = rawurlencode($newFileName);

        // Update shorthand links to old template name
        $dtlValues = mysql__select_assoc2($mysqli, "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_Value LIKE '%/{$oldNameEncoded}%'"); // \d*\/OLD_TEMPLATE_NAME\.tpl
        $oldNameEncoded = str_replace('.', '\.', $oldNameEncoded);
        foreach($dtlValues as $dtlID => $dtlValue){

            $dtlValue = preg_replace("~/{$oldNameEncoded}~g", "/{$newNameEncoded}", $dtlValue);

            mysql__insertupdate($mysqli, 'recDetails', 'dtl', ['dtl_ID' => $dtlID, 'dtl_Value' => $dtlValue]);
        }

        // Update widget settings that use the old template name
        $dtlValues = mysql__select_assoc2($mysqli, "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_Value LIKE '%\"{$oldFileName}\"%'");
        $oldFileNameReg = str_replace('.', '\.', $oldFileName);
        foreach($dtlValues as $dtlID => $dtlValue){

            $dtlValue = preg_replace("~\"{$oldFileNameReg}\"~g", "\"{$newFileName}\"", $dtlValue);

            mysql__insertupdate($mysqli, 'recDetails', 'dtl', ['dtl_ID' => $dtlID, 'dtl_Value' => $dtlValue]);
        }

        // Update scheduled reports that use the old template name
        $mysqli->query("UPDATE usrReportSchedule SET rps_Template='{$newFileName}' WHERE rps_Template='{$oldFileName}'");

        return 'renamed';
    }

    /**
     * Converts Heurist-specific identifiers within a template string between local database IDs and global concept codes.
     *
     * This method uses regular expressions to find Smarty-like variable tags (e.g., `{$record.f123}`)
     * within the template content. It then processes these identifiers:
     * - **Mode 0 (Export to Global Concept IDs):** Converts local detail type IDs (e.g., `f123`)
     *   to their corresponding concept codes (e.g., `f10-23` or `f[[10-23]]` if it includes underscores).
     *   This is used when exporting a template to be shared.
     * - **Mode 1 (Import to Local IDs):** Converts concept codes (e.g., `f10-23` or `f[[10-23]]`)
     *   back to local detail type IDs specific to the current database. If a concept code
     *   cannot be mapped to a local ID (i.e., the field doesn't exist), it's marked with `[[...]]`
     *   and added to a `details_not_found` list. This mode is used when importing a template
     *   or processing `.gpl` files.
     *
     * The conversion handles field suffixes like 's' (e.g., `f123s` for plural/array access)
     * and `_originalvalue`.
     *
     * @param string $template The template content string to be converted.
     * @param int $mode The conversion mode:
     *                  - 0: Convert local IDs to global concept codes.
     *                  - 1: Convert global concept codes to local IDs.
     * @return string|array If mode is 0, returns the converted template string.
     *                      If mode is 1, returns an associative array:
     *                      `['template' => (string)converted_template, 'details_not_found' => (array)list_of_unmapped_concept_codes]`
     * @throws \Exception If the input template string is empty.
     */
    public function convertTemplate($template, $mode)
    {
        //1. get template content
        //2. find all texts within {}
        //3. find words within this text
        //4. split by .
        //5. find starting with "f"
        //6. get local DT ID - find Concept Code
        //7. replace

        //1. get template content
        if($template==null || $template==''){
            throw new \Exception('Template is empty');
        }

        //2. find all texts within {} - expressions
        if (! preg_match_all('/\{([^}]+)\}/s', $template, $matches)){
            return $template;    // nothing to do -- no substitutions
        }

        $not_found_details = array();
        $replacements_exp = array();

        $len = count($matches[1]);
        for ($i=0; $i < $len; ++$i) {

            $exp = $matches[1][$i];
            if(!trim($exp)) {continue;} //empty{}
            if(substr($exp,0,1)=="*" && substr($exp,-1)=="*") {continue;} //this is comment

        //3. find words within this text
            if (! preg_match_all('/(\\$([a-zA-Z_0-9.])+)/', $exp, $matches2) ){
                continue;
            }

            $replacements = array();

            foreach ($matches2[1] as $var) {

        //4. split by "."
                    $parts = explode(".", $var);
                    $parts2 = array();
                    foreach ($parts as $part) {
        //5. find starting with "f"
                        if(strpos($part, 'f')===0){
                            $prefix = 'f';
                        }elseif(strpos($part, '$f')===0){
                            $prefix = '$f';
                        }else{
                            $prefix = null;
                        }

                        if($prefix){
        //6. get local DT ID - find Concept Code
                            $code = substr($part, strlen($prefix));
                            if(substr($part, -1)=='s'){
                                    $suffix = 's';
                                    $code = substr($code,0,strlen($code)-1);
                            }elseif(ReportTemplateMgr::endsWith($part,'_originalvalue')){
                                    $suffix = '_originalvalue';
                                    $code = substr($code,0,strlen($code)-strlen($suffix));
                            }else{
                                    $suffix = "";
                            }

                            if($mode==0){
                                $localID = $code;
                                if(strpos($localID,"_")===false){
                                    $conceptCode = ConceptCode::getDetailTypeConceptID($localID);
                                    $part = $prefix.str_replace("-","_",$conceptCode).$suffix;
                                }
                            }else{
                                $conceptCode = $code;

                                if(strpos($conceptCode,"_")!==false){
                                    $conceptCode = str_replace("_","-",$conceptCode);

                                    $localID = ConceptCode::getDetailTypeLocalID($conceptCode);
                                    if($localID==null){
                                        //local code not found - it means that this detail is not in this database
                                        array_push($not_found_details, $conceptCode);
                                        $part = $prefix."[[".$conceptCode."]]".$suffix;
                                    }else{
                                        $part = $prefix.$localID.$suffix;
                                    }
                                }
                            }
                        }
                        array_push($parts2, $part);
                    }
                    $new_var = implode(".", $parts2);

                    if($var!=$new_var){
                        $replacements[$var] = $new_var;
                    }
            }//for vars

            if(!empty($replacements)){
                   $new_exp = "{".$this->arrayStrReplace(array_keys($replacements), array_values($replacements), $exp)."}";
                   if($matches[0][$i] != $new_exp){
                        $replacements_exp[$matches[0][$i]] = $new_exp;
                   }
            }
        }//for expressions


        if(!empty($replacements_exp)){
             $template = $this->arrayStrReplace(array_keys($replacements_exp), array_values($replacements_exp), $template);
        }

        return $mode == 1 ? ["template" => $template, "details_not_found" => $not_found_details] : $template;
    }

    /**
     * Converts local Heurist field IDs in a template to global concept codes and prepares it for export.
     *
     * This method is used to make a template portable between different Heurist databases.
     * It reads the template content (either from a specified file or a provided string),
     * then uses `convertTemplate()` in mode 0 to replace local field IDs (e.g., `{$record.f123}`)
     * with their global concept code equivalents (e.g., `{$record.f10-23}`).
     *
     * If `$is_check_only` is true, it only verifies that the database is registered (a prerequisite for export)
     * and returns 'ok' without outputting the file. Otherwise, it sends appropriate HTTP headers
     * for file download and prints the converted template content. The downloaded file will have a `.gpl` extension.
     *
     * @param string|null $filename The basename of the template file to export. Used if `$template_body` is null.
     * @param bool $is_check_only If true, performs only pre-checks and doesn't output the file.
     * @param string|null $template_body (Optional) The direct template content string. If provided, `$filename` is used for naming the output.
     * @return string|void Returns 'ok' if `$is_check_only` is true and checks pass. Otherwise, outputs the file content
     *                     directly to the browser and script execution typically ends. Returns null implicitly.
     * @throws \Exception If the database is not registered (required for concept codes) or if the template is empty.
     */
    public function exportTemplate($filename, $is_check_only, $template_body = null)
    {
        $dbID = $this->system->settings->get('sys_dbRegisteredID');
        if (!$dbID) {
            throw new \Exception('Database must be registered to allow translation of local template to global template');
        }

        if ($filename) {
            $template_file = $this->checkTemplate($filename);
            $template_body = file_get_contents($template_file);
        } else {
            $filename = 'Export.gpl';
        }

        if ($template_body && strlen($template_body) > 0) {
            $filename = str_replace(".tpl", ".gpl", basename($filename));

            if ($is_check_only) {
                return 'ok';
            } else {
                $content = $this->convertTemplate($template_body, 0);
                header('Content-type: html/text');
                header('Content-Disposition: attachment; filename=' . $filename);
                print $content;
            }
        } else {
            throw new \Exception('Template is not defined or empty');
        }
        return null;
    }

    /**
     * Imports a template file (typically `.gpl` or `.tpl`), converting global concept IDs to local field IDs.
     *
     * This method handles a file upload (passed via `$params`), reads its content,
     * and then uses `convertTemplate()` in mode 1 to map global concept IDs (e.g., `f10-23`)
     * to local Heurist field IDs (e.g., `f123`).
     * The converted template is then saved as a `.tpl` file in the template directory
     * using a unique filename derived from the original.
     *
     * If `$for_cms` is provided, it implies the template is a CMS snippet and is saved
     * to a specific CMS templates/snippets directory with the given name.
     *
     * @param array $params An array describing the uploaded file, typically from `$_FILES`
     *                      (must include 'name', 'size', 'tmp_name').
     * @param string|null $for_cms (Optional) If importing for CMS, this is the target filename
     *                             (without path) for the snippet.
     * @return array An associative array:
     *               `['filename' => (string)saved_tpl_filename, 'details_not_found' => (array)list_of_unmapped_concept_codes]`
     * @throws \Exception If the upload is invalid, file cannot be read, or template content is empty.
     */
    public function importTemplate($params, $for_cms = null)
    {
        if (!$params || !$params['size']) {
            throw new \Exception('Error occurred during upload - file is zero size');
        }

        $origfilename = basename($params['name']);
        $filename = null;

        if ($for_cms) {
            $path = realpath(dirname(__FILE__) . '/../../hclient/widgets/cms/templates/snippets/');
            if ($path) {
                $filename = $path . DIRECTORY_SEPARATOR . basename($for_cms);
            }
        } elseif (isset($params['tmp_name']) && is_uploaded_file($params['tmp_name'])) {
            $filename = USanitize::sanitizePath($params['tmp_name']);
        }

        if (!$filename || !file_exists($filename)) {
            throw new \Exception('Error occurred during upload - file does not exist');
        }

        $template = file_get_contents($filename);
        $res = $this->convertTemplate($template, 1);

        if (isset($res['error'])) {
            throw new \Exception($res['error']);
        }

        $origfilename = getUniqueFileName($this->dir, $origfilename, 'tpl');

        $save_res = [];
        $save_res['filename'] = $this->saveTemplate($res['template'], $origfilename);

        if (!empty($res['details_not_found'])) {
            $save_res['details_not_found'] = $res['details_not_found'];
        }

        return $save_res;
    }

    /**
     * A custom string replacement function that iterates through search terms to replace them.
     *
     * This method is designed to handle replacements more carefully than a simple `str_replace`
     * by finding the earliest match in each iteration and replacing it, then continuing
     * with the rest of the string. This can be useful to avoid issues where a replacement
     * might create a new match for an earlier search term.
     *
     * @param array $search An array of strings to search for.
     * @param array $replace An array of strings to replace with.
     * @param string $subject The subject string.
     * @return string The string with all occurrences of search terms replaced.
     */
    private function arrayStrReplace($search, $replace, $subject)
    {
        $result = '';

        while ($subject !== '') {
            list($match_idx, $match_offset) = $this->findNextMatch($search, $subject);

            if ($match_idx !== -1) {
                $result .= substr($subject, 0, $match_offset) . $replace[$match_idx];
                $subject = substr($subject, $match_offset + strlen($search[$match_idx]));
            } else {
                $result .= $subject;
                break;
            }
        }

        return $result;
    }

    /**
     * Finds the first occurrence of any of the given search terms in a subject string.
     *
     * Iterates through the `$search` array and finds which term appears earliest in `$subject`.
     *
     * @param array $search An array of strings to search for.
     * @param string $subject The string to search in.
     * @return array Returns `[$match_idx, $match_offset]`. `$match_idx` is the index in `$search`
     *               of the term found, or -1 if no terms are found. `$match_offset` is the starting
     *               position of the found term in `$subject`, or -1.
     */
    private function findNextMatch($search, $subject)
    {
        $match_idx = -1;
        $match_offset = -1;

        foreach ($search as $i => $term) {
            if ($term === '') {
                continue;
            }

            $offset = strpos($subject, $term);
            if ($offset !== false && ($match_offset === -1 || $offset < $match_offset)) {
                $match_idx = $i;
                $match_offset = $offset;
            }
        }

        return [$match_idx, $match_offset];
    }

    //
    /**
     * Checks if a string (`$haystack`) ends with a specific substring (`$needle`).
     *
     * Note: For PHP 8.0+, `str_ends_with()` provides this functionality natively.
     *
     * @param string $haystack The string to check.
     * @param string $needle The substring to look for at the end of `$haystack`.
     * @return bool True if `$haystack` ends with `$needle`, false otherwise.
     *              Returns true if `$needle` is an empty string.
     */
    private static function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }

}
