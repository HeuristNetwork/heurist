<?php
/**
* EmailFormSession.php - Session values of the email form (plan 27h)
*
* The answer of the captcha question (session key `captcha_code`, as the legacy captcha.php and
* CaptchaService) and the times of the messages sent in this session (rate limit). Implemented by
* ServiceFactory over the legacy session; tests give an array.
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

/** Captcha answer and sent times of the visitor's session. */
interface EmailFormSession
{
    /** Keep the answer of the question just given. */
    public function storeAnswer(string $answer): void;

    /** The kept answer, removed (one try per question); null when there is none. */
    public function takeAnswer(): ?string;

    /** @return array<int,int> Unix times of the messages sent in this session. */
    public function sentTimes(): array;

    /** @param array<int,int> $times Unix times of the messages sent in this session. */
    public function setSentTimes(array $times): void;
}
