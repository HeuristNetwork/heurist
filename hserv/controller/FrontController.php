<?php
namespace hserv\controller;

use hserv\controller\ReportController;
use hserv\System;
use hserv\utilities\USanitize;

class FrontController
{
    private $system;
    private $req_params;

    public function __construct()
    {
        //take from get or post
        $this->req_params = USanitize::sanitizeInputArray();    
        
        $system = new System();
        if(!$system->init(@$this->req_params['db'])){
            dataOutput($system->getError());
            return null;
        }

        $this->system = $system;
    }


    public function run(){
        //detect controller class
        if(!(isset($this->system) && $this->system->is_inited())){
            return;
        }
        
        if($this->req_params['template'] || $this->req_params['template_body']){
        
            $controller = new ReportController($this->system, $this->req_params);
            $controller->handleRequest(@$this->req_params['action']);
        }
        
    }
    
}
?>
