<?php
/**
* QueryBuilder.php - Modern Heurist record query SQL facade
*
* @project     Heurist academic knowledge management system
* @package     Records\Search\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

namespace Heurist\Records\Query\Compiler;



use Heurist\Database\DatabaseInterface;
use Heurist\Records\Query\CompiledQuery;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\UnsupportedQueryException;
use Heurist\Records\Query\Parser\RecordQueryParser;

/** Public facade that composes parameterized IDs and count SQL. */
final class QueryBuilder
{
    private const DEFAULT_LIMIT=300000;
    private const MAX_LIMIT=300000;
    private $parser; private $resolver; private $fields; private $records; private $sort; private $terms;

    /** Initialise all query compilers with one database abstraction. */
    public function __construct(DatabaseInterface $database)
    {
        $this->parser=new RecordQueryParser();
        $this->resolver=new QueryValueResolver($database,$this->parser);
        $this->fields=new FieldPredicateCompiler($database);
        $this->records=new RecordPredicateCompiler($database,$this->fields);
        $this->sort=new SortCompiler($this->fields,$this->parser);
        $this->terms=new RelationTermResolver($database);
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
        $sort=$this->compileEffectiveSort($normalized,$state,$context);
        $limit=intval($context['limit']??self::DEFAULT_LIMIT);if($limit<1){$limit=self::DEFAULT_LIMIT;}$limit=min($limit,self::MAX_LIMIT);
        $offset=max(0,intval($context['offset']??0));
        // No joins, so rec_ID is unique; DISTINCT would break ORDER BY under ONLY_FULL_GROUP_BY (error 3065)
        $sql='SELECT r.rec_ID FROM Records r WHERE '.implode(' AND ',$where).$sort.' LIMIT ? OFFSET ?';
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

    /**
     * Compile a query's conditions plus record access over alias r into $state,
     * for callers that compose their own SELECT (field value counts). Parameters
     * already bound to $state (for example by JOINs) keep their position.
     */
    public function compileConditions($query,SqlBuildContext $state,array $context=array()): array
    {
        $normalized=$this->normalize($query);
        if(!$this->supportsSqlExecution($normalized)){throw new UnsupportedQueryException('Query requires batched execution');}
        $where=$this->compileGroup($normalized,'AND',$state,'r',0);
        $this->records->appendAccessConditions($where,$state,$context,'r');
        return $where;
    }

    /** Record access conditions for another Records alias (linked target records). */
    public function accessConditions(SqlBuildContext $state,array $context,string $alias): array
    {
        $where=array();
        $this->records->appendAccessConditions($where,$state,$context,$alias);
        return $where;
    }

    /**
     * Condition that hides a detail row the current user may not see (field
     * visibility, hidden-from-public values) - the rule every detail predicate applies.
     * Binds nothing; returns '' for the database owner.
     */
    public function detailVisibilityCondition(SqlBuildContext $state,string $detailAlias,string $recordAlias): string
    {
        $condition=$this->fields->detailVisibilityCondition($detailAlias,$recordAlias,$state);
        return $condition===''?'':preg_replace('/^s*ANDs+/','',$condition);
    }

    /**
     * Word-prefix FULLTEXT condition for a text column, or null when the column has
     * no FULLTEXT index or the text no indexable word (caller keeps its LIKE).
     */
    public function wordPrefixMatch(string $column, string $text, SqlBuildContext $state): ?string
    {
        return $this->fields->wordPrefixMatch($column, $text, $state);
    }

    /** Compile counts grouped by record type over the complete filtered query. */
    public function buildRectypeCounts($query,array $context=array()): CompiledQuery
    {
        $normalized=$this->normalize($query);
        if(!$this->supportsSqlExecution($normalized)){throw new UnsupportedQueryException('Query requires batched execution');}
        $state=new SqlBuildContext($context);$where=$this->compileGroup($normalized,'AND',$state,'r',0);
        $this->records->appendAccessConditions($where,$state,$context,'r');
        return new CompiledQuery(
            'SELECT r.rec_RecTypeID, COUNT(DISTINCT r.rec_ID) FROM Records r WHERE '
                .implode(' AND ',$where).' GROUP BY r.rec_RecTypeID ORDER BY r.rec_RecTypeID',
            $state->types(),$state->values(),$normalized
        );
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
        // As in buildIds(): no joins, no DISTINCT (error 3065 with ORDER BY under ONLY_FULL_GROUP_BY)
        return new CompiledQuery('SELECT r.rec_ID FROM Records r WHERE '.implode(' AND ',$where).$this->compileEffectiveSort($normalized,$state,$context),$state->types(),$state->values(),$normalized);
    }

    /** Compile an explicit request sort in preference to the query's top-level sort. */
    private function compileEffectiveSort(
        array $normalized,
        SqlBuildContext $state,
        array $context
    ): string {
        if(empty($context['sortProvided'])){
            return $this->sort->compileSort($normalized,$state);
        }
        $withoutSort=array_values(array_filter($normalized,function($predicate): bool {
            $key=(string)array_keys($predicate)[0];
            list($base)=$this->parser->predicateParts($key);
            return !in_array($base,array('sortby','sort','s'),true);
        }));
        $override=$context['sort']??null;
        if($override!==null && $override!=='' && $override!==array()){
            $withoutSort[]=array('sort'=>$override);
        }
        return $this->sort->compileSort($withoutSort,$state);
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
        if(in_array($base,array('rt','related_to','relatedto'),true)){return $this->compileRelationships($r,'rt',$suffix,$value,$state,$depth+1);}
        if(in_array($base,array('rf','related_from','relatedfrom'),true)){return $this->compileRelationships($r,'rf',$suffix,$value,$state,$depth+1);}
        if($base==='links'){return $this->compileAnyLink($r,$suffix,$value,$state,$depth+1);}
        if($base==='related'){return $this->compileRelationships($r,'related',$suffix,$value,$state,$depth+1);}
        if($base==='connected'){
            if($suffix!==''){throw new QueryValidationException('connected does not accept a field or relationship type');}
            return '('.$this->compileAnyLink($r,'',$value,$state,$depth+1)
                .' OR '.$this->compileRelationships($r,'related','',$value,$state,$depth+1).')';
        }
        throw new UnsupportedQueryException('Predicate is not executable: '.$base);
    }

    /** links[:field] - a resource link in either direction: lt OR lf. */
    private function compileAnyLink(string $parentAlias, string $suffix, $value, SqlBuildContext $state, int $depth): string
    {
        list($to, $negate) = $this->resourceLinkExists($parentAlias, 'to', $suffix, $value, $state, $depth);
        list($from) = $this->resourceLinkExists($parentAlias, 'from', $suffix, $value, $state, $depth);
        $either = '('.$to.' OR '.$from.')';
        return $negate ? 'NOT '.$either : $either;
    }

    private function compileResourceLink(
        string $parentAlias,
        string $direction,
        string $suffix,
        $value,
        SqlBuildContext $state,
        int $depth
    ): string {
        list($exists, $negate) = $this->resourceLinkExists($parentAlias, $direction, $suffix, $value, $state, $depth);
        return $negate ? 'NOT '.$exists : $exists;
    }

    /** One-direction resource link as [EXISTS, negated by an exists:NULL modifier]. */
    private function resourceLinkExists(
        string $parentAlias,
        string $direction,
        string $suffix,
        $value,
        SqlBuildContext $state,
        int $depth
    ): array {
        $linkAlias = $state->nextAlias('rl');
        $childAlias = $state->nextAlias('lr');
        $childQuery = $this->parser->linkedValueQuery($value);
        list($childQuery, $negate) = $this->extractExistsModifier($childQuery);
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

        $exists = 'EXISTS (SELECT 1 FROM recLinks '.$linkAlias
            .' INNER JOIN Records '.$childAlias.' ON '.$childAlias.'.rec_ID='
            .$linkAlias.'.'.$childColumn
            .' WHERE '.implode(' AND ', array_merge($edge, $childWhere)).')';
        return array($exists, $negate);
    }

    /**
     * Strip an "exists" modifier from a linked-record child query.
     * NULL negates the surrounding EXISTS to NOT EXISTS; -NULL keeps it (the default).
     */
    private function extractExistsModifier(array $childQuery): array
    {
        $negate = false;
        $remaining = array();
        foreach($childQuery as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base) = $this->parser->predicateParts($key);
            if($base === 'exists'){
                $flag = strtoupper(trim((string)$predicate[$key]));
                if($flag === 'NULL'){ $negate = true; }
                elseif($flag === '-NULL' || $flag === ''){ $negate = false; }
                else{ throw new QueryValidationException('exists predicate accepts NULL or -NULL'); }
                continue;
            }
            $remaining[] = $predicate;
        }
        return array($remaining, $negate);
    }

