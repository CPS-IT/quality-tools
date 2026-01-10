<?php

declare(strict_types=1);

namespace Cpsit\QualityTools\Tests\Integration\Configuration;

use Cpsit\QualityTools\Console\QualityToolsApplication;
use Cpsit\QualityTools\DependencyInjection\ServiceContainer;
use Cpsit\QualityTools\Tests\Unit\TestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @covers \Cpsit\QualityTools\Console\Command\ConfigInitCommand
 * @covers \Cpsit\QualityTools\Console\Command\ConfigValidateCommand
 * @covers \Cpsit\QualityTools\Console\Command\ConfigShowCommand
 * @covers \Cpsit\QualityTools\Configuration\YamlConfigurationLoader
 * @covers \Cpsit\QualityTools\Configuration\Configuration
 * @covers \Cpsit\QualityTools\Configuration\ConfigurationValidator
 */
final class YamlConfigurationWorkflowTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        // Reset the service container to ensure clean state for each test
        ServiceContainer::reset();

        $this->tempDir = TestHelper::createTempDirectory('yaml_workflow_test_');

        // Create a basic project structure
        TestHelper::createComposerJson($this->tempDir, [
            'name' => 'integration/test-project',
            'type' => 'project',
            'require' => ['typo3/cms-core' => '^13.4'],
        ]);

        // Ensure no environment pollution from previous tests
        $envVariablesToClean = [
            'PROJECT_NAME',
            'PHP_VERSION',
            'TYPO3_VERSION',
            'MEMORY_LIMIT',
            'PHPSTAN_LEVEL',
        ];

        foreach ($envVariablesToClean as $envVar) {
            if (getenv($envVar) !== false) {
                putenv($envVar);
            }
        }

        // Note: We create ApplicationTester instances per test method to avoid state leakage
    }

    protected function tearDown(): void
    {
        TestHelper::removeDirectory($this->tempDir);
    }

    private function createAppTester(array $additionalEnv = []): ApplicationTester
    {
        $env = ['QT_PROJECT_ROOT' => $this->tempDir] + $additionalEnv;

        // Also set $_SERVER and $_ENV variables for SecurityService compatibility
        foreach ($env as $key => $value) {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
        }

        return TestHelper::withEnvironment(
            $env,
            function (): ApplicationTester {
                $app = new QualityToolsApplication();
                $app->setAutoExit(false);

                return new ApplicationTester($app);
            },
        );
    }

    public function testCompleteYamlWorkflow(): void
    {
        $appTester = $this->createAppTester();

        // Step 1: Initialize configuration with default template
        $exitCode = $appTester->run(['command' => 'config:init']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Created configuration file', $output);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        self::assertFileExists($configFile);

        // Step 2: Validate the created configuration
        $appTester->run(['command' => 'config:validate']);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Configuration is valid', $output);

        // Step 3: Show the configuration
        $appTester->run(['command' => 'config:show']);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Resolved Configuration', $output);
        self::assertStringContainsString('integration/test-project', $output);
        self::assertStringContainsString('php_version: \'8.3\'', $output);

        // Step 4: Show configuration in JSON format
        $appTester->run(['command' => 'config:show', '--format' => 'json']);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        $output = $appTester->getDisplay();

        // Extract JSON from output
        $lines = explode("\n", $output);
        $jsonStart = false;
        $jsonOutput = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '{')) {
                $jsonStart = true;
            }
            if ($jsonStart) {
                $jsonOutput .= $line . "\n";
            }
        }

        $config = json_decode(trim($jsonOutput), true);
        self::assertNotNull($config);
        self::assertSame('integration/test-project', $config['quality-tools']['project']['name']);
    }

    public function testWorkflowWithCustomTemplate(): void
    {
        $appTester = $this->createAppTester();

        // Initialize with extension template
        $exitCode = $appTester->run([
            'command' => 'config:init',
            '--template' => 'typo3-extension',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $content = file_get_contents($configFile);

        // Verify extension-specific configuration
        self::assertStringContainsString('Classes/', $content);
        self::assertStringContainsString('Configuration/', $content);
        self::assertStringContainsString('level: 8', $content); // Higher PHPStan level for extensions

        // Validate the extension configuration
        $appTester->run(['command' => 'config:validate']);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Configuration is valid', $output);
    }

    public function testWorkflowWithEnvironmentVariables(): void
    {
        // Create configuration with environment variables
        $configContent = <<<YAML
            quality-tools:
              project:
                name: "\${PROJECT_NAME:-fallback-name}"
                php_version: "\${PHP_VERSION:-8.3}"
                typo3_version: "\${TYPO3_VERSION:-13.4}"
              tools:
                phpstan:
                  memory_limit: "\${MEMORY_LIMIT:-1G}"
                  level: \${PHPSTAN_LEVEL:-6}
            YAML;

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($configFile, $configContent);

        // Test with environment variables set
        $appTester = $this->createAppTester([
            'PROJECT_NAME' => 'env-test-project',
            'PHP_VERSION' => '8.4',
            'MEMORY_LIMIT' => '2G',
            'PHPSTAN_LEVEL' => '8',
        ]);

        // Validate configuration
        $appTester->run(['command' => 'config:validate']);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        // Show configuration to verify interpolation
        $appTester->run(['command' => 'config:show', '--format' => 'json']);

        $output = $appTester->getDisplay();

        // Extract and verify JSON
        $lines = explode("\n", $output);
        $jsonStart = false;
        $jsonOutput = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '{')) {
                $jsonStart = true;
            }
            if ($jsonStart) {
                $jsonOutput .= $line . "\n";
            }
        }

        $config = json_decode(trim($jsonOutput), true);

        self::assertSame('env-test-project', $config['quality-tools']['project']['name']);
        self::assertSame('8.4', $config['quality-tools']['project']['php_version']);
        self::assertSame('13.4', $config['quality-tools']['project']['typo3_version']); // default
        self::assertSame('2G', $config['quality-tools']['tools']['phpstan']['memory_limit']);
        self::assertSame(8, $config['quality-tools']['tools']['phpstan']['level']);
    }

    public function testWorkflowWithInvalidConfiguration(): void
    {
        $appTester = $this->createAppTester();

        // Create invalid configuration
        $invalidConfig = <<<YAML
            quality-tools:
              project:
                php_version: "invalid-version"
              tools:
                phpstan:
                  level: 15
            YAML;

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        file_put_contents($configFile, $invalidConfig);

        // Validation should fail
        $appTester->run(['command' => 'config:validate']);

        self::assertSame(Command::FAILURE, $appTester->getStatusCode());

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Unexpected Error:', $output);

        // Show command should also fail
        $appTester->run(['command' => 'config:show']);

        self::assertSame(Command::FAILURE, $appTester->getStatusCode());

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Failed to load configuration', $output);
    }

    public function testWorkflowWithForceOverwrite(): void
    {
        $appTester = $this->createAppTester();

        // Create initial configuration
        $appTester->run(['command' => 'config:init']);

        $configFile = $this->tempDir . '/.quality-tools.yaml';
        $originalContent = file_get_contents($configFile);

        // Try to init again without force
        $appTester->run(['command' => 'config:init']);

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Configuration file already exists', $output);

        // Content should be unchanged
        self::assertSame($originalContent, file_get_contents($configFile));

        // Init with force should overwrite
        $appTester->run([
            'command' => 'config:init',
            '--template' => 'typo3-extension',
            '--force' => true,
        ]);

        $newContent = file_get_contents($configFile);
        self::assertNotSame($originalContent, $newContent);
        self::assertStringContainsString('Classes/', $newContent);
    }

    public function testWorkflowWithVerboseOutput(): void
    {
        $appTester = $this->createAppTester();

        // Initialize configuration
        $appTester->run(['command' => 'config:init']);

        // Validate with verbose output
        $appTester->run(['command' => 'config:validate', '--verbose' => true]);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Configuration Summary', $output);
        self::assertStringContainsString('integration/test-project', $output);
        self::assertStringContainsString('rector', $output);
        self::assertStringContainsString('Scan Paths:', $output);
        self::assertStringContainsString('packages/', $output);

        // Show with verbose output (shows configuration sources)
        $appTester->run(['command' => 'config:show', '--verbose' => true]);

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Configuration Sources', $output);
        self::assertStringContainsString('Project:', $output);
        self::assertStringContainsString('.quality-tools.yaml', $output);
        self::assertStringContainsString('Package defaults', $output);
    }

    public function testWorkflowWithConfigurationHierarchy(): void
    {
        $homeDir = $this->tempDir . '/home';
        mkdir($homeDir, 0o777, true);

        // Create global configuration
        $globalConfig = <<<YAML
            quality-tools:
              project:
                php_version: "8.4"
              tools:
                rector:
                  level: "typo3-12"
                phpstan:
                  memory_limit: "2G"
              output:
                colors: false
                verbosity: "verbose"
            YAML;
        file_put_contents($homeDir . '/.quality-tools.yaml', $globalConfig);

        // Create project configuration that overrides some settings
        $projectConfig = <<<YAML
            quality-tools:
              project:
                name: "hierarchy-test"
                typo3_version: "13.4"
              tools:
                rector:
                  level: "typo3-13"
                fractor:
                  indentation: 4
              output:
                colors: true
            YAML;
        file_put_contents($this->tempDir . '/.quality-tools.yaml', $projectConfig);

        // Verify both configuration files exist
        self::assertFileExists($homeDir . '/.quality-tools.yaml');
        self::assertFileExists($this->tempDir . '/.quality-tools.yaml');

        // Set HOME environment for global config detection
        $originalHome = getenv('HOME');
        $originalServerHome = $_SERVER['HOME'] ?? null;
        putenv('HOME=' . $homeDir);
        $_SERVER['HOME'] = $homeDir;

        $appTester = $this->createAppTester();

        // Validate merged configuration
        $appTester->run(['command' => 'config:validate']);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        // Show configuration to verify merging
        $appTester->run(['command' => 'config:show', '--format' => 'json', '--verbose' => true]);

        $output = $appTester->getDisplay();

        // Should show both configuration sources
        self::assertStringContainsString('Global:', $output);
        self::assertStringContainsString('Project:', $output);

        // Extract and verify merged configuration
        $lines = explode("\n", $output);
        $jsonStart = false;
        $jsonOutput = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '{')) {
                $jsonStart = true;
            }
            if ($jsonStart) {
                $jsonOutput .= $line . "\n";
            }
        }

        $config = json_decode(trim($jsonOutput), true);

        // Verify merged values
        self::assertSame('hierarchy-test', $config['quality-tools']['project']['name']); // project
        self::assertSame('8.4', $config['quality-tools']['project']['php_version']); // global
        self::assertSame('13.4', $config['quality-tools']['project']['typo3_version']); // project

        // Tool settings should be merged
        self::assertSame('typo3-13', $config['quality-tools']['tools']['rector']['level']); // project override
        self::assertSame(4, $config['quality-tools']['tools']['fractor']['indentation']); // project only
        self::assertSame('2G', $config['quality-tools']['tools']['phpstan']['memory_limit']); // global

        // Output settings should be merged
        self::assertTrue($config['quality-tools']['output']['colors']); // project override
        self::assertSame('verbose', $config['quality-tools']['output']['verbosity']); // global

        // Restore environment variables
        if ($originalHome !== false) {
            putenv('HOME=' . $originalHome);
        } else {
            putenv('HOME');
        }
        if ($originalServerHome !== null) {
            $_SERVER['HOME'] = $originalServerHome;
        } else {
            unset($_SERVER['HOME']);
        }
    }

    public function testWorkflowWithMissingConfigurationFile(): void
    {
        $appTester = $this->createAppTester();

        // Try to validate without any configuration file
        $appTester->run(['command' => 'config:validate']);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        $output = $appTester->getDisplay();
        self::assertStringContainsString('No YAML configuration file found', $output);
        self::assertStringContainsString('qt config:init', $output);

        // Show command should work with defaults
        $appTester->run(['command' => 'config:show']);

        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

        $output = $appTester->getDisplay();
        self::assertStringContainsString('Resolved Configuration', $output);
        self::assertStringContainsString('php_version: \'8.3\'', $output); // defaults
    }

    public function testWorkflowWithDifferentConfigurationFileNames(): void
    {
        $configFiles = [
            '.quality-tools.yaml',
            'quality-tools.yaml',
            'quality-tools.yml',
        ];

        foreach ($configFiles as $fileName) {
            $testDir = TestHelper::createTempDirectory('config_names_test_');
            TestHelper::createComposerJson($testDir, ['name' => 'test/project']);

            $config = <<<YAML
                quality-tools:
                  project:
                    name: "test-{$fileName}"
                YAML;
            file_put_contents($testDir . '/' . $fileName, $config);

            TestHelper::withEnvironment(['QT_PROJECT_ROOT' => $testDir], function () use ($fileName): void {
                $app = new QualityToolsApplication();
                $app->setAutoExit(false);
                $appTester = new ApplicationTester($app);

                // Validate should find the file
                $appTester->run(['command' => 'config:validate']);

                self::assertSame(Command::SUCCESS, $appTester->getStatusCode());

                $output = $appTester->getDisplay();
                self::assertStringContainsString($fileName, $output);
                self::assertStringContainsString('Configuration is valid', $output);
            });

            TestHelper::removeDirectory($testDir);
        }
    }

    public function testWorkflowEndToEndPerformance(): void
    {
        $appTester = $this->createAppTester();
        $startTime = microtime(true);

        // Complete workflow should be reasonably fast
        $appTester->run(['command' => 'config:init']);
        $appTester->run(['command' => 'config:validate']);
        $appTester->run(['command' => 'config:show']);
        $appTester->run(['command' => 'config:show', '--format' => 'json']);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete in under 5 seconds
        self::assertLessThan(5.0, $executionTime, 'Complete YAML workflow should be reasonably fast');

        // All commands should have succeeded
        self::assertSame(Command::SUCCESS, $appTester->getStatusCode());
    }
}
