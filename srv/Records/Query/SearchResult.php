<?php
/**
* SearchResult.php - Paginated record search result
*
* Carries ordered record IDs and total count between the query service and
* presentation/controller layers.
*
* @project     Heurist academic knowledge management system
* @package     Records\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     This software may be installed and operated only on servers operated by Heurist Network, or with the prior written permission of Heurist Network. No right is granted to copy, distribute, modify, install or operate this software elsewhere.
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);
namespace Heurist\Records\Query;

/** Immutable IDs/count result for one top-query page. */
final class SearchResult
{
    public array $ids;
    public int $total;
    public int $offset;
    public int $limit;
    public ?string $resultToken;
    /** @var array<int,array{rec_RecTypeID:int,count:int}>|null */
    public ?array $rectypes;

    /** Initialise a normalized result page. */
    public function __construct(array $ids, int $total, int $offset, int $limit, ?string $resultToken = null, ?array $rectypes = null)
    {
        $this->ids = array_values(array_map('intval', $ids));
        $this->total = $total;
        $this->offset = max(0, $offset);
        $this->limit = max(1, $limit);
        $this->resultToken = $resultToken;
        $this->rectypes = $rectypes;
    }

    /** Return the stable controller representation. */
    public function toArray(): array
    {
        $result = array('ids'=>$this->ids, 'total'=>$this->total, 'offset'=>$this->offset,
            'limit'=>$this->limit, 'resultToken'=>$this->resultToken);
        if($this->rectypes !== null){ $result['rectypes'] = $this->rectypes; }
        return $result;
    }
}
