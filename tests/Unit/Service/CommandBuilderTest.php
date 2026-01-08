<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\CommandBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cpsit\QualityTools\Service\CommandBuilder
 */
final class CommandBuilderTest extends TestCase
{
    private CommandBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CommandBuilder();
    }

    /**
     * @test
     */
    public function prepareCommandWithMemoryLimitReturnsOriginalCommandWhenNoMemoryLimit(): void
    {
        $command = ['rector', 'process', '--dry-run'];
        
        $result = $this->builder->prepareCommandWithMemoryLimit($command);

        self::assertSame($command, $result);
    }

    /**
     * @test
     */
    public function prepareCommandWithMemoryLimitInjectsMemoryLimitForPhpExecutable(): void
    {
        $command = ['php', 'rector.phar', 'process'];
        $memoryLimit = '512M';
        
        $result = $this->builder->prepareCommandWithMemoryLimit($command, $memoryLimit);

        $expected = ['php', '-d', 'memory_limit=512M', 'php', 'rector.phar', 'process'];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function prepareCommandWithMemoryLimitInjectsMemoryLimitForPhpScript(): void
    {
        $command = ['/path/to/script.php', 'arg1', 'arg2'];
        $memoryLimit = '1G';
        
        $result = $this->builder->prepareCommandWithMemoryLimit($command, $memoryLimit);

        $expected = ['php', '-d', 'memory_limit=1G', '/path/to/script.php', 'arg1', 'arg2'];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function prepareCommandWithMemoryLimitInjectsMemoryLimitForPharFile(): void
    {
        $command = ['/path/to/tool.phar', '--config', 'config.yaml'];
        $memoryLimit = '256M';
        
        $result = $this->builder->prepareCommandWithMemoryLimit($command, $memoryLimit);

        $expected = ['php', '-d', 'memory_limit=256M', '/path/to/tool.phar', '--config', 'config.yaml'];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function prepareCommandWithMemoryLimitDoesNotInjectForNonPhpCommands(): void
    {
        $command = ['npm', 'run', 'build'];
        $memoryLimit = '512M';
        
        $result = $this->builder->prepareCommandWithMemoryLimit($command, $memoryLimit);

        self::assertSame($command, $result);
    }

    /**
     * @test
     */
    public function prepareCommandWithMemoryLimitHandlesPhpBasenameInExecutable(): void
    {
        $command = ['/usr/local/bin/php8.3', 'script.php'];
        $memoryLimit = '512M';
        
        $result = $this->builder->prepareCommandWithMemoryLimit($command, $memoryLimit);

        $expected = ['php', '-d', 'memory_limit=512M', '/usr/local/bin/php8.3', 'script.php'];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function prepareCommandWithMemoryLimitHandlesEmptyCommand(): void
    {
        $command = [];
        $memoryLimit = '512M';
        
        $result = $this->builder->prepareCommandWithMemoryLimit($command, $memoryLimit);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function prepareCommandWithMemoryLimitHandlesComplexPhpCommand(): void
    {
        $command = ['phpunit', '--coverage-text', '--bootstrap', 'vendor/autoload.php'];
        $memoryLimit = '2G';
        
        $result = $this->builder->prepareCommandWithMemoryLimit($command, $memoryLimit);

        $expected = ['php', '-d', 'memory_limit=2G', 'phpunit', '--coverage-text', '--bootstrap', 'vendor/autoload.php'];
        self::assertSame($expected, $result);
    }
}