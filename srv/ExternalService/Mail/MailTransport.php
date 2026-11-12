<?php
/**
* MailTransport.php - The way an e-mail leaves the server (plan 27h)
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

namespace Heurist\ExternalService\Mail;

/** Sends an e-mail: an SMTP relay or the legacy mailer. */
interface MailTransport
{
    /**
     * @throws \RuntimeException When the e-mail was not sent (the message says why).
     */
    public function send(MailMessage $message): void;
}
