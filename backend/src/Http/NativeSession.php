<?php

declare(strict_types=1);

namespace SecureContact\Http;

use RuntimeException;

final class NativeSession implements Session
{
    public function __construct(private readonly bool $secureCookie)
    {
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        $parameters = session_get_cookie_params();
        setcookie((string) session_name(), '', [
            'expires' => 1,
            'path' => $parameters['path'],
            'secure' => $parameters['secure'],
            'httponly' => $parameters['httponly'],
            'samesite' => $parameters['samesite'],
        ]);
        session_destroy();
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name($this->secureCookie ? '__Host-contact_session' : 'contact_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secureCookie,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Session could not be started.');
        }
    }
}
