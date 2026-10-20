<?php
/**
* FileStoreInterface.php - Storage of registered files behind the /files domain
*
* Two implementations (plan 20, §2.1): LegacyFileStore on recUploadedFiles (now)
* and RecordFileStore on fileRecords/fileRecDetails (after the switch at the end
* of the transition). Both take and give the record format of /records.
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

/** Search and retrieval of file records (writing comes in Phase 2). */
interface FileStoreInterface
{
    /**
     * Search: the parameters of /records (query/q, ids, filter, fields, detail=ids,
     * resolveDetails, defs, sort, limit, offset, total).
     *
     * @return array {records, meta, pagination}, or the ids form of detail.
     */
    public function search(array $params): array;

    /**
     * GET|POST /files/aggregate (plan 20 Phase 3): op=count|rectypes with the query
     * parameters of search; FileController has checked op (values, minmax and ranges
     * are not supported for files).
     *
     * @return array count {query, total}; rectypes {query, total, rectypes}.
     */
    public function aggregate(array $params): array;

    /**
     * One file record (fields default to _all): {records:[record], meta}; records is
     * empty when the file does not exist or may not be seen.
     */
    public function item(array $params, int $fileId): array;
}
