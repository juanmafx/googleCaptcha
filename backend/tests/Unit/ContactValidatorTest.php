<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SecureContact\Contact\ContactValidator;

final class ContactValidatorTest extends TestCase
{
    public function testAcceptsAndTrimsValidInput(): void
    {
        $result = (new ContactValidator())->validate('  Ada Lovelace ', ' ada@example.com ', " Hello\nthere ");

        self::assertSame([], $result->errors);
        self::assertSame('Ada Lovelace', $result->input?->name);
        self::assertSame('ada@example.com', $result->input?->email);
        self::assertSame("Hello\nthere", $result->input?->message);
    }

    public static function headerInjectionPayloads(): array
    {
        return [
            'crlf in email' => ['Ada', "ada@example.com\r\nBcc: victim@example.com", 'Hi', 'email'],
            'trailing crlf in email' => ['Ada', "ada@example.com\r\n", 'Hi', 'email'],
            'lf in email' => ['Ada', "ada@example.com\nBcc: victim@example.com", 'Hi', 'email'],
            'crlf in name' => ["Ada\r\nBcc: victim@example.com", 'ada@example.com', 'Hi', 'name'],
            'nul in name' => ["Ada\0", 'ada@example.com', 'Hi', 'name'],
            'nul in message' => ['Ada', 'ada@example.com', "Hi\0", 'message'],
        ];
    }

    #[DataProvider('headerInjectionPayloads')]
    public function testRejectsControlCharacters(string $name, string $email, string $message, string $field): void
    {
        $result = (new ContactValidator())->validate($name, $email, $message);

        self::assertNull($result->input);
        self::assertArrayHasKey($field, $result->errors);
    }

    public static function invalidInputs(): array
    {
        return [
            'empty name' => ['   ', 'ada@example.com', 'Hi', 'name'],
            'long name' => [str_repeat('a', 101), 'ada@example.com', 'Hi', 'name'],
            'bad email' => ['Ada', 'not-an-email', 'Hi', 'email'],
            'long email' => ['Ada', str_repeat('a', 250) . '@example.com', 'Hi', 'email'],
            'empty message' => ['Ada', 'ada@example.com', '  ', 'message'],
            'long message' => ['Ada', 'ada@example.com', str_repeat('a', 5001), 'message'],
            'invalid utf8' => ["Ada\xff", 'ada@example.com', 'Hi', 'name'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(string $name, string $email, string $message, string $field): void
    {
        self::assertArrayHasKey($field, (new ContactValidator())->validate($name, $email, $message)->errors);
    }

    public function testCountsMultibyteCharactersNotBytes(): void
    {
        self::assertNotNull((new ContactValidator())->validate(str_repeat('é', 100), 'ada@example.com', 'Hi')->input);
    }
}
