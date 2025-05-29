<?php
/*
* WebSite.php - 
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2024 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     7.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

namespace hserv\web;

use hserv\utilities\USanitize;
use hserv\utilities\USystem;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../records/search/recordSearch.php';
require_once dirname(__FILE__).'/../../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';

define('HEAD_E','</head>');

/**
 * Class WebSite
 * 
 * It is initialized in FrontController
 *
 * This class generates web page content as browser output, file, or returns as string 
 */
class WebSite
{
    private $system;
    private $params;

    private $messageError;
    
    // 0 - in browser only
    // 1 - saves into generated-html and return true/false
    // 2 - download generated html as file (not used)
    // 3 - saves into generated-html and report output
    // 4 - returns as variable
    private $publishmode;
    
    private $outputfile; 

    private $isWebPage = false;    
    private $isEditMode = false;
    private $isJsAllowed = false;
    private $isHeadless; //output main conent only - without header and footer
    
    
    private $siteRecord; //it may set - to avoid header/footer generation for evey page
    private $pageRecord;
    
    private $currentLang = 'en';
    
    private $menuTree = null;
    private $menuRecords = null;

    /**
     * Constructor
     *
     * @param mixed $system The system object used for database and other interactions.
     * @param array|null $params The parameters array typically passed from $_REQUEST.
     */
    public function __construct($system, $params=null)
    {
        $this->system = $system;

        if ($params!=null) {
            // Initialize properties from parameters or set defaults
            $this->setParameters($params);
        }
        
        $this->system->defineConstant('RT_CMS_HOME');
        $this->system->defineConstant('RT_CMS_MENU');
    }
    
    /**
     * Initializes properties from parameters or sets defaults.
     *
     * @param array|null $params The parameters array to set.
     */
    public function setParameters($params=null)
    {
        if($params!=null){
            $this->params = $params;
        }

        $this->publishmode = isset($params['publish']) ? intval($params['publish']) : 0; //by default into browser
        $this->publishmode = max(min($this->publishmode,3),0);

        $this->isHeadless = false; //if true it returns main content only

        $this->isJsAllowed = $this->system->settings->isJavaScriptAllowed();
        
        $this->currentLang = $this->params['lang'];
        
    }
    
    
    /**
    * Verifies provided siteID and pageID.
    * Checks presence, accessibility and record type
    * 
    * return true is success
    */
    private function verifyWebsiteIds(){

        $siteId = @$this->params['website']; //website id
        $pageId = @$this->params['pageid'];  //page id  

        
        if(!isPositiveInt($siteId)){
            // if $siteId is not defined - use fist available "CMS home" record
            
            //find default website
            $res = recordSearch($this->system, array('q'=>array('t'=>RT_CMS_HOME), 'detail'=>'ids'));
            $def_rec_id = 0;
            if(@$res['status']==HEURIST_OK){
                $def_rec_id = @$res['data']['records'][0];
            }
            
            if(isPositiveInt($def_rec_id)){
               $siteId = $def_rec_id;
            }else{
                $try_login = $this->system->getCurrentUser() == null;
                $this->outputError('Sorry, there are no publicly accessible websites defined for this database. '
                .'Please ' . ($try_login ? '<div data-heurist-cms="HMenuPersonal" style="display:inline-block">{"reloadOnLogin":true}</div> or' 
                                         : '') . ' ask the owner to publish their website(s).');
                
                return false;
            }
        }

        $this->siteRecord = $this->verifyTypeAndAccess($siteId, true);
        $res = ($this->siteRecord!=null);
        
        if(!$res){
            return false;
        }
        
        if(!$this->isWebPage && isPositiveInt($pageId)){ 
        
            $this->pageRecord = $this->verifyTypeAndAccess($pageId, false);
            $res = ($this->pageRecord!=null);

            $this->outputfile = $pageId.'.html';
        
        }else{
            //page id not defined - this is home page
            $this->pageRecord = null;
            $this->outputfile = $siteId.'.html';
        }
        
        return $res;
    }
    
