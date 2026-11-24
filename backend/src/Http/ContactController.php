<?php

declare(strict_types=1);

namespace SecureContact\Http;

use SecureContact\Captcha\CaptchaVerifier;
use SecureContact\Contact\ContactService;
use SecureContact\Contact\ContactValidator;
use SecureContact\Security\CsrfGuard;
use SecureContact\Security\RateLimiter;

final readonly class ContactController
{
    private const int SUBMIT_LIMIT = 5;
    private const int SUBMIT_WINDOW_SECONDS = 900;

    public function __construct(
        private View $view,
        private CsrfGuard $csrf,
        private RateLimiter $rateLimiter,
        private CaptchaVerifier $captcha,
        private ContactValidator $validator,
        private ContactService $contacts,
        private string $captchaSiteKey,
    ) {
    }

    public function form(): Response
    {
        return $this->renderForm([], ['name' => '', 'email' => '', 'message' => ''], 200);
    }

    public function submit(Request $request): Response
    {
        if (!$this->csrf->isValid($request->postString('csrf_token'))) {
            return Response::html($this->view->render('error', 'Request expired', [
                'message' => 'Your session expired. Reload the page and try again.',
            ]), 403);
        }
        if (!$this->rateLimiter->allow('contact', $request->rateLimitAddress(), self::SUBMIT_LIMIT, self::SUBMIT_WINDOW_SECONDS)) {
            return Response::html($this->view->render('error', 'Too many requests', [
                'message' => 'Too many submissions. Try again later.',
            ]), 429)->withHeaders(['Retry-After' => (string) self::SUBMIT_WINDOW_SECONDS]);
        }

        $values = [
            'name' => $request->postString('name'),
            'email' => $request->postString('email'),
            'message' => $request->postString('message'),
        ];
        $validation = $this->validator->validate($values['name'], $values['email'], $values['message']);
        if ($validation->input === null) {
            return $this->renderForm($validation->errors, $values, 422);
        }
        if (!$this->captcha->verify($request->postString('g-recaptcha-response'))) {
            return $this->renderForm(['captcha' => 'Robot verification failed. Try again.'], $values, 422);
        }

        $this->contacts->submit($validation->input);

        return Response::redirect('/thanks');
    }

    public function thanks(): Response
    {
        return Response::html($this->view->render('thanks', 'Message sent'));
    }

    private function renderForm(array $errors, array $values, int $status): Response
    {
        return Response::html($this->view->render('contact', 'Contact', [
            'errors' => $errors,
            'values' => array_map(
                static fn (string $value): string => mb_scrub($value, 'UTF-8'),
                $values,
            ),
            'csrfToken' => $this->csrf->token(),
            'captchaSiteKey' => $this->captchaSiteKey,
        ], $this->captchaSiteKey), $status);
    }
}
