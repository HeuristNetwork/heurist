<?php
/**
* CallbackMailTransport.php - E-mails through a callback (plan 27h)
*
* The legacy mailer (sendPHPMailer, PHP mail()) is given by ServiceFactory as a closure, so srv
* does not depend on hserv; tests give their own closure.
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

/** A closure `(MailMessage) => void` that throws when the e-mail is not sent. */
final class CallbackMailTransport implements MailTransport
{
    private \Closure $send;

    /** @param \Closure(MailMessage):void $send */
    public function __construct(\Closure $send)
    {
        $this->send = $send;
    }

    public function send(MailMessage $message): void
    {
        ($this->send)($message);
    }
}
