<?php
/**
 * Import paths beneath one vocabulary and return the term ID for each full path.
 *
 * Copied/adapted from the CSV term import path: DbDefTerms::_parseHierarchy
 * and ::_saveTree, reached through ImportAction::importTerms. Split paths into
 * trimmed labels; look up each label under its exact parent, create missing
 * parents before children using the normal term entity save operation.
 *
 * @TODO This function should replace the equivalent code in the CSV importer
 * (ImportAction::importTerms); do not change the CSV importer at this stage.
 *
 * @param \hserv\System $system
 * @param int $parentID Vocabulary/parent term ID
 * @param string[] $paths Hierarchical paths, with dots between levels
 * @return array<string,int> Full input path => leaf term ID (also when existing)
 * @throws RuntimeException on invalid vocabulary or failed import
 */
function importHierarchicalTerms($system, $parentID, array $paths, $separator = '.') {
    $mysqli = $system->getMysqli();
    $parentID = intval($parentID);
    $parent = mysql__select_value($mysqli,
        'SELECT trm_ID FROM defTerms WHERE trm_ID=? AND trm_Domain="enum"', ['i', $parentID]);
    if (!$parent) { throw new RuntimeException('The hierarchical term field has no valid enum vocabulary.'); }
    $paths = array_values(array_unique($paths));
    $result = [];
    $cache = [];
    foreach ($paths as $path) {
        $parts = $separator === '' ? [$path] : explode($separator, $path);
        foreach ($parts as $part) {
            if (trim($part) === '' || mb_strlen(trim($part)) > 200) {
                throw new RuntimeException('Invalid hierarchy component (empty or longer than 200 characters): '.$path);
            }
        }
        $termID = $parentID;
        foreach ($parts as $part) {
            $label = trim(preg_replace('/[ \t]{2,}/u', ' ', $part));
            $cacheKey = $termID.':'.$label;
            if (isset($cache[$cacheKey])) { $termID = $cache[$cacheKey]; continue; }
            // Same parent-scoped lookup as CSV _saveTree; placeholders must
            // be unquoted so MySQL binds BOTH the parent ID and the label.
            $childID = intval(mysql__select_value($mysqli,
                'SELECT trm_ID FROM defTerms WHERE trm_ParentTermID=? AND trm_Label=? AND trm_Domain="enum"',
                ['is', $termID, $label]));
            if (!$childID) {
                $entity = new \hserv\entity\DbDefTerms($system);
                $entity->setData(['isfull'=>1, 'fields'=>[[
                    'trm_Label'=>$label, 'trm_ParentTermID'=>$termID, 'trm_Domain'=>'enum'
                ]]]);
                $saved = $entity->save();
                if ($saved === false || empty($saved)) {
                    throw new RuntimeException('Importing hierarchical term "'.$label.'" failed: '.$system->getErrorMsg());
                }
                $childID = intval(reset($saved));
                if ($childID <= 0) { throw new RuntimeException('No term ID returned for hierarchy component: '.$label); }
            }
            $cache[$cacheKey] = $childID;
            $termID = $childID;
        }
        $result[$path] = $termID;
    }
    return $result;
}
