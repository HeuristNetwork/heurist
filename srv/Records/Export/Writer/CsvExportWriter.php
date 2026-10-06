<?php
/**
* CsvExportWriter.php - CSV/TSV export: one table per record type
*
* Each record type gets its own file "<record type name>.csv" (or .tsv) with the
* columns requested for it ("*" columns for the others, else the title). The
* first column is always H-ID (rec_ID). ExportService zips the files when there
* is more than one. No aggregations (they belong to Crosstabs).
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export\Writer;

use Heurist\Records\Export\ExportColumns;
use Heurist\Records\Export\ExportDefinitions;
use Heurist\Records\Export\ExportRequest;
use RuntimeException;

/** Writes CSV or TSV files per record type. */
final class CsvExportWriter implements ExportWriterInterface
{
    private ExportRequest $request;
    private ExportColumns $columns;
    private ExportDefinitions $definitions;
    private string $workDir;
    private string $extension;
    private string $eol;
    /** @var array<int,resource> */
    private array $handles = array();
    /** @var array<int,array> Expanded columns per record type. */
    private array $expanded = array();
    /** @var array<int,array{path:string,name:string}> */
    private array $files = array();
    /** @var array<int,int> Rows per record type. */
    private array $rows = array();

    /**
     * @param ExportRequest $request Columns and CSV options.
     * @param ExportColumns $columns Column expansion and cells.
     * @param ExportDefinitions $definitions Record type names (file names).
     * @param string $workDir Folder for the files.
     */
    public function __construct(ExportRequest $request, ExportColumns $columns, ExportDefinitions $definitions, string $workDir)
    {
        $this->request = $request;
        $this->columns = $columns;
        $this->definitions = $definitions;
        $this->workDir = rtrim($workDir, '/\\').'/';
        $this->extension = $request->format === 'tsv' || $request->csv['sep'] === "\t" ? 'tsv' : 'csv';
        $this->eol = $request->csv['eol'] === 'win' ? "\r\n" : "\n";
    }

    /** @inheritDoc */
    public function fieldCodes(): array
    {
        $all = array();
        foreach($this->request->columns as $list){
            foreach($list as $column){ $all[] = $column; }
        }
        return ExportColumns::codes($all);
    }

    /** @inheritDoc */
    public function begin(array $meta): void
    {
    }

    /** @inheritDoc */
    public function write(array $records): void
    {
        foreach($records as $record){
            $rectypeId = intval($record['rec_RecTypeID'] ?? 0);
            $handle = $this->handle($rectypeId);
            $cells = $this->columns->cells($record, $this->expanded[$rectypeId], $this->request->csv['mvsep']);
            $this->line($handle, $cells);
            $this->rows[$rectypeId] = ($this->rows[$rectypeId] ?? 0) + 1;
        }
    }

    /** @inheritDoc */
    public function end(): void
    {
        foreach($this->handles as $handle){ fclose($handle); }
        $this->handles = array();
    }

    /** @inheritDoc */
    public function files(): array
    {
        return array_values($this->files);
    }

    /**
     * Rows written per record type.
     *
     * @return array<int,int>
     */
    public function rowCounts(): array
    {
        return $this->rows;
    }

    /** @return resource */
    private function handle(int $rectypeId)
    {
        if(isset($this->handles[$rectypeId])){ return $this->handles[$rectypeId]; }
        $columns = $this->request->columnsFor($rectypeId);
        if(empty($columns)){
            $columns = array(array('field' => 'rec_Title', 'ext' => null));
        }
        $this->expanded[$rectypeId] = $this->columns->expand($rectypeId, $columns, true);
        $path = $this->workDir.'rt'.$rectypeId.'.'.$this->extension;
        $handle = fopen($path, 'wb');
        if($handle === false){
            throw new RuntimeException('Cannot create the export file');
        }
        $this->handles[$rectypeId] = $handle;
        $name = $this->definitions->rectypeName($rectypeId);
        $name = trim((string)preg_replace('/[^\p{L}\p{N} _\-()]+/u', '_', $name));
        $this->files[$rectypeId] = array(
            'path' => $path,
            'name' => ($name === '' ? 'record type '.$rectypeId : $name).'.'.$this->extension
        );
        if($this->request->csv['header']){
            $this->line($handle, array_column($this->expanded[$rectypeId], 'header'));
        }
        return $handle;
    }

    /** Write one row: fields are quoted when they contain the separator, the quote or a line break. */
    private function line($handle, array $cells): void
    {
        $sep = $this->request->csv['sep'];
        $quote = $this->request->csv['quote'];
        $out = array();
        foreach($cells as $cell){
            $cell = (string)$cell;
            if($quote === ''){
                // no quoting: separators and line breaks inside a value would break the table
                $out[] = str_replace(array("\r\n", "\r", "\n", $sep), array(' ', ' ', ' ', ' '), $cell);
            }elseif(strpbrk($cell, $sep.$quote."\r\n") !== false || ($cell !== '' && trim($cell) !== $cell)){
                $out[] = $quote.str_replace($quote, $quote.$quote, $cell).$quote;
            }else{
                $out[] = $cell;
            }
        }
        if(fwrite($handle, implode($sep, $out).$this->eol) === false){
            throw new RuntimeException('Cannot write the export file');
        }
    }
}
