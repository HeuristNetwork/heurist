<?php
namespace hserv\session;

final class SessionStore
{
    private bool $closeAfterWrite = true;

    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function start(): bool
    {
        if ($this->isActive()) {
            return true;
        }

        if (headers_sent()) {
            return false;
        }

        return session_start();
    }

    public function close(): void
    {
        if ($this->isActive()) {
            session_write_close();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->start()) {
            return $default;
        }

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): bool
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

    public function consume(string $key, mixed $default = null): mixed
    {
        if (!$this->start()) {
            return $default;
        }

        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        $this->close();

        return $value;
    }

    public function getDbValue(string $dbnameFull, string $key, mixed $default = null): mixed
    {
        if (!$this->start()) {
            return $default;
        }

        return $_SESSION[$dbnameFull][$key] ?? $default;
    }

    public function setDbValue(string $dbnameFull, string $key, mixed $value): bool
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

    public function updateDb(string $dbnameFull, callable $callback): mixed
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
}