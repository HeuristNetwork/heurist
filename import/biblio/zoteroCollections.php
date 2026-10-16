<?php
/** Optional collection metadata for Zotero synchronisation. */
/** Import missing base definitions once; record-type assignment is optional. */
function zoteroEnsureCollectionDefinitions($system, &$messages) {
    $messages = [];
    foreach (['1774-1189','1774-1190','1774-1191'] as $concept) {
        if (\hserv\structure\ConceptCode::getDetailTypeLocalID($concept)) { continue; }
        $messages[] = 'Importing missing Zotero collection field '.$concept.' from Heurist_Core_Definitions automatically.';
        $import = new DbsImport($system);
        if (!$import->doPrepare(['defType'=>'detailtype', 'conceptCode'=>$concept]) || !$import->doImport()
            || !\hserv\structure\ConceptCode::getDetailTypeLocalID($concept)) {
            throw new RuntimeException('Could not import collection field '.$concept.' from Heurist_Core_Definitions. '.$system->getErrorMsg());
        }
        $messages[] = 'Collection field '.$concept.' imported.';
    }
}

function zoteroCollectionFields($mysqli, array $recordTypes, &$errors, &$nonstandard = null) {
    $errors = []; $fields = []; $nonstandard = [];
    $spec = ['1774-1189'=>['names','freetext'], '1774-1190'=>['identifiers','freetext'], '1774-1191'=>['paths','enum']];
    foreach ($spec as $concept => $config) {
        $id = intval(\hserv\structure\ConceptCode::getDetailTypeLocalID($concept));
        if (!$id) { $errors[] = 'Collection field '.$concept.' could not be obtained from Heurist_Core_Definitions.'; continue; }
        $type = mysql__select_value($mysqli, 'SELECT dty_Type FROM defDetailTypes WHERE dty_ID='.$id);
        if (($config[1]==='enum' && $type!=='enum') || ($config[1]==='freetext' && !in_array($type,['freetext','blocktext']))) {
            $errors[] = 'Existing collection field '.$concept.' differs from its base definition. Please contact support.';
            continue;
        }
        foreach ($recordTypes as $rt => $name) {
            // Values may be saved in non-standard fields even when absent from
            // defRecStructure. Do not silently discard collection data.
            $fields[$rt][$config[0]] = $id;
            $present = mysql__select_value($mysqli, 'SELECT rst_ID FROM defRecStructure WHERE rst_RecTypeID='.intval($rt).' AND rst_DetailTypeID='.$id);
            if (!$present) { $nonstandard[$rt] = $name; }
        }
    }
    return $fields;
}

function zoteroCollectionConfigurationReport(array $errors, array $nonstandard = [], array $messages = []) {
    if (!$errors && !$nonstandard && !$messages) { return ''; }
    $html = '<div style="padding:12px 16px;margin:18px 0;max-width:1000px;line-height:1.5;background:#f3f7f9;border:1px solid #bdcbd2">';
    foreach ($messages as $message) { $html .= '<p style="margin:0 0 12px">'.htmlspecialchars($message).'</p>'; }
    if ($errors) { $html .= '<p>'.htmlspecialchars(implode(' ', $errors)).'</p>'; }
    if ($nonstandard) {
        $html .= '<p style="margin:0">For record types which do not have the required fields, the value is added in the section '
            .'"Non-standard fields for this record type" and can be added to all records of the same type simply by clicking the up-arrow next to the value (displayed when in Structure Modification mode).</p>';
    }
    return $html.'</div>';
}

