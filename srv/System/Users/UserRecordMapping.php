<?php
/**
* UserRecordMapping.php - Users (sysUGrps, ugr_Type user) as records of the System user type
*
* Plan 20, Phase 4d (§10.13). The fields are named by DT_* constants (UserGroupCodes);
* a constant without a concept code yet, or a field the database lacks, is left out.
* - the password (DT_PASSWORD) is written only (hashed by the write service), never given;
* - the address (DT_ADDRESS) is City / State / Postcode, one line each;
* - the account status (DT_ACCOUNT_STATUS) is a term whose code (or label) is the ugr_Enabled value;
* - last login and login count are read only.
* The title is the full name with the login name: "Ian Johnson (ijohnson)".
*
* @project     Heurist academic knowledge management system
* @package     System\Users
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Users;

use Heurist\System\Entity\EntityRecordMapping;

/** Converts rows of sysUGrps (users) to user records and request values to columns. */
final class UserRecordMapping extends EntityRecordMapping
{
    /** Field constant => sysUGrps column (ugr_City stands for the three address columns). */
    public const FIELDS = array(
        'DT_LOGIN_NAME' => array('column' => 'ugr_Name'),
        'DT_GIVEN_NAMES' => array('column' => 'ugr_FirstName'),
        'DT_NAME' => array('column' => 'ugr_LastName'),
        'DT_EMAIL' => array('column' => 'ugr_eMail'),
        'DT_PASSWORD' => array('column' => 'ugr_Password'),
        'DT_ACCOUNT_STATUS' => array('column' => 'ugr_Enabled'),
        'DT_ORGANISATION_NAME' => array('column' => 'ugr_Organisation'),
        'DT_DEPARTMENT' => array('column' => 'ugr_Department'),
        'DT_ADDRESS' => array('column' => 'ugr_City'),
        'DT_RESEARCH_INTERESTS' => array('column' => 'ugr_Interests'),
        'DT_URL' => array('column' => 'ugr_URLs'),
        'DT_ORCID' => array('column' => 'ugr_ORCID'),
        'DT_LAST_LOGIN' => array('column' => 'ugr_LastLoginTime'),
        'DT_LOGIN_COUNT' => array('column' => 'ugr_LoginCount')
    );

    /** Fields a client may write (the status: administrators only, checked by the write service). */
    public const WRITABLE_FIELDS = array(
        'DT_LOGIN_NAME', 'DT_GIVEN_NAMES', 'DT_NAME', 'DT_EMAIL', 'DT_PASSWORD', 'DT_ACCOUNT_STATUS',
        'DT_ORGANISATION_NAME', 'DT_DEPARTMENT', 'DT_ADDRESS', 'DT_RESEARCH_INTERESTS', 'DT_URL', 'DT_ORCID'
    );

    /** Values of ugr_Enabled. */
    public const STATUSES = array('y', 'n', 'y_no_add', 'y_no_delete', 'y_no_add_delete');

    /** Columns of the address lines and their lengths. */
    public const ADDRESS_COLUMNS = array('ugr_City' => 63, 'ugr_State' => 40, 'ugr_Postcode' => 20);

    /** {@inheritdoc} */
    public function rectypeConcept(): string
    {
        return UserGroupCodes::RECORD_TYPES['RT_SYS_USER'];
    }

    /** {@inheritdoc} */
    protected function fieldMappings(): array
    {
        return self::FIELDS;
    }

    /** {@inheritdoc} */
    protected function writableConcepts(): array
    {
        return self::WRITABLE_FIELDS;
    }

    /** {@inheritdoc} The fields are DT_* constants. */
    protected function fieldLocalId(string $key): int
    {
        $concept = UserGroupCodes::concept($key);
        return $concept === '' ? 0 : $this->definitions->localId('dty', $concept);
    }

