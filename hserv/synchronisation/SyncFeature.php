<?php

namespace hserv\synchronisation;

/**
 * Central feature gate for the experimental database synchronisation service.
 */
final class SyncFeature
{
    public const UNAVAILABLE_MESSAGE = 'Sorry, this function is not available on this server';

    public static function isEnabled(): bool
    {
        global $experimental;
        return isset($experimental) && $experimental === true;
    }

    public static function requireEnabled(\hserv\System $system): bool
    {
        if (self::isEnabled()) {
            return true;
        }
        $system->addError(HEURIST_ACTION_BLOCKED, self::UNAVAILABLE_MESSAGE);
        return false;
    }

    /**
     * Returns true when normal user-facing structure editing must be blocked.
     * Synchronisation imports call their services directly and do not bypass
     * this through a browser request flag.
     */
    public static function isStructureLocked(\hserv\System $system): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        $config = $system->settings->getDatabaseSetting('Synchronisation');
        return is_array($config) && ($config['role'] ?? '') === 'satellite';
    }
}
