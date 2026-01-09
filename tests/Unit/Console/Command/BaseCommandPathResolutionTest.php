<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\Configuration;
use Cpsit\QualityTools\Console\Command\BaseCommand;
use Cpsit\QualityTools\Console\Command\RectorLintCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unit test to verify BaseCommand path resolution with multiple paths.
 */
#[CoversClass(BaseCommand::class)]
final class BaseCommandPathResolutionTest extends TestCase
{
    /**
     * Test that getTargetPathForTool still returns first path for backward compatibility
     * while getResolvedPathsForTool returns all paths.
     *
     * @test
     */
    public function getTargetPathForToolReturnsFirstPathWhileResolvedPathsReturnsAll(): void
    {
        // Create a test command that extends BaseCommand
        $command = new class extends BaseCommand {
            public function publicGetTargetPathForTool(InputInterface $input, string $tool): string
            {
                return $this->getTargetPathForTool($input, $tool);
            }

            public function publicGetResolvedPathsForTool(InputInterface $input, string $tool): array
            {
                return $this->getResolvedPathsForTool($input, $tool);
            }

            public function setMockConfiguration(Configuration $config): void
            {
                $this->configuration = $config;
            }

            public function publicConfigure(): void
            {
                $this->configure();
            }

            protected function configure(): void
            {
                parent::configure();
                $this->setName('test:command');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        // Create mock configuration that returns multiple paths
        $mockConfig = $this->createMock(Configuration::class);
        $mockConfig->method('getResolvedPathsForTool')
            ->with('rector')
            ->willReturn([
                '/project/packages',
                '/project/vendor/company1/package1',
                '/project/vendor/company1/package2',
                '/project/vendor/company2/package3',
                '/project/custom-dir',
            ]);

        // Set the mock configuration and configure the command properly
        $command->publicConfigure();
        $command->setMockConfiguration($mockConfig);

        $input = new ArrayInput([], $command->getDefinition());

        // Get all resolved paths - this should return all 5 paths
        $resolvedPaths = $command->publicGetResolvedPathsForTool($input, 'rector');
        $this->assertCount(5, $resolvedPaths, 'All resolved paths should be returned');
        $this->assertSame('/project/packages', $resolvedPaths[0]);
        $this->assertSame('/project/vendor/company1/package1', $resolvedPaths[1]);

        // Get single target path for backward compatibility - should return first path
        $targetPath = $command->publicGetTargetPathForTool($input, 'rector');
        $this->assertSame('/project/packages', $targetPath, 'Single target path should return first resolved path for backward compatibility');
    }

    /**
     * Test that user-provided --path option still works (should override config).
     *
     * @test
     */
    public function userProvidedPathOptionOverridesConfiguration(): void
    {
        $command = new class extends RectorLintCommand {
            public function publicGetTargetPathForTool(InputInterface $input, string $tool): string
            {
                return $this->getTargetPathForTool($input, $tool);
            }

            public function publicConfigure(): void
            {
                $this->configure();
            }

            protected function configure(): void
            {
                parent::configure();
                $this->setName('test:rector-command');
            }
        };

        // Create a temporary directory for testing
        $tempDir = sys_get_temp_dir() . '/qt-test-' . uniqid();
        mkdir($tempDir, 0o777, true);

        try {
            $command->publicConfigure();
            $input = new ArrayInput(['--path' => $tempDir], $command->getDefinition());

            $targetPath = $command->publicGetTargetPathForTool($input, 'rector');

            // User-provided path should override configuration
            $this->assertSame(realpath($tempDir), $targetPath);
        } finally {
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    /**
     * Test that the fix properly handles multiple paths through direct command arguments.
     *
     * @test
     */
    public function commandsNowPassAllResolvedPathsAsArguments(): void
    {
        // This test verifies that the new approach works:
        // Instead of relying on environment variables or single paths,
        // commands now pass all resolved paths directly as arguments

        $mockConfig = $this->createMock(Configuration::class);
        $mockConfig->method('getResolvedPathsForTool')
            ->willReturn([
                '/project/packages',
                '/project/vendor/company1/package1',
                '/project/vendor/company2/package2',
            ]);

        $command = new class extends BaseCommand {
            public function setMockConfiguration(Configuration $config): void
            {
                $this->configuration = $config;
            }

            public function publicGetResolvedPathsForTool(InputInterface $input, string $tool): array
            {
                return $this->getResolvedPathsForTool($input, $tool);
            }

            public function publicConfigure(): void
            {
                $this->configure();
            }

            protected function configure(): void
            {
                parent::configure();
                $this->setName('test:command2');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $command->publicConfigure();
        $command->setMockConfiguration($mockConfig);

        $input = new ArrayInput([], $command->getDefinition());

        // Verify that getResolvedPathsForTool returns all paths
        $paths = $command->publicGetResolvedPathsForTool($input, 'rector');
        $this->assertCount(3, $paths);
        $this->assertContains('/project/packages', $paths);
        $this->assertContains('/project/vendor/company1/package1', $paths);
        $this->assertContains('/project/vendor/company2/package2', $paths);
    }
}
