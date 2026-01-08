<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Service;

use Cpsit\QualityTools\Service\ProcessEnvironmentPreparer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @covers \Cpsit\QualityTools\Service\ProcessEnvironmentPreparer
 */
final class ProcessEnvironmentPreparerTest extends TestCase
{
    private ProcessEnvironmentPreparer $preparer;

    protected function setUp(): void
    {
        $this->preparer = new ProcessEnvironmentPreparer();
    }

    /**
     * @test
     */
    public function prepareEnvironmentReturnsServerEnvironmentByDefault(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')->willReturn(false);

        $result = $this->preparer->prepareEnvironment($input);

        self::assertEquals($_SERVER, $result);
    }

    /**
     * @test
     */
    public function prepareEnvironmentSetsMemoryLimitWhenProvided(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')->willReturn(false);

        $result = $this->preparer->prepareEnvironment($input, '512M');

        self::assertArrayHasKey('PHP_MEMORY_LIMIT', $result);
        self::assertSame('512M', $result['PHP_MEMORY_LIMIT']);
    }

    /**
     * @test
     */
    public function prepareEnvironmentSetsDynamicPathsForFractorWithoutPathOption(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')
            ->with('--path')
            ->willReturn(false);

        $resolvedPaths = ['/path/to/src', '/path/to/config'];
        
        $result = $this->preparer->prepareEnvironment($input, null, 'fractor', $resolvedPaths);

        self::assertArrayHasKey('QT_DYNAMIC_PATHS', $result);
        self::assertSame(json_encode($resolvedPaths), $result['QT_DYNAMIC_PATHS']);
    }

    /**
     * @test
     */
    public function prepareEnvironmentDoesNotSetDynamicPathsForFractorWithPathOption(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')
            ->with('--path')
            ->willReturn(true);

        $resolvedPaths = ['/path/to/src', '/path/to/config'];
        
        $result = $this->preparer->prepareEnvironment($input, null, 'fractor', $resolvedPaths);

        self::assertArrayNotHasKey('QT_DYNAMIC_PATHS', $result);
    }

    /**
     * @test
     */
    public function prepareEnvironmentDoesNotSetDynamicPathsForNonFractorTools(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')->willReturn(false);

        $resolvedPaths = ['/path/to/src', '/path/to/config'];
        
        $result = $this->preparer->prepareEnvironment($input, null, 'rector', $resolvedPaths);

        self::assertArrayNotHasKey('QT_DYNAMIC_PATHS', $result);
    }

    /**
     * @test
     */
    public function prepareEnvironmentDoesNotSetDynamicPathsWithoutResolvedPaths(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')->willReturn(false);

        $result = $this->preparer->prepareEnvironment($input, null, 'fractor', null);

        self::assertArrayNotHasKey('QT_DYNAMIC_PATHS', $result);
    }

    /**
     * @test
     */
    public function prepareEnvironmentCombinesMemoryLimitAndDynamicPaths(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')
            ->with('--path')
            ->willReturn(false);

        $resolvedPaths = ['/path/to/src'];
        
        $result = $this->preparer->prepareEnvironment($input, '1G', 'fractor', $resolvedPaths);

        self::assertArrayHasKey('PHP_MEMORY_LIMIT', $result);
        self::assertSame('1G', $result['PHP_MEMORY_LIMIT']);
        self::assertArrayHasKey('QT_DYNAMIC_PATHS', $result);
        self::assertSame(json_encode($resolvedPaths), $result['QT_DYNAMIC_PATHS']);
    }
}