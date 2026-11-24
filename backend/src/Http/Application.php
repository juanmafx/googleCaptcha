<?php

declare(strict_types=1);

namespace SecureContact\Http;

final readonly class Application
{
    private const array CAPTCHA_PATHS = ['/', '/contact'];

    public function __construct(
        private ContactController $contact,
        private AdminController $admin,
        private View $view,
        private string $stylesheetPath,
        private bool $strictTransport,
    ) {
    }

    public function handle(Request $request): Response
    {
        $response = match ($request->method . ' ' . $request->path) {
            'GET /' => $this->contact->form(),
            'POST /contact' => $this->contact->submit($request),
            'GET /thanks' => $this->contact->thanks(),
            'GET /admin' => $this->admin->dashboard($request),
            'GET /admin/login' => $this->admin->loginForm(),
            'POST /admin/login' => $this->admin->login($request),
            'GET /admin/two-factor' => $this->admin->twoFactorForm(),
            'POST /admin/two-factor' => $this->admin->twoFactor($request),
            'POST /admin/logout' => $this->admin->logout($request),
            'GET /assets/app.css' => $this->stylesheet(),
            default => $this->notFound(),
        };

        return $response->withHeaders(SecurityHeaders::for(
            in_array($request->path, self::CAPTCHA_PATHS, true),
            $this->strictTransport,
        ));
    }

    private function stylesheet(): Response
    {
        $css = file_get_contents($this->stylesheetPath);

        return $css === false
            ? $this->notFound()
            : new Response($css, 200, ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    private function notFound(): Response
    {
        return Response::html($this->view->render('error', 'Not found', ['message' => 'Page not found.']), 404);
    }
}