    /**
    * Loads record
    * Checks allowed type and visibility  
    *
    * 
    * @param int $recId
    * @param mixed $recordType
    * @return {record|null}
    */
    private function verifyTypeAndAccess($recId, $isMainRecord){
        
        //static url in external links may have outdated id - check if this record has been replaced (merged)
        $recId = recordSearchReplacement($this->system->getMysqli(), $recId, 0);
        $rec = recordSearchByID($this->system, $recId, false);
        $err_message = null;
        
        if($rec==null){
            $err_message = 'Webpage with given ID not found';
            
        }elseif(!($isMainRecord && $rec['rec_RecTypeID']==RT_CMS_HOME || $rec['rec_RecTypeID']==RT_CMS_MENU)){
            $err_message = 'Record is not a Webpage';
        }else {
            
            // see ReportRecord->recordIsVisible
        
            $hasAccess = (($rec['rec_NonOwnerVisibility'] == 'public') ||
                           $this->system->isAdmin() ||
                ( ($this->system->getUserId()>0) &&
                        ($rec['rec_NonOwnerVisibility'] !== 'hidden' ||    //visible for logged
                         $this->system->isMember($rec['rec_OwnerUGrpID']) )) );//owner

            if(!$hasAccess)
            {
                $try_login = $this->system->getCurrentUser() == null;
                $err_message = 'The Heurist website at this address is not yet publicly accessible. '
                    . ($try_login ? '<br>Try <div data-heurist-cms="HMenuPersonal" style="display:inline-block">{"reloadOnLogin":true}</div> to view this website.' : '');
            }
            
        }
        
        if($err_message==null){
            return $rec;
        }else{
            $this->outputError($err_message);
            return null;
        }
    }

    /**
     * Main function to generate website html based on CMS_HOME AND CMS_PAGE records
     *
     * @return bool Returns true on successful execution, false on failure.
     */
    public function execute()
    {
        $result = null;

        // Check if the system is initialized
        if (!isset($this->system) || !$this->system->isInited()) {
            $this->outputError();
        } 
        
        if(array_key_exists('webmenu', $this->params)){
            //returns menu content as array or html
            if($this->messageError){
                $result = false;
            }else{            
                $result = $this->getMenuTree( $this->params['webmenu'] ); 
                //fillMenuTree($this->params['webmenu']);
            }

            if (isset($result)) {
                //json output
                if (is_bool($result) && $result == false) {
                    $result = $this->messageError??$this->system->getError();
                } else {
                    $result = ['status' => HEURIST_OK, 'data' => $result];
                }
                dataOutput($result);
            }
            return;
        }
            
        //html output    
        if (!isset($this->params)) {
            // Check if parameters are defined
            $this->outputError('Parameters for website are not defined');
        }elseif ($this->verifyWebsiteIds()) {
            // Load website settings (details of CMS_HOME record): logo, title, langs, bg images, keywords
            $this->loadWebHomePage();
            
            if($this->siteRecord['rec_RecTypeID']==RT_CMS_MENU){
                $this->isWebPage = (defined('DT_CMS_PAGETYPE') &&
                        $this->getVal(DT_CMS_PAGETYPE)==ConceptCode::getTermLocalID('2-6254'));//TRM_PAGETYPE_WEBPAGE
                        
                if(!$this->isWebPage){
                    $this->outputError('Record is not a Website Home');
                }
            }
        }
        
        if(array_key_exists('header', $this->params)){
            
            echo $this->getPageMargin('header'); //direct output
            
        }elseif(array_key_exists('footer', $this->params)){

            echo $this->getPageMargin('footer');

        }elseif(array_key_exists('webtemplate', $this->params)){
            //returns content of page template
            echo $this->getTemplateContent('template', basename($this->params['webtemplate'].'.html'));
        
        }else{
            ob_start();
            if($this->messageError){
                include_once 'WebSiteInfo.php';    
            }else{
                include_once 'WebSiteTemplate.php';    
            }
            $output = ob_get_contents();
            ob_end_clean();            
            $this->handleOutput($output);
        }
        
    }
    
    /**
    * Load details of record (logo, title, langs, bg images, keywords)
    */
    private function loadWebHomePage(){

        recordSearchDetails($this->system, $this->siteRecord, true);
        
        $this->system->defineConstant('DT_NAME');
        $this->system->defineConstant('DT_CMS_KEYWORDS');
        $this->system->defineConstant('DT_SHORT_SUMMARY');
        $this->system->defineConstant('DT_EXTENDED_DESCRIPTION');
        $this->system->defineConstant('DT_CMS_HEADER');
        $this->system->defineConstant('DT_CMS_FOOTER');
        $this->system->defineConstant('DT_CMS_FOOTER_FIXED');
        $this->system->defineConstant('DT_THUMBNAIL');
        $this->system->defineConstant('DT_FILE_RESOURCE');

        $this->system->defineConstant('TRM_NO');
        $this->system->defineConstant('DT_LANGUAGES');
        $this->system->defineConstant('DT_CMS_PAGETYPE');
        
    }

    
    private function getVal($field_id, $is_safe=true){
        return $this->getValue($this->siteRecord, $field_id, $is_safe, $this->currentLang);
    }
        
