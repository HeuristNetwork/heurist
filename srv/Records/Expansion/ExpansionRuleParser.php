<?php
/**
* ExpansionRuleParser.php - Ruleset validation and compact-path conversion
*
* @project     Heurist academic knowledge management system
* @package     Records\Search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace Heurist\Records\Expansion;


use Heurist\Records\Query\Parser\RecordQueryParser;
use Heurist\Records\Query\QueryValidationException;

/** Parses canonical JSON rules and the established compact path notation. */
final class ExpansionRuleParser
{
    /** @var RecordQueryParser */
    private $queryParser;

    public function __construct(?RecordQueryParser $queryParser = null)
    {
        $this->queryParser = $queryParser ?? new RecordQueryParser();
    }

    /** Return a normalized rule tree without removing sibling branches. */
    public function parse($rules): array
    {
        if(is_string($rules)){
            $text = trim($rules);
            if($text === ''){ return array(); }
            $decoded = json_decode($text, true);
            if(json_last_error() === JSON_ERROR_NONE){
                $rules = $decoded;
            }else{
                return $this->pathToRules($text);
            }
        }
        if(!is_array($rules)){
            throw new QueryValidationException('Expansion rules must be a JSON ruleset or compact path');
        }
        if(isset($rules['query'])){ $rules = array($rules); }
        return $this->normalizeLevel($rules, 0);
    }

    /** Convert type:link:type compact notation to the canonical nested ruleset. */
    public function pathToRules(string $path): array
    {
        $tokens = array_values(array_filter(array_map('trim', explode(':', $path)), 'strlen'));
        if(count($tokens) < 3 || !ctype_digit($tokens[0]) || count($tokens)%2 === 0){
            throw new QueryValidationException('Invalid compact expansion path: '.$path);
        }
        $parentType = intval($tokens[0]);
        $root = array();
        $level =& $root;
        for($index=1; $index<count($tokens); $index+=2){
            if(!preg_match('/^(lt|lf|rt|rf)([0-9]*)$/i', $tokens[$index], $match)
                || !ctype_digit($tokens[$index+1])){
                throw new QueryValidationException('Invalid compact expansion path step: '.$tokens[$index]);
            }
            $operator = strtolower($match[1]);
            $field = $match[2];
            $childType = intval($tokens[$index+1]);
            if($childType < 1){ throw new QueryValidationException('Record type IDs in paths must be positive'); }
            $inverse = array('lt'=>'lf', 'lf'=>'lt', 'rt'=>'rf', 'rf'=>'rt')[$operator];
            $key = $inverse.($field === '' ? '' : ':'.$field);
            $rule = array(
                'query'=>array(array('t'=>$childType), array($key=>array(array('t'=>$parentType))))
            );
            $level[] = $rule;
            $last = count($level)-1;
            $level[$last]['levels'] = array();
            $level =& $level[$last]['levels'];
            $parentType = $childType;
        }
        return $this->normalizeLevel($root, 0);
    }

    private function normalizeLevel(array $level, int $depth): array
    {
        if($depth > 20){ throw new QueryValidationException('Expansion rules are nested too deeply'); }
        if($this->isAssociative($level)){ $level = array($level); }
        $normalized = array();
        foreach($level as $rule){
            if(!is_array($rule) || !array_key_exists('query', $rule)){
                throw new QueryValidationException('Every expansion rule requires a query');
            }
            $query = $this->queryParser->normalize($rule['query']);
            $normalized[] = array(
                'query'=>$query,
                'ignore'=>!empty($rule['ignore']),
                'levels'=>$this->normalizeLevel($rule['levels'] ?? array(), $depth+1)
            );
        }
        return $normalized;
    }

    private function isAssociative(array $value): bool
    {
        return !empty($value) && array_keys($value) !== range(0, count($value)-1);
    }
}
