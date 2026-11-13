<?php
/**
* EmailFormRecipients.php - Who gets the messages of a website's email form (plan 27h)
*
* @project     Heurist academic knowledge management system
* @package     ExternalService
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\ExternalService\EmailForm;

/** The title and the e-mail addresses of a website (Website\WebsiteContact). */
interface EmailFormRecipients
{
    /**
     * @param int $siteId Website record (RT_CMS_HOME) or standalone page (RT_CMS_PAGE).
     * @return array{title:string,emails:array<int,string>}|null Null when it is not a website; `emails`: the e-mail
     *         field of the website, else the database owner (may be empty when neither has one).
     */
    public function contact(int $siteId): ?array;
}
