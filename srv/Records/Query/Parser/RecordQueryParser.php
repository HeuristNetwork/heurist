<?php
/**
* RecordQueryParser.php - Plain-text and JSON query normalization and validation
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

namespace Heurist\Records\Query\Parser;



use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\UnsupportedQueryException;

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
        'linkedto'=>'lt','lt'=>'lt','linked_from'=>'lf',
        'linkedfrom'=>'lf','lf'=>'lf','related_to'=>'rt',
        'relatedto'=>'rt','rt'=>'rt','related_from'=>'rf','relatedfrom'=>'rf','rf'=>'rf',
        'related'=>'related','links'=>'links','connected'=>'connected','relf'=>'relf','r'=>'r','any'=>'any','all'=>'all',
        'not'=>'not','sortby'=>'sortby','sort'=>'sortby','s'=>'sortby'
    );
    /**
     * OUTDATED aliases of the link and relationship predicates, kept only for backward
     * compatibility with existing saved filters, rule sets and facet definitions written in the
     * old JSON format (hserv composeSql.php gives them the same meaning). normalize() replaces
     * them by lt / lf / rt / rf at every level, so the rest of the engine sees only the short
     * keys. Do not use them in new code or documentation. (2026-11-12)
     */
    private const LEGACY_LINK_ALIASES = array(
        'linked_to'=>'lt', 'linkedto'=>'lt', 'linked_from'=>'lf', 'linkedfrom'=>'lf',
        'related_to'=>'rt', 'relatedto'=>'rt', 'related_from'=>'rf', 'relatedfrom'=>'rf'
    );
    private const LINK_PREDICATES = array('lt','linked_to','linkedto','lf','linked_from','linkedfrom',
        'rt','related_to','relatedto','rf','related_from','relatedfrom','related','links','connected','relf','r');
    private $textSubqueries = array();
    /** @var array<string,bool> Additional logical fields normalized to f:<name>. */
    private $logicalFields = array();

    /** @param string[] $logicalFields Optional storage-independent field names. */
    public function __construct(array $logicalFields = array())
    {
        foreach($logicalFields as $field){
            $field = strtolower(trim((string)$field));
            if($field !== ''){ $this->logicalFields[$field] = true; }
        }
    }

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

        $normalized = $this->normalizeLogicalFields($this->expandTextGroups($this->normalizeQueryArray($normalized)));
        $normalized = $this->replaceLegacyLinkAliases($normalized);
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

        // Conjunctions of the old simple format (2026-11-12): uppercase AND / && splits the
        // query at the top, uppercase OR splits a part into alternatives, the words of an
        // alternative are ANDed: "a b OR c d" = (a AND b) OR (c AND d). Sorting is taken out
        // of the alternatives and applies to the whole query.
        $result = array();
        $sort = array();
        foreach($this->splitTokens($tokens, array('AND','&&')) as $part){
            $alternatives = array();
            foreach($this->splitTokens($part, array('OR')) as $alternative){
                $predicates = array();
                foreach($this->parseTokens($alternative) as $predicate){
                    list($base) = $this->predicateParts((string)array_keys($predicate)[0]);
                    if($base === 'sortby'){ $sort[] = $predicate; }else{ $predicates[] = $predicate; }
                }
                if(!empty($predicates)){ $alternatives[] = $predicates; }
            }
            if(count($alternatives) === 1){
                $result = array_merge($result, $alternatives[0]);
            }elseif(count($alternatives) > 1){
                $any = array();
                foreach($alternatives as $predicates){
                    $any[] = count($predicates) === 1 ? $predicates[0] : array('all'=>$predicates);
                }
                $result[] = array('any'=>$any);
            }
        }
        $result = array_merge($result, $sort);

        if(empty($result)){
            throw new QueryValidationException('Query contains no searchable predicates');
        }
        return $result;
    }

    /** Split tokens at the given separator words (exact case); empty parts are dropped. */
    private function splitTokens(array $tokens, array $separators): array
    {
        $parts = array(array());
        foreach($tokens as $token){
            if(in_array($token, $separators, true)){ $parts[] = array(); continue; }
            $parts[count($parts)-1][] = $token;
        }
        return array_values(array_filter($parts));
    }

    /** Predicates of one alternative: words and keyword predicates, ANDed. */
    private function parseTokens(array $tokens): array
    {
        $result = array();

        for($index=0; $index<count($tokens); $index++){
            $token = $tokens[$index];
            if($token === ''){ continue; }

            // -keyword:value = not (keyword:value), the old meaning: no value matches.
            // A word (-Johnson) stays a negated title value.
            $negate = false;
            if(strlen($token) > 1 && $token[0] === '-' && $this->looksLikeKeywordToken(substr($token, 1))){
                $negate = true;
                $token = substr($token, 1);
            }

            list($rawKeyword, $inlineValue, $hasInlineValue) = $this->splitKeywordValue($token);
            list($keyword, $suffix) = $this->plainKeyword($rawKeyword, $hasInlineValue);

            if($keyword === null){
                $this->assertNotOutdatedKeyword($rawKeyword, $hasInlineValue);
                $result[] = array('title'=>$this->unquote($token));
                continue;
            }

            $next = $tokens[$index+1] ?? null;
            $nextIsSubquery = $next !== null && preg_match('/^__SUBQUERY_[0-9]+__$/', $next) === 1;
            if($hasInlineValue && $nextIsSubquery && $inlineValue === ''){
                // f:134:(t:12 title:Berlin) - the subquery is the value
                $hasInlineValue = false;
            }elseif($hasInlineValue && $nextIsSubquery && $suffix === '' && ctype_digit($inlineValue)
                && in_array($keyword, self::LINK_PREDICATES, true)){
                // linkedto:134(t:12) - the number is the field
                $suffix = $inlineValue;
                $hasInlineValue = false;
            }

            if($suffix !== ''){ $keyword .= ':'.$suffix; }
            if($hasInlineValue){
                $value = $inlineValue;
            }else{
                if(in_array($keyword, array('user','ws'), true)
                    && ($next === null || $this->looksLikeKeywordToken($next))){
                    $value = 'current';
                }elseif($next === null){
                    throw new QueryValidationException('Missing value for query keyword '.$rawKeyword);
                }else{
                    $value = $tokens[++$index];
                }
            }
            $value = $this->resolveTextValue($value);

            // all:x / any:x with a value (not a group) = any field, as in the old format
            if(($keyword === 'all' || $keyword === 'any') && !is_array($value)){ $keyword = 'f'; }
            // a tag is matched exactly anyway: tag=x is tag:x
            if($keyword === 'tag' && is_string($value) && strpos($value, '=') === 0){ $value = substr($value, 1); }

            $predicate = array($keyword=>$value);
            $result[] = $negate && $keyword !== 'sortby' ? array('not'=>array($predicate)) : $predicate;
        }
        return $result;
    }

    /**
     * Keywords of the old simple format that are not supported any more: an error with a
     * hint instead of a silent title search. (2026-11-12)
     */
    private const OUTDATED_KEYWORDS = array(
        'linkto'=>'lt (records that point to) or lf (records pointed to)',
        'link_to'=>'lt (records that point to) or lf (records pointed to)',
        'linkfrom'=>'lt (records that point to) or lf (records pointed to)',
        'link_from'=>'lt (records that point to) or lf (records pointed to)',
        'relationsfor'=>'related:<record id>',
        'latitude'=>'geo:within or geo:intersects with a WKT area',
        'longitude'=>'geo:within or geo:intersects with a WKT area',
        'lat'=>'geo:within or geo:intersects with a WKT area',
        'long'=>'geo:within or geo:intersects with a WKT area',
        'lng'=>'geo:within or geo:intersects with a WKT area',
        'vt'=>'access:', 'visibility'=>'access:',
        'u'=>'url:', 'n'=>'notes:', 'date'=>'modified:',
        'hhash'=>null
    );

    /** Throw for an outdated keyword used as a keyword (with a value). */
    private function assertNotOutdatedKeyword(string $rawKeyword, bool $usedAsKeyword): void
    {
        $lower = strtolower(trim($rawKeyword));
        if(!$usedAsKeyword || !array_key_exists($lower, self::OUTDATED_KEYWORDS)){ return; }
        $hint = self::OUTDATED_KEYWORDS[$lower];
        throw new QueryValidationException('Query keyword '.$lower.' is not supported any more'
            .($hint === null ? '' : ', use '.$hint));
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
        $result = array();
        foreach(array_values($query) as $item){
            if(is_array($item) && count($item) > 1 && $this->isAssociative($item)){
                foreach($item as $key=>$value){ $result[] = array((string)$key=>$value); }
            }else{
                $result[] = $item;
            }
        }
        return $result;
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
            if($base === 'connected' && $suffix !== ''){
                throw new QueryValidationException('Predicate connected does not accept a field or relationship type');
            }
            if($base === 'relf' && ($suffix === ''|| !$this->isResolvableFieldSuffix($suffix))){
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
            if(in_array($base, array('r','relf'), true)){
                throw new UnsupportedQueryException('Predicate requires chunked execution: '.$key);
            }
            if(in_array($base, array('lt','linked_to','linkedto','lf','linked_from','linkedfrom'), true)
                && $this->isLinkFieldPresenceTest($suffix, $value)){
                continue;
            }
            if(in_array($base, array('any','all','not'), true)){
                $this->assertSqlExecutable($this->normalizeQueryArray($value), $depth);
            }elseif(in_array($base, self::LINK_PREDICATES, true)){
                $child = $this->linkedValueQuery($value);
                if(in_array($base, array('rt','related_to','relatedto','rf','related_from','relatedfrom','related'), true)){
                    list($child, $relationship) = $this->splitRelationshipQuery($child);
                    $this->assertSqlExecutable($relationship, $depth+1);
                }elseif($base === 'connected' && $this->hasRelationshipConstraint($child)){
                    throw new QueryValidationException('connected does not accept r or relf; use related');
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
            'links','connected','relf','r','any','all','not','sortby','sort','s','exists'
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
        // parentheses inside "quoted" values (e.g. WKT "POLYGON ((…))") are literal
        while(($close = $this->unquotedPosition($query, ')')) !== null){
            $open = $this->unquotedPosition(substr($query, 0, $close), '(', true);
            if($open === null){
                throw new QueryValidationException('Unmatched closing parenthesis');
            }
            $inner = substr($query, $open+1, $close-$open-1);
            $parsedInner = $this->parsePlainText($inner);
            $index = count($this->textSubqueries);
            $this->textSubqueries[$index] = $parsedInner;
            $query = substr($query, 0, $open).' __SUBQUERY_'.$index.'__ '.substr($query, $close+1);
        }
        if($this->unquotedPosition($query, '(') !== null){
            throw new QueryValidationException('Unmatched opening parenthesis');
        }
        return $query;
    }
    /** Position of the first (or last) `$char` outside double quotes, or null. */
    private function unquotedPosition(string $text, string $char, bool $last = false): ?int
    {
        $found = null;
        $quoted = false;
        for($i=0, $n=strlen($text); $i<$n; $i++){
            if($text[$i] === '"'){ $quoted = !$quoted; continue; }
            if(!$quoted && $text[$i] === $char){
                if(!$last){ return $i; }
                $found = $i;
            }
        }
        return $found;
    }
    private function tokenize(string $query): array
    {
        preg_match_all('/(?:[^\s"]+|"[^"]*")+/u', $query, $matches);
        return $matches[0] ?? array();
    }
    private function looksLikeKeywordToken(string $token): bool
    {
        list($rawKeyword, , $hasValue) = $this->splitKeywordValue($token);
        list($keyword) = $this->plainKeyword($rawKeyword, $hasValue);
        return $keyword !== null;
    }

    /**
     * Split a token into [keyword, value, has value]. The keyword ends at the first `:`,
     * or at `=`, `<`, `>` written instead of the colon (old format: title=x, f:1>B,
     * modified>2001-09-11); the operator stays in the value (title:=x, f:1:>B).
     * A field may be given by number or name: f:1:x, f:Name:x, f:"Name with spaces"<x.
     */
    private function splitKeywordValue(string $token): array
    {
        // geo[:<id>]:within|intersects:<value> - the match mode belongs to the key
        if(preg_match('/^(geo(?::?[0-9]+)?:(?:within|intersects)):(.*)$/i', $token, $matches)){
            return array($matches[1], $matches[2], true);
        }
        // <keyword>:<field id><separator><value>
        if(preg_match('/^([a-z_]+):([0-9]+)([:=<>])(.*)$/is', $token, $matches)){
            return array($matches[1].$matches[2], ($matches[3] === ':' ? '' : $matches[3]).$matches[4], true);
        }
        // f / fc with a field name: f:Name:x, f:"Birth date":<1970 (an any-field value
        // containing a colon is written in quotes: f:"10:30")
        if(preg_match('/^(f|field|fc|count|cnt):(?:"([^"]+)"|([^\s:"=<>@\-][^\s:"=<>]*))([:=<>])(.*)$/is', $token, $matches)){
            $name = $matches[2] !== '' ? $matches[2] : $matches[3];
            return array($matches[1].':'.$name, ($matches[4] === ':' ? '' : $matches[4]).$matches[5], true);
        }
        $position = $this->firstUnquoted($token, ':=<>');
        if($position === null || $position === 0){ return array($token, null, false); }
        $separator = $token[$position];
        return array(substr($token, 0, $position), ($separator === ':' ? '' : $separator).substr($token, $position+1), true);
    }

    /** Position of the first of $chars outside double quotes, or null. */
    private function firstUnquoted(string $text, string $chars): ?int
    {
        $quoted = false;
        for($i=0, $n=strlen($text); $i<$n; $i++){
            if($text[$i] === '"'){ $quoted = !$quoted; continue; }
            if(!$quoted && strpos($chars, $text[$i]) !== false){ return $i; }
        }
        return null;
    }

    /**
     * [canonical keyword, suffix] of a plain-text keyword, or [null, ''] for a word.
     * $hasValue: the keyword has an inline value; only then a field name may be glued
     * on (fGender:male), so a word like "fred" is not f + "red".
     */
    private function plainKeyword(string $raw, bool $hasValue = true): array
    {
        $raw = trim($raw);
        $lower = strtolower($raw);
        if(preg_match('/^geo(?::?([0-9]+))?:(within|intersects)$/', $lower, $matches)){
            return array('geo', ($matches[1] !== '' ? $matches[1].':' : '').$matches[2]);
        }
        // f:Name / fc:"Birth date" from splitKeywordValue()
        if(preg_match('/^(f|field|fc|count|cnt):(.+)$/is', $raw, $matches)){
            return array(self::KEYWORD_ALIASES[strtolower($matches[1])], $matches[2]);
        }
        // a whole keyword wins over prefix splitting (`related` is not `r` + "elated")
        if(isset(self::KEYWORD_ALIASES[$lower])){
            return array(self::KEYWORD_ALIASES[$lower], '');
        }
        if(preg_match('/^([a-z_]+?)([0-9]+)$/i', $raw, $matches)){
            $base = $matches[1]; $suffix = $matches[2];
        }else{
            $base = $lower; $suffix = '';
            // only a field keyword takes a glued name: fGender:male, fcAuthor:>2
            foreach(array('field','fc','f') as $prefix){
                if($hasValue && strpos($lower, $prefix) === 0 && strlen($raw)>strlen($prefix)){
                    $base = $prefix; $suffix = substr($raw, strlen($prefix)); break;
                }
            }
        }
        $base = strtolower($base);
        if(!isset(self::KEYWORD_ALIASES[$base])){
            return isset($this->logicalFields[$base]) ? array('f', $base) : array(null, '');
        }
        return array(self::KEYWORD_ALIASES[$base], $suffix);
    }
    /**
     * Replace the outdated link aliases (LEGACY_LINK_ALIASES) by lt / lf / rt / rf, keeping the
     * field suffix ("linkedto:134" -> "lt:134"), in groups and linked subqueries at any depth.
     */
    private function replaceLegacyLinkAliases(array $group): array
    {
        $result = array();
        foreach($group as $predicate){
            if(!is_array($predicate) || count($predicate) !== 1){
                $result[] = $predicate;
                continue;
            }
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base, $suffix) = $this->predicateParts($key);
            if(isset(self::LEGACY_LINK_ALIASES[$base])){
                $base = self::LEGACY_LINK_ALIASES[$base];
                $key = $base.($suffix !== '' ? ':'.$suffix : '');
            }
            if(is_array($value) && (in_array($base, array('any','all','not'), true)
                || (in_array($base, self::LINK_PREDICATES, true) && !$this->isScalarValueList($value)))){
                $value = $this->replaceLegacyLinkAliases($this->normalizeQueryArray($value));
            }
            $result[] = array($key=>$value);
        }
        return $result;
    }

    /** Normalize schema fields recursively before ordinary query validation. */
    private function normalizeLogicalFields(array $group): array
    {
        $result = array();
        foreach($group as $predicate){
            if(!is_array($predicate) || count($predicate) !== 1){
                $result[] = $predicate;
                continue;
            }
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base) = $this->predicateParts($key);
            if(isset($this->logicalFields[$base])){ $key = 'f:'.$base; }
            if(is_array($value) && in_array($base, array('any','all','not'), true)){
                $value = $this->normalizeLogicalFields($this->normalizeQueryArray($value));
            }
            $result[] = array($key=>$value);
        }
        return $result;
    }
    /**
     * A group (`all` / `any` / `not`) may hold a plain-text query, e.g. a text query
     * embedded by an expansion level `{t:10, lf:[{all:"t:5 f:1:x"}]}`. It is parsed
     * here; its sort is dropped (only the top level sorts).
     */
    private function expandTextGroups(array $group): array
    {
        $result = array();
        foreach($group as $predicate){
            if(!is_array($predicate) || count($predicate) !== 1){
                $result[] = $predicate;
                continue;
            }
            $key = (string)array_keys($predicate)[0];
            $value = $predicate[$key];
            list($base) = $this->predicateParts($key);
            $isGroup = in_array($base, array('any','all','not'), true);
            if($isGroup && is_string($value) && trim($value) !== ''){
                $decoded = json_decode(trim($value), true);
                $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                    ? $decoded : $this->withoutSort($this->textToJson($value));
            }
            if(is_array($value) && ($isGroup
                || (in_array($base, self::LINK_PREDICATES, true) && !$this->isScalarValueList($value)))){
                $value = $this->expandTextGroups($this->normalizeQueryArray($value));
            }
            $result[] = array($key=>$value);
        }
        return $result;
    }

    /** Predicates of a parsed text query without its sort. */
    private function withoutSort(array $group): array
    {
        return array_values(array_filter($group, function($predicate){
            if(!is_array($predicate) || count($predicate) !== 1){ return true; }
            list($base) = $this->predicateParts((string)array_keys($predicate)[0]);
            return !in_array($base, array('sortby','sort','s'), true);
        }));
    }

    private function resolveTextValue($value)
    {
        $value = $this->unquote((string)$value);
        // an operator before a quoted value: ="Johnson, John", -"New York", <"B"
        if(preg_match('/^(==|>=|<=|<>|=|<|>|-|@\+|@-|@)"(.*)"$/s', $value, $matches)){
            return $matches[1].$matches[2];
        }
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
        // an empty linked query means any record (in expansion rules: the parent result)
        if($value === null || $value === array()){ return array(); }
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

    /** Whether a linked query constrains the Relationship record (r, r:<field>, relf). */
    private function hasRelationshipConstraint(array $query): bool
    {
        foreach($query as $predicate){
            list($base) = $this->predicateParts((string)array_keys($predicate)[0]);
            if($base === 'r' || $base === 'relf'){ return true; }
        }
        return false;
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
