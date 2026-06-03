<?php
namespace hserv\session;

use hserv\System;

final class UserSession
{
    public function __construct(
        private SessionStore $session,
        private System $system
    ) {}

    public function needsRefresh(): bool
    {
        $dbname = $this->system->dbnameFull();
        return (int)$this->session->getDbValue($dbname, 'need_refresh', 0) === 1;
    }

    public function clearNeedsRefresh(): void
    {
        $this->session->unsetDbValue($this->system->dbnameFull(), 'need_refresh');
    }

    public function getPermissions(): array
    {
        $dbname = $this->system->dbnameFull();
        $permissions = $this->session->getDbValue($dbname, 'ugr_Permissions', []);
        return is_array($permissions) ? $permissions : [];
    }

    public function getPreferences(): array
    {
        $dbname = $this->system->dbnameFull();
        $preferences = $this->session->getDbValue($dbname, 'ugr_Preferences', []);
        return is_array($preferences) ? $preferences : [];
    }

    public function getPreference(string $key, mixed $default = null): mixed
    {
        $preferences = $this->getPreferences();
        return $preferences[$key] ?? $default;
    }

    public function setDbValue(string $key, mixed $value): bool
    {
        return $this->session->setDbValue($this->system->dbnameFull(), $key, $value);
    }

    public function unsetDbValue(string $key): bool
    {
        return $this->session->unsetDbValue($this->system->dbnameFull(), $key);
    }

    public function clearPreferencesByPrefix(string $prefix): void
    {
        $dbname = $this->system->dbnameFull();

        $this->session->updateDb($dbname, function (&$dbSession) use ($prefix) {
            if (empty($dbSession['ugr_Preferences']) || !is_array($dbSession['ugr_Preferences'])) {
                return;
            }

            foreach (array_keys($dbSession['ugr_Preferences']) as $key) {
                if (strpos($key, $prefix) === 0) {
                    unset($dbSession['ugr_Preferences'][$key]);
                }
            }
        });
    }

    public function replacePreferences(array $preferences): void
    {
        $this->setDbValue('ugr_Preferences', $preferences);
    }

    public function mergePreferences(array $params, array $exclude = []): array
    {
        $dbname = $this->system->dbnameFull();

        $result = $this->session->updateDb($dbname, function (&$dbSession) use ($params, $exclude) {
            if (empty($dbSession['ugr_Preferences']) || !is_array($dbSession['ugr_Preferences'])) {
                $dbSession['ugr_Preferences'] = [];
            }

            foreach ($params as $property => $value) {
                if (!in_array($property, $exclude, true)) {
                    $dbSession['ugr_Preferences'][$property] = $value;
                }
            }

            return $dbSession['ugr_Preferences'];
        });

        return is_array($result) ? $result : [];
    }

    public function getResetPins(): array
    {
        $pins = $this->session->getDbValue($this->system->dbnameFull(), 'reset_pins', []);
        return is_array($pins) ? $pins : [];
    }

    public function updateResetPins(callable $callback): mixed
    {
        return $this->session->updateDb($this->system->dbnameFull(), function (&$dbSession) use ($callback) {
            if (!isset($dbSession['reset_pins']) || !is_array($dbSession['reset_pins'])) {
                $dbSession['reset_pins'] = [
                    'blocked' => 0,
                    'last_block' => null
                ];
            }

            return $callback($dbSession['reset_pins']);
        });
    }

    public function getResetPinForUser(int $userId): ?array
    {
        $pins = $this->getResetPins();
        $entry = $pins[$userId] ?? null;
        return is_array($entry) ? $entry : null;
    }

    public function setResetPinForUser(int $userId, array $entry): void
    {
        $this->updateResetPins(function (&$pins) use ($userId, $entry) {
            $pins[$userId] = $entry;
        });
    }

    public function removeResetPinForUser(int $userId): void
    {
        $this->updateResetPins(function (&$pins) use ($userId) {
            unset($pins[$userId]);
        });
    }

    public function markResetPinRedeemed(int $userId): void
    {
        $this->updateResetPins(function (&$pins) use ($userId) {
            if (isset($pins[$userId]) && is_array($pins[$userId])) {
                $pins[$userId]['redeemed'] = true;
            }
        });
    }

    public function resetPasswordBlockStateIfExpired(int $now, int $ttlSeconds): void
    {
        $this->updateResetPins(function (&$pins) use ($now, $ttlSeconds) {
            if (($pins['last_block'] ?? null) !== null && ((int)$pins['last_block'] + $ttlSeconds) < $now) {
                $pins['blocked'] = 0;
                $pins['last_block'] = null;
            }
        });
    }

    public function incrementPasswordResetBlock(int $now): void
    {
        $this->updateResetPins(function (&$pins) use ($now) {
            $pins['blocked'] = ((int)($pins['blocked'] ?? 0)) + 1;
            $pins['last_block'] = $now;
        });
    }

}