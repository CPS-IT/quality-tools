<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\ToolRunner;

use Cpsit\QualityTools\ToolRunner\ToolRunnerInterface;
use Cpsit\QualityTools\ToolRunner\ToolRunnerRegistry;
use Cpsit\QualityTools\ToolRunner\ToolRunRequest;
use Cpsit\QualityTools\ToolRunner\ToolRunResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolRunnerRegistry::class)]
final class ToolRunnerRegistryTest extends TestCase
{
    #[Test]
    public function getReturnsRegisteredRunner(): void
    {
        $runner = $this->createRunnerStub(['rector']);
        $registry = new ToolRunnerRegistry([$runner]);

        $this->assertSame($runner, $registry->get('rector'));
    }

    #[Test]
    public function getThrowsExceptionForUnknownTool(): void
    {
        $registry = new ToolRunnerRegistry([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No runner registered for tool: unknown');

        $registry->get('unknown');
    }

    #[Test]
    public function hasReturnsTrueForRegisteredTool(): void
    {
        $runner = $this->createRunnerStub(['phpstan']);
        $registry = new ToolRunnerRegistry([$runner]);

        $this->assertTrue($registry->has('phpstan'));
    }

    #[Test]
    public function hasReturnsFalseForUnregisteredTool(): void
    {
        $registry = new ToolRunnerRegistry([]);

        $this->assertFalse($registry->has('unknown'));
    }

    #[Test]
    public function registersMultipleToolsFromSingleRunner(): void
    {
        $runner = $this->createRunnerStub(['lint:rector', 'fix:rector']);
        $registry = new ToolRunnerRegistry([$runner]);

        $this->assertTrue($registry->has('lint:rector'));
        $this->assertTrue($registry->has('fix:rector'));
        $this->assertSame($runner, $registry->get('lint:rector'));
        $this->assertSame($runner, $registry->get('fix:rector'));
    }

    #[Test]
    public function registersMultipleRunners(): void
    {
        $rectorRunner = $this->createRunnerStub(['rector']);
        $phpstanRunner = $this->createRunnerStub(['phpstan']);

        $registry = new ToolRunnerRegistry([$rectorRunner, $phpstanRunner]);

        $this->assertSame($rectorRunner, $registry->get('rector'));
        $this->assertSame($phpstanRunner, $registry->get('phpstan'));
    }

    #[Test]
    public function lastRunnerWinsForDuplicateToolNames(): void
    {
        $firstRunner = $this->createRunnerStub(['rector']);
        $secondRunner = $this->createRunnerStub(['rector']);

        $registry = new ToolRunnerRegistry([$firstRunner, $secondRunner]);

        $this->assertSame($secondRunner, $registry->get('rector'));
    }

    #[Test]
    public function getRegisteredToolsReturnsAllToolNames(): void
    {
        $rectorRunner = $this->createRunnerStub(['rector']);
        $phpstanRunner = $this->createRunnerStub(['phpstan']);

        $registry = new ToolRunnerRegistry([$rectorRunner, $phpstanRunner]);

        $tools = $registry->getRegisteredTools();
        $this->assertCount(2, $tools);
        $this->assertContains('rector', $tools);
        $this->assertContains('phpstan', $tools);
    }

    #[Test]
    public function getRegisteredToolsReturnsEmptyArrayWhenNoRunners(): void
    {
        $registry = new ToolRunnerRegistry([]);

        $this->assertSame([], $registry->getRegisteredTools());
    }

    #[Test]
    public function acceptsIterableInConstructor(): void
    {
        $runner = $this->createRunnerStub(['rector']);
        $generator = (static function () use ($runner): \Generator {
            yield $runner;
        })();

        $registry = new ToolRunnerRegistry($generator);

        $this->assertTrue($registry->has('rector'));
    }

    /**
     * @param list<string> $tools
     */
    private function createRunnerStub(array $tools): ToolRunnerInterface
    {
        $runner = $this->createMock(ToolRunnerInterface::class);
        $runner->method('supportedTools')->willReturn($tools);

        return $runner;
    }
}