    //
    // TBD Move to new class HRecord
    //
    private function getValue($record, $field_id, $is_safe=false, $lang=null){
        
        if(is_string($field_id) && strpos($field_id,'-')){
            $field_id = ConceptCode::getDetailTypeLocalID($field_id);
        }

        $val = @$record['details']?@$record['details'][$field_id]:@$record[$field_id];

        if(is_array($val) && count($val)>0){
            
            if($lang==null){
                $val = array_shift($val); //get first  
            }else{
                $val = getCurrentTranslation($val, $lang);    
            }

        }elseif($val==null){
            $val ='';
        }
        
        return $is_safe ?htmlspecialchars(strip_tags($val)) :$val;
    }
    
    //
    // Move to new class HRecord
    //
    private function getFile($record, $field_id, $def=''){

        if(is_string($field_id) && strpos($field_id,'-')){
            $field_id = ConceptCode::getDetailTypeLocalID($field_id);
        }

        $val = @$record['details']?@$record['details'][$field_id]:@$record[$field_id];

        if(is_array($val)){
            $file = array_shift($val);
            $file = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&file='.$file['fileid'];
        }else{
            $file = $def;
        }

        return $file;
    }
    
    /**
    * Returns values defined in siteRecord for website header meta tags
    *     
    * @param mixed $field
    * @param mixed $is_out
    */
    public function meta($field, $is_out=true){
        
        $val = '';
        if($this->siteRecord){
            
            $codes = array('title'=>DT_NAME, 
                           'keywords'=>DT_CMS_KEYWORDS,
                           'description'=>DT_SHORT_SUMMARY);
                           //'content'=>DT_EXTENDED_DESCRIPTION);
            
            $val = '';
            
            if(array_key_exists($field, $codes)){
                $val = $this->getVal($codes[$field]);
            }elseif($field=='lang'){
                $val = $this->currentLang; 
            }elseif($field=='favicon'){            
                $val = $this->getFile($this->siteRecord, DT_THUMBNAIL, (HEURIST_BASE_URL.'favicon.ico'));
            }
            
        }elseif($field=='favicon'){
            $val = HEURIST_BASE_URL.'favicon.ico';
        }elseif($field=='title'){
            $val = HEURIST_TITLE;
        }
        
        if($is_out){
            echo $val;
        }
        return $val;
    }

    public function getSiteId(){
        return $this->siteRecord['rec_ID'] ?? 0;
    }

    public function getPageId(){
        $rec = $this->pageRecord??$this->siteRecord;
        return $rec['rec_ID'] ?? 0;
    }
    
    //
    // Returns page main content (stored in DT_EXTENDED_DESCRIPTION)
    //
    public function getPageContent($is_out=true){
        
        if($this->messageError){
            $val = $this->messageError;
        }else{
            $val = '';
        /*
            if($this->pageRecord && !@$this->pageRecord['details']){
                recordSearchDetails($this->system, $this->pageRecord, true);
            }
            
            $record = $this->pageRecord??$this->siteRecord;
            $val = @$record['details']?@$record['details'][DT_EXTENDED_DESCRIPTION]:@$record[DT_EXTENDED_DESCRIPTION];
            if(is_array($val) && count($val)>0){
                $val = implode('',$val);
            }
            //TBD $this->currentLang
        */            
        }
        
        if($is_out){
            echo $val;
        }
        return $val;
    }
    
    /*
    *
    */
    private function getTemplateContent($type, $filename=null){
        
        if(!$filename){
            $filename = 'default.html';
        }
        
        $templateType = '';
        if($type=='footer'){
            $templateType = 'footers/';
        }elseif($type=='header'){
            $templateType = 'headers/';
        }
        
        $template = null;
        $full_filename = HEURIST_DIR.'hserv/web/templates/'.$templateType.basename($filename);
        if(file_exists($full_filename)){
            $template = file_get_contents($full_filename);
        }
        if($template==null || $template==''){
            //header template not defined - take the default one
            $template = $this->getTemplateContent($type);
        }
        return $template;
    }

