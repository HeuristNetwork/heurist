<?php
namespace hserv\session;

use hserv\System;

final class CaptchaService
{
    public function __construct(
        private SessionStore $session,
        private System $system
    ) {}

    public function consumeCaptcha(
        ?string $provided,
        string $sessionKey = 'captcha_code',
        string $missingMessage = 'Captcha is not defined. Please provide correct value',
        string $invalidMessage = 'Are you a bot? Please enter the correct answer to the challenge question'
    ): bool {
        $expected = $this->session->consume($sessionKey);

        if ($provided === null || $provided === '' || $expected === null || $expected === '') {
            $this->system->addError(HEURIST_ACTION_BLOCKED, $missingMessage);
            return false;
        }

        if (!hash_equals((string)$expected, (string)$provided)) {
            $this->system->addError(HEURIST_ACTION_BLOCKED, $invalidMessage);
            return false;
        }

        return true;
    }
}