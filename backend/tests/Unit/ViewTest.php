<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SecureContact\Http\View;

final class ViewTest extends TestCase
{
    public function testEscapesHtmlContexts(): void
    {
        self::assertSame('&lt;script&gt;&quot;&apos;&amp;', View::escape('<script>"\'&'));
    }

    public function testRejectsTemplateTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new View(dirname(__DIR__, 3) . '/frontend/templates'))->render('../../secret', 'Title');
    }
}
