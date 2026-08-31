<?php
/**
* ConceptCode.php - Definition concept-code resolver
*
* Resolves local term IDs and stable term concept codes directly through the
* database abstraction without legacy static System state.
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Runtime;

use Heurist\Database\DatabaseInterface;

/** Resolves term identifiers in the current definition schema. */
final class ConceptCode
{
    private DatabaseInterface $database;

    /** Initialise concept-code lookup. */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /** Return the stable OriginatingDBID-ID code for a local term. */
    public function getTermConceptId(int $termId): ?string
    {
        $row = $this->database->fetchRows(
            'SELECT trm_OriginatingDBID,trm_IDInOriginatingDB FROM defTerms WHERE trm_ID=? LIMIT 1',
            array($termId)
        );
        return empty($row) || !is_numeric($row[0][0]) || !is_numeric($row[0][1])
            ? null : intval($row[0][0]).'-'.intval($row[0][1]);
    }

    /** Return the local term ID represented by an OriginatingDBID-ID code. */
    public function getTermLocalId(string $conceptCode): int
    {
        if(!preg_match('/^(\d+)-(\d+)$/', $conceptCode, $matches)){ return 0; }
        return intval($this->database->fetchValue(
            'SELECT trm_ID FROM defTerms WHERE trm_OriginatingDBID=? '
            .'AND trm_IDInOriginatingDB=? LIMIT 1',
            array(intval($matches[1]), intval($matches[2])), 0
        ));
    }
}
