<?php

declare(strict_types=1);

namespace SecureContact\Security;

use SecureContact\Http\Session;

final readonly class CsrfGuard
{
    private const string SESSION_KEY = 'csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function isValid(string $submitted): bool
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) && $token !== '' && hash_equals($token, $submitted);
    }

    public function rotate(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }
}
