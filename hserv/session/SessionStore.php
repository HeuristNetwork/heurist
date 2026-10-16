<?php
namespace hserv\session;

use hserv\utilities\USystem;

final class SessionStore
{
    private const SESSION_NAME = 'heurist-sessionid';
    private int $cookieLifetime = 0;

    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
    
    public function setCookieLifetime(int $seconds): void
    {
        $this->cookieLifetime = max(0, $seconds);
    }    

    public function start(): bool
    {
        if ($this->isActive()) {
            return true;
        }

        if (headers_sent()) {
            return false;
        }    

        if (session_name() !== self::SESSION_NAME) {
            session_name(self::SESSION_NAME);
        }

        session_cache_limiter('none');

        /*
         * Keep server-side session files long enough for persistent logins.
         * Cookie lifetime is controlled by USystem::sessionUpdateCookies().
         *
         * Important: PHP's automatic session cookie is disabled below. Otherwise
         * every later session_start() can emit a plain browser-session cookie
         *   Set-Cookie: heurist-sessionid=...; path=/
         * which overwrites the persistent remember-me cookie in the browser.
         */
        ini_set('session.gc_maxlifetime', (string)(30 * 24 * 60 * 60));

        if (!empty($_COOKIE[self::SESSION_NAME])) {
            $cookieSessionId = (string)$_COOKIE[self::SESSION_NAME];

            // Conservative validation for PHP session IDs.
            if (preg_match('/^[A-Za-z0-9,-]{16,128}$/', $cookieSessionId) === 1) {
                session_id($cookieSessionId);
            }
        }

        $ok = @session_start([
            'use_cookies' => 0,
            'use_only_cookies' => 0
        ]);

        if (!$ok) {
            return false;
        }

        if (empty($_COOKIE[self::SESSION_NAME]) || $this->cookieLifetime > 0) {
            $lifetime = $this->cookieLifetime > 0 ? time() + $this->cookieLifetime : 0;
            USystem::sessionUpdateCookies($lifetime);
        }

        return true;
    }

    public function close(): void
    {
        if ($this->isActive()) {
            session_write_close();
        }
    }

    public function get(string $key, $default = null)
    {
        if (!$this->start()) {
            return $default;
        }

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, $value): bool
    {
        if (!$this->start()) {
            return false;
        }

        $_SESSION[$key] = $value;
        $this->close();

        return true;
    }

    public function unset(string $key): bool
    {
        if (!$this->start()) {
            return false;
        }

        unset($_SESSION[$key]);
        $this->close();

        return true;
    }

    public function consume(string $key, $default = null)
    {
        if (!$this->start()) {
            return $default;
        }

        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        $this->close();

        return $value;
    }

    public function getDbValue(string $dbnameFull, string $key, $default = null)
    {
        if (!$this->start()) {
            return $default;
        }

        return $_SESSION[$dbnameFull][$key] ?? $default;
    }

    public function setDbValue(string $dbnameFull, string $key, $value): bool
    {
        if (!$this->start()) {
            return false;
        }

        if (!isset($_SESSION[$dbnameFull]) || !is_array($_SESSION[$dbnameFull])) {
            $_SESSION[$dbnameFull] = [];
        }

        $_SESSION[$dbnameFull][$key] = $value;
        $this->close();

        return true;
    }

    public function unsetDbValue(string $dbnameFull, string $key): bool
    {
        if (!$this->start()) {
            return false;
        }

        unset($_SESSION[$dbnameFull][$key]);
        $this->close();

        return true;
    }

    public function updateDb(string $dbnameFull, callable $callback)
    {
        if (!$this->start()) {
            return null;
        }

        if (!isset($_SESSION[$dbnameFull]) || !is_array($_SESSION[$dbnameFull])) {
            $_SESSION[$dbnameFull] = [];
        }

        $result = $callback($_SESSION[$dbnameFull]);
        $this->close();

        return $result;
    }

    public function update(callable $callback)
    {
        if (!$this->start()) {
            return null;
        }

        $result = $callback($_SESSION);
        $this->close();

        return $result;
    }

    public function regenerateId(bool $deleteOldSession = true): bool
    {
        if (!$this->start()) {
            return false;
        }

        return session_regenerate_id($deleteOldSession);
    }

    public function destroy(): void
    {
        if (!$this->start()) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}