    //
    // 
    //
    //
    /*
    *  Loads template for header/footer from file, record or given template 
    *  Returns raw template or processed template
    */
    public function getPageMargin($type){
        
        if($this->messageError){
            return '';
        }
        
        if(@$this->params[$type]){
            //it can be eather template file name or template content
            $template = $this->params[$type];
            if(strlen($this->params[$type])<20){
                //assume this is file name
                $template = $this->getTemplateContent($type, $template);
            }
        }else{
            //take from website home 
            if($this->isWebPage){
                $template = '<header><div data-heurist-cms="HMenuPersonal" style="display:{$website.showLogin};position:fixed;left:10px;z-index:9999"></div><div style="position:fixed;right:10px;z-index:9999">{$website.languages}</div></header>';
            }else{
                $template = $this->getVal($type=='footer'?DT_CMS_FOOTER:DT_CMS_HEADER, false);    
                if(!$template){ //not defined
                    $template = $this->getTemplateContent($type);
                }
            }
        }
        
        if(!$this->isWebPage && strpos($template,'<'.$type.' ')!==0){
            if($type=='header'){
                $template = '<header id="main-header" style="background-image: url(&quot;{$website.bgImage}&quot;) !important; background-repeat: repeat-x !important; background-size: auto 100%;">'.$template.'</header>';
            }else{
                $template = '<footer id="page-footer">'.$template.'</footer>';
            }
        }

        //return raw template
        if(@$this->params['raw']){
            return $template;
        }
        
        //    
        $bgImage = $this->getFile($this->siteRecord, '99-951', ''); //DT_CMS_BANNER
        if($bgImage!=null){
            //$bgImage = 'background-image: url(&quot;'.$bgImage
            //    .'&quot;) !important; background-repeat: repeat-x !important; background-size: auto;';
        }else{
            $bgImage = '';
        }

        //backward capability with v2
        if(strpos($template,'id="main-logo"')>0){
            $doc = new \DOMDocument();
            //$doc->preserveWhiteSpace = false;
            $doc->loadHTML($template);
            $ele = $doc->getElementById('main-logo');
            if($ele) {
                //$divInner = $doc->createDocumentFragment();
                //$divInner->appendXML('<img src="{$website.logo}" alt="Logo"/>');
                //$ele->appendChild($divInner);
                
                $img = $doc->createElement("img");
                $img = $ele->appendChild($img);
                $img->setAttribute('src', '{$website.logo}');                
                
                //$ele->nodeValue = '<img src="{$website.logo}" alt="Logo"/>';
            }
            
            
            $ele = $doc->getElementById('main-title');
            if($ele) $ele->nodeValue = '{$website.title}';
            $template = $doc->saveHTML();            
            
            $template = str_replace('%7B%24','{$',$template);
            $template = str_replace('%7D','}',$template);
        }
/*        
            #main-logo
            main-title
            main-logo-alt
            main-title-alt
            main-title-alt2
*/        
        
        //get header settings
        //replace template values {} with settings from siteRecord (CMS_HOME)
        $header_tpl = $this->getWebSiteOptions( true );
            
        $values_to_replace = array_map(function ($v) {
                    return "{\$website.$v}";
             }, array_keys($header_tpl));
             
        //$header.classes DT_CMS_BANNER
        $result = str_replace($values_to_replace, array_values($header_tpl), $template);
                
        return $result;
    }
    
