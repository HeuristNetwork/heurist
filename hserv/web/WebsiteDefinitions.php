<?php
/**
* WebsiteDefinitions.php - Installs the definitions of the website format v4 (plan 27a)
*
* Implements Heurist\Website\WebsiteDefinitionsInterface with the legacy
* definition code (srv/ does not write definitions): missing fields are
* imported from the Heurist reference database (2) with DbsImport, as the
* reports setup does; fields missing in a record structure are added at its
* end with DbDefRecStructure (optional, one value; translations are counted
* per language by the record validator).
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

namespace hserv\web;

use Heurist\Website\WebsiteDefinitionsInterface;
use hserv\entity\DbDefRecStructure;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../structure/import/dbsImport.php';

/** Legacy writer of the website v4 definitions. */
final class WebsiteDefinitions implements WebsiteDefinitionsInterface
{
    /** Heurist reference database the definitions come from. */
    private const SOURCE_DATABASE_ID = 2;
    /** Display order of added fields: after the existing ones. */
    private const ORDER = 9000;

    private $system;

    /** @param \hserv\System $system Initialized system of a database manager. */
    public function __construct($system)
    {
        $this->system = $system;
    }

    public function install(array $fieldCodes, array $rectypeCodes): array
    {
        $report = array();
        ConceptCode::setSystem($this->system);

        $missing = array();
        foreach ($fieldCodes as $code) {
            if (!(intval(ConceptCode::getDetailTypeLocalID($code)) > 0)) {
                list($origin, $id) = array_map('intval', explode('-', $code));
                if ($origin !== self::SOURCE_DATABASE_ID) {
                    throw new \DomainException('Field '.$code.' is missing and can not be imported');
                }
                $missing[] = $id;
            }
        }
        if (!empty($missing)) {
            $import = new \DbsImport($this->system);
            $ok = $import->doPrepare(array(
                'defType' => 'detailtype',
                'databaseID' => self::SOURCE_DATABASE_ID,
                'definitionID' => $missing
            ));
            if ($ok) {
                $ok = $import->doImport();
            }
            if ($ok) {
                ConceptCode::setSystem($this->system);
                $report[] = 'Imported fields 2-'.implode(', 2-', $missing);
            } else {
                // the other fields are still added; the missing one is reported
                $error = $this->system->getError();
                $report[] = 'Cannot import the fields 2-'.implode(', 2-', $missing).' from database 2'
                    .(is_array($error) && !empty($error['message']) ? ': '.strip_tags((string)$error['message']) : '');
                $this->system->clearError();
            }
        }

        $mysqli = $this->system->getMysqli();
        foreach ($rectypeCodes as $rectypeCode) {
            $rectypeId = intval(ConceptCode::getRecTypeLocalID($rectypeCode));
            if ($rectypeId < 1) {
                $report[] = 'Record type '.$rectypeCode.' is not defined in this database';
                continue;
            }
            $existing = array_map('intval', mysql__select_list2($mysqli,
                'SELECT rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID='.$rectypeId));
            $add = array();
            foreach ($fieldCodes as $code) {
                $fieldId = intval(ConceptCode::getDetailTypeLocalID($code));
                if ($fieldId > 0 && !in_array($fieldId, $existing, true)) {
                    $add[] = $fieldId;
                }
            }
            if (empty($add)) {
                $report[] = 'Record type '.$rectypeCode.' already has the website v4 fields';
                continue;
            }
            $structure = new DbDefRecStructure($this->system, array(
                'entity' => 'defRecStructure',
                'rtyID' => $rectypeId,
                'order' => self::ORDER,
                'newfields' => array('fields' => $add)
            ));
            if ($structure->batch_action() === false) {
                $error = $this->system->getError();
                throw new \RuntimeException('Cannot add fields to record type '.$rectypeCode
                    .(is_array($error) && !empty($error['message']) ? ': '.strip_tags((string)$error['message']) : ''));
            }
            // DbDefRecStructure adds fields as "recommended"; these are filled by the website editor
            $mysqli->query('UPDATE defRecStructure SET rst_RequirementType=\'optional\' WHERE rst_RecTypeID='.$rectypeId
                .' AND rst_DetailTypeID IN ('.implode(',', array_map('intval', $add)).')');
            $report[] = 'Added '.count($add).' field(s) to record type '.$rectypeCode;
        }
        return $report;
    }

    public function makeOptional(array $fields): array
    {
        $report = array();
        ConceptCode::setSystem($this->system);
        $mysqli = $this->system->getMysqli();
        foreach ($fields as $rectypeCode => $fieldCodes) {
            $rectypeId = intval(ConceptCode::getRecTypeLocalID($rectypeCode));
            if ($rectypeId < 1) {
                continue;
            }
            foreach ($fieldCodes as $code) {
                $fieldId = intval(ConceptCode::getDetailTypeLocalID($code));
                if ($fieldId < 1) {
                    continue;
                }
                $mysqli->query('UPDATE defRecStructure SET rst_RequirementType=\'optional\' WHERE rst_RecTypeID='.$rectypeId
                    .' AND rst_DetailTypeID='.$fieldId.' AND rst_RequirementType=\'required\'');
                if ($mysqli->affected_rows > 0) {
                    $report[] = 'Field '.$code.' of record type '.$rectypeCode.' is now optional';
                }
            }
        }
        return $report;
    }
}
