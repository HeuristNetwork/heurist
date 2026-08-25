<?php
/**
* RecordQueryParser.php - Plain-text and JSON query normalization and validation
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

use hserv\records\search\QueryValidationException;
use hserv\records\search\UnsupportedQueryException;

/** Parses and validates the established Heurist record query language. */
final class RecordQueryParser
{
    private const MAX_SQL_LINK_DEPTH = 8;
    private const KEYWORD_ALIASES = array(
        'type'=>'t','typeid'=>'t','typename'=>'t','t'=>'t','id'=>'ids','ids'=>'ids',
        'title'=>'title','url'=>'url','notes'=>'notes','added'=>'added','modified'=>'modified',
        'before'=>'before','after'=>'after','since'=>'after','addedby'=>'addedby','owner'=>'owner',
        'workgroup'=>'owner','wg'=>'owner','access'=>'access','user'=>'user','usr'=>'user',
        'ws'=>'ws','workset'=>'ws','tag'=>'tag','keyword'=>'tag','kwd'=>'tag','field'=>'f','f'=>'f',
        'count'=>'fc','cnt'=>'fc','fc'=>'fc','geo'=>'geo','file'=>'file','linked_to'=>'lt',
        'linkedto'=>'lt','linkto'=>'lt','link_to'=>'lt','lt'=>'lt','linked_from'=>'lf',
        'linkedfrom'=>'lf','linkfrom'=>'lf','link_from'=>'lf','lf'=>'lf','related_to'=>'rt',
        'relatedto'=>'rt','rt'=>'rt','related_from'=>'rf','relatedfrom'=>'rf','rf'=>'rf',
        'related'=>'related','links'=>'links','relf'=>'relf','r'=>'r','any'=>'any','all'=>'all',
        'not'=>'not','sortby'=>'sortby','sort'=>'sortby','s'=>'sortby'
    );
    private const LINK_PREDICATES = array('lt','linked_to','linkedto','lf','linked_from','linkedfrom',
        'rt','related_to','relatedto','rf','related_from','relatedfrom','related','links','relf','r');
    private $textSubqueries = array();

    public function normalize($query): array
    {
        if(is_array($query)){
            $normalized = $query;
        }else{
            $text = trim((string)$query);
            if($text === ''){
                throw new QueryValidationException('Query cannot be empty');
            }
            $decoded = json_decode($text, true);
            if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
                $normalized = $decoded;
            }else{
                $normalized = $this->textToJson($text);
            }
        }

