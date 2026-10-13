<?php
/**
* ValidationResult.php - Issues and prepared detail rows of RecordValidator
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

/** Result of one record validation. */
final class ValidationResult
{
    /** @var array<int,array{field:string,index:?int,code:string,severity:string,message:string}> */
    private array $issues = array();
    /** @var array<int,array{dty:int,value:?string,fileId:?int,geo:?string,hidden:?int}> */
    private array $rows = array();

    /** Add an error (the record cannot be saved). */
    public function error(string $field, ?int $index, string $code, string $message): void
    {
        $this->issues[] = array('field'=>$field, 'index'=>$index, 'code'=>$code, 'severity'=>'error', 'message'=>$message);
    }

    /** Add a warning (the record is saved). */
    public function warning(string $field, ?int $index, string $code, string $message): void
    {
        $this->issues[] = array('field'=>$field, 'index'=>$index, 'code'=>$code, 'severity'=>'warning', 'message'=>$message);
    }

    /**
     * Add a prepared recDetails row.
     *
     * @param array{dty:int,value:?string,fileId:?int,geo:?string,hidden:?int} $row
     */
    public function addRow(array $row): void
    {
        $this->rows[] = $row;
    }

    /** True when there is at least one error. */
    public function hasErrors(): bool
    {
        foreach($this->issues as $issue){
            if($issue['severity'] === 'error'){ return true; }
        }
        return false;
    }

    /** @return array<int,array> All issues in the order found. */
    public function issues(): array
    {
        return $this->issues;
    }

    /** @return array<int,array> Warnings only. */
    public function warnings(): array
    {
        return array_values(array_filter($this->issues, static function($issue){
            return $issue['severity'] === 'warning';
        }));
    }

    /** @return array<int,array{dty:int,value:?string,fileId:?int,geo:?string,hidden:?int}> */
    public function rows(): array
    {
        return $this->rows;
    }
}
