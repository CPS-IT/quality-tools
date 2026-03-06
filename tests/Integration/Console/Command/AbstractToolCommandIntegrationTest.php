<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Console\Command;

use Cpsit\QualityTools\Configuration\ConfigurationLoaderInterface;
use Cpsit\QualityTools\Console\Command\AbstractToolCommand;
use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Integration tests for AbstractToolCommand with real process execution and file system operations.
 *
 * @covers \Cpsit\QualityTools\Console\Command\AbstractToolCommand
 */
final class AbstractToolCommandIntegrationTest extends TestCase
{
    private string $tempProjectRoot;
    private QualityToolsApplication $application;
    private TestableToolIntegrationCommand $command;

    protected function setUp(): void
    {
        $this->tempProjectRoot = TestHelper::createTempDirectory('tool_integration_test_');
        $this->setupProjectStructure();

        // Set up application with temporary project root
        TestHelper::withEnvironment(
            ['QT_PROJECT_ROOT' => $this->tempProjectRoot],
            function (): void {
                $this->application = new QualityToolsApplication();
            },
        );

        $mockLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $this->command = new TestableToolIntegrationCommand($mockLoader);
        $this->application->add($this->command);
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempProjectRoot);
    }

    private function setupProjectStructure(): void
    {
        // Create TYPO3 project structure
        TestHelper::createComposerJson($this->tempProjectRoot, TestHelper::getComposerContent('typo3-core'));

        // Create quality tools config directory
        $configDir = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config';
        mkdir($configDir, 0o777, true);

        // Create test configuration files
        file_put_contents($configDir . '/test-config.php', '<?php return ["test" => true];');
        file_put_contents($configDir . '/rector.php', '<?php return [];');
        file_put_contents($configDir . '/phpstan.neon', 'parameters: {}');

        // Create additional test directories
        mkdir($this->tempProjectRoot . '/packages', 0o777, true);
        mkdir($this->tempProjectRoot . '/custom-path', 0o777, true);
    }

    public function testConfigurationPathResolutionWithRealFiles(): void
    {
        $customConfigPath = $this->tempProjectRoot . '/custom-config.php';
        file_put_contents($customConfigPath, '<?php return ["custom" => true];');

        $input = new ArrayInput(['--config' => $customConfigPath], $this->command->getDefinition());

        $resolvedPath = $this->command->testResolveConfigPath('default.php', $input->getOption('config'));

        // Use basename comparison to avoid /private/var vs /var path differences on macOS
        $this->assertEquals(basename($customConfigPath), basename($resolvedPath));
        $this->assertEquals(file_get_contents($customConfigPath), file_get_contents($resolvedPath));
        $this->assertFileExists($resolvedPath);
    }

    public function testDefaultConfigurationPathResolution(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());

        $resolvedPath = $this->command->testResolveConfigPath('rector.php');

        $expectedPath = $this->tempProjectRoot . '/vendor/cpsit/quality-tools/config/rector.php';
        $this->assertEquals(realpath($expectedPath), $resolvedPath);
        $this->assertFileExists($resolvedPath);
    }

    public function testCompleteWorkflowWithAllOptions(): void
    {
        // Create a custom config and target path
        $customConfig = $this->tempProjectRoot . '/workflow-config.php';
        $customTarget = $this->tempProjectRoot . '/workflow-target';

        file_put_contents($customConfig, '<?php return ["workflow" => true];');
        mkdir($customTarget, 0o777, true);

        $input = new ArrayInput([
            '--config' => $customConfig,
            '--path' => $customTarget,
        ], $this->command->getDefinition());

        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        // Test that we can resolve the custom config
        $resolvedConfig = $this->command->testResolveConfigPath('default.php', $customConfig);
        // Use basename comparison to avoid /private/var vs /var path differences on macOS
        $this->assertEquals(basename($customConfig), basename($resolvedConfig));
        $this->assertEquals(file_get_contents($customConfig), file_get_contents($resolvedConfig));

        // Test that we can get the target path
        $targetPath = $this->command->testGetTargetPath($input);
        $this->assertEquals(realpath($customTarget), $targetPath);

        // Test process execution with the custom setup
        $exitCode = $this->command->testExecuteProcess(
            ['echo', 'Workflow test'],
            $input,
            $output,
        );

        $this->assertEquals(0, $exitCode);

        $outputContent = $output->fetch();
        $this->assertStringContainsString('Executing:', $outputContent);
        $this->assertStringContainsString('Workflow test', $outputContent);
    }

    /**
     * Test auto-discovery of tool configuration files.
     */
    public function testToolConfigurationAutoDiscovery(): void
    {
        // Create a rector.php file in the project root
        $rectorConfig = $this->tempProjectRoot . '/rector.php';
        file_put_contents($rectorConfig, '<?php return ["custom_rector" => true];');

        // Test that rector command discovers the custom config
        $resolvedPath = $this->command->testResolveConfigPath('rector.php');

        // Should discover the custom rector.php in project root
        $this->assertEquals(realpath($rectorConfig), $resolvedPath);
        $this->assertFileExists($resolvedPath);
    }

    /**
     * Test configuration path resolution with symlinks.
     */
    public function testConfigurationPathResolutionWithSymlinks(): void
    {
        $realConfigPath = $this->tempProjectRoot . '/real-config.php';
        $symlinkPath = $this->tempProjectRoot . '/symlink-config.php';

        file_put_contents($realConfigPath, '<?php return ["symlink_test" => true];');
        symlink($realConfigPath, $symlinkPath);

        $input = new ArrayInput(['--config' => $symlinkPath], $this->command->getDefinition());

        $resolvedPath = $this->command->testResolveConfigPath('default.php', $input->getOption('config'));

        // The resolved path should point to the symlink itself (not the real file)
        // Use basename comparison to avoid /private/var vs /var path differences on macOS
        $this->assertEquals(basename($symlinkPath), basename($resolvedPath));
        $this->assertFileExists($resolvedPath);
    }

    /**
     * Test that non-existent config file throws proper exception.
     */
    public function testNonExistentConfigurationThrowsException(): void
    {
        $nonExistentPath = $this->tempProjectRoot . '/non-existent.php';

        $this->expectException(\Cpsit\QualityTools\Exception\ConfigurationException::class);
        $this->expectExceptionMessage('Configuration file not found');

        $this->command->testResolveConfigPath('default.php', $nonExistentPath);
    }

    /**
     * Test tool-specific memory limit calculation.
     */
    public function testToolSpecificMemoryLimit(): void
    {
        $input = new ArrayInput([], $this->command->getDefinition());
        $output = new BufferedOutput();

        $memoryLimit = $this->command->testGetToolMemoryLimit($input, $output);

        // Should return a memory limit string like "256M" or similar
        $this->assertNotNull($memoryLimit);
        $this->assertMatchesRegularExpression('/^\d+M$/', $memoryLimit);
    }
}

