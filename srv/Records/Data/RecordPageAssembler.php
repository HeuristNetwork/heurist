<?php
/**
* RecordPageAssembler.php - Record objects for a list of ids and an output field selection
*
* Loads header fields, native detail values and values reached through linked
* paths (lt/lf/rt/rf traversals) for one page of records. Used by the /records
* API (RecordQueryController) and by the record export (plan 13), so both give
* the same record objects.
*
* @project     Heurist academic knowledge management system
* @package     Records\Data
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

namespace Heurist\Records\Data;

use Heurist\Records\Expansion\ExpansionRequest;

/** Builds record objects of the /records envelope for ordered ids. */
final class RecordPageAssembler
{
    /** @var RecordDataService|object */
    private $dataService;

    /** @var callable fn(): ExpansionEngine|object */
    private $engineFactory;

    /**
     * @param RecordDataService|object $dataService Batched value loading.
     * @param callable $engineFactory Returns the expansion engine for linked paths.
     */
    public function __construct($dataService, callable $engineFactory)
    {
        $this->dataService = $dataService;
        $this->engineFactory = $engineFactory;
    }

    /**
     * Record objects for the ids, in the order of the ids.
     *
     * @param array $ids Record ids.
     * @param array $selection Output of RecordFieldSelector::parse().
     * @param array $options resolveDetails (bool).
     * @return array{records:array, paths:array<string,string>} Records and the public
     *         path ids of linked fields (id => traversal code).
     */
    public function assemble(array $ids, array $selection, array $options = array()): array
    {
        $native = array_values(array_filter($selection['details'], static function($field){
            return $field['traversal'] === null;
        }));
        $linked = array_values(array_filter($selection['details'], static function($field){
            return $field['traversal'] !== null;
        }));
        $valueOptions = array(
            'resolveDetails'=>$options['resolveDetails'] ?? false,
            // Presentation-only virtual headers are resolved by RecordDataService.
            'virtuals'=>$selection['virtuals'] ?? array(),
            // fields=_all - every populated detail value, regardless of type.
            'allDetails'=>$selection['all'] ?? false
        );
        $records = $this->dataService->loadRecords(
            $ids, $selection['headers'], $native, $valueOptions
        );
        $paths = array();
        $linkedByTraversal = array();
        foreach($linked as $field){ $linkedByTraversal[$field['traversal']][] = $field; }
        foreach($linkedByTraversal as $traversal=>$pathFields){
            $engine = call_user_func($this->engineFactory);
            $expansion = $engine->expand(new ExpansionRequest($ids, $traversal));
            $terminalPathId = null;
            foreach($expansion->getPaths() as $pathId=>$code){
                if($code === $traversal){ $terminalPathId = (string)$pathId; }
            }
            if($terminalPathId === null){ continue; }
            $publicPathId = (string)(count($paths)+1);
            $paths[$publicPathId] = $traversal;
            $occurrences = $expansion->getOccurrences($terminalPathId);
            foreach($pathFields as $field){
                $this->dataService->attachLinkedValues(
                    $records, $field, $occurrences, $publicPathId, $valueOptions
                );
            }
        }
        return array('records'=>$records, 'paths'=>$paths);
    }

    /** Field definitions of the selection (meta.fields.details of the envelope). */
    public function fieldMetadata(array $selection): array
    {
        return $this->dataService->loadFieldMetadata($selection['details']);
    }
}
