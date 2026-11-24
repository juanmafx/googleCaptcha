<?php

declare(strict_types=1);

namespace SecureContact\Http;

use SecureContact\Admin\AdminAuthenticator;
use SecureContact\Contact\ContactService;
use SecureContact\Security\CsrfGuard;
use SecureContact\Security\RateLimiter;
use SecureContact\Support\Clock;

final readonly class AdminController
{
    private const int PENDING_TTL_SECONDS = 300;
    private const int IDLE_TTL_SECONDS = 900;
    private const int ABSOLUTE_TTL_SECONDS = 28800;
    private const int LOGIN_ADDRESS_LIMIT = 10;
    private const int LOGIN_ACCOUNT_LIMIT = 5;
    private const int LOGIN_WINDOW_SECONDS = 900;
    private const int TOTP_LIMIT = 5;
    private const int TOTP_WINDOW_SECONDS = 300;

    public function __construct(
        private View $view,
        private Session $session,
        private CsrfGuard $csrf,
        private RateLimiter $rateLimiter,
        private AdminAuthenticator $authenticator,
        private ContactService $contacts,
        private Clock $clock,
    ) {
    }

    public function loginForm(): Response
    {
        if ($this->authenticatedAdminId() !== null) {
            return Response::redirect('/admin');
        }

        return $this->renderLogin(null, 200);
    }

    public function login(Request $request): Response
    {
        if (!$this->csrf->isValid($request->postString('csrf_token'))) {
            return $this->forbidden();
        }

        $username = $request->postString('username');
        if (!$this->rateLimiter->allow('login-address', $request->rateLimitAddress(), self::LOGIN_ADDRESS_LIMIT, self::LOGIN_WINDOW_SECONDS)
            || !$this->rateLimiter->allow('login-account', strtolower(substr($username, 0, 64)), self::LOGIN_ACCOUNT_LIMIT, self::LOGIN_WINDOW_SECONDS)) {
            return $this->tooManyAttempts(self::LOGIN_WINDOW_SECONDS);
        }

        $admin = $this->authenticator->verifyPassword($username, $request->postString('password'));
        if ($admin === null) {
            return $this->renderLogin('Invalid credentials.', 401);
        }

        $this->session->regenerate();
        $this->csrf->rotate();
        $this->session->remove('admin_id');
        $this->session->remove('admin_authenticated_at');
        $this->session->remove('admin_last_seen_at');
        $this->session->set('pending_admin_id', $admin->id);
        $this->session->set('pending_admin_at', $this->clock->now());

        return Response::redirect('/admin/two-factor');
    }

    public function twoFactorForm(): Response
    {
        if ($this->pendingAdminId() === null) {
            return Response::redirect('/admin/login');
        }

        return $this->renderTwoFactor(null, 200);
    }

    public function twoFactor(Request $request): Response
    {
        if (!$this->csrf->isValid($request->postString('csrf_token'))) {
            return $this->forbidden();
        }

        $adminId = $this->pendingAdminId();
        if ($adminId === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->rateLimiter->allow('totp', (string) $adminId, self::TOTP_LIMIT, self::TOTP_WINDOW_SECONDS)) {
            return $this->tooManyAttempts(self::TOTP_WINDOW_SECONDS);
        }
        if (!$this->authenticator->verifySecondFactor($adminId, $request->postString('code'))) {
            return $this->renderTwoFactor('Invalid or already used code.', 401);
        }

        $now = $this->clock->now();
        $this->session->regenerate();
        $this->csrf->rotate();
        $this->session->remove('pending_admin_id');
        $this->session->remove('pending_admin_at');
        $this->session->set('admin_id', $adminId);
        $this->session->set('admin_authenticated_at', $now);
        $this->session->set('admin_last_seen_at', $now);

        return Response::redirect('/admin');
    }

    public function dashboard(Request $request): Response
    {
        if ($this->authenticatedAdminId() === null) {
            return Response::redirect('/admin/login');
        }

        $this->session->set('admin_last_seen_at', $this->clock->now());
        $pageCount = $this->contacts->pageCount();
        $page = min($request->queryPage(), $pageCount);

        return Response::html($this->view->render('submissions', 'Submissions', [
            'submissions' => $this->contacts->page($page),
            'page' => $page,
            'pageCount' => $pageCount,
            'csrfToken' => $this->csrf->token(),
        ]));
    }

    public function logout(Request $request): Response
    {
        if (!$this->csrf->isValid($request->postString('csrf_token'))) {
            return $this->forbidden();
        }

        $this->session->destroy();

        return Response::redirect('/admin/login');
    }

    private function authenticatedAdminId(): ?int
    {
        $adminId = $this->session->get('admin_id');
        $authenticatedAt = $this->session->get('admin_authenticated_at');
        $lastSeenAt = $this->session->get('admin_last_seen_at');
        if (!is_int($adminId) || !is_int($authenticatedAt) || !is_int($lastSeenAt)) {
            return null;
        }

        $now = $this->clock->now();
        if ($now - $authenticatedAt > self::ABSOLUTE_TTL_SECONDS
            || $now - $lastSeenAt > self::IDLE_TTL_SECONDS
            || !$this->authenticator->isActive($adminId)) {
            $this->session->remove('admin_id');
            $this->session->remove('admin_authenticated_at');
            $this->session->remove('admin_last_seen_at');

            return null;
        }

        return $adminId;
    }

    private function pendingAdminId(): ?int
    {
        $adminId = $this->session->get('pending_admin_id');
        $startedAt = $this->session->get('pending_admin_at');
        if (!is_int($adminId) || !is_int($startedAt)) {
            return null;
        }
        if ($this->clock->now() - $startedAt > self::PENDING_TTL_SECONDS) {
            $this->session->remove('pending_admin_id');
            $this->session->remove('pending_admin_at');

            return null;
        }

        return $adminId;
    }

    private function renderLogin(?string $error, int $status): Response
    {
        return Response::html($this->view->render('login', 'Admin sign in', [
            'error' => $error,
            'csrfToken' => $this->csrf->token(),
        ]), $status);
    }

    private function renderTwoFactor(?string $error, int $status): Response
    {
        return Response::html($this->view->render('two-factor', 'Two-factor verification', [
            'error' => $error,
            'csrfToken' => $this->csrf->token(),
        ]), $status);
    }

    private function forbidden(): Response
    {
        return Response::html($this->view->render('error', 'Request expired', [
            'message' => 'Your session expired. Reload the page and try again.',
        ]), 403);
    }

    private function tooManyAttempts(int $retryAfter): Response
    {
        return Response::html($this->view->render('error', 'Too many attempts', [
            'message' => 'Too many attempts. Try again later.',
        ]), 429)->withHeaders(['Retry-After' => (string) $retryAfter]);
    }
}
