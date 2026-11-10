<?php
/**
* FieldDependencies.php - Which title and calculated fields depend on a field (plan 32)
*
* A PATCH of some fields recalculates only what depends on them:
*   - title: the field is the first part of a [token] of the title mask of the record type
*     (no mask: DT_NAME); a mask with an unknown field = the title is filled on every save;
*   - calc: calculated fields of the record type whose formula names the field
*     (f{id}, f{id}s, f{id}_originalvalue); a calculated field that is run counts as
*     changed for the others and for the title;
*   - childTitle: child record types (plan 26) whose title mask uses
*     [Parent entity.field]; [Parent entity] alone = the parent title (childOnTitle);
*     a parent field the mask names but this type does not have = any change (childAny).
* Formulas that read a field only through $heurist->getRecord(...) and titles of other
* records that point to the record are not followed (as before plan 32).
*
* The whole database is analysed at once and kept in def-snapshot-dependencies.json of the
* database "entity" directory, beside def-snapshot.json. Legacy System::cleanDefCache()
* removes def-snapshot*.json on every definition change (also of calculation functions,
* DbEntityBase, 2026-11-10), so the file is made again on the next use.
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

use Heurist\Definitions\DefinitionLookup;

/** Dependencies of titles and calculated fields on fields, cached per database. */
final class FieldDependencies
{
    public const FILE = 'def-snapshot-dependencies.json';
    private const VERSION = 1;

    private DefinitionLookup $definitions;
    private TitleMask $titleMask;
    private string $file;
    /** @var array<int,array>|null rty => dependencies (see build()) */
    private ?array $rectypes = null;

    /**
     * @param string $entityDirectory Absolute path of the database "entity" directory ('' = no cache file).
     */
    public function __construct(DefinitionLookup $definitions, TitleMask $titleMask, string $entityDirectory = '')
    {
        $this->definitions = $definitions;
        $this->titleMask = $titleMask;
        $directory = rtrim(str_replace('\\', '/', $entityDirectory), '/');
        $this->file = $directory === '' ? '' : $directory.'/'.self::FILE;
    }

    /**
     * Dependencies of one record type.
     *
     * @return array{titleUnknown:bool,title:array<int,int>,calc:array<int,array<int,int>>,
     *         childTitle:array<int,array<int,int>>,childOnTitle:array<int,int>,childAny:array<int,int>,
     *         childFields:array<int,int>}
     */
    public function forRectype(int $rectypeId): array
    {
        $item = $this->all()[$rectypeId] ?? array();
        return array(
            'titleUnknown' => !empty($item['titleUnknown']),
            'title' => array_map('intval', $item['title'] ?? array()),
            'calc' => self::intKeys($item['calc'] ?? array()),
            'childTitle' => self::intKeys($item['childTitle'] ?? array()),
            'childOnTitle' => array_map('intval', $item['childOnTitle'] ?? array()),
            'childAny' => array_map('intval', $item['childAny'] ?? array()),
            'childFields' => array_map('intval', $item['childFields'] ?? array())
        );
    }

    /**
     * What a change of some fields of a record type needs.
     *
     * @param array<int,int> $changed Changed field ids.
     * @return array{title:bool,calc:array<int,int>,changed:array<int,int>,childTypes:array<int,int>,
     *         childOnTitle:array<int,int>,childFields:array<int,int>}
     *         calc = calculated fields to run (in structure order); changed = $changed + calc;
     *         childTypes = child types whose title depends on the changed fields; childOnTitle =
     *         child types to refresh when the title changes; childFields = changed child pointer fields.
     */
    public function affected(int $rectypeId, array $changed): array
    {
        $item = $this->forRectype($rectypeId);
        $set = array_fill_keys(array_map('intval', $changed), true);
        $calc = array();
        do{
            $added = false;
            foreach($item['calc'] as $fieldId => $refs){
                if(isset($calc[$fieldId]) || empty(array_intersect_key(array_flip($refs), $set))){ continue; }
                $calc[$fieldId] = true;
                $set[$fieldId] = true;
                $added = true;
            }
        }while($added);
        $changedIds = array_keys($set);
        $childTypes = $item['childAny'];
        foreach($changedIds as $fieldId){
            $childTypes = array_merge($childTypes, $item['childTitle'][$fieldId] ?? array());
        }
        return array(
            'title' => $item['titleUnknown'] || !empty(array_intersect($item['title'], $changedIds)),
            'calc' => array_keys($calc),
            'changed' => $changedIds,
            'childTypes' => array_values(array_unique($childTypes)),
            'childOnTitle' => $item['childOnTitle'],
            'childFields' => array_values(array_intersect($item['childFields'], $changedIds))
        );
    }

