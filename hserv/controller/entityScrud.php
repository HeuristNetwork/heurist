<?php
/**
* entityScrud.php - Controller to SCRUD most of database tables/entities
*
* It uses functions from entityScrudSrv.php to search, create, read, update and delete entries in most of database tables.
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
    require_once dirname(__FILE__).'/../../autoload.php';

    use hserv\utilities\USanitize;
    use hserv\utilities\USystem;

    require_once 'entityScrudSrv.php';

    if(!isset($req_params)){ //if set array has been already modified in api.php
        $req_params = USanitize::sanitizeInputArray();
    }

    $dbname = @$req_params['db'];

    $system_init_failed = false;

    $system = new hserv\System();

    $dbdef_cache = null;
    $db_time = 0;

    $db_check_error = mysql__check_dbname( $dbname );//validate db name

    if($db_check_error==null
        && isset($defaultRootFileUploadURL)
        && strpos($defaultRootFileUploadURL,'sydney.edu.au')===false )
    {
            $path = $system->getSysDir('entity', $dbname);
            if(is_dir($path) && file_exists($path)){
                $dbdef_cache = $path.'dbdef_cache.json';
            }
    }

    //
    // for structure request (to refresh db defs on client side)
    // entity parameters may be a name of structure table or
    // all - get all definitions (from cache dbdef_cache.json if it exists)
    // force_all - get all definitions and update dbdef_cache.json
    // relevance - compare filetime with timestamp,
    //                      if filetime is later - returns definitions
    //                      otherwise file time
    if( @$req_params['a']=='structure' && @$req_params['entity']=='relevance' &&
            ($dbdef_cache==null || !file_exists($dbdef_cache)) ){
            //ignore relevance check if file is missed
            header(CTYPE_JSON);
                print json_encode( array('uptodate'=>1, 'cachefile'=>$dbdef_cache));
            exit;
    }
    
    if( @$req_params['a']=='structure'
        && (@$req_params['entity']=='all' || @$req_params['entity']=='relevance')
        && $dbdef_cache!=null && file_exists($dbdef_cache)
        ){
            if($db_check_error==null){

                $dbdef_cache_is_uptodate = true;

                if($req_params['entity']=='relevance'){
                    $req_params['entity'] = 'all';
                    $file_time = filemtime($dbdef_cache);

                    if($file_time - intval($req_params['timestamp']) < 100){
                        //compare file time with time of db defs on client side
                        //defintions are up to date on client side
                        header(CTYPE_JSON);
                        print json_encode( array('uptodate'=>$file_time, 
                                'req_timestamp'=>$req_params['timestamp']) );
                                //'cachefile'=>$dbdef_cache) );
                        exit;
                        //otherwise download dbdef cache
                    }
                }else{
                    //check file time and last update time of definitions
                    if($system->init($dbname)){
                        $dbdef_mod = getDefinitionsModTime($system->getMysqli());//see utils_db

                        if($dbdef_mod!=null){
                            $db_time  = $dbdef_mod->getTimestamp();
                            $file_time = filemtime($dbdef_cache);

                            if($db_time>$file_time){ //db def cache is outdated
                                  $req_params['entity'] = 'force_all';
                                  $dbdef_cache_is_uptodate = false;
                            }
                        }

                    }else{
                        $system_init_failed = true;
                    }
                }

                if($dbdef_cache_is_uptodate){
                    //download db def cache or direct access
                    if(isset($allowWebAccessEntityFiles) && $allowWebAccessEntityFiles)
                    {
                        $host_params = USystem::getHostParams();
                        //
                        if(strpos($defaultRootFileUploadURL, $host_params['server_url'])===0){
                            $url = $defaultRootFileUploadURL;
                        }else{
                            //replace server name to avoid CORS issues
                            $parts = explode('/',$defaultRootFileUploadURL);
                            $url = $host_params['server_url'] . '/' . implode('/',array_slice($parts,3));
                        }

                        //rawurlencode - required for security reports only
                        $url = $url.rawurlencode($dbname).'/entity/dbdef_cache.json';
                        redirectURL($url);

                    }else{
                        downloadFile(MIMETYPE_JSON, $dbdef_cache);
                    }
                    exit;
                }
            }else{
                exit;//wrong db name
            }

    }

    $response = array();
    $res = false;

    $entity = null;

    $need_config = false;

    if( (!$system_init_failed)  //system can be inited beforehand for getDefinitionsModTime
        && ($system->isInited() || $system->init($dbname)))
    {

        //USanitize::sanitizeRequest($req_params); it brokes json strings
        USanitize::stripScriptTagInRequest($req_params);//remove <script>

        $res = array();
        $entities = array();

        if(@$req_params['a']=='structure'){
            // see HAPI4.refreshEntityData
            if(@$req_params['entity']=='force_all'){  //recreate cache
                $req_params['entity'] = 'all';
                //remove cache
                if($dbdef_cache!=null){
                    $system->cleanDefCache();
                }
            }elseif(@$req_params['entity']=='relevance'){
                $req_params['entity'] = 'all';
            }
            $res = entityRefreshDefs($system, @$req_params['entity'], true);

            //update dbdef cache
            if(@$req_params['entity']=='all' && $res!==false && $dbdef_cache!=null){
                //$res['db_time'] = $db_time;
                //update db defintion cache file
                file_put_contents($dbdef_cache, json_encode($res));
                $res['timestamp'] = filemtime($dbdef_cache);//it will be stored on client side
            }

        }else {
            $res = entityExecute($system, $req_params);
        }

        $system->dbclose();
    }


    if(intval(@$req_params['restapi'])>0){

        if( is_bool($res) && !$res ){

            $system->errorExitApi();

        }else{
            header(HEADER_CORS_POLICY);
            header(CTYPE_JSON);

            if(intval(@$req_params['restapi'])===2 && isset($apiResponseContext)){
                $res = prepareDefinitionApiResponse($res, $req_params, $apiResponseContext);
                if($res===null){
                    $system->errorExitApi('Definition not found', HEURIST_NOT_FOUND);
                }
            }

            $code = (@$req_params['a'] == 'save') ? 201 : 200;
            http_response_code($code);

            print json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }else{
        header(CTYPE_JSON);

        if( is_bool($res) && !$res ){
            $response = $system->getError();
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
        }

        if (strnatcmp(phpversion(), '7.3') >= 0) {

            try{

               $res = json_encode($response, JSON_THROW_ON_ERROR);

               if(false && strlen($res)>20000){
                   ob_start();
                   echo json_encode($res);
                   $output = gzencode(ob_get_contents(),6);
                   ob_end_clean();
                   header('Content-Encoding: gzip');
                   echo $output;
                   unset($output);
               }else{
                   echo $res;
                   unset($res);
               }


            } catch (JsonException $e) {

                $res = json_encode($response, JSON_INVALID_UTF8_IGNORE );

                print $res;
            }

        }else{

            $res = json_encode($response);//JSON_INVALID_UTF8_IGNORE
            if(!$res){

                //
                //find wrong value
                $wrong_string = null;
                try{
                    array_walk_recursive($response, 'find_invalid_string');

                }catch(Exception $exception) {
                       $wrong_string = $exception->getMessage();
                }

                $msg = 'Your data definitions (names, descriptions) contain invalid characters (non UTF-8). '
                .'Or system cannot convert them properly.';

                if($wrong_string){
                    $msg = $msg . ' Invalid character in string: '.$wrong_string;
                }

                $system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                print json_encode( $system->getError() );
            }else{
                print $res;
            }

        }


    }

    /**
     * Converts the normal DbEntitySearch result into the public definition API format.
     * This is used only for restapi=2 requests prepared by api.php.
     *
     * @param array $result Normal DbEntitySearch response.
     * @param array $params Sanitized request parameters.
     * @param array $context API route context prepared by api.php.
     * @return array|null Collection envelope, single item, or null when an item is not found.
     */
    function prepareDefinitionApiResponse($result, $params, $context){

        $items = definitionApiItems($result);
        $mode = @$context['mode'] === 'item' ? 'item' : 'collection';

        if($mode === 'item'){
            return count($items)>0 ? reset($items) : null;
        }

        $offset = max(0, intval(@$params['offset']));
        $limit = intval(@$params['limit']);
        if($limit<1){
            $limit = 1000;
        }

        $total = isset($result['count']) ? intval($result['count']) : count($items);

        $meta = array(
            'database' => @$params['db'],
            'entity' => @$context['entity']
        );
        if(isset($context['recordTypeId'])){
            $meta['recordTypeId'] = intval($context['recordTypeId']);
        }

        $pagination = array(
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'self' => definitionApiPageUrl($offset, $limit),
            'next' => null
        );

        $nextOffset = $offset + count($items);
        if(count($items)>0 && $nextOffset < $total){
            $pagination['next'] = definitionApiPageUrl($nextOffset, $limit);
        }

        return array(
            'items' => array_values($items),
            'meta' => $meta,
            'pagination' => $pagination
        );
    }

    /** Convert DbEntitySearch rows to API items while preserving the selected fields. */
    function definitionApiItems($result){

        if(!is_array($result) || !isset($result['records']) || !is_array($result['records'])){
            return array();
        }

        // details=id produces a list of integer IDs and has no fields array.
        if(!isset($result['fields']) || !is_array($result['fields'])){
            return array_values($result['records']);
        }

        $fields = $result['fields'];
        $records = $result['records'];
        $items = array();

        $orderedKeys = array();
        if(isset($result['order']) && is_array($result['order'])){
            $orderedKeys = $result['order'];
        }else{
            $orderedKeys = array_keys($records);
        }

        foreach($orderedKeys as $key){
            if(!array_key_exists($key, $records)){
                continue;
            }
            $row = $records[$key];
            $item = array();
            foreach($fields as $idx=>$field){
                $item[$field] = array_key_exists($idx, $row) ? $row[$idx] : null;
            }
            $items[] = $item;
        }

        return $items;
    }

    /** Build an absolute paging URL, preserving all query parameters. */
    function definitionApiPageUrl($offset, $limit){

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '';
        $query = array();
        $queryString = parse_url($requestUri, PHP_URL_QUERY);
        if($queryString){
            parse_str($queryString, $query);
        }
        $query['offset'] = intval($offset);
        $query['limit'] = intval($limit);

        $hostParams = USystem::getHostParams();
        $base = rtrim($hostParams['server_url'], '/');
        return $base.$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }


    /**
     * Finds invalid UTF-8 strings in a recursive array walk.
     *
     * This function is typically used as a callback for array_walk_recursive.
     * It checks if a string value contains invalid UTF-8 characters.
     * If an invalid string is found, it throws an Exception with the
     * UTF-8 converted string.
     *
     * @param mixed $val The value to check.
     * @throws \Exception If an invalid UTF-8 string is found.
     * @return void
     */
    function find_invalid_string($val){
        if(is_string($val)){
            $stripped_val = iconv('UTF-8', 'UTF-8//IGNORE', $val);/* important */
            if($stripped_val!=$val){
                throw new Exception(mb_convert_encoding($val,'UTF-8'));
            }
        }
    }
/*
Description Of Usual Server Responses:
200 OK - the request was successful (some API calls may return 201 instead).
201 Created - the request was successful and a resource was created.
204 No Content - the request was successful but there is no representation to return (i.e. the response is empty).
400 Bad Request - the request could not be understood or was missing required parameters.
401 Unauthorized - authentication failed or user doesn't have permissions for requested operation.
403 Forbidden - access denied.
404 Not Found - resource was not found.
405 Method Not Allowed - requested method is not supported for resource.
*/
?>