    //
    //
    //
    public function getWebSiteOptions($isFull){

        //main menu - json array 
        $menuContent = $this->getMenuTree();
        
        $isFixedFooter = false;
        
        $footerPosition = $this->getVal(DT_CMS_FOOTER_FIXED);
        $isFixedFooter = (!($footerPosition == ConceptCode::getTermLocalID('3-5029') || //unset position
                            $footerPosition == TRM_NO)); //not fixed position  ConceptCode::getTermLocalID('2-531')
        
        $webSiteOptions = array(
            'siteId'=>$this->getSiteId(),
            'pageId'=>$this->getPageId(),   //initial page
            'siteMenu'=>$menuContent,       //need for edit mode only
            'isWebPage' =>$this->isWebPage,
            'isShowTitle'=>$this->getVal('99-952')!=TRM_NO,
            'isFixedFooter'=>$isFixedFooter,
            //'languageCodes'=>$this->getLanguages()
        );
        
        if($isFull){
        
            $webSiteOptionsExt = array(
                'logo'=>$this->getFile($this->siteRecord, DT_FILE_RESOURCE), 
                'logoAlt'=>$this->getFile($this->siteRecord, '2-926'), 
                
                'title'=>$this->getVal(DT_NAME),
                'description'=>$this->getVal(DT_SHORT_SUMMARY),
                'titleAlt1'=>$this->getVal('3-1009'),
                'titleAlt2'=>$this->getVal('2-1052'),
                
                'url'=>$this->getPageUrl(0),
                'urlAlt'=>$this->getVal('2-943') ?? '#',
                
                'bgImage'=>$bgImage,
                'languages'=>$this->getLanguageSelector(), //returns html snipper for language selector
                
                'showLogin'=>'block',
                'navbar'=>$this->getMainMenu(),  //returns html snippet for navbar menu  need for v3
                
                'pageTitle'=>'',
                
                'hostInfo' => '',
                'heuristInfo'=>'<a href="https://HeuristNetwork.org" target="_blank" style="text-decoration:none;" title="This website is generated by Heurist, an academic knowledge management system developed at the University of Sydney Faculty of Arts and Social Sciences under the direction of Dr Ian Johnson, chief programmer Artem Osmakov.">
                powered by &nbsp;&nbsp;<img src="'.ASSETS_URL.'v6/logo.png" height="32"> Heurist
                </a>'
            );
            
        
            $val = $this->getVal('2-1095');    
            $webSiteOptionsExt['showLogin']=$val==TRM_NO?'none':'block';
                
            list($host_logo, $host_url) = USystem::getHostLogoAndUrl();
            if($host_logo){
                $webSiteOptionsExt['hostInfo'] = '<a href="'.($host_url??'#')
                    .'" target="_blank" style="text-decoration:none;color:black;">'
                    .' at: &nbsp;<img src="'.$host_logo.'" height="32" align="center"></a>';
            }
                
            
            if(!$webSiteOptionsExt['logoAlt']){
                $webSiteOptionsExt['logoAlt'] = ASSETS_URL.'16x16.gif';
            }

            $webSiteOptions = array_merge($webSiteOptions, $webSiteOptionsExt);        
        }
        
        return $webSiteOptions;
    }

    //
    // For header - language selector
    //
    private function getLanguageSelector(){
        
        $res = '';
        if(defined('DT_LANGUAGES')){
            $website_languages = @$this->siteRecord['details'][DT_LANGUAGES];
        
            if(!isEmptyArray($website_languages)){
                //$website_languages = array_values($website_languages);
                $orig_arr = print_r($website_languages,true);
                $website_languages_codes = getTermCodes($this->system->getMysqli(), $website_languages);
                
                $website_languages_res = array();//defined codes

                foreach($website_languages as $term_id){
                    $lang_code = @$website_languages_codes[$term_id];

                    if($lang_code){
                        $lang_code = strtoupper($lang_code);
                        if($website_language_def=='') {$website_language_def = $lang_code;} //first language in list
                        $res = $res.'<option value="'.$lang_code.'" '
                            .(($this->currentLang==$lang_code)?'selected':'')
                            .'>'.$lang_code.'</option>';
                        //'<a href="#" data-lang="'.$lang_code.'" onclick="switchLanguage(event)">'.$lang_code.'</a><br>';
                    }
                }
                //$website_languages = $website_languages_res;
            }
        }        
        
        if($res!=''){
             $res = '<select id="main-languages" class="form-select-sm me-2 w-auto">'.$res.'</select>';
        }
        return $res;
    }
    
    //
    //
    //
    private function getPageUrl($pageId){
        
        $url = HEURIST_BASE_URL.'?db='.$this->system->dbname()
                .'&ver=3&website='.$this->siteRecord['rec_ID'];
        if($pageId>0){
            $url .= '&pageid='.$pageId;
        }
        if(@$this->params['edit']){
              $url .= '&edit='.$this->params['edit'];
        }
        if($this->currentLang && $this->currentLang!='def'){
              $url .= '&lang='.$this->currentLang;
        }
        
        return $url;
    }

    //
    // Returns submenu as html
    //
    private function getMainSubMenu($menu, $records){
        
        $res = '<ul class="dropdown-menu dropdown dropend">';
        
        foreach($menu as $id=>$subs){
            
            $menu_title = $this->getValue($records[$id], DT_NAME, true, $this->currentLang);

            $has_subs = !empty($subs);
            if($has_subs){ 
                $res .= '<li class="dropdown dropend"><a class="dropdown-item dropdown-toggle" href="#" id="dropdown-layouts" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.$menu_title.'</a>';
                
                $res .= $this->getMainSubMenu($subs, $records);
                
                $res .= '</li>';
                
            }else{
                $res .= '<li><a class="dropdown-item" data-heurist-pageid="'
                          .$id.'" href="'.$this->getPageUrl($id).'">'.$menu_title.'</a></li>';
            }
            
        }
        
        return $res .= '</ul>';
                
    }
    
