<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\ToolRunner;

use Cpsit\QualityTools\ToolRunner\ToolRunRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolRunRequest::class)]
final class ToolRunRequestTest extends TestCase
{
    #[Test]
    public function constructorSetsRequiredProperties(): void
    {
        $request = new ToolRunRequest(
            toolName: 'rector',
            dryRun: true,
        );

        $this->assertSame('rector', $request->toolName);
        $this->assertTrue($request->dryRun);
        $this->assertNull($request->configOverride);
        $this->assertNull($request->pathOverride);
        $this->assertSame([], $request->toolOptions);
    }

    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $request = new ToolRunRequest(
            toolName: 'phpstan',
            dryRun: false,
            configOverride: '/custom/phpstan.neon',
            pathOverride: '/project/src',
            toolOptions: ['level' => 8, 'memory-limit' => '1G'],
        );

        $this->assertSame('phpstan', $request->toolName);
        $this->assertFalse($request->dryRun);
        $this->assertSame('/custom/phpstan.neon', $request->configOverride);
        $this->assertSame('/project/src', $request->pathOverride);
        $this->assertSame(['level' => 8, 'memory-limit' => '1G'], $request->toolOptions);
    }

    #[Test]
    public function isImmutable(): void
    {
        $request = new ToolRunRequest(toolName: 'rector', dryRun: true);

        $reflection = new \ReflectionClass($request);
        $this->assertTrue($reflection->isReadOnly());
    }
}
