<?php
/**
* QueryBuilder.php - Modern Heurist record query SQL facade
*
* @project     Heurist academic knowledge management system
* @package     Records\Search\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace hserv\records\search\query;


require_once dirname(__FILE__, 2).'/SearchTypes.php';
require_once dirname(__FILE__).'/SqlBuildContext.php';
require_once dirname(__FILE__).'/RecordQueryParser.php';
require_once dirname(__FILE__).'/QueryValueResolver.php';
require_once dirname(__FILE__).'/FieldPredicateCompiler.php';
require_once dirname(__FILE__).'/RecordPredicateCompiler.php';
require_once dirname(__FILE__).'/SortCompiler.php';

use hserv\records\search\CompiledQuery;
use hserv\records\search\QueryValidationException;
use hserv\records\search\UnsupportedQueryException;

/** Public facade that composes parameterized IDs and count SQL. */
final class QueryBuilder
{
    private const DEFAULT_LIMIT=300000;
    private const MAX_LIMIT=300000;
    private $parser; private $resolver; private $fields; private $records; private $sort;

    public function __construct($mysqli=null)
    {
        $this->parser=new RecordQueryParser();
        $this->resolver=new QueryValueResolver($mysqli,$this->parser);
        $this->fields=new FieldPredicateCompiler($mysqli);
        $this->records=new RecordPredicateCompiler($mysqli,$this->fields);
        $this->sort=new SortCompiler($this->fields,$this->parser);
    }

    public function normalize($query): array{return $this->resolver->resolve($this->parser->normalize($query));}
    public function textToJson(string $query): array{return $this->parser->textToJson($query);}
    public function validate(array $query): void{$this->parser->validate($query);}
    public function supportsFlatExecution($query): bool{return $this->parser->supportsFlatExecution($query);}
    public function supportsSqlExecution($query): bool{return $this->parser->supportsSqlExecution($query);}

    public function buildIds($query,array $context=array()): CompiledQuery
    {
        $normalized=$this->normalize($query);
        if(!$this->supportsSqlExecution($normalized)){throw new UnsupportedQueryException('Query requires batched execution');}
        $state=new SqlBuildContext($context);$where=$this->compileGroup($normalized,'AND',$state,'r',0);
        $this->records->appendAccessConditions($where,$state,$context,'r');
        $sort=$this->sort->compileSort($normalized,$state);
        $limit=intval($context['limit']??self::DEFAULT_LIMIT);if($limit<1){$limit=self::DEFAULT_LIMIT;}$limit=min($limit,self::MAX_LIMIT);
        $offset=max(0,intval($context['offset']??0));
        $sql='SELECT DISTINCT r.rec_ID FROM Records r WHERE '.implode(' AND ',$where).$sort.' LIMIT ? OFFSET ?';
        $state->bind($limit,'i');$state->bind($offset,'i');
        return new CompiledQuery($sql,$state->types(),$state->values(),$normalized);
    }

    public function buildCount($query,array $context=array()): CompiledQuery
    {
        $normalized=$this->normalize($query);
        if(!$this->supportsSqlExecution($normalized)){throw new UnsupportedQueryException('Query requires batched execution');}
        $state=new SqlBuildContext($context);$where=$this->compileGroup($normalized,'AND',$state,'r',0);
        $this->records->appendAccessConditions($where,$state,$context,'r');
        return new CompiledQuery('SELECT COUNT(DISTINCT r.rec_ID) FROM Records r WHERE '.implode(' AND ',$where),$state->types(),$state->values(),$normalized);
    }

    /** Build a bounded, index-driven probe for an unsuffixed any-field predicate. */
    public function buildAnyFieldCandidates($value,array $context=array(),int $limit=5001): CompiledQuery
    {
        $limit=max(1,$limit);
        $state=new SqlBuildContext($context);
        $source=$this->fields->anyFieldCandidateSource($value,$state);
        $state->bind($limit,'i');
        return new CompiledQuery(
            'SELECT DISTINCT candidates.rec_ID FROM ('.$source.') candidates LIMIT ?',
            $state->types(),
            $state->values(),
            array(array('f'=>$value))
        );
    }

    /** Build a bounded candidate probe for an integer or float detail field. */
    public function buildNumericFieldCandidates(
        int $fieldId,
        $value,
        array $context=array(),
        int $limit=5001
    ): ?CompiledQuery {
        if(!$this->fields->isNumericField($fieldId)){return null;}
        $limit=max(1,$limit);
        $state=new SqlBuildContext($context);
        $source=$this->fields->numericFieldCandidateSource($fieldId,$value,$state);
        $state->bind($limit,'i');
        return new CompiledQuery(
            'SELECT DISTINCT candidates.rec_ID FROM ('.$source.') candidates LIMIT ?',
            $state->types(),
            $state->values(),
            array(array('f:'.$fieldId=>$value))
        );
    }