    /** @return array<int,array> rty => dependencies: from the cache file, else built and written. */
    public function all(): array
    {
        if($this->rectypes !== null){ return $this->rectypes; }
        $cached = $this->read();
        if($cached !== null){ return $this->rectypes = $cached; }
        $this->rectypes = $this->build();
        $this->write($this->rectypes);
        return $this->rectypes;
    }

    /** Analyse all record types (only those with dependencies are kept). */
    public function build(): array
    {
        $result = array();
        $nameField = $this->definitions->constant('DT_NAME');
        $parentRefs = array(); // child rty => names after "Parent entity"
        foreach($this->definitions->rectypes() as $rectypeId => $rectype){
            $rectypeId = intval($rectypeId);
            $mask = (string)($rectype['titleMask'] ?? '');
            if(trim($mask) === ''){
                if($nameField > 0){ $result[$rectypeId]['title'] = array($nameField); }
                continue;
            }
            $references = $this->titleMask->maskReferences($rectypeId, $mask);
            if($references === null){
                $result[$rectypeId]['titleUnknown'] = true;
                continue;
            }
            if(!empty($references['fields'])){ $result[$rectypeId]['title'] = $references['fields']; }
            if(!empty($references['parent'])){ $parentRefs[$rectypeId] = $references['parent']; }
        }

        foreach($this->definitions->allCalculatedFields() as $rectypeId => $formulas){
            foreach($formulas as $fieldId => $formula){
                preg_match_all('/(?<![A-Za-z0-9_])f(\d+)(?!\d)/', $formula, $matches);
                $result[$rectypeId]['calc'][$fieldId] = array_values(array_unique(array_map('intval', $matches[1])));
            }
        }

        foreach($this->definitions->childLinks() as $link){
            $parentType = intval($link['rty']);
            $result[$parentType]['childFields'][] = intval($link['dty']);
            foreach($parentRefs as $childType => $names){
                if(!empty($link['targetTypes']) && !in_array($childType, $link['targetTypes'], true)){ continue; }
                foreach($names as $name){
                    $fieldId = $name === '' ? 0 : $this->titleMask->resolveField($parentType, $name);
                    if($name === ''){
                        $result[$parentType]['childOnTitle'][] = $childType;
                    }elseif($fieldId > 0 && isset($this->definitions->structureRows($parentType)[$fieldId])){
                        $result[$parentType]['childTitle'][$fieldId][] = $childType;
                    }else{
                        $result[$parentType]['childAny'][] = $childType;
                    }
                }
            }
        }

        foreach($result as $rectypeId => $item){
            foreach(array('childFields', 'childOnTitle', 'childAny') as $key){
                if(isset($item[$key])){ $result[$rectypeId][$key] = array_values(array_unique($item[$key])); }
            }
            foreach($item['childTitle'] ?? array() as $fieldId => $types){
                $result[$rectypeId]['childTitle'][$fieldId] = array_values(array_unique($types));
            }
        }
        return $result;
    }

    /** @return array<int,array>|null */
    private function read(): ?array
    {
        if($this->file === '' || !is_readable($this->file)){ return null; }
        $cached = json_decode((string)@file_get_contents($this->file), true);
        if(!is_array($cached) || ($cached['version'] ?? 0) !== self::VERSION || !is_array($cached['rectypes'] ?? null)){
            return null;
        }
        return self::intKeys($cached['rectypes']);
    }

    /** Write the cache file (a temporary file renamed: readers never see a part). */
    private function write(array $rectypes): void
    {
        if($this->file === '' || !is_dir(dirname($this->file)) || !is_writable(dirname($this->file))){ return; }
        $temporary = $this->file.'.'.getmypid().'.tmp';
        $json = json_encode(array('version' => self::VERSION, 'rectypes' => (object)$rectypes), JSON_UNESCAPED_SLASHES);
        if(@file_put_contents($temporary, $json) !== false && !@rename($temporary, $this->file)){
            @unlink($temporary);
        }
    }

    /** Array with integer keys (JSON objects come back with string keys). */
    private static function intKeys(array $values): array
    {
        $result = array();
        foreach($values as $key => $value){ $result[intval($key)] = $value; }
        return $result;
    }
}
