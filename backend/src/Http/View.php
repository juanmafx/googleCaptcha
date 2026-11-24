<?php

declare(strict_types=1);

namespace SecureContact\Http;

use InvalidArgumentException;
use Throwable;

final readonly class View
{
    public function __construct(private string $templateDirectory)
    {
    }

    public static function escape(string|int|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    public function render(string $template, string $title, array $variables = [], ?string $captchaSiteKey = null): string
    {
        $content = $this->capture($template, $variables);

        return $this->capture('layout', [
            'title' => $title,
            'content' => $content,
            'captchaSiteKey' => $captchaSiteKey,
        ]);
    }

    private function capture(string $template, array $variables): string
    {
        if (preg_match('/\A[a-z-]+\z/', $template) !== 1) {
            throw new InvalidArgumentException('Invalid template name.');
        }

        $file = $this->templateDirectory . '/' . $template . '.php';
        ob_start();
        try {
            (static function (string $__file, array $__variables): void {
                extract($__variables, EXTR_SKIP);
                $e = View::escape(...);
                require $__file;
            })($file, $variables);

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
