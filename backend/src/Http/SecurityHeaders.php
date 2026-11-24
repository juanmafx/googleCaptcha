<?php

declare(strict_types=1);

namespace SecureContact\Http;

final class SecurityHeaders
{
    public static function for(bool $allowCaptcha, bool $strictTransport): array
    {
        $scriptSource = $allowCaptcha
            ? "script-src https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; frame-src https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/"
            : "script-src 'none'; frame-src 'none'";

        $headers = [
            'Content-Security-Policy' => "default-src 'none'; " . $scriptSource
                . "; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), geolocation=(), microphone=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cache-Control' => 'no-store',
        ];
        if ($strictTransport) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }
}