/**
 * Testable implementation of AbstractToolCommand for integration testing.
 */
class TestableToolIntegrationCommand extends AbstractToolCommand
{
    // Use 'rector' as the tool name since it accepts .php config files
    public const string TOOL_NAME = 'rector';

    public function __construct(ConfigurationLoaderInterface $loader)
    {
        parent::__construct($loader);
        $this->setDescription('Test tool integration command for AbstractToolCommand testing');
    }

    public function getToolName(): string
    {
        return self::TOOL_NAME;
    }

    protected function getDefaultConfigFileName(): string
    {
        return 'rector.php';
    }

    protected function buildToolCommand(
        InputInterface $input,
        OutputInterface $output,
        string $configPath,
        array $targetPaths,
    ): array {
        return ['echo', 'Test tool command with config: ' . $configPath];
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setName('test:tool-integration');
        parent::configure(); // This adds the base options
    }

    public function testResolveConfigPath(string $configFile, ?string $customConfigPath = null): string
    {
        return $this->resolveConfigPath($configFile, $customConfigPath);
    }

    public function testGetTargetPath(InputInterface $input): string
    {
        return $this->getTargetPath($input);
    }

    public function testExecuteProcess(
        array $command,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        return $this->executeProcess($command, $input, $output);
    }

    public function testGetToolMemoryLimit(InputInterface $input, OutputInterface $output): ?string
    {
        return $this->getToolMemoryLimit($input, $output);
    }
}
