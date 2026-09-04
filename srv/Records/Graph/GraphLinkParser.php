<?php
/**
* GraphLinkParser.php - Compact link-spec parsing for initial graph edges
*
* Converts the established compact link notation into a structured definition
* used by GraphEdgeDiscovery. Two forms are supported:
*
*   10:lt240:48     direct field link  - type 10 -> field 240 -> type 48
*   10:rt3260:10    relationship link  - type 10 -> relation type 3260 -> type 10
*
* Direction (lt/lf, rt/rf) is preserved for provenance. Internal-edge discovery
* keeps both endpoints inside the seed result set, so the field or relationship
* type is the effective filter.
*
* @project     Heurist academic knowledge management system
* @package     Records\Graph
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Records\Graph;

use Heurist\Records\Query\QueryValidationException;

/** Parses compact source:operator:target link specifications. */
final class GraphLinkParser
{
    /**
     * @param string $spec Compact link specification.
     * @return array{spec:string,sourceType:int,operator:string,relation:bool,detailTypeId:?int,relationTypeId:?int,targetType:int}
     */
    public function parse(string $spec): array
    {
        $tokens = explode(':', trim($spec));
        if(count($tokens) !== 3){
            throw new QueryValidationException('A link spec must be source:operator:target - got: '.$spec);
        }
        list($source, $middle, $target) = $tokens;
        if(!ctype_digit($source) || intval($source) < 1){
            throw new QueryValidationException('Link spec source must be a record type ID: '.$spec);
        }
        if(!ctype_digit($target) || intval($target) < 1){
            throw new QueryValidationException('Link spec target must be a record type ID: '.$spec);
        }
        if(!preg_match('/^(lt|lf|rt|rf)([0-9]+)$/i', $middle, $match)){
            throw new QueryValidationException('Link spec operator must be lt|lf|rt|rf followed by a field or relation type ID: '.$spec);
        }
        $operator = strtolower($match[1]);
        $referenceId = intval($match[2]);
        if($referenceId < 1){
            throw new QueryValidationException('Link spec field or relation type ID must be positive: '.$spec);
        }
        $isRelation = ($operator === 'rt' || $operator === 'rf');
        return array(
            'spec' => trim($spec),
            'sourceType' => intval($source),
            'operator' => $operator,
            'relation' => $isRelation,
            'detailTypeId' => $isRelation ? null : $referenceId,
            'relationTypeId' => $isRelation ? $referenceId : null,
            'targetType' => intval($target),
        );
    }

    /**
     * @param string[] $specs
     * @return array<int,array<string,mixed>>
     */
    public function parseList(array $specs): array
    {
        $parsed = array();
        foreach($specs as $spec){
            $parsed[] = $this->parse((string)$spec);
        }
        return $parsed;
    }
}
