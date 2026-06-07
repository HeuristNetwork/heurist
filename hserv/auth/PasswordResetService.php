<?php
namespace hserv\auth;

use hserv\System;

final class PasswordResetService
{
    private const BLOCK_TTL_SECONDS = 3600;
    private const PIN_TTL_SECONDS = 300;
    private const VERY_OLD_PIN_SECONDS = 3600;
    private const MAX_RESENDS = 5;
    private const MAX_BLOCKS = 3;

    private System $system;

    public function __construct(System $system)
    {
        $this->system = $system;
    }

    /**
     * Handles password reset PIN creation, resend, and validation.
     * Keeps the legacy return contract used by user_HandleResetPin():
     * true for a validated PIN, string for resend/new-pin messages, false on error.
     */
    public function handlePin(string $username, string|int $pin = '', string $captcha = '') //: bool|string
    {
        $mysqli = $this->system->getMysqli();
        $now = time();

        if ($pin == 1) {
            $pin = '';
        }
        $pin = (string)$pin;

        $user = \user_getByField($mysqli, 'ugr_Name', $username);
        if ($user === null) {
            $user = \user_getByField($mysqli, 'ugr_eMail', $username);
        }
        if ($user === null) {
            $this->system->addError(HEURIST_NOT_FOUND, 'Unable to find provided username / email');
            return false;
        }

        $userId = (int)$user['ugr_ID'];
        $userSession = $this->system->userSession();
        $userSession->resetPasswordBlockStateIfExpired($now, self::BLOCK_TTL_SECONDS);

        $pins = $userSession->getResetPins();
        if ((int)($pins['blocked'] ?? 0) >= self::MAX_BLOCKS) {
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'We are unable to send a reset pin at this time.<br>Please try again later');
            return false;
        }

        $pinState = $userSession->getResetPinForUser($userId) ?? $this->emptyPinState();
        $checkPin = $pin !== '' && !empty($pinState['pin']);

        if ($checkPin && (int)($pinState['expire'] ?? 0) > $now) {
            if (!\passwordCheck($pin, (string)$pinState['pin'])) {
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Invalid pin provided');
                return false;
            }

            $userSession->markResetPinRedeemed($userId);
            return true;
        }

        $newPin = \passwordGenerate();
        $hasPin = !empty($pinState['pin']);
        $response = true;
        $testCaptcha = true;
        $resends = max(1, (int)($pinState['resends'] ?? 1));

        if ($hasPin) {
            if ($now > ((int)($pinState['expire'] ?? 0) + self::VERY_OLD_PIN_SECONDS)) {
                $resends = 1;
            } else {
                $testCaptcha = false;
                $resends++;
                $expired = (int)($pinState['expire'] ?? 0) < $now;

                if ($resends > self::MAX_RESENDS) {
                    $userSession->incrementPasswordResetBlock($now);

                    $msg = ($expired ? 'Your pin has expired.<br> However, we' : 'We')
                        . ' are unable to send another reset pin at this moment, plase contact the Heurist team or try again at a later time';

                    $this->system->addError(HEURIST_ACTION_BLOCKED, $msg);
                    return false;
                }

                if ($expired && $checkPin) {
                    $response = 'Your current reset pin has expired.<br>A new one has been sent to your email';
                } else {
                    $response = 'A new pin has been sent';
                }
            }
        }

        if ($testCaptcha && !$this->system->captcha()->consumeCaptcha($captcha)) {
            return false;
        }

        $db = $this->system->dbnameFull();
        $dbownerEmail = \user_getDbOwner($mysqli, 'ugr_eMail');

        $emailTitle = 'Forgot password';
        $emailBody = 'Dear ' . $user['ugr_FirstName'] . "\n\n"
            . 'A reset pin was requested for your account on database ' . $db . ".\n\n"
            . 'Your username is: ' . $user['ugr_Name'] . "\n"
            . 'Your reset pin is: ' . $newPin . "\n\n"
            . "This pin will expire in 5 minutes. Please enter it in the popup to reset your password.\n\n"
            . 'Database Owner: ' . $dbownerEmail;

        if (!\sendEmail($user['ugr_eMail'], $emailTitle, $emailBody)) {
            $msg = $this->system->getError();
            $this->system->addError(HEURIST_SYSTEM_CONFIG, 'We were unable to email you a reset pin', $msg ? ($msg['message'] ?? null) : null);
            return false;
        }

        $userSession->setResetPinForUser($userId, [
            'pin' => \hash_it($newPin),
            'expire' => $now + self::PIN_TTL_SECONDS,
            'resends' => $resends,
            'user' => $userId,
            'redeemed' => false
        ]);

        return $response;
    }

    public function resetPassword(string $username, string $password, string $pin): bool
    {
        $mysqli = $this->system->getMysqli();

        if ($username === '' || $password === '' || $pin === '') {
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'A username, the new password, and the reset pin are required for this function');
            return false;
        }

        $user = \user_getByField($mysqli, 'ugr_Name', $username);
        if ($user === null) {
            $user = \user_getByField($mysqli, 'ugr_eMail', $username);
        }
        if ($user === null) {
            $this->system->addError(HEURIST_NOT_FOUND, 'Cannot set new password. Unable to find specified username / email.');
            return false;
        }

        $userId = (int)$user['ugr_ID'];
        $pinState = $this->system->userSession()->getResetPinForUser($userId);

        if (!$pinState) {
            $this->system->addError(HEURIST_ERROR, 'An error has occurred with changing your password using a reset pin.<br>Please contact the Heurist team');
            return false;
        }

        if (empty($pinState['pin']) || !\passwordCheck($pin, (string)$pinState['pin'])) {
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Invalid reset pin');
            return false;
        }

        if (($pinState['redeemed'] ?? false) !== true) {
            $this->system->addError(HEURIST_ERROR, 'We were unable to verify the reset pin');
            return false;
        }

        $res = \userUpdatePassword($mysqli, $userId, \hash_it($password));

        if (is_numeric($res) && $res > 0) {
            $this->system->userSession()->removeResetPinForUser($userId);
            return true;
        }

        $this->system->addError(HEURIST_ERROR, 'We were unable to reset your password, an error occurred while updating your user account details');
        return false;
    }

    private function emptyPinState(): array
    {
        return [
            'pin' => '',
            'resends' => 1,
            'attempts' => 0,
            'expire' => null,
            'redeemed' => false
        ];
    }
}