    /**
     * Record of a UserGroupRepository row.
     *
     * @return array{headers:array<string,?string>,details:array<int,array<int,string>>}
     */
    public function toRecord(array $row): array
    {
        return array(
            'headers' => array(
                'rec_ID' => (string)intval($row['ugr_ID']),
                'rec_RecTypeID' => (string)$this->rectypeId(),
                'rec_Title' => self::title($row),
                'rec_Modified' => isset($row['ugr_Modified']) ? (string)$row['ugr_Modified'] : null
            ),
            'details' => $this->detailsOf($row)
        );
    }

    /** "Ian Johnson (ijohnson)"; the login name alone when there is no name. */
    public static function title(array $row): string
    {
        $login = trim((string)($row['ugr_Name'] ?? ''));
        $name = trim(trim((string)($row['ugr_FirstName'] ?? '')).' '.trim((string)($row['ugr_LastName'] ?? '')));
        return $name === '' || strcasecmp($name, $login) === 0 ? $login : $name.($login !== '' ? ' ('.$login.')' : '');
    }

    /**
     * Columns of the request values: the writable fields as the base gives them, with the
     * address split into its lines and the status as its ugr_Enabled value.
     *
     * @param array<int,array<int,mixed>> $details field id => values as given
     * @return array<string,?string>
     */
    public function columnsOfRequest(array $details): array
    {
        $columns = $this->writableColumns($details);
        if(array_key_exists('ugr_City', $columns)){
            $lines = preg_split('/\r\n|\r|\n/', (string)$columns['ugr_City']);
            foreach(array_keys(self::ADDRESS_COLUMNS) as $index => $column){
                $line = trim((string)($lines[$index] ?? ''));
                $columns[$column] = $line === '' ? null : $line;
            }
        }
        if(array_key_exists('ugr_Enabled', $columns) && $columns['ugr_Enabled'] !== null){
            $columns['ugr_Enabled'] = $this->statusOf((string)$columns['ugr_Enabled']);
        }
        return $columns;
    }

    /** ugr_Enabled of a field value: a term of the status vocabulary, or the value itself ("y_no_add"). */
    public function statusOf(string $value): ?string
    {
        $value = strtolower(trim($value));
        if(in_array($value, self::STATUSES, true)){ return $value; }
        $fieldId = $this->fieldId('DT_ACCOUNT_STATUS');
        if($fieldId < 1 || !ctype_digit($value)){ return null; }
        foreach(self::STATUSES as $status){
            if($this->statusTerm($fieldId, $status) === $value){ return $status; }
        }
        return null;
    }

    /** {@inheritdoc} No password; the address lines; the status as its term; no empty login statistics. */
    protected function fieldValue(int $fieldId, array $mapping, array $row): ?string
    {
        switch($mapping['column']){
            case 'ugr_Password':
                return null;
            case 'ugr_City':
                $lines = array();
                foreach(array_keys(self::ADDRESS_COLUMNS) as $column){ $lines[] = trim((string)($row[$column] ?? '')); }
                $text = rtrim(implode("\n", $lines));
                return trim($text) === '' ? null : $text;
            case 'ugr_Enabled':
                $value = (string)($row['ugr_Enabled'] ?? '');
                return $value === '' ? null : ($this->statusTerm($fieldId, $value) ?? $value);
            case 'ugr_LoginCount':
                return intval($row['ugr_LoginCount'] ?? 0) > 0 ? (string)intval($row['ugr_LoginCount']) : null;
        }
        return parent::fieldValue($fieldId, $mapping, $row);
    }

    /** Term id of a status: the term whose code, else label, is the ugr_Enabled value. */
    private function statusTerm(int $fieldId, string $status): ?string
    {
        $vocabulary = $this->definitions->field($fieldId)['vocabulary'] ?? array();
        if(empty($vocabulary)){ return null; }
        $byLabel = null;
        foreach($this->definitions->terms($this->definitions->termDescendants($vocabulary)) as $id => $term){
            if(in_array($id, $vocabulary, true)){ continue; }
            if(strtolower(trim((string)($term['code'] ?? ''))) === $status){ return (string)$id; }
            if($byLabel === null && strtolower(trim((string)$term['term'])) === $status){ $byLabel = (string)$id; }
        }
        return $byLabel;
    }
}
