<?php
/**
* ReportDefinitions.php - Definitions used by report templates
*
* Record type names, field types, record type structures (with separators),
* terms (label, code, concept id, description, hierarchical label), translations
* of terms/files/types (defTranslations) and local ids of concept codes. Loaded
* lazily once per report run.
*
* Replaces dbs_GetRectypeNames, dbs_GetDetailTypes, dbs_GetTerms/DbsTerms,
* DbDefRecStructure, ConceptCode::get*LocalID and ReportRecord::getTranslation.
*
* @project     Heurist academic knowledge management system
* @package     Reports\Smarty
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;

/** Cached definitions of one report run. */
final class ReportDefinitions
{
    private DatabaseInterface $database;
    private string $registeredDbId;
    private ?array $rectypeNames = null;
    private ?array $fieldTypes = null;
    /** @var array<int,array<int,string>> rty => [dty => display name] in form order */
    private array $structures = array();
    /** Terms (and the concept-code rule) come from the shared srv loader. */
    private DefinitionLookup $lookup;
    /** @var array<string,array<string,array<string,string>>> entity => lang => id|field => text */
    private array $translations = array();

    /**
     * @param DatabaseInterface $database Database of the report.
     * @param string $registeredDbId Registered database id ('' when not registered).
     */
    public function __construct(DatabaseInterface $database, string $registeredDbId = '')
    {
        $this->database = $database;
        $this->registeredDbId = $registeredDbId;
        $this->lookup = new DefinitionLookup($database);
    }

    /** Name of a record type ('' when unknown). */
    public function rectypeName($rtyId): string
    {
        if($this->rectypeNames === null){
            $this->rectypeNames = array();
            foreach($this->database->fetchRows('SELECT rty_ID,rty_Name FROM defRecTypes') as $row){
                $this->rectypeNames[intval($row[0])] = (string)$row[1];
            }
        }
        return $this->rectypeNames[intval($rtyId)] ?? '';
    }

    /** Base type of a field (freetext, enum, file, ...), or null when unknown. */
    public function fieldType($dtyId): ?string
    {
        if($this->fieldTypes === null){
            $this->fieldTypes = array();
            foreach($this->database->fetchRows('SELECT dty_ID,dty_Type FROM defDetailTypes') as $row){
                $this->fieldTypes[intval($row[0])] = (string)$row[1];
            }
        }
        return $this->fieldTypes[intval($dtyId)] ?? null;
    }

    /**
     * Fields of a record type in form order (separators included): [dty => display name].
     *
     * @param mixed $rtyId Record type.
     * @return array<int,string>
     */
    public function structure($rtyId): array
    {
        $rtyId = intval($rtyId);
        if(!isset($this->structures[$rtyId])){
            $this->structures[$rtyId] = array();
            // the query of the legacy DbDefRecStructure "listshort": fields with the same
            // display order come in the order the database gives them (as in the legacy engine)
            foreach($this->database->fetchRows(
                'SELECT rst_DetailTypeID,IF(rst_DisplayName IS NOT NULL AND CHAR_LENGTH(rst_DisplayName)>0,rst_DisplayName,dty_Name) '
                .'FROM defRecStructure LEFT JOIN defDetailTypes ON dty_ID=rst_DetailTypeID WHERE rst_RecTypeID=? '
                .'ORDER BY rst_DisplayOrder ASC', array($rtyId)
            ) as $row){
                $this->structures[$rtyId][intval($row[0])] = (string)$row[1];
            }
        }
        return $this->structures[$rtyId];
    }

    /**
     * A term (DefinitionLookup): internalid, term (label), code, conceptid, desc, parent,
     * inverse; null when unknown.
     *
     * @param mixed $termId
     * @return array|null
     */
    public function term($termId): ?array
    {
        return $this->lookup->term(intval($termId));
    }

    /**
     * Label of a term with the labels of its parent terms ("Parent.Child"); the
     * vocabulary is not included and repeated parts are removed
     * (DbsTerms::getTermLabel with hierarchy).
     *
     * @param mixed $termId
     * @return string
     */
    public function termFullLabel($termId): string
    {
        $term = $this->term($termId);
        if($term === null){ return ''; }
        $labels = explode('.', $term['term']);
        $guard = 0;
        while($term['parent'] > 0 && $guard++ < 50){
            $term = $this->term($term['parent']);
            if($term === null || !($term['parent'] > 0)){
                break; // the vocabulary itself is not shown
            }
            $labels = array_merge(explode('.', $term['term']), $labels);
        }
        $i = 1;
        while($i < count($labels)){
            $prefix = implode('.', array_slice($labels, 0, $i)).'.';
            $rest = implode('.', array_slice($labels, $i));
            if(strpos($rest, $prefix) === 0){
                $labels = array_slice($labels, $i);
                $i = 1;
            }else{
                $i++;
            }
        }
        return implode('.', $labels);
    }

    /**
     * Inverse relation type of a term (itself when it has none).
     *
     * @param int $termId
     * @return int
     */
    public function inverseTerm(int $termId): int
    {
        $term = $this->term($termId);
        if($term !== null && $term['inverse'] > 0){
            return $term['inverse'];
        }
        foreach($this->lookup->allTerms() as $id => $other){
            if($other['inverse'] === $termId){
                return $id;
            }
        }
        return $termId;
    }

