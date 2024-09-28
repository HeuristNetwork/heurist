<?php
namespace hserv\controllers;

use hserv\System;
use hserv\report\ReportTemplateMgr;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';

if(!isset($system)){
    $system = new System();
    if(!$system->init(@$_REQUEST['db'])){
        exit;
    }
    $mode = $_REQUEST['mode'];
    $controller = new ReportController($system);
    $controller->handleRequest($mode);
}


class ReportController
{
    private $system;
    private $dir;
    private $repAction;
    private $req_params;

    public function __construct($system, $params=null)
    {
        $this->system = $system;
        $this->dir = HEURIST_SMARTY_TEMPLATES_DIR;
        
        if(!$params){
            //take from get or post
            $this->req_params = USanitize::sanitizeInputArray();    
        }
        
        $this->repAction = new ReportTemplateMgr($this->system, $this->dir);
    }

    public function handleRequest($mode)
    {
        $result = null;
        $mimeType = null;
        $filename = null;
            
        try {
            
            $template_file = $this->getTemplateFileName();
            $template_body = $this->getTemplateBody();
            
            switch ($mode) {
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
                    $this->repAction->exportTemplate($template_file, null);
                    break;

                case 'check':
                    $this->repAction->checkTemplate($template_file);
                    $result = 'exist';
                    break;

                default:
                    throw new \Exception('Invalid "mode" parameter');
            }
            
        } catch (\Exception $e) {
            $result = false;
            $system->addError(HEURIST_INVALID_REQUEST, $e->getMessage());
        }
        
        if($result!=null){
            
            if($mimeType==null){ //default json output
            
                if(is_bool($result) && $result==false){
                    $result = $system->getError();
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
        return (array_key_exists('template', $this->req_params) ?
            USanitize::sanitizeFileName(basename(urldecode($this->req_params['template'])), false) :
            null);
    }

    private function getTemplateBody()
    {
        return (array_key_exists('template_body', $this->req_params) ?
            $this->req_params['template_body'] :
            null);
    }
}