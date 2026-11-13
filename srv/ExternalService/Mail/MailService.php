<?php
/**
* MailService.php - The mail transport of the installation (plan 27h)
*
* An SMTP relay when `$mailSmtp['host']` is set in heuristConfigIni.php (SmtpMailTransport),
* otherwise the legacy mailer (sendPHPMailer, PHP mail()).
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

/** Chooses the transport. */
final class MailService
{
    /**
     * @param array<string,mixed> $smtp `$mailSmtp` of heuristConfigIni.php (empty: not configured).
     * @param \Closure(MailMessage):void|null $legacy The legacy mailer.
     * @throws \RuntimeException When there is no way to send.
     */
    public static function transport(array $smtp, ?\Closure $legacy): MailTransport
    {
        if(trim((string)($smtp['host'] ?? '')) !== ''){
            return new SmtpMailTransport($smtp);
        }
        if($legacy === null){
            throw new \RuntimeException('Mailing is not configured on this server');
        }
        return new CallbackMailTransport($legacy);
    }
}
