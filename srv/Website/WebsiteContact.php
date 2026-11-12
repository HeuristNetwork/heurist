<?php
/**
* WebsiteContact.php - Recipient of the email form of a website (plan 27h)
*
* As legacy DbSysBugreport::_prepareEmail: the e-mail field (DT_EMAIL, values split by ";") of the website (or page)
* record, else the e-mail of the database owner (user 2). The website is read without the access rule of the
* visitor: only its title and the address are used, and the address is never sent to the visitor.
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

declare(strict_types=1);

namespace Heurist\Website;

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\ExternalService\EmailForm\EmailFormRecipients;
use Heurist\Records\Store\RecordRepository;
use Heurist\Users\UserGroupRepository;

/** The title and e-mail addresses of a website record. */
final class WebsiteContact implements EmailFormRecipients
{
    /** The database owner (legacy user_getDbOwner). */
    private const OWNER = 2;

    private DatabaseInterface $database;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    public function contact(int $siteId): ?array
    {
        $definitions = DefinitionLookup::shared($this->database);
        // a website, or a standalone page (its email form writes to the database owner)
        $types = array_filter(array($definitions->constant('RT_CMS_HOME'), $definitions->constant('RT_CMS_PAGE')));
        $records = RecordRepository::shared($this->database);
        if(!in_array(intval($records->rectypeOf(array($siteId))[$siteId] ?? 0), $types, true)){ return null; }
        $name = $definitions->constant('DT_NAME');
        $emailField = $definitions->constant('DT_EMAIL');
        $values = $records->values(array($siteId), array_values(array_filter(array($name, $emailField))))[$siteId] ?? array();
        $emails = array();
        foreach($emailField > 0 ? ($values[$emailField] ?? array()) : array() as $value){
            foreach(explode(';', (string)$value) as $address){
                $address = trim($address);
                if(filter_var($address, FILTER_VALIDATE_EMAIL) !== false){ $emails[] = $address; }
            }
        }
        if(empty($emails)){
            $owner = (new UserGroupRepository($this->database))->rows(array(self::OWNER), 'user')[self::OWNER] ?? null;
            $address = trim((string)($owner['ugr_eMail'] ?? ''));
            if(filter_var($address, FILTER_VALIDATE_EMAIL) !== false){ $emails[] = $address; }
        }
        return array('title' => (string)(($name > 0 ? ($values[$name][0] ?? '') : '')), 'emails' => array_values(array_unique($emails)));
    }
}
