<?php

declare(strict_types=1);

namespace SecureContact\Captcha;

final readonly class RecaptchaVerifier implements CaptchaVerifier
{
    private const string ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';
    private const int MAX_TOKEN_BYTES = 4096;

    public function __construct(
        private HttpPoster $http,
        private string $secret,
        private ?string $expectedHostname = null,
    ) {
    }

    public function verify(string $token): bool
    {
        if ($token === '' || strlen($token) > self::MAX_TOKEN_BYTES) {
            return false;
        }

        $body = $this->http->post(self::ENDPOINT, ['secret' => $this->secret, 'response' => $token]);
        if ($body === null) {
            return false;
        }

        $result = json_decode($body, true);
        if (!is_array($result) || ($result['success'] ?? false) !== true) {
            return false;
        }

        return $this->expectedHostname === null || ($result['hostname'] ?? null) === $this->expectedHostname;
    }
}