    /**
     * rt / rf / related [:field] - relationships as correlated EXISTS, one per edge read
     * ("leg", see RelationTermResolver::relationshipLegs): stored direction, stored relation
     * types, and the record types of both ends. The legs are alternatives (OR).
     */
    private function compileRelationships(
        string $parentAlias,
        string $base,
        string $suffix,
        $value,
        SqlBuildContext $state,
        int $depth
    ): string {
        // exists:NULL - records without such a relationship (as for lt/lf)
        list($linkedQuery, $negate) = $this->extractExistsModifier($this->parser->linkedValueQuery($value));
        list($childQuery, $relationshipQuery, $explicitTypes) = $this->splitRelationshipQuery($linkedQuery);
        $parts = array();
        foreach($this->terms->relationshipLegs($base, $suffix, $explicitTypes) as $leg){
            $parts[] = $this->relationshipExists($parentAlias, $leg, $childQuery, $relationshipQuery, $state, $depth);
        }
        $any = empty($parts) ? '0=1' : (count($parts) === 1 ? $parts[0] : '('.implode(' OR ', $parts).')');
        return $negate ? 'NOT '.$any : $any;
    }

    /** One leg: the outer record's type, and an EXISTS over its relationships in one stored direction. */
    private function relationshipExists(
        string $parentAlias,
        array $leg,
        array $childQuery,
        array $relationshipQuery,
        SqlBuildContext $state,
        int $depth
    ): string {
        $linkAlias = $state->nextAlias('rrl');
        $childAlias = $state->nextAlias('rr');
        $relationshipAlias = $state->nextAlias('rel');
        $direction = $leg['direction'];
        $relationTypes = $leg['types'];
        $outerType = '';
        if($leg['outerTypes'] !== null){
            $outerType = $this->typeCondition($parentAlias.'.rec_RecTypeID', $leg['outerTypes'], $state).' AND ';
        }

        $parentColumn = $direction === 'to' ? 'rl_SourceID' : 'rl_TargetID';
        $childColumn = $direction === 'to' ? 'rl_TargetID' : 'rl_SourceID';
        $edge = array(
            $linkAlias.'.'.$parentColumn.'='.$parentAlias.'.rec_ID',
            $linkAlias.'.rl_RelationID IS NOT NULL'
        );
        if($relationTypes !== null){
            $edge[] = $this->typeCondition($linkAlias.'.rl_RelationTypeID', $relationTypes, $state);
        }
        if($leg['linkedTypes'] !== null){
            $edge[] = $this->typeCondition($childAlias.'.rec_RecTypeID', $leg['linkedTypes'], $state);
        }
        $childWhere = $this->compileGroup($childQuery, 'AND', $state, $childAlias, $depth);
        $this->records->appendAccessConditions($childWhere, $state, $state['context'], $childAlias);
        $relationshipWhere = $this->compileGroup(
            $relationshipQuery, 'AND', $state, $relationshipAlias, $depth
        );
        $this->records->appendAccessConditions(
            $relationshipWhere, $state, $state['context'], $relationshipAlias
        );

        $exists = 'EXISTS (SELECT 1 FROM recLinks '.$linkAlias
            .' INNER JOIN Records '.$childAlias.' ON '.$childAlias.'.rec_ID='
            .$linkAlias.'.'.$childColumn
            .' INNER JOIN Records '.$relationshipAlias.' ON '.$relationshipAlias.'.rec_ID='
            .$linkAlias.'.rl_RelationID'
            .' WHERE '.implode(' AND ', array_merge($edge, $childWhere, $relationshipWhere)).')';
        return $outerType === '' ? $exists : '('.$outerType.$exists.')';
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

    /** `column IN (ids)`, or a false condition for an empty list. */
    private function typeCondition(string $column, array $ids, SqlBuildContext $state): string
    {
        if(empty($ids)){ return '0=1'; }
        foreach($ids as $id){ $state->bind(intval($id), 'i'); }
        return $column.' IN ('.implode(',', array_fill(0, count($ids), '?')).')';
    }

}
