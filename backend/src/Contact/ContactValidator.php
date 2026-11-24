<?php

declare(strict_types=1);

namespace SecureContact\Contact;

final class ContactValidator
{
    public const int MAX_NAME_LENGTH = 100;
    public const int MAX_EMAIL_LENGTH = 254;
    public const int MAX_MESSAGE_LENGTH = 5000;

    public function validate(string $name, string $email, string $message): ValidationResult
    {
        $errors = [];
        if (!mb_check_encoding($name, 'UTF-8') || preg_match('/\p{Cc}/u', $name) === 1) {
            $errors['name'] = 'Enter a valid name.';
        }
        if (!mb_check_encoding($email, 'UTF-8') || preg_match('/\p{Cc}/u', $email) === 1) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!mb_check_encoding($message, 'UTF-8') || preg_match('/[^\P{Cc}\r\n\t]/u', $message) === 1) {
            $errors['message'] = 'The message contains unsupported characters.';
        }
        if ($errors !== []) {
            return new ValidationResult(null, $errors);
        }

        $name = trim($name);
        $email = trim($email);
        $message = trim($message);
        $nameLength = mb_strlen($name, 'UTF-8');
        $messageLength = mb_strlen($message, 'UTF-8');

        if ($nameLength === 0 || $nameLength > self::MAX_NAME_LENGTH) {
            $errors['name'] = 'Name must be between 1 and 100 characters.';
        }
        if (strlen($email) > self::MAX_EMAIL_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($messageLength === 0 || $messageLength > self::MAX_MESSAGE_LENGTH) {
            $errors['message'] = 'Message must be between 1 and 5000 characters.';
        }

        return $errors === []
            ? new ValidationResult(new ContactInput($name, $email, $message), [])
            : new ValidationResult(null, $errors);
    }
}
