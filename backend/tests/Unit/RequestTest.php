<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SecureContact\Http\Request;

final class RequestTest extends TestCase
{
    #[DataProvider('addresses')]
    public function testRateLimitAddressNormalization(string $first, string $second, bool $sameBucket): void
    {
        $left = (new Request('POST', '/', [], [], $first))->rateLimitAddress();
        $right = (new Request('POST', '/', [], [], $second))->rateLimitAddress();

        self::assertSame($sameBucket, $left === $right);
    }

    public static function addresses(): array
    {
        return [
            'same ipv6 prefix' => ['2001:db8:1:2::1', '2001:db8:1:2:ffff::9', true],
            'different ipv6 prefix' => ['2001:db8:1:2::1', '2001:db8:1:3::1', false],
            'equivalent ipv6 spelling' => ['2001:DB8:1:2::1', '2001:0db8:0001:0002::1', true],
            'distinct ipv4' => ['203.0.113.10', '203.0.113.11', false],
            'mapped ipv4 equals ipv4' => ['::ffff:203.0.113.10', '203.0.113.10', true],
            'mapped ipv4 stays distinct' => ['::ffff:203.0.113.10', '::ffff:203.0.113.11', false],
        ];
    }
}