    public function buildIdSet($query,array $context=array()): CompiledQuery
    {
        $normalized=$this->normalize($query);
        if(!$this->supportsFlatExecution($normalized)){throw new UnsupportedQueryException('Query requires linked execution');}
        $state=new SqlBuildContext($context);$where=$this->compileGroup($normalized,'AND',$state,'r',0);
        $this->records->appendAccessConditions($where,$state,$context,'r');
        return new CompiledQuery('SELECT DISTINCT r.rec_ID FROM Records r WHERE '.implode(' AND ',$where).$this->sort->compileSort($normalized,$state),$state->types(),$state->values(),$normalized);
    }

    private function compileGroup(array $group,string $operator,SqlBuildContext $state,string $recordAlias='r',int $depth=0): array
    {
        $conditions=array();
        foreach($group as $predicate){
            $key=(string)array_keys($predicate)[0];$value=$predicate[$key];list($base,$suffix)=$this->parser->predicateParts($key);
            if(in_array($base,array('sortby','sort','s'),true)){continue;}
            if(in_array($base,array('any','all','not'),true)){
                $nested=$this->parser->normalizeQueryArray($value);
                $parts=$this->compileGroup($nested,$base==='any'?'OR':'AND',$state,$recordAlias,$depth);
                $expression='('.implode($base==='any'?' OR ':' AND ',$parts).')';
                $conditions[]=$base==='not'?'NOT '.$expression:$expression;continue;
            }
            $conditions[]=$this->compilePredicate($base,$suffix,$value,$state,$recordAlias,$depth);
        }
        if(empty($conditions)){$conditions[]='1=1';}
        if($operator==='OR'&&count($conditions)>1){return array('('.implode(' OR ',$conditions).')');}
        return $conditions;
    }

    private function compilePredicate(string $base,string $suffix,$value,SqlBuildContext $state,string $r='r',int $depth=0): string
    {
        $record=$this->records->compile($base,$suffix,$value,$state,$r);if($record!==null){return $record;}
        if($base==='f'||$base==='field'){
            if($suffix===''){return $this->fields->anyFieldCondition($value,$state,$r);}
            list($fieldId,$termField)=$this->fields->fieldSuffixParts($suffix);
            return $this->fields->fieldCondition($fieldId,$value,$state,$r,$termField);
        }
        if(in_array($base,array('fc','count','cnt'),true)){return $this->fields->fieldCountCondition(intval($suffix),$value,$state,$r);}
        if($base==='geo'){return $this->fields->geoCondition($suffix,$value,$state,$r);}
        if($base==='file'){return $this->fields->fileCondition($suffix,$value,$state,$r);}
        if(in_array($base,array('lt','linked_to','linkedto'),true)){
            if($this->fields->isLinkFieldPresenceTest($suffix,$value)){return $this->fields->fieldCondition(intval($suffix),$value,$state,$r);}
            return $this->compileResourceLink($r,'to',$suffix,$value,$state,$depth+1);
        }
        if(in_array($base,array('lf','linked_from','linkedfrom'),true)){
            if($this->fields->isLinkFieldPresenceTest($suffix,$value)){return $this->fields->fieldCondition(intval($suffix),$value,$state,$r);}
            return $this->compileResourceLink($r,'from',$suffix,$value,$state,$depth+1);
        }
        if(in_array($base,array('rt','related_to','relatedto'),true)){return $this->compileRelationship($r,'to',$value,$state,$depth+1);}
        if(in_array($base,array('rf','related_from','relatedfrom'),true)){return $this->compileRelationship($r,'from',$value,$state,$depth+1);}
        throw new UnsupportedQueryException('Predicate is not executable: '.$base);
    }

