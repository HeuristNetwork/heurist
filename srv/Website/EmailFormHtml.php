<?php
/**
* EmailFormHtml.php - HTML of the email form widget of a website (plan 27h)
*
* The form is written by the server (no captcha in the HTML: pages may be cached); the site runtime
* (HEmailForm.js) asks for the captcha question and sends the message to /external/email. The same
* markup is made by HEmailForm for an empty host (the designer's draft). Settings: `display` inline
* (default) or button (a button that opens the form in a dialog), `label` / `labels` {lang: caption}
* of the button. Fixed words in English and French (as TemporalLocale, plan 31).
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

/** Writes the email form. */
final class EmailFormHtml
{
    /** Words of the form by language (keys = the English words, as $HR in HEmailForm.js). */
    private const WORDS = array(
        'fre' => array(
            'Your name' => 'Votre nom',
            'Email address' => 'Adresse e-mail',
            'Message' => 'Message',
            'Prove you are human:' => 'Prouvez que vous êtes humain :',
            'Send' => 'Envoyer',
            'Email us' => 'Nous écrire',
            'Close' => 'Fermer',
            'JavaScript is needed to send this form.' => 'JavaScript est nécessaire pour envoyer ce formulaire.'
        )
    );

    /**
     * @param array<string,mixed> $settings display, label, labels.
     * @param int $siteId The website (or the standalone page) whose owner gets the messages.
     * @param string $language Three-letter code of the page language.
     */
    public static function render(array $settings, int $siteId, string $language): string
    {
        $language = strtolower($language) === 'fra' ? 'fre' : strtolower($language);
        $word = static function(string $text) use ($language): string {
            return htmlspecialchars(self::WORDS[$language][$text] ?? $text, ENT_QUOTES, 'UTF-8');
        };
        $form = '<form class="h-site-email-form" data-site="'.$siteId.'" novalidate>'
            .'<label class="h-site-email-field"><span>'.$word('Your name').'</span>'
            .'<input type="text" name="name" required maxlength="100" autocomplete="name"></label>'
            .'<label class="h-site-email-field"><span>'.$word('Email address').'</span>'
            .'<input type="email" name="email" required maxlength="254" autocomplete="email"></label>'
            .'<label class="h-site-email-field"><span>'.$word('Message').'</span>'
            .'<textarea name="message" required maxlength="5000" rows="6"></textarea></label>'
            .'<label class="h-site-email-field h-site-email-captcha"><span>'.$word('Prove you are human:')
            .' <span class="h-site-email-question"></span></span>'
            .'<input type="text" name="captcha" required maxlength="16" inputmode="numeric" autocomplete="off"></label>'
            .'<div class="h-site-email-actions"><button type="submit" class="h-site-email-send">'.$word('Send').'</button></div>'
            .'<p class="h-site-email-status" role="status" aria-live="polite"></p>'
            .'<noscript><p>'.$word('JavaScript is needed to send this form.').'</p></noscript>'
            .'</form>';
        if(($settings['display'] ?? 'inline') !== 'button'){ return $form; }
        $labels = is_array($settings['labels'] ?? null) ? $settings['labels'] : array();
        $caption = trim((string)($labels[$language] ?? $settings['label'] ?? ''));
        $caption = $caption !== '' ? htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') : $word('Email us');
        return '<button type="button" class="h-site-email-open">'.$caption.'</button>'
            .'<dialog class="h-site-email-dialog" aria-label="'.$caption.'"><div class="h-site-email-dialog-head"><span>'.$caption.'</span>'
            .'<button type="button" class="h-site-email-close" aria-label="'.$word('Close').'">&times;</button></div>'.$form.'</dialog>';
    }
}
