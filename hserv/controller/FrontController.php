<?php
/**
* FrontController.php - Class FrontController
*
* Manages overall flow and delegates request to the appropriate controller.
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.6
*/
namespace hserv\controller;

use hserv\controller\ReportController;
use hserv\controller\MapController;
use hserv\controller\UserController;
use hserv\records\import\IiifManifestImporter;
use hserv\System;
use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;
use hserv\web\WebSite;
use hserv\utilities\UJwt;

/**
 * Class FrontController
 *
 * This class is responsible for managing the overall system flow and handling
 * requests by detecting and delegating to the appropriate controller.
 *
 */
class FrontController
{
    /**
     * @var System $system The system object managing the core system functionalities.
     */
    private $system;

    /**
     * @var array $req_params The sanitized request parameters from GET or POST.
     */
    private $req_params;

    /**
     * FrontController constructor.
     *
     * Initializes the request parameters by sanitizing the input and
     * sets up the system. If the system initialization fails, an error is returned.
     *
     * @return void
     */
    public function __construct($params=null)
    {
        // Take from GET or POST
        $this->req_params = is_array($params) ? $params : USanitize::sanitizeInputArray();
        $system = new System();
        if (!$system->init(@$this->req_params['db'])) {
            if(array_key_exists('website', $this->req_params)){
                //header('Location: '.HEURIST_BASE_URL.'documentation/context_help/missedDatabaseDontPanic.htm');
                include_once dirname(__FILE__).'/../../hclient/framecontent/dbNotFound.php';
                exit;
            }else{
                dataOutput($system->getError());
            }
            return;
        }
        $this->system = $system;

        ConceptCode::setSystem($system);
    }
    
    public function isInited(): bool
    {
        return (isset($this->system) && $this->system->isInited());
    }

    /**
     * Runs the front controller logic.
     *
     * Detects if the system is initialized and decides which controller to
     * delegate the request to. If the controller is detected as ReportController,
     * the request is passed on for further handling.
     *
     * @return void
     */
    public function run()
    {
        global $jwt_Secret; 
        
        if(!$this->isInited()){
            return;
        }

        // Detect controller class
        if (@$this->req_params['controller'] == 'MapController') {

            $controller = new MapController($this->system, $this->req_params);
            $controller->handleRequest(@$this->req_params['action']);

        }elseif (@$this->req_params['controller'] == 'UserController') {

            $controller = new UserController($this->system, $this->req_params);
            $controller->handleRequest(@$this->req_params['action']);

        }elseif (@$this->req_params['controller'] == 'ReportController'  // $this->req_params['controller']
            || @$this->req_params['template']
            || @$this->req_params['template_body']
            || @$this->req_params['template_id']) {

            //validate authentication via TOKEN
            if(isset($jwt_Secret) && strlen($jwt_Secret)>7)
            {
                $auth = UJwt::get_auth_header();
                

                if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
                    
                    $payload = UJwt::jwt_verify($m[1], $jwt_Secret);
                    if ($payload === false) {
                        //UJwt::json_out(401, ['error'=>'invalid_token'], ['WWW-Authenticate'=>'Bearer error="invalid_token"']);
                    }else{
                        // Optional: check scopes
                        // if (!in_array('read:data', (array)($payload['scope'] ?? []))) { ... }
                        $userID = $payload['sub'];
                        $this->system->setCurrentUser([
                                'ugr_ID'=>$userID, 
                                'ugr_Groups'=>user_getWorkgroups( $this->system->getMysqli(), $userID )
                        ]);

                    }
                }
            }
                
            $controller = new ReportController($this->system, $this->req_params);
            $controller->handleRequest(@$this->req_params['action']);

        }elseif(array_key_exists('website', $this->req_params)){

            $website = new WebSite($this->system, $this->req_params);
            
            $website->execute();    
            
        }elseif(@$this->req_params['controller'] == 'ImportAnnotations'){
            
            $controller = new IiifManifestImporter($this->system, $this->req_params);
            $result = $controller->execute();
            
            if (is_bool($result) && $result == false) {
                $result = $this->system->getError();
            } else {
                $result = ['status' => HEURIST_OK, 'data' => $result];
            }
            dataOutput($result);            
        }
    }
    
    public function getWebsiteVersion(){
        
        $website = new WebSite($this->system, $this->req_params);
        
        return $website->getWebSiteVersion();
    }
    
}
