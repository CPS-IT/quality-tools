<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Unit\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Console\Command\AbstractToolCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Exception\ConfigurationException;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Edge case and error scenario tests for AbstractToolCommand.
 *
 * @covers \Cpsit\QualityTools\Console\Command\AbstractToolCommand
 */
final class AbstractToolCommandEdgeCasesTest extends TestCase
{
    private EdgeCaseTestToolCommand $command;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TestHelper::createTempDirectory('abstract_tool_edge_case_test_');

        // Create a TYPO3 project structure for proper project root detection
        TestHelper::createComposerJson($this->tempDir, TestHelper::getComposerContent('typo3-core'));

        // Set up environment to use temp directory as project root and initialize application
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempDir],
            function (): void {
                $app = new QualityToolsApplication();
                $mockLoader = $this->createMock(ConfigurationLoaderInterface::class);
                $this->command = new EdgeCaseTestToolCommand($mockLoader);
                $this->command->setApplication($app);
            },
        );
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testResolveConfigPathWithEmptyCustomPath(): void
    {
        $projectRoot = $this->tempDir;
        $configDir = $projectRoot . '/vendor/cpsit/quality-tools/config';
        mkdir($configDir, 0o777, true);

        $defaultConfigFile = $configDir . '/test.php';
        file_put_contents($defaultConfigFile, '<?php return [];');

        // Empty string is treated as a file path and will fail - this tests error handling
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Configuration file not found: ');

        $this->command->testResolveConfigPath('test.php', '');
    }

    public function testResolveConfigPathWithSymlinkConfigFile(): void
    {
        // Note: AbstractToolCommand has strict security validation that prevents
        // using .php files for a 'test-tool'. We expect this to fail with a security error.
        $actualConfigFile = $this->tempDir . '/actual-config.php';
        $symlinkConfigFile = $this->tempDir . '/symlink-config.php';

        file_put_contents($actualConfigFile, '<?php return [];');
        symlink($actualConfigFile, $symlinkConfigFile);

        // AbstractToolCommand applies security validation - .php files are not allowed for test-tool
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Security validation failed for custom config file');

        $this->command->testResolveConfigPath('test.php', $symlinkConfigFile);
    }

    public function testResolveConfigPathWithRelativeCustomPath(): void
    {
        // Note: AbstractToolCommand applies strict security validation for tool config files.
        // Since 'test-tool' is not a known tool in ALLOWED_CONFIG_EXTENSIONS,
        // any file extension will fail validation.
        $relativeConfigFile = 'config/test-tool.yml';
        $fullPath = $this->tempDir . '/' . $relativeConfigFile;

        // Create config file in project directory
        mkdir(\dirname($fullPath), 0o777, true);
        file_put_contents($fullPath, 'test: config');

        try {
            // This should fail with security validation error
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Security validation failed for custom config file');

            $this->command->testResolveConfigPath('test.yml', $fullPath);
        } finally {
            // Clean up
            if (file_exists($fullPath)) {
                unlink($fullPath);
                rmdir(\dirname($fullPath));
            }
        }
    }
}

/**
 * Test command for edge case testing.
 */
final class EdgeCaseTestToolCommand extends AbstractToolCommand
{
    public const string TOOL_NAME = 'test-tool';

    public function __construct(ConfigurationLoaderInterface $loader)
    {
        parent::__construct($loader);
    }

    public function getToolName(): string
    {
        return self::TOOL_NAME;
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'test.php';
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        return ['echo', 'test'];
    }

    public function testResolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        return $this->resolveConfigPath($configFile, $customConfigPath);
    }
}
