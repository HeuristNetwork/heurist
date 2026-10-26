<?php
/**
* CmsRecordCheck.php - Save check of a v4 website or page record (plan 27a §7)
*
* Added to RecordValidator (addRecordCheck) by ServiceFactory. Runs only for
* the website and page record types when the saved version (2-49) is 4, so
* legacy websites are not affected. A configuration that is not JSON or not
* "heurist-cms/4" blocks the save; the other problems are warnings.
*
* @project     Heurist academic knowledge management system
* @package     Website
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website;

use Heurist\Records\Edit\ValidationResult;

/** Callable record check for RecordValidator. */
final class CmsRecordCheck
{
    /** @var array<string,int> */
    private array $codes;
    private string $baseUrl;

    /** @param array<string,int> $codes WebsiteRepository::codes(). */
    public function __construct(array $codes, string $baseUrl = '')
    {
        $this->codes = $codes;
        $this->baseUrl = $baseUrl;
    }

    /** @param array<int,array<int,mixed>> $details dty => values, as given to the validator. */
    public function __invoke(int $rectypeId, array $details, ValidationResult $result): void
    {
        if($rectypeId < 1 || !in_array($rectypeId, array($this->codes['home'] ?? 0, $this->codes['page'] ?? 0), true)){ return; }
        if(trim($this->first($details, 'version')) !== WebsiteService::VERSION){ return; }
        $key = (string)($this->codes['configuration'] ?? 0);
        $parsed = CmsValidator::parse($this->first($details, 'configuration'));
        foreach($parsed['issues'] as $issue){
            $result->error($key, null, $issue['code'], $issue['message']);
        }
        if(CmsValidator::hasError($parsed['issues'])){ return; }
        $parts = array();
        foreach(array('content', 'header', 'footer') as $name){
            $parts[] = $this->first($details, $name);
        }
        $translations = CmsValidator::translations($this->all($details, 'translations'));
        // the hosts of a record are configured on the same record (header and footer on the website)
        foreach(CmsValidator::check($parsed['config'], $parts, $translations, $this->baseUrl) as $issue){
            if($issue['level'] === 'warning'){
                $result->warning($key, null, $issue['code'], $issue['message']);
            }
        }
    }

    /** First value of a field as text. */
    private function first(array $details, string $name): string
    {
        $values = $this->all($details, $name);
        return isset($values[0]) ? (string)$values[0] : '';
    }

    /** Values of a field as text. */
    private function all(array $details, string $name): array
    {
        $fieldId = $this->codes[$name] ?? 0;
        if($fieldId < 1){ return array(); }
        $values = $details[$fieldId] ?? $details[(string)$fieldId] ?? array();
        $result = array();
        foreach((array)$values as $value){
            $value = is_array($value) ? ($value['value'] ?? '') : $value;
            if(is_scalar($value)){ $result[] = (string)$value; }
        }
        return $result;
    }
}
