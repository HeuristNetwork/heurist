<?php
/**
* ExportWriterInterface.php - One record export format
*
* ExportService loads the records in batches (RecordPageAssembler, with the field
* codes of the writer) and passes each batch to write(). A writer writes into
* its own files in the work folder; files() lists them when end() has run.
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

/** Streaming writer of one export format. */
interface ExportWriterInterface
{
    /**
     * Field codes loaded for every record (RecordFieldSelector input, e.g. "_all").
     *
     * @return string[]
     */
    public function fieldCodes(): array;

    /**
     * Start the output.
     *
     * @param array $meta database, title, total (records to write).
     */
    public function begin(array $meta): void;

    /**
     * Write one batch of record objects (in export order).
     *
     * @param array<int,array> $records Records of RecordPageAssembler.
     */
    public function write(array $records): void;

    /** Finish and close the output. */
    public function end(): void;

    /**
     * Written files.
     *
     * @return array<int,array{path:string,name:string}> Path in the work folder and the name in the download.
     */
    public function files(): array;
}