    //
    // For header - navbar with first level of menu
    // Returns menu as HTML snippet
    //
    private function getMainMenu(){
        
        if($this->isWebPage){
            return '';
        }
        
        $this->fillMenuTree();    
        
        $siteID = $this->siteRecord['rec_ID'];
        $menu_tree = $this->menuTree[$siteID];
        
        $res = '<ul class="navbar-nav ms-auto dropdown-hover-all">'; //nav nav-pills  navbar-nav
        
        foreach($menu_tree as $id=>$subs){ //first level is list of buttons with dropdowns
        
            $menu_title = $this->getValue($this->menuRecords[$id], DT_NAME, true, $this->currentLang);
            
            $has_subs = !empty($subs);
            if($has_subs){

                $res .= '<li class="nav-item dropdown"><a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">'.$menu_title.'</a>';
                
                $res .= $this->getMainSubMenu($subs, $this->menuRecords);
                
                $res .= '</li>';
                
            }else{
                $res .= '<li class="nav-item"><a class="nav-link" data-heurist-pageid="'
                        .$id.'" href="'.$this->getPageUrl($id).'">'.$menu_title.'</a></li>';
            }
        }
        $res .= '</ul>';
        return $res;
                
    }
    
    //
    //
    //
    private function fillMenuTree($menuRecIDs=null){
        
        $this->system->defineConstant('DT_CMS_TOP_MENU');
        $this->system->defineConstant('DT_CMS_MENU');
        $this->system->defineConstant('DT_NAME');
        //$this->system->defineConstant('DT_CMS_TARGET');
        
        if($this->menuTree==null){
            $this->menuRecords = array();  //
            //see recordSearch.php
            $this->menuTree = recordSearchMenuItems2($this->system, $menuRecIDs, $this->menuRecords, true );
        }
    }

    //
    //  Returns json tree for menu (used in _editCMS_SiteMenu)
    //
    public function getMenuTree($parentMenuRecIDs=null, $menu_tree=null){
        
        if($menu_tree==null){ //root
        
            if($parentMenuRecIDs==null){
                $siteID = $this->siteRecord['rec_ID'];
                $parentMenuRecIDs = array($siteID);
            }else{
                $parentMenuRecIDs = prepareIds($parentMenuRecIDs);
            }
            
            $this->fillMenuTree($parentMenuRecIDs);

            if(count($parentMenuRecIDs)==1 && false){  //include home as first level menu
                $parentKey = $parentMenuRecIDs[0];
                $menu_tree = $this->menuTree[$parentKey];
            }else{
                $parentKey = 0;
                $menu_tree = $this->menuTree;
            }
        }else{
            $parentKey = $parentMenuRecIDs;
        }
        
        $res = array();

        foreach($menu_tree as $page_id=>$subs){ //first level is list of buttons with dropdowns
        
            $menuName = $this->getValue($this->menuRecords[$page_id], DT_NAME, true, $this->currentLang);
            
            $key = $parentKey.','.$page_id;
            
            $item = array();
            $item['key'] = $key; // set unique key
            $item['title'] = $menuName;
            $item['parent_id'] = $parentKey; //reference to parent menu(or home)
            $item['page_id'] = $page_id;
            /*
            $item['page_showtitle'] = 1;
            $item['page_target'] = ''; //(this.options.target=='popup')?'popup':pageTarget;
            //$res['expanded'] = (this.options.expand_levels>0 || lvl<this.options.expand_levels); 
            $item['has_access'] = true;
                        //(window.hWin.HAPI4.is_admin() 
                        //|| window.hWin.HAPI4.is_member(resdata.fld(record,'rec_OwnerUGrpID')));
            */
                               
            $has_subs = !empty($subs);
            if($has_subs){
                $item['children'] = $this->getMenuTree($key, $subs);
            }
            
            array_push($res, $item);
        }
        
        return $res;
    }
    
    //
    // for output (0,2,3,4) - outs error page with message
    // for file only (1) - in browser only
    //
    private function outputError($error_msg=null){

        if(!isset($error_msg)){
            $error_msg = $this->system->getErrorMsg();
            if($error_msg==''){
                $error_msg = 'Undefined error';
            }
            $error_msg = '<span class="text-danger fw-bold">'.$error_msg.'</span>';
        }

        $this->messageError = $error_msg;

        //$this->handleOutput($error_msg);
    }