    /**
     * Translations of terms (trm_Label, trm_Description), files (ulf_Caption,
     * ulf_Description), record types and fields (names), with the default text
     * where no translation exists. One id gives a string, several ids an array.
     *
     * @param string $entity trm, ulf, rty or dty.
     * @param mixed $ids One id, a comma-separated list or an array.
     * @param string|null $field Field (anything containing "desc" means the description).
     * @param string $lang 3-letter language code.
     * @return string|array
     */
    public function translation(string $entity, $ids, ?string $field, string $lang)
    {
        if(!in_array($entity, array('trm', 'ulf', 'rty', 'dty'), true)){
            return '';
        }
        if($entity === 'trm'){
            $field = stripos((string)$field, 'desc') === false ? 'trm_Label' : 'trm_Description';
        }elseif($entity === 'ulf'){
            $field = stripos((string)$field, 'desc') === false ? 'ulf_Caption' : 'ulf_Description';
        }else{
            $field = $entity.'_Name';
        }
        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);
        $ids = array_values(array_filter(array_map('trim', array_map('strval', $ids)), 'strlen'));
        $cache = &$this->translations[$entity][$lang];
        $result = array();
        $missing = array();
        foreach($ids as $id){
            if(isset($cache[$id.'|'.$field])){
                $result[$id] = $cache[$id.'|'.$field];
            }else{
                $missing[] = $id;
            }
        }
        if(!empty($missing)){
            $numeric = array_values(array_filter($missing, 'ctype_digit'));
            $found = array();
            if(!empty($numeric)){
                $placeholders = implode(',', array_fill(0, count($numeric), '?'));
                foreach($this->database->fetchRows(
                    'SELECT trn_Code,trn_Translation FROM defTranslations WHERE trn_Source=? '
                    .'AND trn_LanguageCode=? AND trn_Code IN ('.$placeholders.')',
                    array_merge(array($field, $lang), array_map('intval', $numeric))
                ) as $row){
                    $found[(string)$row[0]] = (string)$row[1];
                }
            }
            $defaults = $this->defaultTexts($entity, $field, $missing);
            foreach($missing as $id){
                $text = $found[$id] ?? $defaults[$id] ?? '';
                $cache[$id.'|'.$field] = $text;
                $result[$id] = $text;
            }
        }
        unset($cache);
        return count($result) === 1 ? (string)array_shift($result) : $result;
    }

    /**
     * Local id of a record type, field or term from a concept code ("2-1"), a
     * local code ("0000-12") or a local id ("12"); 0 when not found.
     *
     * @param string $entity rty, dty or trm.
     * @param mixed $conceptCode
     * @return int
     */
    public function localId(string $entity, $conceptCode): int
    {
        $tables = array('rty' => 'defRecTypes', 'dty' => 'defDetailTypes', 'trm' => 'defTerms');
        if(!isset($tables[$entity])){ return 0; }
        $table = $tables[$entity];
        $prefix = $entity.'_';
        $parts = explode('-', trim((string)$conceptCode));
        if(count($parts) === 1 && ctype_digit($parts[0])){
            $localId = intval($parts[0]);
        }elseif(count($parts) === 2 && ctype_digit($parts[1])
            && (!(intval($parts[0]) > 0) || $parts[0] === $this->registeredDbId)){
            $localId = intval($parts[1]);
        }elseif(count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])){
            return intval($this->database->fetchValue(
                'SELECT '.$prefix.'ID FROM '.$table.' WHERE '.$prefix.'OriginatingDBID=? AND '
                .$prefix.'IDInOriginatingDB=? LIMIT 1',
                array(intval($parts[0]), intval($parts[1])), 0
            ));
        }else{
            return 0;
        }
        return intval($this->database->fetchValue(
            'SELECT '.$prefix.'ID FROM '.$table.' WHERE '.$prefix.'ID=? LIMIT 1', array($localId), 0
        ));
    }

    /** Default texts of entities without a translation. */
    private function defaultTexts(string $entity, string $field, array $ids): array
    {
        $defaults = array();
        if($entity === 'trm'){
            foreach($ids as $id){
                $term = $this->term($id);
                $defaults[$id] = $term === null ? '' : (string)($field === 'trm_Label' ? $term['term'] : $term['desc']);
            }
        }elseif($entity === 'ulf'){
            foreach($ids as $id){
                $column = ctype_digit($id) ? 'ulf_ID' : 'ulf_ObfuscatedFileID';
                if($column === 'ulf_ObfuscatedFileID' && !preg_match('/^[a-z0-9]+$/i', $id)){
                    continue;
                }
                $defaults[$id] = (string)$this->database->fetchValue(
                    'SELECT '.$field.' FROM recUploadedFiles WHERE '.$column.'=? LIMIT 1', array($id), ''
                );
            }
        }elseif($entity === 'rty'){
            foreach($ids as $id){ $defaults[$id] = $this->rectypeName($id); }
        }else{
            foreach($ids as $id){
                $defaults[$id] = (string)$this->database->fetchValue(
                    'SELECT dty_Name FROM defDetailTypes WHERE dty_ID=? LIMIT 1', array(intval($id)), ''
                );
            }
        }
        return $defaults;
    }
}