        $normalized = $this->normalizeQueryArray($normalized);
        $this->validate($normalized);
        return $normalized;
    }

    /**
     * Convert simplified Heurist plain text into JSON-query form.
     */
    public function textToJson(string $query): array
    {
        $this->textSubqueries = array();
        return $this->parsePlainText($query);
    }

    /** Parse plain text while sharing the current recursive-subquery registry. */
    private function parsePlainText(string $query): array
    {
        $query = trim(ltrim(trim($query), '*'));
        if($query === ''){
            throw new QueryValidationException('Query cannot be empty');
        }

        $query = $this->extractTextSubqueries($query);
        $tokens = $this->tokenize($query);
        $result = array();

        for($index=0; $index<count($tokens); $index++){
            $token = $tokens[$index];
            if($token === ''){ continue; }

            list($rawKeyword, $inlineValue, $hasInlineValue) = $this->splitKeywordValue($token);
            list($keyword, $suffix) = $this->plainKeyword($rawKeyword);

            if($keyword === null){
                $result[] = array('title'=>$this->unquote($token));
                continue;
            }

            if($suffix !== ''){ $keyword .= ':'.$suffix; }
            if($hasInlineValue){
                $value = $inlineValue;
            }else{
                if(in_array($keyword, array('user','ws'), true)
                    && (!isset($tokens[$index+1]) || $this->looksLikeKeywordToken($tokens[$index+1]))){
                    $value = 'current';
                }elseif(!isset($tokens[$index+1])){
                    throw new QueryValidationException('Missing value for query keyword '.$rawKeyword);
                }else{
                    $value = $tokens[++$index];
                }
            }
            $value = $this->resolveTextValue($value);
            $result[] = array($keyword=>$value);
        }

        if(empty($result)){
            throw new QueryValidationException('Query contains no searchable predicates');
        }
        return $result;
    }

    /** Validate the established Heurist JSON query structure. */
    public function validate(array $query): void
    {
        if(empty($query)){
            throw new QueryValidationException('Query contains no predicates');
        }
        $this->validateGroup($query, 0);
    }

    /**
     * Build the paginated IDs-only query for a flat query.
     *
     * Context keys: userId, groupIds, isDbOwner, limit, offset.
     */
    public function normalizeQueryArray(array $query): array
    {
        if($this->isAssociative($query)){
            $result = array();
            foreach($query as $key=>$value){
                $result[] = array((string)$key=>$value);
            }
            return $result;
        }
        return array_values($query);
    }
    private function validateGroup(array $group, int $depth): void
    {
        if($depth > 20){
            throw new QueryValidationException('Query nesting is too deep');
        }
        foreach($group as $predicate){
            if(!is_array($predicate) || count($predicate)!==1){
                throw new QueryValidationException('Every query predicate must contain exactly one key');
            }
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if(!$this->isKnownPredicate($base)){
                throw new QueryValidationException('Unknown query predicate: '.$key);
            }
            if(in_array($base, array('fc','count','cnt'), true)
                && ($suffix === '' || !$this->isResolvableFieldSuffix($suffix))){
                throw new QueryValidationException('Predicate '.$key.' requires a field ID or field name');
            }
            if(in_array($base, array('f','field'), true)
                && $suffix !== '' && !$this->isValidFieldSuffix($suffix)){
                throw new QueryValidationException('Predicate '.$key.' has an invalid field ID');
            }
            if($base === 'relf' && ($suffix === '' || !$this->isResolvableFieldSuffix($suffix))){
                throw new QueryValidationException('Predicate '.$key.' requires a Relationship field ID or name');
            }
            if($base === 'r' && $suffix !== '' && !$this->isResolvableFieldSuffix($suffix)){
                throw new QueryValidationException('Predicate '.$key.' has an invalid Relationship field');
            }
            if(($base === 'any' || $base === 'all' || $base === 'not') && !is_array($value)){
                throw new QueryValidationException($base.' requires a query array');
            }
            if(is_array($value) && ($base === 'any' || $base === 'all' || $base === 'not'
                || (in_array($base, self::LINK_PREDICATES, true) && !$this->isScalarValueList($value)))){
                $nested = $this->normalizeQueryArray($value);
                $this->validateGroup($nested, $depth+1);
            }
        }
    }
    private function assertFlatExecutable(array $query): void
    {
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base) = $this->predicateParts($key);
            if(in_array($base, self::LINK_PREDICATES, true)){
                throw new UnsupportedQueryException(
                    'Linked and related predicates will be executed separately in Phase 2: '.$key
                );
            }
            if(($base === 'any' || $base === 'all' || $base === 'not') && is_array($value)){
                $this->assertFlatExecutable($this->normalizeQueryArray($value));
            }elseif(is_array($value) && !in_array($base, array(
                't','type','typeid','id','ids','tag','keyword','kwd','sortby','sort','s'
            ), true)){
                throw new UnsupportedQueryException('Nested query requires Phase 2 execution: '.$key);
            }
        }
    }

    /** Validate the bounded subset that can be expressed as correlated EXISTS. */
    private function assertSqlExecutable(array $query, int $depth = 0): void
    {
        if($depth > self::MAX_SQL_LINK_DEPTH){
            throw new UnsupportedQueryException('Linked query exceeds the SQL nesting limit');
        }
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if(in_array($base, array('links','related','r','relf'), true)){
                throw new UnsupportedQueryException('Predicate requires chunked execution: '.$key);
            }
            if(in_array($base, array('lt','linked_to','linkedto','lf','linked_from','linkedfrom'), true)
                && $this->isLinkFieldPresenceTest($suffix, $value)){
                continue;
            }
            if(in_array($base, array('rt','related_to','relatedto','rf','related_from','relatedfrom'), true)
                && $suffix !== ''){
                throw new UnsupportedQueryException('Relation-marker predicates require chunked execution: '.$key);
            }
            if(in_array($base, array('any','all','not'), true)){
                $this->assertSqlExecutable($this->normalizeQueryArray($value), $depth);
            }elseif(in_array($base, self::LINK_PREDICATES, true)){
                $child = $this->linkedValueQuery($value);
                if(in_array($base, array('rt','related_to','relatedto','rf','related_from','relatedfrom'), true)){
                    list($child, $relationship) = $this->splitRelationshipQuery($child);
                    $this->assertSqlExecutable($relationship, $depth+1);
                }
                $this->assertSqlExecutable($child, $depth+1);
            }elseif(is_array($value)
                && !in_array($base, array(
                    't','type','typeid','id','ids','tag','keyword','kwd','sortby','sort','s','geo'
                ), true)){
                throw new UnsupportedQueryException('Nested query cannot be compiled to SQL: '.$key);
            }
        }
    }
    public function predicateParts(string $key): array
    {
        $parts = explode(':', strtolower(trim($key)), 2);
        return array($parts[0], $parts[1] ?? '');
    }
    private function isKnownPredicate(string $base): bool
    {
        return in_array($base, array(
            '_all','plain','t','type','typeid','typename','id','ids','title','url','notes',
            'added','modified','before','after','since','addedby','owner','workgroup','wg',
            'access','user','usr','ws','workset','tag','keyword','kwd','f','field','fc','count','cnt',
            'geo','file','lt','linked_to','linkedto','lf','linked_from','linkedfrom',
            'rt','related_to','relatedto','rf','related_from','relatedfrom','related',
            'links','relf','r','any','all','not','sortby','sort','s'
        ), true);
    }
    private function isAssociative(array $value): bool
    {
        if(empty($value)){ return false; }
        return array_keys($value) !== range(0, count($value)-1);
    }
    private function isScalarValueList(array $value): bool
    {
        if(!empty($value) && $this->isAssociative($value)){ return false; }
        foreach($value as $item){ if(is_array($item)){ return false; } }
        return true;
    }
    private function extractTextSubqueries(string $query): string
    {
        while(strpos($query, ')') !== false){
            $close = strpos($query, ')');
            $open = strrpos(substr($query, 0, $close), '(');
            if($open === false){
                throw new QueryValidationException('Unmatched closing parenthesis');
            }
            $inner = substr($query, $open+1, $close-$open-1);
            $parsedInner = $this->parsePlainText($inner);
            $index = count($this->textSubqueries);
            $this->textSubqueries[$index] = $parsedInner;
            $query = substr($query, 0, $open).' __SUBQUERY_'.$index.'__ '.substr($query, $close+1);
        }
        if(strpos($query, '(') !== false){
            throw new QueryValidationException('Unmatched opening parenthesis');
        }
        return $query;
    }
    private function tokenize(string $query): array
    {
        preg_match_all('/(?:[^\s"]+|"[^"]*")+/u', $query, $matches);
        return $matches[0] ?? array();
    }
    private function looksLikeKeywordToken(string $token): bool
    {
        list($rawKeyword) = $this->splitKeywordValue($token);
        list($keyword) = $this->plainKeyword($rawKeyword);
        return $keyword !== null;
    }
    private function splitKeywordValue(string $token): array
    {
        if(preg_match('/^([a-z_]+):([0-9]+):(.*)$/i', $token, $matches)){
            return array($matches[1].$matches[2], $matches[3], true);
        }
        $position = strpos($token, ':');
        if($position === false){ return array($token, null, false); }
        return array(substr($token, 0, $position), substr($token, $position+1), true);
    }
    private function plainKeyword(string $raw): array
    {
        $raw = trim($raw);
        $lower = strtolower($raw);
        if(preg_match('/^([a-z_]+?)([0-9]+)$/i', $raw, $matches)){
            $base = $matches[1]; $suffix = $matches[2];
        }else{
            $base = $lower; $suffix = '';
            foreach(array('linked_from','linked_to','related_from','related_to','field','file','geo','relf','fc','lf','lt','rf','rt','f','r') as $prefix){
                if(strpos($lower, $prefix) === 0 && strlen($raw)>strlen($prefix)){
                    $base = $prefix; $suffix = substr($raw, strlen($prefix)); break;
                }
            }
        }
        $base = strtolower($base);
        if(!isset(self::KEYWORD_ALIASES[$base])){ return array(null, ''); }
        return array(self::KEYWORD_ALIASES[$base], $suffix);
    }
    private function resolveTextValue($value)
    {
        $value = $this->unquote((string)$value);
        if(preg_match('/^__SUBQUERY_([0-9]+)__$/', $value, $matches)){
            $index = intval($matches[1]);
            if(!isset($this->textSubqueries[$index])){
                throw new QueryValidationException('Invalid subquery placeholder');
            }
            return $this->textSubqueries[$index];
        }
        return $value;
    }
    private function unquote(string $value): string
    {
        $value = trim($value);
        if(strlen($value)>=2 && $value[0] === '"' && $value[strlen($value)-1] === '"'){
            return substr($value, 1, -1);
        }
        return $value;
    }

    public function supportsFlatExecution($query): bool
    {
        try{ $this->assertFlatExecutable($this->normalize($query)); return true; }
        catch(UnsupportedQueryException $e){ return false; }
    }

    public function supportsSqlExecution($query): bool
    {
        try{ $this->assertSqlExecutable($this->normalize($query)); return true; }
        catch(UnsupportedQueryException $e){ return false; }
    }

    public function linkedValueQuery($value): array
    {
        if(is_array($value) && !$this->isScalarValueList($value)){ return $this->normalizeQueryArray($value); }
        return array(array('ids'=>$this->numericList($value, 'linked record')));
    }

    private function isLinkFieldPresenceTest(string $suffix, $value): bool
    {
        if($suffix==='' || !ctype_digit($suffix) || is_array($value)){return false;}
        $value=strtoupper(trim((string)$value));
        return $value==='NULL' || $value==='-NULL';
    }

    private function isValidFieldSuffix(string $suffix): bool
    {
        return $this->isResolvableFieldSuffix($suffix);
    }
    private function isResolvableFieldSuffix(string $suffix): bool
    {
        return preg_match('/^(?:[1-9]\d*|[^:]+)(?::(?:term|label|concept|conceptid|desc|code))?$/u',$suffix)===1;
    }

    private function splitRelationshipQuery(array $query): array
    {
        $child = array(); $relationship = array();
        foreach($query as $predicate){
            $key = (string)array_keys($predicate)[0];
            list($base, $suffix) = $this->predicateParts($key);
            if($base === 'relf' || ($base === 'r' && $suffix !== '')){
                $relationship[] = array('f:'.$suffix=>$predicate[$key]);
            }elseif(!($base === 'r' && $suffix === '')){ $child[] = $predicate; }
        }
        return array(empty($child)?array(array('_all'=>true)):$child,
            empty($relationship)?array(array('_all'=>true)):$relationship, null);
    }

    private function numericList($value, string $label): array
    {
        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
        $result = array();
        foreach($items as $item){
            if(!is_numeric($item) || intval($item)<1){
                throw new QueryValidationException('Invalid '.$label.' ID: '.(string)$item);
            }
            $result[] = intval($item);
        }
        $result = array_values(array_unique($result));
        if(empty($result)){ throw new QueryValidationException('Empty '.$label.' ID list'); }
        return $result;
    }
}