    public function getError(){
        return $this->messageError;
    }

    /**
     * Handles the output of website content, save to file  or outputting it as required.
     *
     * @param string $website_output The rendered website output.
     * @param bool $need_sanitize Whether or not to sanitize the output.
     * 
     * @return true/false or string for publishmode==4
     */
    private function handleOutput($website_output, $need_sanitize=true){

        $errors = null;

        //sanitize
        if($need_sanitize){
            //$website_output = $this->stripJavascriptAndSantize($website_output);
        }
        
        if ($this->publishmode==4) {
            return $website_output;
        }
        

        if($this->publishmode!=1){ // 1 = save to file only
            header("Content-type: text/html;charset=UTF-8");
        }
        
        // 0 - in browser only (returns true/false)
        // 1 - saves into generated-html (returns true/false)
        // 2 - download generated html as file
        // 3 - saves into generated-html and report output
        // 4 - returns output as string variable
        if ($this->publishmode==2) {//download

                header('Pragma: public');
                header('Content-Disposition: attachment; filename="'.$this->outputfile.'"');
                header(CONTENT_LENGTH . strlen($website_output));
                echo $website_output;

        }elseif ($this->publishmode==0) {    //browser output only

            echo $website_output;
            
        }else {
            //3 - save into file and report
            //1 - save into file and info page

            if($this->outputfile!=null){
                $errors = $this->saveOutputToFile($this->outputfile, $website_output);
            }
            
            
            if($this->publishmode==3){
                echo $website_output; //both save and output
            }else{ //publishmode==1 - save to file and report
                //$this->generateInfoPage($this->outputfile, $errors);
                
                //TBD
            }
        }
    }

    //
    //
    //
    private function saveOutputToFile($file_name, $web_output){

        $errors = null;

        try{
            //output to generated-reports only
            $dirname = $this->system->getSysDir(DIR_GENERATED_HTML);
            if(!folderCreate($dirname, true)){
                return 'Failed to create folder for generated reports';
            }

            $res_file = $dirname."/".$file_name; // acutal file
            $temp_file = $dirname."/_".$file_name; // temporary file, if needed

            $file = false; // file handler
            $use_temp = false; // using temporary file

            if(!file_exists($res_file) || is_writable($res_file)){ // open existing file
                $file = fopen ($res_file, "w");
            }else{ // create temp file to replace original
                $file = fopen($temp_file, "w");
                $use_temp = true;
            }

            if(!$file){
                $errors = "Can't write file $res_file. Check permission for directory";
            }else{
                fwrite($file, $web_output);
                fclose($file);
            }

            if($use_temp){

                if(unlink($res_file) === false){ // Delete old file
                    unlink($temp_file);// on error, remove temp file
                    $errors = "Can't delete old webpage file $res_file. Check permission for file";
                }elseif(rename($temp_file, $res_file) === false){ // Rename temp file
                    unlink($temp_file);// on error, remove temp file
                    $errors = "Can't rename webpage file $temp_file to $res_file. Check permissions";
                }
            }


        }catch(\Exception $e)
        {
            $errors = $e->getMessage();
        }

        return $errors;

    }

    //------------------------
    /**
     * Adds custom styles and scripts from CMS settings.
     *
     * @return string The HTML content containing custom styles and scripts.
     */
    // 
    // includes publisher's custom scripts and styles AND links to external resources
    //
    public function getCustomScriptsAndStyles()
    {
        
         $head = '';
         $css_fields = array();
         if($this->system->defineConstant('DT_CMS_CSS')){
             array_push($css_fields, DT_CMS_CSS);
         }
         if($this->system->defineConstant('DT_CMS_EXTFILES')){
             array_push($css_fields, DT_CMS_EXTFILES);
         }
         if($this->system->defineConstant('DT_CMS_SCRIPT')){
             array_push($css_fields, DT_CMS_SCRIPT);
         }
         if(empty($css_fields)){
             return '';
         }

         $record = recordSearchByID($this->system, $this->getSiteId(), $css_fields, 'rec_ID');
         if(!@$record['details']){
            return '';
         }

         if(defined('DT_CMS_CSS') && @$record['details'][DT_CMS_CSS]){
             //add to begining
             $val = $this->getValue($record, DT_CMS_CSS);
             $head .= '<style>'.$val.'</style>';
         }
         
         if($this->system->settings->isJavaScriptAllowed()){

             if(defined('DT_CMS_SCRIPT') && @$record['details'][DT_CMS_SCRIPT]){
                 //add to begining
                 $val = $this->getValue($record, DT_CMS_SCRIPT);
                 $head .= '<script>function afterPageLoad'.$this->getSiteId().'(){'.$val.'}</script>';
             }
             
             if(defined('DT_CMS_EXTFILES') && @$record['details'][DT_CMS_EXTFILES]){
                 //add to header
                 $external_files = $record['details'][DT_CMS_EXTFILES] ?? [];
                 if(!is_array($external_files)){
                         $external_files = array($external_files);
                 }

                 foreach ($external_files as $ext_file){
                    $head .= $ext_file;
                 }
             }
         }

         return $head;
    }
    
