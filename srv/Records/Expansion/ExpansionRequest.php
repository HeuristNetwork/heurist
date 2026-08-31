<?php
/**
* ExpansionRequest.php - Linked-record expansion request
*
* @project Heurist academic knowledge management system
* @package Records\Expansion
* @link https://HeuristNetwork.org
* @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author Artem Osmakov <osmakov@gmail.com>
* @author Ian Johnson <ian.johnson.heurist@gmail.com>
* @since 7.0
*/
declare(strict_types=1);
namespace Heurist\Records\Expansion;

/** Immutable request for expanding an already paginated top-record set. */
final class ExpansionRequest
{
    public array $seedIds;
    public $rules;
    public int $batchSize;
    public bool $includeHeaders;

    /** Initialise and constrain expansion seeds and execution options. */
    public function __construct(array $seedIds, $rules, array $options = array())
    {
        $this->seedIds=array_values(array_unique(array_filter(array_map('intval',$seedIds))));
        $this->rules=$rules;
        $this->batchSize=min(5000,max(1,intval($options['batchSize']??500)));
        $this->includeHeaders=!empty($options['includeHeaders']);
    }
}
