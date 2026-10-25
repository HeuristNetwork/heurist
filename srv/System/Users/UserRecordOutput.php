<?php
/**
* UserRecordOutput.php - User records of the /sys domain
*
* Plan 20, Phase 4d (§10.13): /sys/user in the record format. Logged-in users see the
* users (as legacy); guests none. The password is never given.
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

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityRecordOutput;
use Heurist\Users\UserGroupRepository;

/** User records of the /sys domain. */
final class UserRecordOutput extends EntityRecordOutput
{
    /** Headers of a user record (in this order). */
    public const HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_Title', 'rec_Modified');

    private UserRecordMapping $mapping;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        parent::__construct($database, $runtime);
        $this->mapping = new UserRecordMapping(DefinitionLookup::shared($database));
    }

    /** {@inheritdoc} */
    public function mapping(): UserRecordMapping
    {
        return $this->mapping;
    }

    /** {@inheritdoc} */
    protected function entityImageType(): ?string
    {
        return 'user';
    }

    /** {@inheritdoc} */
    protected function headers(): array
    {
        return self::HEADERS;
    }

    /** {@inheritdoc} Logged-in users only (legacy gives guests no names or e-mails). */
    protected function rows(array $ids): array
    {
        if($this->runtime->userId < 1){ return array(); }
        return UserGroupRepository::shared($this->database)->rows($ids, 'user');
    }

    /** {@inheritdoc} */
    protected function toRecord(array $row): array
    {
        return $this->mapping->toRecord($row);
    }

    /** {@inheritdoc} A user is its own owner. */
    protected function ownerColumn(): string
    {
        return 'ugr_ID';
    }

    /** {@inheritdoc} The former keyword "email". */
    protected function keywords(): array
    {
        $email = $this->mapping->fieldId('DT_EMAIL');
        return array('email' => $email > 0 ? (string)$email : '');
    }
}
