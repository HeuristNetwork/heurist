<?php
namespace hserv\controller;

use hserv\System;
use hserv\report\ReportTemplateMgr;
use hserv\report\ReportExecute;
use hserv\utilities\USanitize;

class ReportController
{
    private $system;
    private $dir;
    private $repAction;
    private $req_params;

    public function __construct($system, $params=null)
    {
        $this->req_params = is_array($params) ?$params :USanitize::sanitizeInputArray();    

        if(!isset($system)){
            $system = new System();
            if(!$system->init(@$this->req_params['db'])){
                dataOutput($system->getError());
                return null;
            }
        }        

        $this->system = $system;
        $this->dir = HEURIST_SMARTY_TEMPLATES_DIR;
        
        $this->repAction = new ReportTemplateMgr($this->system, $this->dir);
    }

    public function handleRequest($action)
    {
        $result = null;
        $mimeType = null;
        $filename = null;
            
        try {
            
            $template_file = $this->getTemplateFileName();
            $template_body = $this->getTemplateBody();
            
            if($template_file && $action==null){
                $action = 'execute';
            }
            
            switch ($action) {
                case 'execute':
                               
                    $repExec = new ReportExecute($this->system, $this->req_params);
                    $repExec->execute();
                    break;

                case 'update':
                
                    $result = $this->updateTemplate();
                    break;
                    
                case 'list':
                    $result = $this->repAction->getList();;
                    break;

                case 'get':
                    $this->repAction->downloadTemplate($template_file);
                    break;

                case 'save':
                    $result = $this->saveTemplate($template_body, $template_file);
                    break;

                case 'delete':
                    $result = $this->repAction->deleteTemplate($template_file);
                    break;

                case 'import':
                    $result = $this->importTemplate();
                    break;

                case 'export':
                    $is_check_only = array_key_exists('check', $this->req_params);
                
                    $result = $this->repAction->exportTemplate($template_file, $is_check_only, null);
                    break;

                case 'check':
                    $this->repAction->checkTemplate($template_file);
                    $result = 'exist';
                    break;

                default:
                    throw new \Exception('Invalid "action" parameter');
            }
            
        } catch (\Exception $e) {
            $result = false;
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
        }
        
        if(isset($result)){
            
            if($mimeType==null){ //default json output
            
                if(is_bool($result) && $result==false){
                    $result = $this->system->getError();
                }else{
                    $result = array('status'=>HEURIST_OK, 'data'=> $result);
                }
            }
            dataOutput($result, $filename, $mimeType); //ses const.php        
        }
    }
    
    private function saveTemplate($template_body, $template_file)
    {
        $template_body = urldecode($template_body); //need for get only!!!
        return $this->repAction->saveTemplate($template_body, $template_file);
    }

    private function importTemplate()
    {
        $params = null;
        $for_cms = null;
        if (isset($this->req_params['import_template']['cms_tmp_name'])) {
            // for CMS
            $for_cms = basename($this->req_params['import_template']['cms_tmp_name']);
            $params['size'] = 999;
            $params['name'] = $this->req_params['import_template']['name'];
        } else {
            // for import uploaded gpl
            $params = $_FILES['import_template'];
        }

        return $this->repAction->importTemplate($params, $for_cms);
    }

    private function getTemplateFileName()
    {
        if(array_key_exists('template', $this->req_params)){
            return USanitize::sanitizeFileName(basename(urldecode($this->req_params['template'])), false);
        }
        
        return null;
    }

    private function getTemplateBody()
    {
        return (array_key_exists('template_body', $this->req_params) ?
            $this->req_params['template_body'] :
            null);
    }
    
    
    private function updateTemplate(){
 
        $rps_ID = intval($this->req_params['id']); //rps_ID in usrReportSchedule
        
        //$row = mysql__select_row_assoc($this->system->get_mysqli(), 'SELECT * FROM usrReportSchedule WHERE rps_ID='.$rps_ID);
        
        $query = 'SELECT * FROM usrReportSchedule';
        
        if($rps_ID>0){
            $query .= ' WHERE rps_ID='.$rps_ID;
        }else{
            $this->req_params['publish'] = 4; //void - no browser output
        }
        
        $res = $this->system->get_mysqli()->query('select * from usrReportSchedule');
        if($res){
            while ($row = $res->fetch_assoc()) {
                $result = $this->repAction->updateTemplate($this->req_params, $row);
            }
            $res->close();
        }
        
        
        
        
    }
    
    
}