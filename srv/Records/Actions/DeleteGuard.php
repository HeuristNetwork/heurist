<?php
/**
* DeleteGuard.php - Protection of the record action "delete" (plan 33, Artem 2026-11-10)
*
*   - at most FREE records: no password;
*   - more: the server password $passwordForServerFunctions (heuristConfigIni.php), checked by the
*     callable given by ServiceFactory (legacy texts of System::verifyActionPassword);
*   - more than FREE records that are SHARE (50 %) or more of the non-temporary records of the
*     database: only the database owner (with the password); others are refused.
*
* @project     Heurist academic knowledge management system
* @package     Records\Actions
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Actions;

use DomainException;

/** Rules of a record delete by the Actions tool. */
final class DeleteGuard
{
    /** Records deleted without the password. */
    public const FREE = 10;

    /** Share of the database that only the database owner may delete. */
    public const SHARE = 0.5;

    /** @var callable fn(string $entered): void, throws DomainException */
    private $checkPassword;

    /**
     * @param callable $checkPassword Checks the server password; throws DomainException.
     */
    public function __construct(callable $checkPassword)
    {
        $this->checkPassword = $checkPassword;
    }

    /**
     * Check a delete.
     *
     * @param int $count Records to delete.
     * @param int $total Non-temporary records of the database.
     * @param bool $isDbOwner The user is the database owner.
     * @param string $password Entered server password.
     * @throws DomainException Refused (403).
     */
    public function check(int $count, int $total, bool $isDbOwner, string $password): void
    {
        if($count <= self::FREE){ return; }
        if($total > 0 && $count >= $total * self::SHARE && !$isDbOwner){
            throw new DomainException('This deletes half or more of the records of the database ('.$count.' of '.$total
                .'). Use Database administration > Clear database, or ask the database owner.');
        }
        call_user_func($this->checkPassword, $password);
    }

    /**
     * The password check of the server password (legacy System::verifyActionPassword).
     *
     * @param string $configured $passwordForServerFunctions of the installation ('' = not set).
     * @return callable fn(string $entered): void
     */
    public static function serverPassword(string $configured): callable
    {
        return static function(string $entered) use ($configured): void {
            if(trim($entered) === ''){
                throw new DomainException('Enter the server password to delete more than '.self::FREE.' records');
            }
            if(strlen($configured) < 6){
                throw new DomainException('This action is not allowed unless a challenge password of sufficient length is set'
                    .' - please consult system administrator');
            }
            if(!hash_equals($configured, $entered)){
                throw new DomainException('Invalid password. If this password is required to access a specific function, it is NOT'
                    .' your login password, it is a special password generally only available to the people who manage the server.');
            }
        };
    }
}
