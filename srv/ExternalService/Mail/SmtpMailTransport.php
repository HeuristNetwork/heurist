<?php
/**
* SmtpMailTransport.php - E-mails through an SMTP relay (plan 27h)
*
* Configured by `$mailSmtp` of heuristConfigIni.php:
*   $mailSmtp = array('host' => 'smtp.example.org', 'port' => 587, 'user' => '...', 'password' => '...',
*                     'secure' => 'tls', 'from' => 'no-reply@example.org', 'fromName' => 'Heurist');
* `secure`: tls (STARTTLS), ssl (SMTPS) or '' (none); without `user` no authentication. Any provider with
* SMTP works (Gmail, Office 365, Brevo, Mailjet, SendGrid ...).
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

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/** PHPMailer over SMTP. */
final class SmtpMailTransport implements MailTransport
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config `$mailSmtp`: host, port, user, password, secure, from, fromName. */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(MailMessage $message): void
    {
        $from = trim((string)($this->config['from'] ?? ''));
        if(filter_var($from, FILTER_VALIDATE_EMAIL) === false){
            throw new \RuntimeException('The mail relay is not configured: the sender address ($mailSmtp from) is missing');
        }
        $mail = new PHPMailer(true);
        try{
            $mail->isSMTP();
            $mail->Host = (string)$this->config['host'];
            $mail->Port = intval($this->config['port'] ?? 587) ?: 587;
            $secure = strtolower((string)($this->config['secure'] ?? 'tls'));
            if($secure === 'ssl'){
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }elseif($secure === 'tls'){
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }else{
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $user = (string)($this->config['user'] ?? '');
            $mail->SMTPAuth = $user !== '';
            if($user !== ''){
                $mail->Username = $user;
                $mail->Password = (string)($this->config['password'] ?? '');
            }
            $mail->Timeout = 15;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->setFrom($from, (string)($this->config['fromName'] ?? 'Heurist'));
            if($message->replyTo !== ''){ $mail->addReplyTo($message->replyTo, $message->replyToName); }
            foreach($message->to as $address){ $mail->addAddress($address); }
            $mail->isHTML(true);
            $mail->Subject = $message->subject;
            $mail->Body = $message->html;
            $mail->AltBody = html_entity_decode(strip_tags(str_replace(array('<br>', '<br/>', '</p>'), "\n", $message->html)), ENT_QUOTES, 'UTF-8');
            $mail->send();
        }catch(MailerException $e){
            throw new \RuntimeException('The e-mail was not sent: '.$mail->ErrorInfo);
        }
    }
}
