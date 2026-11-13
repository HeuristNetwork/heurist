<?php
/**
* EmailFormService.php - The email form of a website (plan 27h, port of the legacy emailForm widget)
*
* GET /external/email: a simple captcha question ("4 + 7 + 1 =", as the legacy captcha.php); the answer
* is kept in the visitor's session. POST /external/email {site, name, email, message, captcha, url}: the
* fields are checked, the answer is used once, at most RATE_LIMIT messages per session and hour, and the
* message goes to the e-mail of the website (or the database owner) with Reply-To = the visitor.
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

use Heurist\ExternalService\Mail\MailMessage;
use Heurist\ExternalService\Mail\MailTransport;

/**
 * Question and send. Errors: \InvalidArgumentException (400, a field), \RuntimeException with code 403 (captcha),
 * 404 (not a website), 429 (too many messages) or 502 (not sent).
 */
final class EmailFormService
{
    /** Messages per session and hour. */
    public const RATE_LIMIT = 5;
    public const MAX_NAME = 100;
    public const MAX_MESSAGE = 5000;

    private EmailFormSession $session;
    private EmailFormRecipients $recipients;
    /** @var \Closure():MailTransport Made only when a message is sent. */
    private \Closure $transport;
    /** @var \Closure():int */
    private \Closure $clock;

    /**
     * @param \Closure():MailTransport $transport
     * @param \Closure():int|null $clock Unix time (tests).
     */
    public function __construct(EmailFormSession $session, EmailFormRecipients $recipients, \Closure $transport, ?\Closure $clock = null)
    {
        $this->session = $session;
        $this->recipients = $recipients;
        $this->transport = $transport;
        $this->clock = $clock ?? static function(): int { return time(); };
    }

    /** A new question; its answer is kept in the session (the previous one is replaced). */
    public function question(): string
    {
        $first = random_int(1, 9);
        $second = random_int(1, 9);
        $this->session->storeAnswer((string)($first + $second + 1));
        return $first.' + '.$second.' + 1 =';
    }

    /**
     * Check and send a message.
     *
     * @param array<string,mixed> $body site, name, email, message, captcha, url (the page, optional).
     * @throws \InvalidArgumentException|\RuntimeException See the class.
     */
    public function send(array $body): void
    {
        $now = ($this->clock)();
        $recent = array_values(array_filter($this->session->sentTimes(), static function($time) use ($now): bool {
            return is_int($time) && $time > $now - 3600;
        }));
        if(count($recent) >= self::RATE_LIMIT){
            throw new \RuntimeException('Too many messages: please try again later', 429);
        }
        foreach(array('name', 'email', 'message', 'captcha', 'url') as $key){
            if(array_key_exists($key, $body) && $body[$key] !== null && !is_string($body[$key]) && !is_int($body[$key])){
                throw new \InvalidArgumentException('Parameter '.$key.' must be a string');
            }
        }
        $site = filter_var($body['site'] ?? null, FILTER_VALIDATE_INT);
        if($site === false || $site < 1){ throw new \InvalidArgumentException('The website (site) is not defined'); }
        $name = trim((string)($body['name'] ?? ''));
        if($name === '' || preg_match('/[\r\n\0]/', $name) === 1 || mb_strlen($name) > self::MAX_NAME){
            throw new \InvalidArgumentException('Your name is required (one line, at most '.self::MAX_NAME.' characters)');
        }
        $email = trim((string)($body['email'] ?? ''));
        if(filter_var($email, FILTER_VALIDATE_EMAIL) === false){
            throw new \InvalidArgumentException('Email does not appear to be valid');
        }
        $message = trim(str_replace("\r\n", "\n", (string)($body['message'] ?? '')));
        if($message === '' || preg_match('//u', $message) !== 1 || mb_strlen($message) > self::MAX_MESSAGE){
            throw new \InvalidArgumentException('A message of 1 to '.self::MAX_MESSAGE.' characters is required');
        }
        // the answer is used once (as legacy CaptchaService::consumeCaptcha), spaces removed
        $given = preg_replace('/\s+/', '', (string)($body['captcha'] ?? ''));
        $expected = $this->session->takeAnswer();
        if($expected === null || $expected === '' || $given === '' || !hash_equals($expected, $given)){
            throw new \RuntimeException('Are you a bot? Please enter the correct answer to the challenge question', 403);
        }
        $contact = $this->recipients->contact($site);
        if($contact === null){ throw new \RuntimeException('Not a website', 404); }
        if(empty($contact['emails'])){ throw new \RuntimeException('This website has no e-mail address to send to', 502); }
        $url = trim((string)($body['url'] ?? ''));
        if(preg_match('#^https?://#i', $url) !== 1 || strlen($url) > 500){ $url = ''; }
        // a title of a legacy website may have HTML: the text only
        $title = trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', $contact['title'])), ENT_QUOTES, 'UTF-8'));
        $title = $title !== '' ? $title : '#'.$site;
        $subject = 'Website "'.self::oneLine($title).'": message from '.$name;
        $html = '<p>'.self::html($message).'</p><hr>'
            .'<p>From '.self::html($name).' &lt;'.self::html($email).'&gt;<br>'
            .'Email form of the website '.self::html($title).($url !== '' ? ' (<a href="'.self::html($url).'">'.self::html($url).'</a>)' : '').'</p>';
        try{
            ($this->transport)()->send(new MailMessage($contact['emails'], $subject, $html, $email, $name));
        }catch(\RuntimeException $e){
            throw new \RuntimeException($e->getMessage() !== '' ? $e->getMessage() : 'The e-mail was not sent', 502);
        }
        $recent[] = $now;
        $this->session->setSentTimes($recent);
    }

    /** Escaped text with its line breaks. */
    private static function html(string $text): string
    {
        return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }

    private static function oneLine(string $text): string
    {
        return trim((string)preg_replace('/[\r\n\0]+/', ' ', $text));
    }
}
