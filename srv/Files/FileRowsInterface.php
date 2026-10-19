<?php
/**
* FileRowsInterface.php - Where the row of a registered file is kept
*
* The two stores of the /files domain differ only here (plan 20, §8.3): the
* legacy table recUploadedFiles (LegacyFileRows) or the record pair
* fileRecords/fileRecDetails (RecordFileRows). Both take and give the columns of
* recUploadedFiles (FileRepository::COLUMNS + fxm_MimeType), the one internal
* form of FileWriteService.
*
* @project     Heurist academic knowledge management system
* @package     Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Files;

/** Reads and writes of file rows (ulf_* columns). */
interface FileRowsInterface
{
    /** Row of a file, or null. */
    public function row(int $fileId): ?array;

    /** Row of a file by its obfuscated key (rec_Hash), or null. */
    public function rowByKey(string $key): ?array;

    /** Insert a row and return the file id. */
    public function insert(array $columns): int;

    /** Change the given columns of a row (ulf_Modified is set by the caller). */
    public function update(int $fileId, array $columns): void;

    /** Delete a row. */
    public function delete(int $fileId): void;

    /** @return array<int,int> Files registered for this URL. */
    public function idsByUrl(string $url): array;

    /** @return array<int,int> Files with this MD5 checksum. */
    public function idsByChecksum(string $md5): array;

    /** Run a function in one transaction. */
    public function transaction(callable $work);
}
