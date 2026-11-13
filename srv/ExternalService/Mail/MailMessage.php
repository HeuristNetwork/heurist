<?php
/**
* MailMessage.php - One e-mail to send (plan 27h)
*
* The recipients, the subject, an HTML body and the reply-to address. The sender is the
* server (the transport decides the From address), so a reply goes to `replyTo`.
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

/** An e-mail: recipients, subject, HTML body, reply-to. */
final class MailMessage
{
    /** @var array<int,string> */
    public array $to;
    public string $subject;
    public string $html;
    public string $replyTo;
    public string $replyToName;

    /**
     * @param array<int,string> $to Valid e-mail addresses.
     * @param string $subject One line.
     * @param string $html Body (escaped by the caller).
     * @param string $replyTo E-mail address of a reply ('' none).
     * @param string $replyToName Name of the reply address.
     */
    public function __construct(array $to, string $subject, string $html, string $replyTo = '', string $replyToName = '')
    {
        $this->to = array_values($to);
        $this->subject = $subject;
        $this->html = $html;
        $this->replyTo = $replyTo;
        $this->replyToName = $replyToName;
    }
}
