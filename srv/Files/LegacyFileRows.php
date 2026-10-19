<?php
/**
* LegacyFileRows.php - File rows in recUploadedFiles
*
* Reads through FileRepository, writes through LegacyFileTable (plan 20).
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

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Files\LegacyFileTable;

/** File rows of the legacy table. */
final class LegacyFileRows implements FileRowsInterface
{
    private WritableDatabaseInterface $database;
    private FileRepository $files;
    private LegacyFileTable $table;

    public function __construct(WritableDatabaseInterface $database)
    {
        $this->database = $database;
        $this->files = new FileRepository($database); // not shared: rows change during the request
        $this->table = new LegacyFileTable($database);
    }

    public function row(int $fileId): ?array
    {
        return $this->files->rows(array($fileId))[$fileId] ?? null;
    }

    public function rowByKey(string $key): ?array
    {
        $found = $this->files->filesByObfuscatedId(array($key));
        $id = intval(reset($found)['ulf_ID'] ?? 0);
        return $id > 0 ? $this->row($id) : null;
    }

    public function insert(array $columns): int
    {
        return $this->table->insert($columns);
    }

    public function update(int $fileId, array $columns): void
    {
        $this->table->update($fileId, $columns);
    }

    public function delete(int $fileId): void
    {
        $this->table->delete($fileId);
    }

    public function idsByUrl(string $url): array
    {
        return $this->files->idsByUrl($url);
    }

    public function idsByChecksum(string $md5): array
    {
        return $this->files->idsByChecksum($md5);
    }

    public function transaction(callable $work)
    {
        return FileTransaction::run($this->database, $work);
    }
}