/** Get/create a field vocabulary without altering any populated term-set constraint. */
function zoteroCollectionVocabulary($system, $fieldID) {
    $mysqli = $system->getMysqli();
    $tree = mysql__select_value($mysqli, 'SELECT dty_JsonTermIDTree FROM defDetailTypes WHERE dty_ID='.intval($fieldID));
    if (is_numeric($tree) && intval($tree)>0) { return intval($tree); }
    if ($tree && $tree!=='[]' && $tree!=='null') {
        throw new RuntimeException('Field 1774-1191 must reference a single vocabulary, rather than a restricted set of terms. Configure its vocabulary in Design > Fields.');
    }
    $root = intval(mysql__select_value($mysqli,
        'SELECT trm_ID FROM defTerms WHERE (trm_ParentTermID IS NULL OR trm_ParentTermID=0) AND trm_Domain="enum" AND trm_Label=?',
        ['s','Zotero collections']));
    if (!$root) {
        $entity = new \hserv\entity\DbDefTerms($system);
        $entity->setData(['isfull'=>1, 'fields'=>[['trm_Label'=>'Zotero collections', 'trm_ParentTermID'=>null, 'trm_Depth'=>0, 'trm_Domain'=>'enum']]]);
        $ids = $entity->save();
        if ($ids===false || empty($ids)) { throw new RuntimeException('Cannot create the Zotero collections vocabulary: '.$system->getErrorMsg()); }
        $root = intval(reset($ids));
    }
    if (!mysql__insertupdate($mysqli, 'defDetailTypes', 'dty_', ['dty_ID'=>intval($fieldID), 'dty_JsonTermIDTree'=>(string)$root, 'dty_Modified'=>date('Y-m-d H:i:s')])) {
        throw new RuntimeException('Cannot attach the Zotero collections vocabulary to field 1774-1191.');
    }
    return $root;
}

function zoteroFetchCollections($apiKey, $type, $libraryID){
    $collections = [];
    for($start = 0; ; $start += 100){
        $url = 'https://api.zotero.org/'.$type.'/'.rawurlencode($libraryID).'/collections?format=json&limit=100&start='.$start;
        $curl = curl_init($url);
        $options = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>20, CURLOPT_TIMEOUT=>120,
            CURLOPT_HTTPHEADER=>['Zotero-API-Version: 3', 'Zotero-API-Key: '.$apiKey]];
        if(defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE && defined('HEURIST_HTTP_PROXY')){
            $options[CURLOPT_PROXY] = HEURIST_HTTP_PROXY;
            if(defined('HEURIST_HTTP_PROXY_AUTH')){$options[CURLOPT_PROXYUSERPWD] = HEURIST_HTTP_PROXY_AUTH;}
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $page = json_decode($body ?: '', true);
        if($status != 200 || !is_array($page) || array_values($page) !== $page){
            throw new RuntimeException('Collection request failed (HTTP '.intval($status).'). Please retry.');
        }
        foreach($page as $collection){
            $data = $collection['data'] ?? null;
            if(!is_array($data) || empty($data['key']) || !isset($data['name']) || !array_key_exists('parentCollection', $data)){
                throw new RuntimeException('Invalid collection response.');
            }
            $collections[$data['key']] = $data;
        }
        if(count($page) < 100){break;}
    }
    return $collections;
}

/** Direct memberships only; ancestor names form the display path. */
function zoteroCollectionValues($keys, $collections, $library){
    if(!is_array($keys)){throw new RuntimeException('Invalid item collection list.');}
    $values = ['names'=>[], 'paths'=>[], 'identifiers'=>[]];
    foreach(array_unique($keys) as $key){
        $parts = [];
        $seen = [];
        $current = $key;
        while($current){
            if(isset($seen[$current]) || !isset($collections[$current])){
                throw new RuntimeException('Missing collection or cyclic hierarchy. Retry synchronisation.');
            }
            $seen[$current] = true;
            // Reserve dots for hierarchy separators; sanitise each name first.
            array_unshift($parts, str_replace('.', '_', $collections[$current]['name']));
            $current = $collections[$current]['parentCollection'];
        }
        $values['names'][] = $collections[$key]['name'];
        $values['paths'][] = implode('.', $parts);
        $values['identifiers'][] = $library.'/collections/'.$key;
    }
    foreach ($values as $kind => $items) { $values[$kind] = array_values(array_unique($items)); }
    return $values;
}