    /**
     * Returns website info (title, description) as json
     */
    public function getWebSiteInfo(){
       return '';
/* TBD                
       $webinfo = array('title'=>$this->getVal(DT_NAME),
            'description'=>$this->getVal(DT_SHORT_SUMMARY),
            'titleAlt1'=>$this->getVal('3-1009'),
            'titleAlt2'=>$this->getVal('2-1052'));
        
       return '<script>var websiteInfo='.json_encode($webinfo).'</script>';; 
*/       
    }

    /**
     * Handles the case where JavaScript is allowed.
     *
     * @param string $tpl_source The source template content.
     * @param string $font_styles The CSS font styles.
     * @return string The modified template content with JavaScript and styles.
     */
    private function handleJsAllowed($tpl_source, $font_styles){

    }

    /**
     * Sanitizes the HTML using HTMLPurifier.
     *
     * @param string $tpl_source The source template content.
     * @param string $font_styles The CSS font styles.
     * @return string The sanitized template content.
     */
    private function sanitizeHtml($tpl_source, $font_styles)
    {

                //if javascript not allowed, use html purifier to remove suspicious code
                $config = \HTMLPurifier_Config::createDefault();
                $config->set('HTML.Doctype', 'HTML 4.01 Transitional');

                $config->set('HTML.DefinitionID', 'html5-definitions');// unqiue id
                $config->set('HTML.DefinitionRev', 1);

                $config->set('Cache', 'SerializerPath', $this->system->getSysDir('scratch'));
                $config->set('CSS.Trusted', true);
                $config->set('Attr.AllowedFrameTargets','_blank');
                $config->set('HTML.SafeIframe', true);
                //allow YouTube, Soundlcoud and Vimeo
                // https://w.soundcloud.com/player/
                $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|w\.soundcloud\.com/player/)%');

                $def = $config->getHTMLDefinition(true);
                $def->addElement(
                    'audio',
                    'Block',
                    'Flow',
                    'Common',
                    [
                        'controls' => 'Bool',
                        'autoplay' => 'Bool',
                        'data-id' => 'Number'
                    ]
                );
                $def->addElement('source', 'Block', 'Flow', 'Common', array(
                    'src' => 'URI',
                    'type' => 'Text',
                ));
                
                //$config->set('HTML.AllowedAttributes','*.data-heurist-rec');
                $def->addAttribute('div', 'data-heurist-rec', 'Number');

                /* to test it
                if ($def = $config->maybeGetRawHTMLDefinition()) {
                    // http://developers.whatwg.org/the-video-element.html#the-video-element
                    $def->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', array(
                        'src' => 'URI',
                        'type' => 'Text',
                        'width' => 'Length',
                        'height' => 'Length',
                        'poster' => 'URI',
                        'preload' => 'Enum#auto,metadata,none',
                        'controls' => 'Bool',
                    ));
                }
                $config->set('HTML.Trusted', true);
                $config->set('Filter.ExtractStyleBlocks', true);

                */

                $purifier = new \HTMLPurifier($config);

                $tpl_source = $purifier->purify($tpl_source);

                if(!empty($font_styles)){
                    if(strpos($tpl_source, '<head>')>0){
                        $tpl_source = str_replace(HEAD_E,$font_styles.HEAD_E, $tpl_source);
                    }else{
                        $tpl_source = $font_styles.$tpl_source;
                    }
                }

                return $tpl_source;
    }

    /**
     * Strips JavaScript and sanitizes HTML output.
     * This function is called before other output filters.
     *
     * @param string $tpl_source The source template content.
     * @param \Smarty\Template $template The Smarty template object.
     * @return string The sanitized template content.
     */
    private function stripJavascriptAndSantize($web_output){
        return $web_output;
    }

}