    private function compileResourceLink(
        string $parentAlias,
        string $direction,
        string $suffix,
        $value,
        SqlBuildContext $state,
        int $depth
    ): string {
        $linkAlias = $state->nextAlias('rl');
        $childAlias = $state->nextAlias('lr');
        $childQuery = $this->parser->linkedValueQuery($value);
        $parentColumn = $direction === 'to' ? 'rl_SourceID' : 'rl_TargetID';
        $childColumn = $direction === 'to' ? 'rl_TargetID' : 'rl_SourceID';
        $edge = array(
            $linkAlias.'.'.$parentColumn.'='.$parentAlias.'.rec_ID',
            $linkAlias.'.rl_RelationID IS NULL'
        );
        if($suffix !== ''){
            if(!ctype_digit($suffix) || intval($suffix)<1){
                throw new QueryValidationException('Resource-link field ID must be positive');
            }
            $state->bind(intval($suffix), 'i');
            $edge[] = $linkAlias.'.rl_DetailTypeID=?';
        }else{
            $edge[] = $linkAlias.'.rl_DetailTypeID>0';
        }
        $childWhere = $this->compileGroup($childQuery, 'AND', $state, $childAlias, $depth);
        $this->records->appendAccessConditions($childWhere, $state, $state['context'], $childAlias);

        return 'EXISTS (SELECT 1 FROM recLinks '.$linkAlias
            .' INNER JOIN Records '.$childAlias.' ON '.$childAlias.'.rec_ID='
            .$linkAlias.'.'.$childColumn
            .' WHERE '.implode(' AND ', array_merge($edge, $childWhere)).')';
    }

    /** Compile a directional Relationship-record edge as correlated EXISTS. */
    private function compileRelationship(
        string $parentAlias,
        string $direction,
        $value,
        SqlBuildContext $state,
        int $depth
    ): string {
        $linkAlias = $state->nextAlias('rrl');
        $childAlias = $state->nextAlias('rr');
        $relationshipAlias = $state->nextAlias('rel');
        list($childQuery, $relationshipQuery, $relationTypes) =
            $this->splitRelationshipQuery($this->parser->linkedValueQuery($value));

        $parentColumn = $direction === 'to' ? 'rl_SourceID' : 'rl_TargetID';
        $childColumn = $direction === 'to' ? 'rl_TargetID' : 'rl_SourceID';
        $edge = array(
            $linkAlias.'.'.$parentColumn.'='.$parentAlias.'.rec_ID',
            $linkAlias.'.rl_RelationID IS NOT NULL'
        );
        if($relationTypes !== null){
            $edge[] = $this->relationshipTypeCondition($linkAlias, $relationTypes, $state);
        }
        $childWhere = $this->compileGroup($childQuery, 'AND', $state, $childAlias, $depth);
        $this->records->appendAccessConditions($childWhere, $state, $state['context'], $childAlias);
        $relationshipWhere = $this->compileGroup(
            $relationshipQuery, 'AND', $state, $relationshipAlias, $depth
        );
        $this->records->appendAccessConditions(
            $relationshipWhere, $state, $state['context'], $relationshipAlias
        );

        return 'EXISTS (SELECT 1 FROM recLinks '.$linkAlias
            .' INNER JOIN Records '.$childAlias.' ON '.$childAlias.'.rec_ID='
            .$linkAlias.'.'.$childColumn
            .' INNER JOIN Records '.$relationshipAlias.' ON '.$relationshipAlias.'.rec_ID='
            .$linkAlias.'.rl_RelationID'
            .' WHERE '.implode(' AND ', array_merge($edge, $childWhere, $relationshipWhere)).')';
    }

    /** Separate endpoint predicates from Relationship-record predicates. */
    private function splitRelationshipQuery(array $query): array
    {
        $child = array();
        $relationship = array();
        $types = null;
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->parser->predicateParts($key);
            if($base === 'r' && $suffix === ''){
                $current = $this->fields->numericList($value, 'relationship type');
                $types = $types === null ? $current : array_values(array_intersect($types, $current));
            }elseif($base === 'relf' || ($base === 'r' && $suffix !== '')){
                if($suffix === '' || !ctype_digit($suffix) || intval($suffix)<1){
                    throw new QueryValidationException('Relationship-record field ID must be positive');
                }
                $relationship[] = array('f:'.intval($suffix)=>$value);
            }else{
                $child[] = $predicate;
            }
        }
        return array(
            empty($child) ? array(array('_all'=>true)) : $child,
            empty($relationship) ? array(array('_all'=>true)) : $relationship,
            $types
        );
    }

    /** Include requested relationship terms and descendants in the closure table. */
    private function relationshipTypeCondition(string $linkAlias, array $types, SqlBuildContext $state): string
    {
        if(empty($types)){ return '0=1'; }
        $direct = implode(',', array_fill(0, count($types), '?'));
        foreach($types as $type){ $state->bind($type, 'i'); }
        $descendants = implode(',', array_fill(0, count($types), '?'));
        foreach($types as $type){ $state->bind($type, 'i'); }
        return '('.$linkAlias.'.rl_RelationTypeID IN ('.$direct.') OR '
            .$linkAlias.'.rl_RelationTypeID IN (SELECT trl_TermID FROM defTermsLinks '
            .'WHERE trl_ParentID IN ('.$descendants.')))';
    }

}
