<?php
/**
* ReportDefinitions.php - Definitions used by report templates
*
* Record type names, field types, record type structures (with separators),
* terms (label, code, concept id, description, hierarchical label), translations
* of terms/files/types (defTranslations) and local ids of concept codes. Everything
* about definitions comes from DefinitionLookup (plan 15); file captions are read
* here until the file layer exists (plan 15, Phase 3).
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
use Heurist\Records\Files\FileRepository;

/** Definitions of one report run. */
final class ReportDefinitions
{
    private DatabaseInterface $database;
    /** Record types, fields, structure, terms, translations and concept codes. */
    private DefinitionLookup $lookup;
    /** @var array<string,array<string,array<string,string>>> entity => lang => id|field => text */
    private array $translations = array();

    /** @param DatabaseInterface $database Database of the report. */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
        $this->lookup = DefinitionLookup::shared($database);
    }

    /** Name of a record type ('' when unknown). */
    public function rectypeName($rtyId): string
    {
        return $this->lookup->rectypeName(intval($rtyId));
    }

    /** Base type of a field (freetext, enum, file, ...), or null when unknown. */
    public function fieldType($dtyId): ?string
    {
        $type = $this->lookup->fieldType(intval($dtyId));
        return $type === '' ? null : $type;
    }

    /**
     * Fields of a record type in form order (separators included): [dty => display name,
     * or the field name when the record type has none].
     *
     * @param mixed $rtyId Record type.
     * @return array<int,string>
     */
    public function structure($rtyId): array
    {
        return $this->lookup->structureNames(intval($rtyId));
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
        // a term that names this one as its inverse (the first by id, as before)
        $others = array_filter($this->lookup->termInverse(array($termId)), static function($id) use ($termId){
            return $id !== $termId;
        });
        return empty($others) ? $termId : min($others);
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
            $found = $this->lookup->translations($field, array_map('intval', $numeric), $lang);
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
        return $this->lookup->localId($entity, $conceptCode);
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
            $files = FileRepository::shared($this->database);
            foreach($ids as $id){
                $isId = ctype_digit($id);
                if(!$isId && !preg_match('/^[a-z0-9]+$/i', $id)){
                    continue;
                }
                $file = $isId ? ($files->files(array(intval($id)))[intval($id)] ?? null)
                    : ($files->filesByObfuscatedId(array($id))[$id] ?? null);
                $defaults[$id] = (string)($file[$field] ?? '');
            }
        }elseif($entity === 'rty'){
            foreach($ids as $id){ $defaults[$id] = $this->rectypeName($id); }
        }else{
            foreach($ids as $id){ $defaults[$id] = $this->lookup->field(intval($id))['name'] ?? ''; }
        }
        return $defaults;
    }
}
